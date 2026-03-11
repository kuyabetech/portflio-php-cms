<?php
// client/pay-invoice.php
// Online Payment Page - FULLY RESPONSIVE

require_once dirname(__DIR__) . '/includes/init.php';

// Check if client is logged in
if (!isset($_SESSION['client_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$clientUserId = $_SESSION['client_id'];
$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$invoiceId) {
    header('Location: invoices.php');
    exit;
}

/* ======================================================
   GET CLIENT USER
====================================================== */

$clientUser = db()->fetch(
    "SELECT * FROM client_users WHERE id = ?",
    [$clientUserId]
);

if (!$clientUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$companyId = $clientUser['client_id'] ?? 0;

/* ======================================================
   GET INVOICE (AUTHORIZED)
====================================================== */

$invoice = null;

if ($companyId > 0) {
    $invoice = db()->fetch(
        "SELECT i.*, 
                c.company_name,
                c.email,
                c.phone,
                p.title AS project_title
         FROM project_invoices i
         JOIN clients c ON i.client_id = c.id
         LEFT JOIN projects p ON i.project_id = p.id
         WHERE i.id = ?
         AND i.client_id = ?
         AND i.status IN ('sent','pending','overdue')",
        [$invoiceId, $companyId]
    );
}

if (!$invoice) {
    $_SESSION['error'] = 'Invoice not found or not available for payment.';
    header('Location: invoices.php');
    exit;
}

/* ======================================================
   CALCULATE BALANCE
====================================================== */

$total = (float)($invoice['total'] ?? 0);
$paid = (float)($invoice['paid_amount'] ?? 0);

$balanceDue = $total - $paid;

if ($balanceDue <= 0) {
    $_SESSION['success'] = 'This invoice has already been paid in full.';
    header('Location: invoice-details.php?id=' . $invoiceId);
    exit;
}

/* ======================================================
   LOAD ACTIVE PAYMENT GATEWAYS
====================================================== */

$gateways = db()->fetchAll(
    "SELECT * FROM payment_gateways 
     WHERE is_active = 1 
     ORDER BY id ASC"
) ?? [];

/* ======================================================
   DETERMINE GATEWAY KEYS
====================================================== */

$isSandbox = false;
$stripeKey = '';
$paypalClientId = '';

foreach ($gateways as $gateway) {

    if ($gateway['gateway_name'] === 'Stripe') {
        $isSandbox = (bool)($gateway['sandbox_mode'] ?? false);

        $stripeKey = $isSandbox
            ? ($gateway['sandbox_api_key'] ?? '')
            : ($gateway['api_key'] ?? '');
    }

    if ($gateway['gateway_name'] === 'PayPal') {

        $paypalClientId = $isSandbox
            ? ($gateway['sandbox_client_id'] ?? '')
            : ($gateway['client_id'] ?? '');
    }
}

/* ======================================================
   PAGE TITLE
====================================================== */

$pageTitle = 'Pay Invoice #' . htmlspecialchars($invoice['invoice_number']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo $pageTitle; ?> | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php if (!empty($stripeKey)): ?>
    <!-- Stripe JS -->
    <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>
    
    <?php if (!empty($paypalClientId)): ?>
    <!-- PayPal JS -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo htmlspecialchars($paypalClientId); ?>&currency=<?php echo $invoice['currency'] ?? 'USD'; ?>&components=buttons,funding-eligibility"></script>
    <?php endif; ?>
    
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
            background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            position: relative;
        }
        
        /* Background decoration */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
            z-index: 0;
        }
        
        body::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, transparent 70%);
            z-index: 0;
        }
        
        /* ========================================
           PAYMENT CONTAINER
           ======================================== */
        
        .payment-page {
            width: 100%;
            max-width: 600px;
            position: relative;
            z-index: 10;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .payment-container {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        /* ========================================
           INVOICE SUMMARY
           ======================================== */
        
        .invoice-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 24px 20px;
            color: white;
        }
        
        .invoice-summary h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }
        
        .summary-details {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 16px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        
        .detail-row:first-child {
            padding-top: 0;
        }
        
        .detail-row span:first-child {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .detail-row strong {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
        }
        
        .detail-row .amount-due {
            font-size: 1.5rem;
        }
        
        /* ========================================
           PAYMENT METHODS
           ======================================== */
        
        .payment-methods {
            padding: 24px 20px;
        }
        
        .payment-methods h3 {
            font-size: 1.2rem;
            color: #1e293b;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        
        .payment-method {
            margin-bottom: 24px;
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .payment-method:last-child {
            margin-bottom: 0;
        }
        
        .payment-method:hover {
            border-color: #667eea;
            box-shadow: 0 10px 25px -5px rgba(102, 126, 234, 0.2);
        }
        
        .method-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .method-header i {
            font-size: 2.2rem;
        }
        
        .method-header .fa-stripe {
            color: #6772e5;
        }
        
        .method-header .fa-paypal {
            color: #00457c;
        }
        
        .method-header h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            flex: 1;
        }
        
        /* Stripe Elements */
        .stripe-card-element {
            background: #f8fafc;
            padding: 14px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            margin-bottom: 16px;
            transition: all 0.2s ease;
        }
        
        .stripe-card-element.StripeElement--focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .stripe-card-element.StripeElement--invalid {
            border-color: #ef4444;
        }
        
        /* Error Message */
        .error-message {
            color: #ef4444;
            font-size: 0.85rem;
            margin-bottom: 16px;
            min-height: 20px;
            padding-left: 4px;
        }
        
        /* PayPal Button Container */
        #paypal-button-container {
            min-height: 45px;
            margin-top: 10px;
        }
        
        /* ========================================
           BUTTONS
           ======================================== */
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            line-height: 1;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .btn i {
            font-size: 1rem;
        }
        
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* ========================================
           SECURITY BADGE
           ======================================== */
        
        .payment-security {
            background: #f8fafc;
            padding: 16px 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.85rem;
            color: #64748b;
            border-top: 2px solid #e2e8f0;
            flex-wrap: wrap;
        }
        
        .payment-security i {
            font-size: 1.1rem;
            color: #667eea;
        }
        
        /* ========================================
           ALERTS
           ======================================== */
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        
        .alert i {
            font-size: 1.2rem;
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
        
        /* ========================================
           LOADING OVERLAY
           ======================================== */
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 16px;
        }
        
        .loading-overlay.show {
            display: flex;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .loading-text {
            color: #1e293b;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        /* ========================================
           RESPONSIVE STYLES
           ======================================== */
        
        /* Tablet */
        @media (min-width: 640px) {
            .payment-page {
                padding: 20px;
            }
            
            .invoice-summary {
                padding: 32px;
            }
            
            .invoice-summary h2 {
                font-size: 1.8rem;
            }
            
            .summary-details {
                padding: 20px;
            }
            
            .detail-row strong {
                font-size: 1.5rem;
            }
            
            .payment-methods {
                padding: 32px;
            }
            
            .payment-methods h3 {
                font-size: 1.3rem;
            }
            
            .method-header i {
                font-size: 2.5rem;
            }
            
            .method-header h4 {
                font-size: 1.2rem;
            }
        }
        
        /* Desktop */
        @media (min-width: 1024px) {
            .payment-page {
                max-width: 700px;
            }
            
            .payment-method {
                padding: 24px;
            }
        }
        
        /* Print */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .payment-page {
                box-shadow: none;
            }
            
            .payment-methods,
            .payment-security,
            .btn {
                display: none !important;
            }
            
            .invoice-summary {
                background: white;
                color: black;
            }
            
            .summary-details {
                background: #f8fafc;
                color: #1e293b;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Processing your payment...</div>
    </div>

    <div class="payment-page">
        <div class="payment-container">
            <!-- Invoice Summary -->
            <div class="invoice-summary">
                <h2>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h2>
                
                <div class="summary-details">
                    <div class="detail-row">
                        <span>Amount Due:</span>
                        <strong class="amount-due">$<?php echo number_format($balanceDue, 2); ?></strong>
                    </div>
                    <div class="detail-row">
                        <span>Due Date:</span>
                        <span><?php echo !empty($invoice['due_date']) ? date('F d, Y', strtotime($invoice['due_date'])) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Client:</span>
                        <span><?php echo htmlspecialchars($invoice['company_name'] ?? 'N/A'); ?></span>
                    </div>
                    <?php if (!empty($invoice['project_title'])): ?>
                    <div class="detail-row">
                        <span>Project:</span>
                        <span><?php echo htmlspecialchars($invoice['project_title']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment Methods -->
            <div class="payment-methods">
                <h3>Select Payment Method</h3>
                
                <?php if (empty($gateways)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Online payment is currently unavailable. Please contact us for alternative payment methods.
                </div>
                <?php endif; ?>
                
                <?php foreach ($gateways as $gateway): 
                    $gatewayName = $gateway['gateway_name'] ?? '';
                ?>
                    <?php if ($gatewayName === 'Stripe' && !empty($stripeKey)): ?>
                    <!-- Stripe Payment Method -->
                    <div class="payment-method" id="stripe-method">
                        <div class="method-header">
                            <i class="fab fa-stripe"></i>
                            <h4>Pay with Credit Card</h4>
                        </div>
                        
                        <form id="stripe-payment-form">
                            <input type="hidden" id="stripe-amount" value="<?php echo $balanceDue * 100; ?>">
                            <input type="hidden" id="stripe-currency" value="<?php echo $invoice['currency'] ?? 'USD'; ?>">
                            <input type="hidden" id="stripe-invoice" value="<?php echo $invoiceId; ?>">
                            
                            <div id="card-element" class="stripe-card-element">
                                <!-- Stripe Elements will create form here -->
                            </div>
                            <div id="card-errors" class="error-message" role="alert"></div>
                            
                            <button type="submit" class="btn btn-primary btn-block" id="stripe-submit">
                                <i class="fas fa-lock"></i> Pay $<?php echo number_format($balanceDue, 2); ?>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($gatewayName === 'PayPal' && !empty($paypalClientId)): ?>
                    <!-- PayPal Payment Method -->
                    <div class="payment-method" id="paypal-method">
                        <div class="method-header">
                            <i class="fab fa-paypal"></i>
                            <h4>Pay with PayPal</h4>
                        </div>
                        
                        <div id="paypal-button-container" 
                             data-amount="<?php echo $balanceDue; ?>" 
                             data-currency="<?php echo $invoice['currency'] ?? 'USD'; ?>" 
                             data-invoice="<?php echo $invoiceId; ?>">
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Security Badge -->
            <div class="payment-security">
                <i class="fas fa-lock"></i>
                <span>Secure payment powered by Stripe & PayPal. Your payment information is encrypted.</span>
            </div>
        </div>
    </div>

    <script>
    // Show loading overlay
    function showLoading() {
        document.getElementById('loadingOverlay').classList.add('show');
    }

    // Hide loading overlay
    function hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('show');
    }

    <?php if (!empty($stripeKey)): ?>
    // Initialize Stripe
    (function() {
        const stripeMethod = document.getElementById('stripe-method');
        if (!stripeMethod) return;
        
        try {
            const stripe = Stripe('<?php echo htmlspecialchars($stripeKey); ?>');
            const elements = stripe.elements();
            
            const cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        fontFamily: '"Inter", sans-serif',
                        color: '#1e293b',
                        '::placeholder': {
                            color: '#94a3b8',
                        },
                        ':-webkit-autofill': {
                            color: '#1e293b',
                        },
                    },
                    invalid: {
                        color: '#ef4444',
                        iconColor: '#ef4444',
                    },
                },
                hidePostalCode: false,
            });
            
            cardElement.mount('#card-element');
            
            // Handle real-time validation errors
            cardElement.on('change', function(event) {
                const displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });
            
            // Handle form submission
            const form = document.getElementById('stripe-payment-form');
            const submitBtn = document.getElementById('stripe-submit');
            const errorElement = document.getElementById('card-errors');
            
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                
                // Disable button and show loading
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner spinner"></i> Processing...';
                showLoading();
                
                try {
                    const {paymentMethod, error} = await stripe.createPaymentMethod({
                        type: 'card',
                        card: cardElement,
                        billing_details: {
                            name: '<?php echo htmlspecialchars($clientUser['first_name'] . ' ' . $clientUser['last_name']); ?>',
                            email: '<?php echo htmlspecialchars($clientUser['email']); ?>',
                        },
                    });
                    
                    if (error) {
                        errorElement.textContent = error.message;
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-lock"></i> Pay $<?php echo number_format($balanceDue, 2); ?>';
                        hideLoading();
                    } else {
                        // Send payment method to server
                        const response = await fetch('process-stripe-payment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                payment_method_id: paymentMethod.id,
                                invoice_id: <?php echo $invoiceId; ?>,
                                amount: <?php echo $balanceDue; ?>,
                                currency: '<?php echo $invoice['currency'] ?? 'USD'; ?>'
                            }),
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            window.location.href = 'payment-success.php?invoice=<?php echo $invoiceId; ?>&transaction=' + data.transaction_id;
                        } else {
                            errorElement.textContent = data.error || 'Payment failed. Please try again.';
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-lock"></i> Pay $<?php echo number_format($balanceDue, 2); ?>';
                            hideLoading();
                        }
                    }
                } catch (err) {
                    console.error('Stripe Error:', err);
                    errorElement.textContent = 'An unexpected error occurred. Please try again.';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-lock"></i> Pay $<?php echo number_format($balanceDue, 2); ?>';
                    hideLoading();
                }
            });
        } catch (err) {
            console.error('Stripe initialization error:', err);
            document.getElementById('stripe-method').style.display = 'none';
        }
    })();
    <?php endif; ?>

    <?php if (!empty($paypalClientId)): ?>
    // Initialize PayPal
    (function() {
        const paypalContainer = document.getElementById('paypal-button-container');
        if (!paypalContainer) return;
        
        try {
            paypal.Buttons({
                style: {
                    shape: 'rect',
                    color: 'gold',
                    layout: 'vertical',
                    label: 'pay',
                    height: 45,
                },
                
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: '<?php echo $balanceDue; ?>',
                                currency_code: '<?php echo $invoice['currency'] ?? 'USD'; ?>'
                            },
                            description: 'Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?>',
                            custom_id: '<?php echo $invoiceId; ?>',
                            invoice_id: 'INV-<?php echo $invoice['invoice_number']; ?>'
                        }],
                        application_context: {
                            shipping_preference: 'NO_SHIPPING'
                        }
                    });
                },
                
                onApprove: function(data, actions) {
                    showLoading();
                    
                    return actions.order.capture().then(function(details) {
                        // Send payment details to server
                        return fetch('process-paypal-payment.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                order_id: data.orderID,
                                invoice_id: <?php echo $invoiceId; ?>,
                                amount: '<?php echo $balanceDue; ?>',
                                payer: details.payer,
                                payment_id: details.id,
                                status: details.status
                            }),
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.href = 'payment-success.php?invoice=<?php echo $invoiceId; ?>&transaction=' + data.transaction_id;
                            } else {
                                alert('Payment failed: ' + (data.error || 'Unknown error'));
                                hideLoading();
                            }
                        })
                        .catch(error => {
                            console.error('PayPal Error:', error);
                            alert('An error occurred while processing your payment.');
                            hideLoading();
                        });
                    });
                },
                
                onCancel: function(data) {
                    alert('Payment cancelled. You can try again anytime.');
                },
                
                onError: function(err) {
                    console.error('PayPal Error:', err);
                    alert('An error occurred with PayPal. Please try again or contact support.');
                }
            }).render('#paypal-button-container');
        } catch (err) {
            console.error('PayPal initialization error:', err);
            paypalContainer.innerHTML = '<div class="alert alert-info">PayPal is temporarily unavailable. Please try again later.</div>';
        }
    })();
    <?php endif; ?>

    // Prevent accidental navigation
    window.addEventListener('beforeunload', function(e) {
        if (document.getElementById('loadingOverlay').classList.contains('show')) {
            e.preventDefault();
            e.returnValue = 'Payment is being processed. Are you sure you want to leave?';
        }
    });
    </script>
</body>
</html>