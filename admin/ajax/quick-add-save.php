<?php
// admin/ajax/quick-add-save.php
// Handle quick add form submissions

require_once dirname(__DIR__, 2) . '/includes/init.php';
header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if ($type === 'project') {
    // Validate required fields
    if (empty($_POST['title'])) {
        echo json_encode(['success' => false, 'message' => 'Project title is required']);
        exit;
    }
    
    $data = [
        'title' => sanitize($_POST['title']),
        'slug' => createSlug($_POST['title']),
        'short_description' => sanitize($_POST['short_description'] ?? ''),
        'technologies' => sanitize($_POST['technologies'] ?? ''),
        'project_url' => sanitize($_POST['project_url'] ?? ''),
        'completion_date' => $_POST['completion_date'] ?? null,
        'status' => 'draft',
        'is_featured' => 0
    ];
    
    try {
        db()->insert('projects', $data);
        echo json_encode(['success' => true, 'message' => 'Project created successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
} elseif ($type === 'post') {
    // Validate required fields
    if (empty($_POST['title'])) {
        echo json_encode(['success' => false, 'message' => 'Post title is required']);
        exit;
    }
    
    if (empty($_POST['content'])) {
        echo json_encode(['success' => false, 'message' => 'Post content is required']);
        exit;
    }
    
    $data = [
        'title' => sanitize($_POST['title']),
        'slug' => createSlug($_POST['title']),
        'content' => $_POST['content'],
        'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
        'author_id' => $_SESSION['user_id'],
        'status' => $_POST['status'] ?? 'draft',
        'allow_comments' => 1
    ];
    
    try {
        db()->insert('blog_posts', $data);
        echo json_encode(['success' => true, 'message' => 'Blog post created successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
}
?>