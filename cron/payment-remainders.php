<?php
// cron/payment-reminders.php
// Send payment reminders for overdue invoices
// Run daily via cron

require_once dirname(__DIR__) . '/includes/init.php';

// Get overdue invoices
$overdueInvoices = db()->fetchAll("
    SELECT i.*, c.email, c.contact_person, c.company_name 
    FROM project_invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.status = 'sent' 
    AND i.due_date < CURDATE()
    AND i.balance_due > 0
");

$sent = 0;
foreach ($overdueInvoices as $invoice) {
    $daysOverdue = (new DateTime())->diff(new DateTime($invoice['due_date']))->days;
    
    $success = mailer()->sendTemplate('payment_reminder', [
        'email' => $invoice['email'],
        'name' => $invoice['contact_person']
    ], [
        'client_name' => $invoice['contact_person'],
        'invoice_number' => $invoice['invoice_number'],
        'due_date' => date('F d, Y', strtotime($invoice['due_date'])),
        'balance_due' => number_format($invoice['balance_due'], 2),
        'days_overdue' => $daysOverdue,
        'invoice_url' => BASE_URL . '/client/pay-invoice.php?id=' . $invoice['id']
    ]);
    
    if ($success) $sent++;
}

echo "[" . date('Y-m-d H:i:s') . "] Sent $sent payment reminders\n";