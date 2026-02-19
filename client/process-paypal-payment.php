<?php
// client/process-paypal-payment.php
// Process PayPal Payment

require_once dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$orderId = $input['order_id'];
$invoiceId = (int)$input['invoice_id'];
$amount = (float)$input['amount'];
$payer = $input['payer'];

// Get invoice details
$invoice = db()->fetch("SELECT * FROM project_invoices WHERE id = ?", [$invoiceId]);

if (!$invoice || $invoice['balance_due'] != $amount) {
    echo json_encode(['success' => false, 'error' => 'Invalid invoice']);
    exit;
}

// Get PayPal configuration
$paypalConfig = db()->fetch("SELECT * FROM payment_gateways WHERE gateway_name = 'PayPal'");
if (!$paypalConfig || !$paypalConfig['is_active']) {
    echo json_encode(['success' => false, 'error' => 'PayPal not configured']);
    exit;
}

// Record payment
$paymentNumber = 'PPL-' . time() . '-' . $invoiceId;
db()->insert('invoice_payments', [
    'invoice_id' => $invoiceId,
    'payment_number' => $paymentNumber,
    'payment_date' => date('Y-m-d'),
    'amount' => $amount,
    'payment_method' => 'paypal',
    'transaction_id' => $orderId,
    'reference_number' => $payer['email_address'] ?? '',
    'status' => 'completed'
]);

// Update invoice
$newPaidAmount = $invoice['paid_amount'] + $amount;
$newBalance = $invoice['total'] - $newPaidAmount;
$newStatus = $newBalance <= 0 ? 'paid' : $invoice['status'];

db()->update('project_invoices', [
    'paid_amount' => $newPaidAmount,
    'balance_due' => $newBalance,
    'status' => $newStatus,
    'paid_at' => $newStatus === 'paid' ? date('Y-m-d H:i:s') : null
], 'id = :id', ['id' => $invoiceId]);

echo json_encode(['success' => true]);
?>