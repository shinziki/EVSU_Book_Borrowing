<?php
// Include mail configuration
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/phpmailer_loader.php';
require_once __DIR__ . '/mail_helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$usePhpMailer = isPhpMailerAvailable();

// Normalize config keys used by sendEmail()
if (isset($mail_config) && is_array($mail_config)) {
    $mail_config = normalizeMailConfig($mail_config);
}

$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}
error_log(
    date('Y-m-d H:i:s') . ' - PHPMailer: ' . ($usePhpMailer ? 'INSTALLED' : 'NOT FOUND') . "\n",
    3,
    $logsDir . '/email_log.txt'
);

/**
 * Helper function to send email with proper error handling and logging
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body
 * @param string $headers Additional headers (optional)
 * @param string $altBody Plain text alternative body (optional)
 * @return bool True if email sent successfully, false otherwise
 */
function sendEmail($to, $subject, $message, $headers = '', $altBody = '') {
    global $mail_config, $usePhpMailer;
    
    // Define logs directory with proper path
    $logsDir = __DIR__ . '/../logs';
    $logFile = $logsDir . '/email_log.txt';
    
    // Log the attempt
    $logMessage = date('Y-m-d H:i:s') . " - Attempting to send email to: " . $to . " | Subject: " . $subject . "\n";
    
    // Make sure logs directory exists
    if (!is_dir($logsDir)) {
        if (!mkdir($logsDir, 0755, true)) {
            // If we can't create the logs directory, try to use the current directory
            $logsDir = '.';
            $logFile = './email_log.txt';
        }
    }
    
    error_log($logMessage, 3, $logFile);
    
    // Store email in file - for backup and development environments
    $emailsDir = __DIR__ . '/../emails';
    if (!is_dir($emailsDir)) {
        if (!mkdir($emailsDir, 0755, true)) {
            error_log(date('Y-m-d H:i:s') . " - Email status: FAILED (Cannot create emails directory)\n", 3, $logFile);
            return false;
        }
    }
    
    // Extract sender info from headers or use defaults
    $fromName = 'Coffee Prince Library';
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
    
    // Generate a filename based on timestamp and recipient
    $timestamp = date('Ymd_His');
    $safeEmail = str_replace(['@', '.', '+'], '_', $to);
    $filename = $emailsDir . '/email_' . $timestamp . '_' . $safeEmail . '.txt';
    
    // Create email file content
    $emailContent = "To: $to\n";
    $emailContent .= "From: $fromName <$fromEmail>\n";
    $emailContent .= "Subject: $subject\n";
    if (!empty($headers)) {
        $emailContent .= "Headers: $headers\n";
    }
    $emailContent .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $emailContent .= "-------------------------------------------\n";
    $emailContent .= $message;
    
    // Save to file as backup
    file_put_contents($filename, $emailContent);
    error_log(date('Y-m-d H:i:s') . " - Email saved to file: $filename\n", 3, $logFile);
    
    $mail_config = normalizeMailConfig($mail_config ?? []);
    $usePhpMailer = isPhpMailerAvailable();
    error_log(date('Y-m-d H:i:s') . " - PHPMailer detection check: " . ($usePhpMailer ? "FOUND" : "NOT FOUND") . "\n", 3, $logFile);
    
    // Try to send via SMTP when host and credentials are configured
    if (!empty($mail_config['use_smtp']) && $usePhpMailer) {
        try {
            $mail = new PHPMailer(true);
            applySmtpToMailer($mail, $mail_config);
            
            $mail->SMTPDebug = isset($mail_config['smtp_debug']) ? $mail_config['smtp_debug'] : 0;
            $mail->Debugoutput = function($str, $level) use ($logFile) {
                error_log(date('Y-m-d H:i:s') . " - SMTP Debug: $str\n", 3, $logFile);
            };
            
            error_log(date('Y-m-d H:i:s') . " - Using SMTP: host={$mail_config['smtp_host']}, port={$mail_config['smtp_port']}, secure={$mail_config['smtp_secure']}\n", 3, $logFile);
            
            $mail->setFrom($mail_config['from_email'], $mail_config['from_name']);
            $mail->addAddress($to);
            $mail->addReplyTo($mail_config['from_email'], $mail_config['from_name']);
            
            error_log(date('Y-m-d H:i:s') . " - Set from: {$mail_config['from_email']}, to: {$to}\n", 3, $logFile);
            
            // Check if email is HTML format (by looking at headers)
            $isHtml = false;
            if (!empty($headers) && strpos($headers, 'text/html') !== false) {
                $isHtml = true;
                error_log(date('Y-m-d H:i:s') . " - Detected HTML email\n", 3, $logFile);
            }
            
            // Content
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // If HTML email, generate plain text alternative automatically
            if ($isHtml) {
                if (!empty($altBody)) {
                    // Use provided alt body if available
                    $mail->AltBody = $altBody;
                    error_log(date('Y-m-d H:i:s') . " - Using provided alt body\n", 3, $logFile);
                } else {
                    // Create a simple plaintext version by stripping tags
                    $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</tr>'], "\n", $message));
                    $plainText = preg_replace('/[\r\n]+/', "\n", $plainText); // Normalize line breaks
                    $plainText = html_entity_decode($plainText); // Convert HTML entities to characters
                    $mail->AltBody = trim($plainText);
                    error_log(date('Y-m-d H:i:s') . " - Generated alt body from HTML\n", 3, $logFile);
                }
            }
            
            // Give SMTP operations more time
            $originalTimeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', 60); // 60 seconds timeout
            
            error_log(date('Y-m-d H:i:s') . " - Attempting to send email via SMTP\n", 3, $logFile);
            
            // Send the email
            $success = $mail->send();
            
            // Restore original timeout
            ini_set('default_socket_timeout', $originalTimeout);
            
            if ($success) {
                error_log(date('Y-m-d H:i:s') . " - Email status: SUCCESS (sent via PHPMailer SMTP)\n", 3, $logFile);
                return true;
            } else {
                error_log(date('Y-m-d H:i:s') . " - Email status: FAILED (PHPMailer couldn't send: " . $mail->ErrorInfo . ")\n", 3, $logFile);
                
                // Try Gmail-specific approach
                if (stripos($mail_config['smtp_host'], 'gmail') !== false) {
                    error_log(date('Y-m-d H:i:s') . " - Attempting Gmail-specific method\n", 3, $logFile);
                    
                    try {
                        // Create a new Gmail-specific instance
                        $gmailMail = new PHPMailer(true);
                        $gmailMail->isSMTP();
                        $gmailMail->Host = 'smtp.gmail.com';
                        $gmailMail->SMTPAuth = true;
                        $gmailMail->Username = $mail_config['smtp_username'];
                        $gmailMail->Password = $mail_config['smtp_password'];
                        
                        // Try alternative port/security combinations for Gmail
                        if ($mail_config['smtp_port'] == 587) {
                            $gmailMail->Port = 465;
                            $gmailMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            error_log(date('Y-m-d H:i:s') . " - Gmail fallback: Switching to port 465 with SSL\n", 3, $logFile);
                        } else {
                            $gmailMail->Port = 587;
                            $gmailMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            error_log(date('Y-m-d H:i:s') . " - Gmail fallback: Switching to port 587 with TLS\n", 3, $logFile);
                        }
                        
                        // Add SSL options to bypass potential certificate issues
                        $gmailMail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );
                        
                        // Recipients
                        $gmailMail->setFrom($mail_config['smtp_username'], $mail_config['from_name']);
                        $gmailMail->addAddress($to);
                        
                        // Content
                        $gmailMail->isHTML($isHtml);
                        $gmailMail->Subject = $subject;
                        $gmailMail->Body = $message;
                        
                        if ($isHtml && !empty($altBody)) {
                            $gmailMail->AltBody = $altBody;
                        }
                        
                        // Extend timeout
                        $gmailMail->Timeout = 60;
                        
                        // Attempt Gmail-specific sending
                        $gmailSuccess = $gmailMail->send();
                        
                        if ($gmailSuccess) {
                            error_log(date('Y-m-d H:i:s') . " - Email status: SUCCESS (sent via Gmail-specific method)\n", 3, $logFile);
                            return true;
                        } else {
                            error_log(date('Y-m-d H:i:s') . " - Gmail-specific method also failed: " . $gmailMail->ErrorInfo . "\n", 3, $logFile);
                        }
                    } catch (Exception $gmailEx) {
                        error_log(date('Y-m-d H:i:s') . " - Gmail-specific exception: " . $gmailEx->getMessage() . "\n", 3, $logFile);
                    }
                }
                
                // Try to fall back to basic mail() function
                error_log(date('Y-m-d H:i:s') . " - Attempting fallback to mail() function\n", 3, $logFile);
                
                // Set up email headers
                $mailHeaders = "From: $fromName <$fromEmail>\r\n";
                if (!empty($headers)) {
                    $mailHeaders .= $headers;
                }
                
                // Try mail() function as a last resort
                if (mail($to, $subject, $isHtml ? $plainText : $message, $mailHeaders)) {
                    error_log(date('Y-m-d H:i:s') . " - Fallback mail() succeeded\n", 3, $logFile);
        return true;
    } else {
                    error_log(date('Y-m-d H:i:s') . " - Fallback mail() failed\n", 3, $logFile);
        return false;
                }
            }
            
        } catch (Exception $e) {
            error_log(date('Y-m-d H:i:s') . " - PHPMailer Exception: " . $e->getMessage() . "\n", 3, $logFile);
            error_log(date('Y-m-d H:i:s') . " - Stack trace: " . $e->getTraceAsString() . "\n", 3, $logFile);
            error_log(date('Y-m-d H:i:s') . " - Falling back to mail() function\n", 3, $logFile);
            
            // Try Brevo API as a second fallback
            if (function_exists('sendEmailViaBrevo')) {
                error_log(date('Y-m-d H:i:s') . " - Attempting to send via Brevo API\n", 3, $logFile);
                try {
                    $brevoResult = sendEmailViaBrevo($to, $subject, $isHtml ? $message : nl2br($message));
                    if ($brevoResult) {
                        error_log(date('Y-m-d H:i:s') . " - Brevo API sending succeeded\n", 3, $logFile);
                        return true;
                    } else {
                        error_log(date('Y-m-d H:i:s') . " - Brevo API sending failed\n", 3, $logFile);
                    }
                } catch (Exception $brevoEx) {
                    error_log(date('Y-m-d H:i:s') . " - Brevo API exception: " . $brevoEx->getMessage() . "\n", 3, $logFile);
                }
            }
            
            // Try basic mail() function as a last resort
            $mailHeaders = "From: $fromName <$fromEmail>\r\n";
            if (!empty($headers)) {
                $mailHeaders .= $headers;
            }
            
            $plainText = $message;
            if (strpos($headers, 'text/html') !== false && !empty($altBody)) {
                $plainText = $altBody;
            } else if (strpos($headers, 'text/html') !== false) {
                $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</tr>'], "\n", $message));
                $plainText = html_entity_decode($plainText);
            }
            
            if (mail($to, $subject, $plainText, $mailHeaders)) {
                error_log(date('Y-m-d H:i:s') . " - Fallback mail() succeeded\n", 3, $logFile);
                return true;
            } else {
                error_log(date('Y-m-d H:i:s') . " - Fallback mail() failed\n", 3, $logFile);
                return false;
            }
        }
    } else {
        // If SMTP not configured or PHPMailer not available, try to use mail()
        if (!$usePhpMailer) {
            error_log(date('Y-m-d H:i:s') . " - PHPMailer not found, using PHP mail() function\n", 3, $logFile);
        } else {
            error_log(date('Y-m-d H:i:s') . " - SMTP not configured, using PHP mail() function\n", 3, $logFile);
        }
        
        // Set up email headers
        $mailHeaders = "From: $fromName <$fromEmail>\r\n";
        if (!empty($headers)) {
            $mailHeaders .= $headers;
        }
        
        $plainText = $message;
        if (strpos($headers, 'text/html') !== false && !empty($altBody)) {
            $plainText = $altBody;
        } else if (strpos($headers, 'text/html') !== false) {
            $plainText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</tr>'], "\n", $message));
            $plainText = html_entity_decode($plainText);
        }
        
        if (mail($to, $subject, $plainText, $mailHeaders)) {
            error_log(date('Y-m-d H:i:s') . " - PHP mail() succeeded\n", 3, $logFile);
            return true;
        } else {
            error_log(date('Y-m-d H:i:s') . " - PHP mail() failed, but email is saved to file\n", 3, $logFile);
            error_log(date('Y-m-d H:i:s') . " - Email status: FALLBACK SUCCESS (Saved to file: $filename)\n", 3, $logFile);
            return true; // Return true as we have a file backup
        }
    }
}

