<?php
/**
 * Client Project Details - View single project details
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
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    header('Location: projects.php');
    exit;
}

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
    $clientCompany = db()->fetch("SELECT company_name FROM clients WHERE id = ?", [$clientUser['client_id']]);
}

// Get project details with proper authorization
$project = null;

if (!empty($clientUser['client_id'])) {
    // Check if client has access to this project
    $project = db()->fetch("
        SELECT * FROM projects 
        WHERE id = ? AND client_id = ?
    ", [$projectId, $clientUser['client_id']]);
}

if (!$project) {
    // Project not found or not authorized
    $_SESSION['error'] = 'Project not found or you do not have access to it.';
    header('Location: projects.php');
    exit;
}

// Get project timeline/milestones (if table exists)
$timelines = [];
try {
    $tableCheck = db()->fetch("SHOW TABLES LIKE 'project_timeline'");
    if ($tableCheck) {
        $timelines = db()->fetchAll("
            SELECT * FROM project_timeline 
            WHERE project_id = ? 
            ORDER BY due_date ASC
        ", [$projectId]) ?? [];
    }
} catch (Exception $e) {
    error_log("Timeline fetch error: " . $e->getMessage());
}

// Get project documents (if table exists)
$documents = [];
try {
    $tableCheck = db()->fetch("SHOW TABLES LIKE 'project_documents'");
    if ($tableCheck) {
        $documents = db()->fetchAll("
            SELECT * FROM project_documents 
            WHERE project_id = ? 
            ORDER BY created_at DESC
        ", [$projectId]) ?? [];
    }
} catch (Exception $e) {
    error_log("Documents fetch error: " . $e->getMessage());
}

// Calculate progress (you can customize this based on your needs)
$progress = 0;
if (!empty($timelines)) {
    $totalTasks = count($timelines);
    $completedTasks = count(array_filter($timelines, function($t) { 
        return !empty($t['completed']); 
    }));
    $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
} else {
    // Default progress based on status
    $progress = match($project['status'] ?? '') {
        'planning' => 25,
        'in_progress' => 60,
        'completed' => 100,
        default => 0
    };
}

$pageTitle = $project['title'] ?? 'Project Details';
require_once '../includes/client-header.php';
?>

<div class="project-details">
    <!-- Back Navigation -->
    <div class="back-nav">
        <a href="projects.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Projects
        </a>
    </div>

    <!-- Project Header -->
    <div class="project-header">
        <div class="header-left">
            <h1><?php echo htmlspecialchars($project['title'] ?? 'Untitled Project'); ?></h1>
            <div class="project-meta">
                <span class="status-badge status-<?php echo $project['status'] ?? 'planning'; ?>">
                    <?php 
                    $statusLabels = [
                        'planning' => 'Planning',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'on_hold' => 'On Hold',
                        'cancelled' => 'Cancelled'
                    ];
                    echo $statusLabels[$project['status'] ?? 'planning'] ?? ucfirst($project['status'] ?? 'Planning');
                    ?>
                </span>
                <span class="project-date">
                    <i class="far fa-calendar"></i>
                    Started: <?php echo !empty($project['created_at']) ? date('F d, Y', strtotime($project['created_at'])) : 'N/A'; ?>
                </span>
            </div>
        </div>
        
        <?php if (($project['status'] ?? '') === 'in_progress'): ?>
        <div class="progress-circle">
            <svg width="80" height="80" viewBox="0 0 80 80">
                <circle class="progress-bg" cx="40" cy="40" r="35"></circle>
                <circle class="progress-fill" cx="40" cy="40" r="35" 
                        stroke-dasharray="<?php echo 2 * M_PI * 35; ?>" 
                        stroke-dashoffset="<?php echo 2 * M_PI * 35 * (1 - $progress/100); ?>"></circle>
            </svg>
            <span class="progress-text"><?php echo $progress; ?>%</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Project Content Grid -->
    <div class="details-grid">
        <!-- Left Column -->
        <div class="left-column">
            <!-- Description -->
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Project Description</h3>
                
                <?php if (!empty($project['short_description'])): ?>
                <div class="description-section">
                    <h4>Short Description</h4>
                    <p><?php echo nl2br(htmlspecialchars($project['short_description'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($project['full_description'])): ?>
                <div class="description-section">
                    <h4>Full Description</h4>
                    <div class="full-description">
                        <?php echo nl2br(htmlspecialchars($project['full_description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($project['short_description']) && empty($project['full_description'])): ?>
                <p class="text-muted">No description available.</p>
                <?php endif; ?>
            </div>

            <!-- Project Timeline -->
            <?php if (!empty($timelines)): ?>
            <div class="info-card">
                <h3><i class="fas fa-clock"></i> Project Timeline</h3>
                <div class="timeline-list">
                    <?php foreach ($timelines as $timeline): 
                        $isOverdue = empty($timeline['completed']) && !empty($timeline['due_date']) && strtotime($timeline['due_date']) < time();
                    ?>
                    <div class="timeline-item <?php echo $isOverdue ? 'overdue' : ''; ?> <?php echo !empty($timeline['completed']) ? 'completed' : ''; ?>">
                        <div class="timeline-icon">
                            <i class="fas <?php echo !empty($timeline['completed']) ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <h4><?php echo htmlspecialchars($timeline['title'] ?? 'Untitled'); ?></h4>
                            <?php if (!empty($timeline['description'])): ?>
                            <p><?php echo htmlspecialchars($timeline['description']); ?></p>
                            <?php endif; ?>
                            <span class="timeline-date">
                                <i class="far fa-calendar"></i> 
                                Due: <?php echo !empty($timeline['due_date']) ? date('M d, Y', strtotime($timeline['due_date'])) : 'No date'; ?>
                                <?php if (!empty($timeline['completed'])): ?>
                                <span class="completed-badge">Completed</span>
                                <?php elseif ($isOverdue): ?>
                                <span class="overdue-badge">Overdue</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div class="right-column">
            <!-- Project Info -->
            <div class="info-card">
                <h3><i class="fas fa-cogs"></i> Project Details</h3>
                <div class="info-list">
                    <?php if (!empty($project['category'])): ?>
                    <div class="info-item">
                        <span class="info-label">Category:</span>
                        <span class="info-value"><?php echo htmlspecialchars($project['category']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['budget'])): ?>
                    <div class="info-item">
                        <span class="info-label">Budget:</span>
                        <span class="info-value">$<?php echo number_format((float)$project['budget'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['technologies'])): ?>
                    <div class="info-item">
                        <span class="info-label">Technologies:</span>
                        <span class="info-value">
                            <div class="tech-tags">
                                <?php 
                                $techs = array_map('trim', explode(',', $project['technologies']));
                                foreach ($techs as $tech): 
                                    if (!empty($tech)):
                                ?>
                                <span class="tech-tag"><?php echo htmlspecialchars($tech); ?></span>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['completion_date'])): ?>
                    <div class="info-item">
                        <span class="info-label">Completed:</span>
                        <span class="info-value"><?php echo date('F d, Y', strtotime($project['completion_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Client Info (if different from current client) -->
            <?php if (!empty($project['client_name']) && $project['client_name'] !== ($clientCompany['company_name'] ?? '')): ?>
            <div class="info-card">
                <h3><i class="fas fa-user-tie"></i> Client Information</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($project['client_name']); ?></span>
                    </div>
                    
                    <?php if (!empty($project['client_website'])): ?>
                    <div class="info-item">
                        <span class="info-label">Website:</span>
                        <span class="info-value">
                            <a href="<?php echo htmlspecialchars($project['client_website']); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo htmlspecialchars($project['client_website']); ?>
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Project Links -->
            <?php if (!empty($project['project_url']) || !empty($project['github_url'])): ?>
            <div class="info-card">
                <h3><i class="fas fa-link"></i> Project Links</h3>
                <div class="link-list">
                    <?php if (!empty($project['project_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank" rel="noopener noreferrer" class="project-link">
                        <i class="fas fa-globe"></i>
                        <span>Live Project</span>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['github_url'])): ?>
                    <a href="<?php echo htmlspecialchars($project['github_url']); ?>" target="_blank" rel="noopener noreferrer" class="project-link">
                        <i class="fab fa-github"></i>
                        <span>GitHub Repository</span>
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Documents -->
            <?php if (!empty($documents)): ?>
            <div class="info-card">
                <h3><i class="fas fa-file-alt"></i> Project Documents</h3>
                <div class="document-list">
                    <?php foreach ($documents as $doc): ?>
                    <a href="<?php echo UPLOAD_URL ?>documents/<?php echo urlencode($doc['filename']); ?>" 
                       target="_blank" class="document-item" download>
                        <i class="fas fa-file-<?php 
                            $ext = strtolower(pathinfo($doc['filename'] ?? '', PATHINFO_EXTENSION));
                            echo match($ext) {
                                'pdf' => 'pdf',
                                'doc', 'docx' => 'word',
                                'xls', 'xlsx' => 'excel',
                                'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
                                default => 'alt'
                            };
                        ?>"></i>
                        <div class="document-info">
                            <span class="doc-title"><?php echo htmlspecialchars($doc['title'] ?? $doc['filename']); ?></span>
                            <span class="doc-size"><?php echo formatFileSize($doc['file_size'] ?? 0); ?></span>
                        </div>
                        <i class="fas fa-download"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.project-details {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

/* Back Navigation */
.back-nav {
    margin-bottom: 20px;
}

