<?php
// admin/newsletter-subscribers.php
// Newsletter Subscribers Management

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
            $msg = 'bulk_deleted';
        } elseif ($action === 'unsubscribe') {
            db()->query("UPDATE newsletter_subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id IN ($ids)");
            $msg = 'bulk_unsubscribed';
        } elseif ($action === 'activate') {
            db()->query("UPDATE newsletter_subscribers SET status = 'active', unsubscribed_at = NULL WHERE id IN ($ids)");
            $msg = 'bulk_activated';
        }
        
        header("Location: newsletter.php?type=subscribers&msg=$msg");
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
    header('Location: newsletter.php?type=subscribers&msg=unsubscribed');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('newsletter_subscribers', 'id = ?', [$id]);
    header('Location: newsletter.php?type=subscribers&msg=deleted');
    exit;
}

// Handle import
if (isset($_POST['import_subscribers'])) {
    $emails = explode("\n", $_POST['emails_list']);
    $imported = 0;
    $errors = 0;
    
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
                    db()->insert('newsletter_subscribers', [
                        'email' => $email,
                        'first_name' => $name,
                        'source' => 'import',
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    $imported++;
                }
            } catch (Exception $e) {
                $errors++;
            }
        } else {
            $errors++;
        }
    }
    
    header("Location: newsletter.php?type=subscribers&msg=imported&imported=$imported&errors=$errors");
    exit;
}

// Get subscribers with pagination
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$status = $_GET['status'] ?? 'all';
$where = "";
if ($status !== 'all') {
    $where = "WHERE status = '" . db()->getConnection()->quote($status) . "'";
}

$totalSubscribers = db()->fetch("SELECT COUNT(*) as count FROM newsletter_subscribers $where")['count'] ?? 0;
$totalPages = ceil($totalSubscribers / $perPage);

