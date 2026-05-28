<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this page
requireLogin();

// Initialize variables
$success = '';
$error = '';

// Get current admin data
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :id");
$stmt->bindParam(':id', $_SESSION['admin_id']);
$stmt->execute();
$admin = $stmt->fetch();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle profile image upload
    if (isset($_POST['update_profile_image'])) {
        // Check if image was uploaded
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_image']['name'];
            $filesize = $_FILES['profile_image']['size'];
            $filetype = $_FILES['profile_image']['type'];
            
            // Get file extension
            $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
            $file_ext = strtolower($file_ext);
            
            // Verify file extension
            if (!in_array($file_ext, $allowed)) {
                $error = 'Error: Please select a valid image file format (JPG, JPEG, PNG, GIF).';
            }
            
            // Verify file size - 5MB maximum
            if ($filesize > 5 * 1024 * 1024) {
                $error = 'Error: File size exceeds 5MB limit.';
            }
            
            // Verify MIME type
            if (empty($error)) {
                // Create uploads directory if it doesn't exist
                if (!is_dir('uploads/profile_images')) {
                    mkdir('uploads/profile_images', 0755, true);
                }
                
                // Generate unique filename
                $new_filename = 'admin_' . $_SESSION['admin_id'] . '_' . time() . '.' . $file_ext;
                $upload_path = 'uploads/profile_images/' . $new_filename;
                
                // Move the file
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Update database with new image path
                    $stmt = $pdo->prepare("UPDATE admins SET profile_image = :image WHERE id = :id");
                    $stmt->execute([
                        ':image' => $upload_path,
                        ':id' => $_SESSION['admin_id']
                    ]);
                    
                    // Log activity
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'Settings', 'Updated profile image')");
                        $logStmt->execute([$_SESSION['admin_id']]);
                    } catch (Exception $e) {
                        // Silently handle errors with logging
                    }
                    
                    $success = 'Profile image updated successfully';
                    
                    // Refresh admin data
                    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = :id");
                    $stmt->bindParam(':id', $_SESSION['admin_id']);
                    $stmt->execute();
                    $admin = $stmt->fetch();
                } else {
                    $error = 'Error: Failed to upload image.';
                }
            }
        } else {
            $error = 'Error: Please select an image to upload.';
        }
    }
    
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate input
        if (empty($username) || empty($email)) {
            $error = 'Username and email are required';
        } else {
            // Update profile
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Check if password is being changed
                $passwordUpdate = '';
                $params = [
                    ':username' => $username,
                    ':email' => $email,
                    ':id' => $_SESSION['admin_id']
                ];
                
                if (!empty($current_password) && !empty($new_password)) {
                    // Verify current password
                    if (password_verify($current_password, $admin['password'])) {
                        if ($new_password === $confirm_password) {
                            $passwordUpdate = ', password = :password';
                            $params[':password'] = password_hash($new_password, PASSWORD_DEFAULT);
                        } else {
                            throw new Exception('New passwords do not match');
                        }
                    } else {
                        throw new Exception('Current password is incorrect');
                    }
                }
                
                // Update admin profile
                $sql = "UPDATE admins SET username = :username, email = :email" . $passwordUpdate . " WHERE id = :id";
                $updateStmt = $pdo->prepare($sql);
                $updateStmt->execute($params);
                
                // Update session
                $_SESSION['admin_username'] = $username;
                
                // Log activity
                try {
                    $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'Settings', 'Updated profile settings')");
                    $logStmt->execute([$_SESSION['admin_id']]);
                } catch (Exception $e) {
                    // Silently handle errors with logging
                }
                
                // Commit transaction
                $pdo->commit();
                
                $success = 'Profile updated successfully';
                
                // Refresh admin data
                $stmt->execute();
                $admin = $stmt->fetch();
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }
    
    // Handle add staff account (admin only)
    if (isset($_POST['add_staff'])) {
        if (!isAdmin()) {
            $error = 'You do not have permission to add staff accounts.';
        } else {
            $staffFullname = trim($_POST['staff_fullname'] ?? '');
            $staffUsername = trim($_POST['staff_username'] ?? '');
            $staffEmail = trim($_POST['staff_email'] ?? '');
            $staffPassword = $_POST['staff_password'] ?? '';
            $staffConfirm = $_POST['staff_confirm_password'] ?? '';

            if (empty($staffFullname) || empty($staffUsername) || empty($staffEmail) || empty($staffPassword)) {
                $error = 'All staff fields are required.';
            } elseif ($staffPassword !== $staffConfirm) {
                $error = 'Staff passwords do not match.';
            } elseif (strlen($staffPassword) < 6) {
                $error = 'Staff password must be at least 6 characters.';
            } else {
                try {
                    $checkStmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1");
                    $checkStmt->execute([$staffUsername, $staffEmail]);
                    if ($checkStmt->fetch()) {
                        $error = 'Username or email is already in use.';
                    } else {
                        $insertStmt = $pdo->prepare("
                            INSERT INTO admins (username, password, fullname, email, role)
                            VALUES (:username, :password, :fullname, :email, 'staff')
                        ");
                        $insertStmt->execute([
                            ':username' => $staffUsername,
                            ':password' => password_hash($staffPassword, PASSWORD_DEFAULT),
                            ':fullname' => $staffFullname,
                            ':email' => $staffEmail,
                        ]);
                        $newStaffId = (int) $pdo->lastInsertId();
                        saveStaffPermissions($newStaffId, getDefaultStaffPermissionKeys());
                        try {
                            $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'Settings', ?)");
                            $logStmt->execute([$_SESSION['admin_id'], 'Added staff account: ' . $staffUsername]);
                        } catch (Exception $e) {
                        }
                        $success = 'Staff account created successfully.';
                        header('Location: settings.php?tab=staff&staff_id=' . $newStaffId);
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Error creating staff account: ' . $e->getMessage();
                }
            }
        }
    }

    // Handle save staff permissions (admin only)
    if (isset($_POST['save_staff_permissions'])) {
        if (!isAdmin()) {
            $error = 'You do not have permission to update staff permissions.';
        } else {
            $staffId = (int) ($_POST['staff_id'] ?? 0);
            $permissionKeys = $_POST['permissions'] ?? [];
            if (!is_array($permissionKeys)) {
                $permissionKeys = [];
            }
            try {
                $checkStmt = $pdo->prepare("SELECT id, fullname FROM admins WHERE id = ? AND role = 'staff' LIMIT 1");
                $checkStmt->execute([$staffId]);
                $staffUser = $checkStmt->fetch();
                if (!$staffUser) {
                    $error = 'Staff account not found.';
                } elseif (empty($permissionKeys)) {
                    $error = 'Select at least one permission for this staff member.';
                } else {
                    saveStaffPermissions($staffId, $permissionKeys);
                    try {
                        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'Settings', ?)");
                        $logStmt->execute([$_SESSION['admin_id'], 'Updated permissions for staff: ' . $staffUser['fullname']]);
                    } catch (Exception $e) {
                    }
                    $success = 'Permissions saved for ' . htmlspecialchars($staffUser['fullname']) . '.';
                    header('Location: settings.php?tab=staff&staff_id=' . $staffId);
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Error saving permissions: ' . $e->getMessage();
            }
        }
    }

    // Handle delete staff account (admin only)
    if (isset($_POST['delete_staff'])) {
        if (!isAdmin()) {
            $error = 'You do not have permission to remove staff accounts.';
        } else {
            $staffId = (int) ($_POST['staff_id'] ?? 0);
            if ($staffId === (int) $_SESSION['admin_id']) {
                $error = 'You cannot delete your own account.';
            } elseif ($staffId > 0) {
                try {
                    $checkStmt = $pdo->prepare("SELECT id, username, role FROM admins WHERE id = ? LIMIT 1");
                    $checkStmt->execute([$staffId]);
                    $staffUser = $checkStmt->fetch();
                    if (!$staffUser || $staffUser['role'] !== 'staff') {
                        $error = 'Only staff accounts can be removed here.';
                    } else {
                        $delStmt = $pdo->prepare("DELETE FROM admins WHERE id = ? AND role = 'staff'");
                        $delStmt->execute([$staffId]);
                        $success = 'Staff account removed successfully.';
                    }
                } catch (PDOException $e) {
                    $error = 'Error removing staff account: ' . $e->getMessage();
                }
            }
        }
    }

    // Handle email settings update
    if (isset($_POST['update_email_settings'])) {
        if (!isAdmin()) {
            $error = 'You do not have permission to change email settings.';
        } else {
        $smtp_host = trim($_POST['smtp_host']);
        $smtp_port = trim($_POST['smtp_port']);
        $smtp_username = trim($_POST['smtp_username']);
        $smtp_password = trim($_POST['smtp_password']);
        $smtp_encryption = $_POST['smtp_encryption'];

        require_once __DIR__ . '/config/mail_helpers.php';
        $normalized = normalizeMailConfig([
            'smtp_host' => $smtp_host,
            'smtp_port' => $smtp_port,
            'smtp_username' => $smtp_username,
            'smtp_password' => $smtp_password,
            'smtp_encryption' => $smtp_encryption,
            'from_email' => $smtp_username,
            'from_name' => 'EVSU Book Borrowing System',
        ]);
        
        try {
            $esc = function ($v) {
                return str_replace(["\\", "'"], ["\\\\", "\\'"], $v);
            };
            $mailConfigContent = "<?php\n";
            $mailConfigContent .= "// Mail Configuration\n";
            $mailConfigContent .= "\$mail_config = [\n";
            $mailConfigContent .= "    'smtp_host' => '" . $esc($normalized['smtp_host']) . "',\n";
            $mailConfigContent .= "    'smtp_port' => '" . $esc($normalized['smtp_port']) . "',\n";
            $mailConfigContent .= "    'smtp_username' => '" . $esc($normalized['smtp_username']) . "',\n";
            $mailConfigContent .= "    'smtp_password' => '" . $esc($normalized['smtp_password']) . "',\n";
            $mailConfigContent .= "    'smtp_encryption' => '" . $esc($normalized['smtp_encryption']) . "',\n";
            $mailConfigContent .= "    'smtp_secure' => '" . $esc($normalized['smtp_secure']) . "',\n";
            $mailConfigContent .= "    'use_smtp' => true,\n";
            $mailConfigContent .= "    'smtp_auth' => true,\n";
            $mailConfigContent .= "    'from_email' => '" . $esc($normalized['from_email']) . "',\n";
            $mailConfigContent .= "    'from_name' => '" . $esc($normalized['from_name']) . "'\n";
            $mailConfigContent .= "];\n";
            
            file_put_contents('config/mail_config.php', $mailConfigContent);
            
            // Log activity
            try {
                $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'Settings', 'Updated email settings')");
                $logStmt->execute([$_SESSION['admin_id']]);
            } catch (Exception $e) {
                // Silently handle errors with logging
            }
            
            $success = 'Email settings updated successfully';
            
        } catch (Exception $e) {
            $error = 'Error updating email settings: ' . $e->getMessage();
        }
        }
    }
}

