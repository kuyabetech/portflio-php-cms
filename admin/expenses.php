<?php
// admin/expenses.php
// Project Expense Tracking

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Expense Tracking';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Expenses']
];

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get receipt file before deleting
    $expense = db()->fetch("SELECT receipt_file FROM project_expenses WHERE id = ?", [$id]);
    if ($expense && $expense['receipt_file']) {
        $filePath = UPLOAD_PATH . 'expenses/' . $expense['receipt_file'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    db()->delete('project_expenses', 'id = ?', [$id]);
    header('Location: expenses.php?msg=deleted');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_expense'])) {
    $data = [
        'project_id' => (int)$_POST['project_id'] ?: null,
        'expense_date' => $_POST['expense_date'],
        'category' => $_POST['category'],
        'description' => sanitize($_POST['description']),
        'amount' => (float)$_POST['amount'],
        'tax_amount' => (float)$_POST['tax_amount'],
        'billable' => isset($_POST['billable']) ? 1 : 0,
        'status' => $_POST['status'],
        'notes' => sanitize($_POST['notes']),
        'created_by' => $_SESSION['user_id']
    ];
    
    // Handle receipt upload
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['receipt'], 'expenses/');
        if (isset($upload['success'])) {
            // Delete old receipt if updating
            if (!empty($_POST['id'])) {
                $old = db()->fetch("SELECT receipt_file FROM project_expenses WHERE id = ?", [$_POST['id']]);
                if ($old && $old['receipt_file']) {
                    $oldPath = UPLOAD_PATH . 'expenses/' . $old['receipt_file'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
            }
            $data['receipt_file'] = $upload['filename'];
        }
    }
    
    if (!empty($_POST['id'])) {
        db()->update('project_expenses', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('project_expenses', $data);
        $msg = 'created';
    }
    
    header("Location: expenses.php?msg=$msg");
    exit;
}

// Get expense for editing
$expense = null;
if ($id > 0 && $action === 'edit') {
    $expense = db()->fetch("SELECT * FROM project_expenses WHERE id = ?", [$id]);
}

// Get all expenses
$expenses = db()->fetchAll("
    SELECT e.*, p.title as project_title 
    FROM project_expenses e
    LEFT JOIN projects p ON e.project_id = p.id
    ORDER BY e.expense_date DESC
");

// Get projects for dropdown
$projects = db()->fetchAll("SELECT id, title FROM projects ORDER BY created_at DESC");

// Get total expenses by category
$categoryTotals = db()->fetchAll("
    SELECT category, SUM(amount + tax_amount) as total
    FROM project_expenses
    GROUP BY category
    ORDER BY total DESC
");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><?php echo $action === 'edit' ? 'Edit Expense' : ($action === 'add' ? 'Add Expense' : 'Expense Tracking'); ?></h2>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add Expense
        </a>
        <a href="?action=report" class="btn btn-outline">
            <i class="fas fa-chart-bar"></i>
            View Report
        </a>
        <?php else: ?>
        <a href="expenses.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Expenses
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Expense recorded successfully!';
        if ($_GET['msg'] === 'updated') echo 'Expense updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Expense deleted successfully!';
        ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Category Summary -->
    <div class="category-summary">
        <h3>Expenses by Category</h3>
        <div class="category-grid">
            <?php foreach ($categoryTotals as $cat): ?>
            <div class="category-card">
                <div class="category-name"><?php echo ucfirst($cat['category']); ?></div>
                <div class="category-total">$<?php echo number_format($cat['total'], 2); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Project</th>
                    <th>Amount</th>
                    <th>Tax</th>
                    <th>Total</th>
                    <th>Billable</th>
                    <th>Status</th>
                    <th>Receipt</th>
                    <th width="100">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $exp): 
                    $total = $exp['amount'] + $exp['tax_amount'];
                ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($exp['expense_date'])); ?></td>
                    <td><span class="category-badge"><?php echo ucfirst($exp['category']); ?></span></td>
                    <td><?php echo htmlspecialchars($exp['description']); ?></td>
                    <td><?php echo $exp['project_title'] ?: '-'; ?></td>
                    <td>$<?php echo number_format($exp['amount'], 2); ?></td>
                    <td>$<?php echo number_format($exp['tax_amount'], 2); ?></td>
                    <td><strong>$<?php echo number_format($total, 2); ?></strong></td>
                    <td>
                        <?php if ($exp['billable']): ?>
                        <span class="badge success">Yes</span>
                        <?php else: ?>
                        <span class="badge">No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $exp['status']; ?>">
                            <?php echo ucfirst($exp['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($exp['receipt_file']): ?>
                        <a href="<?php echo UPLOAD_URL . 'expenses/' . $exp['receipt_file']; ?>" target="_blank" class="action-btn">
                            <i class="fas fa-file"></i>
                        </a>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="?action=edit&id=<?php echo $exp['id']; ?>" class="action-btn" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?delete=<?php echo $exp['id']; ?>" class="action-btn delete-btn" 
                               onclick="return confirm('Delete this expense?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($expenses)): ?>
                <tr>
                    <td colspan="11" class="text-center">No expenses recorded</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Expense Form -->
    <div class="form-container" style="max-width: 600px;">
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <?php if ($expense): ?>
            <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="expense_date">Expense Date *</label>
                    <input type="date" id="expense_date" name="expense_date" required 
                           value="<?php echo $expense['expense_date'] ?? date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="category">Category *</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <option value="software" <?php echo ($expense['category'] ?? '') === 'software' ? 'selected' : ''; ?>>Software & Subscriptions</option>
                        <option value="hardware" <?php echo ($expense['category'] ?? '') === 'hardware' ? 'selected' : ''; ?>>Hardware</option>
                        <option value="hosting" <?php echo ($expense['category'] ?? '') === 'hosting' ? 'selected' : ''; ?>>Hosting & Domain</option>
                        <option value="marketing" <?php echo ($expense['category'] ?? '') === 'marketing' ? 'selected' : ''; ?>>Marketing</option>
                        <option value="travel" <?php echo ($expense['category'] ?? '') === 'travel' ? 'selected' : ''; ?>>Travel</option>
                        <option value="office" <?php echo ($expense['category'] ?? '') === 'office' ? 'selected' : ''; ?>>Office Supplies</option>
                        <option value="contractor" <?php echo ($expense['category'] ?? '') === 'contractor' ? 'selected' : ''; ?>>Contractors</option>
                        <option value="other" <?php echo ($expense['category'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" rows="2" required><?php echo $expense['description'] ?? ''; ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="amount">Amount *</label>
                    <input type="number" id="amount" name="amount" required step="0.01" min="0"
                           value="<?php echo $expense['amount'] ?? 0; ?>">
                </div>
                
                <div class="form-group">
                    <label for="tax_amount">Tax Amount</label>
                    <input type="number" id="tax_amount" name="tax_amount" step="0.01" min="0"
                           value="<?php echo $expense['tax_amount'] ?? 0; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="project_id">Related Project</label>
                    <select id="project_id" name="project_id">
                        <option value="">-- None --</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>"
                            <?php echo ($expense['project_id'] ?? '') == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="pending" <?php echo ($expense['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo ($expense['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo ($expense['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="billable" value="1" 
                           <?php echo ($expense['billable'] ?? 1) ? 'checked' : ''; ?>>
                    Billable to client
                </label>
            </div>
            
            <div class="form-group">
                <label for="receipt">Receipt/Invoice</label>
                <input type="file" id="receipt" name="receipt" accept="image/*,.pdf">
                <?php if ($expense && $expense['receipt_file']): ?>
                <div class="current-file">
                    <a href="<?php echo UPLOAD_URL . 'expenses/' . $expense['receipt_file']; ?>" target="_blank">
                        View Current Receipt
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="3"><?php echo $expense['notes'] ?? ''; ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_expense" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $expense ? 'Update Expense' : 'Save Expense'; ?>
                </button>
                <a href="expenses.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
.category-summary {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.category-summary h3 {
    margin-bottom: 15px;
    font-size: 1rem;
}

.category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.category-card {
    background: var(--gray-100);
    padding: 15px;
    border-radius: 8px;
    text-align: center;
}

.category-name {
    font-size: 0.9rem;
    color: var(--gray-600);
    margin-bottom: 5px;
}

.category-total {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary);
}

.category-badge {
    display: inline-block;
    padding: 4px 8px;
    background: var(--gray-200);
    border-radius: 4px;
    font-size: 0.8rem;
}

.badge.success {
    background: rgba(16,185,129,0.1);
    color: #10b981;
    padding: 2px 8px;
    border-radius: 12px;
}

.current-file {
    margin-top: 10px;
    padding: 10px;
    background: var(--gray-100);
    border-radius: 4px;
}

.current-file a {
    color: var(--primary);
    text-decoration: none;
}

.current-file a:hover {
    text-decoration: underline;
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>