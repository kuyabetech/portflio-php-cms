<?php
// webhook/paypal.php
// PayPal Webhook Handler

require_once dirname(__DIR__) . '/includes/init.php';

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Verify webhook signature (implement PayPal webhook verification)
// This is a simplified version

if ($data['event_type'] === 'PAYMENT.CAPTURE.COMPLETED') {
    $resource = $data['resource'];
    
    $invoiceId = $resource['invoice_id'] ?? 0;
    $amount = $resource['amount']['value'] ?? 0;
    $transactionId = $resource['id'] ?? '';
    
    if ($invoiceId && $amount) {
        // Record payment
        db()->insert('invoice_payments', [
            'invoice_id' => $invoiceId,
            'payment_number' => 'PPL-' . time(),
            'payment_date' => date('Y-m-d'),
            'amount' => $amount,
            'payment_method' => 'paypal',
            'transaction_id' => $transactionId,
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
}

http_response_code(200);
?>