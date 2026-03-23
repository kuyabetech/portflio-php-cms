<?php
// admin/profile.php
// Admin Profile Management - FULLY RESPONSIVE

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'My Profile';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Profile']
];

$user = Auth::user();
$error = '';
$success = '';

// Helper function to get device info


// Helper function to get location from IP (simplified)
function getLocationFromIP($ip) {
    // Skip local IPs
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0) {
        return 'Local Network';
    }
    
    // You can integrate with a geolocation API here
    // For now, return a placeholder
    return 'Unknown Location';
    
    /* Example with ip-api.com (free, no API key required)
    $ch = curl_init("http://ip-api.com/json/{$ip}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data['status'] === 'success') {
            return $data['city'] . ', ' . $data['country'];
        }
    }
    return 'Unknown';
    */
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $data = [
            'full_name' => sanitize($_POST['full_name']),
            'email' => sanitize($_POST['email']),
            'bio' => sanitize($_POST['bio'])
        ];
        
        // Handle profile image upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['profile_image'], 'profiles/');
            if (isset($upload['success'])) {
                // Delete old image
                if ($user['profile_image'] && file_exists(UPLOAD_PATH . 'profiles/' . $user['profile_image'])) {
                    unlink(UPLOAD_PATH . 'profiles/' . $user['profile_image']);
                }
                $data['profile_image'] = $upload['filename'];
            }
        }
        
        db()->update('users', $data, 'id = ?', [$user['id']]);
        $success = 'Profile updated successfully!';
        $user = Auth::user(); // Refresh user data
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        // Verify current password
        $stored = db()->fetch("SELECT password_hash FROM users WHERE id = ?", [$user['id']]);
        
        if (!password_verify($current, $stored['password_hash'])) {
            $error = 'Current password is incorrect';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match';
        } else {
            $hash = Auth::hashPassword($new);
            db()->update('users', ['password_hash' => $hash], 'id = ?', [$user['id']]);
            $success = 'Password changed successfully!';
        }
    }
}

// Get login logs
$logs = db()->fetchAll(
    "SELECT * FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$user['id']]
);

// Include header
require_once 'includes/header.php';
?>

