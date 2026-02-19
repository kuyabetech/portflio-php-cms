<?php
// admin/email-queue.php
// Email Queue Management - SAFE VERSION with prepared statements

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
// Get statistics (no variables → safe)
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

<div class="content-header">
    <h2>Email Queue</h2>
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

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success">
    <?php
    $messages = [
        'retried'     => 'Email queued for retry',
        'retried_all' => 'All failed emails queued for retry',
        'cancelled'   => 'Email cancelled'
    ];
    echo $messages[$_GET['msg']] ?? 'Action completed';
    ?>
</div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-mini-grid">
    <div class="stat-mini-card">
        <div class="stat-mini-icon blue"><i class="fas fa-clock"></i></div>
        <div class="stat-mini-content">
            <h3>Pending</h3>
            <span class="stat-mini-value"><?= $stats['pending'] ?></span>
        </div>
    </div>
    <div class="stat-mini-card">
        <div class="stat-mini-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-mini-content">
            <h3>Sent</h3>
            <span class="stat-mini-value"><?= $stats['sent'] ?></span>
        </div>
    </div>
    <div class="stat-mini-card">
        <div class="stat-mini-icon red"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-mini-content">
            <h3>Failed</h3>
            <span class="stat-mini-value"><?= $stats['failed'] ?></span>
        </div>
    </div>
    <div class="stat-mini-card">
        <div class="stat-mini-icon gray"><i class="fas fa-ban"></i></div>
        <div class="stat-mini-content">
            <h3>Cancelled</h3>
            <span class="stat-mini-value"><?= $stats['cancelled'] ?></span>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="filter-tabs">
    <a href="?filter=all"       class="filter-tab <?= $filter === 'all'       ? 'active' : '' ?>">All</a>
    <a href="?filter=pending"   class="filter-tab <?= $filter === 'pending'   ? 'active' : '' ?>">Pending</a>
    <a href="?filter=sent"      class="filter-tab <?= $filter === 'sent'      ? 'active' : '' ?>">Sent</a>
    <a href="?filter=failed"    class="filter-tab <?= $filter === 'failed'    ? 'active' : '' ?>">Failed</a>
    <a href="?filter=cancelled" class="filter-tab <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
</div>

<!-- Queue Table -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>To</th>
                <th>Subject</th>
                <th>Template</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Created</th>
                <th>Scheduled</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($queue as $item): ?>
            <tr class="<?= htmlspecialchars($item['status'] ?? '') ?>">
                <td>
                    <strong><?= htmlspecialchars($item['to_name'] ?: $item['to_email'] ?? '') ?></strong><br>
                    <small><?= htmlspecialchars($item['to_email'] ?? '') ?></small>
                </td>
                <td><?= htmlspecialchars($item['subject'] ?? '') ?></td>
                <td>
                    <?php if (!empty($item['template_key'])): ?>
                    <code><?= htmlspecialchars($item['template_key']) ?></code>
                    <?php else: ?>
                    —
                    <?php endif; ?>
                </td>
                <td>
                    <span class="priority-badge <?= htmlspecialchars($item['priority'] ?? 'normal') ?>">
                        <?= ucfirst(htmlspecialchars($item['priority'] ?? 'normal')) ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge <?= htmlspecialchars($item['status'] ?? '') ?>">
                        <?= ucfirst(htmlspecialchars($item['status'] ?? '')) ?>
                    </span>
                </td>
                <td><?= (int)($item['attempts'] ?? 0) ?>/<?= (int)($item['max_attempts'] ?? 0) ?></td>
                <td><?= $item['created_at'] ? date('M d, H:i', strtotime($item['created_at'])) : '-' ?></td>
                <td>
                    <?php if ($item['scheduled_at']): ?>
                    <?= date('M d, H:i', strtotime($item['scheduled_at'])) ?>
                    <?php else: ?>
                    Now
                    <?php endif; ?>
                </td>
                <td>
                    <div class="action-buttons">
                        <?php if (($item['status'] ?? '') === 'failed'): ?>
                        <a href="?retry=<?= (int)$item['id'] ?>" class="action-btn" title="Retry">
                            <i class="fas fa-redo"></i>
                        </a>
                        <?php endif; ?>

                        <?php if (($item['status'] ?? '') === 'pending'): ?>
                        <a href="?cancel=<?= (int)$item['id'] ?>" class="action-btn" title="Cancel"
                           onclick="return confirm('Cancel this email?')">
                            <i class="fas fa-ban"></i>
                        </a>
                        <?php endif; ?>

                        <button class="action-btn" onclick='viewDetails(<?= json_encode($item, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Details">
                            <i class="fas fa-info-circle"></i>
                        </button>
                    </div>

                    <?php if (!empty($item['error_message'])): ?>
                    <small class="error-text"><?= htmlspecialchars($item['error_message']) ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (empty($queue)): ?>
            <tr>
                <td colspan="9" class="text-center">No emails in queue</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?filter=<?= urlencode($filter) ?>&p=<?= $page - 1 ?>" class="page-link">
        <i class="fas fa-chevron-left"></i>
    </a>
    <?php endif; ?>

    <?php
    $range = 2;
    $start = max(1, $page - $range);
    $end   = min($totalPages, $page + $range);
    for ($i = $start; $i <= $end; $i++): ?>
    <a href="?filter=<?= urlencode($filter) ?>&p=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>">
        <?= $i ?>
    </a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
    <a href="?filter=<?= urlencode($filter) ?>&p=<?= $page + 1 ?>" class="page-link">
        <i class="fas fa-chevron-right"></i>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Details Modal -->
