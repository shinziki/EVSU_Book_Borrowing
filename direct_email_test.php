<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/mail_config.php';

echo "<h1>Direct Email Test</h1>";

// Function to send an email using PHP's built-in mail function
function sendDirectEmail($to, $subject, $message, $sender) {
    $headers = "From: $sender\r\n";
    $headers .= "Reply-To: $sender\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Try to send email
    $result = mail($to, $subject, $message, $headers);
    
    // Log attempt
    $log = date('Y-m-d H:i:s') . " - TO: $to, SUBJECT: $subject, RESULT: " . ($result ? "SUCCESS" : "FAILED") . "\n";
    $logFile = __DIR__ . '/logs/direct_email_test.log';
    file_put_contents($logFile, $log, FILE_APPEND);
    
    return $result;
}

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Test 1: Direct PHP mail() function
echo "<h2>Test 1: Using PHP's mail() function</h2>";
$to = "migsbacho04@gmail.com"; // Your email 
$subject = "Direct Mail Test";
$message = "This is a test message sent directly from PHP mail() at " . date('Y-m-d H:i:s');

$result1 = sendDirectEmail($to, $subject, $message, "migsbacho04@gmail.com");
echo "Result: " . ($result1 ? "Email sent successfully" : "Failed to send email") . "<br>";

// Test 2: Using mail() with your Gmail SMTP settings
echo "<h2>Test 2: Using Gmail in From field</h2>";
$subject2 = "Gmail From Test";
$message2 = "This is a test message with Gmail in From header at " . date('Y-m-d H:i:s');

$result2 = sendDirectEmail($to, $subject2, $message2, $GLOBALS['mail_config']['smtp_username']);
echo "Result: " . ($result2 ? "Email sent successfully" : "Failed to send email") . "<br>";

// Check XAMPP mail configuration
echo "<h2>XAMPP Mail Configuration</h2>";
$sendmail_path = ini_get('sendmail_path');
echo "Sendmail path: " . ($sendmail_path ? $sendmail_path : "Not configured") . "<br>";

$sendmail_from = ini_get('sendmail_from');
echo "Sendmail from: " . ($sendmail_from ? $sendmail_from : "Not configured") . "<br>";

// Check if mail function exists
echo "mail() function " . (function_exists('mail') ? "exists" : "DOES NOT EXIST") . "<br>";

// Create a PHP mailer without PHPMailer
echo "<h2>Alternative: Create an SMTP Mailer</h2>";
echo "To send emails using SMTP without PHPMailer, you would need to:<br>";
echo "1. Install PHPMailer using Composer: <code>composer require phpmailer/phpmailer</code><br>";
echo "2. Or download PHPMailer manually and place it in a 'vendor/phpmailer' directory<br>";
echo "3. Or use PHP's built-in socket functions to connect directly to SMTP (complex)<br>";

// Check for local mail functionality
echo "<h2>Local Mail Suggestions</h2>";
echo "For Windows/XAMPP, you may need to:<br>";
echo "1. Configure sendmail in php.ini<br>";
echo "2. Use a local mail tool like Fake Sendmail or SMTP4Dev<br>";
echo "3. Use the 'emails' directory to store emails for development<br>";

// Alternative approach suggestion
echo "<h2>File-based backup solution</h2>";
$emails_dir = __DIR__ . '/emails';
if (!is_dir($emails_dir)) {
    mkdir($emails_dir, 0755, true);
}

// Save email to file
$timestamp = date('Ymd_His');
$filename = $emails_dir . "/direct_test_{$timestamp}.txt";

$email_content = "To: $to\n";
$email_content .= "Subject: $subject\n";
$email_content .= "From: migsbacho04@gmail.com\n";
$email_content .= "Date: " . date('Y-m-d H:i:s') . "\n";
$email_content .= "-------------------------------------------\n";
$email_content .= $message;

file_put_contents($filename, $email_content);
echo "Email saved to file: " . basename($filename) . "<br>";

// Check if file-based fallback works
$fallback_file = __DIR__ . '/config/mailer.php';
if (file_exists($fallback_file)) {
    echo "<p>Your system is configured to save emails to the 'emails' directory when sending fails.</p>";
}

echo "<p>These tests are complete. Check your email and the logs directory for results.</p>";
?> 