<?php
// index.php - Front Controller
// Handles all URL routing and page loading

require_once 'includes/init.php';

// Get the URL parameter
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';

// Route to appropriate page
if ($url === '') {
    // Home page - try to load from pages table first
    $pageSlug = 'home';
    $page = db()->fetch("SELECT id FROM pages WHERE slug = ? AND status = 'published'", [$pageSlug]);
    
    if ($page) {
        // Load from page management system
        require 'templates/page.php';
    } else {
        // Fallback to old template sections
        require 'templates/layouts/header.php';
        
        // Load default sections
        require 'templates/sections/hero.php';
        require 'templates/sections/skills.php';
        require 'templates/sections/projects.php';
        require 'templates/sections/testimonials.php';
        require 'templates/sections/contact.php';
        
        require 'templates/layouts/footer.php';
    }
} 
elseif ($url === 'projects') {
    // Projects listing page
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $projects = getProjects();
    
    // Set page title
    $pageTitle = 'My Projects';
    $pageDescription = 'Browse my latest web development projects';
    
    require 'templates/layouts/header.php';
    require 'templates/projects-list.php';
    require 'templates/layouts/footer.php';
} 
elseif (strpos($url, 'project/') === 0) {
    // Single project page
    $slug = str_replace('project/', '', $url);
    $project = getProject($slug);
    
    if (!$project) {
        // Project not found - show 404
        header("HTTP/1.0 404 Not Found");
        $pageTitle = 'Project Not Found';
        require 'templates/layouts/header.php';
        echo '<div class="container py-5 text-center">';
        echo '<h1>404 - Project Not Found</h1>';
        echo '<p>The project you are looking for does not exist.</p>';
        echo '<a href="' . BASE_URL . '/projects" class="btn btn-primary mt-3">View All Projects</a>';
        echo '</div>';
        require 'templates/layouts/footer.php';
        exit;
    }
    
    // Set page title from project
    $pageTitle = $project['title'];
    $pageDescription = $project['short_description'] ?? 'Project details';
    
    require 'templates/layouts/header.php';
    require 'templates/project-single.php';
    require 'templates/layouts/footer.php';
} 
elseif ($url === 'blog') {
    // Blog listing page
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $posts = getBlogPosts($page);
    
    $pageTitle = 'Blog';
    $pageDescription = 'Read my latest articles and tutorials';
    
    require 'templates/layouts/header.php';
    require 'templates/blog-list.php';
    require 'templates/layouts/footer.php';
} 
elseif (strpos($url, 'blog/') === 0) {
    // Single blog post
    $slug = str_replace('blog/', '', $url);
    $post = getBlogPost($slug);
    
    if (!$post) {
        header("HTTP/1.0 404 Not Found");
        $pageTitle = 'Post Not Found';
        require 'templates/layouts/header.php';
        echo '<div class="container py-5 text-center"><h1>404 - Post Not Found</h1></div>';
        require 'templates/layouts/footer.php';
        exit;
    }
    
    $pageTitle = $post['title'];
    $pageDescription = $post['excerpt'] ?? '';
    
    require 'templates/layouts/header.php';
    require 'templates/blog-single.php';
    require 'templates/layouts/footer.php';
} 
elseif ($url === 'contact') {
    // Handle contact form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = [
            'name' => sanitize($_POST['name']),
            'email' => sanitize($_POST['email']),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'company' => sanitize($_POST['company'] ?? ''),
            'subject' => sanitize($_POST['subject'] ?? ''),
            'message' => sanitize($_POST['message']),
            'budget_range' => sanitize($_POST['budget_range'] ?? '')
        ];
        
        if (saveContactMessage($data)) {
            // Send email notification
            $to = getSetting('contact_email');
            if ($to) {
                $subject = "New Contact Form Submission: " . $data['subject'];
                $message = "Name: " . $data['name'] . "\n";
                $message .= "Email: " . $data['email'] . "\n";
                $message .= "Message: " . $data['message'];
                mail($to, $subject, $message);
            }
            
            $_SESSION['contact_success'] = true;
        }
        redirect('/contact?success=1');
    }
    
    // Try to load contact page from pages table
    $pageSlug = 'contact';
    $page = db()->fetch("SELECT id FROM pages WHERE slug = ? AND status = 'published'", [$pageSlug]);
    
    if ($page) {
        // Load from page management system
        require 'templates/page.php';
    } else {
        // Fallback to old contact template
        $pageTitle = 'Contact Me';
        $pageDescription = 'Get in touch with me';
        require 'templates/layouts/header.php';
        require 'templates/contact-form.php';
        require 'templates/layouts/footer.php';
    }
} 
else {
    // Try to load page from database
    $pageSlug = $url;
    $page = db()->fetch("SELECT id FROM pages WHERE slug = ? AND status = 'published'", [$pageSlug]);
    
    if ($page) {
        // Load from page management system
        require 'templates/page.php';
    } else {
        // 404 page
        header("HTTP/1.0 404 Not Found");
        $pageTitle = 'Page Not Found';
        require 'templates/layouts/header.php';
        echo '<div class="container py-5 text-center">';
        echo '<h1>404 - Page Not Found</h1>';
        echo '<p class="lead">The page you are looking for does not exist.</p>';
        echo '<a href="' . BASE_URL . '" class="btn btn-primary mt-3">Go to Homepage</a>';
        echo '</div>';
        require 'templates/layouts/footer.php';
    }
}
?>