<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensure admins.role column exists (admin | staff)
 */
function ensureAdminRoleColumn() {
    global $pdo;
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'role'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER email");
            $pdo->exec("UPDATE admins SET role = 'admin' WHERE role = '' OR role IS NULL");
        }
    } catch (PDOException $e) {
        error_log('ensureAdminRoleColumn: ' . $e->getMessage());
    }
}

/**
 * Ensure members.course column exists.
 */
function ensureMemberCourseColumn() {
    global $pdo;
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'course'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE members ADD COLUMN course VARCHAR(100) DEFAULT NULL AFTER address");
        }
    } catch (PDOException $e) {
        error_log('ensureMemberCourseColumn: ' . $e->getMessage());
    }
}

/**
 * Course/program options for member registration.
 */
/**
 * Ensure members.status column exists (active | inactive).
 */
function ensureMemberStatusColumn() {
    global $pdo;
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'status'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE members ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER course");
            $pdo->exec("UPDATE members SET status = 'active' WHERE status = '' OR status IS NULL");
        }
    } catch (PDOException $e) {
        error_log('ensureMemberStatusColumn: ' . $e->getMessage());
    }
}

/**
 * Ensure members.student_id column exists.
 */
function ensureMemberStudentIdColumn() {
    global $pdo;
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'student_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE members ADD COLUMN student_id VARCHAR(50) DEFAULT NULL AFTER course");
            $pdo->exec("UPDATE members SET student_id = barcode WHERE student_id IS NULL AND barcode IS NOT NULL AND barcode NOT LIKE 'MEM%'");
        }
    } catch (PDOException $e) {
        error_log('ensureMemberStudentIdColumn: ' . $e->getMessage());
    }
}

/**
 * Ensure transactions.due_date supports time (DATETIME).
 * Needed so due dates can be exactly 24 hours from borrow time.
 */
function ensureTransactionsDueDateDateTime() {
    global $pdo;
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'due_date'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$col || !isset($col['Type'])) {
            return;
        }

        $type = strtolower((string) $col['Type']);
        if (str_starts_with($type, 'date') && !str_starts_with($type, 'datetime')) {
            $pdo->exec("ALTER TABLE transactions MODIFY COLUMN due_date DATETIME NOT NULL");
        }
    } catch (PDOException $e) {
        error_log('ensureTransactionsDueDateDateTime: ' . $e->getMessage());
    }
}

function getMemberStatusOptions() {
    return [
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];
}

function isMemberActive($member) {
    ensureMemberStatusColumn();
    if (!$member || !is_array($member)) {
        return false;
    }
    return ($member['status'] ?? 'active') === 'active';
}

function getMemberCourseOptions() {
    return [
        'BSIT' => 'BSIT - Bachelor of Science in Information Technology',
        'BSCS' => 'BSCS - Bachelor of Science in Computer Science',
        'BSCE' => 'BSCE - Bachelor of Science in Civil Engineering',
        'BSEE' => 'BSEE - Bachelor of Science in Electrical Engineering',
        'BSME' => 'BSME - Bachelor of Science in Mechanical Engineering',
        'BSBA' => 'BSBA - Bachelor of Science in Business Administration',
        'BSED' => 'BSED - Bachelor of Science in Education',
        'BSHM' => 'BSHM - Bachelor of Science in Hospitality Management',
        'BSN' => 'BSN - Bachelor of Science in Nursing',
        'BSCrim' => 'BSCrim - Bachelor of Science in Criminology',
        'Other' => 'Other',
    ];
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

/**
 * Get current user role (admin or staff)
 */
function getUserRole() {
    if (!isLoggedIn()) {
        return null;
    }
    ensureAdminRoleColumn();
    if (!isset($_SESSION['admin_role'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT role FROM admins WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['admin_id']]);
            $row = $stmt->fetch();
            $_SESSION['admin_role'] = $row['role'] ?? 'admin';
        } catch (PDOException $e) {
            $_SESSION['admin_role'] = 'admin';
        }
    }
    return $_SESSION['admin_role'];
}

function isAdmin() {
    return isLoggedIn() && getUserRole() === 'admin';
}

function isStaff() {
    return isLoggedIn() && getUserRole() === 'staff';
}

/**
 * Ensure staff_permissions table exists
 */
