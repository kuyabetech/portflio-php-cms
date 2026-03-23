<?php
/**
 * Admin Messages Management - Communicate with Clients
 * FULLY RESPONSIVE WITH WORKING CONTACT MESSAGES AND EMAIL REPLIES
 */

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Message Management';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle reply to client (from client_messages table)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to_client'])) {
    $messageId = (int)$_POST['message_id'];
    $replyMessage = trim($_POST['reply_message']);
    
    // Get original message
    $original = db()->fetch("
        SELECT cm.*, cu.first_name, cu.last_name, cu.email 
        FROM client_messages cm
        JOIN client_users cu ON cm.client_id = cu.id
        WHERE cm.id = ?
    ", [$messageId]);
    
    if ($original && !empty($replyMessage)) {
        try {
            // Insert admin reply
            db()->insert('client_messages', [
                'client_id' => $original['client_id'],
                'sender' => 'admin',
                'subject' => 'Re: ' . $original['subject'],
                'message' => $replyMessage,
                'status' => 'unread',
                'created_at' => date('Y-m-d H:i:s'),
                'reply_to_id' => $messageId
            ]);
            
            // Mark original as read if it was unread
            db()->query("UPDATE client_messages SET status = ? WHERE id = ?", ['read', $messageId]);
            
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Reply sent successfully'];
        } catch (Exception $e) {
            error_log("Admin reply error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to send reply'];
        }
    }
    header('Location: messages.php?view=' . $messageId . '&type=client');
    exit;
}

/**
 * Handle reply to contact with email sending using Mailer class
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to_contact'])) {
    $messageId = (int)$_POST['message_id'];
    $replyMessage = trim($_POST['reply_message']);
    $sendCopy = isset($_POST['send_copy']) ? true : false;
    
    // Get original message
    $original = db()->fetch("SELECT * FROM contact_messages WHERE id = ?", [$messageId]);
    
    if ($original && !empty($replyMessage)) {
        try {
            // Mark as read
            db()->query("UPDATE contact_messages SET is_read = ? WHERE id = ?", [1, $messageId]);
            
            // Log the reply
            db()->insert('message_replies', [
                'message_id' => $messageId,
                'reply_message' => $replyMessage,
                'sent_at' => date('Y-m-d H:i:s'),
                'sent_by' => $_SESSION['user_id']
            ]);
            
            // Prepare email content
            $subject = $original['subject'] ? 'Re: ' . $original['subject'] : 'Reply to your contact message';
            
            // Get company name from settings
            $companyName = getSetting('site_name', SITE_NAME);
            $contactEmail = getSetting('contact_email', '');
            
            // Build HTML email body
            $htmlBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                        line-height: 1.6;
                        color: #1e293b;
                        margin: 0;
                        padding: 0;
                        background-color: #f8fafc;
                    }
                    .container {
                        max-width: 600px;
                        margin: 20px auto;
                        background: #ffffff;
                        border-radius: 12px;
                        overflow: hidden;
                        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                    }
                    .header {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        padding: 30px;
                        text-align: center;
                    }
                    .header h1 {
                        margin: 0;
                        font-size: 24px;
                        font-weight: 600;
                    }
                    .content {
                        padding: 30px;
                    }
                    .greeting {
                        font-size: 16px;
                        margin-bottom: 20px;
                    }
                    .reply-box {
                        background: #f1f5f9;
                        padding: 20px;
                        border-radius: 8px;
                        margin: 20px 0;
                        border-left: 4px solid #667eea;
                    }
                    .reply-box p {
                        margin: 0;
                        font-size: 16px;
                        color: #1e293b;
                        line-height: 1.6;
                    }
                    .original-message {
                        background: #f8fafc;
                        padding: 20px;
                        border-radius: 8px;
                        margin: 20px 0;
                        border: 1px solid #e2e8f0;
                    }
                    .original-message h3 {
                        margin: 0 0 10px 0;
                        font-size: 16px;
                        color: #475569;
                        font-weight: 600;
                    }
                    .original-message p {
                        margin: 0;
                        color: #64748b;
                    }
                    .original-message .sent-date {
                        margin-top: 10px;
                        font-size: 12px;
                        color: #94a3b8;
                    }
                    .footer {
                        padding: 20px 30px;
                        border-top: 1px solid #e2e8f0;
                        text-align: center;
                        color: #94a3b8;
                        font-size: 14px;
                    }
                    .signature {
                        margin-top: 30px;
                        padding-top: 20px;
                        border-top: 1px solid #e2e8f0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>" . htmlspecialchars($companyName) . "</h1>
                    </div>
                    
                    <div class='content'>
                        <p class='greeting'>Dear " . htmlspecialchars($original['name']) . ",</p>
                        
                        <p>Thank you for contacting us. Here's our response to your inquiry:</p>
                        
                        <div class='reply-box'>
                            " . nl2br(htmlspecialchars($replyMessage)) . "
                        </div>
                        
                        <p>If you have any further questions, please don't hesitate to contact us again.</p>
                        
                        <div class='signature'>
                            <p>Best regards,<br>
                            <strong>" . htmlspecialchars($companyName) . " Team</strong></p>
                        </div>
                        
                        <div class='original-message'>
                            <h3>Your original message:</h3>
                            <p>" . nl2br(htmlspecialchars($original['message'])) . "</p>
                            <p class='sent-date'>Sent on: " . date('F j, Y \a\t g:i A', strtotime($original['created_at'])) . "</p>
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " " . htmlspecialchars($companyName) . ". All rights reserved.</p>
                        <p style='margin-top: 10px; font-size: 12px;'>
                            This email was sent in response to your contact form submission.
                        </p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // Build plain text version
            $textBody = "Dear " . $original['name'] . ",\n\n";
            $textBody .= "Thank you for contacting us. Here's our response to your inquiry:\n\n";
            $textBody .= $replyMessage . "\n\n";
            $textBody .= "If you have any further questions, please don't hesitate to contact us again.\n\n";
            $textBody .= "Best regards,\n";
            $textBody .= $companyName . " Team\n\n";
            $textBody .= "---\n";
            $textBody .= "Your original message:\n";
            $textBody .= $original['message'] . "\n";
            $textBody .= "Sent on: " . date('F j, Y \a\t g:i A', strtotime($original['created_at']));
            
            // Set reply-to options
            $replyTo = [
                'email' => !empty($contactEmail) ? $contactEmail : getSetting('smtp_from_email', ''),
                'name' => $companyName . ' Support'
            ];
            
            // Send email using Mailer class
            $mailSent = mailer()->sendHTML(
                $original['email'],                    // to
                $subject,                               // subject
                $htmlBody,                              // HTML body
                $textBody,                              // plain text body
                [
                    'reply_to' => $replyTo,            // reply-to address
                    'is_html' => true                   // HTML format
                ]
            );
            
            if ($mailSent) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Reply sent successfully to ' . $original['email']];
                
                // Send a copy to admin if requested
                if ($sendCopy) {
                    $adminEmail = getSetting('admin_email', '');
                    if (!empty($adminEmail)) {
                        mailer()->sendHTML(
                            $adminEmail,
                            'Copy: ' . $subject,
                            $htmlBody,
                            $textBody,
                            ['reply_to' => $replyTo]
                        );
                    }
                }
            } else {
                // Email failed but we still logged the reply
                $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Reply logged but email delivery failed. Please check email settings.'];
                error_log("Failed to send email reply to contact: " . $original['email']);
            }
            
        } catch (Exception $e) {
            error_log("Contact reply error: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to send reply: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid message or empty reply'];
    }
    
    header('Location: messages.php?view=' . $messageId . '&type=contact');
    exit;
}

// Handle mark as read/unread for client messages
if (isset($_GET['mark_client_read']) && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    db()->query("UPDATE client_messages SET status = ? WHERE id = ?", ['read', $messageId]);
    header('Location: messages.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] . '&type=client' : ''));
    exit;
}

if (isset($_GET['mark_client_unread']) && isset($_GET['id'])) {
    $messageId = (int)$_GET['id'];
    db()->query("UPDATE client_messages SET status = ? WHERE id = ?", ['unread', $messageId]);
    header('Location: messages.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] . '&type=client' : ''));
    exit;
}

// Handle contact message actions
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    db()->query("UPDATE contact_messages SET is_read = ? WHERE id = ?", [1, $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message marked as read'];
    header('Location: messages.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] . '&type=contact' : ''));
    exit;
}

if (isset($_GET['unread'])) {
    $id = (int)$_GET['unread'];
    db()->query("UPDATE contact_messages SET is_read = ? WHERE id = ?", [0, $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message marked as unread'];
    header('Location: messages.php' . (isset($_GET['view']) ? '?view=' . $_GET['view'] . '&type=contact' : ''));
    exit;
}

if (isset($_GET['delete_contact'])) {
    $id = (int)$_GET['delete_contact'];
    
    // Delete replies first
    db()->query("DELETE FROM message_replies WHERE message_id = ?", [$id]);
    // Delete message
    db()->query("DELETE FROM contact_messages WHERE id = ?", [$id]);
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message deleted'];
    header('Location: messages.php');
    exit;
}

if (isset($_GET['delete_client'])) {
    $id = (int)$_GET['delete_client'];
    
    // Get the view message ID before deleting
    $viewMessageId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
    $viewType = isset($_GET['type']) ? $_GET['type'] : 'client';
    
    db()->query("DELETE FROM client_messages WHERE id = ?", [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message deleted'];
    
    // Redirect appropriately
    if ($viewMessageId == $id) {
        header('Location: messages.php');
    } else {
        header('Location: messages.php' . ($viewMessageId ? '?view=' . $viewMessageId . '&type=' . $viewType : ''));
    }
    exit;
}

// Get statistics
$stats = [
    'client_unread' => db()->fetch("SELECT COUNT(*) as count FROM client_messages WHERE status = 'unread' AND sender = 'client'")['count'],
    'contact_unread' => db()->fetch("SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0")['count'],
    'total_clients' => db()->fetch("SELECT COUNT(DISTINCT client_id) as count FROM client_messages")['count'],
    'total_messages' => db()->fetch("SELECT COUNT(*) as count FROM client_messages")['count'] + 
                        db()->fetch("SELECT COUNT(*) as count FROM contact_messages")['count']
];

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$viewMessageId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewType = isset($_GET['type']) ? $_GET['type'] : 'client';

// Get client messages with client info
$clientMessages = [];
if ($filter === 'all' || $filter === 'client' || $filter === 'unread_client') {
    $where = ["1=1"];
    $params = [];
    
    if ($filter === 'unread_client') {
        $where[] = "cm.status = 'unread' AND cm.sender = 'client'";
    } elseif ($filter === 'client') {
        $where[] = "cm.sender = 'client'";
    }
    
    if (!empty($search)) {
        $where[] = "(cu.first_name LIKE ? OR cu.last_name LIKE ? OR cu.email LIKE ? OR cm.subject LIKE ? OR cm.message LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $where);
    
    $query = "
        SELECT cm.*, 
               cu.first_name, cu.last_name, cu.email, cu.company,
               (SELECT COUNT(*) FROM client_messages WHERE reply_to_id = cm.id) as reply_count
        FROM client_messages cm
        JOIN client_users cu ON cm.client_id = cu.id
        WHERE $whereClause
        ORDER BY 
            CASE WHEN cm.status = 'unread' AND cm.sender = 'client' THEN 0 ELSE 1 END,
            cm.created_at DESC
        LIMIT 50
    ";
    
    $clientMessages = db()->fetchAll($query, $params);
}

// Get contact messages
$contactMessages = [];
if ($filter === 'all' || $filter === 'contact' || $filter === 'unread_contact') {
    $where = ["1=1"];
    $params = [];
    
    if ($filter === 'unread_contact') {
        $where[] = "is_read = 0";
    }
    
    if (!empty($search)) {
        $where[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $where);
    
    $query = "
        SELECT * FROM contact_messages 
        WHERE $whereClause 
        ORDER BY 
            CASE WHEN is_read = 0 THEN 0 ELSE 1 END,
            created_at DESC
        LIMIT 50
    ";
    
    $contactMessages = db()->fetchAll($query, $params);
}

// Get conversation if viewing a specific thread (for client messages)
$conversation = [];
$mainMessage = null;

if ($viewMessageId > 0 && $viewType === 'client') {
    // First, get the main message
    $mainMessage = db()->fetch("
        SELECT cm.*, cu.first_name, cu.last_name, cu.email, cu.company, cu.id as client_user_id
        FROM client_messages cm
        JOIN client_users cu ON cm.client_id = cu.id
        WHERE cm.id = ?
    ", [$viewMessageId]);
    
    if ($mainMessage) {
        // Mark this specific message as read if it's from client and unread
        if ($mainMessage['sender'] === 'client' && $mainMessage['status'] === 'unread') {
            db()->query("UPDATE client_messages SET status = ? WHERE id = ?", ['read', $viewMessageId]);
        }
        
        // Get the subject without "Re:" prefix for matching
        $subject = $mainMessage['subject'];
        $baseSubject = preg_replace('/^Re:\s*/i', '', $subject);
        
        // Get all messages in this conversation
        $conversation = db()->fetchAll("
            SELECT cm.*, 
                   cu.first_name, cu.last_name,
                   CASE 
                       WHEN cm.sender = 'admin' THEN 'Support Team'
                       ELSE CONCAT(cu.first_name, ' ', cu.last_name)
                   END as sender_name
            FROM client_messages cm
            JOIN client_users cu ON cm.client_id = cu.id
            WHERE cm.client_id = ? 
                AND (
                    cm.subject LIKE ? 
                    OR cm.subject LIKE ? 
                    OR cm.id = ? 
                    OR cm.reply_to_id = ?
                    OR ? IN (SELECT id FROM client_messages WHERE reply_to_id = cm.id)
                )
            ORDER BY cm.created_at ASC
        ", [
            $mainMessage['client_id'], 
            "%$baseSubject%", 
            "%Re: $baseSubject%", 
            $viewMessageId,
            $viewMessageId,
            $viewMessageId
        ]);
        
        // If no conversation found with subject matching, get all messages from this client
        if (empty($conversation)) {
            $conversation = db()->fetchAll("
                SELECT cm.*, 
                       cu.first_name, cu.last_name,
                       CASE 
                           WHEN cm.sender = 'admin' THEN 'Support Team'
                           ELSE CONCAT(cu.first_name, ' ', cu.last_name)
                       END as sender_name
                FROM client_messages cm
                JOIN client_users cu ON cm.client_id = cu.id
                WHERE cm.client_id = ?
                ORDER BY cm.created_at ASC
            ", [$mainMessage['client_id']]);
        }
        
        // Mark all client messages in this conversation as read
        $messageIds = array_column($conversation, 'id');
        if (!empty($messageIds)) {
            $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
            $params = array_merge($messageIds, ['client', 'unread']);
            db()->query("
                UPDATE client_messages 
                SET status = 'read' 
                WHERE id IN ($placeholders) AND sender = ? AND status = ?
            ", $params);
        }
    }
}

// Get contact message details if viewing a contact message
$contactDetail = null;
$contactReplies = [];
if ($viewMessageId > 0 && $viewType === 'contact') {
    $contactDetail = db()->fetch("SELECT * FROM contact_messages WHERE id = ?", [$viewMessageId]);
    
    // Mark as read
    if ($contactDetail && !$contactDetail['is_read']) {
        db()->query("UPDATE contact_messages SET is_read = ? WHERE id = ?", [1, $viewMessageId]);
        $contactDetail['is_read'] = 1;
    }
    
    // Get replies for this contact message
    $contactReplies = db()->fetchAll("
        SELECT * FROM message_replies 
        WHERE message_id = ? 
        ORDER BY sent_at ASC
    ", [$viewMessageId]);
}


// Include header
require_once 'includes/header.php';
?>

<!-- Mobile Menu Toggle -->
<div class="mobile-menu-toggle" id="mobileMenuToggle">
    <i class="fas fa-bars"></i>
    <span>Messages</span>
    <?php if ($stats['client_unread'] + $stats['contact_unread'] > 0): ?>
    <span class="mobile-unread-badge"><?php echo $stats['client_unread'] + $stats['contact_unread']; ?></span>
    <?php endif; ?>
</div>

<div class="messages-page">
    <!-- Page Header -->
    <div class="content-header">
        <h2>
            <i class="fas fa-envelope"></i> 
            <span class="header-title">Message Management</span>
            <?php if ($stats['client_unread'] + $stats['contact_unread'] > 0): ?>
            <span class="header-badge">
                <?php echo $stats['client_unread'] + $stats['contact_unread']; ?> unread
            </span>
            <?php endif; ?>
        </h2>
        
        <!-- Mobile Filter Button -->
        <button class="mobile-filter-btn" id="mobileFilterBtn">
            <i class="fas fa-sliders-h"></i> Filter
        </button>
        
        <div class="header-actions" id="headerActions">
            <div class="filter-tabs-wrapper">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="?filter=unread_client" class="filter-tab <?php echo $filter === 'unread_client' ? 'active' : ''; ?>">
                        Client Unread 
                        <?php if ($stats['client_unread'] > 0): ?>
                        <span class="badge"><?php echo $stats['client_unread']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=unread_contact" class="filter-tab <?php echo $filter === 'unread_contact' ? 'active' : ''; ?>">
                        Contact Unread 
                        <?php if ($stats['contact_unread'] > 0): ?>
                        <span class="badge"><?php echo $stats['contact_unread']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=client" class="filter-tab <?php echo $filter === 'client' ? 'active' : ''; ?>">
                        Client
                    </a>
                    <a href="?filter=contact" class="filter-tab <?php echo $filter === 'contact' ? 'active' : ''; ?>">
                        Contact
                    </a>
                </div>
            </div>
            
            <div class="search-box">
                <form method="GET" id="searchForm">
                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" placeholder="Search messages..." value="<?php echo htmlspecialchars($search); ?>">
                        <?php if (!empty($search)): ?>
                        <a href="?filter=<?php echo $filter; ?>" class="clear-search-btn" title="Clear search">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="search-submit">Search</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?>" id="flashMessage">
            <div class="alert-content">
                <i class="fas <?php echo $_SESSION['flash']['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <span><?php echo $_SESSION['flash']['message']; ?></span>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-details">
                <h3>Total Messages</h3>
                <span class="stat-value"><?php echo number_format($stats['total_messages']); ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-user-clock"></i>
            </div>
            <div class="stat-details">
                <h3>Client Unread</h3>
                <span class="stat-value"><?php echo $stats['client_unread']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-globe"></i>
            </div>
            <div class="stat-details">
                <h3>Contact Unread</h3>
                <span class="stat-value"><?php echo $stats['contact_unread']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <h3>Active Clients</h3>
                <span class="stat-value"><?php echo $stats['total_clients']; ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="messages-container <?php echo $viewMessageId ? 'conversation-view' : ''; ?>">
        <!-- Messages List Column -->
        <div class="messages-list-column" id="messagesListColumn">
            <div class="messages-list-header">
                <h3>
                    <?php 
                    if ($filter === 'client') echo 'Client Messages';
                    elseif ($filter === 'contact') echo 'Contact Form Messages';
                    elseif ($filter === 'unread_client') echo 'Unread Client Messages';
                    elseif ($filter === 'unread_contact') echo 'Unread Contact Messages';
                    else echo 'All Messages';
                    ?>
                </h3>
                <div class="header-filters">
                    <?php if ($filter !== 'contact' && $filter !== 'unread_contact'): ?>
                    <select class="filter-select" onchange="window.location.href='?filter='+this.value<?php echo !empty($search) ? '+&search='.urlencode($search) : ''; ?><?php echo $viewMessageId ? '+&view='.$viewMessageId.'&type=client' : ''; ?>">
                        <option value="client" <?php echo $filter === 'client' ? 'selected' : ''; ?>>Client Messages</option>
                        <option value="unread_client" <?php echo $filter === 'unread_client' ? 'selected' : ''; ?>>Unread Client</option>
                    </select>
                    <?php endif; ?>
                    <?php if ($filter !== 'client' && $filter !== 'unread_client'): ?>
                    <select class="filter-select" onchange="window.location.href='?filter='+this.value<?php echo !empty($search) ? '+&search='.urlencode($search) : ''; ?><?php echo $viewMessageId ? '+&view='.$viewMessageId.'&type=contact' : ''; ?>">
                        <option value="contact" <?php echo $filter === 'contact' ? 'selected' : ''; ?>>Contact Messages</option>
                        <option value="unread_contact" <?php echo $filter === 'unread_contact' ? 'selected' : ''; ?>>Unread Contact</option>
                    </select>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="messages-list" id="messagesList">
                <!-- Client Messages -->
                <?php if ($filter !== 'contact' && $filter !== 'unread_contact'): ?>
                    <?php if (!empty($clientMessages)): ?>
                        <?php foreach ($clientMessages as $message): 
                            $isUnread = $message['status'] === 'unread' && $message['sender'] === 'client';
                            $isActive = $viewMessageId == $message['id'] && $viewType === 'client';
                            $clientName = $message['first_name'] . ' ' . $message['last_name'];
                            $timeAgo = timeAgo($message['created_at']);
                        ?>
                        <a href="?view=<?php echo $message['id']; ?>&type=client&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
                           class="message-list-item client-message <?php echo $isUnread ? 'unread' : ''; ?> <?php echo $isActive ? 'active' : ''; ?>"
                           data-message-id="<?php echo $message['id']; ?>">
                            <div class="message-icon">
                                <i class="fas fa-user"></i>
                                <?php if ($isUnread): ?>
                                <span class="mobile-unread-dot"></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="message-content">
                                <div class="message-header">
                                    <div class="message-sender">
                                        <strong><?php echo htmlspecialchars($clientName); ?></strong>
                                        <span class="type-badge client">Client</span>
                                        <?php if ($message['reply_count'] > 0): ?>
                                        <span class="reply-badge" title="Has replies">
                                            <i class="fas fa-reply"></i> <?php echo $message['reply_count']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="message-date" title="<?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>">
                                        <?php echo $timeAgo; ?>
                                    </div>
                                </div>
                                
                                <div class="message-subject">
                                    <?php echo htmlspecialchars($message['subject']); ?>
                                </div>
                                
                                <div class="message-preview">
                                    <?php echo htmlspecialchars(substr($message['message'], 0, 60)); ?>
                                    <?php if (strlen($message['message']) > 60): ?>...<?php endif; ?>
                                </div>
                                
                                <div class="message-meta-footer">
                                    <?php if ($isUnread): ?>
                                    <span class="unread-badge">New</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($message['company'])): ?>
                                    <span class="company-tag"><?php echo htmlspecialchars($message['company']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="message-actions">
                                <?php if ($isUnread): ?>
                                <a href="?mark_client_read=<?php echo $message['id']; ?>&view=<?php echo $viewMessageId; ?>&type=client" 
                                   class="action-icon" title="Mark as Read" onclick="event.stopPropagation()">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php else: ?>
                                <a href="?mark_client_unread=<?php echo $message['id']; ?>&view=<?php echo $viewMessageId; ?>&type=client" 
                                   class="action-icon" title="Mark as Unread" onclick="event.stopPropagation()">
                                    <i class="fas fa-envelope"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="?delete_client=<?php echo $message['id']; ?>&view=<?php echo $viewMessageId; ?>&type=client" 
                                   class="action-icon delete" title="Delete" 
                                   onclick="event.stopPropagation(); return confirm('Delete this message?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Contact Messages -->
                <?php if ($filter !== 'client' && $filter !== 'unread_client'): ?>
                    <?php if (!empty($contactMessages)): ?>
                        <?php foreach ($contactMessages as $message): 
                            $isUnread = !$message['is_read'];
                            $isActive = $viewMessageId == $message['id'] && $viewType === 'contact';
                            $timeAgo = timeAgo($message['created_at']);
                        ?>
                        <a href="?view=<?php echo $message['id']; ?>&type=contact&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" 
                           class="message-list-item contact-message <?php echo $isUnread ? 'unread' : ''; ?> <?php echo $isActive ? 'active' : ''; ?>">
                            <div class="message-icon">
                                <i class="fas fa-globe"></i>
                                <?php if ($isUnread): ?>
                                <span class="mobile-unread-dot"></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="message-content">
                                <div class="message-header">
                                    <div class="message-sender">
                                        <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                                        <span class="type-badge contact">Contact</span>
                                    </div>
                                    <div class="message-date" title="<?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>">
                                        <?php echo $timeAgo; ?>
                                    </div>
                                </div>
                                
                                <div class="message-subject">
                                    <?php echo $message['subject'] ? htmlspecialchars($message['subject']) : '<em>No Subject</em>'; ?>
                                </div>
                                
                                <div class="message-preview">
                                    <span class="email-small"><?php echo htmlspecialchars($message['email']); ?></span> - 
                                    <?php echo htmlspecialchars(substr($message['message'], 0, 40)); ?>...
                                </div>
                                
                                <?php if ($isUnread): ?>
                                <span class="unread-badge">New</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="message-actions">
                                <?php if ($isUnread): ?>
                                <a href="?read=<?php echo $message['id']; ?>&view=<?php echo $viewMessageId; ?>&type=contact" class="action-icon" title="Mark as Read" onclick="event.stopPropagation()">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php else: ?>
                                <a href="?unread=<?php echo $message['id']; ?>&view=<?php echo $viewMessageId; ?>&type=contact" class="action-icon" title="Mark as Unread" onclick="event.stopPropagation()">
                                    <i class="fas fa-envelope"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="?delete_contact=<?php echo $message['id']; ?>&view=<?php echo $viewMessageId; ?>&type=contact" 
                                   class="action-icon delete" title="Delete" 
                                   onclick="event.stopPropagation(); return confirm('Delete this contact message?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (empty($clientMessages) && empty($contactMessages)): ?>
                <div class="empty-list">
                    <i class="fas fa-envelope-open"></i>
                    <p>No messages found</p>
                    <?php if (!empty($search)): ?>
                    <a href="?filter=<?php echo $filter; ?>" class="clear-search">Clear search</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Conversation Column -->
        <div class="conversation-column" id="conversationColumn">
            <?php if ($viewType === 'client' && !empty($conversation) && $mainMessage): ?>
                <!-- Client Message Conversation -->
                <!-- Mobile Back Button -->
                <div class="mobile-back-btn" onclick="goBackToList()">
                    <i class="fas fa-arrow-left"></i> Back to messages
                </div>
                
                <!-- Conversation Header -->
                <div class="conversation-header">
                    <div class="conversation-header-content">
                        <h2><?php echo htmlspecialchars($mainMessage['subject']); ?></h2>
                        <div class="client-info">
                            <div class="client-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="client-details">
                                <strong><?php echo htmlspecialchars($mainMessage['first_name'] . ' ' . $mainMessage['last_name']); ?></strong>
                                <?php if (!empty($mainMessage['company'])): ?>
                                <span class="company">(<?php echo htmlspecialchars($mainMessage['company']); ?>)</span>
                                <?php endif; ?>
                                <a href="mailto:<?php echo $mainMessage['email']; ?>" class="email-link">
                                    <i class="fas fa-envelope"></i> 
                                    <span class="email-text"><?php echo htmlspecialchars($mainMessage['email']); ?></span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <button class="btn-reply" onclick="showReplyForm()">
                        <i class="fas fa-reply"></i>
                        <span class="reply-text">Reply</span>
                    </button>
                </div>
                
                <!-- Conversation Messages -->
                <div class="conversation-messages" id="conversationMessages">
                    <?php foreach ($conversation as $msg): 
                        $isAdmin = $msg['sender'] === 'admin';
                        $messageTime = date('M j, Y g:i A', strtotime($msg['created_at']));
                    ?>
                    <div class="conversation-message <?php echo $isAdmin ? 'admin-message' : 'client-message'; ?>">
                        <div class="message-bubble">
                            <div class="message-meta">
                                <span class="message-author">
                                    <?php echo $isAdmin ? 'You (Support)' : htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?>
                                </span>
                                <span class="message-time" title="<?php echo $messageTime; ?>">
                                    <?php echo timeAgo($msg['created_at']); ?>
                                </span>
                                <?php if (!$isAdmin && $msg['status'] === 'unread'): ?>
                                <span class="status-badge">New</span>
                                <?php endif; ?>
                            </div>
                            <div class="message-text">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Reply Form for Client -->
                <div class="reply-form" id="replyForm" style="display: none;">
                    <form method="POST">
                        <input type="hidden" name="message_id" value="<?php echo $viewMessageId; ?>">
                        <div class="form-group">
                            <textarea name="reply_message" rows="3" placeholder="Type your reply to <?php echo htmlspecialchars($mainMessage['first_name']); ?>..." required></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="hideReplyForm()">Cancel</button>
                            <button type="submit" name="reply_to_client" class="btn-send">
                                <i class="fas fa-paper-plane"></i> Send Reply
                            </button>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($viewType === 'contact' && $contactDetail): ?>
                <!-- Contact Message Detail -->
                <!-- Mobile Back Button -->
                <div class="mobile-back-btn" onclick="goBackToList()">
                    <i class="fas fa-arrow-left"></i> Back to messages
                </div>
                
                <!-- Contact Message Header -->
                <div class="conversation-header">
                    <div class="conversation-header-content">
                        <h2><?php echo $contactDetail['subject'] ? htmlspecialchars($contactDetail['subject']) : 'Contact Form Message'; ?></h2>
                        <div class="client-info">
                            <div class="client-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="client-details">
                                <strong><?php echo htmlspecialchars($contactDetail['name']); ?></strong>
                                <?php if (!empty($contactDetail['company'])): ?>
                                <span class="company">(<?php echo htmlspecialchars($contactDetail['company']); ?>)</span>
                                <?php endif; ?>
                                <a href="mailto:<?php echo $contactDetail['email']; ?>" class="email-link">
                                    <i class="fas fa-envelope"></i> 
                                    <span class="email-text"><?php echo htmlspecialchars($contactDetail['email']); ?></span>
                                </a>
                                <?php if (!empty($contactDetail['phone'])): ?>
                                <a href="tel:<?php echo $contactDetail['phone']; ?>" class="email-link">
                                    <i class="fas fa-phone"></i> 
                                    <span><?php echo htmlspecialchars($contactDetail['phone']); ?></span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="message-date-full">
                            Received: <?php echo date('F j, Y \a\t g:i A', strtotime($contactDetail['created_at'])); ?>
                        </div>
                    </div>
                    <button class="btn-reply" onclick="showContactReplyForm()">
                        <i class="fas fa-reply"></i>
                        <span class="reply-text">Reply via Email</span>
                    </button>
                </div>
                
                <!-- Contact Message Content -->
                <div class="conversation-messages" id="conversationMessages">
                    <div class="contact-message-full">
                        <div class="message-bubble contact-bubble">
                            <div class="message-text">
                                <?php echo nl2br(htmlspecialchars($contactDetail['message'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Previous Replies -->
                    <?php if (!empty($contactReplies)): ?>
                        <div class="reply-history">
                            <h4>Previous Replies</h4>
                            <?php foreach ($contactReplies as $reply): ?>
                            <div class="message-bubble admin-message">
                                <div class="message-meta">
                                    <span class="message-author">You (Support)</span>
                                    <span class="message-time"><?php echo date('M j, Y g:i A', strtotime($reply['sent_at'])); ?></span>
                                </div>
                                <div class="message-text">
                                    <?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Reply Form for Contact -->
                <div class="reply-form" id="contactReplyForm" style="display: none;">
                    <form method="POST">
                        <input type="hidden" name="message_id" value="<?php echo $viewMessageId; ?>">
                        <div class="form-group">
                            <label class="reply-label">
                                <i class="fas fa-envelope"></i> 
                                Replying to: <strong><?php echo htmlspecialchars($contactDetail['email']); ?></strong>
                            </label>
                            <textarea name="reply_message" rows="4" placeholder="Type your email reply to <?php echo htmlspecialchars($contactDetail['name']); ?>..." required></textarea>
                        </div>
                        
                        <!-- Send copy to myself option -->
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="send_copy" value="1" checked>
                                <span>Send a copy to myself</span>
                            </label>
                            <small class="help-text">A copy will be sent to your email address</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="hideContactReplyForm()">Cancel</button>
                            <button type="submit" name="reply_to_contact" class="btn-send">
                                <i class="fas fa-paper-plane"></i> Send Email Reply
                            </button>
                        </div>
                    </form>
                </div>
                
            <?php else: ?>
                <div class="no-conversation">
                    <div class="no-conversation-content">
                        <i class="fas fa-comments"></i>
                        <h3>Select a message</h3>
                        <p>Choose a message from the list to view the details and reply</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ========================================
   MOBILE-FIRST RESPONSIVE DESIGN SYSTEM
   ======================================== */

/* CSS Variables */
:root {
    --primary: #667eea;
    --primary-dark: #5a67d8;
    --primary-light: #e6e9ff;
    --success: #10b981;
    --success-light: #d1fae5;
    --danger: #ef4444;
    --danger-light: #fee2e2;
    --warning: #f59e0b;
    --warning-light: #fef3c7;
    --dark: #1e293b;
    --gray-800: #334155;
    --gray-700: #475569;
    --gray-600: #64748b;
    --gray-500: #94a3b8;
    --gray-400: #cbd5e1;
    --gray-300: #d1d5db;
    --gray-200: #e2e8f0;
    --gray-100: #f1f5f9;
    --gray-50: #f8fafc;
    --white: #ffffff;
    
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
    --radius-full: 9999px;
}

/* ========================================
   BASE STYLES (Mobile First - 320px)
   ======================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background: var(--gray-50);
    color: var(--dark);
    line-height: 1.5;
}

.messages-page {
    padding: 12px;
    max-width: 100%;
    margin: 0 auto;
}

/* ========================================
   TYPOGRAPHY
   ======================================== */

h1, h2, h3, h4, h5, h6 {
    font-weight: 600;
    line-height: 1.2;
}

/* ========================================
   MOBILE MENU TOGGLE
   ======================================== */

.mobile-menu-toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--white);
    padding: 16px;
    border-radius: var(--radius-lg);
    margin-bottom: 16px;
    box-shadow: var(--shadow-md);
    cursor: pointer;
    font-weight: 500;
    color: var(--dark);
    position: relative;
    transition: all 0.2s ease;
}

.mobile-menu-toggle:active {
    background: var(--gray-100);
    transform: scale(0.98);
}

.mobile-menu-toggle i {
    font-size: 20px;
    color: var(--primary);
}

.mobile-unread-badge {
    background: var(--danger);
    color: var(--white);
    padding: 4px 10px;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 600;
    margin-left: auto;
}

/* ========================================
   CONTENT HEADER
   ======================================== */

.content-header {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 16px;
    margin-bottom: 16px;
    box-shadow: var(--shadow-md);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.content-header h2 {
    font-size: 20px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    flex-wrap: wrap;
}

.content-header h2 i {
    color: var(--primary);
    font-size: 24px;
}

.header-title {
    font-size: clamp(18px, 5vw, 24px);
}

.header-badge {
    display: inline-block;
    padding: 4px 12px;
    background: var(--danger);
    color: var(--white);
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.mobile-filter-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: var(--primary);
    color: var(--white);
    border: none;
    padding: 12px 16px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    width: 100%;
    transition: all 0.2s ease;
}

.mobile-filter-btn:active {
    background: var(--primary-dark);
    transform: scale(0.98);
}

.header-actions {
    display: none;
    width: 100%;
}

.header-actions.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ========================================
   FILTER TABS
   ======================================== */

.filter-tabs-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    margin-bottom: 16px;
    padding-bottom: 4px;
}

.filter-tabs-wrapper::-webkit-scrollbar {
    display: none;
}

.filter-tabs {
    display: flex;
    gap: 6px;
    background: var(--gray-100);
    padding: 6px;
    border-radius: var(--radius-lg);
    min-width: min-content;
}

.filter-tab {
    padding: 10px 16px;
    color: var(--gray-600);
    text-decoration: none;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    background: transparent;
}

.filter-tab:active {
    background: rgba(255,255,255,0.7);
}

.filter-tab.active {
    background: var(--white);
    color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.filter-tab .badge {
    background: var(--primary);
    color: var(--white);
    padding: 3px 8px;
    border-radius: var(--radius-full);
    font-size: 10px;
    font-weight: 600;
}

/* ========================================
   SEARCH BOX
   ======================================== */

.search-box {
    width: 100%;
}

.search-box form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.search-input-wrapper {
    position: relative;
    width: 100%;
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
    font-size: 14px;
    pointer-events: none;
}

.search-box input {
    width: 100%;
    padding: 14px 14px 14px 42px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-md);
    font-size: 15px;
    transition: all 0.2s ease;
    background: var(--white);
    -webkit-appearance: none;
}

.search-box input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
}

.search-box input::placeholder {
    color: var(--gray-400);
}

.clear-search-btn {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
    text-decoration: none;
    padding: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-full);
    background: var(--gray-100);
    width: 28px;
    height: 28px;
}

.clear-search-btn:active {
    background: var(--gray-200);
}

.search-submit {
    background: var(--primary);
    color: var(--white);
    border: none;
    padding: 14px 20px;
    border-radius: var(--radius-md);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
    -webkit-appearance: none;
}

.search-submit:active {
    background: var(--primary-dark);
    transform: scale(0.98);
}

/* ========================================
   STATISTICS CARDS
   ======================================== */

.stats-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: var(--shadow-md);
    transition: all 0.2s ease;
}

