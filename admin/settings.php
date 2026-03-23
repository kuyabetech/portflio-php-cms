<?php
// admin/settings.php
// Site Settings Management with SMTP Configuration

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Site Settings';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Settings']
];

// Default color values
$defaultColors = [
    'primary_color' => '#2563eb',
    'secondary_color' => '#7c3aed',
    'success_color' => '#10b981',
    'warning_color' => '#f59e0b',
    'danger_color' => '#ef4444',
    'info_color' => '#3b82f6',
    'dark_color' => '#0f172a',
    'light_color' => '#f8fafc'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        if ($key !== 'submit' && $key !== 'reset_colors' && $key !== 'test_smtp') {
            $value = sanitize($value);
            // Check if setting exists
            $exists = db()->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                db()->update('site_settings', ['setting_value' => $value], 'setting_key = :key', ['key' => $key]);
            } else {
                db()->insert('site_settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_type' => 'text']);
            }
        }
    }
    
    // Handle reset to default colors
    if (isset($_POST['reset_colors'])) {
        foreach ($defaultColors as $key => $value) {
            $exists = db()->fetch("SELECT id FROM site_settings WHERE setting_key = ?", [$key]);
            if ($exists) {
                db()->update('site_settings', ['setting_value' => $value], 'setting_key = :key', ['key' => $key]);
            } else {
                db()->insert('site_settings', ['setting_key' => $key, 'setting_value' => $value, 'setting_type' => 'color']);
            }
        }
    }
    
    // Handle test SMTP
    if (isset($_POST['test_smtp'])) {
        // This will be handled by AJAX separately
    }
    
    // Handle logo upload
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['site_logo'], 'settings');
        if (isset($upload['success'])) {
            // Delete old logo
            $oldLogo = getSetting('site_logo');
            if ($oldLogo && file_exists(UPLOAD_PATH_SETTINGS . $oldLogo)) {
                unlink(UPLOAD_PATH_SETTINGS . $oldLogo);
            }
            
            $exists = db()->fetch("SELECT id FROM site_settings WHERE setting_key = 'site_logo'");
            if ($exists) {
                db()->update('site_settings', ['setting_value' => $upload['filename']], 'setting_key = :key', ['key' => 'site_logo']);
            } else {
                db()->insert('site_settings', ['setting_key' => 'site_logo', 'setting_value' => $upload['filename'], 'setting_type' => 'image']);
            }
        }
    }
    
    // Handle favicon upload
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['favicon'], 'settings');
        if (isset($upload['success'])) {
            // Delete old favicon
            $oldFavicon = getSetting('favicon');
            if ($oldFavicon && file_exists(UPLOAD_PATH_SETTINGS . $oldFavicon)) {
                unlink(UPLOAD_PATH_SETTINGS . $oldFavicon);
            }
            
            $exists = db()->fetch("SELECT id FROM site_settings WHERE setting_key = 'favicon'");
            if ($exists) {
                db()->update('site_settings', ['setting_value' => $upload['filename']], 'setting_key = :key', ['key' => 'favicon']);
            } else {
                db()->insert('site_settings', ['setting_key' => 'favicon', 'setting_value' => $upload['filename'], 'setting_type' => 'image']);
            }
        }
    }
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Settings updated successfully!'];
    header('Location: settings.php');
    exit;
}

