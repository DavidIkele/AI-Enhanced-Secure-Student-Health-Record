<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Audit-trail data access.
 *
 * Read-only: this repository ONLY issues SELECT statements. There is no
 * update/delete path for audit logs at the application level, so records
 * cannot be altered or removed through the application (append-only by
 * construction, matching the ERD design note).
 *
 * The audit viewer never returns health content: rows carry entity references
 * and opaque change summaries only. The password/token/API-key/encryption-key
 * values are never stored (see AuditLogService) so they cannot be selected.
 */
final class AuditLogRepository extends BaseRepository
{
    /**
     * Recent audit entries, newest first, for an admin-only read-only viewer.
     *
     * @param int $limit  page size (1..200)
     * @param int $offset rows to skip
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50, int $offset = 0, ?string $action = null): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT a.id, a.user_id, a.action, a.entity_type, a.entity_id,
                       a.new_values, a.ip_address, a.request_method, a.request_path,
                       a.created_at,
                       u.username
                  FROM audit_logs a
                  LEFT JOIN users u ON u.id = a.user_id';

        $params = [];
        if ($action !== null && $action !== '') {
            $sql .= ' WHERE a.action = :action';
            $params[':action'] = mb_substr($action, 0, 80);
        }

        $sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total number of audit entries (optionally filtered by action), used for
     * pagination.
     */
    public function count(?string $action = null): int
    {
        $sql = 'SELECT COUNT(*) FROM audit_logs';

        $params = [];
        if ($action !== null && $action !== '') {
            $sql .= ' WHERE action = :action';
            $params[':action'] = mb_substr($action, 0, 80);
        }

        $stmt = $this->prepare($sql, $params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Distinct action values currently present, for the filter control.
     *
     * @return array<int, string>
     */
    public function actions(): array
    {
        $rows = $this->prepare('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('strval', $rows);
    }
}