.stat-card:active {
    transform: scale(0.99);
    background: var(--gray-50);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.stat-icon.blue { 
    background: rgba(37,99,235,0.1); 
    color: #2563eb; 
}

.stat-icon.orange { 
    background: rgba(245,158,11,0.1); 
    color: #f59e0b; 
}

.stat-icon.purple { 
    background: rgba(124,58,237,0.1); 
    color: #7c3aed; 
}

.stat-icon.green { 
    background: rgba(16,185,129,0.1); 
    color: #10b981; 
}

.stat-details {
    flex: 1;
    min-width: 0;
}

.stat-details h3 {
    font-size: 13px;
    color: var(--gray-600);
    margin-bottom: 4px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--dark);
    display: block;
    line-height: 1.2;
}

/* ========================================
   MESSAGES CONTAINER
   ======================================== */

.messages-container {
    background: var(--white);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    min-height: 500px;
    display: flex;
    flex-direction: column;
}

/* ========================================
   MESSAGES LIST COLUMN
   ======================================== */

.messages-list-column {
    background: var(--white);
    width: 100%;
    display: flex;
    flex-direction: column;
    border-bottom: 1px solid var(--gray-200);
}

.messages-list-header {
    padding: 16px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: var(--gray-50);
}

.messages-list-header h3 {
    font-size: 16px;
    color: var(--dark);
    margin: 0;
    font-weight: 600;
}

