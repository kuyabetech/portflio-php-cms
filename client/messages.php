<?php
/**
 * Client Messages - Communication with admin (Fixed & Regenerated)
 */

require_once dirname(__DIR__) . '/includes/init.php';

// ------------------------
// Constants (if not already defined)
if (!defined('SENDER_ADMIN')) define('SENDER_ADMIN', 'admin');
if (!defined('SENDER_CLIENT')) define('SENDER_CLIENT', 'client');
if (!defined('MSG_STATUS_UNREAD')) define('MSG_STATUS_UNREAD', 'unread');
if (!defined('MSG_STATUS_READ')) define('MSG_STATUS_READ', 'read');
// ------------------------

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if client is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$clientId = $_SESSION['client_id'];

// Get client info
$client = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientId]);

// ------------------------
// Handle reply to admin message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to_message'])) {
    $originalMessageId = (int)($_POST['message_id'] ?? 0);
    $replyMessage = trim($_POST['reply_message'] ?? '');

    // Fetch original message
    $original = db()->fetch("SELECT * FROM client_messages WHERE id = ? AND client_id = ?", [$originalMessageId, $clientId]);

    if ($original && $replyMessage !== '') {
        try {
            db()->insert('client_messages', [
                'client_id' => $clientId,
                'sender' => SENDER_CLIENT,
                'subject' => 'Re: ' . $original['subject'],
                'message' => $replyMessage,
                'status' => MSG_STATUS_UNREAD,
                'created_at' => date('Y-m-d H:i:s'),
                'reply_to_id' => $originalMessageId
            ]);

            addAdminNotification('new_client_reply', [
                'client_name' => trim($client['first_name'] . ' ' . $client['last_name']),
                'subject' => 'Re: ' . $original['subject']
            ]);

            $success = 'Reply sent successfully';
        } catch (Exception $e) {
            error_log("Reply error: " . $e->getMessage());
            $error = 'Failed to send reply';
        }
    }
}

// ------------------------
// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        $error = 'Please fill in all fields';
    } else {
        try {
            db()->insert('client_messages', [
                'client_id' => $clientId,
                'sender' => SENDER_CLIENT,
                'subject' => $subject,
                'message' => $message,
                'status' => MSG_STATUS_UNREAD,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            addAdminNotification('new_client_message', [
                'client_name' => trim($client['first_name'] . ' ' . $client['last_name']),
                'subject' => $subject
            ]);

            $success = 'Message sent successfully';
        } catch (Exception $e) {
            error_log("Message error: " . $e->getMessage());
            $error = 'Failed to send message';
        }
    }
}

// ------------------------
// Mark single message as read
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    db()->updatePositional(
        'client_messages',
        ['status' => MSG_STATUS_READ],
        'id = ? AND client_id = ? AND sender = ?',
        [$messageId, $clientId, SENDER_ADMIN]
    );
    header('Location: messages.php' . (isset($_GET['view']) ? '?view=' . (int)$_GET['view'] : ''));
    exit;
}

// ------------------------
// Pagination setup
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Total messages count
$totalMessages = db()->fetch("SELECT COUNT(*) as count FROM client_messages WHERE client_id = ?", [$clientId])['count'] ?? 0;
$totalPages = ceil($totalMessages / $perPage);

// Fetch messages
$messages = db()->fetchAll("
    SELECT * FROM client_messages 
    WHERE client_id = ? 
    ORDER BY 
        CASE WHEN status = 'unread' AND sender = 'admin' THEN 0 ELSE 1 END,
        created_at DESC
    LIMIT ? OFFSET ?
", [$clientId, $perPage, $offset]);

// ------------------------
// Conversation thread if viewing a message
$conversation = [];
$viewMessageId = isset($_GET['view']) ? (int)$_GET['view'] : 0;

if ($viewMessageId) {
    $mainMessage = db()->fetch("SELECT * FROM client_messages WHERE id = ? AND client_id = ?", [$viewMessageId, $clientId]);
    
    if ($mainMessage) {
        $baseSubject = preg_replace('/^Re:\s*/i', '', $mainMessage['subject']);

        // Fetch conversation by subject or reply chain
        $conversation = db()->fetchAll("
            SELECT * FROM client_messages 
            WHERE client_id = ? AND (subject LIKE ? OR subject LIKE ? OR id = ? OR reply_to_id = ?)
            ORDER BY created_at ASC
        ", [
            $clientId,
            "%$baseSubject%",
            "%Re: $baseSubject%",
            $viewMessageId,
            $viewMessageId
        ]);

        // Mark all admin messages in this conversation as read
        db()->updatePositional(
            'client_messages',
            ['status' => MSG_STATUS_READ],
            'client_id = ? AND sender = ? AND (subject LIKE ? OR subject LIKE ?)',
            [
                $clientId,
                SENDER_ADMIN,
                "%$baseSubject%",
                "%Re: $baseSubject%"
            ]
        );
    }
}

