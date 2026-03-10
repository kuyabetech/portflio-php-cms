<?php
// templates/blog-single.php
// Single Blog Post Template with Integrated Comment Form

// Ensure post data exists
if (!isset($post) || !$post) {
    header('Location: ' . BASE_URL . '/blog');
    exit;
}

// Get tags for this post
$tags = [];
try {
    $tags = db()->fetchAll(
        "SELECT t.* FROM blog_tags t
         JOIN blog_post_tags pt ON t.id = pt.tag_id
         WHERE pt.post_id = ?",
        [$post['id']]
    );
} catch (Exception $e) {
    // Silently fail - tags are optional
}

// Get approved comments for this post
$comments = [];
try {
    $comments = db()->fetchAll(
        "SELECT * FROM blog_comments 
         WHERE post_id = ? AND is_approved = 1 AND parent_id = 0
         ORDER BY created_at DESC",
        [$post['id']]
    );
} catch (Exception $e) {
    // Silently fail - comments are optional
}

// Get related posts
$related = [];
try {
    $related = db()->fetchAll(
        "SELECT title, slug, published_at, featured_image 
         FROM blog_posts 
         WHERE status = 'published' AND id != ? AND category_id = ?
         ORDER BY published_at DESC 
         LIMIT 3",
        [$post['id'], $post['category_id']]
    );
} catch (Exception $e) {
    // Silently fail - related posts are optional
}

// Format date
$publishedDate = date('F d, Y', strtotime($post['published_at']));
?>

<!-- Post Header -->
<section class="post-header">
    <div class="container">
        <div class="post-categories">
            <?php if (!empty($post['category_name'])): ?>
            <a href="<?php echo BASE_URL; ?>/blog?category=<?php echo $post['category_id']; ?>" class="post-category">
                <?php echo htmlspecialchars($post['category_name']); ?>
            </a>
            <?php endif; ?>
        </div>
        
        <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
        
        <div class="post-meta">
            <div class="post-author">
                <i class="far fa-user"></i>
                <span><?php echo htmlspecialchars($post['author_name'] ?? 'Admin'); ?></span>
            </div>
            
            <div class="post-date">
                <i class="far fa-calendar"></i>
                <?php echo $publishedDate; ?>
            </div>
            
            <div class="post-reading-time">
                <i class="far fa-clock"></i>
                <?php echo $post['reading_time'] ?: '5'; ?> min read
            </div>
            
            <div class="post-views">
                <i class="far fa-eye"></i>
                <?php echo number_format($post['views'] ?? 0); ?> views
            </div>
        </div>
    </div>
</section>

