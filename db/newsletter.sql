-- Newsletter Subscribers Table
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `first_name` varchar(100) DEFAULT NULL,
    `last_name` varchar(100) DEFAULT NULL,
    `status` enum('active','unsubscribed','bounced') DEFAULT 'active',
    `source` varchar(50) DEFAULT 'website',
    `bounce_reason` text DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `created_at` datetime NOT NULL,
    `unsubscribed_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Newsletter Templates Table
CREATE TABLE IF NOT EXISTS `newsletter_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `content` text NOT NULL,
    `is_default` tinyint(1) DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Newsletter Campaigns Table
CREATE TABLE IF NOT EXISTS `newsletter_campaigns` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `template_id` int(11) DEFAULT NULL,
    `content` text NOT NULL,
    `status` enum('draft','scheduled','sending','sent','cancelled') DEFAULT 'draft',
    `scheduled_at` datetime DEFAULT NULL,
    `sent_at` datetime DEFAULT NULL,
    `sent_count` int(11) DEFAULT 0,
    `failed_count` int(11) DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `template_id` (`template_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Newsletter Queue Table (for sending emails in batches)
CREATE TABLE IF NOT EXISTS `newsletter_queue` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `campaign_id` int(11) NOT NULL,
    `subscriber_id` int(11) NOT NULL,
    `email` varchar(255) NOT NULL,
    `name` varchar(100) DEFAULT NULL,
    `status` enum('pending','sent','failed') DEFAULT 'pending',
    `attempts` int(11) DEFAULT 0,
    `error_message` text DEFAULT NULL,
    `sent_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `campaign_id` (`campaign_id`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;