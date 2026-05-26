<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

// Check if transaction ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setFlashMessage('Transaction ID is required.', 'error');
    header('Location: penalties_record.php');
    exit;
}

$transactionId = intval($_GET['id']);

// Check if transaction exists and has a penalty
$stmt = $pdo->prepare("
    SELECT t.*, b.title as book_title, m.fullname as member_name
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN members m ON t.member_id = m.id
    WHERE t.id = :id AND t.penalty_amount > 0
");
$stmt->bindParam(':id', $transactionId, PDO::PARAM_INT);
$stmt->execute();
$transaction = $stmt->fetch();

if (!$transaction) {
    setFlashMessage('Transaction not found or does not have a penalty.', 'error');
    header('Location: penalties_record.php');
    exit;
}

// If the penalty is already paid, redirect back
if ($transaction['payment_status'] === 'Paid' || $transaction['payment_status'] === 'Penalty Paid') {
    setFlashMessage('This penalty has already been paid.', 'info');
    header('Location: penalties_record.php');
    exit;
}

// Mark penalty as paid
$stmt = $pdo->prepare("
    UPDATE transactions
    SET payment_status = 'Penalty Paid'
    WHERE id = :id
");
$stmt->bindParam(':id', $transactionId, PDO::PARAM_INT);
$result = $stmt->execute();

// Check if update was successful
if ($result) {
    // Log the payment
    $logMessage = "Penalty for transaction #{$transactionId} marked as paid. ";
    $logMessage .= "Book: {$transaction['book_title']}, Member: {$transaction['member_name']}, ";
    $logMessage .= "Amount: ₱" . number_format($transaction['penalty_amount'], 2);
    
    // Ensure logs directory exists
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    
    // Log to file
    file_put_contents(
        'logs/penalty_payments.log', 
        date('Y-m-d H:i:s') . " - " . $logMessage . "\n", 
        FILE_APPEND
    );
    
    // Add activity log entry
    logActivity('Marked penalty as paid', $logMessage, 'penalty');
    
    setFlashMessage('Penalty has been marked as paid successfully.', 'success');
} else {
    setFlashMessage('Failed to mark penalty as paid. Please try again.', 'error');
}

// Redirect back to penalties record page
header('Location: penalties_record.php');
exit;
?> 