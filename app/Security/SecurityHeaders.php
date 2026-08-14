<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Applies hardening security headers to every HTTP response.
 *
 * Deliberately conservative (OWASP Secure Headers Project baseline). The
 * Content-Security-Policy is kept compatible with local Bootstrap assets and
 * any future CDN-only usage; inline scripts/styles are NOT allowed so that
 * injected markup cannot run script.
 */
final class SecurityHeaders
{
    /**
     * @return array<string, string> header name => value
     */
    public static function headers(bool $https): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'X-XSS-Protection' => '0',
            'Content-Security-Policy' =>
                "default-src 'self'; " .
                "base-uri 'self'; " .
                "object-src 'none'; " .
                "frame-ancestors 'none'; " .
                // Local vendor (Bootstrap, Chart.js) is served from
                // public/assets/vendor; no third-party CDN is needed, so the
                // policy stays strict.
                "script-src 'self'; " .
                "style-src 'self'; " .
                "font-src 'self' data:; " .
                "img-src 'self' data:; " .
                "connect-src 'self'; " .
                "form-action 'self'",
        ];

        if ($https) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }

    public static function apply(bool $https): void
    {
        foreach (self::headers($https) as $name => $value) {
            header($name . ': ' . $value);
        }
    }
}