<div class="profile-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h2>
                <i class="fas fa-user-circle"></i> 
                My Profile
            </h2>
            <p>Manage your account settings and preferences</p>
        </div>
        <div class="header-actions">
            <span class="last-login">
                <i class="fas fa-clock"></i> 
                Last login: <?php echo isset($_SESSION['login_time']) ? date('M d, Y H:i', $_SESSION['login_time']) : 'N/A'; ?>
            </span>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success" id="flashMessage">
            <div class="alert-content">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error" id="flashMessage">
            <div class="alert-content">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Mobile Navigation Tabs -->
    <div class="mobile-profile-tabs">
        <button class="tab-btn active" onclick="switchProfileTab('info')">
            <i class="fas fa-user"></i> Profile
        </button>
        <button class="tab-btn" onclick="switchProfileTab('security')">
            <i class="fas fa-key"></i> Security
        </button>
        <button class="tab-btn" onclick="switchProfileTab('activity')">
            <i class="fas fa-history"></i> Activity
        </button>
    </div>

    <!-- Profile Grid -->
    <div class="profile-grid">
        <!-- Profile Information Card -->
        <div class="profile-card" id="info-card">
            <div class="card-header">
                <h3><i class="fas fa-user-edit"></i> Profile Information</h3>
                <button class="edit-toggle" onclick="toggleEditMode()">
                    <i class="fas fa-pen"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="profile-form" id="profileForm">
                <div class="profile-avatar-section">
                    <div class="profile-avatar-wrapper">
                        <div class="profile-avatar" id="profileAvatar">
                            <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo UPLOAD_URL . 'profiles/' . $user['profile_image']; ?>" 
                                 alt="Profile" class="avatar-image" id="avatarImage">
                            <?php else: ?>
                            <div class="avatar-placeholder" id="avatarPlaceholder">
                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="avatar-upload-overlay" id="avatarUploadOverlay">
                            <i class="fas fa-camera"></i>
                            <span>Change Photo</span>
                        </div>
                        <input type="file" id="profile_image" name="profile_image" 
                               accept="image/*" style="display: none;">
                    </div>
                    
                    <div class="avatar-info">
                        <h4><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></h4>
                        <p class="user-role"><?php echo ucfirst($user['role']); ?></p>
                        <p class="upload-hint">JPG, PNG or GIF. Max 2MB.</p>
                    </div>
                </div>

                <div class="form-sections">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h4>Basic Information</h4>
                        
                        <div class="form-group">
                            <label for="username">
                                <i class="fas fa-at"></i> Username
                            </label>
                            <div class="input-wrapper readonly">
                                <input type="text" id="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" 
                                       readonly disabled>
                                <span class="input-note">Username cannot be changed</span>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">
                                    <i class="fas fa-signature"></i> Full Name
                                </label>
                                <input type="text" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                       class="editable-field" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" 
                                       required class="editable-field" disabled>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="bio">
                                <i class="fas fa-align-left"></i> Bio / About
                            </label>
                            <textarea id="bio" name="bio" rows="4" 
                                      placeholder="Tell something about yourself..."
                                      class="editable-field" disabled><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Account Details -->
                    <div class="form-section">
                        <h4>Account Details</h4>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Account Created</span>
                                <span class="info-value"><?php echo isset($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : 'N/A'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Updated</span>
                                <span class="info-value"><?php echo !empty($user['updated_at']) ? date('M d, Y', strtotime($user['updated_at'])) : 'Never'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">User Role</span>
                                <span class="info-value role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Account Status</span>
                                <span class="info-value status-badge <?php echo !empty($user['is_active']) ? 'active' : 'inactive'; ?>">
                                    <?php echo !empty($user['is_active']) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions" id="formActions" style="display: none;">
                    <button type="button" class="btn btn-outline" onclick="cancelEdit()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Security Card (Change Password) -->
        <div class="profile-card" id="security-card" style="display: none;">
            <div class="card-header">
                <h3><i class="fas fa-shield-alt"></i> Security Settings</h3>
            </div>
            
            <form method="POST" class="password-form" id="passwordForm">
                <div class="form-section">
                    <h4>Change Password</h4>
                    
                    <div class="form-group">
                        <label for="current_password">
                            <i class="fas fa-lock"></i> Current Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" id="current_password" name="current_password" 
                                   class="password-input" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password">
                            <i class="fas fa-key"></i> New Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" id="new_password" name="new_password" 
                                   class="password-input" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-requirements">
                            <p>Password must contain:</p>
                            <ul>
                                <li id="req-length">✓ At least 8 characters</li>
                                <li id="req-lower">✓ One lowercase letter</li>
                                <li id="req-upper">✓ One uppercase letter</li>
                                <li id="req-number">✓ One number</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-check-circle"></i> Confirm New Password
                        </label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="password-input" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-match" id="passwordMatch"></div>
                    </div>

                    <div class="password-strength-meter">
                        <div class="strength-label">Password Strength:</div>
                        <div class="strength-bars">
                            <div class="strength-bar" id="strengthBar1"></div>
                            <div class="strength-bar" id="strengthBar2"></div>
                            <div class="strength-bar" id="strengthBar3"></div>
                            <div class="strength-bar" id="strengthBar4"></div>
                        </div>
                        <span class="strength-text" id="strengthText">Enter password</span>
                    </div>
                </div>

                <div class="form-section">
                    <h4>Two-Factor Authentication</h4>
                    <div class="twofa-option">
                        <div class="twofa-info">
                            <i class="fas fa-mobile-alt"></i>
                            <div>
                                <strong>Two-Factor Authentication</strong>
                                <p>Add an extra layer of security to your account</p>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="alert('2FA setup coming soon!')">
                            Enable 2FA
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" name="change_password" class="btn btn-primary btn-large">
                        <i class="fas fa-key"></i> Update Password
                    </button>
                </div>
            </form>
        </div>

        <!-- Login History Card -->
        <div class="profile-card full-width" id="activity-card" style="display: none;">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Login Activity</h3>
                <button class="refresh-btn" onclick="refreshActivity()" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            
            <div class="activity-timeline">
                <?php foreach ($logs as $index => $log): 
                    $isCurrent = $index === 0; // First one is most recent
                ?>
                <div class="timeline-item <?php echo $isCurrent ? 'current' : ''; ?>">
                    <div class="timeline-icon">
                        <i class="fas <?php echo $isCurrent ? 'fa-circle' : 'fa-history'; ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="timeline-date">
                                <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                            </span>
                            <span class="timeline-time">
                                <?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                            </span>
                            <?php if ($isCurrent): ?>
                            <span class="current-badge">Current Session</span>
                            <?php endif; ?>
                        </div>
                        <div class="timeline-details">
                            <div class="detail-item">
                                <i class="fas fa-network-wired"></i>
                                <code><?php echo $log['ip_address']; ?></code>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-globe"></i>
                                <span>Location: <?php echo getLocationFromIP($log['ip_address']); ?></span>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-laptop"></i>
                                <span><?php echo getDeviceInfo($log['user_agent']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <p>No login history available</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Active Sessions Card -->
        <div class="profile-card full-width">
            <div class="card-header">
                <h3><i class="fas fa-globe"></i> Active Sessions</h3>
                <button class="btn btn-outline btn-sm" onclick="alert('This would log out other sessions')">
                    <i class="fas fa-sign-out-alt"></i> Log Out Others
                </button>
            </div>
            
            <div class="sessions-grid">
                <div class="session-card current">
                    <div class="session-header">
                        <div class="session-device">
                            <i class="fas fa-laptop"></i>
                            <strong>Current Session</strong>
                        </div>
                        <span class="session-badge">Active Now</span>
                    </div>
                    <div class="session-details">
                        <div class="session-row">
                            <i class="fas fa-info-circle"></i>
                            <span><?php echo getDeviceInfo($_SERVER['HTTP_USER_AGENT']); ?></span>
                        </div>
                        <div class="session-row">
                            <i class="fas fa-network-wired"></i>
                            <span>IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></span>
                        </div>
                        <div class="session-row">
                            <i class="fas fa-clock"></i>
                            <span>Started: <?php echo isset($_SESSION['login_time']) ? date('M d, Y H:i:s', $_SESSION['login_time']) : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ========================================
   MOBILE-FIRST RESPONSIVE STYLES
   ======================================== */

:root {
    --primary: #667eea;
    --primary-dark: #5a67d8;
    --primary-light: #e6e9ff;
    --success: #10b981;
    --success-light: #d1fae5;
    --warning: #f59e0b;
    --warning-light: #fef3c7;
    --danger: #ef4444;
    --danger-light: #fee2e2;
    --dark: #1e293b;
    --gray-800: #334155;
    --gray-700: #475569;
    --gray-600: #64748b;
    --gray-500: #94a3b8;
    --gray-400: #cbd5e1;
    --gray-300: #d1d5db;
    --gray-200: #e2e8f0;
    --gray-100: #f1f5f9;
    --gray-50: #f8fafc;
    --white: #ffffff;
    
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --radius-xl: 16px;
    --radius-full: 9999px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    background: var(--gray-50);
    color: var(--dark);
    line-height: 1.5;
}

.profile-page {
    padding: 15px;
    max-width: 1400px;
    margin: 0 auto;
}

/* ========================================
   PAGE HEADER
   ======================================== */

.page-header {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-md);
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.header-content h2 {
    font-size: 24px;
    color: var(--dark);
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-content h2 i {
    color: var(--primary);
    font-size: 28px;
}

.header-content p {
    color: var(--gray-500);
    font-size: 14px;
}

.last-login {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: var(--gray-100);
    border-radius: var(--radius-md);
    font-size: 13px;
    color: var(--gray-600);
    white-space: nowrap;
}

.last-login i {
    color: var(--primary);
    font-size: 14px;
}

/* ========================================
   ALERTS
   ======================================== */

.alert {
    padding: 16px 20px;
    border-radius: var(--radius-lg);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    animation: slideIn 0.3s ease;
    background: var(--white);
    box-shadow: var(--shadow-lg);
}

.alert-success {
    background: var(--success-light);
    color: #065f46;
    border-left: 4px solid var(--success);
}

.alert-error {
    background: var(--danger-light);
    color: #991b1b;
    border-left: 4px solid var(--danger);
}

.alert-content {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}

.alert-content i {
    font-size: 20px;
}

.alert-close {
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    color: currentColor;
    opacity: 0.5;
    transition: opacity 0.2s ease;
    padding: 0 8px;
    line-height: 1;
}

.alert-close:hover {
    opacity: 1;
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

/* ========================================
   MOBILE PROFILE TABS
   ======================================== */

.mobile-profile-tabs {
    display: flex;
    gap: 5px;
    background: var(--white);
    padding: 5px;
    border-radius: var(--radius-lg);
    margin-bottom: 20px;
    box-shadow: var(--shadow-md);
}

.mobile-profile-tabs .tab-btn {
    flex: 1;
    padding: 12px 5px;
    border: none;
    background: transparent;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 500;
    color: var(--gray-600);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    -webkit-tap-highlight-color: transparent;
}

.mobile-profile-tabs .tab-btn i {
    font-size: 14px;
}

.mobile-profile-tabs .tab-btn.active {
    background: var(--primary);
    color: var(--white);
}

.mobile-profile-tabs .tab-btn:active {
    transform: scale(0.96);
}

/* ========================================
   PROFILE GRID
   ======================================== */

.profile-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Profile Cards */
.profile-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow-md);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.profile-card:hover {
    box-shadow: var(--shadow-lg);
}

.profile-card.full-width {
    width: 100%;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--gray-200);
}

.card-header h3 {
    font-size: 18px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.card-header h3 i {
    color: var(--primary);
    font-size: 20px;
}

.edit-toggle {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    border: none;
    background: var(--gray-100);
    color: var(--gray-600);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.edit-toggle:hover {
    background: var(--primary);
    color: var(--white);
}

.edit-toggle:active {
    transform: scale(0.95);
}

.refresh-btn {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    border: none;
    background: var(--gray-100);
    color: var(--gray-600);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.refresh-btn:hover {
    background: var(--primary);
    color: var(--white);
    transform: rotate(180deg);
}

.refresh-btn:active {
    transform: rotate(180deg) scale(0.95);
}

/* ========================================
   PROFILE AVATAR
   ======================================== */

.profile-avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--gray-200);
}

.profile-avatar-wrapper {
    position: relative;
    width: 120px;
    height: 120px;
    cursor: pointer;
}

.profile-avatar {
    width: 100%;
    height: 100%;
    border-radius: var(--radius-full);
    overflow: hidden;
    border: 4px solid var(--primary-light);
    box-shadow: var(--shadow-lg);
}

.avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 600;
}

.avatar-upload-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0,0,0,0.7);
    color: white;
    text-align: center;
    padding: 8px;
    border-radius: 0 0 var(--radius-full) var(--radius-full);
    font-size: 12px;
    opacity: 0;
    transition: opacity 0.3s ease;
    backdrop-filter: blur(4px);
}

