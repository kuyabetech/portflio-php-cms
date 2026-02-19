<?php
// templates/page.php
// Simple page template

// Get page data
if (!isset($pageSlug)) {
    $pageSlug = 'home';
}

$page = db()->fetch("SELECT * FROM pages WHERE slug = ? AND status = 'published'", [$pageSlug]);

if (!$page) {
    header("HTTP/1.0 404 Not Found");
    require 'templates/layouts/header.php';
    echo '<div class="container py-5 text-center"><h1>404 - Page Not Found</h1></div>';
    require 'templates/layouts/footer.php';
    exit;
}

// Set page title
$pageTitle = $page['meta_title'] ?: $page['title'];

// Include header
require 'templates/layouts/header.php';
?>

<!-- ALL SECTIONS WILL DISPLAY HERE IN ORDER -->

<!-- 1. Hero Section - Always first -->
<?php include 'templates/sections/hero.php'; ?>

<!-- 2. Skills Section -->
<?php include 'templates/sections/skills.php'; ?>

<!-- 3. Projects Section -->
<?php include 'templates/sections/projects.php'; ?>

<!-- 4. Testimonials Section -->
<?php include 'templates/sections/testimonials.php'; ?>

<!-- 5. Contact Section -->
<?php include 'templates/sections/contact.php'; ?>

<?php
// Include footer
require 'templates/layouts/footer.php';
?>