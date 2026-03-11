CREATE TABLE IF NOT EXISTS `client_users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `client_id` int(11) DEFAULT NULL,
    `email` varchar(100) NOT NULL,
    `password_hash` varchar(255) NOT NULL,
    `first_name` varchar(50) DEFAULT NULL,
    `last_name` varchar(50) DEFAULT NULL,
    `role` varchar(50) DEFAULT 'client',
    `is_active` tinyint(1) DEFAULT 1,
    `last_login` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `client_remember_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `client_id` int(11) NOT NULL,
    `selector` varchar(20) NOT NULL,
    `hashed_validator` varchar(255) NOT NULL,
    `expires_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `selector` (`selector`),
    KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- Client notifications table
CREATE TABLE IF NOT EXISTS `client_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'fa-info-circle',
    `link` VARCHAR(500) DEFAULT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client activity log
CREATE TABLE IF NOT EXISTS `client_activity_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `icon` VARCHAR(50) DEFAULT 'fa-circle',
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client documents
CREATE TABLE IF NOT EXISTS `client_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `project_id` INT DEFAULT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `filename` VARCHAR(255) NOT NULL,
    `file_size` INT,
    `file_type` VARCHAR(100),
    `uploaded_by` INT,
    `download_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Support tickets
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `ticket_number` VARCHAR(20) UNIQUE NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `priority` ENUM('low','medium','high','urgent') DEFAULT 'medium',
    `status` ENUM('open','in_progress','waiting','resolved','closed') DEFAULT 'open',
    `last_reply_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client (client_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ticket replies
CREATE TABLE IF NOT EXISTS `ticket_replies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `client_id` INT DEFAULT NULL,
    `message` TEXT NOT NULL,
    `is_staff` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client messages table
CREATE TABLE IF NOT EXISTS `client_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `admin_id` INT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `sender` ENUM('client', 'admin') DEFAULT 'client',
    `status` ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    `parent_id` INT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_client` (`client_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;