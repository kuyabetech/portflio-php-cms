<?php
// admin/email-queue.php
// Email Queue Management - FULLY RESPONSIVE VERSION

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Email Queue';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Email Queue']
];

// ────────────────────────────────────────────────
// Handle actions
// ────────────────────────────────────────────────

if (isset($_GET['retry'])) {
    $id = (int)$_GET['retry'];
    db()->update('email_queue', [
        'status'        => 'pending',
        'attempts'      => 0,
        'error_message' => null
    ], 'id = ?', [$id]);
    header('Location: email-queue.php?msg=retried');
    exit;
}

if (isset($_GET['retry_all'])) {
    db()->update('email_queue', [
        'status'        => 'pending',
        'attempts'      => 0,
        'error_message' => null
    ], 'status = ?', ['failed']);
    header('Location: email-queue.php?msg=retried_all');
    exit;
}

if (isset($_GET['cancel'])) {
    $id = (int)$_GET['cancel'];
    db()->update('email_queue', ['status' => 'cancelled'], 'id = ?', [$id]);
    header('Location: email-queue.php?msg=cancelled');
    exit;
}

// ────────────────────────────────────────────────
// Get statistics
// ────────────────────────────────────────────────

$stats = db()->fetch("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending'    THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'sent'       THEN 1 ELSE 0 END) AS sent,
        SUM(CASE WHEN status = 'failed'     THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN status = 'cancelled'  THEN 1 ELSE 0 END) AS cancelled
    FROM email_queue
") ?? ['total' => 0, 'pending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];

// ────────────────────────────────────────────────
// Pagination & filtering
// ────────────────────────────────────────────────

$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$filter   = $_GET['filter'] ?? 'all';
$whereSql = '';
$params   = [];

if ($filter !== 'all' && in_array($filter, ['pending','sent','failed','cancelled'])) {
    $whereSql = 'WHERE status = ?';
    $params[] = $filter;
}

// Total count for pagination
$totalItems = db()->fetch(
    "SELECT COUNT(*) AS count FROM email_queue $whereSql",
    $params
)['count'] ?? 0;

$totalPages = ceil($totalItems / $perPage);

// ────────────────────────────────────────────────
// Fetch queue items
// ────────────────────────────────────────────────

$listParams = array_merge($params, [$perPage, $offset]);

$queue = db()->fetchAll("
    SELECT * FROM email_queue
    $whereSql
    ORDER BY 
        CASE priority 
            WHEN 'high'   THEN 1
            WHEN 'normal' THEN 2
            WHEN 'low'    THEN 3
            ELSE 4
        END,
        created_at DESC
    LIMIT ? OFFSET ?
", $listParams);

// ────────────────────────────────────────────────
// Render page
// ────────────────────────────────────────────────

require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="content-header">
    <h2>
        <i class="fas fa-clock"></i> Email Queue
        <?php if ($stats['pending'] > 0): ?>
        <span class="header-badge"><?= $stats['pending'] ?> pending</span>
        <?php endif; ?>
    </h2>
    <div class="header-actions">
        <?php if ($stats['failed'] > 0): ?>
        <a href="?retry_all=1" class="btn btn-warning" onclick="return confirm('Retry all failed emails?')">
            <i class="fas fa-redo"></i> Retry All Failed
        </a>
        <?php endif; ?>
        <a href="email-templates.php" class="btn btn-outline">
            <i class="fas fa-envelope"></i> Templates
        </a>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible">
    <i class="fas fa-check-circle"></i>
    <?php
    $messages = [
        'retried'     => 'Email queued for retry',
        'retried_all' => 'All failed emails queued for retry',
        'cancelled'   => 'Email cancelled'
    ];
    echo $messages[$_GET['msg']] ?? 'Action completed';
    ?>
    <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
</div>
<?php endif; ?>

<!-- Stats Cards - Responsive Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-details">
            <h3>Pending</h3>
            <span class="stat-value"><?= $stats['pending'] ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
            <h3>Sent</h3>
            <span class="stat-value"><?= $stats['sent'] ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-details">
            <h3>Failed</h3>
            <span class="stat-value"><?= $stats['failed'] ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gray">
            <i class="fas fa-ban"></i>
        </div>
        <div class="stat-details">
            <h3>Cancelled</h3>
            <span class="stat-value"><?= $stats['cancelled'] ?></span>
        </div>
    </div>
</div>

<!-- Filter Tabs - Responsive -->
<div class="filter-tabs-container">
    <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All</a>
        <a href="?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?filter=sent" class="filter-tab <?= $filter === 'sent' ? 'active' : '' ?>">Sent</a>
        <a href="?filter=failed" class="filter-tab <?= $filter === 'failed' ? 'active' : '' ?>">Failed</a>
        <a href="?filter=cancelled" class="filter-tab <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>
</div>

<!-- Mobile Search/Filter Bar (visible on mobile) -->
<div class="mobile-filter-bar">
    <select class="mobile-filter-select" onchange="window.location.href='?filter='+this.value">
        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Emails</option>
        <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="sent" <?= $filter === 'sent' ? 'selected' : '' ?>>Sent</option>
        <option value="failed" <?= $filter === 'failed' ? 'selected' : '' ?>>Failed</option>
        <option value="cancelled" <?= $filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>
</div>

<!-- Queue Table - Responsive -->
<div class="table-responsive">
    <table class="admin-table email-queue-table">
        <thead>
            <tr>
                <th>To</th>
                <th>Subject</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($queue as $item): ?>
            <tr class="status-<?= htmlspecialchars($item['status'] ?? '') ?>">
                <td class="recipient-cell">
                    <div class="recipient-info">
                        <strong><?= htmlspecialchars($item['to_name'] ?: $item['to_email'] ?? '') ?></strong>
                        <span class="recipient-email"><?= htmlspecialchars($item['to_email'] ?? '') ?></span>
                    </div>
                </td>
                <td class="subject-cell">
                    <div class="subject-info">
                        <span class="subject-text"><?= htmlspecialchars($item['subject'] ?? '') ?></span>
                        <?php if (!empty($item['template_key'])): ?>
                        <span class="template-badge"><?= htmlspecialchars($item['template_key']) ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <span class="priority-badge priority-<?= htmlspecialchars($item['priority'] ?? 'normal') ?>">
                        <?= ucfirst(htmlspecialchars($item['priority'] ?? 'normal')) ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge status-<?= htmlspecialchars($item['status'] ?? '') ?>">
                        <?= ucfirst(htmlspecialchars($item['status'] ?? '')) ?>
                    </span>
                </td>
                <td class="attempts-cell">
                    <span class="attempts-count"><?= (int)($item['attempts'] ?? 0) ?>/<?= (int)($item['max_attempts'] ?? 3) ?></span>
                </td>
                <td class="date-cell">
                    <div class="date-info">
                        <span class="date"><?= $item['created_at'] ? date('M d, Y', strtotime($item['created_at'])) : '-' ?></span>
                        <span class="time"><?= $item['created_at'] ? date('h:i A', strtotime($item['created_at'])) : '' ?></span>
                    </div>
                </td>
                <td class="actions-cell">
                    <div class="action-buttons">
                        <?php if (($item['status'] ?? '') === 'failed'): ?>
                        <a href="?retry=<?= (int)$item['id'] ?>" class="action-btn" title="Retry">
                            <i class="fas fa-redo"></i>
                        </a>
                        <?php endif; ?>

                        <?php if (($item['status'] ?? '') === 'pending'): ?>
                        <a href="?cancel=<?= (int)$item['id'] ?>" class="action-btn cancel-btn" title="Cancel"
                           onclick="return confirm('Cancel this email?')">
                            <i class="fas fa-ban"></i>
                        </a>
                        <?php endif; ?>

                        <button class="action-btn info-btn" onclick='viewDetails(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Details">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>
                    <?php if (!empty($item['error_message'])): ?>
                    <div class="error-message-mobile">
                        <small class="error-text"><?= htmlspecialchars($item['error_message']) ?></small>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($item['error_message'])): ?>
            <tr class="error-row">
                <td colspan="7" class="error-cell">
                    <small class="error-text"><?= htmlspecialchars($item['error_message']) ?></small>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>

            <?php if (empty($queue)): ?>
            <tr>
                <td colspan="7" class="text-center">
                    <div class="empty-state">
                        <i class="fas fa-envelope-open"></i>
                        <h4>No emails in queue</h4>
                        <p>The email queue is empty</p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Mobile Card View (visible on small screens) -->
<div class="mobile-cards">
    <?php foreach ($queue as $item): ?>
    <div class="email-card status-<?= htmlspecialchars($item['status'] ?? '') ?>">
        <div class="card-header">
            <div class="recipient">
                <strong><?= htmlspecialchars($item['to_name'] ?: $item['to_email'] ?? '') ?></strong>
                <span class="recipient-email"><?= htmlspecialchars($item['to_email'] ?? '') ?></span>
            </div>
            <span class="status-badge status-<?= htmlspecialchars($item['status'] ?? '') ?>">
                <?= ucfirst(htmlspecialchars($item['status'] ?? '')) ?>
            </span>
        </div>
        
        <div class="card-body">
            <div class="subject-line">
                <strong>Subject:</strong> <?= htmlspecialchars($item['subject'] ?? '') ?>
            </div>
            
            <div class="meta-grid">
                <div class="meta-item">
                    <span class="meta-label">Priority:</span>
                    <span class="priority-badge priority-<?= htmlspecialchars($item['priority'] ?? 'normal') ?>">
                        <?= ucfirst(htmlspecialchars($item['priority'] ?? 'normal')) ?>
                    </span>
                </div>
                
                <div class="meta-item">
                    <span class="meta-label">Attempts:</span>
                    <span><?= (int)($item['attempts'] ?? 0) ?>/<?= (int)($item['max_attempts'] ?? 3) ?></span>
                </div>
                
                <div class="meta-item">
                    <span class="meta-label">Created:</span>
                    <span><?= $item['created_at'] ? date('M d, Y', strtotime($item['created_at'])) : '-' ?></span>
                </div>
                
                <?php if (!empty($item['template_key'])): ?>
                <div class="meta-item">
                    <span class="meta-label">Template:</span>
                    <code><?= htmlspecialchars($item['template_key']) ?></code>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($item['error_message'])): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($item['error_message']) ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="card-footer">
            <div class="action-buttons">
                <?php if (($item['status'] ?? '') === 'failed'): ?>
                <a href="?retry=<?= (int)$item['id'] ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-redo"></i> Retry
                </a>
                <?php endif; ?>

                <?php if (($item['status'] ?? '') === 'pending'): ?>
                <a href="?cancel=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-danger" 
                   onclick="return confirm('Cancel this email?')">
                    <i class="fas fa-ban"></i> Cancel
                </a>
                <?php endif; ?>

                <button class="btn btn-sm btn-outline" onclick='viewDetails(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    <i class="fas fa-info-circle"></i> Details
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (empty($queue)): ?>
    <div class="empty-state">
        <i class="fas fa-envelope-open"></i>
        <h4>No emails in queue</h4>
        <p>The email queue is empty</p>
    </div>
    <?php endif; ?>
