<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple view renderer. Templates live under app/Views and are plain PHP
 * files. A template can be wrapped in a layout located under
 * app/Views/layouts. Escaping must be applied with e() inside templates;
 * this file intentionally performs no output escaping on its own so that
 * double-escaping is avoided.
 */
final class View
{
    /**
     * Render a template, optionally wrapped in a layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], ?string $layout = 'main'): void
    {
        $content = self::capture($template, $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        $data['content'] = $content;
        $data['title'] = $data['title'] ?? app_name();
        echo self::capture('layouts/' . $layout, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $template, array $data): string
    {
        $path = APP_PATH . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}