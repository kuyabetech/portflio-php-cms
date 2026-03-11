<?php
/**
 * Client Dashboard - Complete Overview
 * Shows projects, invoices, messages, notifications, and quick actions
 */

require_once dirname(__DIR__) . '/includes/init.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if client is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$clientId = $_SESSION['client_id'];
$clientUserId = $_SESSION['client_user_id'] ?? $clientId;

// Get client user information
$clientUser = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientUserId]);

if (!$clientUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get client company information
$clientCompany = null;
if (!empty($clientUser['client_id'])) {
    $clientCompany = db()->fetch("SELECT * FROM clients WHERE id = ?", [$clientUser['client_id']]);
}

// Log session for security
logClientSession($clientUserId);

// Initialize stats
$stats = [
    'total_projects' => 0,
    'completed_projects' => 0,
    'total_invoices' => 0,
    'paid_invoices' => 0,
    'pending_invoices' => 0,
    'overdue_invoices' => 0,
    'total_spent' => 0,
    'outstanding_balance' => 0,
    'unread_messages' => 0,
    'unread_notifications' => 0
];

// Get client's company ID for queries
$companyId = $clientUser['client_id'] ?? 0;

// Projects stats - using client_id from clients table (company)
if ($companyId > 0) {
    try {
        $projectStats = db()->fetch("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM projects 
            WHERE client_id = ?
        ", [$companyId]);
        
        $stats['total_projects'] = (int)($projectStats['total'] ?? 0);
        $stats['completed_projects'] = (int)($projectStats['completed'] ?? 0);
    } catch (Exception $e) {
        error_log("Projects stats error: " . $e->getMessage());
    }
}

// Invoices stats
if ($companyId > 0) {
    try {
        $invoiceStats = db()->fetch("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END), 0) as total_paid,
                COALESCE(SUM(CASE WHEN status != 'paid' AND status != 'cancelled' THEN (total - COALESCE(paid_amount, 0)) ELSE 0 END), 0) as outstanding
            FROM project_invoices 
            WHERE client_id = ?
        ", [$companyId]);
        
        $stats['total_invoices'] = (int)($invoiceStats['total'] ?? 0);
        $stats['paid_invoices'] = (int)($invoiceStats['paid'] ?? 0);
        $stats['pending_invoices'] = (int)($invoiceStats['pending'] ?? 0);
        $stats['overdue_invoices'] = (int)($invoiceStats['overdue'] ?? 0);
        $stats['total_spent'] = (float)($invoiceStats['total_paid'] ?? 0);
        $stats['outstanding_balance'] = (float)($invoiceStats['outstanding'] ?? 0);
    } catch (Exception $e) {
        error_log("Invoices stats error: " . $e->getMessage());
    }
}

// Messages stats
try {
    $stats['unread_messages'] = (int)(db()->fetch("
        SELECT COUNT(*) as count FROM client_messages 
        WHERE client_id = ? AND sender = 'admin' AND status = 'unread'
    ", [$clientUserId])['count'] ?? 0);
} catch (Exception $e) {
    error_log("Messages stats error: " . $e->getMessage());
}

// Notifications stats
try {
    $stats['unread_notifications'] = (int)(db()->fetch("
        SELECT COUNT(*) as count FROM client_notifications 
        WHERE client_id = ? AND is_read = 0
    ", [$clientUserId])['count'] ?? 0);
} catch (Exception $e) {
    error_log("Notifications stats error: " . $e->getMessage());
}

// Get recent projects
$recentProjects = [];
if ($companyId > 0) {
    try {
        $recentProjects = db()->fetchAll("
            SELECT * FROM projects 
            WHERE client_id = ? 
            ORDER BY 
                CASE 
                    WHEN status = 'in_progress' THEN 1
                    WHEN status = 'planning' THEN 2
                    WHEN status = 'completed' THEN 3
                    ELSE 4
                END,
                created_at DESC 
            LIMIT 5
        ", [$companyId]) ?? [];
    } catch (Exception $e) {
        error_log("Recent projects error: " . $e->getMessage());
    }
}

// Get recent invoices
$recentInvoices = [];
if ($companyId > 0) {
    try {
        $recentInvoices = db()->fetchAll("
            SELECT * FROM project_invoices 
            WHERE client_id = ? 
            ORDER BY 
                CASE 
                    WHEN status = 'pending' AND due_date < NOW() THEN 1
                    WHEN status = 'pending' THEN 2
                    WHEN status = 'paid' THEN 3
                    ELSE 4
                END,
                due_date ASC 
            LIMIT 5
        ", [$companyId]) ?? [];
    } catch (Exception $e) {
        error_log("Recent invoices error: " . $e->getMessage());
    }
}

// Get recent messages
$recentMessages = [];
try {
    $recentMessages = db()->fetchAll("
        SELECT * FROM client_messages 
        WHERE client_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ", [$clientUserId]) ?? [];
} catch (Exception $e) {
    error_log("Recent messages error: " . $e->getMessage());
}

// Get recent notifications
$recentNotifications = [];
try {
    $recentNotifications = db()->fetchAll("
        SELECT * FROM client_notifications 
        WHERE client_id = ? 
        ORDER BY 
            is_read ASC,
            created_at DESC 
        LIMIT 5
    ", [$clientUserId]) ?? [];
} catch (Exception $e) {
    error_log("Recent notifications error: " . $e->getMessage());
}

// Get upcoming deadlines (invoices due in next 7 days)
$upcomingDeadlines = [];
if ($companyId > 0) {
    try {
        $upcomingDeadlines = db()->fetchAll("
            SELECT * FROM project_invoices 
            WHERE client_id = ? 
                AND status = 'pending' 
                AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY due_date ASC
        ", [$companyId]) ?? [];
    } catch (Exception $e) {
        error_log("Upcoming deadlines error: " . $e->getMessage());
    }
}

$pageTitle = 'Dashboard';
require_once "../includes/client-header.php"
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        /* Header */
        .client-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo h1 {
            font-size: 24px;
            color: #1e293b;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notification-icon {
            position: relative;
            cursor: pointer;
        }
        
        .notification-icon i {
            font-size: 20px;
            color: #64748b;
        }
        
        .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: background 0.2s ease;
        }
        
        .user-menu:hover {
            background: #f1f5f9;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .user-name {
            font-weight: 500;
            color: #1e293b;
        }
        
        .logout-btn {
            background: #fee2e2;
            color: #991b1b;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            margin-left: 10px;
            transition: all 0.2s ease;
        }
        
        .logout-btn:hover {
            background: #fecaca;
        }
        
        /* Main Content */
        .dashboard-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .welcome-content h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .welcome-content p {
            opacity: 0.9;
        }
        
        .date-badge {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeIn 0.5s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.blue { background: rgba(102,126,234,0.1); color: #667eea; }
        .stat-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
        .stat-icon.orange { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .stat-icon.red { background: rgba(239,68,68,0.1); color: #ef4444; }
        .stat-icon.purple { background: rgba(124,58,237,0.1); color: #7c3aed; }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            display: block;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 13px;
            color: #64748b;
            display: block;
            margin-bottom: 3px;
        }
        
        .stat-sub {
            font-size: 11px;
            color: #94a3b8;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
        }
        
        /* Cards */
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            animation: fadeIn 0.5s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .card-header h3 {
            font-size: 16px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-header h3 i {
            color: #667eea;
        }
        
        .view-all {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .view-all:hover {
            color: #764ba2;
        }
        
        /* Project List */
        .project-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .project-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        
        .project-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }
        
        .project-info h4 {
            font-size: 15px;
            margin-bottom: 5px;
        }
        
        .project-info h4 a {
            color: #1e293b;
            text-decoration: none;
            font-weight: 600;
        }
        
        .project-info h4 a:hover {
            color: #667eea;
        }
        
        .project-meta {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-planning { background: #fef3c7; color: #92400e; }
        .status-in_progress { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        
        /* Invoice List */
        .invoice-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .invoice-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        
        .invoice-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }
        
        .invoice-info h4 {
            font-size: 15px;
            margin-bottom: 5px;
        }
        
        .invoice-info h4 a {
            color: #1e293b;
            text-decoration: none;
            font-weight: 600;
        }
        
        .invoice-info h4 a:hover {
            color: #667eea;
        }
        
        .invoice-meta {
            font-size: 12px;
            color: #64748b;
        }
        
        .invoice-amount {
            text-align: right;
        }
        
        .amount {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            display: block;
            margin-bottom: 5px;
        }
        
        .invoice-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        
        /* Message List */
        .message-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .message-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .message-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }
        
        .message-item.unread {
            background: #eff6ff;
            border-left: 3px solid #667eea;
        }
        
        .message-icon {
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
        }
        
        .message-content {
            flex: 1;
        }
        
        .message-sender {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            display: block;
            margin-bottom: 3px;
        }
        
        .message-preview {
            font-size: 12px;
            color: #64748b;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
        
        .message-time {
            font-size: 11px;
            color: #94a3b8;
            white-space: nowrap;
        }
        
        /* Notification List */
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .notification-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .notification-item:hover {
            background: #f1f5f9;
        }
        
        .notification-item.unread {
            background: #eff6ff;
        }
        
        .notification-item i {
            width: 30px;
            height: 30px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 14px;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-content p {
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 3px;
        }
        
        .notification-time {
            font-size: 11px;
            color: #94a3b8;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            text-decoration: none;
            color: #1e293b;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .action-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .action-btn i {
            font-size: 20px;
            color: #667eea;
        }
        
        .action-btn:hover i {
            color: white;
        }
        
        .action-btn span {
            font-size: 12px;
            font-weight: 500;
        }
        
        .action-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
        }
        
        /* Upcoming Deadlines */
        .deadline-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .deadline-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 13px;
        }
        
        .deadline-item.urgent {
            background: #fee2e2;
        }
        
        .deadline-info a {
            color: #1e293b;
            text-decoration: none;
            font-weight: 500;
        }
        
        .deadline-info a:hover {
            color: #667eea;
        }
        
        .deadline-date {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #64748b;
            font-size: 11px;
        }
        
        .deadline-amount {
            font-weight: 600;
            color: #1e293b;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: #f8fafc;
            border-radius: 10px;
        }
        
        .empty-state i {
            font-size: 40px;
            color: #cbd5e1;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #94a3b8;
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .welcome-banner {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .project-item,
            .invoice-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .invoice-amount {
                text-align: left;
                width: 100%;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .amount {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>


    <main class="dashboard-container">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h1>Welcome back, <?php echo htmlspecialchars($clientUser['first_name'] ?: 'Client'); ?>!</h1>
                <p><?php echo htmlspecialchars($clientCompany['company_name'] ?? 'Your Account'); ?></p>
            </div>
            <div class="date-badge">
                <i class="far fa-calendar"></i>
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['total_projects']; ?></span>
                    <span class="stat-label">Total Projects</span>
                    <span class="stat-sub"><?php echo $stats['completed_projects']; ?> completed</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?php echo $stats['total_invoices']; ?></span>
                    <span class="stat-label">Total Invoices</span>
                    <span class="stat-sub"><?php echo $stats['paid_invoices']; ?> paid</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-value">$<?php echo number_format($stats['total_spent'], 2); ?></span>
                    <span class="stat-label">Total Spent</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-value">$<?php echo number_format($stats['outstanding_balance'], 2); ?></span>
                    <span class="stat-label">Outstanding</span>
                    <span class="stat-sub"><?php echo $stats['overdue_invoices']; ?> overdue</span>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="dashboard-grid">
            <!-- Left Column -->
            <div class="left-column">
                <!-- Recent Projects -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-project-diagram"></i> Recent Projects</h3>
                        <a href="projects.php" class="view-all">View All →</a>
                    </div>
                    
                    <?php if (!empty($recentProjects)): ?>
                    <div class="project-list">
                        <?php foreach ($recentProjects as $project): ?>
                        <div class="project-item">
                            <div class="project-info">
                                <h4>
                                    <a href="project-details.php?id=<?php echo $project['id']; ?>">
                                        <?php echo htmlspecialchars($project['title']); ?>
                                    </a>
                                </h4>
                                <span class="project-meta">
                                    <i class="far fa-calendar"></i>
                                    Started: <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
                                </span>
                            </div>
                            <span class="status-badge status-<?php echo $project['status']; ?>">
                                <?php 
                                $labels = ['planning' => 'Planning', 'in_progress' => 'In Progress', 'completed' => 'Completed'];
                                echo $labels[$project['status']] ?? ucfirst($project['status']); 
                                ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No projects yet</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Invoices -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-invoice"></i> Recent Invoices</h3>
                        <a href="invoices.php" class="view-all">View All →</a>
                    </div>
                    
                    <?php if (!empty($recentInvoices)): ?>
                    <div class="invoice-list">
                        <?php foreach ($recentInvoices as $invoice): 
                            $isOverdue = $invoice['status'] === 'pending' && strtotime($invoice['due_date']) < time();
                            $displayStatus = $isOverdue ? 'overdue' : $invoice['status'];
                        ?>
                        <div class="invoice-item">
                            <div class="invoice-info">
                                <h4>
                                    <a href="invoice-details.php?id=<?php echo $invoice['id']; ?>">
                                        Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                    </a>
                                </h4>
                                <span class="invoice-meta">
                                    <i class="far fa-calendar"></i>
                                    Due: <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                                </span>
                            </div>
                            <div class="invoice-amount">
                                <span class="amount">$<?php echo number_format($invoice['total'], 2); ?></span>
                                <span class="invoice-status status-<?php echo $displayStatus; ?>">
                                    <?php echo ucfirst($displayStatus); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <p>No invoices yet</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Messages -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-envelope"></i> Recent Messages</h3>
                        <a href="messages.php" class="view-all">View All →</a>
                    </div>
                    
                    <?php if (!empty($recentMessages)): ?>
                    <div class="message-list">
                        <?php foreach ($recentMessages as $msg): ?>
                        <div class="message-item <?php echo ($msg['sender'] === 'admin' && $msg['status'] === 'unread') ? 'unread' : ''; ?>" 
                             onclick="location.href='message-detail.php?id=<?php echo $msg['id']; ?>'">
                            <div class="message-icon">
                                <i class="fas <?php echo $msg['sender'] === 'admin' ? 'fa-user-tie' : 'fa-user'; ?>"></i>
                            </div>
                            <div class="message-content">
                                <span class="message-sender">
                                    <?php echo $msg['sender'] === 'admin' ? 'Support Team' : 'You'; ?>
                                </span>
                                <span class="message-preview">
                                    <?php echo htmlspecialchars(substr($msg['message'], 0, 60)); ?>...
                                </span>
                            </div>
                            <span class="message-time">
                                <?php echo timeAgo($msg['created_at']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-envelope-open"></i>
                        <p>No messages yet</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="right-column">
                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    </div>
                    
                    <div class="quick-actions">
                        <a href="support.php" class="action-btn">
                            <i class="fas fa-headset"></i>
                            <span>Get Support</span>
                        </a>
                        
                        <a href="messages.php" class="action-btn">
                            <i class="fas fa-envelope"></i>
                            <span>Send Message</span>
                            <?php if ($stats['unread_messages'] > 0): ?>
                            <span class="action-badge"><?php echo $stats['unread_messages']; ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <a href="documents.php" class="action-btn">
                            <i class="fas fa-file-alt"></i>
                            <span>Documents</span>
                        </a>
                        
                        <a href="profile.php" class="action-btn">
                            <i class="fas fa-user-cog"></i>
                            <span>Profile</span>
                        </a>
                    </div>
                </div>

                <!-- Upcoming Deadlines -->
                <?php if (!empty($upcomingDeadlines)): ?>
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Upcoming Deadlines</h3>
                    </div>
                    
                    <div class="deadline-list">
                        <?php foreach ($upcomingDeadlines as $deadline): 
                            $daysLeft = ceil((strtotime($deadline['due_date']) - time()) / 86400);
                            $isUrgent = $daysLeft <= 3;
                        ?>
                        <div class="deadline-item <?php echo $isUrgent ? 'urgent' : ''; ?>">
                            <div class="deadline-info">
                                <a href="invoice-details.php?id=<?php echo $deadline['id']; ?>">
                                    Invoice #<?php echo htmlspecialchars($deadline['invoice_number']); ?>
                                </a>
                                <div class="deadline-date">
                                    <i class="far fa-calendar"></i>
                                    Due in <?php echo $daysLeft; ?> days
                                </div>
                            </div>
                            <span class="deadline-amount">
                                $<?php echo number_format($deadline['total'], 2); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Notifications -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bell"></i> Recent Notifications</h3>
                        <a href="notifications.php" class="view-all">View All →</a>
                    </div>
                    
                    <?php if (!empty($recentNotifications)): ?>
                    <div class="notification-list">
                        <?php foreach ($recentNotifications as $note): ?>
                        <div class="notification-item <?php echo !$note['is_read'] ? 'unread' : ''; ?>">
                            <i class="fas <?php echo $note['icon'] ?? 'fa-info-circle'; ?>"></i>
                            <div class="notification-content">
                                <p><?php echo htmlspecialchars($note['message']); ?></p>
                                <span class="notification-time">
                                    <?php echo timeAgo($note['created_at']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Account Summary -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user"></i> Account Summary</h3>
                    </div>
                    
                    <div style="padding: 10px 0;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding: 5px 0; border-bottom: 1px dashed #e2e8f0;">
                            <span style="color: #64748b;">Member since:</span>
                            <span style="font-weight: 500; color: #1e293b;">
                                <?php echo date('M d, Y', strtotime($clientUser['created_at'] ?? 'now')); ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding: 5px 0; border-bottom: 1px dashed #e2e8f0;">
                            <span style="color: #64748b;">Last login:</span>
                            <span style="font-weight: 500; color: #1e293b;">
                                <?php echo $clientUser['last_login'] ? timeAgo($clientUser['last_login']) : 'First visit'; ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #64748b;">Account status:</span>
                            <span style="color: #10b981; font-weight: 600;">Active</span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <a href="profile.php" style="color: #667eea; text-decoration: none; font-size: 13px;">
                            Manage Profile <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Auto-refresh session (optional)
        setTimeout(function() {
            location.reload();
        }, 30 * 60 * 1000); // Refresh every 30 minutes

        // Mark notifications as read when clicked
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                // You can add AJAX call here to mark as read
                this.classList.remove('unread');
            });
        });
    </script>
</body>
</html>
<?php
require_once "../includes/client-footer.php"

?>