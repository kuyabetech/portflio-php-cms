<?php
// includes/client-header.php
// Client Portal Header with Navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Client Portal'; ?> | <?php echo SITE_NAME; ?></title>
    
    <!-- Fonts & Icons -->
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
            display: flex;
            flex-direction: column;
        }
        
        /* Client Header */
        .client-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo-area h1 {
            font-size: 20px;
            color: #1e293b;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: #64748b;
            cursor: pointer;
        }
        
        /* Main Navigation */
        .main-nav {
            flex: 1;
            margin-left: 30px;
        }
        
        .main-nav ul {
            display: flex;
            list-style: none;
            gap: 5px;
        }
        
        .main-nav a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 14px;
            font-weight: 500;
        }
        
        .main-nav a:hover {
            background: #f1f5f9;
            color: #1e293b;
        }
        
        .main-nav a.active {
            background: #667eea;
            color: white;
        }
        
        .main-nav a i {
            font-size: 16px;
        }
        
        /* User Menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .notifications {
            position: relative;
        }
        
        .notifications-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: #64748b;
            cursor: pointer;
            padding: 5px;
            position: relative;
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ef4444;
            color: white;
            font-size: 10px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-dropdown {
            position: relative;
        }
        
        .user-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: background 0.2s ease;
        }
        
        .user-btn:hover {
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
        }
        
        .user-info {
            text-align: left;
        }
        
        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .user-role {
            font-size: 11px;
            color: #64748b;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            min-width: 200px;
            display: none;
            z-index: 1000;
            border: 1px solid #e2e8f0;
        }
        
        .user-dropdown:hover .dropdown-menu {
            display: block;
        }
        
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 15px;
            color: #1e293b;
            text-decoration: none;
            transition: background 0.2s ease;
        }
        
        .dropdown-menu a:hover {
            background: #f1f5f9;
        }
        
        .dropdown-menu a i {
            width: 20px;
            color: #667eea;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e2e8f0;
            margin: 5px 0;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .main-nav {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                padding: 20px;
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
                margin-left: 0;
            }
            
            .main-nav.show {
                display: block;
            }
            
            .main-nav ul {
                flex-direction: column;
            }
            
            .main-nav a {
                padding: 12px 15px;
            }
            
            .user-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header class="client-header">
        <div class="header-container">
            <div class="logo-area">
                <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1><?php echo SITE_NAME; ?> Client Portal</h1>
            </div>
            
            <nav class="main-nav" id="mainNav">
                <ul>
                    <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a></li>
                    <li><a href="projects.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'projects.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-project-diagram"></i> Projects
                    </a></li>
                    <li><a href="invoices.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'invoices.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice"></i> Invoices
                    </a></li>
                    <li><a href="messages.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'messages.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-envelope"></i> Messages
                    </a></li>
                    <li><a href="documents.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'documents.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i> Documents
                    </a></li>
                    <li><a href="support.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'support.php') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-headset"></i> Support
                    </a></li>
                </ul>
            </nav>
            
            <div class="user-menu">
                <div class="notifications">
                    <button class="notifications-btn" onclick="location.href='notifications.php'">
                        <i class="fas fa-bell"></i>
                        <?php
                        $unreadNotifications = db()->fetch("SELECT COUNT(*) as count FROM client_notifications WHERE client_id = ? AND is_read = 0", [$_SESSION['client_id']])['count'] ?? 0;
                        if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <div class="user-dropdown">
                    <button class="user-btn">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($client['first_name'] ?? 'C', 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($client['first_name'] ?? 'Client'); ?></div>
                            <div class="user-role">Client</div>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    
                    <div class="dropdown-menu">
                        <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Account Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="activity-log.php"><i class="fas fa-history"></i> Activity Log</a>
                        <a href="help.php"><i class="fas fa-question-circle"></i> Help Center</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <script>
        function toggleMobileMenu() {
            document.getElementById('mainNav').classList.toggle('show');
            
            
        }
        </script>
        
        <script>
// Real-time notification system
class NotificationSystem {
    constructor() {
        this.checkInterval = 30000; // 30 seconds
        this.lastCheck = Math.floor(Date.now() / 1000);
        this.init();
    }
    
    init() {
        // Check for updates periodically
        setInterval(() => this.checkUpdates(), this.checkInterval);
        
        // Initial load of notifications
        this.loadNotifications();
        this.loadUnreadCount();
    }
    
    checkUpdates() {
        fetch(`ajax/notifications.php?action=check_updates&last_check=${this.lastCheck}`)
            .then(response => response.json())
            .then(data => {
                if (data.has_updates) {
                    this.showNotification('New updates available', 'info');
                    this.loadNotifications();
                    this.loadUnreadCount();
                    this.updateBadges(data);
                }
                this.lastCheck = data.timestamp;
            })
            .catch(error => console.error('Error checking updates:', error));
    }
    
    loadNotifications() {
        fetch('ajax/notifications.php?action=get_recent')
            .then(response => response.json())
            .then(data => {
                this.renderNotifications(data.notifications);
            })
            .catch(error => console.error('Error loading notifications:', error));
    }
    
    loadUnreadCount() {
        fetch('ajax/notifications.php?action=get_unread_count')
            .then(response => response.json())
            .then(data => {
                this.updateNotificationBadge(data.count);
            })
            .catch(error => console.error('Error loading unread count:', error));
        
        fetch('ajax/notifications.php?action=get_unread_messages')
            .then(response => response.json())
            .then(data => {
                this.updateMessageBadge(data.count);
            })
            .catch(error => console.error('Error loading message count:', error));
    }
    
    renderNotifications(notifications) {
        const container = document.getElementById('notificationDropdown');
        if (!container) return;
        
        if (notifications.length === 0) {
            container.innerHTML = '<div class="no-notifications">No new notifications</div>';
            return;
        }
        
        let html = '';
        notifications.forEach(note => {
            html += `
                <div class="notification-item ${note.is_read ? '' : 'unread'}" data-id="${note.id}">
                    <i class="fas ${note.icon}"></i>
                    <div class="notification-content">
                        <p>${note.message}</p>
                        <span class="time">${note.time_ago}</span>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
        // Add click handlers
        container.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => this.markAsRead(item.dataset.id));
        });
    }
    
    markAsRead(notificationId) {
        fetch('ajax/notifications.php?action=mark_read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadUnreadCount();
            }
        })
        .catch(error => console.error('Error marking as read:', error));
    }
    
    updateNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    updateMessageBadge(count) {
        const badge = document.getElementById('messageBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    updateBadges(data) {
        this.updateNotificationBadge(data.new_notifications);
        // Message badge handled separately
    }
    
    showNotification(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.notificationSystem = new NotificationSystem();
});
</script>

<style>
/* Toast Notifications */
.toast-notification {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 9999;
    animation: slideIn 0.3s ease;
    border-left: 4px solid #667eea;
    max-width: 350px;
}

