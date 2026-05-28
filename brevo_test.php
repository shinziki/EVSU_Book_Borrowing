<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include necessary files
require_once 'config/db_connect.php';
require_once 'config/functions.php';

echo "<h1>Brevo API Email Test</h1>";

// Create logs and emails directories if they don't exist
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}

if (!is_dir('emails')) {
    mkdir('emails', 0755, true);
}

// Test sending an email
if (isset($_POST['email']) && !empty($_POST['email'])) {
    $to = $_POST['email'];
    $subject = "Test Email from EVSU Book Borrowing System via Brevo API";
    $message = "This is a test email sent using Brevo API at " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "If you received this email, your email system is configured correctly using the Brevo API.";
    
    // Import the sendEmail function
    require_once 'config/mailer.php';
    
    $result = sendEmail($to, $subject, $message);
    
    if ($result) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 15px; margin: 20px 0; border-radius: 5px;'>
            <b>Success!</b> Test email sent successfully to {$to}. Please check your inbox.
        </div>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 15px; margin: 20px 0; border-radius: 5px;'>
            <b>Error!</b> Failed to send test email. Check the logs for more details.
        </div>";
    }
}

// Display form
?>

<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
    <form method="post" action="">
        <div style="margin-bottom: 15px;">
            <label for="email" style="display: block; margin-bottom: 5px; font-weight: bold;">Email Address:</label>
            <input type="email" id="email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
        </div>
        <button type="submit" style="background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">Send Test Email</button>
    </form>
    
    <div style="margin-top: 30px;">
        <h2>Brevo API Implementation Details</h2>
        <p>This test uses the Brevo API (formerly Sendinblue) to send emails directly from the application. 
        Benefits include:</p>
        <ul>
            <li>Higher delivery rates than PHP's mail() function</li>
            <li>No need for complex SMTP configuration</li>
            <li>Reliable email delivery for notifications</li>
            <li>Tracking and analytics for sent emails</li>
        </ul>
        <p>The logs directory will contain detailed information about any API calls and responses.</p>
    </div>
    
    <p style="margin-top: 20px;"><a href="notifications.php">Return to notifications page</a></p>
</div> 