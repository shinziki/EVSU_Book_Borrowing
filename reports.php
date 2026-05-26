<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';
require_once 'config/report_helpers.php';

requireAdmin();

$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$currentYear = (int) date('Y');
$minYear = $currentYear - 10;

if ($selectedYear < $minYear || $selectedYear > $currentYear) {
    $selectedYear = $currentYear;
}

if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    generateAnnualReportPdf($selectedYear);
}

$preview = getAnnualReportData($selectedYear);
$summary = $preview['summary'];

include 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Annual Reports</h2>
    <p class="text-gray-600 dark:text-gray-400">Generate a comprehensive PDF report of all library data for a selected year.</p>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6 mb-6">
    <form method="GET" action="reports.php" class="flex flex-col md:flex-row md:items-end gap-4">
        <div class="w-full md:w-48">
            <label for="year" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Report Year</label>
            <select id="year" name="year"
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-[#a91515] focus:border-[#a91515] dark:bg-gray-700 dark:text-white">
                <?php for ($y = $currentYear; $y >= $minYear; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $selectedYear === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg">
                <i class="fas fa-eye mr-2"></i> Preview
            </button>
            <a href="reports.php?year=<?php echo $selectedYear; ?>&download=pdf"
               class="bg-[#a91515] hover:bg-[#8f1212] text-white px-6 py-2 rounded-lg inline-flex items-center">
                <i class="fas fa-file-pdf mr-2"></i> Download PDF
            </a>
        </div>
    </form>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Total Books</p>
        <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $summary['total_books']; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Total Members</p>
        <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $summary['total_members']; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Borrow Transactions</p>
        <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $summary['total_borrows']; ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Penalties (PHP)</p>
        <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($summary['penalty_total'], 2); ?></p>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Report Preview — <?php echo $selectedYear; ?></h3>
    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        The PDF includes: executive summary, books catalog, members list, transactions, penalties, overdue records, and notifications.
    </p>
    <ul class="text-sm text-gray-700 dark:text-gray-300 space-y-2">
        <li><i class="fas fa-book text-[#a91515] mr-2"></i> Books: <?php echo count($preview['books']); ?> titles</li>
        <li><i class="fas fa-users text-[#a91515] mr-2"></i> Members: <?php echo count($preview['members']); ?> registered</li>
        <li><i class="fas fa-exchange-alt text-[#a91515] mr-2"></i> Transactions: <?php echo count($preview['transactions']); ?> borrow records</li>
        <li><i class="fas fa-file-invoice-dollar text-[#a91515] mr-2"></i> Penalties: <?php echo count($preview['penalties']); ?> records</li>
        <li><i class="fas fa-exclamation-circle text-[#a91515] mr-2"></i> Overdue: <?php echo count($preview['overdue']); ?> records</li>
        <li><i class="fas fa-bell text-[#a91515] mr-2"></i> Notifications: <?php echo count($preview['notifications']); ?> logged (up to 100 in PDF)</li>
    </ul>
</div>

<?php include 'includes/footer.php'; ?>
