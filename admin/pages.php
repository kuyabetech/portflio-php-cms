<?php
// admin/pages.php
// Complete Page Management System

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Page Management';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Pages']
];

if ($action === 'add') {
    $breadcrumbs[] = ['title' => 'Add New Page'];
} elseif ($action === 'edit') {
    $breadcrumbs[] = ['title' => 'Edit Page'];
} elseif ($action === 'sections') {
    $breadcrumbs[] = ['title' => 'Manage Sections'];
}

// Handle page delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if this is the homepage
    $page = db()->fetch("SELECT is_homepage FROM pages WHERE id = ?", [$id]);
    if ($page && !empty($page['is_homepage'])) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Cannot delete the homepage'];
    } else {
        db()->delete('pages', 'id = ?', [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Page deleted successfully'];
    }
    header('Location: pages.php');
    exit;
}

// Handle page status toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $page = db()->fetch("SELECT status FROM pages WHERE id = ?", [$id]);
    if ($page && isset($page['status'])) {
        $newStatus = $page['status'] === 'published' ? 'draft' : 'published';
        db()->update('pages', ['status' => $newStatus], 'id = :id', ['id' => $id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Page status updated'];
    }
    header('Location: pages.php');
    exit;
}

// Handle set as homepage
if (isset($_GET['set_homepage'])) {
    $id = (int)$_GET['set_homepage'];
    // Remove current homepage
    db()->update('pages', ['is_homepage' => 0], 'is_homepage = 1', []);
    // Set new homepage
    db()->update('pages', ['is_homepage' => 1], 'id = :id', ['id' => $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Homepage updated successfully'];
    header('Location: pages.php');
    exit;
}

// Handle page form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_page'])) {
    $data = [
        'title' => isset($_POST['title']) ? sanitize($_POST['title']) : '',
        'slug' => !empty($_POST['slug']) ? sanitize($_POST['slug']) : createSlug($_POST['title'] ?? ''),
        'meta_title' => isset($_POST['meta_title']) ? sanitize($_POST['meta_title']) : '',
        'meta_description' => isset($_POST['meta_description']) ? sanitize($_POST['meta_description']) : '',
        'meta_keywords' => isset($_POST['meta_keywords']) ? sanitize($_POST['meta_keywords']) : '',
        'layout' => $_POST['layout'] ?? 'default',
        'status' => $_POST['status'] ?? 'draft',
        'sort_order' => isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0,
        'created_by' => $_SESSION['user_id']
    ];
    
    // Check if slug is unique
    $existing = db()->fetch("SELECT id FROM pages WHERE slug = ? AND id != ?", 
        [$data['slug'], $_POST['id'] ?? 0]);
    
    if ($existing && !empty($existing)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Slug already exists. Please choose another.'];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Handle featured image upload
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['featured_image'], 'pages/');
        if (isset($upload['success'])) {
            $data['featured_image'] = $upload['filename'];
        }
    }
    
    if (!empty($_POST['id'])) {
        db()->update('pages', $data, 'id = :id', ['id' => $_POST['id']]);
        $pageId = $_POST['id'];
        $msg = 'updated';
    } else {
        $pageId = db()->insert('pages', $data);
        $msg = 'created';
    }
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Page ' . $msg . ' successfully'];
    header('Location: pages.php?action=sections&id=' . $pageId);
    exit;
}

// Get page for editing
$page = null;
if ($id > 0 && ($action === 'edit' || $action === 'sections')) {
    $pageData = db()->fetch("SELECT * FROM pages WHERE id = ?", [$id]);
    if ($pageData && is_array($pageData)) {
        $page = $pageData;
    }
}

// Get all pages
$pages = db()->fetchAll("
    SELECT p.*, 
           COUNT(ps.id) as section_count,
           u.username as author_name
    FROM pages p
    LEFT JOIN page_sections ps ON p.id = ps.page_id
    LEFT JOIN users u ON p.created_by = u.id
    GROUP BY p.id
    ORDER BY p.is_homepage DESC, p.sort_order, p.created_at DESC
") ?: [];

// Get templates for dropdown
$templates = db()->fetchAll("SELECT * FROM page_templates ORDER BY is_default DESC, name") ?: [];

// Get sections for the page (if viewing sections)
$pageSections = [];
if ($action === 'sections' && $id > 0) {
    $pageSections = db()->fetchAll("
        SELECT * FROM page_sections 
        WHERE page_id = ? 
        ORDER BY sort_order ASC
    ", [$id]) ?: [];
}

// Safe echo function
function safeEcho($value, $default = '') {
    echo htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

// Include header
require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="content-header">
    <h2>
        <i class="fas fa-file-alt"></i> 
   <?php 
if ($action === 'edit'): 
    echo 'Edit Page';
elseif ($action === 'add'): 
    echo 'Create New Page';
elseif ($action === 'sections'): 
    echo 'Manage Sections';
    if ($page && isset($page['title'])): 
        echo ': ' . safeEcho($page['title']); 
    endif;
else: 
    echo 'Page Management';
endif;
?>
    </h2>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Page
        </a>
        <a href="templates.php" class="btn btn-outline">
            <i class="fas fa-template"></i> Templates
        </a>
        <?php elseif ($action === 'sections' && $page && isset($page['id'])): ?>
        <a href="?action=edit&id=<?php echo (int)$page['id']; ?>" class="btn btn-outline">
            <i class="fas fa-edit"></i> Edit Page Details
        </a>
        <a href="sections.php?page_id=<?php echo (int)$page['id']; ?>" class="btn btn-primary">
            <i class="fas fa-plus-circle"></i> Add Section
        </a>
        <a href="<?php echo BASE_URL; ?>/<?php echo isset($page['slug']) ? $page['slug'] : ''; ?>" target="_blank" class="btn btn-success">
            <i class="fas fa-eye"></i> View Page
        </a>
        <?php else: ?>
        <a href="pages.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Pages
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible">
        <i class="fas <?php echo $_SESSION['flash']['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo safeEcho($_SESSION['flash']['message']); ?>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Pages List -->
    <div class="pages-grid">
        <?php if (empty($pages)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No Pages Yet</h3>
                <p>Create your first page to get started</p>
                <a href="?action=add" class="btn btn-primary">Create Page</a>
            </div>
        <?php else: ?>
            <?php foreach ($pages as $pg): ?>
            <div class="page-card <?php echo isset($pg['status']) ? $pg['status'] : 'draft'; ?>">
                <div class="page-header">
                    <?php if (!empty($pg['featured_image'])): ?>
                    <div class="page-image">
                        <img src="<?php echo UPLOAD_URL . 'pages/' . $pg['featured_image']; ?>" 
                             alt="<?php echo safeEcho($pg['title']); ?>">
                    </div>
                    <?php endif; ?>
                    <div class="page-badges">
                        <?php if (!empty($pg['is_homepage'])): ?>
                        <span class="badge homepage" title="Homepage">
                            <i class="fas fa-home"></i>
                        </span>
                        <?php endif; ?>
                        <span class="badge status <?php echo isset($pg['status']) ? $pg['status'] : 'draft'; ?>">
                            <?php echo isset($pg['status']) ? ucfirst($pg['status']) : 'Draft'; ?>
                        </span>
                    </div>
                </div>
                
                <div class="page-content">
                    <h3 class="page-title">
                        <?php echo safeEcho($pg['title']); ?>
                        <?php if (isset($pg['slug'])): ?>
                        <small>/<?php echo safeEcho($pg['slug']); ?></small>
                        <?php endif; ?>
                    </h3>
                    
                    <p class="page-meta">
                        <i class="fas fa-layer-group"></i> <?php echo (int)($pg['section_count'] ?? 0); ?> sections
                        <span class="separator">•</span>
                        <i class="fas fa-user"></i> <?php echo safeEcho($pg['author_name'] ?? 'System'); ?>
                    </p>
                    
                    <?php if (!empty($pg['meta_description'])): ?>
                    <p class="page-description"><?php echo safeEcho($pg['meta_description']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="page-footer">
                    <div class="page-actions">
                        <a href="?action=sections&id=<?php echo (int)$pg['id']; ?>" class="btn-icon" title="Manage Sections">
                            <i class="fas fa-layer-group"></i>
                        </a>
                        <a href="?action=edit&id=<?php echo (int)$pg['id']; ?>" class="btn-icon" title="Edit Page">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if (empty($pg['is_homepage'])): ?>
                        <a href="?set_homepage=<?php echo (int)$pg['id']; ?>" class="btn-icon" title="Set as Homepage"
                           onclick="return confirm('Make this the homepage?')">
                            <i class="fas fa-home"></i>
                        </a>
                        <?php endif; ?>
                        <a href="?toggle=<?php echo (int)$pg['id']; ?>" class="btn-icon" title="Toggle Status">
                            <i class="fas fa-power-off"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/<?php echo isset($pg['slug']) ? $pg['slug'] : ''; ?>" target="_blank" class="btn-icon" title="View Page">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if (empty($pg['is_homepage'])): ?>
                        <a href="?delete=<?php echo (int)$pg['id']; ?>" class="btn-icon delete-btn" 
                           onclick="return confirm('Delete this page?')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Page Form -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" class="page-form" id="pageForm">
            <?php if ($page && isset($page['id'])): ?>
            <input type="hidden" name="id" value="<?php echo (int)$page['id']; ?>">
            <?php endif; ?>
            
            <div class="form-tabs">
                <button type="button" class="tab-btn active" data-tab="basic">Basic Info</button>
                <button type="button" class="tab-btn" data-tab="seo">SEO Settings</button>
                <button type="button" class="tab-btn" data-tab="advanced">Advanced</button>
            </div>
            
            <!-- Basic Info Tab -->
            <div id="tab-basic" class="tab-pane active">
                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Page Title <span class="required">*</span></label>
                            <input type="text" id="title" name="title" required 
                                   value="<?php echo safeEcho($page['title'] ?? ''); ?>"
                                   placeholder="e.g., About Us">
                        </div>
                        
                        <div class="form-group">
                            <label for="slug">URL Slug</label>
                            <input type="text" id="slug" name="slug" 
                                   value="<?php echo safeEcho($page['slug'] ?? ''); ?>"
                                   placeholder="about-us">
                            <small>Leave empty to auto-generate from title</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="layout">Layout Template</label>
                            <select id="layout" name="layout">
                                <option value="default" <?php echo (isset($page['layout']) && $page['layout'] === 'default') ? 'selected' : ''; ?>>Default</option>
                                <option value="full-width" <?php echo (isset($page['layout']) && $page['layout'] === 'full-width') ? 'selected' : ''; ?>>Full Width</option>
                                <option value="sidebar-left" <?php echo (isset($page['layout']) && $page['layout'] === 'sidebar-left') ? 'selected' : ''; ?>>Sidebar Left</option>
                                <option value="sidebar-right" <?php echo (isset($page['layout']) && $page['layout'] === 'sidebar-right') ? 'selected' : ''; ?>>Sidebar Right</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="draft" <?php echo (isset($page['status']) && $page['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo (isset($page['status']) && $page['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                <option value="archived" <?php echo (isset($page['status']) && $page['status'] === 'archived') ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="featured_image">Featured Image</label>
                        <input type="file" id="featured_image" name="featured_image" accept="image/*">
                        <?php if ($page && !empty($page['featured_image'])): ?>
                        <div class="current-image">
                            <img src="<?php echo UPLOAD_URL . 'pages/' . $page['featured_image']; ?>" 
                                 alt="Featured image">
                            <p><small>Current image</small></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- SEO Tab -->
            <div id="tab-seo" class="tab-pane">
                <div class="form-section">
                    <div class="form-group">
                        <label for="meta_title">Meta Title</label>
                        <input type="text" id="meta_title" name="meta_title" 
                               value="<?php echo safeEcho($page['meta_title'] ?? ''); ?>"
                               placeholder="SEO Title (50-60 characters)">
                        <small class="char-counter" data-target="meta_title" data-max="60"></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="meta_description">Meta Description</label>
                        <textarea id="meta_description" name="meta_description" rows="3" 
                                  placeholder="SEO Description (150-160 characters)"><?php echo safeEcho($page['meta_description'] ?? ''); ?></textarea>
                        <small class="char-counter" data-target="meta_description" data-max="160"></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="meta_keywords">Meta Keywords</label>
                        <input type="text" id="meta_keywords" name="meta_keywords" 
                               value="<?php echo safeEcho($page['meta_keywords'] ?? ''); ?>"
                               placeholder="keyword1, keyword2, keyword3">
                    </div>
                </div>
            </div>
            
            <!-- Advanced Tab -->
            <div id="tab-advanced" class="tab-pane">
                <div class="form-section">
                    <div class="form-group">
                        <label for="sort_order">Sort Order</label>
                        <input type="number" id="sort_order" name="sort_order" 
                               value="<?php echo (int)($page['sort_order'] ?? 0); ?>" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="template">Use Template</label>
                        <select id="template" name="template_id">
                            <option value="">-- Select Template --</option>
                            <?php foreach ($templates as $template): ?>
                            <option value="<?php echo (int)$template['id']; ?>">
                                <?php echo safeEcho($template['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Selecting a template will replace current content</small>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_page" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Page
                </button>
                <a href="pages.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>

<?php elseif ($action === 'sections' && $page && isset($page['id'])): ?>
    <!-- Sections Management -->
    <div class="sections-management">
        <div class="sections-header">
            <div class="section-stats">
                <div class="stat">
                    <span class="stat-value"><?php echo count($pageSections); ?></span>
                    <span class="stat-label">Total Sections</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?php echo (int)($page['view_count'] ?? 0); ?></span>
                    <span class="stat-label">Page Views</span>
                </div>
            </div>
            <div class="section-actions">
                <a href="sections.php?page_id=<?php echo (int)$page['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Section
                </a>
                <button class="btn btn-outline" onclick="saveSectionOrder()">
                    <i class="fas fa-save"></i> Save Order
                </button>
            </div>
        </div>
        
        <!-- Sections List -->
        <div class="sections-list" id="sectionsList">
            <?php if (empty($pageSections)): ?>
            <div class="empty-sections">
                <i class="fas fa-layer-group"></i>
                <h4>No sections yet</h4>
                <p>Start building your page by adding sections</p>
                <a href="sections.php?page_id=<?php echo (int)$page['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add First Section
                </a>
            </div>
            <?php else: ?>
                <?php foreach ($pageSections as $section): ?>
                <div class="section-item" data-section-id="<?php echo (int)$section['id']; ?>" data-order="<?php echo (int)($section['sort_order'] ?? 0); ?>">
                    <div class="section-drag">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    
                    <div class="section-icon">
                        <i class="fas <?php echo getSectionIcon($section['section_type'] ?? 'custom'); ?>"></i>
                    </div>
                    
                    <div class="section-info">
                        <h4><?php echo safeEcho($section['title'] ?? 'Untitled Section'); ?></h4>
                        <p class="section-type"><?php echo ucfirst($section['section_type'] ?? 'custom'); ?></p>
                        <?php if (!empty($section['subtitle'])): ?>
                        <p class="section-subtitle"><?php echo safeEcho($section['subtitle']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section-status">
                        <?php if (!empty($section['is_visible'])): ?>
                        <span class="status-badge published">Visible</span>
                        <?php else: ?>
                        <span class="status-badge draft">Hidden</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section-actions">
                        <a href="sections.php?action=edit&id=<?php echo (int)$section['id']; ?>" class="btn-icon" title="Edit Section">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?duplicate=<?php echo (int)$section['id']; ?>" class="btn-icon" title="Duplicate">
                            <i class="fas fa-copy"></i>
                        </a>
                        <a href="?toggle=<?php echo (int)$section['id']; ?>" class="btn-icon" title="Toggle Visibility">
                            <i class="fas fa-eye<?php echo empty($section['is_visible']) ? '-slash' : ''; ?>"></i>
                        </a>
                        <a href="?delete=<?php echo (int)$section['id']; ?>" class="btn-icon delete-btn" 
                           onclick="return confirm('Delete this section?')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<style>
/* Pages Grid */
.pages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.page-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    border: 2px solid transparent;
}

.page-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    border-color: var(--primary);
}

.page-card.draft {
    opacity: 0.8;
}

.page-header {
    position: relative;
    height: 150px;
    overflow: hidden;
}

.page-image {
    width: 100%;
    height: 100%;
}

.page-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.page-card:hover .page-image img {
    transform: scale(1.05);
}

.page-badges {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 5px;
}

.badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.badge.homepage {
    background: var(--primary);
    color: white;
}

.badge.status.published { background: #10b981; color: white; }
.badge.status.draft { background: #f59e0b; color: white; }
.badge.status.archived { background: #6b7280; color: white; }

.page-content {
    padding: 20px;
}

.page-title {
    font-size: 1.1rem;
    margin-bottom: 8px;
}

.page-title small {
    font-size: 0.8rem;
    color: var(--gray-500);
    font-weight: normal;
    margin-left: 5px;
}

.page-meta {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 10px;
}

.page-meta i {
    margin-right: 3px;
}

.page-meta .separator {
    margin: 0 5px;
}

.page-description {
    font-size: 0.9rem;
    color: var(--gray-600);
    line-height: 1.5;
    margin-bottom: 15px;
}

.page-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--gray-200);
    background: var(--gray-100);
}

.page-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

/* Form Container */
.form-container {
    max-width: 900px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
}

.form-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 25px;
    background: var(--gray-100);
    padding: 5px;
    border-radius: 10px;
    flex-wrap: wrap;
}

.tab-btn {
    flex: 1;
    padding: 12px 15px;
    background: none;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    color: var(--gray-600);
    transition: all 0.3s ease;
    min-width: 100px;
}

.tab-btn:hover {
    background: rgba(255,255,255,0.7);
    color: var(--primary);
}

.tab-btn.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.form-section {
    background: var(--gray-50);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    border: 1px solid var(--gray-200);
}

.form-section h3 {
    font-size: 1.1rem;
    margin-bottom: 20px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-300);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--gray-700);
    font-size: 0.95rem;
}

.form-group .required {
    color: #ef4444;
    margin-left: 3px;
}

.form-group .optional {
    font-size: 0.8rem;
    color: var(--gray-500);
    font-weight: normal;
    margin-left: 5px;
}

.form-group input[type="text"],
.form-group input[type="url"],
.form-group input[type="number"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
}

.form-group input[type="file"] {
    padding: 10px;
    border: 2px dashed var(--gray-300);
    border-radius: 10px;
    background: var(--gray-50);
    width: 100%;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: var(--gray-500);
    font-size: 0.8rem;
}

.char-counter {
    text-align: right;
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* Current Image */
.current-image {
    margin-top: 15px;
    padding: 15px;
    background: white;
    border-radius: 8px;
    border: 1px dashed var(--gray-300);
}

.current-image img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.current-image p {
    margin-top: 10px;
    color: var(--gray-600);
    font-size: 0.9rem;
}

/* Sections Management */
.sections-management {
    margin-top: 20px;
}

.sections-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.section-stats {
    display: flex;
    gap: 20px;
}

.stat {
    text-align: center;
    min-width: 100px;
}

.stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
}

.stat-label {
    font-size: 0.8rem;
    color: var(--gray-600);
}

.section-actions {
    display: flex;
    gap: 10px;
}

.sections-list {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    min-height: 300px;
}

.section-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: var(--gray-100);
    border-radius: 10px;
    margin-bottom: 10px;
    cursor: move;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.section-item:hover {
    border-color: var(--primary);
    background: white;
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.section-drag {
    color: var(--gray-400);
    cursor: grab;
    font-size: 1.2rem;
}

.section-drag:active {
    cursor: grabbing;
}

.section-icon {
    width: 40px;
    height: 40px;
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.2rem;
}

.section-info {
    flex: 1;
}

.section-info h4 {
    font-size: 1rem;
    margin-bottom: 3px;
}

.section-type {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
}

.section-subtitle {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-top: 3px;
}

.section-status {
    margin: 0 10px;
}

.section-actions {
    display: flex;
    gap: 5px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: white;
    color: var(--gray-600);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
    border: 1px solid var(--gray-200);
}

.btn-icon:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: scale(1.1);
}

.btn-icon.delete-btn:hover {
    background: #ef4444;
    border-color: #ef4444;
}

/* Empty States */
.empty-state,
.empty-sections {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}

.empty-state i,
.empty-sections i {
    font-size: 4rem;
    margin-bottom: 15px;
    color: var(--gray-300);
}

.empty-state h3,
.empty-sections h4 {
    color: var(--gray-600);
    margin-bottom: 10px;
}

.empty-state p,
.empty-sections p {
    color: var(--gray-500);
    margin-bottom: 20px;
}

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: rgba(16,185,129,0.1);
    color: #0b5e42;
    border: 1px solid rgba(16,185,129,0.3);
}

.alert-error {
    background: rgba(239,68,68,0.1);
    color: #991b1b;
    border: 1px solid rgba(239,68,68,0.3);
}

.alert i {
    font-size: 1.2rem;
}

.alert-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
    padding: 0 5px;
}

.alert-close:hover {
    opacity: 1;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid var(--gray-200);
}

/* Responsive */
@media (max-width: 768px) {
    .pages-grid {
        grid-template-columns: 1fr;
    }
    
    .form-tabs {
        flex-direction: column;
    }
    
    .tab-btn {
        width: 100%;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .sections-header {
        flex-direction: column;
        text-align: center;
    }
    
    .section-stats {
        justify-content: center;
    }
    
    .section-item {
        flex-wrap: wrap;
    }
    
    .section-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions button,
    .form-actions a {
        width: 100%;
    }
}
</style>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const tabId = this.dataset.tab;
        
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        
        this.classList.add('active');
        document.getElementById(`tab-${tabId}`).classList.add('active');
    });
});

// Auto-generate slug from title
const titleInput = document.getElementById('title');
const slugInput = document.getElementById('slug');

if (titleInput && slugInput) {
    titleInput.addEventListener('input', function() {
        if (!slugInput.value || slugInput.value === createSlug(this.value)) {
            slugInput.value = createSlug(this.value);
        }
    });
}

function createSlug(text) {
    return text.toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

// Character counters
document.querySelectorAll('.char-counter').forEach(counter => {
    const target = document.getElementById(counter.dataset.target);
    const max = counter.dataset.max;
    
    if (target) {
        const updateCounter = () => {
            const len = target.value.length;
            counter.textContent = `${len} / ${max} characters`;
            counter.style.color = len > max ? '#ef4444' : (len > max * 0.8 ? '#f59e0b' : '#666');
        };
        target.addEventListener('input', updateCounter);
        updateCounter();
    }
});

// Sections drag and drop
const sectionsList = document.getElementById('sectionsList');
if (sectionsList) {
    new Sortable(sectionsList, {
        animation: 150,
        handle: '.section-drag',
        ghostClass: 'dragging',
        onEnd: function() {
            updateSectionOrder();
        }
    });
}

function updateSectionOrder() {
    const sections = document.querySelectorAll('.section-item');
    const order = [];
    
    sections.forEach((section, index) => {
        order.push({
            id: section.dataset.sectionId,
            order: index
        });
    });
    
    // Save order via AJAX
    fetch('ajax/update-section-order.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ sections: order })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Success', 'Section order saved', 'success');
        }
    });
}

function saveSectionOrder() {
    updateSectionOrder();
}

function showNotification(title, message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    const container = document.querySelector('.content-header');
    container.parentNode.insertBefore(notification, container.nextSibling);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>