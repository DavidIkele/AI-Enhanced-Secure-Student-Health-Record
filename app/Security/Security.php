<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Security utility foundation.
 *
 * Provide reusable primitives used across prompts:
 *  - deterministic, timing-safe comparisons
 *  - CSRF token generation/validation primitives (fully wired to forms in a
 *    later prompt, but the API surface is defined here)
 *  - safe string validation helpers
 *
 * This is deliberately a foundation: complete authentication/CSRF flows are
 * added during authentication hardening.
 */
final class Security
{
    /**
     * Timing-safe string comparison.
     */
    public static function hashEquals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Generate a cryptographically secure random token.
     */
    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /**
     * Generate a per-session CSRF token (stored server-side) and return it.
     * The token is placed in the session; validation always compares the
     * presented value against the session value.
     */
    public static function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken();
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate a presented CSRF token against the session token.
     */
    public static function verifyCsrfToken(?string $presented): bool
    {
        $expected = $_SESSION['csrf_token'] ?? null;
        if (!is_string($expected) || $expected === '' || !is_string($presented) || $presented === '') {
            return false;
        }
        return self::hashEquals($expected, $presented);
    }

    /**
     * Validate a plaintext password meets the minimum policy (12+ chars,
     * at least one digit and one letter). Real password storage uses
     * password_hash(); this is a policy check only.
     */
    public static function passwordPolicyOk(string $password): bool
    {
        if (strlen($password) < 12) {
            return false;
        }
        if (!preg_match('/[0-9]/', $password) || !preg_match('/[a-zA-Z]/', $password)) {
            return false;
        }
        return true;
    }

    /**
     * Cheap allow-list validator for role/permission style identifiers.
     */
    public static function isSafeIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/', $value);
    }
}