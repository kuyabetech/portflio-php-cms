<?php
// admin/projects.php
// Project Management

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
    header('Location: projects.php?msg=deleted');
    exit;
}

// Handle status toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $project = db()->fetch("SELECT status FROM projects WHERE id = ?", [$id]);
    $newStatus = $project['status'] === 'published' ? 'draft' : 'published';
    db()->update('projects', ['status' => $newStatus], 'id = :id', ['id' => $id]);
    header('Location: projects.php?msg=toggled');
    exit;
}

// Handle featured toggle
if (isset($_GET['featured'])) {
    $id = (int)$_GET['featured'];
    $project = db()->fetch("SELECT is_featured FROM projects WHERE id = ?", [$id]);
    $newStatus = $project['is_featured'] ? 0 : 1;
    db()->update('projects', ['is_featured' => $newStatus], 'id = :id', ['id' => $id]);
    header('Location: projects.php?msg=updated');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Handle image upload
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['featured_image']);
        if (isset($upload['success'])) {
            // Delete old image if updating
            if (!empty($_POST['id'])) {
                $old = db()->fetch("SELECT featured_image FROM projects WHERE id = ?", [$_POST['id']]);
                if ($old && $old['featured_image']) {
                    $oldPath = UPLOAD_PATH . $old['featured_image'];
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
        $msg = 'updated';
    } else {
        db()->insert('projects', $data);
        $msg = 'created';
    }
    
    header("Location: projects.php?msg=$msg");
    exit;
}

// Get project for editing
$project = null;
if ($id > 0 && $action === 'edit') {
    $project = db()->fetch("SELECT * FROM projects WHERE id = ?", [$id]);
}

// Get all projects
$projects = db()->fetchAll("SELECT * FROM projects ORDER BY created_at DESC");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><?php echo $action === 'edit' ? 'Edit Project' : ($action === 'add' ? 'Add New Project' : 'All Projects'); ?></h2>
    <?php if ($action === 'list'): ?>
    <a href="?action=add" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        Add New Project
    </a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Project created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Project updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Project deleted successfully!';
        if ($_GET['msg'] === 'toggled') echo 'Project status updated!';
        ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Projects List -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th width="60">Image</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Technologies</th>
                    <th>Status</th>
                    <th>Featured</th>
                    <th>Date</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td>
                        <?php if ($project['featured_image']): ?>
                        <img src="<?php echo UPLOAD_URL . $project['featured_image']; ?>" 
                             alt="<?php echo $project['title']; ?>" 
                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                        <?php else: ?>
                        <div style="width: 50px; height: 50px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-image" style="color: #999;"></i>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($project['title']); ?></strong>
                        <br>
                        <small><?php echo htmlspecialchars(substr($project['short_description'] ?? '', 0, 50)); ?>...</small>
                    </td>
                    <td><?php echo htmlspecialchars($project['category'] ?: 'Uncategorized'); ?></td>
                    <td>
                        <div class="tech-tags">
                            <?php 
                            $techs = explode(',', $project['technologies'] ?? '');
                            foreach (array_slice($techs, 0, 2) as $tech): 
                            ?>
                            <span class="tech-tag"><?php echo trim($tech); ?></span>
                            <?php endforeach; ?>
                            <?php if (count($techs) > 2): ?>
                            <span class="tech-tag">+<?php echo count($techs) - 2; ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <a href="?toggle=<?php echo $project['id']; ?>" class="status-toggle" title="Toggle Status">
                            <span class="status-badge <?php echo $project['status']; ?>">
                                <?php echo ucfirst($project['status']); ?>
                            </span>
                        </a>
                    </td>
                    <td>
                        <a href="?featured=<?php echo $project['id']; ?>" class="featured-toggle" title="Toggle Featured">
                            <?php if ($project['is_featured']): ?>
                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                            <?php else: ?>
                            <i class="far fa-star" style="color: #999;"></i>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td>
                        <?php echo date('M d, Y', strtotime($project['created_at'])); ?>
                        <?php if ($project['completion_date']): ?>
                        <br><small>Completed: <?php echo date('M Y', strtotime($project['completion_date'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="?action=edit&id=<?php echo $project['id']; ?>" class="action-btn" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?delete=<?php echo $project['id']; ?>" class="action-btn delete-btn" 
                               onclick="return confirm('Are you sure you want to delete this project?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/project/<?php echo $project['slug']; ?>" target="_blank" class="action-btn" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($projects)): ?>
                <tr>
                    <td colspan="8" class="text-center">No projects found. Click "Add New Project" to create one.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Form -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <?php if ($project): ?>
            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Project Title *</label>
                        <input type="text" id="title" name="title" required 
                               value="<?php echo $project['title'] ?? ''; ?>"
                               placeholder="e.g., E-commerce Platform">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" 
                               value="<?php echo $project['category'] ?? ''; ?>"
                               placeholder="e.g., Web App, E-commerce">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="short_description">Short Description</label>
                    <textarea id="short_description" name="short_description" rows="3" 
                              placeholder="Brief overview of the project"><?php echo $project['short_description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="full_description">Full Description</label>
                    <textarea id="full_description" name="full_description" rows="8" 
                              placeholder="Detailed project description..."><?php echo $project['full_description'] ?? ''; ?></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Technologies & Details</h3>
                
                <div class="form-group">
                    <label for="technologies">Technologies Used</label>
                    <input type="text" id="technologies" name="technologies" 
                           value="<?php echo $project['technologies'] ?? ''; ?>"
                           placeholder="PHP, MySQL, JavaScript, React (comma separated)">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="completion_date">Completion Date</label>
                        <input type="date" id="completion_date" name="completion_date" 
                               value="<?php echo $project['completion_date'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="client_name">Client Name</label>
                        <input type="text" id="client_name" name="client_name" 
                               value="<?php echo $project['client_name'] ?? ''; ?>"
                               placeholder="e.g., ABC Corp">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="client_website">Client Website</label>
                    <input type="url" id="client_website" name="client_website" 
                           value="<?php echo $project['client_website'] ?? ''; ?>"
                           placeholder="https://client-website.com">
                </div>
            </div>
            
            <div class="form-section">
                <h3>Project Links</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="project_url">Live Project URL</label>
                        <input type="url" id="project_url" name="project_url" 
                               value="<?php echo $project['project_url'] ?? ''; ?>"
                               placeholder="https://example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="github_url">GitHub Repository URL</label>
                        <input type="url" id="github_url" name="github_url" 
                               value="<?php echo $project['github_url'] ?? ''; ?>"
                               placeholder="https://github.com/username/repo">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Media</h3>
                
                <div class="form-group">
                    <label for="featured_image">Featured Image</label>
                    <input type="file" id="featured_image" name="featured_image" accept="image/*">
                    <?php if ($project && $project['featured_image']): ?>
                    <div class="current-image">
                        <img src="<?php echo UPLOAD_URL . $project['featured_image']; ?>" 
                             alt="Current featured image" style="max-width: 200px; margin-top: 10px;">
                        <p><small>Current image</small></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Publishing Settings</h3>
                
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
                            <span class="checkbox-text">Feature this project on homepage</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $project ? 'Update Project' : 'Create Project'; ?>
                </button>
                <a href="projects.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.content-header h2 {
    font-size: 1.5rem;
    margin: 0;
}

.tech-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}

.tech-tag {
    padding: 2px 6px;
    background: var(--gray-200);
    border-radius: 4px;
    font-size: 0.7rem;
    color: var(--gray-700);
}

.status-toggle {
    text-decoration: none;
}

.featured-toggle {
    text-decoration: none;
    font-size: 1.1rem;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.form-section {
    background: var(--gray-100);
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.form-section h3 {
    font-size: 1.1rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-300);
}

.checkbox-group {
    display: flex;
    align-items: center;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.current-image {
    margin-top: 10px;
    padding: 10px;
    background: var(--gray-200);
    border-radius: 4px;
}

.current-image p {
    margin: 5px 0 0;
    font-size: 0.8rem;
    color: var(--gray-600);
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>