<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\HttpException;
use App\Core\Request;

/**
 * Centralized error and exception handling.
 *
 * - Registers PHP handlers for uncaught exceptions and errors.
 * - Logs a sanitized record (no secrets, no credentials, no stack traces to
 *   the browser).
 * - In production, shows a generic page and never reveals internal details.
 * - In development, shows limited diagnostics (never credentials).
 */
final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0'); // handled centrally; never raw-displayed

        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);

        // Untreated fatal errors still route through the shutdown handler so
        // the generic page is shown instead of a blank/partial response.
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(\Throwable $e): void
    {
        $status = 500;
        if ($e instanceof HttpException) {
            $status = $e->statusCode();
        }

        Logger::error('Unhandled ' . get_class($e) . ': ' . $e->getMessage(), [
            'code' => $e->getCode(),
            'file' => is_debug() ? $e->getFile() : null,
            'line' => is_debug() ? $e->getLine() : null,
        ]);

        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');

        $requestedJson = !empty($_SERVER['HTTP_ACCEPT'])
            && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

        if ($requestedJson) {
            header('Content-Type: application/json; charset=utf-8');
            $jsonMessages = [
                403 => 'Access denied.',
                404 => 'Resource not found.',
                405 => 'Method not allowed.',
            ];
            $payload = [
                'success' => false,
                'error' => $jsonMessages[$status] ?? 'An internal error occurred.',
            ];
            echo json_encode($payload);
            exit;
        }

        echo self::renderPage($status, $e);
        exit;
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false; // suppressed via @
        }

        Logger::error('PHP error: ' . $message, [
            'severity' => $severity,
            'file' => is_debug() ? $file : null,
            'line' => is_debug() ? $line : null,
        ]);

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        Logger::error('Fatal error: ' . $error['message']);

        if (!is_debug()) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                echo self::renderPage(500);
            }
        }
    }

    private static function renderPage(int $status, ?\Throwable $e = null): string
    {
        $messages = [
            403 => ['Forbidden', 'You are not allowed to access this resource.'],
            404 => ['Page not found', 'The page you are looking for does not exist or has been moved.'],
            405 => ['Method not allowed', 'This request method is not supported.'],
        ];
        [$title, $message] = $messages[$status] ?? ['Something went wrong', 'An unexpected error occurred. Please try again later.'];

        if (is_debug() && $e !== null && self::isLoopbackClient()) {
            // Development diagnostics only; never include credentials or
            // raw environment values (passwords etc.). File/line details are
            // restricted to loopback clients so that a debug deployment never
            // discloses filesystem layout to remote visitors.
            $details = '<h2>Details (development mode)</h2><p>' . e($e->getMessage()) . '</p>';
            $details .= '<p><code>' . e($e->getFile() . ':' . $e->getLine()) . '</code></p>';
        } else {
            $details = '';
        }

        ob_start();
        require APP_PATH . '/Views/errors/error.php';
        return (string) ob_get_clean();
    }

    /**
     * Whether the current request originates from the loopback interface.
     * Used to gate verbose development diagnostics.
     */
    private static function isLoopbackClient(): bool
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return in_array($remote, ['127.0.0.1', '::1', 'localhost'], true)
            || strpos($remote, '127.') === 0
            || strpos($remote, '::ffff:127.') === 0;
    }
}