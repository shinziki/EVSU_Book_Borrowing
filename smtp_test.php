<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>SMTP Connection Test</h1>";

// First, check if we have the required PHPMailer files
$baseDir = __DIR__ . '/vendor/phpmailer/phpmailer/src';
$requiredFiles = ['PHPMailer.php', 'SMTP.php', 'Exception.php'];
$allFilesExist = true;

echo "<h2>Checking for PHPMailer files</h2>";
foreach ($requiredFiles as $file) {
    $filePath = "$baseDir/$file";
    $exists = file_exists($filePath);
    echo "$file: " . ($exists ? "Found" : "Missing") . "<br>";
    if (!$exists) {
        $allFilesExist = false;
    }
}

if (!$allFilesExist) {
    echo "<p>Some PHPMailer files are missing. <a href='download_phpmailer.php'>Click here to download them</a>.</p>";
    exit;
}

// Load mail configuration
echo "<h2>Loading mail configuration</h2>";
if (file_exists('config/mail_config.php')) {
    require_once 'config/mail_config.php';
    echo "Configuration loaded successfully.<br>";
} else {
    echo "Failed to load mail configuration file.<br>";
    exit;
}

// Try to load PHPMailer classes
echo "<h2>Loading PHPMailer classes</h2>";
try {
    require_once $baseDir . '/Exception.php';
    require_once $baseDir . '/PHPMailer.php';
    require_once $baseDir . '/SMTP.php';
    
    echo "PHPMailer classes loaded successfully.<br>";
    
    // Do not use namespace imports with 'use' - older PHP versions don't support it here
    // We'll use fully qualified class names instead
    
    echo "Ready to test SMTP connection.<br>";
} catch (Error $e) {
    echo "Error loading PHPMailer classes: " . $e->getMessage() . "<br>";
    exit;
}

// Create logs directory if it doesn't exist
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

// Test SMTP connection
echo "<h2>Testing SMTP connection</h2>";
try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    // Server settings
    $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host       = $mail_config['smtp_host'];
    $mail->SMTPAuth   = $mail_config['smtp_auth'];
    $mail->Username   = $mail_config['smtp_username'];
    $mail->Password   = $mail_config['smtp_password'];
    $mail->SMTPSecure = $mail_config['smtp_secure'];
    $mail->Port       = $mail_config['smtp_port'];
    
    // Output debug info to custom variable
    ob_start();
    
    // Try to connect
    echo "Connecting to " . $mail_config['smtp_host'] . ":" . $mail_config['smtp_port'] . "...<br>";
    $mail->smtpConnect();
    
    $debugOutput = ob_get_clean();
    echo "<pre>$debugOutput</pre>";
    
    echo "SMTP connection successful!<br>";
    
    // Try to send a test email
    echo "<h2>Sending test email</h2>";
    
    // Recipients
    $mail->setFrom($mail_config['smtp_username'], $mail_config['from_name']);
    $mail->addAddress($mail_config['smtp_username']);
    
    // Content
    $mail->isHTML(false);
    $mail->Subject = 'SMTP Test Email';
    $mail->Body    = 'This is a test email sent using PHPMailer with SMTP at ' . date('Y-m-d H:i:s');
    
    // Debug output for sending
    ob_start();
    $mail->send();
    $sendDebugOutput = ob_get_clean();
    
    echo "<pre>$sendDebugOutput</pre>";
    echo "Email sent successfully!<br>";
    
    // Log the success
    file_put_contents('logs/smtp_test.log', date('Y-m-d H:i:s') . " - SMTP connection and email sending successful\n", FILE_APPEND);
    
} catch (Exception $e) {
    $debugOutput = ob_get_clean();
    echo "<pre>$debugOutput</pre>";
    echo "SMTP Error: " . $mail->ErrorInfo . "<br>";
    
    // Log the error
    file_put_contents('logs/smtp_test.log', date('Y-m-d H:i:s') . " - SMTP Error: " . $mail->ErrorInfo . "\n", FILE_APPEND);
    
    echo "<h2>Troubleshooting Suggestions</h2>";
    echo "<ul>";
    echo "<li>Check if your Gmail account has less secure app access enabled or an app password configured</li>";
    echo "<li>Make sure your username and password are correct</li>";
    echo "<li>Check if your Gmail account has SMTP access restrictions</li>";
    echo "<li>Try using a different port (465 for SSL)</li>";
    echo "<li>If using 2FA, make sure you're using an App Password, not your regular password</li>";
    echo "</ul>";
}

echo "<p><a href='notifications.php'>Return to notifications page</a></p>";
?> 