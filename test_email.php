<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success' => false, 'message' => 'Direct access not allowed']));
}

require_once __DIR__ . '/config/phpmailer_loader.php';
require_once __DIR__ . '/config/mail_helpers.php';

$config = [
    'smtp_host' => trim($_POST['smtp_host'] ?? ''),
    'smtp_port' => trim($_POST['smtp_port'] ?? '587'),
    'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
    'smtp_username' => trim($_POST['smtp_username'] ?? ''),
    'smtp_password' => $_POST['smtp_password'] ?? '',
];

if (empty($config['smtp_host']) || empty($config['smtp_username'])) {
    exit(json_encode(['success' => false, 'message' => 'SMTP host and username are required']));
}

$result = testSmtpConnection($config);
echo json_encode($result);
