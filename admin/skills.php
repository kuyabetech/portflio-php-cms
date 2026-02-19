<?php
// admin/skills.php
// Skills Management

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
    header('Location: skills.php?msg=deleted');
    exit;
}

// Handle visibility toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $skill = db()->fetch("SELECT is_visible FROM skills WHERE id = ?", [$id]);
    $newStatus = $skill['is_visible'] ? 0 : 1;
    db()->update('skills', ['is_visible' => $newStatus], 'id = :id', ['id' => $id]);
    header('Location: skills.php?msg=toggled');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => sanitize($_POST['name']),
        'category' => sanitize($_POST['category']),
        'proficiency' => (int)$_POST['proficiency'],
        'icon_class' => sanitize($_POST['icon_class']),
        'years_experience' => (float)$_POST['years_experience'],
        'display_order' => (int)$_POST['display_order'],
        'is_visible' => isset($_POST['is_visible']) ? 1 : 0
    ];
    
    if (!empty($_POST['id'])) {
        db()->update('skills', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('skills', $data);
        $msg = 'created';
    }
    
    header("Location: skills.php?msg=$msg");
    exit;
}

// Get skill for editing
$skill = null;
if ($id > 0 && $action === 'edit') {
    $skill = db()->fetch("SELECT * FROM skills WHERE id = ?", [$id]);
}

// Get all skills
$skills = db()->fetchAll("SELECT * FROM skills ORDER BY display_order ASC, name ASC");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><?php echo $action === 'edit' ? 'Edit Skill' : ($action === 'add' ? 'Add New Skill' : 'Manage Skills'); ?></h2>
    <?php if ($action === 'list'): ?>
    <button class="btn btn-primary" onclick="showAddForm()">
        <i class="fas fa-plus"></i>
        Add New Skill
    </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Skill created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Skill updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Skill deleted successfully!';
        if ($_GET['msg'] === 'toggled') echo 'Skill visibility updated!';
        ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Skills List -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Icon</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Proficiency</th>
                    <th>Experience</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($skills as $skill): ?>
                <tr>
                    <td>
                        <i class="<?php echo $skill['icon_class'] ?: 'fas fa-code'; ?>" 
                           style="font-size: 24px; color: var(--primary);"></i>
                    </td>
                    <td><strong><?php echo htmlspecialchars($skill['name']); ?></strong></td>
                    <td><?php echo ucfirst($skill['category']); ?></td>
                    <td>
                        <div class="progress-indicator">
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?php echo $skill['proficiency']; ?>%;"></div>
                            </div>
                            <span><?php echo $skill['proficiency']; ?>%</span>
                        </div>
                    </td>
                    <td><?php echo $skill['years_experience']; ?> years</td>
                    <td><?php echo $skill['display_order']; ?></td>
                    <td>
                        <a href="?toggle=<?php echo $skill['id']; ?>" class="status-toggle">
                            <span class="status-badge <?php echo $skill['is_visible'] ? 'published' : 'draft'; ?>">
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
                    <td colspan="8" class="text-center">No skills found. Click "Add New Skill" to create one.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Form -->
    <div class="form-container" id="skillForm">
        <form method="POST" class="admin-form">
            <?php if ($skill): ?>
            <input type="hidden" name="id" value="<?php echo $skill['id']; ?>">
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Skill Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo $skill['name'] ?? ''; ?>"
                           placeholder="e.g., PHP, JavaScript">
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="technical" <?php echo ($skill['category'] ?? '') === 'technical' ? 'selected' : ''; ?>>Technical</option>
                        <option value="professional" <?php echo ($skill['category'] ?? '') === 'professional' ? 'selected' : ''; ?>>Professional</option>
                        <option value="language" <?php echo ($skill['category'] ?? '') === 'language' ? 'selected' : ''; ?>>Language</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="proficiency">Proficiency (%)</label>
                    <input type="range" id="proficiency" name="proficiency" 
                           min="0" max="100" value="<?php echo $skill['proficiency'] ?? 80; ?>"
                           oninput="this.nextElementSibling.value = this.value">
                    <output><?php echo $skill['proficiency'] ?? 80; ?>%</output>
                </div>
                
                <div class="form-group">
                    <label for="years_experience">Years Experience</label>
                    <input type="number" id="years_experience" name="years_experience" 
                           step="0.5" min="0" value="<?php echo $skill['years_experience'] ?? 0; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="icon_class">Icon Class</label>
                <input type="text" id="icon_class" name="icon_class" 
                       value="<?php echo $skill['icon_class'] ?? 'fas fa-code'; ?>"
                       placeholder="e.g., fab fa-php, fas fa-database">
                <small>FontAwesome icon classes. <a href="https://fontawesome.com/icons" target="_blank">Browse icons</a></small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="display_order">Display Order</label>
                    <input type="number" id="display_order" name="display_order" 
                           min="0" value="<?php echo $skill['display_order'] ?? 0; ?>">
                </div>
                
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_visible" 
                               <?php echo ($skill['is_visible'] ?? 1) ? 'checked' : ''; ?>>
                        <span class="checkbox-text">Visible on website</span>
                    </label>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $skill ? 'Update Skill' : 'Save Skill'; ?>
                </button>
                <a href="skills.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
.progress-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
}

.progress-bar-bg {
    width: 100px;
    height: 6px;
    background: var(--gray-200);
    border-radius: 3px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 3px;
    transition: width 0.3s ease;
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

.checkbox-label input[type="range"] {
    margin-right: 10px;
}

.checkbox-label output {
    min-width: 40px;
}
</style>

<script>
function showAddForm() {
    window.location.href = '?action=add';
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>