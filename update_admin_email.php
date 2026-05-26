<?php
// Update admin email address
require_once 'config/db_connect.php';

$error = null;
$success = null;
$newEmail = 'migsbacho04@gmail.com';

try {
    // Update the admin email
    $sql = "UPDATE admins SET email = :email WHERE username = 'admin'";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':email', $newEmail);
    
    if ($stmt->execute()) {
        $rowCount = $stmt->rowCount();
        if ($rowCount > 0) {
            $success = "Admin email updated successfully to: " . htmlspecialchars($newEmail);
        } else {
            $error = "No admin account found with username 'admin'.";
        }
    } else {
        $error = "Failed to update admin email.";
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Admin Email - Coffee Prince Library</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-md">
        <div class="bg-white shadow-md rounded-lg p-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-800">Update Admin Email</h1>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                    <div class="flex">
                        <i class="fas fa-exclamation-circle mt-1 mr-2"></i>
                        <p><?php echo $error; ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                    <div class="flex">
                        <i class="fas fa-check-circle mt-1 mr-2"></i>
                        <p><?php echo $success; ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="bg-blue-50 p-4 rounded-md border border-blue-200 mb-4">
                <h3 class="font-semibold text-blue-800 mb-2">Admin Email Update</h3>
                <p class="text-blue-700">
                    This script has updated the admin email address to <strong><?php echo htmlspecialchars($newEmail); ?></strong> 
                    for the account with username 'admin'.
                </p>
                <p class="text-blue-700 mt-2">
                    This ensures that OTP verification codes will be sent to the correct email address.
                </p>
            </div>
            
            <div class="flex justify-between">
                <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Home
                </a>
                <a href="login.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html> 