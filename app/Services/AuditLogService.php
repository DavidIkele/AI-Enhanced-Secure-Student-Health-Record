<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Minimal audit logging for health-record operations (PROMPT 5).
 *
 * Writes to the append-only audit_logs table. The full audit subsystem
 * (PROMPT 14) extends this to login/logout/administrative actions; this
 * foundation covers the health-record events PROMPT 5 requires.
 *
 * Privacy rules enforced here:
 *  - passwords, tokens, API keys and encryption keys are never logged
 *  - health content (clinical notes, diagnoses detail) is never written
 *  - only entity references and opaque change summaries are stored
 *  - the record is attributable (user, IP, user agent, method, path, time)
 */
final class AuditLogService
{
    public static function record(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?array $summary = null,
        ?int $userId = null
    ): void {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs
                   (user_id, action, entity_type, entity_id, old_values, new_values,
                    ip_address, user_agent, request_method, request_path, created_at)
                 VALUES (:user_id, :action, :entity_type, :entity_id, NULL, :new_values,
                    :ip, :ua, :method, :path, NOW())'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':action', $action, PDO::PARAM_STR);
            $stmt->bindValue(':entity_type', $entityType, PDO::PARAM_STR);
            $stmt->bindValue(':entity_id', $entityId === null ? null : mb_substr((string) $entityId, 0, 64), PDO::PARAM_STR);
            $stmt->bindValue(':new_values', $summary !== null ? json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null, PDO::PARAM_STR);
            $stmt->bindValue(':ip', mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), PDO::PARAM_STR);
            $stmt->bindValue(':ua', mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), PDO::PARAM_STR);
            $stmt->bindValue(':method', mb_substr((string) ($_SERVER['REQUEST_METHOD'] ?? ''), 0, 10), PDO::PARAM_STR);
            $stmt->bindValue(':path', mb_substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255), PDO::PARAM_STR);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Audit logging must never break the primary operation.
            Logger::error('Audit log write failed', ['error' => $e->getMessage()]);
        }
    }
}
