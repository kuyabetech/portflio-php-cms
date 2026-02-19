<?php
// admin/ajax/preview-email-template.php
// Preview email template

require_once dirname(__DIR__, 2) . '/includes/init.php';

if (!Auth::check()) {
    die('Unauthorized');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$template = db()->fetch("SELECT * FROM email_templates WHERE id = ?", [$id]);

if (!$template) {
    die('Template not found');
}

// Prepare preview variables
$variables = [
    'client_name' => 'John Doe',
    'project_name' => 'Website Redesign',
    'invoice_number' => 'INV-2024-0123',
    'amount' => '1,500.00',
    'balance_due' => '1,500.00',
    'due_date' => date('F d, Y', strtotime('+30 days')),
    'payment_method' => 'Credit Card',
    'transaction_id' => 'ch_123456789',
    'payment_date' => date('F d, Y'),
    'days_overdue' => '5',
    'project_status' => 'In Progress',
    'update_message' => 'We have completed the initial design phase and are ready for your feedback.',
    'task_name' => 'Review Design Mockups',
    'task_description' => 'Please review the initial design concepts and provide feedback.',
    'assignee_name' => 'Jane Smith',
    'priority' => 'high',
    'due_date' => date('F d, Y', strtotime('+7 days')),
    'sender_name' => 'Project Manager',
    'recipient_name' => 'John Doe',
    'message_preview' => 'This is a preview of the message content...',
    'message_date' => date('F d, Y H:i'),
    'conversation_url' => '#',
    'file_name' => 'project-document.pdf',
    'file_description' => 'Project specifications and requirements',
    'file_size' => '2.5 MB',
    'file_category' => 'Documentation',
    'uploaded_by' => 'Admin',
    'file_url' => '#',
    'milestone_name' => 'Design Approval',
    'milestone_description' => 'Client approval of design concepts',
    'progress_percent' => '75',
    'portal_url' => BASE_URL . '/client',
    'project_url' => BASE_URL . '/client/project/1',
    'invoice_url' => BASE_URL . '/client/pay-invoice.php?id=1',
    'task_url' => '#',
    'site_name' => SITE_NAME,
    'site_url' => BASE_URL,
    'year' => date('Y'),
    'primary_color' => getSetting('primary_color', '#2563eb'),
    'secondary_color' => getSetting('secondary_color', '#7c3aed'),
    'contact_email' => getSetting('contact_email'),
    'contact_phone' => getSetting('contact_phone'),
    'sent_time' => date('Y-m-d H:i:s')
];

// Replace variables
$body = $template['body'];
foreach ($variables as $key => $value) {
    $body = str_replace('{{' . $key . '}}', $value, $body);
}

echo $body;
?>