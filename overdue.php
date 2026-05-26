<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

// Get all overdue books
$stmt = $pdo->query("
    SELECT 
        t.id as transaction_id, 
        t.borrow_date, 
        t.due_date, 
        t.payment_amount,
        t.status,
        b.id as book_id, 
        b.title as book_title, 
        b.author as book_author, 
        b.barcode as book_barcode,
        m.id as member_id, 
        m.fullname as member_name, 
        m.barcode as member_barcode,
        m.email as member_email,
        DATEDIFF(CURRENT_DATE, t.due_date) as days_overdue
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    JOIN members m ON t.member_id = m.id
    WHERE t.status IN ('Overdue', 'Needs Replacement')
    ORDER BY days_overdue DESC
");
$overdueBooks = $stmt->fetchAll();

// Include header
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Overdue Books</h2>
        <p class="text-gray-600 dark:text-gray-400">View books that are past their due date</p>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
    <?php if (count($overdueBooks) > 0): ?>
        <div class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-300 text-xl mr-3"></i>
                <p class="text-yellow-800 dark:text-yellow-200">
                    <span class="font-bold"><?php echo count($overdueBooks); ?></span> books are currently overdue / need replacement.
                    Late returns incur a flat penalty fee (see amount per book below).
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 rounded-tl-lg">Book</th>
                        <th class="px-4 py-3">Member</th>
                        <th class="px-4 py-3">Due Date</th>
                        <th class="px-4 py-3">Days Overdue</th>
                        <th class="px-4 py-3 rounded-tr-lg">Penalty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdueBooks as $overdue): ?>
                        <tr class="border-b dark:border-gray-700 <?php 
                            if ($overdue['status'] === 'Needs Replacement') echo 'bg-purple-50/30 dark:bg-purple-900/10';
                            elseif ($overdue['days_overdue'] > 14) echo 'bg-red-50 dark:bg-red-900/20';
                            else echo 'bg-white dark:bg-gray-900'; 
                        ?>">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white flex flex-wrap items-center gap-2">
                                    <span><?php echo htmlspecialchars($overdue['book_title']); ?></span>
                                </div>
                                <div class="text-xs text-gray-500">Barcode: <?php echo htmlspecialchars($overdue['book_barcode']); ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <div><?php echo htmlspecialchars($overdue['member_name']); ?></div>
                                <div class="text-xs text-gray-500">
                                    <?php echo !empty($overdue['member_email']) ? htmlspecialchars($overdue['member_email']) : 'No email'; ?>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <?php echo date('M j, Y, g:i A', strtotime($overdue['due_date'])); ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($overdue['days_overdue'] > 7): ?>
                                    <div class="px-2 py-1 rounded-full text-xs inline-block bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 font-semibold">
                                        Needs Replacement
                                    </div>
                                <?php else: ?>
                                    <div class="px-2 py-1 rounded-full text-xs inline-block
                                        <?php 
                                        if ($overdue['days_overdue'] > 30) echo 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                                        else if ($overdue['days_overdue'] > 14) echo 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
                                        else echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                                        ?>">
                                        <?php echo $overdue['days_overdue']; ?> days
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 font-medium text-red-600 dark:text-red-400">
                                ₱<?php echo number_format(calculateLateReturnPenalty($overdue), 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="p-8 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 dark:bg-green-900 mb-4">
                <i class="fas fa-check text-green-500 dark:text-green-300 text-2xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">No Overdue Books</h3>
            <p class="text-gray-600 dark:text-gray-400">All books are currently returned on time.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?> 