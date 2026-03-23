<?php
/**
 * projects.php
 * Projects Listing Page - Displays all projects
 */

require_once 'includes/init.php';

// Define truncateText function if it doesn't exist
if (!function_exists('truncateText')) {
    function truncateText($text, $length = 100) {
        if (empty($text)) return '';
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . '...';
    }
}

// Get all projects with pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$category = isset($_GET['category']) ? $_GET['category'] : null;

$perPage = 9;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["status = 'published'"];
$params = [];

// Category filter
if ($category) {
    $where[] = "category = ?";
    $params[] = $category;
}

$whereClause = implode(' AND ', $where);

// Get total count for pagination
$totalProjects = db()->fetchColumn(
    "SELECT COUNT(*) FROM projects WHERE $whereClause",
    $params
) ?: 0;

$totalPages = ceil($totalProjects / $perPage);

// Get projects with pagination
$projects = db()->fetchAll(
    "SELECT *
     FROM projects
     WHERE $whereClause
     ORDER BY is_featured DESC, created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
) ?: [];

// Get categories from projects table
$categories = db()->fetchAll(
    "SELECT DISTINCT category
     FROM projects
     WHERE category IS NOT NULL
     ORDER BY category ASC"
) ?: [];

$pageTitle = 'Projects';
$pageDescription = 'Browse through my portfolio of web development projects';

// Include header
require_once 'templates/layouts/header.php';
?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <h1 class="page-title">My Projects</h1>
        <p class="page-description">
            Browse through my portfolio of web development projects. Each project represents 
            unique challenges and solutions in modern web development.
        </p>
    </div>
</section>

<!-- Projects Section -->
<section class="projects-page">
    <div class="container">
        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-tabs">
                <a href="?page=1" class="filter-tab <?php echo !$category ? 'active' : ''; ?>">
                    All Projects
                </a>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <?php if (isset($cat['id']) && isset($cat['name'])): ?>
                        <a href="?category=<?php echo (int)$cat['id']; ?>&page=1" 
                           class="filter-tab <?php echo $category == $cat['id'] ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat['name'] ?? ''); ?>
                        </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="project-count">
                Showing <?php echo count($projects); ?> of <?php echo $totalProjects; ?> projects
            </div>
        </div>

        <?php if (!empty($projects)): ?>
        <!-- Projects Grid -->
        <div class="projects-grid">
            <?php foreach ($projects as $project): ?>
            <div class="project-card">
                <div class="project-image">
                    <?php if (!empty($project['featured_image'])): ?>
                    <img src="<?php echo UPLOAD_URL . 'projects/' . htmlspecialchars($project['featured_image'] ?? ''); ?>" 
                         alt="<?php echo htmlspecialchars($project['title'] ?? ''); ?>"
                         loading="lazy">
                    <?php else: ?>
                    <div class="project-image-placeholder">
                        <i class="fas fa-code"></i>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['featured']) && $project['featured'] == 1): ?>
                    <span class="featured-badge">
                        <i class="fas fa-star"></i> Featured
                    </span>
                    <?php endif; ?>
                    
                    <div class="project-overlay">
                        <div class="project-links">
                            <a href="<?php echo BASE_URL; ?>/project/<?php echo htmlspecialchars($project['slug'] ?? ''); ?>" 
                               class="project-link" title="View Details">
                                <i class="fas fa-search"></i>
                            </a>
                            <?php if (!empty($project['project_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['project_url']); ?>" 
                               target="_blank" rel="noopener noreferrer" 
                               class="project-link" title="Live Preview">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (!empty($project['github_url'])): ?>
                            <a href="<?php echo htmlspecialchars($project['github_url']); ?>" 
                               target="_blank" rel="noopener noreferrer" 
                               class="project-link" title="View Code">
                                <i class="fab fa-github"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="project-info">
                    <div class="project-meta">
                        <?php if (!empty($project['category_name'])): ?>
                        <span class="project-category">
                            <i class="fas fa-folder"></i> 
                            <?php echo htmlspecialchars($project['category_name']); ?>
                        </span>
                        <?php endif; ?>
                        
                        <span class="project-date">
                            <i class="far fa-calendar"></i> 
                            <?php echo !empty($project['created_at']) ? date('M Y', strtotime($project['created_at'])) : 'Date N/A'; ?>
                        </span>
                    </div>
                    
                    <h3><?php echo htmlspecialchars($project['title'] ?? 'Untitled Project'); ?></h3>
                    <p><?php echo htmlspecialchars(truncateText($project['short_description'] ?? '', 120)); ?></p>
                    
                    <?php if (!empty($project['technologies'])): ?>
                    <div class="project-tech">
                        <?php 
                        $techs = explode(',', $project['technologies']);
                        $techs = array_slice($techs, 0, 4); // Show only first 4
                        foreach ($techs as $tech): 
                        ?>
                        <span class="tech-tag"><?php echo htmlspecialchars(trim($tech)); ?></span>
                        <?php endforeach; ?>
                        <?php 
                        $totalTechs = count(explode(',', $project['technologies']));
                        if ($totalTechs > 4): 
                        ?>
                        <span class="tech-tag more">+<?php echo $totalTechs - 4; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?><?php echo $category ? '&category=' . $category : ''; ?>" 
               class="page-link prev">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <div class="page-numbers">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $category ? '&category=' . $category : ''; ?>" 
                   class="page-number <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $category ? '&category=' . $category : ''; ?>" 
               class="page-link next">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- No Projects Found -->
        <div class="no-projects">
            <i class="fas fa-folder-open"></i>
            <h2>No Projects Found</h2>
            <p>There are no projects in this category yet. Check back later!</p>
            <a href="<?php echo BASE_URL; ?>/projects" class="btn btn-primary">
                <i class="fas fa-redo"></i> View All Projects
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<style>
/* ========================================
   PROJECTS PAGE STYLES
   ======================================== */

:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #1e293b;
    --gray-700: #334155;
    --gray-600: #475569;
    --gray-500: #64748b;
    --gray-400: #94a3b8;
    --gray-300: #cbd5e1;
    --gray-200: #e2e8f0;
    --gray-100: #f1f5f9;
    --white: #ffffff;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: white;
    padding: 80px 0;
    text-align: center;
    margin-bottom: 40px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.page-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 20px;
    animation: fadeInUp 0.6s ease;
}

.page-description {
    font-size: 1.2rem;
    max-width: 700px;
    margin: 0 auto;
    opacity: 0.9;
    animation: fadeInUp 0.8s ease;
}

/* Filter Bar */
.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    flex-wrap: wrap;
    gap: 20px;
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.filter-tab {
    padding: 8px 16px;
    color: var(--gray-600);
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
}

.filter-tab:hover,
.filter-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.project-count {
    color: var(--gray-500);
    font-size: 0.95rem;
}

/* Projects Grid */
.projects-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-bottom: 50px;
}

/* Project Card */
.project-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
    animation: fadeInUp 0.6s ease;
    animation-fill-mode: both;
}

