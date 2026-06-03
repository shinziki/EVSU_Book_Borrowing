<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';
require_once 'config/backup_helpers.php';

requireLogin();

if (!isAdmin()) {
    http_response_code(403);
    die('Access denied.');
}

$filename = basename($_GET['file'] ?? '');
$path = resolveBackupFilePath($filename);

if ($path === null) {
    http_response_code(404);
    die('Backup file not found.');
}

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');

readfile($path);
exit;
