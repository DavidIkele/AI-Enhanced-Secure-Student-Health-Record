<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\NotFoundHttpException;
use ReflectionMethod;

/**
 * Tiny dependency-free router.
 *
 * Routes are declared in app/Routes/web.php. Handlers are defined as
 * [ControllerFQCN, method]. Routes may contain {param} placeholders.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, keys:array, handler:array, middleware:array, name?:string}> */
    private array $routes = [];

    public function add(string $method, string $path, array $handler, ?string $name = null, array $middleware = []): void
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');
        $keys = [];

        $pattern = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', static function (array $m) use (&$keys) {
            $keys[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $path);

        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $pattern . '$#',
            'keys' => $keys,
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => $name,
        ];
    }

    public function get(string $path, array $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $name, $middleware);
    }

    public function post(string $path, array $handler, ?string $name = null, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $name, $middleware);
    }

    /**
     * Match a method/path to a route. Returns the matched handler plus the
     * resolved parameters. The "no match" reason is exposed separately so the
     * dispatcher can return 404 (no such path) vs 405 (path exists but the
     * method is not allowed).
     *
     * @return array{result: array{handler:array, middleware:array, params:array}, allowed:array<int,string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');

        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            if ($route['method'] === $method) {
                $params = [];
                foreach ($route['keys'] as $key) {
                    $params[$key] = $matches[$key] ?? null;
                }
                return [
                    'result' => [
                        'handler' => $route['handler'],
                        'middleware' => $route['middleware'],
                        'params' => $params,
                    ],
                    'allowed' => $allowed,
                ];
            }
            $allowed[] = $route['method'];
        }

        return $allowed === [] ? null : ['result' => [], 'allowed' => array_values(array_unique($allowed))];
    }

    /**
     * Dispatch the current request to its handler.
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        // HEAD is treated as GET (RFC 9110 §9.3.2): if a route is registered
        // for GET, also serve HEAD against it (without a body).
        $probeMethod = $method === 'HEAD' ? 'GET' : $method;
        $matched = $this->match($probeMethod, $request->path());

        if ($matched === null) {
            throw new NotFoundHttpException('The requested page could not be found.');
        }
        if ($matched['result'] === []) {
            // Path matches another method but not the current one -> 405.
            $response = new Response();
            $response->header('Allow', implode(', ', $matched['allowed']));
            throw new \App\Exceptions\HttpException('Method not allowed.', 405);
        }

        $matched = $matched['result'];
        [$controllerClass, $method] = $matched['handler'];
        $response = new Response();
        $controller = new $controllerClass($request, $response);

        // Route parameters are captured as strings by the regex. Cast each
        // parameter to the declared type of the controller method so that
        // typed signatures (e.g. int $studentId) receive the correct value
        // instead of raising a TypeError under strict_types=1. Params are
        // keyed by the route placeholder name, which matches the controller
        // method parameter name.
        $params = $matched['params'];
        $reflection = new ReflectionMethod($controllerClass, $method);
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!array_key_exists($name, $params) || $params[$name] === null) {
                continue;
            }
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType) {
                $value = $params[$name];
                if ($type->getName() === 'int') {
                    $params[$name] = filter_var($value, FILTER_VALIDATE_INT);
                    if ($params[$name] === false) {
                        throw new NotFoundHttpException('The requested resource could not be found.');
                    }
                }
            }
        }

        $middleware = array_map(static function ($mw) {
            // A callable (e.g. a middleware factory closure) is used as-is;
            // a class-string is invoked as a static handle() method.
            if (is_callable($mw)) {
                return $mw;
            }
            return [$mw, 'handle'];
        }, $matched['middleware']);

        $controllerCallable = static function () use ($controller, $method, $params): void {
            $controller->{$method}(...array_values($params));
        };

        MiddlewarePipeline::run($middleware, $request, $response, $controllerCallable);
    }
}