// Get all settings
$settings = [];
$result = db()->fetchAll("SELECT * FROM site_settings");
foreach ($result as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Merge with defaults for missing settings
foreach ($defaultColors as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Set default SMTP values if not set
$smtpDefaults = [
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_encryption' => 'tls',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_from_email' => getSetting('contact_email', ''),
    'smtp_from_name' => SITE_NAME,
    'smtp_reply_to' => ''
];

foreach ($smtpDefaults as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>
        <i class="fas fa-cog"></i> Site Settings
    </h2>
    <div class="header-actions">
        <button class="btn btn-outline" onclick="window.location.href='index.php'">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </button>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible">
        <i class="fas <?php echo $_SESSION['flash']['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo $_SESSION['flash']['message']; ?>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="settings-form" id="settingsForm">
    <!-- General Settings -->
    <div class="settings-section">
        <div class="section-header">
            <i class="fas fa-globe"></i>
            <h3>General Settings</h3>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="site_name">
                    <i class="fas fa-tag"></i>
                    Site Name
                </label>
                <input type="text" id="site_name" name="site_name" 
                       value="<?php echo htmlspecialchars($settings['site_name'] ?? SITE_NAME); ?>"
                       placeholder="My Portfolio">
            </div>
            
            <div class="form-group">
                <label for="site_title">
                    <i class="fas fa-heading"></i>
                    Site Title
                </label>
                <input type="text" id="site_title" name="site_title" 
                       value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>"
                       placeholder="Professional Web Developer Portfolio">
            </div>
        </div>
        
        <div class="form-group">
            <label for="site_description">
                <i class="fas fa-align-left"></i>
                Site Description
            </label>
            <textarea id="site_description" name="site_description" rows="3" 
                      placeholder="Describe your website..."><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="site_keywords">
                <i class="fas fa-key"></i>
                Meta Keywords
            </label>
            <input type="text" id="site_keywords" name="site_keywords" 
                   value="<?php echo htmlspecialchars($settings['site_keywords'] ?? ''); ?>"
                   placeholder="web developer, portfolio, php, laravel, javascript">
            <small>Separate keywords with commas</small>
        </div>
    </div>
    
    <!-- Branding -->
    <div class="settings-section">
        <div class="section-header">
            <i class="fas fa-paint-brush"></i>
            <h3>Branding</h3>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="site_logo">
                    <i class="fas fa-image"></i>
                    Site Logo
                </label>
                <div class="file-input-wrapper">
                    <input type="file" id="site_logo" name="site_logo" accept="image/*">
                    <div class="file-input-label">
                        <i class="fas fa-upload"></i>
                        Choose Logo
                    </div>
                </div>
                <?php if (!empty($settings['site_logo'])): ?>
                <div class="current-image">
                    <img src="<?php echo UPLOAD_URL_SETTINGS . $settings['site_logo']; ?>" 
                         alt="Site Logo">
                    <p>Current logo</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="favicon">
                    <i class="fas fa-star"></i>
                    Favicon
                </label>
                <div class="file-input-wrapper">
                    <input type="file" id="favicon" name="favicon" accept="image/x-icon,image/png">
                    <div class="file-input-label">
                        <i class="fas fa-upload"></i>
                        Choose Favicon
                    </div>
                </div>
                <?php if (!empty($settings['favicon'])): ?>
                <div class="current-image">
                    <img src="<?php echo UPLOAD_URL_SETTINGS . $settings['favicon']; ?>" 
                         alt="Favicon" style="max-width: 32px;">
                    <p>Current favicon</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Color Scheme -->
    <div class="settings-section">
        <div class="section-header">
            <i class="fas fa-palette"></i>
            <h3>Color Scheme</h3>
            <button type="submit" name="reset_colors" class="btn btn-sm btn-outline" value="1">
                <i class="fas fa-undo"></i> Reset to Defaults
            </button>
        </div>
        
        <div class="color-grid">
            <div class="color-item">
                <label for="primary_color">Primary Color</label>
                <div class="color-picker-wrapper">
                    <input type="color" id="primary_color" name="primary_color" 
                           value="<?php echo $settings['primary_color']; ?>">
                    <input type="text" value="<?php echo $settings['primary_color']; ?>" 
                           class="color-value" readonly>
                </div>
            </div>
            
            <div class="color-item">
                <label for="secondary_color">Secondary Color</label>
                <div class="color-picker-wrapper">
                    <input type="color" id="secondary_color" name="secondary_color" 
                           value="<?php echo $settings['secondary_color']; ?>">
                    <input type="text" value="<?php echo $settings['secondary_color']; ?>" 
                           class="color-value" readonly>
                </div>
            </div>
            
            <div class="color-item">
                <label for="success_color">Success Color</label>
                <div class="color-picker-wrapper">
                    <input type="color" id="success_color" name="success_color" 
                           value="<?php echo $settings['success_color']; ?>">
                    <input type="text" value="<?php echo $settings['success_color']; ?>" 
                           class="color-value" readonly>
                </div>
            </div>
            
            <div class="color-item">
                <label for="warning_color">Warning Color</label>
                <div class="color-picker-wrapper">
                    <input type="color" id="warning_color" name="warning_color" 
                           value="<?php echo $settings['warning_color']; ?>">
                    <input type="text" value="<?php echo $settings['warning_color']; ?>" 
                           class="color-value" readonly>
                </div>
            </div>
            
            <div class="color-item">
                <label for="danger_color">Danger Color</label>
                <div class="color-picker-wrapper">
                    <input type="color" id="danger_color" name="danger_color" 
                           value="<?php echo $settings['danger_color']; ?>">
                    <input type="text" value="<?php echo $settings['danger_color']; ?>" 
                           class="color-value" readonly>
                </div>
            </div>
            
            <div class="color-item">
                <label for="info_color">Info Color</label>
                <div class="color-picker-wrapper">
                    <input type="color" id="info_color" name="info_color" 
                           value="<?php echo $settings['info_color']; ?>">
                    <input type="text" value="<?php echo $settings['info_color']; ?>" 
                           class="color-value" readonly>
                </div>
            </div>
            
            <div class="color-item">
                <label for="dark_color">Dark Color</label>
                <div class="color-picker-wrapper">
                    <input type="color" id="dark_color" name="dark_color" 
                           value="<?php echo $settings['dark_color']; ?>">
                    <input type="text" value="<?php echo $settings['dark_color']; ?>" 
                           class="color-value" readonly>
                </div>
            </div>
            
            <div class="color-item">
                <label for="light_color">Light Color</label>
                <div class="color-picker-wrapper">
                    <input type="color" id="light_color" name="light_color" 
                           value="<?php echo $settings['light_color']; ?>">
                    <input type="text" value="<?php echo $settings['light_color']; ?>" 
                           class="color-value" readonly>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contact Information -->
    <div class="settings-section">
        <div class="section-header">
            <i class="fas fa-address-card"></i>
            <h3>Contact Information</h3>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="contact_email">
                    <i class="fas fa-envelope"></i>
                    Contact Email
                </label>
                <input type="email" id="contact_email" name="contact_email" 
                       value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>"
                       placeholder="hello@example.com">
            </div>
            
            <div class="form-group">
                <label for="contact_phone">
                    <i class="fas fa-phone"></i>
                    Contact Phone
                </label>
                <input type="text" id="contact_phone" name="contact_phone" 
                       value="<?php echo htmlspecialchars($settings['contact_phone'] ?? ''); ?>"
                       placeholder="+1 234 567 890">
            </div>
        </div>
        
        <div class="form-group">
            <label for="address">
                <i class="fas fa-map-marker-alt"></i>
                Address
            </label>
            <input type="text" id="address" name="address" 
                   value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>"
                   placeholder="New York, NY">
        </div>
    </div>
    
    <!-- Social Media -->
    <div class="settings-section">
        <div class="section-header">
            <i class="fas fa-share-alt"></i>
            <h3>Social Media Links</h3>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="github_url">
                    <i class="fab fa-github"></i>
                    GitHub
                </label>
                <input type="url" id="github_url" name="github_url" 
                       value="<?php echo htmlspecialchars($settings['github_url'] ?? ''); ?>"
                       placeholder="https://github.com/username">
            </div>
            
            <div class="form-group">
                <label for="linkedin_url">
                    <i class="fab fa-linkedin"></i>
                    LinkedIn
                </label>
                <input type="url" id="linkedin_url" name="linkedin_url" 
                       value="<?php echo htmlspecialchars($settings['linkedin_url'] ?? ''); ?>"
                       placeholder="https://linkedin.com/in/username">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="twitter_url">
                    <i class="fab fa-twitter"></i>
                    Twitter
                </label>
                <input type="url" id="twitter_url" name="twitter_url" 
                       value="<?php echo htmlspecialchars($settings['twitter_url'] ?? ''); ?>"
                       placeholder="https://twitter.com/username">
            </div>
            
            <div class="form-group">
                <label for="facebook_url">
                    <i class="fab fa-facebook"></i>
                    Facebook
                </label>
                <input type="url" id="facebook_url" name="facebook_url" 
                       value="<?php echo htmlspecialchars($settings['facebook_url'] ?? ''); ?>"
                       placeholder="https://facebook.com/username">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="instagram_url">
                    <i class="fab fa-instagram"></i>
                    Instagram
                </label>
                <input type="url" id="instagram_url" name="instagram_url" 
                       value="<?php echo htmlspecialchars($settings['instagram_url'] ?? ''); ?>"
                       placeholder="https://instagram.com/username">
            </div>
            
            <div class="form-group">
                <label for="youtube_url">
                    <i class="fab fa-youtube"></i>
                    YouTube
                </label>
                <input type="url" id="youtube_url" name="youtube_url" 
                       value="<?php echo htmlspecialchars($settings['youtube_url'] ?? ''); ?>"
                       placeholder="https://youtube.com/@username">
            </div>
        </div>
    </div>
    
    <!-- Newsletter Settings -->
    <div class="settings-section">
        <div class="section-header">
            <i class="fas fa-newspaper"></i>
            <h3>Newsletter Settings</h3>
        </div>
        
        <div class="form-group checkbox">
            <label class="checkbox-label">
                <input type="checkbox" name="newsletter_enabled" value="1" 
                       <?php echo (isset($settings['newsletter_enabled']) && $settings['newsletter_enabled'] == '1') ? 'checked' : ''; ?>>
                <span class="checkbox-text">Enable Newsletter Signup</span>
            </label>
        </div>
        
        <div class="form-group">
            <label for="mailchimp_api_key">Mailchimp API Key</label>
            <input type="text" id="mailchimp_api_key" name="mailchimp_api_key" 
                   value="<?php echo htmlspecialchars($settings['mailchimp_api_key'] ?? ''); ?>"
                   placeholder="Enter your Mailchimp API key">
            <small>Optional - for advanced email marketing</small>
        </div>
        
        <div class="form-group">
            <label for="mailchimp_list_id">Mailchimp List ID</label>
            <input type="text" id="mailchimp_list_id" name="mailchimp_list_id" 
                   value="<?php echo htmlspecialchars($settings['mailchimp_list_id'] ?? ''); ?>"
                   placeholder="Enter your Mailchimp list ID">
        </div>
    </div>
    
    <!-- SEO Settings -->
    <div class="settings-section">
        <div class="section-header">
            <i class="fas fa-search"></i>
            <h3>SEO Settings</h3>
        </div>
        
        <div class="form-group">
            <label for="google_analytics">
                <i class="fab fa-google"></i>
                Google Analytics ID
            </label>
            <input type="text" id="google_analytics" name="google_analytics" 
                   value="<?php echo htmlspecialchars($settings['google_analytics'] ?? ''); ?>"
                   placeholder="G-XXXXXXXXXX">
        </div>
        
        <div class="form-group">
            <label for="facebook_pixel">
                <i class="fab fa-facebook"></i>
                Facebook Pixel ID
            </label>
            <input type="text" id="facebook_pixel" name="facebook_pixel" 
                   value="<?php echo htmlspecialchars($settings['facebook_pixel'] ?? ''); ?>"
                   placeholder="Enter your Facebook Pixel ID">
        </div>
    </div>
    
    <!-- Email Settings (SMTP) -->
    <div class="settings-section">
        <div class="section-header">
            <i class="fas fa-envelope-open-text"></i>
            <h3>SMTP Email Settings</h3>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="smtp_host">
                    <i class="fas fa-server"></i>
                    SMTP Host
                </label>
                <input type="text" id="smtp_host" name="smtp_host" 
                       value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                       placeholder="smtp.gmail.com">
                <small>Your SMTP server address</small>
            </div>
            
            <div class="form-group">
                <label for="smtp_port">
                    <i class="fas fa-plug"></i>
                    SMTP Port
                </label>
                <input type="number" id="smtp_port" name="smtp_port" 
                       value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>"
                       placeholder="587">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="smtp_username">
                    <i class="fas fa-user"></i>
                    SMTP Username
                </label>
                <input type="text" id="smtp_username" name="smtp_username" 
                       value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                       placeholder="your-email@gmail.com">
            </div>
            
            <div class="form-group">
                <label for="smtp_password">
                    <i class="fas fa-lock"></i>
                    SMTP Password
                </label>
                <input type="password" id="smtp_password" name="smtp_password" 
                       value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>"
                       placeholder="Your SMTP password or app password">
                <small>For Gmail, use an <a href="https://support.google.com/accounts/answer/185833" target="_blank">App Password</a></small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="smtp_encryption">
                    <i class="fas fa-shield-alt"></i>
                    Encryption
                </label>
                <select id="smtp_encryption" name="smtp_encryption">
                    <option value="tls" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] === 'tls') ? 'selected' : ''; ?>>TLS (recommended)</option>
                    <option value="ssl" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] === 'ssl') ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo (isset($settings['smtp_encryption']) && $settings['smtp_encryption'] === 'none') ? 'selected' : ''; ?>>None</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="smtp_from_email">
                    <i class="fas fa-envelope"></i>
                    From Email
                </label>
                <input type="email" id="smtp_from_email" name="smtp_from_email" 
                       value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? $settings['contact_email'] ?? ''); ?>"
                       placeholder="noreply@yourdomain.com">
                <small>Email address that emails will come from</small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="smtp_from_name">
                    <i class="fas fa-tag"></i>
                    From Name
                </label>
                <input type="text" id="smtp_from_name" name="smtp_from_name" 
                       value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? SITE_NAME); ?>"
                       placeholder="<?php echo SITE_NAME; ?>">
                <small>Name that appears in the From field</small>
            </div>
            
            <div class="form-group">
                <label for="smtp_reply_to">
                    <i class="fas fa-reply"></i>
                    Reply-To Email
                </label>
                <input type="email" id="smtp_reply_to" name="smtp_reply_to" 
                       value="<?php echo htmlspecialchars($settings['smtp_reply_to'] ?? ''); ?>"
                       placeholder="contact@yourdomain.com">
                <small>Optional: replies will go to this address</small>
            </div>
        </div>
        
        <div class="form-group">
            <button type="button" class="btn btn-outline" onclick="testSMTP()" id="testSMTPBtn">
                <i class="fas fa-vial"></i> Test SMTP Connection
            </button>
            <div id="smtpTestResult" style="margin-top: 15px;"></div>
        </div>
    </div>
    
    <!-- Maintenance Mode -->
    <div class="settings-section">
        <div class="section-header">
            <i class="fas fa-tools"></i>
            <h3>Maintenance Mode</h3>
        </div>
        
        <div class="form-group checkbox">
            <label class="checkbox-label">
                <input type="checkbox" name="maintenance_mode" value="1" 
                       <?php echo !empty($settings['maintenance_mode']) ? 'checked' : ''; ?>>
                <span class="checkbox-text">Enable Maintenance Mode</span>
            </label>
        </div>
        
        <div class="form-group">
            <label for="maintenance_message">Maintenance Message</label>
            <textarea id="maintenance_message" name="maintenance_message" rows="3" 
                      placeholder="Site is under maintenance. Please check back later."><?php echo htmlspecialchars($settings['maintenance_message'] ?? 'Site is under maintenance. Please check back later.'); ?></textarea>
        </div>
    </div>
    
    <div class="form-actions">
        <button type="submit" name="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i>
            Save All Settings
        </button>
        <button type="button" class="btn btn-outline" onclick="window.location.reload()">
            <i class="fas fa-sync-alt"></i>
            Refresh
        </button>
    </div>