// Staff accounts list (admin only)
$staffAccounts = [];
$editingStaffId = 0;
$editingStaff = null;
$editingStaffPermissionKeys = [];
$permissionDefinitions = getStaffPermissionDefinitions();

if (isAdmin()) {
    ensureStaffPermissionsTable();
    $staffStmt = $pdo->query("SELECT id, username, fullname, email, role FROM admins WHERE role = 'staff' ORDER BY fullname");
    $staffAccounts = $staffStmt->fetchAll();

    $editingStaffId = (int) ($_GET['staff_id'] ?? 0);
    if ($editingStaffId > 0) {
        $stmt = $pdo->prepare("SELECT id, username, fullname, email FROM admins WHERE id = ? AND role = 'staff' LIMIT 1");
        $stmt->execute([$editingStaffId]);
        $editingStaff = $stmt->fetch();
        if ($editingStaff) {
            $editingStaffPermissionKeys = array_keys(getStaffPermissions($editingStaffId));
        } else {
            $editingStaffId = 0;
        }
    }
}

// Load current email settings
if (file_exists('config/mail_config.php')) {
    include_once 'config/mail_config.php';
}

// Settings tabs
$settingsTabs = isAdmin()
    ? [
        'account' => ['label' => 'My Account', 'icon' => 'user'],
        'staff' => ['label' => 'Staff Accounts', 'icon' => 'user-shield'],
        'permissions' => ['label' => 'Role Permissions', 'icon' => 'key'],
        'email' => ['label' => 'Email Settings', 'icon' => 'envelope'],
    ]
    : [
        'account' => ['label' => 'My Account', 'icon' => 'user'],
        'permissions' => ['label' => 'My Permissions', 'icon' => 'key'],
    ];

