<?php
require_once 'config/db_connect.php';
require_once 'config/functions.php';

// Require login to access this endpoint
requireLogin();

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'active_borrows' => 0,
    'borrow_limit' => 3,
    'member_name' => '',
    'membership_type' => '',
    'borrowed_books' => []
];

// Check if Student ID (or legacy barcode) is provided
$memberIdentifier = null;
if (isset($_GET['student_id']) && trim($_GET['student_id']) !== '') {
    $memberIdentifier = trim($_GET['student_id']);
} elseif (isset($_GET['barcode']) && trim($_GET['barcode']) !== '') {
    // backward compatibility (older UI)
    $memberIdentifier = trim($_GET['barcode']);
}

if ($memberIdentifier !== null) {
    
    // Get member details
    $memberInfo = getMemberByBarcode($memberIdentifier);
    
    if ($memberInfo) {
        // Get borrow limit based on membership type
        $borrowLimit = $memberInfo['membership_type'] == 'Premium' ? 5 : 3;
        
        // Get active borrows count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_borrows 
            FROM transactions 
            WHERE member_id = :member_id 
            AND status IN ('Borrowed', 'Overdue', 'Needs Replacement')
        ");
        $stmt->bindParam(':member_id', $memberInfo['id']);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $activeBorrows = $result ? (int)$result['active_borrows'] : 0;
        
        // Get borrowed books details
        $stmt = $pdo->prepare("
            SELECT t.id, t.borrow_date, t.due_date, t.status,
                   b.title as book_title
            FROM transactions t
            JOIN books b ON t.book_id = b.id
            WHERE t.member_id = :member_id
            AND t.status IN ('Borrowed', 'Overdue', 'Needs Replacement')
            ORDER BY t.due_date ASC
        ");
        $stmt->bindParam(':member_id', $memberInfo['id']);
        $stmt->execute();
        $borrowedBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update response with member information
        $response['success'] = true;
        $response['member_name'] = $memberInfo['fullname'];
        $response['membership_type'] = $memberInfo['membership_type'];
        $response['active_borrows'] = $activeBorrows;
        $response['borrowed_books'] = $borrowedBooks;
        
        $response['message'] = "Member has ${activeBorrows} active borrows";
    } else {
        $response['message'] = "Member not found with Student ID: $memberIdentifier";
    }
} else {
    $response['message'] = "No Student ID provided";
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?> 