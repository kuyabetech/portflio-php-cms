<?php
// client/pay-invoice.php
// Online Payment Page

require_once dirname(__DIR__) . '/includes/init.php';

// Check if client is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get invoice details
$invoice = db()->fetch("
    SELECT i.*, c.company_name, c.email, c.phone,
           p.title as project_title
    FROM project_invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN projects p ON i.project_id = p.id
    WHERE i.id = ? AND i.status IN ('sent', 'overdue')
", [$invoiceId]);

if (!$invoice) {
    header('Location: dashboard.php');
    exit;
}

// Get active payment gateways
$gateways = db()->fetchAll("SELECT * FROM payment_gateways WHERE is_active = 1");

// Include header
require_once 'includes/header.php';
?>

<div class="payment-page">
    <div class="payment-container">
        <div class="invoice-summary">
            <h2>Invoice #<?php echo $invoice['invoice_number']; ?></h2>
            
            <div class="summary-details">
                <div class="detail-row">
                    <span>Amount Due:</span>
                    <strong>$<?php echo number_format($invoice['balance_due'], 2); ?></strong>
                </div>
                <div class="detail-row">
                    <span>Due Date:</span>
                    <span><?php echo date('F d, Y', strtotime($invoice['due_date'])); ?></span>
                </div>
                <div class="detail-row">
                    <span>Client:</span>
                    <span><?php echo htmlspecialchars($invoice['company_name']); ?></span>
                </div>
                <?php if ($invoice['project_title']): ?>
                <div class="detail-row">
                    <span>Project:</span>
                    <span><?php echo htmlspecialchars($invoice['project_title']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="payment-methods">
            <h3>Select Payment Method</h3>
            
            <?php foreach ($gateways as $gateway): 
                $isSandbox = $gateway['sandbox_mode'];
                $apiKey = $isSandbox ? $gateway['sandbox_api_secret'] : $gateway['api_secret'];
            ?>
                <?php if ($gateway['gateway_name'] === 'Stripe' && $apiKey): ?>
                <div class="payment-method" id="stripe-method">
                    <div class="method-header">
                        <i class="fab fa-stripe"></i>
                        <h4>Pay with Credit Card (Stripe)</h4>
                    </div>
                    
                    <form id="stripe-payment-form" data-amount="<?php echo $invoice['balance_due'] * 100; ?>" 
                          data-currency="<?php echo $invoice['currency']; ?>" data-invoice="<?php echo $invoiceId; ?>">
                        <div id="card-element" class="stripe-card-element">
                            <!-- Stripe Elements will create form here -->
                        </div>
                        <div id="card-errors" class="error-message" role="alert"></div>
                        <button type="submit" class="btn btn-primary btn-block" id="stripe-submit">
                            <i class="fas fa-lock"></i> Pay $<?php echo number_format($invoice['balance_due'], 2); ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <?php if ($gateway['gateway_name'] === 'PayPal' && $apiKey): ?>
                <div class="payment-method" id="paypal-method">
                    <div class="method-header">
                        <i class="fab fa-paypal"></i>
                        <h4>Pay with PayPal</h4>
                    </div>
                    
                    <div id="paypal-button-container" data-amount="<?php echo $invoice['balance_due']; ?>" 
                         data-currency="<?php echo $invoice['currency']; ?>" data-invoice="<?php echo $invoiceId; ?>"></div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <?php if (empty($gateways)): ?>
            <div class="alert alert-info">
                Online payment is currently unavailable. Please contact us for alternative payment methods.
            </div>
            <?php endif; ?>
        </div>
        
        <div class="payment-security">
            <i class="fas fa-lock"></i>
            <span>Secure payment powered by Stripe & PayPal. Your payment information is encrypted.</span>
        </div>
    </div>
</div>

<!-- Stripe JS -->
<script src="https://js.stripe.com/v3/"></script>
<!-- PayPal JS -->
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo $isSandbox ? 'sb' : 'live'; ?>&currency=USD"></script>

<style>
.payment-page {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.payment-container {
    max-width: 600px;
    width: 100%;
}

.invoice-summary {
    background: white;
    border-radius: 12px 12px 0 0;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.invoice-summary h2 {
    margin-bottom: 20px;
    color: var(--dark);
    text-align: center;
}

.summary-details {
    background: var(--gray-100);
    padding: 20px;
    border-radius: 8px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-300);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row strong {
    font-size: 1.2rem;
    color: var(--primary);
}

.payment-methods {
    background: white;
    padding: 30px;
    border-top: 2px solid var(--gray-200);
}

.payment-methods h3 {
    margin-bottom: 20px;
    text-align: center;
}

.payment-method {
    margin-bottom: 30px;
    padding: 20px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
}

.payment-method:last-child {
    margin-bottom: 0;
}

.method-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.method-header i {
    font-size: 2rem;
}

.method-header .fa-stripe {
    color: #6772e5;
}

.method-header .fa-paypal {
    color: #00457c;
}

.stripe-card-element {
    background: var(--gray-100);
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.error-message {
    color: #ef4444;
    font-size: 0.9rem;
    margin-bottom: 15px;
    min-height: 20px;
}

.payment-security {
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    color: white;
    padding: 15px;
    border-radius: 0 0 12px 12px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.payment-security i {
    font-size: 1.2rem;
}

#paypal-button-container {
    min-height: 45px;
}

@media (max-width: 768px) {
    .payment-container {
        margin: 20px;
    }
}
</style>

<script>
// Initialize Stripe
const stripeMethod = document.getElementById('stripe-method');
if (stripeMethod) {
    const stripe = Stripe('<?php echo $apiKey; ?>');
    const elements = stripe.elements();
    const cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#424770',
                '::placeholder': {
                    color: '#aab7c4',
                },
            },
        },
    });
    
    cardElement.mount('#card-element');
    
    const form = document.getElementById('stripe-payment-form');
    const submitBtn = document.getElementById('stripe-submit');
    const errorElement = document.getElementById('card-errors');
    
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        const {paymentMethod, error} = await stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
        });
        
        if (error) {
            errorElement.textContent = error.message;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-lock"></i> Pay $<?php echo number_format($invoice['balance_due'], 2); ?>';
        } else {
            // Send payment method to server
            fetch('process-stripe-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_method_id: paymentMethod.id,
                    invoice_id: <?php echo $invoiceId; ?>,
                    amount: <?php echo $invoice['balance_due']; ?>
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'payment-success.php?invoice=<?php echo $invoiceId; ?>';
                } else {
                    errorElement.textContent = data.error;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-lock"></i> Pay $<?php echo number_format($invoice['balance_due'], 2); ?>';
                }
            });
        }
    });
}

// Initialize PayPal
const paypalContainer = document.getElementById('paypal-button-container');
if (paypalContainer) {
    paypal.Buttons({
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{
                    amount: {
                        value: '<?php echo $invoice['balance_due']; ?>',
                        currency_code: '<?php echo $invoice['currency']; ?>'
                    },
                    description: 'Invoice #<?php echo $invoice['invoice_number']; ?>'
                }]
            });
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                // Send payment details to server
                fetch('process-paypal-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: data.orderID,
                        invoice_id: <?php echo $invoiceId; ?>,
                        amount: '<?php echo $invoice['balance_due']; ?>',
                        payer: details.payer
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'payment-success.php?invoice=<?php echo $invoiceId; ?>';
                    } else {
                        alert('Payment failed: ' + data.error);
                    }
                });
            });
        },
        onError: function(err) {
            console.error('PayPal Error:', err);
            alert('An error occurred with PayPal. Please try again.');
        }
    }).render('#paypal-button-container');
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>