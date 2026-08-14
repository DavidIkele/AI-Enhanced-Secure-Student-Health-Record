<?php

declare(strict_types=1);

namespace App\Security;

use App\Services\Database;
use PDO;

/**
 * Brute-force / credential-stuffing protection.
 *
 * Tracks failed login attempts in the `login_attempts` table, keyed by both
 * the submitted identifier and the client IP. The window and attempt limits
 * are configured via .env (RATE_LIMIT_ATTEMPTS, RATE_LIMIT_WINDOW).
 *
 * Rate limiting keys are compared by exact string so that no timing side
 * channel is introduced.
 */
final class RateLimiter
{
    public function __construct(
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        private readonly PDO $db,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            (int) config('security.rate_limit_attempts'),
            (int) config('security.rate_limit_window'),
            Database::connection()
        );
    }

    /**
     * Count failed attempts for a given key within the configured window.
     */
    public function attemptsInWindow(string $key): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $this->windowSeconds);
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
               FROM login_attempts
              WHERE succeeded = 0
                AND (identifier = :idkey OR ip_address = :ipkey)
                AND attempted_at >= :cutoff'
        );
        $stmt->bindValue(':idkey', $key, PDO::PARAM_STR);
        $stmt->bindValue(':ipkey', $key, PDO::PARAM_STR);
        $stmt->bindValue(':cutoff', $cutoff, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * True when the key has hit the maximum allowed failed attempts.
     */
    public function isBlocked(string $key): bool
    {
        return $this->attemptsInWindow($key) >= $this->maxAttempts;
    }

    /**
     * Record a login attempt (successful or not). PII is limited to the
     * identifier as submitted and the IP address.
     */
    public function recordAttempt(string $identifier, string $ipAddress, bool $succeeded, ?int $userId = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (user_id, identifier, ip_address, succeeded, attempted_at)
             VALUES (:uid, :identifier, :ip, :succeeded, NOW())'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':identifier', mb_substr($identifier, 0, 190), PDO::PARAM_STR);
        $stmt->bindValue(':ip', mb_substr($ipAddress, 0, 45), PDO::PARAM_STR);
        $stmt->bindValue(':succeeded', $succeeded ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function clearForIdentifier(string $identifier): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM login_attempts WHERE identifier = :identifier'
        );
        $stmt->bindValue(':identifier', mb_substr($identifier, 0, 190), PDO::PARAM_STR);
        $stmt->execute();
    }
}
