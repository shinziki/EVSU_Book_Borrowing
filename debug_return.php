<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db_connect.php';
require_once 'config/functions.php';

echo "<h1>Return Process Debugger</h1>";

// Check if we have a barcode to process
if (isset($_GET['barcode']) && !empty($_GET['barcode'])) {
    $barcode = $_GET['barcode'];
    echo "<h2>Processing Return for Barcode: " . htmlspecialchars($barcode) . "</h2>";
    
    // Get book info
    $bookInfo = getBookByBarcode($barcode);
    if ($bookInfo) {
        echo "<h3>Book Information:</h3>";
        echo "<pre>";
        print_r($bookInfo);
        echo "</pre>";
        
        // Get active transactions for this book
        $stmt = $pdo->prepare("
            SELECT t.*, m.fullname, m.email
            FROM transactions t
            JOIN members m ON t.member_id = m.id
            WHERE t.book_id = :book_id AND t.status IN ('Borrowed', 'Overdue')
            ORDER BY t.id DESC
        ");
        $stmt->bindParam(':book_id', $bookInfo['id'], PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($transactions) > 0) {
            echo "<h3>Active Transactions Found:</h3>";
            echo "<pre>";
            print_r($transactions);
            echo "</pre>";
            
            // Process the return
            echo "<h3>Attempting Return Process:</h3>";
            
            // Save the original error reporting level
            $original = error_reporting(E_ALL);
            
            // Start output buffering to capture errors
            ob_start();
            
            // Try to process the return
            $result = returnBook($barcode);
            
            // Get any errors that occurred
            $errors = ob_get_clean();
            
            // Restore error reporting level
            error_reporting($original);
            
            if ($result) {
                echo "<p style='color: green; font-weight: bold;'>SUCCESS: Book returned successfully!</p>";
                echo "<h4>Return Process Result:</h4>";
                echo "<pre>";
                print_r($result);
                echo "</pre>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>FAILED: Could not process return.</p>";
                
                if (!empty($errors)) {
                    echo "<h4>Errors Encountered:</h4>";
                    echo "<pre style='color: red;'>";
                    echo $errors;
                    echo "</pre>";
                }
                
                // Check database structure to see if columns match what we expect
                echo "<h4>Database Structure Check:</h4>";
                
                // Check transactions table structure
                $stmt = $pdo->query("DESCRIBE transactions");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<p>Transactions Table Columns:</p>";
                echo "<ul>";
                foreach ($columns as $column) {
                    echo "<li><strong>{$column['Field']}</strong> ({$column['Type']})</li>";
                }
                echo "</ul>";
                
                // Check books table structure
                $stmt = $pdo->query("DESCRIBE books");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<p>Books Table Columns:</p>";
                echo "<ul>";
                foreach ($columns as $column) {
                    echo "<li><strong>{$column['Field']}</strong> ({$column['Type']})</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p style='color: orange; font-weight: bold;'>No active transactions found for this book. It may already be returned.</p>";
            
            // Check book status
            echo "<p>Current book status: <strong>" . htmlspecialchars($bookInfo['status']) . "</strong></p>";
            echo "<p>Current quantity: <strong>" . htmlspecialchars($bookInfo['quantity']) . "</strong></p>";
        }
    } else {
        echo "<p style='color: red; font-weight: bold;'>ERROR: Book not found with barcode " . htmlspecialchars($barcode) . "</p>";
    }
    
    echo "<hr>";
}

// Form to test with other barcodes
?>

<form method="GET" action="debug_return.php">
    <div style="margin: 20px 0;">
        <label for="barcode"><strong>Enter Book Barcode to Test:</strong></label>
        <input type="text" id="barcode" name="barcode" value="<?php echo isset($_GET['barcode']) ? htmlspecialchars($_GET['barcode']) : ''; ?>" style="padding: 5px; margin: 0 10px;">
        <button type="submit" style="padding: 5px 10px; background: #4CAF50; color: white; border: none; border-radius: 4px;">Process Return</button>
    </div>
</form>

<p><a href="return.php">Back to Regular Return Page</a></p> 