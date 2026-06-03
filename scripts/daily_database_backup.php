<?php
/**
 * Daily database backup — run via cron or Windows Task Scheduler.
 *
 * Example (Linux cron at 2:00 AM):
 *   0 2 * * * /usr/bin/php /path/to/EVSU_Book_Borrowing/scripts/daily_database_backup.php
 *
 * Example (Windows Task Scheduler):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\EVSU_Book_Borrowing\scripts\daily_database_backup.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config/db_connect.php';
require_once $root . '/config/backup_helpers.php';

$result = createDatabaseBackup($pdo);
logDatabaseBackupRun($result, 'cron');

if ($result['success']) {
    echo $result['message'] . PHP_EOL;
    exit(0);
}

fwrite(STDERR, $result['message'] . PHP_EOL);
exit(1);
