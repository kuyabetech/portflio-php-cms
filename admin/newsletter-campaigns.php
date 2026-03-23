<?php
// admin/newsletter-campaigns.php
// Newsletter Campaigns Management

// Handle actions
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('newsletter_campaigns', 'id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign deleted successfully'];
    header('Location: newsletter.php?type=campaigns');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_campaign'])) {
    $data = [
        'name' => sanitize($_POST['name']),
        'subject' => sanitize($_POST['subject']),
        'content' => $_POST['content'],
        'status' => $_POST['status'] ?? 'draft',
        'scheduled_at' => !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null
    ];
    
    if (!empty($_POST['id'])) {
        db()->update('newsletter_campaigns', $data, 'id = :id', ['id' => $_POST['id']]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign updated successfully'];
    } else {
        db()->insert('newsletter_campaigns', $data);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign created successfully'];
    }
    
    header('Location: newsletter.php?type=campaigns');
    exit;
}

// Handle send campaign
if (isset($_GET['send'])) {
    $id = (int)$_GET['send'];
    $result = sendCampaign($id);
    
    if ($result['success']) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => $result['message']];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => $result['message']];
    }
    
    header('Location: newsletter.php?type=campaigns');
    exit;
}

// Handle test send
if (isset($_POST['test_send'])) {
    $id = (int)$_POST['campaign_id'];
    $testEmail = sanitize($_POST['test_email']);
    
    $result = sendTestCampaign($id, $testEmail);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Handle batch send via AJAX
if (isset($_POST['batch_send'])) {
    $campaignId = (int)$_POST['campaign_id'];
    $batchSize = isset($_POST['batch_size']) ? (int)$_POST['batch_size'] : 50;
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : 0;
    
    $result = processCampaignBatch($campaignId, $batchSize, $offset);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

/**
 * Send campaign function
 */
function sendCampaign($campaignId) {
    $campaign = db()->fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [$campaignId]);
    if (!$campaign) {
        return ['success' => false, 'message' => 'Campaign not found'];
    }
    
    // Check if campaign is already sent
    if ($campaign['status'] === 'sent') {
        return ['success' => false, 'message' => 'Campaign already sent'];
    }
    
    // Get all active subscribers
    $subscribers = db()->fetchAll(
        "SELECT id, email, first_name, last_name FROM newsletter_subscribers WHERE status = 'active'"
    );
    
    if (empty($subscribers)) {
        return ['success' => false, 'message' => 'No active subscribers found'];
    }
    
    // Clear any existing queue for this campaign
    db()->delete('newsletter_queue', 'campaign_id = ?', [$campaignId]);
    
    // Create queue entries
    $queued = 0;
    foreach ($subscribers as $sub) {
        $fullName = trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? ''));
        
        db()->insert('newsletter_queue', [
            'campaign_id' => $campaignId,
            'subscriber_id' => $sub['id'],
            'email' => $sub['email'],
            'name' => $fullName ?: $sub['email'],
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $queued++;
    }
    
    // Update campaign status
    db()->update('newsletter_campaigns', [
        'status' => 'sending',
        'sent_count' => 0,
        'failed_count' => 0
    ], 'id = :id', ['id' => $campaignId]);
    
    return [
        'success' => true, 
        'message' => "Campaign queued successfully. $queued subscribers ready to receive emails."
    ];
}

/**
 * Process a batch of emails
 */
function processCampaignBatch($campaignId, $batchSize = 50, $offset = 0) {
    $campaign = db()->fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [$campaignId]);
    if (!$campaign) {
        return ['success' => false, 'error' => 'Campaign not found'];
    }
    
    // Get pending emails for this campaign
    $queue = db()->fetchAll(
        "SELECT * FROM newsletter_queue 
         WHERE campaign_id = ? AND status = 'pending' 
         LIMIT ? OFFSET ?",
        [$campaignId, $batchSize, $offset]
    );
    
    if (empty($queue)) {
        // No more pending emails, mark campaign as sent
        db()->update('newsletter_campaigns', [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $campaignId]);
        
        return ['success' => true, 'completed' => true];
    }
    
    $sent = 0;
    $failed = 0;
    
    foreach ($queue as $item) {
        $result = sendEmail($item, $campaign);
        
        if ($result['success']) {
            db()->update('newsletter_queue', [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $item['id']]);
            $sent++;
        } else {
            $attempts = $item['attempts'] + 1;
            $status = $attempts >= 3 ? 'failed' : 'pending';
            
            db()->update('newsletter_queue', [
                'status' => $status,
                'attempts' => $attempts,
                'error_message' => $result['error']
            ], 'id = :id', ['id' => $item['id']]);
            $failed++;
        }
    }
    
    // Update campaign counts
    $totalSent = db()->fetchColumn(
        "SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = ? AND status = 'sent'",
        [$campaignId]
    ) ?: 0;
    
    $totalFailed = db()->fetchColumn(
        "SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = ? AND status = 'failed'",
        [$campaignId]
    ) ?: 0;
    
    $totalPending = db()->fetchColumn(
        "SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = ? AND status = 'pending'",
        [$campaignId]
    ) ?: 0;
    
    db()->update('newsletter_campaigns', [
        'sent_count' => $totalSent,
        'failed_count' => $totalFailed
    ], 'id = :id', ['id' => $campaignId]);
    
    return [
        'success' => true,
        'sent' => $sent,
        'failed' => $failed,
        'total_sent' => $totalSent,
        'total_failed' => $totalFailed,
        'total_pending' => $totalPending,
        'remaining' => $totalPending,
        'completed' => $totalPending === 0
    ];
}

