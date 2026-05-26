<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Vendor and PHPMailer Check</h1>";

// Check if vendor directory exists
echo "<h2>Checking Vendor Directory</h2>";
if (is_dir('vendor')) {
    echo "<p style='color: green;'>✓ Vendor directory exists</p>";
} else {
    echo "<p style='color: red;'>✗ Vendor directory does not exist</p>";
}

// Check if autoload.php exists
echo "<h2>Checking Autoload</h2>";
if (file_exists('vendor/autoload.php')) {
    echo "<p style='color: green;'>✓ vendor/autoload.php exists</p>";
} else {
    echo "<p style='color: red;'>✗ vendor/autoload.php does not exist</p>";
}

// Check PHPMailer files
echo "<h2>Checking PHPMailer Files</h2>";
$phpmailer_files = [
    'vendor/phpmailer/phpmailer/src/PHPMailer.php',
    'vendor/phpmailer/phpmailer/src/SMTP.php',
    'vendor/phpmailer/phpmailer/src/Exception.php'
];

$all_present = true;
foreach ($phpmailer_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file exists</p>";
    } else {
        echo "<p style='color: red;'>✗ $file does not exist</p>";
        $all_present = false;
    }
}

if (!$all_present) {
    echo "<div style='margin-top: 20px; padding: 10px; background-color: #f2dede; border: 1px solid #ebccd1; color: #a94442;'>";
    echo "<h3>PHPMailer Not Properly Installed</h3>";
    echo "<p>It appears that PHPMailer is not properly installed. You can install it using one of these methods:</p>";
    echo "<ol>";
    echo "<li><strong>Using Composer:</strong> Run <code>composer require phpmailer/phpmailer</code></li>";
    echo "<li><strong>Manual Installation:</strong> Download from <a href='https://github.com/PHPMailer/PHPMailer/releases'>GitHub</a> and extract to vendor/phpmailer/phpmailer/</li>";
    echo "</ol>";
    echo "</div>";
}

// Try loading PHPMailer
echo "<h2>Trying to Load PHPMailer</h2>";
try {
    if (file_exists('vendor/autoload.php')) {
        echo "<p>Trying to load via autoload...</p>";
        require 'vendor/autoload.php';
        
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "<p style='color: green;'>✓ PHPMailer class loaded via autoload</p>";
        } else {
            echo "<p style='color: red;'>✗ PHPMailer class not found in autoload</p>";
        }
    }
    
    if (file_exists('vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
        echo "<p>Trying to load directly...</p>";
        require_once 'vendor/phpmailer/phpmailer/src/Exception.php';
        require_once 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once 'vendor/phpmailer/phpmailer/src/SMTP.php';
        
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "<p style='color: green;'>✓ PHPMailer class loaded directly</p>";
        } else {
            echo "<p style='color: red;'>✗ PHPMailer class not found via direct loading</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error loading PHPMailer: " . $e->getMessage() . "</p>";
}

// Check mail_config.php
echo "<h2>Checking mail_config.php</h2>";
if (file_exists('config/mail_config.php')) {
    echo "<p style='color: green;'>✓ config/mail_config.php exists</p>";
    
    // Load the mail config and display key parameters (obscuring password)
    require_once 'config/mail_config.php';
    
    if (isset($mail_config) && is_array($mail_config)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Parameter</th><th>Value</th></tr>";
        
        $keys = ['use_smtp', 'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_auth', 'smtp_username', 'from_email', 'from_name'];
        foreach ($keys as $key) {
            echo "<tr>";
            echo "<td>$key</td>";
            echo "<td>" . (isset($mail_config[$key]) ? htmlspecialchars($mail_config[$key]) : 'not set') . "</td>";
            echo "</tr>";
        }
        
        // Show partial password
        echo "<tr>";
        echo "<td>smtp_password</td>";
        if (isset($mail_config['smtp_password'])) {
            $password = $mail_config['smtp_password'];
            $masked = substr($password, 0, 4) . str_repeat('*', max(0, strlen($password) - 4));
            echo "<td>$masked</td>";
        } else {
            echo "<td>not set</td>";
        }
        echo "</tr>";
        
        echo "</table>";
    } else {
        echo "<p style='color: red;'>✗ \$mail_config variable not properly set in config/mail_config.php</p>";
    }
} else {
    echo "<p style='color: red;'>✗ config/mail_config.php does not exist</p>";
}

// Installation instructions
echo "<h2>Installation Instructions</h2>";
echo "<p>If PHPMailer is missing, you can install it using Composer:</p>";
echo "<pre>composer require phpmailer/phpmailer</pre>";

echo "<p>Or download PHPMailer manually:</p>";
echo "<ol>";
echo "<li>Download the latest release from <a href='https://github.com/PHPMailer/PHPMailer/releases'>GitHub</a></li>";
echo "<li>Create the directory structure: vendor/phpmailer/phpmailer/</li>";
echo "<li>Extract the files from the downloaded archive into this directory</li>";
echo "</ol>";

echo "<p><a href='email_test.php'>Run Email Test</a></p>";
?> 