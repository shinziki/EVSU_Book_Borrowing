<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

// Check if we have a receipt in session
if (!isset($_SESSION['borrow_receipt'])) {
    header('Location: borrow.php');
    exit;
}

$receipt = $_SESSION['borrow_receipt'];

// Include header
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Borrowing Receipt</h2>
        <p class="text-gray-600 dark:text-gray-400">Transaction #<?php echo htmlspecialchars($receipt['transaction_id']); ?></p>
    </div>
    <div>
        <button onclick="printReceipt()" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-print mr-2"></i> Print Receipt
        </button>
    </div>
</div>

<?php if (isset($receipt['email_sent'])): ?>
<div class="mb-4 p-3 <?php echo $receipt['email_sent'] ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-yellow-100 border border-yellow-400 text-yellow-700'; ?> rounded">
    <?php if ($receipt['email_sent']): ?>
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <p>A receipt has been emailed to <strong><?php echo htmlspecialchars($receipt['member_email']); ?></strong>. Please check your inbox or spam folder.</p>
        </div>
    <?php else: ?>
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <p>The system attempted to send a receipt email but encountered issues. A backup copy has been saved.</p>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <!-- Receipt content for printing -->
        <div id="receipt-content" class="max-w-2xl mx-auto">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Coffee Prince Library</h1>
                <p class="text-gray-600 dark:text-gray-400">Book Borrowing Receipt</p>
                <p class="text-sm text-gray-500 dark:text-gray-500">Transaction #<?php echo htmlspecialchars($receipt['transaction_id']); ?></p>
            </div>

            <!-- Transaction Barcode -->
            <div class="mb-6 text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Scan this barcode when returning the book</p>
                <svg class="barcode-transaction mx-auto" jsbarcode-format="CODE128" jsbarcode-value="TRX<?php echo htmlspecialchars($receipt['transaction_id']); ?>" jsbarcode-textmargin="0" jsbarcode-fontoptions="bold" jsbarcode-height="60"></svg>
            </div>

            <div class="border-t border-b border-gray-200 dark:border-gray-700 py-4 mb-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Date Borrowed:</p>
                        <p class="font-medium text-gray-800 dark:text-white">
                            <?php echo date('F j, Y, g:i A', strtotime($receipt['borrow_date'])); ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Due Date:</p>
                        <p class="font-medium text-gray-800 dark:text-white">
                            <?php echo date('F j, Y, g:i A', strtotime($receipt['due_date'])); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Book Details</h3>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p><span class="font-medium">Title:</span> <?php echo htmlspecialchars($receipt['book_title']); ?></p>
                    <p><span class="font-medium">Barcode:</span> <?php echo htmlspecialchars($receipt['book_barcode']); ?></p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Borrower Details</h3>
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <p><span class="font-medium">Name:</span> <?php echo htmlspecialchars($receipt['member_name']); ?></p>
                    <p><span class="font-medium">Member ID:</span> <?php echo htmlspecialchars($receipt['member_barcode']); ?></p>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Important Notes</h3>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    <li>Please return the book on or before the due date.</li>
                    <li>Late returns may incur a penalty fee.</li>
                    <li>Please handle library materials with care.</li>
                    <li>For any inquiries, please contact the library staff.</li>
                    <li><strong>Important:</strong> Please bring this receipt when returning the book.</li>
                </ul>
            </div>

            <div class="text-center text-sm text-gray-500 dark:text-gray-400 mt-8 pt-4 border-t border-gray-200 dark:border-gray-700">
                <p>Thank you for using Coffee Prince Library!</p>
                <p>This receipt was generated on <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        </div>

        <div class="mt-6 flex justify-center">
            <a href="borrow.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg">
                Borrow Another Book
            </a>
        </div>
    </div>
</div>

<script>
function printReceipt() {
    const printContents = document.getElementById('receipt-content').innerHTML;
    const originalContents = document.body.innerHTML;
    
    // Create a print-friendly version
    const printStyles = `
        <style>
            body { font-family: Arial, sans-serif; color: #000; background: #fff; }
            @page { margin: 0.5cm; }
            .bg-gray-50 { background-color: #f9fafb; padding: 15px; border-radius: 4px; margin-bottom: 10px; }
            h1, h3 { margin-bottom: 8px; }
            .mb-2 { margin-bottom: 8px; }
            .mb-4 { margin-bottom: 16px; }
            .mb-6 { margin-bottom: 24px; }
            .font-medium, .font-semibold, .font-bold { font-weight: bold; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 5px 0; }
            .border-b { border-bottom: 1px solid #e5e7eb; }
            .text-right { text-align: right; }
            .list-disc { list-style-type: disc; padding-left: 20px; }
            .text-center { text-align: center; }
            .text-sm { font-size: 0.875rem; }
            .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
            .border-t { border-top: 1px solid #e5e7eb; }
            .mt-8 { margin-top: 2rem; }
        </style>
    `;
    
    document.body.innerHTML = printStyles + printContents;
    window.print();
    document.body.innerHTML = originalContents;
}

// Initialize barcodes when page loads
document.addEventListener('DOMContentLoaded', function() {
    JsBarcode(".barcode-transaction").init();
});
</script>

<?php
// Include footer
include 'includes/footer.php';

// Optionally clear the receipt data from session if needed
// unset($_SESSION['borrow_receipt']);
?> 