/**
 * Send individual email
 */
function sendEmail($queueItem, $campaign) {
    $to = $queueItem['email'];
    $subject = $campaign['subject'];
    
    // Replace placeholders
    $content = $campaign['content'];
    $content = str_replace('{{name}}', $queueItem['name'], $content);
    $content = str_replace('{{email}}', $queueItem['email'], $content);
    $content = str_replace('{{subject}}', $campaign['subject'], $content);
    
    // Add unsubscribe link
    $unsubscribeLink = BASE_URL . "/unsubscribe.php?email=" . urlencode($queueItem['email']);
    $content = str_replace('{{unsubscribe_link}}', $unsubscribeLink, $content);
    
    // Headers
    $fromEmail = getSetting('newsletter_email', 'newsletter@' . $_SERVER['HTTP_HOST']);
    $fromName = getSetting('company_name', SITE_NAME);
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $fromName <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
    $headers .= "List-Unsubscribe: <$unsubscribeLink>\r\n";
    
    // Try to send
    try {
        $sent = mail($to, $subject, $content, $headers);
        
        if ($sent) {
            return ['success' => true];
        } else {
            $error = error_get_last();
            return ['success' => false, 'error' => $error['message'] ?? 'Unknown error'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send test email
 */
function sendTestCampaign($campaignId, $testEmail) {
    $campaign = db()->fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [$campaignId]);
    if (!$campaign) {
        return ['success' => false, 'message' => 'Campaign not found'];
    }
    
    if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }
    
    // Prepare test content
    $content = $campaign['content'];
    $content = str_replace('{{name}}', 'Test User', $content);
    $content = str_replace('{{email}}', $testEmail, $content);
    $content = str_replace('{{subject}}', $campaign['subject'], $content);
    $content = str_replace('{{unsubscribe_link}}', BASE_URL . '/unsubscribe.php?email=' . urlencode($testEmail), $content);
    
    $fromEmail = getSetting('newsletter_email', 'newsletter@' . $_SERVER['HTTP_HOST']);
    $fromName = getSetting('company_name', SITE_NAME);
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $fromName <$fromEmail>\r\n";
    
    $sent = mail($testEmail, "[TEST] " . $campaign['subject'], $content, $headers);
    
    if ($sent) {
        return ['success' => true, 'message' => 'Test email sent successfully'];
    } else {
        $error = error_get_last();
        return ['success' => false, 'message' => 'Failed to send: ' . ($error['message'] ?? 'Unknown error')];
    }
}

// Get campaigns with statistics
$campaigns = db()->fetchAll("
    SELECT c.*, 
           (SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = c.id) as total_queued,
           (SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = c.id AND status = 'sent') as total_sent,
           (SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = c.id AND status = 'failed') as total_failed,
           (SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = c.id AND status = 'pending') as total_pending
    FROM newsletter_campaigns c
    ORDER BY c.created_at DESC
") ?: [];

// Get current campaign for editing
$campaign = null;
if ($id > 0 && $action === 'edit') {
    $campaign = db()->fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [$id]);
}

require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><i class="fas fa-bullhorn"></i> Email Campaigns</h2>
    <div class="header-actions">
        <div class="btn-group">
            <a href="newsletter.php?type=subscribers" class="btn <?php echo $type === 'subscribers' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-users"></i> Subscribers
            </a>
            <a href="newsletter.php?type=campaigns" class="btn <?php echo $type === 'campaigns' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-bullhorn"></i> Campaigns
            </a>
        </div>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Campaign
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible">
        <i class="fas <?php echo $_SESSION['flash']['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo $_SESSION['flash']['message']; ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?php if ($action === 'list'): ?>

<!-- Campaign Stats -->
<div class="stats-mini-grid">
    <div class="stat-mini-card">
        <div class="stat-mini-icon blue">
            <i class="fas fa-bullhorn"></i>
        </div>
        <div class="stat-mini-content">
            <h3>Total Campaigns</h3>
            <span class="stat-mini-value"><?php echo count($campaigns); ?></span>
        </div>
    </div>
    
    <div class="stat-mini-card">
        <div class="stat-mini-icon green">
            <i class="fas fa-paper-plane"></i>
        </div>
        <div class="stat-mini-content">
            <h3>Sent</h3>
            <span class="stat-mini-value">
                <?php 
                $sentCampaigns = array_filter($campaigns, fn($c) => $c['status'] === 'sent');
                echo count($sentCampaigns); 
                ?>
            </span>
        </div>
    </div>
    
    <div class="stat-mini-card">
        <div class="stat-mini-icon orange">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-mini-content">
            <h3>Scheduled</h3>
            <span class="stat-mini-value">
                <?php 
                $scheduled = array_filter($campaigns, fn($c) => $c['status'] === 'scheduled');
                echo count($scheduled); 
                ?>
            </span>
        </div>
    </div>
    
    <div class="stat-mini-card">
        <div class="stat-mini-icon purple">
            <i class="fas fa-pen"></i>
        </div>
        <div class="stat-mini-content">
            <h3>Drafts</h3>
            <span class="stat-mini-value">
                <?php 
                $drafts = array_filter($campaigns, fn($c) => $c['status'] === 'draft');
                echo count($drafts); 
                ?>
            </span>
        </div>
    </div>
</div>

<!-- Campaigns Table -->
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Sent/Failed</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($campaigns as $camp): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($camp['name']); ?></strong></td>
                <td><?php echo htmlspecialchars($camp['subject']); ?></td>
                <td>
                    <span class="status-badge <?php echo $camp['status']; ?>">
                        <?php echo ucfirst($camp['status']); ?>
                    </span>
                    <?php if ($camp['status'] === 'scheduled' && !empty($camp['scheduled_at'])): ?>
                    <br><small><?php echo date('M d, H:i', strtotime($camp['scheduled_at'])); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($camp['status'] === 'sending' || $camp['status'] === 'sent'): ?>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?php 
                            $total = ($camp['total_queued'] ?? 0);
                            $sent = ($camp['total_sent'] ?? 0);
                            $percent = $total > 0 ? round(($sent / $total) * 100) : 0;
                            echo $percent;
                        ?>%"></div>
                        <span class="progress-text"><?php echo $percent; ?>%</span>
                    </div>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($camp['status'] === 'sent' || $camp['status'] === 'sending'): ?>
                    <span class="sent-count"><?php echo $camp['total_sent'] ?? 0; ?></span>
                    <?php if (($camp['total_failed'] ?? 0) > 0): ?>
                    <br><span class="failed-count"><?php echo $camp['total_failed']; ?></span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('M d, Y', strtotime($camp['created_at'])); ?></td>
                <td>
                    <div class="action-buttons">
                        <a href="?action=edit&id=<?php echo $camp['id']; ?>" class="action-btn" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <?php if ($camp['status'] === 'draft'): ?>
                        <a href="?send=<?php echo $camp['id']; ?>" class="action-btn" title="Send Now" 
                           onclick="return confirm('Send this campaign to all active subscribers?')">
                            <i class="fas fa-paper-plane"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($camp['status'] === 'draft' || $camp['status'] === 'scheduled'): ?>
                        <button class="action-btn" title="Test Send" 
                                onclick="showTestForm(<?php echo $camp['id']; ?>)">
                            <i class="fas fa-vial"></i>
                        </button>
                        <?php endif; ?>
                        
                        <a href="?delete=<?php echo $camp['id']; ?>" class="action-btn delete-btn" 
                           onclick="return confirm('Delete this campaign?')" title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($campaigns)): ?>
            <tr>
                <td colspan="7" class="empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <h3>No campaigns yet</h3>
                    <p>Create your first email campaign to start engaging with subscribers.</p>
                    <a href="?action=add" class="btn btn-primary">Create Campaign</a>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Test Send Modal -->
<div class="modal" id="testModal">
    <div class="modal-overlay" onclick="closeTestModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-vial"></i> Send Test Email</h3>
            <button class="close-modal" onclick="closeTestModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="testForm">
                <input type="hidden" name="campaign_id" id="testCampaignId">
                
                <div class="form-group">
                    <label for="test_email">Test Email Address</label>
                    <input type="email" id="test_email" name="test_email" class="form-control" 
                           placeholder="your@email.com" required>
                </div>
            </form>
            <div id="testResult" class="test-result" style="display: none;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeTestModal()">Cancel</button>
            <button class="btn btn-primary" onclick="sendTest()">
                <i class="fas fa-paper-plane"></i> Send Test
            </button>
        </div>
    </div>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>

<!-- Campaign Form -->
<div class="form-container">
    <form method="POST" class="admin-form" id="campaignForm">
        <?php if ($campaign): ?>
        <input type="hidden" name="id" value="<?php echo $campaign['id']; ?>">
        <?php endif; ?>
        
        <div class="form-section">
            <h3>Campaign Details</h3>
            
            <div class="form-group">
                <label for="name">Campaign Name</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo htmlspecialchars($campaign['name'] ?? ''); ?>"
                       placeholder="e.g., March Newsletter">
            </div>
            
            <div class="form-group">
                <label for="subject">Email Subject</label>
                <input type="text" id="subject" name="subject" required 
                       value="<?php echo htmlspecialchars($campaign['subject'] ?? ''); ?>"
                       placeholder="e.g., This Month's Updates">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" onchange="toggleScheduled()">
                        <option value="draft" <?php echo ($campaign['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="scheduled" <?php echo ($campaign['status'] ?? '') === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                    </select>
                </div>
                
                <div class="form-group" id="scheduled_datetime" style="display: <?php echo ($campaign['status'] ?? '') === 'scheduled' ? 'block' : 'none'; ?>;">
                    <label for="scheduled_at">Schedule Date & Time</label>
                    <input type="datetime-local" id="scheduled_at" name="scheduled_at" 
                           value="<?php echo isset($campaign['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($campaign['scheduled_at'])) : ''; ?>">
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Email Content</h3>
            
            <div class="form-group">
                <label for="content">HTML Content</label>
                <textarea id="content" name="content" rows="15" class="code-editor"><?php echo htmlspecialchars($campaign['content'] ?? ''); ?></textarea>
                <div class="help-box">
                    <p><strong>Available variables:</strong></p>
                    <ul>
                        <li><code>{{name}}</code> - Subscriber's name</li>
                        <li><code>{{email}}</code> - Subscriber's email</li>
                        <li><code>{{subject}}</code> - Email subject</li>
                        <li><code>{{unsubscribe_link}}</code> - Unsubscribe link</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="save_campaign" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Campaign
            </button>
            <a href="newsletter.php?type=campaigns" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<?php endif; ?>

<style>
/* Campaign Styles */
.stats-mini-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-mini-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.stat-mini-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-mini-icon.blue { background: rgba(37,99,235,0.1); color: #2563eb; }
.stat-mini-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-mini-icon.orange { background: rgba(245,158,11,0.1); color: #f59e0b; }
.stat-mini-icon.purple { background: rgba(124,58,237,0.1); color: #7c3aed; }

.stat-mini-content h3 {
    font-size: 0.9rem;
    color: var(--gray-600);
    margin-bottom: 5px;
}

.stat-mini-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
}

.status-badge.draft { background: rgba(107,114,128,0.1); color: #6b7280; }
.status-badge.scheduled { background: rgba(245,158,11,0.1); color: #f59e0b; }
.status-badge.sending { background: rgba(37,99,235,0.1); color: #2563eb; }
.status-badge.sent { background: rgba(16,185,129,0.1); color: #10b981; }

.progress-container {
    position: relative;
    height: 20px;
    background: var(--gray-200);
    border-radius: 10px;
    overflow: hidden;
    width: 120px;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #2563eb, #7c3aed);
    border-radius: 10px;
    transition: width 0.3s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 11px;
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.sent-count {
    color: #10b981;
    font-weight: 600;
}

.failed-count {
    color: #ef4444;
    font-weight: 600;
}

.help-box {
    background: var(--gray-100);
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.help-box ul {
    margin: 10px 0 0;
    padding-left: 20px;
}

.help-box code {
    background: white;
    padding: 2px 6px;
    border-radius: 4px;
    color: var(--primary);
}

.test-result {
    margin-top: 15px;
    padding: 10px;
    border-radius: 6px;
}

.test-result.success {
    background: rgba(16,185,129,0.1);
    color: #10b981;
}

.test-result.error {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}

@media (max-width: 768px) {
    .stats-mini-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-mini-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
let currentCampaignId = 0;

function toggleScheduled() {
    const status = document.getElementById('status').value;
    const datetimeDiv = document.getElementById('scheduled_datetime');
    datetimeDiv.style.display = status === 'scheduled' ? 'block' : 'none';
}

function showTestForm(campaignId) {
    currentCampaignId = campaignId;
    document.getElementById('testCampaignId').value = campaignId;
    document.getElementById('test_email').value = '';
    document.getElementById('testResult').style.display = 'none';
    document.getElementById('testModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeTestModal() {
    document.getElementById('testModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function sendTest() {
    const testEmail = document.getElementById('test_email').value;
    const testResult = document.getElementById('testResult');
    
    if (!testEmail) {
        alert('Please enter an email address');
        return;
    }
    
    const formData = new FormData();
    formData.append('test_send', '1');
    formData.append('campaign_id', currentCampaignId);
    formData.append('test_email', testEmail);
    
    fetch('newsletter.php?type=campaigns', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        testResult.style.display = 'block';
        testResult.className = 'test-result ' + (data.success ? 'success' : 'error');
        testResult.innerHTML = data.message;
        
        if (data.success) {
            setTimeout(closeTestModal, 2000);
        }
    });
}

// Close modal when clicking overlay
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closeTestModal();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>