<?php
// Include database connection
require_once 'config/db_connect.php';

// Admin credentials we want to set
$username = 'admin';
$password = 'admin123';
$fullname = 'Administrator';
$email = 'admin@coffeeprincelibrary.com';

try {
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        // Admin exists, update password
        $updateStmt = $pdo->prepare("UPDATE admins SET password = :password WHERE id = :id");
        $updateStmt->bindParam(':password', $password);  // Store as plain text for simplicity
        $updateStmt->bindParam(':id', $user['id']);
        $updateStmt->execute();
        
        echo "<p>Admin password has been reset to 'admin123'.</p>";
    } else {
        // Admin doesn't exist, create new admin
        $insertStmt = $pdo->prepare("
            INSERT INTO admins (username, password, fullname, email) 
            VALUES (:username, :password, :fullname, :email)
        ");
        $insertStmt->bindParam(':username', $username);
        $insertStmt->bindParam(':password', $password);  // Store as plain text for simplicity
        $insertStmt->bindParam(':fullname', $fullname);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->execute();
        
        echo "<p>New admin user 'admin' has been created with password 'admin123'.</p>";
    }
    
    echo "<p>You can now <a href='login.php'>login</a> with username 'admin' and password 'admin123'.</p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 