.toast-notification.toast-success {
    border-left-color: #10b981;
}

.toast-notification.toast-error {
    border-left-color: #ef4444;
}

.toast-notification i {
    font-size: 20px;
}

.toast-notification.toast-success i {
    color: #10b981;
}

.toast-notification.toast-error i {
    color: #ef4444;
}

.toast-notification.fade-out {
    animation: fadeOut 0.3s ease forwards;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

/* Notification Dropdown */
.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 350px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    display: none;
    z-index: 1000;
    max-height: 400px;
    overflow-y: auto;
}

.notification-dropdown.show {
    display: block;
}

.notification-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    border-bottom: 1px solid #e2e8f0;
    cursor: pointer;
    transition: background 0.2s ease;
}

.notification-item:hover {
    background: #f8fafc;
}

.notification-item.unread {
    background: #eff6ff;
}

.notification-item i {
    width: 30px;
    height: 30px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
}

.notification-content {
    flex: 1;
}

.notification-content p {
    font-size: 13px;
    color: #1e293b;
    margin-bottom: 3px;
}

.notification-content .time {
    font-size: 11px;
    color: #94a3b8;
}

.no-notifications {
    padding: 30px;
    text-align: center;
    color: #94a3b8;
    font-size: 14px;
}

/* Badge */
.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
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
</style>