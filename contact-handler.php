<?php
/**
 * Contact Form Handler
 * Processes AJAX contact form submissions with styled email notifications
 */

require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/json');

// Enable debug logs (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

error_log("=== CONTACT FORM SUBMISSION ===");

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

// Start session if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ------------------------------------------------
// CSRF VALIDATION
// ------------------------------------------------

if (!isset($_SESSION['csrf_token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session expired. Please refresh.'
    ]);
    exit;
}

if (!isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid security token.'
    ]);
    exit;
}

// ------------------------------------------------
// HONEYPOT SPAM CHECK
// ------------------------------------------------

if (!empty($_POST['website']) ||
    !empty($_POST['url']) ||
    !empty($_POST['phone'])) {

    error_log("Spam bot detected via honeypot.");
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully.'
    ]);
    exit;
}

// ------------------------------------------------
// INPUT VALIDATION
// ------------------------------------------------

$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
$subject = trim($_POST['subject'] ?? '');

$errors = [];

if ($name === '' || strlen($name) < 2) {
    $errors[] = 'name';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'email';
}

if ($message === '' || strlen($message) < 10) {
    $errors[] = 'message';
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please complete all required fields.',
        'fields' => $errors
    ]);
    exit;
}

// Sanitize
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// ------------------------------------------------
// PREPARE DATA
// ------------------------------------------------

$data = [
    'name'       => $name,
    'email'      => $email,
    'subject'    => $subject,
    'message'    => $message,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'created_at' => date('Y-m-d H:i:s')
];

// ------------------------------------------------
// DATABASE SAVE
// ------------------------------------------------

try {

    $db = db();

    // Ensure table exists
    $db->getConnection()->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            subject VARCHAR(200),
            message TEXT NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->insert('contact_messages', $data);

    error_log("Contact message stored successfully.");

} catch (Exception $e) {

    error_log("Database error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred.'
    ]);
    exit;
}

// ------------------------------------------------
// SEND STYLED EMAIL NOTIFICATION
// ------------------------------------------------