$activeTab = $_GET['tab'] ?? 'account';
if (!array_key_exists($activeTab, $settingsTabs)) {
    $activeTab = 'account';
}

$permRole = $_GET['role'] ?? 'staff';
if (!in_array($permRole, ['admin', 'staff'], true)) {
    $permRole = 'staff';
}

$permissionsMatrix = getRolePermissionsMatrix();

// Include header
include 'includes/header.php';
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Settings</h2>
    <p class="text-gray-600 dark:text-gray-400"><?php echo isAdmin() ? 'Manage your account, staff, and system settings' : 'Manage your account settings'; ?></p>
</div>

<?php if ($success): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded dark:bg-green-900 dark:text-green-300">
        <div class="flex">
            <i class="fas fa-check-circle mr-2 mt-1"></i>
            <span><?php echo $success; ?></span>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded dark:bg-red-900 dark:text-red-300">
        <div class="flex">
            <i class="fas fa-exclamation-circle mr-2 mt-1"></i>
            <span><?php echo $error; ?></span>
        </div>
    </div>
<?php endif; ?>

<!-- Settings Tabs -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-md mb-6 overflow-hidden">
    <nav class="flex flex-wrap border-b border-gray-200 dark:border-gray-700" aria-label="Settings tabs">
        <?php foreach ($settingsTabs as $tabId => $tabInfo): ?>
        <a href="settings.php?tab=<?php echo urlencode($tabId); ?><?php echo ($tabId === 'permissions' && isAdmin()) ? '&role=staff' : ''; ?>"
           class="flex items-center px-4 py-3 text-sm font-medium border-b-2 transition-colors <?php echo $activeTab === $tabId
               ? 'border-primary-600 text-primary-600 dark:text-primary-400 dark:border-primary-400 bg-primary-50 dark:bg-primary-900/20'
               : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:border-gray-300'; ?>">
            <i class="fas fa-<?php echo $tabInfo['icon']; ?> mr-2"></i>
            <?php echo htmlspecialchars($tabInfo['label']); ?>
        </a>
        <?php endforeach; ?>
    </nav>
