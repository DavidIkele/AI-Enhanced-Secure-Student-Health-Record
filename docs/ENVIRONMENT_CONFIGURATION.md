# Environment Configuration Guide

All application configuration lives in a single `.env` file at the project
root. Copy `.env.example` to `.env` and adjust the values. The file is loaded
by `app/Core/Environment.php` and read by `app/Config/config.php`.

Types: booleans accept `true`/`false`, `1`/`0`, `yes`/`no`.

## Application

| Variable | Default | Purpose |
|---|---|---|
| `APP_NAME` | full product name | Display name used in views. |
| `APP_ENV` | `development` | `development` or `production`. Affects error display and diagnostics. **Must be `production` for live deployments.** |
| `APP_DEBUG` | `true` | When `false`, generic error pages are shown and stack traces / internal paths are never exposed to the browser. **Must be `false` in production.** |
| `APP_URL` | (empty) | Base URL of the `public/` document root. Empty = auto-detect. |

## Session

| Variable | Default | Purpose |
|---|---|---|
| `SESSION_NAME` | `srms_session` | PHP session cookie name. |
| `SESSION_LIFETIME` | `0` | Cookie lifetime in seconds (`0` = browser session). |
| `SESSION_TIMEOUT` | `30` | Idle session timeout in minutes (`0` disables). |

## Database

| Variable | Default | Purpose |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Database host. |
| `DB_PORT` | `3306` | Database port. **XAMPP MariaDB on the reference machine uses `3307`.** |
| `DB_NAME` | `student_health` | Database name. |
| `DB_USERNAME` | `root` | Database user. |
| `DB_PASSWORD` | (empty) | Database password. Production must use a strong non-empty password. |
| `DB_CHARSET` | `utf8mb4` | Connection charset. |

## Cookie / security behaviour

| Variable | Default | Purpose |
|---|---|---|
| `COOKIE_SECURE` | `auto` | `auto` = set the Secure flag only over HTTPS; `1` = always; `0` = never. |
| `RATE_LIMIT_ATTEMPTS` | `5` | Failed-login attempts allowed before lockout. |
| `RATE_LIMIT_WINDOW` | `300` | Window (seconds) in which attempts are counted. |
| `LOCKOUT_HOURS` | `1` | Hours an account stays locked after exceeding the limit. |

## AI decision-support service

| Variable | Default | Purpose |
|---|---|---|
| `AI_ENABLED` | `false` | Toggles AI prediction requests. When `false` the rest of the app is fully usable. |
| `AI_BASE_URL` | `http://127.0.0.1:8000` | Base URL of the local FastAPI service. Loopback only. |
| `AI_API_KEY` | `change-me-ai-service-key` | Shared secret. Must match the key the AI service is started with. Production: strong random value (e.g. `python -c "import secrets; print(secrets.token_urlsafe(32))"`). |
| `AI_ALLOWED_HOSTS` | `127.0.0.1,localhost,::1` | Whitelist for the AI host (SSRF guard). Keep loopback-only. |
| `AI_CONNECT_TIMEOUT` | `3` | Connection timeout (seconds). |
| `AI_TIMEOUT` | `8` | Total request timeout (seconds). |
| `AI_RETRIES` | `1` | Retries on transient failure. |
| `AI_MAX_REQUEST_BYTES` | `8192` | Max request body accepted by the PHP→AI client. |

The AI service itself reads its own separated `.env` inside `ai-service/` (see
`AI_SERVICE_SETUP.md`); it does **not** read the root `.env`.

## Production checklist

1. `APP_ENV=production`, `APP_DEBUG=false`.
2. Strong, non-default `DB_*` credentials.
3. `AI_ENABLED=true/true` + strong `AI_API_KEY` if the service is deployed;
   otherwise `AI_ENABLED=false`.
4. HTTPS in front of the app so `COOKIE_SECURE=auto` sets the Secure flag.
5. Never commit `.env` (it is gitignored). Commit only `.env.example`.

## Development vs production drift notes

- The reference machine runs MariaDB on port `3307`; the `.env.example` keeps
  `3306` as the MySQL-platform-neutral default. Set `DB_PORT` to match your
  server.
- `.env` currently uses the development key value `dev-ai-service-key`; this is
  a development placeholder and must be replaced in any shared environment.