<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use App\Security\AccessControl;
use App\Security\Hasher;
use App\Security\RateLimiter;

/**
 * Authentication service.
 *
 * Responsibilities:
 *   - verify credentials (Argon2id/bcrypt)
 *   - rate limiting + brute-force protection (per IP and per identifier)
 *   - account lockout after repeated failures
 *   - session regeneration on login (session fixation protection)
 *   - idle session timeout
 *   - logout (destroys session + cookie)
 *
 * The response to "unknown user" and "wrong password" is intentionally
 * identical, and the same amount of hashing work is done either way, so that
 * account existence cannot be probed via message or timing differences
 * (user-enumeration protection).
 */
final class AuthService
{
    private const GENERIC_ERROR = 'Invalid username/email or password.';

    private UserRepository $users;
    private RateLimiter $limiter;
    private int $maxAttempts;
    private int $windowSeconds;
    private int $lockoutSeconds;
    private int $timeoutSeconds;

    public function __construct(?UserRepository $users = null, ?RateLimiter $limiter = null)
    {
        $this->users = $users ?? new UserRepository();
        $this->limiter = $limiter ?? RateLimiter::fromConfig();
        $this->maxAttempts = (int) config('security.rate_limit_attempts');
        $this->windowSeconds = (int) config('security.rate_limit_window');
        $this->lockoutSeconds = (int) config('security.lockout_hours') * 3600;
        $this->timeoutSeconds = (int) config('session.timeout') * 60;
    }

    /**
     * Attempt to authenticate a user.
     *
     * @return array{success: bool, error?: string, user?: array, locked?: bool}
     */
    public function attempt(string $identifier, string $password, string $ipAddress): array
    {
        $identifier = trim($identifier);

        // Basic validation first (no database work).
        if ($identifier === '' || $password === '') {
            return ['success' => false, 'error' => 'Enter your username/email and password.'];
        }

        // Rate limiting: block by identifier OR IP before doing any work.
        if ($this->limiter->isBlocked($identifier)) {
            AuditLogService::record('failed_login', 'auth', null, ['reason' => 'rate_limited', 'identifier' => $identifier]);
            return ['success' => false, 'error' => 'Too many login attempts. Try again later.', 'blocked' => true];
        }
        if ($this->limiter->isBlocked($ipAddress)) {
            AuditLogService::record('failed_login', 'auth', null, ['reason' => 'rate_limited', 'ip' => $ipAddress]);
            return ['success' => false, 'error' => 'Too many login attempts. Try again later.', 'blocked' => true];
        }

        $user = $this->users->findByLoginIdentifier($identifier);

        // Always perform a hash comparison, even for unknown identifiers, to
        // equalise timing (prevents timing-based user enumeration).
        $hash = $user['password_hash'] ?? Hasher::hash(self::GENERIC_ERROR . random_bytes(8));
        $passwordOk = Hasher::verify($password, $hash);

        if ($user === null) {
            // Unknown identifier: still record a failed attempt for rate
            // limiting (keyed by the submitted identifier and IP).
            $this->limiter->recordAttempt($identifier, $ipAddress, false);
            AuditLogService::record(
                'failed_login',
                'auth',
                null,
                ['reason' => 'unknown_identifier', 'identifier' => mb_substr($identifier, 0, 80)]
            );
            return ['success' => false, 'error' => self::GENERIC_ERROR];
        }

        // Account lockout check (persisted lockout from prior failures).
        if ($this->users->isLocked((int) $user['id'])) {
            AuditLogService::record(
                'failed_login',
                'auth',
                (string) $user['id'],
                ['reason' => 'account_locked'],
                (int) $user['id']
            );
            return ['success' => false, 'error' => 'Account temporarily locked. Try again later.', 'locked' => true];
        }

        // Disabled accounts are treated as generic failures.
        if ((int) $user['is_active'] !== 1) {
            $this->limiter->recordAttempt($identifier, $ipAddress, false, (int) $user['id']);
            AuditLogService::record(
                'failed_login',
                'auth',
                (string) $user['id'],
                ['reason' => 'inactive'],
                (int) $user['id']
            );
            return ['success' => false, 'error' => self::GENERIC_ERROR];
        }

        if (!$passwordOk) {
            $this->limiter->recordAttempt($identifier, $ipAddress, false, (int) $user['id']);
            $failed = $this->users->incrementFailedAttempts((int) $user['id']);
            AuditLogService::record(
                'failed_login',
                'auth',
                (string) $user['id'],
                ['reason' => 'invalid_credentials'],
                (int) $user['id']
            );

            if ($failed >= $this->maxAttempts) {
                $this->users->lockUser((int) $user['id'], $this->lockoutSeconds);
                // Lockout is a distinct auditable event: the account transition
                // from active to locked (attributable to the repeated failures).
                AuditLogService::record(
                    'lockout',
                    'user',
                    (string) $user['id'],
                    ['reason' => 'max_attempts', 'failed_attempts' => $failed],
                    (int) $user['id']
                );
                return ['success' => false, 'error' => 'Account temporarily locked. Try again later.', 'locked' => true];
            }

            return ['success' => false, 'error' => self::GENERIC_ERROR];
        }

        // Success: reset tracking, record success, regenerate session.
        $this->users->resetFailedAttempts((int) $user['id']);
        $this->limiter->clearForIdentifier($identifier);
        $this->limiter->recordAttempt($identifier, $ipAddress, true, (int) $user['id']);
        $this->users->updateLastLogin((int) $user['id']);

        $this->establishAuthenticatedSession((int) $user['id']);

        AuditLogService::record(
            'login',
            'auth',
            (string) $user['id'],
            null,
            (int) $user['id']
        );

        return ['success' => true, 'user' => $user];
    }

