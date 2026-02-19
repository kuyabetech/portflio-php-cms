<?php
// admin/testimonials.php
// Testimonials Management

require_once '../includes/auth.php';
Auth::requireAuth();

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get image before deleting
    $testimonial = db()->fetch("SELECT client_image FROM testimonials WHERE id = ?", [$id]);
    if ($testimonial && $testimonial['client_image']) {
        $imagePath = UPLOAD_PATH . 'testimonials/' . $testimonial['client_image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    db()->delete('testimonials', 'id = ?', [$id]);
    header('Location: testimonials.php?msg=deleted');
    exit;
}

// Handle status update
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    db()->update('testimonials', ['status' => 'approved'], 'id = :id', ['id' => $id]);
    header('Location: testimonials.php?msg=approved');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'client_name' => sanitize($_POST['client_name']),
        'client_position' => sanitize($_POST['client_position']),
        'client_company' => sanitize($_POST['client_company']),
        'testimonial' => sanitize($_POST['testimonial']),
        'rating' => (int)$_POST['rating'],
        'project_id' => !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'status' => $_POST['status']
    ];
    
    // Handle image upload
    if (isset($_FILES['client_image']) && $_FILES['client_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['client_image'], 'testimonials/');
        if (isset($upload['success'])) {
            $data['client_image'] = $upload['filename'];
        }
    }
    
    if (!empty($_POST['id'])) {
        db()->update('testimonials', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('testimonials', $data);
        $msg = 'created';
    }
    
    header("Location: testimonials.php?msg=$msg");
    exit;
}

// Get testimonial for editing
$testimonial = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $testimonial = db()->fetch("SELECT * FROM testimonials WHERE id = ?", [$id]);
}

// Get all testimonials
$testimonials = db()->fetchAll(
    "SELECT t.*, p.title as project_title 
     FROM testimonials t 
     LEFT JOIN projects p ON t.project_id = p.id 
     ORDER BY t.created_at DESC"
);