// ------------------------
// Get unread count
$unreadCount = db()->fetch("
    SELECT COUNT(*) as count FROM client_messages 
    WHERE client_id = ? AND status = 'unread' AND sender = 'admin'
", [$clientId])['count'] ?? 0;

// ------------------------
$pageTitle = 'Messages';
require_once '../includes/client-header.php';
?>

<div class="messages-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-envelope"></i> Messages</h1>
            <p>Communicate with our support team</p>
        </div>
        
        <!-- New Message Button -->
        <button class="btn-primary" onclick="showNewMessageForm()">
            <i class="fas fa-plus"></i> New Message
        </button>
    </div>

    <!-- Unread Banner -->
    <?php if ($unreadCount > 0 && !$viewMessageId): ?>
    <div class="unread-banner">
        <i class="fas fa-envelope"></i>
        You have <strong><?php echo $unreadCount; ?> unread message<?php echo $unreadCount > 1 ? 's' : ''; ?></strong>
        <a href="#messages-list" class="btn-small">View</a>
    </div>
    <?php endif; ?>

    <!-- New Message Form (hidden by default) -->
    <div class="new-message-form" id="newMessageForm" style="display: none;">
        <div class="form-card">
            <h3>Send New Message</h3>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" required 
                           placeholder="Enter message subject">
                </div>
                
                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" rows="5" required 
                              placeholder="Type your message here..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="hideNewMessageForm()">
                        Cancel
                    </button>
                    <button type="submit" name="send_message" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="messages-container <?php echo $viewMessageId ? 'conversation-view' : ''; ?>">
        <!-- Messages List Column -->
        <div class="messages-list-column" id="messages-list">
            <div class="messages-list-header">
                <h3>All Messages</h3>
                <?php if ($totalPages > 1): ?>
                <select class="page-selector" onchange="window.location.href='?p='+this.value<?php echo $viewMessageId ? '+&view=<?php echo $viewMessageId; ?>' : ''; ?>">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $i == $page ? 'selected' : ''; ?>>
                        Page <?php echo $i; ?> of <?php echo $totalPages; ?>
                    </option>
                    <?php endfor; ?>
                </select>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($messages)): ?>
            <div class="messages-list">
                <?php foreach ($messages as $message): 
                    $isUnread = $message['status'] === 'unread' && $message['sender'] === 'admin';
                    $isActive = $viewMessageId == $message['id'];
                ?>
                <a href="?view=<?php echo $message['id']; ?><?php echo $page > 1 ? '&p='.$page : ''; ?>" 
                   class="message-list-item <?php echo $isUnread ? 'unread' : ''; ?> <?php echo $isActive ? 'active' : ''; ?>">
                    <div class="message-icon">
                        <?php if ($message['sender'] === 'admin'): ?>
                        <i class="fas fa-user-tie"></i>
                        <?php else: ?>
                        <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="message-content">
                        <div class="message-header">
                            <div class="message-sender">
                                <strong>
                                    <?php echo $message['sender'] === 'admin' ? 'Support Team' : 'You'; ?>
                                </strong>
                                <?php if ($message['sender'] === 'admin'): ?>
                                <span class="staff-badge">Staff</span>
                                <?php endif; ?>
                            </div>
                            <div class="message-date">
                                <?php echo timeAgo($message['created_at']); ?>
                            </div>
                        </div>
                        
                        <div class="message-subject">
                            <?php echo htmlspecialchars($message['subject']); ?>
                        </div>
                        
                        <div class="message-preview">
                            <?php echo htmlspecialchars(substr($message['message'], 0, 60)); ?>
                            <?php if (strlen($message['message']) > 60): ?>...<?php endif; ?>
                        </div>
                        
                        <?php if ($isUnread): ?>
                        <span class="unread-badge">New</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-list">
                <i class="fas fa-envelope-open"></i>
                <p>No messages yet</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Conversation Column -->
        <div class="conversation-column">
            <?php if (!empty($conversation)): ?>
                <!-- Conversation Header -->
                <div class="conversation-header">
                    <h2><?php echo htmlspecialchars($mainMessage['subject']); ?></h2>
                    <button class="btn-small btn-outline" onclick="showReplyForm()">
                        <i class="fas fa-reply"></i> Reply
                    </button>
                </div>
                
                <!-- Conversation Messages -->
                <div class="conversation-messages" id="conversationMessages">
                    <?php foreach ($conversation as $msg): 
                        $isAdmin = $msg['sender'] === 'admin';
                    ?>
                    <div class="conversation-message <?php echo $isAdmin ? 'admin-message' : 'client-message'; ?>">
                        <div class="message-bubble">
                            <div class="message-meta">
                                <span class="message-author">
                                    <?php echo $isAdmin ? 'Support Team' : 'You'; ?>
                                </span>
                                <span class="message-time">
                                    <?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?>
                                </span>
                            </div>
                            <div class="message-text">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Reply Form -->
                <div class="reply-form" id="replyForm" style="display: none;">
                    <form method="POST">
                        <input type="hidden" name="message_id" value="<?php echo $viewMessageId; ?>">
                        <div class="form-group">
                            <textarea name="reply_message" rows="3" placeholder="Type your reply..." required></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-secondary btn-small" onclick="hideReplyForm()">Cancel</button>
                            <button type="submit" name="reply_to_message" class="btn-primary btn-small">
                                <i class="fas fa-paper-plane"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-conversation">
                    <i class="fas fa-comments"></i>
                    <h3>Select a message to view the conversation</h3>
                    <p>Choose a message from the list to start reading</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.messages-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.header-content h1 {
    font-size: 28px;
    color: #1e293b;
    margin-bottom: 5px;
}

