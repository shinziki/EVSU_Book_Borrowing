<?php
require_once __DIR__ . '/phpmailer_loader.php';

/**
 * Normalize SMTP settings so port and encryption match (fixes common Gmail failures).
 */
function normalizeMailConfig(array $config) {
    if (empty($config['smtp_host'])) {
        return $config;
    }

    $normalized = $config;
    $port = (int) ($config['smtp_port'] ?? 587);
    $encryption = strtolower($config['smtp_encryption'] ?? $config['smtp_secure'] ?? 'tls');

    if ($encryption === 'none' || $encryption === '') {
        $normalized['smtp_secure'] = '';
        $normalized['smtp_encryption'] = 'none';
    } elseif ($port === 465 || $encryption === 'ssl' || $encryption === 'smtps') {
        $normalized['smtp_port'] = '465';
        $normalized['smtp_secure'] = 'ssl';
        $normalized['smtp_encryption'] = 'ssl';
    } else {
        $normalized['smtp_port'] = '587';
        $normalized['smtp_secure'] = 'tls';
        $normalized['smtp_encryption'] = 'tls';
    }

    $normalized['use_smtp'] = !empty($config['use_smtp']) || !empty($config['smtp_username']);
    $normalized['smtp_auth'] = $normalized['use_smtp'];

    if (empty($normalized['from_email']) && !empty($config['smtp_username'])) {
        $normalized['from_email'] = $config['smtp_username'];
    }
    if (empty($normalized['from_name'])) {
        $normalized['from_name'] = 'Coffee Prince Library';
    }

    $normalized['smtp_options'] = $config['smtp_options'] ?? [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    return $normalized;
}

/**
 * Apply normalized SMTP settings to a PHPMailer instance.
 */
function applySmtpToMailer($mail, array $config) {
    $config = normalizeMailConfig($config);

    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->Port = (int) $config['smtp_port'];
    $mail->SMTPAuth = !empty($config['smtp_auth']);
    $mail->Username = $config['smtp_username'] ?? '';
    $mail->Password = $config['smtp_password'] ?? '';
    $mail->Timeout = (int) ($config['smtp_timeout'] ?? 60);

    $secure = $config['smtp_secure'] ?? '';
    if ($secure === 'tls') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($secure === 'ssl') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = '';
        $mail->SMTPAutoTLS = false;
    }

    if (!empty($config['smtp_options'])) {
        $mail->SMTPOptions = $config['smtp_options'];
    }

    return $config;
}

/**
 * Test SMTP connection; tries alternate Gmail port if first attempt fails.
 */
function testSmtpConnection(array $config) {
    if (!isPhpMailerAvailable()) {
        return ['success' => false, 'message' => 'PHPMailer not found. Run: composer install'];
    }

    $config = normalizeMailConfig($config);
    $attempts = [
        ['port' => (int) $config['smtp_port'], 'secure' => $config['smtp_secure']],
    ];

    if (stripos($config['smtp_host'], 'gmail') !== false) {
        if ($config['smtp_secure'] === 'ssl') {
            $attempts[] = ['port' => 587, 'secure' => 'tls'];
        } else {
            $attempts[] = ['port' => 465, 'secure' => 'ssl'];
        }
    }

    $lastError = 'SMTP connection failed';
    foreach ($attempts as $attempt) {
        $tryConfig = $config;
        $tryConfig['smtp_port'] = (string) $attempt['port'];
        $tryConfig['smtp_secure'] = $attempt['secure'];
        $tryConfig['smtp_encryption'] = $attempt['secure'];

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            applySmtpToMailer($mail, $tryConfig);
            $mail->SMTPDebug = 0;
            if ($mail->smtpConnect()) {
                $mail->smtpClose();
                $hint = ($attempt['port'] !== (int) $config['smtp_port'])
                    ? " (works on port {$attempt['port']} with {$attempt['secure']})"
                    : '';
                return [
                    'success' => true,
                    'message' => 'SMTP connection successful' . $hint,
                    'port' => $attempt['port'],
                    'encryption' => $attempt['secure'],
                ];
            }
        } catch (\Exception $e) {
            $lastError = $mail->ErrorInfo ?: $e->getMessage();
        }
    }

    if (stripos($config['smtp_host'], 'gmail') !== false) {
        $lastError .= '. For Gmail use an App Password (not your normal password): https://myaccount.google.com/apppasswords';
    }

    return ['success' => false, 'message' => $lastError];
}

