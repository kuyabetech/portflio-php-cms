<?php
/**
 * Client Notifications - View all notifications
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

// Handle mark as read
if (isset($_GET['read'])) {
    $notificationId = (int)$_GET['read'];
    db()->update('client_notifications', ['is_read' => 1], 'id = ? AND client_id = ?', [$notificationId, $clientId]);
    header('Location: notifications.php');
    exit;
}

// Handle mark all as read
if (isset($_GET['read_all'])) {
    db()->update('client_notifications', ['is_read' => 1], 'client_id = ? AND is_read = 0', [$clientId]);
    header('Location: notifications.php');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $notificationId = (int)$_GET['delete'];
    db()->delete('client_notifications', 'id = ? AND client_id = ?', [$notificationId, $clientId]);
    header('Location: notifications.php');
    exit;
}

// Handle clear all
if (isset($_GET['clear_all'])) {
    db()->delete('client_notifications', 'client_id = ?', [$clientId]);
    header('Location: notifications.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$totalNotifications = db()->fetch("SELECT COUNT(*) as count FROM client_notifications WHERE client_id = ?", [$clientId])['count'] ?? 0;
$totalPages = ceil($totalNotifications / $perPage);

// Get notifications
$notifications = db()->fetchAll("
    SELECT * FROM client_notifications 
    WHERE client_id = ? 
    ORDER BY 
        is_read ASC,
        created_at DESC 
    LIMIT ? OFFSET ?
", [$clientId, $perPage, $offset]);

// Get unread count
$unreadCount = db()->fetch("SELECT COUNT(*) as count FROM client_notifications WHERE client_id = ? AND is_read = 0", [$clientId])['count'] ?? 0;

$pageTitle = 'Notifications';
require_once '../includes/client-header.php';
?>

<div class="notifications-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <p>Stay updated with your account activity</p>
        </div>
        
        <div class="header-actions">
            <?php if ($unreadCount > 0): ?>
            <a href="?read_all=1" class="btn-outline" onclick="return confirm('Mark all notifications as read?')">
                <i class="fas fa-check-double"></i> Mark All Read
            </a>
            <?php endif; ?>
            
            <?php if ($totalNotifications > 0): ?>
            <a href="?clear_all=1" class="btn-outline-danger" onclick="return confirm('Delete all notifications? This cannot be undone.')">
                <i class="fas fa-trash"></i> Clear All
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Card -->
    <div class="summary-card">
        <div class="summary-item">
            <span class="summary-label">Total Notifications</span>
            <span class="summary-value"><?php echo $totalNotifications; ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Unread</span>
            <span class="summary-value"><?php echo $unreadCount; ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Read</span>
            <span class="summary-value"><?php echo $totalNotifications - $unreadCount; ?></span>
        </div>
    </div>

    <!-- Notifications List -->
    <?php if (!empty($notifications)): ?>
    <div class="notifications-list">
        <?php foreach ($notifications as $notification): ?>
        <div class="notification-card <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
            <div class="notification-icon">
                <i class="fas <?php echo $notification['icon'] ?? 'fa-bell'; ?>"></i>
            </div>
            
            <div class="notification-content">
                <div class="notification-header">
                    <div class="notification-title">
                        <?php if ($notification['type']): ?>
                        <span class="notification-type"><?php echo ucfirst($notification['type']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="notification-time">
                        <?php echo timeAgo($notification['created_at']); ?>
                    </div>
                </div>
                
                <div class="notification-message">
                    <?php echo htmlspecialchars($notification['message']); ?>
                </div>
                
                <?php if (!empty($notification['link'])): ?>
                <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="notification-link">
                    View Details <i class="fas fa-arrow-right"></i>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="notification-actions">
                <?php if (!$notification['is_read']): ?>
                <a href="?read=<?php echo $notification['id']; ?>" class="action-btn" title="Mark as read">
                    <i class="fas fa-check"></i>
                </a>
                <?php endif; ?>
                <a href="?delete=<?php echo $notification['id']; ?>" class="action-btn delete" 
                   onclick="return confirm('Delete this notification?')" title="Delete">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>" class="page-link">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>" class="page-link">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Empty State -->
    <div class="empty-state">
        <i class="fas fa-bell-slash"></i>
        <h3>No Notifications</h3>
        <p>You don't have any notifications at the moment.</p>
    </div>
    <?php endif; ?>
</div>

<style>
.notifications-page {
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

.header-actions {
    display: flex;
    gap: 10px;
}

/* Buttons */
.btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: transparent;
    color: #667eea;
    border: 2px solid #667eea;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-outline:hover {
    background: #667eea;
    color: white;
}

.btn-outline-danger {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: transparent;
    color: #ef4444;
    border: 2px solid #ef4444;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn-outline-danger:hover {
    background: #ef4444;
    color: white;
}

/* Summary Card */
.summary-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    display: flex;
    justify-content: space-around;
    flex-wrap: wrap;
    gap: 20px;
}

.summary-item {
    text-align: center;
}

.summary-label {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-value {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
}

/* Notifications List */
.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.notification-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    display: flex;
    gap: 15px;
    align-items: flex-start;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.notification-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.notification-card.unread {
    background: #eff6ff;
    border-left: 3px solid #667eea;
}

.notification-icon {
    width: 40px;
    height: 40px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    font-size: 18px;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
    flex-wrap: wrap;
    gap: 10px;
}

.notification-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.notification-type {
    display: inline-block;
    padding: 2px 6px;
    background: #e2e8f0;
    color: #475569;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.notification-time {
    font-size: 12px;
    color: #64748b;
}

.notification-message {
    color: #1e293b;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 8px;
}

.notification-link {
    color: #667eea;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.notification-link:hover {
    text-decoration: underline;
}

.notification-actions {
    display: flex;
    gap: 5px;
    flex-shrink: 0;
}

.action-btn {
    width: 32px;
    height: 32px;
    background: #f1f5f9;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: #667eea;
    color: white;
}

.action-btn.delete:hover {
    background: #ef4444;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 30px;
}

.page-link {
    padding: 8px 12px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s ease;
}

.page-link:hover,
.page-link.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
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
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .btn-outline,
    .btn-outline-danger {
        width: 100%;
        justify-content: center;
    }
    
    .summary-card {
        flex-direction: column;
        gap: 15px;
    }
    
    .notification-card {
        flex-direction: column;
    }
    
    .notification-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<?php require_once '../includes/client-footer.php'; ?>