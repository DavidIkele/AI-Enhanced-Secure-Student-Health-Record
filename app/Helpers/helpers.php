<?php

declare(strict_types=1);

/**
 * Global helper functions used across the application.
 *
 * These are plain functions (not namespaced) so they are available everywhere.
 */

if (!function_exists('e')) {
    /**
     * HTML-escape a string for safe output. Must be used for ALL dynamic output.
     *
     * @param mixed $value
     */
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /**
     * Build a URL relative to the application's public base path.
     */
    function base_url(string $path = ''): string
    {
        $base = (string) config('app.url');

        if ($base === '') {
            static $detected = null;
            if ($detected === null) {
                $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
                $dir = rtrim(str_replace('\\', '/', (string) dirname((string) $script)), '/');
                $detected = $dir;
            }
            $base = $detected;
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    /**
     * Issue an HTTP redirect and terminate execution.
     */
    function redirect(string $path, int $status = 302): void
    {
        $url = preg_match('#^https?://#i', $path) ? $path : base_url($path);
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('is_https')) {
    /**
     * Detect whether the current request was made over HTTPS.
     *
     * In production behind a reverse proxy/load balancer, the
     * `HTTP_X_FORWARDED_PROTO` header is typically set by the proxy.
     * When present, its value is used; otherwise the direct $_SERVER checks
     * apply. This prevents the app from thinking HTTP traffic is HTTPS when
     * sitting behind a proxy that already terminates TLS.
     */
    function is_https(): bool
    {
        // Check proxy header first (most common in production).
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        return isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443;
    }
}

if (!function_exists('app_name')) {
    function app_name(): string
    {
        return (string) config('app.name');
    }
}

if (!function_exists('is_debug')) {
    function is_debug(): bool
    {
        return (bool) config('app.debug');
    }
}

if (!function_exists('app_env')) {
    function app_env(): string
    {
        return (string) config('app.env');
    }
}