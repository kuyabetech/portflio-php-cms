<?php
// admin/view-message.php
// Detailed message view with reply history

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: messages.php');
    exit;
}

// Get message details
$message = db()->fetch("SELECT * FROM contact_messages WHERE id = ?", [$id]);

if (!$message) {
    header('Location: messages.php');
    exit;
}

// Mark as read
if (!$message['is_read']) {
    db()->update('contact_messages', ['is_read' => 1], 'id = :id', ['id' => $id]);
}

// Get reply history
$replies = db()->fetchAll("
    SELECT mr.*, u.username as replied_by_name 
    FROM message_replies mr
    LEFT JOIN users u ON mr.sent_by = u.id
    WHERE mr.message_id = ?
    ORDER BY mr.sent_at ASC
", [$id]);

$pageTitle = 'Message from ' . $message['name'];
require_once 'includes/header.php';
?>

<div class="content-header">
    <h1>
        <i class="fas fa-envelope"></i> 
        Message Details
    </h1>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="openQuickReply(<?php echo $id; ?>, '<?php echo htmlspecialchars($message['email']); ?>', '<?php echo htmlspecialchars($message['name']); ?>', '<?php echo htmlspecialchars(addslashes($message['subject'])); ?>')">
            <i class="fas fa-reply"></i> Reply
        </button>
        <a href="messages.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Messages
        </a>
    </div>
</div>

<div class="message-view-container">
    <!-- Message Header -->
    <div class="message-header-card">
        <div class="sender-info">
            <div class="sender-avatar">
                <?php echo strtoupper(substr($message['name'], 0, 1)); ?>
            </div>
            <div class="sender-details">
                <h2><?php echo htmlspecialchars($message['name']); ?></h2>
                <p class="sender-contact">
                    <a href="mailto:<?php echo $message['email']; ?>"><?php echo $message['email']; ?></a>
                    <?php if (!empty($message['phone'])): ?>
                    • <a href="tel:<?php echo $message['phone']; ?>"><?php echo $message['phone']; ?></a>
                    <?php endif; ?>
                </p>
                <?php if (!empty($message['company'])): ?>
                <p class="sender-company">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($message['company']); ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="message-metadata">
                <div class="metadata-item">
                    <span class="label">Received:</span>
                    <span class="value"><?php echo date('M j, Y \a\t g:i A', strtotime($message['created_at'])); ?></span>
                </div>
                <div class="metadata-item">
                    <span class="label">Status:</span>
                    <span class="value">
                        <span class="status-badge <?php echo $message['is_read'] ? 'read' : 'unread'; ?>">
                            <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                        </span>
                    </span>
                </div>
                <?php if (!empty($message['ip_address'])): ?>
                <div class="metadata-item">
                    <span class="label">IP Address:</span>
                    <span class="value"><?php echo $message['ip_address']; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="message-subject">
            <strong>Subject:</strong> 
            <?php echo $message['subject'] ? htmlspecialchars($message['subject']) : '<em>No Subject</em>'; ?>
        </div>
    </div>
    
    <!-- Original Message -->
    <div class="message-content-card">
        <h3><i class="fas fa-envelope-open"></i> Original Message</h3>
        <div class="message-body">
            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
        </div>
    </div>
    
    <!-- Reply History -->
    <?php if (!empty($replies)): ?>
    <div class="reply-history-card">
        <h3><i class="fas fa-history"></i> Reply History</h3>
        <div class="reply-timeline">
            <?php foreach ($replies as $reply): ?>
            <div class="reply-item">
                <div class="reply-header">
                    <div class="reply-sender">
                        <i class="fas fa-user-circle"></i>
                        <strong><?php echo htmlspecialchars($reply['replied_by_name'] ?? 'System'); ?></strong>
                        <span class="reply-time"><?php echo date('M j, Y \a\t g:i A', strtotime($reply['sent_at'])); ?></span>
                    </div>
                </div>
                <div class="reply-body">
                    <?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="quick-actions-card">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="action-buttons-grid">
            <a href="mailto:<?php echo $message['email']; ?>?subject=Re: <?php echo urlencode($message['subject']); ?>" class="action-button">
                <i class="fas fa-envelope"></i>
                <span>Email Client</span>
            </a>
            
            <?php if (!empty($message['phone'])): ?>
            <a href="tel:<?php echo $message['phone']; ?>" class="action-button">
                <i class="fas fa-phone"></i>
                <span>Call Client</span>
            </a>
            <?php endif; ?>
            
            <button class="action-button" onclick="openQuickReply(<?php echo $id; ?>, '<?php echo htmlspecialchars($message['email']); ?>', '<?php echo htmlspecialchars($message['name']); ?>', '<?php echo htmlspecialchars(addslashes($message['subject'])); ?>')">
                <i class="fas fa-reply"></i>
                <span>Quick Reply</span>
            </button>
            
            <a href="?delete=<?php echo $id; ?>" class="action-button delete" onclick="return confirm('Delete this message?')">
                <i class="fas fa-trash"></i>
                <span>Delete</span>
            </a>
        </div>
    </div>
</div>

<!-- Quick Reply Modal (reuse from messages.php) -->
<div class="modal" id="quickReplyModal">
    <div class="modal-overlay" onclick="closeQuickReply()"></div>
    <div class="modal-container modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-reply"></i> Quick Reply</h3>
            <button class="close-modal" onclick="closeQuickReply()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="reply-info">
                <div class="reply-recipient">
                    <strong>To:</strong> <span id="replyToEmail"></span>
                </div>
                <div class="reply-subject">
                    <strong>Subject:</strong> <span id="replySubject"></span>
                </div>
            </div>
            
            <form id="quickReplyForm">
                <input type="hidden" name="message_id" id="replyMessageId">
                
                <div class="form-group">
                    <label for="replyMessage">Your Reply <span class="required">*</span></label>
                    <textarea id="replyMessage" name="reply_message" rows="6" required 
                              placeholder="Type your reply here..." class="reply-textarea"></textarea>
                </div>
                
                <div class="form-group checkbox">
                    <label class="checkbox-label">
                        <input type="checkbox" name="send_copy" value="1" checked>
                        <span>Send a copy to myself</span>
                    </label>
                </div>
            </form>
            
            <div class="reply-status" id="replyStatus"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeQuickReply()">Cancel</button>
            <button class="btn btn-primary" onclick="sendQuickReply()">
                <i class="fas fa-paper-plane"></i> Send Reply
            </button>
        </div>
    </div>
</div>

<style>
/* Message View Styles */
.message-view-container {
    max-width: 900px;
    margin: 0 auto;
}

.message-header-card,
.message-content-card,
.reply-history-card,
.quick-actions-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.sender-info {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.sender-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 600;
}

.sender-details {
    flex: 1;
}

.sender-details h2 {
    margin: 0 0 5px 0;
    font-size: 24px;
}

.sender-contact {
    margin-bottom: 5px;
}

.sender-contact a {
    color: #2563eb;
    text-decoration: none;
}

.sender-contact a:hover {
    text-decoration: underline;
}

.sender-company {
    color: #64748b;
    font-size: 0.95rem;
}

.message-metadata {
    background: #f8fafc;
    padding: 15px;
    border-radius: 8px;
    min-width: 250px;
}

.metadata-item {
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
}

.metadata-item:last-child {
    margin-bottom: 0;
}

.metadata-item .label {
    color: #64748b;
    font-weight: 500;
}

.metadata-item .value {
    color: #1e293b;
    font-weight: 600;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.read {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.unread {
    background: #fee2e2;
    color: #991b1b;
}

.message-subject {
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
    font-size: 1.1rem;
}

.message-content-card h3,
.reply-history-card h3,
.quick-actions-card h3 {
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #1e293b;
}

.message-body {
    background: #f8fafc;
    padding: 25px;
    border-radius: 8px;
    line-height: 1.7;
    white-space: pre-wrap;
    font-size: 1rem;
}

.reply-timeline {
    position: relative;
    padding-left: 30px;
}

.reply-timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e2e8f0;
}

.reply-item {
    position: relative;
    margin-bottom: 25px;
}

.reply-item::before {
    content: '';
    position: absolute;
    left: -34px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #2563eb;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.reply-header {
    margin-bottom: 10px;
}

.reply-sender {
    display: flex;
    align-items: center;
    gap: 8px;
}

.reply-sender i {
    color: #2563eb;
    font-size: 1.2rem;
}

.reply-time {
    color: #64748b;
    font-size: 0.85rem;
    margin-left: 10px;
}

.reply-body {
    background: #f8fafc;
    padding: 15px;
    border-radius: 8px;
    margin-left: 0;
    border-left: 3px solid #2563eb;
}

.action-buttons-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.action-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 20px;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    color: #1e293b;
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-button:hover {
    background: #2563eb;
    border-color: #2563eb;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(37,99,235,0.15);
}

.action-button i {
    font-size: 1.5rem;
}

.action-button span {
    font-size: 0.9rem;
    font-weight: 500;
}

.action-button.delete:hover {
    background: #dc2626;
    border-color: #dc2626;
}

/* Responsive */
@media (max-width: 768px) {
    .sender-info {
        flex-direction: column;
    }
    
    .message-metadata {
        width: 100%;
    }
    
    .action-buttons-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .action-buttons-grid {
        grid-template-columns: 1fr;
    }
    
    .message-header-card,
    .message-content-card,
    .reply-history-card {
        padding: 15px;
    }
    
    .sender-avatar {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
    
    .sender-details h2 {
        font-size: 20px;
    }
}
</style>

<script>
// Quick Reply Functions (same as in messages.php)
function openQuickReply(messageId, email, name, subject) {
    currentMessageId = messageId;
    currentMessageEmail = email;
    currentMessageName = name;
    currentMessageSubject = subject;
    
    document.getElementById('replyMessageId').value = messageId;
    document.getElementById('replyToEmail').textContent = `${name} <${email}>`;
    document.getElementById('replySubject').textContent = subject ? `Re: ${subject}` : 'Re: Contact Form Message';
    document.getElementById('replyMessage').value = '';
    document.getElementById('replyStatus').innerHTML = '';
    
    document.getElementById('quickReplyModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeQuickReply() {
    document.getElementById('quickReplyModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function sendQuickReply() {
    const replyMessage = document.getElementById('replyMessage').value.trim();
    const sendCopy = document.querySelector('input[name="send_copy"]').checked;
    const statusDiv = document.getElementById('replyStatus');
    
    if (!replyMessage) {
        statusDiv.innerHTML = '<span style="color: #dc2626;">Please enter a reply message</span>';
        return;
    }
    
    statusDiv.innerHTML = '<span style="color: #64748b;"><i class="fas fa-spinner fa-spin"></i> Sending...</span>';
    
    const formData = new FormData();
    formData.append('quick_reply', '1');
    formData.append('message_id', currentMessageId);
    formData.append('reply_message', replyMessage);
    formData.append('send_copy', sendCopy ? '1' : '0');
    
    fetch('messages.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.innerHTML = '<span style="color: #10b981;"><i class="fas fa-check-circle"></i> Reply sent successfully!</span>';
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            statusDiv.innerHTML = `<span style="color: #dc2626;"><i class="fas fa-exclamation-circle"></i> ${data.message}</span>`;
        }
    })
    .catch(error => {
        statusDiv.innerHTML = '<span style="color: #dc2626;"><i class="fas fa-exclamation-circle"></i> Network error</span>';
    });
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeQuickReply();
    }
});

// Close modal when clicking overlay
document.querySelector('.modal-overlay')?.addEventListener('click', closeQuickReply);
</script>

<?php require_once 'includes/footer.php'; ?>