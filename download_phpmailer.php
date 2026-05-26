<?php
// Script to manually download and install PHPMailer

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHPMailer Installer</h1>";

// Define the directory structure
$vendorDir = __DIR__ . '/vendor';
$phpmailerDir = $vendorDir . '/phpmailer/phpmailer';
$srcDir = $phpmailerDir . '/src';

// Create directories if they don't exist
if (!is_dir($vendorDir)) {
    echo "<p>Creating vendor directory...</p>";
    if (!mkdir($vendorDir, 0755, true)) {
        die("<p>Failed to create vendor directory!</p>");
    }
}

if (!is_dir($phpmailerDir)) {
    echo "<p>Creating phpmailer directory...</p>";
    if (!mkdir($phpmailerDir, 0755, true)) {
        die("<p>Failed to create phpmailer directory!</p>");
    }
}

if (!is_dir($srcDir)) {
    echo "<p>Creating src directory...</p>";
    if (!mkdir($srcDir, 0755, true)) {
        die("<p>Failed to create src directory!</p>");
    }
}

// The URL to download PHPMailer (latest version)
$phpmailerUrl = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip';
$zipFile = __DIR__ . '/phpmailer.zip';

echo "<p>Downloading PHPMailer...</p>";

// Try to download the zip file
if (function_exists('curl_version')) {
    $ch = curl_init($phpmailerUrl);
    $fp = fopen($zipFile, 'w');
    
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $success = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    
    if (!$success) {
        echo "<p>Failed to download PHPMailer using cURL!</p>";
        unlink($zipFile);
    } else {
        echo "<p>Download successful!</p>";
    }
} elseif (ini_get('allow_url_fopen')) {
    $fileContent = file_get_contents($phpmailerUrl);
    if ($fileContent === false) {
        echo "<p>Failed to download PHPMailer using file_get_contents!</p>";
    } else {
        file_put_contents($zipFile, $fileContent);
        echo "<p>Download successful!</p>";
    }
} else {
    echo "<p>Neither cURL nor file_get_contents is available. Cannot download PHPMailer!</p>";
    die();
}

// Check if the file was downloaded successfully
if (!file_exists($zipFile) || filesize($zipFile) < 100000) {
    echo "<p>Download seems to have failed or file is too small.</p>";
    die();
}

// Extract the zip file
echo "<p>Extracting PHPMailer...</p>";

$zip = new ZipArchive;
if ($zip->open($zipFile) === true) {
    // The ZIP contains a directory like "PHPMailer-6.9.1", so we need to extract from there
    $extractDir = __DIR__ . '/phpmailer-extract';
    $zip->extractTo($extractDir);
    $zip->close();
    
    echo "<p>Extraction completed!</p>";
    
    // Find the extracted directory
    $extracted = glob($extractDir . '/PHPMailer-*', GLOB_ONLYDIR);
    if (empty($extracted)) {
        echo "<p>Could not find extracted PHPMailer directory!</p>";
        die();
    }
    
    $sourceDir = $extracted[0];
    echo "<p>Found extracted directory: " . basename($sourceDir) . "</p>";
    
    // Copy the needed files
    echo "<p>Copying files...</p>";
    
    // Copy src directory
    $files = [
        '/src/PHPMailer.php' => $srcDir . '/PHPMailer.php',
        '/src/SMTP.php' => $srcDir . '/SMTP.php',
        '/src/Exception.php' => $srcDir . '/Exception.php',
        '/src/POP3.php' => $srcDir . '/POP3.php'
    ];
    
    foreach ($files as $src => $dest) {
        if (copy($sourceDir . $src, $dest)) {
            echo "<p>Copied " . basename($src) . " successfully.</p>";
        } else {
            echo "<p>Failed to copy " . basename($src) . "!</p>";
        }
    }
    
    // Create a simple autoload file
    $autoloadContent = '<?php
// Simple autoloader for PHPMailer
spl_autoload_register(function ($class) {
    // Base directory for PHPMailer classes
    $base_dir = __DIR__ . \'/phpmailer/phpmailer/src/\';
    
    // Replace namespace prefix with base directory
    $namespace = \'PHPMailer\\\\PHPMailer\\\\\';
    $len = strlen($namespace);
    if (strncmp($namespace, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace(\'\\\\\', \'/\', $relative_class) . \'.php\';
    
    if (file_exists($file)) {
        require $file;
    }
});';

    file_put_contents($vendorDir . '/autoload.php', $autoloadContent);
    echo "<p>Created simple autoload file.</p>";
    
    // Clean up
    echo "<p>Cleaning up...</p>";
    unlink($zipFile);
    
    // Delete the extract directory
    function deleteDir($dirPath) {
        if (!is_dir($dirPath)) {
            return;
        }
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $path = $dirPath . '/' . $file;
            is_dir($path) ? deleteDir($path) : unlink($path);
        }
        return rmdir($dirPath);
    }
    
    deleteDir($extractDir);
    echo "<p>Cleanup completed!</p>";
    
    echo "<div style='padding: 10px; background-color: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d; margin: 20px 0;'>";
    echo "<h3>Installation Successful!</h3>";
    echo "<p>PHPMailer has been installed successfully!</p>";
    echo "</div>";
    
    echo "<p><a href='vendor_check.php'>Check PHPMailer Installation</a></p>";
    echo "<p><a href='email_test.php'>Run Email Test</a></p>";
    
} else {
    echo "<p>Failed to open the downloaded zip file!</p>";
    unlink($zipFile);
    die();
}
?> 