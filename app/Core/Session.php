<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session foundation. Configures secure cookie parameters and exposes
 * minimal helpers. Authentication-specific behaviour (login, logout,
 * regeneration on privilege change, timeout handling) is added in a later
 * prompt; this class only establishes secure session settings.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $cookieSecure = (string) config('security.cookie_secure');
        if ($cookieSecure === 'auto') {
            $cookieSecure = is_https() ? '1' : '0';
        }

        session_name((string) config('session.name'));

        session_set_cookie_params([
            'lifetime' => (int) config('session.lifetime'),
            'path' => '/',
            'domain' => '',
            'secure' => (bool) $cookieSecure, // Secure flag only over HTTPS (AVOID cookie transmission in cleartext)
            'httponly' => true,               // Never expose the session cookie to scripts
            'samesite' => 'Lax',              // Mitigate CSRF at transport level
        ]);

        session_start();
        self::$started = true;
    }

    public static function isActive(): bool
    {
        return self::$started || session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Safely rotate the session ID (mitigates session fixation). Full
     * authentication flows enforce this; the helper is a foundation utility.
     */
    public static function regenerate(): void
    {
        if (self::isActive()) {
            session_regenerate_id(true);
        }
    }

    /**
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION ?? []);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Flash a one-time message (shown once, then removed).
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * Consume and clear flash messages.
     *
     * @return array<string, string>
     */
    public static function flushFlash(): array
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            return [];
        }
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($messages) ? $messages : [];
    }

    public static function destroy(): void
    {
        if (self::isActive()) {
            // Preserve any pending one-time flash so it survives the redirect
            // that follows logout. The session is fully cleared and a fresh
            // session id is issued (session fixation protection on logout).
            $flash = $_SESSION['_flash'] ?? [];

            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }
            session_destroy();
            self::$started = false;

            if (is_array($flash) && $flash !== []) {
                // Force a brand-new session id so the preserved flash is stored
                // under the id that the client actually receives next request.
                session_id(session_create_id());
                self::start();
                $_SESSION['_flash'] = $flash;
            }
        }
    }
}