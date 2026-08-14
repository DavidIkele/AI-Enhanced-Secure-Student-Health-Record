<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

/**
 * Requires an authenticated session. Unauthenticated requests are redirected
 * to the login page. Used as a route-level middleware.
 */
final class AuthMiddleware
{
    public static function handle(Request $request, Response $response, callable $next): void
    {
        $auth = new AuthService();

        if (!$auth->check()) {
            Session::flash('warning', 'Please sign in to continue.');
            $response->redirect('/auth/login');
            return;
        }

        $next();
    }
}
