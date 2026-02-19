<?php
// client/payment-success.php
// Payment Success Page

require_once dirname(__DIR__) . '/includes/init.php';

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$invoiceId = isset($_GET['invoice']) ? (int)$_GET['invoice'] : 0;

// Get invoice details
$invoice = db()->fetch("
    SELECT i.*, c.company_name 
    FROM project_invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.id = ?
", [$invoiceId]);

if (!$invoice) {
    header('Location: dashboard.php');
    exit;
}

// Include header
require_once 'includes/header.php';
?>

<div class="success-page">
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>Payment Successful!</h1>
        
        <p>Thank you for your payment. Your transaction has been completed successfully.</p>
        
        <div class="payment-details">
            <h3>Payment Details</h3>
            
            <div class="detail-row">
                <span>Invoice Number:</span>
                <strong><?php echo $invoice['invoice_number']; ?></strong>
            </div>
            
            <div class="detail-row">
                <span>Amount Paid:</span>
                <strong>$<?php echo number_format($invoice['paid_amount'], 2); ?></strong>
            </div>
            
            <div class="detail-row">
                <span>Payment Date:</span>
                <strong><?php echo date('F d, Y'); ?></strong>
            </div>
            
            <?php if ($invoice['balance_due'] > 0): ?>
            <div class="detail-row">
                <span>Remaining Balance:</span>
                <strong>$<?php echo number_format($invoice['balance_due'], 2); ?></strong>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="success-actions">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-home"></i>
                Go to Dashboard
            </a>
            
            <a href="view-invoice.php?id=<?php echo $invoiceId; ?>" class="btn btn-outline">
                <i class="fas fa-file-invoice"></i>
                View Invoice
            </a>
        </div>
        
        <p class="receipt-note">
            A receipt has been sent to your email address.
            If you don't receive it within a few minutes, please check your spam folder.
        </p>
    </div>
</div>

<style>
.success-page {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
}

.success-container {
    max-width: 600px;
    width: 100%;
    background: white;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.success-icon {
    font-size: 5rem;
    color: #10b981;
    margin-bottom: 20px;
}

.success-icon i {
    animation: scaleIn 0.5s ease;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
    }
    to {
        transform: scale(1);
    }
}

.success-container h1 {
    margin-bottom: 15px;
    color: var(--dark);
}

.success-container p {
    color: var(--gray-600);
    margin-bottom: 30px;
}

.payment-details {
    background: var(--gray-100);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
    text-align: left;
}

.payment-details h3 {
    margin-bottom: 15px;
    color: var(--dark);
    font-size: 1.1rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-300);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row strong {
    color: var(--primary);
}

.success-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-bottom: 20px;
}

.receipt-note {
    font-size: 0.9rem;
    color: var(--gray-500);
    margin-top: 20px;
}

@media (max-width: 768px) {
    .success-actions {
        flex-direction: column;
    }
    
    .success-actions .btn {
        width: 100%;
    }
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>