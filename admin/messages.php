<?php
// admin/messages.php
// Enhanced Contact Messages Management - FULLY RESPONSIVE

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Message Management';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Messages']
];

// Handle mark as read
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    db()->update('contact_messages', ['is_read' => 1], 'id = :id', ['id' => $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message marked as read'];
    header('Location: messages.php');
    exit;
}

// Handle mark as unread
if (isset($_GET['unread'])) {
    $id = (int)$_GET['unread'];
    db()->update('contact_messages', ['is_read' => 0], 'id = :id', ['id' => $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message marked as unread'];
    header('Location: messages.php');
    exit;
}

// Handle mark all as read
if (isset($_GET['read_all'])) {
    db()->update('contact_messages', ['is_read' => 1], 'is_read = 0', []);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'All messages marked as read'];
    header('Location: messages.php');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('contact_messages', 'id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Message deleted successfully'];
    header('Location: messages.php');
    exit;
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected = $_POST['selected'] ?? [];
    $action = $_POST['bulk_action'];
    
    if (!empty($selected)) {
        $ids = implode(',', array_map('intval', $selected));
        
        if ($action === 'read') {
            db()->query("UPDATE contact_messages SET is_read = 1 WHERE id IN ($ids)");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Selected messages marked as read'];
        } elseif ($action === 'unread') {
            db()->query("UPDATE contact_messages SET is_read = 0 WHERE id IN ($ids)");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Selected messages marked as unread'];
        } elseif ($action === 'delete') {
            db()->query("DELETE FROM contact_messages WHERE id IN ($ids)");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Selected messages deleted'];
        } elseif ($action === 'export') {
            exportMessages($selected);
            exit;
        }
    }
    header('Location: messages.php');
    exit;
}

// Handle export
if (isset($_GET['export'])) {
    exportMessages();
    exit;
}

// Export function
function exportMessages($selected = []) {
    if (!empty($selected)) {
        $ids = implode(',', array_map('intval', $selected));
        $messages = db()->fetchAll("SELECT * FROM contact_messages WHERE id IN ($ids) ORDER BY created_at DESC");
    } else {
        $messages = db()->fetchAll("SELECT * FROM contact_messages ORDER BY created_at DESC");
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="messages-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Company', 'Subject', 'Message', 'Date', 'Status']);
    
    foreach ($messages as $msg) {
        fputcsv($output, [
            $msg['id'],
            $msg['name'],
            $msg['email'],
            $msg['phone'] ?? '',
            $msg['company'] ?? '',
            $msg['subject'] ?? '',
            $msg['message'],
            $msg['created_at'],
            $msg['is_read'] ? 'Read' : 'Unread'
        ]);
    }
    fclose($output);
    exit;
}

// Helper functions
function truncateEmail($email, $length = 25) {
    if (strlen($email) <= $length) {
        return $email;
    }
    return substr($email, 0, $length) . '...';
}



// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query based on filters
$where = ["1=1"];
$params = [];

if ($filter === 'unread') {
    $where[] = "is_read = 0";
} elseif ($filter === 'read') {
    $where[] = "is_read = 1";
}

if (!empty($search)) {
    $where[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($date_from)) {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
}

$whereClause = implode(' AND ', $where);

// Get messages with pagination
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalMessages = db()->fetch("SELECT COUNT(*) as count FROM contact_messages WHERE $whereClause", $params)['count'] ?? 0;
$totalPages = ceil($totalMessages / $perPage);

$messages = db()->fetchAll("
    SELECT * FROM contact_messages 
    WHERE $whereClause 
    ORDER BY 
        CASE WHEN is_read = 0 THEN 0 ELSE 1 END,
        created_at DESC 
    LIMIT ? OFFSET ?
", array_merge($params, [$perPage, $offset]));

// Get statistics
$stats = db()->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
        SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as this_week,
        MAX(created_at) as last_message
    FROM contact_messages
") ?? ['total' => 0, 'unread' => 0, 'today' => 0, 'this_week' => 0, 'last_message' => null];

// Get message sources
$sources = db()->fetchAll("
    SELECT 
        CASE 
            WHEN phone IS NOT NULL AND phone != '' THEN 'Phone'
            ELSE 'Email'
        END as source,
        COUNT(*) as count
    FROM contact_messages
    GROUP BY source
");

// Include header
require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="content-header">
    <h2>
        <i class="fas fa-envelope"></i> Message Management
        <?php if ($stats['unread'] > 0): ?>
        <span class="header-badge"><?php echo $stats['unread']; ?> unread</span>
        <?php endif; ?>
    </h2>
    <div class="header-actions">
        <?php if ($stats['unread'] > 0): ?>
        <a href="?read_all=1" class="btn btn-success" onclick="return confirm('Mark all messages as read?')">
            <i class="fas fa-check-double"></i> Mark All Read
        </a>
        <?php endif; ?>
        <a href="?export=1" class="btn btn-primary">
            <i class="fas fa-download"></i> Export CSV
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

<!-- Statistics Cards - Responsive Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="stat-details">
            <h3>Total Messages</h3>
            <span class="stat-value"><?php echo number_format($stats['total']); ?></span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon <?php echo $stats['unread'] > 0 ? 'orange' : 'green'; ?>">
            <i class="fas <?php echo $stats['unread'] > 0 ? 'fa-envelope-open' : 'fa-check-circle'; ?>"></i>
        </div>
        <div class="stat-details">
            <h3>Unread</h3>
            <span class="stat-value"><?php echo $stats['unread']; ?></span>
            <span class="stat-label"><?php echo $stats['total'] > 0 ? round(($stats['unread'] / $stats['total']) * 100, 1) : 0; ?>%</span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-details">
            <h3>Today</h3>
            <span class="stat-value"><?php echo $stats['today']; ?></span>
            <span class="stat-label">new messages</span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon teal">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class-="stat-details">
            <h3>This Week</h3>
            <span class="stat-value"><?php echo $stats['this_week']; ?></span>
            <span class="stat-label">total messages</span>
        </div>
    </div>
</div>

<!-- Source Distribution - Responsive -->
<?php if (!empty($sources)): ?>
<div class="source-distribution">
    <h3><i class="fas fa-chart-pie"></i> Message Sources</h3>
    <div class="source-bars">
        <?php foreach ($sources as $source): 
            $percentage = $stats['total'] > 0 ? round(($source['count'] / $stats['total']) * 100, 1) : 0;
        ?>
        <div class="source-item">
            <span class="source-name">
                <i class="fas <?php echo $source['source'] === 'Phone' ? 'fa-phone' : 'fa-envelope'; ?>"></i>
                <?php echo $source['source']; ?>
            </span>
            <div class="source-bar-container">
                <div class="source-bar" style="width: <?php echo $percentage; ?>%"></div>
                <span class="source-count"><?php echo $source['count']; ?> (<?php echo $percentage; ?>%)</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filter Bar - Responsive -->
<div class="filter-bar">
    <form method="GET" class="filter-form">
        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">Unread</a>
            <a href="?filter=read" class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">Read</a>
        </div>
        
        <div class="filter-search">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search messages..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        
        <div class="filter-dates">
            <input type="date" name="date_from" value="<?php echo $date_from; ?>" placeholder="From">
            <span>to</span>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>" placeholder="To">
        </div>
        
        <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
        <a href="messages.php" class="btn btn-outline btn-sm">Reset</a>
    </form>
</div>

<!-- Mobile Filter Bar (visible on small screens) -->
<div class="mobile-filter-bar">
    <select class="mobile-filter-select" onchange="window.location.href=this.value">
        <option value="?filter=all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Messages</option>
        <option value="?filter=unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
        <option value="?filter=read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Read Only</option>
    </select>
    
    <button class="btn btn-outline btn-sm" onclick="toggleMobileSearch()">
        <i class="fas fa-search"></i> Search
    </button>
</div>

<!-- Mobile Search Panel (hidden by default) -->
<div class="mobile-search-panel" id="mobileSearchPanel" style="display: none;">
    <form method="GET" class="mobile-search-form">
        <input type="hidden" name="filter" value="<?php echo $filter; ?>">
        <div class="mobile-search-group">
            <input type="text" name="search" placeholder="Search messages..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="mobile-search-group">
            <input type="date" name="date_from" value="<?php echo $date_from; ?>" placeholder="From">
        </div>
        <div class="mobile-search-group">
            <input type="date" name="date_to" value="<?php echo $date_to; ?>" placeholder="To">
        </div>
        <div class="mobile-search-actions">
            <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <a href="messages.php" class="btn btn-outline btn-sm">Reset</a>
        </div>
    </form>
</div>

<!-- Bulk Actions Bar - Responsive -->
<form method="POST" id="bulkForm">
    <div class="bulk-actions-bar">
        <div class="bulk-select-wrapper">
            <select name="bulk_action" class="bulk-select">
                <option value="">Bulk Actions</option>
                <option value="read">Mark as Read</option>
                <option value="unread">Mark as Unread</option>
                <option value="delete">Delete</option>
                <option value="export">Export Selected</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm" onclick="return confirmBulkAction()">Apply</button>
        </div>
        
        <div class="selected-count" id="selectedCount">
            <span>0</span> selected
        </div>
    </div>

    <!-- Messages Table - Desktop View -->
    <div class="table-responsive desktop-table">
        <table class="admin-table messages-table">
            <thead>
                <tr>
                    <th width="30">
                        <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                    </th>
                    <th width="40">Status</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th width="120">Received</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $message): 
                    $isUnread = !$message['is_read'];
                ?>
                <tr class="<?php echo $isUnread ? 'unread' : ''; ?>" data-message-id="<?php echo $message['id']; ?>">
                    <td>
                        <input type="checkbox" name="selected[]" value="<?php echo $message['id']; ?>" class="select-item">
                    </td>
                    <td>
                        <span class="status-indicator <?php echo $isUnread ? 'pulse' : ''; ?>" 
                              title="<?php echo $isUnread ? 'Unread' : 'Read'; ?>">
                            <i class="fas fa-circle"></i>
                        </span>
                    </td>
                    <td>
                        <div class="contact-info">
                            <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                            <?php if (!empty($message['company'])): ?>
                            <br><small class="company-name"><?php echo htmlspecialchars($message['company']); ?></small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="contact-details">
                            <a href="mailto:<?php echo $message['email']; ?>" class="email-link">
                                <i class="fas fa-envelope"></i>
                                <span class="email-text"><?php echo htmlspecialchars(truncateEmail($message['email'], 20)); ?></span>
                            </a>
                            <?php if (!empty($message['phone'])): ?>
                            <br>
                            <a href="tel:<?php echo $message['phone']; ?>" class="phone-link">
                                <i class="fas fa-phone"></i>
                                <span class="phone-text"><?php echo htmlspecialchars($message['phone']); ?></span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="subject <?php echo empty($message['subject']) ? 'empty' : ''; ?>">
                            <?php echo $message['subject'] ? htmlspecialchars(truncate($message['subject'], 30)) : '<em>No Subject</em>'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="message-preview">
                            <span class="message-text"><?php echo htmlspecialchars(truncate($message['message'], 50)); ?></span>
                            <button class="preview-btn" onclick="previewMessage(<?php echo $message['id']; ?>)" title="Preview">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="date-info">
                            <span class="date"><?php echo date('M d, Y', strtotime($message['created_at'])); ?></span>
                            <br>
                            <span class="time"><?php echo date('h:i A', strtotime($message['created_at'])); ?></span>
                        </div>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="view-message.php?id=<?php echo $message['id']; ?>" class="action-btn" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            
                            <?php if ($isUnread): ?>
                            <a href="?read=<?php echo $message['id']; ?>" class="action-btn" title="Mark as Read">
                                <i class="fas fa-check"></i>
                            </a>
                            <?php else: ?>
                            <a href="?unread=<?php echo $message['id']; ?>" class="action-btn" title="Mark as Unread">
                                <i class="fas fa-envelope"></i>
                            </a>
                            <?php endif; ?>
                            
                            <a href="mailto:<?php echo $message['email']; ?>?subject=Re: <?php echo urlencode($message['subject']); ?>" 
                               class="action-btn" title="Reply by Email">
                                <i class="fas fa-reply"></i>
                            </a>
                            
                            <a href="?delete=<?php echo $message['id']; ?>" class="action-btn delete-btn" 
                               onclick="return confirm('Are you sure you want to delete this message?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($messages)): ?>
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-envelope-open"></i>
                            <h4>No messages found</h4>
                            <p>Try adjusting your filters or check back later</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Cards View -->
    <div class="mobile-cards">
        <?php foreach ($messages as $message): 
            $isUnread = !$message['is_read'];
        ?>
        <div class="message-card <?php echo $isUnread ? 'unread' : ''; ?>" data-message-id="<?php echo $message['id']; ?>">
            <div class="card-header">
                <div class="card-checkbox">
                    <input type="checkbox" name="selected[]" value="<?php echo $message['id']; ?>" class="select-item-mobile">
                </div>
                <div class="card-status">
                    <span class="status-indicator <?php echo $isUnread ? 'pulse' : ''; ?>">
                        <i class="fas fa-circle"></i>
                    </span>
                </div>
                <div class="card-title">
                    <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                    <?php if (!empty($message['company'])): ?>
                    <span class="company-tag"><?php echo htmlspecialchars($message['company']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-date">
                    <?php echo date('M d', strtotime($message['created_at'])); ?>
                </div>
            </div>
            
            <div class="card-body">
                <div class="card-contact">
                    <a href="mailto:<?php echo $message['email']; ?>" class="contact-link">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars(truncateEmail($message['email'], 25)); ?>
                    </a>
                    <?php if (!empty($message['phone'])): ?>
                    <a href="tel:<?php echo $message['phone']; ?>" class="contact-link">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($message['phone']); ?>
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="card-subject">
                    <strong>Subject:</strong> 
                    <?php echo $message['subject'] ? htmlspecialchars($message['subject']) : '<em>No Subject</em>'; ?>
                </div>
                
                <div class="card-message">
                    <?php echo htmlspecialchars(truncate($message['message'], 100)); ?>
                </div>
                
                <div class="card-time">
                    <i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                </div>
            </div>
            
            <div class="card-footer">
                <div class="card-actions">
                    <a href="view-message.php?id=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline" title="View">
                        <i class="fas fa-eye"></i> View
                    </a>
                    
                    <?php if ($isUnread): ?>
                    <a href="?read=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline" title="Mark as Read">
                        <i class="fas fa-check"></i> Read
                    </a>
                    <?php else: ?>
                    <a href="?unread=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline" title="Mark as Unread">
                        <i class="fas fa-envelope"></i> Unread
                    </a>
                    <?php endif; ?>
                    
                    <a href="mailto:<?php echo $message['email']; ?>?subject=Re: <?php echo urlencode($message['subject']); ?>" 
                       class="btn btn-sm btn-outline" title="Reply">
                        <i class="fas fa-reply"></i> Reply
                    </a>
                    
                    <a href="?delete=<?php echo $message['id']; ?>" class="btn btn-sm btn-outline-danger" 
                       onclick="return confirm('Delete this message?')" title="Delete">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($messages)): ?>
        <div class="empty-state">
            <i class="fas fa-envelope-open"></i>
            <h4>No messages found</h4>
            <p>Try adjusting your filters or check back later</p>
        </div>
        <?php endif; ?>
    </div>
</form>

<!-- Pagination - Responsive -->
<?php if ($totalPages > 1): ?>
<div class="pagination-container">
    <div class="pagination-info">
        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
    </div>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?p=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="page-link" title="Previous page">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end = min($totalPages, $page + $range);
        
        if ($start > 1): ?>
        <a href="?p=1&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="page-link">1</a>
        <?php if ($start > 2): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="?p=<?php echo $i; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <a href="?p=<?php echo $totalPages; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="page-link"><?php echo $totalPages; ?></a>
        <?php endif; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?p=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" 
           class="page-link" title="Next page">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Message Preview Modal -->
<div class="modal" id="previewModal">
    <div class="modal-overlay" onclick="closePreviewModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-envelope-open"></i> Message Preview</h3>
            <button class="close-modal" onclick="closePreviewModal()">&times;</button>
        </div>
        <div class="modal-body" id="previewContent">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
        </div>
        <div class="modal-footer">
            <a href="#" id="previewViewLink" class="btn btn-primary">View Full Message</a>
            <a href="#" id="previewReplyLink" class="btn btn-success">Reply</a>
            <button class="btn btn-outline" onclick="closePreviewModal()">Close</button>
        </div>
    </div>
</div>

<style>
/* ========================================
   RESPONSIVE STYLES FOR MESSAGES PAGE
   ======================================== */

/* Base Styles */
:root {
    --primary: #2563eb;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #1e293b;
    --gray-600: #475569;
    --gray-500: #64748b;
    --gray-400: #94a3b8;
    --gray-300: #cbd5e1;
    --gray-200: #e2e8f0;
    --gray-100: #f1f5f9;
}

/* Header Badge */
.header-badge {
    display: inline-block;
    padding: 5px 12px;
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 500;
    margin-left: 10px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-icon.blue { background: rgba(37,99,235,0.1); color: #2563eb; }
.stat-icon.orange { background: rgba(245,158,11,0.1); color: #f59e0b; }
.stat-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-icon.purple { background: rgba(124,58,237,0.1); color: #7c3aed; }
.stat-icon.teal { background: rgba(20,184,166,0.1); color: #14b8a6; }

.stat-details h3 {
    font-size: 0.9rem;
    color: var(--gray-600);
    margin-bottom: 5px;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
    display: block;
}

.stat-label {
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* Source Distribution */
.source-distribution {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.source-distribution h3 {
    font-size: 1rem;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.source-bars {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.source-item {
    display: flex;
    align-items: center;
    gap: 15px;
}

.source-name {
    min-width: 80px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.source-bar-container {
    flex: 1;
    height: 24px;
    background: var(--gray-200);
    border-radius: 12px;
    position: relative;
    overflow: hidden;
}

.source-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), #7c3aed);
    border-radius: 12px;
    transition: width 0.3s ease;
}

.source-count {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.filter-form {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.filter-tabs {
    display: flex;
    gap: 5px;
    background: var(--gray-100);
    padding: 3px;
    border-radius: 8px;
}

.filter-tab {
    padding: 6px 15px;
    color: var(--gray-600);
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.filter-tab:hover,
.filter-tab.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filter-search {
    position: relative;
    flex: 1;
    min-width: 200px;
}

.filter-search i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
}

.filter-search input {
    width: 100%;
    padding: 8px 15px 8px 35px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.9rem;
}

.filter-search input:focus {
    outline: none;
    border-color: var(--primary);
}

.filter-dates {
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-dates input {
    padding: 8px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.9rem;
}

/* Mobile Filter */
.mobile-filter-bar {
    display: none;
    gap: 10px;
    margin-bottom: 15px;
}

.mobile-filter-select {
    flex: 1;
    padding: 10px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.95rem;
    background: white;
}

.mobile-search-panel {
    background: white;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.mobile-search-group {
    margin-bottom: 10px;
}

.mobile-search-group input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.95rem;
}

.mobile-search-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.mobile-search-actions .btn {
    flex: 1;
}

/* Bulk Actions */
.bulk-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px 15px;
    background: var(--gray-100);
    border-radius: 8px;
    flex-wrap: wrap;
    gap: 10px;
}

.bulk-select-wrapper {
    display: flex;
    gap: 10px;
    align-items: center;
}

.bulk-select {
    padding: 8px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
    min-width: 150px;
}

.selected-count {
    font-size: 0.9rem;
    color: var(--gray-600);
}

.selected-count span {
    font-weight: 600;
    color: var(--primary);
}

/* Desktop Table */
.desktop-table {
    overflow-x: auto;
    margin-bottom: 20px;
    border-radius: 12px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.messages-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.messages-table th {
    background: var(--gray-100);
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.9rem;
    white-space: nowrap;
}

.messages-table td {
    padding: 15px 12px;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: top;
}

.messages-table tr.unread {
    background: rgba(37,99,235,0.03);
}

.messages-table tr:hover {
    background: var(--gray-100);
}

/* Status Indicator */
.status-indicator {
    display: inline-block;
    color: #10b981;
    font-size: 0.8rem;
}

.status-indicator.pulse {
    color: var(--primary);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Contact Info */
.contact-info .company-name {
    color: var(--gray-500);
    font-size: 0.75rem;
}

/* Contact Details */
.email-link,
.phone-link {
    color: var(--gray-600);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.85rem;
}

.email-link:hover,
.phone-link:hover {
    color: var(--primary);
}

.email-text,
.phone-text {
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Subject */
.subject {
    font-size: 0.9rem;
    max-width: 150px;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.subject.empty {
    color: var(--gray-500);
    font-style: italic;
}

/* Message Preview */
.message-preview {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: var(--gray-600);
    max-width: 200px;
}

.message-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.preview-btn {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s ease;
    width: 24px;
    height: 24px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

tr:hover .preview-btn {
    opacity: 1;
}

.preview-btn:hover {
    background: var(--primary);
    color: white;
}

/* Date Info */
.date-info {
    text-align: center;
    white-space: nowrap;
}

.date {
    font-weight: 500;
    font-size: 0.9rem;
}

.time {
    font-size: 0.75rem;
    color: var(--gray-500);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.action-btn {
    width: 32px;
    height: 32px;
    background: var(--gray-100);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: var(--primary);
    color: white;
    transform: scale(1.1);
}

.action-btn.delete-btn:hover {
    background: var(--danger);
}

/* Mobile Cards */
.mobile-cards {
    display: none;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.message-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-left: 4px solid transparent;
}

.message-card.unread {
    border-left-color: var(--primary);
    background: rgba(37,99,235,0.02);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.card-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.card-status {
    margin-right: 5px;
}

.card-title {
    flex: 1;
    font-weight: 500;
}

.company-tag {
    display: inline-block;
    padding: 2px 8px;
    background: var(--gray-200);
    border-radius: 12px;
    font-size: 0.7rem;
    margin-left: 5px;
    color: var(--gray-700);
}

.card-date {
    color: var(--gray-500);
    font-size: 0.8rem;
}

.card-contact {
    margin-bottom: 10px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.contact-link {
    color: var(--gray-600);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.contact-link:hover {
    color: var(--primary);
}

.card-subject {
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.card-message {
    color: var(--gray-600);
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 10px;
    padding: 10px;
    background: var(--gray-100);
    border-radius: 6px;
}

.card-time {
    color: var(--gray-500);
    font-size: 0.8rem;
    margin-bottom: 10px;
}

.card-footer {
    border-top: 1px solid var(--gray-200);
    padding-top: 12px;
}

.card-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.card-actions .btn {
    flex: 1;
    min-width: 80px;
    padding: 6px 8px;
    font-size: 0.8rem;
}

.btn-outline-danger {
    background: transparent;
    color: var(--danger);
    border: 1px solid var(--danger);
}

.btn-outline-danger:hover {
    background: var(--danger);
    color: white;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.pagination-info {
    color: var(--gray-600);
    font-size: 0.9rem;
}

.pagination {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.page-link {
    padding: 8px 12px;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    color: var(--gray-700);
    text-decoration: none;
    transition: all 0.2s ease;
    min-width: 36px;
    text-align: center;
}

.page-link:hover,
.page-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-dots {
    padding: 8px 4px;
    color: var(--gray-500);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 15px;
}

.empty-state h4 {
    color: var(--gray-600);
    margin-bottom: 5px;
}

.empty-state p {
    color: var(--gray-500);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    overflow: hidden;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--gray-500);
}

.close-modal:hover {
    color: var(--danger);
}

.modal-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.loading-spinner {
    text-align: center;
    padding: 30px;
    color: var(--gray-500);
}

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: rgba(16,185,129,0.1);
    color: #065f46;
    border: 1px solid rgba(16,185,129,0.2);
}

.alert-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
}

.alert-close:hover {
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

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.btn-sm {
    padding: 5px 12px;
    font-size: 0.85rem;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #0f9e6e;
}

.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: white;
}

/* ========================================
   RESPONSIVE BREAKPOINTS
   ======================================== */

/* Tablet */
@media (max-width: 1023px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-tabs {
        justify-content: center;
    }
    
    .filter-search {
        width: 100%;
    }
    
    .filter-dates {
        width: 100%;
    }
    
    .filter-dates input {
        flex: 1;
    }
}

/* Mobile Landscape & Portrait */
@media (max-width: 767px) {
    .content-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .header-actions {
        width: 100%;
        flex-wrap: wrap;
    }
    
    .header-actions .btn {
        flex: 1;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-bar {
        display: none;
    }
    
    .mobile-filter-bar {
        display: flex;
    }
    
    .desktop-table {
        display: none;
    }
    
    .mobile-cards {
        display: flex;
    }
    
    .bulk-actions-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .bulk-select-wrapper {
        width: 100%;
    }
    
    .bulk-select {
        flex: 1;
    }
    
    .pagination-container {
        flex-direction: column;
        align-items: center;
    }
}

/* Small Mobile */
@media (max-width: 480px) {
    .header-actions {
        flex-direction: column;
    }
    
    .header-actions .btn {
        width: 100%;
    }
    
    .card-actions .btn {
        width: 100%;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
    }
}

/* Print */
@media print {
    .header-actions,
    .filter-bar,
    .mobile-filter-bar,
    .bulk-actions-bar,
    .action-buttons,
    .card-actions,
    .pagination,
    .modal {
        display: none !important;
    }
    
    .desktop-table {
        display: block !important;
        overflow: visible;
    }
    
    .mobile-cards {
        display: none !important;
    }
}
</style>

<script>
let selectedMessages = [];

function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.select-item, .select-item-mobile');
    checkboxes.forEach(cb => {
        cb.checked = source.checked;
        if (source.checked) {
            if (!selectedMessages.includes(cb.value)) {
                selectedMessages.push(cb.value);
            }
        } else {
            selectedMessages = [];
        }
    });
    updateSelectedCount();
}

document.querySelectorAll('.select-item, .select-item-mobile').forEach(cb => {
    cb.addEventListener('change', function() {
        if (this.checked) {
            selectedMessages.push(this.value);
        } else {
            selectedMessages = selectedMessages.filter(id => id !== this.value);
        }
        updateSelectedCount();
        updateSelectAll();
    });
});

function updateSelectedCount() {
    const countSpan = document.querySelector('#selectedCount span');
    if (countSpan) {
        countSpan.textContent = selectedMessages.length;
    }
}

function updateSelectAll() {
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        const total = document.querySelectorAll('.select-item, .select-item-mobile').length;
        selectAll.checked = selectedMessages.length === total;
        selectAll.indeterminate = selectedMessages.length > 0 && selectedMessages.length < total;
    }
}

function confirmBulkAction() {
    const action = document.querySelector('select[name="bulk_action"]').value;
    
    if (!action) {
        alert('Please select an action');
        return false;
    }
    
    if (selectedMessages.length === 0) {
        alert('Please select at least one message');
        return false;
    }
    
    if (action === 'delete') {
        return confirm(`Are you sure you want to delete ${selectedMessages.length} message(s)?`);
    }
    
    return true;
}

function toggleMobileSearch() {
    const panel = document.getElementById('mobileSearchPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function previewMessage(messageId) {
    fetch(`ajax/get-message.php?id=${messageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modal = document.getElementById('previewModal');
                const content = document.getElementById('previewContent');
                const viewLink = document.getElementById('previewViewLink');
                const replyLink = document.getElementById('previewReplyLink');
                
                content.innerHTML = `
                    <div class="message-details">
                        <p><strong>From:</strong> ${data.name} (${data.email})</p>
                        ${data.phone ? `<p><strong>Phone:</strong> ${data.phone}</p>` : ''}
                        ${data.company ? `<p><strong>Company:</strong> ${data.company}</p>` : ''}
                        <p><strong>Subject:</strong> ${data.subject || 'No Subject'}</p>
                        <p><strong>Date:</strong> ${data.created_at}</p>
                        <hr>
                        <div class="message-body">
                            ${data.message.replace(/\n/g, '<br>')}
                        </div>
                    </div>
                `;
                
                viewLink.href = `view-message.php?id=${messageId}`;
                replyLink.href = `mailto:${data.email}?subject=Re: ${encodeURIComponent(data.subject || 'Contact Form')}`;
                
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        });
}

function closePreviewModal() {
    document.getElementById('previewModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking overlay
document.querySelector('.modal-overlay')?.addEventListener('click', closePreviewModal);

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreviewModal();
    }
});

// Auto-hide alerts after 5 seconds
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => {
            alert.style.display = 'none';
        }, 300);
    }, 5000);
});

// Handle responsive behavior
function handleResponsive() {
    const width = window.innerWidth;
    const desktopTable = document.querySelector('.desktop-table');
    const mobileCards = document.querySelector('.mobile-cards');
    
    if (width <= 767) {
        if (desktopTable) desktopTable.style.display = 'none';
        if (mobileCards) mobileCards.style.display = 'flex';
    } else {
        if (desktopTable) desktopTable.style.display = 'block';
        if (mobileCards) mobileCards.style.display = 'none';
    }
}

// Run on load and resize
window.addEventListener('load', handleResponsive);
window.addEventListener('resize', handleResponsive);
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>