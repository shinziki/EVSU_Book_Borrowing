<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

// Initialize filter variables
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filters
$query = "
    SELECT t.*, 
           b.title as book_title, b.author as book_author, b.barcode as book_barcode,
           m.fullname as member_name, m.barcode as member_barcode
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

$query .= " ORDER BY t.borrow_date DESC";

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
                                <?php echo date('M j, Y', strtotime($transaction['borrow_date'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <?php echo date('M j, Y', strtotime($transaction['due_date'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <?php echo ($transaction['return_date']) ? date('M j, Y', strtotime($transaction['return_date'])) : '-'; ?>
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
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No transactions found matching the current filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?> 