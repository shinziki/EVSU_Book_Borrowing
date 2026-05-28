<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Required files
require_once 'config/db_connect.php';
require_once 'config/functions.php';
require_once 'config/mailer.php';
require_once 'config/mail_config.php';

echo '<h1>Email System Diagnostic Tool</h1>';

// Create logs and email directories
if (!is_dir('logs')) {
    echo "<p>Creating logs directory...</p>";
    if (mkdir('logs', 0755, true)) {
        echo "<p class='success'>Logs directory created successfully.</p>";
    } else {
        echo "<p class='error'>Failed to create logs directory!</p>";
    }
} else {
    echo "<p>Logs directory exists.</p>";
}

if (!is_dir('emails')) {
    echo "<p>Creating emails directory...</p>";
    if (mkdir('emails', 0755, true)) {
        echo "<p class='success'>Emails directory created successfully.</p>";
    } else {
        echo "<p class='error'>Failed to create emails directory!</p>";
    }
} else {
    echo "<p>Emails directory exists.</p>";
}

// Check if cURL is installed and working
echo '<h2>Checking cURL Extension</h2>';
if (function_exists('curl_version')) {
    $curl_info = curl_version();
    echo "<p class='success'>cURL is installed. Version: " . $curl_info['version'] . "</p>";
} else {
    echo "<p class='error'>cURL is NOT installed or enabled! This is required for API calls.</p>";
}

// Check mail configuration
echo '<h2>Mail Configuration Check</h2>';
echo '<pre>';
// Display mail config with sensitive data masked
$masked_config = isset($mail_config) ? $mail_config : array();
if (isset($masked_config['smtp_password'])) {
    $masked_config['smtp_password'] = '********';
}
print_r($masked_config);
echo '</pre>';

if (!isset($mail_config) || !is_array($mail_config)) {
    echo "<p class='error'>Mail configuration is missing or invalid!</p>";
} else {
    echo "<p class='success'>Mail configuration exists.</p>";
    
    // Check required fields
    $required_fields = ['from_email', 'from_name'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($mail_config[$field]) || empty($mail_config[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        echo "<p class='error'>Missing required mail configuration fields: " . implode(', ', $missing_fields) . "</p>";
    } else {
        echo "<p class='success'>All required mail configuration fields are present.</p>";
    }
}

// Test Brevo API directly with detailed error information
echo '<h2>Direct Brevo API Test</h2>';

function testBrevoAPI($to, $subject, $message) {
    global $mail_config;
    
    // Brevo API key
    $apiKey = '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';
    
    // Set default values if mail_config is not set
    $fromName = 'EVSU Book Borrowing System';
    $fromEmail = 'noreply@coffeeprincelibrary.com';
    
    // Use values from mail_config if they exist
    if (isset($mail_config) && is_array($mail_config)) {
        if (isset($mail_config['from_name']) && !empty($mail_config['from_name'])) {
            $fromName = $mail_config['from_name'];
        }
        
        if (isset($mail_config['from_email']) && !empty($mail_config['from_email'])) {
            $fromEmail = $mail_config['from_email'];
        }
    }
    
    // Prepare the data
    $data = [
        'sender' => [
            'name' => $fromName,
            'email' => $fromEmail
        ],
        'to' => [
            [
                'email' => $to
            ]
        ],
        'subject' => $subject,
        'htmlContent' => nl2br($message),
        'textContent' => $message
    ];
    
    // Show the request payload (with API key partially masked)
    echo "<h3>API Request Details:</h3>";
    echo "<p>URL: https://api.sendinblue.com/v3/smtp/email</p>";
    echo "<p>API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -5) . "</p>";
    echo "<p>From Name: " . htmlspecialchars($fromName) . "</p>";
    echo "<p>From Email: " . htmlspecialchars($fromEmail) . "</p>";
    echo "<p>To Email: " . htmlspecialchars($to) . "</p>";
    echo "<p>Subject: " . htmlspecialchars($subject) . "</p>";
    
    // Prepare the request
    $url = 'https://api.sendinblue.com/v3/smtp/email';
    $headers = [
        'Content-Type: application/json',
        'api-key: ' . $apiKey
    ];
    
    // Initialize cURL with verbose debug output
    $curl = curl_init();
    
    // Create a file handle for the CURL debug info
    $verbose = fopen('logs/curl_verbose.log', 'w+');
    
    // Set cURL options
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_VERBOSE => true,
        CURLOPT_STDERR => $verbose,  
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    // Execute the request
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $errorNo = curl_errno($curl);
    $error = curl_error($curl);
    
    // Close cURL
    curl_close($curl);
    
    // Close the file handle
    fclose($verbose);
    
    // Display results
    echo "<h3>API Response:</h3>";
    echo "<p>HTTP Code: " . $httpCode . "</p>";
    
    if ($errorNo) {
        echo "<p class='error'>cURL Error #" . $errorNo . ": " . $error . "</p>";
    }
    
    echo "<p>Response Body:</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    // Load the verbose debug info
    echo "<h3>Connection Debug Log:</h3>";
    echo "<pre>";
    echo htmlspecialchars(file_get_contents('logs/curl_verbose.log'));
    echo "</pre>";
    
    // Return success or failure
    return $httpCode >= 200 && $httpCode < 300;
}

// Form for testing email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $to = $_POST['email'];
    $subject = "Test Email from EVSU Book Borrowing System";
    $message = "This is a test email sent at " . date('Y-m-d H:i:s') . "\n\n";
    $message .= "If you received this email, your email system is working correctly.";
    
    echo "<h3>Test Results:</h3>";
    $result = testBrevoAPI($to, $subject, $message);
    
    if ($result) {
        echo "<p class='success'>API call successful! Check your inbox for the test email.</p>";
    } else {
        echo "<p class='error'>API call failed. Check the response details above.</p>";
    }
}

// Form for sending test email
echo '<h2>Send Test Email</h2>';
echo '<form method="post" action="">';
echo '<label for="email">Email Address:</label>';
echo '<input type="email" id="email" name="email" required value="migsbacho04@gmail.com">';
echo '<button type="submit" name="test_email">Send Test Email</button>';
echo '</form>';

// Check logs
echo '<h2>Log Files</h2>';

$log_files = glob('logs/*.log');
$log_files = array_merge($log_files, glob('logs/*.txt'));

if (empty($log_files)) {
    echo "<p>No log files found.</p>";
} else {
    echo "<ul>";
    foreach ($log_files as $log_file) {
        echo "<li><a href='javascript:void(0);' onclick='toggleLog(\"" . basename($log_file) . "\")'>" . basename($log_file) . "</a>";
        echo "<div id='log_" . basename($log_file) . "' style='display:none;'>";
        echo "<pre>" . htmlspecialchars(file_exists($log_file) ? file_get_contents($log_file) : 'File not found') . "</pre>";
        echo "</div></li>";
    }
    echo "</ul>";
}

// Inspect email files
echo '<h2>Email Files (Fallback Storage)</h2>';

$email_files = glob('emails/*.txt');

if (empty($email_files)) {
    echo "<p>No email files found.</p>";
} else {
    echo "<ul>";
    foreach ($email_files as $email_file) {
        echo "<li><a href='javascript:void(0);' onclick='toggleEmail(\"" . basename($email_file) . "\")'>" . basename($email_file) . "</a>";
        echo "<div id='email_" . basename($email_file) . "' style='display:none;'>";
        echo "<pre>" . htmlspecialchars(file_exists($email_file) ? file_get_contents($email_file) : 'File not found') . "</pre>";
        echo "</div></li>";
    }
    echo "</ul>";
}

// Test default PHP mail() function
echo '<h2>PHP mail() Function Test</h2>';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_php_mail'])) {
    $to = $_POST['php_email'];
    $subject = "Test Email via PHP mail()";
    $message = "This is a test email sent using the PHP mail() function at " . date('Y-m-d H:i:s');
    $headers = "From: EVSU Book Borrowing System <noreply@example.com>\r\n";
    
    $result = @mail($to, $subject, $message, $headers);
    
    if ($result) {
        echo "<p class='success'>PHP mail() reported success, but email delivery depends on your server configuration.</p>";
    } else {
        echo "<p class='error'>PHP mail() failed. Your server might not have a mail service configured.</p>";
    }
}

