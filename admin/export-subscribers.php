<?php
// admin/export-subscribers.php
// Export subscribers to CSV

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$ids = $_POST['ids'] ?? [];

if (empty($ids)) {
    // Export all active subscribers
    $subscribers = db()->fetchAll("SELECT * FROM newsletter_subscribers WHERE status = 'active' ORDER BY created_at DESC");
} else {
    // Export selected subscribers
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $subscribers = db()->fetchAll("SELECT * FROM newsletter_subscribers WHERE id IN ($placeholders) ORDER BY created_at DESC", $ids);
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['Email', 'First Name', 'Last Name', 'Status', 'Subscribed Date', 'Source']);

// Add data rows
foreach ($subscribers as $subscriber) {
    fputcsv($output, [
        $subscriber['email'],
        $subscriber['first_name'],
        $subscriber['last_name'],
        $subscriber['status'],
        $subscriber['created_at'],
        $subscriber['source']
    ]);
}

fclose($output);
exit;
?>