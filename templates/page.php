<?php
// templates/page.php
// Enhanced page template with additional features

// Get page data
if (!isset($pageSlug)) {
    $pageSlug = 'home';
}

$page = db()->fetch("SELECT * FROM pages WHERE slug = ? AND status = 'published'", [$pageSlug]);

if (!$page) {
    header("HTTP/1.0 404 Not Found");
    require 'templates/layouts/header.php';
    echo '<div class="container py-5 text-center">';
    echo '<h1 class="display-4">404 - Page Not Found</h1>';
    echo '<p class="lead">The page you are looking for does not exist.</p>';
    echo '<a href="' . BASE_URL . '" class="btn btn-primary mt-3">Return to Homepage</a>';
    echo '</div>';
    require 'templates/layouts/footer.php';
    exit;
}

// Set page title with SEO optimization
$pageTitle = !empty($page['meta_title']) ? $page['meta_title'] : $page['title'];
$pageDescription = !empty($page['meta_description']) ? $page['meta_description'] : getSetting('site_description', '');
$pageKeywords = !empty($page['meta_keywords']) ? $page['meta_keywords'] : getSetting('site_keywords', '');

// Add to global meta tags for header to use
$GLOBALS['page_meta'] = [
    'title' => $pageTitle,
    'description' => $pageDescription,
    'keywords' => $pageKeywords
];

// Include header
require 'templates/layouts/header.php';
?>

<!-- Page Title Bar (if not home page) -->
<?php if ($pageSlug !== 'home'): ?>
<section class="page-title-bar bg-light py-3">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 mb-0"><?php echo htmlspecialchars($page['title']); ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($page['title']); ?></li>
                </ol>
            </nav>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Page Content Area (if page has custom content) -->
<?php if (!empty($page['content'])): ?>
<section class="page-content py-4">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php 
                        // Display page content if exists
                        echo $page['content']; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ALL SECTIONS WILL DISPLAY HERE IN ORDER -->

<!-- 1. Hero Section - Always first -->
<?php if (file_exists('templates/sections/hero.php')) include 'templates/sections/hero.php'; ?>

<!-- 2. Skills Section -->
<?php if (file_exists('templates/sections/skills.php')) include 'templates/sections/skills.php'; ?>

<!-- 3. Projects Section -->
<?php if (file_exists('templates/sections/projects.php')) include 'templates/sections/projects.php'; ?>

<!-- 4. Testimonials Section -->
<?php if (file_exists('templates/sections/testimonials.php')) include 'templates/sections/testimonials.php'; ?>

<!-- 5. Contact Section -->
<?php if (file_exists('templates/sections/contact.php')) include 'templates/sections/contact.php'; ?>

<!-- Call to Action Section (New) -->


<?php
// Include footer
require 'templates/layouts/footer.php';
?>