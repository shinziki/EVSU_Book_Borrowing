<?php
// Connect to database using the same credentials as in config/db_connect.php
require_once 'config/db_connect.php';

try {
    // Add is_read column if it doesn't exist
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS column_exists
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'coffee_prince_library'
        AND TABLE_NAME = 'notifications'
        AND COLUMN_NAME = 'is_read'
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ((int)$result['column_exists'] === 0) {
        // Add the column
        $pdo->exec("ALTER TABLE notifications ADD COLUMN is_read BOOLEAN DEFAULT FALSE");
        echo "Column 'is_read' added successfully to notifications table.<br>";
        
        // Update existing notifications
        $pdo->exec("UPDATE notifications SET is_read = TRUE WHERE created_at < NOW()");
        echo "Existing notifications marked as read.<br>";
    } else {
        echo "Column 'is_read' already exists in notifications table.<br>";
    }
    
    echo "Database update completed successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 