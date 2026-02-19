<?php
// admin/newsletter-campaigns.php
// Newsletter Campaigns Management

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('newsletter_campaigns', 'id = ?', [$id]);
    header('Location: newsletter.php?type=campaigns&msg=deleted');
    exit;
}

// Handle send now
if (isset($_GET['send'])) {
    $id = (int)$_GET['send'];
    sendNewsletter($id);
    header('Location: newsletter.php?type=campaigns&msg=sent');
    exit;
}

// Handle duplicate
if (isset($_GET['duplicate'])) {
    $id = (int)$_GET['duplicate'];
    $campaign = db()->fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [$id]);
    if ($campaign) {
        db()->insert('newsletter_campaigns', [
            'title' => $campaign['title'] . ' (Copy)',
            'subject' => $campaign['subject'],
            'content' => $campaign['content'],
            'status' => 'draft',
            'created_by' => $_SESSION['user_id']
        ]);
    }
    header('Location: newsletter.php?type=campaigns&msg=duplicated');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_campaign'])) {
    $data = [
        'title' => sanitize($_POST['title']),
        'subject' => sanitize($_POST['subject']),
        'content' => $_POST['content'],
        'status' => $_POST['status'],
        'created_by' => $_SESSION['user_id']
    ];
    
    if ($_POST['status'] === 'scheduled' && !empty($_POST['scheduled_at'])) {
        $data['scheduled_at'] = $_POST['scheduled_at'];
    }
    
    if (!empty($_POST['id'])) {
        db()->update('newsletter_campaigns', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('newsletter_campaigns', $data);
        $msg = 'created';
    }
    
    header("Location: newsletter.php?type=campaigns&msg=$msg");
    exit;
}

// Function to send newsletter
function sendNewsletter($campaignId) {
    $campaign = db()->fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [$campaignId]);
    if (!$campaign) return false;
    
    // Get active subscribers
    $subscribers = db()->fetchAll("SELECT * FROM newsletter_subscribers WHERE status = 'active'");
    
    $sent = 0;
    foreach ($subscribers as $subscriber) {
        // Personalize content
        $content = $campaign['content'];
        $content = str_replace('{{first_name}}', $subscriber['first_name'], $content);
        $content = str_replace('{{last_name}}', $subscriber['last_name'], $content);
        $content = str_replace('{{email}}', $subscriber['email'], $content);
        $content = str_replace('{{unsubscribe_url}}', BASE_URL . '/unsubscribe.php?email=' . urlencode($subscriber['email']), $content);
        $content = str_replace('{{site_name}}', SITE_NAME, $content);
        $content = str_replace('{{site_url}}', BASE_URL, $content);
        $content = str_replace('{{year}}', date('Y'), $content);
        
        // Send email (using mail function - replace with SMTP in production)
        $to = $subscriber['email'];
        $subject = $campaign['subject'];
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SITE_NAME . " <" . getSetting('contact_email') . ">\r\n";
        
        if (mail($to, $subject, $content, $headers)) {
            // Record send
            db()->insert('newsletter_stats', [
                'campaign_id' => $campaignId,
                'subscriber_id' => $subscriber['id'],
                'sent_at' => date('Y-m-d H:i:s')
            ]);
            $sent++;
        } else {
            // Mark as bounced
            db()->update('newsletter_subscribers', [
                'status' => 'bounced',
                'bounce_reason' => 'Email sending failed'
            ], 'id = :id', ['id' => $subscriber['id']]);
        }
    }
    
    // Update campaign
    db()->update('newsletter_campaigns', [
        'status' => 'sent',
        'sent_at' => date('Y-m-d H:i:s'),
        'recipient_count' => $sent
    ], 'id = :id', ['id' => $campaignId]);
    
    return true;
}

// Get campaign for editing
$campaign = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $campaign = db()->fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [$id]);
}

