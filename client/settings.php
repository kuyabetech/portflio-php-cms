<?php
/**
 * Client Settings - Account preferences and security settings
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

$clientId = $_SESSION['client_id'];

// Get client information
$client = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientId]);

$success = '';
$error = '';

// Handle general settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $timezone = $_POST['timezone'] ?? 'UTC';
    $dateFormat = $_POST['date_format'] ?? 'M d, Y';
    $language = $_POST['language'] ?? 'en';
    
    $notifications = [
        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
        'invoice_notifications' => isset($_POST['invoice_notifications']) ? 1 : 0,
        'project_updates' => isset($_POST['project_updates']) ? 1 : 0,
        'marketing_emails' => isset($_POST['marketing_emails']) ? 1 : 0,
        'login_alerts' => isset($_POST['login_alerts']) ? 1 : 0
    ];
    
    $display = [
        'items_per_page' => (int)($_POST['items_per_page'] ?? 20),
        'compact_view' => isset($_POST['compact_view']) ? 1 : 0
    ];
    
    $settings = json_encode([
        'timezone' => $timezone,
        'date_format' => $dateFormat,
        'language' => $language,
        'notifications' => $notifications,
        'display' => $display
    ]);
    
    try {
        db()->update('client_users', [
            'settings' => $settings,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$clientId]);
        
        $success = 'Settings saved successfully';
        
        // Log activity
        logClientActivity($clientId, 'settings_update', 'Updated account settings');
        
    } catch (Exception $e) {
        error_log("Settings update error: " . $e->getMessage());
        $error = 'Failed to save settings';
    }
}

// Handle two-factor authentication
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_2fa'])) {
    // Generate secret
    $secret = generate2FASecret();
    $qrCode = generate2FAQRCode($client['email'], $secret);
    
    $_SESSION['2fa_secret'] = $secret;
    
    $show2FASetup = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
    $code = $_POST['verification_code'] ?? '';
    $secret = $_SESSION['2fa_secret'] ?? '';
    
    if (verify2FACode($secret, $code)) {
        try {
            db()->update('client_users', [
                'two_factor_secret' => $secret,
                'two_factor_enabled' => 1
            ], 'id = ?', [$clientId]);
            
            unset($_SESSION['2fa_secret']);
            $success = 'Two-factor authentication enabled successfully';
            
            // Log activity
            logClientActivity($clientId, 'security_update', 'Enabled two-factor authentication');
            
        } catch (Exception $e) {
            error_log("2FA setup error: " . $e->getMessage());
            $error = 'Failed to enable two-factor authentication';
        }
    } else {
        $error = 'Invalid verification code';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disable_2fa'])) {
    try {
        db()->update('client_users', [
            'two_factor_secret' => null,
            'two_factor_enabled' => 0
        ], 'id = ?', [$clientId]);
        
        $success = 'Two-factor authentication disabled';
        
        // Log activity
        logClientActivity($clientId, 'security_update', 'Disabled two-factor authentication');
        
    } catch (Exception $e) {
        error_log("2FA disable error: " . $e->getMessage());
        $error = 'Failed to disable two-factor authentication';
    }
}

// Handle session management
if (isset($_GET['revoke_session'])) {
    $sessionId = (int)$_GET['revoke_session'];
    
    try {
        db()->delete('client_sessions', 'id = ? AND client_id = ?', [$sessionId, $clientId]);
        $success = 'Session revoked successfully';
    } catch (Exception $e) {
        error_log("Session revoke error: " . $e->getMessage());
        $error = 'Failed to revoke session';
    }
}

if (isset($_GET['revoke_all_sessions'])) {
    try {
        db()->delete('client_sessions', 'client_id = ? AND session_id != ?', [$clientId, session_id()]);
        $success = 'All other sessions revoked';
    } catch (Exception $e) {
        error_log("Session revoke all error: " . $e->getMessage());
        $error = 'Failed to revoke sessions';
    }
}

// Handle account deletion request
if (isset($_POST['request_deletion'])) {
    $password = $_POST['password'] ?? '';
    $reason = $_POST['deletion_reason'] ?? '';
    
    if (!password_verify($password, $client['password_hash'])) {
        $error = 'Invalid password';
    } else {
        try {
            // Create deletion request
            db()->insert('account_deletion_requests', [
                'client_id' => $clientId,
                'reason' => $reason,
                'status' => 'pending',
                'requested_at' => date('Y-m-d H:i:s')
            ]);
            
            // Notify admin
            addAdminNotification('account_deletion_request', [
                'client_name' => $client['first_name'] . ' ' . $client['last_name'],
                'client_email' => $client['email']
            ]);
            
            $success = 'Account deletion request submitted. You will be contacted within 48 hours.';
            
            // Log activity
            logClientActivity($clientId, 'account_deletion', 'Requested account deletion');
            
        } catch (Exception $e) {
            error_log("Deletion request error: " . $e->getMessage());
            $error = 'Failed to submit deletion request';
        }
    }
}

// Get active sessions
$sessions = db()->fetchAll("
    SELECT * FROM client_sessions 
    WHERE client_id = ? 
    ORDER BY last_activity DESC 
    LIMIT 10
", [$clientId]);

// Parse current settings
$settings = json_decode($client['settings'] ?? '{}', true);
$defaultSettings = [
    'timezone' => 'UTC',
    'date_format' => 'M d, Y',
    'language' => 'en',
    'notifications' => [
        'email_notifications' => 1,
        'invoice_notifications' => 1,
        'project_updates' => 1,
        'marketing_emails' => 0,
        'login_alerts' => 1
    ],
    'display' => [
        'items_per_page' => 20,
        'compact_view' => 0
    ]
];

$settings = array_merge($defaultSettings, $settings);

$pageTitle = 'Account Settings';
require_once '../includes/client-header.php';
?>

<div class="settings-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-cog"></i> Account Settings</h1>
            <p>Manage your preferences and security settings</p>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Settings Tabs -->
    <div class="settings-tabs">
        <button class="tab-btn active" onclick="showTab('general')">
            <i class="fas fa-sliders-h"></i> General
        </button>
        <button class="tab-btn" onclick="showTab('notifications')">
            <i class="fas fa-bell"></i> Notifications
        </button>
        <button class="tab-btn" onclick="showTab('security')">
            <i class="fas fa-shield-alt"></i> Security
        </button>
        <button class="tab-btn" onclick="showTab('sessions')">
            <i class="fas fa-desktop"></i> Active Sessions
        </button>
        <button class="tab-btn" onclick="showTab('privacy')">
            <i class="fas fa-user-secret"></i> Privacy
        </button>
    </div>

    <!-- General Settings Tab -->
    <div id="tab-general" class="tab-content active">
        <div class="settings-card">
            <h3><i class="fas fa-sliders-h"></i> General Settings</h3>
            
            <form method="POST" class="settings-form">
                <div class="form-group">
                    <label for="language">Language</label>
                    <select id="language" name="language">
                        <option value="en" <?php echo $settings['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="es" <?php echo $settings['language'] === 'es' ? 'selected' : ''; ?>>Español</option>
                        <option value="fr" <?php echo $settings['language'] === 'fr' ? 'selected' : ''; ?>>Français</option>
                        <option value="de" <?php echo $settings['language'] === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="timezone">Timezone</label>
                    <select id="timezone" name="timezone">
                        <?php
                        $timezones = [
                            'UTC' => 'UTC',
                            'America/New_York' => 'Eastern Time',
                            'America/Chicago' => 'Central Time',
                            'America/Denver' => 'Mountain Time',
                            'America/Los_Angeles' => 'Pacific Time',
                            'Europe/London' => 'London',
                            'Europe/Paris' => 'Paris',
                            'Asia/Tokyo' => 'Tokyo',
                            'Asia/Singapore' => 'Singapore',
                            'Australia/Sydney' => 'Sydney'
                        ];
                        foreach ($timezones as $value => $label):
                        ?>
                        <option value="<?php echo $value; ?>" <?php echo $settings['timezone'] === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_format">Date Format</label>
                    <select id="date_format" name="date_format">
                        <option value="M d, Y" <?php echo $settings['date_format'] === 'M d, Y' ? 'selected' : ''; ?>>Jan 15, 2024</option>
                        <option value="d M Y" <?php echo $settings['date_format'] === 'd M Y' ? 'selected' : ''; ?>>15 Jan 2024</option>
                        <option value="Y-m-d" <?php echo $settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>2024-01-15</option>
                        <option value="d/m/Y" <?php echo $settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>15/01/2024</option>
                        <option value="m/d/Y" <?php echo $settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>01/15/2024</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="items_per_page">Items Per Page</label>
                    <select id="items_per_page" name="items_per_page">
                        <option value="10" <?php echo ($settings['display']['items_per_page'] ?? 20) == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo ($settings['display']['items_per_page'] ?? 20) == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo ($settings['display']['items_per_page'] ?? 20) == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo ($settings['display']['items_per_page'] ?? 20) == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="compact_view" value="1" 
                               <?php echo ($settings['display']['compact_view'] ?? 0) ? 'checked' : ''; ?>>
                        <span>Use compact view (show more items with less detail)</span>
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="save_settings" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Notifications Tab -->
    <div id="tab-notifications" class="tab-content">
        <div class="settings-card">
            <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
            
            <form method="POST" class="settings-form">
                <div class="notification-group">
                    <h4>Email Notifications</h4>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="email_notifications" value="1" 
                                   <?php echo ($settings['notifications']['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Receive email notifications</span>
                        </label>
                        <p class="help-text">Get notified about important account activities via email</p>
                    </div>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="invoice_notifications" value="1" 
                                   <?php echo ($settings['notifications']['invoice_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Invoice notifications</span>
                        </label>
                        <p class="help-text">Receive emails when new invoices are created or payments are received</p>
                    </div>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="project_updates" value="1" 
                                   <?php echo ($settings['notifications']['project_updates'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Project updates</span>
                        </label>
                        <p class="help-text">Get notified about project milestones and status changes</p>
                    </div>
                </div>
                
                <div class="notification-group">
                    <h4>Security Notifications</h4>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="login_alerts" value="1" 
                                   <?php echo ($settings['notifications']['login_alerts'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Login alerts</span>
                        </label>
                        <p class="help-text">Get notified when someone logs into your account</p>
                    </div>
                </div>
                
                <div class="notification-group">
                    <h4>Marketing</h4>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="marketing_emails" value="1" 
                                   <?php echo ($settings['notifications']['marketing_emails'] ?? 0) ? 'checked' : ''; ?>>
                            <span>Marketing emails</span>
                        </label>
                        <p class="help-text">Receive updates about new features and promotions</p>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="save_settings" class="btn-primary">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Security Tab -->
    <div id="tab-security" class="tab-content">
        <div class="settings-card">
            <h3><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h3>
            
            <?php if (isset($show2FASetup)): ?>
            <!-- 2FA Setup -->
            <div class="twofa-setup">
                <p>Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</p>
                
                <div class="qr-code">
                    <img src="<?php echo $qrCode; ?>" alt="2FA QR Code">
                </div>
                
                <p class="secret-key">
                    <strong>Secret Key:</strong> <code><?php echo $secret; ?></code>
                </p>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="verification_code">Enter verification code from app</label>
                        <input type="text" id="verification_code" name="verification_code" 
                               placeholder="000000" maxlength="6" pattern="[0-9]{6}" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="verify_2fa" class="btn-primary">
                            <i class="fas fa-check"></i> Verify & Enable
                        </button>
                        <a href="settings.php" class="btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
            
            <?php elseif ($client['two_factor_enabled'] ?? 0): ?>
            <!-- 2FA Enabled -->
            <div class="twofa-status enabled">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong>Two-factor authentication is enabled</strong>
                    <p>Your account is protected with an additional layer of security</p>
                </div>
            </div>
            
            <form method="POST" onsubmit="return confirm('Disable two-factor authentication? This will reduce your account security.')">
                <button type="submit" name="disable_2fa" class="btn-outline-danger">
                    <i class="fas fa-ban"></i> Disable Two-Factor Authentication
                </button>
            </form>
            
            <?php else: ?>
            <!-- 2FA Disabled -->
            <div class="twofa-status disabled">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Two-factor authentication is not enabled</strong>
                    <p>Add an extra layer of security to your account</p>
                </div>
            </div>
            
            <form method="POST">
                <button type="submit" name="setup_2fa" class="btn-primary">
                    <i class="fas fa-qrcode"></i> Set Up Two-Factor Authentication
                </button>
            </form>
            <?php endif; ?>
        </div>
        
        <div class="settings-card">
            <h3><i class="fas fa-history"></i> Login History</h3>
            
            <a href="activity-log.php" class="btn-outline">
                <i class="fas fa-history"></i> View Full Activity Log
            </a>
        </div>
    </div>

    <!-- Active Sessions Tab -->
    <div id="tab-sessions" class="tab-content">
        <div class="settings-card">
            <h3><i class="fas fa-desktop"></i> Active Sessions</h3>
            
            <?php if (!empty($sessions)): ?>
            <div class="sessions-list">
                <?php foreach ($sessions as $session): 
                    $isCurrent = $session['session_id'] === session_id();
                ?>
                <div class="session-item <?php echo $isCurrent ? 'current' : ''; ?>">
                    <div class="session-icon">
                        <i class="fas <?php 
                            echo strpos($session['user_agent'], 'Mobile') !== false ? 'fa-mobile-alt' : 'fa-desktop'; 
                        ?>"></i>
                    </div>
                    
                    <div class="session-info">
                        <div class="session-header">
                            <span class="session-device">
                                <?php echo getDeviceInfo($session['user_agent']); ?>
                            </span>
                            <?php if ($isCurrent): ?>
                            <span class="current-badge">Current Session</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="session-details">
                            <span class="detail-item">
                                <i class="fas fa-network-wired"></i>
                                IP: <?php echo $session['ip_address']; ?>
                            </span>
                            <span class="detail-item">
                                <i class="fas fa-clock"></i>
                                Last active: <?php echo timeAgo($session['last_activity']); ?>
                            </span>
                            <span class="detail-item">
                                <i class="fas fa-calendar"></i>
                                Started: <?php echo date('M d, Y', strtotime($session['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!$isCurrent): ?>
                    <a href="?revoke_session=<?php echo $session['id']; ?>" class="btn-revoke" 
                       onclick="return confirm('Revoke this session?')">
                        <i class="fas fa-times"></i> Revoke
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($sessions) > 1): ?>
            <div class="session-actions">
                <a href="?revoke_all_sessions=1" class="btn-outline" 
                   onclick="return confirm('Revoke all other sessions? You will be logged out from other devices.')">
                    <i class="fas fa-sign-out-alt"></i> Revoke All Other Sessions
                </a>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <p class="no-data">No active sessions found</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Privacy Tab -->
    <div id="tab-privacy" class="tab-content">
        <div class="settings-card">
            <h3><i class="fas fa-download"></i> Download Your Data</h3>
            <p>Get a copy of all your personal data stored in our system.</p>
            
            <a href="export-data.php" class="btn-primary">
                <i class="fas fa-download"></i> Request Data Export
            </a>
        </div>
        
        <div class="settings-card">
            <h3><i class="fas fa-user-slash"></i> Delete Account</h3>
            
            <div class="delete-account-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Warning: This action cannot be undone</strong>
                    <p>All your data, including projects, documents, and messages, will be permanently deleted.</p>
                </div>
            </div>
            
            <button class="btn-outline-danger" onclick="showDeleteConfirmation()">
                <i class="fas fa-trash"></i> Request Account Deletion
            </button>
            
            <!-- Delete Confirmation Form (hidden by default) -->
            <div id="deleteConfirmation" style="display: none; margin-top: 20px;">
                <form method="POST" onsubmit="return confirm('Are you absolutely sure? This cannot be undone.')">
                    <div class="form-group">
                        <label for="password">Enter your password to confirm</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="deletion_reason">Reason for leaving (optional)</label>
                        <textarea id="deletion_reason" name="deletion_reason" rows="3" 
                                  placeholder="Tell us why you're leaving..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary" onclick="hideDeleteConfirmation()">
                            Cancel
                        </button>
                        <button type="submit" name="request_deletion" class="btn-danger">
                            <i class="fas fa-exclamation-triangle"></i> Submit Deletion Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.settings-page {
    max-width: 800px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    margin-bottom: 30px;
}

.header-content h1 {
    font-size: 28px;
    color: #1e293b;
    margin-bottom: 5px;
}

.header-content p {
    color: #64748b;
}

/* Alerts */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* Settings Tabs */
.settings-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    background: white;
    padding: 5px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    flex-wrap: wrap;
}

