<?php
// admin/blog-categories.php
// Blog Categories Management

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('blog_categories', 'id = ?', [$id]);
    header('Location: blog.php?type=categories&msg=deleted');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $data = [
        'name' => sanitize($_POST['name']),
        'slug' => createSlug($_POST['name']),
        'description' => sanitize($_POST['description']),
        'parent_id' => (int)$_POST['parent_id'],
        'display_order' => (int)$_POST['display_order'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    if (!empty($_POST['id'])) {
        db()->update('blog_categories', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('blog_categories', $data);
        $msg = 'created';
    }
    
    header("Location: blog.php?type=categories&msg=$msg");
    exit;
}

// Get all categories
$categories = db()->fetchAll("SELECT * FROM blog_categories ORDER BY display_order, name");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>Blog Categories</h2>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="showCategoryForm()">
            <i class="fas fa-plus"></i>
            Add Category
        </button>
        <a href="blog.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Posts
        </a>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Category created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Category updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Category deleted successfully!';
        ?>
    </div>
<?php endif; ?>

<!-- Category Form -->
<div class="form-container" id="categoryForm" style="display: none;">
    <h3>Add/Edit Category</h3>
    <form method="POST" class="admin-form">
        <input type="hidden" name="id" id="cat_id" value="">
        
        <div class="form-row">
            <div class="form-group">
                <label for="cat_name">Category Name *</label>
                <input type="text" id="cat_name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="parent_id">Parent Category</label>
                <select id="parent_id" name="parent_id">
                    <option value="0">None (Top Level)</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>">
                        <?php echo str_repeat('—', $cat['parent_id'] ? 1 : 0) . ' ' . htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="cat_description">Description</label>
            <textarea id="cat_description" name="description" rows="3"></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="display_order">Display Order</label>
                <input type="number" id="display_order" name="display_order" value="0" min="0">
            </div>
            
            <div class="form-group checkbox-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" checked>
                    <span class="checkbox-text">Active</span>
                </label>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="save_category" class="btn btn-primary">Save Category</button>
            <button type="button" class="btn btn-outline" onclick="hideCategoryForm()">Cancel</button>
        </div>
    </form>
</div>

<!-- Categories List -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Description</th>
                <th>Posts</th>
                <th>Status</th>
                <th>Order</th>
                <th width="120">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
            <?php
            $postCount = db()->fetch("SELECT COUNT(*) as count FROM blog_posts WHERE category_id = ?", [$cat['id']])['count'];
            ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                    <?php if ($cat['parent_id'] > 0): ?>
                    <br><small>Subcategory of: <?php 
                        $parent = db()->fetch("SELECT name FROM blog_categories WHERE id = ?", [$cat['parent_id']]);
                        echo htmlspecialchars($parent['name'] ?? '');
                    ?></small>
                    <?php endif; ?>
                </td>
                <td><code><?php echo $cat['slug']; ?></code></td>
                <td><?php echo htmlspecialchars(substr($cat['description'] ?? '', 0, 50)); ?></td>
                <td><?php echo $postCount; ?></td>
                <td>
                    <span class="status-badge <?php echo $cat['is_active'] ? 'published' : 'draft'; ?>">
                        <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </td>
                <td><?php echo $cat['display_order']; ?></td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?type=categories&delete=<?php echo $cat['id']; ?>" 
                           class="action-btn delete-btn"
                           onclick="return confirm('Delete this category? Posts will be uncategorized.')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($categories)): ?>
            <tr>
                <td colspan="7" class="text-center">No categories found. Click "Add Category" to create one.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.action-buttons {
    display: flex;
    gap: 5px;
}

.form-container {
    margin-bottom: 30px;
}
</style>

<script>
function showCategoryForm() {
    document.getElementById('categoryForm').style.display = 'block';
    document.getElementById('cat_id').value = '';
    document.getElementById('cat_name').value = '';
    document.getElementById('cat_description').value = '';
    document.getElementById('parent_id').value = '0';
    document.getElementById('display_order').value = '0';
    document.querySelector('#categoryForm input[name="is_active"]').checked = true;
}

function hideCategoryForm() {
    document.getElementById('categoryForm').style.display = 'none';
}

function editCategory(category) {
    showCategoryForm();
    document.getElementById('cat_id').value = category.id;
    document.getElementById('cat_name').value = category.name;
    document.getElementById('cat_description').value = category.description || '';
    document.getElementById('parent_id').value = category.parent_id;
    document.getElementById('display_order').value = category.display_order;
    document.querySelector('#categoryForm input[name="is_active"]').checked = category.is_active == 1;
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>