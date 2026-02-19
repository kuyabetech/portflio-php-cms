<?php
// unsubscribe.php
// Handle newsletter unsubscriptions

require_once 'includes/init.php';

$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';

if ($email) {
    $subscriber = db()->fetch("SELECT * FROM newsletter_subscribers WHERE email = ?", [$email]);
    
    if ($subscriber) {
        db()->update('newsletter_subscribers', [
            'status' => 'unsubscribed',
            'unsubscribed_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $subscriber['id']]);
        
        $message = "You have been successfully unsubscribed from our newsletter.";
    } else {
        $message = "Email not found in our subscriber list.";
    }
} else {
    $message = "No email provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📧</div>
        <h1>Unsubscribe</h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="<?php echo BASE_URL; ?>" class="btn">Return to Homepage</a>
    </div>
</body>
</html>