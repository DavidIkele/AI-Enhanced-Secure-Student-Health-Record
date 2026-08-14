# Administrator Guide

Guide for system administrators operating the Student Health Record System.

## Roles overview

| Role | Scope |
|---|---|
| `admin` | Full administrative access; limited clinical data by policy. Can view audit logs, run analytics, view system health, broadcast system notifications, manage users/roles (permission hooks), approve appointments. |
| `staff` | Healthcare staff (nurse / doctor): clinical data responsibilities — manage records, visits, diagnoses, treatments, medications, vitals, request/approve appointments, view analytics, request AI decision support, send health alerts. |
| `student` | Access to own records only: view own profile/records/insights, request appointments, notifications. |

Access is enforced per-route by `AuthMiddleware` (logged-in), `PermissionMiddleware::require(...)` (permission), and `RoleMiddleware::oneOf('admin')` (role boundary). Interesting endpoint mappings are listed in `APP.md`/routes (`app/Routes/web.php`).

## Logging in

- Browse to the base URL (e.g. `http://localhost/<project>/public/`) and log in
  with a seeded development account (see `DATABASE_SETUP.md`).
- Failed login attempts are rate-limited and lock the account after
  `RATE_LIMIT_ATTEMPTS` (default 5) failures within `RATE_LIMIT_WINDOW`
  (default 300 s). Cleared by successful login or a new lockout window.

## Key screens

### Dashboard
Role-aware landing page after login. Shows relevant summaries.

### Records (`/records`, `/records/{studentId}`)
View/manage health records by student. Staff with `records.manage` can edit
profiles, add medical history, record clinic visits. Ownership checks enforced.

### Appointments (`/appointments`)
- Students request appointments for a staff member; staff/admin approve or
  reject; reschedule and cancel available.
- `GET /appointments/availability` returns free slots (used by the new
  appointment form).
- Concurrency safe: two simultaneous requests for the same slot produce
  exactly one appointment (verified in PROMPT 18).

### Analytics
- `/analytics/visits` — visit history analytics (staff/admin).
- `/analytics/outbreaks` — illness-pattern / outbreak flagging (staff/admin);
  `POST /analytics/outbreaks/run` requires `analytics.manage`.

### AI decision support
- `/system/ai/health` — AI service status (permission `analytics.view`).
- `POST /records/{studentId}/predictions/{type}` — decision-support prediction
  (permission `records.manage`). Reference types: `malaria_risk`,
  `asthma_exacerbation`, `typhoid_risk`.
- Always presented as decision support, not a diagnosis.

### Insights
- Staff generate personalized health insights for students.
- Students mark insights read/dismiss from their profile.

### Notifications
- `/notifications` inbox for the logged-in user.
- Staff (`alerts.manage`) can send a targeted health alert to a student.
- Admin (`users.manage`) can broadcast a system announcement.

### Administrative area (`/admin/area` + `/admin/audit`)
- `/admin/area` is admin-only.
- `/admin/audit` shows the append-only audit log (`audit.view`). Audit rows
  record who did what (`user_id`, action, entity, IP, UA, method, path,
  old/new values) for accountability.

### System health (`/system/health`)
- Public, minimal page. DB + AI service status as an administrator.

## Security behaviour admins should know

- Passwords hashed with Argon2id (fallback bcrypt); never stored plain.
- Sessions: regenerated on login; idle timeout default 30 min; HttpOnly +
  Secure (auto over HTTPS) cookies; SameSite enforced.
- CSRF: every state-changing POST requires a token.
- Role/permission middleware enforces boundaries; ownership checks in
  controllers (prevent IDOR/BOLA).
- Rate limiting + lockout on login.
- Security headers + CSP (script-src self + jsdelivr CDN for vendored libs).
- `display_errors=0`; production shows generic error pages without stack
  traces / internal paths. Server-side error logs are written to `app/Logs/`.
- Audit log is append-only and cannot be truncated by end users.
- `.htaccess` blocks direct access to `.env`, `app/`, `database/`, `tests/`,
  `ai-service/`.

## Backup / recovery

Run and verify backups per `BACKUP_PROCEDURE.md`; restore per
`RECOVERY_PROCEDURE.md`. Set up scheduled `mysqldump` jobs and periodic restore
drills. The `audit_logs` table is the authoritative record — retain its backups
for the required legal retention period.

## AI service supervision

- Keep `ai-service` running (loopback, port 8000) under a supervisor; the app
  degrades gracefully when it is down but AI features go unavailable.
- Restart the service after changing model artifacts; verify via
  `/system/ai/health`.

## Production hardening before go-live

1. `.env`: `APP_ENV=production`, `APP_DEBUG=false`.
2. Strong DB credentials and AI key (see `ENVIRONMENT_CONFIGURATION.md`).
3. HTTPS + `COOKIE_SECURE=auto`.
4. Remove or rotate all seeded dev accounts; add real users with
   least privilege.
5. Run `SECURITY_CHECKLIST.md` checks.