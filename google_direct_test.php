<?php
// Direct Gmail test - Specially designed for Gmail accounts with restrictions
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/mail_config.php';

echo "<h1>Gmail Direct Authentication Test</h1>";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process form submission
    $recipient = $_POST['recipient'] ?? $mail_config['smtp_username'];
    $subject = $_POST['subject'] ?? 'Test Email from Coffee Prince Library';
    $message = $_POST['message'] ?? 'This is a test email sent at ' . date('Y-m-d H:i:s');
    $port = (int)($_POST['port'] ?? $mail_config['smtp_port']);
    $secure = $_POST['secure'] ?? $mail_config['smtp_secure'];
    $timeout = (int)($_POST['timeout'] ?? 60);
    
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Enable SMTP debugging
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // Setup SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $mail_config['smtp_username'];
        $mail->Password = $mail_config['smtp_password'];
        
        // Extend timeout settings
        $mail->Timeout = $timeout;
        ini_set('default_socket_timeout', $timeout);
        
        // Set specific security options
        if ($secure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        // Additional Gmail-specific settings
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Output buffering to capture debug output
        ob_start();
        
        // Set sender and recipient
        $mail->setFrom($mail_config['smtp_username'], 'Coffee Prince Library');
        $mail->addAddress($recipient);
        $mail->addReplyTo($mail_config['smtp_username'], 'Coffee Prince Library');
        
        // Set email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br($message);
        $mail->AltBody = strip_tags($message);
        
        // Send the email
        $result = $mail->send();
        $debugOutput = ob_get_clean();
        
        echo "<div style='padding: 15px; margin: 20px 0; " . 
             ($result ? "background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724;" : 
                       "background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;") . 
             " border-radius: 4px;'>";
        
        if ($result) {
            echo "<h3>Success!</h3>";
            echo "<p>Email sent successfully to $recipient.</p>";
            echo "<p>Please check your inbox AND spam folder for the message.</p>";
        } else {
            echo "<h3>Failed to send email</h3>";
            echo "<p>Error: " . $mail->ErrorInfo . "</p>";
        }
        
        echo "</div>";
        
        echo "<h3>Debug Output:</h3>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border: 1px solid #ddd; overflow: auto; max-height: 300px;'>";
        echo htmlspecialchars($debugOutput);
        echo "</pre>";
        
    } catch (Exception $e) {
        $debugOutput = ob_get_clean();
        
        echo "<div style='padding: 15px; margin: 20px 0; background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; border-radius: 4px;'>";
        echo "<h3>Exception Occurred</h3>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
        
        echo "<h3>Debug Output:</h3>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border: 1px solid #ddd; overflow: auto; max-height: 300px;'>";
        echo htmlspecialchars($debugOutput);
        echo "</pre>";
    }
}
?>

<div style="max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
    <h2>Gmail Direct Test</h2>
    
    <p>This tool bypasses regular settings and tries to connect directly to Gmail with advanced options.</p>
    
    <form method="post" action="">
        <div style="margin-bottom: 15px;">
            <label for="recipient" style="display: block; margin-bottom: 5px; font-weight: bold;">Recipient Email:</label>
            <input type="email" id="recipient" name="recipient" value="<?php echo htmlspecialchars($mail_config['smtp_username']); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">Enter your Gmail email address to send to yourself</small>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="subject" style="display: block; margin-bottom: 5px; font-weight: bold;">Subject:</label>
            <input type="text" id="subject" name="subject" value="URGENT: Gmail Test from Coffee Prince Library <?php echo date('H:i:s'); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="message" style="display: block; margin-bottom: 5px; font-weight: bold;">Message:</label>
            <textarea id="message" name="message" rows="5" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">This is a test email sent at <?php echo date('Y-m-d H:i:s'); ?>.

If you're reading this, the Gmail connection is working!

From Coffee Prince Library</textarea>
        </div>
        
        <div style="margin-bottom: 15px; display: flex; gap: 15px;">
            <div style="flex: 1;">
                <label for="port" style="display: block; margin-bottom: 5px; font-weight: bold;">Port:</label>
                <select id="port" name="port" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="465" <?php echo ($mail_config['smtp_port'] == 465) ? 'selected' : ''; ?>>465 (SSL)</option>
                    <option value="587" <?php echo ($mail_config['smtp_port'] == 587) ? 'selected' : ''; ?>>587 (TLS)</option>
                    <option value="25">25 (Unencrypted)</option>
                </select>
            </div>
            
            <div style="flex: 1;">
                <label for="secure" style="display: block; margin-bottom: 5px; font-weight: bold;">Security:</label>
                <select id="secure" name="secure" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="ssl" <?php echo ($mail_config['smtp_secure'] == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                    <option value="tls" <?php echo ($mail_config['smtp_secure'] == 'tls') ? 'selected' : ''; ?>>TLS</option>
                    <option value="">None</option>
                </select>
            </div>
            
            <div style="flex: 1;">
                <label for="timeout" style="display: block; margin-bottom: 5px; font-weight: bold;">Timeout (seconds):</label>
                <input type="number" id="timeout" name="timeout" value="60" min="10" max="300" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <button type="submit" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">Send Test Email</button>
            <a href="gmail_test.php" style="margin-left: 10px; text-decoration: none; color: #666;">Back to Gmail Test</a>
        </div>
    </form>
    
    <h3 style="margin-top: 30px;">Important Tips for Gmail</h3>
    <ul style="line-height: 1.6;">
        <li><strong>App Password Required:</strong> If you have 2-factor authentication enabled on your Gmail, you <em>must</em> use an app password</li>
        <li><strong>Try Both Ports:</strong> If one port doesn't work, try the other (465 for SSL, 587 for TLS)</li>
        <li><strong>Check Spam Folder:</strong> Gmail often puts automated test emails in spam</li>
        <li><strong>Google Security:</strong> Check for any Google security alerts - you might need to allow "less secure apps" or confirm the login attempt</li>
        <li><strong>Organization Restrictions:</strong> If using a Google Workspace account managed by an organization, they may have restrictions preventing SMTP access</li>
    </ul>
    
    <h3>Recommended Google Settings</h3>
    <ol style="line-height: 1.6;">
        <li>Enable 2-Step Verification: <a href="https://myaccount.google.com/signinoptions/two-step-verification" target="_blank">https://myaccount.google.com/signinoptions/two-step-verification</a></li>
        <li>Generate an App Password: <a href="https://myaccount.google.com/apppasswords" target="_blank">https://myaccount.google.com/apppasswords</a></li>
        <li>Use that App Password in your mail_config.php file</li>
    </ol>
</div> 