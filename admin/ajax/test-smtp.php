<?php
// admin/ajax/test-smtp.php
// Test SMTP connection

require_once dirname(__DIR__, 2) . '/includes/init.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Create temporary mailer instance
require_once dirname(__DIR__, 2) . '/includes/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $input['host'];
    $mail->Port = (int)$input['port'];
    
    if (!empty($input['encryption']) && $input['encryption'] !== 'none') {
        $mail->SMTPSecure = $input['encryption'];
    }
    
    if (!empty($input['username']) && !empty($input['password'])) {
        $mail->SMTPAuth = true;
        $mail->Username = $input['username'];
        $mail->Password = $input['password'];
    }
    
    $mail->SMTPDebug = 0;
    $mail->Timeout = 10;
    
    // Test connection
    if ($mail->smtpConnect()) {
        $mail->smtpClose();
        echo json_encode(['success' => true, 'message' => 'SMTP connection successful!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not connect to SMTP server']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>