<?php
require_once 'config/db_connect.php';

// Update the admin email to the one you provided
$adminEmail = 'migsbacho04@gmail.com';
$adminName = 'Coffee Prince Admin';

try {
    $stmt = $pdo->prepare("UPDATE admins SET email = :email, fullname = :fullname WHERE username = 'admin'");
    $stmt->bindParam(':email', $adminEmail);
    $stmt->bindParam(':fullname', $adminName);
    
    if ($stmt->execute()) {
        echo "Admin email updated successfully to: " . htmlspecialchars($adminEmail);
    } else {
        echo "Failed to update admin email";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?> 