<?php
// ajax/get-project.php
require_once dirname(__DIR__) . '/includes/init.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$project = db()->fetch("SELECT * FROM projects WHERE id = ? AND status = 'published'", [$projectId]);

if (!$project) {
    echo json_encode(['success' => false]);
    exit;
}

// Generate HTML for modal
ob_start();
?>
<div class="quick-view-project">
    <?php if (!empty($project['featured_image'])): ?>
    <div class="quick-view-image">
        <img src="<?php echo UPLOAD_URL . $project['featured_image']; ?>" 
             alt="<?php echo htmlspecialchars($project['title']); ?>">
    </div>
    <?php endif; ?>
    
    <div class="quick-view-details">
        <h2><?php echo htmlspecialchars($project['title']); ?></h2>
        
        <?php if (!empty($project['category'])): ?>
        <span class="quick-view-category"><?php echo htmlspecialchars($project['category']); ?></span>
        <?php endif; ?>
        
        <div class="quick-view-description">
            <h3>Overview</h3>
            <p><?php echo nl2br(htmlspecialchars($project['full_description'] ?? $project['short_description'])); ?></p>
        </div>
        
        <?php if (!empty($project['technologies'])): ?>
        <div class="quick-view-tech">
            <h3>Technologies Used</h3>
            <div class="tech-list">
                <?php 
                $techs = explode(',', $project['technologies']);
                foreach ($techs as $tech): 
                ?>
                <span class="tech-badge"><?php echo htmlspecialchars(trim($tech)); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($project['client_name'])): ?>
        <div class="quick-view-client">
            <h3>Client</h3>
            <p><?php echo htmlspecialchars($project['client_name']); ?></p>
g        </div>
        <?php endif; ?>
        
        <?php if (!empty($project['completion_date'])): ?>
        <div class="quick-view-date">
            <h3>Completed</h3>
            <p><?php echo date('F Y', strtotime($project['completion_date'])); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="quick-view-links">
            <?php if (!empty($project['project_url'])): ?>
            <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank" class="btn btn-primary">
                <i class="fas fa-external-link-alt"></i> Live Demo
            </a>
            <?php endif; ?>
            
            <?php if (!empty($project['github_url'])): ?>
            <a href="<?php echo htmlspecialchars($project['github_url']); ?>" target="_blank" class="btn btn-outline">
                <i class="fab fa-github"></i> Source Code
            </a>
            <?php endif; ?>
            
            <a href="<?php echo BASE_URL; ?>/project/<?php echo $project['slug']; ?>" class="btn btn-outline">
                <i class="fas fa-arrow-right"></i> Full Details
            </a>
        </div>
    </div>
</div>

<style>
.quick-view-project {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    padding: 30px;
}

.quick-view-image {
    height: 400px;
    overflow: hidden;
    border-radius: 12px;
}

.quick-view-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.quick-view-details {
    padding: 20px 0;
}

.quick-view-details h2 {
    font-size: 2rem;
    color: #0f172a;
    margin-bottom: 10px;
}

.quick-view-category {
    display: inline-block;
    padding: 5px 15px;
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
    border-radius: 20px;
    font-size: 0.9rem;
    margin-bottom: 20px;
}

.quick-view-description h3,
.quick-view-tech h3,
.quick-view-client h3,
.quick-view-date h3 {
    font-size: 1.1rem;
    color: #0f172a;
    margin-bottom: 10px;
}

.quick-view-description p {
    color: #475569;
    line-height: 1.8;
    margin-bottom: 25px;
}

.tech-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 25px;
}

.tech-badge {
    padding: 5px 12px;
    background: #f1f5f9;
    border-radius: 20px;
    font-size: 0.85rem;
    color: #475569;
}

.quick-view-client p,
.quick-view-date p {
    color: #475569;
    margin-bottom: 25px;
}

.quick-view-links {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 25px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #2563eb;
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.2);
}

.btn-outline {
    background: transparent;
    color: #2563eb;
    border: 2px solid #2563eb;
}

.btn-outline:hover {
    background: #2563eb;
    color: white;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .quick-view-project {
        grid-template-columns: 1fr;
    }
    
    .quick-view-image {
        height: 250px;
    }
}
</style>

<?php
$html = ob_get_clean();
echo json_encode(['success' => true, 'html' => $html]);
?>