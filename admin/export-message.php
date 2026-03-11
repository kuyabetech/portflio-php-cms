<?php
// admin/export-messages.php
// Advanced message export with filters

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

// Get filter parameters
$format = $_GET['format'] ?? 'csv';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? 'all';

// Build query
$where = ["1=1"];
$params = [];

if (!empty($date_from)) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

if ($status === 'read') {
    $where[] = "is_read = 1";
} elseif ($status === 'unread') {
    $where[] = "is_read = 0";
}

$whereClause = implode(' AND ', $where);

// Get messages
$messages = db()->fetchAll("
    SELECT * FROM contact_messages 
    WHERE $whereClause 
    ORDER BY created_at DESC
", $params);

// Get summary statistics
$total = count($messages);
$read = count(array_filter($messages, fn($m) => $m['is_read']));
$unread = $total - $read;

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="messages-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add summary section
    fputcsv($output, ['EXPORT SUMMARY']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Total Messages:', $total]);
    fputcsv($output, ['Read:', $read]);
    fputcsv($output, ['Unread:', $unread]);
    fputcsv($output, ['Date Range:', $date_from ?: 'All', $date_to ?: 'All']);
    fputcsv($output, []);
    fputcsv($output, ['DETAILED REPORT']);
    fputcsv($output, []);
    
    // Headers
    fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Company', 'Subject', 'Message', 'Date', 'Status', 'IP Address']);
    
    // Data
    foreach ($messages as $msg) {
        fputcsv($output, [
            $msg['id'],
            $msg['name'],
            $msg['email'],
            $msg['phone'] ?? '',
            $msg['company'] ?? '',
            $msg['subject'] ?? '',
            $msg['message'],
            $msg['created_at'],
            $msg['is_read'] ? 'Read' : 'Unread',
            $msg['ip_address'] ?? ''
        ]);
    }
    
    fclose($output);
    
} elseif ($format === 'excel') {
    // Excel XML format
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="messages-' . date('Y-m-d') . '.xls"');
    
    echo '<?xml version="1.0"?>';
    echo '<?mso-application progid="Excel.Sheet"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<Worksheet ss:Name="Messages">';
    echo '<Table>';
    
    // Headers
    echo '<Row>';
    echo '<Cell><Data ss:Type="String">ID</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Name</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Email</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Phone</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Company</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Subject</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Message</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Date</Data></Cell>';
    echo '<Cell><Data ss:Type="String">Status</Data></Cell>';
    echo '</Row>';
    
    // Data
    foreach ($messages as $msg) {
        echo '<Row>';
        echo '<Cell><Data ss:Type="Number">' . $msg['id'] . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($msg['name']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($msg['email']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($msg['phone'] ?? '') . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($msg['company'] ?? '') . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($msg['subject'] ?? '') . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($msg['message']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . $msg['created_at'] . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . ($msg['is_read'] ? 'Read' : 'Unread') . '</Data></Cell>';
        echo '</Row>';
    }
    
    echo '</Table>';
    echo '</Worksheet>';
    echo '</Workbook>';
    
} elseif ($format === 'pdf') {
    // PDF export would require a library like Dompdf
    // This is a placeholder
    header('Location: messages.php?error=pdf_not_available');
    exit;
}

exit;