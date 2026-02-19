<?php
// client/process-stripe-payment.php
// Process Stripe Payment

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

$paymentMethodId = $input['payment_method_id'];
$invoiceId = (int)$input['invoice_id'];
$amount = (float)$input['amount'];

// Get invoice details
$invoice = db()->fetch("SELECT * FROM project_invoices WHERE id = ?", [$invoiceId]);

if (!$invoice || $invoice['balance_due'] != $amount) {
    echo json_encode(['success' => false, 'error' => 'Invalid invoice']);
    exit;
}

// Get Stripe configuration
$stripeConfig = db()->fetch("SELECT * FROM payment_gateways WHERE gateway_name = 'Stripe'");
if (!$stripeConfig || !$stripeConfig['is_active']) {
    echo json_encode(['success' => false, 'error' => 'Stripe not configured']);
    exit;
}

// Set API key based on mode
$apiKey = $stripeConfig['sandbox_mode'] ? $stripeConfig['sandbox_api_key'] : $stripeConfig['api_key'];

// Initialize Stripe
require_once dirname(__DIR__) . '/includes/vendor/autoload.php';
\Stripe\Stripe::setApiKey($apiKey);

try {
    // Create payment intent
    $intent = \Stripe\PaymentIntent::create([
        'amount' => $amount * 100, // Convert to cents
        'currency' => strtolower($invoice['currency']),
        'payment_method' => $paymentMethodId,
        'confirmation_method' => 'manual',
        'confirm' => true,
        'return_url' => BASE_URL . '/client/payment-success.php?invoice=' . $invoiceId,
    ]);
    
    if ($intent->status === 'succeeded') {
        // Record payment
        $paymentNumber = 'STR-' . time() . '-' . $invoiceId;
        db()->insert('invoice_payments', [
            'invoice_id' => $invoiceId,
            'payment_number' => $paymentNumber,
            'payment_date' => date('Y-m-d'),
            'amount' => $amount,
            'payment_method' => 'stripe',
            'transaction_id' => $intent->id,
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
    } else {
        echo json_encode(['success' => false, 'error' => 'Payment failed']);
    }
    
} catch (\Stripe\Exception\CardException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Payment processing error']);
}
?>