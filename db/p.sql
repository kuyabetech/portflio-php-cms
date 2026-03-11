-- Run this SQL in phpMyAdmin or MySQL

-- Invoices Table (enhanced)
CREATE TABLE IF NOT EXISTS project_invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    client_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('draft', 'sent', 'viewed', 'paid', 'overdue', 'cancelled', 'refunded') DEFAULT 'draft',
    
    -- Billing Details
    bill_to TEXT NOT NULL,
    ship_to TEXT,
    
    -- Line Items (JSON for flexibility)
    items JSON NOT NULL,
    
    -- Financial Summary
    subtotal DECIMAL(10,2) NOT NULL,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    discount_type ENUM('percentage', 'fixed') NULL,
    discount_value DECIMAL(10,2) DEFAULT 0,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    shipping_amount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    balance_due DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    
    -- Payment Details
    payment_method VARCHAR(50),
    payment_terms TEXT,
    notes TEXT,
    terms_conditions TEXT,
    
    -- Tax Information
    tax_id VARCHAR(100),
    business_number VARCHAR(100),
    
    -- Files
    pdf_path VARCHAR(255),
    
    -- Tracking
    sent_at DATETIME,
    viewed_at DATETIME,
    paid_at DATETIME,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
);

-- Invoice Items Table (for detailed tracking)
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    item_type ENUM('service', 'product', 'hourly', 'expense') DEFAULT 'service',
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES project_invoices(id) ON DELETE CASCADE
);

-- Payments Table
CREATE TABLE IF NOT EXISTS invoice_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    payment_number VARCHAR(50) UNIQUE NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'check', 'bank_transfer', 'credit_card', 'paypal', 'stripe', 'other') NOT NULL,
    transaction_id VARCHAR(255),
    reference_number VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',
    notes TEXT,
    receipt_sent BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES project_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Payment Gateways Configuration
CREATE TABLE IF NOT EXISTS payment_gateways (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gateway_name VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    api_key TEXT,
    api_secret TEXT,
    webhook_secret TEXT,
    sandbox_mode BOOLEAN DEFAULT TRUE,
    sandbox_api_key TEXT,
    sandbox_api_secret TEXT,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Recurring Invoices
CREATE TABLE IF NOT EXISTS recurring_invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    project_id INT,
    frequency ENUM('weekly', 'biweekly', 'monthly', 'quarterly', 'biannually', 'annually') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    next_date DATE NOT NULL,
    last_generated DATE,
    template JSON NOT NULL, -- Stores invoice template data
    status ENUM('active', 'paused', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);

-- Expenses Tracking
CREATE TABLE IF NOT EXISTS project_expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    receipt_file VARCHAR(255),
    billable BOOLEAN DEFAULT TRUE,
    billed_invoice_id INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (billed_invoice_id) REFERENCES project_invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tax Rates
CREATE TABLE IF NOT EXISTS tax_rates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    rate DECIMAL(5,2) NOT NULL,
    type ENUM('inclusive', 'exclusive') DEFAULT 'exclusive',
    is_default BOOLEAN DEFAULT FALSE,
    applies_to VARCHAR(50) DEFAULT 'all',
    region VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default tax rates
INSERT INTO tax_rates (name, rate, is_default) VALUES
('No Tax', 0, 1),
('VAT 20%', 20, 0),
('Sales Tax 10%', 10, 0);

-- Insert payment gateways
INSERT INTO payment_gateways (gateway_name, is_active, sandbox_mode) VALUES
('Stripe', 0, 1),
('PayPal', 0, 1),
('Bank Transfer', 1, 0);



-- Project timeline/milestones table
CREATE TABLE IF NOT EXISTS `project_timeline` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `due_date` DATE NOT NULL,
    `completed` BOOLEAN DEFAULT FALSE,
    `completed_at` DATETIME NULL,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_project` (`project_id`),
    INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Project documents table
CREATE TABLE IF NOT EXISTS `project_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `client_id` INT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `filename` VARCHAR(255) NOT NULL,
    `file_size` INT,
    `file_type` VARCHAR(100),
    `uploaded_by` INT,
    `download_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_project` (`project_id`),
    INDEX `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;