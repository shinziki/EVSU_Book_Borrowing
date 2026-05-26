<?php

/**
 * Path to the library logo used in PDF reports.
 */
function getReportLogoPath()
{
    $root = dirname(__DIR__);
    foreach ([
        $root . '/logo/EVSU_Official_Logo.png',
        $root . '/logo/EVSU_Official_Logo.jpg',
    ] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * Collect annual report data for a given calendar year.
 */
function getAnnualReportData($year)
{
    global $pdo;

    $year = (int) $year;
    $start = sprintf('%04d-01-01 00:00:00', $year);
    $end = sprintf('%04d-12-31 23:59:59', $year);

    $data = [
        'year' => $year,
        'generated_at' => date('F j, Y g:i A'),
        'summary' => [],
        'books' => [],
        'members' => [],
        'transactions' => [],
        'penalties' => [],
        'overdue' => [],
        'notifications' => [],
    ];

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM books WHERE created_at <= :end");
    $stmt->execute([':end' => $end]);
    $data['summary']['total_books'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM books WHERE created_at BETWEEN :start AND :end");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['summary']['books_added'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM members WHERE created_at <= :end");
    $stmt->execute([':end' => $end]);
    $data['summary']['total_members'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM members WHERE created_at BETWEEN :start AND :end");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['summary']['members_added'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM transactions WHERE borrow_date BETWEEN :start AND :end");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['summary']['total_borrows'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM transactions WHERE return_date BETWEEN :start AND :end");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['summary']['total_returns'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM transactions WHERE status IN ('Overdue', 'Needs Replacement') AND borrow_date BETWEEN :start AND :end");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['summary']['overdue_count'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) AS s FROM transactions WHERE borrow_date BETWEEN :start AND :end");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['summary']['borrow_fees'] = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(penalty_amount), 0) AS s FROM transactions WHERE penalty_amount > 0 AND borrow_date BETWEEN :start AND :end");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['summary']['penalty_total'] = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM notifications WHERE created_at BETWEEN :start AND :end");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['summary']['notifications_sent'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT title, author, barcode, category, status, stock, DATE_FORMAT(created_at, '%Y-%m-%d') AS added FROM books ORDER BY title");
    $data['books'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT fullname, email, phone, barcode, membership_type, DATE_FORMAT(created_at, '%Y-%m-%d') AS joined FROM members WHERE created_at <= :end ORDER BY fullname");
    $stmt->execute([':end' => $end]);
    $data['members'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT t.id, b.title AS book_title, m.fullname AS member_name,
               DATE_FORMAT(t.borrow_date, '%Y-%m-%d') AS borrow_date,
               DATE_FORMAT(t.due_date, '%Y-%m-%d') AS due_date,
               DATE_FORMAT(t.return_date, '%Y-%m-%d') AS return_date,
               t.status, t.payment_amount, t.penalty_amount, t.payment_status
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        JOIN members m ON t.member_id = m.id
        WHERE t.borrow_date BETWEEN :start AND :end
        ORDER BY t.borrow_date DESC
    ");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT t.id, b.title AS book_title, m.fullname AS member_name,
               t.penalty_type, t.penalty_amount, t.payment_status, t.status,
               DATE_FORMAT(t.borrow_date, '%Y-%m-%d') AS borrow_date
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        JOIN members m ON t.member_id = m.id
        WHERE t.penalty_amount > 0 AND t.borrow_date BETWEEN :start AND :end
        ORDER BY t.borrow_date DESC
    ");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['penalties'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT t.id, b.title AS book_title, m.fullname AS member_name,
               DATE_FORMAT(t.due_date, '%Y-%m-%d') AS due_date, t.status,
               t.penalty_amount, t.payment_status
        FROM transactions t
        JOIN books b ON t.book_id = b.id
        JOIN members m ON t.member_id = m.id
        WHERE t.status IN ('Overdue', 'Borrowed', 'Needs Replacement')
          AND t.due_date < :end
          AND (t.return_date IS NULL OR t.return_date BETWEEN :start AND :end)
          AND t.borrow_date BETWEEN :start AND :end
        ORDER BY t.due_date
    ");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['overdue'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m-%d') AS sent_date, type,
               LEFT(message, 120) AS message_preview, is_sent
        FROM notifications
        WHERE created_at BETWEEN :start AND :end
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $data['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $data;
}

/**
 * Build and stream annual PDF report with bordered tables.
 */
function generateAnnualReportPdf($year)
{
    require_once __DIR__ . '/SimplePdf.php';
    $data = getAnnualReportData($year);
    $pdf = new SimplePdf();
    $pdf->addPage();

    $pdf->writeReportCoverHeader(
        'EVSU Book Borrowing System',
        'Annual Report ' . $data['year'],
        getReportLogoPath(),
        'Generated: ' . $data['generated_at']
    );
    $pdf->writeSpacer(1);

    $s = $data['summary'];
    $pdf->writeSectionHeading('1. Executive Summary');
    $pdf->drawKeyValueTable([
        'Total Books (catalog)' => $s['total_books'],
        'Books Added in ' . $data['year'] => $s['books_added'],
        'Total Members' => $s['total_members'],
        'Members Registered in ' . $data['year'] => $s['members_added'],
        'Total Borrow Transactions' => $s['total_borrows'],
        'Total Returns' => $s['total_returns'],
        'Overdue Records' => $s['overdue_count'],
        'Penalties Assessed (PHP)' => number_format($s['penalty_total'], 2),
        'Notifications Logged' => $s['notifications_sent'],
    ]);
    $pdf->writeSectionBreak();

    $pdf->writeSectionHeading('2. Book Catalog');
    $bookRows = [];
    foreach ($data['books'] as $row) {
        $bookRows[] = [
            $row['title'],
            $row['author'],
            $row['barcode'],
            $row['category'] ?? '-',
            $row['status'],
            $row['stock'],
        ];
    }
    $pdf->drawTable(
        ['Title', 'Author', 'Barcode', 'Category', 'Status', 'Stock'],
        $bookRows,
        [130, 95, 75, 75, 70, 70]
    );
    $pdf->writeSectionBreak();

    $pdf->writeSectionHeading('3. Members');
    $memberRows = [];
    foreach ($data['members'] as $row) {
        $memberRows[] = [
            $row['fullname'],
            $row['email'],
            $row['phone'] ?? '-',
            $row['barcode'],
            $row['membership_type'] ?? 'Regular',
            $row['joined'],
        ];
    }
    $pdf->drawTable(
        ['Name', 'Email', 'Phone', 'Barcode', 'Type', 'Joined'],
        $memberRows,
        [105, 110, 70, 70, 65, 95]
    );
    $pdf->writeSectionBreak();

    $pdf->writeSectionHeading('4. Transactions');
    $txRows = [];
    foreach ($data['transactions'] as $row) {
        $txRows[] = [
            $row['id'],
            $row['book_title'],
            $row['member_name'],
            $row['borrow_date'],
            $row['due_date'],
            $row['return_date'] ?? '-',
            $row['status'],
            number_format((float) $row['payment_amount'], 2),
        ];
    }
    $pdf->drawTable(
        ['ID', 'Book', 'Member', 'Borrowed', 'Due', 'Returned', 'Status'],
        $txRows,
        [28, 105, 90, 58, 58, 58, 58]
    );
    $pdf->writeSectionBreak();

    $pdf->writeSectionHeading('5. Penalties');
    $penaltyRows = [];
    foreach ($data['penalties'] as $row) {
        $penaltyRows[] = [
            $row['id'],
            $row['book_title'],
            $row['member_name'],
            $row['penalty_type'] ?? '-',
            number_format((float) $row['penalty_amount'], 2),
            $row['payment_status'],
        ];
    }
    $pdf->drawTable(
        ['ID', 'Book', 'Member', 'Type', 'Amount', 'Payment Status'],
        $penaltyRows,
        [28, 120, 105, 55, 65, 142]
    );
    $pdf->writeSectionBreak();

    $pdf->writeSectionHeading('6. Overdue Activity');
    $overdueRows = [];
    foreach ($data['overdue'] as $row) {
        $overdueRows[] = [
            $row['id'],
            $row['book_title'],
            $row['member_name'],
            $row['due_date'],
            $row['status'],
            number_format((float) $row['penalty_amount'], 2),
            $row['payment_status'],
        ];
    }
    $pdf->drawTable(
        ['ID', 'Book', 'Member', 'Due Date', 'Status', 'Penalty', 'Payment'],
        $overdueRows,
        [28, 115, 100, 58, 58, 58, 98]
    );
    $pdf->writeSectionBreak();

    $pdf->writeSectionHeading('7. Notifications');
    $notifRows = [];
    foreach ($data['notifications'] as $row) {
        $notifRows[] = [
            $row['sent_date'],
            $row['type'] ?: 'General',
            $row['message_preview'],
            $row['is_sent'] ? 'Yes' : 'No',
        ];
    }
    $pdf->drawTable(
        ['Date', 'Type', 'Message Preview', 'Sent'],
        $notifRows,
        [65, 90, 310, 50]
    );

    $pdf->writeSpacer(2);
    $pdf->writeLine('End of Annual Report - EVSU Book Borrowing System (c) ' . $data['year']);

    $filename = 'EVSU_Annual_Report_' . $data['year'] . '.pdf';
    $pdf->output($filename);
}
