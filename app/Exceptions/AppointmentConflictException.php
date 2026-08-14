<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Raised when an appointment booking or reschedule conflicts with an existing
 * appointment for the same staff member (double-booking prevention).
 */
class AppointmentConflictException extends AppException
{
}
