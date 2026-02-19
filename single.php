<?php
// blog-single.php - Single Blog Post

require_once 'includes/init.php';

$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';

if (!$slug) {
    header('Location: blog.php');
    exit;
}

// Get post
$post = db()->fetch(
    "SELECT p.*, c.name as category_name, c.slug as category_slug,
     u.username as author_name, u.profile_image as author_image
     FROM blog_posts p
     LEFT JOIN blog_categories c ON p.category_id = c.id
     LEFT JOIN users u ON p.author_id = u.id
     WHERE p.slug = ? AND p.status = 'published'",
    [$slug]
);

if (!$post) {
    header('HTTP/1.0 404 Not Found');
    require 'templates/layouts/header.php';
    echo '<div class="container"><h1>Post Not Found</h1></div>';
    require 'templates/layouts/footer.php';
    exit;
}

// Update view count
db()->update('blog_posts', ['views' => $post['views'] + 1], 'id = :id', ['id' => $post['id']]);

// Get tags
$tags = db()->fetchAll(
    "SELECT t.* FROM blog_tags t
     JOIN blog_post_tags pt ON t.id = pt.tag_id
     WHERE pt.post_id = ?",
    [$post['id']]
);

// Get comments
$comments = db()->fetchAll(
    "SELECT * FROM blog_comments 
     WHERE post_id = ? AND is_approved = 1 AND parent_id = 0
     ORDER BY created_at DESC",
    [$post['id']]
);

// Get related posts
$related = db()->fetchAll(
    "SELECT p.* FROM blog_posts p
     WHERE p.status = 'published' AND p.id != ?
     AND p.category_id = ?
     ORDER BY p.published_at DESC
     LIMIT 3",
    [$post['id'], $post['category_id']]
);

require 'templates/layouts/header.php';
?>