$subscribers = db()->fetchAll("
    SELECT * FROM newsletter_subscribers 
    $where
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
", [$perPage, $offset]);

// Get statistics
$stats = db()->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed,
        SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
        SUM(CASE DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
    FROM newsletter_subscribers
") ?? ['total' => 0, 'active' => 0, 'unsubscribed' => 0, 'bounced' => 0, 'today' => 0];

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>Newsletter Subscribers</h2>
    <div class="header-actions">
        <div class="filter-tabs">
            <a href="?type=subscribers&status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">All (<?php echo $stats['total']; ?>)</a>
            <a href="?type=subscribers&status=active" class="filter-tab <?php echo $status === 'active' ? 'active' : ''; ?>">Active (<?php echo $stats['active']; ?>)</a>
            <a href="?type=subscribers&status=unsubscribed" class="filter-tab <?php echo $status === 'unsubscribed' ? 'active' : ''; ?>">Unsubscribed (<?php echo $stats['unsubscribed']; ?>)</a>
            <a href="?type=subscribers&status=bounced" class="filter-tab <?php echo $status === 'bounced' ? 'active' : ''; ?>">Bounced (<?php echo $stats['bounced']; ?>)</a>
        </div>
        <button class="btn btn-primary" onclick="showImportForm()">
            <i class="fas fa-upload"></i>
            Import
        </button>
        <a href="?type=campaigns&action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Campaign
        </a>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'deleted') echo 'Subscriber deleted successfully!';
        if ($_GET['msg'] === 'unsubscribed') echo 'Subscriber unsubscribed successfully!';
        if ($_GET['msg'] === 'bulk_deleted') echo 'Selected subscribers deleted successfully!';
        if ($_GET['msg'] === 'bulk_unsubscribed') echo 'Selected subscribers unsubscribed successfully!';
        if ($_GET['msg'] === 'bulk_activated') echo 'Selected subscribers activated successfully!';
        if ($_GET['msg'] === 'imported') {
            echo "Imported {$_GET['imported']} subscribers with {$_GET['errors']} errors.";
        }
        ?>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="stats-mini-grid">
    <div class="stat-mini-card">
        <div class="stat-mini-icon blue">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-mini-content">
            <h3>Total</h3>
            <span class="stat-mini-value"><?php echo $stats['total']; ?></span>
        </div>
    </div>
    
    <div class="stat-mini-card">
        <div class="stat-mini-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-mini-content">
            <h3>Active</h3>
            <span class="stat-mini-value"><?php echo $stats['active']; ?></span>
        </div>
    </div>
    
    <div class="stat-mini-card">
        <div class="stat-mini-icon orange">
            <i class="fas fa-user-slash"></i>
        </div>
        <div class="stat-mini-content">
            <h3>Unsubscribed</h3>
            <span class="stat-mini-value"><?php echo $stats['unsubscribed']; ?></span>
        </div>
    </div>
    
    <div class="stat-mini-card">
        <div class="stat-mini-icon red">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-mini-content">
            <h3>Bounced</h3>
            <span class="stat-mini-value"><?php echo $stats['bounced']; ?></span>
        </div>
    </div>
    
    <div class-="stat-mini-card">
        <div class="stat-mini-icon purple">
            <i class="fas fa-calendar-day"></i>
        </div>
        <div class="stat-mini-content">
            <h3>Today</h3>
            <span class="stat-mini-value"><?php echo $stats['today']; ?></span>
        </div>
    </div>
</div>

<!-- Import Form -->
<div class="form-container" id="importForm" style="display: none;">
    <h3>Import Subscribers</h3>
    <form method="POST" class="admin-form">
        <div class="form-group">
            <label for="emails_list">Email List</label>
            <textarea id="emails_list" name="emails_list" rows="10" class="form-control" 
                      placeholder="email1@example.com, John Doe&#10;email2@example.com, Jane Smith"></textarea>
            <small>Format: email, name (one per line). Name is optional.</small>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="import_subscribers" class="btn btn-primary">
                <i class="fas fa-upload"></i>
                Import Subscribers
            </button>
            <button type="button" class="btn btn-outline" onclick="hideImportForm()">Cancel</button>
        </div>
    </form>
</div>

<!-- Bulk Actions Form -->
<form method="POST" id="bulkForm">
    <div class="bulk-actions">
        <select name="bulk_action" class="bulk-select">
            <option value="">Bulk Actions</option>
            <option value="export">Export Selected</option>
            <option value="activate">Activate</option>
            <option value="unsubscribe">Unsubscribe</option>
            <option value="delete">Delete</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm" onclick="return confirmBulkAction()">Apply</button>
    </div>

    <!-- Subscribers Table -->
    <div class="table-responsive">
        <table class="admin-table">
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
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subscribers as $subscriber): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="selected[]" value="<?php echo $subscriber['id']; ?>" class="select-item">
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($subscriber['email']); ?></strong>
                        <?php if ($subscriber['status'] === 'bounced'): ?>
                        <br><small class="text-danger"><?php echo htmlspecialchars($subscriber['bounce_reason']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $name = trim($subscriber['first_name'] . ' ' . $subscriber['last_name']);
                        echo $name ? htmlspecialchars($name) : '-';
                        ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $subscriber['status']; ?>">
                            <?php echo ucfirst($subscriber['status']); ?>
                        </span>
                    </td>
                    <td><?php echo ucfirst($subscriber['source']); ?></td>
                    <td>
                        <?php echo date('M d, Y', strtotime($subscriber['created_at'])); ?>
                        <?php if ($subscriber['unsubscribed_at']): ?>
                        <br><small>Unsubscribed: <?php echo date('M d, Y', strtotime($subscriber['unsubscribed_at'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if ($subscriber['status'] === 'active'): ?>
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
                    <td colspan="7" class="text-center">No subscribers found</td>
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
    <a href="?type=subscribers&status=<?php echo $status; ?>&p=<?php echo $page - 1; ?>" class="page-link">
        <i class="fas fa-chevron-left"></i>
    </a>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?type=subscribers&status=<?php echo $status; ?>&p=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
        <?php echo $i; ?>
    </a>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
    <a href="?type=subscribers&status=<?php echo $status; ?>&p=<?php echo $page + 1; ?>" class="page-link">
        <i class="fas fa-chevron-right"></i>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-tabs {
    display: flex;
    gap: 5px;
    background: var(--gray-100);
    padding: 4px;
    border-radius: 8px;
}

.filter-tab {
    padding: 6px 12px;
    color: var(--gray-700);
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.filter-tab:hover,
.filter-tab.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stats-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-mini-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-mini-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-mini-icon.blue {
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
}

.stat-mini-icon.green {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.stat-mini-icon.orange {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.stat-mini-icon.red {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.stat-mini-icon.purple {
    background: rgba(124, 58, 237, 0.1);
    color: #7c3aed;
}

.stat-mini-content h3 {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-bottom: 3px;
}

.stat-mini-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
}

.bulk-actions {
    margin-bottom: 15px;
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

.action-buttons {
    display: flex;
    gap: 5px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 20px;
}

.page-link {
    padding: 8px 12px;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    color: var(--gray-700);
    text-decoration: none;
    transition: all 0.3s ease;
}

.page-link:hover,
.page-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.text-danger {
    color: #ef4444;
}
</style>

<script>
function showImportForm() {
    document.getElementById('importForm').style.display = 'block';
}

function hideImportForm() {
    document.getElementById('importForm').style.display = 'none';
}

function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.select-item');
    checkboxes.forEach(cb => cb.checked = source.checked);
}

function confirmBulkAction() {
    const action = document.querySelector('select[name="bulk_action"]').value;
    const selected = document.querySelectorAll('.select-item:checked').length;
    
    if (!action) {
        alert('Please select an action');
        return false;
    }
    
    if (selected === 0) {
        alert('Please select at least one subscriber');
        return false;
    }
    
    if (action === 'delete') {
        return confirm('Are you sure you want to delete the selected subscribers?');
    }
    
    return true;
}

function exportSubscribers(ids) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export-subscribers.php';
    
    ids.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>