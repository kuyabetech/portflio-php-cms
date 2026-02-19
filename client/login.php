<?php
// client/login.php
require_once dirname(__DIR__) . '/includes/init.php';

// === Security Headers (add early) ===
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline'");

// Force HTTPS
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Secure session settings
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', '1');
session_start();

// Regenerate CSRF token if missing or on login success
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting (session-based; consider IP+DB in production)
$max_attempts    = 5;
$lockout_minutes = 15;

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts']     = 0;
    $_SESSION['login_lockout_until'] = 0;
}

$error = '';

// === Remember-Me Auto-Login Check ===
if (!isset($_SESSION['client_login']) && !empty($_COOKIE['remember'])) {
    [$selector, $validator_hex] = explode(':', $_COOKIE['remember'], 2) + [null, null];

    if ($selector && $validator_hex && strlen($validator_hex) === 64) {
        $validator = hex2bin($validator_hex);

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

                $_SESSION['client_id']    = $user['id'];
                $_SESSION['client_login'] = true;
                $_SESSION['client_name']  = trim(implode(' ', array_filter([
                    $user['first_name'] ?? '',
                    $user['last_name']  ?? ''
                ])));

                // Optional: rotate token here (delete old + create new)
                // db()->delete('client_remember_tokens', 'selector = ?', [$selector]);

                header('Location: dashboard.php');
                exit;
            }
        }
    }

    // Invalidate bad/expired cookie
    setcookie('remember', '', time() - 3600, '/', '', true, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request. Please try again.';
    } elseif ($_SESSION['login_attempts'] >= $max_attempts && time() < $_SESSION['login_lockout_until']) {
        $remaining = ceil(($_SESSION['login_lockout_until'] - time()) / 60);
        $error = "Too many failed attempts. Please wait $remaining minute" . ($remaining > 1 ? 's' : '') . ".";
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $client = db()->fetch(
                "SELECT * FROM client_users 
                 WHERE email = ? AND is_active = 1 
                 LIMIT 1",
                [$email]
            );

            if ($client && password_verify($password, $client['password_hash'])) {
                session_regenerate_id(true); // Prevent session fixation

                $_SESSION['client_id']    = $client['id'];
                $_SESSION['client_login'] = true;
                $_SESSION['client_name']  = trim(implode(' ', array_filter([
                    $client['first_name'] ?? '',
                    $client['last_name']  ?? ''
                ])));

                // Reset rate limiting
                $_SESSION['login_attempts']      = 0;
                $_SESSION['login_lockout_until'] = 0;

                // === Remember Me (secure token method) ===
                if ($remember) {
                    $selector  = bin2hex(random_bytes(9));      // public, stored in DB
                    $validator = random_bytes(32);              // secret, never sent in plain

                    db()->insert('client_remember_tokens', [
                        'client_id'        => $client['id'],
                        'selector'         => $selector,
                        'hashed_validator' => password_hash($validator, PASSWORD_DEFAULT),
                        'expires_at'       => date('Y-m-d H:i:s', time() + 30 * 86400),
                    ]);

                    $cookie_value = $selector . ':' . bin2hex($validator);

                    setcookie('remember', $cookie_value, [
                        'expires'  => time() + 30 * 86400,
                        'path'     => '/',
                        'domain'   => '',
                        'secure'   => true,
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
        /* Your original styles here – unchanged for brevity */
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333;
        }
        /* ... rest of your CSS ... */
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><?= htmlspecialchars(SITE_NAME ?? 'Client Portal') ?></h1>
            <p>Sign in to your secure dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="alert"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="form-group">
                <label for="email">Email</label>
                <i class="fas fa-envelope icon"></i>
                <input type="email" id="email" name="email" required autocomplete="email" autofocus placeholder="you@company.com">
            </div>

            <div class="form-group password-wrapper">
                <label for="password">Password</label>
                <i class="fas fa-lock icon"></i>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>

            <div class="options">
                <label class="remember">
                    <input type="checkbox" name="remember"> Remember me (30 days)
                </label>
                <span class="forgot"><a href="forgot-password.php">Forgot password?</a></span>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="footer-links">
            <a href="<?= htmlspecialchars(BASE_URL ?? '/') ?>">← Back to website</a>
            <?php if (defined('ALLOW_SELF_REGISTRATION') && ALLOW_SELF_REGISTRATION): ?>
                | <a href="register.php">Create account</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>