function ensureStaffPermissionsTable() {
    global $pdo;
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS staff_permissions (
                id INT(11) NOT NULL AUTO_INCREMENT,
                admin_id INT(11) NOT NULL,
                permission_key VARCHAR(64) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY unique_staff_permission (admin_id, permission_key),
                KEY admin_id (admin_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    } catch (PDOException $e) {
        error_log('ensureStaffPermissionsTable: ' . $e->getMessage());
    }
}

/**
 * Configurable staff permission definitions
 */
function getStaffPermissionDefinitions() {
    return [
        [
            'group' => 'Dashboard',
            'icon' => 'chart-bar',
            'permissions' => [
                ['key' => 'dashboard.view', 'label' => 'View dashboard & statistics', 'default' => true],
            ],
        ],
        [
            'group' => 'Books',
            'icon' => 'book',
            'permissions' => [
                ['key' => 'books.view', 'label' => 'View & search books catalog', 'default' => true],
                ['key' => 'books.manage', 'label' => 'Add, edit, delete & print book barcodes', 'default' => false],
            ],
        ],
        [
            'group' => 'Members',
            'icon' => 'users',
            'permissions' => [
                ['key' => 'members.view', 'label' => 'View & search members', 'default' => true],
                ['key' => 'members.add', 'label' => 'Add new members', 'default' => true],
                ['key' => 'members.edit', 'label' => 'Edit, delete & print member barcodes', 'default' => false],
            ],
        ],
        [
            'group' => 'Borrow & Return',
            'icon' => 'hand-holding',
            'permissions' => [
                ['key' => 'borrow.process', 'label' => 'Process book borrowing', 'default' => true],
                ['key' => 'return.process', 'label' => 'Process book returns', 'default' => true],
                ['key' => 'transactions.view', 'label' => 'View borrow & return history', 'default' => true],
            ],
        ],
        [
            'group' => 'Penalties & Overdue',
            'icon' => 'exclamation-triangle',
            'permissions' => [
                ['key' => 'penalties.view', 'label' => 'Book penalties page', 'default' => true],
                ['key' => 'penalties.record', 'label' => 'Penalties record', 'default' => true],
                ['key' => 'penalties.mark_paid', 'label' => 'Mark penalties as paid', 'default' => true],
                ['key' => 'overdue.view', 'label' => 'View overdue books list', 'default' => true],
            ],
        ],
        [
            'group' => 'Notifications',
            'icon' => 'bell',
            'permissions' => [
                ['key' => 'notifications.view', 'label' => 'View notification center & bell', 'default' => true],
                ['key' => 'notifications.send', 'label' => 'Send notifications to members', 'default' => true],
            ],
        ],
        [
            'group' => 'Account',
            'icon' => 'cog',
            'permissions' => [
                ['key' => 'settings.profile', 'label' => 'Update own profile & password', 'default' => true],
            ],
        ],
    ];
}

function getAllStaffPermissionKeys() {
    $keys = [];
    foreach (getStaffPermissionDefinitions() as $group) {
        foreach ($group['permissions'] as $perm) {
            $keys[] = $perm['key'];
        }
    }
    return $keys;
}

function getDefaultStaffPermissionKeys() {
    $keys = [];
    foreach (getStaffPermissionDefinitions() as $group) {
        foreach ($group['permissions'] as $perm) {
            if (!empty($perm['default'])) {
                $keys[] = $perm['key'];
            }
        }
    }
    return $keys;
}

/**
 * Map pages to required permission key(s) — staff needs at least one if array
 */
function getPagePermissionMap() {
    return [
        'index.php' => 'dashboard.view',
        'books.php' => 'books.view',
        'borrow.php' => 'borrow.process',
        'return.php' => 'return.process',
        'borrow_receipt.php' => 'borrow.process',
        'transactions.php' => 'transactions.view',
        'members.php' => 'members.view',
        'settings.php' => 'settings.profile',
        'check_member_borrows.php' => 'borrow.process',
        'penalties.php' => 'penalties.view',
        'penalties_record.php' => 'penalties.record',
        'mark_penalty_paid.php' => 'penalties.mark_paid',
        'overdue.php' => 'overdue.view',
        'notifications.php' => 'notifications.view',
        'send_individual_notification.php' => 'notifications.send',
        'logout.php' => null,
    ];
}

function getStaffPermissions($staffId = null) {
    global $pdo;
    if ($staffId === null && isLoggedIn()) {
        $staffId = $_SESSION['admin_id'];
    }
    if (!$staffId) {
        return [];
    }

    static $cache = [];
    if (isset($cache[$staffId])) {
        return $cache[$staffId];
    }

    ensureStaffPermissionsTable();
    $stmt = $pdo->prepare("SELECT permission_key FROM staff_permissions WHERE admin_id = ?");
    $stmt->execute([$staffId]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($rows)) {
        $rows = getDefaultStaffPermissionKeys();
    }

    $cache[$staffId] = array_fill_keys($rows, true);
    return $cache[$staffId];
}

function clearStaffPermissionsCache($staffId = null) {
    // Bust static cache by using a global flag — reset on next request is fine;
    // also refresh session for current user
    if ($staffId && isset($_SESSION['admin_id']) && (int) $staffId === (int) $_SESSION['admin_id']) {
        unset($_SESSION['staff_permissions_loaded']);
    }
}

function saveStaffPermissions($staffId, array $permissionKeys) {
    global $pdo;
    ensureStaffPermissionsTable();

    $validKeys = getAllStaffPermissionKeys();
    $permissionKeys = array_values(array_intersect($permissionKeys, $validKeys));

    $pdo->prepare("DELETE FROM staff_permissions WHERE admin_id = ?")->execute([$staffId]);

    if (!empty($permissionKeys)) {
        $stmt = $pdo->prepare("INSERT INTO staff_permissions (admin_id, permission_key) VALUES (?, ?)");
        foreach ($permissionKeys as $key) {
            $stmt->execute([$staffId, $key]);
        }
    }

    clearStaffPermissionsCache($staffId);
}

function staffHasPermission($permissionKey, $staffId = null) {
    if (isAdmin()) {
        return true;
    }
    if (!isStaff()) {
        return false;
    }
    $permissions = getStaffPermissions($staffId);
    return isset($permissions[$permissionKey]);
}

function requireStaffPermission($permissionKey) {
    requireLogin();
    if (!staffHasPermission($permissionKey)) {
        setFlashMessage('You do not have permission to perform this action.', 'error');
        header('Location: ' . getDefaultLandingPage());
        exit;
    }
}

/**
 * Permission matrix for admin settings (role comparison UI)
 */
function getRolePermissionsMatrix() {
    $matrix = [];
    foreach (getStaffPermissionDefinitions() as $group) {
        $items = [];
        foreach ($group['permissions'] as $perm) {
            $items[] = [
                'label' => $perm['label'],
                'admin' => true,
                'staff' => !empty($perm['default']),
            ];
        }
        $matrix[] = [
            'group' => $group['group'],
            'icon' => $group['icon'],
            'items' => $items,
        ];
    }
    return $matrix;
}

/**
 * Render permission badge for role matrix
 */
function renderPermissionBadge($allowed) {
    if ($allowed === true) {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200"><i class="fas fa-check mr-1"></i> Allowed</span>';
    }
    if ($allowed === 'partial') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"><i class="fas fa-minus mr-1"></i> Limited</span>';
    }
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200"><i class="fas fa-times mr-1"></i> Not allowed</span>';
}

function canAccessPage($page = null) {
    if ($page === null) {
        $page = basename($_SERVER['PHP_SELF']);
    }
    if (!isLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }

    $pageMap = getPagePermissionMap();
    if (!isset($pageMap[$page])) {
        return false;
    }

    $required = $pageMap[$page];
    if ($required === null) {
        return true;
    }

    return staffHasPermission($required);
}

/**
 * Default page after login
 */
function getDefaultLandingPage() {
    if (isAdmin()) {
        return 'index.php';
    }
    if (isStaff()) {
        $priority = [
            'index.php' => 'dashboard.view',
            'borrow.php' => 'borrow.process',
            'return.php' => 'return.process',
            'members.php' => 'members.view',
            'books.php' => 'books.view',
            'transactions.php' => 'transactions.view',
            'settings.php' => 'settings.profile',
        ];
        foreach ($priority as $page => $permission) {
            if (staffHasPermission($permission)) {
                return $page;
            }
        }
    }
    return 'settings.php';
}

/**
 * Redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    ensureAdminRoleColumn();
    if (!canAccessPage()) {
        setFlashMessage('You do not have permission to access that page.', 'error');
        header('Location: ' . getDefaultLandingPage());
        exit;
    }
}

/**
 * Admin-only pages
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlashMessage('You do not have permission to access that page.', 'error');
        header('Location: ' . getDefaultLandingPage());
        exit;
    }
}

/**
 * Redirect if already logged in
 */
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header('Location: ' . getDefaultLandingPage());
        exit;
    }
}

/**
 * Display flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION['message'])) {
        echo '<div class="' . $_SESSION['message_type'] . ' p-4 mb-4 rounded-lg">';
        echo '<div class="flex items-center">';
        
        $icon = 'info-circle';
        if ($_SESSION['message_type'] === 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300') {
            $icon = 'check-circle';
        } else if ($_SESSION['message_type'] === 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300') {
            $icon = 'exclamation-circle';
        }
        
        echo '<i class="fas fa-' . $icon . ' mr-3"></i>';
        echo '<span>' . $_SESSION['message'] . '</span>';
        echo '</div></div>';
        
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

/**
 * Set a flash message
 */
