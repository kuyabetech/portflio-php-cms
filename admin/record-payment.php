<?php
// admin/record-payment.php
// Record Payment for Invoice

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

$pageTitle = 'Record Payment';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Invoices', 'url' => 'invoices.php'],
    ['title' => 'Record Payment']
];

// Get invoice details
$invoice = db()->fetch("
    SELECT i.*, c.company_name 
    FROM project_invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.id = ?
", [$invoiceId]);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $amount = (float)$_POST['amount'];
    $paymentDate = $_POST['payment_date'];
    
    // Generate payment number
    $paymentNumber = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    $data = [
        'invoice_id' => $invoiceId,
        'payment_number' => $paymentNumber,
        'payment_date' => $paymentDate,
        'amount' => $amount,
        'payment_method' => $_POST['payment_method'],
        'transaction_id' => $_POST['transaction_id'],
        'reference_number' => $_POST['reference_number'],
        'status' => 'completed',
        'notes' => $_POST['notes'],
        'created_by' => $_SESSION['user_id']
    ];
    
    db()->insert('invoice_payments', $data);
    
    // Update invoice paid amount
    $newPaidAmount = $invoice['paid_amount'] + $amount;
    $newBalance = $invoice['total'] - $newPaidAmount;
    $newStatus = $newBalance <= 0 ? 'paid' : $invoice['status'];
    
    db()->update('project_invoices', [
        'paid_amount' => $newPaidAmount,
        'balance_due' => $newBalance,
        'status' => $newStatus,
        'paid_at' => $newStatus === 'paid' ? date('Y-m-d H:i:s') : null
    ], 'id = :id', ['id' => $invoiceId]);
    
    // Log activity
    logActivity('payment', $invoiceId, 'Payment recorded: $' . $amount);
    
    header('Location: invoices.php?action=view&id=' . $invoiceId . '&msg=payment_recorded');
    exit;
}
// Send payment confirmation
$client = db()->fetch("SELECT * FROM clients WHERE id = ?", [$invoice['client_id']]);
mailer()->sendTemplate('payment_confirmation', [
    'email' => $client['email'],
    'name' => $client['contact_person']
], [
    'client_name' => $client['contact_person'],
    'invoice_number' => $invoice['invoice_number'],
    'amount' => number_format($amount, 2),
    'payment_date' => date('F d, Y', strtotime($paymentDate)),
    'payment_method' => ucfirst(str_replace('_', ' ', $_POST['payment_method'])),
    'transaction_id' => $_POST['transaction_id'] ?? 'N/A',
    'portal_url' => BASE_URL . '/client'
]);
// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>Record Payment</h2>
    <a href="invoices.php?action=view&id=<?php echo $invoiceId; ?>" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i>
        Back to Invoice
    </a>
</div>

<div class="form-container" style="max-width: 600px;">
    <div class="invoice-summary">
        <h3>Invoice #<?php echo $invoice['invoice_number']; ?></h3>
        <p><strong>Client:</strong> <?php echo htmlspecialchars($invoice['company_name']); ?></p>
        <p><strong>Total Amount:</strong> $<?php echo number_format($invoice['total'], 2); ?></p>
        <p><strong>Already Paid:</strong> $<?php echo number_format($invoice['paid_amount'], 2); ?></p>
        <p><strong>Balance Due:</strong> $<?php echo number_format($invoice['balance_due'], 2); ?></p>
    </div>
    
    <form method="POST" class="admin-form">
        <div class="form-group">
            <label for="amount">Payment Amount *</label>
            <input type="number" id="amount" name="amount" required step="0.01" min="0.01" 
                   max="<?php echo $invoice['balance_due']; ?>" class="form-control">
        </div>
        
        <div class="form-group">
            <label for="payment_date">Payment Date *</label>
            <input type="date" id="payment_date" name="payment_date" required 
                   value="<?php echo date('Y-m-d'); ?>" class="form-control">
        </div>
        
        <div class="form-group">
            <label for="payment_method">Payment Method *</label>
            <select id="payment_method" name="payment_method" required class="form-control">
                <option value="">-- Select Method --</option>
                <option value="cash">Cash</option>
                <option value="check">Check</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="credit_card">Credit Card</option>
                <option value="paypal">PayPal</option>
                <option value="stripe">Stripe</option>
                <option value="other">Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="transaction_id">Transaction ID</label>
            <input type="text" id="transaction_id" name="transaction_id" class="form-control">
        </div>
        
        <div class="form-group">
            <label for="reference_number">Reference Number</label>
            <input type="text" id="reference_number" name="reference_number" class="form-control">
        </div>
        
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3" class="form-control"></textarea>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="record_payment" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Record Payment
            </button>
            <a href="invoices.php?action=view&id=<?php echo $invoiceId; ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<style>
.invoice-summary {
    background: var(--gray-100);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.invoice-summary h3 {
    margin-bottom: 10px;
    color: var(--primary);
}

.invoice-summary p {
    margin: 5px 0;
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>