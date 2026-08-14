# Installation Guide

This guide describes a clean installation of the **AI-Enhanced Secure Web-Based
Student Health Record Management System**.

> Production readiness notice: this is a reference deployment for a real-world
> case. It is **not** a medical device, and has **no** medical certification or
> clinical validation. Do not rely on it for clinical decision-making without
> appropriate review and approval.

## 1. Prerequisites

| Component | Version | Notes |
|---|---|---|
| PHP | 8.2+ (8.3 recommended) | `pdo_mysql`, `openssl`, `mbstring` extensions enabled |
| Web server | Apache (XAMPP) | `.htaccess` + `mod_rewrite` required |
| Database | MySQL 8.x or MariaDB 10.4+ | `utf8mb4` support required |
| Python | 3.12+ | Only required for the optional AI decision-support service |

The reference development environment used for this project is **XAMPP with
MariaDB on TCP port 3307**. Standard MySQL installations typically use port
3306; see `ENVIRONMENT_CONFIGURATION.md`.

## 2. Obtain the files

Copy the project folder (which contains `app/`, `public/`, `database/`,
`ai-service/`, `tests/`, `docs/`, `.env.example`) to the web root.

For Apache under XAMPP the project is served from the XAMPP `htdocs` folder:

```
C:\xampp\htdocs\<project-folder>\
```

The web-accessible entry point is the `public/` sub-folder. It must be
reachable at your configured base URL, e.g.:

```
http://localhost/<project-folder>/public/
```

`public/app_entry.php` is the front controller. The `.htaccess` files route all
non-file requests to it and deny direct access to `.env`, `app/`, `database/`,
`tests/` and `ai-service/`.

## 3. Configure the environment

1. Copy `.env.example` to `.env`.
2. Open `.env` and set at minimum:
   - `APP_ENV=development` during setup, `production` when going live.
   - `APP_DEBUG=true` during setup, `false` when going live.
   - Correct `DB_*` values for your database (host, port, name, user, password).
   - `AI_ENABLED=false` unless you have started the AI service (see
     `AI_SERVICE_SETUP.md`).

See `ENVIRONMENT_CONFIGURATION.md` for a full explanation of every variable.

## 4. Set up the database

Follow `DATABASE_SETUP.md`. In short:

```powershell
C:\xampp\php\php.exe database\database_installer.php
```

This creates the `student_health` database (if missing), applies `schema.sql`
(22 tables, FKs, indexes, unique constraints) and seeds development users.

Verify with:

```powershell
C:\xampp\php\php.exe database\database_verify.php
```

Expected result: `15 PASS / 0 FAIL`.

## 5. Start the web server

Start Apache (and MariaDB) through the XAMPP Control Panel, then open the base
URL in a browser. You should see the login page.

### Development accounts (seed data — change before any shared deployment)

| Username | Role | Development password |
|---|---|---|
| `admin` | Administrator | `DevAdmin#2026` |
| `nurse` | Healthcare Staff | `DevNurse#2026` |
| `doctor` | Healthcare Staff | `DevDoctor#2026` |
| `ade` | Student | `DevStudent#2026` |
| `bala` | Student | `DevStudent#2026` |

## 6. (Optional) Start the AI decision-support service

The core system is fully usable with `AI_ENABLED=false`. To enable AI-powered
decision support:

1. Follow `AI_SERVICE_SETUP.md` to install, configure and start the FastAPI
   service.
2. Set `AI_ENABLED=true` and the matching `AI_API_KEY` in `.env`.
3. Restart Apache / refresh the page.

## 7. Verify the installation

- Log in as each role and confirm the correct landing page.
- Visit `system/health` as an administrator to confirm connectivity, AI service
  status, and recent audit-log activity.
- Confirm appointments, records, analytics and AI prediction screens render
  without error.

## 8. Production checklist

Before any live deployment:

- `APP_ENV=production` and `APP_DEBUG=false`.
- Strong, unique database credentials (never the empty dev root password).
- A strong `AI_API_KEY` (e.g. `python -c "import secrets; print(secrets.token_urlsafe(32))"`)
  if AI is enabled, and a loopback-only `AI_BASE_URL`.
- HTTPS in front of the app (`COOKIE_SECURE=auto` will then set the Secure flag).
- Default seed accounts removed or passwords rotated.
- Run the security checks in `SECURITY_CHECKLIST.md`.
