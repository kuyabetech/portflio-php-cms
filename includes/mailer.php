<?php
// includes/mailer.php
// Email handling class

class Mailer {
    private $config;
    
    public function __construct() {
        $this->config = [
            'smtp_host' => getSetting('smtp_host'),
            'smtp_port' => getSetting('smtp_port'),
            'smtp_user' => getSetting('smtp_username'),
            'smtp_pass' => getSetting('smtp_password'),
            'smtp_encryption' => getSetting('smtp_encryption', 'tls'),
            'from_email' => getSetting('contact_email'),
            'from_name' => SITE_NAME
        ];
    }
    
    /**
     * Queue an email for sending
     */
    public function queue($to, $subject, $body, $templateKey = null, $priority = 'normal', $scheduledAt = null) {
        $data = [
            'to_email' => is_array($to) ? $to['email'] : $to,
            'to_name' => is_array($to) ? ($to['name'] ?? null) : null,
            'subject' => $subject,
            'body' => $body,
            'template_key' => $templateKey,
            'priority' => $priority,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt
        ];
        
        return db()->insert('email_queue', $data);
    }
    
    /**
     * Send email immediately using template
     */
    public function sendTemplate($templateKey, $to, $variables = []) {
        // Get template
        $template = db()->fetch("SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1", [$templateKey]);
        if (!$template) {
            error_log("Email template not found: $templateKey");
            return false;
        }
        
        // Prepare variables
        $variables = array_merge($this->getGlobalVariables(), $variables);
        
        // Replace variables in subject and body
        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body'], $variables);
        
        // Queue or send based on settings
        if (getSetting('queue_emails', '0') == '1') {
            return $this->queue($to, $subject, $body, $templateKey);
        } else {
            return $this->sendNow($to, $subject, $body);
        }
    }
    
    /**
     * Send email immediately
     */
    public function sendNow($to, $subject, $body, $attachments = []) {
        $toEmail = is_array($to) ? $to['email'] : $to;
        $toName = is_array($to) ? ($to['name'] ?? '') : '';
        
        // Prepare headers
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $this->config['from_name'] . " <" . $this->config['from_email'] . ">\r\n";
        
        if ($toName) {
            $headers .= "Reply-To: " . $this->config['from_email'] . "\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
        }
        
        // Use SMTP if configured
        if (!empty($this->config['smtp_host'])) {
            return $this->sendSMTP($toEmail, $subject, $body, $headers);
        } else {
            // Use PHP mail function
            return mail($toEmail, $subject, $body, $headers);
        }
    }
    
    /**
     * Send via SMTP
     */
    private function sendSMTP($to, $subject, $body, $headers) {
        // Implement SMTP sending here
        // For now, fall back to mail()
        return mail($to, $subject, $body, $headers);
    }
    
    /**
     * Process email queue (call from cron)
     */
    public function processQueue($limit = 50) {
        $emails = db()->fetchAll("
            SELECT * FROM email_queue 
            WHERE status = 'pending' 
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            ORDER BY 
                CASE priority 
                    WHEN 'high' THEN 1 
                    WHEN 'normal' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                created_at ASC
            LIMIT ?
        ", [$limit]);
        
        $processed = 0;
        foreach ($emails as $email) {
            $success = $this->sendNow(
                ['email' => $email['to_email'], 'name' => $email['to_name']],
                $email['subject'],
                $email['body']
            );
            
            $status = $success ? 'sent' : 'failed';
            $errorMessage = $success ? null : 'Failed to send';
            
            // Update queue
            db()->update('email_queue', [
                'status' => $status,
                'attempts' => $email['attempts'] + 1,
                'error_message' => $errorMessage,
                'sent_at' => $success ? date('Y-m-d H:i:s') : null
            ], 'id = :id', ['id' => $email['id']]);
            
            // Log
            db()->insert('email_logs', [
                'email_queue_id' => $email['id'],
                'template_key' => $email['template_key'],
                'to_email' => $email['to_email'],
                'subject' => $email['subject'],
                'status' => $status,
                'error_message' => $errorMessage
            ]);
            
            if ($success) $processed++;
        }
        
        return $processed;
    }
    
    /**
     * Get global variables for email templates
     */
    private function getGlobalVariables() {
        return [
            'site_name' => SITE_NAME,
            'site_url' => BASE_URL,
            'year' => date('Y'),
            'primary_color' => getSetting('primary_color', '#2563eb'),
            'secondary_color' => getSetting('secondary_color', '#7c3aed'),
            'contact_email' => getSetting('contact_email'),
            'contact_phone' => getSetting('contact_phone'),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'php_version' => phpversion()
        ];
    }
    
    /**
     * Replace variables in text
     */
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }
    
    /**
     * Send test email
     */
    public function sendTest($to) {
        return $this->sendTemplate('test_email', $to, [
            'sent_time' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get email statistics
     */
    public function getStats($days = 30) {
        return db()->fetch("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                COUNT(DISTINCT template_key) as templates_used
            FROM email_queue 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ", [$days]);
    }
}

// Global mailer function
function mailer() {
    static $mailer = null;
    if ($mailer === null) {
        $mailer = new Mailer();
    }
    return $mailer;
}
?>