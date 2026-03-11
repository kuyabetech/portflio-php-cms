<?php
/**
 * Client Message Detail - View and reply to individual messages
 */

require_once dirname(__DIR__) . '/includes/init.php';

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
$messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$messageId) {
    header('Location: messages.php');
    exit;
}

// Get message details
$message = db()->fetch("
    SELECT m.*, 
           CASE 
               WHEN m.sender = 'admin' THEN 'Support Team'
               ELSE CONCAT(c.first_name, ' ', c.last_name)
           END as sender_name
    FROM client_messages m
    LEFT JOIN client_users c ON m.sender_id = c.id
    WHERE m.id = ? AND (m.client_id = ? OR m.sender_id = ?)
", [$messageId, $clientId, $clientId]);

if (!$message) {
    header('Location: messages.php');
    exit;
}

// Mark as read if from admin
if ($message['sender'] === 'admin' && $message['status'] === 'unread') {
    db()->update('client_messages', ['status' => 'read'], 'id = ?', [$messageId]);
}

// Get conversation thread (all messages with same subject/context)
$threadMessages = db()->fetchAll("
    SELECT m.*,
           CASE 
               WHEN m.sender = 'admin' THEN 'Support Team'
               ELSE CONCAT(c.first_name, ' ', c.last_name)
           END as sender_name,
           c.avatar as sender_avatar
    FROM client_messages m
    LEFT JOIN client_users c ON m.sender_id = c.id
    WHERE (m.client_id = ? OR m.sender_id = ?)
      AND (m.subject LIKE ? OR m.parent_id = ? OR m.id = ?)
    ORDER BY m.created_at ASC
", [
    $clientId, 
    $clientId, 
    '%' . $message['subject'] . '%',
    $messageId,
    $messageId
]);

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $replyMessage = trim($_POST['message'] ?? '');
    
    if (empty($replyMessage)) {
        $error = 'Please enter a message';
    } else {
        try {
            db()->insert('client_messages', [
                'client_id' => $clientId,
                'sender_id' => $clientId,
                'sender' => 'client',
                'parent_id' => $messageId,
                'subject' => 'Re: ' . $message['subject'],
                'message' => $replyMessage,
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Notify admin
            addAdminNotification('new_message_reply', [
                'client_name' => getClientName($clientId),
                'subject' => $message['subject']
            ]);
            
            $success = 'Reply sent successfully';
            
            // Refresh thread
            $threadMessages = db()->fetchAll("
                SELECT m.*,
                       CASE 
                           WHEN m.sender = 'admin' THEN 'Support Team'
                           ELSE CONCAT(c.first_name, ' ', c.last_name)
                       END as sender_name,
                       c.avatar as sender_avatar
                FROM client_messages m
                LEFT JOIN client_users c ON m.sender_id = c.id
                WHERE (m.client_id = ? OR m.sender_id = ?)
                  AND (m.subject LIKE ? OR m.parent_id = ? OR m.id = ?)
                ORDER BY m.created_at ASC
            ", [
                $clientId, 
                $clientId, 
                '%' . $message['subject'] . '%',
                $messageId,
                $messageId
            ]);
            
        } catch (Exception $e) {
            error_log("Message reply error: " . $e->getMessage());
            $error = 'Failed to send reply';
        }
    }
}

$pageTitle = $message['subject'];
require_once '../includes/client-header.php';
?>

