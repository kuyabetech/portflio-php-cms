<?php
// admin/ajax/mark-all-read.php
// Mark all notifications as read

require_once dirname(__DIR__, 2) . '/includes/init.php';
header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    // Mark all messages as read
    db()->update('contact_messages', ['is_read' => 1], '1 = 1', []);
    
    // You can also mark other notifications here
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
?>