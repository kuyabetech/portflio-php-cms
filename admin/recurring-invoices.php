<?php
// admin/recurring-invoices.php
// Recurring Invoices Management

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Recurring Invoices';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Invoices', 'url' => 'invoices.php'],
    ['title' => 'Recurring Invoices']
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recurring'])) {
    $template = [
        'items' => json_decode($_POST['items'], true),
        'tax_rate' => $_POST['tax_rate'],
        'discount_type' => $_POST['discount_type'],
        'discount_value' => $_POST['discount_value'],
        'shipping_amount' => $_POST['shipping_amount'],
        'notes' => $_POST['notes'],
        'terms_conditions' => $_POST['terms_conditions']
    ];
    
    $data = [
        'client_id' => (int)$_POST['client_id'],
        'project_id' => (int)$_POST['project_id'] ?: null,
        'frequency' => $_POST['frequency'],
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'] ?: null,
        'next_date' => calculateNextDate($_POST['start_date'], $_POST['frequency']),
        'template' => json_encode($template),
        'status' => $_POST['status']
    ];
    
    if (!empty($_POST['id'])) {
        db()->update('recurring_invoices', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('recurring_invoices', $data);
        $msg = 'created';
    }
    
    header("Location: recurring-invoices.php?msg=$msg");
    exit;
}

function calculateNextDate($startDate, $frequency) {
    $date = new DateTime($startDate);
    switch ($frequency) {
        case 'weekly':
            $date->modify('+1 week');
            break;
        case 'biweekly':
            $date->modify('+2 weeks');
            break;
        case 'monthly':
            $date->modify('+1 month');
            break;
        case 'quarterly':
            $date->modify('+3 months');
            break;
        case 'biannually':
            $date->modify('+6 months');
            break;
        case 'annually':
            $date->modify('+1 year');
            break;
    }
    return $date->format('Y-m-d');
}

// Get recurring invoice for editing
$recurring = null;
if ($id > 0 && $action === 'edit') {
    $recurring = db()->fetch("SELECT * FROM recurring_invoices WHERE id = ?", [$id]);
    if ($recurring) {
        $recurring['template'] = json_decode($recurring['template'], true);
    }
}

