# Deployment Guide

This project is a traditional **PHP + MySQL + Apache** application with a
separate **Python FastAPI** AI service. It is **not a Node.js / static site**,
so it cannot run on Vercel's serverless platform as-is (see
[Why not Vercel](#why-not-vercel)).

You can run it anywhere that provides **Apache/PHP, MySQL, and a Python
process** — locally via the included Docker Compose stack, or on a VPS /
shared host / PaaS that offers PHP and MySQL.

---

## Quick start with Docker Compose (any machine with Docker)

The repo ships with a full stack: `db` (MySQL 8), `web` (PHP 8.3 + Apache) and
`ai` (FastAPI). The AI service is reachable only on the internal compose
network — its port is not published to the host.

```bash
# 1. Configure secrets (required - never commit the real .env)
cp .env.example .env
#    edit .env and set a strong value for DB_PASSWORD and AI_API_KEY
#    (AI_API_KEY must match on both the PHP app and the AI service)

# 2. Build and start
docker compose up --build

# 3. Open
open http://localhost:8080
```

The database is initialised automatically on first run from
`database/schema.sql` (schema) and `database/seed_data.sql` (dev seed data).

### Verify the AI service is wired up

```bash
# From inside the ai container (its port is not published to the host):
docker compose exec ai python -c "import urllib.request;print(urllib.request.urlopen('http://127.0.0.1:8000/health').read())"
# In the app: log in as admin, open System -> Health and confirm
# "AI service: available".
```

---

## Deploying to a PHP + MySQL host (e.g. Hostinger / Namecheap / cPanel)

Vercel cannot host PHP. Use a host that supports Apache/PHP/MySQL instead.

1. Push the repo to GitHub (see below).
2. Create a MySQL database and user on the host; import the schema and seed:
   ```
   mysql -h <host> -u <user> -p <dbname> < database/schema.sql
   mysql -h <host> -u <user> -p <dbname> < database/seed_data.sql
   ```
   Or upload both via phpMyAdmin.
3. Upload the project files so the **document root points at `public/`**.
   - cPanel: set the "Document Root" for the domain to `/public`.
   - `app/`, `database/`, `tests/` and `ai-service/` must sit **above** the
     web root (they are never served). The included `.htaccess` also denies
     web access to them as a belt-and-braces measure.
4. Create a `.env` on the server from `.env.example` with the production DB
   credentials and a strong `AI_API_KEY`.
5. Run the AI service as a background process on the same server (or a second
   VPS): see `docs/AI_SERVICE_SETUP.md`. Point `AI_BASE_URL` at it and set
   `AI_ALLOWED_HOSTS` to its hostname.
6. Terminate HTTPS at the load balancer / host and leave `COOKIE_SECURE=auto`.

---

## Deploying to a PaaS that supports PHP + MySQL (e.g. Railway, Render)

These platforms accept the repo directly (or via the Dockerfile):

- **Railway**: add three services — MySQL, the repo root (`Dockerfile`), and
  `ai-service` (`ai-service/Dockerfile`). Set the env vars from `.env.example`
  on each. Use the internal DB service hostname for `DB_HOST`, and the AI
  service's internal URL for `AI_BASE_URL` (add that host to
  `AI_ALLOWED_HOSTS`).
- **Render**: one "Web Service" for the PHP app, one for the AI service, plus
  a managed MySQL. Same env-var approach.

---

## Publishing to GitHub (safety checklist)

1. **Do not commit secrets.** `.gitignore` already excludes `.env`,
   `ai-service/.env`, `vendor/`, `node_modules/`, model artifacts, and logs.
   Keep real credentials out of `.env.example` too (it uses placeholders).
2. **Create the repo and push:**
   ```bash
   git add .
   git commit -m "Initial commit"
   git branch -M main
   git remote add origin https://github.com/<you>/<repo>.git
   git push -u origin main
   ```
3. **Verify nothing sensitive is staged** before pushing:
   ```bash
   git status
   git diff --cached --name-only
   ```
4. For a production deploy, generate fresh credentials and **rotate the demo
   seed users** (see `docs/DATABASE_SETUP.md`) — the dev accounts in
   `seed_data.sql` are placeholders only.

---

## Why not Vercel?

Vercel runs static/JAMstack sites and serverless functions. This project
requires:

- a persistent **PHP + Apache** runtime (`.htaccess`, `public/`, front
  controller),
- a persistent **MySQL** database,
- a long-running **FastAPI** process on a fixed port.

None of these fit Vercel's model. If you specifically need Vercel, the only
option is a separate static front-end that talks to a hosted API + DB — which
is a substantial rewrite, not a deployment step. The recommended path is one
of the PHP+MySQL options above.

---

## Environment reference (`.env.example`)

| Variable | Purpose |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection (least-privilege user) |
| `AI_ENABLED` | `true` enables AI integration; `false` runs without it |
| `AI_BASE_URL` | URL of the FastAPI service |
| `AI_API_KEY` | Shared key; must match `ai-service`'s key |
| `AI_ALLOWED_HOSTS` | SSRF guard allow-list for the AI base URL host |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` in any real deployment |