// Get all campaigns
$campaigns = db()->fetchAll("
    SELECT c.*, u.username as author,
           (SELECT COUNT(*) FROM newsletter_stats WHERE campaign_id = c.id) as sent_count,
           (SELECT COUNT(*) FROM newsletter_stats WHERE campaign_id = c.id AND opened_at IS NOT NULL) as open_count,
           (SELECT COUNT(*) FROM newsletter_stats WHERE campaign_id = c.id AND clicked_at IS NOT NULL) as click_count
    FROM newsletter_campaigns c
    LEFT JOIN users u ON c.created_by = u.id
    ORDER BY c.created_at DESC
");

// Get templates for dropdown
$templates = db()->fetchAll("SELECT * FROM newsletter_templates ORDER BY name");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><?php echo $campaign ? 'Edit Campaign' : 'Email Campaigns'; ?></h2>
    <div class="header-actions">
        <?php if (!$campaign): ?>
        <a href="?type=campaigns&action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Campaign
        </a>
        <a href="?type=templates" class="btn btn-outline">
            <i class="fas fa-template"></i>
            Templates
        </a>
        <?php else: ?>
        <a href="?type=campaigns" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Campaigns
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Campaign created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Campaign updated successfully!';
        if ($['msg'] === 'deleted') echo 'Campaign deleted successfully!';
        if ($_GET['msg'] === 'sent') echo 'Campaign sent successfully!';
        if ($_GET['msg'] === 'duplicated') echo 'Campaign duplicated successfully!';
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['action']) && ($_GET['action'] === 'add' || $campaign)): ?>
    <!-- Campaign Form -->
    <div class="form-container">
        <form method="POST" class="admin-form" id="campaignForm">
            <?php if ($campaign): ?>
            <input type="hidden" name="id" value="<?php echo $campaign['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="title">Campaign Title *</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo $campaign['title'] ?? ''; ?>"
                           placeholder="e.g., March Newsletter">
                </div>
                
                <div class="form-group">
                    <label for="subject">Email Subject *</label>
                    <input type="text" id="subject" name="subject" required 
                           value="<?php echo $campaign['subject'] ?? ''; ?>"
                           placeholder="Enter email subject line">
                </div>
            </div>
            
            <div class="form-group">
                <label for="template">Load Template</label>
                <select id="template" onchange="loadTemplate(this.value)" class="form-control">
                    <option value="">-- Select Template --</option>
                    <?php foreach ($templates as $template): ?>
                    <option value="<?php echo $template['id']; ?>">
                        <?php echo htmlspecialchars($template['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="content">Email Content *</label>
                <textarea id="content" name="content" rows="20" required 
                          placeholder="Write your email content here..."><?php echo $campaign['content'] ?? ''; ?></textarea>
                <small>Available variables: {{first_name}}, {{last_name}}, {{email}}, {{unsubscribe_url}}, {{site_name}}, {{site_url}}, {{year}}</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" onchange="toggleSchedule(this.value)">
                        <option value="draft" <?php echo ($campaign['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="scheduled" <?php echo ($campaign['status'] ?? '') === 'scheduled' ? 'selected' : ''; ?>>Schedule</option>
                        <option value="sending" <?php echo ($campaign['status'] ?? '') === 'sending' ? 'selected' : ''; ?>>Send Now</option>
                    </select>
                </div>
                
                <div class="form-group" id="scheduleField" style="display: none;">
                    <label for="scheduled_at">Schedule Date & Time</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" 
                           value="<?php echo $campaign ? date('Y-m-d\TH:i', strtotime($campaign['scheduled_at'] ?? '')) : ''; ?>">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_campaign" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $campaign ? 'Update Campaign' : 'Save Campaign'; ?>
                </button>
                <?php if ($campaign && $campaign['status'] === 'draft'): ?>
                <button type="button" class="btn btn-success" onclick="sendNow(<?php echo $campaign['id']; ?>)">
                    <i class="fas fa-paper-plane"></i>
                    Send Now
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- Campaigns List -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>Status</th>
                    <th>Recipients</th>
                    <th>Opens</th>
                    <th>Clicks</th>
                    <th>Created</th>
                    <th>Scheduled</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $camp): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($camp['title']); ?></strong>
                        <br>
                        <small><?php echo htmlspecialchars($camp['subject']); ?></small>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $camp['status']; ?>">
                            <?php echo ucfirst($camp['status']); ?>
                        </span>
                    </td>
                    <td><?php echo number_format($camp['recipient_count']); ?></td>
                    <td>
                        <?php echo number_format($camp['open_count']); ?>
                        <?php if ($camp['recipient_count'] > 0): ?>
                        <br><small><?php echo round(($camp['open_count'] / $camp['recipient_count']) * 100, 1); ?>%</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo number_format($camp['click_count']); ?>
                        <?php if ($camp['open_count'] > 0): ?>
                        <br><small><?php echo round(($camp['click_count'] / $camp['open_count']) * 100, 1); ?>% CTR</small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($camp['created_at'])); ?></td>
                    <td>
                        <?php if ($camp['scheduled_at']): ?>
                        <?php echo date('M d, Y H:i', strtotime($camp['scheduled_at'])); ?>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if ($camp['status'] === 'draft'): ?>
                            <a href="?type=campaigns&send=<?php echo $camp['id']; ?>" class="action-btn" title="Send Now"
                               onclick="return confirm('Send this campaign now?')">
                                <i class="fas fa-paper-plane"></i>
                            </a>
                            <a href="?type=campaigns&edit=<?php echo $camp['id']; ?>" class="action-btn" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <a href="?type=campaigns&duplicate=<?php echo $camp['id']; ?>" class="action-btn" title="Duplicate">
                                <i class="fas fa-copy"></i>
                            </a>
                            <a href="?type=campaigns&delete=<?php echo $camp['id']; ?>" 
                               class="action-btn delete-btn"
                               onclick="return confirm('Delete this campaign?')">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php if ($camp['status'] === 'sent'): ?>
                            <a href="?type=campaigns&stats=<?php echo $camp['id']; ?>" class="action-btn" title="Statistics">
                                <i class="fas fa-chart-bar"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($campaigns)): ?>
                <tr>
                    <td colspan="8" class="text-center">No campaigns found. Create your first campaign!</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<style>
.form-container {
    max-width: 900px;
}

.form-group textarea {
    font-family: monospace;
}

.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}
</style>

<script>
function toggleSchedule(value) {
    const scheduleField = document.getElementById('scheduleField');
    scheduleField.style.display = value === 'scheduled' ? 'block' : 'none';
}

function loadTemplate(templateId) {
    if (!templateId) return;
    
    fetch('ajax/get-template.php?id=' + templateId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('subject').value = data.template.subject;
                document.getElementById('content').value = data.template.content;
            }
        });
}

function sendNow(campaignId) {
    if (confirm('Send this campaign immediately?')) {
        window.location.href = '?type=campaigns&send=' + campaignId;
    }
}

// Initialize schedule field
document.addEventListener('DOMContentLoaded', function() {
    const status = document.getElementById('status');
    if (status) {
        toggleSchedule(status.value);
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>