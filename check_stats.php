<?php
// Turn on error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'config/db_connect.php';

// Function to run a count query
function getCount($pdo, $query) {
    try {
        $stmt = $pdo->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

// Get direct counts from database
$totalBooks = getCount($pdo, "SELECT COUNT(*) as count FROM books");
$totalMembers = getCount($pdo, "SELECT COUNT(*) as count FROM members");
$activeBorrows = getCount($pdo, "SELECT COUNT(*) as count FROM transactions WHERE status = 'Borrowed'");
$overdueBooks = getCount($pdo, "SELECT COUNT(*) as count FROM transactions WHERE status = 'Overdue'");

// Get counts from books table status
$booksMarkedBorrowed = getCount($pdo, "SELECT COUNT(*) as count FROM books WHERE status = 'Borrowed'");
$booksMarkedOverdue = getCount($pdo, "SELECT COUNT(*) as count FROM books WHERE status = 'Overdue'");

// Output results
echo "<h1>Database Statistics</h1>";
echo "<h2>Actual Counts</h2>";
echo "<p>Total Books: $totalBooks</p>";
echo "<p>Total Members: $totalMembers</p>";
echo "<p>Active Borrowings (from transactions): $activeBorrows</p>";
echo "<p>Overdue Books (from transactions): $overdueBooks</p>";

echo "<h2>Status in Books Table</h2>";
echo "<p>Books marked as Borrowed: $booksMarkedBorrowed</p>";
echo "<p>Books marked as Overdue: $booksMarkedOverdue</p>";

echo "<h2>Details of Borrowed/Overdue Books</h2>";
// Show all borrowed and overdue books with details
echo "<table border='1'>
<tr>
    <th>Book ID</th>
    <th>Title</th>
    <th>Status in Books table</th>
    <th>Due Date</th>
    <th>Status in Transactions</th>
    <th>Days Overdue</th>
</tr>";

$stmt = $pdo->query("
    SELECT b.id, b.title, b.status AS book_status, 
           t.due_date, t.status AS transaction_status,
           DATEDIFF(CURRENT_DATE, t.due_date) AS days_overdue
    FROM books b
    JOIN transactions t ON b.id = t.book_id
    WHERE t.status IN ('Borrowed', 'Overdue')
    ORDER BY t.due_date
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['title']) . "</td>";
    echo "<td>" . $row['book_status'] . "</td>";
    echo "<td>" . $row['due_date'] . "</td>";
    echo "<td>" . $row['transaction_status'] . "</td>";
    echo "<td>" . $row['days_overdue'] . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Debug Information</h2>";
echo "<p>Current Date: " . date('Y-m-d') . "</p>";

// Check if updateOverdueBooks is being triggered
echo "<p>Running updateOverdueBooks() manually now...</p>";
include_once 'config/functions.php';
$updatedCount = updateOverdueBooks();
echo "<p>Books updated to overdue status: $updatedCount</p>";

// Get counts again after update
$booksMarkedBorrowedAfter = getCount($pdo, "SELECT COUNT(*) as count FROM books WHERE status = 'Borrowed'");
$booksMarkedOverdueAfter = getCount($pdo, "SELECT COUNT(*) as count FROM books WHERE status = 'Overdue'");

echo "<h2>Status in Books Table (After Update)</h2>";
echo "<p>Books marked as Borrowed: $booksMarkedBorrowedAfter</p>";
echo "<p>Books marked as Overdue: $booksMarkedOverdueAfter</p>"; 