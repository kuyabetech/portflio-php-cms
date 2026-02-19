<?php
// admin/view-message.php
// View Single Message

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pageTitle = 'View Message';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Messages', 'url' => 'messages.php'],
    ['title' => 'View Message']
];

// Mark as read
db()->update('contact_messages', ['is_read' => 1], 'id = :id', ['id' => $id]);

// Get message
$message = db()->fetch("SELECT * FROM contact_messages WHERE id = ?", [$id]);

if (!$message) {
    header('Location: messages.php');
    exit;
}

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply = sanitize($_POST['reply']);
    $subject = sanitize($_POST['subject']);
    
    // Send email
    $to = $message['email'];
    $headers = "From: " . getSetting('contact_email') . "\r\n";
    $headers .= "Reply-To: " . getSetting('contact_email') . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $fullMessage = "Hello " . $message['name'] . ",\n\n";
    $fullMessage .= $reply . "\n\n";
    $fullMessage .= "Best regards,\n";
    $fullMessage .= getSetting('site_name') . " Team";
    
    if (mail($to, $subject, $fullMessage, $headers)) {
        db()->update('contact_messages', ['is_replied' => 1], 'id = :id', ['id' => $id]);
        $success = "Reply sent successfully!";
    } else {
        $error = "Failed to send reply. Please try again.";
    }
}

// Include header
require_once 'includes/header.php';
?>

<div class="message-view">
    <div class="message-header">
        <div class="message-subject">
            <h2><?php echo htmlspecialchars($message['subject'] ?: 'No Subject'); ?></h2>
            <div class="message-meta">
                <span class="message-date">
                    <i class="far fa-calendar"></i>
                    <?php echo date('F d, Y \a\t h:i A', strtotime($message['created_at'])); ?>
                </span>
                <span class="message-status <?php echo $message['is_read'] ? 'read' : 'unread'; ?>">
                    <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                </span>
                <?php if ($message['is_replied']): ?>
                <span class="message-status replied">Replied</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="message-actions">
            <a href="mailto:<?php echo $message['email']; ?>" class="btn btn-primary">
                <i class="fas fa-reply"></i>
                Reply via Email
            </a>
            <a href="messages.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>
                Back to Messages
            </a>
        </div>
    </div>
    
    <div class="message-sender-info">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($message['name']); ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Email:</span>
                <span class="info-value">
                    <a href="mailto:<?php echo $message['email']; ?>">
                        <?php echo htmlspecialchars($message['email']); ?>
                    </a>
                </span>
            </div>
            
            <?php if ($message['phone']): ?>
            <div class="info-item">
                <span class="info-label">Phone:</span>
                <span class="info-value">
                    <a href="tel:<?php echo $message['phone']; ?>">
                        <?php echo htmlspecialchars($message['phone']); ?>
                    </a>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if ($message['company']): ?>
            <div class="info-item">
                <span class="info-label">Company:</span>
                <span class="info-value"><?php echo htmlspecialchars($message['company']); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($message['budget_range']): ?>
            <div class="info-item">
                <span class="info-label">Budget Range:</span>
                <span class="info-value budget-badge"><?php echo htmlspecialchars($message['budget_range']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">IP Address:</span>
                <span class="info-value"><code><?php echo $message['ip_address']; ?></code></span>
            </div>
        </div>
    </div>
    
    <div class="message-body">
        <h3>Message:</h3>
        <div class="message-content">
            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
        </div>
    </div>
    
    <div class="quick-reply">
        <h3>Quick Reply</h3>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" class="reply-form">
            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" 
                       value="Re: <?php echo htmlspecialchars($message['subject'] ?: 'Contact Form Submission'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="reply">Your Reply</label>
                <textarea id="reply" name="reply" rows="8" required 
                          placeholder="Write your reply here...">Dear <?php echo htmlspecialchars($message['name']); ?>,



Best regards,
<?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="send_reply" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i>
                    Send Reply
                </button>
                <a href="messages.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
.message-view {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--gray-200);
}

.message-subject h2 {
    font-size: 1.5rem;
    margin-bottom: 10px;
    color: var(--dark);
}

.message-meta {
    display: flex;
    gap: 15px;
    align-items: center;
}

.message-date {
    color: var(--gray-500);
    font-size: 0.9rem;
}

.message-status {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.message-status.read {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.message-status.unread {
    background: rgba(37, 99, 235, 0.1);
    color: var(--primary);
}

.message-status.replied {
    background: rgba(124, 58, 237, 0.1);
    color: var(--secondary);
}

.message-sender-info {
    background: var(--gray-100);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.info-label {
    font-size: 0.85rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-weight: 500;
    color: var(--dark);
}

.info-value a {
    color: var(--primary);
    text-decoration: none;
}

.info-value a:hover {
    text-decoration: underline;
}

.budget-badge {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border-radius: 20px;
    font-weight: 600;
}

.message-body {
    margin-bottom: 40px;
}

.message-body h3 {
    font-size: 1.1rem;
    margin-bottom: 15px;
    color: var(--dark);
}

.message-content {
    background: var(--gray-100);
    padding: 25px;
    border-radius: 8px;
    line-height: 1.8;
    font-size: 1rem;
    white-space: pre-wrap;
}

.quick-reply {
    border-top: 2px solid var(--gray-200);
    padding-top: 30px;
}

.quick-reply h3 {
    font-size: 1.1rem;
    margin-bottom: 20px;
    color: var(--dark);
}

.reply-form {
    max-width: 800px;
}

.reply-form textarea {
    font-family: inherit;
    line-height: 1.6;
}

@media (max-width: 768px) {
    .message-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .message-meta {
        flex-wrap: wrap;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>