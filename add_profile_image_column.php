<?php
// Include database connection
require_once 'config/db_connect.php';

try {
    // Check if the column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM admins LIKE 'profile_image'");
    $columnExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnExists) {
        // Add the profile_image column
        $pdo->exec("ALTER TABLE admins ADD COLUMN profile_image VARCHAR(255) NULL AFTER email");
        echo "Success: profile_image column has been added to the admins table.";
    } else {
        echo "Info: profile_image column already exists in the admins table.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 