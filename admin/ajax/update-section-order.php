<?php
// admin/ajax/update-section-order.php

require_once dirname(__DIR__, 2) . '/includes/init.php';

if (!Auth::check()) {
    http_response_code(401);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['sections'])) {
    foreach ($data['sections'] as $section) {
        db()->update('page_sections', 
            ['sort_order' => $section['order']], 
            'id = :id', 
            ['id' => $section['id']]
        );
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>