.back-link {
    color: #64748b;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: color 0.2s ease;
}

.back-link:hover {
    color: #667eea;
}

/* Project Header */
.project-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
    flex-wrap: wrap;
    gap: 20px;
}

.header-left h1 {
    font-size: 32px;
    color: #1e293b;
    margin-bottom: 10px;
    line-height: 1.2;
}

.project-meta {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.project-date {
    color: #64748b;
    font-size: 14px;
}

.project-date i {
    margin-right: 5px;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-planning { background: #fef3c7; color: #92400e; }
.status-in_progress { background: #dbeafe; color: #1e40af; }
.status-completed { background: #d1fae5; color: #065f46; }
.status-on_hold { background: #ffe2e2; color: #991b1b; }
.status-cancelled { background: #e2e8f0; color: #475569; }

/* Progress Circle */
.progress-circle {
    position: relative;
    width: 80px;
    height: 80px;
    flex-shrink: 0;
}

.progress-circle svg {
    transform: rotate(-90deg);
    width: 100%;
    height: 100%;
}

.progress-bg {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 8;
}

.progress-fill {
    fill: none;
    stroke: #667eea;
    stroke-width: 8;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.3s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 16px;
    font-weight: 600;
    color: #667eea;
}

/* Details Grid */
.details-grid {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
}

/* Info Cards */
.info-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transition: box-shadow 0.3s ease;
}

.info-card:hover {
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.info-card h3 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-card h3 i {
    color: #667eea;
}

/* Description */
.description-section {
    margin-bottom: 25px;
}

.description-section h4 {
    font-size: 16px;
    color: #1e293b;
    margin-bottom: 10px;
    font-weight: 600;
}

.description-section p,
.full-description {
    color: #475569;
    line-height: 1.7;
    font-size: 15px;
}

.full-description {
    background: #f8fafc;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #667eea;
}

/* Info List */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-item {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px solid #e2e8f0;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    width: 100px;
    font-weight: 600;
    color: #475569;
    font-size: 14px;
}

.info-value {
    flex: 1;
    color: #1e293b;
    font-size: 14px;
}

/* Tech Tags */
.tech-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.tech-tag {
    display: inline-block;
    padding: 4px 10px;
    background: #f1f5f9;
    border-radius: 20px;
    font-size: 12px;
    color: #475569;
    font-weight: 500;
}

/* Timeline */
.timeline-list {
    position: relative;
    padding-left: 30px;
}

.timeline-list::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e2e8f0;
}

.timeline-item {
    position: relative;
    margin-bottom: 25px;
    display: flex;
    gap: 15px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -29px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #667eea;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 1;
}

.timeline-item.overdue::before {
    background: #ef4444;
}

.timeline-item.completed::before {
    background: #10b981;
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
    font-size: 14px;
    flex-shrink: 0;
}

.timeline-content {
    flex: 1;
    padding-bottom: 5px;
}

.timeline-content h4 {
    font-size: 16px;
    color: #1e293b;
    margin-bottom: 5px;
    font-weight: 600;
}

.timeline-content p {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 5px;
    line-height: 1.5;
}

.timeline-date {
    font-size: 12px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
}

.completed-badge,
.overdue-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 5px;
}

.completed-badge {
    background: #d1fae5;
    color: #065f46;
}

.overdue-badge {
    background: #fee2e2;
    color: #991b1b;
}

/* Project Links */
.link-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.project-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    color: #1e293b;
    text-decoration: none;
    transition: all 0.2s ease;
}

.project-link:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
    transform: translateY(-2px);
}

.project-link i:first-child {
    font-size: 20px;
    color: #667eea;
}

.project-link:hover i:first-child {
    color: white;
}

.project-link i:last-child {
    margin-left: auto;
    opacity: 0.5;
    font-size: 14px;
}

/* Documents */
.document-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.document-item:hover {
    background: #f1f5f9;
    border-color: #667eea;
    transform: translateY(-2px);
}

.document-item i:first-child {
    font-size: 24px;
    color: #667eea;
}

.document-info {
    flex: 1;
    min-width: 0;
}

.doc-title {
    display: block;
    color: #1e293b;
    font-weight: 500;
    font-size: 14px;
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.doc-size {
    font-size: 11px;
    color: #94a3b8;
}

.document-item i:last-child {
    color: #64748b;
    opacity: 0.5;
    transition: opacity 0.2s ease;
}

.document-item:hover i:last-child {
    opacity: 1;
    color: #667eea;
}

/* Text Muted */
.text-muted {
    color: #94a3b8;
    font-style: italic;
}

/* Responsive */
@media (max-width: 1024px) {
    .details-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .project-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-left h1 {
        font-size: 28px;
    }
    
    .info-item {
        flex-direction: column;
        gap: 5px;
    }
    
    .info-label {
        width: auto;
    }
    
    .info-card {
        padding: 20px;
    }
    
    .timeline-item {
        flex-direction: column;
        gap: 10px;
    }
    
    .timeline-icon {
        align-self: flex-start;
    }
}

@media (max-width: 480px) {
    .project-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .header-left h1 {
        font-size: 24px;
    }
    
    .project-link,
    .document-item {
        flex-wrap: wrap;
    }
    
    .document-info {
        width: 100%;
        order: 2;
    }
    
    .document-item i:last-child {
        margin-left: auto;
    }
}
</style>

<?php
/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}

require_once '../includes/client-footer.php'; 
?>