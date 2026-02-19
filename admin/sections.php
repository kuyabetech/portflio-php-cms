<?php
// admin/sections.php
// Complete Section Editor

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$action = $_GET['action'] ?? 'add';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pageId = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;
$sectionType = $_GET['type'] ?? 'custom';

$pageTitle = 'Section Editor';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Pages', 'url' => 'pages.php'],
    ['title' => 'Section Editor']
];

// Get page info
$page = null;
if ($pageId > 0) {
    $page = db()->fetch("SELECT * FROM pages WHERE id = ?", [$pageId]);
}

if (!$page && $pageId > 0) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Page not found'];
    header('Location: pages.php');
    exit;
}

// Handle section delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $section = db()->fetch("SELECT page_id FROM page_sections WHERE id = ?", [$id]);
    if ($section && isset($section['page_id'])) {
        db()->delete('page_sections', 'id = ?', [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Section deleted successfully'];
        header('Location: pages.php?action=sections&id=' . $section['page_id']);
    } else {
        header('Location: pages.php');
    }
    exit;
}

// Handle section visibility toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $section = db()->fetch("SELECT is_visible, page_id FROM page_sections WHERE id = ?", [$id]);
    if ($section && isset($section['page_id'])) {
        $newStatus = $section['is_visible'] ? 0 : 1;
        db()->update('page_sections', ['is_visible' => $newStatus], 'id = :id', ['id' => $id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Section visibility updated'];
        header('Location: pages.php?action=sections&id=' . $section['page_id']);
    } else {
        header('Location: pages.php');
    }
    exit;
}

// Handle section duplication
if (isset($_GET['duplicate'])) {
    $id = (int)$_GET['duplicate'];
    $section = db()->fetch("SELECT * FROM page_sections WHERE id = ?", [$id]);
    if ($section && is_array($section)) {
        unset($section['id']);
        $section['title'] = ($section['title'] ?? '') . ' (Copy)';
        // Get max sort order
        $maxOrder = db()->fetch("SELECT MAX(sort_order) as max FROM page_sections WHERE page_id = ?", [$section['page_id']])['max'] ?? 0;
        $section['sort_order'] = $maxOrder + 1;
        db()->insert('page_sections', $section);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Section duplicated successfully'];
        header('Location: pages.php?action=sections&id=' . $section['page_id']);
    } else {
        header('Location: pages.php');
    }
    exit;
}

// Handle section form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_section'])) {
    
    $currentPageId = !empty($_POST['page_id']) ? (int)$_POST['page_id'] : $pageId;
    
    // Prepare data for insertion/update
    $data = [
        'page_id' => $currentPageId,
        'section_type' => $_POST['section_type'] ?? 'custom',
        'title' => isset($_POST['title']) ? sanitize($_POST['title']) : '',
        'subtitle' => isset($_POST['subtitle']) ? sanitize($_POST['subtitle']) : '',
        'content' => $_POST['content'] ?? '',
        'settings' => !empty($_POST['settings']) ? json_encode($_POST['settings']) : '{}',
        'background_type' => $_POST['background_type'] ?? 'none',
        'background_value' => isset($_POST['background_value']) ? sanitize($_POST['background_value']) : '',
        'text_color' => isset($_POST['text_color']) ? sanitize($_POST['text_color']) : '#333333',
        'layout_style' => $_POST['layout_style'] ?? 'default',
        'css_class' => isset($_POST['css_class']) ? sanitize($_POST['css_class']) : '',
        'custom_css' => $_POST['custom_css'] ?? '',
        'is_visible' => isset($_POST['is_visible']) ? 1 : 0
    ];
    
    // Handle background image upload
    if (isset($_FILES['background_image']) && $_FILES['background_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['background_image'], 'sections/');
        if (isset($upload['success'])) {
            $data['background_value'] = $upload['filename'];
            $data['background_type'] = 'image';
        }
    }
    
    // If this is an update
    if (!empty($_POST['id'])) {
        $sectionId = (int)$_POST['id'];
        $result = db()->update('page_sections', $data, 'id = :id', ['id' => $sectionId]);
        
        if ($result !== false) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Section updated successfully'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to update section'];
        }
        
    // This is a new section
    } else {
        // Get max sort order
        $maxOrder = db()->fetch("SELECT MAX(sort_order) as max FROM page_sections WHERE page_id = ?", [$currentPageId])['max'] ?? 0;
        $data['sort_order'] = $maxOrder + 1;
        
        $newId = db()->insert('page_sections', $data);
        
        if ($newId) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Section created successfully'];
            
            // Handle section items if any
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $index => $item) {
                    $itemData = [
                        'section_id' => $newId,
                        'title' => isset($item['title']) ? sanitize($item['title']) : '',
                        'description' => isset($item['description']) ? sanitize($item['description']) : '',
                        'icon' => isset($item['icon']) ? sanitize($item['icon']) : '',
                        'sort_order' => $index
                    ];
                    
                    if (!empty($item['price'])) {
                        $itemData['price'] = (float)$item['price'];
                    }
                    
                    if (!empty($item['position'])) {
                        $itemData['subtitle'] = sanitize($item['position']);
                    }
                    
                    db()->insert('section_items', $itemData);
                }
            }
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to create section'];
        }
    }
    
    // Redirect back to sections list
    header('Location: pages.php?action=sections&id=' . $currentPageId);
    exit;
}

