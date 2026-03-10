<?php
/**
 * index.php - Front Controller
 * Handles all incoming requests and routes them to appropriate handlers
 */

require_once 'includes/init.php';

// Enable debug logging only in development
if (defined('DEV_MODE') && DEV_MODE) {
    error_log("Front Controller accessed: " . ($_GET['url'] ?? 'home'));
}

/**
 * Router class to handle all routing logic
 */
class Router {
    private $routes = [];
    private $notFoundHandler;
    
    /**
     * Add a route to the router
     */
    public function addRoute($method, $path, $handler) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'pattern' => $this->convertToPattern($path)
        ];
        return $this;
    }
    
    /**
     * Set 404 handler
     */
    public function setNotFoundHandler($handler) {
        $this->notFoundHandler = $handler;
        return $this;
    }
    
    /**
     * Dispatch the request to appropriate handler
     */
    public function dispatch($url, $method) {
        $url = $this->normalizeUrl($url);
        $method = strtoupper($method);
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            if (preg_match($route['pattern'], $url, $matches)) {
                // Remove numeric keys, keep named parameters
                $params = array_filter($matches, function($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);
                
                return $this->executeHandler($route['handler'], $params);
            }
        }
        
        // No route found - execute 404 handler
        return $this->executeHandler($this->notFoundHandler);
    }
    
    /**
     * Convert route path to regex pattern
     */
    private function convertToPattern($path) {
        // Replace {param} with named capture group
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
    
    /**
     * Normalize URL by removing trailing slash and empty segments
     */
    private function normalizeUrl($url) {
        $url = trim($url, '/');
        return $url === '' ? '/' : '/' . $url;
    }
    
    /**
     * Execute the handler with parameters
     */
    private function executeHandler($handler, $params = []) {
        if (is_callable($handler)) {
            return call_user_func($handler, $params);
        } elseif (is_string($handler) && method_exists($this, $handler)) {
            return call_user_func([$this, $handler], $params);
        }
        
        throw new Exception("Invalid route handler");
    }
}

/**
 * Page Controller - Handles page rendering
 */
