<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();
ensureMemberStudentIdColumn();

$canViewTxDetails = staffHasPermission('transactions.view_details');
$canDeleteTx = staffHasPermission('transactions.delete');
$canDeleteAllTx = staffHasPermission('transactions.delete_all');
$canShowTxActions = $canViewTxDetails || $canDeleteTx;

// Delete actions (admin always allowed; staff per permission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete_transaction') {
            if (!$canDeleteTx) {
                setFlashMessage('You do not have permission to delete transactions.', 'error');
                header('Location: transactions.php');
                exit;
            }

            $txId = (int) ($_POST['transaction_id'] ?? 0);
            if ($txId <= 0) {
                setFlashMessage('Invalid transaction ID.', 'error');
                header('Location: transactions.php');
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = :id");
            $stmt->bindParam(':id', $txId, PDO::PARAM_INT);
            $stmt->execute();

            setFlashMessage('Transaction deleted successfully.', 'success');
            header('Location: transactions.php');
            exit;
        }

        if ($action === 'delete_all_transactions') {
            if (!$canDeleteAllTx) {
                setFlashMessage('You do not have permission to delete all transactions.', 'error');
                header('Location: transactions.php');
                exit;
            }

            $confirm = trim((string) ($_POST['confirm'] ?? ''));
            if ($confirm !== 'DELETE ALL') {
                setFlashMessage('Confirmation text mismatch. Type "DELETE ALL" to proceed.', 'error');
                header('Location: transactions.php');
                exit;
            }

            // Clear all transaction records
            $pdo->exec("DELETE FROM transactions");

            setFlashMessage('All transactions were deleted.', 'success');
            header('Location: transactions.php');
            exit;
        }
    } catch (PDOException $e) {
        setFlashMessage('Database error: ' . $e->getMessage(), 'error');
        header('Location: transactions.php');
        exit;
    }
}

// Initialize filter variables
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filters
$query = "
    SELECT t.*, 
           b.title AS book_title, b.author AS book_author, b.barcode AS book_barcode, b.isbn AS book_isbn,
           m.fullname AS member_name, m.barcode AS member_barcode, m.email AS member_email,
           m.phone AS member_phone, m.student_id AS member_student_id
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN members m ON t.member_id = m.id
    WHERE 1=1
";

$params = [];

if ($status !== 'all') {
    $query .= " AND t.status = :status";
    $params[':status'] = $status;
}

