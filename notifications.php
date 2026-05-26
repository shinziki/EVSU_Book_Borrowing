<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($action === 'delete' && $id > 0) {
        if (deleteNotification($id)) {
            setFlashMessage('Notification deleted successfully', 'success');
        } else {
            setFlashMessage('Failed to delete notification', 'error');
        }
    header('Location: notifications.php');
    exit;
    } elseif ($action === 'delete_all') {
        $memberId = $_SESSION['admin_id']; // Current admin/staff member
        if (deleteAllMemberNotifications($memberId)) {
            setFlashMessage('All notifications deleted successfully', 'success');
        } else {
            setFlashMessage('Failed to delete notifications', 'error');
        }
    header('Location: notifications.php');
    exit;
    } elseif ($action === 'mark_read' && $id > 0) {
        if (markNotificationAsRead($id)) {
            setFlashMessage('Notification marked as read', 'success');
        } else {
            setFlashMessage('Failed to mark notification as read', 'error');
        }
        header('Location: notifications.php');
        exit;
    } elseif ($action === 'send_test_email') {
        $testEmail = $_POST['test_email'] ?? '';
        if (!empty($testEmail)) {
            $subject = "Test Email from Coffee Prince Library";
            $message = "This is a test email from Coffee Prince Library to verify email delivery is working correctly.\n\n";
            $message .= "Time sent: " . date('Y-m-d H:i:s') . "\n";
            $message .= "If you received this email, your email system is configured correctly.";
            
            $sent = sendEmailWithLogging($testEmail, $subject, $message);
            if ($sent) {
                setFlashMessage('Test email sent successfully. Please check your inbox.', 'success');
            } else {
                setFlashMessage('Failed to send test email. Please check server mail configuration.', 'error');
            }
    header('Location: notifications.php');
    exit;
        }
    }
}