function setFlashMessage($message, $type = 'success') {
    $_SESSION['message'] = $message;
    
    switch ($type) {
        case 'success':
            $_SESSION['message_type'] = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
            break;
        case 'error':
            $_SESSION['message_type'] = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
            break;
        default:
            $_SESSION['message_type'] = 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
    }
}

/**
 * Generate a new book barcode
 */
function generateBookBarcode() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(barcode, 4) AS UNSIGNED)) AS max_id FROM books WHERE barcode LIKE 'LIB%'");
    $result = $stmt->fetch();
    
    $nextId = 1;
    if ($result && $result['max_id']) {
        $nextId = $result['max_id'] + 1;
    }
    
    return 'LIB' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate a new member barcode
 */
function generateMemberBarcode() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(barcode, 4) AS UNSIGNED)) AS max_id FROM members WHERE barcode LIKE 'MEM%'");
    $result = $stmt->fetch();
    
    $nextId = 1;
    if ($result && $result['max_id']) {
        $nextId = $result['max_id'] + 1;
    }
    
    return 'MEM' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
}

/**
 * Get dashboard statistics
 */
function getDashboardStats() {
    global $pdo;
    
    $stats = [
        'total_books' => 0,
        'total_members' => 0,
        'borrowed_books' => 0,
        'overdue_books' => 0
    ];
    
    // Count total books
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM books");
    $result = $stmt->fetch();
    $stats['total_books'] = $result['count'];
    
    // Count total members
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM members");
    $result = $stmt->fetch();
    $stats['total_members'] = $result['count'];
    
    // Count borrowed books from transactions (more accurate than books table)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'Borrowed'");
    $result = $stmt->fetch();
    $stats['borrowed_books'] = $result['count'];
    
    // Count overdue books from transactions (more accurate than books table)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM transactions WHERE status = 'Overdue'");
    $result = $stmt->fetch();
    $stats['overdue_books'] = $result['count'];
    
    return $stats;
}

/**
 * Get recent activities for dashboard
 */
