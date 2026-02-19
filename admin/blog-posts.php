<?php
// admin/blog-posts.php
// Blog Posts Management

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get image before deleting
    $post = db()->fetch("SELECT featured_image FROM blog_posts WHERE id = ?", [$id]);
    if ($post && $post['featured_image']) {
        $imagePath = UPLOAD_PATH . 'blog/' . $post['featured_image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
    db()->delete('blog_posts', 'id = ?', [$id]);
    header('Location: blog.php?msg=deleted');
    exit;
}

// Handle status update
if (isset($_GET['publish'])) {
    $id = (int)$_GET['publish'];
    db()->update('blog_posts', [
        'status' => 'published',
        'published_at' => date('Y-m-d H:i:s')
    ], 'id = :id', ['id' => $id]);
    header('Location: blog.php?msg=published');
    exit;
}

// Handle featured toggle
if (isset($_GET['featured'])) {
    $id = (int)$_GET['featured'];
    $post = db()->fetch("SELECT is_featured FROM blog_posts WHERE id = ?", [$id]);
    $newStatus = $post['is_featured'] ? 0 : 1;
    db()->update('blog_posts', ['is_featured' => $newStatus], 'id = :id', ['id' => $id]);
    header('Location: blog.php?msg=updated');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title' => sanitize($_POST['title']),
        'slug' => createSlug($_POST['title']),
        'excerpt' => sanitize($_POST['excerpt']),
        'content' => $_POST['content'],
        'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
        'author_id' => $_SESSION['user_id'],
        'reading_time' => calculateReadingTime($_POST['content']),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'status' => $_POST['status'],
        'meta_title' => sanitize($_POST['meta_title']),
        'meta_description' => sanitize($_POST['meta_description']),
        'meta_keywords' => sanitize($_POST['meta_keywords']),
        'allow_comments' => isset($_POST['allow_comments']) ? 1 : 0
    ];
    
    if ($_POST['status'] === 'published' && empty($_POST['published_at'])) {
        $data['published_at'] = date('Y-m-d H:i:s');
    } elseif (!empty($_POST['published_at'])) {
        $data['published_at'] = $_POST['published_at'];
    }
    
    // Handle featured image upload
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['featured_image'], 'blog/');
        if (isset($upload['success'])) {
            // Delete old image if updating
            if (!empty($_POST['id'])) {
                $old = db()->fetch("SELECT featured_image FROM blog_posts WHERE id = ?", [$_POST['id']]);
                if ($old && $old['featured_image']) {
                    $oldPath = UPLOAD_PATH . 'blog/' . $old['featured_image'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
            }
            $data['featured_image'] = $upload['filename'];
        }
    }
    
    if (!empty($_POST['id'])) {
        db()->update('blog_posts', $data, 'id = :id', ['id' => $_POST['id']]);
        $postId = $_POST['id'];
        $msg = 'updated';
    } else {
        $postId = db()->insert('blog_posts', $data);
        $msg = 'created';
    }
    
    // Handle tags
    if (!empty($_POST['tags'])) {
        // Delete existing tags
        db()->delete('blog_post_tags', 'post_id = ?', [$postId]);
        
        // Add new tags
        $tags = explode(',', $_POST['tags']);
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if (!empty($tagName)) {
                $tagSlug = createSlug($tagName);
                // Check if tag exists
                $tag = db()->fetch("SELECT id FROM blog_tags WHERE slug = ?", [$tagSlug]);
                if ($tag) {
                    $tagId = $tag['id'];
                } else {
                    $tagId = db()->insert('blog_tags', [
                        'name' => $tagName,
                        'slug' => $tagSlug
                    ]);
                }
                db()->insert('blog_post_tags', [
                    'post_id' => $postId,
                    'tag_id' => $tagId
                ]);
            }
        }
    }
    
    header("Location: blog.php?msg=$msg");
    exit;
}

// Helper function to calculate reading time
function calculateReadingTime($content) {
    $words = str_word_count(strip_tags($content));
    $minutes = ceil($words / 200); // Average reading speed: 200 words per minute
    return max(1, $minutes);
}

// Get post for editing
$post = null;
if ($id > 0 && $action === 'edit') {
    $post = db()->fetch("SELECT * FROM blog_posts WHERE id = ?", [$id]);
    // Get tags for this post
    $tags = db()->fetchAll(
        "SELECT t.name FROM blog_tags t 
         JOIN blog_post_tags pt ON t.id = pt.tag_id 
         WHERE pt.post_id = ?",
        [$id]
    );
    $post['tags'] = implode(', ', array_column($tags, 'name'));
}

// Get all posts
$posts = db()->fetchAll(
    "SELECT p.*, c.name as category_name, u.username as author_name,
     (SELECT COUNT(*) FROM blog_comments WHERE post_id = p.id AND is_approved = 1) as comment_count
     FROM blog_posts p
     LEFT JOIN blog_categories c ON p.category_id = c.id
     LEFT JOIN users u ON p.author_id = u.id
     ORDER BY p.created_at DESC"
);

