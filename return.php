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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = $_POST['book_barcode'] ?? '';
    
    // Validate inputs
    if (empty($barcode)) {
        $message = 'Barcode is required';
        $messageType = 'error';
    } else {
        // Check if it's a transaction barcode (starting with TRX)
        if (strpos($barcode, 'TRX') === 0) {
            $transactionId = substr($barcode, 3); // Remove TRX prefix
            
            // Get transaction info by ID
            $stmt = $pdo->prepare("
                SELECT t.*, b.barcode as book_barcode
                FROM transactions t 
                JOIN books b ON t.book_id = b.id
                WHERE t.id = :transaction_id AND t.status IN ('Borrowed', 'Overdue', 'Needs Replacement')
            ");
            $stmt->bindParam(':transaction_id', $transactionId, PDO::PARAM_INT);
            $stmt->execute();
            $transactionData = $stmt->fetch();
            
            if (!$transactionData) {
                $message = 'Transaction not found or already returned';
                $messageType = 'error';
            } else {
                // Use the book barcode from the transaction to process return
                $result = returnBook($transactionData['book_barcode']);
                
                if ($result) {
                    // Mark payment as paid
                    $paymentStatus = ($result['has_penalty']) ? 'Overdue Fee Paid' : 'Paid';
                    
                    $stmt = $pdo->prepare("
                        UPDATE transactions 
                        SET payment_status = :payment_status
                        WHERE id = :id
                    ");
                    $stmt->bindParam(':payment_status', $paymentStatus);
                    $stmt->bindParam(':id', $transactionId, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    setFlashMessage('Book returned successfully and payment recorded', 'success');
                    header('Location: return.php');
                    exit;
                } else {
                    // Get book info to check status
                    $bookInfo = getBookByBarcode($transactionData['book_barcode']);
                    if ($bookInfo && $bookInfo['status'] === 'Available') {
                        $message = 'Book appears to be already returned.';
                    } else {
                        $message = 'Failed to process return. Please try again or contact a librarian.';
                    }
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
                $message = 'Book is already available and not borrowed';
                $messageType = 'error';
            } else {
                // Process returning
                if (returnBook($barcode)) {
                    setFlashMessage('Book returned successfully', 'success');
                    header('Location: return.php');
                    exit;
                } else {
                    // Debug logging
                    error_log("Return failed for book barcode: " . $barcode);
                    
                    // Check if there are active transactions for this book
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count
                        FROM transactions
                        WHERE book_id = :book_id AND status IN ('Borrowed', 'Overdue', 'Needs Replacement')
                    ");
                    $stmt->bindParam(':book_id', $bookInfo['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch();
                    
                    if ($result && $result['count'] == 0) {
                        $message = 'Book appears to be already returned.';
                    } else {
                        $message = 'Failed to process return. Please try again or contact a librarian.';
                    }
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
                   m.fullname as member_name, m.barcode as member_barcode
            FROM transactions t 
            JOIN books b ON t.book_id = b.id
            JOIN members m ON t.member_id = m.id
            WHERE t.id = :transaction_id AND t.status IN ('Borrowed', 'Overdue', 'Needs Replacement')
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
                SELECT t.*, m.fullname as member_name, m.barcode as member_barcode
                FROM transactions t
                JOIN members m ON t.member_id = m.id
                WHERE t.book_id = :book_id AND t.status IN ('Borrowed', 'Overdue', 'Needs Replacement')
                ORDER BY t.id DESC
                LIMIT 1
            ");
            $stmt->bindParam(':book_id', $bookInfo['id'], PDO::PARAM_INT);
            $stmt->execute();
            $transactionInfo = $stmt->fetch();
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Return a Book</h2>
    <p class="text-gray-600 dark:text-gray-400">Scan book barcode or receipt barcode to return</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Scan Barcode to Return</h3>
        
        <div class="p-4 mb-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-info-circle text-blue-600 dark:text-blue-300 text-xl mr-3"></i>
                <p class="text-blue-800 dark:text-blue-200">You can scan either the book barcode or the transaction barcode from the borrowing receipt</p>
            </div>
        </div>
        
        <form method="POST" action="return.php" class="space-y-6">
            <div>
                <label for="book_barcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Scan Barcode</label>
                <input type="text" id="book_barcode" name="book_barcode" placeholder="Scan book or transaction barcode" 
                       value="<?php echo isset($_GET['barcode']) ? htmlspecialchars($_GET['barcode']) : ''; ?>"
                       class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"
                       autofocus>
            </div>
            
            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                Process Return
            </button>
        </form>
        
        <?php if ($bookInfo && $transactionInfo): ?>
            <div class="mt-6 p-4 bg-green-50 dark:bg-green-900 rounded-lg">
                <h4 class="font-semibold text-green-800 dark:text-green-200 mb-2">Book Information</h4>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Title:</span>
                        <span class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($bookInfo['title']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Author:</span>
                        <span class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($bookInfo['author']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Status:</span>
                        <span class="font-medium <?php echo ($bookInfo['status'] === 'Overdue' || $bookInfo['status'] === 'Needs Replacement') ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400'; ?>">
                            <?php echo htmlspecialchars($bookInfo['status']); ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Quantity:</span>
                        <span class="font-medium text-gray-800 dark:text-white">
                            <?php echo isset($bookInfo['quantity']) ? htmlspecialchars($bookInfo['quantity']) : 
                                  (isset($bookInfo['stock']) ? htmlspecialchars($bookInfo['stock']) : '1'); ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Borrowed By:</span>
                        <span class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($transactionInfo['member_name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Due Date:</span>
                        <span class="font-medium <?php echo (strtotime($transactionInfo['due_date']) < time()) ? 'text-red-600 dark:text-red-400' : 'text-gray-800 dark:text-white'; ?>">
                            <?php echo date('M j, Y', strtotime($transactionInfo['due_date'])); ?>
                        </span>
                    </div>
                    
                    <?php if (strtotime($transactionInfo['due_date']) < time()): ?>
                        <?php
                        $latePenalty = (float) ($transactionInfo['penalty_amount'] ?? 0);
                        if ($latePenalty <= 0) {
                            $latePenalty = calculateLateReturnPenalty($transactionInfo);
                        }
                        ?>
                        <?php if ($latePenalty > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Late Penalty:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">₱<?php echo number_format($latePenalty, 2); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Status:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">Overdue — penalty may apply</span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (isset($transactionInfo['id'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Transaction ID:</span>
                            <span class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($transactionInfo['id']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Return Information</h3>
        
        <div class="space-y-4">
            <div class="p-4 bg-yellow-50 dark:bg-yellow-900 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-receipt text-yellow-600 dark:text-yellow-300 text-xl mr-3"></i>
                    <div>
                        <p class="text-yellow-800 dark:text-yellow-200 font-medium">Receipt Barcode</p>
                        <p class="text-yellow-700 dark:text-yellow-300 text-sm">For quicker returns, scan the transaction barcode from the borrowing receipt</p>
                    </div>
                </div>
            </div>
            
            <div class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Demo Barcodes</h4>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Use these for testing:</p>
                
                <div class="grid grid-cols-1 gap-4">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Book: LIB1002 (Borrowed)</p>
                        <svg class="barcode-canvas mx-auto" jsbarcode-format="CODE128" jsbarcode-value="LIB1002" jsbarcode-textmargin="0" jsbarcode-fontoptions="bold"></svg>
                    </div>
                    <div class="text-center mt-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Transaction Receipt: TRX2</p>
                        <svg class="barcode-canvas mx-auto" jsbarcode-format="CODE128" jsbarcode-value="TRX2" jsbarcode-textmargin="0" jsbarcode-fontoptions="bold"></svg>
                    </div>
                </div>
            </div>
            
            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Recently Returned</h4>
                <?php
                // Get recently returned books
                $stmt = $pdo->query("
                    SELECT t.id, t.return_date, t.payment_status,
                           b.title as book_title, b.barcode as book_barcode,
                           m.fullname as member_name
                    FROM transactions t
                    JOIN books b ON t.book_id = b.id
                    JOIN members m ON t.member_id = m.id
                    WHERE t.status = 'Returned' AND t.return_date IS NOT NULL
                    ORDER BY t.return_date DESC
                    LIMIT 3
                ");
                $recentReturns = $stmt->fetchAll();
                
                if (count($recentReturns) > 0):
                ?>
                    <div class="space-y-3">
                        <?php foreach ($recentReturns as $return): ?>
                            <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-600 last:border-0">
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($return['book_title']); ?></p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Returned by: <?php echo htmlspecialchars($return['member_name']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo date('M j, Y', strtotime($return['return_date'])); ?></p>
                                    <p class="text-xs">
                                        <span class="inline-block px-2 py-1 rounded-full <?php echo (strpos($return['payment_status'], 'Paid') !== false) ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'; ?>">
                                            <?php echo htmlspecialchars($return['payment_status']); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600 dark:text-gray-400 text-center py-2">No recent returns</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?> 