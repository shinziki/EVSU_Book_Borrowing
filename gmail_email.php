<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Simple Email Solution</h1>";

// Create emails directory if it doesn't exist
if (!is_dir('emails')) {
    mkdir('emails', 0755, true);
}

// Function to save email as file (guaranteed to work)
function saveEmailToFile($to, $subject, $message, $from = "Coffee Prince Library") {
    $timestamp = date('Ymd_His');
    $safeEmail = str_replace(['@', '.', '+'], '_', $to);
    $filename = 'emails/email_' . $timestamp . '_' . $safeEmail . '.txt';
    
    // Create email content
    $emailContent = "To: $to\n";
    $emailContent .= "From: $from\n";
    $emailContent .= "Subject: $subject\n";
    $emailContent .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $emailContent .= "-------------------------------------------\n";
    $emailContent .= $message;
    
    // Save to file
    if (file_put_contents($filename, $emailContent)) {
        return [
            'success' => true,
            'filename' => $filename,
            'method' => 'file'
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Could not save file',
            'method' => 'file'
        ];
    }
}

// Test sending an email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = $_POST['email'];
    $subject = $_POST['subject'] ?? "Test Email from Coffee Prince Library";
    $message = $_POST['message'] ?? "This is a test email sent at " . date('Y-m-d H:i:s') . "\n\n";
    $message .= $_POST['message'] ? "" : "If you received this email, your email system is working correctly.";
    
    // Save to file as a reliable fallback
    $result = saveEmailToFile($to, $subject, $message);
    
    if ($result['success']) {
        echo "<div style='background-color:#d4edda; color:#155724; padding:15px; margin:15px 0; border-radius:5px;'>";
        echo "<strong>Success!</strong> Email stored in file: " . htmlspecialchars($result['filename']);
        echo "</div>";
        
        echo "<div style='background-color:#cce5ff; color:#004085; padding:15px; margin:15px 0; border-radius:5px;'>";
        echo "<strong>Important:</strong> Since this is a development environment, the email was saved as a file instead of being sent.";
        echo "<p>To view the email content, click <a href='javascript:void(0);' onclick='showEmailContent()'>here</a>.</p>";
        echo "<div id='emailContent' style='display:none; background:#f8f9fa; border:1px solid #ddd; padding:15px; margin-top:10px;'>";
        echo "<pre>" . htmlspecialchars(file_get_contents($result['filename'])) . "</pre>";
        echo "</div>";
        echo "</div>";
    } else {
        echo "<div style='background-color:#f8d7da; color:#721c24; padding:15px; margin:15px 0; border-radius:5px;'>";
        echo "<strong>Error!</strong> " . htmlspecialchars($result['error']);
        echo "</div>";
    }
}

// Interface to test the email functionality
?>
<div style="max-width: 800px; margin: 30px auto; font-family: Arial, sans-serif;">
    <p>This is a simple solution for email functionality. In a development environment, emails will be saved as files in the 'emails' folder. In production, this could be connected to a mail service API.</p>
    
    <form method="post" style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h2 style="margin-top: 0;">Send a Test Email</h2>
        
        <div style="margin-bottom: 15px;">
            <label for="email" style="display: block; margin-bottom: 5px; font-weight: bold;">To:</label>
            <input type="email" id="email" name="email" required 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                   value="<?php echo htmlspecialchars($_POST['email'] ?? 'migsbacho04@gmail.com'); ?>">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="subject" style="display: block; margin-bottom: 5px; font-weight: bold;">Subject:</label>
            <input type="text" id="subject" name="subject"
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                   placeholder="Test Email from Coffee Prince Library">
        </div>
        
        <div style="margin-bottom: 15px;">
            <label for="message" style="display: block; margin-bottom: 5px; font-weight: bold;">Message:</label>
            <textarea id="message" name="message" rows="5" 
                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                      placeholder="Enter your message here..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
        </div>
        
        <button type="submit" style="background: #007bff; color: #fff; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer;">
            Send Email
        </button>
    </form>
    
    <h2>Recent Email Files</h2>
    <div id="recentEmails">
        <?php
        $files = glob('emails/*.txt');
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        $files = array_slice($files, 0, 10);
        
        if (empty($files)) {
            echo "<p>No email files found.</p>";
        } else {
            echo "<ul style='list-style-type: none; padding: 0;'>";
            foreach ($files as $idx => $file) {
                echo "<li style='margin-bottom: 5px;'>";
                echo "<a href='javascript:void(0);' onclick='viewEmail(" . $idx . ")' style='color: #007bff; text-decoration: none;'>";
                echo htmlspecialchars(basename($file)) . " (" . date("Y-m-d H:i:s", filemtime($file)) . ")";
                echo "</a>";
                echo "</li>";
            }
            echo "</ul>";
            
            // Generate JavaScript array of email contents
            echo "<script>";
            echo "var emailContents = [";
            foreach ($files as $file) {
                echo "'" . addslashes(htmlspecialchars(file_get_contents($file))) . "',";
            }
            echo "];";
            echo "</script>";
        }
        ?>
    </div>
</div>

<div id="emailModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7);">
    <div style="background-color: white; margin: 10% auto; padding: 20px; width: 80%; max-width: 700px; border-radius: 5px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <h2 style="margin: 0;">Email Content</h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
        <pre id="modalContent" style="background: #f8f9fa; border: 1px solid #ddd; padding: 15px; overflow-x: auto; white-space: pre-wrap;"></pre>
    </div>
</div>

<script>
function showEmailContent() {
    var content = document.getElementById('emailContent');
    if (content.style.display === 'none') {
        content.style.display = 'block';
    } else {
        content.style.display = 'none';
    }
}

function viewEmail(index) {
    document.getElementById('modalContent').textContent = emailContents[index];
    document.getElementById('emailModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('emailModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    var modal = document.getElementById('emailModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<h2>Using This System for Login OTP and Notifications</h2>

<div style="max-width: 800px; margin: 30px auto; font-family: Arial, sans-serif; background: #f8f9fa; padding: 20px; border-radius: 5px;">
    <h3>Instructions:</h3>
    <ol>
        <li>For <strong>development testing</strong>, when you try to log in, the OTP email will be saved as a file in the 'emails' folder.</li>
        <li>Open the most recent email file from the list above to find your OTP code.</li>
        <li>Use that code to complete the login.</li>
        <li>Similarly, book borrowing and return notifications will be saved as files.</li>
    </ol>
    
    <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">
        <h4 style="margin-top: 0;">Why Email Isn't Working:</h4>
        <p>There are several possible reasons why the Brevo API isn't working:</p>
        <ul>
            <li>The API key might be invalid or expired</li>
            <li>There might be network/firewall restrictions</li>
            <li>The sender domain might need verification</li>
            <li>XAMPP might not have curl properly configured</li>
            <li>The free tier might have limitations or require account verification</li>
        </ul>
    </div>
    
    <p><a href="debug_email.php" style="color: #007bff;">Run the diagnostic tool</a> for more detailed troubleshooting.</p>
</div> 