// Get section for editing
$section = null;
if ($id > 0 && $action === 'edit') {
    $sectionData = db()->fetch("SELECT * FROM page_sections WHERE id = ?", [$id]);
    if ($sectionData && is_array($sectionData)) {
        $section = $sectionData;
        $section['settings'] = !empty($section['settings']) ? json_decode($section['settings'], true) : [];
        $pageId = $section['page_id'];
        // Get page info again if needed
        $page = db()->fetch("SELECT * FROM pages WHERE id = ?", [$pageId]);
    }
}

// Get section items if editing
$sectionItems = [];
if ($section && isset($section['id'])) {
    $sectionItems = db()->fetchAll("SELECT * FROM section_items WHERE section_id = ? ORDER BY sort_order ASC", [$section['id']]) ?: [];
}

// Safe echo function
function safeEcho($value, $default = '') {
    echo htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>
        <i class="fas fa-layer-group"></i>
        <?php echo $section ? 'Edit Section' : 'Add New Section'; ?>
        <?php if ($page && isset($page['title'])): ?>
        <small>on page: <?php echo safeEcho($page['title']); ?></small>
        <?php endif; ?>
    </h2>
    <div class="header-actions">
        <a href="pages.php?action=sections&id=<?php echo (int)$pageId; ?>" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Sections
        </a>
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

<!-- Section Form -->
<div class="form-container">
    <form method="POST" enctype="multipart/form-data" class="section-form" id="sectionForm">
        <input type="hidden" name="id" value="<?php echo (int)($section['id'] ?? ''); ?>">
        <input type="hidden" name="page_id" value="<?php echo (int)$pageId; ?>">
        <input type="hidden" name="section_type" value="<?php echo safeEcho($section['section_type'] ?? $sectionType); ?>">
        
        <div class="form-tabs">
            <button type="button" class="tab-btn active" data-tab="content">Content</button>
            <button type="button" class="tab-btn" data-tab="design">Design</button>
            <button type="button" class="tab-btn" data-tab="settings">Settings</button>
            <?php if (in_array($sectionType, ['services', 'team', 'features', 'pricing', 'gallery'])): ?>
            <button type="button" class="tab-btn" data-tab="items">Items</button>
            <?php endif; ?>
        </div>
        
        <!-- Content Tab -->
        <div id="tab-content" class="tab-pane active">
            <div class="form-section">
                <div class="form-group">
                    <label for="title">Section Title <span class="optional">(optional)</span></label>
                    <input type="text" id="title" name="title" 
                           value="<?php echo safeEcho($section['title'] ?? ''); ?>"
                           placeholder="e.g., Our Services">
                </div>
                
                <div class="form-group">
                    <label for="subtitle">Subtitle <span class="optional">(optional)</span></label>
                    <input type="text" id="subtitle" name="subtitle" 
                           value="<?php echo safeEcho($section['subtitle'] ?? ''); ?>"
                           placeholder="Section subtitle or tagline">
                </div>
                
                <div class="form-group">
                    <label for="content">Content <span class="optional">(optional)</span></label>
                    <textarea id="content" name="content" rows="8" 
                              placeholder="Enter your content here..."><?php echo safeEcho($section['content'] ?? ''); ?></textarea>
                    <small><i class="fas fa-info-circle"></i> Supports HTML. You can use basic formatting tags.</small>
                </div>
            </div>
        </div>
        
        <!-- Design Tab -->
        <div id="tab-design" class="tab-pane">
            <div class="form-section">
                <h3><i class="fas fa-paint-brush"></i> Background</h3>
                
                <div class="form-group">
                    <label for="background_type">Background Type</label>
                    <select id="background_type" name="background_type" onchange="toggleBackgroundFields()">
                        <option value="none" <?php echo (isset($section['background_type']) && $section['background_type'] === 'none') ? 'selected' : ''; ?>>None (Transparent)</option>
                        <option value="color" <?php echo (isset($section['background_type']) && $section['background_type'] === 'color') ? 'selected' : ''; ?>>Solid Color</option>
                        <option value="image" <?php echo (isset($section['background_type']) && $section['background_type'] === 'image') ? 'selected' : ''; ?>>Background Image</option>
                        <option value="video" <?php echo (isset($section['background_type']) && $section['background_type'] === 'video') ? 'selected' : ''; ?>>Video Background</option>
                    </select>
                </div>
                
                <div id="bg-color-field" class="form-group" style="display: none;">
                    <label for="background_value">Background Color</label>
                    <div class="color-input-group">
                        <input type="color" id="background_value" name="background_value" 
                               value="<?php echo safeEcho($section['background_value'] ?? '#ffffff'); ?>">
                        <input type="text" value="<?php echo safeEcho($section['background_value'] ?? '#ffffff'); ?>" 
                               onchange="document.getElementById('background_value').value = this.value">
                    </div>
                </div>
                
                <div id="bg-image-field" class="form-group" style="display: none;">
                    <label for="background_image">Background Image</label>
                    <input type="file" id="background_image" name="background_image" accept="image/*">
                    <?php if ($section && isset($section['background_type']) && $section['background_type'] === 'image' && !empty($section['background_value'])): ?>
                    <div class="current-image">
                        <img src="<?php echo UPLOAD_URL . 'sections/' . $section['background_value']; ?>" 
                             alt="Background">
                        <p><small>Current image</small></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div id="bg-video-field" class="form-group" style="display: none;">
                    <label for="background_video">Video URL (YouTube/Vimeo/MP4)</label>
                    <input type="url" id="background_video" name="background_value" 
                           value="<?php echo safeEcho($section['background_value'] ?? ''); ?>"
                           placeholder="https://www.youtube.com/watch?v=...">
                    <small>Supports YouTube, Vimeo, or direct MP4 URLs</small>
                </div>
                
                <h3><i class="fas fa-palette"></i> Colors</h3>
                
                <div class="form-group">
                    <label for="text_color">Text Color</label>
                    <div class="color-input-group">
                        <input type="color" id="text_color" name="text_color" 
                               value="<?php echo safeEcho($section['text_color'] ?? '#333333'); ?>">
                        <input type="text" value="<?php echo safeEcho($section['text_color'] ?? '#333333'); ?>" 
                               onchange="document.getElementById('text_color').value = this.value">
                    </div>
                </div>
                
                <h3><i class="fas fa-th"></i> Layout</h3>
                
                <div class="form-group">
                    <label for="layout_style">Layout Style</label>
                    <select id="layout_style" name="layout_style">
                        <option value="default" <?php echo (isset($section['layout_style']) && $section['layout_style'] === 'default') ? 'selected' : ''; ?>>Default</option>
                        <option value="full-width" <?php echo (isset($section['layout_style']) && $section['layout_style'] === 'full-width') ? 'selected' : ''; ?>>Full Width</option>
                        <option value="boxed" <?php echo (isset($section['layout_style']) && $section['layout_style'] === 'boxed') ? 'selected' : ''; ?>>Boxed (Container)</option>
                        <option value="split" <?php echo (isset($section['layout_style']) && $section['layout_style'] === 'split') ? 'selected' : ''; ?>>Split (50/50)</option>
                        <option value="grid-2" <?php echo (isset($section['layout_style']) && $section['layout_style'] === 'grid-2') ? 'selected' : ''; ?>>2 Column Grid</option>
                        <option value="grid-3" <?php echo (isset($section['layout_style']) && $section['layout_style'] === 'grid-3') ? 'selected' : ''; ?>>3 Column Grid</option>
                        <option value="grid-4" <?php echo (isset($section['layout_style']) && $section['layout_style'] === 'grid-4') ? 'selected' : ''; ?>>4 Column Grid</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Settings Tab -->
        <div id="tab-settings" class="tab-pane">
            <div class="form-section">
                <h3><i class="fas fa-cog"></i> Advanced Settings</h3>
                
                <div class="form-group">
                    <label for="css_class">CSS Class</label>
                    <input type="text" id="css_class" name="css_class" 
                           value="<?php echo safeEcho($section['css_class'] ?? ''); ?>"
                           placeholder="custom-class another-class">
                    <small>Space-separated CSS classes for custom styling</small>
                </div>
                
                <div class="form-group">
                    <label for="custom_css">Custom CSS</label>
                    <textarea id="custom_css" name="custom_css" rows="6" 
                              placeholder="/* Add custom CSS for this section only */
.example-class {
    property: value;
}"><?php echo safeEcho($section['custom_css'] ?? ''); ?></textarea>
                    <small>CSS will be applied only to this section</small>
                </div>
                
                <div class="form-group checkbox">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_visible" value="1" 
                               <?php echo (isset($section['is_visible']) && $section['is_visible'] == 1) ? 'checked' : ''; ?>>
                        <span class="checkbox-text">Visible on page</span>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Items Tab (for repeatable content) -->
        <?php if (in_array($sectionType, ['services', 'team', 'features', 'pricing', 'gallery'])): ?>
        <div id="tab-items" class="tab-pane">
            <div class="form-section">
                <div class="section-header">
                    <h3><i class="fas fa-list"></i> Section Items</h3>
                    <p>Add and manage repeatable items for this section</p>
                </div>
                
                <div id="items-container">
                    <?php if (!empty($sectionItems)): ?>
                        <?php foreach ($sectionItems as $index => $item): ?>
                        <div class="item-card" data-id="<?php echo $item['id']; ?>" data-index="<?php echo $index; ?>">
                            <div class="item-header">
                                <div class="item-title">
                                    <i class="fas fa-grip-vertical"></i>
                                    <span><?php echo safeEcho($item['title'] ?: 'New Item'); ?></span>
                                </div>
                                <div class="item-actions">
                                    <button type="button" class="btn-icon" onclick="toggleItem(<?php echo $index; ?>)" title="Expand/Collapse">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <button type="button" class="btn-icon delete" onclick="removeItem(<?php echo $index; ?>)" title="Delete Item">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="item-fields">
                                <div class="form-group">
                                    <label>Title</label>
                                    <input type="text" name="items[<?php echo $index; ?>][title]" value="<?php echo safeEcho($item['title']); ?>" placeholder="Enter title">
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="items[<?php echo $index; ?>][description]" rows="3" placeholder="Enter description"><?php echo safeEcho($item['description']); ?></textarea>
                                </div>
                                <?php if ($sectionType === 'team'): ?>
                                <div class="form-group">
                                    <label>Position/Role</label>
                                    <input type="text" name="items[<?php echo $index; ?>][position]" value="<?php echo safeEcho($item['subtitle']); ?>" placeholder="e.g., CEO, Developer">
                                </div>
                                <?php endif; ?>
                                <?php if ($sectionType === 'pricing'): ?>
                                <div class="form-group">
                                    <label>Price</label>
                                    <input type="number" name="items[<?php echo $index; ?>][price]" value="<?php echo safeEcho($item['price']); ?>" step="0.01" placeholder="0.00">
                                </div>
                                <?php endif; ?>
                                <div class="form-group">
                                    <label>Icon (FontAwesome class)</label>
                                    <input type="text" name="items[<?php echo $index; ?>][icon]" value="<?php echo safeEcho($item['icon']); ?>" placeholder="fas fa-code">
                                </div>
                                <div class="form-group">
                                    <label>Image</label>
                                    <input type="file" name="items[<?php echo $index; ?>][image]" accept="image/*">
                                    <?php if (!empty($item['image'])): ?>
                                    <small>Current: <?php echo $item['image']; ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="btn btn-outline btn-sm" onclick="addItem()">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
                
                <div class="info-message">
                    <i class="fas fa-info-circle"></i>
                    <span>Drag the <i class="fas fa-grip-vertical"></i> handle to reorder items</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <button type="submit" name="save_section" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Save Section
            </button>
            <a href="pages.php?action=sections&id=<?php echo (int)$pageId; ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<!-- Rest of the CSS and JavaScript remains the same... -->
<!-- Add the same CSS styles from pages.php here -->

<?php
// Include footer
require_once 'includes/footer.php';
?>