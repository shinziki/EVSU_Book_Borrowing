<?php
// Test OTP functionality
require_once 'config/db_connect.php';
require_once 'config/functions.php';

$error = null;
$success = null;
$otp = null;
$email = 'migsbacho04@gmail.com';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_otp'])) {
        // Get admin data
        $stmt = $pdo->prepare("SELECT id, fullname FROM admins WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            $error = "Admin not found with email: " . htmlspecialchars($email);
        } else {
            // Generate and store OTP directly
            $otp = generateOTP();
            $expiryTime = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes
            
            // Delete any existing OTPs
            $stmt = $pdo->prepare("DELETE FROM otp_verifications WHERE user_id = ?");
            $stmt->execute([$admin['id']]);
            
            // Insert new OTP
            $stmt = $pdo->prepare("INSERT INTO otp_verifications (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
            $result = $stmt->execute([$admin['id'], $otp, $expiryTime]);
            
            if ($result) {
                // Write to file as backup
                $timestamp = date('Ymd_His');
                $filename = 'emails/otp_' . $timestamp . '_' . str_replace(['@', '.'], '_', $email) . '.txt';
                
                $subject = "EVSU Book Borrowing System - Test OTP Code";
                $message = "Dear " . htmlspecialchars($admin['fullname']) . ",\n\n";
                $message .= "Your test verification code is: " . $otp . "\n\n";
                $message .= "This code will expire in 10 minutes.\n\n";
                $message .= "Thank you,\nEVSU Book Borrowing System Team";
                
                $emailContent = "To: $email\nSubject: $subject\n\n$message";
                
                if (file_put_contents($filename, $emailContent)) {
                    $success = "OTP generated and saved to file: " . $filename;
                } else {
                    $error = "Failed to save OTP to file.";
                }
                
                // Also try to send via email
                $emailSent = sendEmailWithLogging($email, $subject, $message);
                if ($emailSent) {
                    $success .= "<br>Email sent successfully!";
                } else {
                    $success .= "<br>Email could not be sent, but OTP was saved to file.";
                }
            } else {
                $error = "Failed to store OTP in database.";
            }
        }
    } elseif (isset($_POST['verify_otp'])) {
        $enteredOTP = $_POST['otp'] ?? '';
        
        if (empty($enteredOTP)) {
            $error = "Please enter an OTP.";
        } else {
            // Get admin data
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                $error = "Admin not found with email: " . htmlspecialchars($email);
            } else {
                // Verify OTP directly
                $stmt = $pdo->prepare("
                    SELECT id, otp_code
                    FROM otp_verifications 
                    WHERE user_id = ? AND otp_code = ? AND expires_at > NOW()
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$admin['id'], $enteredOTP]);
                $otpRecord = $stmt->fetch();
                
                if ($otpRecord) {
                    // Delete the used OTP
                    $deleteStmt = $pdo->prepare("DELETE FROM otp_verifications WHERE id = ?");
                    $deleteStmt->execute([$otpRecord['id']]);
                    
                    $success = "OTP verification successful!";
                } else {
                    $error = "Invalid or expired OTP.";
                }
            }
        }
    }
}

// Check existing OTPs in the database
try {
    $stmt = $pdo->query("SELECT ov.id, ov.user_id, ov.otp_code, ov.expires_at, ov.created_at, a.email, a.fullname 
                         FROM otp_verifications ov
                         JOIN admins a ON ov.user_id = a.id
                         ORDER BY ov.created_at DESC");
    $otpRecords = $stmt->fetchAll();
} catch (PDOException $e) {
    $otpRecords = [];
}

// Get list of email files
$emailFiles = glob('emails/*.txt');
usort($emailFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
$emailFiles = array_slice($emailFiles, 0, 10); // Show only the 10 most recent files
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test OTP Functionality - EVSU Book Borrowing System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h1 class="text-2xl font-bold mb-6 text-gray-800">Test OTP Functionality</h1>
            
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
            
            <?php if ($otp): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">
                    <div class="flex">
                        <i class="fas fa-key mt-1 mr-2"></i>
                        <p>Generated OTP: <strong><?php echo $otp; ?></strong></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h2 class="text-xl font-semibold mb-3 text-gray-700">Generate OTP</h2>
                    <form method="post" class="space-y-4 bg-gray-50 p-4 rounded-lg">
                        <div>
                            <label class="block text-gray-700 mb-1">Target Email:</label>
                            <input type="text" value="<?php echo htmlspecialchars($email); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md" readonly>
                        </div>
                        <button type="submit" name="send_otp" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                            <i class="fas fa-paper-plane mr-2"></i> Generate & Send OTP
                        </button>
                    </form>
                </div>
                
                <div>
                    <h2 class="text-xl font-semibold mb-3 text-gray-700">Verify OTP</h2>
                    <form method="post" class="space-y-4 bg-gray-50 p-4 rounded-lg">
                        <div>
                            <label for="otp" class="block text-gray-700 mb-1">Enter OTP Code:</label>
                            <input type="text" id="otp" name="otp" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-center tracking-widest text-xl"
                                   maxlength="6" autocomplete="off" required>
                        </div>
                        <button type="submit" name="verify_otp" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md">
                            <i class="fas fa-check-circle mr-2"></i> Verify OTP
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-3 text-gray-700">Active OTPs in Database</h2>
                <?php if (empty($otpRecords)): ?>
                    <p class="text-gray-500 italic">No active OTPs found in the database.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OTP Code</th>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($otpRecords as $record): ?>
                                    <tr>
                                        <td class="py-2 px-3 text-sm font-mono"><?php echo htmlspecialchars($record['otp_code']); ?></td>
                                        <td class="py-2 px-3 text-sm"><?php echo htmlspecialchars($record['fullname']); ?></td>
                                        <td class="py-2 px-3 text-sm">
                                            <?php 
                                                $expires = new DateTime($record['expires_at']);
                                                $now = new DateTime();
                                                $isExpired = $now > $expires;
                                                echo $isExpired ? '<span class="text-red-500">Expired</span>' : htmlspecialchars($record['expires_at']); 
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-3 text-gray-700">Recent Email Files</h2>
                <?php if (empty($emailFiles)): ?>
                    <p class="text-gray-500 italic">No email files found.</p>
                <?php else: ?>
                    <ul class="space-y-2">
                        <?php foreach ($emailFiles as $file): ?>
                            <li>
                                <a href="javascript:void(0);" onclick="toggleEmail('<?php echo basename($file); ?>')" class="text-blue-600 hover:underline flex items-center">
                                    <i class="fas fa-envelope mr-2"></i>
                                    <?php echo basename($file); ?>
                                    <span class="text-xs text-gray-500 ml-2">(<?php echo date('Y-m-d H:i:s', filemtime($file)); ?>)</span>
                                </a>
                                <div id="email_<?php echo basename($file); ?>" class="hidden mt-2 p-3 bg-gray-50 rounded-md">
                                    <pre class="text-sm overflow-x-auto whitespace-pre-wrap"><?php echo htmlspecialchars(file_get_contents($file)); ?></pre>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-6 flex justify-between">
            <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md transition-colors">
                <i class="fas fa-arrow-left mr-2"></i> Back to Home
            </a>
            <a href="login.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">
                <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
            </a>
        </div>
    </div>
    
    <script>
        function toggleEmail(filename) {
            const element = document.getElementById('email_' + filename);
            if (element.classList.contains('hidden')) {
                element.classList.remove('hidden');
            } else {
                element.classList.add('hidden');
            }
        }
    </script>
</body>
</html> 