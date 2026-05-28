<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();
ensureMemberCourseColumn();
ensureMemberStatusColumn();
ensureMemberStudentIdColumn();

$canManageMembers = isAdmin() || staffHasPermission('members.edit');
$courseOptions = getMemberCourseOptions();
$statusOptions = getMemberStatusOptions();
$canAddMembers = isStaff() && staffHasPermission('members.add');

// Process form submissions
$action = $_GET['action'] ?? '';
$memberId = $_GET['id'] ?? 0;

if ($action === 'add' && !$canAddMembers) {
    setFlashMessage('You do not have permission to add members.', 'error');
    header('Location: members.php');
    exit;
}

if (isStaff()) {
    if (in_array($action, ['delete', 'edit', 'print_barcode'], true) && !$canManageMembers) {
        setFlashMessage('You do not have permission to perform that action.', 'error');
        header('Location: members.php');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['id']) && !$canManageMembers) {
            setFlashMessage('You do not have permission to edit members.', 'error');
            header('Location: members.php');
            exit;
        }
        if (empty($_POST['id']) && !$canAddMembers) {
            setFlashMessage('You do not have permission to add members.', 'error');
            header('Location: members.php');
            exit;
        }
    }
}

// Handle member deletion
if ($action === 'delete' && $memberId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM members WHERE id = :id");
        $stmt->bindParam(':id', $memberId, PDO::PARAM_INT);
        $stmt->execute();
        
        setFlashMessage('Member deleted successfully', 'success');
    } catch (PDOException $e) {
        setFlashMessage('Error deleting member: ' . $e->getMessage(), 'error');
    }
    
    header('Location: members.php');
    exit;
}

// Print member barcode
if ($action === 'print_barcode' && $memberId) {
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = :id");
    $stmt->bindParam(':id', $memberId, PDO::PARAM_INT);
    $stmt->execute();
    $memberData = $stmt->fetch();
    
    if ($memberData) {
        // Display barcode print page
        include 'includes/header.php';
        ?>
        <div class="mb-6 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Member Barcode</h2>
                <p class="text-gray-600 dark:text-gray-400">Print barcode for <?php echo htmlspecialchars($memberData['fullname']); ?></p>
            </div>
            <div>
                <a href="members.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to List
                </a>
                <button onclick="printBarcode()" class="ml-2 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
            <div id="barcode-content" class="max-w-md mx-auto text-center p-8">
                <div class="mb-4">
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($memberData['fullname']); ?></h3>
                    <p class="text-gray-600 dark:text-gray-400">Member ID: <?php echo htmlspecialchars($memberData['barcode']); ?></p>
                </div>
                
                <div class="my-8">
                    <svg class="barcode-large mx-auto" jsbarcode-format="CODE128" jsbarcode-value="<?php echo htmlspecialchars($memberData['barcode']); ?>" jsbarcode-textmargin="0" jsbarcode-height="80" jsbarcode-fontoptions="bold" jsbarcode-fontSize="16" jsbarcode-width="2"></svg>
                </div>
                
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">EVSU Book Borrowing System</p>
            </div>
        </div>
        
        <style>
            @media print {
                header, .mb-6, footer {
                    display: none;
                }
                .barcode-large {
                    max-width: 100%;
                }
                body {
                    background: white;
                }
                .bg-white {
                    background: white !important;
                    box-shadow: none !important;
                    border: none !important;
                }
                .rounded-xl {
                    border-radius: 0 !important;
                }
            }
            .barcode-large {
                width: 300px;
                padding: 10px;
                background: white;
                border: 1px solid #e2e8f0;
                border-radius: 0.375rem;
            }
        </style>
        
        <script>
            function printBarcode() {
                window.print();
            }
            
            // Render barcode when page is loaded
            document.addEventListener('DOMContentLoaded', function() {
                JsBarcode(".barcode-large").init();
            });
        </script>
        
        <?php
        include 'includes/footer.php';
        exit;
    } else {
        setFlashMessage('Member not found', 'error');
        header('Location: members.php');
        exit;
    }
}

