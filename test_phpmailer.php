<?php
// Test script for PHPMailer with Gmail SMTP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

echo "<h1>Testing PHPMailer with Gmail SMTP</h1>";

// Function to test if we can connect to SMTP server
function testSMTPConnection($host, $port, $secure, $username, $password) {
    echo "<h2>Testing SMTP Connection...</h2>";
    echo "<p>Host: $host<br>Port: $port<br>Username: $username</p>";
    
    try {
        // Try to establish a socket connection
        if ($secure == 'ssl') {
            $hostPrefix = 'ssl://';
        } elseif ($secure == 'tls') {
            $hostPrefix = ''; // We'll use explicit TLS later
        } else {
            $hostPrefix = '';
        }
        
        echo "<p>Trying to connect to {$hostPrefix}{$host}:{$port}...</p>";
        $socket = @fsockopen($hostPrefix . $host, $port, $errno, $errstr, 10);
        
        if (!$socket) {
            echo "<p style='color:red;'>Failed to connect! Error: $errstr ($errno)</p>";
            return false;
        }
        
        echo "<p style='color:green;'>Connected to SMTP server!</p>";
        fclose($socket);
        
        return true;
    } catch (Exception $e) {
        echo "<p style='color:red;'>Connection test failed: " . $e->getMessage() . "</p>";
        return false;
    }
}

// Function to send an email using PHPMailer
function sendTestEmail($to, $subject, $body, $host, $port, $secure, $username, $password) {
    echo "<h2>Sending Email...</h2>";
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = $host;                                  // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = $username;                              // SMTP username
        $mail->Password   = $password;                              // SMTP password
        
        if ($secure == 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;     // Enable TLS encryption
        } elseif ($secure == 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;        // Enable SSL encryption
        }
        
        $mail->Port       = $port;                                  // TCP port to connect to
        
        // Debugging output
        $mail->Debugoutput = function($str, $level) {
            echo "<pre style='margin: 0; padding: 2px 5px; background: #f8f9fa; border-bottom: 1px solid #ddd;'>" . htmlspecialchars($str) . "</pre>";
        };
        
        // Recipients
        $mail->setFrom($username, 'Coffee Prince Library');
        $mail->addAddress($to);                                     // Add a recipient
        $mail->addReplyTo($username, 'Coffee Prince Library');
        
        // Content
        $mail->isHTML(false);                                       // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        echo "<p style='color:green; font-weight:bold;'>Message has been sent!</p>";
        return true;
    } catch (Exception $e) {
        echo "<p style='color:red; font-weight:bold;'>Message could not be sent. Mailer Error: {$mail->ErrorInfo}</p>";
        return false;
    }
}

// SMTP settings
$host = 'smtp.gmail.com';
$port = 587;
$secure = 'tls';
$username = 'migsbacho04@gmail.com'; // Gmail address
$password = 'wiee okxg wyvr nqxl'; // App password
$to = 'migsbacho04@gmail.com'; // For testing, send to self

// Test connection
$connected = testSMTPConnection($host, $port, $secure, $username, $password);

// Only try to send if connection test passed
if ($connected) {
    // Prepare email content
    $subject = 'Coffee Prince Library - SMTP Test';
    $body = "This is a test email sent from Coffee Prince Library at " . date('Y-m-d H:i:s') . "\n\n";
    $body .= "If you received this email, your SMTP configuration is working correctly!";
    
    // Send test email
    $sent = sendTestEmail($to, $subject, $body, $host, $port, $secure, $username, $password);
    
    if ($sent) {
        echo "<p>The test was successful! Check your inbox for the test email.</p>";
    } else {
        echo "<p>The email wasn't sent. Check the error message above for details.</p>";
    }
} else {
    echo "<p>Cannot proceed with sending an email due to connection test failure.</p>";
}
?> 