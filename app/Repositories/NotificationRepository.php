<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Notification data access.
 *
 * Notifications are keyed directly by the recipient user id. All reads and
 * mutations are scoped by that user id so a caller can never read or change
 * another user's notifications (IDOR/BOLA defence at the data layer).
 *
 * De-duplication: the `notifications` table has a UNIQUE key on
 * (user_id, type, reference_type, reference_id). `create()` is idempotent for
 * the same target/event: a second call returns the existing row's id instead
 * of stacking a duplicate. Broadcast-style notifications (reference_id NULL)
 * deliberately bypass the unique key so each broadcast is a distinct event.
 */
final class NotificationRepository extends BaseRepository
{
    public const TITLE_MAX = 150;
    public const TYPE_MAX = 50;

    /** Hard cap on the number of notification rows returned per page. */
    private const MAX_LIST = 200;

    /**
     * Notifications for a user, newest first, bounded to MAX_LIST rows so the
     * inbox can never load an unbounded result set.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(int $userId, bool $unreadOnly = false, int $limit = self::MAX_LIST): array
    {
        $limit = max(1, min(self::MAX_LIST, $limit));

        $sql = 'SELECT id, user_id, type, title, body, reference_type, reference_id,
                       is_read, read_at, created_at
                  FROM notifications
                 WHERE user_id = :uid';
        if ($unreadOnly) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :limit';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->prepare(
            'SELECT id, user_id, type, title, body, reference_type, reference_id,
                    is_read, read_at, created_at
               FROM notifications
              WHERE id = :id
              LIMIT 1',
            [':id' => $id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0',
            [':uid' => $userId]
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Whether a notification for the same target/event already exists
     * (de-duplication guard used by broadcast callers).
     */
    public function hasOfType(int $userId, string $type, ?string $referenceType, ?int $referenceId): bool
    {
        $sql = 'SELECT COUNT(*) FROM notifications
                 WHERE user_id = :uid AND type = :type AND reference_type = :ref_type';
        $params = [':uid' => $userId, ':type' => $type, ':ref_type' => $referenceType];
        if ($referenceId !== null) {
            $sql .= ' AND reference_id = :ref_id';
            $params[':ref_id'] = $referenceId;
        } else {
            $sql .= ' AND reference_id IS NULL';
        }
        return (int) $this->prepare($sql, $params)->fetchColumn() > 0;
    }

    /**
     * Create a notification. Idempotent for the same (user, type,
     * reference_type, reference_id): returns the existing id on a duplicate
     * instead of inserting a second row.
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        ?string $body,
        ?string $referenceType,
        ?int $referenceId
    ): int {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO notifications
                   (user_id, type, title, body, reference_type, reference_id, is_read)
                 VALUES (?, ?, ?, ?, ?, ?, 0)'
            );
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, mb_substr($type, 0, self::TYPE_MAX), PDO::PARAM_STR);
            $stmt->bindValue(3, mb_substr($title, 0, self::TITLE_MAX), PDO::PARAM_STR);
            $stmt->bindValue(4, $body, PDO::PARAM_STR);
            $stmt->bindValue(5, $referenceType, PDO::PARAM_STR);
            $stmt->bindValue(6, $referenceId, PDO::PARAM_INT);
            $stmt->execute();
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            // Duplicate key (uq_notif_dedup): return the existing notification id.
            $stmt = $this->db->prepare(
                'SELECT id FROM notifications
                  WHERE user_id = ? AND type = ? AND reference_type = ?
                    AND reference_id <=> ?
                  LIMIT 1'
            );
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $type, PDO::PARAM_STR);
            $stmt->bindValue(3, $referenceType, PDO::PARAM_STR);
            $stmt->bindValue(4, $referenceId, PDO::PARAM_INT);
            $stmt->execute();
            $id = $stmt->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
            // Unexpected error: rethrow rather than mask it.
            throw $e;
        }
    }

    /**
     * Mark a single notification read. Ownership-scoped: only the owning user
     * can mark it, and only while it is still unread.
     */
    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications
                SET is_read = 1, read_at = NOW()
              WHERE id = :id AND user_id = :uid AND is_read = 0'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Mark all of a user's notifications read. Returns the number marked.
     */
    public function markAllRead(int $userId): int
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications
                SET is_read = 1, read_at = NOW()
              WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