    /**
     * Establish the authenticated session with a fresh session ID.
     */
    private function establishAuthenticatedSession(int $userId): void
    {
        // Regenerate ID BEFORE storing the auth marker (fixation protection).
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('auth_time', time());
        Session::set('last_activity', time());
    }

    /**
     * Whether the current session is authenticated and not expired.
     */
    public function check(): bool
    {
        if (!Session::has('user_id')) {
            return false;
        }
        return $this->checkTimeout();
    }

    /**
     * Idle timeout enforcement. Returns true when still valid.
     */
    private function checkTimeout(): bool
    {
        if ($this->timeoutSeconds <= 0) {
            return true;
        }
        $lastActivity = (int) Session::get('last_activity', 0);
        if ($lastActivity > 0 && (time() - $lastActivity) > $this->timeoutSeconds) {
            $this->logout();
            return false;
        }
        Session::set('last_activity', time());
        return true;
    }

    public function id(): ?int
    {
        if (!$this->check()) {
            return null;
        }
        $id = Session::get('user_id');
        return is_int($id) ? $id : null;
    }

    public function user(): ?array
    {
        $id = $this->id();
        return $id === null ? null : $this->users->findById($id);
    }

    public function logout(): void
    {
        Session::destroy();
    }

    /**
     * Register a new student account and profile (self-registration).
     *
     * Creates the user with the student role, then a linked student profile,
     * inside one transaction. Returns the new user id on success, or a
     * field-keyed error map on failure (e.g. duplicates).
     *
     * @param array<string, mixed> $data validated, normalized registration data
     * @return array{success: bool, user_id?: int, errors?: array<string, string>}
     */
    public function register(array $data): array
    {
        $username = strtolower(trim((string) ($data['username'] ?? '')));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $regNumber = strtoupper(trim((string) ($data['reg_number'] ?? '')));

        // Pre-flight uniqueness checks (DB constraints enforce these too).
        if ($this->users->existsByUsername($username)) {
            return ['success' => false, 'errors' => ['username' => ['That username is already taken.']]];
        }
        if ($this->users->existsByEmail($email)) {
            return ['success' => false, 'errors' => ['email' => ['An account with that email address already exists.']]];
        }

        $students = new StudentRepository();
        if ($students->existsByRegNumber($regNumber)) {
            return ['success' => false, 'errors' => ['reg_number' => ['That registration number is already registered.']]];
        }

        $roleId = AccessControl::roleIdFor('student');
        if ($roleId === null) {
            return ['success' => false, 'errors' => ['_form' => ['Student registration is not available at the moment.']]];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $userId = $this->users->create([
                'username' => $username,
                'email' => $email,
                'password_hash' => Hasher::hash((string) ($data['password'] ?? '')),
                'role_id' => $roleId,
            ]);
            $students->create([
                'user_id' => $userId,
                'reg_number' => $regNumber,
                'first_name' => trim((string) ($data['first_name'] ?? '')),
                'last_name' => trim((string) ($data['last_name'] ?? '')),
                'other_names' => trim((string) ($data['other_names'] ?? '')),
                'date_of_birth' => ($data['date_of_birth'] ?? '') !== '' ? $data['date_of_birth'] : null,
                'gender' => ($data['gender'] ?? '') !== '' ? $data['gender'] : null,
                'email' => $email,
                'phone' => trim((string) ($data['phone'] ?? '')),
                'address' => trim((string) ($data['address'] ?? '')),
                'department' => trim((string) ($data['department'] ?? '')),
                'faculty' => trim((string) ($data['faculty'] ?? '')),
                'level_of_study' => trim((string) ($data['level_of_study'] ?? '')),
                'emergency_contact_name' => trim((string) ($data['emergency_contact_name'] ?? '')),
                'emergency_contact_phone' => trim((string) ($data['emergency_contact_phone'] ?? '')),
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['success' => false, 'errors' => ['_form' => ['Registration could not be completed. Please try again.']]];
        }

        return ['success' => true, 'user_id' => $userId];
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }
}