.project-card:nth-child(2) { animation-delay: 0.1s; }
.project-card:nth-child(3) { animation-delay: 0.2s; }
.project-card:nth-child(4) { animation-delay: 0.3s; }
.project-card:nth-child(5) { animation-delay: 0.4s; }
.project-card:nth-child(6) { animation-delay: 0.5s; }
.project-card:nth-child(7) { animation-delay: 0.6s; }
.project-card:nth-child(8) { animation-delay: 0.7s; }
.project-card:nth-child(9) { animation-delay: 0.8s; }

.project-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
}

/* Project Image */
.project-image {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: var(--gray-100);
}

.project-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.project-card:hover .project-image img {
    transform: scale(1.1);
}

.project-image-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: white;
    font-size: 3rem;
}

/* Featured Badge */
.featured-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: var(--warning);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 2;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.featured-badge i {
    margin-right: 4px;
}

/* Project Overlay */
.project-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(37, 99, 235, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.project-card:hover .project-overlay {
    opacity: 1;
}

.project-links {
    display: flex;
    gap: 15px;
}

.project-link {
    width: 50px;
    height: 50px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.2rem;
    text-decoration: none;
    transition: all 0.3s ease;
    transform: translateY(20px);
    opacity: 0;
}

.project-card:hover .project-link {
    transform: translateY(0);
    opacity: 1;
}

.project-link:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-3px) !important;
}

.project-link:nth-child(2) {
    transition-delay: 0.1s;
}

.project-link:nth-child(3) {
    transition-delay: 0.2s;
}

/* Project Info */
.project-info {
    padding: 25px;
}

.project-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 12px;
    font-size: 0.85rem;
}

.project-category,
.project-date {
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 5px;
}

.project-category i,
.project-date i {
    color: var(--primary);
}

.project-info h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
    line-height: 1.4;
}

.project-info p {
    color: var(--gray-600);
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 20px;
}

/* Technology Tags */
.project-tech {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tech-tag {
    padding: 4px 10px;
    background: var(--gray-100);
    color: var(--primary);
    font-size: 0.75rem;
    font-weight: 500;
    border-radius: 20px;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
}

.tech-tag:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.tech-tag.more {
    background: var(--gray-200);
    color: var(--gray-600);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    margin-top: 50px;
}

.page-link {
    padding: 10px 20px;
    background: white;
    color: var(--primary);
    text-decoration: none;
    border-radius: 8px;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.page-link:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-numbers {
    display: flex;
    gap: 8px;
}

.page-number {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    color: var(--gray-600);
    text-decoration: none;
    border-radius: 8px;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
}

.page-number:hover,
.page-number.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* No Projects Found */
.no-projects {
    text-align: center;
    padding: 80px 20px;
    background: var(--gray-100);
    border-radius: 16px;
}

.no-projects i {
    font-size: 5rem;
    color: var(--gray-400);
    margin-bottom: 20px;
}

.no-projects h2 {
    font-size: 2rem;
    color: var(--dark);
    margin-bottom: 10px;
}

.no-projects p {
    color: var(--gray-500);
    font-size: 1.1rem;
    margin-bottom: 30px;
}

/* Button */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 30px;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: var(--primary);
    color: white;
    box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(37, 99, 235, 0.3);
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .projects-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-header {
        padding: 60px 0;
    }
    
    .page-title {
        font-size: 2.5rem;
    }
    
    .page-description {
        font-size: 1rem;
    }
    
    .filter-bar {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .projects-grid {
        grid-template-columns: 1fr;
    }
    
    .pagination {
        flex-direction: column;
        gap: 15px;
    }
    
    .page-numbers {
        order: -1;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 2rem;
    }
    
    .filter-tabs {
        width: 100%;
    }
    
    .filter-tab {
        flex: 1;
        text-align: center;
    }
    
    .project-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .project-info {
        padding: 20px;
    }
    
    .project-info h3 {
        font-size: 1.1rem;
    }
}
</style>

<?php
// Include footer
require_once 'templates/layouts/footer.php';
?>