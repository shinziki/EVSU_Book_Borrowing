<?php
// Simple email test script
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/mail_config.php';
require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

echo "<h1>Email Test</h1>";

try {
    $mail = new PHPMailer(true);

    // Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host       = $mail_config['smtp_host'];
    $mail->SMTPAuth   = $mail_config['smtp_auth'];
    $mail->Username   = $mail_config['smtp_username'];
    $mail->Password   = $mail_config['smtp_password'];
    $mail->SMTPSecure = $mail_config['smtp_secure'];
    $mail->Port       = $mail_config['smtp_port'];

    // Turn on output buffering to capture debug output
    ob_start();
    
    // Recipients
    $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
    $mail->addAddress($mail_config['smtp_username'], 'Gmail User'); // Add recipient (same as sender for testing)
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Coffee Prince Library ' . date('Y-m-d H:i:s');
    $mail->Body    = '<h2>This is a test email</h2><p>Sent at ' . date('Y-m-d H:i:s') . '</p>';
    $mail->AltBody = 'This is a test email. Sent at ' . date('Y-m-d H:i:s');
    
    // Try to send
    $mail->send();
    
    // Get debug output
    $debugOutput = ob_get_clean();
    
    echo "<div style='padding: 10px; background-color: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d;'>";
    echo "<p><strong>Success!</strong> Email has been sent to " . $mail_config['smtp_username'] . "</p>";
    echo "</div>";
    
    echo "<h3>Debug Output:</h3>";
    echo "<pre>" . htmlspecialchars($debugOutput) . "</pre>";
    
} catch (Exception $e) {
    $debugOutput = ob_get_clean();
    
    echo "<div style='padding: 10px; background-color: #f2dede; border: 1px solid #ebccd1; color: #a94442;'>";
    echo "<p><strong>Error!</strong> Message could not be sent.</p>";
    echo "<p>Mailer Error: " . $mail->ErrorInfo . "</p>";
    echo "</div>";
    
    echo "<h3>Debug Output:</h3>";
    echo "<pre>" . htmlspecialchars($debugOutput) . "</pre>";
    
    echo "<h3>Troubleshooting:</h3>";
    echo "<ol>";
    echo "<li>Check if your Gmail account has 2-factor authentication enabled. If so, you need to use an App Password.</li>";
    echo "<li>Make sure the App Password in mail_config.php is correct: " . (isset($mail_config['smtp_password']) ? substr($mail_config['smtp_password'], 0, 4) . '...' : 'Not set') . "</li>";
    echo "<li>Try changing the port to 465 and secure setting to 'ssl' instead of 'tls'</li>";
    echo "<li>Ensure Gmail account doesn't have any restrictions on less secure apps</li>";
    echo "</ol>";
}
?> 