.profile-avatar-wrapper:hover .avatar-upload-overlay {
    opacity: 1;
}

.avatar-info {
    text-align: center;
}

.avatar-info h4 {
    font-size: 18px;
    color: var(--dark);
    margin-bottom: 5px;
}

.user-role {
    color: var(--primary);
    font-weight: 500;
    margin-bottom: 5px;
    font-size: 14px;
}

.upload-hint {
    font-size: 12px;
    color: var(--gray-500);
}

/* ========================================
   FORM SECTIONS
   ======================================== */

.form-sections {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.form-section {
    background: var(--gray-50);
    padding: 20px;
    border-radius: var(--radius-lg);
}

.form-section h4 {
    font-size: 16px;
    color: var(--dark);
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-section h4 i {
    color: var(--primary);
}

.form-row {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--gray-700);
    font-size: 14px;
}

.form-group label i {
    color: var(--primary);
    margin-right: 5px;
    width: 16px;
}

.input-wrapper {
    position: relative;
}

.input-wrapper input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-md);
    font-size: 14px;
    transition: all 0.2s ease;
    background: var(--white);
}

.input-wrapper.readonly input {
    background: var(--gray-100);
    color: var(--gray-600);
    border-color: var(--gray-200);
    cursor: not-allowed;
}

.input-note {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--gray-500);
}

