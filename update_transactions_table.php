<?php
require_once 'config/db_connect.php';

// Check if the script has already been run
$checkScript = $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'transactions' AND COLUMN_NAME = 'penalty_type'");
if ($checkScript->rowCount() > 0) {
    echo "Script already executed. The transactions table already has the penalty_type column.";
    exit;
}

// Update the transactions table to add penalty_type column
try {
    // Add penalty_type column to transactions table
    $pdo->exec("ALTER TABLE transactions ADD COLUMN penalty_type ENUM('late', 'damaged', 'lost') DEFAULT NULL AFTER penalty_amount");
    
    // Update the status enum to include Damaged and Lost
    $pdo->exec("ALTER TABLE transactions MODIFY COLUMN status ENUM('Borrowed', 'Returned', 'Overdue', 'Damaged', 'Lost') DEFAULT 'Borrowed'");
    
    // Update the payment_status enum to include Penalty Fee Pending and Penalty Fee Paid
    $pdo->exec("ALTER TABLE transactions MODIFY COLUMN payment_status ENUM('Pending', 'Paid', 'Overdue Fee Pending', 'Overdue Fee Paid', 'Penalty Fee Pending', 'Penalty Fee Paid') DEFAULT 'Pending'");
    
    // Update the notification types to include Book Damaged Penalty and Book Lost Penalty
    $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type ENUM('Due Soon', 'Overdue', 'Return Confirmation', 'Payment', 'System', 'Book Damaged Penalty', 'Book Lost Penalty') NOT NULL");
    
    // Update the books status enum to include Damaged and Lost
    $pdo->exec("ALTER TABLE books MODIFY COLUMN status ENUM('Available', 'Borrowed', 'Overdue', 'Damaged', 'Lost') DEFAULT 'Available'");
    
    echo "Database updated successfully. Added penalty_type column to transactions table and updated enums.";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?> 