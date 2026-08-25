<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Personalized health-insight data access.
 *
 * Stores insight_type, generated content, data_version and lifecycle status
 * (active / dismissed / expired, read/unread) per student. All writes are
 * scoped by the owning student id so a caller can never touch another
 * student's insight (IDOR/BOLA defence at the data layer).
 */
final class HealthInsightRepository extends BaseRepository
{
    /**
     * Active insights for a student, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(int $studentId): array
    {
        $stmt = $this->prepare(
            'SELECT id, student_id, insight_type, title, content, data_version,
                    status, is_read, read_at, created_at
               FROM health_insights
              WHERE student_id = :sid
                AND status = :status
              ORDER BY created_at DESC, id DESC',
            [':sid' => $studentId, ':status' => 'active']
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Full insight history for a student (any status), for staff review.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyForStudent(int $studentId, int $limit = 50): array
    {
        $stmt = $this->prepare(
            'SELECT id, student_id, insight_type, title, content, data_version,
                    status, is_read, read_at, created_at
               FROM health_insights
              WHERE student_id = :sid
              ORDER BY created_at DESC, id DESC
              LIMIT :limit',
            [':sid' => $studentId, ':limit' => $limit]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Look up a single insight (used for ownership checks).
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $insightId): ?array
    {
        $stmt = $this->prepare(
            'SELECT id, student_id, insight_type, title, content, data_version,
                    status, is_read, read_at, created_at
               FROM health_insights
              WHERE id = :id
              LIMIT 1',
            [':id' => $insightId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Whether the student already has an active insight of a given type
     * (de-duplication guard so regeneration does not pile up duplicates).
     */
    public function hasActiveOfType(int $studentId, string $insightType): bool
    {
        $stmt = $this->prepare(
            'SELECT COUNT(*) FROM health_insights
              WHERE student_id = :sid
                AND insight_type = :type
                AND status = :status',
            [':sid' => $studentId, ':type' => $insightType, ':status' => 'active']
        );
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Create an insight. Returns the new insight id.
     */
    public function create(int $studentId, string $insightType, string $title, string $content, ?string $dataVersion = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO health_insights
               (student_id, insight_type, title, content, data_version, status, is_read)
             VALUES (:sid, :type, :title, :content, :data_version, :status, 0)'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':type', mb_substr($insightType, 0, 80), PDO::PARAM_STR);
        $stmt->bindValue(':title', mb_substr($title, 0, 150), PDO::PARAM_STR);
        $stmt->bindValue(':content', $content, PDO::PARAM_STR);
        $stmt->bindValue(':data_version', $dataVersion !== null ? mb_substr($dataVersion, 0, 50) : null, PDO::PARAM_STR);
        $stmt->bindValue(':status', 'active', PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Mark an insight read (ownership-scoped).
     */
    public function markRead(int $insightId, int $studentId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE health_insights
                SET is_read = 1, read_at = NOW()
              WHERE id = :id AND student_id = :sid AND is_read = 0'
        );
        $stmt->bindValue(':id', $insightId, PDO::PARAM_INT);
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Dismiss an active insight (ownership-scoped; only active insights).
     */
    public function dismiss(int $insightId, int $studentId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE health_insights
                SET status = :status
              WHERE id = :id AND student_id = :sid AND status = :current'
        );
        $stmt->bindValue(':status', 'dismissed', PDO::PARAM_STR);
        $stmt->bindValue(':id', $insightId, PDO::PARAM_INT);
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':current', 'active', PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}