input.editable-field, textarea.editable-field {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-md);
    font-size: 14px;
    transition: all 0.2s ease;
    background: var(--gray-100);
    color: var(--gray-600);
    cursor: not-allowed;
    font-family: inherit;
}

input.editable-field:enabled, textarea.editable-field:enabled {
    background: var(--white);
    color: var(--dark);
    border-color: var(--primary);
    cursor: text;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

textarea.editable-field {
    resize: vertical;
    min-height: 100px;
}

/* ========================================
   INFO GRID
   ======================================== */

.info-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 12px;
    background: var(--white);
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-200);
}

.info-label {
    font-size: 12px;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 15px;
    font-weight: 500;
    color: var(--dark);
}

.role-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 600;
}

.role-badge.role-admin { 
    background: var(--warning-light); 
    color: #92400e; 
}

.role-badge.role-manager { 
    background: var(--success-light); 
    color: #065f46; 
}

.role-badge.role-user { 
    background: #e0f2fe; 
    color: #0369a1; 
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 600;
}

.status-badge.active {
    background: var(--success-light);
    color: #065f46;
}

.status-badge.inactive {
    background: var(--danger-light);
    color: #991b1b;
}

/* ========================================
   PASSWORD INPUT
   ======================================== */

.password-input-wrapper {
    position: relative;
}

