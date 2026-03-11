<?php
// admin/send-invoice.php
// Send Invoice via Email

require_once dirname(__DIR__) . '/includes/init.php';
require_once __DIR__ . '/classes/InvoicePDF.php';
require_once __DIR__ . '/classes/MailHelper.php';

Auth::requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid invoice ID'];
    header('Location: invoices.php');
    exit;
}

// Get invoice details
$invoice = db()->fetch("
    SELECT i.*, c.company_name, c.contact_person, c.email, c.phone, c.address
    FROM project_invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    WHERE i.id = ?
", [$id]);

if (!$invoice) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invoice not found'];
    header('Location: invoices.php');
    exit;
}

// Check if client has email
if (empty($invoice['email'])) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Client email address is missing'];
    header('Location: invoices.php?action=view&id=' . $id);
    exit;
}

// Get invoice items
$items = db()->fetchAll("
    SELECT * FROM invoice_items 
    WHERE invoice_id = ? 
    ORDER BY sort_order
", [$id]);

// Company details
$company = [
    'name' => SITE_NAME,
    'address' => getSetting('address') ?? '123 Business Street, City, State 12345',
    'email' => getSetting('contact_email') ?? 'billing@example.com',
    'phone' => getSetting('contact_phone') ?? '+1 (555) 123-4567'
];

// Generate PDF
$pdfGenerator = new InvoicePDF($invoice, $items, $company, [
    'contact_person' => $invoice['contact_person'] ?? '',
    'email' => $invoice['email'] ?? '',
    'phone' => $invoice['phone'] ?? ''
]);

$dompdf = $pdfGenerator->generate();
$pdfOutput = $dompdf->output();

// Save PDF temporarily
$tempDir = sys_get_temp_dir();
$tempFile = $tempDir . '/invoice-' . $invoice['invoice_number'] . '-' . time() . '.pdf';
file_put_contents($tempFile, $pdfOutput);

// Send email
$mailer = new MailHelper();
$sent = $mailer->sendInvoice(
    $invoice['email'],
    $invoice['contact_person'] ?: $invoice['company_name'],
    $invoice,
    $tempFile
);

// Clean up temp file
if (file_exists($tempFile)) {
    unlink($tempFile);
}

if ($sent) {
    // Update invoice status to sent if it was draft
    if ($invoice['status'] === 'draft') {
        db()->update('project_invoices', [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $invoice['id']]);
    }
    
    // Log activity
    if (function_exists('logActivity')) {
        logActivity('invoice', $invoice['id'], 'Invoice sent to ' . $invoice['email']);
    }
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Invoice sent successfully to ' . $invoice['email']];
} else {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to send email: ' . $mailer->getError()];
}

header('Location: invoices.php?action=view&id=' . $invoice['id']);
exit;
?>