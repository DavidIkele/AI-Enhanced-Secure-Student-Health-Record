<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Lightweight server-side validator.
 *
 * All validation happens server-side; the browser's built-in validation is a
 * convenience layer only and is never trusted. Rules are registered by field
 * name, then evaluated. Collected errors are grouped by field so views can
 * display per-field messages (WCAG 3.3.1 Error Identification).
 */
final class Validator
{
    /** @var array<string, array<int, string>> field => list of errors */
    private array $errors = [];

    /** @var array<string, mixed> normalized values keyed by field */
    private array $values = [];

    public function value(string $field, $default = null)
    {
        return $this->values[$field] ?? $default;
    }

    /**
     * Register a trimmed string input for the given field.
     */
    public function field(string $field, mixed $input): self
    {
        $this->values[$field] = is_string($input) ? trim($input) : $input;
        return $this;
    }

    public function required(string $field): self
    {
        $value = $this->values[$field] ?? '';
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field][] = 'This field is required.';
        }
        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        $value = (string) ($this->values[$field] ?? '');
        if (mb_strlen($value) > $max) {
            $this->errors[$field][] = "Please keep this under {$max} characters.";
        }
        return $this;
    }

    public function intBetween(string $field, int $min, int $max): self
    {
        $value = $this->values[$field] ?? '';
        if ($value !== '' && $value !== null) {
            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                $this->errors[$field][] = 'Please enter a whole number.';
            } else {
                $num = (int) $value;
                if ($num < $min || $num > $max) {
                    $this->errors[$field][] = "Please enter a value between {$min} and {$max}.";
                }
            }
        }
        return $this;
    }

    public function decimal(string $field, float $min, float $max): self
    {
        $value = $this->values[$field] ?? '';
        if ($value !== '' && $value !== null) {
            if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                $this->errors[$field][] = 'Please enter a valid number.';
            } else {
                $num = (float) $value;
                if ($num < $min || $num > $max) {
                    $this->errors[$field][] = "Please enter a value between {$min} and {$max}.";
                }
            }
        }
        return $this;
    }

    public function inList(string $field, array $allowed): self
    {
        $value = $this->values[$field] ?? '';
        if ($value !== '' && $value !== null && !in_array($value, $allowed, true)) {
            $this->errors[$field][] = 'Please choose a valid option.';
        }
        return $this;
    }

    public function date(string $field): self
    {
        $value = $this->values[$field] ?? '';
        if ($value !== '' && $value !== null && strtotime((string) $value) === false) {
            $this->errors[$field][] = 'Please enter a valid date.';
        }
        return $this;
    }

    public function datetime(string $field): self
    {
        $value = $this->values[$field] ?? '';
        if ($value !== '' && $value !== null) {
            $parsed = strtotime((string) $value);
            if ($parsed === false) {
                $this->errors[$field][] = 'Please enter a valid date and time.';
            }
        }
        return $this;
    }

    /**
     * The value must be a parseable datetime in the future (used for
     * appointment booking so past slots cannot be selected).
     */
    public function futureDatetime(string $field): self
    {
        $value = $this->values[$field] ?? '';
        if ($value !== '' && $value !== null) {
            $parsed = strtotime((string) $value);
            if ($parsed === false) {
                $this->errors[$field][] = 'Please enter a valid date and time.';
            } elseif ($parsed <= time()) {
                $this->errors[$field][] = 'Please choose a date and time in the future.';
            }
        }
        return $this;
    }

    public function email(string $field): self
    {
        $value = $this->values[$field] ?? '';
        if ($value !== '' && $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field][] = 'Please enter a valid email address.';
        }
        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * Attach a custom error message to a field (used for checks that don't
     * map to a simple rule, e.g. password policy or confirmation mismatch).
     */
    public function addError(string $field, string $message): self
    {
        $this->errors[$field][] = $message;
        return $this;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