.password-input {
    width: 100%;
    padding: 12px 45px 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-md);
    font-size: 14px;
    transition: all 0.2s ease;
    background: var(--white);
}

.password-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    padding: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
}

.toggle-password:hover {
    color: var(--primary);
}

.toggle-password:active {
    transform: translateY(-50%) scale(0.95);
}

/* ========================================
   PASSWORD REQUIREMENTS
   ======================================== */

.password-requirements {
    margin-top: 10px;
    padding: 15px;
    background: var(--gray-100);
    border-radius: var(--radius-md);
    font-size: 12px;
}

.password-requirements p {
    color: var(--gray-600);
    margin-bottom: 8px;
    font-weight: 500;
}

.password-requirements ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.password-requirements li {
    color: var(--gray-500);
    margin-bottom: 5px;
    padding-left: 24px;
    position: relative;
    transition: color 0.2s ease;
}

.password-requirements li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border-radius: var(--radius-full);
    background: var(--gray-300);
    transition: all 0.2s ease;
}

.password-requirements li.valid {
    color: var(--success);
}

.password-requirements li.valid::before {
    background: var(--success);
    content: '✓';
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
}

.password-match {
    margin-top: 8px;
    font-size: 13px;
    min-height: 24px;
}

.match-success {
    color: var(--success);
    display: flex;
    align-items: center;
    gap: 5px;
}

.match-error {
    color: var(--danger);
    display: flex;
    align-items: center;
    gap: 5px;
}

/* ========================================
   PASSWORD STRENGTH METER
   ======================================== */

.password-strength-meter {
    margin: 15px 0;
}

.strength-label {
    font-size: 13px;
    color: var(--gray-600);
    margin-bottom: 8px;
}

.strength-bars {
    display: flex;
    gap: 6px;
    margin-bottom: 8px;
}

.strength-bar {
    flex: 1;
    height: 6px;
    background: var(--gray-200);
    border-radius: var(--radius-full);
    transition: all 0.3s ease;
}

.strength-bar.active {
    background: var(--primary);
}

.strength-bar.active:nth-child(1) { background: var(--danger); }
.strength-bar.active:nth-child(2) { background: var(--warning); }
.strength-bar.active:nth-child(3) { background: #3b82f6; }
.strength-bar.active:nth-child(4) { background: var(--success); }

.strength-text {
    font-size: 13px;
    color: var(--gray-500);
    font-weight: 500;
}

/* ========================================
   TWO FACTOR AUTH
   ======================================== */

.twofa-option {
    display: flex;
    flex-direction: column;
    gap: 15px;
    padding: 15px;
    background: var(--white);
    border-radius: var(--radius-md);
    border: 1px solid var(--gray-200);
}

.twofa-info {
    display: flex;
    gap: 15px;
    align-items: center;
}

.twofa-info i {
    font-size: 28px;
    color: var(--primary);
    background: var(--primary-light);
    padding: 10px;
    border-radius: var(--radius-md);
}

.twofa-info strong {
    font-size: 15px;
    color: var(--dark);
    display: block;
    margin-bottom: 3px;
}

.twofa-info p {
    font-size: 13px;
    color: var(--gray-500);
    margin: 0;
}

/* ========================================
   ACTIVITY TIMELINE
   ======================================== */

.activity-timeline {
    position: relative;
    padding-left: 30px;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 12px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--gray-200);
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-icon {
    position: absolute;
    left: -30px;
    width: 30px;
    height: 30px;
    background: var(--white);
    border: 2px solid var(--gray-200);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-500);
    z-index: 1;
    transition: all 0.2s ease;
}

