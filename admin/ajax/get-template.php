<?php
// admin/ajax/get-template.php
require_once dirname(__DIR__, 2) . '/includes/init.php';
Auth::requireAuth();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false]);
    exit;
}

$template = db()->fetch("SELECT * FROM newsletter_templates WHERE id = ?", [$id]);

if (!$template) {
    echo json_encode(['success' => false]);
    exit;
}

echo json_encode([
    'success' => true,
    'name' => $template['name'],
    'subject' => $template['subject'],
    'content' => $template['content']
]);