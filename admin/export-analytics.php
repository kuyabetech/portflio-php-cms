<?php
// admin/export-analytics.php
// Export Analytics Data

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$period = $_GET['period'] ?? '30days';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get all analytics data for export
$data = db()->fetchAll("
    SELECT 
        visit_date,
        visit_time,
        page_url,
        page_type,
        device_type,
        browser,
        os,
        country,
        city,
        referrer_url
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    ORDER BY visit_date DESC, visit_time DESC
", [$startDate, $endDate]);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="analytics-' . $startDate . '-to-' . $endDate . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, ['Date', 'Time', 'Page URL', 'Page Type', 'Device', 'Browser', 'OS', 'Country', 'City', 'Referrer']);

// Add data rows
foreach ($data as $row) {
    fputcsv($output, [
        $row['visit_date'],
        $row['visit_time'],
        $row['page_url'],
        $row['page_type'],
        $row['device_type'],
        $row['browser'],
        $row['os'],
        $row['country'] ?? 'Unknown',
        $row['city'] ?? 'Unknown',
        $row['referrer_url'] ?? 'Direct'
    ]);
}

fclose($output);
exit;
?>