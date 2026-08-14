<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Authorized health-alert data access (PROMPT 13).
 *
 * Health alerts are authored by authorized clinic staff (alerts.manage) and
 * recorded here as an auditable alert entry. Delivery to the student's
 * notification inbox is handled by NotificationRepository. Alert content uses
 * fixed privacy-safe templates (never raw clinical text) so a notification
 * preview can never expose unnecessary health information.
 */
final class HealthAlertRepository extends BaseRepository
{
    public const TITLE_MAX = 150;

    /**
     * Record an authorized health alert for a student.
     *
     * @param array<string, mixed>|null $metadata
     * @return int new health_alert id
     */
    public function create(
        int $studentId,
        string $alertType,
        string $severity,
        string $title,
        string $message,
        ?array $metadata = null,
        ?int $createdBy = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO health_alerts
               (student_id, alert_type, severity, title, message, metadata, is_resolved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, NOW())'
        );
        $stmt->bindValue(1, $studentId, PDO::PARAM_INT);
        $stmt->bindValue(2, mb_substr($alertType, 0, 10), PDO::PARAM_STR);
        $stmt->bindValue(3, mb_substr($severity, 0, 10), PDO::PARAM_STR);
        $stmt->bindValue(4, mb_substr($title, 0, self::TITLE_MAX), PDO::PARAM_STR);
        $stmt->bindValue(5, $message, PDO::PARAM_STR);
        $stmt->bindValue(6, $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Alerts for a student, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(int $studentId): array
    {
        return $this->prepare(
            'SELECT id, student_id, alert_type, severity, title, message,
                    metadata, is_resolved, resolved_by, resolved_at, created_at
               FROM health_alerts
              WHERE student_id = :sid
              ORDER BY created_at DESC, id DESC',
            [':sid' => $studentId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
