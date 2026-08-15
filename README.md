# AI-Enhanced Secure Student Health Record Management System

A web-based student health record system built for university health centers, with
role-based access control, an audit trail, and an optional AI decision-support
service for early risk flagging (malaria, asthma exacerbation, typhoid).

The system is split into two independent parts on one machine:

- **Web portal** — PHP 8.3 / MySQL, no framework. Handles authentication, student
  records, appointments, analytics, and admin functions.
- **AI service** — a separate FastAPI (Python) microservice that the portal calls
  server-to-server for risk predictions. The browser never talks to it directly,
  and the portal works fully with the AI service turned off.

> Not a medical device. Predictions are statistical estimates from synthetic
> training data and are decision support only, never a diagnosis. See
> [`docs/KNOWN_LIMITATIONS.md`](docs/KNOWN_LIMITATIONS.md) for the full list of
> what this project does and doesn't guarantee.

## Features

- Role-based access (Student, Healthcare Staff, Admin) with permission middleware
- Argon2id password hashing (bcrypt fallback), enforced password policy
- CSRF protection on all state-changing requests, timing-safe token comparison
- Login rate limiting (per identifier and per IP) with account lockout after
  repeated failures, plus session fixation protection and idle timeout
- Full audit logging of authentication events and record access
- AI-powered risk prediction (malaria, asthma, typhoid) via a de-identified
  numeric feature vector sent to the AI service — no PII ever leaves the portal
- SSRF-guarded AI client: base URL is validated against an allow-list, requests
  fail closed on any unexpected response
- Appointments, visit analytics, and outbreak-alert dashboards
- WCAG 2.2 AA accessibility pass on core screens

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 (custom router, PSR-4 autoloading), PDO with prepared statements throughout |
| Database | MySQL 8.x (MariaDB 10.4+ also supported) |
| AI service | Python 3.12+, FastAPI |
| Frontend | Bootstrap, Chart.js, vanilla JS |
| Deployment | Docker / docker-compose |

## Getting started

Full setup instructions, including database installation and the optional AI
service, are in [`docs/INSTALLATION.md`](docs/INSTALLATION.md). Short version:

```bash
# 1. copy env config and set your own values
cp .env.example .env

# 2. install the database schema and seed data
php database/database_installer.php

# 3. verify the install
php database/database_verify.php

# 4. serve public/ with Apache or PHP's built-in server
php -S localhost:8000 -t public
```

Default development accounts (rotate or remove before any shared deployment)
are listed in `docs/INSTALLATION.md`.

To enable the AI service, see [`docs/AI_SERVICE_SETUP.md`](docs/AI_SERVICE_SETUP.md).
The portal runs fine with `AI_ENABLED=false`.

## Documentation

- [`docs/INSTALLATION.md`](docs/INSTALLATION.md) — full setup walkthrough
- [`docs/DATABASE_SETUP.md`](docs/DATABASE_SETUP.md) — schema and seeding
- [`docs/AI_SERVICE_SETUP.md`](docs/AI_SERVICE_SETUP.md) — FastAPI service setup
- [`docs/SECURITY_CHECKLIST.md`](docs/SECURITY_CHECKLIST.md) — pre-deployment security review
- [`docs/ADMINISTRATOR_GUIDE.md`](docs/ADMINISTRATOR_GUIDE.md) / [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) — usage guides
- [`docs/KNOWN_LIMITATIONS.md`](docs/KNOWN_LIMITATIONS.md) — honest scope and limitations
- [`database/ERD.md`](database/ERD.md) — entity relationship diagram

## Testing

- `tests/router_smoke.php` — basic routing smoke test
- `tests/p18/security.php` — security test suite covering SQLi, XSS, CSRF,
  IDOR, privilege escalation, auth bypass, session attacks, and SSRF
- `ai-service/tests/` — pytest suite for the AI service (`pytest ai-service/tests`)

## License

MIT — see [`LICENSE`](LICENSE).

