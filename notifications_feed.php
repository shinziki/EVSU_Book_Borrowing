<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isAdmin() && !staffHasPermission('notifications.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$sinceId = (int) ($_GET['since_id'] ?? 0);
$limit = (int) ($_GET['limit'] ?? 10);
$items = getRecentStaffNotifications($limit, $sinceId);

// New-only poll returns ascending order for prepend logic
if ($sinceId > 0) {
    $items = array_reverse($items);
}

$formatted = [];
foreach ($items as $row) {
    $meta = getNotificationTypeMeta($row['type'] ?? 'System');
    $formatted[] = [
        'id' => (int) $row['id'],
        'type' => $row['type'],
        'message' => $row['message'],
        'is_read' => (bool) $row['is_read'],
        'created_at' => $row['created_at'],
        'created_at_label' => date('M j, g:i A', strtotime($row['created_at'])),
        'icon' => $meta['icon'],
        'icon_class' => $meta['class'],
        'mark_read_url' => 'notifications.php?action=mark_read&id=' . (int) $row['id'],
    ];
}

echo json_encode([
    'unread_count' => getUnreadNotificationCount(),
    'items' => $formatted,
    'server_time' => appNowString(),
]);
