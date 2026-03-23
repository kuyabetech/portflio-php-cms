<?php
/**
 * sitemap.php
 * Dynamic XML Sitemap Generator
 * 
 * This file generates a sitemap.xml dynamically from your database content.
 * Access it at: https://yourdomain.com/sitemap.php
 * You can also set up URL rewriting to make it appear as sitemap.xml
 */

require_once 'includes/init.php';

// Set content type to XML
header('Content-Type: application/xml; charset=utf-8');

// Prevent caching
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Get all published pages
$pages = db()->fetchAll(
    "SELECT slug, updated_at FROM pages WHERE status = 'published' ORDER BY slug"
) ?: [];

// Get all published blog posts
$blogPosts = db()->fetchAll(
    "SELECT slug, updated_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC"
) ?: [];

// Get all published projects
$projects = db()->fetchAll(
    "SELECT slug, updated_at FROM projects WHERE status = 'published' ORDER BY created_at DESC"
) ?: [];

// Get all project categories (optional)
$categories = db()->fetchAll(
    "SELECT id, name, slug FROM project_categories WHERE is_active = 1 ORDER BY name"
) ?: [];

// Get all blog categories (optional)
$blogCategories = db()->fetchAll(
    "SELECT id, name, slug FROM blog_categories WHERE is_active = 1 ORDER BY name"
) ?: [];

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
echo '        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' . "\n";
echo '        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n";

// Homepage
echo '  <url>' . "\n";
echo '    <loc>' . BASE_URL . '/</loc>' . "\n";
echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
echo '    <changefreq>daily</changefreq>' . "\n";
echo '    <priority>1.0</priority>' . "\n";
echo '  </url>' . "\n";

// Static pages
$staticPages = [
    ['url' => '/about', 'freq' => 'monthly', 'priority' => '0.8'],
    ['url' => '/skills', 'freq' => 'monthly', 'priority' => '0.8'],
    ['url' => '/projects', 'freq' => 'weekly', 'priority' => '0.9'],
    ['url' => '/blog', 'freq' => 'daily', 'priority' => '0.9'],
    ['url' => '/contact', 'freq' => 'monthly', 'priority' => '0.8'],
    ['url' => '/services', 'freq' => 'monthly', 'priority' => '0.7'],
    ['url' => '/resources', 'freq' => 'monthly', 'priority' => '0.7'],
    ['url' => '/hire-me', 'freq' => 'monthly', 'priority' => '0.8'],
];

foreach ($staticPages as $page) {
    echo '  <url>' . "\n";
    echo '    <loc>' . BASE_URL . $page['url'] . '</loc>' . "\n";
    echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
    echo '    <changefreq>' . $page['freq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $page['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Dynamic Pages from database
foreach ($pages as $page) {
    if ($page['slug'] !== 'home' && $page['slug'] !== 'index') {
        $lastmod = !empty($page['updated_at']) ? date('Y-m-d', strtotime($page['updated_at'])) : date('Y-m-d');
        echo '  <url>' . "\n";
        echo '    <loc>' . BASE_URL . '/' . htmlspecialchars($page['slug']) . '</loc>' . "\n";
        echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.7</priority>' . "\n";
        echo '  </url>' . "\n";
    }
}

// Project Categories (if you want to include category pages)
if (!empty($categories)) {
    foreach ($categories as $category) {
        echo '  <url>' . "\n";
        echo '    <loc>' . BASE_URL . '/projects?category=' . $category['id'] . '</loc>' . "\n";
        echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.6</priority>' . "\n";
        echo '  </url>' . "\n";
    }
}

// Blog Categories
if (!empty($blogCategories)) {
    foreach ($blogCategories as $category) {
        echo '  <url>' . "\n";
        echo '    <loc>' . BASE_URL . '/blog?category=' . $category['id'] . '</loc>' . "\n";
        echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.6</priority>' . "\n";
        echo '  </url>' . "\n";
    }
}

// Individual Blog Posts
foreach ($blogPosts as $post) {
    $lastmod = !empty($post['updated_at']) ? date('Y-m-d', strtotime($post['updated_at'])) : date('Y-m-d');
    echo '  <url>' . "\n";
    echo '    <loc>' . BASE_URL . '/blog/' . htmlspecialchars($post['slug']) . '</loc>' . "\n";
    echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
    echo '    <changefreq>monthly</changefreq>' . "\n";
    echo '    <priority>0.8</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Individual Projects
foreach ($projects as $project) {
    $lastmod = !empty($project['updated_at']) ? date('Y-m-d', strtotime($project['updated_at'])) : date('Y-m-d');
    echo '  <url>' . "\n";
    echo '    <loc>' . BASE_URL . '/project/' . htmlspecialchars($project['slug']) . '</loc>' . "\n";
    echo '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
    echo '    <changefreq>monthly</changefreq>' . "\n";
    echo '    <priority>0.8</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Close urlset
echo '</urlset>';