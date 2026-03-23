<?php
// unsubscribe.php
// Handle newsletter unsubscribes

require_once 'includes/init.php';

$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email address');
}

// Update subscriber status
db()->update('newsletter_subscribers', [
    'status' => 'unsubscribed',
    'unsubscribed_at' => date('Y-m-d H:i:s')
], 'email = :email', ['email' => $email]);

// Show confirmation page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Unsubscribed - <?php echo SITE_NAME; ?></title>
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
            color: #2563eb;
            margin-bottom: 20px;
        }
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
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📧</div>
        <h1>Successfully Unsubscribed</h1>
        <p>You have been unsubscribed from our newsletter. You will no longer receive emails from us.</p>
        <a href="<?php echo BASE_URL; ?>" class="btn">Return to Homepage</a>
    </div>
</body>
</html>