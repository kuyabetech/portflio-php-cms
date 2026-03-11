<?php
/**
 * Client Profile - Manage account settings
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

if (!$client) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $company = trim($_POST['company'] ?? '');
        
        if (empty($firstName) || empty($lastName)) {
            $error = 'First name and last name are required';
        } else {
            try {
                db()->update('client_users', [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'company' => $company
                ], 'id = ?', [$clientId]);
                
                $success = 'Profile updated successfully';
                
                // Update session name
                $_SESSION['client_name'] = $firstName . ' ' . $lastName;
                
                // Refresh client data
                $client = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientId]);
                
            } catch (Exception $e) {
                error_log("Profile update error: " . $e->getMessage());
                $error = 'Failed to update profile';
            }
        }
    }
    
    // Handle password change
    elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match';
        } elseif (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif (!password_verify($currentPassword, $client['password_hash'])) {
            $error = 'Current password is incorrect';
        } else {
            try {
                db()->update('client_users', [
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
                ], 'id = ?', [$clientId]);
                
                $success = 'Password changed successfully';
                
            } catch (Exception $e) {
                error_log("Password change error: " . $e->getMessage());
                $error = 'Failed to change password';
            }
        }
    }
    
    // Handle avatar upload
    elseif (isset($_POST['upload_avatar'])) {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
                $error = 'Only JPG, PNG and GIF images are allowed';
            } elseif ($_FILES['avatar']['size'] > $maxSize) {
                $error = 'Image size must be less than 2MB';
            } else {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $clientId . '_' . time() . '.' . $ext;
                $uploadPath = UPLOAD_PATH . 'avatars/' . $filename;
                
                // Create directory if it doesn't exist
                if (!is_dir(UPLOAD_PATH . 'avatars/')) {
                    mkdir(UPLOAD_PATH . 'avatars/', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                    // Delete old avatar if exists
                    if (!empty($client['avatar']) && file_exists(UPLOAD_PATH . 'avatars/' . $client['avatar'])) {
                        unlink(UPLOAD_PATH . 'avatars/' . $client['avatar']);
                    }
                    
                    db()->update('client_users', ['avatar' => $filename], 'id = ?', [$clientId]);
                    $client['avatar'] = $filename;
                    $success = 'Avatar uploaded successfully';
                } else {
                    $error = 'Failed to upload avatar';
                }
            }
        } else {
            $error = 'Please select an image to upload';
        }
    }
}

$pageTitle = 'My Profile';
require_once '../includes/client-header.php';
?>

<div class="profile-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
            <p>Manage your account settings and preferences</p>
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

    <!-- Profile Grid -->
    <div class="profile-grid">
        <!-- Left Column - Avatar & Info -->
        <div class="left-column">
            <div class="profile-card">
                <h3><i class="fas fa-image"></i> Profile Picture</h3>
                
                <div class="avatar-section">
                    <div class="avatar-preview">
                        <?php if (!empty($client['avatar'])): ?>
                        <img src="<?php echo UPLOAD_URL . 'avatars/' . $client['avatar']; ?>" 
                             alt="Profile Avatar" class="avatar-img">
                        <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($client['first_name'] ?? 'C', 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="avatar-form">
                        <div class="file-input-wrapper">
                            <input type="file" name="avatar" id="avatar" accept="image/*">
                            <label for="avatar" class="file-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Choose Image
                            </label>
                        </div>
                        <button type="submit" name="upload_avatar" class="btn-upload">
                            Upload Avatar
                        </button>
                        <p class="help-text">Max size: 2MB. Formats: JPG, PNG, GIF</p>
                    </form>
                </div>
            </div>

            <div class="profile-card">
                <h3><i class="fas fa-info-circle"></i> Account Info</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($client['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Member Since:</span>
                        <span class="info-value"><?php echo date('F d, Y', strtotime($client['created_at'] ?? 'now')); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Login:</span>
                        <span class="info-value"><?php echo $client['last_login'] ? date('F d, Y h:i A', strtotime($client['last_login'])) : 'Never'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column - Forms -->
        <div class="right-column">
            <!-- Edit Profile Form -->
            <div class="profile-card">
                <h3><i class="fas fa-user-edit"></i> Edit Profile</h3>
                
                <form method="POST" class="profile-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($client['first_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($client['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>"
                               placeholder="+1 234 567 890">
                    </div>
                    
                    <div class="form-group">
                        <label for="company">Company</label>
                        <input type="text" id="company" name="company" 
                               value="<?php echo htmlspecialchars($client['company'] ?? ''); ?>"
                               placeholder="Your company name">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="profile-card">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                
                <form method="POST" class="password-form">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <p class="help-text">Minimum 8 characters</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Notification Preferences -->
            <div class="profile-card">
                <h3><i class="fas fa-bell"></i> Notification Preferences</h3>
                
                <form method="POST" class="preferences-form">
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="email_notifications" <?php echo ($client['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Email notifications for new messages</span>
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="invoice_notifications" <?php echo ($client['invoice_notifications'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Email notifications for new invoices</span>
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="project_updates" <?php echo ($client['project_updates'] ?? 1) ? 'checked' : ''; ?>>
                            <span>Email notifications for project updates</span>
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="save_preferences" class="btn-primary">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.profile-page {
    max-width: 1200px;
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

/* Profile Grid */
.profile-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
}

/* Profile Cards */
.profile-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.profile-card h3 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.profile-card h3 i {
    color: #667eea;
}

/* Avatar Section */
.avatar-section {
    text-align: center;
}

.avatar-preview {
    width: 150px;
    height: 150px;
    margin: 0 auto 20px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #667eea;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

.avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
    font-weight: 600;
}

.avatar-form {
    text-align: center;
}

.file-input-wrapper {
    margin-bottom: 15px;
}

.file-input-wrapper input {
    display: none;
}

.file-label {
    display: inline-block;
    padding: 10px 20px;
    background: #f1f5f9;
    color: #1e293b;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.file-label:hover {
    background: #e2e8f0;
}

.file-label i {
    margin-right: 5px;
}

.btn-upload {
    background: #667eea;
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease;
    margin-bottom: 10px;
}

.btn-upload:hover {
    background: #5a67d8;
    transform: translateY(-2px);
}

.help-text {
    font-size: 12px;
    color: #94a3b8;
}

/* Info List */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-item {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px solid #e2e8f0;
}

.info-label {
    width: 120px;
    font-weight: 600;
    color: #475569;
}

.info-value {
    flex: 1;
    color: #1e293b;
}

/* Forms */
.profile-form,
.password-form,
.preferences-form {
    max-width: 100%;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
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

.form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Checkbox Group */
.checkbox-group {
    margin-bottom: 12px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    color: #475569;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* Form Actions */
.form-actions {
    margin-top: 20px;
}

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
}

.btn-primary:hover {
    background: #5a67d8;
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 1024px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .info-item {
        flex-direction: column;
        gap: 5px;
    }
    
    .info-label {
        width: auto;
    }
    
    .btn-primary {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require_once '../includes/client-footer.php'; ?>