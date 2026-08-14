<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Forbidden (403) - used by authorization middleware and controllers when the
 * current user lacks the required role/permission or resource ownership.
 */
class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = 'You are not allowed to access this resource.')
    {
        parent::__construct($message, 403);
    }
}
