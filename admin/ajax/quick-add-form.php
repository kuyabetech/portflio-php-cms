<?php
// admin/ajax/quick-add-form.php
// Quick Add Forms for AJAX loading

require_once dirname(__DIR__, 2) . '/includes/init.php';
Auth::requireAuth();

$type = $_GET['type'] ?? '';

if ($type === 'project') {
    ?>
    <form id="quickAddForm" class="quick-add-form">
        <div class="form-group">
            <label for="title">Project Title *</label>
            <input type="text" id="title" name="title" required>
        </div>
        
        <div class="form-group">
            <label for="short_description">Short Description</label>
            <textarea id="short_description" name="short_description" rows="3"></textarea>
        </div>
        
        <div class="form-group">
            <label for="technologies">Technologies</label>
            <input type="text" id="technologies" name="technologies" placeholder="PHP, MySQL, JavaScript">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="project_url">Project URL</label>
                <input type="url" id="project_url" name="project_url">
            </div>
            
            <div class="form-group">
                <label for="completion_date">Completion Date</label>
                <input type="date" id="completion_date" name="completion_date">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Project</button>
            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
        </div>
    </form>
    <?php
} elseif ($type === 'post') {
    ?>
    <form id="quickAddForm" class="quick-add-form">
        <div class="form-group">
            <label for="title">Post Title *</label>
            <input type="text" id="title" name="title" required>
        </div>
        
        <div class="form-group">
            <label for="content">Content *</label>
            <textarea id="content" name="content" rows="5" required></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="">Select Category</option>
                    <?php
                    $categories = db()->fetchAll("SELECT id, name FROM blog_categories WHERE is_active = 1");
                    foreach ($categories as $cat) {
                        echo "<option value=\"{$cat['id']}\">{$cat['name']}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Post</button>
            <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
        </div>
    </form>
    <?php
}
?>aa<?php
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