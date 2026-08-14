# Recovery Procedure

Steps to restore the Student Health Record System after data loss or
corruption. Always restore into a controlled, tested sequence: stop the app,
restore the database, restore files, restart services, verify.

## 1. Restore the database

Database restores use the `mysql` client and a logical dump taken per
`BACKUP_PROCEDURE.md`.

```powershell
# Create the database if it was dropped:
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root -e "CREATE DATABASE student_health CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Restore from the dump:
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root student_health < D:\backups\srms\db_YYYYMMDD_HHMMSS.sql
```

The dump contains `DROP TABLE IF EXISTS` in dependency order, so restoring over
an existing `student_health` re-installs cleanly.

## 2. Restore application files

```powershell
Copy-Item -Recurse D:\backups\srms\app_YYYYMMDD\<project> "C:\xampp\htdocs\<project>"
```

Preserve permissions and exclude nothing required. If `public/assets` vendor
files or `app/` were restored from the backup, ensure web-readable permissions.

## 3. Restore configuration

```powershell
Copy-Item D:\backups\srms\env_YYYYMMDD.txt .env
```

Ensure `.env` matches the current database credentials on this host
(`DB_PORT` especially — the reference MariaDB uses 3307).

## 4. Restore AI service state (if recovery includes it)

```powershell
Copy-Item -Recurse D:\backups\srms\ai_models_YYYYMMDD "ai-service\models"
# ensure ai-service\.env exists with the current AI_API_KEY
```

## 5. Restart services in order

1. MariaDB/MySQL (XAMPP Control Panel).
2. Apache.
3. AI service (only if AI is enabled):

```powershell
cd ai-service
.venv\Scripts\python run.py
```

> Note: if a previous `mysqld` process was force-killed, InnoDB must run
> crash recovery. Stop the service cleanly (XAMPP stop) and restart it; do not
> skip crash recovery — recent table data reads may stall until recovery
> completes.

## 6. Verify the recovery

```powershell
C:\xampp\php\php.exe database\database_verify.php   # expect 15 PASS / 0 FAIL
C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root student_health -e "SELECT COUNT(*) AS users FROM users;"
```

- Log in as each role; confirm records, appointments, analytics and audit log
  screens render.
- As administrator, open `system/health` and confirm DB + AI status.
- Confirm a recent audit-log row exists for the restore event.

## 7. Post-recovery

- Record the restore event, source backup file, and timestamp.
- Re-run the most recent backup immediately so the archives are current.
- Update `docs/KNOWN_LIMITATIONS.md` if anything did not restore cleanly.