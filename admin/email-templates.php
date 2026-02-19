<?php
// admin/email-templates.php
// Email Templates Management

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Email Templates';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Email Templates']
];

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('email_templates', 'id = ?', [$id]);
    header('Location: email-templates.php?msg=deleted');
    exit;
}

// Handle toggle active
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $template = db()->fetch("SELECT is_active FROM email_templates WHERE id = ?", [$id]);
    $newStatus = $template['is_active'] ? 0 : 1;
    db()->update('email_templates', ['is_active' => $newStatus], 'id = :id', ['id' => $id]);
    header('Location: email-templates.php?msg=toggled');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_template'])) {
    $data = [
        'template_key' => sanitize($_POST['template_key']),
        'name' => sanitize($_POST['name']),
        'subject' => sanitize($_POST['subject']),
        'body' => $_POST['body'],
        'variables' => sanitize($_POST['variables']),
        'category' => sanitize($_POST['category']),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Check if template key exists
    $existing = db()->fetch("SELECT id FROM email_templates WHERE template_key = ? AND id != ?", 
        [$data['template_key'], $_POST['id'] ?? 0]);
    
    if ($existing) {
        $error = 'Template key already exists';
    } else {
        if (!empty($_POST['id'])) {
            db()->update('email_templates', $data, 'id = :id', ['id' => $_POST['id']]);
            $msg = 'updated';
        } else {
            db()->insert('email_templates', $data);
            $msg = 'created';
        }
        header("Location: email-templates.php?msg=$msg");
        exit;
    }
}

// Handle test send
if (isset($_POST['send_test'])) {
    $templateId = (int)$_POST['template_id'];
    $testEmail = $_POST['test_email'];
    
    $template = db()->fetch("SELECT * FROM email_templates WHERE id = ?", [$templateId]);
    if ($template && filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        // Prepare test variables
        $variables = [
            'client_name' => 'Test Client',
            'project_name' => 'Test Project',
            'invoice_number' => 'INV-2024-0001',
            'amount' => '1,000.00',
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'payment_method' => 'Credit Card',
            'transaction_id' => 'TEST-' . time()
        ];
        
        $sent = mailer()->sendTemplate($template['template_key'], [
            'email' => $testEmail,
            'name' => 'Test User'
        ], $variables);
        
        if ($sent) {
            $testMessage = "Test email sent to $testEmail";
        } else {
            $testError = "Failed to send test email";
        }
    }
}

// Get template for editing
$template = null;
if ($id > 0 && $action === 'edit') {
    $template = db()->fetch("SELECT * FROM email_templates WHERE id = ?", [$id]);
}

// Get all templates
$templates = db()->fetchAll("SELECT * FROM email_templates ORDER BY category, name");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><?php echo $action === 'edit' ? 'Edit Template' : ($action === 'add' ? 'New Template' : 'Email Templates'); ?></h2>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Template
        </a>
        <a href="email-queue.php" class="btn btn-outline">
            <i class="fas fa-clock"></i>
            Email Queue
        </a>
        <a href="email-logs.php" class="btn btn-outline">
            <i class="fas fa-history"></i>
            Email Logs
        </a>
        <?php else: ?>
        <a href="email-templates.php" class="btn btn-outline">
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
        if ($_GET['msg'] === 'toggled') echo 'Template status updated!';
        ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (isset($testMessage)): ?>
    <div class="alert alert-success"><?php echo $testMessage; ?></div>
<?php endif; ?>