</div>

<!-- Tab: My Account -->
<div id="tab-account" class="<?php echo $activeTab !== 'account' ? 'hidden' : ''; ?>">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Profile Image -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Profile Image</h3>
        
        <div class="flex flex-col items-center">
            <div class="w-32 h-32 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden mb-4 flex items-center justify-center">
                <?php if (!empty($admin['profile_image']) && file_exists($admin['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Profile" class="w-full h-full object-cover">
                <?php else: ?>
                    <i class="fas fa-user text-gray-400 dark:text-gray-500 text-5xl"></i>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="settings.php?tab=account" enctype="multipart/form-data" class="w-full">
                <div class="mb-4">
                    <label for="profile_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Image</label>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*" 
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Maximum file size: 5MB. Supported formats: JPG, PNG, GIF.</p>
                </div>
                
                <div class="mt-4">
                    <button type="submit" name="update_profile_image" 
                            class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition duration-300">
                        Upload Image
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Profile Settings -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Profile Settings</h3>
        
        <form method="POST" action="settings.php?tab=account">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" 
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" 
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mt-6 mb-2">
                <h4 class="font-medium text-gray-800 dark:text-white">Change Password</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">Leave blank if you don't want to change</p>
            </div>
            
            <div class="mb-4">
                <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
                <input type="password" id="current_password" name="current_password" 
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mb-4">
                <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                <input type="password" id="new_password" name="new_password" 
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mb-4">
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mt-6">
                <button type="submit" name="update_profile" 
                        class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition duration-300">
                    Update Profile
                </button>
            </div>
        </form>
    </div>
</div>
</div>

<?php if (isAdmin()): ?>
<!-- Tab: Staff Accounts -->
<div id="tab-staff" class="<?php echo $activeTab !== 'staff' ? 'hidden' : ''; ?>">
    <!-- Staff Management -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Staff Accounts</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Create staff accounts, then configure which features each staff member can access.</p>

        <form method="POST" action="settings.php?tab=staff" class="mb-8 border-b border-gray-200 dark:border-gray-700 pb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="staff_fullname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                    <input type="text" id="staff_fullname" name="staff_fullname" required
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label for="staff_username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                    <input type="text" id="staff_username" name="staff_username" required
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label for="staff_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                    <input type="email" id="staff_email" name="staff_email" required
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div></div>
                <div>
                    <label for="staff_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                    <input type="password" id="staff_password" name="staff_password" required minlength="6"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label for="staff_confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password</label>
                    <input type="password" id="staff_confirm_password" name="staff_confirm_password" required minlength="6"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" name="add_staff"
                        class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition duration-300">
                    <i class="fas fa-user-plus mr-1"></i> Add Staff
                </button>
            </div>
        </form>

        <?php if (count($staffAccounts) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Name</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Username</th>
                        <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Email</th>
                        <th class="px-4 py-2 text-center text-gray-600 dark:text-gray-300">Permissions</th>
                        <th class="px-4 py-2 text-right text-gray-600 dark:text-gray-300">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($staffAccounts as $staff): ?>
                    <tr>
                        <td class="px-4 py-3 text-gray-800 dark:text-white"><?php echo htmlspecialchars($staff['fullname']); ?></td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($staff['username']); ?></td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($staff['email']); ?></td>
                        <td class="px-4 py-3 text-center">
                            <a href="settings.php?tab=staff&staff_id=<?php echo (int) $staff['id']; ?>"
                               class="inline-flex items-center px-3 py-1 text-sm rounded-lg <?php echo $editingStaffId === (int) $staff['id']
                                   ? 'bg-primary-600 text-white'
                                   : 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300 hover:bg-primary-200 dark:hover:bg-primary-800'; ?>">
                                <i class="fas fa-key mr-1"></i> Configure
                            </a>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="settings.php?tab=staff" class="inline" onsubmit="return confirm('Remove this staff account?');">
                                <input type="hidden" name="staff_id" value="<?php echo (int) $staff['id']; ?>">
                                <button type="submit" name="delete_staff" class="text-red-600 hover:text-red-800 dark:text-red-400 text-sm">
                                    <i class="fas fa-trash mr-1"></i> Remove
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-sm text-gray-500 dark:text-gray-400">No staff accounts yet. Add one using the form above.</p>
        <?php endif; ?>

        <?php if ($editingStaff): ?>
        <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
            <h4 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">
                Permissions for <?php echo htmlspecialchars($editingStaff['fullname']); ?>
            </h4>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                @<?php echo htmlspecialchars($editingStaff['username']); ?> — check each permission this staff member should have.
            </p>

            <form method="POST" action="settings.php?tab=staff&staff_id=<?php echo (int) $editingStaffId; ?>">
                <input type="hidden" name="staff_id" value="<?php echo (int) $editingStaffId; ?>">

                <div class="space-y-4 mb-6">
                    <?php foreach ($permissionDefinitions as $group): ?>
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700/50 flex items-center justify-between">
                            <span class="font-medium text-gray-800 dark:text-white">
                                <i class="fas fa-<?php echo htmlspecialchars($group['icon']); ?> text-primary-600 dark:text-primary-400 mr-2"></i>
                                <?php echo htmlspecialchars($group['group']); ?>
                            </span>
                            <button type="button" class="text-xs text-primary-600 dark:text-primary-400 hover:underline select-group-all"
                                    data-group="<?php echo htmlspecialchars($group['group']); ?>">Select all</button>
                        </div>
                        <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3" data-permission-group="<?php echo htmlspecialchars($group['group']); ?>">
                            <?php foreach ($group['permissions'] as $perm): ?>
                            <label class="flex items-start space-x-3 cursor-pointer p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($perm['key']); ?>"
                                       class="mt-1 h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 staff-perm-checkbox"
                                       data-group="<?php echo htmlspecialchars($group['group']); ?>"
                                       <?php echo in_array($perm['key'], $editingStaffPermissionKeys, true) ? 'checked' : ''; ?>>
                                <span class="text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($perm['label']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="save_staff_permissions"
                            class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition duration-300">
                        <i class="fas fa-save mr-1"></i> Save Permissions
                    </button>
                    <button type="button" id="apply-default-permissions"
                            class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white font-medium rounded-lg transition duration-300">
                        Reset to defaults
                    </button>
                    <a href="settings.php?tab=staff" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:underline">Cancel</a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab: Role Permissions -->
<div id="tab-permissions" class="<?php echo $activeTab !== 'permissions' ? 'hidden' : ''; ?>">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">
                <?php echo isAdmin() ? 'Role Permissions' : 'My Permissions'; ?>
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                <?php echo isAdmin()
                    ? 'Default staff role overview. Configure each staff member individually under Staff Accounts.'
                    : 'Permissions assigned to your account by an administrator.'; ?>
            </p>
        </div>

        <?php if (isAdmin()): ?>
        <nav class="flex flex-wrap gap-2 mb-6 p-1 bg-gray-100 dark:bg-gray-700 rounded-lg w-fit" aria-label="Role type">
            <a href="settings.php?tab=permissions&role=admin"
               class="px-4 py-2 text-sm font-medium rounded-md transition-colors <?php echo $permRole === 'admin'
                   ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-sm'
                   : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-user-cog mr-1"></i> Admin Role
            </a>
            <a href="settings.php?tab=permissions&role=staff"
               class="px-4 py-2 text-sm font-medium rounded-md transition-colors <?php echo $permRole === 'staff'
                   ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-sm'
                   : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'; ?>">
                <i class="fas fa-user-tie mr-1"></i> Staff Role
            </a>
        </nav>

        <div class="mb-6 p-4 rounded-lg <?php echo $permRole === 'admin'
            ? 'bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800'
            : 'bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800'; ?>">
            <div class="flex items-start">
                <i class="fas fa-<?php echo $permRole === 'admin' ? 'user-cog text-primary-600 dark:text-primary-400' : 'user-tie text-amber-600 dark:text-amber-400'; ?> text-xl mr-3 mt-0.5"></i>
                <div>
                    <p class="font-semibold text-gray-800 dark:text-white">
                        <?php echo $permRole === 'admin' ? 'Administrator' : 'Staff'; ?> permissions
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        <?php echo $permRole === 'admin'
                            ? 'Full access to manage the library, books, members, staff accounts, and system settings.'
                            : 'Focused on daily operations: borrowing, returning, members, penalties, and viewing records.'; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="mb-6 p-4 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800">
            <div class="flex items-start">
                <i class="fas fa-user-tie text-amber-600 dark:text-amber-400 text-xl mr-3 mt-0.5"></i>
                <div>
                    <p class="font-semibold text-gray-800 dark:text-white">Staff account</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">You are logged in as staff. Contact an administrator if you need additional access.</p>
                </div>
            </div>
        </div>
        <?php
        $permRole = 'staff';
        endif;
        ?>

        <div class="space-y-6">
            <?php foreach ($permissionDefinitions as $group): ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex items-center">
                    <i class="fas fa-<?php echo htmlspecialchars($group['icon']); ?> text-primary-600 dark:text-primary-400 mr-2"></i>
                    <h4 class="font-semibold text-gray-800 dark:text-white"><?php echo htmlspecialchars($group['group']); ?></h4>
                </div>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($group['permissions'] as $perm): ?>
                    <li class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <span class="text-sm text-gray-700 dark:text-gray-300"><?php echo htmlspecialchars($perm['label']); ?></span>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            <?php if (isAdmin() && $permRole === 'admin'): ?>
                                <?php echo renderPermissionBadge(true); ?>
                            <?php elseif (isAdmin() && $permRole === 'staff'): ?>
                                <?php echo renderPermissionBadge(!empty($perm['default'])); ?>
                            <?php else: ?>
                                <?php echo renderPermissionBadge(staffHasPermission($perm['key'])); ?>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (isAdmin()): ?>
        <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-white mb-4">Side-by-side comparison</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-600 dark:text-gray-300">Permission</th>
                            <th class="px-4 py-2 text-center text-gray-600 dark:text-gray-300">Admin</th>
                            <th class="px-4 py-2 text-center text-gray-600 dark:text-gray-300">Staff</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($permissionDefinitions as $group): ?>
                            <?php foreach ($group['permissions'] as $perm): ?>
                            <tr class="bg-white dark:bg-gray-800">
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                    <span class="text-xs text-gray-500 dark:text-gray-500 block"><?php echo htmlspecialchars($group['group']); ?></span>
                                    <?php echo htmlspecialchars($perm['label']); ?>
                                </td>
                                <td class="px-4 py-2 text-center"><?php echo renderPermissionBadge(true); ?></td>
                                <td class="px-4 py-2 text-center"><?php echo renderPermissionBadge(!empty($perm['default'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tab: Email Settings -->
<div id="tab-email" class="<?php echo $activeTab !== 'email' ? 'hidden' : ''; ?>">
    <!-- Email Settings -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Email Settings</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Gmail: use port <strong>465</strong> with <strong>SSL</strong>, or port <strong>587</strong> with <strong>TLS</strong>.
            Use a <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="text-primary-600 underline">Google App Password</a> (not your normal Gmail password).
        </p>
        
        <form method="POST" action="settings.php?tab=email">
            <div class="mb-4">
                <label for="smtp_host" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SMTP Server</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($mail_config['smtp_host'] ?? ''); ?>" 
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mb-4">
                <label for="smtp_port" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">SMTP Port</label>
                <input type="text" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($mail_config['smtp_port'] ?? ''); ?>" 
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mb-4">
                <label for="smtp_encryption" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Encryption</label>
                <select id="smtp_encryption" name="smtp_encryption" 
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    <option value="tls" <?php echo (isset($mail_config['smtp_encryption']) && $mail_config['smtp_encryption'] === 'tls') ? 'selected' : ''; ?>>TLS</option>
                    <option value="ssl" <?php echo (isset($mail_config['smtp_encryption']) && $mail_config['smtp_encryption'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo (isset($mail_config['smtp_encryption']) && $mail_config['smtp_encryption'] === 'none') ? 'selected' : ''; ?>>None</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label for="smtp_username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username / Email</label>
                <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($mail_config['smtp_username'] ?? ''); ?>" 
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mb-4">
                <label for="smtp_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($mail_config['smtp_password'] ?? ''); ?>" 
                       class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
            </div>
            
            <div class="mt-2 mb-4">
                <button type="button" id="test-email-btn" 
                        class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded transition duration-300">
                    Test Connection
                </button>
                <span id="test-email-result" class="ml-2 text-sm"></span>
            </div>
            
            <div class="mt-6">
                <button type="submit" name="update_email_settings" 
                        class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg transition duration-300">
                    Save Email Settings
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    const defaultStaffPermissions = <?php echo json_encode(getDefaultStaffPermissionKeys()); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.select-group-all').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const group = btn.getAttribute('data-group');
                document.querySelectorAll('.staff-perm-checkbox[data-group="' + group + '"]').forEach(function(cb) {
                    cb.checked = true;
                });
            });
        });

        const applyDefaultsBtn = document.getElementById('apply-default-permissions');
        if (applyDefaultsBtn) {
            applyDefaultsBtn.addEventListener('click', function() {
                document.querySelectorAll('.staff-perm-checkbox').forEach(function(cb) {
                    cb.checked = defaultStaffPermissions.includes(cb.value);
                });
            });
        }

        // Test email connection
        const testEmailBtn = document.getElementById('test-email-btn');
        const testEmailResult = document.getElementById('test-email-result');
        
        const smtpEncryption = document.getElementById('smtp_encryption');
        const smtpPort = document.getElementById('smtp_port');
        if (smtpEncryption && smtpPort) {
            smtpEncryption.addEventListener('change', function() {
                if (smtpEncryption.value === 'ssl') {
                    smtpPort.value = '465';
                } else if (smtpEncryption.value === 'tls') {
                    smtpPort.value = '587';
                }
            });
        }

        if (testEmailBtn) {
            testEmailBtn.addEventListener('click', function() {
                testEmailResult.textContent = 'Testing connection...';
                testEmailResult.className = 'ml-2 text-sm text-blue-600 dark:text-blue-400';
                
                // Collect form data
                const formData = new FormData();
                formData.append('smtp_host', document.getElementById('smtp_host').value);
                formData.append('smtp_port', document.getElementById('smtp_port').value);
                formData.append('smtp_encryption', document.getElementById('smtp_encryption').value);
                formData.append('smtp_username', document.getElementById('smtp_username').value);
                formData.append('smtp_password', document.getElementById('smtp_password').value);
                
                // Send request
                fetch('test_email.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let msg = data.message || 'Connection successful!';
                        if (data.port && data.encryption) {
                            document.getElementById('smtp_port').value = data.port;
                            document.getElementById('smtp_encryption').value = data.encryption;
                            msg += ' — settings updated to port ' + data.port + ' / ' + data.encryption;
                        }
                        testEmailResult.textContent = msg;
                        testEmailResult.className = 'ml-2 text-sm text-green-600 dark:text-green-400';
                    } else {
                        testEmailResult.textContent = 'Connection failed: ' + data.message;
                        testEmailResult.className = 'ml-2 text-sm text-red-600 dark:text-red-400';
                    }
                })
                .catch(error => {
                    testEmailResult.textContent = 'Error testing connection';
                    testEmailResult.className = 'ml-2 text-sm text-red-600 dark:text-red-400';
                });
            });
        }
    });
</script>

<?php
// Include footer
include 'includes/footer.php';
?> 