.header-content p {
    color: #64748b;
}

/* Unread Banner */
.unread-banner {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.3s ease;
}

.unread-banner .btn-small {
    background: white;
    color: #667eea;
    padding: 5px 15px;
    border-radius: 5px;
    text-decoration: none;
    margin-left: auto;
    font-weight: 600;
    font-size: 0.9rem;
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Buttons */
.btn-primary {
    background: #667eea;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary:hover {
    background: #5a67d8;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #f1f5f9;
    color: #1e293b;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.btn-small {
    padding: 8px 16px;
    font-size: 13px;
    border-radius: 6px;
}

.btn-outline {
    background: transparent;
    border: 2px solid #667eea;
    color: #667eea;
}

.btn-outline:hover {
    background: #667eea;
    color: white;
}

/* Messages Container */
.messages-container {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    min-height: 600px;
}

/* Messages List Column */
.messages-list-column {
    background: #f8fafc;
    border-right: 1px solid #e2e8f0;
}

.messages-list-header {
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.messages-list-header h3 {
    font-size: 16px;
    color: #1e293b;
}

.page-selector {
    padding: 5px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    font-size: 12px;
    background: white;
}

.messages-list {
    height: 550px;
    overflow-y: auto;
}

.message-list-item {
    display: flex;
    gap: 12px;
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    text-decoration: none;
    transition: all 0.2s ease;
}

.message-list-item:hover {
    background: #f1f5f9;
}

.message-list-item.active {
    background: #eff6ff;
    border-left: 3px solid #667eea;
}

.message-list-item.unread {
    background: #ffffff;
    font-weight: 500;
}

.message-icon {
    width: 40px;
    height: 40px;
    background: #e2e8f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    font-size: 18px;
    flex-shrink: 0;
}

.message-content {
    flex: 1;
    min-width: 0;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.message-sender {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    color: #1e293b;
}

.staff-badge {
    background: #667eea;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.message-date {
    font-size: 11px;
    color: #64748b;
}

.message-subject {
    font-size: 14px;
    color: #1e293b;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.message-preview {
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.unread-badge {
    display: inline-block;
    background: #667eea;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    margin-top: 5px;
}

.empty-list {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}

.empty-list i {
    font-size: 40px;
    margin-bottom: 10px;
}

/* Conversation Column */
.conversation-column {
    display: flex;
    flex-direction: column;
    height: 600px;
    background: white;
}

.conversation-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.conversation-header h2 {
    font-size: 18px;
    color: #1e293b;
    margin: 0;
}

.conversation-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.conversation-message {
    display: flex;
    flex-direction: column;
}

.conversation-message.admin-message {
    align-items: flex-start;
}

.conversation-message.client-message {
    align-items: flex-end;
}

.message-bubble {
    max-width: 80%;
    padding: 12px 16px;
    border-radius: 12px;
    position: relative;
}

.admin-message .message-bubble {
    background: #f1f5f9;
    border-bottom-left-radius: 4px;
}

.client-message .message-bubble {
    background: #667eea;
    color: white;
    border-bottom-right-radius: 4px;
}

.message-meta {
    margin-bottom: 5px;
    font-size: 12px;
}

.admin-message .message-author {
    color: #475569;
    font-weight: 600;
}

.client-message .message-author {
    color: rgba(255,255,255,0.9);
    font-weight: 600;
}

.message-time {
    color: #94a3b8;
    margin-left: 8px;
    font-size: 11px;
}

.client-message .message-time {
    color: rgba(255,255,255,0.7);
}

.message-text {
    font-size: 14px;
    line-height: 1.5;
    word-wrap: break-word;
}

.reply-form {
    padding: 20px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}

.reply-form textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    resize: vertical;
    font-family: inherit;
}

.reply-form textarea:focus {
    outline: none;
    border-color: #667eea;
}

.reply-form .form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 10px;
}

.no-conversation {
    text-align: center;
    padding: 100px 20px;
    color: #94a3b8;
}

.no-conversation i {
    font-size: 60px;
    margin-bottom: 20px;
    color: #cbd5e1;
}

.no-conversation h3 {
    color: #1e293b;
    margin-bottom: 10px;
}

/* New Message Form */
.new-message-form {
    margin-bottom: 30px;
}

.form-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.form-card h3 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #475569;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Alerts */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

/* Responsive */
@media (max-width: 768px) {
    .messages-container {
        grid-template-columns: 1fr;
    }
    
    .messages-list-column {
        display: <?php echo $viewMessageId ? 'none' : 'block'; ?>;
    }
    
    .conversation-column {
        display: <?php echo $viewMessageId ? 'flex' : 'none'; ?>;
        height: auto;
        min-height: 500px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .btn-primary {
        width: 100%;
        justify-content: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions button {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .message-bubble {
        max-width: 100%;
    }
    
    .conversation-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
}
</style>

<script>
function showNewMessageForm() {
    document.getElementById('newMessageForm').style.display = 'block';
    document.getElementById('newMessageForm').scrollIntoView({ behavior: 'smooth' });
}

function hideNewMessageForm() {
    document.getElementById('newMessageForm').style.display = 'none';
}

function showReplyForm() {
    document.getElementById('replyForm').style.display = 'block';
    document.getElementById('conversationMessages').scrollTop = document.getElementById('conversationMessages').scrollHeight;
}

function hideReplyForm() {
    document.getElementById('replyForm').style.display = 'none';
}

// Auto scroll to bottom of conversation
document.addEventListener('DOMContentLoaded', function() {
    const conversationMessages = document.getElementById('conversationMessages');
    if (conversationMessages) {
        conversationMessages.scrollTop = conversationMessages.scrollHeight;
    }
});

// Mark message as read when viewing
<?php if ($viewMessageId): ?>
fetch('?mark_read=1&id=<?php echo $viewMessageId; ?>', { method: 'GET' });
<?php endif; ?>
</script>

<?php require_once '../includes/client-footer.php'; ?>