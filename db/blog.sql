-- Run this SQL in phpMyAdmin or MySQL

-- Blog Categories Table
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    image VARCHAR(255),
    parent_id INT DEFAULT 0,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Blog Tags Table
CREATE TABLE IF NOT EXISTS blog_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Blog Posts Table
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(255),
    category_id INT,
    author_id INT,
    views INT DEFAULT 0,
    reading_time INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at DATETIME,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(255),
    allow_comments BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Blog Posts Tags Junction Table
CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT,
    tag_id INT,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
);

-- Blog Comments Table
CREATE TABLE IF NOT EXISTS blog_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    parent_id INT DEFAULT 0,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    website VARCHAR(255),
    comment TEXT NOT NULL,
    is_approved BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE CASCADE
);

-- SEO Metadata Table
CREATE TABLE IF NOT EXISTS seo_metadata (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page_url VARCHAR(255) UNIQUE NOT NULL,
    page_type ENUM('home', 'projects', 'blog', 'contact', 'custom') DEFAULT 'custom',
    title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    og_title VARCHAR(255),
    og_description TEXT,
    og_image VARCHAR(255),
    twitter_title VARCHAR(255),
    twitter_description TEXT,
    twitter_image VARCHAR(255),
    canonical_url VARCHAR(255),
    noindex BOOLEAN DEFAULT FALSE,
    nofollow BOOLEAN DEFAULT FALSE,
    schema_markup TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Redirects Table
CREATE TABLE IF NOT EXISTS seo_redirects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    old_url VARCHAR(255) NOT NULL,
    new_url VARCHAR(255) NOT NULL,
    status_code INT DEFAULT 301,
    hits INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_old_url (old_url)
);

-- Analytics Table
CREATE TABLE IF NOT EXISTS seo_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    page_url VARCHAR(255),
    page_type VARCHAR(50),
    visitor_ip VARCHAR(45),
    user_agent TEXT,
    referrer_url TEXT,
    visit_date DATE,
    visit_time TIME,
    country VARCHAR(100),
    city VARCHAR(100),
    device_type ENUM('desktop', 'tablet', 'mobile'),
    browser VARCHAR(100),
    os VARCHAR(100),
    screen_resolution VARCHAR(20),
    language VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample categories
INSERT INTO blog_categories (name, slug, description) VALUES
('Web Development', 'web-development', 'Tips, tutorials, and insights about web development'),
('PHP Programming', 'php-programming', 'Deep dives into PHP, best practices, and modern techniques'),
('JavaScript', 'javascript', 'Frontend development, frameworks, and JavaScript tips'),
('Career Advice', 'career-advice', 'Guidance for developers on career growth and freelancing'),
('Tutorials', 'tutorials', 'Step-by-step guides to help you learn new skills');

-- Insert sample tags
INSERT INTO blog_tags (name, slug) VALUES
('PHP', 'php'),
('Laravel', 'laravel'),
('MySQL', 'mysql'),
('JavaScript', 'javascript'),
('React', 'react'),
('Vue.js', 'vuejs'),
('HTML5', 'html5'),
('CSS3', 'css3'),
('Responsive Design', 'responsive-design'),
('SEO', 'seo');