// Get projects for dropdown
$projects = db()->fetchAll("SELECT id, title FROM projects ORDER BY title");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Testimonials - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h2><?php echo SITE_NAME; ?></h2>
                <p>Admin Panel</p>
            </div>
            
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="projects.php" class="nav-item">
                    <i class="fas fa-code-branch"></i>
                    <span>Projects</span>
                </a>
                
                <a href="skills.php" class="nav-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Skills</span>
                </a>
                
                <a href="testimonials.php" class="nav-item active">
                    <i class="fas fa-star"></i>
                    <span>Testimonials</span>
                </a>
                
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
                
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                        <span>Administrator</span>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-main">
            <header class="admin-header">
                <h1 class="page-title">
                    <?php echo $testimonial ? 'Edit Testimonial' : 'Manage Testimonials'; ?>
                </h1>
                <div class="header-actions">
                    <?php if (!$testimonial): ?>
                    <button class="btn btn-primary" onclick="showAddForm()">
                        <i class="fas fa-plus"></i>
                        Add New Testimonial
                    </button>
                    <?php endif; ?>
                </div>
            </header>
            
            <div class="admin-content">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        if ($_GET['msg'] === 'created') echo 'Testimonial created successfully!';
                        if ($_GET['msg'] === 'updated') echo 'Testimonial updated successfully!';
                        if ($_GET['msg'] === 'deleted') echo 'Testimonial deleted successfully!';
                        if ($_GET['msg'] === 'approved') echo 'Testimonial approved successfully!';
                        ?>
                    </div>
                <?php endif; ?>
                
                <!-- Add/Edit Form -->
                <div class="form-container" id="testimonialForm" style="<?php echo $testimonial ? 'display: block;' : 'display: none;'; ?>">
                    <h2><?php echo $testimonial ? 'Edit Testimonial' : 'Add New Testimonial'; ?></h2>
                    
                    <form method="POST" enctype="multipart/form-data" class="admin-form">
                        <?php if ($testimonial): ?>
                        <input type="hidden" name="id" value="<?php echo $testimonial['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="client_name">Client Name *</label>
                                <input type="text" id="client_name" name="client_name" required 
                                       value="<?php echo $testimonial['client_name'] ?? ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="rating">Rating</label>
                                <select id="rating" name="rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?php echo $i; ?>" 
                                        <?php echo ($testimonial['rating'] ?? 5) == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="client_position">Position</label>
                                <input type="text" id="client_position" name="client_position" 
                                       value="<?php echo $testimonial['client_position'] ?? ''; ?>"
                                       placeholder="e.g., CEO, Founder">
                            </div>
                            
                            <div class="form-group">
                                <label for="client_company">Company</label>
                                <input type="text" id="client_company" name="client_company" 
                                       value="<?php echo $testimonial['client_company'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="testimonial">Testimonial *</label>
                            <textarea id="testimonial" name="testimonial" rows="5" required><?php echo $testimonial['testimonial'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="project_id">Related Project</label>
                                <select id="project_id" name="project_id">
                                    <option value="">-- Select Project --</option>
                                    <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>"
                                        <?php echo ($testimonial['project_id'] ?? '') == $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="client_image">Client Photo</label>
                                <input type="file" id="client_image" name="client_image" accept="image/*">
                                <?php if ($testimonial && $testimonial['client_image']): ?>
                                <div class="current-image">
                                    <img src="<?php echo UPLOAD_URL . 'testimonials/' . $testimonial['client_image']; ?>" 
                                         alt="Client photo" style="max-width: 100px; margin-top: 10px;">
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="pending" <?php echo ($testimonial['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo ($testimonial['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                </select>
                            </div>
                            
                            <div class="form-group checkbox">
                                <label>
                                    <input type="checkbox" name="is_featured" 
                                           <?php echo ($testimonial['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                                    Feature this testimonial
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?php echo $testimonial ? 'Update Testimonial' : 'Save Testimonial'; ?>
                            </button>
                            <?php if ($testimonial): ?>
                            <a href="testimonials.php" class="btn btn-outline">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <!-- Testimonials List -->
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Client</th>
                                <th>Testimonial</th>
                                <th>Rating</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($testimonials as $t): ?>
                            <tr class="<?php echo $t['status'] === 'pending' ? 'unread' : ''; ?>">
                                <td>
                                    <?php if ($t['client_image']): ?>
                                    <img src="<?php echo UPLOAD_URL . 'testimonials/' . $t['client_image']; ?>" 
                                         alt="<?php echo $t['client_name']; ?>" 
                                         style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: #eee; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user" style="color: #999;"></i>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($t['client_name']); ?></strong>
                                    <br>
                                    <small><?php echo htmlspecialchars($t['client_position']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(substr($t['testimonial'], 0, 100)); ?>...
                                </td>
                                <td>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $t['rating']): ?>
                                        <i class="fas fa-star" style="color: #fbbf24;"></i>
                                        <?php else: ?>
                                        <i class="far fa-star" style="color: #ddd;"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </td>
                                <td><?php echo htmlspecialchars($t['project_title'] ?: '-'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $t['status']; ?>">
                                        <?php echo ucfirst($t['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($t['is_featured']): ?>
                                    <i class="fas fa-star" style="color: #fbbf24;"></i>
                                    <?php else: ?>
                                    <i class="far fa-star" style="color: #999;"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                <td>
                                    <?php if ($t['status'] === 'pending'): ?>
                                    <a href="?approve=<?php echo $t['id']; ?>" class="action-btn" title="Approve">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="?edit=<?php echo $t['id']; ?>" class="action-btn" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $t['id']; ?>" class="action-btn delete-btn" 
                                       onclick="return confirm('Are you sure you want to delete this testimonial?')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($testimonials)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No testimonials found. Click "Add New Testimonial" to create one.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    function showAddForm() {
        document.getElementById('testimonialForm').style.display = 'block';
        document.getElementById('testimonialForm').scrollIntoView({ behavior: 'smooth' });
    }
    </script>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>