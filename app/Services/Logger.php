<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Centralized file logger.
 *
 * - Writes structured, timestamped log lines to app/Logs.
 * - Redacts secrets (password-ish keys, tokens) before logging so sensitive
 *   values never reach the log files. The redaction happens on any context
 *   array, which is the only way data reaches the logger from services.
 */
final class Logger
{
    private const REDACT = ['password', 'passwd', 'pwd', 'secret', 'token', 'api_key', 'apikey', 'db_password'];

    public static function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);

        try {
            $logDir = APP_PATH . DIRECTORY_SEPARATOR . 'Logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $file = $logDir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';

            $line = sprintf(
                "[%s] %s %s %s",
                date('Y-m-d H:i:s'),
                $level,
                $message,
                self::contextToString(self::redact($context))
            );

            file_put_contents($file, rtrim($line) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Logging must never crash the application.
            error_log('Logger failure: ' . $e->getMessage());
        }
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);
            foreach (self::REDACT as $needle) {
                if (strpos($lower, $needle) !== false && $value !== '') {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }
        }
        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function contextToString(array $context): string
    {
        if ($context === []) {
            return '';
        }
        try {
            return json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (RuntimeException) {
            return '';
        }
    }
}