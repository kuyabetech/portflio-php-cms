<?php
// ajax/get-message.php
// Get message details for preview modal

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

// Get message details
$message = db()->fetch("SELECT * FROM contact_messages WHERE id = ?", [$id]);

if (!$message) {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit;
}

// Get reply history
$replies = db()->fetchAll("
    SELECT mr.*, u.username as replied_by_name 
    FROM message_replies mr
    LEFT JOIN users u ON mr.sent_by = u.id
    WHERE mr.message_id = ?
    ORDER BY mr.sent_at ASC
", [$id]);

// Format response
$response = [
    'success' => true,
    'id' => $message['id'],
    'name' => $message['name'],
    'email' => $message['email'],
    'phone' => $message['phone'] ?? '',
    'company' => $message['company'] ?? '',
    'subject' => $message['subject'] ?? 'No Subject',
    'message' => $message['message'],
    'is_read' => (bool)$message['is_read'],
    'ip_address' => $message['ip_address'] ?? '',
    'user_agent' => $message['user_agent'] ?? '',
    'created_at' => date('F j, Y \a\t g:i A', strtotime($message['created_at'])),
    'replies' => []
];

// Add replies
foreach ($replies as $reply) {
    $response['replies'][] = [
        'id' => $reply['id'],
        'message' => $reply['reply_message'],
        'sent_at' => date('F j, Y \a\t g:i A', strtotime($reply['sent_at'])),
        'sent_by' => $reply['replied_by_name'] ?? 'System'
    ];
}

echo json_encode($response);