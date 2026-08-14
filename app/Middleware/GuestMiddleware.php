<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Allows only guests (unauthenticated users). Authenticated users are
 * redirected away from guest-only pages (e.g. login).
 */
final class GuestMiddleware
{
    public static function handle(Request $request, Response $response, callable $next): void
    {
        $auth = new AuthService();

        if ($auth->check()) {
            $response->redirect('/');
            return;
        }

        $next();
    }
}
