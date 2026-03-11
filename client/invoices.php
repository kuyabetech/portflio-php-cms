<?php
/**
 * Client Invoices - View all invoices
 * PHP 8+ Compatible with enhanced features
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

$clientUserId = $_SESSION['client_id'];

// Get client user information
$clientUser = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientUserId]);

if (!$clientUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get client company ID
$companyId = $clientUser['client_id'] ?? 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filter
$status = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$where = ["1=1"];
$params = [];

// Only show invoices if client has a company ID
if ($companyId > 0) {
    $where[] = "client_id = ?";
    $params[] = $companyId;
} else {
    // No company assigned, show no invoices
    $where[] = "1=0";
}

if ($status !== 'all') {
    $where[] = "status = ?";
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $where[] = "invoice_date >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = "invoice_date <= ?";
    $params[] = $dateTo;
}

$whereClause = implode(' AND ', $where);

// Initialize variables
$totalInvoices = 0;
$totalPages = 0;
$invoices = [];
$totalOutstanding = 0;
$totalPaid = 0;

if ($companyId > 0) {
    try {
        // Get total count
        $countQuery = "SELECT COUNT(*) as count FROM project_invoices WHERE $whereClause";
        $totalInvoices = db()->fetch($countQuery, $params)['count'] ?? 0;
        $totalPages = ceil($totalInvoices / $perPage);

        // Get invoices
        $invoices = db()->fetchAll("
            SELECT * FROM project_invoices 
            WHERE $whereClause 
            ORDER BY 
                CASE 
                    WHEN status = 'pending' AND due_date < CURDATE() THEN 1
                    WHEN status = 'pending' THEN 2
                    WHEN status = 'overdue' THEN 3
                    WHEN status = 'paid' THEN 4
                    ELSE 5
                END,
                due_date ASC 
            LIMIT ? OFFSET ?
        ", array_merge($params, [$perPage, $offset])) ?? [];

        // Calculate totals
        foreach ($invoices as $invoice) {
            if (($invoice['status'] ?? '') === 'paid') {
                $totalPaid += (float)($invoice['total'] ?? 0);
            } else {
                $totalOutstanding += (float)($invoice['total'] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log("Invoices fetch error: " . $e->getMessage());
    }
}

$pageTitle = 'My Invoices';
require_once '../includes/client-header.php';
?>

<div class="invoices-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-file-invoice"></i> My Invoices</h1>
            <p>View and manage your invoices</p>
        </div>
        
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <span class="summary-label">Total Invoices</span>
                <span class="summary-value"><?php echo $totalInvoices; ?></span>
            </div>
            <div class="summary-card">
                <span class="summary-label">Outstanding</span>
                <span class="summary-value">$<?php echo number_format($totalOutstanding, 2); ?></span>
            </div>
            <div class="summary-card">
                <span class="summary-label">Paid</span>
                <span class="summary-value">$<?php echo number_format($totalPaid, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="filter-form" id="filterForm">
            <div class="filter-row">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Invoices</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Apply
                    </button>
                    <a href="invoices.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Invoices List -->
    <?php if (!empty($invoices)): ?>
    <div class="invoices-list">
        <?php foreach ($invoices as $invoice): 
            $isOverdue = ($invoice['status'] ?? '') === 'pending' && !empty($invoice['due_date']) && strtotime($invoice['due_date']) < time();
            $displayStatus = $isOverdue ? 'overdue' : ($invoice['status'] ?? 'pending');
            
            // Parse items safely
            $items = [];
            if (!empty($invoice['items'])) {
                $items = json_decode($invoice['items'], true) ?: [];
            }
        ?>
        <div class="invoice-card status-<?php echo $displayStatus; ?>">
            <div class="invoice-header">
                <div class="invoice-number">
                    <h3>Invoice #<?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></h3>
                    <span class="status-badge status-<?php echo $displayStatus; ?>">
                        <?php echo ucfirst($displayStatus); ?>
                    </span>
                </div>
                <div class="invoice-amount">
                    <span class="amount">$<?php echo number_format((float)($invoice['total'] ?? 0), 2); ?></span>
                </div>
            </div>
            
            <div class="invoice-body">
                <div class="invoice-dates">
                    <div class="date-item">
                        <span class="date-label">Invoice Date:</span>
                        <span class="date-value"><?php echo !empty($invoice['invoice_date']) ? date('M d, Y', strtotime($invoice['invoice_date'])) : 'N/A'; ?></span>
                    </div>
                    <div class="date-item">
                        <span class="date-label">Due Date:</span>
                        <span class="date-value <?php echo $isOverdue ? 'text-danger' : ''; ?>">
                            <?php echo !empty($invoice['due_date']) ? date('M d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?>
                            <?php if ($isOverdue): ?>
                            <span class="overdue-badge">Overdue</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($invoice['notes'])): ?>
                <div class="invoice-notes">
                    <i class="fas fa-sticky-note"></i>
                    <?php echo htmlspecialchars($invoice['notes']); ?>
                </div>
                <?php endif; ?>
                
                <div class="invoice-items-preview">
                    <?php if (!empty($items)): ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Price</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($items, 0, 3) as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['description'] ?? 'Item'); ?></td>
                                <td class="text-right"><?php echo (float)($item['quantity'] ?? 1); ?></td>
                                <td class="text-right">$<?php echo number_format((float)($item['unit_price'] ?? 0), 2); ?></td>
                                <td class="text-right">$<?php echo number_format((float)($item['quantity'] ?? 1) * (float)($item['unit_price'] ?? 0), 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($items) > 3): ?>
                            <tr>
                                <td colspan="4" class="text-muted text-center">
                                    + <?php echo count($items) - 3; ?> more items
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="invoice-footer">
                <div class="payment-summary">
                    <?php if (($invoice['status'] ?? '') === 'paid'): ?>
                    <span class="payment-status paid">
                        <i class="fas fa-check-circle"></i> Paid on <?php echo !empty($invoice['paid_at']) ? date('M d, Y', strtotime($invoice['paid_at'])) : date('M d, Y', strtotime($invoice['created_at'] ?? 'now')); ?>
                    </span>
                    <?php else: ?>
                    <span class="payment-status pending">
                        <i class="fas fa-clock"></i> Awaiting payment
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="invoice-actions">
                    <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>" class="btn-view">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                    <?php if (($invoice['status'] ?? '') !== 'paid'): ?>
                    <a href="pay-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn-pay">
                        Pay Now <i class="fas fa-credit-card"></i>
                    </a>
                    <?php endif; ?>
                    <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn-download" title="Download PDF">
                        <i class="fas fa-download"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="page-link">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php 
        // Show limited page numbers
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        if ($startPage > 1): ?>
        <a href="?page=1&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="page-link">1</a>
        <?php if ($startPage > 2): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <a href="?page=<?php echo $totalPages; ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="page-link">
            <?php echo $totalPages; ?>
        </a>
        <?php endif; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" class="page-link">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Empty State -->
    <div class="empty-state">
        <i class="fas fa-file-invoice"></i>
        <h3>No Invoices Found</h3>
        <?php if ($companyId == 0): ?>
        <p>Your account is not yet associated with any company. Please contact support.</p>
        <?php else: ?>
        <p>You don't have any invoices yet.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.invoices-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
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

/* Summary Cards */
.summary-cards {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.summary-card {
    background: white;
    padding: 15px 25px;
    border-radius: 12px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    text-align: center;
    min-width: 120px;
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
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
}

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.filter-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 150px;
}

