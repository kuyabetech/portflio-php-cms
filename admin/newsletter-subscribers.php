<?php
// admin/newsletter-subscribers.php
// Newsletter Subscribers Management

// Export function
function exportSubscribers($selected = []) {
    if (!empty($selected)) {
        $ids = implode(',', array_map('intval', $selected));
        $subscribers = db()->fetchAll("SELECT * FROM newsletter_subscribers WHERE id IN ($ids) ORDER BY created_at DESC");
    } else {
        $subscribers = db()->fetchAll("SELECT * FROM newsletter_subscribers ORDER BY created_at DESC");
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Email', 'First Name', 'Last Name', 'Status', 'Source', 'Subscribed Date', 'Unsubscribed Date']);
    
    foreach ($subscribers as $sub) {
        fputcsv($output, [
            $sub['id'],
            $sub['email'],
            $sub['first_name'] ?? '',
            $sub['last_name'] ?? '',
            $sub['status'] ?? 'active',
            $sub['source'] ?? 'website',
            $sub['created_at'],
            $sub['unsubscribed_at'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected = $_POST['selected'] ?? [];
    $action = $_POST['bulk_action'];
    
    if (!empty($selected)) {
        $ids = implode(',', array_map('intval', $selected));
        
        if ($action === 'export') {
            exportSubscribers($selected);
            exit;
        } elseif ($action === 'delete') {
            db()->query("DELETE FROM newsletter_subscribers WHERE id IN ($ids)");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Selected subscribers deleted successfully'];
        } elseif ($action === 'unsubscribe') {
            db()->query("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id IN ($ids)");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Selected subscribers unsubscribed successfully'];
        } elseif ($action === 'activate') {
            db()->query("UPDATE newsletter_subscribers SET status = 'active', unsubscribed_at = NULL WHERE id IN ($ids)");
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Selected subscribers activated successfully'];
        }
        
        header('Location: newsletter.php?type=subscribers');
        exit;
    }
}

// Handle single unsubscribe
if (isset($_GET['unsubscribe'])) {
    $id = (int)$_GET['unsubscribe'];
    db()->update('newsletter_subscribers', [
        'status' => 'unsubscribed',
        'unsubscribed_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Subscriber unsubscribed successfully'];
    header('Location: newsletter.php?type=subscribers');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('newsletter_subscribers', 'id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Subscriber deleted successfully'];
    header('Location: newsletter.php?type=subscribers');
    exit;
}

// Handle import
if (isset($_POST['import_subscribers'])) {
    $emails = explode("\n", $_POST['emails_list']);
    $imported = 0;
    $errors = 0;
    $errorMessages = [];
    
    foreach ($emails as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Parse email and name (format: email, name or email name)
        $parts = preg_split('/[,\s]+/', $line, 2);
        $email = trim($parts[0]);
        $name = isset($parts[1]) ? trim($parts[1]) : '';
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                // Check if exists
                $existing = db()->fetch("SELECT id FROM newsletter_subscribers WHERE email = ?", [$email]);
                if (!$existing) {
                    // Split name into first and last
                    $nameParts = explode(' ', $name, 2);
                    $firstName = $nameParts[0] ?? '';
                    $lastName = $nameParts[1] ?? '';
                    
                    db()->insert('newsletter_subscribers', [
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'source' => 'import',
                        'status' => 'active',
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    $imported++;
                }
            } catch (Exception $e) {
                $errors++;
                $errorMessages[] = "Error importing $email: " . $e->getMessage();
            }
        } else {
            $errors++;
            $errorMessages[] = "Invalid email format: $email";
        }
    }
    
    $message = "Imported $imported subscribers";
    if ($errors > 0) {
        $message .= " with $errors errors";
        error_log(implode("\n", $errorMessages));
    }
    
    $_SESSION['flash'] = ['type' => $errors > 0 ? 'warning' : 'success', 'message' => $message];
    header('Location: newsletter.php?type=subscribers');
    exit;
}

// Get status filter
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get subscribers with pagination
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build where clause safely
$where = "";
$params = [];

if ($status !== 'all') {
    $where = "WHERE status = ?";
    $params[] = $status;
}

// Get total count
$totalSubscribers = db()->fetch("SELECT COUNT(*) as count FROM newsletter_subscribers $where", $params)['count'] ?? 0;
$totalPages = $totalSubscribers > 0 ? ceil($totalSubscribers / $perPage) : 1;

// Get subscribers for current page
$subscribers = db()->fetchAll("
    SELECT * FROM newsletter_subscribers 
    $where
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
", array_merge($params, [$perPage, $offset])) ?: [];

// Get statistics
$stats = db()->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed,
        SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM newsletter_subscribers
") ?? ['total' => 0, 'active' => 0, 'unsubscribed' => 0, 'bounced' => 0, 'today' => 0];

// Ensure all stats are integers
foreach ($stats as $key => $value) {
    $stats[$key] = (int)$value;
}

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <div class="header-left">
        <h1>
            <i class="fas fa-envelope-open-text"></i> 
            Newsletter Subscribers
        </h1>
        <?php if ($stats['today'] > 0): ?>
        <span class="today-badge">+<?php echo $stats['today']; ?> today</span>
        <?php endif; ?>
    </div>
    
    <div class="header-actions">
        <div class="btn-group">
            <a href="newsletter.php?type=subscribers" class="btn <?php echo $type === 'subscribers' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-users"></i> Subscribers
            </a>
            <a href="newsletter.php?type=campaigns" class="btn <?php echo $type === 'campaigns' ? 'btn-primary' : 'btn-outline'; ?>">
                <i class="fas fa-bullhorn"></i> Campaigns
            </a>
        </div>
        <button class="btn btn-primary" onclick="showImportModal()">
            <i class="fas fa-upload"></i> Import
        </button>
        <a href="?export=1" class="btn btn-success">
            <i class="fas fa-download"></i> Export
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

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Active</div>
            <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
            <div class="stat-percent"><?php echo $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100) : 0; ?>%</div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-user-slash"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Unsubscribed</div>
            <div class="stat-value"><?php echo number_format($stats['unsubscribed']); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Bounced</div>
            <div class="stat-value"><?php echo number_format($stats['bounced']); ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-content">
            <div class="stat-label">Today</div>
            <div class="stat-value"><?php echo number_format($stats['today']); ?></div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<div class="filter-tabs">
    <a href="?type=subscribers&status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
        All <span class="count">(<?php echo $stats['total']; ?>)</span>
    </a>
    <a href="?type=subscribers&status=active" class="filter-tab <?php echo $status === 'active' ? 'active' : ''; ?>">
        Active <span class="count">(<?php echo $stats['active']; ?>)</span>
    </a>
    <a href="?type=subscribers&status=unsubscribed" class="filter-tab <?php echo $status === 'unsubscribed' ? 'active' : ''; ?>">
        Unsubscribed <span class="count">(<?php echo $stats['unsubscribed']; ?>)</span>
    </a>
    <a href="?type=subscribers&status=bounced" class="filter-tab <?php echo $status === 'bounced' ? 'active' : ''; ?>">
        Bounced <span class="count">(<?php echo $stats['bounced']; ?>)</span>
    </a>
</div>

<!-- Import Modal -->
<div class="modal" id="importModal">
    <div class="modal-overlay" onclick="hideImportModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-upload"></i> Import Subscribers</h3>
            <button class="close-modal" onclick="hideImportModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="importForm">
                <div class="form-group">
                    <label for="emails_list">Email List (one per line)</label>
                    <textarea id="emails_list" name="emails_list" rows="8" class="form-control" 
                              placeholder="john@example.com, John Doe&#10;jane@example.com, Jane Smith"></textarea>
                    <p class="help-text">Format: email, name (name is optional). One subscriber per line.</p>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="hideImportModal()">Cancel</button>
            <button type="submit" form="importForm" name="import_subscribers" class="btn btn-primary">
                <i class="fas fa-upload"></i> Import
            </button>
        </div>
    </div>
</div>

<!-- Bulk Actions Form -->
<form method="POST" id="bulkForm">
    <div class="bulk-actions">
        <div class="bulk-select-wrapper">
            <select name="bulk_action" class="bulk-select">
                <option value="">Bulk Actions</option>
                <option value="export">📥 Export Selected</option>
                <option value="activate">✅ Activate</option>
                <option value="unsubscribe">📧 Unsubscribe</option>
                <option value="delete">🗑️ Delete</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmBulkAction()">
                Apply
            </button>
        </div>
        <div class="selected-count" id="selectedCount">0 selected</div>
    </div>

    <!-- Subscribers Table -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th width="30">
                        <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                    </th>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th>Subscribed</th>
                    <th width="100">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscribers as $subscriber): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="selected[]" value="<?php echo $subscriber['id']; ?>" class="select-item" onchange="updateSelectedCount()">
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($subscriber['email']); ?></strong>
                        <?php if (!empty($subscriber['bounce_reason']) && $subscriber['status'] === 'bounced'): ?>
                        <div class="bounce-reason"><?php echo htmlspecialchars($subscriber['bounce_reason']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $firstName = $subscriber['first_name'] ?? '';
                        $lastName = $subscriber['last_name'] ?? '';
                        $fullName = trim($firstName . ' ' . $lastName);
                        echo $fullName ? htmlspecialchars($fullName) : '<span class="text-muted">—</span>';
                        ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $subscriber['status'] ?? 'active'; ?>">
                            <?php echo ucfirst($subscriber['status'] ?? 'active'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="source-badge"><?php echo ucfirst($subscriber['source'] ?? 'website'); ?></span>
                    </td>
                    <td>
                        <?php echo date('M d, Y', strtotime($subscriber['created_at'])); ?>
                        <?php if (!empty($subscriber['unsubscribed_at'])): ?>
                        <br><small class="text-muted">Unsubscribed: <?php echo date('M d, Y', strtotime($subscriber['unsubscribed_at'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if (($subscriber['status'] ?? 'active') === 'active'): ?>
                            <a href="?type=subscribers&unsubscribe=<?php echo $subscriber['id']; ?>" 
                               class="action-btn" title="Unsubscribe"
                               onclick="return confirm('Unsubscribe this user?')">
                                <i class="fas fa-user-slash"></i>
                            </a>
                            <?php endif; ?>
                            <a href="?type=subscribers&delete=<?php echo $subscriber['id']; ?>" 
                               class="action-btn delete-btn"
                               onclick="return confirm('Delete this subscriber?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($subscribers)): ?>
                <tr>
                    <td colspan="7" class="empty-state">
                        <i class="fas fa-envelope-open"></i>
                        <h3>No subscribers found</h3>
                        <p>Add a subscription form to your website to start building your list.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?type=subscribers&status=<?php echo $status; ?>&p=<?php echo $page - 1; ?>" class="page-link" title="Previous">
        <i class="fas fa-chevron-left"></i>
    </a>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?type=subscribers&status=<?php echo $status; ?>&p=<?php echo $i; ?>" 
       class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
        <?php echo $i; ?>
    </a>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
    <a href="?type=subscribers&status=<?php echo $status; ?>&p=<?php echo $page + 1; ?>" class="page-link" title="Next">
        <i class="fas fa-chevron-right"></i>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
/* ========================================
   NEWSLETTER SUBSCRIBERS STYLES
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
    --white: #ffffff;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.header-left h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-left h1 i {
    color: var(--primary);
}

.today-badge {
    background: var(--success);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-group {
    display: flex;
    gap: 5px;
    background: var(--gray-100);
    padding: 5px;
    border-radius: 8px;
}

.btn-group .btn {
    border: none;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
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

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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

.stat-icon.blue { background: rgba(37,99,235,0.1); color: var(--primary); }
.stat-icon.green { background: rgba(16,185,129,0.1); color: var(--success); }
.stat-icon.orange { background: rgba(245,158,11,0.1); color: var(--warning); }
.stat-icon.red { background: rgba(239,68,68,0.1); color: var(--danger); }
.stat-icon.purple { background: rgba(124,58,237,0.1); color: #7c3aed; }

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 12px;
    color: var(--gray-500);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.stat-percent {
    font-size: 12px;
    color: var(--gray-500);
    margin-top: 2px;
}

/* Filter Tabs */
.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 8px 20px;
    color: var(--gray-600);
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    background: white;
    border: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 6px;
}

.filter-tab .count {
    font-size: 12px;
    color: var(--gray-500);
}

.filter-tab:hover,
.filter-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.filter-tab.active .count {
    color: white;
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
    max-width: 500px;
    max-height: 90vh;
    background: white;
    border-radius: 16px;
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
    font-size: 24px;
    cursor: pointer;
    color: var(--gray-500);
}

.modal-body {
    padding: 20px;
    max-height: calc(90vh - 140px);
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Bulk Actions */
.bulk-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 15px;
    background: var(--gray-100);
    border-radius: 12px;
    flex-wrap: wrap;
    gap: 15px;
}

.bulk-select-wrapper {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.bulk-select {
    padding: 10px 16px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    min-width: 180px;
    font-size: 14px;
    background: white;
}

.selected-count {
    font-size: 14px;
    color: var(--gray-600);
    font-weight: 500;
}

/* Table */
.table-responsive {
    overflow-x: auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.data-table th {
    background: var(--gray-100);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 15px;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
    font-size: 14px;
}

.data-table tr:hover td {
    background: var(--gray-50);
}

.bounce-reason {
    font-size: 11px;
    color: var(--danger);
    margin-top: 3px;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active { background: rgba(16,185,129,0.1); color: var(--success); }
.status-badge.unsubscribed { background: rgba(107,114,128,0.1); color: var(--gray-600); }
.status-badge.bounced { background: rgba(239,68,68,0.1); color: var(--danger); }

.source-badge {
    display: inline-block;
    padding: 2px 8px;
    background: var(--gray-200);
    border-radius: 12px;
    font-size: 11px;
    color: var(--gray-700);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
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

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 25px;
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

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideDown 0.3s ease;
}

.alert-success {
    background: rgba(16,185,129,0.1);
    color: #065f46;
    border: 1px solid rgba(16,185,129,0.2);
}

.alert-warning {
    background: rgba(245,158,11,0.1);
    color: #92400e;
    border: 1px solid rgba(245,158,11,0.2);
}

.alert-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
}

/* Form Elements */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--dark);
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

.help-text {
    font-size: 12px;
    color: var(--gray-500);
    margin-top: 5px;
}

.text-muted {
    color: var(--gray-500);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 48px;
    color: var(--gray-300);
    margin-bottom: 15px;
}

.empty-state h3 {
    color: var(--gray-600);
    margin-bottom: 5px;
}

.empty-state p {
    color: var(--gray-500);
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .content-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .btn-group {
        width: 100%;
    }
    
    .btn-group .btn {
        flex: 1;
    }
    
    .bulk-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .bulk-select-wrapper {
        width: 100%;
    }
    
    .bulk-select {
        flex: 1;
    }
    
    .modal-container {
        width: 95%;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-tabs {
        flex-direction: column;
    }
    
    .filter-tab {
        width: 100%;
        justify-content: center;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<script>
let selectedCount = 0;

function showImportModal() {
    document.getElementById('importModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function hideImportModal() {
    document.getElementById('importModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.select-item');
    checkboxes.forEach(cb => {
        cb.checked = source.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.select-item:checked');
    selectedCount = checkboxes.length;
    document.getElementById('selectedCount').textContent = selectedCount + ' selected';
    
    // Update select all checkbox
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        const total = document.querySelectorAll('.select-item').length;
        selectAll.checked = selectedCount === total;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < total;
    }
}

function confirmBulkAction() {
    const action = document.querySelector('select[name="bulk_action"]').value;
    
    if (!action) {
        alert('Please select an action');
        return false;
    }
    
    if (selectedCount === 0) {
        alert('Please select at least one subscriber');
        return false;
    }
    
    if (action === 'delete') {
        return confirm('Are you sure you want to delete the selected subscribers?');
    }
    
    return true;
}

// Close modal when clicking overlay
document.addEventListener('click', function(e) {
    const modal = document.getElementById('importModal');
    if (e.target.classList.contains('modal-overlay')) {
        hideImportModal();
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});
</script>

<?php require_once 'includes/footer.php'; ?>