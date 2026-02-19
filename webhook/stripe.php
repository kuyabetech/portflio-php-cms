<?php
// webhook/stripe.php
// Stripe Webhook Handler

require_once dirname(__DIR__) . '/includes/init.php';

// Get Stripe configuration
$stripeConfig = db()->fetch("SELECT * FROM payment_gateways WHERE gateway_name = 'Stripe'");
if (!$stripeConfig) {
    http_response_code(500);
    exit('Stripe not configured');
}

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;

try {
    // Verify webhook signature
    $webhookSecret = $stripeConfig['sandbox_mode'] ? $stripeConfig['sandbox_api_secret'] : $stripeConfig['api_secret'];
    
    require_once dirname(__DIR__) . '/includes/vendor/autoload.php';
    \Stripe\Stripe::setApiKey($stripeConfig['sandbox_mode'] ? $stripeConfig['sandbox_api_key'] : $stripeConfig['api_key']);
    
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $webhookSecret
    );
    
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

// Handle the event
switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;
        handleSuccessfulPayment($paymentIntent);
        break;
        
    case 'payment_intent.payment_failed':
        $paymentIntent = $event->data->object;
        handleFailedPayment($paymentIntent);
        break;
        
    default:
        echo 'Received unknown event type ' . $event->type;
}

http_response_code(200);

function handleSuccessfulPayment($paymentIntent) {
    // Find invoice by metadata
    $invoiceId = $paymentIntent->metadata->invoice_id ?? 0;
    if (!$invoiceId) return;
    
    $amount = $paymentIntent->amount / 100; // Convert from cents
    
    // Record payment
    db()->insert('invoice_payments', [
        'invoice_id' => $invoiceId,
        'payment_number' => 'STR-' . time(),
        'payment_date' => date('Y-m-d'),
        'amount' => $amount,
        'payment_method' => 'stripe',
        'transaction_id' => $paymentIntent->id,
        'status' => 'completed'
    ]);
    
    // Update invoice
    $invoice = db()->fetch("SELECT * FROM project_invoices WHERE id = ?", [$invoiceId]);
    if ($invoice) {
        $newPaidAmount = $invoice['paid_amount'] + $amount;
        $newBalance = $invoice['total'] - $newPaidAmount;
        $newStatus = $newBalance <= 0 ? 'paid' : $invoice['status'];
        
        db()->update('project_invoices', [
            'paid_amount' => $newPaidAmount,
            'balance_due' => $newBalance,
            'status' => $newStatus,
            'paid_at' => $newStatus === 'paid' ? date('Y-m-d H:i:s') : null
        ], 'id = :id', ['id' => $invoiceId]);
    }
}

function handleFailedPayment($paymentIntent) {
    // Log failed payment
    error_log('Payment failed: ' . $paymentIntent->id);
}