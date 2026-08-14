<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\ForbiddenHttpException;
use App\Security\AccessControl;
use App\Services\AuthService;

/**
 * Route-level role guard. Build a middleware callable with
 * RoleMiddleware::oneOf('admin', 'staff'). Requests from users without any of
 * the required roles get a 403.
 */
final class RoleMiddleware
{
    /**
     * @param string ...$roles allowed role slugs
     */
    public static function oneOf(string ...$roles): callable
    {
        return static function (Request $request, Response $response, callable $next) use ($roles): void {
            $auth = new AuthService();
            $userId = $auth->id();

            if ($userId === null) {
                Session::flash('warning', 'Please sign in to continue.');
                $response->redirect('/auth/login');
                return;
            }

            if (!AccessControl::hasAnyRole($userId, ...$roles)) {
                throw new ForbiddenHttpException('You are not authorized to access this area.');
            }

            $next();
        };
    }
}