echo '<form method="post" action="">';
echo '<label for="php_email">Email Address:</label>';
echo '<input type="email" id="php_email" name="php_email" required value="migsbacho04@gmail.com">';
echo '<button type="submit" name="test_php_mail">Test PHP mail()</button>';
echo '</form>';

// Alternative solutions section
echo '<h2>Alternative Solutions</h2>';
echo '<p>If Brevo API is not working, you can try these alternatives:</p>';
echo '<ul>';
echo '<li><strong>SMTP Direct:</strong> Configure your server to use SMTP directly with another provider.</li>';
echo '<li><strong>Mailgun:</strong> <a href="https://www.mailgun.com/" target="_blank">Mailgun</a> offers a similar API with a generous free tier.</li>';
echo '<li><strong>File Storage:</strong> For development, you can use the file storage fallback that already exists in the code.</li>';
echo '<li><strong>Local SMTP:</strong> For testing, you can use tools like <a href="https://github.com/rnwood/smtp4dev" target="_blank">smtp4dev</a> or <a href="https://mailtrap.io/" target="_blank">Mailtrap</a>.</li>';
echo '</ul>';

?>

<style>
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    margin: 0;
    padding: 20px;
    color: #333;
}
h1, h2, h3 {
    color: #2c3e50;
}
.success {
    color: #27ae60;
    font-weight: bold;
}
.error {
    color: #e74c3c;
    font-weight: bold;
}
pre {
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 15px;
    overflow-x: auto;
}
input[type="email"] {
    padding: 8px;
    width: 300px;
    margin-right: 10px;
    border: 1px solid #ddd;
    border-radius: 3px;
}
button {
    padding: 8px 15px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}
button:hover {
    background: #2980b9;
}
form {
    margin-bottom: 20px;
}
ul {
    list-style-type: none;
    padding-left: 0;
}
li {
    margin-bottom: 10px;
}
a {
    color: #3498db;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>

<script>
function toggleLog(filename) {
    var element = document.getElementById('log_' + filename);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
}

function toggleEmail(filename) {
    var element = document.getElementById('email_' + filename);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
}
</script> 