function getRecentActivities($limit = 3) {
    global $pdo;
    
    $activities = [];
    
    // Get recent transactions (both borrowing and returning)
    $stmt = $pdo->prepare("
        SELECT t.id, t.borrow_date, t.return_date, t.status,
               b.title as book_title, b.barcode as book_barcode, 
               m.fullname as member_name, m.barcode as member_barcode
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        JOIN members m ON t.member_id = m.id
        -- Sort strictly by the most recent borrow (not by return time)
        ORDER BY t.borrow_date DESC
        LIMIT :limit
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Check if a book is available
 */
function isBookAvailable($barcode) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT status FROM books WHERE barcode = :barcode");
    $stmt->bindParam(':barcode', $barcode);
    $stmt->execute();
    
    $result = $stmt->fetch();
    
    return ($result && $result['status'] === 'Available');
}

/**
 * Get book information by barcode
 */
function getBookByBarcode($barcode) {
    global $pdo;
    
    // First, check table structure
    $stmt = $pdo->query("DESCRIBE books");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    $hasQuantityField = in_array('quantity', $columns);
    $hasStockField = in_array('stock', $columns);
    $hasIsbnField = in_array('isbn', $columns);
    
    // Build query based on available columns
    if ($hasQuantityField && $hasStockField) {
        $selectFields = 'id, title, author, barcode, quantity, stock, status';
    } elseif ($hasQuantityField) {
        $selectFields = 'id, title, author, barcode, quantity, status';
    } elseif ($hasStockField) {
        $selectFields = 'id, title, author, barcode, stock, status';
    } else {
        $selectFields = 'id, title, author, barcode, status';
    }
    
    if ($hasIsbnField) {
        $selectFields .= ', isbn';
        $stmt = $pdo->prepare("SELECT {$selectFields} FROM books WHERE barcode = :barcode OR isbn = :isbn");
        $stmt->bindParam(':barcode', $barcode);
        $stmt->bindParam(':isbn', $barcode);
    } else {
        $stmt = $pdo->prepare("SELECT {$selectFields} FROM books WHERE barcode = :barcode");
        $stmt->bindParam(':barcode', $barcode);
    }
    
    $stmt->execute();
    $bookInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Normalize the data - if only stock exists, duplicate it as quantity for compatibility
    if ($bookInfo) {
        if (!$hasQuantityField && $hasStockField) {
            $bookInfo['quantity'] = $bookInfo['stock'];
        }
        // If only quantity exists, duplicate it as stock for compatibility
        if ($hasQuantityField && !$hasStockField) {
            $bookInfo['stock'] = $bookInfo['quantity'];
        }
        // If neither exists, set them both to a default value of 1
        if (!$hasQuantityField && !$hasStockField) {
            $bookInfo['quantity'] = 1;
            $bookInfo['stock'] = 1;
        }
    }
    
    return $bookInfo;
}

/**
 * Get member information by barcode
 */
function getMemberByBarcode($barcode) {
    global $pdo;
    ensureMemberStudentIdColumn();

    $stmt = $pdo->prepare("SELECT * FROM members WHERE barcode = :barcode OR student_id = :barcode");
    $stmt->bindParam(':barcode', $barcode);
    $stmt->execute();

    return $stmt->fetch();
}

/**
 * Check if member has reached borrowing limit (3 books)
 */
function memberHasReachedBorrowLimit($memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_borrows
        FROM transactions
        WHERE member_id = :member_id AND status IN ('Borrowed', 'Overdue', 'Needs Replacement')
    ");
    $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
    $stmt->execute();
    
    $result = $stmt->fetch();
    return ($result && $result['active_borrows'] >= 3);
}

/**
 * Get penalty settings
 */
function getPenaltySettings() {
    return [
        'borrowing_limit' => 3,
        'late_return_fee' => 25.00,
        'damaged_book_fee' => 1500.00,
        'lost_book_fee' => 2000.00
    ];
}

/**
 * Late return penalty (legacy borrows with a fee use 3x that fee; otherwise flat late fee).
 */
function calculateLateReturnPenalty(array $transaction) {
    $borrowFee = (float) ($transaction['payment_amount'] ?? 0);
    if ($borrowFee > 0) {
        return $borrowFee * 3;
    }
    $settings = getPenaltySettings();
    return (float) ($settings['late_return_fee'] ?? 25.00);
}

/**
 * Process a book borrowing transaction
 * 
 * @param string $bookBarcode Book barcode
 * @param string $memberBarcode Member barcode
 * @param int $days Number of days to borrow
 * @param float $paymentAmount Payment amount
 * @return bool True if successful
 */
function borrowBook($bookBarcode, $memberBarcode, $days = 1, $paymentAmount = 0.00) {
    global $pdo;
    
    try {
        // Create a log directory if it doesn't exist
        if (!is_dir(__DIR__ . '/../logs')) {
            mkdir(__DIR__ . '/../logs', 0755, true);
        }
        $logFile = __DIR__ . '/../logs/borrow_debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Starting borrowBook: book=$bookBarcode, member=$memberBarcode, days=$days\n", FILE_APPEND);
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get book and member details
        $book = getBookByBarcode($bookBarcode);
        $member = getMemberByBarcode($memberBarcode);
        
        if (!$book) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Book not found with barcode: $bookBarcode\n", FILE_APPEND);
            return false;
        }
        
        if (!$member) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Member not found with barcode: $memberBarcode\n", FILE_APPEND);
            return false;
        }

        if (!isMemberActive($member)) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Inactive member cannot borrow: ID=" . $member['id'] . "\n", FILE_APPEND);
            setFlashMessage('This member is inactive and cannot borrow books.', 'error');
            return false;
        }
        
        // Check if member has reached borrowing limit (3 books)
        if (memberHasReachedBorrowLimit($member['id'])) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Member has reached borrowing limit (3 books): ID=" . $member['id'] . ", Name=" . $member['fullname'] . "\n", FILE_APPEND);
            setFlashMessage('Member has reached the maximum borrowing limit of 3 books.', 'error');
            return false;
        }
        
        // Log book and member details
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Book found: ID=" . $book['id'] . ", Title=" . $book['title'] . ", Status=" . $book['status'] . ", Quantity=" . ($book['quantity'] ?? 'N/A') . "\n", FILE_APPEND);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Member found: ID=" . $member['id'] . ", Name=" . $member['fullname'] . "\n", FILE_APPEND);
        
        // Check column names in books table
        $stmt = $pdo->query("DESCRIBE books");
        $bookColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bookColumns[] = $row['Field'];
        }
        
        $hasQuantityField = in_array('quantity', $bookColumns);
        $hasStockField = in_array('stock', $bookColumns);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Table columns: hasQuantity=" . ($hasQuantityField ? 'Yes' : 'No') . ", hasStock=" . ($hasStockField ? 'Yes' : 'No') . "\n", FILE_APPEND);
        
        // Check if book is available based on status and quantity/stock
        if ($hasQuantityField) {
            if ($book['status'] !== 'Available' && $book['quantity'] <= 0) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Book not available: Status=" . $book['status'] . ", Quantity=" . $book['quantity'] . "\n", FILE_APPEND);
                return false;
            }
        } else if ($hasStockField) {
            if ($book['status'] !== 'Available' && $book['stock'] <= 0) {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Book not available: Status=" . $book['status'] . ", Stock=" . $book['stock'] . "\n", FILE_APPEND);
                return false;
            }
        } else {
            // If no quantity tracking, just check status
            if ($book['status'] !== 'Available') {
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Book not available: Status=" . $book['status'] . "\n", FILE_APPEND);
                return false;
            }
        }
        
        // Ensure due_date supports time component (DATETIME)
        ensureTransactionsDueDateDateTime();

        // Calculate due date (exactly +N days from now, keeping time)
        $dueDate = (new DateTimeImmutable('now'))->modify('+' . (int) $days . ' day')->format('Y-m-d H:i:s');
        
        // Insert transaction record
        $stmt = $pdo->prepare("
            INSERT INTO transactions (book_id, member_id, borrow_date, due_date, status, payment_amount, payment_status)
            VALUES (:book_id, :member_id, NOW(), :due_date, 'Borrowed', :payment_amount, 'Paid')
        ");
        $stmt->bindParam(':book_id', $book['id'], PDO::PARAM_INT);
        $stmt->bindParam(':member_id', $member['id'], PDO::PARAM_INT);
        $stmt->bindParam(':due_date', $dueDate);
        $stmt->bindParam(':payment_amount', $paymentAmount);
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Attempting to insert transaction record\n", FILE_APPEND);
        $insertResult = $stmt->execute();
        
        if (!$insertResult) {
            $errorInfo = $stmt->errorInfo();
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed to insert transaction: " . json_encode($errorInfo) . "\n", FILE_APPEND);
            throw new PDOException("Failed to insert transaction: " . $errorInfo[2]);
        }
        
        $transactionId = $pdo->lastInsertId();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transaction inserted successfully, ID: $transactionId\n", FILE_APPEND);
        
        // Update book quantity/stock and status if needed
        if ($hasQuantityField) {
            // Decrement quantity
            $newQuantity = $book['quantity'] - 1;
            $newStatus = $newQuantity > 0 ? 'Available' : 'Borrowed';
            
            $stmt = $pdo->prepare("
                UPDATE books 
                SET quantity = :quantity_value, status = :status 
                WHERE id = :id
            ");
            $stmt->bindParam(':quantity_value', $newQuantity, PDO::PARAM_INT);
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':id', $book['id'], PDO::PARAM_INT);
            
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Updating book quantity to $newQuantity, status to $newStatus\n", FILE_APPEND);
            $updateResult = $stmt->execute();
            
            if (!$updateResult) {
                $errorInfo = $stmt->errorInfo();
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed to update book quantity: " . json_encode($errorInfo) . "\n", FILE_APPEND);
                throw new PDOException("Failed to update book quantity: " . $errorInfo[2]);
            }
        } else if ($hasStockField) {
            // Decrement stock
            $newStock = $book['stock'] - 1;
            $newStatus = $newStock > 0 ? 'Available' : 'Borrowed';
            
            $stmt = $pdo->prepare("
                UPDATE books 
                SET stock = :stock_value, status = :status 
                WHERE id = :id
            ");
            $stmt->bindParam(':stock_value', $newStock, PDO::PARAM_INT);
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':id', $book['id'], PDO::PARAM_INT);
            
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Updating book stock to $newStock, status to $newStatus\n", FILE_APPEND);
            $updateResult = $stmt->execute();
            
            if (!$updateResult) {
                $errorInfo = $stmt->errorInfo();
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed to update book stock: " . json_encode($errorInfo) . "\n", FILE_APPEND);
                throw new PDOException("Failed to update book stock: " . $errorInfo[2]);
            }
        } else {
            // Just mark as borrowed if no quantity tracking
            $stmt = $pdo->prepare("
                UPDATE books 
                SET status = 'Borrowed' 
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $book['id'], PDO::PARAM_INT);
            
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Updating book status to Borrowed\n", FILE_APPEND);
            $updateResult = $stmt->execute();
            
            if (!$updateResult) {
                $errorInfo = $stmt->errorInfo();
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed to update book status: " . json_encode($errorInfo) . "\n", FILE_APPEND);
                throw new PDOException("Failed to update book status: " . $errorInfo[2]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transaction committed successfully\n", FILE_APPEND);

        // Send email notification to member
        if (!empty($member['email'])) {
            $subject = "Book Borrowed Confirmation - EVSU Book Borrowing System";
            
            // Create HTML email content
            $htmlMessage = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.5; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; }
        .section { margin-bottom: 20px; }
        .info-box { background-color: #f9f9f9; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>EVSU Book Borrowing System</h1>
            <p>Book Borrowed Confirmation</p>
        </div>
        
        <div class='section'>
            <p>Dear " . htmlspecialchars($member['fullname']) . ",</p>
            <p>You have borrowed the following book from EVSU Book Borrowing System:</p>
            
            <div class='info-box'>
                <p><strong>Title:</strong> " . htmlspecialchars($book['title']) . "</p>
                <p><strong>Author:</strong> " . htmlspecialchars($book['author']) . "</p>
                <p><strong>Borrow Date:</strong> " . date('F j, Y') . "</p>
                <p><strong>Due Date:</strong> " . date('F j, Y', strtotime($dueDate)) . "</p>
            </div>
            
            <p>Please return the book on or before the due date to avoid penalties.</p>
            <p>Late returns may incur a penalty fee.</p>
            
            <p>Thank you for using EVSU Book Borrowing System!</p>
        </div>
    </div>
</body>
</html>";
            
            // Create plain text version
            $plainTextMessage = "Dear " . $member['fullname'] . ",\n\n";
            $plainTextMessage .= "You have borrowed the following book from EVSU Book Borrowing System:\n";
            $plainTextMessage .= "Title: " . $book['title'] . "\n";
            $plainTextMessage .= "Author: " . $book['author'] . "\n";
            $plainTextMessage .= "Borrow Date: " . date('F j, Y') . "\n";
            $plainTextMessage .= "Due Date: " . date('F j, Y', strtotime($dueDate)) . "\n\n";
            $plainTextMessage .= "Please return the book on or before the due date to avoid penalties.\n";
            $plainTextMessage .= "Late returns may incur a penalty fee.\n\n";
            $plainTextMessage .= "Thank you for using EVSU Book Borrowing System!\n";
            
            try {
                // Use the mailer.php functions instead of borrowing-specific ones
                // to avoid circular reference
                require_once __DIR__ . '/mailer.php';
                
                // Create a log file specific to this borrowing
                $borrowLogFile = __DIR__ . '/../logs/borrow_email_' . $pdo->lastInsertId() . '.log';
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Attempting to send email to: " . $member['email'] . "\n", FILE_APPEND);
                
                // Add proper headers for HTML content
                $headers = generateEmailHeaders(['content_type' => 'text/html']);
                
                // Use the standard email function
                $emailResult = sendEmail(
                    $member['email'],
                    $subject,
                    $htmlMessage,
                    $headers,
                    $plainTextMessage
                );
                
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Email sending result: " . ($emailResult ? "SUCCESS" : "FAILED") . "\n", FILE_APPEND);
            } catch (Exception $e) {
                // Log exception but don't fail the borrowing process
                file_put_contents($logFile, date('Y-m-d H:i:s') . " - Email error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
        
        return true;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error in borrowBook: " . $e->getMessage());
        return false;
    }
}

/**
 * Process returning a book
 */
function returnBook($bookBarcode) {
    global $pdo;
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get book details
        $book = getBookByBarcode($bookBarcode);
        
        if (!$book) {
            error_log("returnBook: Book not found with barcode: " . $bookBarcode);
            return false;
        }
        
        // Get the active transaction for this book
        $stmt = $pdo->prepare("
            SELECT t.*, m.fullname, m.email, m.notifications_enabled
            FROM transactions t
            JOIN members m ON t.member_id = m.id
            WHERE t.book_id = :book_id AND t.status IN ('Borrowed', 'Overdue', 'Needs Replacement')
            ORDER BY t.id DESC
            LIMIT 1
        ");
        $stmt->bindParam(':book_id', $book['id'], PDO::PARAM_INT);
        $stmt->execute();
        $transaction = $stmt->fetch();
        
        if (!$transaction) {
            error_log("returnBook: No active transaction found for book ID: " . $book['id']);
            return false;
        }
        
        // Check if the book is overdue and calculate penalty
        $dueDate = strtotime($transaction['due_date']);
        $today = strtotime('today');
        $hasPenalty = ($today > $dueDate);
        $penaltyAmount = $hasPenalty ? calculateLateReturnPenalty($transaction) : 0;
        
        // Get column names from transactions table to ensure we're using the right field name
        $stmt = $pdo->query("DESCRIBE transactions");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['Field'];
        }
        
        // Update transaction - use penalty_amount if late_fee doesn't exist
        $hasPenaltyField = in_array('penalty_amount', $columns);
        $hasLateFeeField = in_array('late_fee', $columns);
        
        if ($hasLateFeeField) {
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET status = 'Returned', 
                    return_date = NOW(),
                    late_fee = :penalty_amount
                WHERE id = :id
            ");
        } else if ($hasPenaltyField) {
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET status = 'Returned', 
                    return_date = NOW(),
                    penalty_amount = :penalty_amount
                WHERE id = :id
            ");
        } else {
            // If neither field exists, don't try to update it
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET status = 'Returned', 
                    return_date = NOW()
                WHERE id = :id
            ");
        }
        
        // Only bind penalty amount if we're using that field
        if ($hasLateFeeField || $hasPenaltyField) {
            $stmt->bindParam(':penalty_amount', $penaltyAmount);
        }
        $stmt->bindParam(':id', $transaction['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Update book status and quantity - check if we have quantity or stock field
        $stmt = $pdo->query("DESCRIBE books");
        $bookColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bookColumns[] = $row['Field'];
        }
        
        $hasQuantityField = in_array('quantity', $bookColumns);
        $hasStockField = in_array('stock', $bookColumns);
        
        if ($hasQuantityField) {
            $newQuantity = $book['quantity'] + 1;
            $stmt = $pdo->prepare("
                UPDATE books 
                SET status = 'Available',
                    quantity = :quantity_value
                WHERE id = :id
            ");
            $stmt->bindParam(':quantity_value', $newQuantity, PDO::PARAM_INT);
        } else if ($hasStockField) {
            $newStock = $book['stock'] + 1;
            $stmt = $pdo->prepare("
                UPDATE books 
                SET status = 'Available',
                    stock = :stock_value
                WHERE id = :id
            ");
            $stmt->bindParam(':stock_value', $newStock, PDO::PARAM_INT);
        } else {
            // If no quantity/stock tracking, just update status
            $stmt = $pdo->prepare("
                UPDATE books 
                SET status = 'Available'
                WHERE id = :id
            ");
        }
        
        $stmt->bindParam(':id', $book['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Create payment status based on overdue status
        $paymentStatus = $hasPenalty ? 'Overdue Fee Pending' : 'Paid';
        
        // Update payment status
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET payment_status = :payment_status
            WHERE id = :id
        ");
        $stmt->bindParam(':payment_status', $paymentStatus);
        $stmt->bindParam(':id', $transaction['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Send email notification if enabled
        if ($transaction['notifications_enabled'] && !empty($transaction['email'])) {
            // Create notification for return
            $notificationType = $hasPenalty ? 'Returned Late' : 'Returned';
            $notificationMessage = "Dear " . htmlspecialchars($transaction['fullname']) . ",\n\n";
            $notificationMessage .= "You have returned the book '" . htmlspecialchars($book['title']) . "'.\n";
            
            if ($hasPenalty) {
                $notificationMessage .= "The book was returned after the due date of " . date('F j, Y', $dueDate) . ".\n";
                $notificationMessage .= "Late fee: " . number_format($penaltyAmount, 2) . " pesos\n\n";
                $notificationMessage .= "Please pay this amount to the librarian.\n\n";
            }
            
            $notificationMessage .= "Thank you for using EVSU Book Borrowing System!\n";
            
            // Record notification in database
            $stmt = $pdo->prepare("
                INSERT INTO notifications (member_id, transaction_id, message, type, is_sent)
                VALUES (:member_id, :transaction_id, :message, :type, :is_sent)
            ");
            $stmt->bindParam(':member_id', $transaction['member_id']);
            $stmt->bindParam(':transaction_id', $transaction['id']);
            $stmt->bindParam(':message', $notificationMessage);
            $stmt->bindParam(':type', $notificationType);
            
            try {
                // Create dedicated log file for this return
                if (!is_dir(__DIR__ . '/../logs')) {
                    mkdir(__DIR__ . '/../logs', 0755, true);
                }
                $returnLogFile = __DIR__ . '/../logs/return_' . $transaction['id'] . '.log';
                
                // Include mail config and PHPMailer
                require_once __DIR__ . '/mail_config.php';
                require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
                require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
                require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
                
                // Use our mailer function from config/mailer.php
                require_once __DIR__ . '/mailer.php';
                
                // Set HTML email content
                $emailSubject = "EVSU Book Borrowing System - Book Return Receipt";
                $htmlMessage = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.5; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin-bottom: 5px; color: #333; }
        .section { margin-bottom: 20px; }
        .section h3 { margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .info-box { background-color: #f9f9f9; padding: 10px; border-radius: 4px; }
        .font-bold { font-weight: bold; }
        .text-center { text-align: center; }
        " . ($hasPenalty ? ".penalty { color: #d9534f; }" : "") . "
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>EVSU Book Borrowing System</h1>
            <p>Book Return Receipt</p>
        </div>
        
        <div class='section'>
            <h3>Return Information</h3>
            <div class='info-box'>
                <p><span class='font-bold'>Book Title:</span> " . htmlspecialchars($book['title']) . "</p>
                <p><span class='font-bold'>Author:</span> " . htmlspecialchars($book['author']) . "</p>
                <p><span class='font-bold'>Date Borrowed:</span> " . date('F j, Y', strtotime($transaction['borrow_date'])) . "</p>
                <p><span class='font-bold'>Due Date:</span> " . date('F j, Y, g:i A', strtotime($transaction['due_date'])) . "</p>
                <p><span class='font-bold'>Return Date:</span> " . date('F j, Y') . "</p>
            </div>
        </div>
        
        " . ($hasPenalty ? "
        <div class='section'>
            <h3>Late Return Fee</h3>
            <div class='info-box'>
                <p class='penalty font-bold'>A late fee of " . number_format($penaltyAmount, 2) . " pesos has been charged.</p>
                <p>Please pay this amount to the librarian.</p>
            </div>
        </div>" : "") . "
        
        <div class='section text-center'>
            <p>Thank you for using EVSU Book Borrowing System!</p>
        </div>
    </div>
</body>
</html>";
                
                // Plain text version
                $plainTextMessage = "COFFEE PRINCE LIBRARY - BOOK RETURN RECEIPT

RETURN INFORMATION:
Book Title: " . $book['title'] . "
Author: " . $book['author'] . "
Date Borrowed: " . date('F j, Y', strtotime($transaction['borrow_date'])) . "
Due Date: " . date('F j, Y, g:i A', strtotime($transaction['due_date'])) . "
Return Date: " . date('F j, Y') . "
" . ($hasPenalty ? "
LATE RETURN FEE:
A late fee of " . number_format($penaltyAmount, 2) . " pesos has been charged.
Please pay this amount to the librarian.
" : "") . "
Thank you for using EVSU Book Borrowing System!";
                
                // Try to send email
                $isSent = sendEmail(
                    $transaction['email'],
                    $emailSubject,
                    $htmlMessage,
                    "Content-Type: text/html; charset=UTF-8",
                    $plainTextMessage
                );
                
                // Log the email status
                $logMessage = date('Y-m-d H:i:s') . " - Return email to " . $transaction['email'] . " status: " . ($isSent ? "SENT" : "FAILED") . "\n";
                file_put_contents($returnLogFile, $logMessage, FILE_APPEND);
                
                // Record notification status
                $stmt->bindValue(':is_sent', $isSent, PDO::PARAM_BOOL);
                $stmt->execute();
                
            } catch (Exception $e) {
                // If email fails, still record the notification but mark as not sent
                $stmt->bindValue(':is_sent', false, PDO::PARAM_BOOL);
                $stmt->execute();
                error_log("Return notification email error: " . $e->getMessage());
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success with details
        return [
            'book_id' => $book['id'],
            'book_title' => $book['title'],
            'member_id' => $transaction['member_id'],
            'transaction_id' => $transaction['id'],
            'borrow_date' => $transaction['borrow_date'],
            'due_date' => $transaction['due_date'],
            'return_date' => date('Y-m-d H:i:s'),
            'has_penalty' => $hasPenalty,
            'penalty_amount' => $penaltyAmount,
            'payment_status' => $paymentStatus
        ];
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error in returnBook: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and update overdue books
 * Run this function periodically to update status of overdue books
 */
function updateOverdueBooks() {
    global $pdo;
    
    try {
        // Create log directory if it doesn't exist
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Create or append to log file
        $logFile = $logDir . '/overdue_updates.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Starting updateOverdueBooks check\n", FILE_APPEND);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Current system date: " . date('Y-m-d') . "\n", FILE_APPEND);
        
        // Get all borrowed books with due date passed
        $stmt = $pdo->prepare("
            SELECT t.id, t.book_id, t.due_date, t.borrow_date, b.title 
            FROM transactions t
            JOIN books b ON t.book_id = b.id
            WHERE t.status IN ('Borrowed', 'Overdue') AND t.due_date < NOW()
        ");
        $stmt->execute();
        $overdueBooks = $stmt->fetchAll();
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Found " . count($overdueBooks) . " active overdue books/transactions\n", FILE_APPEND);
        
        foreach ($overdueBooks as $book) {
            $borrowDateStr = substr($book['borrow_date'], 0, 10);
            $borrowDate = strtotime($borrowDateStr);
            $today = strtotime(date('Y-m-d'));
            $daysSinceBorrow = floor(($today - $borrowDate) / (60 * 60 * 24));
            
            if ($daysSinceBorrow >= 7) {
                $newStatus = 'Needs Replacement';
            } else {
                $newStatus = 'Overdue';
            }
            
            // Log the book being updated
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Updating book ID " . $book['book_id'] . " (" . $book['title'] . ") status to " . $newStatus . ". Borrow date was " . $book['borrow_date'] . ", Due date was " . $book['due_date'] . " (Days since borrow: " . $daysSinceBorrow . ")\n", FILE_APPEND);
            
            // Update book status
            $stmt = $pdo->prepare("UPDATE books SET status = :status WHERE id = :id");
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':id', $book['book_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            // Update transaction status
            $stmt = $pdo->prepare("
                UPDATE transactions 
                SET status = :status
                WHERE id = :transaction_id
            ");
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':transaction_id', $book['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Completed updateOverdueBooks. Updated " . count($overdueBooks) . " books.\n", FILE_APPEND);
        return count($overdueBooks);
    } catch (PDOException $e) {
        // Log the error
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error in updateOverdueBooks: " . $e->getMessage() . "\n", FILE_APPEND);
        return 0;
    }
}

/**
 * Send due date reminder notifications
 * Run this function daily to check for books due soon and send notifications
 */
function sendDueDateReminders($daysBeforeDue = 2) {
    global $pdo;
    
    try {
        // Find transactions that are coming due
        $stmt = $pdo->prepare("
            SELECT t.id, t.due_date, t.book_id, t.member_id,
                   b.title as book_title, b.barcode as book_barcode,
                   m.fullname as member_name, m.email, m.notifications_enabled
            FROM transactions t
            JOIN books b ON t.book_id = b.id
            JOIN members m ON t.member_id = m.id
            WHERE t.status = 'Borrowed'
            AND t.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days_before DAY)
            AND m.notifications_enabled = 1
            AND m.email IS NOT NULL
        ");
        $stmt->bindParam(':days_before', $daysBeforeDue, PDO::PARAM_INT);
        $stmt->execute();
        $dueSoonTransactions = $stmt->fetchAll();
        
        $notificationsSent = 0;
        
        foreach ($dueSoonTransactions as $transaction) {
            $notificationType = 'Due Soon';
            $notificationMessage = "Dear " . htmlspecialchars($transaction['member_name']) . ",\n\n";
            $notificationMessage .= "This is a friendly reminder that the book '" . htmlspecialchars($transaction['book_title']) . "' is due soon.\n";
            $notificationMessage .= "Due date: " . date('F j, Y, g:i A', strtotime($transaction['due_date'])) . "\n\n";
            $notificationMessage .= "Please return the book on time to avoid penalty charges.\n";
            $notificationMessage .= "Late returns may incur a penalty fee.\n\n";
            $notificationMessage .= "Thank you for using EVSU Book Borrowing System!\n";
            
            // Prepare email headers
            $emailSubject = "Book Due Soon Reminder - EVSU Book Borrowing System";
            $emailHeaders = "From: EVSU Book Borrowing System <noreply@coffeeprincelibrary.com>\r\n";
            $emailHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            // Send email
            $emailSent = sendEmailWithLogging($transaction['email'], $emailSubject, $notificationMessage, $emailHeaders);
            
            // Record notification in database
            $stmt = $pdo->prepare("
                INSERT INTO notifications (member_id, transaction_id, message, type, is_sent)
                VALUES (:member_id, :transaction_id, :message, :type, :is_sent)
            ");
            $stmt->bindParam(':member_id', $transaction['member_id']);
            $stmt->bindParam(':transaction_id', $transaction['id']);
            $stmt->bindParam(':message', $notificationMessage);
            $stmt->bindParam(':type', $notificationType);
            $stmt->bindParam(':is_sent', $emailSent, PDO::PARAM_BOOL);
            $stmt->execute();
            
            if ($emailSent) {
                $notificationsSent++;
            }
        }
        
        return $notificationsSent;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Send overdue notifications
 * Run this function daily to notify about overdue books
 */
function sendOverdueNotifications() {
    global $pdo;
    
    try {
        // Find transactions that are overdue
        $stmt = $pdo->prepare("
            SELECT t.id, t.due_date, t.book_id, t.member_id, t.payment_amount,
                   b.title as book_title, b.barcode as book_barcode,
                   m.fullname as member_name, m.email, m.notifications_enabled
            FROM transactions t
            JOIN books b ON t.book_id = b.id
            JOIN members m ON t.member_id = m.id
            WHERE t.status = 'Overdue'
            AND m.notifications_enabled = 1
            AND m.email IS NOT NULL
        ");
        $stmt->execute();
        $overdueTransactions = $stmt->fetchAll();
        
        $notificationsSent = 0;
        
        foreach ($overdueTransactions as $transaction) {
            $penaltyAmount = calculateLateReturnPenalty($transaction);
            
            $notificationType = 'Overdue';
            $notificationMessage = "Dear " . htmlspecialchars($transaction['member_name']) . ",\n\n";
            $notificationMessage .= "The book '" . htmlspecialchars($transaction['book_title']) . "' is OVERDUE.\n";
            $notificationMessage .= "Due date was: " . date('F j, Y, g:i A', strtotime($transaction['due_date'])) . "\n";
            $notificationMessage .= "Penalty amount: " . number_format($penaltyAmount, 2) . " pesos\n\n";
            $notificationMessage .= "Please return the book as soon as possible and settle the penalty.\n\n";
            $notificationMessage .= "Thank you for your cooperation.\n";
            
            // Prepare email headers
            $emailSubject = "OVERDUE BOOK NOTICE - EVSU Book Borrowing System";
            $emailHeaders = "From: EVSU Book Borrowing System <noreply@coffeeprincelibrary.com>\r\n";
            $emailHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            // Send email
            $emailSent = sendEmailWithLogging($transaction['email'], $emailSubject, $notificationMessage, $emailHeaders);
            
            // Record notification in database
            $stmt = $pdo->prepare("
                INSERT INTO notifications (member_id, transaction_id, message, type, is_sent)
                VALUES (:member_id, :transaction_id, :message, :type, :is_sent)
            ");
            $stmt->bindParam(':member_id', $transaction['member_id']);
            $stmt->bindParam(':transaction_id', $transaction['id']);
            $stmt->bindParam(':message', $notificationMessage);
            $stmt->bindParam(':type', $notificationType);
            $stmt->bindParam(':is_sent', $emailSent, PDO::PARAM_BOOL);
            $stmt->execute();
            
            if ($emailSent) {
                $notificationsSent++;
            }
        }
        
        return $notificationsSent;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Delete a notification by ID
 */
function deleteNotification($notificationId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id");
        $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        return $result;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Delete all notifications for a member
 */
function deleteAllMemberNotifications($memberId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE member_id = :member_id");
        $stmt->bindParam(':member_id', $memberId, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        return $result;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notificationId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
        $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        return $result;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Send an email with logging
 * Wrapper around sendEmail that adds logging
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email content
 * @param string $headers Additional headers
 * @return bool Whether email was sent
 */
function sendEmailWithLogging($to, $subject, $message, $headers = '') {
    // Create logs directory if it doesn't exist
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    
    $logFile = 'logs/email_log.txt';
    
    // Log the attempt
    $logEntry = date('Y-m-d H:i:s') . " - Attempt to send email to: $to | Subject: $subject\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Include the mailer
    require_once __DIR__ . '/mailer.php';
    
    // Try to send email
    $result = sendEmail($to, $subject, $message, $headers);
    
    // Log the result
    $resultEntry = date('Y-m-d H:i:s') . " - Email to $to " . ($result ? "SENT" : "FAILED") . "\n";
    file_put_contents($logFile, $resultEntry, FILE_APPEND);
    
    return $result;
}

/**
 * Generate a random OTP code
 * 
 * @param int $length Length of the OTP code
 * @return string The generated OTP code
 */
function generateOTP($length = 6) {
    // Generate a random numeric OTP
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= mt_rand(0, 9);
    }
    return $otp;
}

/**
 * Store OTP in the database
 * 
 * @param int $userId User ID to associate with the OTP
 * @param string $otp The OTP code to store
 * @param int $expiryMinutes Minutes until OTP expires
 * @return bool True if OTP stored successfully, false otherwise
 */
function storeOTPInDatabase($userId, $otp, $expiryMinutes = 10) {
    global $pdo;
    
    try {
        // First, invalidate any existing OTPs for this user
        $stmt = $pdo->prepare("DELETE FROM otp_verifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Create expiry timestamp
        $expiryTime = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
        
        // Store plain OTP (non-hashed) like in secureSystem
        $stmt = $pdo->prepare("INSERT INTO otp_verifications (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
        $result = $stmt->execute([$userId, $otp, $expiryTime]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("OTP Database Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send OTP via email for two-factor authentication
 * 
 * @param string $email Email address to send OTP to
 * @param string $fullname Full name of the recipient
 * @return bool True if OTP sent successfully, false otherwise
 */
function sendOTPEmail($email, $fullname) {
    global $pdo;
    
    // Check for null or empty values
    if (empty($email)) {
        error_log("OTP Email Error: Email is empty");
        return false;
    }
    
    // Use default name if fullname is null
    $fullname = $fullname ?? 'User';
    
    // Get user ID from email
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        error_log("OTP Email Error: User not found for email: $email");
        return false;
    }
    
    // Generate OTP code
    $otp = generateOTP();
    
    // Debug log
    error_log("Generated OTP: " . $otp . " for user ID: " . $user['id'] . " with email: " . $email);
    
    // Store OTP in database
    if (!storeOTPInDatabase($user['id'], $otp)) {
        error_log("OTP Email Error: Failed to store OTP in database");
        return false;
    }
    
    // Prepare email content
    $subject = "EVSU Book Borrowing System - Your Login Verification Code";
    $message = "Dear " . htmlspecialchars($fullname) . ",\n\n";
    $message .= "Your verification code for logging into EVSU Book Borrowing System is: " . $otp . "\n\n";
    $message .= "This code will expire in 10 minutes.\n\n";
    $message .= "If you didn't request this code, please ignore this email or contact support.\n\n";
    $message .= "Thank you,\nEVSU Book Borrowing System Team";
    
    // Also save email to file as backup
    $timestamp = date('Ymd_His');
    $filename = __DIR__ . '/../emails/otp_' . $timestamp . '_' . str_replace(['@', '.'], '_', $email) . '.txt';
    $emailContent = "To: $email\nSubject: $subject\n\n$message";
    file_put_contents($filename, $emailContent);
    
    // Send the OTP email
    return sendEmailWithLogging($email, $subject, $message);
}

/**
 * Verify OTP code entered by user
 * 
 * @param string $enteredOTP The OTP code entered by the user
 * @return bool True if OTP is valid, false otherwise
 */
function verifyOTP($enteredOTP) {
    global $pdo;
    
    // Ensure we have a pending user ID
    if (!isset($_SESSION['admin_pending_id'])) {
        return false;
    }
    
    $userId = $_SESSION['admin_pending_id'];
    
    try {
        // Get the most recent non-expired OTP for this user
        $stmt = $pdo->prepare("
            SELECT id, otp_code, expires_at 
            FROM otp_verifications 
            WHERE user_id = ? 
            AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $otpRecord = $stmt->fetch();
        
        // If no valid OTP found
        if (!$otpRecord) {
            error_log("No valid OTP found for user ID: " . $userId);
            return false;
        }
        
        // Debug log the comparison
        error_log("OTP Verification - Entered: " . $enteredOTP . " | Stored: " . $otpRecord['otp_code']);
        
        // Direct comparison since we're storing plain OTPs
        $valid = ($enteredOTP === $otpRecord['otp_code']);
        
        // If valid, delete the used OTP
        if ($valid) {
            $deleteStmt = $pdo->prepare("DELETE FROM otp_verifications WHERE id = ?");
            $deleteStmt->execute([$otpRecord['id']]);
            
            // Log successful verification
            error_log("OTP Verification successful for user ID: " . $userId);
        } else {
            error_log("OTP Verification failed for user ID: " . $userId);
        }
        
        return $valid;
    } catch (PDOException $e) {
        error_log("OTP Verification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Resend OTP to user
 * 
 * @param int $userId User ID to send new OTP to
 * @return bool True if OTP sent successfully, false otherwise
 */
function resendOTP($userId) {
    global $pdo;
    
    try {
        // Get user details
        $stmt = $pdo->prepare("SELECT email, fullname FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Send new OTP
        return sendOTPEmail($user['email'], $user['fullname']);
    } catch (PDOException $e) {
        error_log("Resend OTP Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate a barcode image URL using an online API
 * 
 * @param string $code The barcode text to encode
 * @param int $width Width of the barcode (default: 300) 
 * @param int $height Height of the barcode (default: 80)
 * @return string URL of the barcode image
 */
function generateBarcodeImage($code, $width = 300, $height = 80) {
    // Use the bwip-js online barcode generator API
    // This is a reliable free service that generates high-quality barcodes
    return "https://bwipjs-api.metafloor.com/?bcid=code128&text=" . urlencode($code) . 
           "&scale=2&includetext=true&textsize=13&textyoffset=5&backgroundcolor=ffffff";
} 