</div>

<!-- Pagination - Responsive -->
<?php if ($totalPages > 1): ?>
<div class="pagination-container">
    <div class="pagination-info">
        Page <?= $page ?> of <?= $totalPages ?>
    </div>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?filter=<?= urlencode($filter) ?>&p=<?= $page - 1 ?>" class="page-link" title="Previous page">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>

        <?php
        $range = 2;
        $start = max(1, $page - $range);
        $end   = min($totalPages, $page + $range);
        
        if ($start > 1): ?>
        <a href="?filter=<?= urlencode($filter) ?>&p=1" class="page-link">1</a>
        <?php if ($start > 2): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="?filter=<?= urlencode($filter) ?>&p=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>">
            <?= $i ?>
        </a>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <a href="?filter=<?= urlencode($filter) ?>&p=<?= $totalPages ?>" class="page-link"><?= $totalPages ?></a>
        <?php endif; ?>

        <?php if ($page < $totalPages): ?>
        <a href="?filter=<?= urlencode($filter) ?>&p=<?= $page + 1 ?>" class="page-link" title="Next page">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Details Modal -->
<div class="modal" id="detailsModal" style="display:none;">
    <div class="modal-overlay" onclick="closeDetails()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Email Details</h3>
            <button class="close-modal" onclick="closeDetails()">&times;</button>
        </div>
        <div class="modal-body" id="detailsContent">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeDetails()">Close</button>
        </div>
    </div>
