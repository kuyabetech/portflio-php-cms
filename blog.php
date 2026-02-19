<?php
// blog.php - Blog Listing Page

require_once 'includes/init.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$category = isset($_GET['category']) ? (int)$_GET['category'] : null;
$tag = isset($_GET['tag']) ? sanitize($_GET['tag']) : null;

$perPage = 6;
$offset = ($page - 1) * $perPage;

// Build query based on filters
$where = ["status = 'published'"];
$params = [];

if ($category) {
    $where[] = "category_id = ?";
    $params[] = $category;
}

if ($tag) {
    $where[] = "id IN (SELECT post_id FROM blog_post_tags WHERE tag_id = (SELECT id FROM blog_tags WHERE slug = ?))";
    $params[] = $tag;
}

$whereClause = implode(' AND ', $where);

// Get posts
$posts = db()->fetchAll(
    "SELECT p.*, c.name as category_name, c.slug as category_slug,
     u.username as author_name,
     (SELECT COUNT(*) FROM blog_comments WHERE post_id = p.id AND is_approved = 1) as comment_count
     FROM blog_posts p
     LEFT JOIN blog_categories c ON p.category_id = c.id
     LEFT JOIN users u ON p.author_id = u.id
     WHERE $whereClause
     ORDER BY p.published_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// Get total count for pagination
$total = db()->fetch(
    "SELECT COUNT(*) as count FROM blog_posts WHERE $whereClause",
    $params
)['count'];

$totalPages = ceil($total / $perPage);

// Get categories for sidebar
$categories = db()->fetchAll(
    "SELECT c.*, COUNT(p.id) as post_count 
     FROM blog_categories c
     LEFT JOIN blog_posts p ON c.id = p.category_id AND p.status = 'published'
     WHERE c.is_active = 1
     GROUP BY c.id
     ORDER BY c.display_order"
);

// Get popular tags
$tags = db()->fetchAll(
    "SELECT t.*, COUNT(pt.post_id) as post_count 
     FROM blog_tags t
     JOIN blog_post_tags pt ON t.id = pt.tag_id
     JOIN blog_posts p ON pt.post_id = p.id
     WHERE p.status = 'published'
     GROUP BY t.id
     ORDER BY post_count DESC
     LIMIT 10"
);

require 'templates/layouts/header.php';
?>

<!-- Blog Header -->
<section class="blog-header">
    <div class="container">
        <h1>Blog & Insights</h1>
        <p>Sharing knowledge, experiences, and thoughts about web development</p>
    </div>
</section>

<!-- Blog Content -->
<section class="blog-content">
    <div class="container">
        <div class="blog-wrapper">
            <!-- Main Content -->
            <div class="blog-main">
                <?php if (empty($posts)): ?>
                <div class="no-posts">
                    <i class="fas fa-newspaper"></i>
                    <h3>No posts yet</h3>
                    <p>Check back soon for new content!</p>
                </div>
                <?php else: ?>
                    <div class="blog-grid">
                        <?php foreach ($posts as $post): ?>
                        <article class="blog-card" data-aos="fade-up">
                            <?php if ($post['featured_image']): ?>
                            <div class="blog-image">
                                <a href="blog/<?php echo $post['slug']; ?>">
                                    <img src="<?php echo UPLOAD_URL . 'blog/' . $post['featured_image']; ?>" 
                                         alt="<?php echo $post['title']; ?>">
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <div class="blog-content">
                                <div class="blog-meta">
                                    <span class="blog-category">
                                        <a href="?category=<?php echo $post['category_id']; ?>">
                                            <?php echo $post['category_name'] ?? 'Uncategorized'; ?>
                                        </a>
                                    </span>
                                    <span class="blog-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('M d, Y', strtotime($post['published_at'])); ?>
                                    </span>
                                    <span class="blog-reading-time">
                                        <i class="far fa-clock"></i>
                                        <?php echo $post['reading_time']; ?> min read
                                    </span>
                                </div>
                                
                                <h2 class="blog-title">
                                    <a href="blog/<?php echo $post['slug']; ?>">
                                        <?php echo $post['title']; ?>
                                    </a>
                                </h2>
                                
                                <p class="blog-excerpt">
                                    <?php echo $post['excerpt'] ?: substr(strip_tags($post['content']), 0, 200) . '...'; ?>
                                </p>
                                
                                <div class="blog-footer">
                                    <div class="blog-author">
                                        <i class="far fa-user"></i>
                                        <?php echo $post['author_name']; ?>
                                    </div>
                                    <div class="blog-comments">
                                        <i class="far fa-comment"></i>
                                        <?php echo $post['comment_count']; ?> comments
                                    </div>
                                    <a href="blog/<?php echo $post['slug']; ?>" class="read-more">
                                        Read More <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $category ? '&category=' . $category : ''; ?>" 
                           class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $category ? '&category=' . $category : ''; ?>" 
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $category ? '&category=' . $category : ''; ?>" 
                           class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <aside class="blog-sidebar">
                <!-- Search -->
                <div class="sidebar-widget search-widget">
                    <h3>Search</h3>
                    <form action="blog-search.php" method="GET">
                        <input type="text" name="q" placeholder="Search articles...">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>
                
                <!-- Categories -->
                <div class="sidebar-widget categories-widget">
                    <h3>Categories</h3>
                    <ul>
                        <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="?category=<?php echo $cat['id']; ?>">
                                <?php echo $cat['name']; ?>
                                <span>(<?php echo $cat['post_count']; ?>)</span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <!-- Popular Tags -->
                <div class="sidebar-widget tags-widget">
                    <h3>Popular Tags</h3>
                    <div class="tag-cloud">
                        <?php foreach ($tags as $tag): ?>
                        <a href="?tag=<?php echo $tag['slug']; ?>" class="tag-link">
                            <?php echo $tag['name']; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Recent Posts -->
                <div class="sidebar-widget recent-posts-widget">
                    <h3>Recent Posts</h3>
                    <?php
                    $recent = db()->fetchAll(
                        "SELECT title, slug, published_at, featured_image 
                         FROM blog_posts 
                         WHERE status = 'published' 
                         ORDER BY published_at DESC 
                         LIMIT 5"
                    );
                    ?>
                    <ul>
                        <?php foreach ($recent as $post): ?>
                        <li>
                            <a href="blog/<?php echo $post['slug']; ?>">
                                <?php if ($post['featured_image']): ?>
                                <img src="<?php echo UPLOAD_URL . 'blog/' . $post['featured_image']; ?>" 
                                     alt="<?php echo $post['title']; ?>">
                                <?php endif; ?>
                                <div class="post-info">
                                    <h4><?php echo $post['title']; ?></h4>
                                    <span><?php echo date('M d, Y', strtotime($post['published_at'])); ?></span>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</section>

<style>
.blog-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    padding: 80px 0;
    text-align: center;
    margin-top: 80px;
}

