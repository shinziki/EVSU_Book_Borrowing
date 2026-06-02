<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

requireLogin();

$tab = ($_GET['tab'] ?? 'most') === 'least' ? 'least' : 'most';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 25;
$limit = max(10, min(50, $limit));

$yearFilter = null;
if (isset($_GET['year']) && $_GET['year'] !== '' && $_GET['year'] !== 'all') {
    $yearFilter = (int) $_GET['year'];
    if ($yearFilter <= 0) {
        $yearFilter = null;
    }
}

$availableYears = getTransactionBorrowYears();
$summary = getBookBorrowMetricsSummary($yearFilter);
$order = $tab === 'least' ? 'ASC' : 'DESC';
$rankings = getBookBorrowRankings($order, $limit, $yearFilter);

$maxCount = 0;
foreach ($rankings as $row) {
    $maxCount = max($maxCount, (int) $row['borrow_count']);
}

include 'includes/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Book Metrics</h2>
        <p class="text-gray-600 dark:text-gray-400">See which books are borrowed most and least</p>
    </div>
    <form method="GET" action="book_metrics.php" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <label for="year" class="text-sm text-gray-600 dark:text-gray-400">Period:</label>
        <select id="year" name="year" onchange="this.form.submit()"
                class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
            <option value="all" <?php echo $yearFilter === null ? 'selected' : ''; ?>>All time</option>
            <?php foreach ($availableYears as $y): ?>
                <option value="<?php echo (int) $y; ?>" <?php echo $yearFilter === (int) $y ? 'selected' : ''; ?>><?php echo (int) $y; ?></option>
            <?php endforeach; ?>
        </select>
        <label for="limit" class="text-sm text-gray-600 dark:text-gray-400 ml-1">Show:</label>
        <select id="limit" name="limit" onchange="this.form.submit()"
                class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
            <?php foreach ([10, 25, 50] as $n): ?>
                <option value="<?php echo $n; ?>" <?php echo $limit === $n ? 'selected' : ''; ?>">Top <?php echo $n; ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4 mb-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total borrows</p>
        <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($summary['total_borrows']); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Books in catalog</p>
        <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($summary['total_books']); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Ever borrowed</p>
        <p class="text-2xl font-bold text-green-600 dark:text-green-400"><?php echo number_format($summary['books_with_borrows']); ?></p>
    </div>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Never borrowed</p>
        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400"><?php echo number_format($summary['books_never_borrowed']); ?></p>
    </div>
</div>

<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
    <div class="border-b border-gray-200 dark:border-gray-700 px-4 pt-4">
        <div class="flex rounded-lg bg-gray-100 dark:bg-gray-700 p-1 max-w-md">
            <a href="book_metrics.php?tab=most&amp;year=<?php echo $yearFilter ? (int) $yearFilter : 'all'; ?>&amp;limit=<?php echo $limit; ?>"
               class="flex-1 py-2 px-3 rounded-lg text-sm font-semibold text-center transition <?php echo $tab === 'most' ? 'bg-white dark:bg-gray-600 text-primary-600 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white'; ?>">
                <i class="fas fa-sort-amount-down mr-1"></i> Most Borrowed
            </a>
            <a href="book_metrics.php?tab=least&amp;year=<?php echo $yearFilter ? (int) $yearFilter : 'all'; ?>&amp;limit=<?php echo $limit; ?>"
               class="flex-1 py-2 px-3 rounded-lg text-sm font-semibold text-center transition <?php echo $tab === 'least' ? 'bg-white dark:bg-gray-600 text-primary-600 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white'; ?>">
                <i class="fas fa-sort-amount-up mr-1"></i> Least Borrowed
            </a>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400 py-3">
            <?php if ($tab === 'most'): ?>
                Books with the highest number of borrow transactions<?php echo $yearFilter ? ' in ' . $yearFilter : ''; ?>.
            <?php else: ?>
                Books with the fewest borrows (including never borrowed)<?php echo $yearFilter ? ' in ' . $yearFilter : ''; ?>.
            <?php endif; ?>
        </p>
    </div>

    <?php if (count($rankings) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 w-12">#</th>
                        <th class="px-4 py-3">Book</th>
                        <th class="px-4 py-3">Barcode</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Borrows</th>
                        <th class="px-4 py-3">Last borrowed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rankings as $index => $book): ?>
                        <?php
                            $count = (int) $book['borrow_count'];
                            $barPercent = $maxCount > 0 ? round(($count / $maxCount) * 100) : 0;
                            $rank = $index + 1;
                        ?>
                        <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 font-semibold text-gray-500 dark:text-gray-400"><?php echo $rank; ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($book['title']); ?></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($book['author']); ?></div>
                                <div class="mt-2 h-1.5 w-full max-w-xs bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full <?php echo $tab === 'most' ? 'bg-primary-500' : 'bg-amber-500'; ?>"
                                         style="width: <?php echo $barPercent; ?>%"></div>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap"><?php echo htmlspecialchars($book['barcode']); ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                    <?php echo $book['status'] === 'Available' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'; ?>">
                                    <?php echo htmlspecialchars($book['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-lg font-bold <?php echo $tab === 'most' ? 'text-primary-600 dark:text-primary-400' : 'text-amber-600 dark:text-amber-400'; ?>">
                                    <?php echo number_format($count); ?>
                                </span>
                                <?php if ($count === 0): ?>
                                    <span class="block text-xs text-gray-500">No borrows yet</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                <?php echo !empty($book['last_borrowed']) ? date('M j, Y', strtotime($book['last_borrowed'])) : '—'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="p-12 text-center">
            <i class="fas fa-chart-bar text-gray-300 dark:text-gray-600 text-5xl mb-4"></i>
            <p class="text-gray-500 dark:text-gray-400">No books in the catalog yet.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
