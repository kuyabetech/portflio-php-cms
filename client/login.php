<?php
/**
 * Client Login Page - Enhanced Professional Version
 * With security features, remember me, and modern design
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

require_once dirname(__DIR__) . '/includes/init.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

/* ==========================================================================
   CHECK IF LOGGED IN
   ========================================================================== */

if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit;
}

/* ==========================================================================
   CSRF PROTECTION
   ========================================================================== */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ==========================================================================
   RATE LIMITING
   ========================================================================== */

$maxAttempts = 5;
$lockoutTime = 15 * 60; // 15 minutes

// Initialize rate limiting
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_lockout_until'] = 0;
}

/* ==========================================================================
   REMEMBER ME FUNCTIONALITY
   ========================================================================== */

if (empty($_SESSION['client_id']) && !empty($_COOKIE['remember_client'])) {
    try {
        $token = explode(':', $_COOKIE['remember_client']);
        
        if (count($token) === 2) {
            $selector = $token[0];
            $validator = hex2bin($token[1]);
            
            // Get token from database
            $rememberToken = db()->fetch(
                "SELECT * FROM client_remember_tokens 
                 WHERE selector = ? AND expires_at > NOW() 
                 LIMIT 1",
                [$selector]
            );
            
            if ($rememberToken && password_verify($validator, $rememberToken['hashed_validator'])) {
                // Get user
                $user = db()->fetch(
                    "SELECT * FROM client_users WHERE id = ? AND is_active = 1",
                    [$rememberToken['client_id']]
                );
                
                if ($user) {
                    // Log the user in
                    session_regenerate_id(true);
                    
                    $_SESSION['client_id'] = $user['id'];
                    $_SESSION['client_email'] = $user['email'];
                    $_SESSION['client_name'] = trim(
                        ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')
                    );
                    
                    header('Location: dashboard.php');
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        error_log('Remember me error: ' . $e->getMessage());
    }
    
    // Clear invalid cookie
    setcookie('remember_client', '', time() - 3600, '/');
}

/* ==========================================================================
   LOGIN HANDLING
   ========================================================================== */

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check rate limit
    if ($_SESSION['login_attempts'] >= $maxAttempts && time() < $_SESSION['login_lockout_until']) {
        $waitMinutes = ceil(($_SESSION['login_lockout_until'] - time()) / 60);
        $error = "Too many failed attempts. Please wait {$waitMinutes} minutes.";
    }
    
    // Verify CSRF token
    elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid security token. Please refresh the page.';
        error_log('CSRF token mismatch');
    }
    
    else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        // Validate input
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Check if client_users table exists
                $tableCheck = db()->fetch("SHOW TABLES LIKE 'client_users'");
                
                if (!$tableCheck) {
                    // Create the table if it doesn't exist
                    db()->getConnection()->exec("
                        CREATE TABLE IF NOT EXISTS `client_users` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `email` VARCHAR(100) NOT NULL UNIQUE,
                            `password_hash` VARCHAR(255) NOT NULL,
                            `first_name` VARCHAR(50),
                            `last_name` VARCHAR(50),
                            `is_active` BOOLEAN DEFAULT TRUE,
                            `last_login` DATETIME,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    
                    // Create remember tokens table
                    db()->getConnection()->exec("
                        CREATE TABLE IF NOT EXISTS `client_remember_tokens` (
                            `id` INT AUTO_INCREMENT PRIMARY KEY,
                            `client_id` INT NOT NULL,
                            `selector` VARCHAR(20) NOT NULL UNIQUE,
                            `hashed_validator` VARCHAR(255) NOT NULL,
                            `expires_at` DATETIME NOT NULL,
                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (client_id) REFERENCES client_users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ");
                    
                    // Insert demo user if no users exist
                    $userCount = db()->fetch("SELECT COUNT(*) as count FROM client_users")['count'];
                    if ($userCount == 0) {
                        $demoHash = password_hash('demo123', PASSWORD_DEFAULT);
                        db()->insert('client_users', [
                            'email' => 'demo@example.com',
                            'password_hash' => $demoHash,
                            'first_name' => 'Demo',
                            'last_name' => 'User'
                        ]);
                        $success = 'Demo account created! Use demo@example.com / demo123';
                    }
                }
                
                // Get user from database
                $user = db()->fetch(
                    "SELECT * FROM client_users WHERE email = ? AND is_active = 1 LIMIT 1",
                    [$email]
                );
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    
                    // Check if password needs rehash
                    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        db()->update('client_users', 
                            ['password_hash' => $newHash], 
                            'id = ?', 
                            [$user['id']]
                        );
                    }
                    
                    // Successful login - reset rate limits
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['login_lockout_until'] = 0;
                    
                    // Regenerate session ID
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['client_id'] = $user['id'];
                    $_SESSION['client_email'] = $user['email'];
                    $_SESSION['client_name'] = trim(
                        ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')
                    );
                    $_SESSION['login_time'] = time();
                    
                    // Handle remember me
                    if ($remember) {
                        // Delete old tokens
                        db()->delete('client_remember_tokens', 'client_id = ?', [$user['id']]);
                        
                        // Create new token
                        $selector = bin2hex(random_bytes(9));
                        $validator = random_bytes(32);
                        $hashedValidator = password_hash($validator, PASSWORD_DEFAULT);
                        $expiresAt = date('Y-m-d H:i:s', time() + 30 * 24 * 60 * 60);
                        
                        db()->insert('client_remember_tokens', [
                            'client_id' => $user['id'],
                            'selector' => $selector,
                            'hashed_validator' => $hashedValidator,
                            'expires_at' => $expiresAt
                        ]);
                        
                        // Set cookie
                        setcookie('remember_client', $selector . ':' . bin2hex($validator), [
                            'expires' => time() + 30 * 24 * 60 * 60,
                            'path' => '/',
                            'httponly' => true,
                            'samesite' => 'Strict'
                        ]);
                    }
                    
                    // Update last login
                    db()->update('client_users', 
                        ['last_login' => date('Y-m-d H:i:s')], 
                        'id = ?', 
                        [$user['id']]
                    );
                    
                    // Generate new CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // Log successful login
                    error_log("Successful login for: {$user['email']}");
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit;
                    
                } else {
                    // Failed login - increment attempts
                    $_SESSION['login_attempts']++;
                    
                    if ($_SESSION['login_attempts'] >= $maxAttempts) {
                        $_SESSION['login_lockout_until'] = time() + $lockoutTime;
                    }
                    
                    // Log failed attempt
                    error_log("Failed login attempt for: $email from IP: " . $_SERVER['REMOTE_ADDR']);
                    
                    $error = 'Invalid email or password.';
                }
                
            } catch (Exception $e) {
                error_log('Login database error: ' . $e->getMessage());
                $error = 'A system error occurred. Please try again later.';
            }
        }
    }
}

