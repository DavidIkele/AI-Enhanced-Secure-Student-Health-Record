<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Core\Autoloader;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Security\SecurityHeaders;
use App\Services\ErrorHandler;

/**
 * Application bootstrap and front-controller orchestration.
 */
final class Application
{
    public static function boot(): void
    {
        self::definePaths();

        // 1. Autoloader file must be explicitly loaded before it can register.
        require APP_PATH . '/Core/Autoloader.php';
        Autoloader::register();

        // 2. Helpers + configuration.
        require APP_PATH . '/Helpers/helpers.php';
        require APP_PATH . '/Config/config.php';

        // 3. Central error handling (must be active before anything else
        //    that could throw).
        ErrorHandler::register();

        // 3. HTTP security headers.
        SecurityHeaders::apply(is_https());

        // 4. Session foundation (secure cookie parameters).
        if (PHP_SAPI !== 'cli') {
            Session::start();
        }

        // 5. Route registration + dispatch.
        $router = new Router();
        require APP_PATH . '/Routes/web.php';

        $request = new Request();
        $router->dispatch($request);
    }

    private static function definePaths(): void
    {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2));
        }
        if (!defined('APP_PATH')) {
            define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
        }
        if (!defined('PUBLIC_PATH')) {
            define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
        }
    }
}