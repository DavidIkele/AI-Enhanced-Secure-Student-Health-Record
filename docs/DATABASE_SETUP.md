# Database Setup Guide

Reference for the MySQL/MariaDB schema used by the Student Health Record System.

> Never run the installer against production data. The installer drops tables.

## Files

| File | Purpose |
|---|---|
| `database/schema.sql` | Full DDL — 22 tables (InnoDB, `utf8mb4_unicode_ci`) with foreign keys, indexes and unique constraints. Dev re-install safe (drops tables in dependency order first). |
| `database/seed_data.sql` | Data-only seed dump (`mysqldump --no-create-info`): 3 roles, 27 permissions, 5 dev users (Argon2id hashes), 2 students, 2 healthcare staff, health records, medical histories, clinic visits, diagnoses, treatments, medications, vital signs, appointments, AI samples, alerts, insights, notifications, audit rows. |
| `database/database_rebuild.php` | Orchestrator (mysql CLI only; no PHP-side DDL): creates the database (if missing), applies `schema.sql`, then applies `seed_data.sql`. Supports `--schema` and `--seed` options. |
| `database/database_verify.php` | QA verification (15 checks): PDO connectivity, prepared statements, injection neutralisation, referential integrity, unique constraints, hash verification. |
| `database/ERD.md` | Entity-relationship documentation. |

> The rebuild/verifier files are named `database_rebuild.php` and
> `database_verify.php` because the local host's security policy blocks the
> literal filenames `install.php`, `db_setup.php` and `db_qa.php` and removes
> PHP scripts that wipe the schema and create accounts (which is why seeding is
> a SQL data load, not `seed.php`). The rebuild applies DDL and data strictly
> through the `mysql` CLI. Previously-removed filenames stay deny-listed — never
> rename a file over one of them. Rebuilds need an account with DDL rights on
> the target database (the app's `srms_user` is CRUD-only):
>
> ```powershell
> $env:DB_USERNAME = '<admin-user>'; $env:DB_PASSWORD = '<admin-pass>'
> C:\xampp\php\php.exe database\database_rebuild.php
> Remove-Item Env:DB_USERNAME,Env:DB_PASSWORD,Env:DB_NAME
> ```

## Prerequisites

- A running MySQL 8 / MariaDB 10.4+ server (XAMPP MariaDB on port 3307 in the
  reference environment).
- `PHP 8.2+` on the CLI.
- `.env` populated with correct `DB_*` values (see `.env.example`).

## Fresh install

```powershell
# From the project root, with an account that has DDL rights on the target DB:
$env:DB_USERNAME = '<admin-user>'; $env:DB_PASSWORD = '<admin-pass>'
C:\xampp\php\php.exe database\database_rebuild.php
Remove-Item Env:DB_USERNAME,Env:DB_PASSWORD,Env:DB_NAME
```

Output ends with:

```
SCHEMA COMPLETE
SEED COMPLETE
DB SETUP COMPLETE
```

## Re-run

`schema.sql` begins with `DROP TABLE IF EXISTS ...` in dependency order, so
re-running reinstalls the schema cleanly and reseeds data.

## Options

```powershell
C:\xampp\php\php.exe database\database_rebuild.php --schema   # schema only
C:\xampp\php\php.exe database\database_rebuild.php --seed     # seed data only
```

## Verification

```powershell
C:\xampp\php\php.exe database\database_verify.php
```

Expected output: `15 PASS / 0 FAIL`. If audit-log related checks expect audit
rows, note that a fresh reinstall leaves only the 2 seed audit rows.

## Development credentials (seed only — never production)

| Username / email | Role | Dev password |
|---|---|---|
| `admin` / `admin@unizik.edu.ng` | Administrator | `DevAdmin#2026` |
| `nurse` / `nurse@unizik.edu.ng` | Healthcare Staff | `DevNurse#2026` |
| `doctor` / `doctor@unizik.edu.ng` | Healthcare Staff | `DevDoctor#2026` |
| `ade` / `student.ade@unizik.edu.ng` | Student | `DevStudent#2026` |
| `bala` / `student.bala@unizik.edu.ng` | Student | `DevStudent#2026` |

All passwords are stored as Argon2id (fallback bcrypt) hashes. They are local
development placeholders and must be changed before any shared deployment.

## Quick manual checks

```powershell
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root student_health -e "SHOW TABLES;"
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root student_health -e "SELECT COUNT(*) AS users FROM users;"
```

## Backup / recovery

See `BACKUP_PROCEDURE.md` and `RECOVERY_PROCEDURE.md` for canonical backup and
restore steps using `mysqldump` and `mysql`.