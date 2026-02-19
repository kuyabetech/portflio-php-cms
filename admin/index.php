<?php
// admin/index.php
// Enhanced Admin Dashboard with Multiple Sections

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Dashboard';
$breadcrumbs = [
    ['title' => 'Dashboard']
];

// Initialize all stats arrays with default values
$stats = [
    // Project Stats
    'total_projects' => 0,
    'active_projects' => 0,
    'completed_projects' => 0,
    'cancelled_projects' => 0,
    'project_completion_rate' => 0,
    
    // Client Stats
    'total_clients' => 0,
    'active_clients' => 0,
    'new_clients_month' => 0,
    'client_retention_rate' => 0,
    
    // Financial Stats
    'total_invoices' => 0,
    'paid_invoices' => 0,
    'pending_invoices' => 0,
    'overdue_invoices' => 0,
    'total_revenue' => 0,
    'monthly_revenue' => 0,
    'average_invoice_value' => 0,
    'outstanding_balance' => 0,
    
    // Task Stats
    'total_tasks' => 0,
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'completed_tasks' => 0,
    'overdue_tasks' => 0,
    'task_completion_rate' => 0,
    
    // Communication Stats
    'unread_messages' => 0,
    'total_messages' => 0,
    'unread_notifications' => 0,
    
    // Content Stats
    'total_blog_posts' => 0,
    'published_posts' => 0,
    'draft_posts' => 0,
    'total_comments' => 0,
    'pending_comments' => 0,
    'total_testimonials' => 0,
    'pending_testimonials' => 0,
    
    // System Stats
    'disk_usage' => 0,
    'disk_free' => 0,
    'disk_total' => 0,
    'php_version' => PHP_VERSION,
    'mysql_version' => 'Unknown',
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'last_backup' => 'Never',
    'system_uptime' => 'Unknown'
];

// Safely get database statistics with error handling
try {
    // Project Statistics
    $projects = db()->fetch("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM projects");
    
    $stats['total_projects'] = isset($projects['total']) ? (int)$projects['total'] : 0;
    $stats['active_projects'] = isset($projects['active']) ? (int)$projects['active'] : 0;
    $stats['completed_projects'] = isset($projects['completed']) ? (int)$projects['completed'] : 0;
    $stats['cancelled_projects'] = isset($projects['cancelled']) ? (int)$projects['cancelled'] : 0;
    $stats['project_completion_rate'] = $stats['total_projects'] > 0 
        ? round(($stats['completed_projects'] / $stats['total_projects']) * 100, 1) 
        : 0;
} catch (Exception $e) {}

