<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Middleware pipeline. Middleware handlers are callables with the signature
 *
 *   function (Request $request, Response $response, callable $next): void
 *
 * Each handler may either terminate the request (by producing output or
 * throwing) or delegate to $next(). Handlers are executed in registration
 * order.
 */
final class MiddlewarePipeline
{
    /**
     * @param array<callable> $middleware
     * @param callable $controller the final handler
     */
    public static function run(array $middleware, Request $request, Response $response, callable $controller): void
    {
        $index = 0;
        $runner = null;

        $runner = static function () use (&$runner, &$index, $middleware, $request, $response, $controller): void {
            if (!isset($middleware[$index])) {
                $controller();
                return;
            }
            $handler = $middleware[$index];
            $index++;
            $handler($request, $response, $runner);
        };

        $runner();
    }
}
