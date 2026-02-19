<?php
// contact-handler.php
// Handle contact form submissions via AJAX

require_once 'includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !Auth::verifyCSRF($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Validate required fields
if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['message'])) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

// Validate email
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

// Prepare data
$data = [
    'name' => sanitize($_POST['name']),
    'email' => sanitize($_POST['email']),
    'subject' => sanitize($_POST['subject'] ?? ''),
    'message' => sanitize($_POST['message']),
    'ip_address' => $_SERVER['REMOTE_ADDR']
];

// Save to database
try {
    $result = db()->insert('contact_messages', $data);
    
    if ($result) {
        // Send email notification
        $to = getSetting('contact_email');
        if ($to) {
            $subject = "New Contact Form Message from " . $data['name'];
            $body = "Name: " . $data['name'] . "\n";
            $body .= "Email: " . $data['email'] . "\n";
            $body .= "Subject: " . $data['subject'] . "\n\n";
            $body .= "Message:\n" . $data['message'];
            
            mail($to, $subject, $body, "From: " . $data['email']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Message sent successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save message']);
    }
} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>