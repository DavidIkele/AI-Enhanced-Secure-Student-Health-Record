<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Persistence for staff-entered symptom assessments (decision support).
 *
 * Stores the staff-authored symptom description (record data, like a chief
 * complaint) plus the validated suggestion list returned by the AI service.
 * The result is always decision-support only and never a diagnosis.
 */
final class SymptomAssessmentRepository extends BaseRepository
{
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';

    /**
     * Create a new assessment; returns the new id.
     *
     * @param array<string, mixed> $result validated assessment payload
     * @param array<int, string>|null $matchedSymptomTerms
     */
    public function create(
        int $studentId,
        string $symptomsText,
        ?array $matchedSymptomTerms,
        array $result,
        int $createdBy
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO symptom_assessments
               (student_id, symptoms_text, matched_symptoms, result,
                model_version, status, created_by)
             VALUES (:sid, :text, :matched, :result, :version, :status, :created_by)'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':text', mb_substr($symptomsText, 0, 2000), PDO::PARAM_STR);
        $stmt->bindValue(
            ':matched',
            json_encode($matchedSymptomTerms ?? [], JSON_UNESCAPED_SLASHES),
            PDO::PARAM_STR
        );
        $stmt->bindValue(':result', json_encode($result, JSON_UNESCAPED_SLASHES), PDO::PARAM_STR);
        $stmt->bindValue(':version', $result['model_version'] ?? 'unknown', PDO::PARAM_STR);
        $stmt->bindValue(':status', self::STATUS_DELIVERED, PDO::PARAM_STR);
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Record a failed assessment (service unavailable etc.).
     */
    public function createFailed(int $studentId, string $symptomsText, string $reason, int $createdBy): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO symptom_assessments
               (student_id, symptoms_text, matched_symptoms, result,
                model_version, status, explanation, created_by)
             VALUES (:sid, :text, :matched, :result, :version, :status, :explanation, :created_by)'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':text', mb_substr($symptomsText, 0, 2000), PDO::PARAM_STR);
        $stmt->bindValue(':matched', '[]', PDO::PARAM_STR);
        $stmt->bindValue(':result', '{}', PDO::PARAM_STR);
        $stmt->bindValue(':version', 'unknown', PDO::PARAM_STR);
        $stmt->bindValue(':status', self::STATUS_FAILED, PDO::PARAM_STR);
        $stmt->bindValue(':explanation', mb_substr($reason, 0, 255), PDO::PARAM_STR);
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Most recent assessments for a student (newest first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function latestForStudent(int $studentId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, student_id, symptoms_text, matched_symptoms, result,
                    model_version, status, explanation, created_by, created_at
               FROM symptom_assessments
              WHERE student_id = :sid
              ORDER BY created_at DESC, id DESC
              LIMIT :limit'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
