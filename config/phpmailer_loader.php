<?php
/**
 * Load PHPMailer via Composer autoload (preferred) or manual vendor paths.
 */
function loadPhpMailer() {
    static $loaded = false;
    if ($loaded) {
        return true;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $loaded = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
        return $loaded;
    }

    $base = dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/';
    $files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
    foreach ($files as $file) {
        $path = $base . $file;
        if (!file_exists($path)) {
            return false;
        }
        require_once $path;
    }

    $loaded = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    return $loaded;
}

function isPhpMailerAvailable() {
    return loadPhpMailer();
}
