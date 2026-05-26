<?php
// Fallback mail test using PHP's built-in mail() function
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/mail_config.php';

echo "<h1>PHP Mail Function Test</h1>";
echo "<p>This test uses PHP's built-in mail() function as a fallback approach.</p>";

$recipient = isset($_POST['recipient']) ? $_POST['recipient'] : $mail_config['smtp_username'];
$subject = isset($_POST['subject']) ? $_POST['subject'] : 'Fallback Mail Test - ' . date('Y-m-d H:i:s');
$message = isset($_POST['message']) ? $_POST['message'] : "This is a test message sent at " . date('Y-m-d H:i:s') . "\n\nThis message was sent using PHP's built-in mail() function.";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create log directory if it doesn't exist
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    $logFile = 'logs/mail_function.log';
    
    // Set up the email headers
    $headers = "From: {$mail_config['from_name']} <{$mail_config['from_email']}>\r\n";
    $headers .= "Reply-To: {$mail_config['from_email']}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    // Determine if we're sending HTML
    $isHtml = isset($_POST['is_html']) && $_POST['is_html'] == '1';
    
    if ($isHtml) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        // Convert line breaks to <br> for HTML messages
        $message = nl2br($message);
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    
    // Log the attempt
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Attempting to send email to {$recipient}\n", FILE_APPEND);
    
    // Send the email
    $result = mail($recipient, $subject, $message, $headers);
    
    // Log the result
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Result: " . ($result ? "Success" : "Failed") . "\n", FILE_APPEND);
    
    // Display the result
    echo "<div style='padding: 15px; margin: 20px 0; " . 
         ($result ? "background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724;" : 
                   "background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;") . 
         " border-radius: 4px;'>";
    
    if ($result) {
        echo "<h3>Success!</h3>";
        echo "<p>Email sent successfully to {$recipient} using mail() function.</p>";
        echo "<p>Please check your inbox <strong>AND spam folder</strong> for the message.</p>";
    } else {
        echo "<h3>Failed to send email</h3>";
        echo "<p>The mail() function returned false. This could be due to misconfiguration in your PHP setup or server restrictions.</p>";
    }
    echo "</div>";
    
    // Show PHP mail configuration
    echo "<h3>PHP Mail Configuration</h3>";
    echo "<pre>";
    
    // Get mail configuration from PHP
    $mailConfig = ini_get('sendmail_path') ? "Sendmail Path: " . ini_get('sendmail_path') : "Sendmail Path: Not configured";
    echo htmlspecialchars($mailConfig) . "\n";
    echo "SMTP: " . htmlspecialchars(ini_get('SMTP')) . "\n";
    echo "smtp_port: " . htmlspecialchars(ini_get('smtp_port')) . "\n";
    echo "sendmail_from: " . htmlspecialchars(ini_get('sendmail_from')) . "\n";
    echo "</pre>";
}
?>

<div style="max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; background: #f9f9f9;">
    <h2>PHP Mail Function Test</h2>
    
    <p>This tool tests PHP's built-in mail() function as a fallback approach when SMTP doesn't work.</p>
    
    <form method="post" action="">
        <div style="margin-bottom: 15px;">
            <label for="recipient" style="display: block; margin-bottom: 5px; font-weight: bold;">Recipient Email:</label>
            <input type="email" id="recipient" name="recipient" value="<?php echo htmlspecialchars($recipient); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            <small style="color: #666;">Enter the email where you want to receive the test message</small>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="subject" style="display: block; margin-bottom: 5px; font-weight: bold;">Subject:</label>
            <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($subject); ?>" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="message" style="display: block; margin-bottom: 5px; font-weight: bold;">Message:</label>
            <textarea id="message" name="message" rows="5" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?php echo htmlspecialchars($message); ?></textarea>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Format:</label>
            <label style="margin-right: 15px;">
                <input type="radio" name="is_html" value="0" checked> Plain Text
            </label>
            <label>
                <input type="radio" name="is_html" value="1"> HTML
            </label>
        </div>
        
        <div style="margin-top: 20px;">
            <button type="submit" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">Send Test Email</button>
            <a href="google_direct_test.php" style="margin-left: 10px; text-decoration: none; color: #666;">Try Gmail Direct Test</a>
        </div>
    </form>
    
    <h3 style="margin-top: 30px;">Important Notes</h3>
    <ul style="line-height: 1.6;">
        <li><strong>Server Configuration:</strong> The PHP mail() function relies on your server's mail configuration</li>
        <li><strong>XAMPP/Local Servers:</strong> Local development servers often don't have mail capabilities configured correctly</li>
        <li><strong>Web Hosting:</strong> This function works best on production web hosting where mail is properly configured</li>
        <li><strong>Check Spam:</strong> Test emails often end up in spam folders</li>
        <li><strong>Gmail Limitations:</strong> Gmail has strict policies on what it accepts as valid emails</li>
    </ul>
</div>

<div style="max-width: 800px; margin: 20px auto; padding: 20px; text-align: center;">
    <p><a href="google_direct_test.php">Try Gmail Direct Test</a> | 
    <a href="gmail_test.php">Try Gmail Test</a> | 
    <a href="borrow.php">Return to Borrow Page</a></p>
</div> 