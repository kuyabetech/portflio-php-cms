<?php
// client/login.php
// Client Portal Login Page

require_once dirname(__DIR__) . '/includes/init.php';

// === Security Headers ===
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline'");

// Force HTTPS in production
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
        header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Secure session settings
ini_set('session.cookie_httponly', '1');
if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
    ini_set('session.cookie_secure', '1');
}
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', '1');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['client_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Regenerate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting
$max_attempts = 5;
$lockout_minutes = 15;

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_lockout_until'] = 0;
}

$error = '';

// === Remember-Me Auto-Login Check ===
if (!isset($_SESSION['client_login']) && !empty($_COOKIE['remember_client'])) {
    [$selector, $validator_hex] = explode(':', $_COOKIE['remember_client'], 2) + [null, null];

    if ($selector && $validator_hex && strlen($validator_hex) === 64) {
        $validator = hex2bin($validator_hex);

        try {
            $token = db()->fetch(
                "SELECT * FROM client_remember_tokens 
                 WHERE selector = ? AND expires_at > NOW() 
                 LIMIT 1",
                [$selector]
            );

            if ($token && password_verify($validator, $token['hashed_validator'])) {
                $user = db()->fetch(
                    "SELECT * FROM client_users 
                     WHERE id = ? AND is_active = 1 
                     LIMIT 1",
                    [$token['client_id']]
                );

                if ($user) {
                    session_regenerate_id(true);

                    $_SESSION['client_id'] = $user['id'];
                    $_SESSION['client_login'] = true;
                    $_SESSION['client_name'] = trim(implode(' ', array_filter([
                        $user['first_name'] ?? '',
                        $user['last_name'] ?? ''
                    ])));

                    // Rotate token (delete old, create new)
                    db()->delete('client_remember_tokens', 'selector = ?', [$selector]);
                    
                    $new_selector = bin2hex(random_bytes(9));
                    $new_validator = random_bytes(32);
                    
                    db()->insert('client_remember_tokens', [
                        'client_id' => $user['id'],
                        'selector' => $new_selector,
                        'hashed_validator' => password_hash($new_validator, PASSWORD_DEFAULT),
                        'expires_at' => date('Y-m-d H:i:s', time() + 30 * 86400),
                    ]);
                    
                    $cookie_value = $new_selector . ':' . bin2hex($new_validator);
                    setcookie('remember_client', $cookie_value, [
                        'expires' => time() + 30 * 86400,
                        'path' => '/',
                        'domain' => '',
                        'secure' => ($_SERVER['HTTP_HOST'] !== 'localhost'),
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);

                    header('Location: dashboard.php');
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log("Remember me error: " . $e->getMessage());
        }
    }

    // Invalidate bad/expired cookie
    setcookie('remember_client', '', time() - 3600, '/', '', true, true);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request. Please try again.';
    } 
    // Check rate limiting
    elseif ($_SESSION['login_attempts'] >= $max_attempts && time() < $_SESSION['login_lockout_until']) {
        $remaining = ceil(($_SESSION['login_lockout_until'] - time()) / 60);
        $error = "Too many failed attempts. Please wait $remaining minute" . ($remaining > 1 ? 's' : '') . ".";
    } 
    else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $client = db()->fetch(
                    "SELECT * FROM client_users 
                     WHERE email = ? AND is_active = 1 
                     LIMIT 1",
                    [$email]
                );

                if ($client && password_verify($password, $client['password_hash'])) {
                    session_regenerate_id(true); // Prevent session fixation

                    $_SESSION['client_id'] = $client['id'];
                    $_SESSION['client_login'] = true;
                    $_SESSION['client_name'] = trim(implode(' ', array_filter([
                        $client['first_name'] ?? '',
                        $client['last_name'] ?? ''
                    ])));

                    // Reset rate limiting
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['login_lockout_until'] = 0;

                    // === Remember Me (secure token method) ===
                    if ($remember) {
                        $selector = bin2hex(random_bytes(9));      // public, stored in DB
                        $validator = random_bytes(32);              // secret, never sent in plain

                        db()->insert('client_remember_tokens', [
                            'client_id' => $client['id'],
                            'selector' => $selector,
                            'hashed_validator' => password_hash($validator, PASSWORD_DEFAULT),
                            'expires_at' => date('Y-m-d H:i:s', time() + 30 * 86400),
                        ]);

                        $cookie_value = $selector . ':' . bin2hex($validator);

                        setcookie('remember_client', $cookie_value, [
                            'expires' => time() + 30 * 86400,
                            'path' => '/',
                            'domain' => '',
                            'secure' => ($_SERVER['HTTP_HOST'] !== 'localhost'),
                            'httponly' => true,
                            'samesite' => 'Strict'
                        ]);
                    }

                    // Update last login
                    db()->update(
                        'client_users',
                        ['last_login' => date('Y-m-d H:i:s')],
                        'id = ?',
                        [$client['id']]
                    );

                    // Refresh CSRF token after successful login
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header('Location: dashboard.php');
                    exit;
                } else {
                    $_SESSION['login_attempts']++;
                    if ($_SESSION['login_attempts'] >= $max_attempts) {
                        $_SESSION['login_lockout_until'] = time() + ($lockout_minutes * 60);
                    }
                    $error = 'Invalid email or password.';
                    
                    // Log failed attempt (optional)
                    error_log("Failed login attempt for email: $email from IP: " . $_SERVER['REMOTE_ADDR']);
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'An error occurred. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - Sign In | <?= htmlspecialchars(SITE_NAME ?? 'Portal') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        .login-container {
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
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 5px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .alert {
            background: #fee;
            color: #c33;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #fcc;
        }
        
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
        
        .form-group .icon {
            position: absolute;
            left: 15px;
            top: 42px;
            color: #999;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 42px;
            color: #999;
            cursor: pointer;
            font-size: 14px;
        }
        
        .toggle-password:hover {
            color: #667eea;
        }
        
        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .remember {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #666;
            cursor: pointer;
        }
        
        .remember input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .forgot a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot a:hover {
            text-decoration: underline;
        }
        
        .btn-login {
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
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .footer-links {
            text-align: center;
            margin-top: 25px;
            font-size: 14px;
            color: #999;
        }
        
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 5px;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        .register-link {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .demo-credentials {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 13px;
            border: 1px dashed #ddd;
        }
        
        .demo-credentials p {
            color: #666;
            margin-bottom: 5px;
        }
        
        .demo-credentials code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            color: #495057;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?= htmlspecialchars(SITE_NAME ?? 'Client Portal') ?></h1>
            <p>Sign in to your secure dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i> 
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="email">Email Address</label>
                <i class="fas fa-envelope icon"></i>
                <input type="email" id="email" name="email" required 
                       autocomplete="email" autofocus 
                       placeholder="you@company.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group password-wrapper">
                <label for="password">Password</label>
                <i class="fas fa-lock icon"></i>
                <input type="password" id="password" name="password" required 
                       autocomplete="current-password" 
                       placeholder="••••••••">
                <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>

            <div class="options">
                <label class="remember">
                    <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>> 
                    Remember me (30 days)
                </label>
                <span class="forgot"><a href="forgot-password.php">Forgot password?</a></span>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="footer-links">
            <a href="<?= htmlspecialchars(BASE_URL ?? '/') ?>">← Back to website</a>
            <?php if (defined('ALLOW_SELF_REGISTRATION') && ALLOW_SELF_REGISTRATION): ?>
                | <a href="register.php">Create account</a>
            <?php endif; ?>
        </div>

        <!-- Demo credentials (remove in production) -->
        <?php if (defined('DEV_MODE') && DEV_MODE): ?>
        <div class="demo-credentials">
            <p><i class="fas fa-info-circle"></i> Demo Credentials:</p>
            <p>Email: <code>demo@example.com</code> | Password: <code>demo123</code></p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', () => {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                togglePassword.classList.toggle('fa-eye');
                togglePassword.classList.toggle('fa-eye-slash');
            });
        }

        // Form submission with loading state
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
                loginBtn.disabled = true;
            });
        }

        // Auto-hide alert after 5 seconds
        const alert = document.querySelector('.alert');
        if (alert) {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        }
    </script>
</body>
</html>