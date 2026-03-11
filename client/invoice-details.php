<?php
/**
 * Client Invoice Details - View single invoice with payment options
 * FULLY RESPONSIVE - Mobile First Design
 */

require_once dirname(__DIR__) . '/includes/init.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if client is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$clientUserId = $_SESSION['client_id'];
$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$invoiceId) {
    header('Location: invoices.php');
    exit;
}

// Get client user information
$clientUser = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientUserId]);

if (!$clientUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get client company ID
$companyId = $clientUser['client_id'] ?? 0;

// Get invoice details with proper authorization
$invoice = null;

if ($companyId > 0) {
    // Check if client has access to this invoice
    $invoice = db()->fetch("
        SELECT i.*, p.title as project_title, c.company_name as client_company
        FROM project_invoices i
        LEFT JOIN projects p ON i.project_id = p.id
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.id = ? AND i.client_id = ?
    ", [$invoiceId, $companyId]);
}

if (!$invoice) {
    // Invoice not found or not authorized
    $_SESSION['error'] = 'Invoice not found or you do not have access to it.';
    header('Location: invoices.php');
    exit;
}

// Get invoice items
$items = [];
if (!empty($invoice['items'])) {
    $items = json_decode($invoice['items'], true) ?: [];
}

// Get payment history
$payments = [];
try {
    $payments = db()->fetchAll("
        SELECT * FROM invoice_payments 
        WHERE invoice_id = ? 
        ORDER BY payment_date DESC
    ", [$invoiceId]) ?? [];
} catch (Exception $e) {
    error_log("Payments fetch error: " . $e->getMessage());
}

// Calculate totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += ((float)($item['quantity'] ?? 1)) * ((float)($item['unit_price'] ?? 0));
}

$taxRate = (float)($invoice['tax_rate'] ?? 0);
$taxAmount = $subtotal * ($taxRate / 100);
$discountAmount = (float)($invoice['discount_amount'] ?? 0);
$total = $subtotal + $taxAmount - $discountAmount;
$paidAmount = (float)($invoice['paid_amount'] ?? 0);
$balanceDue = $total - $paidAmount;

// Check if overdue
$isOverdue = ($invoice['status'] ?? '') === 'pending' && !empty($invoice['due_date']) && strtotime($invoice['due_date']) < time();

// Handle payment processing
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    if ($amount <= 0) {
        $error = 'Please enter a valid amount';
    } elseif ($amount > $balanceDue) {
        $error = 'Payment amount exceeds balance due';
    } else {
        try {
            // Generate transaction ID
            $transactionId = 'TXN_' . strtoupper(uniqid()) . '_' . date('Ymd');
            
            $paymentId = db()->insert('invoice_payments', [
                'invoice_id' => $invoiceId,
                'client_id' => $companyId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'status' => 'completed',
                'payment_date' => date('Y-m-d H:i:s'),
                'notes' => $notes
            ]);
            
            // Update invoice paid amount
            $newPaidAmount = $paidAmount + $amount;
            $newStatus = ($newPaidAmount >= $total) ? 'paid' : 'pending';
            
            db()->updatePositional('project_invoices', [
                'paid_amount' => $newPaidAmount,
                'status' => $newStatus,
                'paid_at' => ($newStatus === 'paid') ? date('Y-m-d H:i:s') : null
            ], 'id = ?', [$invoiceId]);
            
            // Send receipt (if function exists)
            if (function_exists('sendPaymentReceipt')) {
                sendPaymentReceipt($invoice, $amount, $paymentId);
            }
            
            // Log activity
            logClientActivity($clientUserId, 'payment_made', "Payment of $" . number_format($amount, 2) . " made for invoice #{$invoice['invoice_number']}");
            
            $success = 'Payment processed successfully. Transaction ID: ' . $transactionId;
            
            // Refresh invoice data
            $invoice = db()->fetch("SELECT * FROM project_invoices WHERE id = ?", [$invoiceId]);
            $paidAmount = (float)($invoice['paid_amount'] ?? 0);
            $balanceDue = $total - $paidAmount;
            
        } catch (Exception $e) {
            error_log("Payment error: " . $e->getMessage());
            $error = 'Payment processing failed. Please try again or contact support.';
        }
    }
}

$pageTitle = 'Invoice #' . ($invoice['invoice_number'] ?? 'N/A');
require_once "../includes/client-header.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo $pageTitle; ?> | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========================================
           RESET & BASE STYLES - MOBILE FIRST
           ======================================== */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* Main Container */
        .invoice-details-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 12px;
            width: 100%;
            flex: 1;
        }
        

        

        
        /* ========================================
           INVOICE HEADER CARD
           ======================================== */
        
        .invoice-header-card {
            background: white;
            border-radius: 16px;
            padding: 20px 16px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .invoice-title {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .invoice-title h1 {
            font-size: 1.5rem;
            color: #1e293b;
            word-break: break-word;
        }
        
        .project-reference,
        .client-reference {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .project-reference i,
        .client-reference i {
            color: #667eea;
            width: 16px;
        }
        
        .invoice-status {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #e2e8f0; color: #475569; }
        
        .invoice-date {
            color: #64748b;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Invoice Summary */
        .invoice-summary {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-top: 16px;
            border-top: 2px solid #e2e8f0;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .summary-label {
            color: #64748b;
            font-weight: 500;
        }
        
        .summary-value {
            font-weight: 600;
            color: #1e293b;
        }
        
        .summary-item.total {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 2px solid #e2e8f0;
            font-size: 1.1rem;
        }
        
        .summary-item.total .summary-value {
            color: #667eea;
        }
        
        .text-success {
            color: #10b981 !important;
        }
        
        .text-danger {
            color: #ef4444 !important;
        }
        
        /* ========================================
           DETAILS GRID
           ======================================== */
        
        .details-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 20px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 16px;
        }
        
        .info-card:last-child {
            margin-bottom: 0;
        }
        
        .info-card h3 {
            font-size: 1rem;
            color: #1e293b;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-card h3 i {
            color: #667eea;
            font-size: 1rem;
        }
        
        /* Bill To Content */
        .bill-to-content,
        .ship-to-content,
        .terms-content {
            color: #475569;
            line-height: 1.6;
            font-size: 0.9rem;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        /* Dates List */
        .dates-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .date-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .date-item:last-child {
            border-bottom: none;
        }
        
        .date-label {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .date-value {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .overdue-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        /* Notes & Terms */
        .notes-content {
            color: #475569;
            line-height: 1.6;
            font-size: 0.9rem;
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        
        /* Items Table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 0 -16px;
            padding: 0 16px;
            width: calc(100% + 32px);
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 500px;
        }
        
        .items-table th {
            text-align: left;
            padding: 10px 8px;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        
        .items-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        
        .items-table td:first-child {
            max-width: 200px;
            word-break: break-word;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        /* Payment Summary */
        .payment-summary {
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        
        .summary-row.balance-due {
            border-bottom: none;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #e2e8f0;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .amount {
            font-weight: 600;
        }
        
        /* Payment Form */
        .btn-pay {
            background: #667eea;
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }
        
        .btn-pay:hover {
            background: #5a67d8;
            transform: translateY(-2px);
        }
        
        .payment-form {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }
        
        .payment-form .form-group {
            margin-bottom: 16px;
        }
        
        .payment-form label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #475569;
            font-size: 0.85rem;
        }
        
        .payment-form input,
        .payment-form select,
        .payment-form textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s ease;
        }
        
        .payment-form input:focus,
        .payment-form select:focus,
        .payment-form textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #1e293b;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        /* Paid Message */
        .paid-message {
            background: #d1fae5;
            color: #065f46;
            padding: 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 16px;
            font-size: 0.9rem;
        }
        
        .paid-message i {
            font-size: 1.2rem;
        }
        
        /* Payments Table */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 500px;
        }
        
        .payments-table th {
            text-align: left;
            padding: 10px 8px;
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        
        .payments-table td {
            padding: 10px 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .payment-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .payment-status.status-completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .payment-status.status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .payment-status.status-failed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Invoice Actions */
        .invoice-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }
        
        .btn-outline {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        /* Alerts */
        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert i {
            font-size: 1.1rem;
        }
        
        /* No Data */
        .no-data {
            color: #94a3b8;
            font-style: italic;
            padding: 24px;
            text-align: center;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        
        /* Text Muted */
        .text-muted {
            color: #94a3b8;
        }
        
        /* Client Footer */
        .client-footer {
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 20px 0;
            margin-top: 30px;
        }
        
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
        
        .footer-copyright {
            color: #64748b;
            font-size: 0.8rem;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: #64748b;
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.2s ease;
        }
        
        .footer-links a:hover {
            color: #667eea;
        }
        
        /* ========================================
           TABLET STYLES (min-width: 768px)
           ======================================== */
        
        @media (min-width: 768px) {
            .invoice-details-page {
                padding: 20px;
            }
            
            .header-container {
                padding: 0 20px;
            }
            
            .logo h1 {
                font-size: 1.5rem;
            }
            
            .user-name {
                font-size: 1rem;
            }
            
            .invoice-header-card {
                padding: 24px;
            }
            
            .invoice-title {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }
            
            .invoice-title h1 {
                font-size: 1.75rem;
            }
            
            .invoice-status {
                align-items: flex-end;
            }
            
            .invoice-summary {
                flex-direction: row;
                justify-content: flex-end;
                gap: 30px;
            }
            
            .summary-item {
                flex-direction: column;
                align-items: flex-end;
                gap: 5px;
            }
            
            .summary-label {
                margin-bottom: 0;
            }
            
            .info-card {
                padding: 24px;
            }
            
            .date-item {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .date-label {
                font-size: 0.9rem;
            }
            
            .form-actions {
                flex-direction: row;
                justify-content: flex-end;
            }
            
            .btn-secondary,
            .btn-primary {
                padding: 12px 24px;
            }
            
            .invoice-actions {
                flex-direction: row;
                justify-content: flex-end;
            }
            
            .btn-outline {
                width: auto;
                padding: 12px 24px;
            }
            
            .footer-container {
                flex-direction: row;
                justify-content: space-between;
                text-align: left;
            }
        }
        
        /* ========================================
           DESKTOP STYLES (min-width: 1024px)
           ======================================== */
        
        @media (min-width: 1024px) {
            .invoice-details-page {
                padding: 30px;
            }
            
            .details-grid {
                display: grid;
                grid-template-columns: 300px 1fr;
                gap: 24px;
            }
            
            .left-column .info-card:last-child,
            .right-column .info-card:last-child {
                margin-bottom: 0;
            }
            
            .invoice-header-card {
                padding: 30px;
            }
            
            .invoice-title h1 {
                font-size: 2rem;
            }
            
            .items-table {
                font-size: 0.9rem;
            }
            
            .items-table td:first-child {
                max-width: 300px;
            }
        }
        
        /* ========================================
           LARGE DESKTOP (min-width: 1400px)
           ======================================== */
        
        @media (min-width: 1400px) {
            .invoice-details-page {
                max-width: 1400px;
            }
            
            .details-grid {
                grid-template-columns: 350px 1fr;
            }
        }
        
        /* ========================================
           PRINT STYLES
           ======================================== */
        
        @media print {
            .client-header,
            .back-nav,
            .btn-pay,
            .payment-form,
            .invoice-actions .btn-primary,
            .client-footer {
                display: none !important;
            }
            
            .invoice-details-page {
                padding: 0;
            }
            
            .invoice-header-card,
            .info-card {
                box-shadow: none;
                border: 1px solid #e2e8f0;
                break-inside: avoid;
            }
            
            .status-badge {
                border: 1px solid currentColor;
                background: transparent !important;
            }
        }
        
        /* ========================================
           UTILITY CLASSES
           ======================================== */
        
        .d-none {
            display: none;
        }
        
        .d-block {
            display: block;
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-3 {
            margin-top: 16px;
        }
        
        .mb-3 {
            margin-bottom: 16px;
        }
        
        /* Loading Spinner */
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>


    <div class="invoice-details-page">
        <!-- Back Navigation -->
        <div class="back-nav">
            <a href="invoices.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Invoices
            </a>
        </div>

        <!-- Invoice Header -->
        <div class="invoice-header-card">
            <div class="invoice-title">
                <div>
                    <h1>Invoice #<?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></h1>
                    <?php if (!empty($invoice['project_title'])): ?>
                    <p class="project-reference">
                        <i class="fas fa-project-diagram"></i>
                        Project: <?php echo htmlspecialchars($invoice['project_title']); ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($invoice['client_company'])): ?>
                    <p class="client-reference">
                        <i class="fas fa-building"></i>
                        Client: <?php echo htmlspecialchars($invoice['client_company']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="invoice-status">
                    <span class="status-badge status-<?php echo $isOverdue ? 'overdue' : ($invoice['status'] ?? 'pending'); ?>">
                        <?php echo $isOverdue ? 'Overdue' : ucfirst($invoice['status'] ?? 'pending'); ?>
                    </span>
                    <span class="invoice-date">
                        <i class="far fa-calendar"></i>
                        Issued: <?php echo !empty($invoice['invoice_date']) ? date('M d, Y', strtotime($invoice['invoice_date'])) : 'N/A'; ?>
                    </span>
                </div>
            </div>
            
            <div class="invoice-summary">
                <div class="summary-item">
                    <span class="summary-label">Subtotal</span>
                    <span class="summary-value">$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Tax (<?php echo $taxRate; ?>%)</span>
                    <span class="summary-value">$<?php echo number_format($taxAmount, 2); ?></span>
                </div>
                <?php if ($discountAmount > 0): ?>
                <div class="summary-item">
                    <span class="summary-label">Discount</span>
                    <span class="summary-value text-success">-$<?php echo number_format($discountAmount, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-item total">
                    <span class="summary-label">Total</span>
                    <span class="summary-value">$<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <!-- Invoice Details Grid -->
        <div class="details-grid">
            <!-- Left Column - Bill To & Dates -->
            <div class="left-column">
                <div class="info-card">
                    <h3><i class="fas fa-user"></i> Bill To</h3>
                    <div class="bill-to-content">
                        <?php echo !empty($invoice['bill_to']) ? nl2br(htmlspecialchars($invoice['bill_to'])) : '<span class="text-muted">No billing address provided</span>'; ?>
                    </div>
                </div>
                
                <?php if (!empty($invoice['ship_to'])): ?>
                <div class="info-card">
                    <h3><i class="fas fa-truck"></i> Ship To</h3>
                    <div class="ship-to-content">
                        <?php echo nl2br(htmlspecialchars($invoice['ship_to'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="info-card">
                    <h3><i class="far fa-clock"></i> Important Dates</h3>
                    <div class="dates-list">
                        <div class="date-item">
                            <span class="date-label">Invoice Date:</span>
                            <span class="date-value"><?php echo !empty($invoice['invoice_date']) ? date('F d, Y', strtotime($invoice['invoice_date'])) : 'N/A'; ?></span>
                        </div>
                        <div class="date-item <?php echo $isOverdue ? 'text-danger' : ''; ?>">
                            <span class="date-label">Due Date:</span>
                            <span class="date-value">
                                <?php echo !empty($invoice['due_date']) ? date('F d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?>
                                <?php if ($isOverdue): ?>
                                <span class="overdue-badge">Overdue</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (!empty($invoice['paid_at'])): ?>
                        <div class="date-item text-success">
                            <span class="date-label">Paid Date:</span>
                            <span class="date-value"><?php echo date('F d, Y', strtotime($invoice['paid_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($invoice['notes'])): ?>
                <div class="info-card">
                    <h3><i class="fas fa-sticky-note"></i> Notes</h3>
                    <div class="notes-content">
                        <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($invoice['terms_conditions'])): ?>
                <div class="info-card">
                    <h3><i class="fas fa-file-contract"></i> Terms & Conditions</h3>
                    <div class="terms-content">
                        <?php echo nl2br(htmlspecialchars($invoice['terms_conditions'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Items & Payment -->
            <div class="right-column">
                <!-- Items Table -->
                <div class="info-card">
                    <h3><i class="fas fa-list"></i> Invoice Items</h3>
                    
                    <?php if (!empty($items)): ?>
                    <div class="table-responsive">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['description'] ?? 'Item'); ?></td>
                                    <td class="text-center"><?php echo (float)($item['quantity'] ?? 1); ?></td>
                                    <td class="text-right">$<?php echo number_format((float)($item['unit_price'] ?? 0), 2); ?></td>
                                    <td class="text-right">$<?php echo number_format(((float)($item['quantity'] ?? 1) * (float)($item['unit_price'] ?? 0)), 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="no-data">No items found</p>
                    <?php endif; ?>
                </div>

                <!-- Payment Summary -->
                <div class="info-card">
                    <h3><i class="fas fa-credit-card"></i> Payment Summary</h3>
                    
                    <div class="payment-summary">
                        <div class="summary-row">
                            <span>Total Amount:</span>
                            <span class="amount">$<?php echo number_format($total, 2); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Paid Amount:</span>
                            <span class="amount text-success">$<?php echo number_format($paidAmount, 2); ?></span>
                        </div>
                        <div class="summary-row balance-due">
                            <span>Balance Due:</span>
                            <span class="amount <?php echo $balanceDue > 0 ? 'text-danger' : 'text-success'; ?>">
                                $<?php echo number_format($balanceDue, 2); ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($balanceDue > 0 && ($invoice['status'] ?? '') !== 'cancelled'): ?>
                    <!-- Payment Form -->
                    <button class="btn-pay" onclick="togglePaymentForm()">
                        <i class="fas fa-credit-card"></i> Pay Now
                    </button>

                    <div id="paymentForm" style="display: none;">
                        <form method="POST" class="payment-form" id="paymentFormElement">
                            <div class="form-group">
                                <label for="amount">Payment Amount ($)</label>
                                <input type="number" id="amount" name="amount" 
                                       min="0.01" max="<?php echo $balanceDue; ?>" 
                                       step="0.01" value="<?php echo $balanceDue; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_method">Payment Method</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="paypal">PayPal</option>
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_notes">Notes (Optional)</label>
                                <textarea id="payment_notes" name="notes" rows="2" placeholder="Any additional information..."></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn-secondary" onclick="togglePaymentForm()">
                                    Cancel
                                </button>
                                <button type="submit" name="process_payment" class="btn-primary" id="processPaymentBtn">
                                    <i class="fas fa-lock"></i> Process Payment
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if (($invoice['status'] ?? '') === 'paid'): ?>
                    <div class="paid-message">
                        <i class="fas fa-check-circle"></i>
                        This invoice has been paid in full.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Payment History -->
                <?php if (!empty($payments)): ?>
                <div class="info-card">
                    <h3><i class="fas fa-history"></i> Payment History</h3>
                    
                    <div class="table-responsive">
                        <table class="payments-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th class="text-right">Amount</th>
                                    <th>Status</th>
                                    <th>Transaction ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo !empty($payment['payment_date']) ? date('M d, Y', strtotime($payment['payment_date'])) : 'N/A'; ?></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'] ?? 'Unknown')); ?></td>
                                    <td class="text-right">$<?php echo number_format((float)($payment['amount'] ?? 0), 2); ?></td>
                                    <td>
                                        <span class="payment-status status-<?php echo $payment['status'] ?? 'pending'; ?>">
                                            <?php echo ucfirst($payment['status'] ?? 'pending'); ?>
                                        </span>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></small></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Invoice Actions -->
        <div class="invoice-actions">
            <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn-outline">
                <i class="fas fa-download"></i> Download PDF
            </a>
            <a href="#" onclick="window.print(); return false;" class="btn-outline">
                <i class="fas fa-print"></i> Print Invoice
            </a>
            <?php if ($balanceDue > 0 && ($invoice['status'] ?? '') !== 'cancelled'): ?>
            <a href="#" onclick="togglePaymentForm(); return false;" class="btn-primary">
                <i class="fas fa-credit-card"></i> Make a Payment
            </a>
            <?php endif; ?>
        </div>
    </div>



    <script>
    // Toggle payment form
    function togglePaymentForm() {
        const form = document.getElementById('paymentForm');
        if (form) {
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth' });
            } else {
                form.style.display = 'none';
            }
        }
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
        
        // Payment form validation
        const paymentForm = document.getElementById('paymentFormElement');
        const processBtn = document.getElementById('processPaymentBtn');
        
        if (paymentForm && processBtn) {
            paymentForm.addEventListener('submit', function() {
                processBtn.innerHTML = '<i class="fas fa-spinner spinner"></i> Processing...';
                processBtn.disabled = true;
            });
        }
        
        // Mobile menu toggle (if needed)
        const menuBtn = document.querySelector('.mobile-menu-btn');
        const mainNav = document.querySelector('.main-nav');
        
        if (menuBtn && mainNav) {
            menuBtn.addEventListener('click', function() {
                mainNav.classList.toggle('show');
            });
        }
    });

    // Print optimization
    window.onbeforeprint = function() {
        document.querySelectorAll('.btn-pay, .payment-form, .invoice-actions .btn-primary').forEach(el => {
            if (el) el.style.display = 'none';
        });
    };

    window.onafterprint = function() {
        document.querySelectorAll('.btn-pay, .payment-form, .invoice-actions .btn-primary').forEach(el => {
            if (el) el.style.display = '';
        });
    };
    </script>
</body>
</html>
<?php
require_once "../includes/client-footer.php";
?>