.tab-btn {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-radius: 8px;
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tab-btn:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.tab-btn.active {
    background: #667eea;
    color: white;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Settings Cards */
.settings-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.settings-card h3 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 25px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.settings-card h3 i {
    color: #667eea;
}

.settings-card h4 {
    font-size: 16px;
    color: #1e293b;
    margin: 20px 0 15px;
}

/* Forms */
.settings-form {
    max-width: 500px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #475569;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Checkbox Groups */
.checkbox-group {
    margin-bottom: 15px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    color: #1e293b;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.help-text {
    margin-top: 5px;
    margin-left: 28px;
    font-size: 12px;
    color: #94a3b8;
}

/* Notification Groups */
.notification-group {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.notification-group:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

/* Two-Factor Authentication */
.twofa-status {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.twofa-status.enabled {
    background: #d1fae5;
    color: #065f46;
}

.twofa-status.disabled {
    background: #fee2e2;
    color: #991b1b;
}

.twofa-status i {
    font-size: 24px;
}

.twofa-status strong {
    display: block;
    margin-bottom: 5px;
}

.twofa-status p {
    font-size: 13px;
    opacity: 0.9;
}

.twofa-setup {
    text-align: center;
    padding: 20px;
}

.qr-code {
    margin: 20px 0;
    padding: 20px;
    background: white;
    display: inline-block;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.qr-code img {
    width: 200px;
    height: 200px;
}

.secret-key {
    margin: 15px 0;
    font-size: 14px;
}

.secret-key code {
    background: #f1f5f9;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 16px;
    letter-spacing: 1px;
}

/* Sessions List */
.sessions-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.session-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: #f8fafc;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.session-item.current {
    background: #eff6ff;
    border-left: 3px solid #667eea;
}

.session-icon {
    width: 40px;
    height: 40px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    font-size: 18px;
}

.session-info {
    flex: 1;
}

.session-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 5px;
    flex-wrap: wrap;
}

.session-device {
    font-weight: 600;
    color: #1e293b;
}

.current-badge {
    display: inline-block;
    padding: 2px 6px;
    background: #667eea;
    color: white;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.session-details {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: #64748b;
}

.btn-revoke {
    padding: 6px 12px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    transition: all 0.2s ease;
}

.btn-revoke:hover {
    background: #fecaca;
}

.session-actions {
    margin-top: 20px;
    text-align: right;
}

/* Delete Account */
.delete-account-warning {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 10px;
    margin-bottom: 20px;
}

.delete-account-warning i {
    font-size: 24px;
}

.delete-account-warning strong {
    display: block;
    margin-bottom: 5px;
}

.delete-account-warning p {
    font-size: 13px;
    opacity: 0.9;
}

/* Buttons */
.btn-primary {
    background: #667eea;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary:hover {
    background: #5a67d8;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #f1f5f9;
    color: #1e293b;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.btn-outline {
    background: transparent;
    color: #667eea;
    border: 2px solid #667eea;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-outline:hover {
    background: #667eea;
    color: white;
}

.btn-outline-danger {
    background: transparent;
    color: #ef4444;
    border: 2px solid #ef4444;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-outline-danger:hover {
    background: #ef4444;
    color: white;
}

.btn-danger {
    background: #ef4444;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-danger:hover {
    background: #dc2626;
}

.form-actions {
    margin-top: 25px;
}

/* No Data */
.no-data {
    color: #94a3b8;
    font-style: italic;
    padding: 20px;
    text-align: center;
}

/* Responsive */
@media (max-width: 768px) {
    .settings-tabs {
        flex-direction: column;
    }
    
    .tab-btn {
        width: 100%;
        justify-content: center;
    }
    
    .settings-card {
        padding: 20px;
    }
    
    .session-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .btn-revoke {
        width: 100%;
        text-align: center;
    }
    
    .session-details {
        flex-direction: column;
        gap: 5px;
    }
    
    .delete-account-warning {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

function showDeleteConfirmation() {
    document.getElementById('deleteConfirmation').style.display = 'block';
    document.getElementById('deleteConfirmation').scrollIntoView({ behavior: 'smooth' });
}

function hideDeleteConfirmation() {
    document.getElementById('deleteConfirmation').style.display = 'none';
}

function getDeviceInfo(userAgent) {
    if (userAgent.includes('Mobile')) return 'Mobile Device';
    if (userAgent.includes('Tablet')) return 'Tablet';
    return 'Desktop Computer';
}
</script>

<?php require_once '../includes/client-footer.php'; ?>