// Get all recurring invoices
$recurringInvoices = db()->fetchAll("
    SELECT r.*, c.company_name 
    FROM recurring_invoices r
    JOIN clients c ON r.client_id = c.id
    ORDER BY r.next_date ASC
");

// Get clients for dropdown
$clients = db()->fetchAll("SELECT id, company_name FROM clients WHERE status = 'active'");

// Get projects for dropdown
$projects = db()->fetchAll("SELECT id, title FROM projects WHERE status != 'completed'");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><?php echo $action === 'edit' ? 'Edit Recurring Invoice' : ($action === 'add' ? 'Create Recurring Invoice' : 'Recurring Invoices'); ?></h2>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            New Recurring Invoice
        </a>
        <a href="invoices.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Invoices
        </a>
        <?php else: ?>
        <a href="recurring-invoices.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to List
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Recurring invoice created successfully!';
        if ($_GET['msg'] ==='updated') echo 'Recurring invoice updated successfully!';
        ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Recurring Invoices List -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Frequency</th>
                    <th>Start Date</th>
                    <th>Next Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recurringInvoices as $ri): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($ri['company_name']); ?></strong></td>
                    <td><?php echo ucfirst($ri['frequency']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($ri['start_date'])); ?></td>
                    <td><strong><?php echo date('M d, Y', strtotime($ri['next_date'])); ?></strong></td>
                    <td><?php echo $ri['end_date'] ? date('M d, Y', strtotime($ri['end_date'])) : 'Ongoing'; ?></td>
                    <td>
                        <span class="status-badge <?php echo $ri['status']; ?>">
                            <?php echo ucfirst($ri['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="?action=edit&id=<?php echo $ri['id']; ?>" class="action-btn" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="action-btn" onclick="generateNow(<?php echo $ri['id']; ?>)" title="Generate Now">
                                <i class="fas fa-play"></i>
                            </button>
                            <a href="?pause=<?php echo $ri['id']; ?>" class="action-btn" title="Pause">
                                <i class="fas fa-pause"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Recurring Invoice Form -->
    <div class="form-container">
        <form method="POST" class="admin-form">
            <?php if ($recurring): ?>
            <input type="hidden" name="id" value="<?php echo $recurring['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="client_id">Client *</label>
                    <select id="client_id" name="client_id" required>
                        <option value="">-- Select Client --</option>
                        <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>"
                            <?php echo ($recurring['client_id'] ?? '') == $client['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['company_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="project_id">Project (Optional)</label>
                    <select id="project_id" name="project_id">
                        <option value="">-- None --</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>"
                            <?php echo ($recurring['project_id'] ?? '') == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($project['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="frequency">Frequency *</label>
                    <select id="frequency" name="frequency" required>
                        <option value="weekly" <?php echo ($recurring['frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="biweekly" <?php echo ($recurring['frequency'] ?? '') === 'biweekly' ? 'selected' : ''; ?>>Bi-Weekly</option>
                        <option value="monthly" <?php echo ($recurring['frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo ($recurring['frequency'] ?? '') === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="biannually" <?php echo ($recurring['frequency'] ?? '') === 'biannually' ? 'selected' : ''; ?>>Bi-Annually</option>
                        <option value="annually" <?php echo ($recurring['frequency'] ?? '') === 'annually' ? 'selected' : ''; ?>>Annually</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?php echo ($recurring['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="paused" <?php echo ($recurring['status'] ?? '') === 'paused' ? 'selected' : ''; ?>>Paused</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Start Date *</label>
                    <input type="date" id="start_date" name="start_date" required 
                           value="<?php echo $recurring['start_date'] ?? date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date (Optional)</label>
                    <input type="date" id="end_date" name="end_date" 
                           value="<?php echo $recurring['end_date'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="form-section">
                <h3>Invoice Template</h3>
                <p class="info-message">Configure the invoice template that will be used for each recurring invoice.</p>
                
                <!-- Items will be added via JavaScript similar to regular invoices -->
                <div id="invoice-items"></div>
                <button type="button" class="btn btn-outline btn-sm" onclick="addItem()">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
            
            <div class="form-section">
                <h3>Additional Settings</h3>
                
                <div class="form-group">
                    <label for="notes">Default Notes</label>
                    <textarea id="notes" name="notes" rows="3"><?php echo $recurring['template']['notes'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="terms_conditions">Terms & Conditions</label>
                    <textarea id="terms_conditions" name="terms_conditions" rows="3"><?php echo $recurring['template']['terms_conditions'] ?? 'Payment is due within 30 days.'; ?></textarea>
                </div>
            </div>
            
            <input type="hidden" name="items" id="items-json">
            
            <div class="form-actions">
                <button type="submit" name="save_recurring" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $recurring ? 'Update Recurring Invoice' : 'Create Recurring Invoice'; ?>
                </button>
                <a href="recurring-invoices.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
.info-message {
    background: #e8f4fd;
    color: #0369a1;
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.action-buttons {
    display: flex;
    gap: 5px;
}
</style>

<script>
let items = [];

<?php if ($recurring && !empty($recurring['template']['items'])): ?>
items = <?php echo json_encode($recurring['template']['items']); ?>;
<?php endif; ?>

function addItem() {
    items.push({
        description: '',
        quantity: 1,
        unit_price: 0,
        discount: 0,
        tax_rate: 0
    });
    renderItems();
}

function removeItem(index) {
    items.splice(index, 1);
    renderItems();
}

function renderItems() {
    const container = document.getElementById('invoice-items');
    let html = '';
    
    items.forEach((item, index) => {
        html += `
            <div class="item-row">
                <input type="text" placeholder="Description" value="${item.description.replace(/"/g, '&quot;')}" 
                       onchange="updateItem(${index}, 'description', this.value)">
                <input type="number" placeholder="Qty" value="${item.quantity}" min="0" step="0.01"
                       onchange="updateItem(${index}, 'quantity', parseFloat(this.value) || 0)">
                <input type="number" placeholder="Unit Price" value="${item.unit_price}" min="0" step="0.01"
                       onchange="updateItem(${index}, 'unit_price', parseFloat(this.value) || 0)">
                <button type="button" onclick="removeItem(${index})"><i class="fas fa-times"></i></button>
            </div>
        `;
    });
    
    container.innerHTML = html;
    document.getElementById('items-json').value = JSON.stringify(items);
}

function updateItem(index, field, value) {
    items[index][field] = value;
    document.getElementById('items-json').value = JSON.stringify(items);
}

function generateNow(id) {
    if (confirm('Generate invoice now?')) {
        window.location.href = 'generate-recurring.php?id=' + id;
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    if (items.length === 0) {
        addItem();
    } else {
        renderItems();
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>