.timeline-item.current .timeline-icon {
    border-color: var(--primary);
    color: var(--primary);
    background: var(--primary-light);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.timeline-content {
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    padding: 15px;
    transition: all 0.2s ease;
}

.timeline-item:hover .timeline-content {
    background: var(--gray-100);
    transform: translateX(5px);
}

.timeline-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.timeline-date {
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.timeline-time {
    color: var(--gray-500);
    font-size: 12px;
}

.current-badge {
    background: var(--primary);
    color: white;
    padding: 3px 10px;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.timeline-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    color: var(--gray-600);
    word-break: break-all;
}

.detail-item i {
    width: 16px;
    color: var(--primary);
    font-size: 14px;
}

.detail-item code {
    background: var(--white);
    padding: 3px 8px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--gray-200);
    font-size: 12px;
    color: var(--gray-700);
}

/* ========================================
   SESSIONS GRID
   ======================================== */

.sessions-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
}

.session-card {
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    padding: 18px;
    border: 2px solid transparent;
    transition: all 0.2s ease;
}

.session-card.current {
    border-color: var(--primary);
    background: var(--primary-light);
}

.session-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--gray-200);
}

.session-device {
    display: flex;
    align-items: center;
    gap: 10px;
}

.session-device i {
    color: var(--primary);
    font-size: 20px;
}

.session-device strong {
    font-size: 15px;
    color: var(--dark);
}

.session-badge {
    background: var(--primary);
    color: white;
    padding: 4px 10px;
    border-radius: var(--radius-full);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

.session-details {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.session-row {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13px;
    color: var(--gray-600);
}

.session-row i {
    width: 18px;
    color: var(--primary);
    font-size: 14px;
}

/* ========================================
   FORM ACTIONS
   ======================================== */

.form-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
}

/* ========================================
   BUTTONS
   ======================================== */

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
    white-space: nowrap;
    -webkit-tap-highlight-color: transparent;
}

.btn-large {
    width: 100%;
    padding: 14px 28px;
    font-size: 16px;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

.btn-primary {
    background: var(--primary);
    color: var(--white);
}

.btn-primary:hover {
    background: var(--primary-dark);
}

.btn-primary:active {
    transform: scale(0.98);
}

.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: var(--white);
}

.btn-outline:active {
    transform: scale(0.98);
}

/* ========================================
   EMPTY STATE
   ======================================== */

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-400);
}

.empty-state i {
    font-size: 56px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state p {
    font-size: 15px;
    color: var(--gray-500);
}

/* ========================================
   TABLET STYLES (768px and up)
   ======================================== */

@media (min-width: 768px) {
    .profile-page {
        padding: 20px;
    }
    
    .page-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        padding: 24px;
    }
    
    .header-content h2 {
        font-size: 28px;
    }
    
    .header-content p {
        font-size: 15px;
    }
    
    .mobile-profile-tabs {
        display: none;
    }
    
    .profile-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
    }
    
    .profile-card {
        padding: 28px;
    }
    
    #security-card,
    #activity-card {
        display: block !important;
    }
    
    .profile-avatar-section {
        flex-direction: row;
        text-align: left;
        gap: 30px;
        align-items: center;
    }
    
    .profile-avatar-wrapper {
        width: 140px;
        height: 140px;
    }
    
    .avatar-info {
        text-align: left;
    }
    
    .avatar-info h4 {
        font-size: 20px;
    }
    
    .form-row {
        flex-direction: row;
        gap: 20px;
    }
    
    .form-row .form-group {
        flex: 1;
    }
    
    .info-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    .twofa-option {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .sessions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-actions {
        flex-direction: row;
        justify-content: flex-end;
    }
    
    .btn-large {
        width: auto;
        min-width: 200px;
    }
    
    .activity-timeline {
        padding-left: 40px;
    }
    
    .timeline-icon {
        left: -40px;
        width: 35px;
        height: 35px;
    }
}

