<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

// Update overdue books
updateOverdueBooks();

// Get dashboard statistics
$stats = getDashboardStats();

// Get recent activities
$recentActivities = getRecentActivities(3);

// Include header
include 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Dashboard</h2>
    <p class="text-gray-600 dark:text-gray-400">Overview of library operations</p>
</div>

<div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-8">
    <div class="grid-card bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 md:p-6">
        <div class="flex items-center">
            <div class="bg-primary-100 dark:bg-primary-900 p-2 md:p-3 rounded-lg mr-3 md:mr-4">
                <i class="fas fa-book text-primary-600 dark:text-primary-300 text-xl md:text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs md:text-sm">Total Books</p>
                <p class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['total_books']; ?></p>
            </div>
        </div>
    </div>
    
    <div class="grid-card bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 md:p-6">
        <div class="flex items-center">
            <div class="bg-green-100 dark:bg-green-900 p-2 md:p-3 rounded-lg mr-3 md:mr-4">
                <i class="fas fa-users text-green-600 dark:text-green-300 text-xl md:text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs md:text-sm">Members</p>
                <p class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['total_members']; ?></p>
            </div>
        </div>
    </div>
    
    <div class="grid-card bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 md:p-6">
        <div class="flex items-center">
            <div class="bg-yellow-100 dark:bg-yellow-900 p-2 md:p-3 rounded-lg mr-3 md:mr-4">
                <i class="fas fa-hand-holding text-yellow-600 dark:text-yellow-300 text-xl md:text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs md:text-sm">Borrowed</p>
                <p class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['borrowed_books']; ?></p>
            </div>
        </div>
    </div>
    
    <div class="grid-card bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 md:p-6">
        <div class="flex items-center">
            <div class="bg-red-100 dark:bg-red-900 p-2 md:p-3 rounded-lg mr-3 md:mr-4">
                <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-300 text-xl md:text-2xl"></i>
            </div>
            <div>
                <p class="text-gray-500 dark:text-gray-400 text-xs md:text-sm">Overdue</p>
                <p class="text-lg md:text-2xl font-bold text-gray-800 dark:text-white"><?php echo $stats['overdue_books']; ?></p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 md:p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Recent Activity</h3>
        <div class="space-y-4">
            <?php if (count($recentActivities) > 0): ?>
                <?php foreach ($recentActivities as $index => $activity): ?>
                    <div class="flex items-start <?php echo ($index < count($recentActivities) - 1) ? 'border-b border-gray-200 dark:border-gray-700 pb-4' : ''; ?>">
                        <?php if ($activity['status'] === 'Borrowed'): ?>
                            <div class="bg-green-100 dark:bg-green-900 p-2 rounded-lg mr-3 flex-shrink-0">
                                <i class="fas fa-hand-holding text-green-600 dark:text-green-300"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 dark:text-white truncate"><?php echo htmlspecialchars($activity['book_title']); ?></p>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Borrowed by <?php echo htmlspecialchars($activity['member_name']); ?></p>
                                <p class="text-gray-500 dark:text-gray-500 text-xs mt-1"><?php echo date('M j, Y', strtotime($activity['borrow_date'])); ?></p>
                            </div>
                        <?php elseif ($activity['status'] === 'Returned'): ?>
                            <div class="bg-blue-100 dark:bg-blue-900 p-2 rounded-lg mr-3 flex-shrink-0">
                                <i class="fas fa-undo text-blue-600 dark:text-blue-300"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 dark:text-white truncate"><?php echo htmlspecialchars($activity['book_title']); ?></p>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Returned by <?php echo htmlspecialchars($activity['member_name']); ?></p>
                                <p class="text-gray-500 dark:text-gray-500 text-xs mt-1"><?php echo date('M j, Y', strtotime($activity['return_date'])); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="bg-red-100 dark:bg-red-900 p-2 rounded-lg mr-3 flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-300"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 dark:text-white truncate"><?php echo htmlspecialchars($activity['book_title']); ?></p>
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Overdue from <?php echo htmlspecialchars($activity['member_name']); ?></p>
                                <p class="text-gray-500 dark:text-gray-500 text-xs mt-1">Due: <?php echo date('M j, Y', strtotime($activity['borrow_date'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4 text-gray-500 dark:text-gray-400">
                    <p>No recent activities found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 md:p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-3 sm:block sm:space-y-4">
            <?php if (isAdmin() || staffHasPermission('metrics.view') || staffHasPermission('dashboard.view')): ?>
            <a href="book_metrics.php" class="w-full flex flex-col sm:flex-row items-center justify-center sm:justify-start p-3 sm:p-4 rounded-lg bg-indigo-50 dark:bg-gray-700 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-gray-600 transition">
                <i class="fas fa-chart-line mb-1 sm:mb-0 sm:mr-3 text-lg"></i>
                <span class="text-sm sm:text-base">Book Metrics</span>
            </a>
            <?php endif; ?>

            <?php if (isAdmin()): ?>
            <a href="books.php?action=add" class="w-full flex flex-col sm:flex-row items-center justify-center sm:justify-start p-3 sm:p-4 rounded-lg bg-primary-50 dark:bg-gray-700 text-primary-700 dark:text-primary-300 hover:bg-primary-100 dark:hover:bg-gray-600 transition">
                <i class="fas fa-plus-circle mb-1 sm:mb-0 sm:mr-3 text-lg"></i>
                <span class="text-sm sm:text-base">Add Book</span>
            </a>
            <a href="reports.php" class="w-full flex flex-col sm:flex-row items-center justify-center sm:justify-start p-3 sm:p-4 rounded-lg bg-red-50 dark:bg-gray-700 text-[#a91515] dark:text-red-300 hover:bg-red-100 dark:hover:bg-gray-600 transition">
                <i class="fas fa-file-pdf mb-1 sm:mb-0 sm:mr-3 text-lg"></i>
                <span class="text-sm sm:text-base">Annual Report (PDF)</span>
            </a>
            <?php endif; ?>

            <?php if (isStaff() && staffHasPermission('members.add')): ?>
            <a href="members.php?action=add" class="w-full flex flex-col sm:flex-row items-center justify-center sm:justify-start p-3 sm:p-4 rounded-lg bg-green-50 dark:bg-gray-700 text-green-700 dark:text-green-300 hover:bg-green-100 dark:hover:bg-gray-600 transition">
                <i class="fas fa-user-plus mb-1 sm:mb-0 sm:mr-3 text-lg"></i>
                <span class="text-sm sm:text-base">Add Member</span>
            </a>
            <?php endif; ?>
            
            <?php if (isStaff() && staffHasPermission('borrow.process')): ?>
            <a href="borrow.php" class="w-full flex flex-col sm:flex-row items-center justify-center sm:justify-start p-3 sm:p-4 rounded-lg bg-blue-50 dark:bg-gray-700 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-gray-600 transition">
                <i class="fas fa-barcode mb-1 sm:mb-0 sm:mr-3 text-lg"></i>
                <span class="text-sm sm:text-base">Borrow</span>
            </a>
            <?php endif; ?>
            
            <?php if (isStaff() && staffHasPermission('return.process')): ?>
            <a href="return.php" class="w-full flex flex-col sm:flex-row items-center justify-center sm:justify-start p-3 sm:p-4 rounded-lg bg-purple-50 dark:bg-gray-700 text-purple-700 dark:text-purple-300 hover:bg-purple-100 dark:hover:bg-gray-600 transition">
                <i class="fas fa-undo mb-1 sm:mb-0 sm:mr-3 text-lg"></i>
                <span class="text-sm sm:text-base">Return</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?> 