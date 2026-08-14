<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PSR-4 style autoloader for the App\ namespace. Maps App\Foo\Bar to
 * <project>/app/Foo/Bar.php. The project root is exposed via ROOT_PATH and
 * the app directory via APP_PATH (defined in public/index.php).
 */
final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'App\\';
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = APP_PATH . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}