/* ========================================
   DESKTOP STYLES (1024px and up)
   ======================================== */

@media (min-width: 1024px) {
    .profile-page {
        padding: 30px;
    }
    
    .page-header {
        padding: 30px;
    }
    
    .profile-grid {
        grid-template-columns: 1.5fr 1fr;
    }
    
    .profile-card.full-width {
        grid-column: 1 / -1;
    }
    
    .info-grid {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .sessions-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .profile-avatar-wrapper {
        width: 160px;
        height: 160px;
    }
    
    .avatar-placeholder {
        font-size: 4rem;
    }
    
    .timeline-content {
        padding: 18px;
    }
}

/* ========================================
   LARGE DESKTOP STYLES (1400px and up)
   ======================================== */

@media (min-width: 1400px) {
    .profile-page {
        padding: 40px;
    }
    
    .profile-grid {
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }
    
    .sessions-grid {
        grid-template-columns: repeat(4, 1fr);
    }
    
    .profile-card {
        padding: 35px;
    }
    
    .profile-avatar-wrapper {
        width: 180px;
        height: 180px;
    }
}

/* ========================================
   DARK MODE SUPPORT
   ======================================== */

@media (prefers-color-scheme: dark) {
    :root {
        --dark: #f1f5f9;
        --gray-800: #e2e8f0;
        --gray-700: #cbd5e1;
        --gray-600: #94a3b8;
        --gray-500: #64748b;
        --gray-400: #475569;
        --gray-300: #334155;
        --gray-200: #1e293b;
        --gray-100: #0f172a;
        --gray-50: #020617;
        --white: #1e293b;
    }
    
    .profile-card {
        background: #1e293b;
    }
    
    .form-section {
        background: #0f172a;
    }
    
    .input-wrapper.readonly input {
        background: #0f172a;
        color: #94a3b8;
    }
    
    .timeline-content {
        background: #0f172a;
    }
    
    .session-card {
        background: #0f172a;
    }
    
    .session-card.current {
        background: rgba(102, 126, 234, 0.15);
    }
    
    .twofa-option {
        background: #0f172a;
    }
    
    .info-item {
        background: #0f172a;
        border-color: #334155;
    }
    
    .password-requirements {
        background: #0f172a;
    }
    
    .detail-item code {
        background: #1e293b;
        border-color: #334155;
    }
}

/* ========================================
   PRINT STYLES
   ======================================== */

@media print {
    .page-header,
    .mobile-profile-tabs,
    .edit-toggle,
    .refresh-btn,
    .btn,
    .form-actions,
    .avatar-upload-overlay {
        display: none !important;
    }
    
    .profile-card {
        box-shadow: none;
        border: 1px solid #ddd;
        break-inside: avoid;
    }
    
    .activity-timeline::before {
        background: #ddd;
    }
}
</style>

<script>
let isEditMode = false;

// Profile image upload trigger
document.querySelector('.profile-avatar-wrapper')?.addEventListener('click', function() {
    document.getElementById('profile_image').click();
});

// Profile image preview
document.getElementById('profile_image')?.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const avatar = document.getElementById('profileAvatar');
            const placeholder = document.getElementById('avatarPlaceholder');
            
            if (placeholder) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'avatar-image';
                img.id = 'avatarImage';
                avatar.innerHTML = '';
                avatar.appendChild(img);
            } else {
                document.getElementById('avatarImage').src = e.target.result;
            }
        }
        reader.readAsDataURL(this.files[0]);
    }
});

// Toggle edit mode
function toggleEditMode() {
    isEditMode = !isEditMode;
    const fields = document.querySelectorAll('.editable-field');
    const actions = document.getElementById('formActions');
    const editBtn = document.querySelector('.edit-toggle i');
    
    fields.forEach(field => {
        if (isEditMode) {
            field.removeAttribute('disabled');
        } else {
            field.setAttribute('disabled', 'disabled');
        }
    });
    
    if (actions) {
        actions.style.display = isEditMode ? 'flex' : 'none';
    }
    if (editBtn) {
        editBtn.className = isEditMode ? 'fas fa-times' : 'fas fa-pen';
    }
}

