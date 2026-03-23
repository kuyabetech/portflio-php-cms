<?php
// admin/newsletter.php
// Newsletter Management - Main Controller

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
        require_once 'newsletter-campaigns.php';
        break;
    case 'templates':
        $pageTitle = 'Email Templates';
        $breadcrumbs[] = ['title' => 'Templates'];
        require_once 'newsletter-templates.php';
        break;
    case 'subscribers':
    default:
        $pageTitle = 'Subscribers';
        $breadcrumbs[] = ['title' => 'Subscribers'];
        require_once 'newsletter-subscribers.php';
        break;
}
?>