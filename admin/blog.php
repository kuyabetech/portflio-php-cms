<?php
// admin/blog.php
// Blog Management

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Blog Management';
$action = $_GET['action'] ?? 'list';
$type = $_GET['type'] ?? 'posts'; // posts, categories, tags, comments
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Blog']
];

// Handle different sections
switch ($type) {
    case 'categories':
        $pageTitle = 'Blog Categories';
        $breadcrumbs[] = ['title' => 'Categories'];
        include 'blog-categories.php';
        break;
    case 'tags':
        $pageTitle = 'Blog Tags';
        $breadcrumbs[] = ['title' => 'Tags'];
        include 'blog-tags.php';
        break;
    case 'comments':
        $pageTitle = 'Blog Comments';
        $breadcrumbs[] = ['title' => 'Comments'];
        include 'blog-comments.php';
        break;
    default:
        $pageTitle = 'Blog Posts';
        $breadcrumbs[] = ['title' => 'Posts'];
        include 'blog-posts.php';
        break;
}
?>