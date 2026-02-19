<?php
// admin/blog-tags.php
// Blog Tags Management

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('blog_tags', 'id = ?', [$id]);
    header('Location: blog.php?type=tags&msg=deleted');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tag'])) {
    $data = [
        'name' => sanitize($_POST['name']),
        'slug' => createSlug($_POST['name'])
    ];
    
    if (!empty($_POST['id'])) {
        db()->update('blog_tags', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('blog_tags', $data);
        $msg = 'created';
    }
    
    header("Location: blog.php?type=tags&msg=$msg");
    exit;
}

// Get all tags with post counts
$tags = db()->fetchAll("
    SELECT t.*, COUNT(pt.post_id) as post_count 
    FROM blog_tags t
    LEFT JOIN blog_post_tags pt ON t.id = pt.tag_id
    GROUP BY t.id
    ORDER BY post_count DESC, t.name
");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>Blog Tags</h2>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="showTagForm()">
            <i class="fas fa-plus"></i>
            Add Tag
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
        if ($_GET['msg'] === 'created') echo 'Tag created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Tag updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Tag deleted successfully!';
        ?>
    </div>
<?php endif; ?>

<!-- Tag Form -->
<div class="form-container" id="tagForm" style="display: none;">
    <h3>Add/Edit Tag</h3>
    <form method="POST" class="admin-form">
        <input type="hidden" name="id" id="tag_id" value="">
        
        <div class="form-group">
            <label for="tag_name">Tag Name *</label>
            <input type="text" id="tag_name" name="name" required>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="save_tag" class="btn btn-primary">Save Tag</button>
            <button type="button" class="btn btn-outline" onclick="hideTagForm()">Cancel</button>
        </div>
    </form>
</div>

<!-- Tags List -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>Posts</th>
                <th>Created</th>
                <th width="120">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tags as $tag): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($tag['name']); ?></strong></td>
                <td><code><?php echo $tag['slug']; ?></code></td>
                <td><?php echo $tag['post_count']; ?></td>
                <td><?php echo date('M d, Y', strtotime($tag['created_at'])); ?></td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="editTag(<?php echo htmlspecialchars(json_encode($tag)); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?type=tags&delete=<?php echo $tag['id']; ?>" 
                           class="action-btn delete-btn"
                           onclick="return confirm('Delete this tag? It will be removed from all posts.')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($tags)): ?>
            <tr>
                <td colspan="5" class="text-center">No tags found. Click "Add Tag" to create one.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function showTagForm() {
    document.getElementById('tagForm').style.display = 'block';
    document.getElementById('tag_id').value = '';
    document.getElementById('tag_name').value = '';
}

function hideTagForm() {
    document.getElementById('tagForm').style.display = 'none';
}

function editTag(tag) {
    showTagForm();
    document.getElementById('tag_id').value = tag.id;
    document.getElementById('tag_name').value = tag.name;
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>