// Handle member form submission (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $barcode = $_POST['barcode'] ?? '';
    $studentId = trim($_POST['student_id'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $memberStatus = $_POST['status'] ?? 'active';
    $notifications_enabled = isset($_POST['notifications_enabled']) ? 1 : 0;
    $isEdit = isset($_POST['id']) && !empty($_POST['id']);

    if (!array_key_exists($memberStatus, $statusOptions)) {
        $memberStatus = 'active';
    }
    
    // Validate required fields
    if (empty($fullname)) {
        setFlashMessage('Member name is required', 'error');
    } elseif (empty($course) || !array_key_exists($course, $courseOptions)) {
        setFlashMessage('Please select a valid course', 'error');
    } else {
        try {
            // Handle photo upload
            $photo_path = null;
            if (!empty($_FILES['photo']['name'])) {
                // Create uploads directory if it doesn't exist
                $target_dir = "uploads/members/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
                $new_filename = uniqid() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                // Check if file is an actual image
                $check = getimagesize($_FILES["photo"]["tmp_name"]);
                if ($check !== false) {
                    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                        $photo_path = $target_file;
                    }
                }
            }
            
            // Validate and store student ID
            $studentIdToSave = !empty($studentId) ? $studentId : null;
            if ($studentIdToSave !== null) {
                if ($isEdit) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE student_id = :student_id AND id != :id");
                    $stmt->execute([':student_id' => $studentIdToSave, ':id' => $_POST['id']]);
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE student_id = :student_id");
                    $stmt->execute([':student_id' => $studentIdToSave]);
                }
                if ($stmt->fetchColumn() > 0) {
                    setFlashMessage('A member with this Student ID already exists.', 'error');
                    header('Location: members.php');
                    exit;
                }
                // Use student ID as library barcode for scanning when provided
                $barcode = $studentIdToSave;
            }

            // If no barcode provided, generate one
            if (empty($barcode)) {
                $barcode = generateMemberBarcode();
            }
            
            if ($isEdit) {
                // Update existing member
                $update_sql = "
                    UPDATE members 
                    SET fullname = :fullname, email = :email, phone = :phone, 
                        address = :address, course = :course, student_id = :student_id, status = :status, barcode = :barcode, 
                        notifications_enabled = :notifications_enabled
                ";
                
                // Only update photo if a new one was uploaded
                if ($photo_path) {
                    $update_sql .= ", photo_path = :photo_path";
                }
                
                $update_sql .= " WHERE id = :id";
                
                $stmt = $pdo->prepare($update_sql);
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':course', $course);
                $stmt->bindParam(':student_id', $studentIdToSave);
                $stmt->bindParam(':status', $memberStatus);
                $stmt->bindParam(':barcode', $barcode);
                $stmt->bindParam(':notifications_enabled', $notifications_enabled);
                $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
                
                if ($photo_path) {
                    $stmt->bindParam(':photo_path', $photo_path);
                }
                
                $stmt->execute();
                
                setFlashMessage('Member updated successfully', 'success');
            } else {
                if (!$canAddMembers) {
                    setFlashMessage('You do not have permission to add members.', 'error');
                    header('Location: members.php');
                    exit;
                }

                // Add new member
                $stmt = $pdo->prepare("
                    INSERT INTO members (fullname, email, phone, address, course, student_id, status, barcode, photo_path, notifications_enabled)
                    VALUES (:fullname, :email, :phone, :address, :course, :student_id, :status, :barcode, :photo_path, :notifications_enabled)
                ");
                $stmt->bindParam(':fullname', $fullname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':course', $course);
                $stmt->bindParam(':student_id', $studentIdToSave);
                $stmt->bindParam(':status', $memberStatus);
                $stmt->bindParam(':barcode', $barcode);
                $stmt->bindParam(':photo_path', $photo_path);
                $stmt->bindParam(':notifications_enabled', $notifications_enabled);
                $stmt->execute();
                
                // Get the newly created member ID
                $newMemberId = $pdo->lastInsertId();

                // Send welcome email (same message as before, HTML + logo from logo/ folder)
                if (!empty($email)) {
                    if (!function_exists('sendMemberWelcomeEmail')) {
                        require_once 'config/mailer.php';
                    }

                    $emailResult = sendMemberWelcomeEmail($email, $fullname, $barcode);
                    
                    // Log the email attempt
                    $logsDir = "logs";
                    if (!is_dir($logsDir)) {
                        mkdir($logsDir, 0755, true);
                    }
                    
                    $logMessage = date('Y-m-d H:i:s') . " - Welcome email to new member: " . $email . " (ID: " . $barcode . ") - Status: " . ($emailResult ? "SUCCESS" : "FAILED") . "\n";
                    file_put_contents($logsDir . "/member_notifications.txt", $logMessage, FILE_APPEND);
                }
                
                setFlashMessage('Member added successfully', 'success');
            }
            
            header('Location: members.php');
            exit;
        } catch (PDOException $e) {
            setFlashMessage('Error: ' . $e->getMessage(), 'error');
        }
    }
}

// Get member data for editing
$memberData = null;
if ($action === 'edit' && $memberId) {
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = :id");
    $stmt->bindParam(':id', $memberId, PDO::PARAM_INT);
    $stmt->execute();
    $memberData = $stmt->fetch();
    
    if (!$memberData) {
        setFlashMessage('Member not found', 'error');
        header('Location: members.php');
        exit;
    }
}

