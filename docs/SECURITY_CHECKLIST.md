# Security Checklist

Objective evidence from the committed security test suite (68 checks) and
code review. Run these checks on every environment before it handles real
data. This list is a **baseline review**, not a guarantee of perfect security.

## Authentication & session

- [ ] Passwords hashed with Argon2id (`memory_cost 65536, time_cost 4, threads 2`), bcrypt fallback; verify with `password_verify`.
- [ ] Sessions regenerate the ID on login; cookies HttpOnly + SameSite; Secure flag set over HTTPS (`COOKIE_SECURE=auto`).
- [ ] Idle session timeout enforced (`SESSION_TIMEOUT`, default 30 min).
- [ ] Logout invalidates the session.
- [ ] Login rate limit + temporary lockout (`RATE_LIMIT_ATTEMPTS`/`RATE_LIMIT_WINDOW`/`LOCKOUT_HOURS`); lockout state tracked in `login_attempts`.
- [ ] No credential leakage in responses or logs.

## Authorization / access control

- [ ] Every protected route runs `AuthMiddleware`.
- [ ] Permission checks via `PermissionMiddleware::require(...)`; role boundary via `RoleMiddleware::oneOf('admin')` for `/admin/*`.
- [ ] Controller-level ownership checks prevent IDOR/BOLA (student may only access own records/insights/appointments).
- [ ] RBAC matrix: student/staff/admin mapped in `database/seed.php`.
- [ ] No privileged route reachable by unauthenticated or lower-role users (verified by the security suite privilege-escalation + auth-bypass checks).

## Input validation & injection

- [ ] All queries via PDO prepared statements; `ATTR_EMULATE_PREPARES=false` (server-side prepares).
- [ ] SQL injection string neutralised (verified: `UNION`/`OR 1=1` payloads return no data).
- [ ] Reflected/stored XSS: all dynamic output escaped (`e()`); inline scripts/styles forbidden by CSP; verified with XSS payloads.
- [ ] CSRF token validated on every state-changing POST (login, logout, records, appointments, notifications, admin actions).
- [ ] Invalid/non-numeric IDs rejected (404) instead of fatal.
- [ ] Body-size limits on AI requests (`AI_MAX_REQUEST_BYTES`); oversized POST bodies clamped gracefully.

## Output encoding / XSS (defence in depth)

- [ ] `e()` escaping used consistently in views; no raw `$_GET`/`$_POST` echoed.
- [ ] No `document.write`/`innerHTML` with unescaped data in `public/assets/js/app.js` (CSP `script-src 'self' https://cdn.jsdelivr.net`), `connect-src 'self'`, `form-action 'self'`.

## Security headers

- [ ] `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera/mic/geolocation/payment/usb denied), `X-XSS-Protection: 0`, CSP present (see `app/Security/SecurityHeaders.php`).
- [ ] `Strict-Transport-Security` emitted when HTTPS (`max-age=31536000; includeSubDomains`).
- [ ] (Hardening) Suppress server/product banners in production: set `ServerSignature Off` and `ServerTokens Prod` (Apache) and `expose_php = Off` (PHP) so `Server` / `X-Powered-By` do not disclose versions. The app already hides its own internal details, but these server-level headers are set by Apache/PHP, not the app.

## Error handling & information disclosure

- [ ] `display_errors=0` always; production error pages generic (no stack trace / internal paths).
- [ ] `APP_DEBUG=false` in production; development diagnostics scoped to dev only, never credentials.
- [ ] Server errors logged server-side (`app/Logs/`) with no secrets.
- [ ] Filesystem/proxy paths never returned to clients (verified: friendly 500 page with `leaks=false`).

## Filesystem / configuration exposure

- [ ] Root `.htaccess` denies direct access to `app/`, `database/`, `tests/`, `ai-service/` and dotfiles.
- [ ] `public/.htaccess` blocks dotfiles; `Options -Indexes` disables directory listing.
- [ ] `.env` gitignored; only `.env.example` is committed; no keys/credentials in source.
- [ ] `app/Logs/`, `ai-service/logs/` not web-accessible.

## AI service (server-to-server)

- [ ] FastAPI binds `127.0.0.1` only (never a public interface).
- [ ] `X-API-Key` required (fail-closed); constant-time comparison.
- [ ] No CORS / docs UI / static files; browsers cannot call it.
- [ ] SSRF guard: PHP AI client whitelists `AI_ALLOWED_HOSTS` (loopback only).
- [ ] Request bodies + auth headers never logged; structured errors only.
- [ ] Model artifacts SHA-256 verified at load; feature names validated strictly.
- [ ] AI responses are decision support only — not presented as diagnosis.

## Audit & privacy

- [ ] Append-only `audit_logs` captures actor, action, entity, IP, UA, method, path, old/new values.
- [ ] PHP→AI requests send only de-identified numeric feature vectors (`student_ref` opaque, never PII).
- [ ] Aggregate analytics (outbreak) exclude identities.
- [ ] Verification commands: `database/database_verify.php` → 15 PASS / 0 FAIL.

## Runbook (post-change re-run)

1. `php database\database_verify.php`
2. Re-run the committed suites (`tests/system/security.php`,
   `tests/system/functional_write.php`, `tests/system/ai_e2e.php`) after any
   code change, or run `composer test:all`.
3. Manual smoke: login as each role; attempt a cross-role URL; confirm friendly 500 page.
4. Confirm no secrets/stack traces in browser console/network when a 500 occurs.