function getLibraryEmailLogoPath() {
    $root = dirname(__DIR__);
    foreach ([
        $root . '/logo/EVSU_Official_Logo.png',
        $root . '/logo/EVSU_Official_Logo.jpg',
    ] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * Original welcome text as plain string (unchanged wording).
 */
function buildMemberWelcomePlainText($fullname, $barcode) {
    $message = 'Dear ' . $fullname . ",\n\n";
    $message .= "Welcome to the Coffee Prince Library! We're excited to have you as a member.\n\n";
    $message .= "Your membership details:\n";
    $message .= 'Member ID: ' . $barcode . "\n\n";
    $message .= "You can use this barcode when borrowing books from our library.\n\n";
    $message .= "Thank you for joining our community!\n\n";
    $message .= "Best regards,\nCoffee Prince Library Team";
    return $message;
}

/**
 * Same welcome message as plain text, as HTML with optional logo source.
 */
function buildMemberWelcomeHtml($fullname, $barcode, $logoSrc = '') {
    $name = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
    $id = htmlspecialchars($barcode, ENT_QUOTES, 'UTF-8');

    $logo = '';
    if (!empty($logoSrc)) {
        $safeLogoSrc = htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8');
        $logo = '<p style="text-align:center;margin:0 0 20px;">'
            . '<img src="' . $safeLogoSrc . '" alt="EVSU Logo" style="width:100px;height:auto;display:block;margin:0 auto;">'
            . '</p>';
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;line-height:1.6;">'
        . $logo
        . '<p>Dear ' . $name . ',</p>'
        . "<p>Welcome to the Coffee Prince Library! We're excited to have you as a member.</p>"
        . '<p>Your membership details:<br>Member ID: <strong>' . $id . '</strong></p>'
        . '<p>You can use this barcode when borrowing books from our library.</p>'
        . '<p>Thank you for joining our community!</p>'
        . '<p>Best regards,<br>Coffee Prince Library Team</p>'
        . '</body></html>';
}

/**
 * Send member welcome email: same message, HTML format, logo from logo/ folder.
 */
function sendMemberWelcomeEmail($to, $fullname, $barcode) {
    global $mail_config;

    if (!function_exists('isPhpMailerAvailable')) {
        require_once __DIR__ . '/phpmailer_loader.php';
    }

    $subject = 'Welcome to Coffee Prince Library';
    $plain = buildMemberWelcomePlainText($fullname, $barcode);
    $logoPath = getLibraryEmailLogoPath();

    // Decide initial logo source for non-SMTP paths (simple HTTP URL so most clients can load it).
    $fallbackLogoUrl = 'https://upload.wikimedia.org/wikipedia/commons/c/c3/EVSU_Official_Logo.png';
    $initialLogoSrc = $fallbackLogoUrl;

    $html = buildMemberWelcomeHtml($fullname, $barcode, $initialLogoSrc);
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";

    if (!isPhpMailerAvailable()) {
        if (!function_exists('sendEmail')) {
            require_once __DIR__ . '/mailer.php';
        }
        return sendEmail($to, $subject, $html, $headers, $plain);
    }

    $config = normalizeMailConfig($mail_config ?? []);
    if (empty($config['use_smtp']) || empty($config['smtp_host'])) {
        if (!function_exists('sendEmail')) {
            require_once __DIR__ . '/mailer.php';
        }
        return sendEmail($to, $subject, $html, $headers, $plain);
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        applySmtpToMailer($mail, $config);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);
        $mail->addReplyTo($config['from_email'], $config['from_name']);
        if ($logoPath) {
            // CID inline images are the most reliable way to render local logos in email clients.
            $mail->addEmbeddedImage(
                $logoPath,
                'librarylogo',
                'EVSU_Official_Logo.png',
                \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                'image/png',
                'inline'
            );
            $html = buildMemberWelcomeHtml($fullname, $barcode, 'cid:librarylogo');
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $plain;
        return $mail->send();
    } catch (\Exception $e) {
        if (!function_exists('sendEmail')) {
            require_once __DIR__ . '/mailer.php';
        }
        return sendEmail($to, $subject, $html, $headers, $plain);
    }
}
