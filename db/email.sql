-- Email Templates Table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_key VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    variables TEXT,
    category VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Email Queue Table
CREATE TABLE IF NOT EXISTS email_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_key VARCHAR(100),
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    attachments TEXT,
    priority ENUM('high', 'normal', 'low') DEFAULT 'normal',
    status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    sent_at DATETIME,
    scheduled_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at)
);

-- Email Logs Table
CREATE TABLE IF NOT EXISTS email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email_queue_id INT,
    template_key VARCHAR(100),
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status VARCHAR(50),
    error_message TEXT,
    opened_at DATETIME,
    clicked_at DATETIME,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default email templates
INSERT INTO email_templates (template_key, name, subject, body, category) VALUES
('welcome_client', 'Welcome Email for Clients', 'Welcome to {{site_name}} Client Portal', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
        .credentials { background: #e8f4fd; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .credentials code { background: #d4e6f1; padding: 3px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{site_name}}!</h1>
        </div>
        <div class="content">
            <p>Hello {{client_name}},</p>
            <p>Welcome to {{site_name}}! We''ve created a client portal account for you to track your projects, communicate with us, and manage invoices.</p>
            
            <div class="credentials">
                <h3>Your Login Credentials:</h3>
                <p><strong>Portal URL:</strong> <a href="{{portal_url}}">{{portal_url}}</a></p>
                <p><strong>Email:</strong> {{email}}</p>
                <p><strong>Password:</strong> <code>{{password}}</code></p>
                <p><small>Please change your password after first login for security.</small></p>
            </div>
            
            <p>You can now:</p>
            <ul>
                <li>View your projects and their progress</li>
                <li>Access project files and documents</li>
                <li>Communicate with our team</li>
                <li>View and pay invoices online</li>
            </ul>
            
            <p style="text-align: center;">
                <a href="{{portal_url}}" class="button">Access Your Portal</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'client'),

('invoice_created', 'New Invoice Created', 'Invoice #{{invoice_number}} from {{site_name}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .invoice-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .invoice-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .total-row { font-weight: bold; font-size: 1.2rem; color: {{primary_color}}; border-bottom: none; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Invoice</h1>
        </div>
        <div class="content">
            <p>Hello {{client_name}},</p>
            <p>A new invoice has been created for your project "{{project_title}}".</p>
            
            <div class="invoice-details">
                <h3>Invoice #{{invoice_number}}</h3>
                <div class="invoice-row">
                    <span>Invoice Date:</span>
                    <span>{{invoice_date}}</span>
                </div>
                <div class="invoice-row">
                    <span>Due Date:</span>
                    <span>{{due_date}}</span>
                </div>
                <div class="invoice-row">
                    <span>Amount:</span>
                    <span>${{amount}}</span>
                </div>
                <div class="invoice-row total-row">
                    <span>Total Due:</span>
                    <span>${{balance_due}}</span>
                </div>
            </div>
            
            <p style="text-align: center;">
                <a href="{{invoice_url}}" class="button">View & Pay Invoice</a>
            </p>
            
            <p>If you have any questions about this invoice, please don''t hesitate to contact us.</p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'invoice'),

('payment_confirmation', 'Payment Confirmation', 'Payment Received for Invoice #{{invoice_number}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10b981; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .payment-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .payment-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .success-icon { text-align: center; font-size: 48px; color: #10b981; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Received!</h1>
        </div>
        <div class="content">
            <div class="success-icon">✓</div>
            
            <p>Hello {{client_name}},</p>
            <p>We''ve received your payment for Invoice #{{invoice_number}}. Thank you for your prompt payment!</p>
            
            <div class="payment-details">
                <h3>Payment Details</h3>
                <div class="payment-row">
                    <span>Invoice Number:</span>
                    <span>#{{invoice_number}}</span>
                </div>
                <div class="payment-row">
                    <span>Payment Amount:</span>
                    <span>${{amount}}</span>
                </div>
                <div class="payment-row">
                    <span>Payment Date:</span>
                    <span>{{payment_date}}</span>
                </div>
                <div class="payment-row">
                    <span>Payment Method:</span>
                    <span>{{payment_method}}</span>
                </div>
                <div class="payment-row">
                    <span>Transaction ID:</span>
                    <span>{{transaction_id}}</span>
                </div>
            </div>
            
            <p>You can view your complete payment history and download receipts from your client portal.</p>
            
            <p style="text-align: center;">
                <a href="{{portal_url}}" class="button">Go to Portal</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'payment'),

('payment_reminder', 'Payment Reminder', 'Payment Reminder: Invoice #{{invoice_number}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f59e0b; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .invoice-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .invoice-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .urgent { color: #f59e0b; font-weight: bold; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Payment Reminder</h1>
        </div>
        <div class="content">
            <p>Hello {{client_name}},</p>
            <p>This is a friendly reminder that invoice #{{invoice_number}} is due for payment.</p>
            
            <div class="invoice-details">
                <h3>Invoice Summary</h3>
                <div class="invoice-row">
                    <span>Invoice Number:</span>
                    <span>#{{invoice_number}}</span>
                </div>
                <div class="invoice-row">
                    <span>Due Date:</span>
                    <span class="urgent">{{due_date}}</span>
                </div>
                <div class="invoice-row">
                    <span>Amount Due:</span>
                    <span>${{balance_due}}</span>
                </div>
                <div class="invoice-row">
                    <span>Days Overdue:</span>
                    <span class="urgent">{{days_overdue}} days</span>
                </div>
            </div>
            
            <p>To avoid any interruption in services, please arrange payment at your earliest convenience.</p>
            
            <p style="text-align: center;">
                <a href="{{invoice_url}}" class="button">Pay Now</a>
            </p>
            
            <p>If you''ve already made the payment, please disregard this message. Thank you for your business!</p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'reminder'),

('project_update', 'Project Status Update', 'Update on Project: {{project_name}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .update-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
        .status-badge { display: inline-block; padding: 4px 12px; background: #e8f4fd; color: {{primary_color}}; border-radius: 20px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Project Update</h1>
        </div>
        <div class="content">
            <p>Hello {{client_name}},</p>
            <p>We have an update on your project "{{project_name}}".</p>
            
            <div class="update-box">
                <h3>Current Status: <span class="status-badge">{{project_status}}</span></h3>
                <p>{{update_message}}</p>
                
                {{progress_html}}
            </div>
            
            <p>You can view the complete project details and latest updates in your client portal.</p>
            
            <p style="text-align: center;">
                <a href="{{project_url}}" class="button">View Project</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'project'),

('task_assigned', 'New Task Assigned', 'New Task: {{task_name}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .task-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .priority-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.9rem; }
        .priority-high { background: #fee2e2; color: #ef4444; }
        .priority-medium { background: #fef3c7; color: #f59e0b; }
        .priority-low { background: #e0f2fe; color: #3b82f6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Task Assigned</h1>
        </div>
        <div class="content">
            <p>Hello {{assignee_name}},</p>
            <p>A new task has been assigned to you.</p>
            
            <div class="task-box">
                <h3>{{task_name}}</h3>
                <p><strong>Project:</strong> {{project_name}}</p>
                <p><strong>Priority:</strong> <span class="priority-badge priority-{{priority}}">{{priority}}</span></p>
                <p><strong>Due Date:</strong> {{due_date}}</p>
                <p><strong>Description:</strong></p>
                <p>{{task_description}}</p>
            </div>
            
            <p style="text-align: center;">
                <a href="{{task_url}}" class="button">View Task</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'task'),

('message_notification', 'New Message', 'New Message Regarding {{project_name}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .message-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .message-meta { color: #666; font-size: 0.9rem; margin-bottom: 10px; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Message</h1>
        </div>
        <div class="content">
            <p>Hello {{recipient_name}},</p>
            <p>You have received a new message regarding project "{{project_name}}".</p>
            
            <div class="message-box">
                <div class="message-meta">
                    <strong>From:</strong> {{sender_name}}<br>
                    <strong>Date:</strong> {{message_date}}
                </div>
                <p>{{message_preview}}</p>
            </div>
            
            <p style="text-align: center;">
                <a href="{{conversation_url}}" class="button">View Message</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'communication'),

('file_uploaded', 'New File Uploaded', 'New File: {{file_name}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .file-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .file-icon { font-size: 48px; text-align: center; color: {{primary_color}}; margin-bottom: 10px; }
        .file-details { background: #f5f5f5; padding: 15px; border-radius: 8px; margin-top: 15px; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New File Uploaded</h1>
        </div>
        <div class="content">
            <p>Hello {{recipient_name}},</p>
            <p>A new file has been uploaded to project "{{project_name}}".</p>
            
            <div class="file-box">
                <div class="file-icon">📄</div>
                <h3>{{file_name}}</h3>
                <p>{{file_description}}</p>
                
                <div class="file-details">
                    <p><strong>Uploaded by:</strong> {{uploaded_by}}</p>
                    <p><strong>File size:</strong> {{file_size}}</p>
                    <p><strong>Category:</strong> {{file_category}}</p>
                </div>
            </div>
            
            <p style="text-align: center;">
                <a href="{{file_url}}" class="button">Download File</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'file'),

('milestone_completed', 'Project Milestone Completed', 'Milestone Completed: {{milestone_name}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10b981; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .milestone-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .progress-bar { height: 20px; background: #e0e0e0; border-radius: 10px; margin: 15px 0; overflow: hidden; }
        .progress-fill { height: 100%; background: {{primary_color}}; width: {{progress_percent}}%; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Milestone Achieved! 🎉</h1>
        </div>
        <div class="content">
            <p>Hello {{client_name}},</p>
            <p>Great news! We''ve completed a milestone on your project "{{project_name}}".</p>
            
            <div class="milestone-box">
                <h3>{{milestone_name}}</h3>
                <p>{{milestone_description}}</p>
                
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p><strong>Overall Project Progress: {{progress_percent}}%</strong></p>
            </div>
            
            <p>You can view the completed work and next steps in your client portal.</p>
            
            <p style="text-align: center;">
                <a href="{{project_url}}" class="button">View Progress</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'project'),

('test_email', 'Test Email', 'Test Email from {{site_name}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: {{primary_color}}; color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .success { background: #e8f5e9; color: #2e7d32; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Test Email</h1>
        </div>
        <div class="content">
            <div class="success">
                <strong>✅ Email Configuration Successful!</strong>
            </div>
            
            <p>This is a test email from {{site_name}}. If you''re reading this, your email system is working correctly.</p>
            
            <p><strong>Test Details:</strong></p>
            <ul>
                <li>Sent at: {{sent_time}}</li>
                <li>Server: {{server_name}}</li>
                <li>PHP Version: {{php_version}}</li>
            </ul>
            
            <p>You can now use the email notification system for:</p>
            <ul>
                <li>Welcome emails to new clients</li>
                <li>Invoice notifications</li>
                <li>Payment confirmations</li>
                <li>Payment reminders</li>
                <li>Project updates</li>
                <li>Task assignments</li>
                <li>File upload notifications</li>
            </ul>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
', 'test');