</form>

<!-- SMTP Test Result Modal -->
<div class="modal" id="smtpTestModal" style="display: none;">
    <div class="modal-overlay" onclick="closeSMTPModal()"></div>
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3>SMTP Test Result</h3>
            <button class="close-modal" onclick="closeSMTPModal()">&times;</button>
        </div>
        <div class="modal-body" id="smtpTestModalBody">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>Testing connection...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="closeSMTPModal()">Close</button>
        </div>
    </div>
</div>

<style>
/* Settings Section Styles */
.settings-section {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border: 1px solid var(--gray-200);
}

.settings-section .section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--gray-200);
}

.settings-section .section-header i {
    font-size: 1.3rem;
    color: var(--primary);
    background: rgba(37,99,235,0.1);
    padding: 10px;
    border-radius: 10px;
}

.settings-section .section-header h3 {
    flex: 1;
    font-size: 1.2rem;
    margin: 0;
    color: var(--dark);
}

.settings-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 15px;
}

.settings-form .form-group {
    margin-bottom: 15px;
}

.settings-form label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--gray-700);
    font-size: 0.95rem;
}

.settings-form label i {
    color: var(--primary);
    width: 20px;
    font-size: 1.1rem;
}

.settings-form input[type="text"],
.settings-form input[type="email"],
.settings-form input[type="password"],
.settings-form input[type="url"],
.settings-form input[type="number"],
.settings-form textarea,
.settings-form select {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.settings-form input:focus,
.settings-form textarea:focus,
.settings-form select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(37,99,235,0.1);
}

