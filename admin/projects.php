<?php
// admin/projects.php
// Project Management - FULLY RESPONSIVE

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Projects Management';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Projects']
];

if ($action === 'add') {
    $breadcrumbs[] = ['title' => 'Add New Project'];
} elseif ($action === 'edit') {
    $breadcrumbs[] = ['title' => 'Edit Project'];
} elseif ($action === 'view') {
    $breadcrumbs[] = ['title' => 'Project Details'];
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get image filename before deleting
    $project = db()->fetch("SELECT featured_image FROM projects WHERE id = ?", [$id]);
    if ($project && $project['featured_image']) {
        $imagePath = UPLOAD_PATH . $project['featured_image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    db()->delete('projects', 'id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Project deleted successfully'];
    header('Location: projects.php');
    exit;
}

// Handle status toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $project = db()->fetch("SELECT status FROM projects WHERE id = ?", [$id]);
    $newStatus = $project['status'] === 'published' ? 'draft' : 'published';
    db()->update('projects', ['status' => $newStatus], 'id = :id', ['id' => $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Project status updated'];
    header('Location: projects.php');
    exit;
}

// Handle featured toggle
if (isset($_GET['featured'])) {
    $id = (int)$_GET['featured'];
    $project = db()->fetch("SELECT is_featured FROM projects WHERE id = ?", [$id]);
    $newStatus = $project['is_featured'] ? 0 : 1;
    db()->update('projects', ['is_featured' => $newStatus], 'id = :id', ['id' => $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Project featured status updated'];
    header('Location: projects.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_project'])) {
    $data = [
        'title' => sanitize($_POST['title']),
        'slug' => createSlug($_POST['title']),
        'short_description' => sanitize($_POST['short_description']),
        'full_description' => $_POST['full_description'],
        'category' => sanitize($_POST['category']),
        'technologies' => sanitize($_POST['technologies']),
        'client_name' => sanitize($_POST['client_name']),
        'client_website' => sanitize($_POST['client_website']),
        'completion_date' => $_POST['completion_date'],
        'project_url' => sanitize($_POST['project_url']),
        'github_url' => sanitize($_POST['github_url']),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'status' => $_POST['status']
    ];
    
    // Validate required fields
    $errors = [];
    if (empty($data['title'])) {
        $errors[] = 'Project title is required';
    }
    
    if (!empty($errors)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => implode('<br>', $errors)];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Handle image upload
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['featured_image'], 'projects/');
        if (isset($upload['success'])) {
            // Delete old image if updating
            if (!empty($_POST['id'])) {
                $old = db()->fetch("SELECT featured_image FROM projects WHERE id = ?", [$_POST['id']]);
                if ($old && $old['featured_image']) {
                    $oldPath = UPLOAD_PATH_PROJECTS . $old['featured_image'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
            }
            $data['featured_image'] = $upload['filename'];
        }
    }
    
    if (!empty($_POST['id'])) {
        db()->update('projects', $data, 'id = :id', ['id' => $_POST['id']]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Project updated successfully'];
    } else {
        db()->insert('projects', $data);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Project created successfully'];
    }
    
    header('Location: projects.php');
    exit;
}

// Get project for editing
$project = null;
if ($id > 0 && ($action === 'edit' || $action === 'view')) {
    $project = db()->fetch("SELECT * FROM projects WHERE id = ?", [$id]);
}

// Get all projects
$projects = db()->fetchAll("SELECT * FROM projects ORDER BY created_at DESC") ?? [];

// Get statistics
$stats = db()->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(is_featured) as featured
    FROM projects
") ?? ['total' => 0, 'published' => 0, 'draft' => 0, 'featured' => 0];

// Include header
require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="content-header">
    <h1>
        <i class="fas fa-project-diagram"></i> 
        <?php 
        if ($action === 'view') echo 'Project Details';
        elseif ($action === 'edit') echo 'Edit Project';
        elseif ($action === 'add') echo 'Add New Project';
        else echo 'Projects Management';
        ?>
        <?php if ($action === 'list' && $stats['total'] > 0): ?>
        <span class="header-badge"><?php echo $stats['published']; ?> published</span>
        <?php endif; ?>
    </h1>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Project
        </a>
        <?php elseif ($action === 'view' && $project): ?>
        <a href="?action=edit&id=<?php echo $project['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Project
        </a>
        <a href="<?php echo BASE_URL; ?>/project/<?php echo $project['slug']; ?>" target="_blank" class="btn btn-outline">
            <i class="fas fa-eye"></i> View Live
        </a>
        <?php else: ?>
        <a href="projects.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible">
        <i class="fas <?php echo $_SESSION['flash']['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo $_SESSION['flash']['message']; ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Total Projects</span>
                <span class="stat-value"><?php echo $stats['total']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Published</span>
                <span class="stat-value"><?php echo $stats['published']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-pencil-alt"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Draft</span>
                <span class="stat-value"><?php echo $stats['draft']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Featured</span>
                <span class="stat-value"><?php echo $stats['featured']; ?></span>
            </div>
        </div>
    </div>

    <!-- Desktop Table View -->
    <div class="desktop-table">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="60">Image</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Technologies</th>
                        <th>Status</th>
                        <th width="80">Featured</th>
                        <th>Date</th>
                        <th width="140">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td class="image-cell">
                            <?php if ($project['featured_image']): ?>
                            <img src="<?php echo UPLOAD_URL_PROJECTS . $project['featured_image']; ?>" 
                                 alt="<?php echo htmlspecialchars($project['title']); ?>" 
                                 class="project-thumbnail">
                            <?php else: ?>
                            <div class="thumbnail-placeholder">
                                <i class="fas fa-image"></i>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="project-title">
                                <strong><?php echo htmlspecialchars($project['title']); ?></strong>
                                <?php if (!empty($project['short_description'])): ?>
                                <br><small><?php echo htmlspecialchars(substr($project['short_description'], 0, 60)); ?>...</small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="category-badge"><?php echo htmlspecialchars($project['category'] ?: 'Uncategorized'); ?></span>
                        </td>
                        <td>
                            <div class="tech-tags">
                                <?php 
                                $techs = array_filter(array_map('trim', explode(',', $project['technologies'] ?? '')));
                                foreach (array_slice($techs, 0, 3) as $tech): 
                                ?>
                                <span class="tech-tag"><?php echo htmlspecialchars($tech); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($techs) > 3): ?>
                                <span class="tech-tag more">+<?php echo count($techs) - 3; ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <a href="?toggle=<?php echo $project['id']; ?>" class="status-toggle" title="Click to toggle status">
                                <span class="status-badge <?php echo $project['status']; ?>">
                                    <?php echo ucfirst($project['status']); ?>
                                </span>
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="?featured=<?php echo $project['id']; ?>" class="featured-toggle" title="Toggle featured">
                                <?php if ($project['is_featured']): ?>
                                <i class="fas fa-star featured-star"></i>
                                <?php else: ?>
                                <i class="far fa-star"></i>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td>
                            <div class="date-info">
                                <span class="created-date"><?php echo date('M d, Y', strtotime($project['created_at'])); ?></span>
                                <?php if ($project['completion_date']): ?>
                                <br><small class="completion-date">Completed: <?php echo date('M Y', strtotime($project['completion_date'])); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?action=view&id=<?php echo $project['id']; ?>" class="action-btn" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $project['id']; ?>" class="action-btn" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?php echo $project['id']; ?>" class="action-btn delete-btn" 
                                   onclick="return confirm('Are you sure you want to delete this project? This action cannot be undone.')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>/project/<?php echo $project['slug']; ?>" target="_blank" class="action-btn" title="View Live">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-project-diagram"></i>
                                <h3>No Projects Found</h3>
                                <p>Get started by adding your first project.</p>
                                <a href="?action=add" class="btn btn-primary">Add New Project</a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile Cards View -->
    <div class="mobile-cards">
        <?php foreach ($projects as $project): ?>
        <div class="project-card status-<?php echo $project['status']; ?>">
            <div class="card-header">
                <div class="project-media">
                    <?php if ($project['featured_image']): ?>
                    <img src="<?php echo UPLOAD_URL_PROJECTS . $project['featured_image']; ?>" 
                         alt="<?php echo htmlspecialchars($project['title']); ?>"
                         class="card-image">
                    <?php else: ?>
                    <div class="card-image-placeholder">
                        <i class="fas fa-project-diagram"></i>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($project['is_featured']): ?>
                    <span class="featured-badge" title="Featured Project">
                        <i class="fas fa-star"></i>
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="project-info">
                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                    <span class="category-badge"><?php echo htmlspecialchars($project['category'] ?: 'Uncategorized'); ?></span>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (!empty($project['short_description'])): ?>
                <p class="project-description"><?php echo htmlspecialchars($project['short_description']); ?></p>
                <?php endif; ?>
                
                <div class="tech-section">
                    <strong>Technologies:</strong>
                    <div class="tech-tags">
                        <?php 
                        $techs = array_filter(array_map('trim', explode(',', $project['technologies'] ?? '')));
                        foreach ($techs as $tech): 
                        ?>
                        <span class="tech-tag"><?php echo htmlspecialchars($tech); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="status-badge <?php echo $project['status']; ?>">
                            <?php echo ucfirst($project['status']); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Created:</span>
                        <span><?php echo date('M d, Y', strtotime($project['created_at'])); ?></span>
                    </div>
                    
                    <?php if ($project['completion_date']): ?>
                    <div class="info-item">
                        <span class="info-label">Completed:</span>
                        <span><?php echo date('M Y', strtotime($project['completion_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($project['client_name']): ?>
                    <div class="info-item">
                        <span class="info-label">Client:</span>
                        <span><?php echo htmlspecialchars($project['client_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-footer">
                <div class="action-buttons">
                    <a href="?action=view&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="?action=edit&id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?toggle=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-power-off"></i> 
                        <?php echo $project['status'] === 'published' ? 'Draft' : 'Publish'; ?>
                    </a>
                    <a href="?featured=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline">
                        <i class="fas <?php echo $project['is_featured'] ? 'fa-star' : 'fa-star-o'; ?>"></i>
                        <?php echo $project['is_featured'] ? 'Unfeature' : 'Feature'; ?>
                    </a>
                    <a href="?delete=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-danger" 
                       onclick="return confirm('Are you sure you want to delete this project?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($projects)): ?>
        <div class="empty-state">
            <i class="fas fa-project-diagram"></i>
            <h3>No Projects Found</h3>
            <p>Get started by adding your first project.</p>
            <a href="?action=add" class="btn btn-primary">Add New Project</a>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'view' && $project): ?>
    <!-- Project Details View -->
    <div class="details-container">
        <div class="details-header">
            <?php if ($project['featured_image']): ?>
            <img src="<?php echo UPLOAD_URL_PROJECTS . $project['featured_image']; ?>" 
                 alt="<?php echo htmlspecialchars($project['title']); ?>" 
                 class="details-image">
            <?php endif; ?>
            <div class="details-title-section">
                <h2><?php echo htmlspecialchars($project['title']); ?></h2>
                <div class="details-meta">
                    <span class="status-badge <?php echo $project['status']; ?>">
                        <?php echo ucfirst($project['status']); ?>
                    </span>
                    <?php if ($project['is_featured']): ?>
                    <span class="featured-badge">
                        <i class="fas fa-star"></i> Featured
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="details-grid">
            <div class="details-card">
                <h3><i class="fas fa-info-circle"></i> Project Information</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">Category:</span>
                        <span class="info-value"><?php echo htmlspecialchars($project['category'] ?: 'Uncategorized'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Technologies:</span>
                        <span class="info-value">
                            <div class="tech-tags">
                                <?php 
                                $techs = array_filter(array_map('trim', explode(',', $project['technologies'] ?? '')));
                                foreach ($techs as $tech): 
                                ?>
                                <span class="tech-tag"><?php echo htmlspecialchars($tech); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Created:</span>
                        <span class="info-value"><?php echo date('F d, Y', strtotime($project['created_at'])); ?></span>
                    </div>
                    <?php if ($project['completion_date']): ?>
                    <div class="info-item">
                        <span class="info-label">Completed:</span>
                        <span class="info-value"><?php echo date('F d, Y', strtotime($project['completion_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($project['client_name'] || $project['client_website']): ?>
            <div class="details-card">
                <h3><i class="fas fa-user-tie"></i> Client Information</h3>
                <div class="info-list">
                    <?php if ($project['client_name']): ?>
                    <div class="info-item">
                        <span class="info-label">Client Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($project['client_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($project['client_website']): ?>
                    <div class="info-item">
                        <span class="info-label">Website:</span>
                        <span class="info-value">
                            <a href="<?php echo htmlspecialchars($project['client_website']); ?>" target="_blank">
                                <?php echo htmlspecialchars($project['client_website']); ?>
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($project['project_url'] || $project['github_url']): ?>
            <div class="details-card">
                <h3><i class="fas fa-link"></i> Project Links</h3>
                <div class="info-list">
                    <?php if ($project['project_url']): ?>
                    <div class="info-item">
                        <span class="info-label">Live URL:</span>
                        <span class="info-value">
                            <a href="<?php echo htmlspecialchars($project['project_url']); ?>" target="_blank">
                                View Project <i class="fas fa-external-link-alt"></i>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($project['github_url']): ?>
                    <div class="info-item">
                        <span class="info-label">GitHub:</span>
                        <span class="info-value">
                            <a href="<?php echo htmlspecialchars($project['github_url']); ?>" target="_blank">
                                View Repository <i class="fas fa-external-link-alt"></i>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($project['short_description']) || !empty($project['full_description'])): ?>
            <div class="details-card full-width">
                <h3><i class="fas fa-align-left"></i> Description</h3>
                <?php if (!empty($project['short_description'])): ?>
                <div class="description-section">
                    <strong>Short Description:</strong>
                    <p><?php echo nl2br(htmlspecialchars($project['short_description'])); ?></p>
                </div>
                <?php endif; ?>
                <?php if (!empty($project['full_description'])): ?>
                <div class="description-section">
                    <strong>Full Description:</strong>
                    <div class="full-description">
                        <?php echo nl2br(htmlspecialchars($project['full_description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Form -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" class="admin-form" id="projectForm">
            <?php if ($project): ?>
            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <h2><i class="fas fa-info-circle"></i> Basic Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Project Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required 
                               value="<?php echo htmlspecialchars($project['title'] ?? ''); ?>"
                               placeholder="e.g., E-commerce Platform">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" 
                               value="<?php echo htmlspecialchars($project['category'] ?? ''); ?>"
                               placeholder="e.g., Web App, E-commerce">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="short_description">Short Description</label>
                    <textarea id="short_description" name="short_description" rows="3" 
                              placeholder="Brief overview of the project (max 200 characters)"><?php echo htmlspecialchars($project['short_description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="full_description">Full Description</label>
                    <textarea id="full_description" name="full_description" rows="8" 
                              placeholder="Detailed project description..."><?php echo htmlspecialchars($project['full_description'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-cogs"></i> Technologies & Details</h2>
                
                <div class="form-group">
                    <label for="technologies">Technologies Used</label>
                    <input type="text" id="technologies" name="technologies" 
                           value="<?php echo htmlspecialchars($project['technologies'] ?? ''); ?>"
                           placeholder="PHP, MySQL, JavaScript, React (comma separated)">
                    <p class="help-text">Separate technologies with commas</p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="completion_date">Completion Date</label>
                        <input type="date" id="completion_date" name="completion_date" 
                               value="<?php echo htmlspecialchars($project['completion_date'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="client_name">Client Name</label>
                        <input type="text" id="client_name" name="client_name" 
                               value="<?php echo htmlspecialchars($project['client_name'] ?? ''); ?>"
                               placeholder="e.g., ABC Corp">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="client_website">Client Website</label>
                    <input type="url" id="client_website" name="client_website" 
                           value="<?php echo htmlspecialchars($project['client_website'] ?? ''); ?>"
                           placeholder="https://client-website.com">
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-link"></i> Project Links</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="project_url">Live Project URL</label>
                        <input type="url" id="project_url" name="project_url" 
                               value="<?php echo htmlspecialchars($project['project_url'] ?? ''); ?>"
                               placeholder="https://example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="github_url">GitHub Repository URL</label>
                        <input type="url" id="github_url" name="github_url" 
                               value="<?php echo htmlspecialchars($project['github_url'] ?? ''); ?>"
                               placeholder="https://github.com/username/repo">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-image"></i> Media</h2>
                
                <div class="form-group">
                    <label for="featured_image">Featured Image</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="featured_image" name="featured_image" accept="image/*">
                        <div class="file-input-label">
                            <i class="fas fa-upload"></i> Choose File
                        </div>
                    </div>
                    <?php if ($project && $project['featured_image']): ?>
                    <div class="current-image">
                        <img src="<?php echo UPLOAD_URL_PROJECTS . $project['featured_image']; ?>" 
                             alt="Current featured image" class="preview-image">
                        <p class="small">Current image</p>
                    </div>
                    <?php endif; ?>
                    <p class="help-text">Recommended size: 1200x630px. Max size: 5MB</p>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-cog"></i> Publishing Settings</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="draft" <?php echo (($project['status'] ?? '') === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo (($project['status'] ?? '') === 'published') ? 'selected' : ''; ?>>Published</option>
                        </select>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_featured" 
                                   <?php echo ($project['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="checkbox-text">
                                <i class="fas fa-star"></i>
                                Feature this project on homepage
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_project" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    <?php echo $project ? 'Update Project' : 'Create Project'; ?>
                </button>
                <a href="projects.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
/* ========================================
   PROJECTS PAGE - RESPONSIVE STYLES
   ======================================== */

:root {
    --primary: #2563eb;
    --secondary: #7c3aed;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #1e293b;
    --gray-600: #475569;
    --gray-500: #64748b;
    --gray-400: #94a3b8;
    --gray-300: #cbd5e1;
    --gray-200: #e2e8f0;
    --gray-100: #f1f5f9;
}

/* Content Header */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.content-header h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.content-header h1 i {
    color: var(--primary);
}

.header-badge {
    display: inline-block;
    padding: 4px 12px;
    background: var(--success);
    color: white;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    line-height: 1;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37,99,235,0.2);
}

.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: white;
}

.btn-outline-danger {
    background: transparent;
    color: var(--danger);
    border: 2px solid var(--danger);
}

.btn-outline-danger:hover {
    background: var(--danger);
    color: white;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

.btn-lg {
    padding: 14px 28px;
    font-size: 16px;
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
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.blue { background: rgba(37,99,235,0.1); color: #2563eb; }
.stat-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-icon.orange { background: rgba(245,158,11,0.1); color: #f59e0b; }
.stat-icon.purple { background: rgba(124,58,237,0.1); color: #7c3aed; }

.stat-details {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 14px;
    color: var(--gray-500);
    margin-bottom: 4px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

/* Desktop Table */
.desktop-table {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

.data-table th {
    background: var(--gray-100);
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 16px;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
    font-size: 14px;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background: var(--gray-50);
}

/* Project Thumbnail */
.image-cell {
    width: 60px;
}

.project-thumbnail {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--gray-200);
}

.thumbnail-placeholder {
    width: 50px;
    height: 50px;
    background: var(--gray-100);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-400);
    font-size: 20px;
    border: 2px dashed var(--gray-300);
}

.project-title {
    max-width: 250px;
}

.project-title small {
    color: var(--gray-500);
    font-size: 12px;
}

/* Category Badge */
.category-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--gray-200);
    border-radius: 20px;
    font-size: 12px;
    color: var(--gray-700);
}

/* Tech Tags */
.tech-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    max-width: 200px;
}

.tech-tag {
    padding: 2px 8px;
    background: var(--gray-200);
    border-radius: 12px;
    font-size: 11px;
    color: var(--gray-700);
    font-weight: 500;
}

.tech-tag.more {
    background: var(--primary);
    color: white;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.published { background: rgba(16,185,129,0.1); color: #10b981; }
.status-badge.draft { background: rgba(107,114,128,0.1); color: #6b7280; }

.status-toggle {
    text-decoration: none;
}

/* Featured Star */
.featured-toggle {
    text-decoration: none;
    font-size: 18px;
}

.featured-star {
    color: #fbbf24;
}

.far.fa-star {
    color: var(--gray-400);
}

/* Date Info */
.date-info {
    font-size: 12px;
}

.created-date {
    font-weight: 500;
    color: var(--dark);
}

.completion-date {
    color: var(--gray-500);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.action-btn {
    width: 36px;
    height: 36px;
    background: var(--gray-100);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: var(--primary);
    color: white;
    transform: scale(1.1);
}

.action-btn.delete-btn:hover {
    background: var(--danger);
}

/* Mobile Cards */
.mobile-cards {
    display: none;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 30px;
}

.project-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    border-left: 4px solid transparent;
}

.project-card.status-published { border-left-color: var(--success); }
.project-card.status-draft { border-left-color: var(--gray-400); }

.card-header {
    position: relative;
}

.project-media {
    height: 160px;
    overflow: hidden;
    background: var(--gray-900);
    position: relative;
}

.card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.card-image-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 40px;
}

.featured-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(245,158,11,0.9);
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.project-info {
    padding: 16px;
}

.project-info h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--dark);
}

.card-body {
    padding: 0 16px 16px;
}

.project-description {
    color: var(--gray-600);
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 16px;
}

.tech-section {
    margin-bottom: 16px;
}

.tech-section strong {
    display: block;
    font-size: 13px;
    color: var(--gray-500);
    margin-bottom: 8px;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    background: var(--gray-100);
    padding: 12px;
    border-radius: 12px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.info-label {
    font-size: 11px;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-footer {
    padding: 16px;
    border-top: 1px solid var(--gray-200);
}

.card-footer .action-buttons {
    justify-content: flex-end;
}

/* Details View */
.details-container {
    background: white;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.details-header {
    display: flex;
    gap: 24px;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 2px solid var(--gray-200);
    flex-wrap: wrap;
}

.details-image {
    width: 200px;
    height: 150px;
    object-fit: cover;
    border-radius: 12px;
    border: 4px solid var(--gray-200);
}

.details-title-section {
    flex: 1;
}

.details-title-section h2 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 12px;
    color: var(--dark);
}

.details-meta {
    display: flex;
    gap: 12px;
    align-items: center;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.details-card {
    background: var(--gray-100);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid var(--gray-200);
}

.details-card.full-width {
    grid-column: 1 / -1;
}

.details-card h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--gray-300);
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--dark);
}

.details-card h3 i {
    color: var(--primary);
}

.description-section {
    margin-bottom: 20px;
}

.description-section strong {
    display: block;
    margin-bottom: 8px;
    color: var(--gray-600);
}

.full-description {
    line-height: 1.6;
    color: var(--gray-700);
}

/* Form Container */
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-section {
    background: white;
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.form-section h2 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--dark);
}

.form-section h2 i {
    color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--dark);
    font-size: 14px;
}

.required {
    color: var(--danger);
    margin-left: 2px;
}

.form-group input[type="text"],
.form-group input[type="url"],
.form-group input[type="date"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

/* File Input */
.file-input-wrapper {
    position: relative;
    margin-bottom: 12px;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
}

.file-input-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: var(--gray-100);
    border: 2px dashed var(--gray-300);
    border-radius: 10px;
    color: var(--gray-600);
    transition: all 0.2s ease;
    font-size: 14px;
}

.file-input-wrapper:hover .file-input-label {
    background: var(--gray-200);
    border-color: var(--primary);
    color: var(--primary);
}

/* Current Image */
.current-image {
    margin-top: 12px;
    text-align: center;
}

.preview-image {
    max-width: 200px;
    max-height: 150px;
    border-radius: 8px;
    border: 2px solid var(--gray-200);
}

/* Checkbox */
.checkbox-group {
    display: flex;
    align-items: center;
    height: 100%;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    padding: 12px 0;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.checkbox-text {
    font-size: 14px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 6px;
}

.checkbox-text i {
    color: var(--primary);
}

/* Help Text */
.help-text {
    font-size: 12px;
    color: var(--gray-500);
    margin-top: 8px;
}

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: rgba(16,185,129,0.1);
    color: #065f46;
    border: 1px solid rgba(16,185,129,0.2);
}

.alert-error {
    background: rgba(239,68,68,0.1);
    color: #991b1b;
    border: 1px solid rgba(239,68,68,0.2);
}

.alert-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
    padding: 0;
    line-height: 1;
}

.alert-close:hover {
    opacity: 1;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    margin-top: 24px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 48px;
    color: var(--gray-300);
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--gray-500);
    margin-bottom: 24px;
}

.text-center {
    text-align: center;
}

.small {
    font-size: 12px;
}

/* ========================================
   RESPONSIVE BREAKPOINTS
   ======================================== */

/* Tablet */
@media (max-width: 1023px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .form-section {
        padding: 24px;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
}

/* Mobile Landscape & Portrait */
@media (max-width: 767px) {
    .content-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .header-actions .btn {
        flex: 1;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .desktop-table {
        display: none;
    }
    
    .mobile-cards {
        display: flex;
    }
    
    .details-header {
        flex-direction: column;
    }
    
    .details-image {
        width: 100%;
        height: 200px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

/* Small Mobile */
@media (max-width: 480px) {
    .header-actions {
        flex-direction: column;
    }
    
    .header-actions .btn {
        width: 100%;
    }
    
    .card-footer .action-buttons {
        flex-direction: column;
    }
    
    .card-footer .btn {
        width: 100%;
    }
    
    .form-section {
        padding: 20px;
    }
    
    .checkbox-group {
        margin-top: 10px;
    }
}

/* Print */
@media print {
    .header-actions,
    .action-buttons,
    .btn,
    .form-actions,
    .mobile-cards {
        display: none !important;
    }
    
    .desktop-table {
        display: block !important;
    }
    
    .details-container {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<script>
// File input preview
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const label = this.nextElementSibling;
            if (label && label.classList.contains('file-input-label')) {
                const fileName = this.files[0] ? this.files[0].name : 'Choose File';
                label.innerHTML = `<i class="fas fa-check"></i> ${fileName}`;
            }
        });
    });

    // Form validation
    const projectForm = document.getElementById('projectForm');
    if (projectForm) {
        projectForm.addEventListener('submit', function(e) {
            const title = document.getElementById('title');
            
            if (!title.value.trim()) {
                alert('Project title is required');
                e.preventDefault();
                return false;
            }
        });
    }

    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});

// Handle responsive behavior
function handleResponsive() {
    const width = window.innerWidth;
    const desktopTable = document.querySelector('.desktop-table');
    const mobileCards = document.querySelector('.mobile-cards');
    
    if (width <= 767) {
        if (desktopTable) desktopTable.style.display = 'none';
        if (mobileCards) mobileCards.style.display = 'flex';
    } else {
        if (desktopTable) desktopTable.style.display = 'block';
        if (mobileCards) mobileCards.style.display = 'none';
    }
}

// Run on load and resize
window.addEventListener('load', handleResponsive);
window.addEventListener('resize', handleResponsive);
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>