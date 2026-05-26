<?php
// Direct table creation script
require_once 'config/db_connect.php';

// Create OTP table
$createOtpTable = "CREATE TABLE IF NOT EXISTS `otp_verifications` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `otp_code` varchar(6) NOT NULL,
    `expires_at` datetime NOT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Create activity log table
$createActivityLogTable = "CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) DEFAULT NULL,
    `action` varchar(100) NOT NULL,
    `description` text,
    `ip_address` varchar(45) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$result1 = $pdo->exec($createOtpTable);
$result2 = $pdo->exec($createActivityLogTable);

echo "Tables created successfully. OTP table: $result1, Activity log table: $result2"; 