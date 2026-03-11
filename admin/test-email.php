<?php
// admin/test-email.php
// Test Email Configuration

require_once dirname(__DIR__) . '/includes/init.php';
require_once __DIR__ . '/classes/MailHelper.php';

Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mailer = new MailHelper();
    
    if ($mailer->testConnection()) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Email test successful! SMTP connection is working.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Email test failed: ' . $mailer->getError()];
    }
    
    header('Location: test-email.php');
    exit;
}

$pageTitle = 'Test Email Configuration';
require_once 'includes/header.php';
?>

<div class="content-header">
    <h1><i class="fas fa-envelope"></i> Test Email Configuration</h1>
    <a href="settings.php" class="btn btn-outline">
        <i class="fas fa-cog"></i> Email Settings
    </a>
</div>

<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?>">
        <i class="fas <?php echo $_SESSION['flash']['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo $_SESSION['flash']['message']; ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<div class="settings-container">
    <div class="settings-section">
        <h2>Current SMTP Settings</h2>
        
        <table class="settings-table">
            <tr>
                <th>SMTP Host:</th>
                <td><?php echo htmlspecialchars(getSetting('smtp_host') ?: 'Not set'); ?></td>
            </tr>
            <tr>
                <th>SMTP Port:</th>
                <td><?php echo htmlspecialchars(getSetting('smtp_port') ?: 'Not set'); ?></td>
            </tr>
            <tr>
                <th>SMTP Username:</th>
                <td><?php echo htmlspecialchars(getSetting('smtp_username') ?: 'Not set'); ?></td>
            </tr>
            <tr>
                <th>SMTP Encryption:</th>
                <td><?php echo htmlspecialchars(getSetting('smtp_encryption') ?: 'Not set'); ?></td>
            </tr>
            <tr>
                <th>Contact Email:</th>
                <td><?php echo htmlspecialchars(getSetting('contact_email') ?: 'Not set'); ?></td>
            </tr>
        </table>
        
        <form method="POST" style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Test SMTP Connection
            </button>
        </form>
        
        <div class="info-box" style="margin-top: 30px; background: #f8fafc; padding: 20px; border-radius: 8px;">
            <h3><i class="fas fa-info-circle"></i> How to set up Gmail SMTP</h3>
            <ol style="margin-top: 10px; line-height: 1.8;">
                <li>Enable 2-Factor Authentication on your Google Account</li>
                <li>Go to Google Account → Security → App Passwords</li>
                <li>Generate an app password for "Mail"</li>
                <li>Use that 16-character password in settings</li>
                <li>SMTP Host: smtp.gmail.com</li>
                <li>SMTP Port: 587</li>
                <li>Encryption: tls</li>
            </ol>
        </div>
    </div>
</div>

<style>
.settings-container {
    max-width: 800px;
    margin: 0 auto;
}

.settings-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.settings-section h2 {
    font-size: 20px;
    margin-bottom: 20px;
    color: #2c3e50;
}

.settings-table {
    width: 100%;
    border-collapse: collapse;
}

.settings-table th,
.settings-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.settings-table th {
    width: 200px;
    font-weight: 600;
    color: #2c3e50;
}

.info-box h3 {
    margin-bottom: 10px;
    color: #2c3e50;
}

.info-box ol {
    padding-left: 20px;
}

.info-box li {
    margin-bottom: 5px;
}
</style>

<?php
require_once 'includes/footer.php';
?>