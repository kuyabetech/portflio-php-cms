<?php
// admin/invoices.php
// Invoice Management - FULLY RESPONSIVE

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
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Cannot delete invoice with existing payments!'];
        header('Location: invoices.php');
        exit;
    }
    
    db()->delete('project_invoices', 'id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Invoice deleted successfully'];
    header('Location: invoices.php');
    exit;
}

// Handle status update
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];
    
    $updateData = ['status' => $status];
    
    if ($status === 'sent') {
        $updateData['sent_at'] = date('Y-m-d H:i:s');
    } elseif ($status === 'paid') {
        $updateData['paid_at'] = date('Y-m-d H:i:s');
    }
    
    db()->update('project_invoices', $updateData, 'id = :id', ['id' => $id]);
    
    // Log activity
    logActivity('invoice', $id, 'Status updated to ' . $status);
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Invoice status updated'];
    header('Location: invoices.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice'])) {
    
    // Parse items from JSON
    $items = [];
    if (!empty($_POST['items'])) {
        $decodedItems = json_decode($_POST['items'], true);
        if (is_array($decodedItems)) {
            $items = $decodedItems;
        }
    }
    
    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += (isset($item['quantity']) ? (float)$item['quantity'] : 0) * 
                     (isset($item['unit_price']) ? (float)$item['unit_price'] : 0);
    }
    
    $taxRate = isset($_POST['tax_rate']) ? (float)$_POST['tax_rate'] : 0;
    $taxAmount = $subtotal * ($taxRate / 100);
    
    // Fix discount_type - use only alphanumeric characters
    $discountType = $_POST['discount_type'] ?? 'none';
    $validDiscountType = preg_replace('/[^a-zA-Z0-9_]/', '', $discountType);
    if (empty($validDiscountType)) {
        $validDiscountType = 'none';
    }
    
    $discountValue = isset($_POST['discount_value']) ? (float)$_POST['discount_value'] : 0;
    $discountAmount = 0;
    
    if ($discountType === 'percentage') {
        $discountAmount = $subtotal * ($discountValue / 100);
    } elseif ($discountType === 'fixed') {
        $discountAmount = $discountValue;
    }
    
    $shippingAmount = isset($_POST['shipping_amount']) ? (float)$_POST['shipping_amount'] : 0;
    $total = $subtotal + $taxAmount - $discountAmount + $shippingAmount;
    
    // Handle paid_amount safely
    $paidAmount = isset($_POST['paid_amount']) ? (float)$_POST['paid_amount'] : 0;
    $balanceDue = $total - $paidAmount;
    
    // Generate invoice number if not provided
    $invoiceNumber = !empty($_POST['invoice_number']) ? $_POST['invoice_number'] : generateInvoiceNumber();
    
    // Prepare data for insertion - ensure items is properly JSON encoded
    $itemsJson = json_encode($items);
    if ($itemsJson === false) {
        $itemsJson = '[]';
    }
    
    $data = [
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'client_id' => isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0,
        'invoice_number' => $invoiceNumber,
        'invoice_date' => $_POST['invoice_date'] ?? date('Y-m-d'),
        'due_date' => $_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
        'status' => $_POST['status'] ?? 'draft',
        'bill_to' => $_POST['bill_to'] ?? '',
        'ship_to' => $_POST['ship_to'] ?? '',
        'items' => $itemsJson,
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'discount_type' => $validDiscountType,
        'discount_value' => $discountValue,
        'discount_amount' => $discountAmount,
        'shipping_amount' => $shippingAmount,
        'total' => $total,
        'paid_amount' => $paidAmount,
        'balance_due' => $balanceDue,
        'currency' => $_POST['currency'] ?? 'USD',
        'payment_terms' => $_POST['payment_terms'] ?? 'net_30',
        'notes' => $_POST['notes'] ?? '',
        'terms_conditions' => $_POST['terms_conditions'] ?? '',
        'tax_id' => $_POST['tax_id'] ?? '',
        'business_number' => $_POST['business_number'] ?? '',
        'created_by' => $_SESSION['user_id'] ?? 0
    ];
    
    // Validate required fields
    $errors = [];
    if (empty($data['client_id'])) {
        $errors[] = 'Client is required';
    }
    if (empty($data['invoice_date'])) {
        $errors[] = 'Invoice date is required';
    }
    if (empty($data['due_date'])) {
        $errors[] = 'Due date is required';
    }
    if (empty($data['bill_to'])) {
        $errors[] = 'Bill to address is required';
    }
    
    // Validate items
    if (empty($items)) {
        $errors[] = 'At least one invoice item is required';
    } else {
        foreach ($items as $index => $item) {
            if (empty($item['description'])) {
                $errors[] = 'Item #' . ($index + 1) . ': Description is required';
            }
            if (empty($item['quantity']) || $item['quantity'] <= 0) {
                $errors[] = 'Item #' . ($index + 1) . ': Valid quantity is required';
            }
            if (!isset($item['unit_price']) || $item['unit_price'] < 0) {
                $errors[] = 'Item #' . ($index + 1) . ': Valid unit price is required';
            }
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => implode('<br>', $errors)];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    if (!empty($_POST['id'])) {
        // Update existing invoice
        db()->update('project_invoices', $data, 'id = :id', ['id' => $_POST['id']]);
        $invoiceId = $_POST['id'];
        $msg = 'updated';
        
        // Delete existing items and re-add
        db()->delete('invoice_items', 'invoice_id = ?', [$invoiceId]);
    } else {
        // Insert new invoice
        $invoiceId = db()->insert('project_invoices', $data);
        if (!$invoiceId) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to create invoice'];
            header('Location: invoices.php?action=add');
            exit;
        }
        $msg = 'created';
    }
    
    // Save line items
    foreach ($items as $index => $item) {
        $itemQuantity = isset($item['quantity']) ? (float)$item['quantity'] : 0;
        $itemUnitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
        $itemDiscount = isset($item['discount']) ? (float)$item['discount'] : 0;
        $itemTaxRate = isset($item['tax_rate']) ? (float)$item['tax_rate'] : 0;
        
        $itemTotal = $itemQuantity * $itemUnitPrice - $itemDiscount;
        $itemTax = $itemTotal * ($itemTaxRate / 100);
        
        db()->insert('invoice_items', [
            'invoice_id' => $invoiceId,
            'item_type' => $item['type'] ?? 'service',
            'description' => $item['description'] ?? '',
            'quantity' => $itemQuantity,
            'unit_price' => $itemUnitPrice,
            'discount' => $itemDiscount,
            'tax_rate' => $itemTaxRate,
            'tax_amount' => $itemTax,
            'total' => $itemTotal + $itemTax,
            'sort_order' => $index
        ]);
    }
    
    // Send invoice notification if status is sent
    if ($data['status'] === 'sent') {
        $client = db()->fetch("SELECT * FROM clients WHERE id = ?", [$data['client_id']]);
        if ($client) {
            // Send email logic here
            // mailer()->sendTemplate('invoice_created', [...]);
        }
    }
    
    // Generate PDF
    generateInvoicePDF($invoiceId);
    
    // Log activity
    logActivity('invoice', $invoiceId, 'Invoice ' . ($msg === 'created' ? 'created' : 'updated'));
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Invoice ' . ($msg === 'created' ? 'created' : 'updated') . ' successfully'];
    header('Location: invoices.php');
    exit;
}

