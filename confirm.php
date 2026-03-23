<?php
/**
 * confirm.php
 * Handle email confirmation for double opt-in
 */

require_once 'includes/init.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    die('Invalid confirmation token');
}

// Find subscriber with this token
$subscriber = db()->fetch(
    "SELECT id, email, status FROM newsletter_subscribers WHERE confirmation_token = ?",
    [$token]
);

if (!$subscriber) {
    $error = 'Invalid or expired confirmation link.';
} elseif ($subscriber['status'] === 'active') {
    $message = 'Your email is already confirmed. Thank you!';
} else {
    // Confirm subscription
    db()->update('newsletter_subscribers', [
        'status' => 'active',
        'confirmed_at' => date('Y-m-d H:i:s'),
        'confirmation_token' => null
    ], 'id = :id', ['id' => $subscriber['id']]);
    
    // Send welcome email
    sendWelcomeEmail($subscriber['email'], '');
    
    $message = 'Your subscription has been confirmed! Thank you for subscribing.';
}

// Show confirmation page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Subscription Confirmation - <?php echo SITE_NAME; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .icon.success { color: #10b981; }
        .icon.error { color: #ef4444; }
        h1 {
            color: #1e293b;
            margin-bottom: 15px;
        }
        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .error-message {
            color: #ef4444;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="icon error">❌</div>
            <h1>Confirmation Failed</h1>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <a href="<?php echo BASE_URL; ?>" class="btn">Return to Homepage</a>
        <?php else: ?>
            <div class="icon success">✅</div>
            <h1>Subscription Confirmed!</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="<?php echo BASE_URL; ?>" class="btn">Return to Homepage</a>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
function sendWelcomeEmail($email, $name) {
    $subject = "Welcome to " . SITE_NAME . " Newsletter";
    
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2563eb; color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { padding: 30px; background: #f9f9f9; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Welcome to " . SITE_NAME . " Newsletter!</h2>
            </div>
            <div class='content'>
                <p>Hello " . ($name ?: 'there') . ",</p>
                <p>Thank you for confirming your subscription! You'll now receive updates about our latest projects, blog posts, and news.</p>
                <p>We're excited to have you on board!</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                <p><a href='" . BASE_URL . "/unsubscribe.php?email=" . urlencode($email) . "'>Unsubscribe</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
    
    mail($email, $subject, $htmlBody, $headers);
}
?>