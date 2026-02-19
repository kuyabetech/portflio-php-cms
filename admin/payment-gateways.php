<?php
// admin/payment-gateways.php
// Payment Gateway Configuration

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Payment Gateways';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Payment Gateways']
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'gateway_') === 0) {
            $parts = explode('_', $key, 3);
            $gateway = $parts[1];
            $field = $parts[2];
            
            $settings = db()->fetch("SELECT * FROM payment_gateways WHERE gateway_name = ?", [$gateway]);
            if ($settings) {
                $currentSettings = json_decode($settings['settings'] ?? '{}', true);
                $currentSettings[$field] = $value;
                
                db()->update('payment_gateways', [
                    'settings' => json_encode($currentSettings)
                ], 'gateway_name = :name', ['name' => $gateway]);
            }
        }
    }
    
    // Handle toggle active
    if (isset($_POST['toggle_active'])) {
        $gateway = $_POST['gateway'];
        $current = db()->fetch("SELECT is_active FROM payment_gateways WHERE gateway_name = ?", [$gateway]);
        db()->update('payment_gateways', [
            'is_active' => $current['is_active'] ? 0 : 1
        ], 'gateway_name = :name', ['name' => $gateway]);
    }
    
    // Handle toggle sandbox
    if (isset($_POST['toggle_sandbox'])) {
        $gateway = $_POST['gateway'];
        $current = db()->fetch("SELECT sandbox_mode FROM payment_gateways WHERE gateway_name = ?", [$gateway]);
        db()->update('payment_gateways', [
            'sandbox_mode' => $current['sandbox_mode'] ? 0 : 1
        ], 'gateway_name = :name', ['name' => $gateway]);
    }
    
    // Update API keys
    if (isset($_POST['update_keys'])) {
        $gateway = $_POST['gateway'];
        $data = [];
        
        if ($gateway === 'Stripe') {
            $data = [
                'api_key' => $_POST['live_secret_key'],
                'sandbox_api_key' => $_POST['test_secret_key'],
                'api_secret' => $_POST['live_publishable_key'],
                'sandbox_api_secret' => $_POST['test_publishable_key'],
                'webhook_secret' => $_POST['webhook_secret']
            ];
        } elseif ($gateway === 'PayPal') {
            $data = [
                'api_key' => $_POST['live_client_id'],
                'sandbox_api_key' => $_POST['sandbox_client_id'],
                'api_secret' => $_POST['live_secret'],
                'sandbox_api_secret' => $_POST['sandbox_secret'],
                'webhook_secret' => $_POST['webhook_id']
            ];
        }
        
        db()->update('payment_gateways', $data, 'gateway_name = :name', ['name' => $gateway]);
    }
    
    header('Location: payment-gateways.php?msg=updated');
    exit;
}

// Get gateway configurations
$gateways = db()->fetchAll("SELECT * FROM payment_gateways ORDER BY gateway_name");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>Payment Gateways</h2>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
    <div class="alert alert-success">Payment gateway settings updated successfully!</div>
<?php endif; ?>

