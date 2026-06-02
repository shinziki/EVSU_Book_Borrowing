<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check for theme preference in cookie
$theme = $_COOKIE['theme'] ?? '';
$darkModeClass = ($theme === 'dark') ? 'dark' : '';

// Determine current page
$currentPage = basename($_SERVER['PHP_SELF']);

if (!function_exists('isAdmin')) {
    require_once dirname(__DIR__) . '/config/functions.php';
}

$navIsAdmin = function_exists('isAdmin') && isAdmin();
$navIsStaff = function_exists('isStaff') && isStaff();
$navRoleLabel = $navIsStaff ? 'Staff' : 'Admin';

// Staff activity notifications (unread count)
$notificationCount = 0;
$recentNotifications = [];
if (function_exists('isLoggedIn') && isLoggedIn()
    && ($navIsAdmin || (function_exists('staffHasPermission') && staffHasPermission('notifications.view')))) {
    if (!isset($pdo)) {
        require_once dirname(__DIR__) . '/config/db_connect.php';
    }
    if (function_exists('getUnreadNotificationCount')) {
        $notificationCount = getUnreadNotificationCount();
        $recentNotifications = getRecentStaffNotifications(8);
    }
}

// Get admin profile image
$profileImage = null;
if (isset($_SESSION['admin_id'])) {
    try {
        require_once 'config/db_connect.php';
        $stmt = $pdo->prepare("SELECT profile_image FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $result = $stmt->fetch();
        if ($result && !empty($result['profile_image']) && file_exists($result['profile_image'])) {
            $profileImage = $result['profile_image'];
        }
    } catch (Exception $e) {
        // Silently fail - default image will be used
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full <?php echo $darkModeClass; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVSU Book Borrowing System - Library Borrowing System</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
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
    <style>
        .barcode-canvas {
            max-width: 100%;
            height: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            padding: 0.5rem;
            background: white;
        }
        .dark .barcode-canvas {
            border-color: #334155;
        }
        .sidebar {
            transition: all 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 250px;
            z-index: 40;
            padding-top: 0;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 0 0 20px rgba(0,0,0,0.3);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0 !important;
            }
        }
        @media (min-width: 769px) {
            .sidebar {
                position: fixed;
            }
            .main-content {
                margin-left: 250px;
            }
        }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 30;
            display: none;
        }
        .overlay.active {
            display: block;
        }
        .grid-card {
            transition: all 0.3s ease;
        }
        .grid-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .dark .grid-card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        /* Responsive tables */
        @media (max-width: 640px) {
            .responsive-table thead {
                display: none;
            }
            .responsive-table tr {
                display: block;
                margin-bottom: 1.5rem;
                border-radius: 0.5rem;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .responsive-table td {
                display: flex;
                text-align: right;
                padding: 0.75rem;
                border-bottom: 1px solid rgba(229, 231, 235, 0.5);
            }
            .responsive-table td::before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: auto;
                text-align: left;
            }
            .responsive-table td:last-child {
                border-bottom: 0;
            }
            .dark .responsive-table tr {
                box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            }
            .dark .responsive-table td {
                border-bottom: 1px solid rgba(55, 65, 81, 0.5);
            }
        }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <div id="main-app" class="h-full flex flex-col">
        <!-- Top Navigation -->
        <header class="bg-[#a91515] shadow-sm fixed top-0 left-0 right-0 z-30">
            <div class="flex items-center justify-between px-4 py-3">
                <!-- Mobile Menu Toggle -->
                <div class="md:hidden">
                    <button id="menu-toggle" class="text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
                
                <!-- Logo and Title (Perfectly centered) -->
                <div class="flex items-center justify-center flex-1">
                    <a href="index.php" class="flex items-center">
                        <div class="bg-white/15 p-2 rounded-full mr-3 w-10 h-10 flex items-center justify-center">
                            <i class="fas fa-book-reader text-white text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white leading-none">EVSU Book Borrowing System</h1>
                            <p class="text-xs text-red-100 hidden md:block mt-1">Library Borrowing System</p>
                        </div>
                    </a>
                </div>
                
                <!-- Theme Toggle and User Profile -->
                <div class="flex items-center space-x-4">
                    <!-- Theme Toggle -->
                    <button id="theme-toggle" class="p-2 rounded-full text-white hover:bg-[#8f1212]">
                        <i class="fas fa-moon dark:hidden"></i>
                        <i class="fas fa-sun hidden dark:block"></i>
                    </button>
                    
                    <?php if (function_exists('isLoggedIn') && isLoggedIn() && ($navIsAdmin || staffHasPermission('notifications.view'))): ?>
                    <!-- Notification Icon with Dropdown -->
                    <div class="relative">
                        <button id="notification-menu-button" class="p-2 rounded-full text-white hover:bg-[#8f1212] relative">
                        <i class="fas fa-bell"></i>
                            <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center <?php echo $notificationCount > 0 ? '' : 'hidden'; ?>"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                        </button>
                        <!-- Notification Dropdown Menu (hidden by default) -->
                        <div id="notification-dropdown" class="hidden absolute right-0 mt-2 w-80 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-50">
                            <div class="py-1">
                                <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Activity</h3>
                                        <a href="notifications.php" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">View all</a>
                                    </div>
                                </div>
                                
                                <div id="notification-list" class="max-h-56 overflow-y-auto" data-last-id="<?php echo !empty($recentNotifications) ? (int) $recentNotifications[0]['id'] : 0; ?>">
                                <?php if (!empty($recentNotifications)): ?>
                                        <?php foreach ($recentNotifications as $notification):
                                            $meta = getNotificationTypeMeta($notification['type'] ?? 'System');
                                        ?>
                                            <a href="notifications.php?action=mark_read&id=<?php echo (int) $notification['id']; ?>" 
                                               class="notification-item block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo empty($notification['is_read']) ? 'bg-blue-50 dark:bg-blue-900/30' : ''; ?>"
                                               data-id="<?php echo (int) $notification['id']; ?>">
                                                <div class="flex items-start">
                                                    <div class="mr-2 mt-0.5">
                                                        <i class="fas fa-<?php echo htmlspecialchars($meta['icon']); ?> <?php echo htmlspecialchars($meta['class']); ?>"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-xs text-gray-900 dark:text-white line-clamp-2">
                                                            <?php echo htmlspecialchars($notification['message']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                            <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?>
                                                        </p>
                                                    </div>
                                                    <?php if (empty($notification['is_read'])): ?>
                                                        <div class="ml-2">
                                                            <div class="w-2 h-2 bg-blue-600 dark:bg-blue-400 rounded-full"></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                <?php else: ?>
                                    <div id="notification-empty" class="p-4 text-center">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No activity yet</p>
                                    </div>
                                <?php endif; ?>
                                </div>
                                
                                <div class="border-t border-gray-200 dark:border-gray-700 mt-1">
                                    <a href="notifications.php" class="block w-full px-4 py-2 text-sm text-center text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <i class="fas fa-bell mr-1"></i> Manage Notifications
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- User Dropdown Menu -->
                    <div class="relative">
                        <button id="user-menu-button" class="p-2 rounded-full text-white hover:bg-[#8f1212] overflow-hidden">
                            <?php if (!empty($profileImage)): ?>
                                <div class="w-6 h-6 rounded-full overflow-hidden">
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="w-full h-full object-cover">
                                </div>
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </button>
                        <!-- Dropdown Menu (hidden by default) -->
                        <div id="user-dropdown" class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 z-50">
                            <div class="py-1">
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-cog mr-2"></i> Account Settings
                                </a>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="pt-16 flex flex-1 overflow-hidden">
            <!-- Mobile overlay -->
            <div id="sidebar-overlay" class="overlay md:hidden"></div>
            
            <!-- Sidebar -->
            <aside id="sidebar" class="sidebar w-64 bg-[#222d31] shadow-md md:shadow-none md:translate-x-0 flex flex-col">
                <!-- Welcome message positioned to exactly match header height -->
                <div class="h-[60px] flex items-center px-4 bg-[#222d31]">
                    <div class="flex items-center">
                        <div class="bg-white/15 p-2 rounded-full mr-3 flex-shrink-0 w-8 h-8 flex items-center justify-center">
                            <?php if (!empty($profileImage)): ?>
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="w-full h-full object-cover rounded-full">
                            <?php else: ?>
                                <i class="fas fa-user text-white text-sm"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white leading-none">Welcome <?php echo htmlspecialchars($navRoleLabel); ?>!</h3>
                            <p class="text-xs text-gray-300 mt-1"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'User'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Main navigation -->
                <div class="flex-1">
                    <nav class="px-4 py-4">
                        <?php
                        $navLinkClass = function ($page) use ($currentPage) {
                            return ($currentPage === $page)
                                ? 'flex items-center p-3 rounded-lg text-white bg-[#a91515]'
                                : 'section-link flex items-center p-3 rounded-lg text-gray-200 hover:bg-[#2c3a3f]';
                        };
                        ?>
                        <ul class="space-y-1">
                        <?php if ($navIsAdmin || staffHasPermission('dashboard.view')): ?>
                        <li>
                            <a href="dashboard.php" class="<?php echo $navLinkClass('dashboard.php'); ?>">
                                <i class="fas fa-chart-bar w-5 mr-3 text-center"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsAdmin || staffHasPermission('metrics.view') || staffHasPermission('dashboard.view')): ?>
                        <li>
                            <a href="book_metrics.php" class="<?php echo $navLinkClass('book_metrics.php'); ?>">
                                <i class="fas fa-chart-line w-5 mr-3 text-center"></i>
                                <span>Book Metrics</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsAdmin || staffHasPermission('books.view')): ?>
                        <li>
                            <a href="books.php" class="<?php echo $navLinkClass('books.php'); ?>">
                                <i class="fas fa-book w-5 mr-3 text-center"></i>
                                <span><?php echo $navIsStaff ? 'Available Books' : 'Books'; ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsAdmin || staffHasPermission('members.view')): ?>
                        <li>
                            <a href="members.php" class="<?php echo $navLinkClass('members.php'); ?>">
                                <i class="fas fa-users w-5 mr-3 text-center"></i>
                                <span>Members</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsStaff && staffHasPermission('borrow.process')): ?>
                        <li>
                            <a href="borrow.php" class="<?php echo $navLinkClass('borrow.php'); ?>">
                                <i class="fas fa-hand-holding w-5 mr-3 text-center"></i>
                                <span>Borrow Book</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsStaff && staffHasPermission('return.process')): ?>
                        <li>
                            <a href="return.php" class="<?php echo $navLinkClass('return.php'); ?>">
                                <i class="fas fa-undo w-5 mr-3 text-center"></i>
                                <span>Return Book</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsAdmin || staffHasPermission('transactions.view')): ?>
                        <li>
                            <a href="transactions.php" class="<?php echo $navLinkClass('transactions.php'); ?>">
                                <i class="fas fa-exchange-alt w-5 mr-3 text-center"></i>
                                <span><?php echo $navIsStaff ? 'Borrow & Return History' : 'Transactions'; ?></span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsAdmin || staffHasPermission('penalties.record')): ?>
                        <li>
                            <a href="penalties_record.php" class="<?php echo $navLinkClass('penalties_record.php'); ?>">
                                <i class="fas fa-file-invoice-dollar w-5 mr-3 text-center"></i>
                                <span>Penalties Record</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsAdmin || staffHasPermission('overdue.view')): ?>
                        <li>
                            <a href="overdue.php" class="<?php echo $navLinkClass('overdue.php'); ?>">
                                <i class="fas fa-exclamation-circle w-5 mr-3 text-center"></i>
                                <span>Overdue Books</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsAdmin || staffHasPermission('notifications.view')): ?>
                        <li>
                            <a href="notifications.php" class="<?php echo $navLinkClass('notifications.php'); ?>">
                                <i class="fas fa-bell w-5 mr-3 text-center"></i>
                                <span>Notifications</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($navIsAdmin): ?>
                        <li>
                            <a href="reports.php" class="<?php echo $navLinkClass('reports.php'); ?>">
                                <i class="fas fa-file-pdf w-5 mr-3 text-center"></i>
                                <span>Annual Reports</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                
                <!-- Footer section for additional links if needed -->
                <div class="p-4 border-t border-white/10">
                    <div class="text-xs text-center text-gray-300">EVSU Book Borrowing System © 2026</div>
                    </div>
            </aside>
            
            <!-- Main Content -->
            <main class="main-content flex-1 overflow-y-auto p-4 md:p-6 bg-gray-50 dark:bg-gray-900"><?php displayFlashMessage(); ?> 

    <script>
        // ... existing code ...
        
        // User dropdown menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const userMenuButton = document.getElementById('user-menu-button');
            const userDropdown = document.getElementById('user-dropdown');
            
            if (userMenuButton && userDropdown) {
                userMenuButton.addEventListener('click', function() {
                    userDropdown.classList.toggle('hidden');
                    // Hide notification dropdown if it's open
                    if (notificationDropdown) {
                        notificationDropdown.classList.add('hidden');
                    }
                });
                
                // Close the dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userMenuButton.contains(event.target) && !userDropdown.contains(event.target)) {
                        userDropdown.classList.add('hidden');
                    }
                });
            }
            
            // Notification dropdown menu toggle
            const notificationMenuButton = document.getElementById('notification-menu-button');
            const notificationDropdown = document.getElementById('notification-dropdown');
            
            if (notificationMenuButton && notificationDropdown) {
                notificationMenuButton.addEventListener('click', function() {
                    notificationDropdown.classList.toggle('hidden');
                    // Hide user dropdown if it's open
                    if (userDropdown) {
                        userDropdown.classList.add('hidden');
                    }
                });

                // Close the dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    if (!notificationMenuButton.contains(event.target) && !notificationDropdown.contains(event.target)) {
                        notificationDropdown.classList.add('hidden');
                    }
                });

                // Real-time staff activity notifications (poll every 15s)
                const notificationList = document.getElementById('notification-list');
                const notificationBadge = document.getElementById('notification-badge');
                let lastNotificationId = notificationList ? parseInt(notificationList.dataset.lastId || '0', 10) : 0;

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text == null ? '' : String(text);
                    return div.innerHTML;
                }

                function renderNotificationItem(item) {
                    const unreadClass = !item.is_read ? 'bg-blue-50 dark:bg-blue-900/30' : '';
                    const dot = !item.is_read ? '<div class="ml-2"><div class="w-2 h-2 bg-blue-600 dark:bg-blue-400 rounded-full"></div></div>' : '';
                    return `
                        <a href="${escapeHtml(item.mark_read_url)}"
                           class="notification-item block px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 ${unreadClass}"
                           data-id="${item.id}">
                            <div class="flex items-start">
                                <div class="mr-2 mt-0.5">
                                    <i class="fas fa-${escapeHtml(item.icon)} ${escapeHtml(item.icon_class)}"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-xs text-gray-900 dark:text-white line-clamp-2">${escapeHtml(item.message)}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(item.created_at_label)}</p>
                                </div>
                                ${dot}
                            </div>
                        </a>`;
                }

                function updateNotificationBadge(count) {
                    if (!notificationBadge) return;
                    if (count > 0) {
                        notificationBadge.textContent = count > 9 ? '9+' : String(count);
                        notificationBadge.classList.remove('hidden');
                    } else {
                        notificationBadge.classList.add('hidden');
                    }
                }

                async function pollNotifications() {
                    try {
                        const url = 'notifications_feed.php?since_id=' + encodeURIComponent(lastNotificationId) + '&limit=10';
                        const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                        if (!res.ok) return;
                        const data = await res.json();
                        updateNotificationBadge(data.unread_count || 0);

                        if (!notificationList || !data.items || !data.items.length) return;

                        const emptyEl = document.getElementById('notification-empty');
                        if (emptyEl) emptyEl.remove();

                        data.items.forEach(function(item) {
                            if (notificationList.querySelector('[data-id="' + item.id + '"]')) return;
                            notificationList.insertAdjacentHTML('afterbegin', renderNotificationItem(item));
                            if (item.id > lastNotificationId) lastNotificationId = item.id;
                        });

                        notificationList.dataset.lastId = String(lastNotificationId);

                        const items = notificationList.querySelectorAll('.notification-item');
                        while (items.length > 12) {
                            items[items.length - 1].remove();
                        }
                    } catch (e) {
                        // silent fail for polling
                    }
                }

                pollNotifications();
                setInterval(pollNotifications, 15000);
            }
        });
    </script>
</body>
</html> 