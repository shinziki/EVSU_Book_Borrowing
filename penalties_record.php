<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

// Get all penalties
$query = "
    SELECT t.id, t.return_date, t.penalty_amount, t.penalty_type, t.payment_status,
           b.title as book_title, b.author as book_author,
           m.fullname as member_name
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN members m ON t.member_id = m.id
    WHERE t.penalty_amount > 0
    ORDER BY t.return_date DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$penalties = $stmt->fetchAll();

// Include header
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-6 text-gray-800 dark:text-white">Penalties Record</h1>
    
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Member</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Book</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Penalty Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <?php if (count($penalties) > 0): ?>
                    <?php foreach ($penalties as $penalty): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                <?php echo date('M j, Y', strtotime($penalty['return_date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                <?php echo htmlspecialchars($penalty['member_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                <?php echo htmlspecialchars($penalty['book_title']); ?><br>
                                <span class="text-xs text-gray-500 dark:text-gray-400">by <?php echo htmlspecialchars($penalty['book_author']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $penalty['penalty_type'] === 'damaged' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                        ($penalty['penalty_type'] === 'lost' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'); ?>">
                                    <?php echo ucfirst(htmlspecialchars($penalty['penalty_type'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                ₱<?php echo number_format($penalty['penalty_amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo ($penalty['payment_status'] === 'Paid' || $penalty['payment_status'] === 'Penalty Paid') ? 
                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'; ?>">
                                    <?php echo htmlspecialchars($penalty['payment_status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if ($penalty['payment_status'] !== 'Paid' && $penalty['payment_status'] !== 'Penalty Paid'): ?>
                                    <a href="mark_penalty_paid.php?id=<?php echo $penalty['id']; ?>" class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300">Mark as Paid</a>
                                <?php endif; ?>
                                <a href="penalties.php?transaction_id=<?php echo $penalty['id']; ?>" class="ml-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            No penalty records found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
