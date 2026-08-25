<?php

declare(strict_types=1);

/**
 * Minimal HTTP test client for the system test suites.
 *
 * A small stream-based client (no curl / composer dependencies) that behaves
 * like a browser for the purposes of the functional/security/write/AI e2e
 * suites:
 *
 *   - keeps a cookie jar across requests (sessions + CSRF state)
 *   - automatically follows redirects (GET-after-302/303 like a browser) and
 *     lands on the final page, so `status()`/`body()` reflect the destination
 *   - exposes status()/body()/contains()/notContains()/header() plus the
 *     helpers csrfFromPage(), sessionCookieValue() and logout() used by the
 *     suites.
 */

final class HttpTestClient
{
    private string $base;
    private string $origin;

    /** @var array<string,string> cookie name => value */
    private array $cookies = [];

    private int $status = 0;

    /** @var array<string,string> lowercased header name => value */
    private array $headers = [];

    private string $body = '';
    private int $maxRedirects = 10;

    public function __construct(string $base)
    {
        $this->base = rtrim($base, '/');
        $parts = parse_url($this->base);
        $this->origin = ($parts['scheme'] ?? 'http')
            . '://' . ($parts['host'] ?? 'localhost')
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    public function get(string $path, array $params = []): void
    {
        $url = $this->base . '/' . ltrim($path, '/');
        if ($params !== []) {
            $sep = str_contains($url, '?') ? '&' : '?';
            $url .= $sep . http_build_query($params);
        }
        $this->request('GET', $url);
    }

    public function post(string $path, array $data): void
    {
        $this->request('POST', $this->base . '/' . ltrim($path, '/'), $data);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function contains(string $needle): bool
    {
        return str_contains($this->body, $needle);
    }

    public function notContains(string $needle): bool
    {
        return !str_contains($this->body, $needle);
    }

    /**
     * Header lookup by name (case-insensitive). Multiple Set-Cookie lines are
     * collapsed with "\n" but the last value is also kept verbatim.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Extract the CSRF token from the current page body. The app renders it as
     * a hidden field: <input type="hidden" name="_csrf" value="...">
     */
    public function csrfFromPage(): string
    {
        foreach (['#name=["\']_csrf["\']\s+value=["\']([^"\']+)["\']#i', '#name=["\']_csrf["\']\s*content=["\']([^"\']+)["\']#i'] as $pattern) {
            if (preg_match($pattern, $this->body, $m)) {
                return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return '';
    }

    /**
     * The raw value of the session cookie currently held in the jar.
     */
    public function sessionCookieValue(): string
    {
        foreach ($this->cookies as $name => $value) {
            if (str_contains(strtolower($name), 'session')) {
                return $value;
            }
        }
        return '';
    }

    /**
     * Perform a CSRF-protected logout (mirrors the helper in the suites).
     */
    public function logout(): void
    {
        $this->get('/dashboard');
        $token = $this->csrfFromPage();
        if ($token === '') {
            $this->get('/');
            $token = $this->csrfFromPage();
        }
        if ($token !== '') {
            $this->post('/auth/logout', ['_csrf' => $token]);
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Perform the request, following redirects like a browser.
     *
     * @param array<int|string, mixed> $data
     */
    private function request(string $method, string $url, array $data = []): void
    {
        $redirects = 0;
        $dataForHop = $data;

        while (true) {
            $this->perform($method, $url, $dataForHop);
            $dataForHop = [];

            $location = $this->headers['location'] ?? null;
            $isRedirect = in_array($this->status, [301, 302, 303, 307, 308], true);

            if (!$isRedirect || $location === null || $redirects >= $this->maxRedirects) {
                return;
            }
            $redirects++;

            // RFC semantics: 303 -> GET; 301/302 from POST/GET -> GET (matches
            // the app's redirect() helper which uses 302); 307/308 preserve the
            // method. The app only issues GET targets, so GET is always safe.
            if ($this->status !== 307 && $this->status !== 308) {
                $method = 'GET';
            }

            $url = $this->absoluteLocation($location);
        }
    }

    /**
     * @param array<int|string, mixed> $data
     */
    private function perform(string $method, string $url, array $data): void
    {
        $headerLines = [
            'User-Agent: srms-test-client/1.0',
            'Accept: text/html,application/xhtml+xml,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Connection: close',
        ];

        $content = '';
        if ($method === 'POST') {
            $content = http_build_query($data, '', '&');
            $headerLines[] = 'Content-Type: application/x-www-form-urlencoded';
            $headerLines[] = 'Content-Length: ' . strlen($content);
        }

        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $headerLines[] = 'Cookie: ' . implode('; ', $pairs);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'content' => $content,
                'ignore_errors' => true,
                'follow_location' => 0,
                'protocol_version' => 1.1,
                'timeout' => 30,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $fp = @fopen($url, 'r', false, $context);
        $raw = $fp === false ? '' : (string) stream_get_contents($fp);
        if ($fp !== false) {
            fclose($fp);
        }

        $this->body = $raw;
        $this->status = 0;
        $this->headers = [];

        foreach ($http_response_header ?? [] as $line) {
            if (stripos($line, 'HTTP/') === 0) {
                $parts = explode(' ', $line, 3);
                $this->status = (int) ($parts[1] ?? 0);
                continue;
            }
            $sep = strpos($line, ':');
            if ($sep === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $sep)));
            $value = trim(substr($line, $sep + 1));
            if ($name === '' || $value === '') {
                continue;
            }
            if ($name === 'set-cookie') {
                $this->storeCookie($value);
                $this->headers[$name] = $value;
                continue;
            }
            $this->headers[$name] = isset($this->headers[$name])
                ? $this->headers[$name] . "\n" . $value
                : $value;
        }
    }

    private function storeCookie(string $setCookie): void
    {
        $pair = explode(';', $setCookie, 2)[0];
        $eq = strpos($pair, '=');
        if ($eq === false) {
            return;
        }
        $name = trim(substr($pair, 0, $eq));
        $value = trim(substr($pair, $eq + 1));
        if ($value === '' || strtolower($value) === 'deleted') {
            unset($this->cookies[$name]);
            return;
        }
        $this->cookies[$name] = $value;
    }

    /**
     * Resolve a Location header to a requestable URL. The app redirects to
     * base_url()-style root-relative paths that may contain literal spaces
     * (the project folder has spaces), so those must be percent-encoded.
     */
    private function absoluteLocation(string $location): string
    {
        $location = str_replace(' ', '%20', $location);

        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $qs = strpos($location, '?');
        $path = $qs === false ? $location : substr($location, 0, $qs);
        $query = $qs === false ? '' : substr($location, $qs);

        if (str_starts_with($path, '/')) {
            return $this->origin . $path . $query;
        }

        return $this->base . '/' . ltrim($path, '/') . $query;
    }
}
