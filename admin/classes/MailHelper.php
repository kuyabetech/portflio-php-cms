<?php
// admin/classes/MailHelper.php
// Professional Email Helper

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailHelper {
    private $mail;
    private $error;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->setup();
    }
    
    private function setup() {
        try {
            // Server settings
            $this->mail->SMTPDebug = SMTP::DEBUG_OFF; // Enable for debugging: SMTP::DEBUG_SERVER
            $this->mail->isSMTP();
            $this->mail->Host       = getSetting('smtp_host') ?: 'smtp.gmail.com';
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = getSetting('smtp_username') ?: '';
            $this->mail->Password   = getSetting('smtp_password') ?: '';
            $this->mail->SMTPSecure = getSetting('smtp_encryption') ?: PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port       = getSetting('smtp_port') ?: 587;
            
            // Default settings
            $this->mail->setFrom(
                getSetting('contact_email') ?: 'noreply@yourcompany.com',
                SITE_NAME
            );
            $this->mail->isHTML(true);
            $this->mail->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }
    }
    
    public function sendInvoice($to, $name, $invoice, $pdfPath) {
        try {
            // Recipient
            $this->mail->addAddress($to, $name);
            $this->mail->addReplyTo(
                getSetting('contact_email') ?: 'billing@yourcompany.com',
                SITE_NAME . ' Billing'
            );
            
            // Attachment
            $this->mail->addAttachment($pdfPath, 'Invoice-' . $invoice['invoice_number'] . '.pdf');
            
            // Content
            $this->mail->Subject = 'Invoice ' . $invoice['invoice_number'] . ' from ' . SITE_NAME;
            $this->mail->Body    = $this->getEmailBody($invoice, $name);
            $this->mail->AltBody = $this->getPlainTextBody($invoice, $name);
            
            return $this->mail->send();
            
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
    
    public function testConnection() {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress(getSetting('contact_email') ?: 'test@example.com', 'Test');
            $this->mail->Subject = 'Test Email from ' . SITE_NAME;
            $this->mail->Body = 'This is a test email to verify SMTP settings.';
            return $this->mail->send();
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }
    
    public function getError() {
        return $this->error;
    }
    
    private function getEmailBody($invoice, $clientName) {
        $balanceDue = (float)($invoice['balance_due'] ?? ($invoice['total'] - ($invoice['paid_amount'] ?? 0)));
        $dueDate = date('F d, Y', strtotime($invoice['due_date']));
        $invoiceDate = date('F d, Y', strtotime($invoice['invoice_date']));
        
        $statusColor = $this->getStatusColor($invoice['status']);
        $statusText = ucfirst($invoice['status']);
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    line-height: 1.6;
                    color: #2c3e50;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background: linear-gradient(135deg, #3498db, #2c3e50);
                    color: white;
                    padding: 30px;
                    text-align: center;
                    border-radius: 10px 10px 0 0;
                }
                .header h1 {
                    margin: 0;
                    font-size: 28px;
                    font-weight: 300;
                }
                .content {
                    background: #ffffff;
                    padding: 30px;
                    border: 1px solid #e2e8f0;
                    border-top: none;
                    border-radius: 0 0 10px 10px;
                }
                .status-badge {
                    display: inline-block;
                    padding: 8px 20px;
                    background-color: ' . $statusColor . ';
                    color: white;
                    border-radius: 30px;
                    font-size: 14px;
                    font-weight: 600;
                    text-transform: uppercase;
                    margin: 20px 0;
                }
                .invoice-details {
                    background: #f8fafc;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .invoice-details table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .invoice-details td {
                    padding: 10px;
                    border-bottom: 1px solid #e2e8f0;
                }
                .invoice-details td:first-child {
                    font-weight: 600;
                    color: #2c3e50;
                }
                .invoice-details td:last-child {
                    text-align: right;
                }
                .invoice-details .total-row {
                    font-size: 18px;
                    font-weight: 700;
                    color: #3498db;
                }
                .invoice-details .balance-row {
                    color: #e74c3c;
                    font-weight: 700;
                }
                .button {
                    display: inline-block;
                    padding: 14px 30px;
                    background: linear-gradient(135deg, #3498db, #2c3e50);
                    color: white;
                    text-decoration: none;
                    border-radius: 50px;
                    font-weight: 600;
                    margin: 20px 0;
                    box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
                }
                .button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
                }
                .footer {
                    text-align: center;
                    padding: 20px;
                    color: #7f8c8d;
                    font-size: 12px;
                    border-top: 1px solid #e2e8f0;
                }
                .company-info {
                    background: #f8fafc;
                    padding: 15px;
                    border-radius: 8px;
                    margin-top: 20px;
                    font-size: 13px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Invoice from ' . SITE_NAME . '</h1>
                </div>
                
                <div class="content">
                    <p>Dear <strong>' . htmlspecialchars($clientName) . '</strong>,</p>
                    
                    <p>Thank you for your business. Please find attached invoice <strong>' . htmlspecialchars($invoice['invoice_number']) . '</strong> for your recent services.</p>
                    
                    <div style="text-align: center;">
                        <span class="status-badge">' . $statusText . '</span>
                    </div>
                    
                    <div class="invoice-details">
                        <table>
                            <tr>
                                <td>Invoice Number:</td>
                                <td><strong>' . htmlspecialchars($invoice['invoice_number']) . '</strong></td>
                            </tr>
                            <tr>
                                <td>Invoice Date:</td>
                                <td>' . $invoiceDate . '</td>
                            </tr>
                            <tr>
                                <td>Due Date:</td>
                                <td>' . $dueDate . '</td>
                            </tr>
                            <tr>
                                <td>Total Amount:</td>
                                <td><strong>$' . number_format($invoice['total'], 2) . '</strong></td>
                            </tr>
                            ' . ($balanceDue > 0 ? '
                            <tr class="balance-row">
                                <td>Balance Due:</td>
                                <td><strong>$' . number_format($balanceDue, 2) . '</strong></td>
                            </tr>
                            ' : '
                            <tr>
                                <td>Amount Paid:</td>
                                <td><strong class="total-row">$' . number_format($invoice['total'], 2) . '</strong></td>
                            </tr>
                            ') . '
                        </table>
                    </div>
                    
                    <div style="text-align: center;">
                        <a href="' . BASE_URL . '/client/pay-invoice.php?id=' . $invoice['id'] . '" class="button">
                            ' . ($balanceDue > 0 ? 'Pay Now' : 'View Invoice') . '
                        </a>
                    </div>
                    
                    <p>If you have any questions about this invoice, please contact us:</p>
                    
                    <div class="company-info">
                        <p>
                            <strong>' . SITE_NAME . '</strong><br>
                            ' . nl2br(htmlspecialchars(getSetting('address') ?? '123 Business Street, City, State 12345')) . '<br>
                            Email: ' . htmlspecialchars(getSetting('contact_email') ?? 'billing@yourcompany.com') . '<br>
                            Phone: ' . htmlspecialchars(getSetting('contact_phone') ?? '+1 (555) 123-4567') . '
                        </p>
                    </div>
                    
                    <p>Thank you for choosing ' . SITE_NAME . '!</p>
                </div>
                
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                    <p>This is an automated message, please do not reply directly to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    private function getPlainTextBody($invoice, $clientName) {
        $balanceDue = (float)($invoice['balance_due'] ?? ($invoice['total'] - ($invoice['paid_amount'] ?? 0)));
        $dueDate = date('F d, Y', strtotime($invoice['due_date']));
        
        $text = "Dear $clientName,\n\n";
        $text .= "Thank you for your business. Please find attached invoice {$invoice['invoice_number']} for your recent services.\n\n";
        $text .= "Invoice Details:\n";
        $text .= "----------------\n";
        $text .= "Invoice Number: {$invoice['invoice_number']}\n";
        $text .= "Invoice Date: " . date('F d, Y', strtotime($invoice['invoice_date'])) . "\n";
        $text .= "Due Date: $dueDate\n";
        $text .= "Total Amount: $" . number_format($invoice['total'], 2) . "\n";
        
        if ($balanceDue > 0) {
            $text .= "Balance Due: $" . number_format($balanceDue, 2) . "\n";
        }
        
        $text .= "\nYou can view and pay your invoice online at:\n";
        $text .= BASE_URL . "/client/pay-invoice.php?id={$invoice['id']}\n\n";
        
        $text .= "If you have any questions, please contact us:\n";
        $text .= SITE_NAME . "\n";
        $text .= getSetting('address') ?? "123 Business Street, City, State 12345\n";
        $text .= "Email: " . (getSetting('contact_email') ?? 'billing@yourcompany.com') . "\n";
        $text .= "Phone: " . (getSetting('contact_phone') ?? '+1 (555) 123-4567') . "\n\n";
        $text .= "Thank you for choosing " . SITE_NAME . "!";
        
        return $text;
    }
    
    private function getStatusColor($status) {
        switch ($status) {
            case 'paid': return '#27ae60';
            case 'sent': return '#3498db';
            case 'overdue': return '#e74c3c';
            case 'draft': return '#95a5a6';
            default: return '#95a5a6';
        }
    }
}