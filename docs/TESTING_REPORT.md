# Testing Report

Consolidated testing summary for the AI-Enhanced Secure Web-Based Student
Health Record Management System. Full detail lives in
`tests/p18/PROMPT_18_REPORT.md`.

> Scope note: this report summarises the checks executed in this project's
> testing cycle. It does **not** constitute medical certification or clinical
> validation.

## Summary

| Area | Tests | Passed | Failed |
|------|------:|-------:|-------:|
| Functional | 53 | 53 | 0 |
| Security | 68 | 68 | 0 |
| AI (unit, pytest) | 45 | 45 | 0 |
| AI (end-to-end) | 15 | 15 | 0 |
| Accessibility | 8 + axe scan | 8 | 0 (0 WCAG 2.2 AA violations) |
| Reliability | 21 | 21 | 0 |
| **Total** | **210** | **210** | **0** |

All results are measured from live executions against a running instance (Apache
+ PHP + MySQL/MariaDB), plus the AI service where applicable; none were inferred
or fabricated.

## Functional (53 PASS / 0 FAIL)

Through the real HTTP stack, covering index pages, forms, writes and
navigation for every module:

- Dashboard (role-aware), Appointments (list/filter/calendar/availability/
  request/booking/reschedule), Clinic Visits, Diagnoses, Medical History,
  Medications, Treatments, Vital Signs, Notifications, Alerts, Analytics,
  AI Insights/predictions, Staff, Students (list/search), Profile
  (view/update/session persistence), Authentication (login across roles,
  logout).
- Write-path tests: appointment creation persisted with correct
  status/reason/staff; audit log row created; failed admin access logged as a
  security event; sensitive files (DB config, seed, router internals) return
  403; pages still render after writes; profile update persists after
  re-login; logout terminates the session.

## Security (68 PASS / 0 FAIL)

- **SQL injection** — parameterized queries; payloads across search/filter/sort/
  IDs blocked; no leaking 500s.
- **XSS** — output escaped (reflected + stored payloads); no stored XSS.
- **CSRF** — missing/wrong token rejected; all state-changing POSTs protected;
  token rotation.
- **IDOR/BOLA** — cross-student access and numeric ID tampering forbidden.
- **Privilege escalation** — student→admin, student→staff,
  unauthenticated→admin all forbidden and logged as security events.
- **Authentication bypass** — protected routes require auth; role-less session
  rejected.
- **Session attacks** — fixation prevented (new session id on login); cookies
  HttpOnly + Secure + SameSite.
- **Rate limiting** — login locked after threshold; locked account blocked;
  unlock verified.
- **SSRF** — AI-service fetch allow-list rejects internal + external targets.
- **Information disclosure** — friendly error pages (`leaks=false`), no stack
  traces, `.env`/config exposure blocked, no debug output.

## AI (60 PASS / 0 FAIL)

- Frontend/AI integration suites cover preprocessing, model validation,
  predictions, evaluation metrics, invalid-input handling, data-leakage
  checks, reproducibility, API contract (`/health`, `/predict`, `/metrics`),
  and error contract (AI down → structured `{"error": ...}`).
- 45 pytest (unit) + 15 end-to-end checks, all passing.

## Accessibility (PASS)

- axe-core scan of Dashboard, Appointments, Students, Alerts, Analytics,
  AI Insights: **0 WCAG 2.2 AA violations**.
- Keyboard navigation fully functional; form labels present; data tables use
  proper headers; charts expose text summaries; errors conveyed as text (not
  colour-only); notifications unread state conveyed with text.

## Reliability (21 PASS / 0 FAIL)

- **Invalid requests**: GET on POST-only route → 404 (not fatal); oversized
  page clamped; negative/non-numeric page handled; malformed/impossible date
  handled; garbage availability params → 422; oversized POST body handled.
- **AI-service failure**: `/health` unavailable; predictions degrade to
  `{"status":"failed"}` with a safe message; response within timeout (4.3s
  measured); app fully usable with AI down.
- **Database failure**: stop `mysqld` → friendly error page
  (`friendly=true`, `leaks=false`, no stack/SQL details); restart → app fully
  functional.
- **Concurrency**: two students race to book the same slot → exactly one
  appointment row; losing request receives a conflict message. Re-verified.

## Failed-then-fixed items (transparency)

- The concurrency test's transient `rows=0` was traced to a **test-harness
  bug** (wrong test-client path in the child process), not an application
  defect; fixed and re-verified `rows=1`.
- A force-killed `mysqld` during DB-failure testing left InnoDB with stalled
  table-data reads until crash recovery ran on a clean restart — an
  environment artifact, not an application issue.

## Databases of evidence

- `tests/p18/PROMPT_18_REPORT.md` — full PROMPT 18 report.
- Re-runnable suites kept in the workspace test directory
  (`reliability.php`, `concurrency.php`, `functional.php`,
  `functional_write.php`, `security.php`, `ai_e2e.php`, `HttpTestClient.php`).
- `database/database_verify.php` → 15 PASS / 0 FAIL.

## Reproducing

1. Start Apache + MySQL; configure `.env`.
2. Install DB (`database/database_installer.php`) and verify
   (`database/database_verify.php`).
3. Start the AI service (see `AI_SERVICE_SETUP.md`) if testing AI paths.
4. Run the retained suites from the workspace test directory against the live URL.