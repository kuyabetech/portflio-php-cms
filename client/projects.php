<?php
/**
 * Client Projects - View all projects assigned to the client
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

$clientUserId = $_SESSION['client_id'];

// Get client user information
$clientUser = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientUserId]);

if (!$clientUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get client company ID (the actual client record)
$companyId = $clientUser['client_id'] ?? 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 9; // Changed to 9 for better grid layout (3x3)
$offset = ($page - 1) * $perPage;

// Filter
$status = $_GET['status'] ?? 'all';

// Build query
$where = ["1=1"];
$params = [];

// Only show projects if client has a company ID
if ($companyId > 0) {
    $where[] = "client_id = ?";
    $params[] = $companyId;
} else {
    // No company assigned, show no projects
    $where[] = "1=0"; // This will return no results
}

if ($status !== 'all') {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

// Get total count
$totalProjects = 0;
$totalPages = 0;
$projects = [];

if ($companyId > 0) {
    try {
        $countQuery = "SELECT COUNT(*) as count FROM projects WHERE $whereClause";
        $totalProjects = db()->fetch($countQuery, $params)['count'] ?? 0;
        $totalPages = ceil($totalProjects / $perPage);

        // Get projects
        $projects = db()->fetchAll("
            SELECT * FROM projects 
            WHERE $whereClause 
            ORDER BY 
                CASE 
                    WHEN status = 'in_progress' THEN 1
                    WHEN status = 'planning' THEN 2
                    WHEN status = 'completed' THEN 3
                    ELSE 4
                END,
                created_at DESC 
            LIMIT ? OFFSET ?
        ", array_merge($params, [$perPage, $offset])) ?? [];
    } catch (Exception $e) {
        error_log("Projects fetch error: " . $e->getMessage());
    }
}

$pageTitle = 'My Projects';
require_once '../includes/client-header.php';
?>

<div class="projects-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-project-diagram"></i> My Projects</h1>
            <p>View and track all your projects</p>
        </div>
        
        <!-- Status Filter -->
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?status=planning" class="filter-tab <?php echo $status === 'planning' ? 'active' : ''; ?>">Planning</a>
            <a href="?status=in_progress" class="filter-tab <?php echo $status === 'in_progress' ? 'active' : ''; ?>">In Progress</a>
            <a href="?status=completed" class="filter-tab <?php echo $status === 'completed' ? 'active' : ''; ?>">Completed</a>
        </div>
    </div>

    <!-- Projects Grid -->
    <?php if (!empty($projects)): ?>
    <div class="projects-grid">
        <?php foreach ($projects as $project): ?>
        <div class="project-card">
            <!-- Project Image -->
            <div class="project-image">
                <?php if (!empty($project['featured_image'])): ?>
                <img src="<?php echo UPLOAD_URL_PROJECTS . $project['featured_image']; ?>" 
                     alt="<?php echo htmlspecialchars($project['title']); ?>"
                     loading="lazy">
                <?php else: ?>
                <div class="image-placeholder">
                    <i class="fas fa-project-diagram"></i>
                </div>
                <?php endif; ?>
                
                <!-- Status Badge -->
                <span class="status-badge status-<?php echo $project['status']; ?>">
                    <?php 
                    $statusLabels = [
                        'planning' => 'Planning',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'on_hold' => 'On Hold',
                        'cancelled' => 'Cancelled'
                    ];
                    echo $statusLabels[$project['status']] ?? ucfirst($project['status']);
                    ?>
                </span>
            </div>
            
            <!-- Project Content -->
            <div class="project-content">
                <h3>
                    <a href="project-details.php?id=<?php echo $project['id']; ?>">
                        <?php echo htmlspecialchars($project['title']); ?>
                    </a>
                </h3>
                
                <?php if (!empty($project['short_description'])): ?>
                <p class="project-description"><?php echo htmlspecialchars($project['short_description']); ?></p>
                <?php endif; ?>
                
                <!-- Project Meta -->
                <div class="project-meta">
                    <div class="meta-item">
                        <i class="far fa-calendar"></i>
                        <span>Started: <?php echo date('M d, Y', strtotime($project['created_at'])); ?></span>
                    </div>
                    
                    <?php if (!empty($project['completion_date'])): ?>
                    <div class="meta-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Completed: <?php echo date('M d, Y', strtotime($project['completion_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['budget'])): ?>
                    <div class="meta-item">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Budget: $<?php echo number_format($project['budget'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['technologies'])): ?>
                    <div class="meta-item">
                        <i class="fas fa-code"></i>
                        <span class="tech-preview">
                            <?php 
                            $techs = explode(',', $project['technologies']);
                            echo htmlspecialchars(trim($techs[0])) . (count($techs) > 1 ? ' +' . (count($techs)-1) : '');
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Progress Bar (for in progress projects) -->
                <?php if ($project['status'] === 'in_progress'): ?>
                <div class="progress-section">
                    <div class="progress-label">
                        <span>Progress</span>
                        <?php 
                        // You can add a progress field to projects table or calculate based on milestones
                        $progress = $project['progress'] ?? 0;
                        ?>
                        <span><?php echo $progress; ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Card Footer -->
            <div class="card-footer">
                <a href="project-details.php?id=<?php echo $project['id']; ?>" class="btn-view">
                    View Details <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>" class="page-link">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        
        <?php 
        // Show limited page numbers
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        if ($startPage > 1): ?>
        <a href="?page=1&status=<?php echo $status; ?>" class="page-link">1</a>
        <?php if ($startPage > 2): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>" 
           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        
        <?php if ($endPage < $totalPages): ?>
        <?php if ($endPage < $totalPages - 1): ?>
        <span class="page-dots">...</span>
        <?php endif; ?>
        <a href="?page=<?php echo $totalPages; ?>&status=<?php echo $status; ?>" class="page-link">
            <?php echo $totalPages; ?>
        </a>
        <?php endif; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>" class="page-link">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Empty State -->
    <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h3>No Projects Found</h3>
        <?php if ($companyId == 0): ?>
        <p>Your account is not yet associated with any company. Please contact support.</p>
        <?php else: ?>
        <p>You don't have any projects yet. They will appear here once assigned.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.projects-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
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

/* Filter Tabs */
.filter-tabs {
    display: flex;
    gap: 10px;
    background: white;
    padding: 5px;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    flex-wrap: wrap;
}