<div class="message-detail-page">
    <!-- Back Navigation -->
    <div class="back-nav">
        <a href="messages.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Messages
        </a>
    </div>

    <!-- Message Header -->
    <div class="message-header-card">
        <div class="message-subject">
            <h1><?php echo htmlspecialchars($message['subject']); ?></h1>
            <span class="message-status status-<?php echo $message['status']; ?>">
                <?php echo ucfirst($message['status']); ?>
            </span>
        </div>
        
        <div class="message-participants">
            <div class="participant">
                <span class="label">From:</span>
                <span class="value">
                    <?php echo htmlspecialchars($message['sender_name']); ?>
                    <?php if ($message['sender'] === 'admin'): ?>
                    <span class="staff-badge">Staff</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="participant">
                <span class="label">To:</span>
                <span class="value">You</span>
            </div>
            <div class="participant">
                <span class="label">Date:</span>
                <span class="value"><?php echo date('F d, Y \a\t h:i A', strtotime($message['created_at'])); ?></span>
            </div>
        </div>
    </div>

    <!-- Conversation Thread -->
    <div class="conversation-thread">
        <?php foreach ($threadMessages as $msg): 
            $isClient = $msg['sender'] === 'client';
        ?>
        <div class="message-bubble <?php echo $isClient ? 'client' : 'staff'; ?>" 
             id="message-<?php echo $msg['id']; ?>">
            
            <div class="message-sender">
                <div class="sender-avatar">
                    <?php if (!empty($msg['sender_avatar'])): ?>
                    <img src="<?php echo UPLOAD_URL . 'avatars/' . $msg['sender_avatar']; ?>" 
                         alt="<?php echo htmlspecialchars($msg['sender_name']); ?>">
                    <?php else: ?>
                    <div class="avatar-placeholder">
                        <?php echo strtoupper(substr($msg['sender_name'], 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="sender-info">
                    <span class="sender-name">
                        <?php echo htmlspecialchars($msg['sender_name']); ?>
                        <?php if (!$isClient): ?>
                        <span class="staff-badge">Staff</span>
                        <?php endif; ?>
                    </span>
                    <span class="message-time">
                        <?php echo timeAgo($msg['created_at']); ?>
                    </span>
                </div>
            </div>
            
            <div class="message-content">
                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
            </div>
            
            <?php if ($msg['id'] == $messageId && $msg['status'] === 'unread'): ?>
            <div class="message-status-indicator">
                <i class="fas fa-check-circle"></i> Read
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Reply Form -->
    <div class="reply-form-card">
        <h3><i class="fas fa-reply"></i> Reply to this message</h3>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" class="reply-form">
            <div class="form-group">
                <textarea name="message" rows="5" required 
                          placeholder="Type your reply here..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="send_reply" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reply
                </button>
            </div>
        </form>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions-card">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="action-buttons">
            <a href="support.php?subject=Re: <?php echo urlencode($message['subject']); ?>" class="action-btn">
                <i class="fas fa-headset"></i>
                <span>Convert to Support Ticket</span>
            </a>
            <a href="#" onclick="window.print(); return false;" class="action-btn">
                <i class="fas fa-print"></i>
                <span>Print Conversation</span>
            </a>
            <a href="messages.php?delete=<?php echo $messageId; ?>" class="action-btn delete" 
               onclick="return confirm('Delete this conversation?')">
                <i class="fas fa-trash"></i>
                <span>Delete Conversation</span>
            </a>
        </div>
    </div>
</div>

<style>
.message-detail-page {
    max-width: 800px;
    margin: 0 auto;
}

/* Back Navigation */
.back-nav {
    margin-bottom: 20px;
}

.back-link {
    color: #64748b;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.back-link:hover {
    color: #667eea;
}

/* Message Header Card */
.message-header-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.message-subject {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.message-subject h1 {
    font-size: 22px;
    color: #1e293b;
}

.message-status {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-unread { background: #fee2e2; color: #991b1b; }
.status-read { background: #e2e8f0; color: #475569; }
.status-replied { background: #d1fae5; color: #065f46; }

.message-participants {
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.participant {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.participant .label {
    color: #64748b;
    font-weight: 500;
}

.participant .value {
    color: #1e293b;
    font-weight: 600;
}

.staff-badge {
    display: inline-block;
    padding: 2px 6px;
    background: #667eea;
    color: white;
    border-radius: 4px;
    font-size: 10px;
    margin-left: 5px;
}

/* Conversation Thread */
.conversation-thread {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}

.message-bubble {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    position: relative;
}

.message-bubble.client {
    border-left: 4px solid #667eea;
}

.message-bubble.staff {
    border-left: 4px solid #10b981;
    background: #f8fafc;
}

.message-sender {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 15px;
}

.sender-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
}

.sender-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 600;
}

.sender-info {
    flex: 1;
}

.sender-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 15px;
    display: block;
    margin-bottom: 3px;
}

.message-time {
    font-size: 11px;
    color: #94a3b8;
}

.message-content {
    color: #475569;
    line-height: 1.6;
    font-size: 15px;
    white-space: pre-wrap;
}

.message-status-indicator {
    position: absolute;
    bottom: 10px;
    right: 15px;
    font-size: 11px;
    color: #10b981;
    display: flex;
    align-items: center;
    gap: 3px;
}

/* Reply Form */
.reply-form-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.reply-form-card h3 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.reply-form-card h3 i {
    color: #667eea;
}

.reply-form textarea {
    width: 100%;
    padding: 15px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    resize: vertical;
    transition: all 0.2s ease;
}

.reply-form textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Quick Actions Card */
.quick-actions-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.quick-actions-card h3 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.quick-actions-card h3 i {
    color: #667eea;
}

.action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    text-decoration: none;
    color: #1e293b;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
    transform: translateY(-2px);
}

.action-btn i {
    font-size: 24px;
    color: #667eea;
}

.action-btn:hover i {
    color: white;
}

.action-btn span {
    font-size: 13px;
    font-weight: 500;
}

.action-btn.delete:hover {
    background: #ef4444;
    border-color: #ef4444;
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

/* Form Actions */
.form-actions {
    margin-top: 20px;
    text-align: right;
}

.btn-primary {
    background: #667eea;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary:hover {
    background: #5a67d8;
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .message-subject {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .message-participants {
        flex-direction: column;
        gap: 10px;
    }
    
    .message-bubble {
        padding: 15px;
    }
    
    .message-sender {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .sender-avatar {
        width: 35px;
        height: 35px;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        text-align: center;
    }
    
    .btn-primary {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php
function getClientName($clientId) {
    $client = db()->fetch("SELECT CONCAT(first_name, ' ', last_name) as name FROM client_users WHERE id = ?", [$clientId]);
    return $client ? $client['name'] : 'Client';
}

require_once '../includes/client-footer.php'; 
?>