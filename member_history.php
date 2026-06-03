<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

requireLogin();
ensureMemberYearLevelColumn();

$memberId = (int) ($_GET['id'] ?? 0);
if ($memberId <= 0) {
    setFlashMessage('Invalid member.', 'error');
    header('Location: members.php');
    exit;
}

$member = getMemberById($memberId);
if (!$member) {
    setFlashMessage('Member not found.', 'error');
    header('Location: members.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'Borrowed', 'Returned', 'Overdue', 'Needs Replacement'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$history = getMemberBorrowingHistory($memberId, $statusFilter);
$allHistory = getMemberBorrowingHistory($memberId, 'all');

$activeCount = 0;
$returnedCount = 0;
foreach ($allHistory as $row) {
    if ($row['status'] === 'Returned') {
        $returnedCount++;
    } else {
        $activeCount++;
    }
}

$canManageMembers = isAdmin() || staffHasPermission('members.edit');

include 'includes/header.php';
?>

<div class="mb-6 flex flex-wrap justify-between items-start gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Borrower Profile</h2>
        <p class="text-gray-600 dark:text-gray-400">Transaction history and member details</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="members.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg inline-flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Members
        </a>
        <?php if ($canManageMembers): ?>
        <a href="members.php?action=edit&id=<?php echo (int) $member['id']; ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg inline-flex items-center">
            <i class="fas fa-edit mr-2"></i> Edit Member
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Borrower profile -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 mb-6">
    <div class="flex flex-col md:flex-row gap-6">
        <div class="flex-shrink-0 text-center md:text-left">
            <?php if (!empty($member['photo_path']) && file_exists($member['photo_path'])): ?>
                <img src="<?php echo htmlspecialchars($member['photo_path']); ?>" alt="" class="h-24 w-24 rounded-full object-cover mx-auto md:mx-0 border-2 border-gray-200 dark:border-gray-600">
            <?php else: ?>
                <div class="h-24 w-24 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center mx-auto md:mx-0">
                    <i class="fas fa-user text-4xl text-gray-400 dark:text-gray-500"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Full Name</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($member['fullname']); ?></p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Student ID</p>
                <p class="text-gray-900 dark:text-white font-mono"><?php echo htmlspecialchars($member['student_id'] ?? '-'); ?></p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Department</p>
                <p class="text-gray-900 dark:text-white"><?php echo htmlspecialchars(getMemberDepartmentLabel($member['course'] ?? '')); ?></p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Year Level</p>
                <p class="text-gray-900 dark:text-white"><?php echo htmlspecialchars(getMemberYearLevelLabel($member['year_level'] ?? '')); ?></p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Gmail / EVSU Email</p>
                <p class="text-gray-900 dark:text-white">
                    <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="text-primary-600 hover:underline dark:text-primary-400">
                        <?php echo htmlspecialchars($member['email']); ?>
                    </a>
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Member Status</p>
                <?php
                $memberStatus = $member['status'] ?? 'active';
                if ($memberStatus === 'inactive'):
                ?>
                    <span class="inline-flex mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>
                <?php else: ?>
                    <span class="inline-flex mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
        <div class="bg-gray-50 dark:bg-gray-900/40 rounded-lg p-4">
            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo count($allHistory); ?></p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Total Records</p>
        </div>
        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
            <p class="text-2xl font-bold text-yellow-800 dark:text-yellow-200"><?php echo $activeCount; ?></p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Current / Open</p>
        </div>
        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
            <p class="text-2xl font-bold text-green-800 dark:text-green-200"><?php echo $returnedCount; ?></p>
            <p class="text-sm text-gray-600 dark:text-gray-400">Returned</p>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 mb-6">
    <form method="GET" action="member_history.php" class="flex flex-col sm:flex-row gap-4 items-end">
        <input type="hidden" name="id" value="<?php echo (int) $memberId; ?>">
        <div class="w-full sm:w-56">
            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by status</label>
            <select id="status" name="status" class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                <?php foreach ($allowedStatuses as $opt): ?>
                <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $statusFilter === $opt ? 'selected' : ''; ?>>
                    <?php echo $opt === 'all' ? 'All records' : htmlspecialchars($opt); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg">
            <i class="fas fa-filter mr-2"></i> Apply
        </button>
        <?php if ($statusFilter !== 'all'): ?>
        <a href="member_history.php?id=<?php echo (int) $memberId; ?>" class="text-gray-600 dark:text-gray-400 hover:underline py-2">Clear filter</a>
        <?php endif; ?>
    </form>
</div>

<!-- Borrowing history table -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Borrowing Records</h3>
        <span class="text-sm text-gray-600 dark:text-gray-400"><?php echo count($history); ?> record(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Book</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Borrow Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Return Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if (count($history) > 0): ?>
                    <?php foreach ($history as $tx): ?>
                    <tr class="bg-white dark:bg-gray-800">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($tx['book_title']); ?></div>
                            <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($tx['book_author']); ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                            <?php echo date('M j, Y g:i A', strtotime($tx['borrow_date'])); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                            <?php echo date('M j, Y g:i A', strtotime($tx['due_date'])); ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400 whitespace-nowrap">
                            <?php echo !empty($tx['return_date']) ? date('M j, Y g:i A', strtotime($tx['return_date'])) : '—'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php echo renderTransactionStatusBadge($tx['status']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            No borrowing records<?php echo $statusFilter !== 'all' ? ' for this filter' : ''; ?>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