class PageController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Display homepage
     */
    public function home() {
        // Check if homepage exists in database
        $page = $this->db->fetch(
            "SELECT id FROM pages WHERE slug = ? AND status = 'published'", 
            ['home']
        );
        
        if ($page) {
            // Load dynamic page
            require 'templates/page.php';
        } else {
            // Load static homepage sections
            $this->renderStaticHome();
        }
    }
    
    /**
     * Render static homepage sections
     */
    private function renderStaticHome() {
        $this->renderHeader();
        require 'templates/sections/hero.php';
        require 'templates/sections/skills.php';
        require 'templates/sections/projects.php';
        require 'templates/sections/testimonials.php';
        require 'templates/sections/contact.php';
        $this->renderFooter();
    }
    
    /**
     * Display a page by slug
     */
    public function page($params) {
        $slug = $params['slug'] ?? '';
        
        // Validate slug
        if (!$this->isValidSlug($slug)) {
            return $this->notFound('Invalid page URL');
        }
        
        // Get page from database
        $page = $this->db->fetch(
            "SELECT * FROM pages WHERE slug = ? AND status = 'published'", 
            [$slug]
        );
        
        if (!$page) {
            return $this->notFound('Page not found');
        }
        
        $pageTitle = $page['title'];
        require 'templates/page.php';
    }
    
    /**
     * Display blog listing
     */
    public function blog() {
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $category = isset($_GET['category']) ? (int)$_GET['category'] : null;
        $tag = isset($_GET['tag']) ? sanitize($_GET['tag']) : null;
        
        $perPage = 6;
        $offset = ($page - 1) * $perPage;
        
        // Build query based on filters
        $where = ["p.status = 'published'"];
        $params = [];
        
        if ($category) {
            $where[] = "p.category_id = ?";
            $params[] = $category;
        }
        
        if ($tag) {
            $where[] = "p.id IN (SELECT post_id FROM blog_post_tags WHERE tag_id = (SELECT id FROM blog_tags WHERE slug = ?))";
            $params[] = $tag;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get posts
        $posts = $this->db->fetchAll(
            "SELECT p.*, u.username as author_name,
                    c.name as category_name, c.slug as category_slug,
                    (SELECT COUNT(*) FROM blog_comments WHERE post_id = p.id AND is_approved = 1) as comment_count
             FROM blog_posts p
             LEFT JOIN users u ON p.author_id = u.id
             LEFT JOIN blog_categories c ON p.category_id = c.id
             WHERE $whereClause
             ORDER BY p.published_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );
        
        // Get total count for pagination
        $totalPosts = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM blog_posts WHERE $whereClause",
            $params
        );
        
        $totalPages = ceil($totalPosts / $perPage);
        
        // Get categories for sidebar
        $categories = $this->db->fetchAll(
            "SELECT c.*, COUNT(p.id) as post_count 
             FROM blog_categories c
             LEFT JOIN blog_posts p ON c.id = p.category_id AND p.status = 'published'
             WHERE c.is_active = 1
             GROUP BY c.id
             ORDER BY c.display_order"
        );
        
        // Get popular tags
        $tags = $this->db->fetchAll(
            "SELECT t.*, COUNT(pt.post_id) as post_count 
             FROM blog_tags t
             JOIN blog_post_tags pt ON t.id = pt.tag_id
             JOIN blog_posts p ON pt.post_id = p.id
             WHERE p.status = 'published'
             GROUP BY t.id
             ORDER BY post_count DESC
             LIMIT 10"
        );
        
        $this->renderHeader();
        require 'templates/blog.php';
        $this->renderFooter();
    }
    
    /**
     * Display single blog post
     */
    public function blogPost($params) {
        $slug = $params['slug'] ?? '';
        
        // Validate slug format
        if (!$this->isValidSlug($slug)) {
            return $this->notFound('Invalid blog post URL');
        }
        
        // Get post from database
        $post = $this->db->fetch("
            SELECT p.*, u.username as author_name,
                   c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN users u ON p.author_id = u.id
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.slug = ? AND p.status = 'published'
        ", [$slug]);
        
        if (!$post) {
            return $this->notFound('Blog post not found');
        }
        
        // Update view count
        $this->db->update(
            'blog_posts', 
            ['views' => ($post['views'] ?? 0) + 1], 
            'id = :id', 
            ['id' => $post['id']]
        );
        
        // Get comments for this post
        $comments = $this->db->fetchAll(
            "SELECT * FROM blog_comments 
             WHERE post_id = ? AND is_approved = 1 AND parent_id = 0
             ORDER BY created_at DESC",
            [$post['id']]
        );
        
        // Get tags for this post
        $tags = $this->db->fetchAll(
            "SELECT t.* FROM blog_tags t
             JOIN blog_post_tags pt ON t.id = pt.tag_id
             WHERE pt.post_id = ?",
            [$post['id']]
        );
        
        // Get related posts
        $related = $this->db->fetchAll(
            "SELECT title, slug, published_at, featured_image 
             FROM blog_posts 
             WHERE status = 'published' AND id != ? AND category_id = ?
             ORDER BY published_at DESC 
             LIMIT 3",
            [$post['id'], $post['category_id']]
        );
        
        // Set page variables
        $pageTitle = $post['title'];
        $pageDescription = $post['excerpt'] ?? '';
        
        $this->renderHeader();
        require 'templates/blog-single.php';
        $this->renderFooter();
    }
    
    /**
     * Display projects listing
     */
    public function projects() {
        $projects = getProjects(); // From your functions file
        
        $this->renderHeader();
        require 'templates/projects-list.php';
        $this->renderFooter();
    }
    
    /**
     * Display single project
     */
    public function project($params) {
        $slug = $params['slug'] ?? '';
        
        // Validate slug
        if (!$this->isValidSlug($slug)) {
            return $this->notFound('Invalid project URL');
        }
        
        $project = getProject($slug); // From your functions file
        
        if (!$project) {
            return $this->notFound('Project not found');
        }
        
        $pageTitle = $project['title'];
        
        $this->renderHeader();
        require 'templates/project-single.php';
        $this->renderFooter();
    }
    
    /**
     * Handle contact page and form submission
     */
    public function contact() {
        // Handle POST request (form submission)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleContactSubmit();
        }
        
        // Check if contact page exists in database
        $page = $this->db->fetch(
            "SELECT id FROM pages WHERE slug = ? AND status = 'published'", 
            ['contact']
        );
        
        if ($page) {
            require 'templates/page.php';
        } else {
            $this->renderHeader();
            require 'templates/contact-form.php';
            $this->renderFooter();
        }
    }
    
    /**
     * Handle contact form submission
     */
    private function handleContactSubmit() {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !Auth::verifyCSRF($_POST['csrf_token'])) {
            $_SESSION['contact_error'] = 'Invalid security token';
            redirect('/contact');
            return;
        }
        
        // Validate required fields
        $errors = $this->validateContactForm($_POST);
        
        if (!empty($errors)) {
            $_SESSION['contact_errors'] = $errors;
            $_SESSION['contact_data'] = $_POST;
            redirect('/contact');
            return;
        }
        
        // Sanitize and prepare data
        $data = [
            'name' => sanitize($_POST['name']),
            'email' => sanitize($_POST['email']),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'company' => sanitize($_POST['company'] ?? ''),
            'subject' => sanitize($_POST['subject'] ?? ''),
            'message' => sanitize($_POST['message']),
            'budget_range' => sanitize($_POST['budget_range'] ?? ''),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        // Save to database
        if (saveContactMessage($data)) {
            // Send email notification
            $this->sendContactNotification($data);
            
            $_SESSION['contact_success'] = true;
            redirect('/contact?success=1');
        } else {
            $_SESSION['contact_error'] = 'Failed to send message. Please try again.';
            redirect('/contact');
        }
    }
    
    /**
     * Validate contact form data
     */
    private function validateContactForm($data) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }
        
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        if (empty($data['message'])) {
            $errors['message'] = 'Message is required';
        } elseif (strlen($data['message']) < 10) {
            $errors['message'] = 'Message must be at least 10 characters';
        }
        
        // Honeypot for spam prevention
        if (!empty($data['website'])) {
            $errors['spam'] = 'Spam detected';
        }
        
        return $errors;
    }
    
    /**
     * Send email notification for contact form
     */
    private function sendContactNotification($data) {
        $to = CONTACT_EMAIL;
        $subject = "New Contact Form Message: {$data['subject']}";
        
        $message = "
            <h2>New Contact Form Submission</h2>
            <p><strong>Name:</strong> {$data['name']}</p>
            <p><strong>Email:</strong> {$data['email']}</p>
            <p><strong>Phone:</strong> {$data['phone']}</p>
            <p><strong>Company:</strong> {$data['company']}</p>
            <p><strong>Subject:</strong> {$data['subject']}</p>
            <p><strong>Budget Range:</strong> {$data['budget_range']}</p>
            <p><strong>Message:</strong></p>
            <p>" . nl2br($data['message']) . "</p>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Reply-To: {$data['email']}\r\n";
        
        @mail($to, $subject, $message, $headers);
    }
    
    /**
     * 404 Not Found handler
     */
    public function notFound($message = 'Page Not Found') {
        header("HTTP/1.0 404 Not Found");
        
        $this->renderHeader();
        echo '<div class="container py-5 text-center">';
        echo '<h1>404 - ' . htmlspecialchars($message) . '</h1>';
        echo '<p>The page you are looking for does not exist.</p>';
        echo '<a href="' . BASE_URL . '" class="btn btn-primary mt-3">Go to Homepage</a>';
        echo '</div>';
        $this->renderFooter();
        
        exit;
    }
    
    /**
     * Render header template
     */
    private function renderHeader() {
        require 'templates/layouts/header.php';
    }
    
    /**
     * Render footer template
     */
    private function renderFooter() {
        require 'templates/layouts/footer.php';
    }
    
    /**
     * Validate URL slug format
     */
    private function isValidSlug($slug) {
        return preg_match('/^[a-z0-9-]+$/', $slug);
    }
}

