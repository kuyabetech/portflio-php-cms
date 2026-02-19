<?php
// admin/profile.php
// Admin Profile Management

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
        
        db()->update('users', $data, 'id = :id', ['id' => $user['id']]);
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
            db()->update('users', ['password_hash' => $hash], 'id = :id', ['id' => $user['id']]);
            $success = 'Password changed successfully!';
        }
    }
}

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>My Profile</h2>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="profile-grid">
    <!-- Profile Information -->
    <div class="profile-card">
        <h3><i class="fas fa-user"></i> Profile Information</h3>
        
        <form method="POST" enctype="multipart/form-data" class="profile-form">
            <div class="profile-avatar-section">
                <div class="profile-avatar">
                    <?php if ($user['profile_image']): ?>
                    <img src="<?php echo UPLOAD_URL . 'profiles/' . $user['profile_image']; ?>" 
                         alt="Profile" class="avatar-image">
                    <?php else: ?>
                    <div class="avatar-placeholder">
                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="avatar-upload">
                    <label for="profile_image" class="btn btn-outline btn-sm">
                        <i class="fas fa-camera"></i>
                        Change Photo
                    </label>
                    <input type="file" id="profile_image" name="profile_image" 
                           accept="image/*" style="display: none;">
                    <p class="upload-hint">JPG, PNG or GIF. Max 2MB.</p>
                </div>
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                <small>Username cannot be changed</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="bio">Bio / About</label>
                <textarea id="bio" name="bio" rows="4" placeholder="Tell something about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="role">Role</label>
                <input type="text" id="role" value="<?php echo ucfirst($user['role']); ?>" disabled>
            </div>
            
            <button type="submit" name="update_profile" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Update Profile
            </button>
        </form>
    </div>
    
    <!-- Change Password -->
    <div class="profile-card">
        <h3><i class="fas fa-key"></i> Change Password</h3>
        
        <form method="POST" class="password-form">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <div class="password-input">
                    <input type="password" id="current_password" name="current_password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password</label>
                <div class="password-input">
                    <input type="password" id="new_password" name="new_password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <small>Minimum 8 characters. Use a mix of letters, numbers and symbols.</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <div class="password-input">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="password-strength" id="passwordStrength">
                <div class="strength-bar"></div>
                <span class="strength-text"></span>
            </div>
            
            <button type="submit" name="change_password" class="btn btn-primary">
                <i class="fas fa-key"></i>
                Change Password
            </button>
        </form>
    </div>
    
    <!-- Login History -->
    <div class="profile-card full-width">
        <h3><i class="fas fa-history"></i> Recent Login Activity</h3>
        
        <div class="login-history">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>IP Address</th>
                        <th>Location</th>
                        <th>Device</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $logs = db()->fetchAll(
                        "SELECT * FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
                        [$user['id']]
                    );
                    
                    foreach ($logs as $log):
                    ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                        <td><code><?php echo $log['ip_address']; ?></code></td>
                        <td>
                            <?php
                            // You can integrate with IP geolocation service here
                            echo 'Unknown';
                            ?>
                        </td>
                        <td>
                            <?php
                            $ua = $log['user_agent'];
                            if (strpos($ua, 'Windows') !== false) echo 'Windows';
                            elseif (strpos($ua, 'Mac') !== false) echo 'macOS';
                            elseif (strpos($ua, 'Linux') !== false) echo 'Linux';
                            elseif (strpos($ua, 'Android') !== false) echo 'Android';
                                            elseif (strpos($ua, 'iOS') !== false) echo 'iOS';
                            else echo 'Unknown';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4" class="text-center">No login history available</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Session Management -->
<div class="profile-card full-width">
    <h3><i class="fas fa-globe"></i> Active Sessions</h3>
    
    <div class="sessions-list">
        <div class="session-item current">
            <div class="session-info">
                <div class="session-device">
                    <i class="fas fa-laptop"></i>
                    <strong>Current Session</strong>
                </div>
                <div class="session-details">
                    <span><?php echo $_SERVER['HTTP_USER_AGENT']; ?></span>
                    <span>IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></span>
                    <span>Started: <?php echo date('M d, Y H:i:s', $_SESSION['login_time'] ?? time()); ?></span>
                </div>
            </div>
            <span class="session-badge">Current</span>
        </div>
    </div>
</div>

<style>
.profile-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.profile-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.profile-card.full-width {
    grid-column: 1 / -1;
}

.profile-card h3 {
    font-size: 1.1rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-card h3 i {
    color: var(--primary);
}

.profile-avatar-section {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 30px;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid var(--primary-light);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 600;
}

.avatar-upload {
    flex: 1;
}

.upload-hint {
    margin-top: 10px;
    font-size: 0.8rem;
    color: var(--gray-500);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--gray-700);
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-group input:disabled {
    background: var(--gray-100);
    color: var(--gray-500);
    cursor: not-allowed;
}

.form-group small {
    display: block;
    margin-top: 5px;
    font-size: 0.8rem;
    color: var(--gray-500);
}

.password-input {
    position: relative;
}

.password-input input {
    padding-right: 40px;
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray-500);
    cursor: pointer;
    padding: 5px;
}

.toggle-password:hover {
    color: var(--primary);
}

.password-strength {
    margin: 15px 0;
    padding: 10px;
    background: var(--gray-100);
    border-radius: 8px;
}

.strength-bar {
    height: 4px;
    background: linear-gradient(90deg, #ef4444, #f59e0b, #10b981);
    width: 0%;
    border-radius: 2px;
    margin-bottom: 5px;
    transition: width 0.3s ease;
}

.strength-text {
    font-size: 0.85rem;
    color: var(--gray-600);
}

.login-history {
    overflow-x: auto;
}

.sessions-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.session-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: var(--gray-100);
    border-radius: 8px;
}

.session-item.current {
    background: rgba(37, 99, 235, 0.05);
    border: 2px solid var(--primary);
}

.session-info {
    flex: 1;
}

.session-device {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 5px;
}

.session-device i {
    font-size: 1.2rem;
    color: var(--primary);
}

.session-details {
    display: flex;
    flex-direction: column;
    gap: 3px;
    font-size: 0.85rem;
    color: var(--gray-600);
}

.session-badge {
    padding: 4px 8px;
    background: var(--primary);
    color: white;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
    
    .profile-avatar-section {
        flex-direction: column;
        text-align: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Profile image preview
document.getElementById('profile_image').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const avatar = document.querySelector('.avatar-image, .avatar-placeholder');
            if (avatar.classList.contains('avatar-placeholder')) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'avatar-image';
                avatar.parentNode.replaceChild(img, avatar);
            } else {
                avatar.src = e.target.result;
            }
        }
        reader.readAsDataURL(this.files[0]);
    }
});

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
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

// Password strength meter
const newPassword = document.getElementById('new_password');
if (newPassword) {
    newPassword.addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.querySelector('.strength-bar');
        const strengthText = document.querySelector('.strength-text');
        
        let strength = 0;
        if (password.length >= 8) strength += 25;
        if (password.match(/[a-z]+/)) strength += 25;
        if (password.match(/[A-Z]+/)) strength += 25;
        if (password.match(/[0-9]+/)) strength += 25;
        
        strengthBar.style.width = strength + '%';
        
        if (strength < 50) {
            strengthText.textContent = 'Weak password';
            strengthText.style.color = '#ef4444';
        } else if (strength < 75) {
            strengthText.textContent = 'Medium password';
            strengthText.style.color = '#f59e0b';
        } else {
            strengthText.textContent = 'Strong password';
            strengthText.style.color = '#10b981';
        }
    });
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>