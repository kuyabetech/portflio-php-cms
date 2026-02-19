<?php
// admin/ajax/get-template.php
// Get template for AJAX request

require_once dirname(__DIR__, 2) . '/includes/init.php';
header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false]);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$template = db()->fetch("SELECT * FROM newsletter_templates WHERE id = ?", [$id]);

if ($template) {
    echo json_encode([
        'success' => true,
        'template' => [
            'subject' => $template['subject'],
            'content' => $template['content']
        ]
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>