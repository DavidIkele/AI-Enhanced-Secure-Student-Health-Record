<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;

/**
 * HTTP response helpers. Controllers return/output responses through this
 * class to keep header handling and JSON encoding in one place.
 */
final class Response
{
    public function status(int $code): self
    {
        http_response_code($code);
        return $this;
    }

    public function header(string $name, string $value): self
    {
        header($name . ': ' . $value);
        return $this;
    }

    public function json(array $payload, int $status = 200): void
    {
        $this->status($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function jsonError(string $message, int $status = 500, array $extra = []): void
    {
        $this->json(array_merge(['success' => false, 'error' => $message], $extra), $status);
    }

    public function noContent(int $status = 204): void
    {
        $this->status($status);
    }

    public function redirect(string $path, int $status = 302): void
    {
        header('Location: ' . (preg_match('#^https?://#i', $path) ? $path : base_url($path)), true, $status);
    }

    /**
     * Abort the request with an HTTP error exception handled centrally.
     */
    public function abort(int $status, string $message): void
    {
        throw new HttpException($message, $status);
    }
}