.settings-form textarea {
    resize: vertical;
    min-height: 80px;
}

.settings-form small {
    display: block;
    margin-top: 5px;
    color: var(--gray-500);
    font-size: 0.8rem;
}

/* File Input */
.file-input-wrapper {
    position: relative;
    margin-bottom: 10px;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
}

.file-input-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: var(--gray-100);
    border: 2px dashed var(--gray-300);
    border-radius: 10px;
    color: var(--gray-600);
    font-size: 0.95rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.file-input-wrapper:hover .file-input-label {
    background: var(--gray-200);
    border-color: var(--primary);
    color: var(--primary);
}

/* Current Image */
.current-image {
    margin-top: 10px;
    padding: 15px;
    background: var(--gray-100);
    border-radius: 8px;
    border: 1px solid var(--gray-200);
    text-align: center;
}

.current-image img {
    max-width: 100%;
    max-height: 100px;
    border-radius: 6px;
    margin-bottom: 5px;
}

.current-image p {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin: 0;
}

/* Color Grid */
.color-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.color-item {
    background: var(--gray-100);
    padding: 15px;
    border-radius: 10px;
    border: 1px solid var(--gray-200);
}

.color-item label {
    display: block;
    margin-bottom: 10px;
    font-weight: 500;
    color: var(--gray-700);
}