.filter-tab {
    padding: 8px 16px;
    color: #64748b;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.filter-tab:hover,
.filter-tab.active {
    background: #667eea;
    color: white;
}

/* Projects Grid */
.projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* Project Card */
.project-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.project-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

/* Project Image */
.project-image {
    position: relative;
    height: 180px;
    overflow: hidden;
    background: #f1f5f9;
}

.project-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.project-card:hover .project-image img {
    transform: scale(1.05);
}

.image-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 40px;
}

.status-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.status-planning { background: #fef3c7; color: #92400e; }
.status-in_progress { background: #dbeafe; color: #1e40af; }
.status-completed { background: #d1fae5; color: #065f46; }
.status-on_hold { background: #ffe2e2; color: #991b1b; }
.status-cancelled { background: #e2e8f0; color: #475569; }

/* Project Content */
.project-content {
    padding: 20px;
    flex: 1;
}

.project-content h3 {
    margin-bottom: 10px;
    font-size: 18px;
    line-height: 1.4;
}

.project-content h3 a {
    color: #1e293b;
    text-decoration: none;
    transition: color 0.2s ease;
}

.project-content h3 a:hover {
    color: #667eea;
}

.project-description {
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 15px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Project Meta */
.project-meta {
    margin-bottom: 15px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 13px;
    margin-bottom: 8px;
}

.meta-item i {
    width: 16px;
    color: #667eea;
    font-size: 14px;
}

.tech-preview {
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

/* Progress Section */
.progress-section {
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #e2e8f0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 5px;
}

.progress-bar {
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea, #764ba2);
    border-radius: 3px;
    transition: width 0.3s ease;
}

/* Card Footer */
.card-footer {
    padding: 15px 20px;
    border-top: 1px solid #e2e8f0;
    text-align: right;
    background: #f8fafc;
}

.btn-view {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-view:hover {
    color: #764ba2;
    gap: 8px;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.page-link {
    padding: 8px 12px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s ease;
    min-width: 40px;
    text-align: center;
}

.page-link:hover,
.page-link.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.page-dots {
    padding: 8px 4px;
    color: #94a3b8;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    max-width: 500px;
    margin: 0 auto;
}

.empty-state i {
    font-size: 60px;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #1e293b;
    margin-bottom: 10px;
    font-size: 24px;
}

.empty-state p {
    color: #64748b;
    font-size: 16px;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-tabs {
        width: 100%;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding: 10px;
        -webkit-overflow-scrolling: touch;
    }
    
    .filter-tab {
        flex-shrink: 0;
    }
    
    .projects-grid {
        grid-template-columns: 1fr;
    }
    
    .pagination {
        gap: 3px;
    }
    
    .page-link {
        padding: 6px 10px;
        min-width: 35px;
    }
}

@media (max-width: 480px) {
    .project-image {
        height: 150px;
    }
    
    .project-content {
        padding: 15px;
    }
    
    .project-content h3 {
        font-size: 16px;
    }
    
    .meta-item {
        font-size: 12px;
    }
}
</style>

<?php require_once '../includes/client-footer.php'; ?>