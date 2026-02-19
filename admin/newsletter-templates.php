<?php
// admin/newsletter-templates.php
// Email Templates Management

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('newsletter_templates', 'id = ?', [$id]);
    header('Location: newsletter.php?type=templates&msg=deleted');
    exit;
}

// Handle set default
if (isset($_GET['default'])) {
    $id = (int)$_GET['default'];
    // Remove default from all
    db()->update('newsletter_templates', ['is_default' => 0], '1 = 1', []);
    // Set new default
    db()->update('newsletter_templates', ['is_default' => 1], 'id = :id', ['id' => $id]);
    header('Location: newsletter.php?type=templates&msg=default_set');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $data = [
        'name' => sanitize($_POST['name']),
        'subject' => sanitize($_POST['subject']),
        'content' => $_POST['content'],
        'category' => sanitize($_POST['category'])
    ];
    
    // Handle thumbnail upload
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['thumbnail'], 'templates/');
        if (isset($upload['success'])) {
            $data['thumbnail'] = $upload['filename'];
        }
    }
    
    if (!empty($_POST['id'])) {
        db()->update('newsletter_templates', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('newsletter_templates', $data);
        $msg = 'created';
    }
    
    header("Location: newsletter.php?type=templates&msg=$msg");
    exit;
}

// Get template for editing
$template = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $template = db()->fetch("SELECT * FROM newsletter_templates WHERE id = ?", [$id]);
}

// Get all templates
$templates = db()->fetchAll("SELECT * FROM newsletter_templates ORDER BY is_default DESC, name");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><?php echo $template ? 'Edit Template' : 'Email Templates'; ?></h2>
    <div class="header-actions">
        <?php if (!$template): ?>
        <a href="?type=templates&action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Template
        </a>
        <a href="?type=campaigns" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Campaigns
        </a>
        <?php else: ?>
        <a href="?type=templates" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Templates
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Template created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Template updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Template deleted successfully!';
        if ($_GET['msg'] === 'default_set') echo 'Default template updated!';
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['action']) && ($_GET['action'] === 'add' || $template)): ?>
    <!-- Template Form -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <?php if ($template): ?>
            <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Template Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo $template['name'] ?? ''; ?>"
                           placeholder="e.g., Welcome Email">
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="general" <?php echo ($template['category'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                        <option value="welcome" <?php echo ($template['category'] ?? '') === 'welcome' ? 'selected' : ''; ?>>Welcome</option>
                        <option value="newsletter" <?php echo ($template['category'] ?? '') === 'newsletter' ? 'selected' : ''; ?>>Newsletter</option>
                        <option value="promotional" <?php echo ($template['category'] ?? '') === 'promotional' ? 'selected' : ''; ?>>Promotional</option>
                        <option value="transactional" <?php echo ($template['category'] ?? '') === 'transactional' ? 'selected' : ''; ?>>Transactional</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="subject">Default Subject *</label>
                <input type="text" id="subject" name="subject" required 
                       value="<?php echo $template['subject'] ?? ''; ?>"
                       placeholder="Enter default subject line">
            </div>
            
            <div class="form-group">
                <label for="content">HTML Template *</label>
                <textarea id="content" name="content" rows="20" required 
                          placeholder="Write your HTML template here..."><?php echo $template['content'] ?? ''; ?></textarea>
                <small>Available variables: {{first_name}}, {{last_name}}, {{email}}, {{unsubscribe_url}}, {{site_name}}, {{site_url}}, {{year}}, {{primary_color}}, {{secondary_color}}</small>
            </div>
            
            <div class="form-group">
                <label for="thumbnail">Thumbnail</label>
                <input type="file" id="thumbnail" name="thumbnail" accept="image/*">
                <?php if ($template && $template['thumbnail']): ?>
                <div class="current-image">
                    <img src="<?php echo UPLOAD_URL . 'templates/' . $template['thumbnail']; ?>" 
                         alt="Thumbnail" style="max-width: 200px; margin-top: 10px;">
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_template" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $template ? 'Update Template' : 'Save Template'; ?>
                </button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Templates Grid -->
    <div class="templates-grid">
        <?php foreach ($templates as $temp): ?>
        <div class="template-card <?php echo $temp['is_default'] ? 'default' : ''; ?>">
            <div class="template-thumbnail">
                <?php if ($temp['thumbnail']): ?>
                <img src="<?php echo UPLOAD_URL . 'templates/' . $temp['thumbnail']; ?>" 
                     alt="<?php echo $temp['name']; ?>">
                <?php else: ?>
                <div class="thumbnail-placeholder">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <?php endif; ?>
                
                <?php if ($temp['is_default']): ?>
                <span class="default-badge">Default</span>
                <?php endif; ?>
            </div>
            
            <div class="template-info">
                <h3><?php echo htmlspecialchars($temp['name']); ?></h3>
                <p class="template-subject">Subject: <?php echo htmlspecialchars($temp['subject']); ?></p>
                <p class="template-category">Category: <?php echo ucfirst($temp['category']); ?></p>
            </div>
            
            <div class="template-actions">
                <a href="?type=templates&edit=<?php echo $temp['id']; ?>" class="btn btn-sm btn-outline" title="Edit">
                    <i class="fas fa-edit"></i>
                </a>
                <?php if (!$temp['is_default']): ?>
                <a href="?type=templates&default=<?php echo $temp['id']; ?>" class="btn btn-sm btn-outline" title="Set as Default">
                    <i class="fas fa-star"></i>
                </a>
                <a href="?type=templates&delete=<?php echo $temp['id']; ?>" 
                   class="btn btn-sm btn-outline delete-btn"
                   onclick="return confirm('Delete this template?')" title="Delete">
                    <i class="fas fa-trash"></i>
                </a>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline" onclick="previewTemplate(<?php echo $temp['id']; ?>)" title="Preview">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Preview Modal -->
<div class="modal" id="previewModal" style="display: none;">
    <div class="modal-dialog" style="max-width: 800px;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Template Preview</h3>
                <button class="close-modal" onclick="closePreview()">&times;</button>
            </div>
            <div class="modal-body" id="previewContent">
                <!-- Preview content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<style>
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.template-card {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    position: relative;
    border: 2px solid transparent;
}

.template-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.template-card.default {
    border-color: var(--primary);
}

.template-thumbnail {
    height: 150px;
    background: var(--gray-100);
    position: relative;
    overflow: hidden;
}

.template-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.thumbnail-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: var(--gray-400);
}

.default-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--primary);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.template-info {
    padding: 15px;
}

.template-info h3 {
    font-size: 1rem;
    margin-bottom: 5px;
}

.template-subject {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-bottom: 3px;
}

.template-category {
    font-size: 0.75rem;
    color: var(--gray-500);
}

.template-actions {
    padding: 15px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 5px;
    justify-content: flex-end;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.85rem;
}

.form-container {
    max-width: 900px;
}

.form-group textarea {
    font-family: monospace;
}
</style>

<script>
function previewTemplate(id) {
    fetch('ajax/preview-template.php?id=' + id)
        .then(response => response.text())
        .then(html => {
            document.getElementById('previewContent').innerHTML = html;
            document.getElementById('previewModal').style.display = 'block';
        });
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('previewModal');
    if (e.target === modal) {
        closePreview();
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>