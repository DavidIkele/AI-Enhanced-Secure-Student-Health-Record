<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Persistence for AI decision-support predictions.
 *
 * Only de-identified aggregate data is stored: the numeric feature snapshot
 * (the same vector sent to the service) plus the risk output and the model
 * version that produced it. No raw clinical text is written here.
 */
final class AiPredictionRepository extends BaseRepository
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';

    /**
     * Create a pending prediction record; returns the new id.
     *
     * @param array<string, float> $features de-identified feature vector
     */
    public function createPending(
        int $studentId,
        string $predictionType,
        array $features,
        int $requestedBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO ai_predictions
               (student_id, prediction_type, model_version, features_snapshot,
                status, requested_by)
             VALUES (:sid, :ptype, :version, :snapshot, :status, :requested_by)'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':ptype', $predictionType, PDO::PARAM_STR);
        $stmt->bindValue(':version', 'unknown', PDO::PARAM_STR);
        $stmt->bindValue(':snapshot', json_encode($features, JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $stmt->bindValue(':status', self::STATUS_PENDING, PDO::PARAM_STR);
        $stmt->bindValue(':requested_by', $requestedBy, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Mark a prediction delivered with the validated service output.
     *
     * @param array<string, mixed> $result validated prediction result
     */
    public function markDelivered(int $predictionId, array $result): void
    {
        $stmt = $this->db->prepare(
            'UPDATE ai_predictions
                SET risk_level = :risk_level,
                    risk_score = :risk_score,
                    confidence = :confidence,
                    model_version = :version,
                    explanation = :explanation,
                    status = :status
              WHERE id = :id'
        );
        $stmt->bindValue(':risk_level', $result['risk_level'], PDO::PARAM_STR);
        $stmt->bindValue(':risk_score', $result['risk_score'], PDO::PARAM_STR);
        $stmt->bindValue(':confidence', $result['confidence'], PDO::PARAM_STR);
        $stmt->bindValue(':version', $result['model_version'], PDO::PARAM_STR);
        $stmt->bindValue(':explanation', $this->explanationFor($result), PDO::PARAM_STR);
        $stmt->bindValue(':status', self::STATUS_DELIVERED, PDO::PARAM_STR);
        $stmt->bindValue(':id', $predictionId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Mark a pending prediction as failed. Explanations are kept safe/short.
     */
    public function markFailed(int $predictionId, string $reason): void
    {
        $stmt = $this->db->prepare(
            'UPDATE ai_predictions
                SET status = :status,
                    explanation = :reason
              WHERE id = :id
                AND status = :pending'
        );
        $stmt->bindValue(':status', self::STATUS_FAILED, PDO::PARAM_STR);
        $stmt->bindValue(':reason', mb_substr($reason, 0, 255), PDO::PARAM_STR);
        $stmt->bindValue(':id', $predictionId, PDO::PARAM_INT);
        $stmt->bindValue(':pending', self::STATUS_PENDING, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Most recent predictions for a student (newest first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function latestForStudent(int $studentId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, student_id, prediction_type, risk_level, risk_score,
                    confidence, model_version, features_snapshot, explanation,
                    status, requested_by, created_at
               FROM ai_predictions
              WHERE student_id = :sid
              ORDER BY created_at DESC, id DESC
              LIMIT :limit'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Latest delivered prediction of a given type for a student, if any.
     *
     * @return array<string, mixed>|null
     */
    public function latestDelivered(int $studentId, string $predictionType): ?array
    {
        $stmt = $this->prepare(
            'SELECT id, student_id, prediction_type, risk_level, risk_score,
                    confidence, model_version, features_snapshot, explanation,
                    status, requested_by, created_at
               FROM ai_predictions
              WHERE student_id = :sid
                AND prediction_type = :ptype
                AND status = :status
              ORDER BY created_at DESC, id DESC
              LIMIT 1',
            [
                ':sid' => $studentId,
                ':ptype' => $predictionType,
                ':status' => self::STATUS_DELIVERED,
            ]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Human-readable, safe explanation for a delivered prediction. Never
     * echoes raw service text — only the validated categorical output.
     *
     * @param array<string, mixed> $result
     */
    private function explanationFor(array $result): string
    {
        $labels = [
            'low' => 'Low risk: no immediate follow-up indicated.',
            'moderate' => 'Moderate risk: review by a clinician is recommended.',
            'high' => 'High risk: prompt clinical review is advised.',
        ];
        return $labels[$result['risk_level']] ?? 'Risk assessment completed.';
    }
}