<!-- Post Content -->
<section class="post-content">
    <div class="container">
        <div class="post-wrapper">
            <!-- Main Content -->
            <article class="post-main">
                <?php if (!empty($post['featured_image'])): ?>
                <div class="post-featured-image">
                    <img src="<?php echo UPLOAD_URL . 'blog/' . $post['featured_image']; ?>" 
                         alt="<?php echo htmlspecialchars($post['title']); ?>">
                </div>
                <?php endif; ?>
                
                <div class="post-body">
                    <?php echo $post['content']; ?>
                </div>
                
                <!-- Tags -->
                <?php if (!empty($tags)): ?>
                <div class="post-tags">
                    <h3>Tags:</h3>
                    <div class="tags-list">
                        <?php foreach ($tags as $tag): ?>
                        <a href="<?php echo BASE_URL; ?>/blog?tag=<?php echo $tag['slug']; ?>" class="tag">
                            #<?php echo htmlspecialchars($tag['name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Share Buttons -->
                <div class="post-share">
                    <h3>Share this article:</h3>
                    <div class="share-buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>" 
                           target="_blank" class="share-btn facebook" title="Share on Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>&text=<?php echo urlencode($post['title']); ?>" 
                           target="_blank" class="share-btn twitter" title="Share on Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>" 
                           target="_blank" class="share-btn linkedin" title="Share on LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode($post['title']); ?>&body=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>" 
                           class="share-btn email" title="Share via Email">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Comments Section (Integrated in same file) -->
                <div class="post-comments" id="comments">
                    <h3>Comments (<?php echo count($comments); ?>)</h3>
                    
                    <!-- Display messages -->
                    <?php if (isset($_SESSION['comment_success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $_SESSION['comment_success']; ?>
                    </div>
                    <?php unset($_SESSION['comment_success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['comment_error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['comment_error']; ?>
                    </div>
                    <?php unset($_SESSION['comment_error']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['comment_errors']) && is_array($_SESSION['comment_errors'])): ?>
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($_SESSION['comment_errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php unset($_SESSION['comment_errors']); ?>
                    <?php endif; ?>
                    
                    <!-- Comment Form (No Labels) -->
                    <div class="comment-form-wrapper" id="comment-form">
                        <h4>Leave a Comment</h4>
                        <form action="<?php echo BASE_URL; ?>/submit-comment.php" method="POST" class="comment-form">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            <input type="hidden" name="parent_id" value="0">
                            <!-- Honeypot field for spam prevention -->
                            <input type="text" name="honeypot" style="display: none;" tabindex="-1" autocomplete="off">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="name" placeholder="Your Name *" required
                                           value="<?php echo isset($_SESSION['comment_data']['name']) ? htmlspecialchars($_SESSION['comment_data']['name']) : ''; ?>">
                                </div>
                                <div class="form-group">
                                    <input type="email" name="email" placeholder="Your Email *" required
                                           value="<?php echo isset($_SESSION['comment_data']['email']) ? htmlspecialchars($_SESSION['comment_data']['email']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <input type="url" name="website" placeholder="Your Website (optional)"
                                       value="<?php echo isset($_SESSION['comment_data']['website']) ? htmlspecialchars($_SESSION['comment_data']['website']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <textarea name="comment" rows="5" placeholder="Your Comment *" required><?php echo isset($_SESSION['comment_data']['comment']) ? htmlspecialchars($_SESSION['comment_data']['comment']) : ''; ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Post Comment
                            </button>
                        </form>
                    </div>
                    
                    <?php 
                    // Clear stored comment data
                    unset($_SESSION['comment_data']);
                    ?>
                    
                    <!-- Comments List -->
                    <?php if (!empty($comments)): ?>
                    <div class="comments-list">
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment">
                            <div class="comment-avatar">
                                <div class="avatar">
                                    <?php echo strtoupper(substr($comment['name'], 0, 1)); ?>
                                </div>
                            </div>
                            
                            <div class="comment-content">
                                <div class="comment-header">
                                    <h4><?php echo htmlspecialchars($comment['name']); ?></h4>
                                    <span class="comment-date">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('M d, Y \a\t h:i A', strtotime($comment['created_at'])); ?>
                                    </span>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                
                                <?php if (!empty($comment['website'])): ?>
                                <a href="<?php echo htmlspecialchars($comment['website']); ?>" target="_blank" class="comment-website">
                                    <i class="fas fa-external-link-alt"></i> Visit Website
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="no-comments">Be the first to comment on this post!</p>
                    <?php endif; ?>
                </div>
            </article>
            
            <!-- Sidebar -->
            <aside class="post-sidebar">
                <!-- About Author -->
                <div class="sidebar-widget author-widget">
                    <h3>About the Author</h3>
                    <div class="author-info">
                        <div class="author-avatar">
                            <?php echo strtoupper(substr($post['author_name'] ?? 'A', 0, 1)); ?>
                        </div>
                        <h4><?php echo htmlspecialchars($post['author_name'] ?? 'Admin'); ?></h4>
                        <p>Professional web developer sharing insights and experiences about web development, programming, and technology.</p>
                    </div>
                </div>
                
                <!-- Related Posts -->
                <?php if (!empty($related)): ?>
                <div class="sidebar-widget related-widget">
                    <h3>Related Posts</h3>
                    <ul class="related-posts">
                        <?php foreach ($related as $rel): ?>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/blog/<?php echo $rel['slug']; ?>">
                                <?php if (!empty($rel['featured_image'])): ?>
                                <div class="related-image">
                                    <img src="<?php echo UPLOAD_URL . 'blog/' . $rel['featured_image']; ?>" 
                                         alt="<?php echo htmlspecialchars($rel['title']); ?>">
                                </div>
                                <?php endif; ?>
                                <div class="related-info">
                                    <h4><?php echo htmlspecialchars($rel['title']); ?></h4>
                                    <span class="related-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($rel['published_at'])); ?>
                                    </span>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Categories Widget -->
                <?php
                $categories = [];
                try {
                    $categories = db()->fetchAll(
                        "SELECT c.*, COUNT(p.id) as post_count 
                         FROM blog_categories c
                         LEFT JOIN blog_posts p ON c.id = p.category_id AND p.status = 'published'
                         WHERE c.is_active = 1
                         GROUP BY c.id
                         ORDER BY c.display_order
                         LIMIT 5"
                    );
                } catch (Exception $e) {}
                ?>
                
                <?php if (!empty($categories)): ?>
                <div class="sidebar-widget categories-widget">
                    <h3>Categories</h3>
                    <ul>
                        <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/blog?category=<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                                <span class="count">(<?php echo $cat['post_count']; ?>)</span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<style>
/* ========================================
   BLOG SINGLE STYLES
   ======================================== */

/* Post Header */
.post-header {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    padding: 100px 0 60px;
    margin-top: 80px;
    text-align: center;
}

.post-category {
    display: inline-block;
    padding: 5px 15px;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    text-decoration: none;
    border-radius: 30px;
    font-size: 0.9rem;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.post-category:hover {
    background: white;
    color: #2563eb;
}

.post-title {
    color: white;
    font-size: clamp(2rem, 5vw, 3.5rem);
    max-width: 800px;
    margin: 0 auto 20px;
    line-height: 1.2;
}

.post-meta {
    display: flex;
    justify-content: center;
    gap: 30px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.95rem;
    flex-wrap: wrap;
}

.post-meta i {
    margin-right: 5px;
}

/* Post Content */
.post-content {
    padding: 60px 0;
    background: #f8fafc;
}

.post-wrapper {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 40px;
}

/* Main Content */
.post-main {
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
}

.post-featured-image {
    margin: -40px -40px 30px;
    height: 400px;
    overflow: hidden;
    border-radius: 12px 12px 0 0;
}

.post-featured-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.post-body {
    line-height: 1.8;
    font-size: 1.1rem;
    color: #334155;
}

.post-body h2 {
    font-size: 1.8rem;
    margin: 40px 0 20px;
    color: #0f172a;
}

.post-body h3 {
    font-size: 1.4rem;
    margin: 30px 0 15px;
    color: #1e293b;
}

.post-body p {
    margin-bottom: 20px;
}

.post-body ul,
.post-body ol {
    margin-bottom: 20px;
    padding-left: 20px;
}

.post-body li {
    margin-bottom: 5px;
}

.post-body blockquote {
    margin: 30px 0;
    padding: 20px 30px;
    background: #f1f5f9;
    border-left: 4px solid #2563eb;
    font-style: italic;
    color: #475569;
}

.post-body pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 20px;
    border-radius: 8px;
    overflow-x: auto;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    margin: 20px 0;
}

.post-body code {
    background: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.9em;
    font-family: 'Courier New', monospace;
}

.post-body pre code {
    background: transparent;
    color: inherit;
    padding: 0;
}

.post-body img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 20px 0;
}

.post-body a {
    color: #2563eb;
    text-decoration: none;
    border-bottom: 1px solid transparent;
    transition: border-color 0.3s ease;
}

.post-body a:hover {
    border-bottom-color: #2563eb;
}

/* Tags */
.post-tags {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid #e2e8f0;
}

.post-tags h3 {
    font-size: 1.1rem;
    margin-bottom: 15px;
    color: #1e293b;
}

.tags-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.tag {
    padding: 6px 14px;
    background: #f1f5f9;
    color: #475569;
    text-decoration: none;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.tag:hover {
    background: #2563eb;
    color: white;
    transform: translateY(-2px);
}

/* Share Buttons */
.post-share {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid #e2e8f0;
}

.post-share h3 {
    font-size: 1.1rem;
    margin-bottom: 15px;
    color: #1e293b;
}

.share-buttons {
    display: flex;
    gap: 10px;
}

.share-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
}

.share-btn.facebook { background: #3b5998; }
.share-btn.twitter { background: #1da1f2; }
.share-btn.linkedin { background: #0077b5; }
.share-btn.email { background: #ea4335; }

.share-btn:hover {
    transform: translateY(-3px) scale(1.1);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

/* Comments Section */
.post-comments {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid #e2e8f0;
}

.post-comments h3 {
    font-size: 1.3rem;
    margin-bottom: 25px;
    color: #1e293b;
}

/* Comment Form - No Labels */
.comment-form-wrapper {
    background: #f8fafc;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 40px;
}

.comment-form-wrapper h4 {
    font-size: 1.1rem;
    margin-bottom: 20px;
    color: #1e293b;
}

.comment-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.comment-form .form-group {
    margin-bottom: 20px;
}

.comment-form input,
.comment-form textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
    font-family: inherit;
    background: white;
}

.comment-form input:focus,
.comment-form textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.comment-form textarea {
    resize: vertical;
    min-height: 120px;
}

.comment-form .btn {
    padding: 12px 30px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.comment-form .btn:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
}

/* Comments List */
.comments-list {
    margin-top: 30px;
}

.comment {
    display: flex;
    gap: 20px;
    padding: 25px;
    background: #f8fafc;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
}

.comment-avatar {
    flex-shrink: 0;
}

.avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 600;
}

.comment-content {
    flex: 1;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 10px;
}

.comment-header h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.comment-date {
    font-size: 0.85rem;
    color: #64748b;
}

.comment-date i {
    margin-right: 3px;
}

.comment-content p {
    margin: 0 0 10px 0;
    color: #334155;
    line-height: 1.6;
}

.comment-website {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #2563eb;
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.3s ease;
}

.comment-website:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.no-comments {
    text-align: center;
    padding: 30px;
    background: #f8fafc;
    border-radius: 8px;
    color: #64748b;
    font-style: italic;
}

/* Alerts */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.3s ease;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert i {
    font-size: 1.2rem;
}

.alert ul {
    margin: 0;
    padding-left: 20px;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Sidebar */
.post-sidebar {
    position: sticky;
    top: 100px;
    align-self: start;
}

.sidebar-widget {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.sidebar-widget h3 {
    font-size: 1.2rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #2563eb;
    color: #1e293b;
}

/* Author Widget */
.author-info {
    text-align: center;
}

.author-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 600;
    margin: 0 auto 15px;
}

.author-info h4 {
    font-size: 1.1rem;
    margin-bottom: 10px;
    color: #1e293b;
}

.author-info p {
    color: #64748b;
    line-height: 1.6;
    margin: 0;
}

/* Related Posts Widget */
.related-posts {
    list-style: none;
    padding: 0;
    margin: 0;
}

.related-posts li {
    margin-bottom: 20px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 15px;
}

.related-posts li:last-child {
    margin-bottom: 0;
    border-bottom: none;
    padding-bottom: 0;
}

.related-posts a {
    display: flex;
    gap: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.related-posts a:hover {
    transform: translateX(5px);
}

.related-image {
    width: 80px;
    height: 80px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.related-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.related-posts a:hover .related-image img {
    transform: scale(1.1);
}

.related-info {
    flex: 1;
}

.related-info h4 {
    font-size: 0.95rem;
    margin-bottom: 5px;
    color: #1e293b;
    line-height: 1.4;
}

.related-date {
    font-size: 0.8rem;
    color: #64748b;
}

.related-date i {
    margin-right: 3px;
}

/* Categories Widget */
.categories-widget ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.categories-widget li {
    margin-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 8px;
}

.categories-widget li:last-child {
    margin-bottom: 0;
    border-bottom: none;
    padding-bottom: 0;
}

.categories-widget a {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #475569;
    text-decoration: none;
    transition: color 0.3s ease;
    padding: 5px 0;
}

.categories-widget a:hover {
    color: #2563eb;
}

.categories-widget .count {
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 15px;
    font-size: 0.8rem;
    color: #64748b;
}

/* Back to Top Button */
.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
    transition: all 0.3s ease;
    z-index: 99;
    opacity: 0;
    visibility: hidden;
}

.back-to-top.show {
    opacity: 1;
    visibility: visible;
}

.back-to-top:hover {
    background: #1d4ed8;
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
}

/* Responsive */
@media (max-width: 1023px) {
    .post-wrapper {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    
    .post-sidebar {
        position: static;
    }
    
    .post-featured-image {
        height: 350px;
    }
}

@media (max-width: 767px) {
    .post-header {
        padding: 80px 0 40px;
    }
    
    .post-title {
        font-size: 2rem;
    }
    
    .post-meta {
        gap: 15px;
        font-size: 0.85rem;
    }
    
    .post-main {
        padding: 25px;
    }
    
    .post-featured-image {
        margin: -25px -25px 20px;
        height: 250px;
    }
    
    .comment-form .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .comment {
        flex-direction: column;
    }
}

@media (max-width: 575px) {
    .post-main {
        padding: 20px;
    }
    
    .post-featured-image {
        margin: -20px -20px 15px;
        height: 200px;
    }
    
    .comment-form-wrapper {
        padding: 20px;
    }
}
</style>

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTop" title="Back to Top">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
// Back to top button
document.addEventListener('DOMContentLoaded', function() {
    const backToTop = document.getElementById('backToTop');
    
    if (backToTop) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 500) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });
        
        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Auto-scroll to comments if URL has #comments or #comment-form
    if (window.location.hash === '#comments' || window.location.hash === '#comment-form') {
        const target = document.querySelector(window.location.hash);
        if (target) {
            setTimeout(() => {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 100);
        }
    }
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});
</script>