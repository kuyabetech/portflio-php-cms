-- database/schema.sql
CREATE DATABASE IF NOT EXISTS kverify_portfolio;
USE kverify_portfolio;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    profile_image VARCHAR(255),
    bio TEXT,
    role ENUM('admin', 'editor') DEFAULT 'admin',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin (password: Admin@123)
INSERT INTO users (username, email, password_hash, full_name, role) 
VALUES ('admin', 'admin@kverify.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Projects table
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) UNIQUE NOT NULL,
    short_description TEXT,
    full_description TEXT,
    category VARCHAR(100),
    technologies TEXT,
    client_name VARCHAR(200),
    client_website VARCHAR(255),
    completion_date DATE,
    project_url VARCHAR(255),
    github_url VARCHAR(255),
    featured_image VARCHAR(255),
    gallery_images TEXT,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('draft', 'published') DEFAULT 'draft',
    views INT DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Skills table
CREATE TABLE skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT 'technical',
    proficiency INT CHECK (proficiency BETWEEN 0 AND 100),
    icon_class VARCHAR(100),
    years_experience DECIMAL(3,1),
    display_order INT DEFAULT 0,
    is_visible BOOLEAN DEFAULT TRUE
);

-- Testimonials table
CREATE TABLE testimonials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_name VARCHAR(200) NOT NULL,
    client_position VARCHAR(200),
    client_company VARCHAR(200),
    client_image VARCHAR(255),
    testimonial TEXT NOT NULL,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    project_id INT,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'approved') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Contact messages table
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL,
    phone VARCHAR(50),
    company VARCHAR(200),
    subject VARCHAR(200),
    message TEXT NOT NULL,
    budget_range VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    is_replied BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Site settings table
CREATE TABLE site_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'image', 'color') DEFAULT 'text'
);

-- Insert default settings
INSERT INTO site_settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'Kverify Digital Solutions', 'text'),
('site_title', 'Professional Web Developer Portfolio', 'text'),
('site_description', 'Expert web developer specializing in custom PHP solutions', 'textarea'),
('contact_email', 'hello@kverify.com', 'text'),
('contact_phone', '+1 234 567 890', 'text'),
('address', 'New York, NY', 'text'),
('primary_color', '#2563eb', 'color'),
('secondary_color', '#7c3aed', 'color'),
('github_url', 'https://github.com/username', 'text'),
('linkedin_url', 'https://linkedin.com/in/username', 'text'),
('twitter_url', 'https://twitter.com/username', 'text');

-- Sample data for testing
INSERT INTO skills (name, category, proficiency, icon_class, years_experience, display_order) VALUES
('PHP', 'technical', 95, 'fab fa-php', 5, 1),
('JavaScript', 'technical', 90, 'fab fa-js', 5, 2),
('MySQL', 'technical', 92, 'fas fa-database', 4, 3),
('HTML5/CSS3', 'technical', 98, 'fab fa-html5', 5, 4),
('React', 'technical', 85, 'fab fa-react', 3, 5),
('Laravel', 'technical', 88, 'fab fa-laravel', 4, 6);