try {

    $adminEmail = getSetting(
        'contact_email',
        'admin@' . parse_url(BASE_URL, PHP_URL_HOST)
    );

    $emailSubject = "New Contact Message from {$name}";

    // Styled HTML Email Template
    $htmlBody = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>New Contact Message</title>
        <style>
            /* Reset styles */
            body, table, td, p, a {
                margin: 0;
                padding: 0;
                border: 0;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                line-height: 1.6;
            }
            
            /* Container */
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #f8fafc;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
                border: 1px solid #e2e8f0;
            }
            
            /* Header */
            .email-header {
                background: linear-gradient(135deg, #1e293b, #0f172a);
                color: white;
                padding: 30px 40px;
                text-align: center;
            }
            
            .email-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
                letter-spacing: -0.5px;
            }
            
            .email-header p {
                margin: 10px 0 0;
                opacity: 0.9;
                font-size: 16px;
            }
            
            /* Content */
            .email-content {
                padding: 40px;
                background: white;
            }
            
            /* Message Card */
            .message-card {
                background: #f8fafc;
                border-radius: 12px;
                padding: 25px;
                margin-bottom: 25px;
                border: 1px solid #e2e8f0;
            }
            
            .message-card h2 {
                margin: 0 0 20px;
                font-size: 20px;
                color: #1e293b;
                font-weight: 600;
                padding-bottom: 10px;
                border-bottom: 2px solid #2563eb;
            }
            
            /* Info Grid */
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 25px;
            }
            
            .info-item {
                background: white;
                padding: 15px;
                border-radius: 10px;
                border: 1px solid #e2e8f0;
            }
            
            .info-label {
                font-size: 12px;
                text-transform: uppercase;
                color: #64748b;
                margin-bottom: 5px;
                font-weight: 600;
                letter-spacing: 0.5px;
            }
            
            .info-value {
                font-size: 16px;
                color: #1e293b;
                font-weight: 500;
                word-break: break-word;
            }
            
            .info-value a {
                color: #2563eb;
                text-decoration: none;
            }
            
            .info-value a:hover {
                text-decoration: underline;
            }
            
            /* Message Body */
            .message-body {
                background: white;
                padding: 20px;
                border-radius: 10px;
                border: 1px solid #e2e8f0;
                margin-bottom: 25px;
            }
            
            .message-body h3 {
                margin: 0 0 15px;
                font-size: 16px;
                color: #1e293b;
                font-weight: 600;
            }
            
            .message-content {
                background: #f8fafc;
                padding: 20px;
                border-radius: 8px;
                font-size: 15px;
                line-height: 1.7;
                color: #334155;
                border-left: 4px solid #2563eb;
            }
            
            /* Meta Info */
            .meta-info {
                background: #f1f5f9;
                padding: 20px;
                border-radius: 10px;
                margin-top: 25px;
                border: 1px solid #e2e8f0;
            }
            
            .meta-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #e2e8f0;
                font-size: 14px;
            }
            
            .meta-row:last-child {
                border-bottom: none;
            }
            
            .meta-label {
                color: #64748b;
                font-weight: 500;
            }
            
            .meta-value {
                color: #1e293b;
                font-weight: 600;
            }
            
            /* Badge */
            .badge {
                display: inline-block;
                background: #2563eb;
                color: white;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            /* Button */
            .btn {
                display: inline-block;
                background: #2563eb;
                color: white;
                padding: 12px 25px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                margin-top: 20px;
                transition: all 0.3s ease;
            }
            
            .btn:hover {
                background: #1d4ed8;
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
            }
            
            /* Footer */
            .email-footer {
                background: #f1f5f9;
                padding: 30px;
                text-align: center;
                border-top: 1px solid #e2e8f0;
            }
            
            .email-footer p {
                color: #64748b;
                font-size: 14px;
                margin: 5px 0;
            }
            
            .email-footer .company-name {
                font-weight: 600;
                color: #1e293b;
            }
            
            /* Divider */
            .divider {
                height: 1px;
                background: linear-gradient(90deg, transparent, #2563eb, transparent);
                margin: 20px 0;
            }
            
            /* Responsive */
            @media (max-width: 600px) {
                .email-content {
                    padding: 20px;
                }
                
                .info-grid {
                    grid-template-columns: 1fr;
                }
                
                .email-header {
                    padding: 20px;
                }
                
                .email-header h1 {
                    font-size: 24px;
                }
            }
        </style>
    </head>
    <body style='margin: 0; padding: 20px; background-color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color: #e2e8f0; padding: 20px;'>
            <tr>
                <td align='center'>
                    <table class='email-container' width='100%' max-width='600' cellpadding='0' cellspacing='0' border='0' style='max-width:600px; width:100%; background:white; border-radius:16px; overflow:hidden; box-shadow:0 10px 25px rgba(0,0,0,0.05);'>
                        <!-- Header -->
                        <tr>
                            <td class='email-header' style='background:linear-gradient(135deg, #1e293b, #0f172a); color:white; padding:30px 40px; text-align:center;'>
                                <h1 style='margin:0; font-size:28px; font-weight:600;'>📬 New Contact Message</h1>
                                <p style='margin:10px 0 0; opacity:0.9;'>You've received a new inquiry from your website</p>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td class='email-content' style='padding:40px; background:white;'>
                                <div class='message-card' style='background:#f8fafc; border-radius:12px; padding:25px; margin-bottom:25px; border:1px solid #e2e8f0;'>
                                    <h2 style='margin:0 0 20px; font-size:20px; color:#1e293b; padding-bottom:10px; border-bottom:2px solid #2563eb;'>
                                        <span class='badge' style='display:inline-block; background:#2563eb; color:white; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; margin-right:10px;'>NEW</span>
                                        Contact Details
                                    </h2>
                                    
                                    <!-- Info Grid -->
                                    <div class='info-grid' style='display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px;'>
                                        <div class='info-item' style='background:white; padding:15px; border-radius:10px; border:1px solid #e2e8f0;'>
                                            <div class='info-label' style='font-size:12px; text-transform:uppercase; color:#64748b; margin-bottom:5px; font-weight:600;'>👤 Name</div>
                                            <div class='info-value' style='font-size:16px; color:#1e293b; font-weight:500;'>{$name}</div>
                                        </div>
                                        
                                        <div class='info-item' style='background:white; padding:15px; border-radius:10px; border:1px solid #e2e8f0;'>
                                            <div class='info-label' style='font-size:12px; text-transform:uppercase; color:#64748b; margin-bottom:5px; font-weight:600;'>📧 Email</div>
                                            <div class='info-value' style='font-size:16px; color:#1e293b; font-weight:500;'>
                                                <a href='mailto:{$email}' style='color:#2563eb; text-decoration:none;'>{$email}</a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Subject (if exists) -->
                                    " . (!empty($subject) ? "
                                    <div style='margin-bottom:20px;'>
                                        <div class='info-item' style='background:white; padding:15px; border-radius:10px; border:1px solid #e2e8f0;'>
                                            <div class='info-label' style='font-size:12px; text-transform:uppercase; color:#64748b; margin-bottom:5px; font-weight:600;'>📝 Subject</div>
                                            <div class='info-value' style='font-size:16px; color:#1e293b; font-weight:500;'>{$subject}</div>
                                        </div>
                                    </div>
                                    " : "") . "
                                    
                                    <!-- Message -->
                                    <div class='message-body' style='background:white; padding:20px; border-radius:10px; border:1px solid #e2e8f0;'>
                                        <h3 style='margin:0 0 15px; font-size:16px; color:#1e293b; font-weight:600;'>💬 Message</h3>
                                        <div class='message-content' style='background:#f8fafc; padding:20px; border-radius:8px; font-size:15px; line-height:1.7; color:#334155; border-left:4px solid #2563eb;'>
                                            " . nl2br($message) . "
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Meta Information -->
                                <div class='meta-info' style='background:#f1f5f9; padding:20px; border-radius:10px; border:1px solid #e2e8f0;'>
                                    <h3 style='margin:0 0 15px; font-size:16px; color:#1e293b;'>📋 Additional Information</h3>
                                    
                                    <div class='meta-row' style='display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e2e8f0;'>
                                        <span class='meta-label' style='color:#64748b;'>🌐 IP Address:</span>
                                        <span class='meta-value' style='color:#1e293b; font-weight:600;'>{$data['ip_address']}</span>
                                    </div>
                                    
                                    <div class='meta-row' style='display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e2e8f0;'>
                                        <span class='meta-label' style='color:#64748b;'>📱 User Agent:</span>
                                        <span class='meta-value' style='color:#1e293b; font-weight:600;'>" . substr($data['user_agent'], 0, 50) . "...</span>
                                    </div>
                                    
                                    <div class='meta-row' style='display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e2e8f0;'>
                                        <span class='meta-label' style='color:#64748b;'>📅 Received:</span>
                                        <span class='meta-value' style='color:#1e293b; font-weight:600;'>" . date('F j, Y \a\t g:i A') . "</span>
                                    </div>
                                    
                                    <div class='meta-row' style='display:flex; justify-content:space-between; padding:8px 0;'>
                                        <span class='meta-label' style='color:#64748b;'>🔢 Message ID:</span>
                                        <span class='meta-value' style='color:#1e293b; font-weight:600;'>#" . uniqid() . "</span>
                                    </div>
                                </div>
                                
                                <!-- Admin Actions -->
                                <div style='text-align: center; margin-top: 30px;'>
                                    <a href='" . BASE_URL . "/admin/contact-messages.php' class='btn' style='display:inline-block; background:#2563eb; color:white; padding:12px 25px; border-radius:8px; text-decoration:none; font-weight:600; transition:all 0.3s ease;'>
                                        🔍 View in Admin Panel
                                    </a>
                                </div>
                                
                                <div class='divider' style='height:1px; background:linear-gradient(90deg, transparent, #2563eb, transparent); margin:20px 0;'></div>
                                
                                <!-- Quick Reply -->
                                <div style='text-align: center;'>
                                    <p style='color:#64748b; margin-bottom:10px;'>Quick reply options:</p>
                                    <a href='mailto:{$email}?subject=Re: " . urlencode($subject ?: 'Your Inquiry') . "' style='color:#2563eb; text-decoration:none; margin:0 10px;'>✉️ Reply via Email</a>
                                    <span style='color:#e2e8f0;'>|</span>
                                    <a href='tel:" . preg_replace('/[^0-9+]/', '', getSetting('contact_phone', '')) . "' style='color:#2563eb; text-decoration:none; margin:0 10px;'>📞 Call Client</a>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td class='email-footer' style='background:#f1f5f9; padding:30px; text-align:center; border-top:1px solid #e2e8f0;'>
                                <p class='company-name' style='color:#1e293b; font-weight:600; margin:5px 0;'>" . SITE_NAME . "</p>
                                <p style='color:#64748b; font-size:14px; margin:5px 0;'>This message was sent from your website contact form.</p>
                                <p style='color:#94a3b8; font-size:12px; margin:5px 0;'>© " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";

    // Plain text version
    $plainBody = "NEW CONTACT MESSAGE\n\n";
    $plainBody .= "Name: $name\n";
    $plainBody .= "Email: $email\n";
    if (!empty($subject)) $plainBody .= "Subject: $subject\n";
    $plainBody .= "\nMessage:\n$message\n\n";
    $plainBody .= "---\n";
    $plainBody .= "IP: {$data['ip_address']}\n";
    $plainBody .= "Date: " . date('F j, Y g:i A') . "\n";
    $plainBody .= "Message ID: #" . uniqid() . "\n";
    $plainBody .= "\nView in admin: " . BASE_URL . "/admin/contact-messages.php";

    // Send email using mailer() if available, otherwise use mail()
    if (function_exists('mailer') && method_exists(mailer(), 'sendHTML')) {
        $sent = mailer()->sendHTML(
            $adminEmail,
            $emailSubject,
            $htmlBody,
            $plainBody,
            [
                'reply_to' => [
                    'email' => $email,
                    'name'  => $name
                ]
            ]
        );
    } else {
        // Fallback to mail() with proper headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
        $headers .= "Reply-To: $name <$email>\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        $sent = mail($adminEmail, $emailSubject, $htmlBody, $headers);
    }

    if ($sent) {
        error_log("Styled admin email notification sent to $adminEmail");
    } else {
        error_log("Email failed to send");
    }

} catch (Exception $e) {
    error_log("Mailer error: " . $e->getMessage());
}

// ------------------------------------------------
// GENERATE NEW CSRF TOKEN
// ------------------------------------------------

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ------------------------------------------------
// SUCCESS RESPONSE
// ------------------------------------------------

echo json_encode([
    'success' => true,
    'message' => 'Message sent successfully!',
    'new_token' => $_SESSION['csrf_token']
]);