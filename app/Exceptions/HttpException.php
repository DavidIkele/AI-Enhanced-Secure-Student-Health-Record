<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * HTTP-level application exceptions (404, 403, 500...). Converted into an
 * appropriate status code and user-facing page by the central error handler.
 */
class HttpException extends AppException
{
    public function __construct(string $message = '', protected int $statusCode = 500)
    {
        parent::__construct($message !== '' ? $message : 'An error occurred.');
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}