function generateInvoiceNumber() {
    $year  = date('Y');
    $month = date('m');

    // Get last invoice for this month
    $lastInvoice = db()->fetch("
        SELECT invoice_number FROM project_invoices
        WHERE invoice_number LIKE 'INV-$year$month-%'
        ORDER BY id DESC LIMIT 1
    ");

    if ($lastInvoice) {
        $num = intval(substr($lastInvoice['invoice_number'], -4)) + 1;
    } else {
        $num = 1;
    }

    // Ensure uniqueness
    while (true) {
        $invoice = "INV-$year$month-" . str_pad($num, 4, '0', STR_PAD_LEFT);

        $exists = db()->fetch(
            "SELECT id FROM project_invoices WHERE invoice_number = ?",
            [$invoice]
        );

        if (!$exists) {
            return $invoice;
        }

        $num++;
    }
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
            if (!is_array($invoice['items'])) {
                $invoice['items'] = [];
            }
        }
    }
}

// Get all invoices
$invoices = db()->fetchAll("
    SELECT i.*, c.company_name, c.contact_person,
           COALESCE((SELECT SUM(amount) FROM invoice_payments WHERE invoice_id = i.id), 0) as total_paid
    FROM project_invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    ORDER BY i.created_at DESC
") ?? [];

// Get clients for dropdown
$clients = db()->fetchAll("SELECT id, company_name FROM clients WHERE status = 'active' ORDER BY company_name") ?? [];

// Get projects for dropdown
$projects = db()->fetchAll("SELECT id, title FROM projects WHERE status != 'completed' ORDER BY created_at DESC") ?? [];

// Get tax rates
$taxRates = db()->fetchAll("SELECT * FROM tax_rates ORDER BY is_default DESC, name") ?? [];

// Get statistics with proper null handling
$stats = db()->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END), 0) as total_paid,
        COALESCE(SUM(CASE WHEN status != 'paid' AND status != 'cancelled' THEN balance_due ELSE 0 END), 0) as outstanding
    FROM project_invoices
") ?? [];

// Ensure all values are set with defaults
$stats = array_merge([
    'total' => 0,
    'draft' => 0,
    'sent' => 0,
    'paid' => 0,
    'overdue' => 0,
    'cancelled' => 0,
    'total_paid' => 0,
    'outstanding' => 0
], $stats);

// Convert any null values to 0
foreach ($stats as $key => $value) {
    if ($value === null) {
        $stats[$key] = 0;
    }
}

// Include header
require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="content-header">
    <h1>
        <i class="fas fa-file-invoice"></i> 
        <?php 
        if ($action === 'view') echo 'Invoice Details';
        elseif ($action === 'edit') echo 'Edit Invoice';
        elseif ($action === 'add') echo 'Create New Invoice';
        else echo 'Invoice Management';
        ?>
    </h1>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Invoice
        </a>
        <a href="?action=recurring" class="btn btn-outline">
            <i class="fas fa-repeat"></i> Recurring
        </a>
        <?php elseif ($action === 'view' && $invoice): ?>
        <a href="?action=edit&id=<?php echo $invoice['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
        <button class="btn btn-outline" onclick="sendInvoice(<?php echo $invoice['id']; ?>)">
            <i class="fas fa-paper-plane"></i> Send
        </button>
        <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-outline">
            <i class="fas fa-download"></i> PDF
        </a>
        <?php else: ?>
        <a href="invoices.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <?php endif; ?>
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

