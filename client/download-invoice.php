<?php
// admin/download-invoice-pdf.php
// Professional PDF Invoice Download

require_once dirname(__DIR__) . '/includes/init.php';
require_once __DIR__ . '/classes/InvoicePDF.php';

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

// Client details
$client = [
    'contact_person' => $invoice['contact_person'] ?? '',
    'email' => $invoice['email'] ?? '',
    'phone' => $invoice['phone'] ?? ''
];

// Generate PDF
$pdfGenerator = new InvoicePDF($invoice, $items, $company, $client);
$dompdf = $pdfGenerator->generate();

// Output PDF
$filename = 'Invoice-' . $invoice['invoice_number'] . '.pdf';
$dompdf->stream($filename, [
    'Attachment' => true, // true for download, false for inline
    'compress' => true
]);

exit;
?>