.filter-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #475569;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-select,
.filter-input {
    width: 100%;
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.filter-select:focus,
.filter-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* Filter Tabs (backward compatibility) */
.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    background: white;
    padding: 5px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    color: #64748b;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.filter-tab:hover,
.filter-tab.active {
    background: #667eea;
    color: white;
}

/* Invoices List */
.invoices-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}

/* Invoice Card */
.invoice-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    border-left: 4px solid transparent;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.invoice-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.invoice-card.status-pending { border-left-color: #f59e0b; }
.invoice-card.status-paid { border-left-color: #10b981; }
.invoice-card.status-overdue { border-left-color: #ef4444; }

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 10px;
}

.invoice-number {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.invoice-number h3 {
    font-size: 18px;
    color: #1e293b;
    margin: 0;
}

.invoice-amount .amount {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending { background: #fef3c7; color: #92400e; }
.status-paid { background: #d1fae5; color: #065f46; }
.status-overdue { background: #fee2e2; color: #991b1b; }

/* Invoice Body */
.invoice-dates {
    display: flex;
    gap: 30px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.date-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.date-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.date-value {
    font-size: 14px;
    color: #1e293b;
    font-weight: 600;
}

.text-danger {
    color: #ef4444;
}

.overdue-badge {
    display: inline-block;
    padding: 2px 6px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 4px;
    font-size: 10px;
    margin-left: 5px;
}

.invoice-notes {
    background: #f8fafc;
    padding: 12px;
    border-radius: 8px;
    font-size: 13px;
    color: #475569;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.invoice-notes i {
    color: #667eea;
}

/* Items Table */
.items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.items-table th {
    text-align: left;
    padding: 8px;
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
}

.items-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #e2e8f0;
}

.text-right {
    text-align: right;
}

.text-muted {
    color: #94a3b8;
}

.text-center {
    text-align: center;
}

/* Invoice Footer */
.invoice-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 15px;
}

.payment-status {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
}

.payment-status.paid {
    color: #10b981;
}

.payment-status.pending {
    color: #f59e0b;
}

.invoice-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-view,
.btn-pay,
.btn-download {
    padding: 8px 15px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-view {
    background: #f1f5f9;
    color: #1e293b;
}

.btn-view:hover {
    background: #e2e8f0;
}

.btn-pay {
    background: #667eea;
    color: white;
}

.btn-pay:hover {
    background: #5a67d8;
    transform: translateY(-2px);
}

.btn-download {
    background: #f1f5f9;
    color: #1e293b;
    padding: 8px 12px;
}

.btn-download:hover {
    background: #e2e8f0;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.page-link {
    padding: 8px 12px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s ease;
    min-width: 40px;
    text-align: center;
}

.page-link:hover,
.page-link.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.page-dots {
    padding: 8px 4px;
    color: #94a3b8;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    max-width: 500px;
    margin: 0 auto;
}

.empty-state i {
    font-size: 60px;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #1e293b;
    margin-bottom: 10px;
    font-size: 24px;
}

.empty-state p {
    color: #64748b;
    font-size: 16px;
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
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5a67d8;
}

.btn-outline {
    background: transparent;
    color: #667eea;
    border: 2px solid #667eea;
}

.btn-outline:hover {
    background: #667eea;
    color: white;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .summary-cards {
        width: 100%;
    }
    
    .summary-card {
        flex: 1;
        min-width: 80px;
        padding: 10px 15px;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        width: 100%;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .filter-actions .btn {
        width: 100%;
    }
    
    .filter-tabs {
        width: 100%;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding: 10px;
    }
    
    .filter-tab {
        white-space: nowrap;
    }
    
    .invoice-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .invoice-dates {
        flex-direction: column;
        gap: 10px;
    }
    
    .invoice-footer {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .invoice-actions {
        width: 100%;
    }
    
    .btn-view,
    .btn-pay,
    .btn-download {
        flex: 1;
        justify-content: center;
    }
    
    .pagination {
        gap: 3px;
    }
    
    .page-link {
        padding: 6px 10px;
        min-width: 35px;
    }
}

@media (max-width: 480px) {
    .invoice-number {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .items-table {
        font-size: 12px;
    }
    
    .invoice-actions {
        flex-direction: column;
    }
    
    .btn-view,
    .btn-pay,
    .btn-download {
        width: 100%;
    }
}
</style>

<script>
// Filter form enhancement
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            // Remove empty fields before submit
            const inputs = this.querySelectorAll('input, select');
            inputs.forEach(input => {
                if (!input.value) {
                    input.disabled = true;
                }
            });
        });
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
</script>

<?php require_once '../includes/client-footer.php'; ?>