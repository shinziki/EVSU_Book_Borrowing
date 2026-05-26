<?php
require_once 'config/db_connect.php';

try {
    // Add profile_image column to admins table if it doesn't exist
    $sql = "ALTER TABLE admins ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL";
    $pdo->exec($sql);
    
    echo "Profile image column added successfully or already exists!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 