<div class="gateways-grid">
    <?php foreach ($gateways as $gateway): 
        $settings = json_decode($gateway['settings'] ?? '{}', true);
    ?>
    <div class="gateway-card">
        <div class="gateway-header">
            <div class="gateway-info">
                <?php if ($gateway['gateway_name'] === 'Stripe'): ?>
                <i class="fab fa-stripe fa-2x" style="color: #6772e5;"></i>
                <?php elseif ($gateway['gateway_name'] === 'PayPal'): ?>
                <i class="fab fa-paypal fa-2x" style="color: #00457c;"></i>
                <?php else: ?>
                <i class="fas fa-university fa-2x"></i>
                <?php endif; ?>
                <h3><?php echo $gateway['gateway_name']; ?></h3>
            </div>
            <div class="gateway-status">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="gateway" value="<?php echo $gateway['gateway_name']; ?>">
                    <button type="submit" name="toggle_active" class="status-toggle <?php echo $gateway['is_active'] ? 'active' : ''; ?>">
                        <?php echo $gateway['is_active'] ? 'Active' : 'Inactive'; ?>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="gateway-mode">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="gateway" value="<?php echo $gateway['gateway_name']; ?>">
                <button type="submit" name="toggle_sandbox" class="mode-toggle <?php echo $gateway['sandbox_mode'] ? 'sandbox' : 'live'; ?>">
                    <?php echo $gateway['sandbox_mode'] ? 'Sandbox Mode' : 'Live Mode'; ?>
                </button>
            </form>
        </div>
        
        <form method="POST" class="gateway-form">
            <input type="hidden" name="gateway" value="<?php echo $gateway['gateway_name']; ?>">
            <input type="hidden" name="update_keys" value="1">
            
            <?php if ($gateway['gateway_name'] === 'Stripe'): ?>
            <div class="form-section">
                <h4>Test Keys (Sandbox)</h4>
                <div class="form-group">
                    <label>Publishable Key</label>
                    <input type="text" name="test_publishable_key" 
                           value="<?php echo $gateway['sandbox_api_secret'] ?? ''; ?>" 
                           placeholder="pk_test_...">
                </div>
                <div class="form-group">
                    <label>Secret Key</label>
                    <input type="text" name="test_secret_key" 
                           value="<?php echo $gateway['sandbox_api_key'] ?? ''; ?>" 
                           placeholder="sk_test_...">
                </div>
            </div>
            
            <div class="form-section">
                <h4>Live Keys (Production)</h4>
                <div class="form-group">
                    <label>Publishable Key</label>
                    <input type="text" name="live_publishable_key" 
                           value="<?php echo $gateway['api_secret'] ?? ''; ?>" 
                           placeholder="pk_live_...">
                </div>
                <div class="form-group">
                    <label>Secret Key</label>
                    <input type="text" name="live_secret_key" 
                           value="<?php echo $gateway['api_key'] ?? ''; ?>" 
                           placeholder="sk_live_...">
                </div>
            </div>
            
            <div class="form-group">
                <label>Webhook Secret</label>
                <input type="text" name="webhook_secret" value="<?php echo $gateway['webhook_secret'] ?? ''; ?>">
                <small>Webhook URL: <?php echo BASE_URL; ?>/webhook/stripe.php</small>
            </div>
            
            <?php elseif ($gateway['gateway_name'] === 'PayPal'): ?>
            <div class="form-section">
                <h4>Sandbox Keys</h4>
                <div class="form-group">
                    <label>Client ID</label>
                    <input type="text" name="sandbox_client_id" 
                           value="<?php echo $gateway['sandbox_api_key'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label>Secret</label>
                    <input type="text" name="sandbox_secret" 
                           value="<?php echo $gateway['sandbox_api_secret'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="form-section">
                <h4>Live Keys</h4>
                <div class="form-group">
                    <label>Client ID</label>
                    <input type="text" name="live_client_id" 
                           value="<?php echo $gateway['api_key'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label>Secret</label>
                    <input type="text" name="live_secret" 
                           value="<?php echo $gateway['api_secret'] ?? ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Webhook ID</label>
                <input type="text" name="webhook_id" value="<?php echo $gateway['webhook_secret'] ?? ''; ?>">
                <small>Webhook URL: <?php echo BASE_URL; ?>/webhook/paypal.php</small>
            </div>
            <?php endif; ?>
            
            <button type="submit" class="btn btn-primary">Save Keys</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<!-- Test Payment Section -->
<div class="test-payment">
    <h3>Test Payment</h3>
    <p>Use these test cards to verify your payment gateway integration:</p>
    
    <table class="test-cards">
        <thead>
            <tr>
                <th>Card Type</th>
                <th>Number</th>
                <th>Expiry</th>
                <th>CVC</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Visa</td>
                <td>4242 4242 4242 4242</td>
                <td>Any future date</td>
                <td>Any 3 digits</td>
            </tr>
            <tr>
                <td>Mastercard</td>
                <td>5555 5555 5555 4444</td>
                <td>Any future date</td>
                <td>Any 3 digits</td>
            </tr>
            <tr>
                <td>Amex</td>
                <td>3782 822463 10005</td>
                <td>Any future date</td>
                <td>Any 4 digits</td>
            </tr>
            <tr>
                <td>Discover</td>
                <td>6011 1111 1111 1117</td>
                <td>Any future date</td>
                <td>Any 3 digits</td>
            </tr>
        </tbody>
    </table>
</div>

<style>
.gateways-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.gateway-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.gateway-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--gray-200);
}

.gateway-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.gateway-info h3 {
    font-size: 1.3rem;
    margin: 0;
}

.status-toggle {
    padding: 6px 15px;
    border: none;
    border-radius: 20px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.status-toggle.active {
    background: #10b981;
    color: white;
}

.status-toggle:not(.active) {
    background: var(--gray-200);
    color: var(--gray-600);
}

.gateway-mode {
    margin-bottom: 20px;
}

.mode-toggle {
    padding: 4px 12px;
    border: none;
    border-radius: 4px;
    font-size: 0.85rem;
    cursor: pointer;
}

.mode-toggle.sandbox {
    background: #f59e0b;
    color: white;
}

.mode-toggle.live {
    background: #10b981;
    color: white;
}

.form-section {
    background: var(--gray-100);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.form-section h4 {
    margin-bottom: 15px;
    color: var(--gray-700);
    font-size: 1rem;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 0.9rem;
}

.form-group input {
    width: 100%;
    padding: 8px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
    font-family: monospace;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: var(--gray-500);
    font-size: 0.8rem;
}

.test-payment {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.test-cards {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.test-cards th {
    text-align: left;
    padding: 10px;
    background: var(--gray-100);
    font-weight: 600;
}

.test-cards td {
    padding: 10px;
    border-bottom: 1px solid var(--gray-200);
    font-family: monospace;
}

@media (max-width: 768px) {
    .gateways-grid {
        grid-template-columns: 1fr;
    }
    
    .gateway-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>