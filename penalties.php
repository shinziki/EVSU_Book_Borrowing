<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

// Initialize variables
$bookInfo = null;
$transactionInfo = null;
$memberInfo = null;
$message = '';
$messageType = '';
$penaltyType = '';
$penaltyAmount = 0;

// Get penalty settings
$penaltySettings = getPenaltySettings();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['book_barcode'] ?? '';
    $penaltyType = $_POST['penalty_type'] ?? '';
    
    // Validate inputs
    if (empty($barcode)) {
        $message = 'Barcode is required';
        $messageType = 'error';
    } elseif (empty($penaltyType) || !in_array($penaltyType, ['damaged', 'lost'])) {
        $message = 'Please select a valid penalty type';
        $messageType = 'error';
    } else {
        // Check if it's a transaction barcode (starting with TRX)
        if (strpos($barcode, 'TRX') === 0) {
            $transactionId = substr($barcode, 3); // Remove TRX prefix
            
            // Get transaction info by ID
            $stmt = $pdo->prepare("
                SELECT t.*, b.barcode as book_barcode, b.title as book_title, b.author as book_author,
                       m.fullname as member_name, m.email as member_email, m.notifications_enabled
                FROM transactions t 
                JOIN books b ON t.book_id = b.id
                JOIN members m ON t.member_id = m.id
                WHERE t.id = :transaction_id AND t.status IN ('Borrowed', 'Overdue')
            ");
            $stmt->bindParam(':transaction_id', $transactionId, PDO::PARAM_INT);
            $stmt->execute();
            $transactionInfo = $stmt->fetch();
            
            if (!$transactionInfo) {
                $message = 'Transaction not found or already returned';
                $messageType = 'error';
            } else {
                // Process penalty
                $result = processBookPenalty($transactionInfo['book_barcode'], $penaltyType);
                
                if ($result) {
                    setFlashMessage('Penalty recorded successfully', 'success');
                    header('Location: penalties.php');
                    exit;
                } else {
                    $message = 'Failed to process penalty. Please try again.';
                    $messageType = 'error';
                }
            }
        } else {
            // Regular book barcode processing
            $bookInfo = getBookByBarcode($barcode);
            
            if (!$bookInfo) {
                $message = 'Book not found with barcode: ' . htmlspecialchars($barcode);
                $messageType = 'error';
            } elseif ($bookInfo['status'] === 'Available') {
                $message = 'Book is not currently borrowed';
                $messageType = 'error';
            } else {
                // Process penalty
                $result = processBookPenalty($barcode, $penaltyType);
                
                if ($result) {
                    setFlashMessage('Penalty recorded successfully', 'success');
                    header('Location: penalties.php');
                    exit;
                } else {
                    $message = 'Failed to process penalty. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    }
    
    // Set flash message if needed
    if ($message) {
        setFlashMessage($message, $messageType);
    }
}

// Get book details if barcode is provided via GET
if (isset($_GET['barcode']) && !empty($_GET['barcode'])) {
    $barcode = $_GET['barcode'];
    
    // Check if it's a transaction barcode
    if (strpos($barcode, 'TRX') === 0) {
        $transactionId = substr($barcode, 3);
        
        $stmt = $pdo->prepare("
            SELECT t.*, b.id as book_id, b.title as book_title, b.author as book_author, b.barcode as book_barcode, b.status as book_status,
                   m.fullname as member_name, m.barcode as member_barcode, m.email as member_email
            FROM transactions t 
            JOIN books b ON t.book_id = b.id
            JOIN members m ON t.member_id = m.id
            WHERE t.id = :transaction_id AND t.status IN ('Borrowed', 'Overdue')
        ");
        $stmt->bindParam(':transaction_id', $transactionId, PDO::PARAM_INT);
        $stmt->execute();
        $transactionInfo = $stmt->fetch();
        
        if ($transactionInfo) {
            $bookInfo = [
                'id' => $transactionInfo['book_id'],
                'title' => $transactionInfo['book_title'],
                'author' => $transactionInfo['book_author'],
                'barcode' => $transactionInfo['book_barcode'],
                'status' => $transactionInfo['book_status']
            ];
        }
    } else {
        // Regular book barcode processing
        $bookInfo = getBookByBarcode($barcode);
        
        if ($bookInfo && $bookInfo['status'] !== 'Available') {
            // Get transaction details
            $stmt = $pdo->prepare("
                SELECT t.*, m.fullname as member_name, m.barcode as member_barcode, m.email as member_email
                FROM transactions t
                JOIN members m ON t.member_id = m.id
                WHERE t.book_id = :book_id AND t.status IN ('Borrowed', 'Overdue')
                ORDER BY t.id DESC
                LIMIT 1
            ");
            $stmt->bindParam(':book_id', $bookInfo['id'], PDO::PARAM_INT);
            $stmt->execute();
            $transactionInfo = $stmt->fetch();
        }
    }
}

/**
 * Process book penalty (damaged or lost)
 */
function processBookPenalty($bookBarcode, $penaltyType) {
    global $pdo, $penaltySettings;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get book details
        $book = getBookByBarcode($bookBarcode);
        
        if (!$book) {
            return false;
        }
        
        // Get the active transaction for this book
        $stmt = $pdo->prepare("
            SELECT t.*, m.fullname, m.email, m.notifications_enabled
            FROM transactions t
            JOIN members m ON t.member_id = m.id
            WHERE t.book_id = :book_id AND t.status IN ('Borrowed', 'Overdue')
            ORDER BY t.id DESC
            LIMIT 1
        ");
        $stmt->bindParam(':book_id', $book['id'], PDO::PARAM_INT);
        $stmt->execute();
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            return false;
        }
        
        // Set penalty amount based on type
        $penaltyAmount = ($penaltyType === 'damaged') ? 
            $penaltySettings['damaged_book_fee'] : 
            $penaltySettings['lost_book_fee'];
        
        // Update transaction with penalty
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'Returned', 
                return_date = NOW(),
                penalty_amount = :penalty_amount,
                penalty_type = :penalty_type,
                payment_status = 'Penalty Fee Pending'
            WHERE id = :id
        ");
        $stmt->bindParam(':penalty_amount', $penaltyAmount);
        $stmt->bindParam(':penalty_type', $penaltyType);
        $stmt->bindParam(':id', $transaction['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Update book status
        if ($penaltyType === 'damaged') {
            $newStatus = 'Damaged';
        } else { // lost
            $newStatus = 'Lost';
        }
        
        $stmt = $pdo->prepare("
            UPDATE books 
            SET status = :status
            WHERE id = :id
        ");
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $book['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Send email notification if enabled
        if ($transaction['notifications_enabled'] && !empty($transaction['email'])) {
            // Create notification message
            $notificationType = ($penaltyType === 'damaged') ? 'Book Damaged Penalty' : 'Book Lost Penalty';
            $notificationMessage = "Dear " . htmlspecialchars($transaction['fullname']) . ",\n\n";
            $notificationMessage .= "We regret to inform you that a penalty has been applied for the book '" . htmlspecialchars($book['title']) . "'.\n\n";
            
            if ($penaltyType === 'damaged') {
                $notificationMessage .= "The book was returned in damaged condition.\n";
                $notificationMessage .= "Penalty fee: " . number_format($penaltyAmount, 2) . " pesos\n\n";
            } else {
                $notificationMessage .= "The book was reported as lost.\n";
                $notificationMessage .= "Penalty fee: " . number_format($penaltyAmount, 2) . " pesos\n\n";
            }
            
            $notificationMessage .= "Please pay this amount to the librarian at your earliest convenience.\n\n";
            $notificationMessage .= "Thank you for your understanding.\n";
            $notificationMessage .= "Coffee Prince Library";
            
            // Record notification in database
            $stmt = $pdo->prepare("
                INSERT INTO notifications (member_id, transaction_id, message, type, is_sent)
                VALUES (:member_id, :transaction_id, :message, :type, 1)
            ");
            $stmt->bindParam(':member_id', $transaction['member_id']);
            $stmt->bindParam(':transaction_id', $transaction['id']);
            $stmt->bindParam(':message', $notificationMessage);
            $stmt->bindParam(':type', $notificationType);
            $stmt->execute();
            
            // Send email
            $emailSubject = "Coffee Prince Library - " . $notificationType;
            
            // Set HTML email content
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
        .penalty { color: #d9534f; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; font-size: 0.9em; color: #777; border-top: 1px solid #eee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Coffee Prince Library</h1>
            <p>" . ($penaltyType === 'damaged' ? 'Book Damaged Notification' : 'Book Lost Notification') . "</p>
        </div>
        
        <div class='section'>
            <p>Dear " . htmlspecialchars($transaction['fullname']) . ",</p>
            <p>We regret to inform you that a penalty has been applied for the following book:</p>
            
            <div class='info-box'>
                <p><strong>Title:</strong> " . htmlspecialchars($book['title']) . "</p>
                <p><strong>Author:</strong> " . htmlspecialchars($book['author']) . "</p>
                <p><strong>Barcode:</strong> " . htmlspecialchars($book['barcode']) . "</p>
                <p><strong>Borrow Date:</strong> " . date('F j, Y', strtotime($transaction['borrow_date'])) . "</p>
            </div>
        </div>
        
        <div class='section'>
            <h3>Penalty Details</h3>
            <div class='info-box'>
                <p><strong>Reason:</strong> " . ($penaltyType === 'damaged' ? 'Book returned in damaged condition' : 'Book reported as lost') . "</p>
                <p><strong>Penalty Amount:</strong> <span class='penalty'>₱" . number_format($penaltyAmount, 2) . "</span></p>
            </div>
            
            <p>Please pay this amount to the librarian at your earliest convenience.</p>
            <p>If you have any questions or concerns, please contact the library staff.</p>
        </div>
        
        <div class='footer'>
            <p>Thank you for your understanding.</p>
            <p>Coffee Prince Library</p>
        </div>
    </div>
</body>
</html>";
            
            // Try to send email
            require_once 'config/mailer.php';
            if (function_exists('sendEmail')) {
                sendEmail($transaction['email'], $emailSubject, $htmlMessage);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error processing penalty: " . $e->getMessage());
        return false;
    }
}

// Include header
include 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Book Penalties</h2>
    <p class="text-gray-600 dark:text-gray-400">Record penalties for damaged or lost books</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Record Book Penalty</h3>
        
        <div class="p-4 mb-4 bg-amber-50 dark:bg-amber-900 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-amber-600 dark:text-amber-300 text-xl mr-3"></i>
                <p class="text-amber-800 dark:text-amber-200">
                    Record penalties for damaged or lost books. Scan the book barcode or transaction barcode.
                    <br>Damaged book fee: ₱<?php echo number_format($penaltySettings['damaged_book_fee'], 2); ?>
                    <br>Lost book fee: ₱<?php echo number_format($penaltySettings['lost_book_fee'], 2); ?>
                </p>
            </div>
        </div>
        
        <form method="POST" action="penalties.php" class="space-y-6">
            <div>
                <label for="book_barcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Scan Barcode</label>
                <input type="text" id="book_barcode" name="book_barcode" placeholder="Scan book or transaction barcode" 
                       value="<?php echo isset($_GET['barcode']) ? htmlspecialchars($_GET['barcode']) : ''; ?>"
                       class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                       autofocus>
            </div>
            
            <div>
                <label for="penalty_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Penalty Type</label>
                <select id="penalty_type" name="penalty_type" class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="">-- Select Penalty Type --</option>
                    <option value="damaged">Book Damaged (₱<?php echo number_format($penaltySettings['damaged_book_fee'], 2); ?>)</option>
                    <option value="lost">Book Lost (₱<?php echo number_format($penaltySettings['lost_book_fee'], 2); ?>)</option>
                </select>
            </div>
            
            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                Record Penalty
            </button>
        </form>
    </div>
    
    <?php if ($bookInfo && $transactionInfo): ?>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Book & Member Details</h3>
        
        <div class="mb-6">
            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Book Information</h4>
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <div class="flex items-center mb-3">
                    <div class="bg-primary-100 dark:bg-primary-900 p-2 rounded-full mr-3">
                        <i class="fas fa-book text-primary-600 dark:text-primary-300"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($bookInfo['title']); ?></p>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($bookInfo['author']); ?></p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Barcode:</span>
                        <span class="text-gray-800 dark:text-white ml-1"><?php echo htmlspecialchars($bookInfo['barcode']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Status:</span>
                        <span class="text-gray-800 dark:text-white ml-1"><?php echo htmlspecialchars($bookInfo['status']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mb-6">
            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Member Information</h4>
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <div class="flex items-center mb-3">
                    <div class="bg-primary-100 dark:bg-primary-900 p-2 rounded-full mr-3">
                        <i class="fas fa-user text-primary-600 dark:text-primary-300"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($transactionInfo['member_name']); ?></p>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($transactionInfo['member_barcode']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div>
            <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Transaction Details</h4>
            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Transaction ID:</span>
                        <span class="text-gray-800 dark:text-white ml-1"><?php echo htmlspecialchars($transactionInfo['id']); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Borrow Date:</span>
                        <span class="text-gray-800 dark:text-white ml-1"><?php echo date('M j, Y', strtotime($transactionInfo['borrow_date'])); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Due Date:</span>
                        <span class="text-gray-800 dark:text-white ml-1"><?php echo date('M j, Y', strtotime($transactionInfo['due_date'])); ?></span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Status:</span>
                        <span class="text-gray-800 dark:text-white ml-1"><?php echo htmlspecialchars($transactionInfo['status']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Include footer
include 'includes/footer.php';
?> 