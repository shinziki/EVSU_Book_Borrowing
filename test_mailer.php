<?php
// Simple test for mailer.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/mail_config.php';
require_once 'config/mailer.php';

echo "Testing Mailer Configuration...<br>";

// Test sending an email
$to = 'migsbacho04@gmail.com';
$subject = 'Test Email from EVSU Book Borrowing System';
$message = "This is a test email sent at " . date('Y-m-d H:i:s') . "\n\n";
$message .= "If you received this email, your email system is working correctly.";

echo "Attempting to send email to: $to<br>";

$result = sendEmail($to, $subject, $message);

if ($result) {
    echo "Email sent successfully!<br>";
    echo "Check the logs/email_log.txt file for details.<br>";
    echo "Also check the emails directory for a copy of the email.<br>";
} else {
    echo "Failed to send email.<br>";
    echo "Check the logs/email_log.txt file for error details.<br>";
}
?> 