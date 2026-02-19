-- Dashboard Widgets Table
CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    widget_key VARCHAR(100) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    widget_type ENUM('chart', 'stats', 'list', 'table', 'custom') DEFAULT 'stats',
    widget_size ENUM('small', 'medium', 'large', 'full') DEFAULT 'medium',
    refresh_interval INT DEFAULT 0, -- 0 = no refresh, seconds otherwise
    data_source VARCHAR(255),
    settings JSON,
    is_system BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User Dashboard Preferences
CREATE TABLE IF NOT EXISTS user_dashboards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    widget_id INT NOT NULL,
    position_x INT DEFAULT 0,
    position_y INT DEFAULT 0,
    width INT DEFAULT 1,
    height INT DEFAULT 1,
    custom_settings JSON,
    is_visible BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (widget_id) REFERENCES dashboard_widgets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_widget (user_id, widget_id)
);

-- Dashboard Alerts
CREATE TABLE IF NOT EXISTS dashboard_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    alert_type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    icon VARCHAR(50),
    link VARCHAR(255),
    is_dismissible BOOLEAN DEFAULT TRUE,
    is_global BOOLEAN DEFAULT FALSE,
    user_id INT,
    expires_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- System Health Metrics
CREATE TABLE IF NOT EXISTS system_health (
    id INT PRIMARY KEY AUTO_INCREMENT,
    metric_key VARCHAR(100) NOT NULL,
    metric_value TEXT,
    metric_type ENUM('gauge', 'counter', 'text') DEFAULT 'gauge',
    status ENUM('ok', 'warning', 'critical') DEFAULT 'ok',
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default dashboard widgets
INSERT INTO dashboard_widgets (widget_key, title, description, icon, widget_type, widget_size, is_system) VALUES
('welcome_widget', 'Welcome to Dashboard', 'Quick overview and getting started guide', 'fas fa-hand-wave', 'custom', 'full', 1),
('stats_overview', 'Quick Stats', 'Overview of your key metrics', 'fas fa-chart-pie', 'stats', 'medium', 1),
('recent_projects', 'Recent Projects', 'Latest projects and their status', 'fas fa-code-branch', 'list', 'medium', 1),
('recent_messages', 'Recent Messages', 'Latest contact form submissions', 'fas fa-envelope', 'list', 'medium', 1),
('project_progress', 'Project Progress', 'Project completion status', 'fas fa-tasks', 'chart', 'medium', 1),
('revenue_chart', 'Revenue Overview', 'Monthly revenue chart', 'fas fa-chart-line', 'chart', 'large', 1),
('task_summary', 'Task Summary', 'Overview of pending tasks', 'fas fa-check-circle', 'stats', 'small', 1),
('client_growth', 'Client Growth', 'New clients this month', 'fas fa-user-plus', 'stats', 'small', 1),
('popular_pages', 'Popular Pages', 'Most visited pages', 'fas fa-star', 'list', 'medium', 1),
('system_health', 'System Health', 'Server and application status', 'fas fa-heartbeat', 'custom', 'small', 1),
('quick_actions', 'Quick Actions', 'Common admin tasks', 'fas fa-bolt', 'custom', 'small', 1),
('recent_activity', 'Recent Activity', 'Latest system activities', 'fas fa-history', 'list', 'medium', 1),
('upcoming_tasks', 'Upcoming Tasks', 'Tasks due soon', 'fas fa-clock', 'list', 'medium', 1),
('browser_stats', 'Browser Statistics', 'Visitor browser breakdown', 'fas fa-globe', 'chart', 'medium', 1),
('device_stats', 'Device Statistics', 'Visitor device breakdown', 'fas fa-mobile-alt', 'chart', 'medium', 1);

-- Insert sample alerts
INSERT INTO dashboard_alerts (title, message, alert_type, icon, is_global) VALUES
('Welcome to the new Dashboard!', 'You can now customize your dashboard by dragging widgets and saving layouts.', 'info', 'fas fa-info-circle', 1),
('System Update Available', 'A new version of the CMS is available. Please backup before updating.', 'warning', 'fas fa-exclamation-triangle', 1);