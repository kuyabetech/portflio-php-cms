<?php
/**
 * Client Reset Password - Set new password with token
 */

require_once dirname(__DIR__) . '/includes/init.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$showForm = false;
$token = $_GET['token'] ?? '';

// Validate token
if (!empty($token)) {
    $reset = db()->fetch("
        SELECT pr.*, cu.email, cu.first_name 
        FROM password_resets pr
        JOIN client_users cu ON pr.client_id = cu.id
        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL
    ", [$token]);
    
    if ($reset) {
        $showForm = true;
    } else {
        $error = 'Invalid or expired reset token. Please request a new one.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirmPassword)) {
        $error = 'Please enter both password fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } else {
        // Verify token again
        $reset = db()->fetch("
            SELECT * FROM password_resets 
            WHERE token = ? AND expires_at > NOW() AND used_at IS NULL
        ", [$token]);
        
        if ($reset) {
            try {
                // Update password
                db()->update('client_users', [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT)
                ], 'id = ?', [$reset['client_id']]);
                
                // Mark token as used
                db()->update('password_resets', [
                    'used_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$reset['id']]);
                
                // Log activity
                logClientActivity($reset['client_id'], 'password_reset', 'Password reset successfully');
                
                $success = 'Password reset successfully! You can now login with your new password.';
                
                // Redirect to login after 3 seconds
                header('refresh:3;url=login.php');
                
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = 'Failed to reset password. Please try again.';
            }
        } else {
            $error = 'Invalid or expired token. Please request a new reset link.';
        }
    }
}

$pageTitle = 'Reset Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header i {
            font-size: 50px;
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .input-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .password-hint {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .back-link {
            text-align: center;
            margin-top: 25px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="header">
            <i class="fas fa-key"></i>
            <?php if ($showForm): ?>
            <h1>Create New Password</h1>
            <p>Enter your new password below</p>
            <?php elseif ($success): ?>
            <h1>Password Reset!</h1>
            <p>Redirecting to login...</p>
            <?php else: ?>
            <h1>Invalid Link</h1>
            <p>Please request a new reset link</p>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($showForm): ?>
        <form method="POST" id="resetForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required 
                           placeholder="••••••••" minlength="8">
                </div>
                <div class="password-hint">Minimum 8 characters</div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="••••••••" minlength="8">
                </div>
            </div>
            
            <button type="submit" name="reset_password" class="btn-primary" id="submitBtn">
                <span>Reset Password</span>
            </button>
        </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
        
        <div class="footer">
            &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
        </div>
    </div>
    
    <?php if ($showForm): ?>
    <script>
        const form = document.getElementById('resetForm');
        const submitBtn = document.getElementById('submitBtn');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        form.addEventListener('submit', function(e) {
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
        });
    </script>
    <?php endif; ?>
</body>
</html>