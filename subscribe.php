<?php
// subscribe.php
// Handle newsletter subscriptions

require_once 'includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $name = sanitize($_POST['name'] ?? '');
    $source = 'website';
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['subscribe_error'] = 'Please enter a valid email address';
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '#newsletter');
        exit;
    }
    
    // Check if already subscribed
    $existing = db()->fetch("SELECT * FROM newsletter_subscribers WHERE email = ?", [$email]);
    
    if ($existing) {
        if ($existing['status'] === 'active') {
            $_SESSION['subscribe_message'] = 'You are already subscribed!';
        } else {
            // Reactivate
            db()->update('newsletter_subscribers', [
                'status' => 'active',
                'unsubscribed_at' => NULL,
                'source' => $source
            ], 'id = :id', ['id' => $existing['id']]);
            $_SESSION['subscribe_message'] = 'Your subscription has been reactivated!';
        }
    } else {
        // Split name into first and last
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        db()->insert('newsletter_subscribers', [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'source' => $source,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        // Send welcome email (optional)
        sendWelcomeEmail($email, $firstName);
        
        $_SESSION['subscribe_success'] = 'Thank you for subscribing!';
    }
    
    header('Location: ' . $_SERVER['HTTP_REFERER'] . '#newsletter');
    exit;
}

function sendWelcomeEmail($email, $name) {
    // Get welcome template
    $template = db()->fetch("SELECT * FROM newsletter_templates WHERE is_default = 1 OR name LIKE '%Welcome%' LIMIT 1");
    
    if ($template) {
        $content = $template['content'];
        $content = str_replace('{{first_name}}', $name, $content);
        $content = str_replace('{{email}}', $email, $content);
        $content = str_replace('{{unsubscribe_url}}', BASE_URL . '/unsubscribe.php?email=' . urlencode($email), $content);
        $content = str_replace('{{site_name}}', SITE_NAME, $content);
        $content = str_replace('{{site_url}}', BASE_URL, $content);
        $content = str_replace('{{year}}', date('Y'), $content);
        
        $subject = $template['subject'];
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SITE_NAME . " <" . getSetting('contact_email') . ">\r\n";
        
        mail($email, $subject, $content, $headers);
    }
}
?>