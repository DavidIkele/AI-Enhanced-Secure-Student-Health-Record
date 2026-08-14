<?php

declare(strict_types=1);

/**
 * PROMPT 18 — shared test bootstrap.
 *
 * Loads the HttpTestClient used by the functional, security, functional-write
 * and AI end-to-end suites. The suites define their own BASE URL, check(),
 * loginAs() and db() helpers and only depend on this file for the client.
 *
 * Usage (each suite):
 *   C:\xampp\php\php.exe tests_p18\functional.php
 */

require __DIR__ . '/HttpTestClient.php';
