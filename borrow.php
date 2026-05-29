<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';
require_once 'config/mail_config.php'; // Ensure we have the mail config

require_once 'config/phpmailer_loader.php';
require_once 'config/mail_helpers.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send borrowing notification email
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $htmlMessage HTML email body
 * @param string $plainTextMessage Plain text email body (optional)
 * @param string $logFile Path to log file (optional)
 * @return bool True if email sent successfully
 */
function sendBorrowingEmail($to, $subject, $htmlMessage, $plainTextMessage = '', $logFile = null) {
    global $mail_config;

    // Create log directory and set log file if not provided
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    if ($logFile === null) {
        $logFile = 'logs/borrowing_emails.txt';
    }
    
    // Log start of email sending process
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Starting to send borrowing email to: $to\n", FILE_APPEND);
    
    try {
        // Use direct PHPMailer instance with known working settings
        $mail = new PHPMailer(true);
        
        // Basic setup
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $mail_config['smtp_username'];
        $mail->Password = $mail_config['smtp_password'];
        
        // Use SSL port and encryption (primary approach)
        $mail->Port = 465;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        
        // Extend timeout settings
        $mail->Timeout = 60;
        ini_set('default_socket_timeout', 60);
        
        // SSL Certificate Verification Options - skip verification
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Debug settings - capture debug output
        $mail->SMTPDebug = 2;
        ob_start();
        
        // Set sender and recipient
        $mail->setFrom($mail_config['smtp_username'], 'EVSU Book Borrowing System');
        $mail->addAddress($to);
        $mail->addReplyTo($mail_config['smtp_username'], 'EVSU Book Borrowing System');
        
        // Set content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlMessage;
        $mail->AltBody = $plainTextMessage;
        
        // Try to send
        $result = $mail->send();
        $debugOutput = ob_get_clean();
        
        // Log debug output
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Port 465/SSL attempt debug output:\n$debugOutput\n", FILE_APPEND);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Port 465/SSL result: " . ($result ? "SUCCESS" : "FAILED") . "\n", FILE_APPEND);
        
        // If first attempt failed, try port 587 with TLS
        if (!$result) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - First attempt failed, trying port 587/TLS\n", FILE_APPEND);
            
            $altMail = new PHPMailer(true);
            $altMail->isSMTP();
            $altMail->Host = 'smtp.gmail.com';
            $altMail->SMTPAuth = true;
            $altMail->Username = $mail_config['smtp_username'];
            $altMail->Password = $mail_config['smtp_password'];
            
            // Use TLS port and encryption (alternate approach)
            $altMail->Port = 587;
            $altMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            
            // Same extended settings
            $altMail->Timeout = 60;
            $altMail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Debug output for second attempt
            $altMail->SMTPDebug = 2;
            ob_start();
            
            // Set sender and recipient (same as before)
            $altMail->setFrom($mail_config['smtp_username'], 'EVSU Book Borrowing System');
            $altMail->addAddress($to);
            $altMail->addReplyTo($mail_config['smtp_username'], 'EVSU Book Borrowing System');
            
            // Set content (same as before)
            $altMail->isHTML(true);
            $altMail->Subject = $subject;
            $altMail->Body = $htmlMessage;
            $altMail->AltBody = $plainTextMessage;
            
            // Try to send
            $result = $altMail->send();
            $debugOutput = ob_get_clean();
            
            // Log debug output for second attempt
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Port 587/TLS attempt debug output:\n$debugOutput\n", FILE_APPEND);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Port 587/TLS result: " . ($result ? "SUCCESS" : "FAILED") . "\n", FILE_APPEND);
        }
        
        // If still failed, let's try one more time with direct send
        if (!$result) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Both SMTP attempts failed, trying direct send\n", FILE_APPEND);
            
            // Save a copy of the email
            $timestamp = date('Ymd_His');
            $safeEmail = str_replace(['@', '.', '+'], '_', $to);
            $emailDir = 'emails';
            if (!is_dir($emailDir)) {
                mkdir($emailDir, 0755, true);
            }
            $emailFile = "$emailDir/borrow_receipt_$timestamp"."_$safeEmail.html";
            file_put_contents($emailFile, $htmlMessage);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Saved email to $emailFile\n", FILE_APPEND);
            
            // Try PHP's mail() function as last resort
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: EVSU Book Borrowing System <{$mail_config['smtp_username']}>\r\n";
            $headers .= "Reply-To: {$mail_config['smtp_username']}\r\n";
            
            $mailResult = mail($to, $subject, $htmlMessage, $headers);
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Direct mail() result: " . ($mailResult ? "SUCCESS" : "FAILED") . "\n", FILE_APPEND);
            
            $result = $mailResult;
        }
        
        return $result;
    }
    catch (Exception $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - PHPMailer Exception: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

// Require login to access this page
requireLogin();

// Initialize variables
$bookInfo = null;
$memberInfo = null;
$message = '';
$messageType = '';
$completeBorrowVisible = false;
$paymentAmount = 0;
$courseOptions = getMemberCourseOptions();

// Handle reset scan process
if (isset($_GET['reset']) && $_GET['reset'] == '1') {
    unset($_SESSION['borrow_book_info']);
    unset($_SESSION['borrow_book_barcode']);
    header('Location: borrow.php');
    exit;
}

// Check if we already have a book scanned in session
if (isset($_SESSION['borrow_book_info'])) {
    $bookInfo = $_SESSION['borrow_book_info'];
    $bookBarcode = $_SESSION['borrow_book_barcode'];
    $completeBorrowVisible = true;
}

// Fixed 1-day borrowing period for all loans
define('BORROW_PERIOD_DAYS', 1);
define('BORROW_PAYMENT_AMOUNT', 0.00);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Book barcode scan
    if (isset($_POST['book_barcode']) && !empty($_POST['book_barcode'])) {
        $bookBarcode = $_POST['book_barcode'] ?? '';
        
        // Check if book exists and is available
        $bookInfo = getBookByBarcode($bookBarcode);
        
        if (!$bookInfo) {
            setFlashMessage('Book not found with barcode: ' . htmlspecialchars($bookBarcode), 'error');
            header('Location: borrow.php');
            exit;
        } elseif ($bookInfo['status'] !== 'Available' && $bookInfo['quantity'] <= 0) {
            // Get more detailed information about why the book is unavailable
            $qtyText = isset($bookInfo['quantity']) ? $bookInfo['quantity'] : 
                      (isset($bookInfo['stock']) ? $bookInfo['stock'] : '0');
            
            // Check if there are any active borrowings for this book
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as active_borrows 
                FROM transactions 
                WHERE book_id = :book_id AND status IN ('Borrowed', 'Overdue', 'Needs Replacement')
            ");
            $stmt->bindParam(':book_id', $bookInfo['id']);
            $stmt->execute();
            $result = $stmt->fetch();
            
            $activeLoans = $result ? (int)$result['active_borrows'] : 0;
            
            $message = 'Book is not available for borrowing. ';
            $message .= 'Status: ' . htmlspecialchars($bookInfo['status']) . ', ';
            $message .= 'Quantity: ' . htmlspecialchars($qtyText) . ', ';
            $message .= 'Currently on loan: ' . htmlspecialchars($activeLoans);
            
            setFlashMessage($message, 'error');
            header('Location: borrow.php');
            exit;
        } else {
            // Save book info to session
            $_SESSION['borrow_book_info'] = $bookInfo;
            $_SESSION['borrow_book_barcode'] = $bookBarcode;
            setFlashMessage('Book found: "' . htmlspecialchars($bookInfo['title']) . '". Now scan member barcode.', 'success');
            header('Location: borrow.php');
            exit;
        }
    }
    
    // Member barcode scan / inline newbie registration and complete borrowing
    if (((isset($_POST['member_barcode']) && !empty($_POST['member_barcode'])) || (isset($_POST['member_type']) && $_POST['member_type'] === 'new')) && 
        isset($_SESSION['borrow_book_info'])) {
        
        $memberType = $_POST['member_type'] ?? 'existing';
        $dueDays = BORROW_PERIOD_DAYS;
        $bookBarcode = $_SESSION['borrow_book_barcode'];
        $paymentAmount = BORROW_PAYMENT_AMOUNT;
        
        if ($memberType === 'new') {
            ensureMemberStudentIdColumn();
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $course = trim($_POST['course'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $studentId = trim($_POST['student_id'] ?? '');
            $studentIdToSave = !empty($studentId) ? $studentId : null;
            $courseOptions = getMemberCourseOptions();

            if (empty($fullname)) {
                setFlashMessage('Member name is required', 'error');
                header('Location: borrow.php');
                exit;
            }
            if (empty($course) || !array_key_exists($course, $courseOptions)) {
                setFlashMessage('Please select a valid course', 'error');
                header('Location: borrow.php');
                exit;
            }

            try {
                if ($studentIdToSave !== null) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE student_id = :student_id");
                    $stmt->execute([':student_id' => $studentIdToSave]);
                    if ($stmt->fetchColumn() > 0) {
                        setFlashMessage('A member with this Student ID already exists.', 'error');
                        header('Location: borrow.php');
                        exit;
                    }
                }

                // Always generate a MEMXXXX barcode (student_id is stored separately)
                $memberBarcode = generateMemberBarcode();

                // Add new member (default status active, notifications enabled)
                $stmt = $pdo->prepare("
                    INSERT INTO members (fullname, email, phone, address, course, student_id, status, barcode, notifications_enabled)
                    VALUES (:fullname, :email, :phone, :address, :course, :student_id, 'active', :barcode, 1)
                ");
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':course', $course);
                $stmt->bindParam(':student_id', $studentIdToSave);
                $stmt->bindParam(':barcode', $memberBarcode);
                $stmt->execute();

                // Retrieve the newly inserted member info
                $memberInfo = getMemberByBarcode($memberBarcode);

                // Send welcome email
                if (!empty($email)) {
                    $emailResult = sendMemberWelcomeEmail($email, $fullname, $memberBarcode);
                    
                    // Log the email attempt
                    $logsDir = "logs";
                    if (!is_dir($logsDir)) {
                        mkdir($logsDir, 0755, true);
                    }
                    $logMessage = date('Y-m-d H:i:s') . " - Welcome email to new newbie member: " . $email . " (ID: " . $memberBarcode . ") - Status: " . ($emailResult ? "SUCCESS" : "FAILED") . "\n";
                    file_put_contents($logsDir . "/member_notifications.txt", $logMessage, FILE_APPEND);
                }
            } catch (PDOException $e) {
                setFlashMessage('Error registering new member: ' . $e->getMessage(), 'error');
                header('Location: borrow.php');
                exit;
            }
        } else {
            $memberBarcode = $_POST['member_barcode'] ?? '';
            // Check if member exists
            $memberInfo = getMemberByBarcode($memberBarcode);
            
            if (!$memberInfo) {
                setFlashMessage('Member not found with barcode: ' . htmlspecialchars($memberBarcode), 'error');
                header('Location: borrow.php');
                exit;
            } elseif (!isMemberActive($memberInfo)) {
                // Reactivate member when they try to borrow again
                if (!empty($memberInfo['id'])) {
                    reactivateMemberById((int) $memberInfo['id']);
                    $memberInfo = getMemberByBarcode($memberBarcode);
                }

                if (!isMemberActive($memberInfo)) {
                    setFlashMessage('This member is inactive and cannot borrow books.', 'error');
                    header('Location: borrow.php');
                    exit;
                }
            }
        }

        // Process borrowing using member info
        if (borrowBook($bookBarcode, $memberBarcode, $dueDays, $paymentAmount)) {
            // Get the transaction ID for the receipt
            $stmt = $pdo->prepare("
                SELECT t.id, t.borrow_date, t.due_date, t.payment_amount 
                FROM transactions t 
                JOIN books b ON t.book_id = b.id 
                WHERE b.barcode = :barcode 
                ORDER BY t.id DESC LIMIT 1
            ");
            $stmt->bindParam(':barcode', $bookBarcode);
            $stmt->execute();
            $transactionData = $stmt->fetch();
            
            // Generate and send email notification if enabled
            $isSent = false;
            if ($memberInfo['notifications_enabled'] && !empty($memberInfo['email'])) {
                // Log that we're attempting to send a borrowing receipt email
                if (!is_dir('logs')) {
                    mkdir('logs', 0755, true);
                }
                $borrowLogFile = 'logs/borrowing_emails.txt';
                file_put_contents($borrowLogFile, date('Y-m-d H:i:s') . " - Starting borrowing receipt email process for: " . $memberInfo['email'] . " (Transaction ID: " . $transactionData['id'] . ")\n", FILE_APPEND);
                
                // Create a dedicated log file for this transaction
                $borrowLogFile = 'logs/borrowing_tx_' . $transactionData['id'] . '.log';
                file_put_contents($borrowLogFile, date('Y-m-d H:i:s') . " - Processing borrowing receipt email for transaction ID: " . $transactionData['id'] . "\n", FILE_APPEND);
                
                // Generate barcode image for transaction
                $transactionBarcode = "TRX" . $transactionData['id'];
                $barcodeImage = generateBarcodeImage($transactionBarcode);
                
                // Create a detailed email receipt similar to borrow_receipt.php
                $emailSubject = "EVSU Book Borrowing System - Borrowing Receipt #" . $transactionData['id'];
                
                // Start building HTML email content with styling
                $emailMessage = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.5; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin-bottom: 5px; color: #333; }
        .section { margin-bottom: 20px; }
        .section h3 { margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .info-box { background-color: #f9f9f9; padding: 10px; border-radius: 4px; }
        .table { width: 100%; border-collapse: collapse; }
        .table td { padding: 8px 0; }
        .table .border-bottom { border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; font-size: 0.9em; color: #777; border-top: 1px solid #eee; padding-top: 20px; }
        ul { padding-left: 20px; }
        .barcode-container { text-align: center; margin: 20px 0; }
        .barcode-info { font-size: 12px; color: #777; margin-top: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>EVSU Book Borrowing System</h1>
            <p>Library Borrowing System</p>
            <p style='font-size: 0.9em; color: #777;'>Transaction #" . $transactionData['id'] . "</p>
        </div>
        
        <div class='barcode-container'>
            <img src='" . $barcodeImage . "' alt='Transaction Barcode' style='max-width: 100%; height: auto;'>
            <p class='barcode-info'>Scan this barcode when returning the book</p>
        </div>
        
        <div class='section'>
            <div style='display: flex; justify-content: space-between;'>
                <div>
                    <p style='font-size: 0.9em; color: #777;'>Date Borrowed:</p>
                    <p style='font-weight: bold;'>" . date('F j, Y, g:i A', strtotime($transactionData['borrow_date'])) . "</p>
                </div>
                <div>
                    <p style='font-size: 0.9em; color: #777;'>Due Date:</p>
                    <p style='font-weight: bold;'>" . date('F j, Y, g:i A', strtotime($transactionData['due_date'])) . "</p>
                </div>
            </div>
        </div>
        
        <div class='section'>
            <h3>Book Details</h3>
            <div class='info-box'>
                <p><span style='font-weight: bold;'>Title:</span> " . htmlspecialchars($_SESSION['borrow_book_info']['title']) . "</p>
                <p><span style='font-weight: bold;'>Author:</span> " . htmlspecialchars($_SESSION['borrow_book_info']['author']) . "</p>
                <p><span style='font-weight: bold;'>Barcode:</span> " . htmlspecialchars($bookBarcode) . "</p>
            </div>
        </div>
        
        <div class='section'>
            <h3>Borrower Details</h3>
            <div class='info-box'>
                <p><span style='font-weight: bold;'>Name:</span> " . htmlspecialchars($memberInfo['fullname']) . "</p>
                <p><span style='font-weight: bold;'>Member ID:</span> " . htmlspecialchars($memberBarcode) . "</p>
            </div>
        </div>
        
        <div class='section'>
            <h3>Important Notes</h3>
            <ul>
                <li>Please return the book on or before the due date.</li>
                <li>Late returns may incur a penalty fee.</li>
                <li>Please handle library materials with care.</li>
                <li>For any inquiries, please contact the library staff.</li>
                <li><strong>Important:</strong> Please bring this receipt when returning the book.</li>
            </ul>
        </div>
        
        <div class='footer'>
            <p>Thank you for using EVSU Book Borrowing System!</p>
            <p>This receipt was generated on " . date('Y-m-d H:i:s') . "</p>
        </div>
    </div>
</body>
</html>";
                
                // Also create a plain text version for email clients that don't support HTML
                $plainTextMessage = "EVSU Book Borrowing System - BORROWING RECEIPT
Transaction #" . $transactionData['id'] . "
 
DATE BORROWED: " . date('F j, Y, g:i A', strtotime($transactionData['borrow_date'])) . "
DUE DATE: " . date('F j, Y, g:i A', strtotime($transactionData['due_date'])) . "
 
BOOK DETAILS:
Title: " . $_SESSION['borrow_book_info']['title'] . "
Author: " . $_SESSION['borrow_book_info']['author'] . "
Barcode: " . $bookBarcode . "
 
BORROWER DETAILS:
Name: " . $memberInfo['fullname'] . "
Member ID: " . $memberBarcode . "
 
IMPORTANT NOTES:
- Please return the book on or before the due date.
- Late returns may incur a penalty fee.
- Please handle library materials with care.
- For any inquiries, please contact the library staff.
- Important: Please bring this receipt when returning the book.
 
Thank you for using EVSU Book Borrowing System!
This receipt was generated on " . date('Y-m-d H:i:s');
 
                // Send email directly using our specialized function
                try {
                    file_put_contents($borrowLogFile, date('Y-m-d H:i:s') . " - Attempting to send email to: " . $memberInfo['email'] . " using direct method\n", FILE_APPEND);
                    // Use our specialized borrowing email function
                    $isSent = sendBorrowingEmail(
                        $memberInfo['email'], 
                        $emailSubject, 
                        $emailMessage, 
                        $plainTextMessage, 
                        $borrowLogFile
                    );
                    file_put_contents($borrowLogFile, date('Y-m-d H:i:s') . " - Direct email result: " . ($isSent ? "SUCCESS" : "FAILED") . "\n", FILE_APPEND);
                } catch (Exception $e) {
                    file_put_contents($borrowLogFile, date('Y-m-d H:i:s') . " - Exception in email sending: " . $e->getMessage() . "\n", FILE_APPEND);
                    $isSent = false;
                }
                
                // Log the email completion status
                $logMessage = date('Y-m-d H:i:s') . " - Borrowing receipt email process complete for transaction ID: " . $transactionData['id'] . " - Status: " . ($isSent ? "SUCCESS" : "FAILED") . "\n";
                file_put_contents($borrowLogFile, $logMessage, FILE_APPEND);
                file_put_contents('logs/borrowing_emails.txt', $logMessage, FILE_APPEND);
            }
            
            // Store transaction data in session for receipt
            $_SESSION['borrow_receipt'] = [
                'transaction_id' => $transactionData['id'],
                'book_title' => $_SESSION['borrow_book_info']['title'],
                'book_barcode' => $bookBarcode,
                'member_name' => $memberInfo['fullname'],
                'member_barcode' => $memberBarcode,
                'member_email' => $memberInfo['email'],
                'borrow_date' => $transactionData['borrow_date'],
                'due_date' => $transactionData['due_date'],
                'payment_amount' => $paymentAmount,
                'email_sent' => $isSent
            ];
            
            // Clear session variables after successful borrow
            unset($_SESSION['borrow_book_info']);
            unset($_SESSION['borrow_book_barcode']);
            
            // Redirect to receipt page
            header('Location: borrow_receipt.php');
            exit;
        } else {
            // Check if the error is due to reaching the borrowing limit
            $member = getMemberByBarcode($memberBarcode);
            $borrowLimit = (isset($member['membership_type']) && $member['membership_type'] == 'Premium') ? 5 : 3;
            
            if (memberHasReachedBorrowLimit($member['id'])) {
                setFlashMessage('You have reached the maximum borrowing limit of ' . $borrowLimit . ' books. Please return some books before borrowing more.', 'error');
            } else {
                setFlashMessage('Failed to process borrowing. Please try again.', 'error');
            }
            header('Location: borrow.php');
            exit;
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Borrow a Book</h2>
    <p class="text-gray-600 dark:text-gray-400">Scan barcodes one at a time to borrow books</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <?php if (!$completeBorrowVisible): ?>
        <!-- Step 1: Scan Book Barcode -->
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Step 1: Scan Book Barcode</h3>
        
        <form method="POST" action="borrow.php" class="space-y-6">
            <div>
                <label for="book_barcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Book Barcode or ISBN</label>
                <input type="text" id="book_barcode" name="book_barcode" placeholder="Scan book barcode or ISBN" 
                       class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                       autofocus>
                <p class="text-sm text-gray-500 mt-1">You can use either the library barcode (LIB####) or the book's ISBN number</p>
            </div>
            
            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                Continue
            </button>
        </form>
        <?php else: ?>
        <!-- Step 2: Scan Member Barcode -->
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Step 2: Scan Member Barcode</h3>
        
        <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
            <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">Book Selected</h4>
            <div class="space-y-1">
                <p class="text-blue-800 dark:text-blue-200"><span class="font-medium">Title:</span> <?php echo htmlspecialchars($bookInfo['title']); ?></p>
                <p class="text-blue-800 dark:text-blue-200"><span class="font-medium">Author:</span> <?php echo htmlspecialchars($bookInfo['author']); ?></p>
                <p class="text-blue-800 dark:text-blue-200"><span class="font-medium">Barcode:</span> <?php echo htmlspecialchars($_SESSION['borrow_book_barcode']); ?></p>
            </div>
            <div class="mt-2">
                <a href="borrow.php?reset=1" class="text-blue-800 dark:text-blue-200 underline text-sm">Cancel and scan different book</a>
            </div>
        </div>
        
        <!-- Segmented Tab Controller to switch between Existing and Newbie -->
        <div class="mb-6 bg-gray-100 dark:bg-gray-700 p-1 rounded-xl flex gap-1 select-none">
            <button type="button" id="tab_existing" onclick="switchMemberType('existing')" 
                    class="flex-1 py-2 px-3 rounded-lg text-sm font-semibold transition-all duration-300 text-center bg-white dark:bg-gray-600 text-primary-600 dark:text-white shadow-sm focus:outline-none">
                <i class="fas fa-user-check mr-2"></i>Existing Member
            </button>
            <button type="button" id="tab_new" onclick="switchMemberType('new')" 
                    class="flex-1 py-2 px-3 rounded-lg text-sm font-semibold transition-all duration-300 text-center text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none">
                <i class="fas fa-user-plus mr-2"></i>New Member (Newbie)
            </button>
        </div>

        <form method="POST" action="borrow.php" class="space-y-6">
            <input type="hidden" id="member_type" name="member_type" value="existing">

            <!-- Existing Member Fields Container -->
            <div id="existing_member_fields" class="space-y-6">
                <div>
                    <label for="member_barcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Member Barcode</label>
                    <input type="text" id="member_barcode" name="member_barcode" placeholder="Scan member barcode" 
                           class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                           autofocus onchange="checkMemberBorrows(this.value)">
                    <div id="member-borrows-info" class="mt-2"></div>
                </div>
            </div>

            <!-- New Member (Newbie) Registration Form Container -->
            <div id="new_member_fields" class="space-y-6 hidden border border-gray-200 dark:border-gray-700 rounded-xl p-5 bg-gray-50/50 dark:bg-gray-800/50">
                <h4 class="text-md font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="fas fa-id-card text-primary-500"></i> New Member Registration
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="student_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Student ID (Optional)</label>
                        <input type="text" id="student_id" name="student_id" placeholder="Leave empty to auto-generate"
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="fullname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                        <input type="text" id="fullname" name="fullname" placeholder="Enter student's full name"
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
                        <input type="email" id="email" name="email" placeholder="Enter student's email address"
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                        <input type="text" id="phone" name="phone" placeholder="Enter phone number (optional)"
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="course" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Course *</label>
                        <select id="course" name="course"
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select a course</option>
                            <?php foreach ($courseOptions as $code => $label): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                        <textarea id="address" name="address" rows="2" placeholder="Enter current address (optional)"
                                  class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"></textarea>
                    </div>
                </div>
            </div>
            
            <div class="p-4 bg-yellow-50 dark:bg-yellow-900/40 rounded-lg border border-yellow-200 dark:border-yellow-800">
                <h4 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-1">Return Period</h4>
                <p class="text-yellow-800 dark:text-yellow-200">1-Day Return (due tomorrow)</p>
            </div>
            
            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                Complete Borrowing
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Borrowing Information</h3>
        
        <div class="space-y-4">
            <div class="p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 dark:text-blue-300 text-xl mr-3"></i>
                    <p class="text-blue-800 dark:text-blue-200">First scan book barcode (or ISBN), then scan member barcode to borrow</p>
                </div>
            </div>
            
            <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Demo Barcodes</h4>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Use these for testing:</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Book: LIB1001</p>
                        <svg class="barcode-canvas mx-auto" jsbarcode-format="CODE128" jsbarcode-value="LIB1001" jsbarcode-textmargin="0" jsbarcode-fontoptions="bold"></svg>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Member: MEM2001</p>
                        <svg class="barcode-canvas mx-auto" jsbarcode-format="CODE128" jsbarcode-value="MEM2001" jsbarcode-textmargin="0" jsbarcode-fontoptions="bold"></svg>
                    </div>
                </div>
            </div>
            
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Recent Borrowings</h4>
                <?php
                // Get recent borrowings
                $stmt = $pdo->query("
                    SELECT t.id, t.borrow_date, t.due_date, t.payment_amount,
                           b.title as book_title, b.barcode as book_barcode,
                           m.fullname as member_name, m.barcode as member_barcode
                    FROM transactions t
                    JOIN books b ON t.book_id = b.id
                    JOIN members m ON t.member_id = m.id
                    WHERE t.status = 'Borrowed'
                    ORDER BY t.borrow_date DESC
                    LIMIT 3
                ");
                $recentBorrowings = $stmt->fetchAll();
                
                if (count($recentBorrowings) > 0):
                ?>
                    <div class="space-y-3">
                        <?php foreach ($recentBorrowings as $borrowing): ?>
                            <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-600 last:border-0">
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($borrowing['book_title']); ?></p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Borrowed by: <?php echo htmlspecialchars($borrowing['member_name']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Due: <?php echo date('M j, Y, g:i A', strtotime($borrowing['due_date'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600 dark:text-gray-400 text-center py-2">No recent borrowings</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function checkMemberBorrows(barcode) {
    // Don't proceed if barcode is empty
    if (!barcode) {
        document.getElementById('member-borrows-info').innerHTML = '';
        return;
    }
    
    // Show loading message
    document.getElementById('member-borrows-info').innerHTML = '<p class="text-sm italic text-gray-500">Loading member information...</p>';
    
    // Perform AJAX request
    fetch('check_member_borrows.php?barcode=' + encodeURIComponent(barcode))
        .then(response => response.json())
        .then(data => {
            const infoDiv = document.getElementById('member-borrows-info');
            
            if (data.success) {
                // Generate HTML for the member info
                let html = `
                    <div class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium text-gray-800 dark:text-gray-200">${data.member_name}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">${data.membership_type} member</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    ${data.active_borrows} currently borrowed
                                </p>
                            </div>
                        </div>`;
                
                // Only show borrowed books list if there are any
                if (data.borrowed_books && data.borrowed_books.length > 0) {
                    html += `<div class="mt-2">
                        <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Currently borrowed books:</p>
                        <ul class="text-xs space-y-1">`;
                    
                    data.borrowed_books.forEach(book => {
                        const dueDate = new Date(book.due_date);
                        const today = new Date();
                        const isOverdue = dueDate < today;
                        
                        html += `<li class="flex justify-between ${isOverdue ? 'text-red-600 dark:text-red-400' : ''}">
                            <span class="truncate mr-2">${book.book_title}</span>
                            <span>Due: ${new Date(book.due_date).toLocaleDateString()}</span>
                        </li>`;
                    });
                    
                    html += `</ul></div>`;
                }
                
                html += `</div>`;
                infoDiv.innerHTML = html;
            } else {
                // Show error message
                infoDiv.innerHTML = `<p class="text-sm text-red-600 dark:text-red-400">${data.message}</p>`;
            }
        })
        .catch(error => {
            console.error('Error checking borrows:', error);
            document.getElementById('member-borrows-info').innerHTML = '';
        });
}

function switchMemberType(type) {
    document.getElementById('member_type').value = type;
    
    const tabExisting = document.getElementById('tab_existing');
    const tabNew = document.getElementById('tab_new');
    const existingFields = document.getElementById('existing_member_fields');
    const newFields = document.getElementById('new_member_fields');
    
    const memberBarcode = document.getElementById('member_barcode');
    const fullname = document.getElementById('fullname');
    const email = document.getElementById('email');
    const course = document.getElementById('course');
    
    if (type === 'existing') {
        // Tab UI styling
        tabExisting.className = "flex-1 py-2 px-3 rounded-lg text-sm font-semibold transition-all duration-300 text-center bg-white dark:bg-gray-600 text-primary-600 dark:text-white shadow-sm focus:outline-none";
        tabNew.className = "flex-1 py-2 px-3 rounded-lg text-sm font-semibold transition-all duration-300 text-center text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none";
        
        // Show/Hide fields
        existingFields.classList.remove('hidden');
        newFields.classList.add('hidden');
        
        // Input requirements
        memberBarcode.required = true;
        fullname.required = false;
        email.required = false;
        course.required = false;
        
        // Focus first input
        memberBarcode.focus();
    } else {
        // Tab UI styling
        tabNew.className = "flex-1 py-2 px-3 rounded-lg text-sm font-semibold transition-all duration-300 text-center bg-white dark:bg-gray-600 text-primary-600 dark:text-white shadow-sm focus:outline-none";
        tabExisting.className = "flex-1 py-2 px-3 rounded-lg text-sm font-semibold transition-all duration-300 text-center text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white focus:outline-none";
        
        // Show/Hide fields
        existingFields.classList.add('hidden');
        newFields.classList.remove('hidden');
        
        // Input requirements
        memberBarcode.required = false;
        fullname.required = true;
        email.required = true;
        course.required = true;
        
        // Focus first input
        fullname.focus();
    }
}

// On page load, initialize requirements based on default selected tab (existing)
document.addEventListener('DOMContentLoaded', function() {
    const memberBarcode = document.getElementById('member_barcode');
    if (memberBarcode) {
        memberBarcode.required = true;
    }
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 