// Get all notifications sorted by date (most recent first)
try {
$stmt = $pdo->query("
        SELECT n.id, n.message, n.created_at, n.type, n.is_sent, 
               IFNULL(n.is_read, 0) as is_read,
               m.fullname as member_name, m.barcode as member_barcode,
               t.borrow_date, t.return_date, t.due_date,
           b.title as book_title
    FROM notifications n
    LEFT JOIN members m ON n.member_id = m.id
    LEFT JOIN transactions t ON n.transaction_id = t.id
    LEFT JOIN books b ON t.book_id = b.id
    ORDER BY n.created_at DESC
");
$notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // If there's an error, try to run the add_column.php script to fix the database schema
    include_once 'add_column.php';
    
    // Try again after fixing the schema
    try {
        $stmt = $pdo->query("
            SELECT n.id, n.message, n.created_at, n.type, n.is_sent, 
                   IFNULL(n.is_read, 0) as is_read,
                   m.fullname as member_name, m.barcode as member_barcode,
                   t.borrow_date, t.return_date, t.due_date,
                   b.title as book_title
            FROM notifications n
            LEFT JOIN members m ON n.member_id = m.id
            LEFT JOIN transactions t ON n.transaction_id = t.id
            LEFT JOIN books b ON t.book_id = b.id
            ORDER BY n.created_at DESC
        ");
        $notifications = $stmt->fetchAll();
    } catch (PDOException $e) {
        // If it still fails, show an error message
        $notifications = [];
        setFlashMessage('Database error: ' . $e->getMessage(), 'error');
    }
}

// Include header
include 'includes/header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Notifications</h2>
        <p class="text-gray-600 dark:text-gray-400">Manage system notifications and member communication</p>
    </div>
    <div class="flex space-x-3">
        <button type="button" onclick="showTestEmailModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-envelope mr-2"></i> Send Test Email
        </button>
        <a href="notifications.php?action=delete_all" onclick="return confirm('Are you sure you want to delete all notifications?')" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-trash-alt mr-2"></i> Clear All
        </a>
    </div>
</div>

<div class="grid grid-cols-1 gap-6">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Notification Log</h3>
        
        <?php if (count($notifications) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-700 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 rounded-tl-lg">Type</th>
                            <th class="px-4 py-3">Member</th>
                            <th class="px-4 py-3">Book</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 rounded-tr-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $notification): ?>
                            <tr class="border-b dark:border-gray-700 <?php echo $notification['is_read'] ? 'bg-gray-50 dark:bg-gray-800' : 'bg-white dark:bg-gray-900'; ?>">
                                <td class="px-4 py-3">
                                    <?php
                                        $typeIcon = 'info-circle';
                                        $typeClass = 'text-blue-500';
                                        
                                        if ($notification['type'] === 'Borrow Confirmation') {
                                            $typeIcon = 'book';
                                            $typeClass = 'text-green-500';
                                        } elseif ($notification['type'] === 'Return Confirmation') {
                                            $typeIcon = 'undo';
                                            $typeClass = 'text-purple-500';
                                        } elseif ($notification['type'] === 'Due Soon') {
                                            $typeIcon = 'clock';
                                            $typeClass = 'text-yellow-500';
                                        } elseif ($notification['type'] === 'Overdue') {
                                            $typeIcon = 'exclamation-triangle';
                                            $typeClass = 'text-red-500';
                                        }
                                    ?>
                                    <span class="flex items-center">
                                        <i class="fas fa-<?php echo $typeIcon; ?> <?php echo $typeClass; ?> mr-2"></i>
                                        <?php echo htmlspecialchars($notification['type']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php echo htmlspecialchars($notification['member_name']); ?>
                                    <?php if (!empty($notification['member_barcode'])): ?>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($notification['member_barcode']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php echo !empty($notification['book_title']) ? htmlspecialchars($notification['book_title']) : 'N/A'; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($notification['is_sent']): ?>
                                        <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            <i class="fas fa-check mr-1"></i> Sent
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            <i class="fas fa-times mr-1"></i> Failed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-2">
                                        <button 
                                            onclick="showNotificationDetails('<?php echo addslashes(htmlspecialchars($notification['message'])); ?>')" 
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                            title="View Details"
                                        >
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (!$notification['is_read']): ?>
                                            <a 
                                                href="notifications.php?action=mark_read&id=<?php echo $notification['id']; ?>" 
                                                class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                title="Mark as Read"
                                            >
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a 
                                            href="notifications.php?action=delete&id=<?php echo $notification['id']; ?>" 
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                            title="Delete"
                                            onclick="return confirm('Are you sure you want to delete this notification?')"
                                        >
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-bell text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-500 dark:text-gray-400">No notifications found</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Email Notifications</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h4 class="font-medium mb-2">PHPMailer Status</h4>
                <?php
                // Check PHPMailer status
                if (file_exists('vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
                    echo '<div class="rounded-lg bg-green-100 dark:bg-green-900 p-3 mb-6">';
                    echo '<div class="flex items-center">';
                    echo '<i class="fas fa-check-circle text-green-500 mr-2"></i>';
                    echo '<span class="font-medium text-green-700 dark:text-green-300">PHPMailer Status</span>';
                    echo '</div>';
                    echo '<p class="text-green-700 dark:text-green-300 text-sm ml-6">PHPMailer is installed and ready to use!</p>';
                    echo '</div>';
                } else {
                    echo '<div class="rounded-lg bg-yellow-100 dark:bg-yellow-900 p-3 mb-6">';
                    echo '<div class="flex items-center">';
                    echo '<i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>';
                    echo '<span class="font-medium text-yellow-700 dark:text-yellow-300">PHPMailer Status</span>';
                    echo '</div>';
                    echo '<p class="text-yellow-700 dark:text-yellow-300 text-sm ml-6">PHPMailer is not installed. <a href="download_phpmailer.php" class="underline">Install it now</a> for better email delivery.</p>';
                    echo '</div>';
                }
                ?>
            </div>
            
            <div>
                <h4 class="font-medium mb-2">File-Based Email Backup</h4>
                <?php
                // Show file-based email backup
                if (is_dir('emails')) {
                    echo '<div class="rounded-lg bg-blue-100 dark:bg-blue-900 p-3 mb-4">';
                    echo '<div class="flex items-center mb-1">';
                    echo '<i class="fas fa-envelope text-blue-500 mr-2"></i>';
                    echo '<span class="font-medium text-blue-700 dark:text-blue-300">File-Based Email Backup</span>';
                    echo '</div>';
                    
                    // Get email files
                    $emailFiles = glob('emails/*.txt');
                    
                    // Filter out OTP-related files
                    $emailFiles = array_filter($emailFiles, function($file) {
                        return strpos($file, 'otp_') === false;
                    });
                    
                    $emailCount = count($emailFiles);
                    
                    echo '<div class="flex items-center justify-between mb-2">';
                    echo '<span class="text-blue-800 dark:text-blue-200">Found ' . $emailCount . ' saved email' . ($emailCount != 1 ? 's' : '') . '</span>';
                    
                    if ($emailCount > 0) {
                        echo '<a href="#" onclick="toggleEmailList(); return false;" class="text-blue-600 dark:text-blue-300 hover:underline">';
                        echo '<span id="emailToggleText">Show</span> emails';
                        echo '</a>';
                    }
                    
                    echo '</div>';
                    
                    if ($emailCount > 0) {
                        echo '<div id="emailList" class="hidden mt-2 space-y-2">';
                        
                        // Sort email files by newest first
                        usort($emailFiles, function($a, $b) {
                            return filemtime($b) - filemtime($a);
                        });
                        
                        // Show most recent 5 emails
                        $recentEmails = array_slice($emailFiles, 0, 5);
                        
                        foreach ($recentEmails as $emailFile) {
                            $emailContent = file_get_contents($emailFile);
                            $fileName = basename($emailFile);
                            
                            // Extract basic info from email file
                            preg_match('/To: (.*?)\n/', $emailContent, $toMatch);
                            preg_match('/Subject: (.*?)\n/', $emailContent, $subjectMatch);
                            preg_match('/Date: (.*?)\n/', $emailContent, $dateMatch);
                            
                            $to = isset($toMatch[1]) ? $toMatch[1] : 'Unknown';
                            $subject = isset($subjectMatch[1]) ? $subjectMatch[1] : 'No subject';
                            $date = isset($dateMatch[1]) ? $dateMatch[1] : date('Y-m-d H:i:s', filemtime($emailFile));
                            
                            echo '<div class="bg-white dark:bg-gray-700 p-2 rounded shadow-sm">';
                            echo '<div class="flex justify-between items-center">';
                            echo '<span class="font-medium">' . htmlspecialchars($subject) . '</span>';
                            echo '<span class="text-xs text-gray-500 dark:text-gray-400">' . htmlspecialchars($date) . '</span>';
                            echo '</div>';
                            echo '<div class="text-sm text-gray-600 dark:text-gray-300">To: ' . htmlspecialchars($to) . '</div>';
                            echo '<div class="mt-1 flex justify-end">';
                            echo '<button onclick="viewEmail(\'' . addslashes(htmlspecialchars($emailContent)) . '\')" ';
                            echo 'class="text-xs text-blue-600 dark:text-blue-400 hover:underline">View Content</button>';
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        if ($emailCount > 5) {
                            echo '<div class="text-center text-sm text-gray-500 dark:text-gray-400">';
                            echo 'Showing 5 of ' . $emailCount . ' emails';
                            echo '</div>';
                        }
                        
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        
        <div class="mt-6">
            <h4 class="font-medium mb-2">Email Logs</h4>
            <?php
                $logFile = "logs/email_log.txt";
                if (file_exists($logFile)) {
                    $logContents = file_get_contents($logFile);
                    $lines = explode("\n", $logContents);
                    $filteredLines = array_filter($lines, function($line) {
                        // Filter out OTP-related log entries
                        return strpos($line, 'Login Verification Code') === false && 
                               strpos($line, 'OTP') === false;
                    });
                    $lastEntries = array_slice($filteredLines, -10); // Get last 10 entries
                    
                    echo '<div class="bg-gray-100 dark:bg-gray-700 p-3 rounded-lg overflow-auto max-h-40 text-sm">';
                    foreach ($lastEntries as $entry) {
                        if (!empty(trim($entry))) {
                            if (strpos($entry, 'SUCCESS') !== false) {
                                echo '<div class="text-green-700 dark:text-green-400">' . htmlspecialchars($entry) . '</div>';
                            } else if (strpos($entry, 'FAILED') !== false) {
                                echo '<div class="text-red-700 dark:text-red-400">' . htmlspecialchars($entry) . '</div>';
                            } else {
                                echo '<div class="text-gray-800 dark:text-gray-300">' . htmlspecialchars($entry) . '</div>';
                            }
                        }
                    }
                    echo '</div>';
                } else {
                    echo '<p class="mt-2 text-yellow-600 dark:text-yellow-400">No log file found yet</p>';
                }
            ?>
        </div>
    </div>
    
<!-- Notification Details Modal -->
<div id="notification-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="absolute inset-0 bg-black opacity-50"></div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg z-10 w-full max-w-2xl">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Notification Contents</h3>
            <button onclick="closeModal('notification-modal')" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
            </div>
        <div class="p-5">
            <pre id="notification-content" class="whitespace-pre-wrap bg-gray-50 dark:bg-gray-700 p-4 rounded-lg text-gray-800 dark:text-gray-200 font-mono text-sm overflow-auto max-h-96"></pre>
            </div>
        <div class="p-5 border-t border-gray-200 dark:border-gray-700 flex justify-end">
            <button onclick="closeModal('notification-modal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div id="test-email-modal" class="fixed inset-0 flex items-center justify-center z-50 hidden">
    <div class="absolute inset-0 bg-black opacity-50"></div>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg z-10 w-full max-w-md">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Send Test Email</h3>
            <button onclick="closeModal('test-email-modal')" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form action="notifications.php?action=send_test_email" method="POST">
            <div class="p-5">
                <div class="mb-4">
                    <label for="test_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Address</label>
                    <input type="email" id="test_email" name="test_email" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" required>
                </div>
            </div>
            <div class="p-5 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('test-email-modal')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg">
                    Cancel
                </button>
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-paper-plane mr-2"></i> Send
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showNotificationDetails(message) {
    document.getElementById('notification-content').textContent = message.replace(/\\n/g, '\n');
    document.getElementById('notification-modal').classList.remove('hidden');
}

function showTestEmailModal() {
    document.getElementById('test-email-modal').classList.remove('hidden');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

// Toggle email list display
function toggleEmailList() {
    const emailList = document.getElementById('emailList');
    const toggleText = document.getElementById('emailToggleText');
    
    if (emailList.classList.contains('hidden')) {
        emailList.classList.remove('hidden');
        toggleText.textContent = 'Hide';
    } else {
        emailList.classList.add('hidden');
        toggleText.textContent = 'Show';
    }
}

// View email content in modal
function viewEmail(content) {
    document.getElementById('notification-content').textContent = content;
    document.getElementById('notification-modal').classList.remove('hidden');
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const notificationModal = document.getElementById('notification-modal');
    const testEmailModal = document.getElementById('test-email-modal');
    
    if (event.target === notificationModal) {
        notificationModal.classList.add('hidden');
    }
    
    if (event.target === testEmailModal) {
        testEmailModal.classList.add('hidden');
    }
});
</script>

<?php
// Create logs directory if it doesn't exist
if (!is_dir('logs')) {
    mkdir('logs', 0755);
}

// Include footer
include 'includes/footer.php';
?> 