<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Session;
use App\Services\Database;
use PDO;

/**
 * Centralized RBAC / authorization engine.
 *
 * Permissions come from the role_permission matrix plus any per-user grants
 * (user_permission). All checks are server-side; no decision ever trusts a
 * value supplied by the client.
 *
 * Results are cached per request (per user id) to avoid repeated queries.
 */
final class AccessControl
{
    /** @var array<int, array<string, bool>> user id => permission slug => true */
    private static array $permissionCache = [];

    /** @var array<int, array<string, string>> user id => role slug => true */
    private static array $roleCache = [];

    public static function reset(): void
    {
        self::$permissionCache = [];
        self::$roleCache = [];
    }

    /**
     * All permission slugs granted to a user (role matrix + direct grants).
     *
     * @return array<string, true>
     */
    public static function permissionsFor(int $userId): array
    {
        if (isset(self::$permissionCache[$userId])) {
            return self::$permissionCache[$userId];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT DISTINCT p.slug
               FROM permissions p
               JOIN role_permission rp ON rp.permission_id = p.id
               JOIN users u ON u.role_id = rp.role_id
              WHERE u.id = :uid
             UNION
             SELECT DISTINCT p.slug
               FROM permissions p
               JOIN user_permission up ON up.permission_id = p.id
              WHERE up.user_id = :uid2'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':uid2', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $slugs = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            $slugs[(string) $slug] = true;
        }
        self::$permissionCache[$userId] = $slugs;
        return $slugs;
    }

    /**
     * @return array<string, true> role slugs for the user
     */
    public static function rolesFor(int $userId): array
    {
        if (isset(self::$roleCache[$userId])) {
            return self::$roleCache[$userId];
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT r.slug
               FROM roles r
               JOIN users u ON u.role_id = r.id
              WHERE u.id = :uid
                AND u.deleted_at IS NULL'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $roles = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            $roles[(string) $slug] = true;
        }
        self::$roleCache[$userId] = $roles;
        return $roles;
    }

    public static function hasRole(int $userId, string $roleSlug): bool
    {
        return isset(self::rolesFor($userId)[$roleSlug]);
    }

    /**
     * Database id of a role by slug (used when assigning roles for accounts
     * created outside the admin UI, e.g. student self-registration).
     */
    public static function roleIdFor(string $roleSlug): ?int
    {
        $stmt = Database::connection()->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $stmt->bindValue(':slug', $roleSlug, PDO::PARAM_STR);
        $stmt->execute();
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function hasAnyRole(int $userId, string ...$roleSlugs): bool
    {
        $roles = self::rolesFor($userId);
        foreach ($roleSlugs as $slug) {
            if (isset($roles[$slug])) {
                return true;
            }
        }
        return false;
    }

    public static function can(int $userId, string $permissionSlug): bool
    {
        return isset(self::permissionsFor($userId)[$permissionSlug]);
    }

    public static function canAny(int $userId, string ...$permissionSlugs): bool
    {
        $permissions = self::permissionsFor($userId);
        foreach ($permissionSlugs as $slug) {
            if (isset($permissions[$slug])) {
                return true;
            }
        }
        return false;
    }

    public static function canAll(int $userId, string ...$permissionSlugs): bool
    {
        $permissions = self::permissionsFor($userId);
        foreach ($permissionSlugs as $slug) {
            if (!isset($permissions[$slug])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Convenience: permission check against the currently authenticated user.
     */
    public static function currentCan(string $permissionSlug): bool
    {
        $id = Session::get('user_id');
        return is_int($id) && self::can($id, $permissionSlug);
    }

    /**
     * Convenience: role check against the currently authenticated user.
     */
    public static function currentHasRole(string $roleSlug): bool
    {
        $id = Session::get('user_id');
        return is_int($id) && self::hasRole($id, $roleSlug);
    }
}