</div>

<style>
/* ========================================
   EMAIL QUEUE PAGE - RESPONSIVE STYLES
   ======================================== */

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
    padding: 4px 10px;
    background: var(--warning);
    color: white;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    margin-left: 10px;
}

/* Stats Grid - Desktop */
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
.stat-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-icon.red { background: rgba(239,68,68,0.1); color: #ef4444; }
.stat-icon.gray { background: rgba(107,114,128,0.1); color: #6b7280; }

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
}

/* Filter Tabs */
.filter-tabs-container {
    background: white;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.filter-tabs {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 8px 16px;
    color: var(--gray-600);
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border: 1px solid var(--gray-200);
}

.filter-tab:hover {
    background: var(--gray-100);
    color: var(--primary);
}

.filter-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Mobile Filter Bar */
.mobile-filter-bar {
    display: none;
    margin-bottom: 20px;
}

.mobile-filter-select {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    font-size: 1rem;
    background: white;
    cursor: pointer;
}

/* Table Styles */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 25px;
    border-radius: 12px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.email-queue-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.email-queue-table th {
    background: var(--gray-100);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.9rem;
    white-space: nowrap;
}

.email-queue-table td {
    padding: 15px;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
}

.email-queue-table tr.status-pending { background: rgba(245,158,11,0.02); }
.email-queue-table tr.status-failed { background: rgba(239,68,68,0.02); }
.email-queue-table tr.status-sent { background: rgba(16,185,129,0.02); }
.email-queue-table tr:hover { background: var(--gray-100); }

/* Recipient Cell */
.recipient-info {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.recipient-email {
    font-size: 0.85rem;
    color: var(--gray-500);
}

/* Subject Cell */
.subject-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.subject-text {
    font-weight: 500;
    color: var(--dark);
}

.template-badge {
    display: inline-block;
    padding: 2px 8px;
    background: var(--gray-200);
    border-radius: 12px;
    font-size: 0.7rem;
    color: var(--gray-700);
    width: fit-content;
}

/* Priority Badge */
.priority-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.priority-high {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}

.priority-normal {
    background: rgba(37,99,235,0.1);
    color: #2563eb;
}

.priority-low {
    background: rgba(16,185,129,0.1);
    color: #10b981;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-pending {
    background: rgba(245,158,11,0.1);
    color: #f59e0b;
}

.status-sent {
    background: rgba(16,185,129,0.1);
    color: #10b981;
}

.status-failed {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
}

.status-cancelled {
    background: rgba(107,114,128,0.1);
    color: #6b7280;
}

/* Attempts Cell */
.attempts-count {
    font-weight: 500;
}

/* Date Cell */
.date-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.date {
    font-weight: 500;
    font-size: 0.9rem;
}

.time {
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.action-btn {
    width: 34px;
    height: 34px;
    background: var(--gray-100);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.action-btn:hover {
    background: var(--primary);
    color: white;
    transform: scale(1.1);
}

.action-btn.cancel-btn:hover {
    background: var(--danger);
}

.action-btn.info-btn:hover {
    background: var(--info);
}

/* Error Message */
.error-text {
    color: var(--danger);
    font-size: 0.75rem;
}

.error-message-mobile {
    margin-top: 5px;
}

.error-row {
    background: rgba(239,68,68,0.05);
}

.error-cell {
    padding-top: 0 !important;
    padding-bottom: 10px !important;
}

/* Mobile Cards */
.mobile-cards {
    display: none;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 25px;
}

.email-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-left: 4px solid transparent;
}

.email-card.status-pending { border-left-color: var(--warning); }
.email-card.status-sent { border-left-color: var(--success); }
.email-card.status-failed { border-left-color: var(--danger); }
.email-card.status-cancelled { border-left-color: var(--gray-500); }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.recipient {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.card-body {
    margin-bottom: 15px;
}

.subject-line {
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
}

.meta-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    font-size: 0.9rem;
}

.meta-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.meta-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
}

.error-message {
    margin-top: 10px;
    padding: 10px;
    background: rgba(239,68,68,0.1);
    border-radius: 6px;
    color: var(--danger);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-footer .action-buttons {
    justify-content: flex-end;
}

.card-footer .btn {
    padding: 8px 12px;
    font-size: 0.85rem;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.85rem;
    border-radius: 6px;
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

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(16,185,129,0.1);
    color: #065f46;
    border: 1px solid rgba(16,185,129,0.2);
}

.alert-success {
    background: rgba(16,185,129,0.1);
    color: #065f46;
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
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translate(-50%, -60%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
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
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}

.close-modal:hover {
    background: var(--danger);
    color: white;
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
    justify-content: flex-end;
}

.loading-spinner {
    text-align: center;
    padding: 30px;
    color: var(--gray-500);
}

.loading-spinner i {
    font-size: 2rem;
    margin-bottom: 10px;
}

/* Details Panel */
.details-panel {
    font-size: 0.95rem;
}

.details-panel p {
    margin-bottom: 8px;
}

.details-panel h4 {
    margin: 20px 0 10px;
    font-size: 1rem;
}

.email-preview {
    background: var(--gray-100);
    padding: 15px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.9rem;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
}

/* ========================================
   RESPONSIVE BREAKPOINTS
   ======================================== */

/* Tablet (768px - 1023px) */
@media (max-width: 1023px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .filter-tabs {
        gap: 3px;
    }
    
    .filter-tab {
        padding: 6px 12px;
        font-size: 0.85rem;
    }
    
    .pagination-container {
        flex-direction: column;
        align-items: center;
    }
}

/* Mobile Landscape (576px - 767px) */
@media (max-width: 767px) {
    .content-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .header-actions {
        width: 100%;
        display: flex;
        gap: 10px;
    }
    
    .header-actions .btn {
        flex: 1;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
    }
    
    .stat-value {
        font-size: 1.2rem;
    }
    
    /* Hide table, show cards */
    .table-responsive {
        display: none;
    }
    
    .mobile-cards {
        display: flex;
    }
    
    .filter-tabs-container {
        display: none;
    }
    
    .mobile-filter-bar {
        display: block;
    }
    
    .pagination {
        gap: 3px;
    }
    
    .page-link {
        padding: 6px 10px;
        min-width: 32px;
        font-size: 0.85rem;
    }
}

/* Mobile Portrait (up to 575px) */
@media (max-width: 575px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .header-actions {
        flex-direction: column;
    }
    
    .header-actions .btn {
        width: 100%;
    }
    
    .email-card {
        padding: 15px;
    }
    
    .meta-grid {
        grid-template-columns: 1fr;
    }
    
    .card-footer .action-buttons {
        flex-direction: column;
    }
    
    .card-footer .btn {
        width: 100%;
    }
    
    .modal-container {
        width: 95%;
        max-height: 90vh;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
    }
}

/* Small Mobile (up to 375px) */
@media (max-width: 375px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .pagination {
        justify-content: center;
    }
    
    .page-link {
        padding: 5px 8px;
        min-width: 30px;
        font-size: 0.8rem;
    }
}

/* Print Styles */
@media print {
    .header-actions,
    .filter-tabs-container,
    .mobile-filter-bar,
    .action-buttons,
    .pagination-container,
    .modal {
        display: none !important;
    }
    
    .table-responsive {
        overflow: visible;
    }
    
    .email-queue-table {
        border: 1px solid #ddd;
    }
}
</style>

<script>
function viewDetails(item) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('detailsContent');
    
    // Safely escape HTML
    const escapeHtml = (text) => {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };
    
    const bodyPreview = item.body ? escapeHtml(item.body.substring(0, 500)) : '';
    const bodySuffix = item.body && item.body.length > 500 ? '...' : '';
    
    let html = `
        <div class="details-panel">
            <p><strong>To:</strong> ${escapeHtml(item.to_name ? item.to_name + ' <' + item.to_email + '>' : item.to_email)}</p>
            <p><strong>Subject:</strong> ${escapeHtml(item.subject || '-')}</p>
            <p><strong>Template:</strong> ${escapeHtml(item.template_key || 'None')}</p>
            <p><strong>Priority:</strong> ${escapeHtml(item.priority || 'normal')}</p>
            <p><strong>Status:</strong> ${escapeHtml(item.status || '')}</p>
            <p><strong>Attempts:</strong> ${parseInt(item.attempts || 0)}/${parseInt(item.max_attempts || 3)}</p>
            <p><strong>Created:</strong> ${escapeHtml(item.created_at || '-')}</p>
            ${item.scheduled_at ? '<p><strong>Scheduled:</strong> ' + escapeHtml(item.scheduled_at) + '</p>' : ''}
            ${item.sent_at ? '<p><strong>Sent:</strong> ' + escapeHtml(item.sent_at) + '</p>' : ''}
            ${item.error_message ? '<p><strong>Error:</strong> ' + escapeHtml(item.error_message) + '</p>' : ''}
            
            <h4>Email Body Preview:</h4>
            <div class="email-preview">${bodyPreview}${bodySuffix}</div>
        </div>
    `;
    
    content.innerHTML = html;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeDetails() {
    document.getElementById('detailsModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking overlay
document.querySelector('.modal-overlay')?.addEventListener('click', closeDetails);

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDetails();
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
    const table = document.querySelector('.table-responsive');
    const cards = document.querySelector('.mobile-cards');
    
    if (width <= 767) {
        if (table) table.style.display = 'none';
        if (cards) cards.style.display = 'flex';
    } else {
        if (table) table.style.display = 'block';
        if (cards) cards.style.display = 'none';
    }
}

// Run on load and resize
window.addEventListener('load', handleResponsive);
window.addEventListener('resize', handleResponsive);
</script>

<?php require_once 'includes/footer.php'; ?>