/**
 * Send email using Brevo API
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body
 * @return bool True if email sent successfully, false otherwise
 */
function sendEmailViaBrevo($to, $subject, $message) {
    global $mail_config;
    
    // Brevo API key
    $apiKey = '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';
    
    // Set default values if mail_config is not set
    $fromName = 'Coffee Prince Library';
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
    
    // Prepare the request
    $url = 'https://api.sendinblue.com/v3/smtp/email';
    $headers = [
        'Content-Type: application/json',
        'api-key: ' . $apiKey
    ];
    
    // Initialize cURL
    $curl = curl_init();
    
    // Set cURL options
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    // Execute the request
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    
    // Close cURL
    curl_close($curl);
    
    // Log the response
    $logsDir = __DIR__ . '/../logs';
    $logFile = $logsDir . '/brevo_api_log.txt';
    
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    error_log(date('Y-m-d H:i:s') . " - Brevo API Response Code: " . $httpCode . "\n", 3, $logFile);
    
    if (!empty($error)) {
        error_log(date('Y-m-d H:i:s') . " - Brevo API Error: " . $error . "\n", 3, $logFile);
    }
    
    if (!empty($response)) {
        error_log(date('Y-m-d H:i:s') . " - Brevo API Response: " . $response . "\n", 3, $logFile);
    }
    
    // Return success or failure
    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Generate email headers with proper format
 * 
 * @param array $options Additional header options
 * @return string Formatted email headers
 */
function generateEmailHeaders($options = []) {
    global $mail_config;
    
    $headers = "";
    
    // Set default values
    $fromName = 'Coffee Prince Library';
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
    
    // Override with options if provided
    $from_email = $options['from_email'] ?? $fromEmail;
    $from_name = $options['from_name'] ?? $fromName;
    
    // Add From header
    $headers .= "From: " . $from_name . " <" . $from_email . ">\r\n";
    
    // Reply-To
    if (isset($options['reply_to'])) {
        $headers .= "Reply-To: " . $options['reply_to'] . "\r\n";
    }
    
    // Content Type
    $content_type = $options['content_type'] ?? 'text/plain';
    $headers .= "Content-Type: " . $content_type . "; charset=UTF-8\r\n";
    
    return $headers;
}
?> 