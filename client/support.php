<?php
/**
 * Client Support Tickets - Get help from support team
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

// Get client information
$client = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientId]);

// Generate ticket number
function generateTicketNumber() {
    return 'TKT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Handle new ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    
    if (empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            $ticketNumber = generateTicketNumber();
            
            db()->insert('support_tickets', [
                'client_id' => $clientId,
                'ticket_number' => $ticketNumber,
                'subject' => $subject,
                'priority' => $priority,
                'project_id' => $projectId,
                'status' => 'open',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $ticketId = db()->lastInsertId();
            
            // Add initial message
            db()->insert('ticket_replies', [
                'ticket_id' => $ticketId,
                'client_id' => $clientId,
                'message' => $message,
                'is_staff' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Send notification to admin
            addAdminNotification('new_ticket', [
                'client_name' => $client['first_name'] . ' ' . $client['last_name'],
                'ticket_number' => $ticketNumber,
                'subject' => $subject
            ]);
            
            $success = 'Ticket created successfully. Ticket number: ' . $ticketNumber;
            
        } catch (Exception $e) {
            error_log("Ticket creation error: " . $e->getMessage());
            $error = 'Failed to create ticket';
        }
    }
}

// Handle reply to ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket'])) {
    $ticketId = (int)$_POST['ticket_id'];
    $message = trim($_POST['reply_message'] ?? '');
    
    if (empty($message)) {
        $error = 'Please enter a reply message';
    } else {
        try {
            db()->insert('ticket_replies', [
                'ticket_id' => $ticketId,
                'client_id' => $clientId,
                'message' => $message,
                'is_staff' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update ticket status
            db()->update('support_tickets', [
                'status' => 'waiting',
                'last_reply_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$ticketId]);
            
            $success = 'Reply sent successfully';
            
        } catch (Exception $e) {
            error_log("Ticket reply error: " . $e->getMessage());
            $error = 'Failed to send reply';
        }
    }
}

// Close ticket
if (isset($_GET['close']) && isset($_GET['id'])) {
    $ticketId = (int)$_GET['id'];
    
    try {
        db()->update('support_tickets', [
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s')
        ], 'id = ? AND client_id = ?', [$ticketId, $clientId]);
        
        $success = 'Ticket closed successfully';
        
    } catch (Exception $e) {
        error_log("Ticket close error: " . $e->getMessage());
        $error = 'Failed to close ticket';
    }
    
    header('Location: support.php');
    exit;
}

// Get view parameter
$view = $_GET['view'] ?? 'list';
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get single ticket details
$ticket = null;
$replies = [];
if ($view === 'view' && $ticketId > 0) {
    $ticket = db()->fetch("
        SELECT t.*, p.title as project_title 
        FROM support_tickets t
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.id = ? AND t.client_id = ?
    ", [$ticketId, $clientId]);
    
    if ($ticket) {
        $replies = db()->fetchAll("
            SELECT r.*, 
                   CASE 
                       WHEN r.is_staff = 1 THEN 'Support Team'
                       ELSE CONCAT(c.first_name, ' ', c.last_name)
                   END as sender_name
            FROM ticket_replies r
            LEFT JOIN client_users c ON r.client_id = c.id
            WHERE r.ticket_id = ?
            ORDER BY r.created_at ASC
        ", [$ticketId]);
        
        // Mark as read if any staff replied
        $hasStaffReply = false;
        foreach ($replies as $reply) {
            if ($reply['is_staff']) {
                $hasStaffReply = true;
                break;
            }
        }
        
        if ($hasStaffReply && $ticket['status'] === 'waiting') {
            db()->update('support_tickets', ['status' => 'in_progress'], 'id = ?', [$ticketId]);
        }
    } else {
        header('Location: support.php');
        exit;
    }
}

// Get tickets list
$status = $_GET['status'] ?? 'all';

$where = ["client_id = ?"];
$params = [$clientId];

if ($status !== 'all') {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

$tickets = db()->fetchAll("
    SELECT * FROM support_tickets 
    WHERE $whereClause 
    ORDER BY 
        CASE 
            WHEN status = 'open' THEN 1
            WHEN status = 'in_progress' THEN 2
            WHEN status = 'waiting' THEN 3
            WHEN status = 'resolved' THEN 4
            WHEN status = 'closed' THEN 5
        END,
        created_at DESC
", $params);

// Get projects for dropdown
$projects = db()->fetchAll("
    SELECT id, title FROM projects 
    WHERE client_id = ? 
    ORDER BY created_at DESC
", [$clientId]);

$pageTitle = $view === 'view' ? 'Ticket #' . $ticket['ticket_number'] : 'Support Tickets';
require_once '../includes/client-header.php';
?>

<?php if ($view === 'list'): ?>
<!-- Tickets List View -->
<div class="support-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-headset"></i> Support Tickets</h1>
            <p>Get help from our support team</p>
        </div>
        
        <button class="btn-primary" onclick="showNewTicketForm()">
            <i class="fas fa-plus"></i> New Ticket
        </button>
    </div>

    <!-- Status Filter -->
    <div class="filter-tabs">
        <a href="?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">All Tickets</a>
        <a href="?status=open" class="filter-tab <?php echo $status === 'open' ? 'active' : ''; ?>">Open</a>
        <a href="?status=in_progress" class="filter-tab <?php echo $status === 'in_progress' ? 'active' : ''; ?>">In Progress</a>
        <a href="?status=resolved" class="filter-tab <?php echo $status === 'resolved' ? 'active' : ''; ?>">Resolved</a>
        <a href="?status=closed" class="filter-tab <?php echo $status === 'closed' ? 'active' : ''; ?>">Closed</a>
    </div>

    <!-- New Ticket Form (hidden by default) -->
    <div class="new-ticket-form" id="newTicketForm" style="display: none;">
        <div class="form-card">
            <h3>Create New Support Ticket</h3>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="subject">Subject <span class="required">*</span></label>
                    <input type="text" id="subject" name="subject" required 
                           placeholder="Brief description of your issue">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="project_id">Related Project (Optional)</label>
                        <select id="project_id" name="project_id">
                            <option value="">-- Select Project --</option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>">
                                <?php echo htmlspecialchars($project['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="message">Message <span class="required">*</span></label>
                    <textarea id="message" name="message" rows="6" required 
                              placeholder="Describe your issue in detail..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="hideNewTicketForm()">
                        Cancel
                    </button>
                    <button type="submit" name="create_ticket" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Create Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tickets List -->
    <?php if (!empty($tickets)): ?>
    <div class="tickets-list">
        <?php foreach ($tickets as $ticket): ?>
        <div class="ticket-card priority-<?php echo $ticket['priority']; ?>">
            <div class="ticket-header">
                <div class="ticket-number">
                    <span class="ticket-id">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                    <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                        <?php echo ucfirst($ticket['status']); ?>
                    </span>
                </div>
                <div class="ticket-priority priority-<?php echo $ticket['priority']; ?>">
                    <i class="fas fa-flag"></i>
                    <?php echo ucfirst($ticket['priority']); ?>
                </div>
            </div>
            
            <div class="ticket-body">
                <h3 class="ticket-subject">
                    <a href="?view=view&id=<?php echo $ticket['id']; ?>">
                        <?php echo htmlspecialchars($ticket['subject']); ?>
                    </a>
                </h3>
                
                <div class="ticket-meta">
                    <span class="meta-item">
                        <i class="far fa-calendar"></i>
                        Created: <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                    </span>
                    
                    <?php if ($ticket['last_reply_at']): ?>
                    <span class="meta-item">
                        <i class="far fa-clock"></i>
                        Last reply: <?php echo timeAgo($ticket['last_reply_at']); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="ticket-footer">
                <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved'): ?>
                <a href="?close=1&id=<?php echo $ticket['id']; ?>" class="btn-close" 
                   onclick="return confirm('Close this ticket?')">
                    <i class="fas fa-check"></i> Close Ticket
                </a>
                <?php endif; ?>
                
                <a href="?view=view&id=<?php echo $ticket['id']; ?>" class="btn-view">
                    View Details <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Empty State -->
    <div class="empty-state">
        <i class="fas fa-ticket-alt"></i>
        <h3>No Tickets Found</h3>
        <p>You haven't created any support tickets yet.</p>
        <button class="btn-primary" onclick="showNewTicketForm()">
            <i class="fas fa-plus"></i> Create First Ticket
        </button>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($view === 'view' && $ticket): ?>
<!-- Single Ticket View -->
<div class="ticket-view">
    <!-- Back Navigation -->
    <div class="back-nav">
        <a href="support.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Tickets
        </a>
    </div>

    <!-- Ticket Header -->
    <div class="ticket-header-card">
        <div class="ticket-title">
            <h1><?php echo htmlspecialchars($ticket['subject']); ?></h1>
            <div class="ticket-badges">
                <span class="ticket-number">#<?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                    <?php echo ucfirst($ticket['status']); ?>
                </span>
                <span class="ticket-priority priority-<?php echo $ticket['priority']; ?>">
                    <i class="fas fa-flag"></i>
                    <?php echo ucfirst($ticket['priority']); ?>
                </span>
            </div>
        </div>
        
        <div class="ticket-meta">
            <div class="meta-item">
                <i class="far fa-calendar"></i>
                <span>Created: <?php echo date('F d, Y \a\t h:i A', strtotime($ticket['created_at'])); ?></span>
            </div>
            
            <?php if ($ticket['project_title']): ?>
            <div class="meta-item">
                <i class="fas fa-project-diagram"></i>
                <span>Project: <?php echo htmlspecialchars($ticket['project_title']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Conversation Thread -->
    <div class="conversation-thread">
        <?php foreach ($replies as $reply): ?>
        <div class="message-bubble <?php echo $reply['is_staff'] ? 'staff' : 'client'; ?>">
            <div class="message-header">
                <div class="sender-info">
                    <div class="sender-avatar">
                        <?php if ($reply['is_staff']): ?>
                        <i class="fas fa-user-tie"></i>
                        <?php else: ?>
                        <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="sender-details">
                        <span class="sender-name">
                            <?php echo $reply['is_staff'] ? 'Support Team' : 'You'; ?>
                        </span>
                        <span class="message-time">
                            <?php echo date('M d, Y \a\t h:i A', strtotime($reply['created_at'])); ?>
                        </span>
                    </div>
                </div>
                <?php if ($reply['is_staff']): ?>
                <span class="staff-badge">Staff</span>
                <?php endif; ?>
            </div>
            
            <div class="message-content">
                <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Reply Form -->
    <?php if ($ticket['status'] !== 'closed' && $ticket['status'] !== 'resolved'): ?>
    <div class="reply-form-card">
        <h3><i class="fas fa-reply"></i> Add Reply</h3>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
            
            <div class="form-group">
                <textarea name="reply_message" rows="4" required 
                          placeholder="Type your reply here..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="reply_ticket" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reply
                </button>
                
                <?php if ($ticket['status'] !== 'closed'): ?>
                <a href="?close=1&id=<?php echo $ticket['id']; ?>" class="btn-outline" 
                   onclick="return confirm('Close this ticket?')">
                    <i class="fas fa-check"></i> Close Ticket
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="ticket-closed-message">
        <i class="fas fa-check-circle"></i>
        <p>This ticket is closed. If you need further assistance, please create a new ticket.</p>
        <a href="support.php" class="btn-primary">Create New Ticket</a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
/* Support Page Styles */
.support-page {
    max-width: 800px;
    margin: 0 auto;
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
}

