<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
    header('Location: ' . getDefaultLandingPage());
    exit;
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Check if username or password is empty
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        // Check credentials
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if ($user) {
            $passwordMatch = false;
            
            // Check if password is stored as plain text (for first login)
            if ($user['password'] === $password) {
                $passwordMatch = true;
                
                // Update password to hashed version
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE admins SET password = :password WHERE id = :id");
                $updateStmt->bindParam(':password', $hashedPassword);
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();
            } 
            // Check if password is hashed
            else if (password_verify($password, $user['password'])) {
                $passwordMatch = true;
            }
            
            if ($passwordMatch) {
                ensureAdminRoleColumn();
                $role = $user['role'] ?? 'admin';
                if (!in_array($role, ['admin', 'staff'], true)) {
                    $role = 'admin';
                }

                // Directly login the user - No 2FA
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_authenticated'] = true;
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = $role;
                $_SESSION['admin_fullname'] = $user['fullname'] ?? $user['username'];
                
                // Log successful login
                try {
                    $logStmt = $pdo->prepare("
                        INSERT INTO activity_log (user_id, action, description) 
                        VALUES (?, 'Login', 'Successfully logged in')
                    ");
                    $logStmt->execute([$user['id']]);
                } catch (PDOException $e) {
                    // Silently ignore if there's an issue with logging
                    error_log("Activity logging error: " . $e->getMessage());
                }
                
                header('Location: ' . getDefaultLandingPage());
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
    }
}

// Check for theme preference in cookie
$theme = $_COOKIE['theme'] ?? '';
$darkModeClass = ($theme === 'dark') ? 'dark' : '';
?>
<!DOCTYPE html>
<html lang="en" class="h-full <?php echo $darkModeClass; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EVSU Book Borrowing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full bg-gradient-to-br from-[#a91515] to-[#fafafa]">
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6">
            <div class="text-center mb-6">
                <div class="flex justify-center mb-4">
                    <div class="bg-primary-100 dark:bg-primary-900 p-3 rounded-full">
                        <i class="fas fa-book-reader text-primary-600 dark:text-primary-300 text-3xl"></i>
                    </div>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">EVSU Book Borrowing System</h1>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Library Borrowing System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 mb-4 rounded dark:bg-red-900 dark:text-red-300">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle mr-2 mt-0.5"></i>
                        <span class="text-sm"><?php echo $error; ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                    <input type="text" id="username" name="username" required 
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                    <input type="password" id="password" name="password" required 
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <button type="submit" 
                        class="w-full bg-[#a91515] hover:bg-[#8f1212] text-white font-semibold py-2 px-4 rounded-lg transition duration-300 transform hover:-translate-y-0.5">
                    Login
                </button>
            </form>
            
            <div class="mt-4 text-center">
                <a href="index.php" class="inline-flex items-center text-sm text-[#a91515] hover:underline mb-3">
                    <i class="fas fa-th-large mr-1"></i> Back to Portal Home
                </a>
                <button id="theme-toggle" class="inline-flex items-center p-2 text-xs text-gray-500 dark:text-gray-400">
                    <i class="fas fa-moon dark:hidden mr-1"></i>
                    <i class="fas fa-sun hidden dark:block mr-1"></i>
                    <span class="dark:hidden">Dark Mode</span>
                    <span class="hidden dark:block">Light Mode</span>
                </button>
                <div class="mt-3 text-xs text-gray-400 dark:text-gray-500">
                    EVSU Book Borrowing System © 2026
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme toggle functionality
        const themeToggle = document.getElementById('theme-toggle');
        themeToggle.addEventListener('click', () => {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                document.cookie = "theme=light; path=/; max-age=31536000"; // 1 year
            } else {
                document.documentElement.classList.add('dark');
                document.cookie = "theme=dark; path=/; max-age=31536000"; // 1 year
            }
        });
    </script>
</body>
</html> 