// Get all members for listing
$search = trim($_GET['search'] ?? '');
$courseFilter = $_GET['course'] ?? '';
if ($courseFilter !== '' && !array_key_exists($courseFilter, $courseOptions)) {
    $courseFilter = '';
}

$memberQuery = 'SELECT * FROM members WHERE 1=1';
$memberParams = [];

if ($search !== '') {
    $memberQuery .= ' AND (
        fullname LIKE :search
        OR email LIKE :search
        OR phone LIKE :search
        OR barcode LIKE :search
        OR student_id LIKE :search
        OR address LIKE :search
        OR course LIKE :search
    )';
    $memberParams[':search'] = '%' . $search . '%';
}

if ($courseFilter !== '') {
    $memberQuery .= ' AND course = :course';
    $memberParams[':course'] = $courseFilter;
}

$memberQuery .= ' ORDER BY fullname';
$stmt = $pdo->prepare($memberQuery);
foreach ($memberParams as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$members = $stmt->fetchAll();

$hasActiveFilters = ($search !== '' || $courseFilter !== '');

// Include header
include 'includes/header.php';
?>

<?php if ($action === 'edit' || ($action === 'add' && $canAddMembers)): ?>
    <!-- Add/Edit Member Form -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                <?php echo ($action === 'edit') ? 'Edit Member' : 'Add New Member'; ?>
            </h2>
            <p class="text-gray-600 dark:text-gray-400">
                <?php echo ($action === 'edit') ? 'Update member information' : 'Register a new library member'; ?>
            </p>
        </div>
        <a href="members.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
        </a>
    </div>
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <form method="POST" action="members.php" enctype="multipart/form-data">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?php echo $memberData['id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="fullname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                    <input type="text" id="fullname" name="fullname" required 
                           value="<?php echo ($memberData) ? htmlspecialchars($memberData['fullname']) : ''; ?>"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo ($memberData) ? htmlspecialchars($memberData['email']) : ''; ?>"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="student_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Student ID (Optional)</label>
                    <input type="text" id="student_id" name="student_id"
                           value="<?php echo ($memberData) ? htmlspecialchars($memberData['student_id'] ?? '') : ''; ?>"
                           placeholder="e.g. 2021-12345"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Must be unique if provided.</p>
                </div>
                
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number</label>
                    <input type="text" id="phone" name="phone" 
                           value="<?php echo ($memberData) ? htmlspecialchars($memberData['phone']) : ''; ?>"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>

                <div>
                    <label for="course" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Course *</label>
                    <select id="course" name="course" required
                            class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <option value="">Select a course</option>
                        <?php
                        $selectedCourse = ($memberData && !empty($memberData['course'])) ? $memberData['course'] : '';
                        foreach ($courseOptions as $code => $label):
                        ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($selectedCourse === $code) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="barcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barcode</label>
                    <input type="text" id="barcode" name="barcode" 
                           value="<?php echo ($memberData) ? htmlspecialchars($memberData['barcode']) : ''; ?>"
                           placeholder="Leave empty to auto-generate"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>

                <?php if ($action === 'edit'): ?>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Member Status *</label>
                    <select id="status" name="status" required
                            class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        <?php
                        $selectedStatus = ($memberData && !empty($memberData['status'])) ? $memberData['status'] : 'active';
                        foreach ($statusOptions as $code => $label):
                        ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($selectedStatus === $code) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Inactive members cannot borrow books.</p>
                </div>
                <?php else: ?>
                <input type="hidden" name="status" value="active">
                <?php endif; ?>
                
                <div class="md:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                    <textarea id="address" name="address" rows="2"
                              class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white"><?php echo ($memberData) ? htmlspecialchars($memberData['address']) : ''; ?></textarea>
                </div>
                
                <div>
                    <label for="photo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Photo</label>
                    <input type="file" id="photo" name="photo" accept="image/*"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <?php if ($memberData && !empty($memberData['photo_path'])): ?>
                        <div class="mt-2">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Current photo: 
                                <a href="<?php echo htmlspecialchars($memberData['photo_path']); ?>" class="text-blue-600 dark:text-blue-400" target="_blank">View</a>
                            </p>
                        </div>
                    <?php endif; ?>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload a profile picture (JPG, PNG)</p>
                </div>
                
                <div>
                    <div class="flex items-center mt-6">
                        <input type="checkbox" id="notifications_enabled" name="notifications_enabled" 
                               <?php echo (!$memberData || $memberData['notifications_enabled']) ? 'checked' : ''; ?>
                               class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:bg-gray-700">
                        <label for="notifications_enabled" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                            Send email notifications (due dates, overdue reminders)
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <a href="members.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg mr-2">Cancel</a>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg">
                    <?php echo ($action === 'edit') ? 'Update Member' : 'Add Member'; ?>
                </button>
            </div>
        </form>
    </div>
<?php else: ?>
    <!-- Members List -->
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $canManageMembers ? 'Member Management' : 'Members'; ?></h2>
            <p class="text-gray-600 dark:text-gray-400">View and manage library members</p>
        </div>
        <?php if ($canAddMembers): ?>
        <a href="members.php?action=add" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Member
        </a>
        <?php endif; ?>
    </div>
    
    <!-- Search Bar -->
    <div class="mb-6">
        <form method="GET" action="members.php" class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" placeholder="Search by name, email, phone or barcode..." 
                    value="<?php echo htmlspecialchars($search); ?>"
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            <div class="w-full sm:w-64">
                <select name="course"
                        class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="">All Courses</option>
                    <?php foreach ($courseOptions as $code => $label): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo ($courseFilter === $code) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
            </div>
            <?php if ($hasActiveFilters): ?>
                <div>
                    <a href="members.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-times mr-2"></i> Clear
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                <?php if ($hasActiveFilters): ?>
                    Filtered Members
                <?php else: ?>
                    All Members
                <?php endif; ?>
            </h3>
            <span class="text-gray-600 dark:text-gray-400">
                <?php if ($hasActiveFilters): ?>
                    <?php echo count($members); ?> members found
                <?php else: ?>
                    <?php echo count($members); ?> registered members
                <?php endif; ?>
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Member</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Student ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Course</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Barcode</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Join Date</th>
                        <?php if ($canManageMembers || staffHasPermission('transactions.view')): ?>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"><?php echo $canManageMembers ? 'Actions' : 'History'; ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (count($members) > 0): ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap" data-label="Member">
                                    <div class="flex items-center">
                                        <?php if (!empty($member['photo_path'])): ?>
                                            <img class="h-12 w-12 rounded-full object-cover" src="<?php echo htmlspecialchars($member['photo_path']); ?>" alt="<?php echo htmlspecialchars($member['fullname']); ?>">
                                        <?php else: ?>
                                            <div class="bg-gray-200 dark:bg-gray-700 h-12 w-12 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user text-gray-400 dark:text-gray-500"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($member['fullname']); ?></div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($member['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white"><?php echo htmlspecialchars($member['phone']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($member['student_id'] ?? '-'); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    <?php
                                    $memberCourse = $member['course'] ?? '';
                                    echo htmlspecialchars($courseOptions[$memberCourse] ?? ($memberCourse ?: '-'));
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $memberStatus = $member['status'] ?? 'active';
                                    if ($memberStatus === 'inactive'):
                                    ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactive</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <svg class="barcode-canvas" jsbarcode-format="CODE128" jsbarcode-value="<?php echo htmlspecialchars($member['barcode']); ?>" jsbarcode-textmargin="0" jsbarcode-fontoptions="bold" jsbarcode-height="40"></svg>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo date('M j, Y', strtotime($member['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <?php if (!$canManageMembers && staffHasPermission('transactions.view')): ?>
                                    <a href="transactions.php?search=<?php echo urlencode($member['fullname']); ?>" class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300" title="View borrow & return history">
                                        <i class="fas fa-history mr-1"></i> History
                                    </a>
                                    <?php elseif ($canManageMembers): ?>
                                    <a href="members.php?action=print_barcode&id=<?php echo $member['id']; ?>" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300 mr-3">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="members.php?action=edit&id=<?php echo $member['id']; ?>" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars(addslashes($member['fullname'])); ?>')" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No members found. Add a member to get started.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-md w-full">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Confirm Deletion</h3>
            <p class="text-gray-700 dark:text-gray-300 mb-6">Are you sure you want to delete "<span id="deleteMemberName"></span>"? This action cannot be undone.</p>
            <div class="flex justify-end">
                <button id="cancelDelete" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg mr-2">Cancel</button>
                <a id="confirmDeleteBtn" href="#" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">Delete</a>
            </div>
        </div>
    </div>
    
    <script>
        function confirmDelete(id, name) {
            document.getElementById('deleteMemberName').textContent = name;
            document.getElementById('confirmDeleteBtn').href = 'members.php?action=delete&id=' + id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        document.getElementById('cancelDelete').addEventListener('click', function() {
            document.getElementById('deleteModal').classList.add('hidden');
        });
    </script>
<?php endif; ?>

<?php
// Include footer
include 'includes/footer.php';
?> 