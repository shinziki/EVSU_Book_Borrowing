# Configuration reference — EVSU Book Borrowing System

Use this document when re-deploying on a new campus server. Pair it with the latest files in `backups/deployment/` (schema, config snapshots, and this doc).

## Application files

| Path | Purpose |
|------|---------|
| `config/db_connect.php` | MySQL host, database name, username, password, timezone |
| `config/mail_config.php` | SMTP host, port, encryption, credentials, from address |
| `config/backup_config.php` | Backup root path (outside web root recommended), optional IP allowlist |
| `config/functions.php` | Core business logic, permissions, penalties, notifications |
| `config/mailer.php` | Email sending (PHPMailer / file fallback) |
| `config/report_helpers.php` | PDF report generation |
| `config/mail_helpers.php` | Mail config normalization |

## Database

| Item | Purpose |
|------|---------|
| Database name | Default: `coffee_prince_library` (set in `db_connect.php`) |
| `config/coffee_prince_library.sql` | Baseline schema reference in the repository |
| `backups/deployment/schema/schema_YYYY-MM-DD.sql` | Live schema-only export (no row data) |
| `backups/database/backup_YYYY-MM-DD.sql` | Full daily data + structure backup |

## PHP / server requirements

- PHP 7.4+ (8.x recommended)
- MySQL 5.7+ or MariaDB 10.4+
- Extensions: `pdo_mysql`, `mbstring`, `json`
- Optional: `mysqldump` in PATH for faster backups
- Apache `mod_rewrite` not required; `.htaccess` denies direct access to `backups/`

## Writable directories

Ensure the web server user can write to:

- `backups/` (or the path set in `config/backup_config.php`)
- `logs/`
- `uploads/profile_images/`
- `emails/` (if file-based mail fallback is used)

## Security notes

- Do not commit `config/backup_config.php` or `config/mail_config.php` with real passwords to public repos.
- Deployment config snapshots in `backups/deployment/config/` store **redacted** passwords; re-enter secrets on the new server.
- Restrict backup downloads to administrators via the application; optionally set `allowed_download_ips` in `backup_config.php`.

## Timezone

- PHP: `Asia/Manila` in `config/db_connect.php`
- MySQL session: `SET time_zone = '+08:00'`
