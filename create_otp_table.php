<?php
// Create OTP verifications table for database-based 2FA
require_once 'config/db_connect.php';

$error = null;
$success = null;

try {
    // Check if table already exists
    $checkTableSql = "SHOW TABLES LIKE 'otp_verifications'";
    $checkResult = $pdo->query($checkTableSql);
    
    if ($checkResult->rowCount() > 0) {
        // Drop the existing table to recreate with new structure
        $dropSql = "DROP TABLE otp_verifications";
        $pdo->exec($dropSql);
        $success = "Existing otp_verifications table dropped for recreation.";
    }
    
    // Create the OTP verifications table with structure matching secureSystem
    $sql = "CREATE TABLE IF NOT EXISTS `otp_verifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `otp_code` varchar(6) NOT NULL,
        `expires_at` datetime NOT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
    $success = "OTP verifications table created successfully with structure matching secureSystem!";
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Check if activity_log table exists, create if it doesn't
try {
    $checkTableSql = "SHOW TABLES LIKE 'activity_log'";
    $checkResult = $pdo->query($checkTableSql);
    
    if ($checkResult->rowCount() > 0) {
        $logSuccess = "The activity_log table already exists in the database.";
    } else {
        // Create the activity log table
        $sql = "CREATE TABLE IF NOT EXISTS `activity_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `action` varchar(100) NOT NULL,
            `description` text,
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($sql);
        $logSuccess = "Activity log table created successfully!";
    }
} catch (PDOException $e) {
    $logError = "Database error: " . $e->getMessage();
}

// Create emails directory if it doesn't exist
$emailsDir = __DIR__ . '/emails';
if (!is_dir($emailsDir)) {
    if (mkdir($emailsDir, 0755, true)) {
        $emailSuccess = "Created emails directory for backup storage of OTP emails.";
    } else {
        $emailError = "Failed to create emails directory. Please create it manually.";
    }
} else {
    $emailSuccess = "Emails directory already exists.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create OTP Table - EVSU Book Borrowing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white shadow-md rounded-lg p-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-800">Database Migration Tool</h1>
            
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3 text-gray-700">OTP Verifications Table</h2>
                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                            <p><?php echo $error; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <div class="flex">
                            <i class="fas fa-check-circle mt-1 mr-2"></i>
                            <p><?php echo $success; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="bg-blue-50 p-4 rounded-md border border-blue-200 mb-4">
                    <h3 class="font-semibold text-blue-800 mb-2">About OTP Verifications Table</h3>
                    <p class="text-blue-700">
                        This table stores one-time passwords (OTPs) for two-factor authentication. 
                        The structure has been updated to match the secureSystem implementation for better compatibility.
                    </p>
                </div>
            </div>
            
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3 text-gray-700">Activity Log Table</h2>
                <?php if (isset($logError)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                            <p><?php echo $logError; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($logSuccess)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <div class="flex">
                            <i class="fas fa-check-circle mt-1 mr-2"></i>
                            <p><?php echo $logSuccess; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="bg-blue-50 p-4 rounded-md border border-blue-200 mb-4">
                    <h3 class="font-semibold text-blue-800 mb-2">About Activity Log Table</h3>
                    <p class="text-blue-700">
                        This table tracks user activities like login attempts, 2FA verifications, 
                        and other security-related events for audit purposes.
                    </p>
                </div>
            </div>
            
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-3 text-gray-700">Email Backup Directory</h2>
                <?php if (isset($emailError)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                        <div class="flex">
                            <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                            <p><?php echo $emailError; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($emailSuccess)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                        <div class="flex">
                            <i class="fas fa-check-circle mt-1 mr-2"></i>
                            <p><?php echo $emailSuccess; ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="bg-blue-50 p-4 rounded-md border border-blue-200">
                    <h3 class="font-semibold text-blue-800 mb-2">About Email Backup</h3>
                    <p class="text-blue-700">
                        All OTP emails will be backed up as text files in the /emails directory, 
                        so you can check the verification code even if email delivery fails.
                    </p>
                </div>
            </div>
            
            <div class="flex justify-between">
                <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Home
                </a>
                <a href="login.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html> 