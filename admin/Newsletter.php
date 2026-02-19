<?php
// admin/newsletter.php
// Newsletter Management

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Newsletter Management';
$action = $_GET['action'] ?? 'dashboard';
$type = $_GET['type'] ?? 'subscribers'; // subscribers, campaigns, templates

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Newsletter']
];

// Handle different sections
switch ($type) {
    case 'campaigns':
        $pageTitle = 'Email Campaigns';
        $breadcrumbs[] = ['title' => 'Campaigns'];
        include 'newsletter-campaigns.php';
        break;
    case 'templates':
        $pageTitle = 'Email Templates';
        $breadcrumbs[] = ['title' => 'Templates'];
        include 'newsletter-templates.php';
        break;
    default:
        $pageTitle = 'Subscribers';
        $breadcrumbs[] = ['title' => 'Subscribers'];
        include 'newsletter-subscribers.php';
        break;
}
?>