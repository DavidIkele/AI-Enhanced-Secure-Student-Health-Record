<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AiPredictionRepository;
use App\Repositories\StudentRepository;
use App\Repositories\SymptomAssessmentRepository;
use App\Security\AccessControl;
use App\Security\Security;
use App\Services\AiClient;
use App\Services\AiServiceException;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\Logger;
use App\Services\PredictiveFeatures;

/**
 * Secure PHP-to-FastAPI decision-support integration (PROMPT 12).
 *
 * Design rules:
 *   - The browser NEVER talks to the FastAPI service. Only this controller
 *     (server-to-server, PHP) sends requests, authenticated by the shared
 *     X-API-Key header.
 *   - Only the de-identified numeric feature vector leaves the application.
 *   - Failures degrade gracefully: a prediction request that fails because the
 *     service is unavailable/timeouts/returns garbage NEVER surfaces an
 *     internal error to the user — the pending record is marked failed and a
 *     safe message is flashed.
 *   - Every request is CSRF-protected, permission-checked (records.manage),
 *     and audited.
 */
final class AiController extends BaseController
{
    public const PREDICTION_TYPES = AiClient::PREDICTION_TYPES;

    /**
     * Run a decision-support prediction for a student (staff action).
     */
    public function predict(int $studentId, string $type): void
    {
        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'records.manage')) {
            $this->abort(403, 'You do not have permission to run predictions.');
        }

        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/records/' . $studentId);
            return;
        }

        if (!in_array($type, self::PREDICTION_TYPES, true)) {
            $this->abort(404, 'Unknown prediction type.');
        }

        $student = (new StudentRepository())->findById($studentId);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $features = (new PredictiveFeatures())->build($studentId, $type);

        $repo = new AiPredictionRepository();
        $predictionId = $repo->createPending($studentId, $type, $features, $userId);

        try {
            // student_ref is opaque (never the reg number or name) and used by
            // the service only for audit/de-duplication correlation.
            $result = AiClient::predict($type, $features, 'student-' . $studentId);
            $repo->markDelivered($predictionId, $result);

            AuditLogService::record(
                'create',
                'ai_prediction',
                (string) $predictionId,
                [
                    'student_id' => $studentId,
                    'prediction_type' => $type,
                    'model_version' => $result['model_version'],
                    'risk_level' => $result['risk_level'],
                ],
                $userId
            );

            \App\Core\Session::flash(
                'success',
                'Decision-support assessment completed (' . $type . ': ' . $result['risk_level'] . ' risk).'
            );
        } catch (AiServiceException $e) {
            $repo->markFailed($predictionId, $e->getMessage());
            AuditLogService::record(
                'create',
                'ai_prediction',
                (string) $predictionId,
                ['student_id' => $studentId, 'prediction_type' => $type, 'status' => 'failed'],
                $userId
            );
            Logger::warning('ai_prediction_failed', [
                'prediction_id' => $predictionId,
                'type' => $type,
                'category' => $e->category(),
            ]);
            \App\Core\Session::flash('warning', $e->userMessage());
        }

        $this->redirect('/records/' . $studentId);
    }

    /**
     * Symptom assessment (staff action): the doctor/nurse enters the symptoms a
     * student described; the AI service suggests possible conditions. Results
     * are decision-support only — never a diagnosis — and are stored for later
     * review in the student's record.
     */
    public function assessSymptoms(int $studentId): void
    {
        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'records.manage')) {
            $this->abort(403, 'You do not have permission to run a symptom assessment.');
        }

        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/records/' . $studentId);
            return;
        }

        $student = (new StudentRepository())->findById($studentId);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $symptomsText = trim((string) $this->request->input('symptoms', ''));
        if ($symptomsText === '') {
            \App\Core\Session::flash('danger', 'Enter the symptoms the student described before running the assessment.');
            $this->redirect('/records/' . $studentId);
            return;
        }
        if (mb_strlen($symptomsText) > 2000) {
            \App\Core\Session::flash('danger', 'Symptom description is too long (max 2000 characters).');
            $this->redirect('/records/' . $studentId);
            return;
        }

        $repo = new SymptomAssessmentRepository();

        try {
            // student_ref stays opaque and non-PII, exactly like predictions.
            $result = AiClient::assessSymptoms($symptomsText, 'student-' . $studentId);
            $assessmentId = $repo->create(
                $studentId,
                $symptomsText,
                $result['matched_symptoms'],
                $result,
                $userId
            );

            AuditLogService::record(
                'create',
                'symptom_assessment',
                (string) $assessmentId,
                [
                    'student_id' => $studentId,
                    'model_version' => $result['model_version'],
                    'conditions' => count($result['conditions']),
                ],
                $userId
            );

            if ($result['conditions'] === []) {
                \App\Core\Session::flash(
                    'warning',
                    'The symptoms entered did not match any known condition profile. Review the record and clinical judgment remains the guide.'
                );
            } else {
                $top = $result['conditions'][0];
                \App\Core\Session::flash(
                    'success',
                    'Symptom assessment completed. Top suggestion: ' . $top['condition']
                        . ' (' . $top['level'] . ' match). Review the details below — this is decision support, not a diagnosis.'
                );
            }
        } catch (AiServiceException $e) {
            $repo->createFailed($studentId, $symptomsText, $e->getMessage(), $userId);
            AuditLogService::record(
                'create',
                'symptom_assessment',
                null,
                ['student_id' => $studentId, 'status' => 'failed'],
                $userId
            );
            Logger::warning('symptom_assessment_failed', [
                'student_id' => $studentId,
                'category' => $e->category(),
            ]);
            \App\Core\Session::flash('warning', $e->userMessage());
        }

        $this->redirect('/records/' . $studentId);
    }

    /**
     * Health check for the AI decision-support service (operational view).
     */
    public function health(): void
    {
        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'analytics.view')) {
            $this->abort(403, 'You do not have permission to view the service status.');
        }

        $enabled = AiClient::enabled();
        $available = false;
        $detail = null;
        if ($enabled) {
            try {
                $health = AiClient::health();
                $available = ($health['status'] ?? null) === 'ok';
                $detail = [
                    'status' => $health['status'] ?? null,
                    'service' => $health['service'] ?? null,
                    'version' => $health['version'] ?? null,
                ];
            } catch (AiServiceException $e) {
                $detail = ['error' => $e->category()];
            }
        }

        $this->renderJson([
            'success' => true,
            'enabled' => $enabled,
            'available' => $available,
            // Intentionally NOT exposing the internal AI service URL/topology.
            'models' => self::PREDICTION_TYPES,
            'detail' => $detail,
        ]);
    }
}