// Cancel edit
function cancelEdit() {
    isEditMode = false;
    const fields = document.querySelectorAll('.editable-field');
    const actions = document.getElementById('formActions');
    const editBtn = document.querySelector('.edit-toggle i');
    
    fields.forEach(field => {
        field.setAttribute('disabled', 'disabled');
        // Reset to original values
        if (field.id === 'full_name') field.value = '<?php echo addslashes($user['full_name'] ?? ''); ?>';
        if (field.id === 'email') field.value = '<?php echo addslashes($user['email']); ?>';
        if (field.id === 'bio') field.value = '<?php echo addslashes($user['bio'] ?? ''); ?>';
    });
    
    if (actions) {
        actions.style.display = 'none';
    }
    if (editBtn) {
        editBtn.className = 'fas fa-pen';
    }
}

// Switch profile tabs on mobile
function switchProfileTab(tab) {
    const tabs = document.querySelectorAll('.tab-btn');
    const cards = {
        'info': document.getElementById('info-card'),
        'security': document.getElementById('security-card'),
        'activity': document.getElementById('activity-card')
    };
    
    tabs.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Hide all cards
    Object.values(cards).forEach(card => {
        if (card) card.style.display = 'none';
    });
    
    // Show selected card
    if (cards[tab]) {
        cards[tab].style.display = 'block';
    }
}

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = event.currentTarget;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Password strength checker
const newPassword = document.getElementById('new_password');
if (newPassword) {
    newPassword.addEventListener('input', checkPasswordStrength);
}

const confirmPassword = document.getElementById('confirm_password');
if (confirmPassword) {
    confirmPassword.addEventListener('input', checkPasswordMatch);
}

function checkPasswordStrength() {
    const password = newPassword.value;
    const bars = document.querySelectorAll('.strength-bar');
    const strengthText = document.getElementById('strengthText');
    
    // Check requirements
    const hasLower = /[a-z]/.test(password);
    const hasUpper = /[A-Z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasLength = password.length >= 8;
    
    // Update requirement indicators
    const reqLength = document.getElementById('req-length');
    const reqLower = document.getElementById('req-lower');
    const reqUpper = document.getElementById('req-upper');
    const reqNumber = document.getElementById('req-number');
    
    if (reqLength) reqLength.classList.toggle('valid', hasLength);
    if (reqLower) reqLower.classList.toggle('valid', hasLower);
    if (reqUpper) reqUpper.classList.toggle('valid', hasUpper);
    if (reqNumber) reqNumber.classList.toggle('valid', hasNumber);
    
    // Calculate strength
    let strength = 0;
    if (hasLength) strength++;
    if (hasLower) strength++;
    if (hasUpper) strength++;
    if (hasNumber) strength++;
    
    // Update bars
    bars.forEach((bar, index) => {
        if (index < strength) {
            bar.classList.add('active');
        } else {
            bar.classList.remove('active');
        }
    });
    
    // Update text
    const strengthLabels = ['Very Weak', 'Weak', 'Medium', 'Strong', 'Very Strong'];
    if (strengthText) {
        strengthText.textContent = strengthLabels[strength];
        strengthText.style.color = strength < 2 ? '#ef4444' : strength < 3 ? '#f59e0b' : '#10b981';
    }
}

function checkPasswordMatch() {
    const password = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (!matchDiv) return;
    
    if (confirm.length === 0) {
        matchDiv.innerHTML = '';
    } else if (password === confirm) {
        matchDiv.innerHTML = '<span class="match-success"><i class="fas fa-check-circle"></i> Passwords match</span>';
    } else {
        matchDiv.innerHTML = '<span class="match-error"><i class="fas fa-exclamation-circle"></i> Passwords do not match</span>';
    }
}

// Refresh activity
function refreshActivity() {
    location.reload();
}

// Auto-hide flash message
document.addEventListener('DOMContentLoaded', function() {
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.opacity = '0';
            setTimeout(() => {
                flashMessage.style.display = 'none';
            }, 300);
        }, 5000);
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>