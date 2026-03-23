<?php
// admin/includes/header.php
// Admin Panel Header with Navigation and Dashboard Integration

// Get current user
$currentUser = Auth::user();

// Get unread counts for notifications with error handling
try {
    $unreadMessages = db()->fetch("SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0")['count'] ?? 0;
    $pendingTestimonials = db()->fetch("SELECT COUNT(*) as count FROM testimonials WHERE status = 'pending'")['count'] ?? 0;
    $pendingComments = db()->fetch("SELECT COUNT(*) as count FROM blog_comments WHERE is_approved = 0")['count'] ?? 0;
    $draftPosts = db()->fetch("SELECT COUNT(*) as count FROM blog_posts WHERE status = 'draft'")['count'] ?? 0;
    $pendingTasks = db()->fetch("SELECT COUNT(*) as count FROM project_tasks WHERE status = 'pending' AND assigned_to = ?", [$_SESSION['user_id'] ?? 0])['count'] ?? 0;
    $overdueInvoices = db()->fetch("SELECT COUNT(*) as count FROM project_invoices WHERE status = 'overdue'")['count'] ?? 0;
} catch (Exception $e) {
    error_log("Header notification error: " . $e->getMessage());
    $unreadMessages = 0;
    $pendingTestimonials = 0;
    $pendingComments = 0;
    $draftPosts = 0;
    $pendingTasks = 0;
    $overdueInvoices = 0;
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDirectory = basename(dirname($_SERVER['PHP_SELF']));

// Get quick stats for dropdown with error handling
try {
    $quickStats = [
        'projects' => db()->fetch("SELECT COUNT(*) as count FROM projects")['count'] ?? 0,
        'clients' => db()->fetch("SELECT COUNT(*) as count FROM clients")['count'] ?? 0,
        'revenue' => db()->fetch("SELECT SUM(total) as total FROM project_invoices WHERE status = 'paid'")['total'] ?? 0,
        'tasks' => db()->fetch("SELECT COUNT(*) as count FROM project_tasks WHERE status != 'completed'")['count'] ?? 0
    ];
} catch (Exception $e) {
    error_log("Header stats error: " . $e->getMessage());
    $quickStats = [
        'projects' => 0,
        'clients' => 0,
        'revenue' => 0,
        'tasks' => 0
    ];
}

// Helper function to check if a nav item is active
function isActive($pages, $currentPage) {
    if (is_array($pages)) {
        return in_array($currentPage, $pages) ? 'active' : '';
    }
    return $pages === $currentPage ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Admin Panel - <?php echo defined('SITE_NAME') ? SITE_NAME : 'Admin'; ?></title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/assets/images/favicon.ico">
    
    <style>
        /* ========================================
           RESET AND BASE STYLES
           ======================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            background: #f1f5f9;
        }

        /* ========================================
           ROOT VARIABLES
           ======================================== */
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #7c3aed;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e293b;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --header-height: 70px;
            --transition-speed: 0.3s;
        }

        /* ========================================
           ADMIN WRAPPER
           ======================================== */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            background: var(--gray-100);
            position: relative;
        }

        /* ========================================
           SIDEBAR STYLES
           ======================================== */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #1a1f2e 0%, #151a28 100%);
            color: #fff;
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        /* Sidebar Scrollbar */
        .admin-sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .admin-sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }

        .admin-sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Sidebar Collapsed State (Desktop) */
        .admin-sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .admin-sidebar.collapsed .sidebar-brand .brand-text,
        .admin-sidebar.collapsed .sidebar-user .user-info,
        .admin-sidebar.collapsed .sidebar-user .user-edit,
        .admin-sidebar.collapsed .nav-item span:not(.nav-badge),
        .admin-sidebar.collapsed .sidebar-footer span,
        .admin-sidebar.collapsed .nav-divider,
        .admin-sidebar.collapsed .sidebar-quick-stats .stat-info {
            display: none;
        }

        .admin-sidebar.collapsed .sidebar-brand {
            justify-content: center;
            padding: 20px 0;
        }

        .admin-sidebar.collapsed .sidebar-logo {
            width: 40px;
            height: 40px;
            margin: 0;
        }

        .admin-sidebar.collapsed .sidebar-user {
            padding: 15px 0;
            justify-content: center;
        }

        .admin-sidebar.collapsed .user-avatar-wrapper {
            margin: 0;
        }

        .admin-sidebar.collapsed .user-avatar {
            width: 45px;
            height: 45px;
        }

        .admin-sidebar.collapsed .nav-item a {
            justify-content: center;
            padding: 15px 0;
        }

        .admin-sidebar.collapsed .nav-item i {
            margin: 0;
            font-size: 1.3rem;
        }

        .admin-sidebar.collapsed .nav-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            transform: scale(0.8);
        }

        .admin-sidebar.collapsed .sidebar-quick-stats .stat-item {
            justify-content: center;
            padding: 10px 0;
        }

        .admin-sidebar.collapsed .sidebar-quick-stats .stat-item i {
            margin: 0;
            font-size: 1.2rem;
        }

        /* Sidebar Header */
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .sidebar-logo {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            object-fit: cover;
        }

        .brand-text h2 {
            font-size: 1.2rem;
            margin: 0;
            color: white;
            font-weight: 600;
            white-space: nowrap;
        }

        .brand-text p {
            font-size: 0.7rem;
            margin: 2px 0 0;
            color: var(--gray-400);
            opacity: 0.9;
            white-space: nowrap;
        }

        /* Sidebar User */
        .sidebar-user {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .user-avatar-wrapper {
            position: relative;
            flex-shrink: 0;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--primary);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
        }

        .user-status {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            border: 2px solid #1a1f2e;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-info h4 {
            font-size: 0.95rem;
            margin: 0 0 3px;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-info p {
            font-size: 0.75rem;
            margin: 0;
            color: var(--gray-400);
            text-transform: capitalize;
        }

        .user-edit {
            color: var(--gray-400);
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .user-edit:hover {
            color: var(--primary);
            transform: scale(1.1);
        }

        /* Sidebar Quick Stats */
        .sidebar-quick-stats {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .sidebar-quick-stats .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .sidebar-quick-stats .stat-item i {
            font-size: 1rem;
            color: var(--primary-light);
            margin-bottom: 5px;
        }

        .sidebar-quick-stats .stat-info {
            display: flex;
            flex-direction: column;
        }

        .sidebar-quick-stats .stat-value {
            font-size: 1rem;
            font-weight: 600;
            color: white;
        }

        .sidebar-quick-stats .stat-label {
            font-size: 0.65rem;
            color: var(--gray-400);
            text-transform: uppercase;
        }

        /* Sidebar Navigation */
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 20px 0;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-divider {
            padding: 15px 20px 5px;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--gray-500);
            letter-spacing: 0.5px;
        }

        .nav-item {
            position: relative;
            margin: 2px 0;
        }

        .nav-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--gray-300);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            border-left: 3px solid transparent;
        }

        .nav-item a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--primary);
        }

        .nav-item.active > a {
            background: rgba(37,99,235,0.15);
            color: white;
            border-left-color: var(--primary);
        }

        .nav-item i {
            width: 20px;
            font-size: 1.1rem;
            color: var(--gray-400);
            flex-shrink: 0;
        }

        .nav-item.active i {
            color: var(--primary);
        }

        .nav-item span {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-badge {
            margin-left: auto;
            background: var(--primary);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .nav-badge.warning {
            background: var(--warning);
        }

        .nav-badge.danger {
            background: var(--danger);
        }

        /* Submenu */
        .nav-item.has-submenu {
            position: relative;
        }

        .submenu-toggle {
            cursor: pointer;
        }

        .submenu-arrow {
            margin-left: auto;
            transition: transform 0.3s ease;
            font-size: 0.8rem !important;
            width: auto !important;
        }

        .nav-item.has-submenu.active .submenu-arrow {
            transform: rotate(90deg);
        }

        .submenu {
            list-style: none;
            padding: 0;
            margin: 0;
            background: rgba(0,0,0,0.2);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .submenu.show {
            max-height: 500px;
        }

        .submenu li a {
            padding: 10px 20px 10px 52px;
            font-size: 0.9rem;
            color: var(--gray-400);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .submenu li a:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .submenu li a i {
            width: 18px;
            font-size: 0.9rem;
        }

        .submenu .nav-badge {
            margin-left: auto;
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
        }

        .footer-link {
            color: var(--gray-400);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .footer-link:hover {
            color: white;
        }

        .footer-link.logout:hover {
            color: var(--danger);
        }

        /* ========================================
           MAIN CONTENT AREA
           ======================================== */
        .admin-main {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .admin-main.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* ========================================
           TOP HEADER
           ======================================== */
        .admin-top-header {
            background: white;
            padding: 0 25px;
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: var(--gray-700);
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        /* Header Search */
        .header-search {
            position: relative;
        }

        .header-search input {
            padding: 10px 15px 10px 45px;
            border: 2px solid var(--gray-200);
            border-radius: 30px;
            font-size: 0.95rem;
            width: 300px;
            transition: all 0.3s ease;
            background: var(--gray-50);
        }

        .header-search input:focus {
            outline: none;
            border-color: var(--primary);
            width: 350px;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .header-search i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 1.1rem;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-top: 10px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        /* Header Right */
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: none;
            background: var(--gray-100);
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Quick Stats Dropdown */
        .quick-stats-wrapper {
            position: relative;
        }

        .quick-stats-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-top: 10px;
            display: none;
            z-index: 1000;
            padding: 20px;
        }

        .quick-stats-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-mini-card {
            background: var(--gray-100);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-mini-value {
            display: block;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
        }

        .stat-mini-label {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
        }

        .revenue-preview {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
        }

        .revenue-preview .label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .revenue-preview .value {
            display: block;
            font-size: 1.6rem;
            font-weight: 700;
        }

        .task-progress-mini {
            margin-top: 15px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            margin-bottom: 5px;
            color: var(--gray-700);
        }

        .progress-bar-mini {
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-fill-mini {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        .view-full-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
        }

        .view-full-link:hover {
            text-decoration: underline;
        }

        /* Notifications */
        .notifications-dropdown {
            position: relative;
        }

        .notification-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: none;
            background: var(--gray-100);
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .notification-btn:hover {
            background: var(--primary);
            color: white;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            animation: pulse 2s infinite;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 350px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-top: 10px;
            display: none;
            z-index: 1000;
        }

        .notification-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .notification-header h3 {
            margin: 0;
            font-size: 1rem;
            color: var(--gray-800);
        }

        .notification-header a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            text-decoration: none;
            color: var(--gray-700);
            transition: background 0.3s ease;
            border-bottom: 1px solid var(--gray-100);
        }

        .notification-item:hover {
            background: var(--gray-50);
        }

        .notification-item.unread {
            background: rgba(37,99,235,0.05);
        }

        .notification-item i {
            width: 35px;
            height: 35px;
            background: var(--gray-200);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-content p {
            margin: 0 0 3px;
            font-size: 0.9rem;
        }

        .notification-content small {
            font-size: 0.7rem;
            color: var(--gray-500);
        }

        .notification-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--gray-200);
            text-align: center;
        }

        .notification-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
        }

        .no-notifications {
            text-align: center;
            color: var(--gray-500);
            padding: 20px;
            margin: 0;
        }

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-menu-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 15px 5px 5px;
            background: var(--gray-100);
            border: none;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 50px;
        }

        .user-menu-btn:hover {
            background: var(--gray-200);
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--primary);
            flex-shrink: 0;
        }

        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder-small {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            color: white;
        }

        .user-name {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--gray-700);
            white-space: nowrap;
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 250px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-top: 10px;
            display: none;
            z-index: 1000;
        }

        .user-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        .dropdown-user-info {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid var(--gray-200);
        }

        .dropdown-user-info strong {
            display: block;
            margin-bottom: 3px;
            color: var(--gray-800);
        }

        .dropdown-user-info span {
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--gray-200);
            margin: 5px 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            text-decoration: none;
            color: var(--gray-700);
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: var(--gray-100);
            color: var(--primary);
        }

        .dropdown-item i {
            width: 20px;
            color: var(--gray-500);
        }

        .dropdown-item.logout:hover {
            color: var(--danger);
        }

        /* ========================================
           MOBILE SIDEBAR OVERLAY
           ======================================== */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ========================================
           BREADCRUMBS
           ======================================== */
        .breadcrumbs {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
            padding: 12px 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .breadcrumb-home {
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .breadcrumb-home:hover {
            text-decoration: underline;
        }

        .breadcrumbs i {
            color: var(--gray-400);
            font-size: 0.8rem;
        }

        .breadcrumbs a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .breadcrumbs a:hover {
            text-decoration: underline;
        }

        .breadcrumbs span {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        /* ========================================
           ADMIN CONTENT
           ======================================== */
        .admin-content-wrapper {
            flex: 1;
            padding: 25px;
        }

        .admin-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            min-height: calc(100vh - var(--header-height) - 90px);
        }

        /* ========================================
           LOADING OVERLAY
           ======================================== */
        .admin-loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* ========================================
           FLASH MESSAGES
           ======================================== */
        .flash-message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }

        .flash-message.success {
            background: rgba(16,185,129,0.1);
            color: #10b981;
            border: 1px solid rgba(16,185,129,0.2);
        }

        .flash-message.error {
            background: rgba(239,68,68,0.1);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.2);
        }

        .flash-message.warning {
            background: rgba(245,158,11,0.1);
            color: #f59e0b;
            border: 1px solid rgba(245,158,11,0.2);
        }

        .flash-message.info {
            background: rgba(37,99,235,0.1);
            color: #2563eb;
            border: 1px solid rgba(37,99,235,0.2);
        }

        /* ========================================
           ANIMATIONS
           ======================================== */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* ========================================
           RESPONSIVE STYLES
           ======================================== */

        /* Large Desktop (1400px and above) */
        @media (min-width: 1400px) {
            :root {
                --sidebar-width: 320px;
            }
            
            .sidebar-quick-stats .stat-value {
                font-size: 1.1rem;
            }
            
            .nav-item a {
                padding: 14px 25px;
            }
        }

        /* Desktop (1200px to 1399px) */
        @media (min-width: 1200px) and (max-width: 1399px) {
            :root {
                --sidebar-width: 280px;
            }
        }

        /* Small Desktop / Large Tablet (992px to 1199px) */
        @media (min-width: 992px) and (max-width: 1199px) {
            :root {
                --sidebar-width: 250px;
            }
            
            .header-search input {
                width: 250px;
            }
            
            .header-search input:focus {
                width: 300px;
            }
        }

        /* Tablet (768px to 991px) */
        @media (min-width: 768px) and (max-width: 991px) {
            :root {
                --sidebar-width: 220px;
            }
            
            .header-search {
                display: none;
            }
            
            .quick-actions {
                gap: 5px;
            }
            
            .user-name {
                max-width: 100px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        /* Mobile (up to 767px) */
        @media (max-width: 767px) {
            /* Sidebar becomes off-canvas */
            .admin-sidebar {
                transform: translateX(-100%);
                width: 280px !important;
                transition: transform 0.3s ease;
                box-shadow: 2px 0 20px rgba(0,0,0,0.3);
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
            }
            
            /* Remove collapsed state on mobile */
            .admin-sidebar.collapsed {
                transform: translateX(-100%);
                width: 280px !important;
            }
            
            .admin-sidebar.collapsed.show {
                transform: translateX(0);
            }
            
            /* Show all content when sidebar is open on mobile */
            .admin-sidebar.show .sidebar-brand .brand-text,
            .admin-sidebar.show .sidebar-user .user-info,
            .admin-sidebar.show .sidebar-user .user-edit,
            .admin-sidebar.show .nav-item span:not(.nav-badge),
            .admin-sidebar.show .sidebar-footer span,
            .admin-sidebar.show .nav-divider,
            .admin-sidebar.show .sidebar-quick-stats .stat-info {
                display: block;
            }
            
            .admin-sidebar.show .sidebar-brand {
                justify-content: flex-start;
                padding: 20px;
            }
            
            .admin-sidebar.show .sidebar-logo {
                margin-right: 12px;
            }
            
            .admin-sidebar.show .sidebar-user {
                justify-content: flex-start;
                padding: 20px;
            }
            
            .admin-sidebar.show .user-avatar-wrapper {
                margin-right: 12px;
            }
            
            .admin-sidebar.show .nav-item a {
                justify-content: flex-start;
                padding: 12px 20px;
            }
            
            .admin-sidebar.show .nav-item i {
                margin-right: 12px;
            }
            
            .admin-sidebar.show .sidebar-quick-stats .stat-item {
                justify-content: flex-start;
                padding: 10px;
            }
            
            .admin-sidebar.show .sidebar-quick-stats .stat-item i {
                margin-right: 8px;
            }
            
            .admin-sidebar.show .sidebar-footer span {
                display: inline;
            }
            
            /* Main content */
            .admin-main {
                margin-left: 0 !important;
            }
            
            .admin-main.expanded {
                margin-left: 0 !important;
            }
            
            /* Hide desktop-specific elements */
            .header-search {
                display: none;
            }
            
            .quick-actions {
                display: none;
            }
            
            .user-name {
                display: none;
            }
            
            /* Adjust header for mobile */
            .admin-top-header {
                padding: 0 15px;
            }
            
            .header-right {
                gap: 10px;
            }
            
            /* Make dropdowns mobile-friendly */
            .quick-stats-dropdown,
            .notification-dropdown,
            .user-dropdown {
                position: fixed;
                top: var(--header-height);
                left: 10px;
                right: 10px;
                width: auto;
                max-width: none;
                margin-top: 5px;
                max-height: calc(100vh - var(--header-height) - 20px);
                overflow-y: auto;
            }
            
            .notification-dropdown {
                max-height: 80vh;
                overflow-y: auto;
            }
            
            /* Breadcrumbs */
            .breadcrumbs {
                padding: 10px 15px;
                font-size: 0.85rem;
                flex-wrap: wrap;
            }
            
            /* Content padding */
            .admin-content-wrapper {
                padding: 15px;
            }
            
            .admin-content {
                padding: 15px;
            }
        }

        /* Small Mobile (up to 480px) */
        @media (max-width: 480px) {
            .admin-sidebar {
                width: 240px !important;
            }
            
            .admin-sidebar.show .sidebar-brand {
                padding: 15px;
            }
            
            .admin-sidebar.show .sidebar-logo {
                width: 35px;
                height: 35px;
            }
            
            .admin-sidebar.show .brand-text h2 {
                font-size: 1rem;
            }
            
            .admin-sidebar.show .brand-text p {
                font-size: 0.6rem;
            }
            
            .admin-sidebar.show .sidebar-user {
                padding: 15px;
            }
            
            .admin-sidebar.show .user-avatar {
                width: 35px;
                height: 35px;
            }
            
            .admin-sidebar.show .user-info h4 {
                font-size: 0.85rem;
            }
            
            .admin-sidebar.show .nav-item a {
                padding: 10px 15px;
            }
            
            .admin-top-header {
                padding: 0 10px;
            }
            
            .sidebar-toggle {
                width: 35px;
                height: 35px;
                font-size: 1.1rem;
            }
            
            .action-btn,
            .notification-btn {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .user-menu-btn {
                padding: 3px 10px 3px 3px;
                height: 45px;
            }
            
            .user-avatar-small {
                width: 35px;
                height: 35px;
            }
            
            .breadcrumbs {
                padding: 8px 12px;
                font-size: 0.8rem;
            }
            
            .admin-content-wrapper {
                padding: 10px;
            }
            
            .admin-content {
                padding: 12px;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .nav-item a,
            .action-btn,
            .notification-btn,
            .user-menu-btn,
            .dropdown-item {
                min-height: 44px;
            }
            
            .nav-item a:hover,
            .action-btn:hover,
            .notification-btn:hover,
            .user-menu-btn:hover {
                transform: none;
            }
        }

        /* Landscape orientation */
        @media (orientation: landscape) and (max-height: 500px) {
            .admin-sidebar {
                overflow-y: auto;
            }
            
            .sidebar-header {
                padding: 10px;
            }
            
            .sidebar-user {
                padding: 10px;
            }
            
            .sidebar-quick-stats {
                padding: 8px 10px;
            }
            
            .nav-item a {
                padding: 8px 15px;
            }
            
            .sidebar-footer {
                padding: 10px;
            }
        }

        /* Print styles */
        @media print {
            .admin-sidebar,
            .admin-top-header,
            .sidebar-overlay {
                display: none !important;
            }
            
            .admin-main {
                margin-left: 0 !important;
            }
            
            .admin-content-wrapper {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="admin-loading" id="adminLoading">
        <div class="loading-spinner"></div>
    </div>

    <!-- Admin Wrapper -->
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="adminSidebar">
            <!-- Sidebar Header with Logo -->
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-brand">
                    <img src="<?php echo defined('UPLOAD_URL') ? UPLOAD_URL . 'logo.png' : ''; ?>" 
                         alt="<?php echo defined('SITE_NAME') ? SITE_NAME : 'Admin'; ?>" 
                         class="sidebar-logo"
                         onerror="this.onerror=null; this.src='../../assets/images/default-logo.png';">
                    <div class="brand-text">
                        <h2><?php echo defined('SITE_NAME') ? SITE_NAME : 'Admin Panel'; ?></h2>
                        <p>Administration Panel</p>
                    </div>
                </a>
            </div>

            <!-- Sidebar User Info -->
            <div class="sidebar-user">
                <div class="user-avatar-wrapper">
                    <div class="user-avatar">
                        <?php if ($currentUser && !empty($currentUser['profile_image'])): ?>
                            <img src="<?php echo defined('UPLOAD_URL') ? UPLOAD_URL . 'profiles/' . $currentUser['profile_image'] : ''; ?>" 
                                 alt="<?php echo $currentUser['full_name'] ?? $currentUser['username'] ?? 'User'; ?>">
                        <?php else: ?>
                            <div class="avatar-placeholder">
                                <?php echo strtoupper(substr(($currentUser['username'] ?? 'A'), 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="user-status" title="Online"></span>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['username'] ?? 'Admin User'); ?></h4>
                    <p><?php echo ucfirst($currentUser['role'] ?? 'admin'); ?></p>
                </div>
                <a href="profile.php" class="user-edit" title="Edit Profile">
                    <i class="fas fa-pen"></i>
                </a>
            </div>

            <!-- Quick Stats in Sidebar -->
            <div class="sidebar-quick-stats">
                <div class="stat-item">
                    <i class="fas fa-code-branch"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $quickStats['projects']; ?></span>
                        <span class="stat-label">Projects</span>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-users"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?php echo $quickStats['clients']; ?></span>
                        <span class="stat-label">Clients</span>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-dollar-sign"></i>
                    <div class="stat-info">
                        <span class="stat-value">$<?php echo number_format($quickStats['revenue'], 0); ?></span>
                        <span class="stat-label">Revenue</span>
                    </div>
                </div>
            </div>

            <!-- Sidebar Navigation -->
            <nav class="sidebar-nav">
                <ul class="nav-menu">
                    <li class="nav-item <?php echo $currentPage == 'index.php' ? 'active' : ''; ?>">
                        <a href="index.php">
                            <i class="fas fa-chart-pie"></i>
                            <span>Dashboard</span>
                            <?php if ($pendingTasks > 0): ?>
                            <span class="nav-badge"><?php echo $pendingTasks; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-divider">CONTENT</li>

                    <li class="nav-item <?php echo in_array($currentPage, ['projects.php', 'edit-project.php', 'project-details.php']) ? 'active' : ''; ?>">
                        <a href="projects.php">
                            <i class="fas fa-code-branch"></i>
                            <span>Projects</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?php echo in_array($currentPage, ['pages.php', 'sections.php']) ? 'active' : ''; ?>">
                        <a href="pages.php">
                            <i class="fas fa-file-alt"></i>
                            <span>Pages</span>
                        </a>
                    </li>
                    
                    <li class="nav-item <?php echo $currentPage == 'skills.php' ? 'active' : ''; ?>">
                        <a href="skills.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Skills</span>
                        </a>
                    </li>

                    <li class="nav-item <?php echo in_array($currentPage, ['testimonials.php', 'edit-testimonial.php']) ? 'active' : ''; ?>">
                        <a href="testimonials.php">
                            <i class="fas fa-star"></i>
                            <span>Testimonials</span>
                            <?php if ($pendingTestimonials > 0): ?>
                            <span class="nav-badge warning"><?php echo $pendingTestimonials; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-item has-submenu <?php echo in_array($currentPage, ['blog.php', 'blog-posts.php', 'blog-categories.php', 'blog-comments.php']) ? 'active' : ''; ?>" id="blogMenu">
                        <a href="#" class="submenu-toggle" onclick="toggleSubmenu(this); return false;">
                            <i class="fas fa-blog"></i>
                            <span>Blog</span>
                            <i class="fas fa-chevron-right submenu-arrow"></i>
                        </a>
                        <ul class="submenu" id="blogSubmenu">
                            <li>
                                <a href="blog.php?type=posts">
                                    <i class="fas fa-file-alt"></i>
                                    <span>All Posts</span>
                                    <?php if ($draftPosts > 0): ?>
                                    <span class="nav-badge"><?php echo $draftPosts; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li>
                                <a href="blog.php?type=categories">
                                    <i class="fas fa-folder"></i>
                                    <span>Categories</span>
                                </a>
                            </li>
                            <li>
                                <a href="blog.php?type=tags">
                                    <i class="fas fa-tags"></i>
                                    <span>Tags</span>
                                </a>
                            </li>
                            <li>
                                <a href="blog.php?type=comments">
                                    <i class="fas fa-comments"></i>
                                    <span>Comments</span>
                                    <?php if ($pendingComments > 0): ?>
                                    <span class="nav-badge warning"><?php echo $pendingComments; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <li class="nav-item <?php echo in_array($currentPage, ['clients.php', 'client-details.php']) ? 'active' : ''; ?>">
                        <a href="clients.php">
                            <i class="fas fa-building"></i>
                            <span>Clients</span>
                        </a>
                    </li>

                    <li class="nav-divider">FINANCIAL</li>

                    <li class="nav-item <?php echo in_array($currentPage, ['invoices.php', 'view-invoice.php']) ? 'active' : ''; ?>">
                        <a href="invoices.php">
                            <i class="fas fa-file-invoice"></i>
                            <span>Invoices</span>
                            <?php if ($overdueInvoices > 0): ?>
                            <span class="nav-badge danger"><?php echo $overdueInvoices; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-item <?php echo $currentPage == 'expenses.php' ? 'active' : ''; ?>">
                        <a href="expenses.php">
                            <i class="fas fa-receipt"></i>
                            <span>Expenses</span>
                        </a>
                    </li>

                    <li class="nav-item <?php echo $currentPage == 'payment-gateways.php' ? 'active' : ''; ?>">
                        <a href="payment-gateways.php">
                            <i class="fas fa-credit-card"></i>
                            <span>Payments</span>
                        </a>
                    </li>

                    <li class="nav-divider">COMMUNICATION</li>

                    <li class="nav-item <?php echo in_array($currentPage, ['messages.php', 'view-message.php']) ? 'active' : ''; ?>">
                        <a href="messages.php">
                            <i class="fas fa-envelope"></i>
                            <span>Messages</span>
                            <?php if ($unreadMessages > 0): ?>
                            <span class="nav-badge"><?php echo $unreadMessages; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <li class="nav-item <?php echo $currentPage == 'newsletter.php' ? 'active' : ''; ?>">
                        <a href="newsletter.php">
                            <i class="fas fa-envelope-open-text"></i>
                            <span>Newsletter</span>
                        </a>
                    </li>
                    <li class="nav-item <?php echo $currentPage == 'email-templates.php' ? 'active' : ''; ?>">
                        <a href="email-templates.php">
                            <i class="fas fa-envelope-open-text"></i>
                            <span>Email Templates</span>
                        </a>
                    </li>

                    <li class="nav-item <?php echo $currentPage == 'email-queue.php' ? 'active' : ''; ?>">
                        <a href="email-queue.php">
                            <i class="fas fa-clock"></i>
                            <span>Email Queue</span>
                        </a>
                    </li>

                    <li class="nav-divider">ANALYTICS</li>

                    <li class="nav-item <?php echo $currentPage == 'analytics.php' ? 'active' : ''; ?>">
                        <a href="analytics.php">
                            <i class="fas fa-chart-line"></i>
                            <span>Analytics</span>
                        </a>
                    </li>

                    <li class="nav-divider">SETTINGS</li>

                    <li class="nav-item <?php echo $currentPage == 'settings.php' ? 'active' : ''; ?>">
                        <a href="settings.php">
                            <i class="fas fa-cog"></i>
                            <span>Site Settings</span>
                        </a>
                    </li>

                    <li class="nav-item <?php echo $currentPage == 'profile.php' ? 'active' : ''; ?>">
                        <a href="profile.php">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <a href="../../index.php" target="_blank" class="footer-link" title="View Site">
                    <i class="fas fa-external-link-alt"></i>
                    <span>View Site</span>
                </a>
                <a href="logout.php" class="footer-link logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-main" id="adminMain">
            <!-- Top Header Bar -->
            <header class="admin-top-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar (Ctrl+B)">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <!-- Search Bar -->
                    <div class="header-search">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search projects, clients, invoices... (Ctrl+K)" id="globalSearch">
                        <div class="search-results" id="searchResults"></div>
                    </div>
                </div>

                <div class="header-right">
                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <button class="action-btn" onclick="quickAdd('project')" title="Add Project (Ctrl+Shift+P)">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="action-btn" onclick="quickAdd('client')" title="Add Client (Ctrl+Shift+C)">
                            <i class="fas fa-building"></i>
                        </button>
                        <button class="action-btn" onclick="quickAdd('invoice')" title="Create Invoice (Ctrl+Shift+I)">
                            <i class="fas fa-file-invoice"></i>
                        </button>
                        <button class="action-btn" onclick="quickAdd('post')" title="Add Blog Post (Ctrl+Shift+B)">
                            <i class="fas fa-feather-alt"></i>
                        </button>
                    </div>

                    <!-- Quick Stats Dropdown -->
                    <div class="quick-stats-wrapper">
                        <button class="action-btn" id="quickStatsBtn" title="Quick Stats">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                        
                        <div class="quick-stats-dropdown" id="quickStatsDropdown">
                            <div class="stats-mini-grid">
                                <div class="stat-mini-card">
                                    <span class="stat-mini-value"><?php echo $quickStats['projects']; ?></span>
                                    <span class="stat-mini-label">Projects</span>
                                </div>
                                <div class="stat-mini-card">
                                    <span class="stat-mini-value"><?php echo $quickStats['clients']; ?></span>
                                    <span class="stat-mini-label">Clients</span>
                                </div>
                                <div class="stat-mini-card">
                                    <span class="stat-mini-value"><?php echo $quickStats['tasks']; ?></span>
                                    <span class="stat-mini-label">Tasks</span>
                                </div>
                                <div class="stat-mini-card">
                                    <span class="stat-mini-value"><?php echo $unreadMessages; ?></span>
                                    <span class="stat-mini-label">Messages</span>
                                </div>
                            </div>
                            
                            <div class="revenue-preview">
                                <span class="label">Total Revenue</span>
                                <span class="value">$<?php echo number_format($quickStats['revenue'], 2); ?></span>
                            </div>
                            
                            <?php
                            try {
                                $totalTasks = db()->fetch("SELECT COUNT(*) as count FROM project_tasks")['count'] ?? 1;
                                $completedTasks = db()->fetch("SELECT COUNT(*) as count FROM project_tasks WHERE status = 'completed'")['count'] ?? 0;
                                $progressPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
                            } catch (Exception $e) {
                                $progressPercent = 0;
                            }
                            ?>
                            <div class="task-progress-mini">
                                <div class="progress-label">
                                    <span>Task Completion</span>
                                    <span><?php echo $progressPercent; ?>%</span>
                                </div>
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: <?php echo $progressPercent; ?>%"></div>
                                </div>
                            </div>
                            
                            <a href="analytics.php" class="view-full-link">View Full Analytics →</a>
                        </div>
                    </div>

                    <!-- Notifications -->
                    <div class="notifications-dropdown">
                        <button class="notification-btn" id="notificationBtn" title="Notifications">
                            <i class="fas fa-bell"></i>
                            <?php 
                            $totalNotifications = $unreadMessages + $pendingTestimonials + $pendingComments + $pendingTasks + $overdueInvoices;
                            if ($totalNotifications > 0): 
                            ?>
                            <span class="notification-badge">
                                <?php echo min($totalNotifications, 99); ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h3>Notifications</h3>
                                <a href="#" onclick="markAllRead(); return false;">Mark all as read</a>
                            </div>
                            <div class="notification-list">
                                <?php if ($unreadMessages > 0): ?>
                                <a href="messages.php" class="notification-item unread">
                                    <i class="fas fa-envelope"></i>
                                    <div class="notification-content">
                                        <p><strong><?php echo $unreadMessages; ?> new message<?php echo $unreadMessages > 1 ? 's' : ''; ?></strong></p>
                                        <small>Click to view messages</small>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($pendingTestimonials > 0): ?>
                                <a href="testimonials.php" class="notification-item unread">
                                    <i class="fas fa-star"></i>
                                    <div class="notification-content">
                                        <p><strong><?php echo $pendingTestimonials; ?> testimonial<?php echo $pendingTestimonials > 1 ? 's' : ''; ?> pending approval</strong></p>
                                        <small>Click to review</small>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($pendingComments > 0): ?>
                                <a href="blog.php?type=comments" class="notification-item unread">
                                    <i class="fas fa-comments"></i>
                                    <div class="notification-content">
                                        <p><strong><?php echo $pendingComments; ?> comment<?php echo $pendingComments > 1 ? 's' : ''; ?> pending moderation</strong></p>
                                        <small>Click to moderate</small>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($pendingTasks > 0): ?>
                                <a href="projects.php" class="notification-item unread">
                                    <i class="fas fa-tasks"></i>
                                    <div class="notification-content">
                                        <p><strong><?php echo $pendingTasks; ?> task<?php echo $pendingTasks > 1 ? 's' : ''; ?> assigned to you</strong></p>
                                        <small>Click to view tasks</small>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($overdueInvoices > 0): ?>
                                <a href="invoices.php?status=overdue" class="notification-item unread">
                                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                                    <div class="notification-content">
                                        <p><strong><?php echo $overdueInvoices; ?> overdue invoice<?php echo $overdueInvoices > 1 ? 's' : ''; ?></strong></p>
                                        <small>Requires immediate attention</small>
                                    </div>
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($totalNotifications == 0): ?>
                                <div class="notification-item">
                                    <p class="no-notifications">No new notifications</p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="notification-footer">
                                <a href="messages.php">View all messages</a>
                            </div>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="user-menu">
                        <button class="user-menu-btn" id="userMenuBtn">
                            <div class="user-avatar-small">
                                <?php if ($currentUser && !empty($currentUser['profile_image'])): ?>
                                    <img src="<?php echo defined('UPLOAD_URL') ? UPLOAD_URL . 'profiles/' . $currentUser['profile_image'] : ''; ?>" 
                                         alt="<?php echo $currentUser['full_name'] ?? $currentUser['username'] ?? 'User'; ?>">
                                <?php else: ?>
                                    <div class="avatar-placeholder-small">
                                        <?php echo strtoupper(substr(($currentUser['username'] ?? 'A'), 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['username'] ?? 'Admin User'); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        
                        <div class="user-dropdown" id="userDropdown">
                            <div class="dropdown-user-info">
                                <strong><?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['username'] ?? 'Admin User'); ?></strong>
                                <span><?php echo $currentUser['email'] ?? ''; ?></span>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                <span>Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item logout">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <div class="admin-content-wrapper">
                <!-- Breadcrumbs -->
                <?php if (isset($breadcrumbs) && !empty($breadcrumbs)): ?>
                <div class="breadcrumbs">
                    <a href="index.php" class="breadcrumb-home">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <i class="fas fa-chevron-right"></i>
                        <?php if (isset($crumb['url'])): ?>
                            <a href="<?php echo $crumb['url']; ?>"><?php echo htmlspecialchars($crumb['title']); ?></a>
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($crumb['title']); ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Flash Messages -->
                <?php if (function_exists('displayFlash')) echo displayFlash(); ?>

                <!-- Page Content -->
                <div class="admin-content">