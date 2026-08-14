<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable representation of an incoming HTTP request.
 */
final class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $query;
    private array $body;
    private array $cookies;
    private array $headers;
    private array $files;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Strip query string for the path used in routing.
        if (($pos = strpos($this->uri, '?')) !== false) {
            $this->path = substr($this->uri, 0, $pos);
        } else {
            $this->path = $this->uri;
        }
        // URL-decode path segments to match route definitions faithfully.
        $this->path = rawurldecode($this->path);
        // Remove the application's base mount path (the directory where the
        // public/gateway.php front controller resides) so routing works whether
        // the project is served as a subdirectory (document root above public/)
        // or with public/ as the document root (base empty, nothing stripped).
        $base = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($base !== '' && $base !== '/' && strpos($this->path, $base) === 0) {
            $this->path = substr($this->path, strlen($base));
        }
        // Normalize duplicate slashes and trailing slash for matching.
        $this->path = '/' . trim(preg_replace('#/{2,}#', '/', $this->path), '/');
        if ($this->path === '/') {
            $this->path = '/';
        }

        $this->query = $_GET ?? [];
        $this->body = $_POST ?? [];
        $this->cookies = $_COOKIE ?? [];
        $this->files = $_FILES ?? [];
        $this->headers = $this->collectHeaders();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * All POST body values, trimmed of surrounding whitespace.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->body as $key => $value) {
            $out[$key] = is_string($value) ? trim($value) : $value;
        }
        return $out;
    }

    /**
     * Subset of POST body values for the given keys only (allow-list). The
     * CSRF token and any field the application does not explicitly expect
     * are dropped, so untrusted keys cannot reach a repository write.
     *
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $out[$key] = $all[$key];
            }
        }
        return $out;
    }

    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, $default = null)
    {
        $key = strtolower(str_replace('-', '_', $name));
        return $this->headers[$key] ?? $default;
    }

    public function files(): array
    {
        return $this->files;
    }

    /**
     * Best effort to detect a fresh AJAX/fetch call.
     */
    public function wantsJson(): bool
    {
        $accept = (string) $this->header('Accept');
        return strpos($accept, 'application/json') !== false;
    }

    private function collectHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(substr($key, 5));
                $headers[$name] = (string) $value;
            }
        }
        return $headers;
    }
}