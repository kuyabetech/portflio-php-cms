<?php
/**
 * subscribe.php
 * Handle newsletter subscriptions via AJAX
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all requests
error_log("=== Newsletter Subscription Request ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

require_once 'includes/init.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Error: Invalid method - " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Set JSON content type
header('Content-Type: application/json');

// Initialize session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log("Session started");
}

// ----------------------------------------------------------------------------
// CSRF Validation
// ----------------------------------------------------------------------------

error_log("Session CSRF: " . ($_SESSION['csrf_token'] ?? 'NOT SET'));
error_log("POST CSRF: " . ($_POST['csrf_token'] ?? 'NOT SET'));

if (!isset($_POST['csrf_token'])) {
    error_log("Error: CSRF token missing from POST");
    echo json_encode(['success' => false, 'message' => 'Security token missing. Please refresh the page.']);
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    error_log("Error: CSRF token missing from session");
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh the page.']);
    exit;
}

if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    error_log("Error: CSRF token mismatch");
    error_log("Expected: " . $_SESSION['csrf_token']);
    error_log("Received: " . $_POST['csrf_token']);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

error_log("CSRF validation passed");

// ----------------------------------------------------------------------------
// Honeypot Spam Protection
// ----------------------------------------------------------------------------

if (!empty($_POST['website']) || !empty($_POST['url']) || !empty($_POST['phone'])) {
    error_log("Bot detected - honeypot fields filled");
    echo json_encode(['success' => true, 'message' => 'Subscription successful!']);
    exit;
}

// ----------------------------------------------------------------------------
// Validate Email
// ----------------------------------------------------------------------------

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';

error_log("Email: $email, Name: $name");

if (empty($email)) {
    error_log("Error: Email is empty");
    echo json_encode(['success' => false, 'message' => 'Email address is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("Error: Invalid email format - $email");
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

// Optional name validation
if (!empty($name) && strlen($name) < 2) {
    error_log("Error: Name too short - $name");
    echo json_encode(['success' => false, 'message' => 'Name must be at least 2 characters']);
    exit;
}

// ----------------------------------------------------------------------------
// Rate Limiting - Prevent spam
// ----------------------------------------------------------------------------

$ip = $_SERVER['REMOTE_ADDR'];
$rateLimit = 5; // Max 5 subscriptions per hour per IP

try {
    // Check if table exists first
    $tableExists = db()->fetch("SHOW TABLES LIKE 'newsletter_subscribers'");
    
    if ($tableExists) {
        $recentCount = db()->fetch(
            "SELECT COUNT(*) as count FROM newsletter_subscribers 
             WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$ip]
        );
        
        if ($recentCount && $recentCount['count'] >= $rateLimit) {
            error_log("Rate limit exceeded for IP: $ip");
            echo json_encode([
                'success' => false, 
                'message' => 'Too many subscription attempts. Please try again later.'
            ]);
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Rate limit check error: " . $e->getMessage());
    // Continue anyway - don't block on rate limit errors
}

// ----------------------------------------------------------------------------
// Check/Create Subscribers Table
// ----------------------------------------------------------------------------

try {
    $tableExists = db()->fetch("SHOW TABLES LIKE 'newsletter_subscribers'");
    error_log("Table exists: " . ($tableExists ? 'Yes' : 'No'));
    
    if (!$tableExists) {
        error_log("Creating newsletter_subscribers table");
        $createTable = "
            CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `email` varchar(255) NOT NULL,
                `first_name` varchar(100) DEFAULT NULL,
                `last_name` varchar(100) DEFAULT NULL,
                `status` enum('active','unsubscribed','bounced','pending') DEFAULT 'active',
                `source` varchar(50) DEFAULT 'website',
                `ip_address` varchar(45) DEFAULT NULL,
                `user_agent` text DEFAULT NULL,
                `created_at` datetime NOT NULL,
                `unsubscribed_at` datetime DEFAULT NULL,
                `confirmed_at` datetime DEFAULT NULL,
                `confirmation_token` varchar(64) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        db()->getConnection()->exec($createTable);
        error_log("Table created successfully");
    }
} catch (Exception $e) {
    error_log("Table creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error. Please try again later.'
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// Check if already subscribed
// ----------------------------------------------------------------------------

try {
    $existing = db()->fetch(
        "SELECT id, status FROM newsletter_subscribers WHERE email = ?",
        [$email]
    );
    
    if ($existing) {
        error_log("Existing subscriber found - ID: {$existing['id']}, Status: {$existing['status']}");
        
        if ($existing['status'] === 'active') {
            echo json_encode([
                'success' => false, 
                'message' => 'This email is already subscribed to our newsletter.'
            ]);
            exit;
        } elseif ($existing['status'] === 'unsubscribed' || $existing['status'] === 'bounced') {
            // Reactivate
            error_log("Reactivating subscriber ID: {$existing['id']}");
            
            $updateData = [
                'status' => 'active',
                'unsubscribed_at' => null,
                'ip_address' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            db()->update('newsletter_subscribers', $updateData, 'id = :id', ['id' => $existing['id']]);
            
            // Send welcome back email
            sendWelcomeEmail($email, $name);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Welcome back! You have been resubscribed.'
            ]);
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Check existing error: " . $e->getMessage());
    // Continue with new subscription
}

// ----------------------------------------------------------------------------
// Parse name into first and last
// ----------------------------------------------------------------------------

$firstName = '';
$lastName = '';
if (!empty($name)) {
    $nameParts = explode(' ', $name, 2);
    $firstName = $nameParts[0];
    $lastName = $nameParts[1] ?? '';
    error_log("Name parsed - First: $firstName, Last: $lastName");
}

// ----------------------------------------------------------------------------
// Save subscriber
// ----------------------------------------------------------------------------

try {
    $data = [
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'status' => 'active',
        'source' => 'website',
        'ip_address' => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    error_log("Attempting to insert: " . json_encode($data));
    
    $result = db()->insert('newsletter_subscribers', $data);
    error_log("Insert result: " . ($result ? "Success (ID: $result)" : "Failed"));
    
    if (!$result) {
        throw new Exception('Failed to insert subscriber');
    }
    
    // Send welcome email
    $emailSent = sendWelcomeEmail($email, $name);
    error_log("Welcome email sent: " . ($emailSent ? 'Yes' : 'No'));
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    error_log("New CSRF token generated: " . $_SESSION['csrf_token']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for subscribing to our newsletter!',
        'new_token' => $_SESSION['csrf_token']
    ]);
    
} catch (Exception $e) {
    error_log("=== SUBSCRIPTION ERROR ===");
    error_log("Error message: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    error_log("File: " . $e->getFile());
    error_log("Line: " . $e->getLine());
    
    // Check for duplicate entry error
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode([
            'success' => false,
            'message' => 'This email is already subscribed.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred. Please try again later.'
        ]);
    }
}

// ----------------------------------------------------------------------------
// Email Functions
// ----------------------------------------------------------------------------

function sendWelcomeEmail($email, $name) {
    try {
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
                    <p>Thank you for subscribing to our newsletter! You'll now receive updates about our latest projects, blog posts, and news.</p>
                    <p>Here's what you can expect:</p>
                    <ul>
                        <li>Monthly updates on new projects</li>
                        <li>Exclusive content and tips</li>
                        <li>Early access to new features</li>
                    </ul>
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
        $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        
        $result = mail($email, $subject, $htmlBody, $headers);
        error_log("Mail function returned: " . ($result ? 'true' : 'false'));
        
        if (!$result) {
            $error = error_get_last();
            error_log("Mail error: " . ($error['message'] ?? 'Unknown error'));
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Email function error: " . $e->getMessage());
        return false;
    }
}
?>