// Get categories for dropdown
$categories = db()->fetchAll("SELECT * FROM blog_categories WHERE is_active = 1 ORDER BY name");

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2><?php echo $action === 'edit' ? 'Edit Post' : ($action === 'add' ? 'Add New Post' : 'Blog Posts'); ?></h2>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add New Post
        </a>
        <a href="?type=categories" class="btn btn-outline">
            <i class="fas fa-folder"></i>
            Categories
        </a>
        <a href="?type=tags" class="btn btn-outline">
            <i class="fas fa-tags"></i>
            Tags
        </a>
        <a href="?type=comments" class="btn btn-outline">
            <i class="fas fa-comments"></i>
            Comments
        </a>
        <?php else: ?>
        <a href="blog.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Posts
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Post created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Post updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Post deleted successfully!';
        if ($_GET['msg'] === 'published') echo 'Post published successfully!';
        ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Posts List -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th width="60">Image</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>Views</th>
                    <th>Comments</th>
                    <th>Status</th>
                    <th>Featured</th>
                    <th>Date</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['featured_image']): ?>
                        <img src="<?php echo UPLOAD_URL . 'blog/' . $p['featured_image']; ?>" 
                             alt="<?php echo $p['title']; ?>" 
                             style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">
                        <?php else: ?>
                        <div style="width: 60px; height: 40px; background: #eee; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-image" style="color: #999;"></i>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($p['title']); ?></strong>
                        <br>
                        <small><?php echo htmlspecialchars(substr($p['excerpt'] ?? strip_tags($p['content']), 0, 50)); ?>...</small>
                    </td>
                    <td><?php echo htmlspecialchars($p['category_name'] ?: 'Uncategorized'); ?></td>
                    <td><?php echo htmlspecialchars($p['author_name']); ?></td>
                    <td><?php echo number_format($p['views']); ?></td>
                    <td><?php echo $p['comment_count']; ?></td>
                    <td>
                        <a href="?publish=<?php echo $p['id']; ?>" class="status-toggle" title="Toggle Status">
                            <span class="status-badge <?php echo $p['status']; ?>">
                                <?php echo ucfirst($p['status']); ?>
                            </span>
                        </a>
                    </td>
                    <td>
                        <a href="?featured=<?php echo $p['id']; ?>" class="featured-toggle" title="Toggle Featured">
                            <?php if ($p['is_featured']): ?>
                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                            <?php else: ?>
                            <i class="far fa-star" style="color: #999;"></i>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td>
                        <?php echo date('M d, Y', strtotime($p['created_at'])); ?>
                        <?php if ($p['published_at']): ?>
                        <br><small>Published: <?php echo date('M d, Y', strtotime($p['published_at'])); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="?action=edit&id=<?php echo $p['id']; ?>" class="action-btn" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?delete=<?php echo $p['id']; ?>" class="action-btn delete-btn" 
                               onclick="return confirm('Are you sure you want to delete this post?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/blog/<?php echo $p['slug']; ?>" target="_blank" class="action-btn" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($posts)): ?>
                <tr>
                    <td colspan="10" class="text-center">No blog posts found. Click "Add New Post" to create one.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Post Form -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <?php if ($post): ?>
            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Post Title *</label>
                        <input type="text" id="title" name="title" required 
                               value="<?php echo $post['title'] ?? ''; ?>"
                               placeholder="Enter an engaging title">
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo ($post['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" rows="3" 
                              placeholder="Brief summary of the post (optional)"><?php echo $post['excerpt'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="content">Content *</label>
                    <textarea id="content" name="content" rows="15" required 
                              placeholder="Write your blog post content here..."><?php echo $post['content'] ?? ''; ?></textarea>
                    <small>Supports HTML. Use proper formatting for best results.</small>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Media & Tags</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="featured_image">Featured Image</label>
                        <input type="file" id="featured_image" name="featured_image" accept="image/*">
                        <?php if ($post && $post['featured_image']): ?>
                        <div class="current-image">
                            <img src="<?php echo UPLOAD_URL . 'blog/' . $post['featured_image']; ?>" 
                                 alt="Featured image" style="max-width: 200px; margin-top: 10px;">
                            <p><small>Current image</small></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="tags">Tags</label>
                        <input type="text" id="tags" name="tags" 
                               value="<?php echo $post['tags'] ?? ''; ?>"
                               placeholder="PHP, Laravel, JavaScript (comma separated)">
                        <small>Separate tags with commas</small>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Publishing Settings</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="draft" <?php echo ($post['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo ($post['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="archived" <?php echo ($post['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="published_at">Publish Date</label>
                        <input type="datetime-local" id="published_at" name="published_at" 
                               value="<?php echo $post ? date('Y-m-d\TH:i', strtotime($post['published_at'] ?? '')) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_featured" 
                                   <?php echo ($post['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="checkbox-text">Feature this post</span>
                        </label>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="allow_comments" 
                                   <?php echo ($post['allow_comments'] ?? 1) ? 'checked' : ''; ?>>
                            <span class="checkbox-text">Allow comments</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>SEO Settings</h3>
                
                <div class="form-group">
                    <label for="meta_title">Meta Title</label>
                    <input type="text" id="meta_title" name="meta_title" 
                           value="<?php echo $post['meta_title'] ?? ''; ?>"
                           placeholder="SEO title (leave empty to use post title)">
                </div>
                
                <div class="form-group">
                    <label for="meta_description">Meta Description</label>
                    <textarea id="meta_description" name="meta_description" rows="3" 
                              placeholder="SEO description"><?php echo $post['meta_description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="meta_keywords">Meta Keywords</label>
                    <input type="text" id="meta_keywords" name="meta_keywords" 
                           value="<?php echo $post['meta_keywords'] ?? ''; ?>"
                           placeholder="keyword1, keyword2, keyword3">
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $post ? 'Update Post' : 'Publish Post'; ?>
                </button>
                <a href="blog.php" class="btn btn-outline">Cancel</a>
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

.header-actions {
    display: flex;
    gap: 10px;
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
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>