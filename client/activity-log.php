<?php
/**
 * Client Activity Log - View account activity history
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

// Get client information
$client = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientId]);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 30;
$offset = ($page - 1) * $perPage;

// Get total count
$totalActivities = db()->fetch("SELECT COUNT(*) as count FROM client_activity_log WHERE client_id = ?", [$clientId])['count'] ?? 0;
$totalPages = ceil($totalActivities / $perPage);

// Get activities
$activities = db()->fetchAll("
    SELECT * FROM client_activity_log 
    WHERE client_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
", [$clientId, $perPage, $offset]);

$pageTitle = 'Activity Log';
require_once '../includes/client-header.php';
?>

<div class="activity-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-history"></i> Activity Log</h1>
            <p>Track all your account activities</p>
        </div>
        
        <div class="header-actions">
            <span class="total-count">
                <i class="fas fa-clipboard-list"></i>
                <?php echo $totalActivities; ?> total activities
            </span>
        </div>
    </div>

    <!-- Activity Timeline -->
    <?php if (!empty($activities)): ?>
    <div class="timeline-container">
        <div class="timeline">
            <?php 
            $currentDate = '';
            foreach ($activities as $activity):
                $activityDate = date('Y-m-d', strtotime($activity['created_at']));
                
                // Show date header
                if ($activityDate !== $currentDate):
                    $currentDate = $activityDate;
            ?>
            <div class="timeline-date-header">
                <span class="date-badge">
                    <?php 
                    if (date('Y-m-d') === $currentDate) {
                        echo 'Today';
                    } elseif (date('Y-m-d', strtotime('-1 day')) === $currentDate) {
                        echo 'Yesterday';
                    } else {
                        echo date('F j, Y', strtotime($currentDate));
                    }
                    ?>
                </span>
            </div>
            <?php endif; ?>
            
            <div class="timeline-item">
                <div class="timeline-icon">
                    <i class="fas <?php echo $activity['icon'] ?? 'fa-circle'; ?>"></i>
                </div>
                
                <div class="timeline-content">
                    <div class="timeline-header">
                        <span class="activity-action">
                            <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                        </span>
                        <span class="activity-time">
                            <?php echo date('h:i A', strtotime($activity['created_at'])); ?>
                        </span>
                    </div>
                    
                    <p class="activity-description">
                        <?php echo htmlspecialchars($activity['description']); ?>
                    </p>
                    
                    <?php if (!empty($activity['ip_address'])): ?>
                    <div class="activity-meta">
                        <span class="meta-item">
                            <i class="fas fa-network-wired"></i>
                            IP: <?php echo $activity['ip_address']; ?>
                        </span>
                        
                        <?php if (!empty($activity['user_agent'])): ?>
                        <span class="meta-item">
                            <i class="fas fa-globe"></i>
                            <?php echo getBrowserInfo($activity['user_agent']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>" class="page-link">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>" class="page-link">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Empty State -->
    <div class="empty-state">
        <i class="fas fa-history"></i>
        <h3>No Activity Yet</h3>
        <p>Your activity log will appear here as you use your account.</p>
    </div>
    <?php endif; ?>
</div>

<style>
.activity-page {
    max-width: 800px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.header-content h1 {
    font-size: 28px;
    color: #1e293b;
    margin-bottom: 5px;
}

.header-content p {
    color: #64748b;
}

.total-count {
    background: #f1f5f9;
    padding: 10px 20px;
    border-radius: 30px;
    color: #475569;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.total-count i {
    color: #667eea;
}

/* Timeline Container */
.timeline-container {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e2e8f0;
}

/* Date Header */
.timeline-date-header {
    position: relative;
    margin: 20px 0 10px;
}

.timeline-date-header:first-child {
    margin-top: 0;
}

.date-badge {
    display: inline-block;
    padding: 5px 15px;
    background: #667eea;
    color: white;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    position: relative;
    left: -20px;
}

/* Timeline Item */
.timeline-item {
    position: relative;
    margin-bottom: 25px;
    display: flex;
    gap: 15px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -34px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #667eea;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-icon {
    width: 30px;
    height: 30px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    font-size: 12px;
    flex-shrink: 0;
}

.timeline-content {
    flex: 1;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
    flex-wrap: wrap;
    gap: 10px;
}

.activity-action {
    font-weight: 600;
    color: #1e293b;
    font-size: 14px;
}

.activity-time {
    font-size: 12px;
    color: #94a3b8;
}

.activity-description {
    color: #475569;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 8px;
}

.activity-meta {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.meta-item {
    font-size: 11px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 5px;
}

.meta-item i {
    font-size: 11px;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 30px;
}

.page-link {
    padding: 8px 12px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s ease;
}

.page-link:hover,
.page-link.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 60px;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #1e293b;
    margin-bottom: 10px;
}

.empty-state p {
    color: #64748b;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .timeline-container {
        padding: 20px;
    }
    
    .timeline-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .activity-meta {
        flex-direction: column;
        gap: 5px;
    }
}
</style>

<?php
function getBrowserInfo($userAgent) {
    if (strpos($userAgent, 'Firefox') !== false) {
        return 'Firefox';
    } elseif (strpos($userAgent, 'Chrome') !== false) {
        return 'Chrome';
    } elseif (strpos($userAgent, 'Safari') !== false) {
        return 'Safari';
    } elseif (strpos($userAgent, 'Edge') !== false) {
        return 'Edge';
    } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
        return 'Internet Explorer';
    } else {
        return 'Unknown Browser';
    }
}

require_once '../includes/client-footer.php'; 
?>