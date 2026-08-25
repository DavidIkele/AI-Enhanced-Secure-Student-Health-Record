<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\HealthInsightRepository;
use App\Repositories\HealthRecordRepository;
use App\Repositories\StudentRepository;
use App\Security\AccessControl;
use App\Security\Security;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\InsightEngine;

/**
 * Personalized health insights.
 *
 * Insights are informational and non-diagnostic (see InsightEngine). They are
 * generated from a student's own recorded clinical data by staff (records.manage)
 * and consumed by the student on their profile. The profile routes verify that
 * the insight belongs to the authenticated student before any mutation, so an
 * insight id from another student is rejected (IDOR/BOLA defence).
 */
final class InsightController extends BaseController
{
    /**
     * (Re)generate personalized insights for a student (staff action).
     */
    public function generate(int $studentId): void
    {
        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'records.manage')) {
            $this->abort(403, 'You do not have permission to generate insights.');
        }

        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/records/' . $studentId);
            return;
        }

        $student = (new StudentRepository())->findById($studentId);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $records = new HealthRecordRepository();
        $record = $records->activeProfileForStudent($studentId);
        $histories = $records->medicalHistoriesForStudent($studentId);
        $visits = $records->clinicVisitsForStudent($studentId);

        $repo = new HealthInsightRepository();
        $created = [];
        foreach (InsightEngine::generateAll($record, $histories, $visits) as $insight) {
            // De-duplication: do not stack a second active insight of the same
            // type on a regeneration run.
            if ($repo->hasActiveOfType($studentId, $insight['insight_type'])) {
                continue;
            }
            $id = $repo->create(
                $studentId,
                $insight['insight_type'],
                $insight['title'],
                $insight['content'],
                $insight['data_version']
            );
            $created[] = $insight['insight_type'];
            AuditLogService::record(
                'create',
                'health_insight',
                (string) $id,
                ['student_id' => $studentId, 'insight_type' => $insight['insight_type'], 'data_version' => $insight['data_version']],
                $userId
            );
        }

        \App\Core\Session::flash(
            $created === [] ? 'info' : 'success',
            $created === []
                ? 'Insights are up to date — no new insights to add.'
                : 'Generated ' . count($created) . ' new personalized insight(s).'
        );
        $this->redirect('/records/' . $studentId);
    }

    /**
     * Mark one of the student's own insights as read.
     */
    public function markRead(int $insightId): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/profile');
            return;
        }

        $student = $this->ownerStudent();
        if ($student === null) {
            $this->abort(403, 'You are not allowed to modify this insight.');
        }

        $repo = new HealthInsightRepository();
        $insight = $repo->findById($insightId);
        if ($insight === null) {
            $this->abort(404, 'Insight not found.');
        }
        if ((int) $insight['student_id'] !== (int) $student['id']) {
            $this->abort(403, 'You are not allowed to modify this insight.');
        }

        $repo->markRead($insightId, (int) $student['id']);
        \App\Core\Session::flash('success', 'Insight marked as read.');
        $this->redirect('/profile');
    }

    /**
     * Dismiss one of the student's own insights.
     */
    public function dismiss(int $insightId): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/profile');
            return;
        }

        $student = $this->ownerStudent();
        if ($student === null) {
            $this->abort(403, 'You are not allowed to modify this insight.');
        }

        $repo = new HealthInsightRepository();
        $insight = $repo->findById($insightId);
        if ($insight === null) {
            $this->abort(404, 'Insight not found.');
        }
        if ((int) $insight['student_id'] !== (int) $student['id']) {
            $this->abort(403, 'You are not allowed to modify this insight.');
        }

        if ($repo->dismiss($insightId, (int) $student['id'])) {
            $auth = new AuthService();
            AuditLogService::record(
                'dismiss',
                'health_insight',
                (string) $insightId,
                ['student_id' => (int) $student['id'], 'insight_type' => (string) $insight['insight_type']],
                $auth->id()
            );
            \App\Core\Session::flash('success', 'Insight dismissed.');
        } else {
            \App\Core\Session::flash('info', 'Insight is no longer active.');
        }
        $this->redirect('/profile');
    }

    /**
     * The student row linked to the authenticated session user (ownership
     * source for student-facing insight mutations). Null when the session user
     * has no student row.
     *
     * @return array<string, mixed>|null
     */
    private function ownerStudent(): ?array
    {
        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null) {
            return null;
        }
        return (new StudentRepository())->findByUserId($userId);
    }
}