<!-- Post Header -->
<section class="post-header">
    <div class="container">
        <div class="post-categories">
            <a href="blog.php?category=<?php echo $post['category_id']; ?>" class="post-category">
                <?php echo $post['category_name'] ?? 'Uncategorized'; ?>
            </a>
        </div>
        
        <h1 class="post-title"><?php echo $post['title']; ?></h1>
        
        <div class="post-meta">
            <div class="post-author">
                <?php if ($post['author_image']): ?>
                <img src="<?php echo UPLOAD_URL . 'profiles/' . $post['author_image']; ?>" 
                     alt="<?php echo $post['author_name']; ?>">
                <?php else: ?>
                <div class="author-avatar"><?php echo substr($post['author_name'], 0, 1); ?></div>
                <?php endif; ?>
                <span><?php echo $post['author_name']; ?></span>
            </div>
            
            <div class="post-date">
                <i class="far fa-calendar"></i>
                <?php echo date('F d, Y', strtotime($post['published_at'])); ?>
            </div>
            
            <div class="post-reading-time">
                <i class="far fa-clock"></i>
                <?php echo $post['reading_time']; ?> min read
            </div>
            
            <div class="post-views">
                <i class="far fa-eye"></i>
                <?php echo number_format($post['views']); ?> views
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
                <?php if ($post['featured_image']): ?>
                <div class="post-featured-image">
                    <img src="<?php echo UPLOAD_URL . 'blog/' . $post['featured_image']; ?>" 
                         alt="<?php echo $post['title']; ?>">
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
                        <a href="blog.php?tag=<?php echo $tag['slug']; ?>" class="tag">
                            #<?php echo $tag['name']; ?>
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
                           target="_blank" class="share-btn facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>&text=<?php echo urlencode($post['title']); ?>" 
                           target="_blank" class="share-btn twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>" 
                           target="_blank" class="share-btn linkedin">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="mailto:?subject=<?php echo urlencode($post['title']); ?>&body=<?php echo urlencode(BASE_URL . '/blog/' . $post['slug']); ?>" 
                           class="share-btn email">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Comments Section -->
                <?php if ($post['allow_comments']): ?>
                <div class="post-comments">
                    <h3>Comments (<?php echo count($comments); ?>)</h3>
                    
                    <!-- Comment Form -->
                    <div class="comment-form">
                        <h4>Leave a Comment</h4>
                        <form action="submit-comment.php" method="POST">
                            <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <input type="text" name="name" placeholder="Your Name *" required>
                                </div>
                                <div class="form-group">
                                    <input type="email" name="email" placeholder="Your Email *" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <input type="url" name="website" placeholder="Website (optional)">
                            </div>
                            
                            <div class="form-group">
                                <textarea name="comment" rows="5" placeholder="Your Comment *" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Post Comment</button>
                        </form>
                    </div>
                    
                    <!-- Comments List -->
                    <div class="comments-list">
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment">
                            <div class="comment-avatar">
                                <?php if ($comment['website']): ?>
                                <a href="<?php echo $comment['website']; ?>" target="_blank">
                                    <div class="avatar"><?php echo substr($comment['name'], 0, 1); ?></div>
                                </a>
                                <?php else: ?>
                                <div class="avatar"><?php echo substr($comment['name'], 0, 1); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="comment-content">
                                <div class="comment-header">
                                    <h4><?php echo htmlspecialchars($comment['name']); ?></h4>
                                    <span class="comment-date">
                                        <?php echo date('M d, Y \a\t h:i A', strtotime($comment['created_at'])); ?>
                                    </span>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                
                                <button class="reply-btn" onclick="showReplyForm(<?php echo $comment['id']; ?>)">
                                    <i class="fas fa-reply"></i> Reply
                                </button>
                                
                                <!-- Reply Form (hidden by default) -->
                                <div id="reply-form-<?php echo $comment['id']; ?>" class="reply-form" style="display: none;">
                                    <form action="submit-comment.php" method="POST">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <input type="text" name="name" placeholder="Your Name *" required>
                                            </div>
                                            <div class="form-group">
                                                <input type="email" name="email" placeholder="Your Email *" required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <textarea name="comment" rows="3" placeholder="Your Reply *" required></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-sm btn-primary">Submit Reply</button>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="hideReplyForm(<?php echo $comment['id']; ?>)">Cancel</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </article>
            
            <!-- Sidebar -->
            <aside class="post-sidebar">
                <!-- Author Info -->
                <div class="sidebar-widget author-widget">
                    <h3>About the Author</h3>
                    <div class="author-info">
                        <?php if ($post['author_image']): ?>
                        <img src="<?php echo UPLOAD_URL . 'profiles/' . $post['author_image']; ?>" 
                             alt="<?php echo $post['author_name']; ?>" class="author-image">
                        <?php else: ?>
                        <div class="author-avatar-large"><?php echo substr($post['author_name'], 0, 1); ?></div>
                        <?php endif; ?>
                        <h4><?php echo $post['author_name']; ?></h4>
                        <p>Professional web developer sharing insights and experiences.</p>
                    </div>
                </div>
                
                <!-- Related Posts -->
                <?php if (!empty($related)): ?>
                <div class="sidebar-widget related-widget">
                    <h3>Related Posts</h3>
                    <ul>
                        <?php foreach ($related as $rel): ?>
                        <li>
                            <a href="blog/<?php echo $rel['slug']; ?>">
                                <?php if ($rel['featured_image']): ?>
                                <img src="<?php echo UPLOAD_URL . 'blog/' . $rel['featured_image']; ?>" 
                                     alt="<?php echo $rel['title']; ?>">
                                <?php endif; ?>
                                <div class="post-info">
                                    <h4><?php echo $rel['title']; ?></h4>
                                    <span><?php echo date('M d, Y', strtotime($rel['published_at'])); ?></span>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Table of Contents -->
                <div class="sidebar-widget toc-widget">
                    <h3>Table of Contents</h3>
                    <div id="toc"></div>
                </div>
            </aside>
        </div>
    </div>
</section>

<style>
/* Post Header */
.post-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 100px 0 60px;
    margin-top: 80px;
    text-align: center;
}

.post-category {
    display: inline-block;
    padding: 5px 15px;
    background: rgba(255,255,255,0.2);
    color: white;
    text-decoration: none;
    border-radius: 20px;
    font-size: 0.9rem;
    margin-bottom: 20px;
}

.post-title {
    color: white;
    font-size: 3rem;
    max-width: 800px;
    margin: 0 auto 20px;
}

.post-meta {
    display: flex;
    justify-content: center;
    gap: 30px;
    color: rgba(255,255,255,0.9);
    font-size: 0.95rem;
}