.color-picker-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
}

.color-picker-wrapper input[type="color"] {
    width: 50px;
    height: 40px;
    padding: 2px;
    border: 2px solid var(--gray-300);
    border-radius: 6px;
    cursor: pointer;
}

.color-value {
    flex: 1;
    padding: 8px 10px;
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
    font-family: monospace;
    font-size: 0.9rem;
}

/* Checkbox */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.checkbox-text {
    font-weight: 500;
    color: var(--gray-700);
}

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: rgba(16,185,129,0.1);
    color: #0b5e42;
    border: 1px solid rgba(16,185,129,0.3);
}

.alert-error {
    background: rgba(239,68,68,0.1);
    color: #991b1b;
    border: 1px solid rgba(239,68,68,0.3);
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid var(--gray-200);
}

.btn-lg {
    padding: 12px 30px;
    font-size: 1rem;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.85rem;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 500px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    overflow: hidden;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translate(-50%, -60%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: var(--gray-500);
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}

.close-modal:hover {
    background: var(--danger);
    color: white;
}

.modal-body {
    padding: 20px;
    max-height: 400px;
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--gray-200);
    display: flex;
    justify-content: flex-end;
}

/* Responsive */
@media (max-width: 768px) {
    .settings-form .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .color-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions button {
        width: 100%;
    }
}
</style>

