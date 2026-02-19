<?php
// admin/invoices.php
// Invoice Management

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Invoice Management';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Invoices']
];

if ($action === 'add') {
    $breadcrumbs[] = ['title' => 'Create Invoice'];
} elseif ($action === 'edit') {
    $breadcrumbs[] = ['title' => 'Edit Invoice'];
} elseif ($action === 'view') {
    $breadcrumbs[] = ['title' => 'Invoice Details'];
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if invoice has payments
    $payments = db()->fetch("SELECT COUNT(*) as count FROM invoice_payments WHERE invoice_id = ?", [$id])['count'];
    if ($payments > 0) {
        header('Location: invoices.php?msg=has_payments');
        exit;
    }
    
    db()->delete('project_invoices', 'id = ?', [$id]);
    header('Location: invoices.php?msg=deleted');
    exit;
}

// Handle status update
if (isset($_GET['status'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];
    
    $updateData = ['status' => $status];
    
    if ($status === 'sent') {
        $updateData['sent_at'] = date('Y-m-d H:i:s');
    } elseif ($status === 'paid') {
        $updateData['paid_at'] = date('Y-m-d H:i:s');
    } elseif ($status === 'cancelled') {
        // Handle cancellation
    }
    
    db()->update('project_invoices', $updateData, 'id = :id', ['id' => $id]);
    
    // Log activity
    logActivity('invoice', $id, 'Status updated to ' . $status);
    
    header('Location: invoices.php?msg=status_updated');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice'])) {
    
    // Generate invoice number if not provided
    $invoiceNumber = $_POST['invoice_number'] ?: generateInvoiceNumber();
    
    // Calculate totals
    $items = json_decode($_POST['items'], true);
    $subtotal = 0;
    
    foreach ($items as $item) {
        $subtotal += $item['quantity'] * $item['unit_price'];
    }
    
    $taxRate = (float)$_POST['tax_rate'];
    $taxAmount = $subtotal * ($taxRate / 100);
    
    $discountType = $_POST['discount_type'];
    $discountValue = (float)$_POST['discount_value'];
    $discountAmount = 0;
    
    if ($discountType === 'percentage') {
        $discountAmount = $subtotal * ($discountValue / 100);
    } elseif ($discountType === 'fixed') {
        $discountAmount = $discountValue;
    }
    
    $shippingAmount = (float)$_POST['shipping_amount'];
    $total = $subtotal + $taxAmount - $discountAmount + $shippingAmount;
    
    $data = [
        'project_id' => (int)$_POST['project_id'] ?: null,
        'client_id' => (int)$_POST['client_id'],
        'invoice_number' => $invoiceNumber,
        'invoice_date' => $_POST['invoice_date'],
        'due_date' => $_POST['due_date'],
        'status' => $_POST['status'],
        'bill_to' => $_POST['bill_to'],
        'ship_to' => $_POST['ship_to'],
        'items' => $items,
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'discount_amount' => $discountAmount,
        'shipping_amount' => $shippingAmount,
        'total' => $total,
        'paid_amount' => (float)$_POST['paid_amount'] ?: 0,
        'balance_due' => $total - ((float)$_POST['paid_amount'] ?: 0),
        'currency' => $_POST['currency'],
        'payment_terms' => $_POST['payment_terms'],
        'notes' => $_POST['notes'],
        'terms_conditions' => $_POST['terms_conditions'],
        'tax_id' => $_POST['tax_id'],
        'business_number' => $_POST['business_number'],
        'created_by' => $_SESSION['user_id']
    ];
    
    if (!empty($_POST['id'])) {
        db()->update('project_invoices', $data, 'id = :id', ['id' => $_POST['id']]);
        $invoiceId = $_POST['id'];
        $msg = 'updated';
        
        // Delete existing items and re-add
        db()->delete('invoice_items', 'invoice_id = ?', [$invoiceId]);
    } else {
        $invoiceId = db()->insert('project_invoices', $data);
        $msg = 'created';
    }
    
    // Save line items
    foreach ($items as $index => $item) {
        $itemTotal = $item['quantity'] * $item['unit_price'] - ($item['discount'] ?? 0);
        $itemTax = $itemTotal * (($item['tax_rate'] ?? 0) / 100);
        
        db()->insert('invoice_items', [
            'invoice_id' => $invoiceId,
            'item_type' => $item['type'] ?? 'service',
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount' => $item['discount'] ?? 0,
            'tax_rate' => $item['tax_rate'] ?? 0,
            'tax_amount' => $itemTax,
            'total' => $itemTotal + $itemTax,
            'sort_order' => $index
        ]);
    }
    
    // Generate PDF
    generateInvoicePDF($invoiceId);
    
    // Log activity
    logActivity('invoice', $invoiceId, 'Invoice ' . ($msg === 'created' ? 'created' : 'updated'));
    
    header("Location: invoices.php?msg=$msg");
    exit;
}
// Send invoice notification
if ($data['status'] === 'sent') {
    $client = db()->fetch("SELECT * FROM clients WHERE id = ?", [$data['client_id']]);
    mailer()->sendTemplate('invoice_created', [
        'email' => $client['email'],
        'name' => $client['contact_person']
    ], [
        'client_name' => $client['contact_person'],
        'invoice_number' => $invoiceNumber,
        'project_title' => $projectTitle ?? 'N/A',
        'invoice_date' => date('F d, Y', strtotime($data['invoice_date'])),
        'due_date' => date('F d, Y', strtotime($data['due_date'])),
        'amount' => number_format($data['total'], 2),
        'balance_due' => number_format($data['total'], 2),
        'invoice_url' => BASE_URL . '/client/pay-invoice.php?id=' . $invoiceId
    ]);
}
// Generate unique invoice number
function generateInvoiceNumber() {
    $year = date('Y');
    $month = date('m');
    
    $lastInvoice = db()->fetch("
        SELECT invoice_number FROM project_invoices 
        WHERE invoice_number LIKE 'INV-$year$month%' 
        ORDER BY id DESC LIMIT 1
    ");
    
    if ($lastInvoice) {
        $lastNum = intval(substr($lastInvoice['invoice_number'], -4));
        $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNum = '0001';
    }
    
    return "INV-$year$month-$newNum";
}

// Generate PDF function
function generateInvoicePDF($invoiceId) {
    // This would use a PDF library like Dompdf or TCPDF
    // For now, we'll just create a placeholder
    $pdfPath = 'invoices/invoice-' . $invoiceId . '.pdf';
    db()->update('project_invoices', ['pdf_path' => $pdfPath], 'id = :id', ['id' => $invoiceId]);
}

// Get invoice for editing/viewing
$invoice = null;
if ($id > 0) {
    if ($action === 'edit' || $action === 'view') {
        $invoice = db()->fetch("SELECT * FROM project_invoices WHERE id = ?", [$id]);
        if ($invoice) {
            $invoice['items'] = json_decode($invoice['items'], true);
        }
    }
}

// Get all invoices
$invoices = db()->fetchAll("
    SELECT i.*, c.company_name, c.contact_person,
           (SELECT SUM(amount) FROM invoice_payments WHERE invoice_id = i.id) as total_paid
    FROM project_invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    ORDER BY i.created_at DESC
");

// Get clients for dropdown
$clients = db()->fetchAll("SELECT id, company_name FROM clients WHERE status = 'active' ORDER BY company_name");

// Get projects for dropdown
$projects = db()->fetchAll("SELECT id, title FROM projects WHERE status != 'completed' ORDER BY created_at DESC");

// Get tax rates
$taxRates = db()->fetchAll("SELECT * FROM tax_rates ORDER BY is_default DESC, name");

// Get statistics
$stats = db()->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as total_paid,
        SUM(CASE WHEN status != 'paid' AND status != 'cancelled' THEN balance_due ELSE 0 END) as outstanding
    FROM project_invoices
") ?? ['total' => 0, 'draft' => 0, 'sent' => 0, 'paid' => 0, 'overdue' => 0, 'total_paid' => 0, 'outstanding' => 0];

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>
        <?php 
        if ($action === 'view') echo 'Invoice Details';
        elseif ($action === 'edit') echo 'Edit Invoice';
        elseif ($action === 'add') echo 'Create New Invoice';
        else echo 'Invoice Management';
        ?>
    </h2>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Create Invoice
        </a>
        <a href="?action=recurring" class="btn btn-outline">
            <i class="fas fa-repeat"></i>
            Recurring Invoices
        </a>
        <a href="?action=expenses" class="btn btn-outline">
            <i class="fas fa-receipt"></i>
            Expenses
        </a>
        <?php elseif ($action === 'view' && $invoice): ?>
        <a href="?action=edit&id=<?php echo $invoice['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i>
            Edit Invoice
        </a>
        <button class="btn btn-outline" onclick="sendInvoice(<?php echo $invoice['id']; ?>)">
            <i class="fas fa-paper-plane"></i>
            Send to Client
        </button>
        <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-outline">
            <i class="fas fa-download"></i>
            Download PDF
        </a>
        <button class="btn btn-outline" onclick="recordPayment(<?php echo $invoice['id']; ?>)">
            <i class="fas fa-money-bill"></i>
            Record Payment
        </button>
        <?php else: ?>
        <a href="invoices.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Invoices
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Invoice created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Invoice updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Invoice deleted successfully!';
        if ($_GET['msg'] === 'status_updated') echo 'Invoice status updated!';
        if ($_GET['msg'] === 'has_payments') echo 'Cannot delete invoice with existing payments!';
        ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="stat-details">
                <h3>Total Invoices</h3>
                <span class="stat-value"><?php echo $stats['total']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon yellow">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-details">
                <h3>Outstanding</h3>
                <span class="stat-value">$<?php echo number_format($stats['outstanding'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <h3>Paid</h3>
                <span class="stat-value">$<?php echo number_format($stats['total_paid'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-details">
                <h3>Overdue</h3>
                <span class="stat-value"><?php echo $stats['overdue']; ?></span>
            </div>
        </div>
    </div>
    
    <!-- Status Tabs -->
    <div class="status-tabs">
        <a href="?status=all" class="status-tab <?php echo !isset($_GET['status']) || $_GET['status'] === 'all' ? 'active' : ''; ?>">
            All (<?php echo $stats['total']; ?>)
        </a>
        <a href="?status=draft" class="status-tab <?php echo ($_GET['status'] ?? '') === 'draft' ? 'active' : ''; ?>">
            Draft (<?php echo $stats['draft']; ?>)
        </a>
        <a href="?status=sent" class="status-tab <?php echo ($_GET['status'] ?? '') === 'sent' ? 'active' : ''; ?>">
            Sent (<?php echo $stats['sent']; ?>)
        </a>
        <a href="?status=paid" class="status-tab <?php echo ($_GET['status'] ?? '') === 'paid' ? 'active' : ''; ?>">
            Paid (<?php echo $stats['paid']; ?>)
        </a>
        <a href="?status=overdue" class="status-tab <?php echo ($_GET['status'] ?? '') === 'overdue' ? 'active' : ''; ?>">
            Overdue (<?php echo $stats['overdue']; ?>)
        </a>
    </div>

    <!-- Invoices Table -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Client</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $filter = $_GET['status'] ?? 'all';
                foreach ($invoices as $inv): 
                    if ($filter !== 'all' && $inv['status'] !== $filter) continue;
                ?>
                <tr class="<?php echo $inv['status'] === 'overdue' ? 'overdue' : ''; ?>">
                    <td>
                        <strong><?php echo $inv['invoice_number']; ?></strong>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($inv['company_name'] ?: 'N/A'); ?></strong>
                        <br><small><?php echo htmlspecialchars($inv['contact_person']); ?></small>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($inv['invoice_date'])); ?></td>
                    <td>
                        <?php echo date('M d, Y', strtotime($inv['due_date'])); ?>
                        <?php if ($inv['status'] === 'sent' && strtotime($inv['due_date']) < time()): ?>
                        <br><span class="badge warning">Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td><strong>$<?php echo number_format($inv['total'], 2); ?></strong></td>
                    <td>$<?php echo number_format($inv['paid_amount'], 2); ?></td>
                    <td>$<?php echo number_format($inv['balance_due'], 2); ?></td>
                    <td>
                        <select class="status-select" onchange="updateStatus(<?php echo $inv['id']; ?>, this.value)">
                            <option value="draft" <?php echo $inv['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="sent" <?php echo $inv['status'] === 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="paid" <?php echo $inv['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="overdue" <?php echo $inv['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            <option value="cancelled" <?php echo $inv['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="?action=view&id=<?php echo $inv['id']; ?>" class="action-btn" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="?action=edit&id=<?php echo $inv['id']; ?>" class="action-btn" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="download-invoice.php?id=<?php echo $inv['id']; ?>" class="action-btn" title="Download PDF">
                                <i class="fas fa-download"></i>
                            </a>
                            <?php if ($inv['status'] !== 'paid'): ?>
                            <button class="action-btn" onclick="recordPayment(<?php echo $inv['id']; ?>)" title="Record Payment">
                                <i class="fas fa-money-bill"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($inv['status'] === 'draft'): ?>
                            <a href="?delete=<?php echo $inv['id']; ?>" class="action-btn delete-btn" 
                               onclick="return confirm('Delete this invoice?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="9" class="text-center">No invoices found. Create your first invoice!</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Invoice Form -->
    <div class="form-container invoice-form">
        <form method="POST" id="invoiceForm" class="admin-form">
            <?php if ($invoice): ?>
            <input type="hidden" name="id" value="<?php echo $invoice['id']; ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <h3>Invoice Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_number">Invoice Number</label>
                        <input type="text" id="invoice_number" name="invoice_number" 
                               value="<?php echo $invoice['invoice_number'] ?? generateInvoiceNumber(); ?>"
                               placeholder="Leave empty to auto-generate">
                    </div>
                    
                    <div class="form-group">
                        <label for="client_id">Client *</label>
                        <select id="client_id" name="client_id" required onchange="loadClientDetails(this.value)">
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" 
                                <?php echo ($invoice['client_id'] ?? '') == $client['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['company_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="project_id">Related Project (Optional)</label>
                        <select id="project_id" name="project_id">
                            <option value="">-- Select Project --</option>
                            <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>"
                                <?php echo ($invoice['project_id'] ?? '') == $project['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="currency">Currency</label>
                        <select id="currency" name="currency">
                            <option value="USD" <?php echo ($invoice['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                            <option value="EUR" <?php echo ($invoice['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                            <option value="GBP" <?php echo ($invoice['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                            <option value="CAD" <?php echo ($invoice['currency'] ?? '') === 'CAD' ? 'selected' : ''; ?>>CAD - Canadian Dollar</option>
                            <option value="AUD" <?php echo ($invoice['currency'] ?? '') === 'AUD' ? 'selected' : ''; ?>>AUD - Australian Dollar</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_date">Invoice Date *</label>
                        <input type="date" id="invoice_date" name="invoice_date" required 
                               value="<?php echo $invoice['invoice_date'] ?? date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date *</label>
                        <input type="date" id="due_date" name="due_date" required 
                               value="<?php echo $invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days')); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="payment_terms">Payment Terms</label>
                    <select id="payment_terms" name="payment_terms">
                        <option value="due_on_receipt" <?php echo ($invoice['payment_terms'] ?? '') === 'due_on_receipt' ? 'selected' : ''; ?>>Due on Receipt</option>
                        <option value="net_15" <?php echo ($invoice['payment_terms'] ?? '') === 'net_15' ? 'selected' : ''; ?>>Net 15</option>
                        <option value="net_30" <?php echo ($invoice['payment_terms'] ?? '') === 'net_30' ? 'selected' : ''; ?>>Net 30</option>
                        <option value="net_60" <?php echo ($invoice['payment_terms'] ?? '') === 'net_60' ? 'selected' : ''; ?>>Net 60</option>
                    </select>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Billing Address</h3>
                
                <div class="form-group">
                    <label for="bill_to">Bill To *</label>
                    <textarea id="bill_to" name="bill_to" rows="4" required><?php echo $invoice['bill_to'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="ship_to">Ship To (if different)</label>
                    <textarea id="ship_to" name="ship_to" rows="4"><?php echo $invoice['ship_to'] ?? ''; ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Invoice Items</h3>
                
                <div id="invoice-items">
                    <!-- Items will be added here via JavaScript -->
                </div>
                
                <button type="button" class="btn btn-outline btn-sm" onclick="addItem()">
                    <i class="fas fa-plus"></i>
                    Add Item
                </button>
            </div>
            
            <div class="form-section">
                <h3>Summary</h3>
                
                <div class="summary-calculations">
                    <div class="calc-row">
                        <span>Subtotal:</span>
                        <span id="calc-subtotal">$0.00</span>
                    </div>
                    
                    <div class="calc-row">
                        <span>Tax:</span>
                        <span>
                            <select id="tax_rate" name="tax_rate" onchange="calculateTotals()">
                                <?php foreach ($taxRates as $tax): ?>
                                <option value="<?php echo $tax['rate']; ?>" <?php echo $tax['is_default'] ? 'selected' : ''; ?>>
                                    <?php echo $tax['name']; ?> (<?php echo $tax['rate']; ?>%)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span id="calc-tax">$0.00</span>
                        </span>
                    </div>
                    
                    <div class="calc-row">
                        <span>Discount:</span>
                        <span>
                            <select id="discount_type" name="discount_type" onchange="calculateTotals()">
                                <option value="none">No Discount</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                            <input type="number" id="discount_value" name="discount_value" 
                                   value="0" min="0" step="0.01" onchange="calculateTotals()" style="width: 100px;">
                            <span id="calc-discount">$0.00</span>
                        </span>
                    </div>
                    
                    <div class="calc-row">
                        <span>Shipping:</span>
                        <span>
                            <input type="number" id="shipping_amount" name="shipping_amount" 
                                   value="0" min="0" step="0.01" onchange="calculateTotals()" style="width: 100px;">
                        </span>
                    </div>
                    
                    <div class="calc-row total">
                        <span>Total:</span>
                        <span id="calc-total">$0.00</span>
                    </div>
                </div>
                
                <input type="hidden" name="items" id="items-json">
                <input type="hidden" name="subtotal" id="subtotal">
                <input type="hidden" name="tax_amount" id="tax_amount">
                <input type="hidden" name="discount_amount" id="discount_amount">
                <input type="hidden" name="total" id="total">
            </div>
            
            <div class="form-section">
                <h3>Additional Information</h3>
                
                                <div class="form-row">
                    <div class="form-group">
                        <label for="tax_id">Tax ID / VAT Number</label>
                        <input type="text" id="tax_id" name="tax_id" value="<?php echo $invoice['tax_id'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="business_number">Business Number</label>
                        <input type="text" id="business_number" name="business_number" value="<?php echo $invoice['business_number'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (visible to client)</label>
                    <textarea id="notes" name="notes" rows="3"><?php echo $invoice['notes'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="terms_conditions">Terms & Conditions</label>
                    <textarea id="terms_conditions" name="terms_conditions" rows="3"><?php echo $invoice['terms_conditions'] ?? 'Payment is due within 30 days. Thank you for your business.'; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="status">Invoice Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo ($invoice['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="sent" <?php echo ($invoice['status'] ?? '') === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="paid" <?php echo ($invoice['status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                
                <?php if ($invoice && $invoice['status'] !== 'draft'): ?>
                <div class="form-group">
                    <label for="paid_amount">Amount Paid</label>
                    <input type="number" id="paid_amount" name="paid_amount" step="0.01" min="0"
                           value="<?php echo $invoice['paid_amount'] ?? 0; ?>">
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_invoice" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $invoice ? 'Update Invoice' : 'Create Invoice'; ?>
                </button>
                <a href="invoices.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>

<?php elseif ($action === 'view' && $invoice): ?>
    <!-- Invoice View -->
    <div class="invoice-view">
        <div class="invoice-header">
            <div class="invoice-status <?php echo $invoice['status']; ?>">
                <?php echo strtoupper($invoice['status']); ?>
            </div>
            
            <div class="invoice-actions">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-outline">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>
        </div>
        
        <div class="invoice-paper">
            <div class="invoice-paper-header">
                <div class="company-info">
                    <h2><?php echo SITE_NAME; ?></h2>
                    <p><?php echo getSetting('address'); ?></p>
                    <p>Email: <?php echo getSetting('contact_email'); ?></p>
                    <p>Phone: <?php echo getSetting('contact_phone'); ?></p>
                </div>
                
                <div class="invoice-title">
                    <h1>INVOICE</h1>
                    <h3><?php echo $invoice['invoice_number']; ?></h3>
                </div>
            </div>
            
            <div class="invoice-paper-body">
                <div class="invoice-dates">
                    <div class="date-box">
                        <strong>Invoice Date:</strong>
                        <span><?php echo date('F d, Y', strtotime($invoice['invoice_date'])); ?></span>
                    </div>
                    <div class="date-box">
                        <strong>Due Date:</strong>
                        <span><?php echo date('F d, Y', strtotime($invoice['due_date'])); ?></span>
                    </div>
                </div>
                
                <div class="invoice-addresses">
                    <div class="bill-to">
                        <h4>Bill To:</h4>
                        <p><?php echo nl2br(htmlspecialchars($invoice['bill_to'])); ?></p>
                    </div>
                    
                    <?php if ($invoice['ship_to']): ?>
                    <div class="ship-to">
                        <h4>Ship To:</h4>
                        <p><?php echo nl2br(htmlspecialchars($invoice['ship_to'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <table class="invoice-items-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Discount</th>
                            <th>Tax</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $items = db()->fetchAll("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order", [$invoice['id']]);
                        foreach ($items as $item): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['description']); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-right">$<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-right"><?php echo $item['discount'] ? '$' . number_format($item['discount'], 2) : '-'; ?></td>
                            <td class="text-right"><?php echo $item['tax_rate'] ? $item['tax_rate'] . '%' : '-'; ?></td>
                            <td class="text-right"><strong>$<?php echo number_format($item['total'], 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="invoice-summary">
                    <div class="summary-left">
                        <?php if ($invoice['notes']): ?>
                        <div class="invoice-notes">
                            <h4>Notes:</h4>
                            <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($invoice['terms_conditions']): ?>
                        <div class="invoice-terms">
                            <h4>Terms & Conditions:</h4>
                            <p><?php echo nl2br(htmlspecialchars($invoice['terms_conditions'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="summary-right">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($invoice['subtotal'], 2); ?></span>
                        </div>
                        
                        <?php if ($invoice['tax_amount'] > 0): ?>
                        <div class="summary-row">
                            <span>Tax (<?php echo $invoice['tax_rate']; ?>%):</span>
                            <span>$<?php echo number_format($invoice['tax_amount'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($invoice['discount_amount'] > 0): ?>
                        <div class="summary-row">
                            <span>Discount:</span>
                            <span>-$<?php echo number_format($invoice['discount_amount'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($invoice['shipping_amount'] > 0): ?>
                        <div class="summary-row">
                            <span>Shipping:</span>
                            <span>$<?php echo number_format($invoice['shipping_amount'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>$<?php echo number_format($invoice['total'], 2); ?></span>
                        </div>
                        
                        <?php if ($invoice['paid_amount'] > 0): ?>
                        <div class="summary-row paid">
                            <span>Paid:</span>
                            <span>$<?php echo number_format($invoice['paid_amount'], 2); ?></span>
                        </div>
                        <div class="summary-row balance">
                            <span>Balance Due:</span>
                            <span>$<?php echo number_format($invoice['balance_due'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="invoice-paper-footer">
                <p>Thank you for your business!</p>
                <?php if ($invoice['tax_id']): ?>
                <p>Tax ID: <?php echo $invoice['tax_id']; ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment History -->
        <?php
        $payments = db()->fetchAll("SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY payment_date DESC", [$invoice['id']]);
        if ($payments):
        ?>
        <div class="payment-history">
            <h3>Payment History</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Payment #</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Transaction ID</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo $payment['payment_number']; ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                        <td><strong>$<?php echo number_format($payment['amount'], 2); ?></strong></td>
                        <td><?php echo $payment['transaction_id'] ?: '-'; ?></td>
                        <td>
                            <span class="status-badge <?php echo $payment['status']; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
/* Invoice Form Styles */
.invoice-form {
    max-width: 1200px;
}

.summary-calculations {
    max-width: 400px;
    margin-left: auto;
    background: var(--gray-100);
    padding: 20px;
    border-radius: 8px;
}

.calc-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-300);
}

.calc-row:last-child {
    border-bottom: none;
}

.calc-row.total {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--primary);
    margin-top: 10px;
    padding-top: 10px;
    border-top: 2px solid var(--gray-400);
}

/* Invoice View Styles */
.invoice-view {
    max-width: 1000px;
    margin: 0 auto;
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.invoice-status {
    padding: 8px 20px;
    border-radius: 30px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.9rem;
}

.invoice-status.draft { background: #e5e7eb; color: #374151; }
.invoice-status.sent { background: #dbeafe; color: #1e40af; }
.invoice-status.paid { background: #d1fae5; color: #065f46; }
.invoice-status.overdue { background: #fee2e2; color: #991b1b; }
.invoice-status.cancelled { background: #f3f4f6; color: #6b7280; }

.invoice-paper {
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.invoice-paper-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
}

.company-info h2 {
    color: var(--primary);
    margin-bottom: 10px;
}

.company-info p {
    color: #6b7280;
    font-size: 0.9rem;
    margin: 3px 0;
}

.invoice-title {
    text-align: right;
}

.invoice-title h1 {
    color: var(--primary);
    font-size: 2.5rem;
    margin-bottom: 5px;
}

.invoice-title h3 {
    color: #6b7280;
    font-weight: normal;
}

.invoice-dates {
    display: flex;
    gap: 30px;
    margin-bottom: 30px;
}

.date-box {
    background: #f9fafb;
    padding: 10px 20px;
    border-radius: 8px;
}

.date-box strong {
    display: block;
    font-size: 0.85rem;
    color: #6b7280;
    margin-bottom: 5px;
}

.date-box span {
    font-size: 1.1rem;
    font-weight: 600;
}

.invoice-addresses {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-bottom: 40px;
}

.bill-to h4,
.ship-to h4 {
    color: #374151;
    margin-bottom: 10px;
    font-size: 1rem;
}

.bill-to p,
.ship-to p {
    color: #6b7280;
    line-height: 1.6;
}

.invoice-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 40px;
}

.invoice-items-table th {
    background: #f9fafb;
    padding: 12px;
    text-align: left;
    font-size: 0.9rem;
    color: #374151;
    border-bottom: 2px solid #e5e7eb;
}

.invoice-items-table td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.invoice-items-table .text-center {
    text-align: center;
}

.invoice-items-table .text-right {
    text-align: right;
}

.invoice-summary {
    display: flex;
    justify-content: space-between;
    gap: 40px;
}

.summary-left {
    flex: 1;
}

.invoice-notes,
.invoice-terms {
    margin-bottom: 20px;
}

.invoice-notes h4,
.invoice-terms h4 {
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.invoice-notes p,
.invoice-terms p {
    color: #6b7280;
    font-size: 0.9rem;
    line-height: 1.5;
}

.summary-right {
    width: 300px;
    background: #f9fafb;
    padding: 20px;
    border-radius: 8px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
}

.summary-row.total {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--primary);
    border-bottom: none;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 2px solid #d1d5db;
}

.summary-row.paid {
    color: #10b981;
}

.summary-row.balance {
    font-weight: 700;
    color: #ef4444;
}

.invoice-paper-footer {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
    color: #6b7280;
    font-size: 0.9rem;
}

.payment-history {
    margin-top: 30px;
}

.payment-history h3 {
    margin-bottom: 15px;
}

/* Status Tabs */
.status-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    background: #f9fafb;
    padding: 4px;
    border-radius: 8px;
    overflow-x: auto;
}

.status-tab {
    padding: 8px 16px;
    color: #6b7280;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.9rem;
    white-space: nowrap;
    transition: all 0.3s ease;
}

.status-tab:hover,
.status-tab.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Status Select */
.status-select {
    padding: 4px 8px;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    font-size: 0.85rem;
    background: white;
}

.overdue {
    background: rgba(239, 68, 68, 0.05);
}

/* Badge */
.badge.warning {
    background: #f59e0b;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
}

/* Responsive */
@media (max-width: 768px) {
    .invoice-paper-header {
        flex-direction: column;
        gap: 20px;
    }
    
    .invoice-title {
        text-align: left;
    }
    
    .invoice-dates {
        flex-direction: column;
        gap: 10px;
    }
    
    .invoice-addresses {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .invoice-summary {
        flex-direction: column;
    }
    
    .summary-right {
        width: 100%;
    }
    
    .invoice-items-table {
        font-size: 0.9rem;
    }
    
    .invoice-items-table th,
    .invoice-items-table td {
        padding: 8px 4px;
    }
}

@media print {
    .admin-sidebar,
    .admin-header,
    .content-header,
    .invoice-header,
    .payment-history,
    .form-actions,
    .action-buttons {
        display: none !important;
    }
    
    .invoice-paper {
        box-shadow: none;
        padding: 0;
    }
}
</style>

<script>
let items = [];

<?php if ($invoice && !empty($invoice['items'])): ?>
items = <?php echo json_encode($invoice['items']); ?>;
<?php endif; ?>

function addItem() {
    items.push({
        type: 'service',
        description: '',
        quantity: 1,
        unit_price: 0,
        discount: 0,
        tax_rate: 0
    });
    renderItems();
    calculateTotals();
}

function removeItem(index) {
    items.splice(index, 1);
    renderItems();
    calculateTotals();
}

function renderItems() {
    const container = document.getElementById('invoice-items');
    let html = '';
    
    items.forEach((item, index) => {
        html += `
            <div class="item-row" data-index="${index}">
                <div class="item-fields">
                    <select class="item-type" onchange="updateItem(${index}, 'type', this.value)">
                        <option value="service" ${item.type === 'service' ? 'selected' : ''}>Service</option>
                        <option value="product" ${item.type === 'product' ? 'selected' : ''}>Product</option>
                        <option value="hourly" ${item.type === 'hourly' ? 'selected' : ''}>Hourly</option>
                        <option value="expense" ${item.type === 'expense' ? 'selected' : ''}>Expense</option>
                    </select>
                    
                    <input type="text" class="item-description" placeholder="Description" 
                           value="${item.description.replace(/"/g, '&quot;')}" 
                           onchange="updateItem(${index}, 'description', this.value)">
                    
                    <input type="number" class="item-quantity" placeholder="Qty" 
                           value="${item.quantity}" min="0" step="0.01"
                           onchange="updateItem(${index}, 'quantity', parseFloat(this.value) || 0)">
                    
                    <input type="number" class="item-price" placeholder="Unit Price" 
                           value="${item.unit_price}" min="0" step="0.01"
                           onchange="updateItem(${index}, 'unit_price', parseFloat(this.value) || 0)">
                    
                    <input type="number" class="item-discount" placeholder="Discount" 
                           value="${item.discount}" min="0" step="0.01"
                           onchange="updateItem(${index}, 'discount', parseFloat(this.value) || 0)">
                    
                    <input type="number" class="item-tax" placeholder="Tax %" 
                           value="${item.tax_rate}" min="0" step="0.01"
                           onchange="updateItem(${index}, 'tax_rate', parseFloat(this.value) || 0)">
                    
                    <button type="button" class="remove-item" onclick="removeItem(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="item-total">
                    Total: $${calculateItemTotal(item).toFixed(2)}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function updateItem(index, field, value) {
    items[index][field] = value;
    calculateTotals();
    renderItems(); // Re-render to update totals
}

function calculateItemTotal(item) {
    const subtotal = item.quantity * item.unit_price;
    const discount = item.discount || 0;
    const afterDiscount = subtotal - discount;
    const tax = afterDiscount * (item.tax_rate / 100);
    return afterDiscount + tax;
}

function calculateTotals() {
    let subtotal = 0;
    
    items.forEach(item => {
        subtotal += item.quantity * item.unit_price;
    });
    
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
    const taxAmount = subtotal * (taxRate / 100);
    
    const discountType = document.getElementById('discount_type').value;
    const discountValue = parseFloat(document.getElementById('discount_value').value) || 0;
    let discountAmount = 0;
    
    if (discountType === 'percentage') {
        discountAmount = subtotal * (discountValue / 100);
    } else if (discountType === 'fixed') {
        discountAmount = discountValue;
    }
    
    const shippingAmount = parseFloat(document.getElementById('shipping_amount').value) || 0;
    const total = subtotal + taxAmount - discountAmount + shippingAmount;
    
    document.getElementById('calc-subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('calc-tax').textContent = '$' + taxAmount.toFixed(2);
    document.getElementById('calc-discount').textContent = '-$' + discountAmount.toFixed(2);
    document.getElementById('calc-total').textContent = '$' + total.toFixed(2);
    
    document.getElementById('subtotal').value = subtotal;
    document.getElementById('tax_amount').value = taxAmount;
    document.getElementById('discount_amount').value = discountAmount;
    document.getElementById('total').value = total;
    document.getElementById('items-json').value = JSON.stringify(items);
}

function loadClientDetails(clientId) {
    if (!clientId) return;
    
    fetch('ajax/get-client-details.php?id=' + clientId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const billTo = `${data.company_name}\n${data.contact_person}\n${data.email}\n${data.phone}\n${data.address}`;
                document.getElementById('bill_to').value = billTo;
            }
        });
}

function updateStatus(invoiceId, status) {
    window.location.href = `invoices.php?status=${status}&id=${invoiceId}`;
}

function sendInvoice(invoiceId) {
    if (confirm('Send this invoice to the client?')) {
        window.location.href = `send-invoice.php?id=${invoiceId}`;
    }
}

function recordPayment(invoiceId) {
    window.location.href = `record-payment.php?invoice_id=${invoiceId}`;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (items.length === 0) {
        addItem(); // Add first empty item
    } else {
        renderItems();
        calculateTotals();
    }
    
    // Load client details if editing
    const clientSelect = document.getElementById('client_id');
    if (clientSelect.value) {
        loadClientDetails(clientSelect.value);
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>