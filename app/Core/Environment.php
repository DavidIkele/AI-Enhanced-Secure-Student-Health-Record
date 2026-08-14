<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal, dependency-free .env loader.
 *
 * Loads KEY=VALUE pairs from a file into the process environment. Real
 * values must not be committed; use the provided .env.example template.
 */
final class Environment
{
    /** @var array<string, true> paths already loaded */
    private static array $loaded = [];

    public static function load(string $path): void
    {
        $real = realpath($path);
        if ($real === false) {
            unset(self::$loaded[$path]);
            return;
        }
        if (isset(self::$loaded[$real])) {
            return;
        }
        self::$loaded[$real] = true;

        $lines = file($real, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            if ($key === '' || strpos($key, ' ') !== false) {
                continue;
            }

            $value = trim(substr($line, $pos + 1));

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Only set when not already present in the real environment; a real
            // environment variable always wins over the .env file.
            if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        return ($value === false || $value === null) ? $default : $value;
    }
}