.post-author {
    display: flex;
    align-items: center;
    gap: 10px;
}

.post-author img,
.author-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.author-avatar {
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

/* Post Content */
.post-content {
    padding: 60px 0;
    background: var(--gray-100);
}

.post-wrapper {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 40px;
}

.post-main {
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
}

.post-body h2 {
    margin: 30px 0 15px;
}

.post-body h3 {
    margin: 25px 0 15px;
}

.post-body p {
    margin-bottom: 20px;
}

.post-body ul,
.post-body ol {
    margin-bottom: 20px;
    padding-left: 20px;
}

.post-body blockquote {
    margin: 30px 0;
    padding: 20px 30px;
    background: var(--gray-100);
    border-left: 4px solid var(--primary);
    font-style: italic;
}

.post-body pre {
    background: var(--gray-800);
    color: white;
    padding: 20px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 20px 0;
}

.post-body code {
    background: var(--gray-100);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.9em;
}

/* Tags */
.post-tags {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid var(--gray-200);
}

.post-tags h3 {
    font-size: 1.1rem;
    margin-bottom: 15px;
}

.tags-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.tag {
    padding: 5px 12px;
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    border-radius: 20px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.tag:hover {
    background: var(--primary);
    color: white;
}

/* Share Buttons */
.post-share {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid var(--gray-200);
}

.post-share h3 {
    font-size: 1.1rem;
    margin-bottom: 15px;
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
    transform: translateY(-3px);
    filter: brightness(1.1);
}

/* Comments */
.post-comments {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid var(--gray-200);
}

.comment-form {
    background: var(--gray-100);
    padding: 30px;
    border-radius: 12px;
    margin: 30px 0;
}

.comment-form h4 {
    margin-bottom: 20px;
}

.comment {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.comment-avatar .avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
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
}

.comment-header h4 {
    margin: 0;
    font-size: 1rem;
}

.comment-date {
    font-size: 0.85rem;
    color: var(--gray-500);
}

.reply-btn {
    background: none;
    border: none;
    color: var(--primary);
    cursor: pointer;
    font-size: 0.85rem;
    margin-top: 10px;
}

.reply-form {
    margin-top: 20px;
    padding: 20px;
    background: var(--gray-100);
    border-radius: 8px;
}

/* Table of Contents */
.toc-widget {
    position: sticky;
    top: 100px;
}

#toc ul {
    list-style: none;
    padding: 0;
}

#toc li {
    margin-bottom: 10px;
}

#toc a {
    color: var(--gray-700);
    text-decoration: none;
    font-size: 0.95rem;
}

#toc a:hover {
    color: var(--primary);
}

/* Responsive */
@media (max-width: 992px) {
    .post-wrapper {
        grid-template-columns: 1fr;
    }
    
    .post-sidebar {
        order: -1;
    }
}

@media (max-width: 768px) {
    .post-header {
        padding: 80px 0 40px;
    }
    
    .post-title {
        font-size: 2rem;
    }
    
    .post-meta {
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .post-main {
        padding: 20px;
    }
    
    .post-featured-image {
        margin: -20px -20px 20px;
        height: 200px;
    }
    
    .comment {
        flex-direction: column;
    }
    
    .comment-avatar .avatar {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
}
</style>

<script>
// Generate Table of Contents
document.addEventListener('DOMContentLoaded', function() {
    const headings = document.querySelectorAll('.post-body h2, .post-body h3');
    const toc = document.getElementById('toc');
    
    if (toc && headings.length) {
        const ul = document.createElement('ul');
        
        headings.forEach((heading, index) => {
            const id = 'heading-' + index;
            heading.id = id;
            
            const li = document.createElement('li');
            const a = document.createElement('a');
            a.href = '#' + id;
            a.textContent = heading.textContent;
            
            if (heading.tagName === 'H3') {
                a.style.paddingLeft = '20px';
            }
            
            li.appendChild(a);
            ul.appendChild(li);
        });
        
        toc.appendChild(ul);
    }
});

function showReplyForm(commentId) {
    document.getElementById('reply-form-' + commentId).style.display = 'block';
}

function hideReplyForm(commentId) {
    document.getElementById('reply-form-' + commentId).style.display = 'none';
}
</script>

<?php require 'templates/layouts/footer.php'; ?>