.header-filters {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-select {
    padding: 12px 14px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 500;
    background: var(--white);
    cursor: pointer;
    width: 100%;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    background-size: 16px;
    padding-right: 40px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
}

/* ========================================
   MESSAGES LIST
   ======================================== */

.messages-list {
    max-height: 500px;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.message-list-item {
    display: flex;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid var(--gray-200);
    text-decoration: none;
    transition: all 0.2s ease;
    position: relative;
    background: var(--white);
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
}

.message-list-item:active {
    background: var(--gray-100);
}

.message-list-item.active {
    background: var(--primary-light);
    border-left: 4px solid var(--primary);
}

.message-list-item.unread {
    background: var(--white);
    font-weight: 500;
}

.message-list-item.client-message.unread {
    border-left: 4px solid var(--warning);
}

.message-list-item.contact-message.unread {
    border-left: 4px solid var(--success);
}

.message-icon {
    width: 44px;
    height: 44px;
    background: var(--gray-100);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
    flex-shrink: 0;
    position: relative;
}

.mobile-unread-dot {
    position: absolute;
    top: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: var(--danger);
    border-radius: var(--radius-full);
    border: 2px solid var(--white);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.8; }
}

.message-content {
    flex: 1;
    min-width: 0;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 6px;
    gap: 8px;
}

.message-sender {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--dark);
    flex-wrap: wrap;
}

