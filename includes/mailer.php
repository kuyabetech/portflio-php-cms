<?php
// includes/mailer.php
// Central PHPMailer Configuration - USE THIS EVERYWHERE

// Load PHPMailer if using Composer
require_once __DIR__ . '/../vendor/autoload.php';

// If not using Composer, use manual includes:
// require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
// require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
// require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * Central Mailer Class
 * Handles all email sending with settings from database
 */
class Mailer {
    private static $instance = null;
    private $mailer;
    private $settings = [];
    
    /**
     * Private constructor (singleton pattern)
     */
    private function __construct() {
        $this->loadSettings();
        $this->initializeMailer();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load SMTP settings from database
     */
    private function loadSettings() {
        try {
            // Load from database
            $this->settings = [
                'smtp_host' => getSetting('smtp_host', ''),
                'smtp_port' => (int)getSetting('smtp_port', 587),
                'smtp_encryption' => getSetting('smtp_encryption', 'tls'),
                'smtp_username' => getSetting('smtp_username', ''),
                'smtp_password' => getSetting('smtp_password', ''),
                'smtp_from_email' => getSetting('smtp_from_email', getSetting('contact_email', 'noreply@' . $_SERVER['HTTP_HOST'])),
                'smtp_from_name' => getSetting('site_name', SITE_NAME),
                'smtp_reply_to' => getSetting('contact_email', ''),
                'smtp_debug' => (defined('DEV_MODE') && DEV_MODE) ? 2 : 0 // 0=off, 1=client, 2=client+server
            ];
        } catch (Exception $e) {
            error_log("Mailer: Failed to load settings - " . $e->getMessage());
            // Fallback to defaults
            $this->settings = [
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_from_email' => 'noreply@' . $_SERVER['HTTP_HOST'],
                'smtp_from_name' => SITE_NAME,
                'smtp_reply_to' => '',
                'smtp_debug' => 0
            ];
        }
    }
    
    /**
     * Initialize PHPMailer with settings
     */
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            if (!empty($this->settings['smtp_host'])) {
                // Use SMTP
                $this->mailer->isSMTP();
                $this->mailer->Host = $this->settings['smtp_host'];
                $this->mailer->Port = $this->settings['smtp_port'];
                
                // Encryption
                if (!empty($this->settings['smtp_encryption']) && $this->settings['smtp_encryption'] !== 'none') {
                    $this->mailer->SMTPSecure = $this->settings['smtp_encryption'];
                }
                
                // Authentication
                if (!empty($this->settings['smtp_username']) && !empty($this->settings['smtp_password'])) {
                    $this->mailer->SMTPAuth = true;
                    $this->mailer->Username = $this->settings['smtp_username'];
                    $this->mailer->Password = $this->settings['smtp_password'];
                }
                
                // Debug mode
                if ($this->settings['smtp_debug'] > 0) {
                    $this->mailer->SMTPDebug = $this->settings['smtp_debug'];
                }
            } else {
                // Use PHP mail() as fallback
                $this->mailer->isMail();
            }
            
            // Default settings
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            
            // Default sender
            $this->mailer->setFrom(
                $this->settings['smtp_from_email'],
                $this->settings['smtp_from_name']
            );
            
            // Default reply-to
            if (!empty($this->settings['smtp_reply_to'])) {
                $this->mailer->addReplyTo(
                    $this->settings['smtp_reply_to'],
                    $this->settings['smtp_from_name']
                );
            }
            
        } catch (Exception $e) {
            error_log("Mailer initialization error: " . $e->getMessage());
        }
    }
    
    /**
     * Send an email
     */
    public function send($to, $subject, $body, $options = []) {
        try {
            // Reset recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearAttachments();
            $this->mailer->clearReplyTos();
            
            // Set recipients
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    if (is_array($recipient)) {
                        $this->mailer->addAddress($recipient['email'], $recipient['name'] ?? '');
                    } else {
                        $this->mailer->addAddress($recipient);
                    }
                }
            } else {
                $this->mailer->addAddress($to);
            }
            
            // Set subject
            $this->mailer->Subject = $subject;
            
            // Set body
            if (!empty($options['is_html'])) {
                $this->mailer->isHTML(true);
                $this->mailer->Body = $body;
                if (!empty($options['alt_body'])) {
                    $this->mailer->AltBody = $options['alt_body'];
                }
            } else {
                $this->mailer->isHTML(false);
                $this->mailer->Body = $body;
            }
            
            // Add CC
            if (!empty($options['cc'])) {
                if (is_array($options['cc'])) {
                    foreach ($options['cc'] as $cc) {
                        $this->mailer->addCC($cc);
                    }
                } else {
                    $this->mailer->addCC($options['cc']);
                }
            }
            
            // Add BCC
            if (!empty($options['bcc'])) {
                if (is_array($options['bcc'])) {
                    foreach ($options['bcc'] as $bcc) {
                        $this->mailer->addBCC($bcc);
                    }
                } else {
                    $this->mailer->addBCC($options['bcc']);
                }
            }
            
            // Add Reply-To
            if (!empty($options['reply_to'])) {
                if (is_array($options['reply_to'])) {
                    $this->mailer->addReplyTo($options['reply_to']['email'], $options['reply_to']['name'] ?? '');
                } else {
                    $this->mailer->addReplyTo($options['reply_to']);
                }
            }
            
            // Add attachments
            if (!empty($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    if (is_array($attachment)) {
                        $this->mailer->addAttachment(
                            $attachment['path'],
                            $attachment['name'] ?? '',
                            $attachment['encoding'] ?? 'base64',
                            $attachment['type'] ?? ''
                        );
                    } else {
                        $this->mailer->addAttachment($attachment);
                    }
                }
            }
            
            // Send
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Mail sent successfully to: " . (is_array($to) ? json_encode($to) : $to));
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Mailer error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send HTML email
     */
    public function sendHTML($to, $subject, $htmlBody, $textBody = '', $options = []) {
        $options['is_html'] = true;
        $options['alt_body'] = $textBody ?: strip_tags($htmlBody);
        return $this->send($to, $subject, $htmlBody, $options);
    }
    
    /**
     * Send email using template
     */
    public function sendTemplate($to, $templateKey, $variables = [], $options = []) {
        try {
            // Get template from database
            $template = db()->fetch(
                "SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1",
                [$templateKey]
            );
            
            if (!$template) {
                error_log("Mailer: Template not found - $templateKey");
                return false;
            }
            
            // Replace variables in subject and body
            $subject = $this->replaceVariables($template['subject'], $variables);
            $body = $this->replaceVariables($template['body'], $variables);
            
            // Add global variables
            $globalVars = [
                'site_name' => SITE_NAME,
                'site_url' => BASE_URL,
                'year' => date('Y'),
                'date' => date('F j, Y'),
                'time' => date('h:i A')
            ];
            
            $subject = $this->replaceVariables($subject, $globalVars);
            $body = $this->replaceVariables($body, $globalVars);
            
            // Send as HTML
            $options['is_html'] = true;
            return $this->send($to, $subject, $body, $options);
            
        } catch (Exception $e) {
            error_log("Mailer template error: " . $e->getMessage());
            return false;
        }
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
     * Test SMTP connection
     */
    public function testConnection() {
        try {
            $this->mailer->smtpConnect();
            return ['success' => true, 'message' => 'SMTP connection successful'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get mailer instance for advanced usage
     */
    public function getPHPMailer() {
        return $this->mailer;
    }
}

/**
 * Global helper function to get Mailer instance
 */
function mailer() {
    return Mailer::getInstance();
}