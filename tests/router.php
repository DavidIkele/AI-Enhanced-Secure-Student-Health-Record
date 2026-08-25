<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in development server, used by CI and local
 * `composer test:*` runs. Mirrors public/.htaccess: existing files are served
 * directly and every other request is routed through the front controller.
 *
 * Usage:
 *   php -S 127.0.0.1:8080 tests/router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$file = dirname(__DIR__) . '/public' . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require dirname(__DIR__) . '/public/app_entry.php';