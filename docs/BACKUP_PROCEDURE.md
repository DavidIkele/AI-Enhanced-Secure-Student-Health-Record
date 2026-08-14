# Backup Procedure

Canonical backup steps for the Student Health Record System. Run as a
scheduled task (daily recommended) by an operator with database access.

## What to back up

1. **Database** — all data: users, health records, clinical data,
   appointments, AI predictions, notifications, audit logs.
2. **Application files** — code is version-controlled / static; back up the
   deployed folder only if it is not under version control.
3. **AI model manifests** — `ai-service/models/registry.json` (the only
   committed model artifact), plus trained `model.joblib` files if they should
   survive a re-install.
4. **Configuration** — `.env` (contains credentials; back it up securely and
   keep it out of source control).

## 1. Database backup (mysqldump)

Reference environment: MariaDB on `127.0.0.1:3307`, user `root`, db
`student_health`. Adjust `-P` and credentials as needed.

```powershell
# Full logical backup with routines and triggers, UTF-8 safe:
C:\xampp\mysql\bin\mysqldump.exe -h 127.0.0.1 -P 3307 -u root `
  --single-transaction --routines --triggers --skip-lock-tables `
  student_health > D:\backups\srms\db_YYYYMMDD_HHMMSS.sql
```

- `--single-transaction` gives a consistent snapshot without locking tables
  (InnoDB).
- `--skip-lock-tables` avoids blocking the app during backup.
- Keep the `.sql` file compressed:

```powershell
Compress-Archive -Path db_YYYYMMDD_HHMMSS.sql -DestinationPath db_YYYYMMDD_HHMMSS.zip
```

## 2. Application files

```powershell
Copy-Item -Recurse "C:\xampp\htdocs\<project>" D:\backups\srms\app_YYYYMMDD\
```

Exclude `ai-service\.venv` and `app\Logs\` transient files if desired (logs are
regenerated; keep the last N days separately if audit/legal retention requires).

> The `audit_logs` table is the authoritative append-only audit record; keep
> database backups containing it for the required retention period.

## 3. AI artifacts

```powershell
Copy-Item "ai-service\models\registry.json" D:\backups\srms\ai_manifest_YYYYMMDD.json
# If trained models should survive re-install:
Copy-Item -Recurse "ai-service\models" D:\backups\srms\ai_models_YYYYMMDD\
```

## 4. Configuration

```powershell
Copy-Item ".env" D:\backups\srms\env_YYYYMMDD.txt   # secure storage only
```

## 5. Retention & verification

- Keep at least 7 daily backups and 4 weekly backups on a different drive /
  media.
- **Verify backups**: restore the most recent backup into a scratch database
  monthly and run `database_verify.php` against it plus a sample of rows.

## 6. MySQL Enterprise / MariaDB backup alternative

`mariadb-backup` (physical backup) may be used instead of mysqldump for large
databases; the logical dump is sufficient and preferred for this system's size.