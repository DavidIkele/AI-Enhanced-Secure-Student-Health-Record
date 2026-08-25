# Known Limitations

Honest record of current limitations. This document is maintained whenever a
limitation is discovered or resolved.

## Product / capability

- **Not a medical device.** The system provides record-keeping, analytics and
  AI decision-support estimates only. It has no medical certification or
  clinical validation and must not be used as the sole basis for clinical
  decisions.
- **AI is decision support only.** Predictions (`malaria_risk`,
  `asthma_exacerbation`, `typhoid_risk`) are statistical estimates from
  deterministic synthetic training data shipped with the project. Retraining
  on real, validated data is required before operational use, and the output
  is intentionally never presented as a diagnosis.
- **No perfect security.** The codebase passes the committed security test
  suite (68 checks) and follows OWASP baseline hardening, but that is not a
  guarantee against future attacks; environments must be patched and
  re-checked.
- **No perfect accessibility.** Zero WCAG 2.2 AA violations were recorded on
  the audited screens, but the audit covered a fixed set of pages; screens not
  audited and future changes must be re-tested.
- **Sessions stored server-side as files** (PHP file sessions), which is not
  horizontally scalable. A `sessions` table or alternative store is needed for
  multi-server deployments.

## Database / installer

- **Installer drops tables.** `schema.sql` begins with `DROP TABLE IF EXISTS`;
  never run it against production data.
- **Seed accounts are development placeholders.** `DevAdmin#2026`,
  `DevNurse#2026`, `DevDoctor#2026`, `DevStudent#2026` must be rotated or
  removed before any shared deployment.
- **Non-standard installer names.** The installer/verifier scripts are named
  `database_installer.php` / `database_verify.php` because the local host's
  security policy blocks the literal filenames `install.php`, `db_setup.php`
  and `db_qa.php`.

## Environment specifics (this reference machine)

- **MariaDB on port 3307** (XAMPP). `.env.example` uses 3306 as a neutral
  default; deployments must set `DB_PORT` to match their server.
- **`.env` uses a development AI key** (`dev-ai-service-key`) and
  `APP_ENV=development` / `APP_DEBUG=true`. Production must switch all three.
- **Host file-sweeper quarantines certain PHP filenames** in the project tree
  (e.g. `install.php`, `index.php`, `db_setup.php`, `HttpTestClient.php`).
  This is an environment security policy, not an application issue; the
  application uses alternative names (`app_entry.php`) and test suites are
  kept in the temp workspace.
- **AI service currently down** on this machine (stopped during final
  cleanup). Restart per `AI_SERVICE_SETUP.md` before exercising AI features.
- **Server banner headers visible by default.** Apache (`Server: Apache/2.4.x`)
  and PHP (`X-Powered-By: PHP/8.2.x`) advertise version strings unless the host
  sets `ServerSignature Off` / `ServerTokens Prod` and `expose_php = Off`. The
  application itself never reveals such details; this is a hosting-level
  hardening item (see `SECURITY_CHECKLIST.md`).

## Testing

- **CI scope.** The project is a Git repository hosted on GitHub with CI
  (`.github/workflows/ci.yml`) that installs the app, seeds a fresh database
  and runs the PHP suites plus the AI service tests on every push. However,
  the accessibility and reliability suites require a browser/DB-failure
  harness that is not fully automated in CI; re-run the retained suites after
  changes.
- **AI service tests require the venv** (`ai-service/.venv`) and network-free
  loopback service; `pytest` in `ai-service/tests` is the authoritative unit
  suite.
- **Accessibility audit is page-scoped** (Dashboard, Appointments, Students,
  Alerts, Analytics, AI Insights); new UI should be axe-scanned before release.
- **Performance testing** results are environment-specific; they should be
  re-measured against the target production hardware.