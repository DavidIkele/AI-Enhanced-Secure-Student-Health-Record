<?php

declare(strict_types=1);

/**
 * Shared test bootstrap.
 *
 * Loads the HttpTestClient used by the functional, security, functional-write
 * and AI end-to-end suites, and exposes environment-driven test settings so
 * the same suites run locally (XAMPP defaults) and in CI.
 *
 * The suites define their own check(), loginAs() and db() helpers and only
 * depend on this file for the client and the shared settings.
 *
 * Usage (each suite):
 *   php tests/system/functional.php
 *
 * Overridable environment variables:
 *   TEST_BASE_URL   base URL of the running app (no trailing slash)
 *   TEST_DB_HOST    database host (default 127.0.0.1)
 *   TEST_DB_PORT    database port (default 3307 - XAMPP)
 *   TEST_DB_NAME    database name (default student_health)
 *   TEST_DB_USER    database user (default root)
 *   TEST_DB_PASS    database password (default empty)
 *   PHP_BINARY      PHP executable used to spawn child processes
 */

require __DIR__ . '/HttpTestClient.php';

function test_base_url(): string
{
    return rtrim((string) getenv('TEST_BASE_URL') ?: 'http://localhost/AI-Enhanced%20Secure%20Web-Based%20Student%20Health/public', '/');
}

function test_db(): PDO
{
    $host = (string) getenv('TEST_DB_HOST') ?: '127.0.0.1';
    $port = (string) getenv('TEST_DB_PORT') ?: '3307';
    $name = (string) getenv('TEST_DB_NAME') ?: 'student_health';
    $user = (string) getenv('TEST_DB_USER') ?: 'root';
    $pass = (string) getenv('TEST_DB_PASS') ?: '';

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

function test_php_binary(): string
{
    return (string) getenv('TEST_PHP_BINARY') ?: (PHP_BINARY ?: 'php');
}