// Check if this is a new installation
$needsSetup = false;
try {
    $tableCheck = db()->fetch("SHOW TABLES LIKE 'client_users'");
    if (!$tableCheck) {
        $needsSetup = true;
    } else {
        $userCount = db()->fetch("SELECT COUNT(*) as count FROM client_users")['count'];
        if ($userCount == 0) {
            $needsSetup = true;
        }
    }
} catch (Exception $e) {
    $needsSetup = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal Login | <?= htmlspecialchars(SITE_NAME ?? 'Secure Portal') ?></title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Reset & Base */
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
        
        /* Login Container */
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 420px;
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
        
        /* Header */
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header .logo {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .login-header .logo i {
            font-size: 30px;
            color: white;
        }
        
        .login-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #bbdefb;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Form Groups */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
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
            font-size: 16px;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e1e1e1;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .input-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            z-index: 2;
        }
        
        .toggle-password:hover {
            color: #667eea;
        }
        
        /* Options Row */
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 14px;
        }
        
        .remember-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            cursor: pointer;
        }
        
        .remember-checkbox input {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-link:hover {
            text-decoration: underline;
        }
        
        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Footer Links */
        .footer-links {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #999;
        }
        
        .footer-links a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        /* Demo Box */
        .demo-box {
            margin-top: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 1px dashed #667eea;
        }
        
        .demo-box p {
            color: #666;
            font-size: 13px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .demo-box code {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            color: #495057;
            font-size: 12px;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .options-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Welcome Back</h1>
            <p>Sign in to access your client portal</p>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($needsSetup): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                First time setup: Use demo@example.com / demo123
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           placeholder="your@email.com"
                           required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" 
                           placeholder="Enter your password"
                           required>
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>
            </div>

            <div class="options-row">
                <label class="remember-checkbox">
                    <input type="checkbox" name="remember">
                    <span>Remember me</span>
                </label>
                <a href="forgot-password.php" class="forgot-link">
                    Forgot password?
                </a>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i>
                <span>Sign In</span>
            </button>
        </form>

        <!-- Footer -->
        <div class="footer-links">
            <a href="<?= BASE_URL ?? '/' ?>">
                <i class="fas fa-arrow-left"></i> Back to Website
            </a>
        </div>

        <!-- Demo Credentials -->
        <div class="demo-box">
            <p>
                <i class="fas fa-flask"></i>
                Demo Credentials:
            </p>
            <code>demo@example.com</code> / <code>demo123</code>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }

        // Form submission with loading state
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        
        if (loginForm) {
            loginForm.addEventListener('submit', function() {
                const icon = loginBtn.querySelector('i');
                const span = loginBtn.querySelector('span');
                
                icon.className = 'fas fa-spinner spinner';
                span.textContent = 'Signing in...';
                loginBtn.disabled = true;
            });
        }

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>