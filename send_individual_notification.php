<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';
require_once 'config/mail_config.php';

// Require login to access this page
requireLogin();

// Check if we have the required parameters
$memberId = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$transactionId = isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 0;

if ($memberId <= 0 || $transactionId <= 0) {
    setFlashMessage('Invalid parameters for sending notification', 'error');
    header('Location: overdue.php');
    exit;
}

// Get transaction and book details
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id, t.borrow_date, t.due_date, t.payment_amount,
            b.title as book_title, b.author as book_author, 
            m.id as member_id, m.fullname as member_name, m.email as member_email,
            DATEDIFF(CURRENT_DATE, t.due_date) as days_overdue
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        JOIN members m ON t.member_id = m.id
        WHERE t.id = :transaction_id AND m.id = :member_id AND t.status = 'Overdue'
    ");
    $stmt->bindParam(':transaction_id', $transactionId);
    $stmt->bindParam(':member_id', $memberId);
    $stmt->execute();
    $data = $stmt->fetch();
    
    if (!$data) {
        setFlashMessage('Transaction or member not found', 'error');
        header('Location: overdue.php');
        exit;
    }
    
    // Calculate penalty
    $penaltyAmount = calculateLateReturnPenalty($data);
    
    // Create notification message
    $message = "Dear " . $data['member_name'] . ",\n\n";
    $message .= "This is a reminder that the book '" . $data['book_title'] . "' is overdue by " . $data['days_overdue'] . " days.\n\n";
    $message .= "Book details:\n";
    $message .= "- Title: " . $data['book_title'] . "\n";
    $message .= "- Author: " . $data['book_author'] . "\n";
    $message .= "- Due date: " . date('F j, Y, g:i A', strtotime($data['due_date'])) . "\n";
    $message .= "- Days overdue: " . $data['days_overdue'] . "\n";
    $message .= "- Late fee: " . number_format($penaltyAmount, 2) . " pesos\n\n";
    $message .= "Please return the book as soon as possible to avoid additional fees.\n\n";
    $message .= "Thank you,\nEVSU Book Borrowing System";
    
    // HTML version of the notification
    $htmlMessage = "<!DOCTYPE html>
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
        .penalty { color: #d9534f; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>EVSU Book Borrowing System</h1>
            <p>Overdue Book Notice</p>
        </div>
        
        <p>Dear " . htmlspecialchars($data['member_name']) . ",</p>
        
        <p>This is a reminder that the book <strong>'" . htmlspecialchars($data['book_title']) . "'</strong> is overdue by <strong>" . $data['days_overdue'] . " days</strong>.</p>
        
        <div class='section'>
            <h3>Book Details</h3>
            <div class='info-box'>
                <p><strong>Title:</strong> " . htmlspecialchars($data['book_title']) . "</p>
                <p><strong>Author:</strong> " . htmlspecialchars($data['book_author']) . "</p>
                <p><strong>Due Date:</strong> " . date('F j, Y, g:i A', strtotime($data['due_date'])) . "</p>
                <p><strong>Days Overdue:</strong> " . $data['days_overdue'] . "</p>
                <p class='penalty'><strong>Late Fee:</strong> ₱" . number_format($penaltyAmount, 2) . "</p>
            </div>
        </div>
        
        <p>Please return the book as soon as possible to avoid additional fees.</p>
        
        <p>Thank you,<br>EVSU Book Borrowing System</p>
    </div>
</body>
</html>";
    
    // Send email if email is available
    $emailSent = false;
    if (!empty($data['member_email'])) {
        // Send HTML email
        $subject = "OVERDUE NOTICE: " . $data['book_title'];
        $headers = "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: EVSU Book Borrowing System <" . $mail_config['smtp_username'] . ">\r\n";
        $emailSent = sendEmail($data['member_email'], $subject, $htmlMessage, $headers, $message);
    }
    
    // Record notification in the database
    $stmt = $pdo->prepare("
        INSERT INTO notifications (member_id, transaction_id, message, type, is_sent)
        VALUES (:member_id, :transaction_id, :message, :type, :is_sent)
    ");
    $stmt->bindParam(':member_id', $memberId);
    $stmt->bindParam(':transaction_id', $transactionId);
    $stmt->bindParam(':message', $message);
    $type = 'Overdue';
    $stmt->bindParam(':type', $type);
    $stmt->bindParam(':is_sent', $emailSent);
    $stmt->execute();
    
    if ($emailSent) {
        setFlashMessage("Overdue notification sent to " . $data['member_name'], 'success');
    } else {
        setFlashMessage("Notification recorded but email could not be sent to " . $data['member_name'], 'warning');
    }
    
} catch (PDOException $e) {
    setFlashMessage('Error sending notification: ' . $e->getMessage(), 'error');
}

header('Location: overdue.php');
exit; 