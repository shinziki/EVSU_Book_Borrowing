<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';
require_once 'config/report_helpers.php';

requireAdmin();

$selectedYear = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
$currentYear  = (int) date('Y');
$minYear      = $currentYear - 10;

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

<style>
/* ── Document preview ── */
.report-paper {
    background: #fff;
    color: #111;
    font-family: 'Georgia', serif;
    line-height: 1.6;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 48px 56px;
    box-shadow: 0 4px 24px rgba(0,0,0,.10);
    max-width: 960px;
    margin: 0 auto;
}
.dark .report-paper { background:#1e2433; color:#e5e7eb; border-color:#374151; }

.report-cover { text-align:center; padding-bottom:32px; border-bottom:2px solid #a91515; margin-bottom:32px; }
.report-cover .logo { width:72px; height:72px; object-fit:contain; margin-bottom:12px; }
.report-cover h1 { font-size:1.5rem; font-weight:700; margin:0 0 4px; color:#a91515; }
.report-cover h2 { font-size:1.1rem; font-weight:600; margin:0 0 8px; }
.report-cover p  { font-size:.82rem; color:#6b7280; margin:0; }
.dark .report-cover p { color:#9ca3af; }

.report-section { margin-bottom:36px; }
.report-section-title {
    font-size:1rem; font-weight:700; color:#a91515;
    border-bottom:1px solid #e5e7eb;
    padding-bottom:6px; margin-bottom:14px; letter-spacing:.03em;
}
.dark .report-section-title { border-color:#374151; }

/* KV summary table */
.kv-table { width:100%; border-collapse:collapse; font-size:.85rem; }
.kv-table tr:nth-child(even) td { background:#f9fafb; }
.dark .kv-table tr:nth-child(even) td { background:#1a2236; }
.kv-table td { padding:6px 10px; border:1px solid #e5e7eb; }
.dark .kv-table td { border-color:#374151; }
.kv-table td:first-child { font-weight:600; width:55%; }

/* Data tables */
.rpt-table { width:100%; border-collapse:collapse; font-size:.78rem; margin-top:4px; }
.rpt-table th {
    background:#a91515; color:#fff;
    padding:7px 8px; text-align:left;
    font-size:.72rem; text-transform:uppercase; letter-spacing:.05em;
}
.rpt-table td { padding:6px 8px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
.dark .rpt-table td { border-color:#374151; }
.rpt-table tr:nth-child(even) td { background:#f9fafb; }
.dark .rpt-table tr:nth-child(even) td { background:#1a2236; }
.rpt-table tr:hover td { background:#fff3f3; }
.dark .rpt-table tr:hover td { background:#2d1a1a; }

.badge {
    display:inline-block; padding:1px 8px; border-radius:999px;
    font-size:.7rem; font-weight:600;
}
.badge-green  { background:#d1fae5; color:#065f46; }
.badge-red    { background:#fee2e2; color:#991b1b; }
.badge-yellow { background:#fef9c3; color:#854d0e; }
.badge-purple { background:#ede9fe; color:#5b21b6; }
.badge-gray   { background:#f3f4f6; color:#374151; }
.dark .badge-green  { background:#064e3b; color:#6ee7b7; }
.dark .badge-red    { background:#7f1d1d; color:#fca5a5; }
.dark .badge-yellow { background:#713f12; color:#fde68a; }
.dark .badge-purple { background:#3b0764; color:#c4b5fd; }
.dark .badge-gray   { background:#374151; color:#d1d5db; }

.rpt-empty { color:#9ca3af; font-style:italic; padding:10px 0; font-size:.82rem; }

/* Sticky toolbar */
#report-toolbar {
    position:sticky; top:0; z-index:40;
    background:rgba(255,255,255,.95);
    backdrop-filter:blur(6px);
    border-bottom:1px solid #e5e7eb;
    padding:10px 0;
    margin-bottom:24px;
}
.dark #report-toolbar {
    background:rgba(17,24,39,.95);
    border-color:#374151;
}
</style>

<!-- ── Sticky toolbar ── -->
<div id="report-toolbar">
    <div class="max-w-[960px] mx-auto px-4 flex flex-col sm:flex-row sm:items-center gap-3">
        <div>
            <h2 class="text-xl font-bold text-gray-800 dark:text-white leading-tight">Annual Reports</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Full document preview of library data</p>
        </div>
        <form method="GET" action="reports.php" class="flex items-center gap-2 sm:ml-auto">
            <label for="year" class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">Report Year</label>
            <select id="year" name="year" onchange="this.form.submit()"
                    class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 text-sm
                           focus:ring-2 focus:ring-[#a91515] dark:bg-gray-700 dark:text-white">
                <?php for ($y = $currentYear; $y >= $minYear; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $selectedYear === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <a href="reports.php?year=<?php echo $selectedYear; ?>&download=pdf"
               class="flex items-center gap-1.5 bg-[#a91515] hover:bg-[#8f1212] text-white px-4 py-1.5 rounded-lg text-sm font-medium transition">
                <i class="fas fa-file-pdf"></i> Download PDF
            </a>
        </form>
    </div>
</div>

<!-- ── Document preview ── -->
<div class="report-paper">

    <!-- Cover -->
    <div class="report-cover">
        <?php
        $logoPath = getReportLogoPath();
        if ($logoPath): ?>
            <img src="<?php
                $root = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']),'/');
                $abs  = str_replace('\\','/',$logoPath);
                echo str_replace($root,'',$abs);
            ?>" alt="Logo" class="logo mx-auto">
        <?php endif; ?>
        <h1>EVSU Book Borrowing System</h1>
        <h2>Annual Report &mdash; <?php echo $selectedYear; ?></h2>
        <p>Generated: <?php echo $preview['generated_at']; ?></p>
    </div>

    <!-- 1. Executive Summary -->
    <div class="report-section" id="sec-summary">
        <div class="report-section-title">1. Executive Summary</div>
        <table class="kv-table">
            <tbody>
                <?php
                $kvRows = [
                    'Total Books (catalog)'                          => $summary['total_books'],
                    'Books Added in ' . $selectedYear               => $summary['books_added'],
                    'Total Members'                                  => $summary['total_members'],
                    'Members Registered in ' . $selectedYear        => $summary['members_added'],
                    'Total Borrow Transactions'                      => $summary['total_borrows'],
                    'Total Returns'                                  => $summary['total_returns'],
                    'Overdue Records'                                => $summary['overdue_count'],
                    'Penalties Assessed (PHP)'                       => '₱ ' . number_format($summary['penalty_total'], 2),
                    'Notifications Logged'                           => $summary['notifications_sent'],
                ];
                foreach ($kvRows as $label => $val): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($label); ?></td>
                        <td><?php echo htmlspecialchars($val); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 2. Book Catalog -->
    <div class="report-section" id="sec-books">
        <div class="report-section-title">2. Book Catalog
            <span class="text-xs font-normal text-gray-400 ml-2">(<?php echo count($preview['books']); ?> titles)</span>
        </div>
        <?php if (count($preview['books']) > 0): ?>
        <div class="overflow-x-auto">
            <table class="rpt-table">
                <thead>
                    <tr>
                        <th>#</th><th>Title</th><th>Author</th><th>Barcode</th>
                        <th>Category</th><th>Status</th><th>Stock</th><th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['books'] as $i => $row):
                        $sc = match(true) {
                            $row['status'] === 'Available'         => 'badge-green',
                            $row['status'] === 'Borrowed'          => 'badge-yellow',
                            $row['status'] === 'Overdue'           => 'badge-red',
                            $row['status'] === 'Needs Replacement' => 'badge-purple',
                            default                                => 'badge-gray'
                        };
                    ?>
                    <tr>
                        <td><?php echo $i+1; ?></td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['author']); ?></td>
                        <td class="font-mono text-xs"><?php echo htmlspecialchars($row['barcode']); ?></td>
                        <td><?php echo htmlspecialchars($row['category'] ?? '-'); ?></td>
                        <td><span class="badge <?php echo $sc; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['stock'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($row['added'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="rpt-empty">No books found.</p>
        <?php endif; ?>
    </div>

    <!-- 3. Members -->
    <div class="report-section" id="sec-members">
        <div class="report-section-title">3. Members
            <span class="text-xs font-normal text-gray-400 ml-2">(<?php echo count($preview['members']); ?> registered)</span>
        </div>
        <?php if (count($preview['members']) > 0): ?>
        <div class="overflow-x-auto">
            <table class="rpt-table">
                <thead>
                    <tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Barcode</th><th>Type</th><th>Joined</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['members'] as $i => $row): ?>
                    <tr>
                        <td><?php echo $i+1; ?></td>
                        <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                        <td class="text-xs"><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                        <td class="font-mono text-xs"><?php echo htmlspecialchars($row['barcode']); ?></td>
                        <td><?php echo htmlspecialchars($row['membership_type'] ?? 'Regular'); ?></td>
                        <td><?php echo htmlspecialchars($row['joined']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="rpt-empty">No members found.</p>
        <?php endif; ?>
    </div>

    <!-- 4. Transactions -->
    <div class="report-section" id="sec-transactions">
        <div class="report-section-title">4. Transactions
            <span class="text-xs font-normal text-gray-400 ml-2">(<?php echo count($preview['transactions']); ?> borrow records)</span>
        </div>
        <?php if (count($preview['transactions']) > 0): ?>
        <div class="overflow-x-auto">
            <table class="rpt-table">
                <thead>
                    <tr><th>ID</th><th>Book</th><th>Member</th><th>Borrowed</th><th>Due</th><th>Returned</th><th>Status</th><th>Fee</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['transactions'] as $row):
                        $sc = match(true) {
                            $row['status'] === 'Returned'          => 'badge-green',
                            $row['status'] === 'Borrowed'          => 'badge-yellow',
                            $row['status'] === 'Overdue'           => 'badge-red',
                            $row['status'] === 'Needs Replacement' => 'badge-purple',
                            default                                => 'badge-gray'
                        };
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                        <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['borrow_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['return_date'] ?? '-'); ?></td>
                        <td><span class="badge <?php echo $sc; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td>₱<?php echo number_format((float)$row['payment_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="rpt-empty">No transactions for <?php echo $selectedYear; ?>.</p>
        <?php endif; ?>
    </div>

    <!-- 5. Penalties -->
    <div class="report-section" id="sec-penalties">
        <div class="report-section-title">5. Penalties
            <span class="text-xs font-normal text-gray-400 ml-2">(<?php echo count($preview['penalties']); ?> records)</span>
        </div>
        <?php if (count($preview['penalties']) > 0): ?>
        <div class="overflow-x-auto">
            <table class="rpt-table">
                <thead>
                    <tr><th>ID</th><th>Book</th><th>Member</th><th>Type</th><th>Amount</th><th>Payment Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['penalties'] as $row):
                        $paid = stripos($row['payment_status'], 'paid') !== false;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                        <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['penalty_type'] ?? '-'); ?></td>
                        <td class="font-semibold text-red-700">₱<?php echo number_format((float)$row['penalty_amount'], 2); ?></td>
                        <td><span class="badge <?php echo $paid ? 'badge-green' : 'badge-red'; ?>"><?php echo htmlspecialchars($row['payment_status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="rpt-empty">No penalties for <?php echo $selectedYear; ?>.</p>
        <?php endif; ?>
    </div>

    <!-- 6. Overdue Activity -->
    <div class="report-section" id="sec-overdue">
        <div class="report-section-title">6. Overdue Activity
            <span class="text-xs font-normal text-gray-400 ml-2">(<?php echo count($preview['overdue']); ?> records)</span>
        </div>
        <?php if (count($preview['overdue']) > 0): ?>
        <div class="overflow-x-auto">
            <table class="rpt-table">
                <thead>
                    <tr><th>ID</th><th>Book</th><th>Member</th><th>Due Date</th><th>Status</th><th>Penalty</th><th>Payment</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['overdue'] as $row):
                        $paid = stripos($row['payment_status'] ?? '', 'paid') !== false;
                        $sc   = match(true) {
                            $row['status'] === 'Needs Replacement' => 'badge-purple',
                            $row['status'] === 'Overdue'           => 'badge-red',
                            default                                => 'badge-gray'
                        };
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['book_title']); ?></td>
                        <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                        <td><span class="badge <?php echo $sc; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td class="font-semibold text-red-700">₱<?php echo number_format((float)($row['penalty_amount'] ?? 0), 2); ?></td>
                        <td><span class="badge <?php echo $paid ? 'badge-green' : 'badge-red'; ?>"><?php echo htmlspecialchars($row['payment_status'] ?? '-'); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="rpt-empty">No overdue activity for <?php echo $selectedYear; ?>.</p>
        <?php endif; ?>
    </div>

    <!-- 7. Notifications -->
    <div class="report-section" id="sec-notifications">
        <div class="report-section-title">7. Notifications
            <span class="text-xs font-normal text-gray-400 ml-2">(<?php echo $summary['notifications_sent']; ?> logged — showing up to 100)</span>
        </div>
        <?php if (count($preview['notifications']) > 0): ?>
        <div class="overflow-x-auto">
            <table class="rpt-table">
                <thead>
                    <tr><th>Date</th><th>Type</th><th>Message Preview</th><th>Sent</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($preview['notifications'] as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['sent_date']); ?></td>
                        <td><span class="badge badge-gray"><?php echo htmlspecialchars($row['type'] ?: 'General'); ?></span></td>
                        <td class="text-xs"><?php echo htmlspecialchars($row['message_preview']); ?>&hellip;</td>
                        <td>
                            <span class="badge <?php echo $row['is_sent'] ? 'badge-green' : 'badge-red'; ?>">
                                <?php echo $row['is_sent'] ? 'Yes' : 'No'; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="rpt-empty">No notifications logged for <?php echo $selectedYear; ?>.</p>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="text-center text-xs text-gray-400 border-t border-gray-200 dark:border-gray-700 pt-6 mt-4">
        End of Annual Report &mdash; EVSU Book Borrowing System &copy; <?php echo $selectedYear; ?>
    </div>
</div>

<!-- Back to top -->
<button id="back-to-top" onclick="window.scrollTo({top:0,behavior:'smooth'})"
    class="fixed bottom-6 right-6 z-50 bg-[#a91515] hover:bg-[#8f1212] text-white w-10 h-10 rounded-full
           shadow-lg flex items-center justify-center transition opacity-0 pointer-events-none"
    title="Back to top">
    <i class="fas fa-arrow-up text-sm"></i>
</button>

<script>
// Show/hide back-to-top
window.addEventListener('scroll', () => {
    const btn = document.getElementById('back-to-top');
    if (window.scrollY > 300) {
        btn.classList.remove('opacity-0','pointer-events-none');
    } else {
        btn.classList.add('opacity-0','pointer-events-none');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
