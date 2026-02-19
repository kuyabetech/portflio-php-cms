<?php
// admin/templates.php
// Page Templates Management

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Page Templates';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Pages', 'url' => 'pages.php'],
    ['title' => 'Templates']
];

// Handle template delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('page_templates', 'id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Template deleted'];
    header('Location: templates.php');
    exit;
}

// Handle set as default
if (isset($_GET['set_default'])) {
    $id = (int)$_GET['set_default'];
    db()->update('page_templates', ['is_default' => 0], '1 = 1', []);
    db()->update('page_templates', ['is_default' => 1], 'id = :id', ['id' => $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Default template updated'];
    header('Location: templates.php');
    exit;
}

// Handle template form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $data = [
        'name' => sanitize($_POST['name']),
        'slug' => createSlug($_POST['name']),
        'description' => sanitize($_POST['description']),
        'layout' => $_POST['layout']
    ];
    
    // Handle thumbnail upload
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['thumbnail'], 'templates/');
        if (isset($upload['success'])) {
            $data['thumbnail'] = $upload['filename'];
        }
    }
    
    if (!empty($_POST['id'])) {
        db()->update('page_templates', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('page_templates', $data);
        $msg = 'created';
    }
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Template ' . $msg];
    header('Location: templates.php');
    exit;
}

// Get template for editing
$template = null;
if ($id > 0 && $action === 'edit') {
    $template = db()->fetch("SELECT * FROM page_templates WHERE id = ?", [$id]);
}

// Get all templates
$templates = db()->fetchAll("SELECT * FROM page_templates ORDER BY is_default DESC, name");

// Include header
require_once 'includes/header.php';
?>

<!-- Rest of the template management UI -->
<!-- (Similar structure to pages.php but for templates) -->

<?php
require_once 'includes/footer.php';
?>