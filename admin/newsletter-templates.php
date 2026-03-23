<?php
// admin/newsletter-templates.php
// Newsletter Templates Management

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('newsletter_templates', 'id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Template deleted successfully'];
    header('Location: newsletter.php?type=templates');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $data = [
        'name' => sanitize($_POST['name']),
        'subject' => sanitize($_POST['subject']),
        'content' => $_POST['content'],
        'is_default' => isset($_POST['is_default']) ? 1 : 0
    ];
    
    if (!empty($_POST['id'])) {
        db()->update('newsletter_templates', $data, 'id = :id', ['id' => $_POST['id']]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Template updated successfully'];
    } else {
        db()->insert('newsletter_templates', $data);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Template created successfully'];
    }
    
    header('Location: newsletter.php?type=templates');
    exit;
}

// Get templates
$templates = db()->fetchAll("SELECT * FROM newsletter_templates ORDER BY is_default DESC, name") ?? [];

// Get current template for editing
$template = null;
if ($id > 0 && $action === 'edit') {
    $template = db()->fetch("SELECT * FROM newsletter_templates WHERE id = ?", [$id]);
}

// Default template content
$defaultTemplate = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2563eb; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        .button { display: inline-block; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{subject}}</h1>
        </div>
        <div class="content">
            <p>Hello {{name}},</p>
            <p>Your content here...</p>
            <p style="text-align: center;">
                <a href="#" class="button">Call to Action</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
            <p><a href="{{unsubscribe_link}}">Unsubscribe</a></p>
        </div>
    </div>
</body>
</html>';

require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><i class="fas fa-file-alt"></i> Email Templates</h2>
    <div class="header-actions">
        <div class="btn-group">
            <a href="newsletter.php?type=subscribers" class="btn <?php echo $type === 'subscribers' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-users"></i> Subscribers
            </a>
            <a href="newsletter.php?type=campaigns" class="btn <?php echo $type === 'campaigns' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-bullhorn"></i> Campaigns
            </a>
            <a href="newsletter.php?type=templates" class="btn <?php echo $type === 'templates' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-file-alt"></i> Templates
            </a>
        </div>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Template
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible">
        <?php echo $_SESSION['flash']['message']; ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?php if ($action === 'list'): ?>

<!-- Templates Grid -->
<div class="templates-grid">
    <?php foreach ($templates as $tpl): ?>
    <div class="template-card">
        <div class="template-header">
            <h3><?php echo htmlspecialchars($tpl['name']); ?></h3>
            <?php if ($tpl['is_default']): ?>
            <span class="default-badge">Default</span>
            <?php endif; ?>
        </div>
        <div class="template-preview">
            <div class="preview-subject">
                <strong>Subject:</strong> <?php echo htmlspecialchars($tpl['subject']); ?>
            </div>
            <div class="preview-content">
                <?php echo substr(strip_tags($tpl['content']), 0, 150); ?>...
            </div>
        </div>
        <div class="template-actions">
            <a href="?action=edit&id=<?php echo $tpl['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
            <a href="?delete=<?php echo $tpl['id']; ?>" class="btn btn-sm btn-outline-danger" 
               onclick="return confirm('Delete this template?')">Delete</a>
            <button class="btn btn-sm btn-outline" onclick="previewTemplate(<?php echo $tpl['id']; ?>)">Preview</button>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($templates)): ?>
    <div class="empty-state">
        <i class="fas fa-file-alt"></i>
        <h4>No templates yet</h4>
        <p>Create your first email template to use in campaigns.</p>
        <a href="?action=add" class="btn btn-primary">Create Template</a>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>

<!-- Template Form -->
<div class="form-container">
    <form method="POST" class="admin-form">
        <?php if ($template): ?>
        <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
        <?php endif; ?>
        
        <div class="form-section">
            <h3>Template Details</h3>
            
            <div class="form-group">
                <label for="name">Template Name</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo htmlspecialchars($template['name'] ?? ''); ?>"
                       placeholder="e.g., Monthly Newsletter">
            </div>
            
            <div class="form-group">
                <label for="subject">Default Subject</label>
                <input type="text" id="subject" name="subject" required 
                       value="<?php echo htmlspecialchars($template['subject'] ?? ''); ?>"
                       placeholder="e.g., This Month's Updates">
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_default" value="1" 
                           <?php echo ($template['is_default'] ?? 0) ? 'checked' : ''; ?>>
                    <span>Set as default template</span>
                </label>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Template HTML</h3>
            
            <div class="form-group">
                <label for="content">HTML Content</label>
                <textarea id="content" name="content" rows="20" class="code-editor"><?php 
                    echo htmlspecialchars($template['content'] ?? $defaultTemplate); 
                ?></textarea>
                <p class="help-text">
                    Available variables: {{name}}, {{email}}, {{subject}}, {{unsubscribe_link}}<br>
                    You can use these in your template and they'll be replaced when sending.
                </p>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="save_template" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Template
            </button>
            <a href="newsletter.php?type=templates" class="btn btn-outline">Cancel</a>
            <button type="button" class="btn btn-outline" onclick="previewCurrentTemplate()">
                <i class="fas fa-eye"></i> Preview
            </button>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div class="modal" id="previewModal">
    <div class="modal-overlay" onclick="closePreview()"></div>
    <div class="modal-container modal-lg">
        <div class="modal-header">
            <h3>Template Preview</h3>
            <button class="close-modal" onclick="closePreview()">&times;</button>
        </div>
        <div class="modal-body" id="previewContent">
            <div class="loading">Loading preview...</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closePreview()">Close</button>
        </div>
    </div>
</div>

<script>
function previewTemplate(templateId) {
    fetch(`ajax/get-template.php?id=${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPreview(data.content);
            }
        });
}

function previewCurrentTemplate() {
    const content = document.getElementById('content').value;
    showPreview(content);
}

function showPreview(content) {
    const modal = document.getElementById('previewModal');
    const previewDiv = document.getElementById('previewContent');
    
    // Replace variables with sample data
    content = content.replace(/{{name}}/g, 'John Doe');
    content = content.replace(/{{email}}/g, 'john@example.com');
    content = content.replace(/{{subject}}/g, document.getElementById('subject')?.value || 'Sample Subject');
    content = content.replace(/{{unsubscribe_link}}/g, '#');
    
    previewDiv.innerHTML = content;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}
</script>

<?php endif; ?>

<style>
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.template-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
}

.template-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.template-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.template-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.default-badge {
    background: rgba(16,185,129,0.1);
    color: #10b981;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.template-preview {
    margin-bottom: 20px;
    padding: 15px;
    background: var(--gray-100);
    border-radius: 8px;
}

.preview-subject {
    margin-bottom: 10px;
    font-size: 0.9rem;
}

.preview-content {
    color: var(--gray-600);
    font-size: 0.85rem;
    line-height: 1.5;
}

.template-actions {
    display: flex;
    gap: 8px;
}

.modal-lg {
    max-width: 800px;
    width: 90%;
}

#previewContent {
    max-height: 70vh;
    overflow-y: auto;
    padding: 20px;
    background: white;
}

.loading {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
}

.code-editor {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 14px;
    line-height: 1.5;
}
</style>

<?php require_once 'includes/footer.php'; ?>