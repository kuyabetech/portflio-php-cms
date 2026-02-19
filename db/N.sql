-- Run this SQL in phpMyAdmin or MySQL

-- Newsletter Subscribers Table
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    -- Newsletter Subscribers Table (continued)
    status ENUM('active', 'unsubscribed', 'bounced') DEFAULT 'active',
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unsubscribed_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    source VARCHAR(100) DEFAULT 'website',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
);

-- Newsletter Campaigns Table
CREATE TABLE IF NOT EXISTS newsletter_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    status ENUM('draft', 'scheduled', 'sending', 'sent', 'cancelled') DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    recipient_count INT DEFAULT 0,
    opens INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Newsletter Templates Table
CREATE TABLE IF NOT EXISTS newsletter_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    thumbnail VARCHAR(255),
    category VARCHAR(100),
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Newsletter Statistics Table
CREATE TABLE IF NOT EXISTS newsletter_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    subscriber_id INT NOT NULL,
    sent_at DATETIME,
    opened_at DATETIME,
    clicked_at DATETIME,
    clicked_links TEXT,
    unsubscribed_at DATETIME,
    bounced BOOLEAN DEFAULT FALSE,
    bounce_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES newsletter_campaigns(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_campaign_subscriber (campaign_id, subscriber_id)
);

-- Insert default templates
INSERT INTO newsletter_templates (name, subject, content, is_default) VALUES
('Welcome Email', 'Welcome to {{site_name}}!', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{site_name}}!</h1>
        </div>
        <div class="content">
            <p>Hi {{first_name}},</p>
            <p>Thank you for subscribing to our newsletter. We''re excited to have you on board!</p>
            <p>You''ll receive updates about:</p>
            <ul>
                <li>New blog posts and tutorials</li>
                <li>Latest projects and case studies</li>
                <li>Industry insights and tips</li>
                <li>Special offers and announcements</li>
            </ul>
            <p style="text-align: center;">
                <a href="{{site_url}}" class="button">Visit Our Website</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
            <p><a href="{{unsubscribe_url}}">Unsubscribe</a> | <a href="{{update_preferences_url}}">Update Preferences</a></p>
        </div>
    </div>
</body>
</html>
', 1),

('Monthly Newsletter', '{{site_name}} - Monthly Update', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .post { margin-bottom: 30px; padding: 20px; background: white; border-radius: 8px; }
        .post-title { font-size: 18px; margin-bottom: 10px; }
        .post-meta { color: #999; font-size: 12px; margin-bottom: 10px; }
        .button { display: inline-block; padding: 10px 20px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Monthly Update</h1>
        </div>
        <div class="content">
            <p>Hi {{first_name}},</p>
            <p>Here''s what we''ve been up to this month:</p>
            
            <div class="post">
                <h2 class="post-title">Latest Blog Post</h2>
                <div class="post-meta">Published on {{date}}</div>
                <p>{{blog_excerpt}}</p>
                <a href="{{blog_url}}" class="button">Read More</a>
            </div>
            
            <div class="post">
                <h2 class="post-title">New Project: {{project_name}}</h2>
                <p>{{project_description}}</p>
                <a href="{{project_url}}" class="button">View Project</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
            <p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>
        </div>
    </div>
</body>
</html>
', 0),

('Project Showcase', 'New Project: {{project_name}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .project-image { width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; }
        .project-details { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .tech-tag { display: inline-block; padding: 5px 10px; background: #f0f0f0; border-radius: 4px; margin-right: 5px; font-size: 12px; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Project: {{project_name}}</h1>
        </div>
        <div class="content">
            <p>Hi {{first_name}},</p>
            <p>I''m excited to share my latest project with you!</p>
            
            {{project_image}}
            
            <div class="project-details">
                <h2>{{project_name}}</h2>
                <p>{{project_description}}</p>
                <p><strong>Technologies used:</strong></p>
                <div>
                    {{project_technologies}}
                </div>
                <p><strong>Client:</strong> {{client_name}}</p>
                <p><strong>Completed:</strong> {{completion_date}}</p>
            </div>
            
            <p style="text-align: center;">
                <a href="{{project_url}}" class="button">View Live Project</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
            <p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>
        </div>
    </div>
</body>
</html>
', 0),

('Blog Post Alert', 'New Blog Post: {{post_title}}', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, {{primary_color}}, {{secondary_color}}); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
        .post-image { width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; margin-bottom: 20px; }
        .post-content { background: white; padding: 30px; border-radius: 8px; }
        .read-more { display: inline-block; margin-top: 20px; color: {{primary_color}}; text-decoration: none; font-weight: bold; }
        .button { display: inline-block; padding: 12px 24px; background: {{primary_color}}; color: white; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Blog Post</h1>
        </div>
        <div class="content">
            <p>Hi {{first_name}},</p>
            <p>I''ve just published a new blog post that I think you''ll find interesting:</p>
            
            {{post_image}}
            
            <div class="post-content">
                <h2>{{post_title}}</h2>
                <p>{{post_excerpt}}</p>
                <p><strong>Reading time:</strong> {{reading_time}} min</p>
                <a href="{{post_url}}" class="button">Read Full Article</a>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{year}} {{site_name}}. All rights reserved.</p>
            <p><a href="{{unsubscribe_url}}">Unsubscribe</a></p>
        </div>
    </div>
</body>
</html>
', 0);