<?php
// cron/generate-recurring-invoices.php
// Run daily to generate recurring invoices

require_once dirname(__DIR__) . '/includes/init.php';

$today = date('Y-m-d');

// Get due recurring invoices
$recurring = db()->fetchAll("
    SELECT * FROM recurring_invoices 
    WHERE status = 'active' 
    AND next_date <= ?
    AND (end_date IS NULL OR end_date >= ?)
", [$today, $today]);

foreach ($recurring as $r) {
    $template = json_decode($r['template'], true);
    
    // Calculate totals
    $subtotal = 0;
    foreach ($template['items'] as $item) {
        $subtotal += $item['quantity'] * $item['unit_price'];
    }
    
    $taxAmount = $subtotal * ($template['tax_rate'] / 100);
    $total = $subtotal + $taxAmount + ($template['shipping_amount'] ?? 0);
    
    // Generate invoice number
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    // Create invoice
    $invoiceId = db()->insert('project_invoices', [
        'client_id' => $r['client_id'],
        'project_id' => $r['project_id'],
        'invoice_number' => $invoiceNumber,
        'invoice_date' => $today,
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'status' => 'draft',
        'items' => json_encode($template['items']),
        'subtotal' => $subtotal,
        'tax_rate' => $template['tax_rate'],
        'tax_amount' => $taxAmount,
        'discount_type' => $template['discount_type'],
        'discount_value' => $template['discount_value'],
        'shipping_amount' => $template['shipping_amount'] ?? 0,
        'total' => $total,
        'balance_due' => $total,
        'notes' => $template['notes'],
        'terms_conditions' => $template['terms_conditions'],
        'created_by' => 1 // System
    ]);
    
    // Update next date
    $nextDate = calculateNextDate($r['next_date'], $r['frequency']);
    db()->update('recurring_invoices', [
        'last_generated' => $today,
        'next_date' => $nextDate
    ], 'id = :id', ['id' => $r['id']]);
    
    // Log activity
    error_log("Generated recurring invoice #$invoiceNumber for client {$r['client_id']}");
}

echo "Processed " . count($recurring) . " recurring invoices.\n";