// =============================================================================
// ROUTING SETUP
// =============================================================================

// Initialize router and controller
$router = new Router();
$controller = new PageController(db());

// Define routes - ORDER MATTERS! More specific routes first
$router
    // Home page
    ->addRoute('GET', '/', [$controller, 'home'])
    
    // Blog routes (specific before generic)
    ->addRoute('GET', '/blog', [$controller, 'blog'])
    ->addRoute('GET', '/blog/{slug}', [$controller, 'blogPost'])
    
    // Projects routes
    ->addRoute('GET', '/projects', [$controller, 'projects'])
    ->addRoute('GET', '/project/{slug}', [$controller, 'project'])
    
    // Contact routes
    ->addRoute('GET', '/contact', [$controller, 'contact'])
    ->addRoute('POST', '/contact', [$controller, 'contact'])
    
    // Dynamic pages (must be last as it's a catch-all)
    ->addRoute('GET', '/{slug}', [$controller, 'page'])
    
    // 404 handler
    ->setNotFoundHandler([$controller, 'notFound']);

// =============================================================================
// DISPATCH THE REQUEST
// =============================================================================

try {
    // Get the requested URL
    $url = $_GET['url'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Dispatch the request
    $router->dispatch($url, $method);
    
} catch (Exception $e) {
    // Log error and show friendly message
    error_log("Routing error: " . $e->getMessage());
    
    if (defined('DEV_MODE') && DEV_MODE) {
        throw $e;
    }
    
    // Show generic error page
    header("HTTP/1.0 500 Internal Server Error");
    require 'templates/layouts/header.php';
    echo '<div class="container py-5 text-center">';
    echo '<h1>500 - Internal Server Error</h1>';
    echo '<p>Something went wrong. Please try again later.</p>';
    echo '<a href="' . BASE_URL . '" class="btn btn-primary mt-3">Go to Homepage</a>';
    echo '</div>';
    require 'templates/layouts/footer.php';
}