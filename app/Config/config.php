<?php

declare(strict_types=1);

/**
 * Central configuration. Loads environment variables (via Environment) and
 * exposes a single merged configuration array. No credentials are hard-coded
 * here; all values come from the environment (.env / real env vars).
 */

use App\Core\Environment;

function config(?string $key = null, $default = null)
{
    static $config = null;

    if ($config === null) {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2));
        }
        // Environment variables must be available before building the array
        // (real env vars always win over .env values).
        Environment::load(ROOT_PATH . '/.env');

        $config = [
            'app' => [
                'name' => (string) env('APP_NAME', 'Student Health Record Management System'),
                'env' => (string) env('APP_ENV', 'production'),
                'debug' => (bool) filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
                'url' => rtrim((string) env('APP_URL', ''), '/'),
            ],
            'session' => [
                'name' => (string) env('SESSION_NAME', 'srms_session'),
                'lifetime' => (int) env('SESSION_LIFETIME', '0'),
                'timeout' => (int) env('SESSION_TIMEOUT', '30'),
            ],
            'db' => [
                'host' => (string) env('DB_HOST', '127.0.0.1'),
                'port' => (int) env('DB_PORT', '3306'),
                'name' => (string) env('DB_NAME', ''),
                'username' => (string) env('DB_USERNAME', ''),
                'password' => (string) env('DB_PASSWORD', ''),
                'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
                'dsn' => sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    (string) env('DB_HOST', '127.0.0.1'),
                    (int) env('DB_PORT', '3306'),
                    (string) env('DB_NAME', ''),
                    (string) env('DB_CHARSET', 'utf8mb4')
                ),
            ],
            'security' => [
                'cookie_secure' => (string) env('COOKIE_SECURE', 'auto'),
                'rate_limit_attempts' => (int) env('RATE_LIMIT_ATTEMPTS', '5'),
                'rate_limit_window' => (int) env('RATE_LIMIT_WINDOW', '300'),
                'lockout_hours' => (int) env('LOCKOUT_HOURS', '1'),
            ],
            'ai' => [
                'enabled' => (bool) filter_var(env('AI_ENABLED', 'false'), FILTER_VALIDATE_BOOL),
                'base_url' => rtrim((string) env('AI_BASE_URL', 'http://127.0.0.1:8000'), '/'),
                'api_key' => (string) env('AI_API_KEY', ''),
                'allowed_hosts' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('AI_ALLOWED_HOSTS', '127.0.0.1,localhost,::1'))
                ))),
                'connect_timeout' => (float) env('AI_CONNECT_TIMEOUT', '3'),
                'timeout' => (float) env('AI_TIMEOUT', '8'),
                'retries' => (int) env('AI_RETRIES', '1'),
                'max_request_bytes' => (int) env('AI_MAX_REQUEST_BYTES', '8192'),
            ],
        ];
    }

    if ($key === null) {
        return $config;
    }

    $parts = explode('.', $key);
    $value = $config;
    foreach ($parts as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }

    return $value;
}

function env(string $key, $default = null)
{
    return Environment::get($key, $default);
}