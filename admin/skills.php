<?php
// admin/skills.php
// Skills Management - FULLY RESPONSIVE

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Skills Management';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Skills']
];

if ($action === 'add') {
    $breadcrumbs[] = ['title' => 'Add New Skill'];
} elseif ($action === 'edit') {
    $breadcrumbs[] = ['title' => 'Edit Skill'];
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('skills', 'id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Skill deleted successfully'];
    header('Location: skills.php');
    exit;
}

// Handle visibility toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $skill = db()->fetch("SELECT is_visible FROM skills WHERE id = ?", [$id]);
    $newStatus = $skill['is_visible'] ? 0 : 1;
    db()->update('skills', ['is_visible' => $newStatus], 'id = :id', ['id' => $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Skill visibility updated'];
    header('Location: skills.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_skill'])) {
    $data = [
        'name' => sanitize($_POST['name']),
        'category' => sanitize($_POST['category']),
        'proficiency' => (int)$_POST['proficiency'],
        'icon_class' => sanitize($_POST['icon_class']),
        'years_experience' => (float)$_POST['years_experience'],
        'display_order' => (int)$_POST['display_order'],
        'is_visible' => isset($_POST['is_visible']) ? 1 : 0
    ];
    
    // Validate required fields
    $errors = [];
    if (empty($data['name'])) {
        $errors[] = 'Skill name is required';
    }
    
    if (!empty($errors)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => implode('<br>', $errors)];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    if (!empty($_POST['id'])) {
        db()->update('skills', $data, 'id = :id', ['id' => $_POST['id']]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Skill updated successfully'];
    } else {
        db()->insert('skills', $data);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Skill created successfully'];
    }
    
    header('Location: skills.php');
    exit;
}

// Get skill for editing
$skill = null;
if ($id > 0 && $action === 'edit') {
    $skill = db()->fetch("SELECT * FROM skills WHERE id = ?", [$id]);
}

// Get all skills
$skills = db()->fetchAll("SELECT * FROM skills ORDER BY display_order ASC, name ASC") ?? [];

// Get statistics
$stats = [
    'total' => count($skills),
    'visible' => count(array_filter($skills, fn($s) => $s['is_visible'])),
    'hidden' => count(array_filter($skills, fn($s) => !$s['is_visible']))
];

// Include header
require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="content-header">
    <h1>
        <i class="fas fa-code"></i> 
        <?php 
        if ($action === 'edit') echo 'Edit Skill';
        elseif ($action === 'add') echo 'Add New Skill';
        else echo 'Skills Management';
        ?>
        <?php if ($action === 'list' && $stats['total'] > 0): ?>
        <span class="header-badge"><?php echo $stats['visible']; ?> visible</span>
        <?php endif; ?>
    </h1>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Skill
        </a>
        <?php else: ?>
        <a href="skills.php" class="btn btn-outline">
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
                <i class="fas fa-code"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Total Skills</span>
                <span class="stat-value"><?php echo $stats['total']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-eye"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Visible</span>
                <span class="stat-value"><?php echo $stats['visible']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-eye-slash"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Hidden</span>
                <span class="stat-value"><?php echo $stats['hidden']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Avg Proficiency</span>
                <span class="stat-value">
                    <?php 
                    $avg = array_sum(array_column($skills, 'proficiency')) / max(1, $stats['total']);
                    echo round($avg) . '%';
                    ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Desktop Table View -->
    <div class="desktop-table">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="50">Icon</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Proficiency</th>
                        <th>Experience</th>
                        <th width="80">Order</th>
                        <th width="100">Status</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skills as $skill): ?>
                    <tr>
                        <td class="icon-cell">
                            <i class="<?php echo $skill['icon_class'] ?: 'fas fa-code'; ?>"></i>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($skill['name']); ?></strong>
                        </td>
                        <td>
                            <span class="category-badge <?php echo $skill['category']; ?>">
                                <?php echo ucfirst($skill['category']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="progress-indicator">
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?php echo $skill['proficiency']; ?>%;"></div>
                                </div>
                                <span class="progress-value"><?php echo $skill['proficiency']; ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="experience-badge">
                                <?php echo $skill['years_experience']; ?> <?php echo $skill['years_experience'] == 1 ? 'year' : 'years'; ?>
                            </span>
                        </td>
                        <td class="text-center"><?php echo $skill['display_order']; ?></td>
                        <td>
                            <a href="?toggle=<?php echo $skill['id']; ?>" class="status-toggle" title="Click to toggle visibility">
                                <span class="status-badge <?php echo $skill['is_visible'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $skill['is_visible'] ? 'Visible' : 'Hidden'; ?>
                                </span>
                            </a>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?action=edit&id=<?php echo $skill['id']; ?>" class="action-btn" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?php echo $skill['id']; ?>" class="action-btn delete-btn" 
                                   onclick="return confirm('Are you sure you want to delete this skill?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($skills)): ?>
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-code"></i>
                                <h3>No Skills Found</h3>
                                <p>Get started by adding your first skill.</p>
                                <a href="?action=add" class="btn btn-primary">Add New Skill</a>
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
        <?php foreach ($skills as $skill): ?>
        <div class="skill-card <?php echo !$skill['is_visible'] ? 'hidden-skill' : ''; ?>">
            <div class="card-header">
                <div class="skill-icon">
                    <i class="<?php echo $skill['icon_class'] ?: 'fas fa-code'; ?>"></i>
                </div>
                <div class="skill-title">
                    <h3><?php echo htmlspecialchars($skill['name']); ?></h3>
                    <span class="category-badge <?php echo $skill['category']; ?>">
                        <?php echo ucfirst($skill['category']); ?>
                    </span>
                </div>
                <span class="status-badge <?php echo $skill['is_visible'] ? 'active' : 'inactive'; ?>">
                    <?php echo $skill['is_visible'] ? 'Visible' : 'Hidden'; ?>
                </span>
            </div>
            
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Proficiency:</span>
                    <div class="progress-indicator">
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: <?php echo $skill['proficiency']; ?>%;"></div>
                        </div>
                        <span class="progress-value"><?php echo $skill['proficiency']; ?>%</span>
                    </div>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Experience:</span>
                    <span class="experience-badge">
                        <?php echo $skill['years_experience']; ?> <?php echo $skill['years_experience'] == 1 ? 'year' : 'years'; ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Display Order:</span>
                    <span class="order-badge"><?php echo $skill['display_order']; ?></span>
                </div>
            </div>
            
            <div class="card-footer">
                <div class="action-buttons">
                    <a href="?action=edit&id=<?php echo $skill['id']; ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?toggle=<?php echo $skill['id']; ?>" class="btn btn-sm btn-outline">
                        <i class="fas <?php echo $skill['is_visible'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i>
                        <?php echo $skill['is_visible'] ? 'Hide' : 'Show'; ?>
                    </a>
                    <a href="?delete=<?php echo $skill['id']; ?>" class="btn btn-sm btn-outline-danger" 
                       onclick="return confirm('Are you sure you want to delete this skill?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($skills)): ?>
        <div class="empty-state">
            <i class="fas fa-code"></i>
            <h3>No Skills Found</h3>
            <p>Get started by adding your first skill.</p>
            <a href="?action=add" class="btn btn-primary">Add New Skill</a>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Form -->
    <div class="form-container">
        <form method="POST" class="admin-form" id="skillForm">
            <?php if ($skill): ?>
            <input type="hidden" name="id" value="<?php echo $skill['id']; ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <h2><i class="fas fa-info-circle"></i> Basic Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Skill Name <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo htmlspecialchars($skill['name'] ?? ''); ?>"
                               placeholder="e.g., PHP, JavaScript, Project Management">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="technical" <?php echo ($skill['category'] ?? '') === 'technical' ? 'selected' : ''; ?>>Technical</option>
                            <option value="professional" <?php echo ($skill['category'] ?? '') === 'professional' ? 'selected' : ''; ?>>Professional</option>
                            <option value="language" <?php echo ($skill['category'] ?? '') === 'language' ? 'selected' : ''; ?>>Language</option>
                            <option value="creative" <?php echo ($skill['category'] ?? '') === 'creative' ? 'selected' : ''; ?>>Creative</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-chart-bar"></i> Proficiency & Experience</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="proficiency">Proficiency (%)</label>
                        <div class="range-wrapper">
                            <input type="range" id="proficiency" name="proficiency" 
                                   min="0" max="100" step="5" value="<?php echo $skill['proficiency'] ?? 80; ?>"
                                   oninput="document.getElementById('proficiencyValue').textContent = this.value + '%'">
                            <output id="proficiencyValue"><?php echo $skill['proficiency'] ?? 80; ?>%</output>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="years_experience">Years of Experience</label>
                        <input type="number" id="years_experience" name="years_experience" 
                               step="0.5" min="0" max="50" value="<?php echo $skill['years_experience'] ?? 0; ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-paint-brush"></i> Appearance & Display</h2>
                
                <div class="form-group">
                    <label for="icon_class">Icon Class</label>
                    <div class="icon-input-wrapper">
                        <input type="text" id="icon_class" name="icon_class" 
                               value="<?php echo htmlspecialchars($skill['icon_class'] ?? 'fas fa-code'); ?>"
                               placeholder="e.g., fab fa-php, fas fa-database">
                        <span class="icon-preview">
                            <i class="<?php echo htmlspecialchars($skill['icon_class'] ?? 'fas fa-code'); ?>"></i>
                        </span>
                    </div>
                    <p class="help-text">
                        <i class="fas fa-info-circle"></i>
                        FontAwesome icon classes. <a href="https://fontawesome.com/icons" target="_blank">Browse icons</a>
                    </p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="display_order">Display Order</label>
                        <input type="number" id="display_order" name="display_order" 
                               min="0" value="<?php echo $skill['display_order'] ?? 0; ?>">
                        <p class="help-text">Lower numbers appear first</p>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_visible" 
                                   <?php echo ($skill['is_visible'] ?? 1) ? 'checked' : ''; ?>>
                            <span class="checkbox-text">
                                <i class="fas fa-eye"></i>
                                Visible on website
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_skill" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    <?php echo $skill ? 'Update Skill' : 'Save Skill'; ?>
                </button>
                <a href="skills.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
/* ========================================
   SKILLS PAGE - RESPONSIVE STYLES
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
    min-width: 800px;
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

.icon-cell i {
    font-size: 24px;
    color: var(--primary);
    width: 32px;
    text-align: center;
}

/* Category Badge */
.category-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.category-badge.technical { background: rgba(37,99,235,0.1); color: #2563eb; }
.category-badge.professional { background: rgba(124,58,237,0.1); color: #7c3aed; }
.category-badge.language { background: rgba(16,185,129,0.1); color: #10b981; }
.category-badge.creative { background: rgba(245,158,11,0.1); color: #f59e0b; }

/* Experience Badge */
.experience-badge {
    display: inline-block;
    padding: 4px 8px;
    background: var(--gray-200);
    border-radius: 12px;
    font-size: 12px;
    color: var(--gray-700);
}

.order-badge {
    display: inline-block;
    padding: 4px 12px;
    background: var(--primary);
    color: white;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

/* Progress Indicator */
.progress-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 120px;
}

.progress-bar-bg {
    flex: 1;
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-value {
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
    min-width: 40px;
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

.status-badge.active { background: rgba(16,185,129,0.1); color: #10b981; }
.status-badge.inactive { background: rgba(107,114,128,0.1); color: #6b7280; }

.status-toggle {
    text-decoration: none;
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

.skill-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    border-left: 4px solid var(--primary);
}

.skill-card.hidden-skill {
    border-left-color: var(--gray-400);
    opacity: 0.8;
}

.card-header {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.skill-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.skill-title {
    flex: 1;
}

.skill-title h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--dark);
}

.card-body {
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-200);
    gap: 12px;
    flex-wrap: wrap;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 13px;
    color: var(--gray-500);
    min-width: 80px;
}

.card-footer .action-buttons {
    justify-content: flex-end;
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
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

/* Range Input */
.range-wrapper {
    display: flex;
    align-items: center;
    gap: 16px;
}

.range-wrapper input[type="range"] {
    flex: 1;
    height: 6px;
    -webkit-appearance: none;
    background: var(--gray-200);
    border-radius: 3px;
}

.range-wrapper input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    background: var(--primary);
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s ease;
}

.range-wrapper input[type="range"]::-webkit-slider-thumb:hover {
    transform: scale(1.2);
}

.range-wrapper output {
    min-width: 50px;
    font-weight: 600;
    color: var(--primary);
    font-size: 16px;
}

/* Icon Input */
.icon-input-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
}

.icon-input-wrapper input {
    flex: 1;
}

.icon-preview {
    width: 44px;
    height: 44px;
    background: var(--gray-100);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
    border: 2px solid var(--gray-200);
}

/* Help Text */
.help-text {
    font-size: 12px;
    color: var(--gray-500);
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.help-text a {
    color: var(--primary);
    text-decoration: none;
}

.help-text a:hover {
    text-decoration: underline;
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
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .range-wrapper {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .range-wrapper output {
        align-self: flex-end;
    }
    
    .icon-input-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
    
    .icon-preview {
        align-self: center;
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
    
    .info-row {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .info-label {
        width: auto;
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
}
</style>

<script>
// File input and form handling
document.addEventListener('DOMContentLoaded', function() {
    // Update icon preview
    const iconInput = document.getElementById('icon_class');
    const iconPreview = document.querySelector('.icon-preview i');
    
    if (iconInput && iconPreview) {
        iconInput.addEventListener('input', function() {
            iconPreview.className = this.value || 'fas fa-code';
        });
    }
    
    // Form validation
    const skillForm = document.getElementById('skillForm');
    if (skillForm) {
        skillForm.addEventListener('submit', function(e) {
            const name = document.getElementById('name');
            
            if (!name.value.trim()) {
                alert('Skill name is required');
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