<?php if (isset($testError)): ?>
    <div class="alert alert-error"><?php echo $testError; ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Templates Grid -->
    <div class="templates-grid">
        <?php foreach ($templates as $t): ?>
        <div class="template-card <?php echo !$t['is_active'] ? 'inactive' : ''; ?>">
            <div class="template-header">
                <span class="template-category"><?php echo ucfirst($t['category']); ?></span>
                <span class="template-status <?php echo $t['is_active'] ? 'active' : 'inactive'; ?>">
                    <?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            
            <div class="template-body">
                <h3><?php echo htmlspecialchars($t['name']); ?></h3>
                <p class="template-key"><code><?php echo $t['template_key']; ?></code></p>
                <p class="template-subject">Subject: <?php echo htmlspecialchars($t['subject']); ?></p>
            </div>
            
            <div class="template-actions">
                <a href="?action=edit&id=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline" title="Edit">
                    <i class="fas fa-edit"></i>
                </a>
                <a href="?toggle=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline" title="Toggle Status">
                    <i class="fas fa-power-off"></i>
                </a>
                <button class="btn btn-sm btn-outline" onclick="previewTemplate(<?php echo $t['id']; ?>)" title="Preview">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline" onclick="testTemplate(<?php echo $t['id']; ?>)" title="Send Test">
                    <i class="fas fa-paper-plane"></i>
                </button>
                <?php if (!in_array($t['template_key'], ['welcome_client', 'invoice_created', 'payment_confirmation'])): ?>
                <a href="?delete=<?php echo $t['id']; ?>" class="btn btn-sm btn-outline delete-btn" 
                   onclick="return confirm('Delete this template?')" title="Delete">
                    <i class="fas fa-trash"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Template Form -->
    <div class="form-container">
        <form method="POST" class="admin-form">
            <?php if ($template): ?>
            <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="template_key">Template Key *</label>
                    <input type="text" id="template_key" name="template_key" required 
                           value="<?php echo $template['template_key'] ?? ''; ?>"
                           placeholder="e.g., welcome_email, invoice_created">
                    <small>Unique identifier for this template (no spaces)</small>
                </div>
                
                <div class="form-group">
                    <label for="name">Template Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo $template['name'] ?? ''; ?>"
                           placeholder="e.g., Welcome Email">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="client" <?php echo ($template['category'] ?? '') === 'client' ? 'selected' : ''; ?>>Client</option>
                        <option value="invoice" <?php echo ($template['category'] ?? '') === 'invoice' ? 'selected' : ''; ?>>Invoice</option>
                        <option value="payment" <?php echo ($template['category'] ?? '') === 'payment' ? 'selected' : ''; ?>>Payment</option>
                        <option value="reminder" <?php echo ($template['category'] ?? '') === 'reminder' ? 'selected' : ''; ?>>Reminder</option>
                        <option value="project" <?php echo ($template['category'] ?? '') === 'project' ? 'selected' : ''; ?>>Project</option>
                        <option value="task" <?php echo ($template['category'] ?? '') === 'task' ? 'selected' : ''; ?>>Task</option>
                        <option value="communication" <?php echo ($template['category'] ?? '') === 'communication' ? 'selected' : ''; ?>>Communication</option>
                        <option value="file" <?php echo ($template['category'] ?? '') === 'file' ? 'selected' : ''; ?>>File</option>
                        <option value="test" <?php echo ($template['category'] ?? '') === 'test' ? 'selected' : ''; ?>>Test</option>
                    </select>
                </div>
                
                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="is_active" <?php echo ($template['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        Active
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="subject">Email Subject *</label>
                <input type="text" id="subject" name="subject" required 
                       value="<?php echo $template['subject'] ?? ''; ?>"
                       placeholder="Enter email subject line">
            </div>
            
            <div class="form-group">
                <label for="variables">Available Variables</label>
                <textarea id="variables" name="variables" rows="2" readonly 
                          placeholder="Variables will be listed here"><?php echo $template['variables'] ?? '{{client_name}}, {{project_name}}, {{invoice_number}}, {{amount}}, {{due_date}}, {{site_name}}, {{site_url}}, {{year}}'; ?></textarea>
                <small>Use {{variable_name}} in your template</small>
            </div>
            
            <div class="form-group">
                <label for="body">Email Body (HTML) *</label>
                <textarea id="body" name="body" rows="20" required class="code-editor"><?php echo $template['body'] ?? ''; ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_template" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $template ? 'Update Template' : 'Create Template'; ?>
                </button>
                <a href="email-templates.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
    
    <!-- Test Send Modal -->
    <div class="modal" id="testModal" style="display: none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Send Test Email</h3>
                    <button class="close-modal" onclick="closeTestModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="template_id" id="test_template_id">
                        <div class="form-group">
                            <label for="test_email">Email Address</label>
                            <input type="email" id="test_email" name="test_email" required 
                                   value="<?php echo getSetting('contact_email'); ?>">
                        </div>
                        <button type="submit" name="send_test" class="btn btn-primary">Send Test</button>
                    </form>
                </div>
            </div>
        </div>
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
                <!-- Preview will load here -->
            </div>
        </div>
    </div>
</div>

<style>
.templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.template-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    position: relative;
    border: 2px solid transparent;
}

.template-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.15);
}

.template-card.inactive {
    opacity: 0.7;
    background: var(--gray-100);
}

.template-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.template-category {
    font-size: 0.8rem;
    padding: 4px 10px;
    background: var(--gray-200);
    border-radius: 20px;
    color: var(--gray-700);
}

.template-status {
    font-size: 0.8rem;
    padding: 4px 10px;
    border-radius: 20px;
}

.template-status.active {
    background: rgba(16,185,129,0.1);
    color: #10b981;
}

.template-status.inactive {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}

.template-body h3 {
    font-size: 1.1rem;
    margin-bottom: 8px;
}

.template-key {
    font-size: 0.8rem;
    margin-bottom: 10px;
}

.template-key code {
    background: var(--gray-100);
    padding: 2px 6px;
    border-radius: 4px;
}

.template-subject {
    font-size: 0.9rem;
    color: var(--gray-600);
    margin-bottom: 15px;
}

.template-actions {
    display: flex;
    gap: 5px;
    justify-content: flex-end;
    border-top: 1px solid var(--gray-200);
    padding-top: 15px;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 0.85rem;
}

.form-container {
    max-width: 1000px;
}

.code-editor {
    font-family: monospace;
    line-height: 1.5;
}

/* Responsive */
@media (max-width: 768px) {
    .templates-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function previewTemplate(id) {
    fetch('ajax/preview-email-template.php?id=' + id)
        .then(response => response.text())
        .then(html => {
            document.getElementById('previewContent').innerHTML = html;
            document.getElementById('previewModal').style.display = 'block';
        });
}

function closePreview() {
    document.getElementById('previewModal').style.display = 'none';
}

function testTemplate(id) {
    document.getElementById('test_template_id').value = id;
    document.getElementById('testModal').style.display = 'block';
}

function closeTestModal() {
    document.getElementById('testModal').style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const previewModal = document.getElementById('previewModal');
    const testModal = document.getElementById('testModal');
    
    if (e.target === previewModal) {
        closePreview();
    }
    if (e.target === testModal) {
        closeTestModal();
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>