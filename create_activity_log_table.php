<?php
require_once 'config/db_connect.php';

try {
    // Create activity_log table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES admins(id) ON DELETE CASCADE
    )";
    
    $pdo->exec($sql);
    
    echo "Activity log table created successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 