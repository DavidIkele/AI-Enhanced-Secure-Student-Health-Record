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
 * Route-level permission guard. Build a middleware callable with
 * PermissionMiddleware::require('records.manage', ...). Any of the given
 * permissions grants access; otherwise a 403 is returned.
 */
final class PermissionMiddleware
{
    /**
     * @param string ...$permissions allowed permission slugs (any-of)
     */
    public static function require(string ...$permissions): callable
    {
        return static function (Request $request, Response $response, callable $next) use ($permissions): void {
            $auth = new AuthService();
            $userId = $auth->id();

            if ($userId === null) {
                Session::flash('warning', 'Please sign in to continue.');
                $response->redirect('/auth/login');
                return;
            }

            if (!AccessControl::canAny($userId, ...$permissions)) {
                throw new ForbiddenHttpException('You do not have permission to perform this action.');
            }

            $next();
        };
    }
}