<script>
// Live color preview
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    colorInput.addEventListener('input', function() {
        const valueInput = this.nextElementSibling;
        if (valueInput && valueInput.classList.contains('color-value')) {
            valueInput.value = this.value;
        }
    });
});

// File input preview
document.querySelectorAll('input[type="file"]').forEach(fileInput => {
    fileInput.addEventListener('change', function(e) {
        const label = this.nextElementSibling;
        if (label && label.classList.contains('file-input-label')) {
            const fileName = this.files[0] ? this.files[0].name : 'Choose file';
            label.innerHTML = `<i class="fas fa-check"></i> ${fileName}`;
        }
    });
});

// Test SMTP connection
function testSMTP() {
    const modal = document.getElementById('smtpTestModal');
    const modalBody = document.getElementById('smtpTestModalBody');
    const btn = document.getElementById('testSMTPBtn');
    
    // Show modal with loading
    modal.style.display = 'block';
    modalBody.innerHTML = `
        <div class="text-center">
            <i class="fas fa-spinner fa-spin fa-3x"></i>
            <p>Testing SMTP connection...</p>
        </div>
    `;
    
   // Test SMTP connection - IMPROVED VERSION
function testSMTP() {
    const modal = document.getElementById('smtpTestModal');
    const modalBody = document.getElementById('smtpTestModalBody');
    const btn = document.getElementById('testSMTPBtn');
    
    // Show modal with loading
    modal.style.display = 'block';
    modalBody.innerHTML = `
        <div class="text-center">
            <i class="fas fa-spinner fa-spin fa-3x"></i>
            <p>Testing SMTP connection...</p>
            <p><small>Connecting to ${document.getElementById('smtp_host').value}:${document.getElementById('smtp_port').value}</small></p>
        </div>
    `;
    
    // Get SMTP settings from form
    const data = {
        host: document.getElementById('smtp_host').value,
        port: document.getElementById('smtp_port').value,
        encryption: document.getElementById('smtp_encryption').value,
        username: document.getElementById('smtp_username').value,
        password: document.getElementById('smtp_password').value
    };
    
    console.log('Testing SMTP with:', data); // For debugging
    
    // Send test request
    fetch('ajax/test-smtp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            modalBody.innerHTML = `
                <div class="alert alert-success" style="text-align: center; padding: 20px;">
                    <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 15px;"></i>
                    <h4 style="margin-bottom: 10px;">Connection Successful!</h4>
                    <p>${data.message}</p>
                </div>
            `;
        } else {
            modalBody.innerHTML = `
                <div class="alert alert-error" style="text-align: center; padding: 20px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #ef4444; margin-bottom: 15px;"></i>
                    <h4 style="margin-bottom: 10px;">Connection Failed</h4>
                    <p>${data.message}</p>
                    <p style="margin-top: 15px; font-size: 0.9rem;">Please check your SMTP settings and try again.</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        modalBody.innerHTML = `
            <div class="alert alert-error" style="text-align: center; padding: 20px;">
                <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #ef4444; margin-bottom: 15px;"></i>
                <h4 style="margin-bottom: 10px;">Connection Error</h4>
                <p>Could not connect to test endpoint.</p>
                <p style="margin-top: 15px; font-size: 0.9rem;">Technical details: ${error.message}</p>
            </div>
        `;
    });
}
    
    // Send test request
    fetch('ajax/test-smtp.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            modalBody.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle fa-2x"></i>
                    <div>
                        <h4>Connection Successful!</h4>
                        <p>${data.message}</p>
                        <p>Your SMTP settings are correct and working.</p>
                    </div>
                </div>
            `;
        } else {
            modalBody.innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle fa-2x"></i>
                    <div>
                        <h4>Connection Failed</h4>
                        <p>${data.message}</p>
                        <p>Please check your SMTP settings and try again.</p>
                    </div>
                </div>
            `;
        }
    })
    .catch(error => {
        modalBody.innerHTML = `
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle fa-2x"></i>
                <div>
                    <h4>Connection Error</h4>
                    <p>Could not connect to test endpoint. Please try again.</p>
                </div>
            </div>
        `;
    });
}

function closeSMTPModal() {
    document.getElementById('smtpTestModal').style.display = 'none';
}

// Close modal when clicking overlay
document.querySelector('.modal-overlay')?.addEventListener('click', closeSMTPModal);

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSMTPModal();
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>