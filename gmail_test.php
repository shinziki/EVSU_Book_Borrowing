<?php
// Gmail SMTP Connection Test
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Gmail SMTP Connection Test</h1>";

// Load mail configuration
require_once 'config/mail_config.php';

// Check for required files
$phpmailerPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
$smtpPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
$exceptionPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';

if (!file_exists($phpmailerPath) || !file_exists($smtpPath) || !file_exists($exceptionPath)) {
    echo "<div style='color: red; border: 1px solid red; padding: 10px; margin-bottom: 20px;'>";
    echo "<h3>PHPMailer files not found!</h3>";
    echo "<p>Please first run <a href='download_phpmailer.php'>download_phpmailer.php</a> to install PHPMailer.</p>";
    echo "</div>";
    exit;
}

require_once $exceptionPath;
require_once $phpmailerPath;
require_once $smtpPath;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Create a log file
$logFile = 'logs/gmail_test.log';
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

function log_message($message) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    echo "<p>" . htmlspecialchars($message) . "</p>";
}

log_message("Starting Gmail SMTP test");

// Display the configuration we're testing with
echo "<h2>Testing with the following configuration:</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
echo "<tr><td>SMTP Host</td><td>{$mail_config['smtp_host']}</td></tr>";
echo "<tr><td>SMTP Port</td><td>{$mail_config['smtp_port']}</td></tr>";
echo "<tr><td>SMTP Secure</td><td>{$mail_config['smtp_secure']}</td></tr>";
echo "<tr><td>SMTP Auth</td><td>" . ($mail_config['smtp_auth'] ? 'Yes' : 'No') . "</td></tr>";
echo "<tr><td>SMTP Username</td><td>{$mail_config['smtp_username']}</td></tr>";
echo "<tr><td>SMTP Password</td><td>" . (isset($mail_config['smtp_password']) ? '********' : 'Not set') . "</td></tr>";
echo "</table>";

// Test SMTP connection only (no sending)
echo "<h2>Step 1: Testing SMTP connection</h2>";

try {
    $smtp = new SMTP;
    $smtp->do_debug = SMTP::DEBUG_CLIENT; // Output debug to browser
    
    log_message("Connecting to {$mail_config['smtp_host']} on port {$mail_config['smtp_port']}...");
    
    // Connect to the SMTP server
    if (!$smtp->connect($mail_config['smtp_host'], $mail_config['smtp_port'])) {
        log_message("Connection failed!");
        echo "<div style='color: red; font-weight: bold;'>Connection failed!</div>";
        exit;
    }
    
    log_message("Connected to {$mail_config['smtp_host']}:{$mail_config['smtp_port']}");
    
    // Say hello
    log_message("Saying EHLO...");
    if (!$smtp->hello(gethostname())) {
        log_message("EHLO failed: " . $smtp->getError()['error']);
        echo "<div style='color: red; font-weight: bold;'>EHLO command failed!</div>";
        $smtp->close();
        exit;
    }
    
    // Get server capabilities
    $capabilities = $smtp->getServerExt('');
    log_message("Server capabilities: " . json_encode($capabilities));
    
    // Check if server supports TLS and needs to start TLS
    if ($mail_config['smtp_secure'] == 'tls') {
        log_message("Starting TLS...");
        if (!$smtp->startTLS()) {
            log_message("TLS failed: " . $smtp->getError()['error']);
            echo "<div style='color: red; font-weight: bold;'>Starting TLS failed!</div>";
            $smtp->close();
            exit;
        }
        
        // Revalidate capabilities after TLS
        if (!$smtp->hello(gethostname())) {
            log_message("EHLO after TLS failed: " . $smtp->getError()['error']);
            echo "<div style='color: red; font-weight: bold;'>EHLO after TLS failed!</div>";
            $smtp->close();
            exit;
        }
        
        $capabilities = $smtp->getServerExt('');
        log_message("Server capabilities after TLS: " . json_encode($capabilities));
    }
    
    // Authenticate
    if ($mail_config['smtp_auth']) {
        log_message("Authenticating...");
        if (!$smtp->authenticate($mail_config['smtp_username'], $mail_config['smtp_password'])) {
            log_message("Authentication failed: " . $smtp->getError()['error']);
            echo "<div style='color: red; font-weight: bold;'>Authentication failed! Check your Gmail username and app password.</div>";
            $smtp->close();
            exit;
        }
        log_message("Authentication successful!");
    }
    
    // Close connection
    $smtp->close();
    log_message("Connection test completed successfully!");
    
    echo "<div style='color: green; font-weight: bold; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "SMTP connection test successful!";
    echo "</div>";
    
} catch (Exception $e) {
    log_message("Exception: " . $e->getMessage());
    echo "<div style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</div>";
}