.blog-header h1 {
    color: white;
    font-size: 3rem;
    margin-bottom: 1rem;
}

.blog-header p {
    color: rgba(255,255,255,0.9);
    font-size: 1.2rem;
}

.blog-content {
    padding: 60px 0;
    background: var(--gray-100);
}

.blog-wrapper {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 40px;
}

.blog-grid {
    display: grid;
    gap: 30px;
}

.blog-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.blog-card:hover {
    transform: translateY(-5px);
}

.blog-image {
    height: 250px;
    overflow: hidden;
}

.blog-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.blog-card:hover .blog-image img {
    transform: scale(1.05);
}

.blog-content {
    padding: 25px;
}

.blog-meta {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
    font-size: 0.9rem;
    color: var(--gray-600);
}

.blog-category a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.blog-title {
    font-size: 1.5rem;
    margin-bottom: 15px;
}

.blog-title a {
    color: var(--dark);
    text-decoration: none;
}

.blog-excerpt {
    color: var(--gray-600);
    line-height: 1.6;
    margin-bottom: 20px;
}

.blog-footer {
    display: flex;
    align-items: center;
    gap: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
    font-size: 0.9rem;
    color: var(--gray-600);
}

.read-more {
    margin-left: auto;
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

/* Sidebar Styles */
.blog-sidebar {
    position: sticky;
    top: 100px;
    align-self: start;
}

.sidebar-widget {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.sidebar-widget h3 {
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary);
    font-size: 1.2rem;
}

/* Search Widget */
.search-widget form {
    display: flex;
    gap: 10px;
}

.search-widget input {
    flex: 1;
    padding: 10px;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
}

.search-widget button {
    padding: 10px 15px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

/* Categories Widget */
.categories-widget ul {
    list-style: none;
}

.categories-widget li {
    margin-bottom: 10px;
}

.categories-widget a {
    display: flex;
    justify-content: space-between;
    color: var(--gray-700);
    text-decoration: none;
    padding: 5px 0;
}

.categories-widget a:hover {
    color: var(--primary);
}

/* Tags Widget */
.tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.tag-link {
    padding: 5px 12px;
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    border-radius: 20px;
    font-size: 0.9rem;
    transition: all 0.3s ease;
}

.tag-link:hover {
    background: var(--primary);
    color: white;
}

/* Recent Posts Widget */
.recent-posts-widget ul {
    list-style: none;
}

.recent-posts-widget li {
    margin-bottom: 15px;
}

.recent-posts-widget a {
    display: flex;
    gap: 15px;
    text-decoration: none;
}

.recent-posts-widget img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 8px;
}

.post-info h4 {
    color: var(--dark);
    font-size: 1rem;
    margin-bottom: 5px;
}

.post-info span {
    color: var(--gray-500);
    font-size: 0.85rem;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 40px;
}

.page-link {
    padding: 10px 15px;
    background: white;
    color: var(--gray-700);
    text-decoration: none;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.page-link:hover,
.page-link.active {
    background: var(--primary);
    color: white;
}

/* No Posts */
.no-posts {
    text-align: center;
    padding: 60px;
    background: white;
    border-radius: 12px;
}

.no-posts i {
    font-size: 4rem;
    color: var(--gray-400);
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 992px) {
    .blog-wrapper {
        grid-template-columns: 1fr;
    }
    
    .blog-sidebar {
        position: static;
    }
}

@media (max-width: 768px) {
    .blog-header {
        padding: 60px 0;
    }
    
    .blog-header h1 {
        font-size: 2rem;
    }
    
    .blog-meta {
        flex-wrap: wrap;
    }
    
    .blog-footer {
        flex-wrap: wrap;
    }
    
    .read-more {
        margin-left: 0;
    }
}
</style>

<?php require 'templates/layouts/footer.php'; ?>