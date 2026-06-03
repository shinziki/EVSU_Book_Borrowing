# Server re-deployment guide — EVSU Book Borrowing System

This guide restores the system on a **new campus server** using separately stored backups.

## What to copy from the old server

1. **Application code** — entire project folder (or fresh clone + restore config).
2. **Full database backup** — `backups/database/backup_YYYY-MM-DD.sql` (most recent date).
3. **Deployment package** (updated daily with backups):
   - `backups/deployment/schema/schema_YYYY-MM-DD.sql` — structure only
   - `backups/deployment/config/config_snapshot_YYYY-MM-DD/` — redacted config copies + `manifest.json`
   - `backups/deployment/docs/` — `CONFIGURATION.md` and this file
4. **Uploads** — `uploads/profile_images/` if profile photos must be preserved.

## Recommended backup location (campus server)

Store backups **outside** the web root:

1. Copy `config/backup_config.sample.php` to `config/backup_config.php`.
2. Set `backup_root` to e.g. `D:\campus_data\evsu_library_backups` or `/var/lib/evsu_library/backups`.
3. Ensure only the web server / backup cron account can read and write that folder.
4. Schedule `scripts/daily_database_backup.php` daily (see Settings → Database Backup).

## New server setup steps

### 1. Install stack

- Apache (or IIS) + PHP + MySQL/MariaDB (XAMPP/WAMP or Linux packages).

### 2. Deploy application

- Place files under the web root (e.g. `htdocs/EVSU_Book_Borrowing`).
- Copy `config/backup_config.php` and point `backup_root` to the campus backup directory.

### 3. Create database

```sql
CREATE DATABASE coffee_prince_library CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### 4. Import data

Using phpMyAdmin or CLI, import the **latest full backup**:

```bash
mysql -u root -p coffee_prince_library < backup_YYYY-MM-DD.sql
```

For an **empty** install with structure only, use `schema_YYYY-MM-DD.sql` instead, then migrate data separately.

### 5. Restore configuration

1. Edit `config/db_connect.php` with new host, database name, user, and password.
2. Edit `config/mail_config.php` with campus SMTP settings (see Settings → Email in the app).
3. Compare with the latest `config_snapshot_YYYY-MM-DD` under deployment backups for reference.

### 6. Permissions and first login

- Ensure `backups/`, `logs/`, and `uploads/` are writable by the web server.
- Log in with an existing admin account from the restored database.
- Verify borrow/return, email, and scheduled backup in Settings.

### 7. Verify backups on the new server

- Run **Create Backup Now** in Settings → Database Backup.
- Confirm files appear under the configured `backup_root` and are not downloadable via direct URL.

## Scheduled daily backup

**Windows (Task Scheduler):**

```text
C:\xampp\php\php.exe C:\xampp\htdocs\EVSU_Book_Borrowing\scripts\daily_database_backup.php
```

**Linux (cron at 2:00 AM):**

```text
0 2 * * * /usr/bin/php /path/to/EVSU_Book_Borrowing/scripts/daily_database_backup.php
```

Each run creates:

- `database/backup_YYYY-MM-DD.sql` — full database
- `deployment/schema/schema_YYYY-MM-DD.sql` — schema only
- `deployment/config/config_snapshot_YYYY-MM-DD/` — config + manifest
- Copies of documentation under `deployment/docs/`

## Troubleshooting

| Issue | Action |
|-------|--------|
| Backup folder not writable | Fix NTFS/Linux permissions on `backup_root` |
| mysqldump not found | Install MySQL client tools; PDO fallback still works |
| Login fails after restore | Confirm `admins` table imported; reset password via DB if needed |
| Email not sending | Re-enter SMTP App Password in Settings → Email |

## Log file

`logs/database_backup.log` — records each automated or manual backup run.
