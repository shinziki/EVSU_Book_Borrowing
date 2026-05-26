<?php
require_once '../config/db_connect.php';
require_once '../config/functions.php';

// Admin-only page
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

echo "<h1>Database Maintenance Tool</h1>";

// Check for any fix operations
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        // Fix book quantities
        case 'fix_quantities':
            $result = $pdo->query("DESCRIBE books");
            $columns = $result->fetchAll(PDO::FETCH_COLUMN);
            
            // Add quantity column if needed
            if (!in_array('quantity', $columns)) {
                echo "<p>Adding quantity column to books table...</p>";
                $pdo->exec("ALTER TABLE books ADD COLUMN quantity INT DEFAULT 1");
            }
            
            // Update quantities based on actual loans
            echo "<p>Updating book quantities based on transactions...</p>";
            $pdo->exec("
                UPDATE books b
                SET quantity = (
                    SELECT 1 - COUNT(*)
                    FROM transactions t
                    WHERE t.book_id = b.id AND t.status IN ('Borrowed', 'Overdue')
                )
                WHERE EXISTS (
                    SELECT 1 FROM transactions t 
                    WHERE t.book_id = b.id AND t.status IN ('Borrowed', 'Overdue')
                )
            ");
            
            echo "<p>Setting minimum quantity to 0 for any negative values...</p>";
            $pdo->exec("UPDATE books SET quantity = 0 WHERE quantity < 0");
            
            echo "<p>Updating book statuses based on quantity...</p>";
            $pdo->exec("UPDATE books SET status = 'Available' WHERE quantity > 0");
            $pdo->exec("UPDATE books SET status = 'Borrowed' WHERE quantity = 0 AND EXISTS (SELECT 1 FROM transactions t WHERE t.book_id = books.id AND t.status IN ('Borrowed', 'Overdue'))");
            
            echo "<p>Quantities fixed successfully!</p>";
            break;
            
        // Reset book statuses
        case 'reset_statuses':
            echo "<p>Resetting all book statuses...</p>";
            
            // First identify borrowed books
            $pdo->exec("UPDATE books SET status = 'Available'");
            
            // Then update those that have active loans
            $pdo->exec("
                UPDATE books b
                SET status = 'Borrowed'
                WHERE EXISTS (
                    SELECT 1 
                    FROM transactions t 
                    WHERE t.book_id = b.id AND t.status IN ('Borrowed', 'Overdue')
                )
            ");
            
            echo "<p>Book statuses reset successfully!</p>";
            break;
        
        // Reset loan statuses
        case 'reset_loans':
            echo "<p>Checking for inconsistent loan statuses...</p>";
            
            // Find loans that should be marked as returned
            $pdo->exec("
                UPDATE transactions 
                SET status = 'Returned', 
                    return_date = NOW() 
                WHERE return_date IS NOT NULL AND status IN ('Borrowed', 'Overdue')
            ");
            
            // Update overdue status for late loans
            $pdo->exec("
                UPDATE transactions 
                SET status = 'Overdue' 
                WHERE due_date < CURDATE() AND status = 'Borrowed'
            ");
            
            echo "<p>Loan statuses reset successfully!</p>";
            break;
    }
}

// Display database stats
echo "<h2>Database Status</h2>";

// Get book stats
$result = $pdo->query("SELECT COUNT(*) as total, SUM(IF(status = 'Available', 1, 0)) as available FROM books");
$bookStats = $result->fetch();

// Get transaction stats
$result = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(IF(status = 'Borrowed', 1, 0)) as borrowed,
        SUM(IF(status = 'Overdue', 1, 0)) as overdue,
        SUM(IF(status = 'Returned', 1, 0)) as returned
    FROM transactions
");
$transactionStats = $result->fetch();

// Books with inconsistent statuses
$result = $pdo->query("
    SELECT COUNT(*) as inconsistent
    FROM books b
    WHERE 
        (b.status = 'Available' AND EXISTS (
            SELECT 1 FROM transactions t 
            WHERE t.book_id = b.id AND t.status IN ('Borrowed', 'Overdue')
        ))
        OR
        (b.status = 'Borrowed' AND NOT EXISTS (
            SELECT 1 FROM transactions t 
            WHERE t.book_id = b.id AND t.status IN ('Borrowed', 'Overdue')
        ))
");
$inconsistentBooks = $result->fetch()['inconsistent'];

echo "<div style='display: flex; margin-bottom: 20px;'>";
echo "<div style='flex: 1; margin-right: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd;'>";
echo "<h3>Books</h3>";
echo "<p>Total: <strong>{$bookStats['total']}</strong></p>";
echo "<p>Available: <strong>{$bookStats['available']}</strong></p>";
echo "<p>Not Available: <strong>" . ($bookStats['total'] - $bookStats['available']) . "</strong></p>";
echo "<p>Inconsistent statuses: <strong style='color: " . ($inconsistentBooks > 0 ? 'red' : 'green') . "'>{$inconsistentBooks}</strong></p>";
echo "</div>";

echo "<div style='flex: 1; padding: 15px; background: #f8f9fa; border: 1px solid #ddd;'>";
echo "<h3>Transactions</h3>";
echo "<p>Total: <strong>{$transactionStats['total']}</strong></p>";
echo "<p>Currently borrowed: <strong>{$transactionStats['borrowed']}</strong></p>";
echo "<p>Overdue: <strong>{$transactionStats['overdue']}</strong></p>";
echo "<p>Returned: <strong>{$transactionStats['returned']}</strong></p>";
echo "</div>";
echo "</div>";

// Display fix options
echo "<h2>Fix Options</h2>";

echo "<div style='display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 20px;'>";

echo "<div style='padding: 15px; background: #e9f7ef; border: 1px solid #d5f5e3; border-radius: 4px;'>";
echo "<h3>Fix Book Quantities</h3>";
echo "<p>Ensures all books have the quantity field and sets correct values based on loans.</p>";
echo "<p><a href='?action=fix_quantities' style='background: #27ae60; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px;'>Run Fix</a></p>";
echo "</div>";

echo "<div style='padding: 15px; background: #ebf5fb; border: 1px solid #d4e6f1; border-radius: 4px;'>";
echo "<h3>Reset Book Statuses</h3>";
echo "<p>Updates all book statuses based on current loans.</p>";
echo "<p><a href='?action=reset_statuses' style='background: #2980b9; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px;'>Run Fix</a></p>";
echo "</div>";

echo "<div style='padding: 15px; background: #fef9e7; border: 1px solid #fcf3cf; border-radius: 4px;'>";
echo "<h3>Reset Loan Statuses</h3>";
echo "<p>Fixes inconsistent loan statuses and updates overdue flags.</p>";
echo "<p><a href='?action=reset_loans' style='background: #f39c12; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 10px;'>Run Fix</a></p>";
echo "</div>";

echo "</div>";

// Return link
echo "<p style='margin-top: 30px;'><a href='../index.php' style='color: #3498db;'>Return to Dashboard</a></p>";
?> 