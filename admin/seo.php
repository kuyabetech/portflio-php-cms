<?php
// admin/seo.php
// SEO Management

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'SEO Management';
$action = $_GET['action'] ?? 'dashboard';
$type = $_GET['type'] ?? 'metadata'; // metadata, redirects, sitemap

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'SEO']
];

// Handle different sections
switch ($type) {
    case 'redirects':
        $pageTitle = 'Redirect Manager';
        $breadcrumbs[] = ['title' => 'Redirects'];
        include 'seo-redirects.php';
        break;
    case 'sitemap':
        $pageTitle = 'Sitemap Generator';
        $breadcrumbs[] = ['title' => 'Sitemap'];
        include 'seo-sitemap.php';
        break;
    default:
        $pageTitle = 'SEO Metadata';
        $breadcrumbs[] = ['title' => 'Metadata'];
        include 'seo-metadata.php';
        break;
}
?>