try {
    // Client Statistics
    $clients = db()->fetch("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new
        FROM clients");
    
    $stats['total_clients'] = isset($clients['total']) ? (int)$clients['total'] : 0;
    $stats['active_clients'] = isset($clients['active']) ? (int)$clients['active'] : 0;
    $stats['new_clients_month'] = isset($clients['new']) ? (int)$clients['new'] : 0;
    $stats['client_retention_rate'] = $stats['total_clients'] > 0 
        ? round(($stats['active_clients'] / $stats['total_clients']) * 100, 1) 
        : 0;
} catch (Exception $e) {}

try {
    // Financial Statistics
    $invoices = db()->fetch("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END), 0) as paid_amount,
        COALESCE(SUM(CASE WHEN status != 'paid' AND status != 'cancelled' THEN balance_due ELSE 0 END), 0) as outstanding
        FROM project_invoices");
    
    $monthlyRevenue = db()->fetch("SELECT COALESCE(SUM(total), 0) as total 
        FROM project_invoices 
        WHERE status = 'paid' 
        AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    
    $stats['total_invoices'] = isset($invoices['total']) ? (int)$invoices['total'] : 0;
    $stats['paid_invoices'] = isset($invoices['paid']) ? (int)$invoices['paid'] : 0;
    $stats['pending_invoices'] = isset($invoices['pending']) ? (int)$invoices['pending'] : 0;
    $stats['overdue_invoices'] = isset($invoices['overdue']) ? (int)$invoices['overdue'] : 0;
    $stats['total_revenue'] = isset($invoices['paid_amount']) ? (float)$invoices['paid_amount'] : 0;
    $stats['monthly_revenue'] = isset($monthlyRevenue['total']) ? (float)$monthlyRevenue['total'] : 0;
    $stats['outstanding_balance'] = isset($invoices['outstanding']) ? (float)$invoices['outstanding'] : 0;
    $stats['average_invoice_value'] = $stats['paid_invoices'] > 0 
        ? round($stats['total_revenue'] / $stats['paid_invoices'], 2) 
        : 0;
} catch (Exception $e) {}

try {
    // Task Statistics
    $tasks = db()->fetch("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
        FROM project_tasks");
    
    $stats['total_tasks'] = isset($tasks['total']) ? (int)$tasks['total'] : 0;
    $stats['pending_tasks'] = isset($tasks['pending']) ? (int)$tasks['pending'] : 0;
    $stats['in_progress_tasks'] = isset($tasks['in_progress']) ? (int)$tasks['in_progress'] : 0;
    $stats['completed_tasks'] = isset($tasks['completed']) ? (int)$tasks['completed'] : 0;
    $stats['overdue_tasks'] = isset($tasks['overdue']) ? (int)$tasks['overdue'] : 0;
    $stats['task_completion_rate'] = $stats['total_tasks'] > 0 
        ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1) 
        : 0;
} catch (Exception $e) {}

try {
    // Communication Statistics
    $messages = db()->fetch("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
        FROM contact_messages");
    
    $stats['total_messages'] = isset($messages['total']) ? (int)$messages['total'] : 0;
    $stats['unread_messages'] = isset($messages['unread']) ? (int)$messages['unread'] : 0;
} catch (Exception $e) {}

try {
    // Content Statistics
    $posts = db()->fetch("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
        FROM blog_posts");
    
    $comments = db()->fetch("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending
        FROM blog_comments");
    
    $testimonials = db()->fetch("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM testimonials");
    
    $stats['total_blog_posts'] = isset($posts['total']) ? (int)$posts['total'] : 0;
    $stats['published_posts'] = isset($posts['published']) ? (int)$posts['published'] : 0;
    $stats['draft_posts'] = isset($posts['draft']) ? (int)$posts['draft'] : 0;
    $stats['total_comments'] = isset($comments['total']) ? (int)$comments['total'] : 0;
    $stats['pending_comments'] = isset($comments['pending']) ? (int)$comments['pending'] : 0;
    $stats['total_testimonials'] = isset($testimonials['total']) ? (int)$testimonials['total'] : 0;
    $stats['pending_testimonials'] = isset($testimonials['pending']) ? (int)$testimonials['pending'] : 0;
} catch (Exception $e) {}

// Get system information
try {
    $stats['mysql_version'] = db()->fetch("SELECT VERSION() as version")['version'] ?? 'Unknown';
    $stats['disk_total'] = disk_total_space(ROOT_PATH);
    $stats['disk_free'] = disk_free_space(ROOT_PATH);
    $stats['disk_usage'] = $stats['disk_total'] > 0 
        ? round((1 - $stats['disk_free'] / $stats['disk_total']) * 100, 1) 
        : 0;
    
    // Format disk sizes
    $stats['disk_total_formatted'] = formatBytes($stats['disk_total']);
    $stats['disk_free_formatted'] = formatBytes($stats['disk_free']);
} catch (Exception $e) {}

// Get recent projects
$recentProjects = [];
try {
    $recentProjects = db()->fetchAll("
        SELECT p.*, c.company_name 
        FROM projects p
        LEFT JOIN clients c ON p.client_id = c.id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
} catch (Exception $e) {}

// Get recent messages
$recentMessages = [];
try {
    $recentMessages = db()->fetchAll("
        SELECT * FROM contact_messages 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
} catch (Exception $e) {}

// Get upcoming tasks
$upcomingTasks = [];
try {
    $upcomingTasks = db()->fetchAll("
        SELECT t.*, p.title as project_title 
        FROM project_tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.status != 'completed' 
        AND t.due_date IS NOT NULL
        ORDER BY t.due_date ASC
        LIMIT 5
    ");
} catch (Exception $e) {}

// Get recent activity
$recentActivity = [];
try {
    $recentActivity = db()->fetchAll("
        SELECT * FROM project_activity_log 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
} catch (Exception $e) {}

// Get monthly revenue data for chart
$monthlyData = [];
try {
    $monthlyData = db()->fetchAll("
        SELECT 
            DATE_FORMAT(paid_at, '%Y-%m') as month,
            SUM(total) as revenue
        FROM project_invoices 
        WHERE status = 'paid' 
        AND paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
        ORDER BY month ASC
    ");
} catch (Exception $e) {}

// Get top clients by revenue
$topClients = [];
try {
    $topClients = db()->fetchAll("
        SELECT 
            c.company_name,
            SUM(i.total) as total_revenue,
            COUNT(i.id) as invoice_count
        FROM clients c
        JOIN project_invoices i ON c.id = i.client_id
        WHERE i.status = 'paid'
        GROUP BY c.id
        ORDER BY total_revenue DESC
        LIMIT 5
    ");
} catch (Exception $e) {}

// Get system alerts
$alerts = [];
try {
    $alerts = db()->fetchAll("
        SELECT * FROM dashboard_alerts 
        WHERE (is_global = 1 OR user_id = ?) 
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC
        LIMIT 5
    ", [$_SESSION['user_id']]);
} catch (Exception $e) {}

// Helper function to format bytes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Include header
require_once 'includes/header.php';
?>

<!-- Welcome Section with Date -->
<div class="welcome-section">
    <div>
        <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
        <p class="text-muted"><?php echo date('l, F j, Y'); ?> • <?php echo date('h:i A'); ?></p>
    </div>
    <div class="quick-actions-header">
        <a href="projects.php?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Project
        </a>
        <a href="invoices.php?action=add" class="btn btn-success">
            <i class="fas fa-file-invoice"></i> Create Invoice
        </a>
    </div>
</div>

<!-- System Alerts -->
<?php if (!empty($alerts)): ?>
<div class="alerts-container">
    <?php foreach ($alerts as $alert): ?>
    <div class="alert alert-<?php echo $alert['alert_type']; ?> alert-dismissible" data-alert-id="<?php echo $alert['id']; ?>">
        <i class="<?php echo $alert['icon'] ?: 'fas fa-info-circle'; ?>"></i>
        <div class="alert-content">
            <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
            <p><?php echo htmlspecialchars($alert['message']); ?></p>
        </div>
        <button class="alert-close" onclick="dismissAlert(<?php echo $alert['id']; ?>)">&times;</button>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Key Performance Indicators -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon revenue">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <div class="kpi-content">
            <span class="kpi-label">Monthly Revenue</span>
            <span class="kpi-value">$<?php echo number_format($stats['monthly_revenue']); ?></span>
            <span class="kpi-trend positive">
                <i class="fas fa-arrow-up"></i> +12.5%
            </span>
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-icon projects">
            <i class="fas fa-code-branch"></i>
        </div>
        <div class="kpi-content">
            <span class="kpi-label">Active Projects</span>
            <span class="kpi-value"><?php echo $stats['active_projects']; ?></span>
            <span class="kpi-sub">of <?php echo $stats['total_projects']; ?> total</span>
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-icon tasks">
            <i class="fas fa-tasks"></i>
        </div>
        <div class="kpi-content">
            <span class="kpi-label">Task Completion</span>
            <span class="kpi-value"><?php echo $stats['task_completion_rate']; ?>%</span>
            <span class="kpi-sub"><?php echo $stats['completed_tasks']; ?>/<?php echo $stats['total_tasks']; ?> tasks</span>
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-icon clients">
            <i class="fas fa-users"></i>
        </div>
        <div class="kpi-content">
            <span class="kpi-label">Client Retention</span>
            <span class="kpi-value"><?php echo $stats['client_retention_rate']; ?>%</span>
            <span class="kpi-sub"><?php echo $stats['new_clients_month']; ?> new this month</span>
        </div>
    </div>
</div>

<!-- Main Dashboard Grid -->
<div class="dashboard-grid">
    <!-- Left Column -->
    <div class="grid-column">
        <!-- Quick Stats Cards -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Quick Stats</h3>
                <a href="#" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <div class="stats-mini-grid">
                    <div class="stat-mini-item">
                        <span class="stat-mini-value"><?php echo $stats['total_projects']; ?></span>
                        <span class="stat-mini-label">Projects</span>
                    </div>
                    <div class="stat-mini-item">
                        <span class="stat-mini-value"><?php echo $stats['total_clients']; ?></span>
                        <span class="stat-mini-label">Clients</span>
                    </div>
                    <div class="stat-mini-item">
                        <span class="stat-mini-value">$<?php echo number_format($stats['average_invoice_value']); ?></span>
                        <span class="stat-mini-label">Avg Invoice</span>
                    </div>
                    <div class="stat-mini-item">
                        <span class="stat-mini-value"><?php echo $stats['published_posts']; ?></span>
                        <span class="stat-mini-label">Blog Posts</span>
                    </div>
                </div>
                
                <div class="progress-stats">
                    <div class="progress-item">
                        <div class="progress-label">
                            <span>Projects Completed</span>
                            <span><?php echo $stats['completed_projects']; ?>/<?php echo $stats['total_projects']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $stats['project_completion_rate']; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-item">
                        <div class="progress-label">
                            <span>Tasks Completed</span>
                            <span><?php echo $stats['completed_tasks']; ?>/<?php echo $stats['total_tasks']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $stats['task_completion_rate']; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-item">
                        <div class="progress-label">
                            <span>Invoices Paid</span>
                            <span><?php echo $stats['paid_invoices']; ?>/<?php echo $stats['total_invoices']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $stats['total_invoices'] > 0 ? round(($stats['paid_invoices'] / $stats['total_invoices']) * 100, 1) : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Projects -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-code-branch"></i> Recent Projects</h3>
                <a href="projects.php" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentProjects)): ?>
                    <p class="no-data">No projects yet</p>
                <?php else: ?>
                    <div class="project-list">
                        <?php foreach ($recentProjects as $project): ?>
                        <div class="project-item">
                            <div class="project-icon">
                                <i class="fas fa-code-branch"></i>
                            </div>
                            <div class="project-info">
                                <h4><?php echo htmlspecialchars($project['title']); ?></h4>
                                <p><?php echo htmlspecialchars($project['company_name'] ?? 'No client'); ?></p>
                            </div>
                            <span class="project-status status-<?php echo $project['status']; ?>">
                                <?php echo ucfirst($project['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upcoming Tasks -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Upcoming Tasks</h3>
                <a href="projects.php" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingTasks)): ?>
                    <p class="no-data">No upcoming tasks</p>
                <?php else: ?>
                    <div class="task-list">
                        <?php foreach ($upcomingTasks as $task): 
                            $daysUntil = ceil((strtotime($task['due_date']) - time()) / (60 * 60 * 24));
                            $dueClass = $daysUntil < 0 ? 'overdue' : ($daysUntil < 3 ? 'urgent' : 'normal');
                        ?>
                        <div class="task-item">
                            <div class="task-priority priority-<?php echo $task['priority']; ?>"></div>
                            <div class="task-info">
                                <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                                <p><?php echo htmlspecialchars($task['project_title']); ?></p>
                            </div>
                            <span class="task-due due-<?php echo $dueClass; ?>">
                                <?php echo $daysUntil < 0 ? 'Overdue' : ($daysUntil == 0 ? 'Today' : $daysUntil . ' days'); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Middle Column -->
    <div class="grid-column">
        <!-- Revenue Chart -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Revenue Overview</h3>
                <div class="card-actions">
                    <button class="btn-icon" onclick="refreshChart()"><i class="fas fa-sync-alt"></i></button>
                </div>
            </div>
            <div class="card-body chart-container">
                <canvas id="revenueChart"></canvas>
                <?php if (empty($monthlyData)): ?>
                <p class="no-data">No revenue data available</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Messages -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-envelope"></i> Recent Messages</h3>
                <a href="messages.php" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentMessages)): ?>
                    <p class="no-data">No messages</p>
                <?php else: ?>
                    <div class="message-list">
                        <?php foreach ($recentMessages as $message): ?>
                        <div class="message-item <?php echo !$message['is_read'] ? 'unread' : ''; ?>">
                            <div class="message-avatar">
                                <?php echo strtoupper(substr($message['name'], 0, 1)); ?>
                            </div>
                            <div class="message-content">
                                <h4><?php echo htmlspecialchars($message['name']); ?></h4>
                                <p><?php echo htmlspecialchars(substr($message['message'], 0, 60)); ?>...</p>
                                <span class="message-time"><?php echo timeAgo($message['created_at']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top Clients -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-trophy"></i> Top Clients</h3>
                <a href="clients.php" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($topClients)): ?>
                    <p class="no-data">No client data available</p>
                <?php else: ?>
                    <div class="client-rankings">
                        <?php foreach ($topClients as $index => $client): ?>
                        <div class="client-rank-item">
                            <span class="rank">#<?php echo $index + 1; ?></span>
                            <div class="client-info">
                                <h4><?php echo htmlspecialchars($client['company_name']); ?></h4>
                                <span class="client-stats"><?php echo $client['invoice_count']; ?> invoices</span>
                            </div>
                            <span class="client-revenue">$<?php echo number_format($client['total_revenue']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="grid-column">
        <!-- System Health -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-heartbeat"></i> System Health</h3>
            </div>
            <div class="card-body">
                <div class="health-grid">
                    <div class="health-item">
                        <span class="health-label">PHP Version</span>
                        <span class="health-value"><?php echo $stats['php_version']; ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">MySQL</span>
                        <span class="health-value"><?php echo $stats['mysql_version']; ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Server</span>
                        <span class="health-value"><?php echo substr($stats['server_software'], 0, 20); ?></span>
                    </div>
                    <div class="health-item">
                        <span class="health-label">Last Backup</span>
                        <span class="health-value"><?php echo $stats['last_backup']; ?></span>
                    </div>
                </div>
                
                <div class="disk-usage">
                    <h4>Disk Usage</h4>
                    <div class="disk-progress">
                        <div class="progress-bar">
                            <div class="progress-fill <?php echo $stats['disk_usage'] > 90 ? 'danger' : ($stats['disk_usage'] > 75 ? 'warning' : 'success'); ?>" 
                                 style="width: <?php echo $stats['disk_usage']; ?>%"></div>
                        </div>
                        <div class="disk-stats">
                            <span><?php echo $stats['disk_usage']; ?>% used</span>
                            <span><?php echo $stats['disk_free_formatted']; ?> free of <?php echo $stats['disk_total_formatted']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content Overview -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-newspaper"></i> Content Overview</h3>
            </div>
            <div class="card-body">
                <div class="content-stats">
                    <div class="content-stat-item">
                        <span class="stat-number"><?php echo $stats['published_posts']; ?></span>
                        <span class="stat-label">Published Posts</span>
                        <?php if ($stats['draft_posts'] > 0): ?>
                        <span class="stat-badge">+<?php echo $stats['draft_posts']; ?> drafts</span>
                        <?php endif; ?>
                    </div>
                    <div class="content-stat-item">
                        <span class="stat-number"><?php echo $stats['total_comments']; ?></span>
                        <span class="stat-label">Comments</span>
                        <?php if ($stats['pending_comments'] > 0): ?>
                        <span class="stat-badge warning"><?php echo $stats['pending_comments']; ?> pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="content-stat-item">
                        <span class="stat-number"><?php echo $stats['total_testimonials']; ?></span>
                        <span class="stat-label">Testimonials</span>
                        <?php if ($stats['pending_testimonials'] > 0): ?>
                        <span class="stat-badge warning"><?php echo $stats['pending_testimonials']; ?> pending</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <a href="#" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivity)): ?>
                    <p class="no-data">No recent activity</p>
                <?php else: ?>
                    <div class="activity-feed">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-circle"></i>
                            </div>
                            <div class="activity-content">
                                <p class="activity-text"><?php echo htmlspecialchars($activity['action']); ?></p>
                                <span class="activity-time"><?php echo timeAgo($activity['created_at']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Tips -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-lightbulb"></i> Quick Tips</h3>
            </div>
            <div class="card-body">
                <div class="tips-list">
                    <div class="tip-item">
                        <i class="fas fa-keyboard"></i>
                        <span>Press <kbd>?</kbd> for keyboard shortcuts</span>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-puzzle-piece"></i>
                        <span>Customize your dashboard in Widgets</span>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-file-export"></i>
                        <span>Export reports from Analytics</span>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-bell"></i>
                        <span>Enable notifications for updates</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Welcome Section */
.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    color: white;
}

.welcome-section h1 {
    color: white;
    margin-bottom: 5px;
}

.welcome-section .text-muted {
    color: rgba(255,255,255,0.8);
}

.quick-actions-header {
    display: flex;
    gap: 10px;
}

.quick-actions-header .btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.3s ease;
}

.quick-actions-header .btn-primary {
    background: white;
    color: #667eea;
}

.quick-actions-header .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.quick-actions-header .btn-success {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
}

.quick-actions-header .btn-success:hover {
    background: white;
    color: #10b981;
}

/* KPI Grid */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.kpi-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}

.kpi-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
}

.kpi-icon.revenue { background: rgba(16,185,129,0.1); color: #10b981; }
.kpi-icon.projects { background: rgba(37,99,235,0.1); color: #2563eb; }
.kpi-icon.tasks { background: rgba(245,158,11,0.1); color: #f59e0b; }
.kpi-icon.clients { background: rgba(124,58,237,0.1); color: #7c3aed; }

.kpi-content {
    flex: 1;
}

.kpi-label {
    display: block;
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-bottom: 5px;
}

.kpi-value {
    display: block;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
    margin-bottom: 3px;
}

.kpi-sub {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.kpi-trend {
    font-size: 0.8rem;
    font-weight: 500;
}

.kpi-trend.positive { color: #10b981; }
.kpi-trend.negative { color: #ef4444; }

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.grid-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Dashboard Cards */
.dashboard-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    overflow: hidden;
}

.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8fafc, #ffffff);
}

.card-header h3 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-header h3 i {
    color: var(--primary);
}

.card-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
}

.card-link:hover {
    text-decoration: underline;
}

.card-actions {
    display: flex;
    gap: 5px;
}

.card-body {
    padding: 20px;
}

/* Chart Container */
.chart-container {
    position: relative;
    height: 250px;
    width: 100%;
}

#revenueChart {
    display: block;
    width: 100% !important;
    height: 100% !important;
}

/* Stats Mini Grid */
.stats-mini-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-mini-item {
    text-align: center;
    padding: 10px;
    background: var(--gray-100);
    border-radius: 8px;
}

.stat-mini-value {
    display: block;
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
}

.stat-mini-label {
    font-size: 0.75rem;
    color: var(--gray-600);
    text-transform: uppercase;
}

/* Progress Items */
.progress-item {
    margin-bottom: 15px;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    margin-bottom: 5px;
    color: var(--gray-700);
}

.progress-bar {
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 3px;
    transition: width 0.3s ease;
}

.progress-fill.success { background: #10b981; }
.progress-fill.warning { background: #f59e0b; }
.progress-fill.danger { background: #ef4444; }

/* Project List */
.project-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.project-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.3s ease;
}

.project-item:hover {
    background: var(--gray-100);
}

.project-icon {
    width: 36px;
    height: 36px;
    background: var(--gray-200);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
}

.project-info {
    flex: 1;
}

.project-info h4 {
    font-size: 0.95rem;
    margin-bottom: 3px;
}

.project-info p {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin: 0;
}

.project-status {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-in_progress { background: rgba(37,99,235,0.1); color: #2563eb; }
.status-completed { background: rgba(16,185,129,0.1); color: #10b981; }
.status-planning { background: rgba(245,158,11,0.1); color: #f59e0b; }
.status-cancelled { background: rgba(239,68,68,0.1); color: #ef4444; }

/* Task List */
.task-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.task-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    background: var(--gray-100);
    border-radius: 8px;
}

.task-priority {
    width: 4px;
    height: 30px;
    border-radius: 2px;
}

.priority-urgent { background: #ef4444; }
.priority-high { background: #f59e0b; }
.priority-medium { background: #3b82f6; }
.priority-low { background: #10b981; }

.task-info {
    flex: 1;
}

.task-info h4 {
    font-size: 0.9rem;
    margin-bottom: 2px;
}

.task-info p {
    font-size: 0.75rem;
    color: var(--gray-600);
    margin: 0;
}

.task-due {
    font-size: 0.7rem;
    padding: 3px 8px;
    border-radius: 12px;
    font-weight: 600;
}

.due-overdue { background: rgba(239,68,68,0.1); color: #ef4444; }
.due-urgent { background: rgba(245,158,11,0.1); color: #f59e0b; }
.due-normal { background: rgba(37,99,235,0.1); color: #2563eb; }

/* Message List */
.message-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.message-item {
    display: flex;
    gap: 12px;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.3s ease;
}

.message-item:hover {
    background: var(--gray-100);
}

.message-item.unread {
    background: rgba(37,99,235,0.05);
}

.message-avatar {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    flex-shrink: 0;
}

.message-content {
    flex: 1;
}

.message-content h4 {
    font-size: 0.9rem;
    margin-bottom: 3px;
}

.message-content p {
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-bottom: 3px;
}

.message-time {
    font-size: 0.7rem;
    color: var(--gray-500);
}

/* Client Rankings */
.client-rankings {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.client-rank-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    background: var(--gray-100);
    border-radius: 8px;
}

.rank {
    width: 30px;
    height: 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.8rem;
}

.client-info {
    flex: 1;
}

.client-info h4 {
    font-size: 0.9rem;
    margin-bottom: 2px;
}

.client-stats {
    font-size: 0.7rem;
    color: var(--gray-600);
}

.client-revenue {
    font-weight: 600;
    color: #10b981;
}

/* Health Grid */
.health-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.health-item {
    padding: 10px;
    background: var(--gray-100);
    border-radius: 8px;
}

.health-label {
    display: block;
    font-size: 0.7rem;
    color: var(--gray-600);
    margin-bottom: 3px;
    text-transform: uppercase;
}

.health-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--dark);
}

/* Disk Usage */
.disk-usage h4 {
    font-size: 0.9rem;
    margin-bottom: 10px;
}

.disk-progress {
    margin-top: 10px;
}

.disk-stats {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--gray-600);
    margin-top: 5px;
}

/* Content Stats */
.content-stats {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.content-stat-item {
    text-align: center;
    padding: 10px;
    background: var(--gray-100);
    border-radius: 8px;
    position: relative;
}

.stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
}

.stat-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    padding: 3px 8px;
    background: var(--primary);
    color: white;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}

.stat-badge.warning {
    background: #f59e0b;
}

/* Activity Feed */
.activity-feed {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px;
    border-left: 2px solid var(--primary);
    background: var(--gray-100);
    border-radius: 0 8px 8px 0;
}

.activity-icon {
    color: var(--primary);
    font-size: 0.5rem;
    margin-top: 5px;
}

.activity-content {
    flex: 1;
}

.activity-text {
    font-size: 0.85rem;
    margin-bottom: 3px;
}

.activity-time {
    font-size: 0.7rem;
    color: var(--gray-600);
}

/* Tips List */
.tips-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.tip-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    background: var(--gray-100);
    border-radius: 8px;
    font-size: 0.85rem;
}

.tip-item i {
    color: var(--primary);
    width: 20px;
}

.tip-item kbd {
    background: var(--gray-300);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-family: monospace;
}

/* Alerts Container */
.alerts-container {
    margin-bottom: 20px;
}

.alert {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.alert-info {
    background: rgba(37,99,235,0.1);
    color: var(--primary);
    border: 1px solid rgba(37,99,235,0.2);
}

.alert-success {
    background: rgba(16,185,129,0.1);
    color: #10b981;
    border: 1px solid rgba(16,185,129,0.2);
}

.alert-warning {
    background: rgba(245,158,11,0.1);
    color: #f59e0b;
    border: 1px solid rgba(245,158,11,0.2);
}

.alert-danger {
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.2);
}

.alert i {
    font-size: 1.2rem;
}

.alert-content {
    flex: 1;
}

.alert-content strong {
    display: block;
    margin-bottom: 3px;
}

.alert-content p {
    margin: 0;
    font-size: 0.9rem;
}

.alert-close {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
}

.alert-close:hover {
    opacity: 1;
}

/* No Data */
.no-data {
    text-align: center;
    color: var(--gray-500);
    padding: 30px;
}

/* Responsive */
@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .welcome-section {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .grid-column {
        gap: 15px;
    }
    
    .health-grid {
        grid-template-columns: 1fr;
    }
    
    .chart-container {
        height: 200px;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('revenueChart')?.getContext('2d');
    if (ctx) {
        const monthlyData = <?php echo json_encode($monthlyData); ?>;
        
        // If no data, show placeholder
        if (monthlyData.length === 0) {
            ctx.font = '14px Arial';
            ctx.fillStyle = '#666';
            ctx.textAlign = 'center';
            ctx.fillText('No revenue data available', ctx.canvas.width/2, ctx.canvas.height/2);
        } else {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: monthlyData.map(d => {
                        // Format month labels nicely
                        const [year, month] = d.month.split('-');
                        return new Date(year, month-1).toLocaleString('default', { month: 'short' }) + ' ' + year;
                    }),
                    datasets: [{
                        label: 'Revenue',
                        data: monthlyData.map(d => parseFloat(d.revenue)),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 2,
                        pointRadius: 4,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: $' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                },
                                maxTicksLimit: 6
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                maxTicksLimit: 8
                            }
                        }
                    },
                    layout: {
                        padding: {
                            top: 10,
                            right: 10,
                            bottom: 10,
                            left: 10
                        }
                    },
                    elements: {
                        line: {
                            borderWidth: 2
                        },
                        point: {
                            radius: 3,
                            hoverRadius: 5
                        }
                    }
                }
            });
        }
    }
});

function dismissAlert(alertId) {
    const alert = document.querySelector(`[data-alert-id="${alertId}"]`);
    if (alert) {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }
}

function refreshChart() {
    location.reload();
}

// Time ago function
function timeAgo(date) {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);
    
    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60
    };
    
    for (let [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit);
        if (interval >= 1) {
            return interval + ' ' + unit + (interval === 1 ? '' : 's') + ' ago';
        }
    }
    
    return 'just now';
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>