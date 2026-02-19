<?php
// admin/blog-comments.php
// Blog Comments Moderation

// Handle approve/reject
if (isset($_GET['approve'])) {
    $id = (int)$_GET['approve'];
    db()->update('blog_comments', ['is_approved' => 1], 'id = :id', ['id' => $id]);
    header('Location: blog.php?type=comments&msg=approved');
    exit;
}

if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    db()->update('blog_comments', ['is_approved' => 0], 'id = :id', ['id' => $id]);
    header('Location: blog.php?type=comments&msg=rejected');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('blog_comments', 'id = ?', [$id]);
    header('Location: blog.php?type=comments&msg=deleted');
    exit;
}

// Bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected = $_POST['selected'] ?? [];
    $action = $_POST['bulk_action'];
    
    if (!empty($selected)) {
        $ids = implode(',', array_map('intval', $selected));
        
        if ($action === 'approve') {
            db()->query("UPDATE blog_comments SET is_approved = 1 WHERE id IN ($ids)");
            $msg = 'bulk_approved';
        } elseif ($action === 'reject') {
            db()->query("UPDATE blog_comments SET is_approved = 0 WHERE id IN ($ids)");
            $msg = 'bulk_rejected';
        } elseif ($action === 'delete') {
            db()->query("DELETE FROM blog_comments WHERE id IN ($ids)");
            $msg = 'bulk_deleted';
        }
        
        header("Location: blog.php?type=comments&msg=$msg");
        exit;
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$where = "";
switch ($filter) {
    case 'pending':
        $where = "WHERE is_approved = 0";
        break;
    case 'approved':
        $where = "WHERE is_approved = 1";
        break;
}

// Get comments with pagination
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalComments = db()->fetch("SELECT COUNT(*) as count FROM blog_comments $where")['count'] ?? 0;
$totalPages = ceil($totalComments / $perPage);

$comments = db()->fetchAll(
    "SELECT c.*, p.title as post_title, p.slug as post_slug
     FROM blog_comments c
     LEFT JOIN blog_posts p ON c.post_id = p.id
     $where
     ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?",
    [$perPage, $offset]
);

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>Blog Comments</h2>
    <div class="header-actions">
        <div class="filter-tabs">
            <a href="?type=comments&filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="?type=comments&filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?type=comments&filter=approved" class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
        </div>
        <a href="blog.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Posts
        </a>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'approved') echo 'Comment approved successfully!';
        if ($_GET['msg'] === 'rejected') echo 'Comment rejected successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Comment deleted successfully!';
        if ($_GET['msg'] === 'bulk_approved') echo 'Selected comments approved!';
        if ($_GET['msg'] === 'bulk_rejected') echo 'Selected comments rejected!';
        if ($_GET['msg'] === 'bulk_deleted') echo 'Selected comments deleted!';
        ?>
    </div>
<?php endif; ?>

<!-- Bulk Actions Form -->
<form method="POST" id="bulkForm">
    <div class="bulk-actions">
        <select name="bulk_action" class="bulk-select">
            <option value="">Bulk Actions</option>
            <option value="approve">Approve</option>
            <option value="reject">Reject</option>
            <option value="delete">Delete</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm" onclick="return confirmBulkAction()">Apply</button>
    </div>

    <!-- Comments Table -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th width="30">
                        <input type="checkbox" id="selectAll" onclick="toggleAll(this)">
                    </th>
                    <th>Comment</th>
                    <th>Post</th>
                    <th>Author</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($comments as $comment): ?>
                <tr class="<?php echo !$comment['is_approved'] ? 'unread' : ''; ?>">
                    <td>
                        <input type="checkbox" name="selected[]" value="<?php echo $comment['id']; ?>" class="select-item">
                    </td>
                    <td>
                        <div class="comment-preview">
                            <strong><?php echo htmlspecialchars($comment['name']); ?></strong>
                            <p><?php echo htmlspecialchars(substr($comment['comment'], 0, 100)); ?>...</p>
                        </div>
                    </td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>/blog/<?php echo $comment['post_slug']; ?>#comment-<?php echo $comment['id']; ?>" target="_blank">
                            <?php echo htmlspecialchars($comment['post_title']); ?>
                        </a>
                    </td>
                    <td>
                        <div class="author-info">
                            <a href="mailto:<?php echo $comment['email']; ?>"><?php echo $comment['email']; ?></a>
                            <?php if ($comment['website']): ?>
                            <br><a href="<?php echo $comment['website']; ?>" target="_blank">Website</a>
                            <?php endif; ?>
                            <br><small>IP: <?php echo $comment['ip_address']; ?></small>
                        </div>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($comment['created_at'])); ?></td>
                    <td>
                        <span class="status-badge <?php echo $comment['is_approved'] ? 'published' : 'pending'; ?>">
                            <?php echo $comment['is_approved'] ? 'Approved' : 'Pending'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <?php if (!$comment['is_approved']): ?>
                            <a href="?type=comments&approve=<?php echo $comment['id']; ?>" class="action-btn" title="Approve">
                                <i class="fas fa-check"></i>
                            </a>
                            <a href="?type=comments&reject=<?php echo $comment['id']; ?>" class="action-btn" title="Reject">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                            <a href="?type=comments&delete=<?php echo $comment['id']; ?>" 
                               class="action-btn delete-btn"
                               onclick="return confirm('Delete this comment?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($comments)): ?>
                <tr>
                    <td colspan="7" class="text-center">No comments found</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</form>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
    <a href="?type=comments&filter=<?php echo $filter; ?>&p=<?php echo $page - 1; ?>" class="page-link">
        <i class="fas fa-chevron-left"></i>
    </a>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?type=comments&filter=<?php echo $filter; ?>&p=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
        <?php echo $i; ?>
    </a>
    <?php endfor; ?>
    
    <?php if ($page < $totalPages): ?>
    <a href="?type=comments&filter=<?php echo $filter; ?>&p=<?php echo $page + 1; ?>" class="page-link">
        <i class="fas fa-chevron-right"></i>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<style>
.filter-tabs {
    display: flex;
    gap: 5px;
    margin-right: 15px;
}

.filter-tab {
    padding: 8px 15px;
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.filter-tab:hover,
.filter-tab.active {
    background: var(--primary);
    color: white;
}

.bulk-actions {
    margin-bottom: 15px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.bulk-select {
    padding: 8px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
    min-width: 150px;
}

.comment-preview p {
    margin: 5px 0 0;
    font-size: 0.85rem;
    color: var(--gray-600);
}

.author-info {
    font-size: 0.85rem;
}

.author-info a {
    color: var(--primary);
    text-decoration: none;
}

.author-info a:hover {
    text-decoration: underline;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 20px;
}

.page-link {
    padding: 8px 12px;
    background: white;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    color: var(--gray-700);
    text-decoration: none;
    transition: all 0.3s ease;
}

.page-link:hover,
.page-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
</style>

<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.select-item');
    checkboxes.forEach(cb => cb.checked = source.checked);
}

function confirmBulkAction() {
    const action = document.querySelector('select[name="bulk_action"]').value;
    const selected = document.querySelectorAll('.select-item:checked').length;
    
    if (!action) {
        alert('Please select an action');
        return false;
    }
    
    if (selected === 0) {
        alert('Please select at least one comment');
        return false;
    }
    
    if (action === 'delete') {
        return confirm('Are you sure you want to delete the selected comments?');
    }
    
    return true;
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>