<?php if ($action === 'list'): ?>
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Total Invoices</span>
                <span class="stat-value"><?php echo (int)$stats['total']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Outstanding</span>
                <span class="stat-value">$<?php echo number_format((float)$stats['outstanding'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Paid</span>
                <span class="stat-value">$<?php echo number_format((float)$stats['total_paid'], 2); ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Overdue</span>
                <span class="stat-value"><?php echo (int)$stats['overdue']; ?></span>
            </div>
        </div>
    </div>
    
    <!-- Status Tabs (Mobile Scrollable) -->
    <div class="status-tabs-wrapper">
        <div class="status-tabs">
            <a href="?status=all" class="status-tab <?php echo !isset($_GET['status']) || $_GET['status'] === 'all' ? 'active' : ''; ?>">
                All <span class="count">(<?php echo (int)$stats['total']; ?>)</span>
            </a>
            <a href="?status=draft" class="status-tab <?php echo ($_GET['status'] ?? '') === 'draft' ? 'active' : ''; ?>">
                Draft <span class="count">(<?php echo (int)$stats['draft']; ?>)</span>
            </a>
            <a href="?status=sent" class="status-tab <?php echo ($_GET['status'] ?? '') === 'sent' ? 'active' : ''; ?>">
                Sent <span class="count">(<?php echo (int)$stats['sent']; ?>)</span>
            </a>
            <a href="?status=paid" class="status-tab <?php echo ($_GET['status'] ?? '') === 'paid' ? 'active' : ''; ?>">
                Paid <span class="count">(<?php echo (int)$stats['paid']; ?>)</span>
            </a>
            <a href="?status=overdue" class="status-tab <?php echo ($_GET['status'] ?? '') === 'overdue' ? 'active' : ''; ?>">
                Overdue <span class="count">(<?php echo (int)$stats['overdue']; ?>)</span>
            </a>
        </div>
    </div>

    <!-- Desktop Table View -->
    <div class="desktop-table">
        <div class="table-responsive">
            <table class="data-table">
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
                        <th width="140">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $filter = $_GET['status'] ?? 'all';
                    foreach ($invoices as $inv): 
                        if ($filter !== 'all' && $inv['status'] !== $filter) continue;
                        
                        $isOverdue = $inv['status'] === 'sent' && strtotime($inv['due_date']) < time();
                        $rowClass = $isOverdue ? 'overdue-row' : '';
                        $totalPaid = isset($inv['total_paid']) ? (float)$inv['total_paid'] : 0;
                        $balance = (float)$inv['total'] - $totalPaid;
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($inv['invoice_number'] ?? 'N/A'); ?></strong>
                        </td>
                        <td>
                            <div class="client-info">
                                <strong><?php echo htmlspecialchars($inv['company_name'] ?: 'N/A'); ?></strong>
                                <br><small><?php echo htmlspecialchars($inv['contact_person'] ?? ''); ?></small>
                            </div>
                        </td>
                        <td><?php echo isset($inv['invoice_date']) ? date('M d, Y', strtotime($inv['invoice_date'])) : 'N/A'; ?></td>
                        <td>
                            <div class="due-date">
                                <?php echo isset($inv['due_date']) ? date('M d, Y', strtotime($inv['due_date'])) : 'N/A'; ?>
                                <?php if ($isOverdue): ?>
                                <span class="overdue-badge">Overdue</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><strong>$<?php echo number_format((float)$inv['total'], 2); ?></strong></td>
                        <td>$<?php echo number_format($totalPaid, 2); ?></td>
                        <td>
                            <span class="balance <?php echo $balance > 0 ? 'due' : 'paid'; ?>">
                                $<?php echo number_format($balance, 2); ?>
                            </span>
                        </td>
                        <td>
                            <select class="status-select status-<?php echo $inv['status']; ?>" 
                                    onchange="updateStatus(<?php echo $inv['id']; ?>, this.value)">
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
                        <td colspan="9" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-file-invoice"></i>
                                <h3>No Invoices Found</h3>
                                <p>Create your first invoice to get started.</p>
                                <a href="?action=add" class="btn btn-primary">Create Invoice</a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile Cards View -->
    <div class="mobile-cards">
        <?php 
        $filter = $_GET['status'] ?? 'all';
        foreach ($invoices as $inv): 
            if ($filter !== 'all' && $inv['status'] !== $filter) continue;
            
            $isOverdue = $inv['status'] === 'sent' && strtotime($inv['due_date']) < time();
            $totalPaid = isset($inv['total_paid']) ? (float)$inv['total_paid'] : 0;
            $balance = (float)$inv['total'] - $totalPaid;
        ?>
        <div class="invoice-card status-<?php echo $inv['status']; ?> <?php echo $isOverdue ? 'overdue' : ''; ?>">
            <div class="card-header">
                <div class="invoice-number">
                    <strong>#<?php echo htmlspecialchars($inv['invoice_number'] ?? 'N/A'); ?></strong>
                    <span class="status-badge <?php echo $inv['status']; ?>">
                        <?php echo ucfirst($inv['status'] ?? 'draft'); ?>
                    </span>
                </div>
                <div class="invoice-amount">
                    <span class="amount">$<?php echo number_format((float)$inv['total'], 2); ?></span>
                </div>
            </div>
            
            <div class="card-body">
                <div class="info-row">
                    <i class="fas fa-building"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($inv['company_name'] ?: 'N/A'); ?></strong>
                        <br><small><?php echo htmlspecialchars($inv['contact_person'] ?? ''); ?></small>
                    </div>
                </div>
                
                <div class="info-row">
                    <i class="fas fa-calendar"></i>
                    <div class="date-info">
                        <span>Issued: <?php echo isset($inv['invoice_date']) ? date('M d, Y', strtotime($inv['invoice_date'])) : 'N/A'; ?></span>
                        <br>
                        <span class="due-date">Due: <?php echo isset($inv['due_date']) ? date('M d, Y', strtotime($inv['due_date'])) : 'N/A'; ?></span>
                        <?php if ($isOverdue): ?>
                        <span class="overdue-badge">Overdue</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="payment-info">
                    <div class="payment-row">
                        <span>Paid:</span>
                        <strong>$<?php echo number_format($totalPaid, 2); ?></strong>
                    </div>
                    <div class="payment-row">
                        <span>Balance:</span>
                        <strong class="<?php echo $balance > 0 ? 'due' : 'paid'; ?>">
                            $<?php echo number_format($balance, 2); ?>
                        </strong>
                    </div>
                </div>
            </div>
            
            <div class="card-footer">
                <div class="action-buttons">
                    <a href="?action=view&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="?action=edit&id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="download-invoice.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-download"></i> PDF
                    </a>
                    <select class="status-select-mobile" onchange="updateStatus(<?php echo $inv['id']; ?>, this.value)">
                        <option value="draft" <?php echo $inv['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="sent" <?php echo $inv['status'] === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="paid" <?php echo $inv['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="overdue" <?php echo $inv['status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <option value="cancelled" <?php echo $inv['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($invoices)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <h3>No Invoices Found</h3>
            <p>Create your first invoice to get started.</p>
            <a href="?action=add" class="btn btn-primary">Create Invoice</a>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Invoice Form -->
    <div class="form-container invoice-form">
        <form method="POST" id="invoiceForm" class="admin-form">
            <?php if ($invoice): ?>
            <input type="hidden" name="id" value="<?php echo $invoice['id']; ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <h2><i class="fas fa-info-circle"></i> Invoice Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_number">Invoice Number</label>
                        <input type="text" id="invoice_number" name="invoice_number" 
                               value="<?php echo htmlspecialchars($invoice['invoice_number'] ?? generateInvoiceNumber()); ?>"
                               placeholder="Auto-generated if empty">
                    </div>
                    
                    <div class="form-group">
                        <label for="client_id">Client <span class="required">*</span></label>
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
                        <label for="project_id">Related Project</label>
                        <select id="project_id" name="project_id">
                            <option value="">-- Optional --</option>
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
                            <option value="USD" <?php echo ($invoice['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD</option>
                            <option value="EUR" <?php echo ($invoice['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR</option>
                            <option value="GBP" <?php echo ($invoice['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_date">Invoice Date <span class="required">*</span></label>
                        <input type="date" id="invoice_date" name="invoice_date" required 
                               value="<?php echo htmlspecialchars($invoice['invoice_date'] ?? date('Y-m-d')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date <span class="required">*</span></label>
                        <input type="date" id="due_date" name="due_date" required 
                               value="<?php echo htmlspecialchars($invoice['due_date'] ?? date('Y-m-d', strtotime('+30 days'))); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="payment_terms">Payment Terms</label>
                    <select id="payment_terms" name="payment_terms">
                        <option value="due_on_receipt" <?php echo ($invoice['payment_terms'] ?? '') === 'due_on_receipt' ? 'selected' : ''; ?>>Due on Receipt</option>
                        <option value="net_15" <?php echo ($invoice['payment_terms'] ?? '') === 'net_15' ? 'selected' : ''; ?>>Net 15</option>
                        <option value="net_30" <?php echo ($invoice['payment_terms'] ?? '') === 'net_30' ? 'selected' : ''; ?>>Net 30</option>
                    </select>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-map-marker-alt"></i> Addresses</h2>
                
                <div class="form-group">
                    <label for="bill_to">Bill To <span class="required">*</span></label>
                    <textarea id="bill_to" name="bill_to" rows="4" required placeholder="Client's billing address"><?php echo htmlspecialchars($invoice['bill_to'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="ship_to">Ship To (if different)</label>
                    <textarea id="ship_to" name="ship_to" rows="4" placeholder="Shipping address if different"><?php echo htmlspecialchars($invoice['ship_to'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-list"></i> Invoice Items</h2>
                
                <div id="invoice-items" class="items-container">
                    <!-- Items will be added here via JavaScript -->
                </div>
                
                <button type="button" class="btn btn-outline btn-sm add-item-btn" onclick="addItem()">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-calculator"></i> Summary</h2>
                
                <div class="summary-calculations">
                    <div class="calc-row">
                        <span>Subtotal:</span>
                        <span id="calc-subtotal">$0.00</span>
                    </div>
                    
                    <div class="calc-row">
                        <span>Tax:</span>
                        <span class="calc-input-group">
                            <select id="tax_rate" name="tax_rate" onchange="calculateTotals()">
                                <option value="0">No Tax</option>
                                <?php foreach ($taxRates as $tax): ?>
                                <option value="<?php echo $tax['rate']; ?>" <?php echo $tax['is_default'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tax['name']); ?> (<?php echo $tax['rate']; ?>%)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <span id="calc-tax">$0.00</span>
                        </span>
                    </div>
                    
                    <div class="calc-row">
                        <span>Discount:</span>
                        <span class="calc-input-group">
                            <select id="discount_type" name="discount_type" onchange="calculateTotals()">
                                <option value="none">No Discount</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed ($)</option>
                            </select>
                            <input type="number" id="discount_value" name="discount_value" 
                                   value="0" min="0" step="0.01" onchange="calculateTotals()" placeholder="0">
                            <span id="calc-discount">$0.00</span>
                        </span>
                    </div>
                    
                    <div class="calc-row">
                        <span>Shipping:</span>
                        <span class="calc-input-group">
                            <input type="number" id="shipping_amount" name="shipping_amount" 
                                   value="0" min="0" step="0.01" onchange="calculateTotals()" placeholder="0.00">
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
                <h2><i class="fas fa-file-alt"></i> Additional Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tax_id">Tax ID / VAT Number</label>
                        <input type="text" id="tax_id" name="tax_id" value="<?php echo htmlspecialchars($invoice['tax_id'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="business_number">Business Number</label>
                        <input type="text" id="business_number" name="business_number" value="<?php echo htmlspecialchars($invoice['business_number'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (visible to client)</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Any notes or instructions for the client"><?php echo htmlspecialchars($invoice['notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="terms_conditions">Terms & Conditions</label>
                    <textarea id="terms_conditions" name="terms_conditions" rows="3"><?php echo htmlspecialchars($invoice['terms_conditions'] ?? 'Payment is due within 30 days. Thank you for your business.'); ?></textarea>
                </div>
                
                <div class="form-row">
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
                               value="<?php echo htmlspecialchars($invoice['paid_amount'] ?? 0); ?>">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_invoice" class="btn btn-primary btn-lg">
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
            <div class="invoice-status-badge <?php echo $invoice['status']; ?>">
                <?php echo strtoupper($invoice['status'] ?? 'DRAFT'); ?>
            </div>
            
            <div class="invoice-actions">
                <button class="btn btn-outline" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>
        </div>
        
        <div class="invoice-paper">
            <div class="invoice-paper-header">
                <div class="company-info">
                    <h2><?php echo SITE_NAME; ?></h2>
                    <p><?php echo htmlspecialchars(getSetting('address') ?? ''); ?></p>
                    <p>Email: <?php echo htmlspecialchars(getSetting('contact_email') ?? ''); ?></p>
                    <p>Phone: <?php echo htmlspecialchars(getSetting('contact_phone') ?? ''); ?></p>
                </div>
                
                <div class="invoice-title">
                    <h1>INVOICE</h1>
                    <h3><?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></h3>
                </div>
            </div>
            
            <div class="invoice-paper-body">
                <div class="invoice-dates">
                    <div class="date-box">
                        <span class="label">Invoice Date:</span>
                        <span class="value"><?php echo isset($invoice['invoice_date']) ? date('F d, Y', strtotime($invoice['invoice_date'])) : 'N/A'; ?></span>
                    </div>
                    <div class="date-box">
                        <span class="label">Due Date:</span>
                        <span class="value"><?php echo isset($invoice['due_date']) ? date('F d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?></span>
                    </div>
                </div>
                
                <div class="invoice-addresses">
                    <div class="bill-to">
                        <h4>Bill To:</h4>
                        <p><?php echo nl2br(htmlspecialchars($invoice['bill_to'] ?? '')); ?></p>
                    </div>
                    
                    <?php if (!empty($invoice['ship_to'])): ?>
                    <div class="ship-to">
                        <h4>Ship To:</h4>
                        <p><?php echo nl2br(htmlspecialchars($invoice['ship_to'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="table-responsive">
                    <table class="invoice-items-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Discount</th>
                                <th class="text-right">Tax</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $items = db()->fetchAll("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order", [$invoice['id']]);
                            foreach ($items as $item): 
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                                <td class="text-center"><?php echo isset($item['quantity']) ? (float)$item['quantity'] : 0; ?></td>
                                <td class="text-right">$<?php echo number_format((float)($item['unit_price'] ?? 0), 2); ?></td>
                                <td class="text-right"><?php echo !empty($item['discount']) ? '$' . number_format((float)$item['discount'], 2) : '-'; ?></td>
                                <td class="text-right"><?php echo !empty($item['tax_rate']) ? $item['tax_rate'] . '%' : '-'; ?></td>
                                <td class="text-right"><strong>$<?php echo number_format((float)($item['total'] ?? 0), 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="invoice-summary">
                    <div class="summary-left">
                        <?php if (!empty($invoice['notes'])): ?>
                        <div class="invoice-notes">
                            <h4>Notes:</h4>
                            <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($invoice['terms_conditions'])): ?>
                        <div class="invoice-terms">
                            <h4>Terms & Conditions:</h4>
                            <p><?php echo nl2br(htmlspecialchars($invoice['terms_conditions'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="summary-right">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format((float)($invoice['subtotal'] ?? 0), 2); ?></span>
                        </div>
                        
                        <?php if (!empty($invoice['tax_amount']) && $invoice['tax_amount'] > 0): ?>
                        <div class="summary-row">
                            <span>Tax (<?php echo (float)($invoice['tax_rate'] ?? 0); ?>%):</span>
                            <span>$<?php echo number_format((float)$invoice['tax_amount'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($invoice['discount_amount']) && $invoice['discount_amount'] > 0): ?>
                        <div class="summary-row">
                            <span>Discount:</span>
                            <span>-$<?php echo number_format((float)$invoice['discount_amount'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($invoice['shipping_amount']) && $invoice['shipping_amount'] > 0): ?>
                        <div class="summary-row">
                            <span>Shipping:</span>
                            <span>$<?php echo number_format((float)$invoice['shipping_amount'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>$<?php echo number_format((float)($invoice['total'] ?? 0), 2); ?></span>
                        </div>
                        
                        <?php 
                        $totalPaid = (float)($invoice['paid_amount'] ?? 0);
                        $balanceDue = (float)($invoice['balance_due'] ?? (($invoice['total'] ?? 0) - $totalPaid));
                        if ($totalPaid > 0): 
                        ?>
                        <div class="summary-row paid">
                            <span>Paid:</span>
                            <span>$<?php echo number_format($totalPaid, 2); ?></span>
                        </div>
                        <div class="summary-row balance">
                            <span>Balance Due:</span>
                            <span>$<?php echo number_format($balanceDue, 2); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="invoice-paper-footer">
                <p>Thank you for your business!</p>
                <?php if (!empty($invoice['tax_id'])): ?>
                <p>Tax ID: <?php echo htmlspecialchars($invoice['tax_id']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Payment History -->
        <?php
        $payments = db()->fetchAll("SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY payment_date DESC", [$invoice['id']]);
        if (!empty($payments)):
        ?>
        <div class="payment-history">
            <h3>Payment History</h3>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Payment #</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo isset($payment['payment_date']) ? date('M d, Y', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($payment['payment_number'] ?? 'N/A'); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'] ?? 'unknown')); ?></td>
                            <td><strong>$<?php echo number_format((float)($payment['amount'] ?? 0), 2); ?></strong></td>
                            <td>
                                <span class="status-badge <?php echo $payment['status'] ?? 'unknown'; ?>">
                                    <?php echo ucfirst($payment['status'] ?? 'unknown'); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
/* ========================================
   INVOICES PAGE - RESPONSIVE STYLES
   ======================================== */

:root {
    --primary: #2563eb;
    --secondary: #7c3aed;
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

/* Content Header */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.content-header h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.content-header h1 i {
    color: var(--primary);
}

.header-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

/* Buttons */
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
    line-height: 1;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37,99,235,0.2);
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

.btn-lg {
    padding: 14px 28px;
    font-size: 16px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.blue { background: rgba(37,99,235,0.1); color: #2563eb; }
.stat-icon.orange { background: rgba(245,158,11,0.1); color: #f59e0b; }
.stat-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-icon.red { background: rgba(239,68,68,0.1); color: #ef4444; }

.stat-details {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 14px;
    color: var(--gray-500);
    margin-bottom: 4px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

/* Status Tabs */
.status-tabs-wrapper {
    overflow-x: auto;
    margin-bottom: 20px;
    -webkit-overflow-scrolling: touch;
}

.status-tabs {
    display: flex;
    gap: 8px;
    padding: 4px;
    background: var(--gray-100);
    border-radius: 12px;
    min-width: min-content;
}

.status-tab {
    padding: 10px 20px;
    color: var(--gray-600);
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-tab .count {
    font-size: 12px;
    color: var(--gray-500);
}

.status-tab:hover,
.status-tab.active {
    background: white;
    color: var(--primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Desktop Table */
.desktop-table {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.data-table th {
    background: var(--gray-100);
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 16px;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
    font-size: 14px;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background: var(--gray-50);
}

.overdue-row {
    background: rgba(239,68,68,0.05);
}

.client-info small {
    color: var(--gray-500);
    font-size: 12px;
}

.due-date {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.overdue-badge {
    display: inline-block;
    padding: 2px 8px;
    background: var(--danger);
    color: white;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.balance {
    font-weight: 600;
}

.balance.due {
    color: var(--danger);
}

.balance.paid {
    color: var(--success);
}

/* Status Select */
.status-select {
    padding: 6px 10px;
    border: 2px solid transparent;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    outline: none;
}

.status-select.status-draft { background: var(--gray-200); color: var(--gray-700); }
.status-select.status-sent { background: #dbeafe; color: #1e40af; }
.status-select.status-paid { background: #d1fae5; color: #065f46; }
.status-select.status-overdue { background: #fee2e2; color: #991b1b; }
.status-select.status-cancelled { background: #f3f4f6; color: #6b7280; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.action-btn {
    width: 36px;
    height: 36px;
    background: var(--gray-100);
    border-radius: 8px;
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
    gap: 16px;
    margin-bottom: 30px;
}

.invoice-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    border-left: 4px solid transparent;
}

.invoice-card.status-draft { border-left-color: var(--gray-500); }
.invoice-card.status-sent { border-left-color: var(--primary); }
.invoice-card.status-paid { border-left-color: var(--success); }
.invoice-card.status-overdue { border-left-color: var(--danger); }
.invoice-card.overdue { background: rgba(239,68,68,0.05); }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.invoice-number {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.invoice-number strong {
    font-size: 16px;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.draft { background: var(--gray-200); color: var(--gray-700); }
.status-badge.sent { background: #dbeafe; color: #1e40af; }
.status-badge.paid { background: #d1fae5; color: #065f46; }
.status-badge.overdue { background: #fee2e2; color: #991b1b; }
.status-badge.cancelled { background: #f3f4f6; color: #6b7280; }

.invoice-amount .amount {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark);
}

.card-body {
    margin-bottom: 16px;
}

.info-row {
    display: flex;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-200);
}

.info-row i {
    width: 20px;
    color: var(--primary);
    font-size: 16px;
}

.info-row > div {
    flex: 1;
}

.date-info {
    font-size: 13px;
}

.payment-info {
    background: var(--gray-100);
    padding: 12px;
    border-radius: 8px;
    margin-top: 12px;
}

.payment-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
}

.payment-row strong.due {
    color: var(--danger);
}

.payment-row strong.paid {
    color: var(--success);
}

.card-footer .action-buttons {
    flex-wrap: wrap;
    gap: 8px;
}

.status-select-mobile {
    padding: 8px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 13px;
    background: white;
    flex: 1;
    min-width: 120px;
}

/* Invoice View */
.invoice-view {
    max-width: 1000px;
    margin: 0 auto;
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.invoice-status-badge {
    padding: 8px 20px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 14px;
}

.invoice-status-badge.draft { background: var(--gray-200); color: var(--gray-700); }
.invoice-status-badge.sent { background: #dbeafe; color: #1e40af; }
.invoice-status-badge.paid { background: #d1fae5; color: #065f46; }
.invoice-status-badge.overdue { background: #fee2e2; color: #991b1b; }

.invoice-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.invoice-paper {
    background: white;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.invoice-paper-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--gray-200);
    flex-wrap: wrap;
    gap: 20px;
}

.company-info h2 {
    color: var(--primary);
    font-size: 24px;
    margin-bottom: 8px;
}

.company-info p {
    color: var(--gray-600);
    font-size: 13px;
    margin: 2px 0;
}

.invoice-title {
    text-align: right;
}

.invoice-title h1 {
    color: var(--primary);
    font-size: 32px;
    margin-bottom: 4px;
}

.invoice-title h3 {
    color: var(--gray-500);
    font-weight: normal;
}

.invoice-dates {
    display: flex;
    gap: 30px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.date-box {
    background: var(--gray-100);
    padding: 12px 20px;
    border-radius: 8px;
    flex: 1;
    min-width: 200px;
}

.date-box .label {
    display: block;
    font-size: 12px;
    color: var(--gray-500);
    margin-bottom: 4px;
}

.date-box .value {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
}

.invoice-addresses {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.bill-to h4,
.ship-to h4 {
    color: var(--dark);
    margin-bottom: 8px;
    font-size: 14px;
}

.bill-to p,
.ship-to p {
    color: var(--gray-600);
    line-height: 1.6;
    font-size: 13px;
}

.invoice-items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    font-size: 13px;
}

.invoice-items-table th {
    background: var(--gray-100);
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
}

.invoice-items-table td {
    padding: 12px;
    border-bottom: 1px solid var(--gray-200);
}

.text-center { text-align: center; }
.text-right { text-align: right; }

.invoice-summary {
    display: flex;
    justify-content: space-between;
    gap: 30px;
    flex-wrap: wrap;
}

.summary-left {
    flex: 1;
    min-width: 300px;
}

.invoice-notes,
.invoice-terms {
    margin-bottom: 20px;
}

.invoice-notes h4,
.invoice-terms h4 {
    color: var(--dark);
    margin-bottom: 8px;
    font-size: 14px;
}

.invoice-notes p,
.invoice-terms p {
    color: var(--gray-600);
    font-size: 13px;
    line-height: 1.5;
}

.summary-right {
    width: 300px;
    background: var(--gray-100);
    padding: 20px;
    border-radius: 12px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-300);
    font-size: 14px;
}

.summary-row.total {
    font-weight: 700;
    font-size: 16px;
    color: var(--primary);
    border-bottom: none;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 2px solid var(--gray-400);
}

.summary-row.paid {
    color: var(--success);
}

.summary-row.balance {
    font-weight: 700;
    color: var(--danger);
}

.invoice-paper-footer {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
    text-align: center;
    color: var(--gray-500);
    font-size: 13px;
}

.payment-history {
    margin-top: 30px;
}

.payment-history h3 {
    font-size: 18px;
    margin-bottom: 16px;
}

/* Form Styles */
.invoice-form {
    max-width: 1200px;
    margin: 0 auto;
}

.form-section {
    background: white;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.form-section h2 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--dark);
}

.form-section h2 i {
    color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--dark);
    font-size: 14px;
}

.required {
    color: var(--danger);
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="date"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

/* Items Container */
.items-container {
    margin-bottom: 16px;
}

.item-row {
    background: var(--gray-100);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    border-left: 4px solid var(--primary);
}

.item-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 10px;
    margin-bottom: 10px;
}

.item-fields select,
.item-fields input {
    padding: 8px;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
    font-size: 13px;
}

.remove-item {
    width: 32px;
    height: 32px;
    background: var(--danger);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.remove-item:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.item-total {
    text-align: right;
    font-weight: 600;
    color: var(--primary);
    padding-top: 8px;
    border-top: 1px dashed var(--gray-300);
}

.add-item-btn {
    width: 100%;
    padding: 12px;
    border: 2px dashed var(--primary);
    background: transparent;
    color: var(--primary);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.add-item-btn:hover {
    background: rgba(37,99,235,0.1);
}

/* Summary Calculations */
.summary-calculations {
    max-width: 500px;
    margin-left: auto;
    background: var(--gray-100);
    padding: 20px;
    border-radius: 12px;
}

.calc-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-300);
    flex-wrap: wrap;
    gap: 10px;
}

.calc-row:last-child {
    border-bottom: none;
}

.calc-row.total {
    font-weight: 700;
    font-size: 18px;
    color: var(--primary);
    margin-top: 10px;
    padding-top: 10px;
    border-top: 2px solid var(--gray-400);
}

.calc-input-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.calc-input-group select,
.calc-input-group input {
    padding: 6px;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
    font-size: 13px;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    margin-top: 24px;
}

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: rgba(16,185,129,0.1);
    color: #065f46;
    border: 1px solid rgba(16,185,129,0.2);
}

.alert-error {
    background: rgba(239,68,68,0.1);
    color: #991b1b;
    border: 1px solid rgba(239,68,68,0.2);
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 48px;
    color: var(--gray-300);
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--gray-500);
    margin-bottom: 24px;
}

/* Responsive Breakpoints */
@media (max-width: 1023px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .invoice-addresses {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

@media (max-width: 767px) {
    .content-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .header-actions .btn {
        flex: 1;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .desktop-table {
        display: none;
    }
    
    .mobile-cards {
        display: flex;
    }
    
    .invoice-paper {
        padding: 20px;
    }
    
    .invoice-paper-header {
        flex-direction: column;
        text-align: center;
    }
    
    .invoice-title {
        text-align: center;
    }
    
    .invoice-dates {
        flex-direction: column;
        gap: 10px;
    }
    
    .invoice-summary {
        flex-direction: column;
    }
    
    .summary-right {
        width: 100%;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .item-fields {
        grid-template-columns: 1fr;
    }
    
    .calc-row {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .calc-input-group {
        width: 100%;
    }
    
    .calc-input-group select,
    .calc-input-group input {
        flex: 1;
    }
}

@media (max-width: 480px) {
    .header-actions {
        flex-direction: column;
    }
    
    .header-actions .btn {
        width: 100%;
    }
    
    .invoice-card .action-buttons {
        flex-direction: column;
    }
    
    .invoice-card .btn,
    .status-select-mobile {
        width: 100%;
    }
    
    .invoice-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .invoice-actions .btn {
        width: 100%;
    }
    
    .form-section {
        padding: 16px;
    }
}

/* Print */
@media print {
    .admin-sidebar,
    .admin-header,
    .content-header,
    .invoice-header,
    .payment-history,
    .form-actions,
    .action-buttons,
    .header-actions,
    .stats-grid,
    .status-tabs-wrapper,
    .mobile-cards {
        display: none !important;
    }
    
    .desktop-table {
        display: block !important;
    }
    
    .invoice-paper {
        box-shadow: none;
        padding: 0;
    }
}
</style>

<script>
let items = [];

<?php if ($invoice && !empty($invoice['items']) && is_array($invoice['items'])): ?>
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
    if (confirm('Remove this item?')) {
        items.splice(index, 1);
        renderItems();
        calculateTotals();
    }
}

function renderItems() {
    const container = document.getElementById('invoice-items');
    if (!container) return;
    
    let html = '';
    
    items.forEach((item, index) => {
        html += `
            <div class="item-row" data-index="${index}">
                <div class="item-fields">
                    <select class="item-type" onchange="updateItem(${index}, 'type', this.value)">
                        <option value="service" ${item.type === 'service' ? 'selected' : ''}>Service</option>
                        <option value="product" ${item.type === 'product' ? 'selected' : ''}>Product</option>
                        <option value="hourly" ${item.type === 'hourly' ? 'selected' : ''}>Hourly</option>
                    </select>
                    
                    <input type="text" class="item-description" placeholder="Description" 
                           value="${(item.description || '').replace(/"/g, '&quot;')}" 
                           onchange="updateItem(${index}, 'description', this.value)">
                    
                    <input type="number" class="item-quantity" placeholder="Qty" 
                           value="${item.quantity || 1}" min="0" step="0.01"
                           onchange="updateItem(${index}, 'quantity', parseFloat(this.value) || 0)">
                    
                    <input type="number" class="item-price" placeholder="Unit Price" 
                           value="${item.unit_price || 0}" min="0" step="0.01"
                           onchange="updateItem(${index}, 'unit_price', parseFloat(this.value) || 0)">
                    
                    <input type="number" class="item-discount" placeholder="Discount" 
                           value="${item.discount || 0}" min="0" step="0.01"
                           onchange="updateItem(${index}, 'discount', parseFloat(this.value) || 0)">
                    
                    <input type="number" class="item-tax" placeholder="Tax %" 
                           value="${item.tax_rate || 0}" min="0" step="0.01"
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
    // Ensure numeric fields are numbers
    if (['quantity', 'unit_price', 'discount', 'tax_rate'].includes(field)) {
        value = parseFloat(value) || 0;
    }
    items[index][field] = value;
    calculateTotals();
    renderItems();
}

function calculateItemTotal(item) {
    const quantity = parseFloat(item.quantity) || 0;
    const unitPrice = parseFloat(item.unit_price) || 0;
    const discount = parseFloat(item.discount) || 0;
    const taxRate = parseFloat(item.tax_rate) || 0;
    
    const subtotal = quantity * unitPrice;
    const afterDiscount = subtotal - discount;
    const tax = afterDiscount * (taxRate / 100);
    return afterDiscount + tax;
}

function calculateTotals() {
    let subtotal = 0;
    
    items.forEach(item => {
        const quantity = parseFloat(item.quantity) || 0;
        const unitPrice = parseFloat(item.unit_price) || 0;
        subtotal += quantity * unitPrice;
    });
    
    const taxRate = parseFloat(document.getElementById('tax_rate')?.value) || 0;
    const taxAmount = subtotal * (taxRate / 100);
    
    const discountType = document.getElementById('discount_type')?.value || 'none';
    const discountValue = parseFloat(document.getElementById('discount_value')?.value) || 0;
    let discountAmount = 0;
    
    if (discountType === 'percentage') {
        discountAmount = subtotal * (discountValue / 100);
    } else if (discountType === 'fixed') {
        discountAmount = discountValue;
    }
    
    const shippingAmount = parseFloat(document.getElementById('shipping_amount')?.value) || 0;
    const total = subtotal + taxAmount - discountAmount + shippingAmount;
    
    // Update display
    document.getElementById('calc-subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('calc-tax').textContent = '$' + taxAmount.toFixed(2);
    document.getElementById('calc-discount').textContent = '-$' + discountAmount.toFixed(2);
    document.getElementById('calc-total').textContent = '$' + total.toFixed(2);
    
    // Update hidden fields
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('tax_amount').value = taxAmount.toFixed(2);
    document.getElementById('discount_amount').value = discountAmount.toFixed(2);
    document.getElementById('total').value = total.toFixed(2);
    
    // Ensure items is valid JSON before stringifying
    const cleanItems = items.map(item => ({
        type: item.type || 'service',
        description: item.description || '',
        quantity: parseFloat(item.quantity) || 1,
        unit_price: parseFloat(item.unit_price) || 0,
        discount: parseFloat(item.discount) || 0,
        tax_rate: parseFloat(item.tax_rate) || 0
    }));
    
    document.getElementById('items-json').value = JSON.stringify(cleanItems);
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
        })
        .catch(error => console.error('Error loading client details:', error));
}

function updateStatus(invoiceId, status) {
    if (confirm('Update invoice status to ' + status + '?')) {
        window.location.href = `invoices.php?status=${status}&id=${invoiceId}`;
    }
}

function sendInvoice(invoiceId) {
    if (confirm('Send this invoice to the client?')) {
        window.location.href = `send-invoice.php?id=${invoiceId}`;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('invoice-items')) {
        if (items.length === 0) {
            addItem();
        } else {
            renderItems();
            calculateTotals();
        }
    }
    
    // Load client details if editing
    const clientSelect = document.getElementById('client_id');
    if (clientSelect && clientSelect.value) {
        loadClientDetails(clientSelect.value);
    }
    
    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
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

window.addEventListener('load', handleResponsive);
window.addEventListener('resize', handleResponsive);
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>