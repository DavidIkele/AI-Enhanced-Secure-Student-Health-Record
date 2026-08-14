<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Not found (404).
 */
class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'The requested page could not be found.')
    {
        parent::__construct($message, 404);
    }
}