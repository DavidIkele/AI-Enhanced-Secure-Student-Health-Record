<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Structured failure for the AI decision-support client (PROMPT 12).
 *
 * Categories map to user-visible (safe) messages and drive the controller's
 * graceful degradation. Internal detail is available to the logger but never
 * rendered to users.
 */
final class AiServiceException extends RuntimeException
{
    public const CATEGORY_CONFIG = 'config';
    public const CATEGORY_UNAVAILABLE = 'unavailable';
    public const CATEGORY_TIMEOUT = 'timeout';
    public const CATEGORY_HTTP = 'http';
    public const CATEGORY_AUTH = 'auth';
    public const CATEGORY_INVALID_RESPONSE = 'invalid_response';

    private string $category;
    private ?int $httpStatus;

    public function __construct(
        string $category,
        string $message,
        ?int $httpStatus = null,
        ?\Throwable $previous = null
    ) {
        $this->category = $category;
        $this->httpStatus = $httpStatus;
        parent::__construct($message, 0, $previous);
    }

    public function category(): string
    {
        return $this->category;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * A safe, non-technical message suitable for the UI.
     */
    public function userMessage(): string
    {
        return match ($this->category) {
            self::CATEGORY_CONFIG => 'AI decision support is not configured on this server.',
            self::CATEGORY_UNAVAILABLE => 'The AI decision-support service is temporarily unavailable. Try again later.',
            self::CATEGORY_TIMEOUT => 'The AI decision-support service took too long to respond. Try again later.',
            self::CATEGORY_AUTH => 'The AI decision-support service rejected this request (authentication). Contact the administrator.',
            self::CATEGORY_INVALID_RESPONSE => 'The AI decision-support service returned an unexpected response.',
            default => 'The AI decision-support service could not complete the request.',
        };
    }
}