.message-sender strong {
    font-size: 14px;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.type-badge {
    padding: 3px 8px;
    border-radius: var(--radius-full);
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.type-badge.client {
    background: var(--warning-light);
    color: #92400e;
}

.type-badge.contact {
    background: var(--success-light);
    color: #065f46;
}

.reply-badge {
    background: var(--primary-light);
    color: var(--primary-dark);
    padding: 3px 8px;
    border-radius: var(--radius-full);
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.message-date {
    font-size: 11px;
    color: var(--gray-500);
    white-space: nowrap;
}

.message-subject {
    font-size: 15px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.message-preview {
    font-size: 13px;
    color: var(--gray-600);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 6px;
}

.email-small {
    color: var(--primary);
    font-size: 12px;
    font-weight: 500;
}

.message-meta-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.unread-badge {
    display: inline-block;
    background: var(--primary);
    color: var(--white);
    padding: 3px 10px;
    border-radius: var(--radius-full);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.company-tag {
    display: inline-block;
    background: var(--gray-100);
    color: var(--gray-700);
    padding: 3px 10px;
    border-radius: var(--radius-full);
    font-size: 10px;
    font-weight: 500;
}

.message-actions {
    display: flex;
    flex-direction: row;
    gap: 6px;
    position: absolute;
    right: 12px;
    top: 12px;
    background: rgba(255,255,255,0.95);
    padding: 6px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    backdrop-filter: blur(4px);
    opacity: 0;
    transition: opacity 0.2s ease;
}

.message-list-item:hover .message-actions,
.message-list-item:active .message-actions {
    opacity: 1;
}

.action-icon {
    width: 36px;
    height: 36px;
    background: var(--white);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    text-decoration: none;
    box-shadow: var(--shadow-sm);
    transition: all 0.15s ease;
    border: 1px solid var(--gray-200);
}

.action-icon:active {
    transform: scale(0.95);
    background: var(--primary);
    color: var(--white);
}

.action-icon.delete:active {
    background: var(--danger);
    color: var(--white);
}

/* ========================================
   CONVERSATION COLUMN
   ======================================== */

.conversation-column {
    display: none;
    flex-direction: column;
    height: 600px;
    background: var(--white);
    width: 100%;
}

.messages-container.conversation-view .conversation-column {
    display: flex;
}

.messages-container.conversation-view .messages-list-column {
    display: none;
}

/* Mobile Back Button */
.mobile-back-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px;
    background: var(--gray-100);
    color: var(--primary);
    font-weight: 500;
    font-size: 15px;
    cursor: pointer;
    border-bottom: 1px solid var(--gray-200);
    -webkit-tap-highlight-color: transparent;
}

.mobile-back-btn:active {
    background: var(--gray-200);
}

.mobile-back-btn i {
    font-size: 18px;
}

/* Conversation Header */
.conversation-header {
    padding: 20px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    flex-direction: column;
    gap: 16px;
    background: var(--white);
}

.conversation-header-content {
    width: 100%;
}

.conversation-header h2 {
    font-size: 18px;
    color: var(--dark);
    margin-bottom: 12px;
    word-break: break-word;
    line-height: 1.4;
}

.client-info {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.client-avatar {
    width: 48px;
    height: 48px;
    background: var(--primary-light);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 28px;
    flex-shrink: 0;
}

.client-details {
    flex: 1;
    min-width: 0;
}

.client-details strong {
    display: block;
    font-size: 16px;
    color: var(--dark);
    margin-bottom: 4px;
}

.client-details .company {
    font-size: 13px;
    color: var(--gray-600);
    margin-left: 4px;
    font-weight: normal;
}

.email-link {
    color: var(--primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    word-break: break-all;
    margin-top: 4px;
    padding: 4px 0;
}

.email-link:active {
    text-decoration: underline;
}

.email-text {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.btn-reply {
    background: var(--primary);
    color: var(--white);
    border: none;
    padding: 14px 20px;
    border-radius: var(--radius-md);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    -webkit-appearance: none;
}

.btn-reply:active {
    background: var(--primary-dark);
    transform: scale(0.98);
}

/* Conversation Messages */
.conversation-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    background: var(--gray-50);
    -webkit-overflow-scrolling: touch;
}

.conversation-message {
    display: flex;
    flex-direction: column;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.conversation-message.admin-message {
    align-items: flex-start;
}

.conversation-message.client-message {
    align-items: flex-end;
}

.message-bubble {
    max-width: 90%;
    padding: 14px 16px;
    border-radius: 18px;
    position: relative;
    word-break: break-word;
    box-shadow: var(--shadow-sm);
}

.admin-message .message-bubble {
    background: var(--white);
    border-bottom-left-radius: 4px;
    border: 1px solid var(--gray-200);
}

.client-message .message-bubble {
    background: var(--primary);
    color: var(--white);
    border-bottom-right-radius: 4px;
}

.message-meta {
    margin-bottom: 6px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.admin-message .message-author {
    color: var(--gray-700);
    font-weight: 600;
}

.client-message .message-author {
    color: rgba(255,255,255,0.9);
    font-weight: 600;
}

.message-time {
    color: var(--gray-500);
    font-size: 10px;
}

.client-message .message-time {
    color: rgba(255,255,255,0.7);
}

.status-badge {
    background: var(--danger);
    color: var(--white);
    padding: 3px 8px;
    border-radius: var(--radius-full);
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.message-text {
    font-size: 14px;
    line-height: 1.6;
    word-wrap: break-word;
}

/* Reply Form */
.reply-form {
    padding: 20px;
    border-top: 2px solid var(--gray-200);
    background: var(--white);
}

.reply-form textarea {
    width: 100%;
    padding: 16px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-lg);
    resize: vertical;
    font-family: inherit;
    font-size: 15px;
    line-height: 1.5;
    transition: all 0.2s ease;
    background: var(--white);
    -webkit-appearance: none;
}

.reply-form textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
}

.reply-form .form-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 16px;
}

.btn-cancel {
    background: var(--gray-200);
    color: var(--gray-700);
    border: none;
    padding: 14px 20px;
    border-radius: var(--radius-md);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
    -webkit-appearance: none;
}

.btn-cancel:active {
    background: var(--gray-300);
    transform: scale(0.98);
}

.btn-send {
    background: var(--primary);
    color: var(--white);
    border: none;
    padding: 14px 20px;
    border-radius: var(--radius-md);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    -webkit-appearance: none;
}

.btn-send:active {
    background: var(--primary-dark);
    transform: scale(0.98);
}

.btn-send i {
    font-size: 16px;
}

/* No Conversation State */
.no-conversation {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    background: var(--gray-50);
    padding: 40px 20px;
}

.no-conversation-content {
    text-align: center;
    max-width: 280px;
}

.no-conversation-content i {
    font-size: 64px;
    color: var(--gray-300);
    margin-bottom: 20px;
}

.no-conversation-content h3 {
    color: var(--dark);
    margin-bottom: 10px;
    font-size: 18px;
}

.no-conversation-content p {
    color: var(--gray-500);
    font-size: 14px;
    line-height: 1.6;
}

/* Empty List State */
.empty-list {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-400);
}

.empty-list i {
    font-size: 56px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-list p {
    font-size: 15px;
    margin-bottom: 16px;
}

.clear-search {
    display: inline-block;
    padding: 12px 24px;
    background: var(--primary-light);
    color: var(--primary);
    text-decoration: none;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
}

.clear-search:active {
    background: var(--primary);
    color: var(--white);
}

/* Alerts */
.alert {
    padding: 16px;
    border-radius: var(--radius-lg);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    animation: slideIn 0.3s ease;
    background: var(--white);
    box-shadow: var(--shadow-lg);
    border-left: 4px solid transparent;
}

.alert-success {
    background: var(--success-light);
    color: #065f46;
    border-left-color: var(--success);
}

.alert-error {
    background: var(--danger-light);
    color: #991b1b;
    border-left-color: var(--danger);
}

.alert-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.alert-content i {
    font-size: 20px;
}

.alert-close {
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    color: currentColor;
    opacity: 0.5;
    transition: opacity 0.2s ease;
    padding: 0 8px;
    line-height: 1;
}

.alert-close:active {
    opacity: 1;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* ========================================
   TABLET BREAKPOINT (768px)
   ======================================== */

@media (min-width: 768px) {
    .messages-page {
        padding: 20px;
    }
    
    .content-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
    }
    
    .mobile-filter-btn {
        display: none;
    }
    
    .header-actions {
        display: block;
        width: auto;
    }
    
    .filter-tabs-wrapper {
        margin-bottom: 0;
    }
    
    .search-box {
        width: 350px;
    }
    
    .search-box form {
        flex-direction: row;
    }
    
    .search-submit {
        width: auto;
        padding: 14px 24px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .messages-container {
        flex-direction: row;
    }
    
    .messages-list-column {
        width: 350px;
        border-right: 1px solid var(--gray-200);
        border-bottom: none;
    }
    
    .messages-list-header {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
    }
    
    .header-filters {
        flex-direction: row;
        width: auto;
    }
    
    .filter-select {
        width: 150px;
    }
    
    .conversation-column {
        display: flex;
        width: calc(100% - 350px);
    }
    
    .messages-container.conversation-view .messages-list-column,
    .messages-container.conversation-view .conversation-column {
        display: flex;
    }
    
    .mobile-back-btn {
        display: none;
    }
    
    .conversation-header {
        flex-direction: row;
        align-items: flex-start;
        justify-content: space-between;
    }
    
    .btn-reply {
        width: auto;
        padding: 12px 24px;
    }
    
    .reply-form .form-actions {
        flex-direction: row;
        justify-content: flex-end;
    }
    
    .btn-cancel,
    .btn-send {
        width: auto;
        min-width: 120px;
    }
    
    .message-actions {
        opacity: 0;
        position: static;
        background: none;
        box-shadow: none;
        padding: 0;
        backdrop-filter: none;
    }
}

/* ========================================
   DESKTOP BREAKPOINT (1024px)
   ======================================== */

@media (min-width: 1024px) {
    .messages-page {
        padding: 24px;
        max-width: 1600px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-icon {
        width: 56px;
        height: 56px;
        font-size: 24px;
    }
    
    .stat-value {
        font-size: 26px;
    }
    
    .messages-list-column {
        width: 400px;
    }
    
    .conversation-column {
        width: calc(100% - 400px);
    }
    
    .message-list-item {
        padding: 20px;
    }
    
    .message-icon {
        width: 48px;
        height: 48px;
        font-size: 22px;
    }
    
    .message-sender strong {
        font-size: 15px;
        max-width: 150px;
    }
    
    .message-subject {
        font-size: 16px;
    }
    
    .message-preview {
        font-size: 14px;
    }
    
    .conversation-header {
        padding: 24px;
    }
    
    .conversation-header h2 {
        font-size: 20px;
    }
    
    .client-avatar {
        width: 56px;
        height: 56px;
        font-size: 32px;
    }
    
    .client-details strong {
        font-size: 18px;
    }
    
    .email-text {
        max-width: 300px;
    }
    
    .message-bubble {
        max-width: 70%;
    }
}

/* ========================================
   LARGE DESKTOP BREAKPOINT (1400px)
   ======================================== */

@media (min-width: 1400px) {
    .messages-list-column {
        width: 450px;
    }
    
    .conversation-column {
        width: calc(100% - 450px);
    }
    
    .message-bubble {
        max-width: 60%;
    }
}

/* ========================================
   TOUCH DEVICE OPTIMIZATIONS
   ======================================== */

@media (hover: none) and (pointer: coarse) {
    .message-actions {
        opacity: 1;
        position: absolute;
        right: 10px;
        top: 10px;
    }
    
    .action-icon {
        width: 40px;
        height: 40px;
    }
    
    .filter-tab,
    .btn-reply,
    .btn-cancel,
    .btn-send,
    .search-submit,
    .mobile-filter-btn,
    .message-list-item {
        cursor: default;
    }
    
    .filter-tab:active,
    .btn-reply:active,
    .btn-cancel:active,
    .btn-send:active,
    .search-submit:active {
        transform: scale(0.98);
    }
}

/* ========================================
   DARK MODE SUPPORT (Optional)
   ======================================== */

@media (prefers-color-scheme: dark) {
    :root {
        --dark: #f1f5f9;
        --gray-800: #e2e8f0;
        --gray-700: #cbd5e1;
        --gray-600: #94a3b8;
        --gray-500: #64748b;
        --gray-400: #475569;
        --gray-300: #334155;
        --gray-200: #1e293b;
        --gray-100: #0f172a;
        --gray-50: #020617;
        --white: #1e293b;
    }
    
    .message-bubble.client-message {
        background: var(--primary-dark);
    }
    
    .message-list-item.active {
        background: rgba(102, 126, 234, 0.2);
    }
}

/* ========================================
   PRINT STYLES
   ======================================== */

@media print {
    .header-actions,
    .filter-tabs-wrapper,
    .search-box,
    .stats-grid,
    .message-actions,
    .btn-reply,
    .reply-form,
    .mobile-back-btn,
    .mobile-menu-toggle,
    .mobile-filter-btn {
        display: none !important;
    }
    
    .messages-container {
        display: block;
        box-shadow: none;
    }
    
    .messages-list-column,
    .conversation-column {
        display: block !important;
        width: 100% !important;
        height: auto;
        overflow: visible;
        border: none;
    }
    
    .message-bubble {
        border: 1px solid #ddd;
        background: white !important;
        color: black !important;
        box-shadow: none;
    }
    
    .message-bubble.client-message {
        background: #f5f5f5 !important;
        color: black !important;
    }
}
</style>
<style>
/* ========================================
   ADDITIONAL STYLES FOR CONTACT REPLIES
   ======================================== */

.contact-message-full {
    margin-bottom: 20px;
}

.contact-bubble {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    max-width: 100%;
}

.reply-history {
    margin-top: 30px;
    border-top: 2px dashed #e2e8f0;
    padding-top: 20px;
}

.reply-history h4 {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 15px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.message-date-full {
    font-size: 12px;
    color: #64748b;
    margin-top: 8px;
}

.message-bubble.admin-message {
    background: #f1f5f9;
    margin-bottom: 10px;
}

.message-bubble.admin-message:last-child {
    margin-bottom: 0;
}

.reply-label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    color: #475569;
    font-size: 14px;
}

.reply-label i {
    color: #667eea;
}

.checkbox-group {
    margin: 15px 0;
    padding: 10px;
    background: #f8fafc;
    border-radius: 8px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #475569;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #667eea;
}

.help-text {
    display: block;
    margin-top: 5px;
    margin-left: 26px;
    font-size: 12px;
    color: #94a3b8;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .contact-bubble {
        background: #1e293b;
        border-color: #334155;
    }
    
    .reply-history h4 {
        color: #94a3b8;
    }
    
    .message-bubble.admin-message {
        background: #334155;
    }
    
    .reply-label {
        color: #cbd5e1;
    }
    
    .checkbox-group {
        background: #1e293b;
    }
    
    .checkbox-label {
        color: #cbd5e1;
    }
    
    .help-text {
        color: #64748b;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .reply-label {
        font-size: 13px;
        flex-wrap: wrap;
    }
    
    .checkbox-group {
        padding: 8px;
    }
    
    .checkbox-label {
        font-size: 13px;
    }
}
</style>

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const headerActions = document.getElementById('headerActions');
    const mobileFilterBtn = document.getElementById('mobileFilterBtn');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            headerActions.classList.toggle('show');
        });
    }
    
    if (mobileFilterBtn) {
        mobileFilterBtn.addEventListener('click', function() {
            headerActions.classList.toggle('show');
        });
    }
    
    // Auto scroll to bottom of conversation
    const conversationMessages = document.getElementById('conversationMessages');
    if (conversationMessages) {
        setTimeout(() => {
            conversationMessages.scrollTop = conversationMessages.scrollHeight;
        }, 100);
    }
    
    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
    
    // Check URL parameters on load
    const urlParams = new URLSearchParams(window.location.search);
    const viewParam = urlParams.get('view');
    
    if (viewParam) {
        document.querySelector('.messages-container').classList.add('conversation-view');
    }
});

// Go back to messages list on mobile
function goBackToList() {
    document.querySelector('.messages-container').classList.remove('conversation-view');
    
    // Update URL without view parameter
    const url = new URL(window.location.href);
    url.searchParams.delete('view');
    url.searchParams.delete('type');
    window.history.pushState({}, '', url);
}

// Show reply form for client messages
function showReplyForm() {
    const replyForm = document.getElementById('replyForm');
    if (replyForm) {
        replyForm.style.display = 'block';
        
        // Scroll to reply form
        setTimeout(() => {
            replyForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
        
        // Focus on textarea
        setTimeout(() => {
            replyForm.querySelector('textarea').focus();
        }, 200);
    }
}

// Hide reply form for client messages
function hideReplyForm() {
    const replyForm = document.getElementById('replyForm');
    if (replyForm) {
        replyForm.style.display = 'none';
    }
}

// Show reply form for contact messages
function showContactReplyForm() {
    const replyForm = document.getElementById('contactReplyForm');
    if (replyForm) {
        replyForm.style.display = 'block';
        
        // Scroll to reply form
        setTimeout(() => {
            replyForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
        
        // Focus on textarea
        setTimeout(() => {
            replyForm.querySelector('textarea').focus();
        }, 200);
    }
}

// Hide reply form for contact messages
function hideContactReplyForm() {
    const replyForm = document.getElementById('contactReplyForm');
    if (replyForm) {
        replyForm.style.display = 'none';
    }
}

// Handle window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        // On desktop, both columns should be visible if a message is selected
        const viewParam = new URLSearchParams(window.location.search).get('view');
        if (viewParam) {
            document.querySelector('.messages-container').classList.add('conversation-view');
            document.getElementById('messagesListColumn').style.display = 'block';
            document.getElementById('conversationColumn').style.display = 'flex';
        }
    }
});

// Prevent event bubbling on action links
document.querySelectorAll('.action-icon').forEach(link => {
    link.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>