// Test sending an actual email
echo "<h2>Step 2: Sending a test email</h2>";

try {
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = $mail_config['smtp_host'];
    $mail->Port = $mail_config['smtp_port'];
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    
    // Turn on output buffering to capture debug output
    ob_start();
    
    // Set the SMTP secure option
    if ($mail_config['smtp_secure'] == 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($mail_config['smtp_secure'] == 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }
    
    // Set authentication
    if ($mail_config['smtp_auth']) {
        $mail->SMTPAuth = true;
        $mail->Username = $mail_config['smtp_username'];
        $mail->Password = $mail_config['smtp_password'];
    }
    
    // Recipients - sending to the same Gmail account for testing
    $mail->setFrom($mail_config['smtp_username'], $mail_config['from_name']);
    $mail->addAddress($mail_config['smtp_username']);
    
    // Set email content
    $mail->isHTML(true);
    $mail->Subject = 'Gmail Connection Test - ' . date('Y-m-d H:i:s');
    $mail->Body = "<h1>Gmail Connection Test</h1>
<p>This is a test email sent at " . date('Y-m-d H:i:s') . "</p>
<p>If you're seeing this, your Gmail SMTP configuration is working correctly!</p>
<p>This test was generated from the EVSU Book Borrowing System.</p>";
    
    $mail->AltBody = "Gmail Connection Test\n\n" .
                     "This is a test email sent at " . date('Y-m-d H:i:s') . "\n\n" .
                     "If you're seeing this, your Gmail SMTP configuration is working correctly!\n\n" .
                     "This test was generated from the EVSU Book Borrowing System.";
    
    // Send the email
    if ($mail->send()) {
        $debugOutput = ob_get_clean();
        
        log_message("Email sent successfully to " . $mail_config['smtp_username']);
        
        echo "<div style='color: green; font-weight: bold; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "Test email sent successfully to " . htmlspecialchars($mail_config['smtp_username']) . "!";
        echo "</div>";
        
        echo "<p>Please check your Gmail inbox for the test message. If you don't see it in your inbox, check your spam folder.</p>";
        
    } else {
        $debugOutput = ob_get_clean();
        
        log_message("Email sending failed");
        
        echo "<div style='color: red; font-weight: bold;'>";
        echo "Failed to send test email: " . $mail->ErrorInfo;
        echo "</div>";
    }
    
    // Display debug output
    echo "<h3>Debug Output:</h3>";
    echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd; font-size: 12px; max-height: 300px; overflow: auto;'>";
    echo htmlspecialchars($debugOutput);
    echo "</pre>";
    
} catch (Exception $e) {
    $debugOutput = ob_get_clean();
    
    log_message("Exception during email sending: " . $e->getMessage());
    
    echo "<div style='color: red; font-weight: bold;'>";
    echo "Exception: " . $e->getMessage();
    echo "</div>";
    
    // Display debug output
    echo "<h3>Debug Output:</h3>";
    echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd; font-size: 12px; max-height: 300px; overflow: auto;'>";
    echo htmlspecialchars($debugOutput);
    echo "</pre>";
}

// Show troubleshooting information
echo "<h2>Troubleshooting</h2>";
echo "<ul>";
echo "<li><strong>Check Gmail settings:</strong> Make sure your Gmail account has 'Less secure app access' enabled or you're using an App Password with 2FA.</li>";
echo "<li><strong>Verify port/encryption:</strong> Try both port 465 with SSL and port 587 with TLS.</li>";
echo "<li><strong>App Password:</strong> If using 2FA, make sure you're using an App Password, not your regular Gmail password.</li>";
echo "<li><strong>Google Account restrictions:</strong> Some organizations or educational institutions might have restrictions on their Gmail accounts.</li>";
echo "<li><strong>Firewall/antivirus:</strong> Make sure your firewall or antivirus is not blocking the connection.</li>";
echo "</ul>";

echo "<h2>Next Steps</h2>";
echo "<p><a href='vendor_check.php'>Check PHPMailer Installation</a></p>";
echo "<p><a href='email_test.php'>Run Email Test</a></p>";
echo "<p><a href='borrow.php'>Go to Borrow Page</a></p>";
?> 