<div class="modal" id="detailsModal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Email Details</h3>
                <button class="close-modal" onclick="closeDetails()">×</button>
            </div>
            <div class="modal-body" id="detailsContent"></div>
        </div>
    </div>
</div>

<style>
.stats-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-mini-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-mini-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

.stat-mini-icon.blue { background: rgba(37,99,235,0.1); color: #2563eb; }
.stat-mini-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-mini-icon.red { background: rgba(239,68,68,0.1); color: #ef4444; }
.stat-mini-icon.gray { background: rgba(107,114,128,0.1); color: #6b7280; }

.stat-mini-content h3 {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-bottom: 2px;
}

.stat-mini-value {
    font-size: 1.3rem;
    font-weight: 600;
}

.filter-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    background: var(--gray-100);
    padding: 4px;
    border-radius: 8px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 6px 12px;
    color: var(--gray-600);
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.9rem;
}

.filter-tab:hover,
.filter-tab.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.priority-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
}

.priority-badge.high { background: rgba(239,68,68,0.1); color: #ef4444; }
.priority-badge.normal { background: rgba(37,99,235,0.1); color: #2563eb; }
.priority-badge.low { background: rgba(16,185,129,0.1); color: #10b981; }

.error-text {
    display: block;
    color: #ef4444;
    font-size: 0.75rem;
    margin-top: 5px;
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
}
</style>

<script>
function viewDetails(item) {
    const modal = document.getElementById('detailsModal');
    const content = document.getElementById('detailsContent');
    
    let html = `
        <div class="details-panel">
            <p><strong>To:</strong> ${item.to_name ? item.to_name + ' <' + item.to_email + '>' : item.to_email}</p>
            <p><strong>Subject:</strong> ${item.subject || '-'}</p>
            <p><strong>Template:</strong> ${item.template_key || 'None'}</p>
            <p><strong>Priority:</strong> ${item.priority || 'normal'}</p>
            <p><strong>Status:</strong> ${item.status || ''}</p>
            <p><strong>Attempts:</strong> \( {item.attempts || 0}/ \){item.max_attempts || 0}</p>
            <p><strong>Created:</strong> ${item.created_at || '-'}</p>
            ${item.scheduled_at ? '<p><strong>Scheduled:</strong> ' + item.scheduled_at + '</p>' : ''}
            ${item.sent_at ? '<p><strong>Sent:</strong> ' + item.sent_at + '</p>' : ''}
            ${item.error_message ? '<p><strong>Error:</strong> ' + item.error_message + '</p>' : ''}
            
            <h4>Email Body Preview:</h4>
            <div class="email-preview">\( {(item.body || '').substring(0, 500)} \){(item.body || '').length > 500 ? '...' : ''}</div>
        </div>
    `;
    
    content.innerHTML = html;
    modal.style.display = 'block';
}

function closeDetails() {
    document.getElementById('detailsModal').style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>