if (!empty($search)) {
    $query .= " AND (b.title LIKE :search OR b.author LIKE :search OR m.fullname LIKE :search OR b.barcode LIKE :search OR m.barcode LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY t.borrow_date DESC, t.id DESC";

// Prepare and execute query
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$transactions = $stmt->fetchAll();

// Include header
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo isStaff() ? 'Borrow & Return History' : 'Transaction History'; ?></h2>
        <p class="text-gray-600 dark:text-gray-400">View borrow and return records with member and book details</p>
    </div>
    <?php if ($canDeleteAllTx): ?>
        <div class="flex gap-2">
            <button type="button" onclick="openDeleteAllModal()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-trash mr-2"></i> Delete All Transactions
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 mb-6">
    <form method="GET" action="transactions.php" class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
            <input type="text" id="search" name="search" placeholder="Search by title, author, or member name" 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
        </div>
        
        <div class="w-full md:w-48">
            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
            <select id="status" name="status" 
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                <option value="all" <?php echo ($status === 'all') ? 'selected' : ''; ?>>All Transactions</option>
                <option value="Borrowed" <?php echo ($status === 'Borrowed') ? 'selected' : ''; ?>>Currently Borrowed</option>
                <option value="Returned" <?php echo ($status === 'Returned') ? 'selected' : ''; ?>>Returned</option>
                <option value="Overdue" <?php echo ($status === 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                <option value="Needs Replacement" <?php echo ($status === 'Needs Replacement') ? 'selected' : ''; ?>>Needs Replacement</option>
            </select>
        </div>
        
        <div class="self-end">
            <button type="submit" class="w-full md:w-auto bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg">
                <i class="fas fa-search mr-2"></i> Filter
            </button>
        </div>
    </form>
</div>

<!-- Transactions Table -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Transaction Records</h3>
        <span class="text-gray-600 dark:text-gray-400"><?php echo count($transactions); ?> transactions found</span>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Book</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Member</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Borrow Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Return Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    <?php if ($canShowTxActions): ?>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <?php if (count($transactions) > 0): ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="bg-gray-200 dark:bg-gray-700 rounded-lg w-10 h-10 flex items-center justify-center mr-3">
                                        <i class="fas fa-book text-gray-500 dark:text-gray-400"></i>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($transaction['book_title']); ?></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($transaction['book_author']); ?></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">Barcode: <?php echo htmlspecialchars($transaction['book_barcode']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($transaction['member_name']); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Barcode: <?php echo htmlspecialchars($transaction['member_barcode']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <?php echo date('M j, Y, g:i A', strtotime($transaction['borrow_date'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <?php echo date('M j, Y, g:i A', strtotime($transaction['due_date'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <?php echo ($transaction['return_date']) ? date('M j, Y, g:i A', strtotime($transaction['return_date'])) : '-'; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($transaction['status'] === 'Borrowed'): ?>
                                    <span class="px-3 py-1 text-sm rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Borrowed</span>
                                <?php elseif ($transaction['status'] === 'Returned'): ?>
                                    <span class="px-3 py-1 text-sm rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Returned</span>
                                <?php elseif ($transaction['status'] === 'Overdue'): ?>
                                    <span class="px-3 py-1 text-sm rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Overdue</span>
                                <?php elseif ($transaction['status'] === 'Needs Replacement'): ?>
                                    <span class="px-3 py-1 text-sm rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Needs Replacement</span>
                                <?php else: ?>
                                    <span class="px-3 py-1 text-sm rounded-full bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($transaction['status']); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php if ($canShowTxActions): ?>
                                <td class="px-6 py-4 text-right text-sm whitespace-nowrap">
                                    <?php
                                    $txDetails = [
                                        'id' => (int) $transaction['id'],
                                        'status' => $transaction['status'],
                                        'borrow_date' => $transaction['borrow_date'],
                                        'due_date' => $transaction['due_date'],
                                        'return_date' => $transaction['return_date'] ?? '',
                                        'payment_amount' => (float) ($transaction['payment_amount'] ?? 0),
                                        'payment_status' => $transaction['payment_status'] ?? '',
                                        'penalty_amount' => (float) ($transaction['penalty_amount'] ?? 0),
                                        'penalty_type' => $transaction['penalty_type'] ?? '',
                                        'book_title' => $transaction['book_title'],
                                        'book_author' => $transaction['book_author'],
                                        'book_barcode' => $transaction['book_barcode'],
                                        'book_isbn' => $transaction['book_isbn'] ?? '',
                                        'member_name' => $transaction['member_name'],
                                        'member_barcode' => $transaction['member_barcode'],
                                        'member_student_id' => $transaction['member_student_id'] ?? '',
                                        'member_email' => $transaction['member_email'] ?? '',
                                        'member_phone' => $transaction['member_phone'] ?? '',
                                    ];
                                    ?>
                                    <?php if ($canViewTxDetails): ?>
                                    <button type="button"
                                            class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 mr-3"
                                            title="View details"
                                            onclick='openTransactionModal(<?php echo json_encode($txDetails, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($canDeleteTx): ?>
                                    <form method="POST" action="transactions.php" onsubmit="return confirm('Delete this transaction? This cannot be undone.');" class="inline">
                                        <input type="hidden" name="action" value="delete_transaction">
                                        <input type="hidden" name="transaction_id" value="<?php echo (int) $transaction['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $canShowTxActions ? 7 : 6; ?>" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No transactions found matching the current filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canViewTxDetails || $canDeleteAllTx): ?>
    <?php if ($canViewTxDetails): ?>
    <!-- Transaction Details Modal -->
    <div id="transactionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center sticky top-0 bg-white dark:bg-gray-800">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Transaction Details</h3>
                <button type="button" onclick="closeTransactionModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-6" id="transactionModalBody">
                <!-- Filled by JavaScript -->
            </div>
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                <button type="button" onclick="closeTransactionModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">Close</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($canDeleteAllTx): ?>
    <!-- Delete All Modal -->
    <div id="deleteAllModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Delete all transactions</h3>
            <p class="text-gray-700 dark:text-gray-300 mb-4">
                This will permanently delete <strong>all</strong> transaction records. Type <strong>DELETE ALL</strong> to confirm.
            </p>

            <form method="POST" action="transactions.php" class="space-y-4">
                <input type="hidden" name="action" value="delete_all_transactions">
                <input type="text" name="confirm" placeholder="Type DELETE ALL"
                       class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:bg-gray-700 dark:text-white">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeDeleteAllModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">Cancel</button>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">Delete All</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        <?php if ($canViewTxDetails): ?>
        function formatDateTime(value) {
            if (!value) return '-';
            const d = new Date(String(value).replace(' ', 'T'));
            if (isNaN(d.getTime())) return value;
            return d.toLocaleString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }

        function openTransactionModal(tx) {
            const penaltyType = tx.penalty_type
                ? tx.penalty_type.charAt(0).toUpperCase() + tx.penalty_type.slice(1)
                : '-';

            const html = `
                <div>
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Transaction</h4>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500 dark:text-gray-400">Transaction ID:</span> <span class="font-medium text-gray-900 dark:text-white">TRX${escapeHtml(tx.id)}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Status:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.status)}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Borrow Date:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(formatDateTime(tx.borrow_date))}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Due Date:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(formatDateTime(tx.due_date))}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Return Date:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(formatDateTime(tx.return_date))}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Receipt Barcode:</span> <span class="font-medium text-gray-900 dark:text-white">TRX${escapeHtml(tx.id)}</span></div>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Book</h4>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div class="sm:col-span-2"><span class="text-gray-500 dark:text-gray-400">Title:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.book_title)}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Author:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.book_author)}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Barcode:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.book_barcode)}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">ISBN:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.book_isbn || '-')}</span></div>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Member</h4>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div class="sm:col-span-2"><span class="text-gray-500 dark:text-gray-400">Name:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.member_name)}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Member Barcode:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.member_barcode)}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Student ID:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.member_student_id || '-')}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Email:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.member_email || '-')}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Phone:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.member_phone || '-')}</span></div>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Payment &amp; Penalties</h4>
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500 dark:text-gray-400">Borrow Fee:</span> <span class="font-medium text-gray-900 dark:text-white">₱${Number(tx.payment_amount).toFixed(2)}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Payment Status:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(tx.payment_status || '-')}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Penalty Amount:</span> <span class="font-medium text-gray-900 dark:text-white">₱${Number(tx.penalty_amount).toFixed(2)}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-400">Penalty Type:</span> <span class="font-medium text-gray-900 dark:text-white">${escapeHtml(penaltyType)}</span></div>
                    </div>
                </div>
            `;

            document.getElementById('transactionModalBody').innerHTML = html;
            document.getElementById('transactionModal').classList.remove('hidden');
        }

        function closeTransactionModal() {
            document.getElementById('transactionModal').classList.add('hidden');
        }

        document.getElementById('transactionModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeTransactionModal();
        });
        <?php endif; ?>

        <?php if ($canDeleteAllTx): ?>
        function openDeleteAllModal() {
            document.getElementById('deleteAllModal').classList.remove('hidden');
        }
        function closeDeleteAllModal() {
            document.getElementById('deleteAllModal').classList.add('hidden');
        }
        <?php endif; ?>
    </script>
<?php endif; ?>

<?php
// Include footer
include 'includes/footer.php';
?> 