.btn-primary:hover {
    background: #5a67d8;
    transform: translateY(-2px);
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

/* Filter Tabs */
.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    background: white;
    padding: 5px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    color: #64748b;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.filter-tab:hover,
.filter-tab.active {
    background: #667eea;
    color: white;
}

/* New Ticket Form */
.new-ticket-form {
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

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
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

.required {
    color: #ef4444;
    margin-left: 2px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Tickets List */
.tickets-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.ticket-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    border-left: 4px solid transparent;
    transition: all 0.2s ease;
}

.ticket-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.ticket-card.priority-low { border-left-color: #10b981; }
.ticket-card.priority-medium { border-left-color: #f59e0b; }
.ticket-card.priority-high { border-left-color: #ef4444; }
.ticket-card.priority-urgent { border-left-color: #7c3aed; }

.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.ticket-number {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ticket-id {
    font-weight: 600;
    color: #1e293b;
}

.ticket-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-open { background: #dbeafe; color: #1e40af; }
.status-in_progress { background: #fef3c7; color: #92400e; }
.status-waiting { background: #e2e8f0; color: #475569; }
.status-resolved { background: #d1fae5; color: #065f46; }
.status-closed { background: #e2e8f0; color: #475569; }

.ticket-priority {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    font-weight: 500;
}

.ticket-priority.priority-low { color: #10b981; }
.ticket-priority.priority-medium { color: #f59e0b; }
.ticket-priority.priority-high { color: #ef4444; }
.ticket-priority.priority-urgent { color: #7c3aed; }

.ticket-body {
    margin-bottom: 15px;
}

.ticket-subject {
    font-size: 16px;
    margin-bottom: 10px;
}

.ticket-subject a {
    color: #1e293b;
    text-decoration: none;
}

.ticket-subject a:hover {
    color: #667eea;
}

.ticket-meta {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.meta-item {
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 5px;
}

.ticket-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid #e2e8f0;
}

.btn-close,
.btn-view {
    padding: 6px 12px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
    transition: all 0.2s ease;
}

.btn-close {
    background: #fee2e2;
    color: #991b1b;
}

.btn-close:hover {
    background: #fecaca;
}

.btn-view {
    color: #667eea;
}

.btn-view:hover {
    text-decoration: underline;
}

/* Ticket View */
.ticket-view {
    max-width: 800px;
    margin: 0 auto;
}

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

.ticket-header-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.ticket-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 15px;
}

.ticket-title h1 {
    font-size: 24px;
    color: #1e293b;
}

.ticket-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
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
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.message-bubble.staff {
    background: #eff6ff;
    border-left: 4px solid #667eea;
}

.message-bubble.client {
    background: white;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.sender-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sender-avatar {
    width: 32px;
    height: 32px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
}

.sender-details {
    display: flex;
    flex-direction: column;
}

.sender-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 14px;
}

.message-time {
    font-size: 11px;
    color: #94a3b8;
}

.staff-badge {
    display: inline-block;
    padding: 2px 6px;
    background: #667eea;
    color: white;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.message-content {
    color: #1e293b;
    line-height: 1.6;
    font-size: 14px;
}

/* Reply Form */
.reply-form-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.reply-form-card h3 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 20px;
}

.ticket-closed-message {
    text-align: center;
    padding: 40px;
    background: #f8fafc;
    border-radius: 15px;
}

.ticket-closed-message i {
    font-size: 50px;
    color: #10b981;
    margin-bottom: 15px;
}

.ticket-closed-message p {
    color: #475569;
    margin-bottom: 20px;
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 60px;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #1e293b;
    margin-bottom: 10px;
}

.empty-state p {
    color: #64748b;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .btn-primary {
        width: 100%;
        justify-content: center;
    }
    
    .filter-tabs {
        width: 100%;
        overflow-x: auto;
        flex-wrap: nowrap;
    }
    
    .filter-tab {
        white-space: nowrap;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .ticket-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .ticket-title {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .ticket-badges {
        width: 100%;
    }
    
    .message-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>

<script>
function showNewTicketForm() {
    document.getElementById('newTicketForm').style.display = 'block';
    document.getElementById('newTicketForm').scrollIntoView({ behavior: 'smooth' });
}

function hideNewTicketForm() {
    document.getElementById('newTicketForm').style.display = 'none';
}
</script>

<?php require_once '../includes/client-footer.php'; ?>