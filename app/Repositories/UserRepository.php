<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Services\Database;
use PDO;

/**
 * User data access. Uses PDO prepared statements exclusively.
 */
final class UserRepository extends BaseRepository
{
    /**
     * Find a user by login identifier (username OR email).
     * Excludes soft-deleted accounts.
     *
     * @return array<string, mixed>|null
     */
    public function findByLoginIdentifier(string $identifier): ?array
    {
        $stmt = $this->prepare(
            'SELECT id, username, email, password_hash, role_id, is_active,
                    must_change_password, failed_login_attempts, locked_until,
                    last_login_at
               FROM users
              WHERE (username = :uname OR email = :email)
                AND deleted_at IS NULL
              LIMIT 1',
            [':uname' => $identifier, ':email' => $identifier]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->prepare(
            'SELECT id, username, email, password_hash, role_id, is_active,
                    must_change_password, failed_login_attempts, locked_until,
                    last_login_at
               FROM users
              WHERE id = :id AND deleted_at IS NULL
              LIMIT 1',
            [':id' => $id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function roleOf(int $id): ?int
    {
        $stmt = $this->prepare('SELECT role_id FROM users WHERE id = :id AND deleted_at IS NULL', [':id' => $id]);
        $role = $stmt->fetchColumn();
        return $role === false ? null : (int) $role;
    }

    /**
     * Create a new user account (usable for student self-registration).
     *
     * @param array{username:string, email:string, password_hash:string, role_id:int} $data
     * @return int The new user's id.
     */
    public function create(array $data): int
    {
        $this->prepare(
            'INSERT INTO users (username, email, password_hash, role_id, is_active, must_change_password)
             VALUES (:username, :email, :password_hash, :role_id, 1, 0)',
            [
                ':username' => $data['username'],
                ':email' => $data['email'],
                ':password_hash' => $data['password_hash'],
                ':role_id' => $data['role_id'],
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Whether a non-deleted account already uses the given username.
     */
    public function existsByUsername(string $username): bool
    {
        $stmt = $this->prepare(
            'SELECT 1 FROM users WHERE username = :username AND deleted_at IS NULL LIMIT 1',
            [':username' => $username]
        );
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Whether a non-deleted account already uses the given email.
     */
    public function existsByEmail(string $email): bool
    {
        $stmt = $this->prepare(
            'SELECT 1 FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1',
            [':email' => $email]
        );
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Whether a non-deleted account other than the given id already uses the
     * email (used when a user edits their own email address).
     */
    public function existsByEmailUnless(string $email, int $exceptUserId): bool
    {
        $stmt = $this->prepare(
            'SELECT 1 FROM users WHERE email = :email AND id <> :id AND deleted_at IS NULL LIMIT 1',
            [':email' => $email, ':id' => $exceptUserId]
        );
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Update a user's email address.
     */
    public function updateEmail(int $id, string $email): void
    {
        $this->prepare('UPDATE users SET email = :email WHERE id = :id', [':id' => $id, ':email' => $email]);
    }

    /**
     * Whether a non-deleted account other than the given id already uses the
     * username (used when a user edits their own username).
     */
    public function existsByUsernameUnless(string $username, int $exceptUserId): bool
    {
        $stmt = $this->prepare(
            'SELECT 1 FROM users WHERE username = :username AND id <> :id AND deleted_at IS NULL LIMIT 1',
            [':username' => $username, ':id' => $exceptUserId]
        );
        return $stmt->fetchColumn() !== false;
    }

    public function updateUsername(int $id, string $username): void
    {
        $this->prepare('UPDATE users SET username = :username WHERE id = :id', [':id' => $id, ':username' => $username]);
    }

    /**
     * Enable or disable an account. Deactivation also clears any failed-login
     * lockout so a reactivated account starts clean.
     */
    public function setActive(int $id, bool $active): void
    {
        $this->prepare(
            'UPDATE users SET is_active = :active, failed_login_attempts = 0,
                    locked_until = NULL
               WHERE id = :id AND deleted_at IS NULL',
            [':id' => $id, ':active' => $active ? 1 : 0]
        );
    }

    /**
     * Soft-delete an account. The row stays intact for audit/compliance
     * purposes but is excluded from login and lookups (deleted_at IS NULL
     * everywhere). Prevents a future re-registration under the same identity.
     */
    public function softDelete(int $id): void
    {
        $this->prepare('UPDATE users SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL', [':id' => $id]);
    }

    public function updateLastLogin(int $id): void
    {
        $this->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id', [':id' => $id]);
    }

    public function incrementFailedAttempts(int $id): int
    {
        $this->prepare(
            'UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id',
            [':id' => $id]
        );
        $stmt = $this->prepare('SELECT failed_login_attempts FROM users WHERE id = :id', [':id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function resetFailedAttempts(int $id): void
    {
        $this->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id',
            [':id' => $id]
        );
    }

    public function lockUser(int $id, int $lockoutSeconds): void
    {
        $this->prepare(
            'UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL :secs SECOND) WHERE id = :id',
            [':id' => $id, ':secs' => $lockoutSeconds]
        );
    }

    public function unlockUser(int $id): void
    {
        $this->prepare('UPDATE users SET locked_until = NULL, failed_login_attempts = 0 WHERE id = :id', [':id' => $id]);
    }

    public function isLocked(int $id): bool
    {
        $stmt = $this->prepare(
            'SELECT locked_until IS NOT NULL AND locked_until > NOW() FROM users WHERE id = :id',
            [':id' => $id]
        );
        $val = $stmt->fetchColumn();
        return (bool) $val;
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $this->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id',
            [':id' => $id, ':hash' => $hash]
        );
    }

    /**
     * Ids of all active (non-deleted) users, for system announcement delivery.
     *
     * @return array<int, int>
     */
    public function allActiveIds(): array
    {
        $ids = $this->prepare(
            'SELECT id FROM users WHERE deleted_at IS NULL AND is_active = 1 ORDER BY id'
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $ids);
    }

    /**
     * Ids of all active users who hold a given permission (via their role or a
     * direct grant). Used to choose authorized recipients for broadcasts such
     * as illness-pattern alerts (analytics.view).
     *
     * @return array<int, int>
     */
    public function userIdsWithPermission(string $permission): array
    {
        $ids = $this->prepare(
            'SELECT DISTINCT u.id
               FROM users u
              WHERE u.deleted_at IS NULL
                AND u.is_active = 1
                AND (
                    EXISTS (
                        SELECT 1
                          FROM role_permission rp
                          JOIN permissions p ON p.id = rp.permission_id
                         WHERE rp.role_id = u.role_id AND p.slug = :slug
                    )
                    OR EXISTS (
                        SELECT 1
                          FROM user_permission up
                          JOIN permissions p2 ON p2.id = up.permission_id
                         WHERE up.user_id = u.id AND p2.slug = :slug2
                    )
                )
              ORDER BY u.id',
            [':slug' => $permission, ':slug2' => $permission]
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $ids);
    }
}
