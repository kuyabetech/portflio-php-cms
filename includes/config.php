<?php
// includes/config.php
// Site Configuration - Works in both Admin and Frontend

// Only define if not already defined
if (!defined('CONFIG_INITIALIZED')) {
    
    // Error reporting (disable in production)
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Timezone
    date_default_timezone_set('UTC');

    // Database configuration
    defined('DB_HOST') or define('DB_HOST', 'localhost');
    defined('DB_NAME') or define('DB_NAME', 'kverify_portfolio');
    defined('DB_USER') or define('DB_USER', 'root');
    defined('DB_PASS') or define('DB_PASS', '');

    // Site URLs - Use $_SERVER to detect base URL dynamically
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8081';
    
    // Get the current script directory
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    
    // Remove /admin from path if we're in admin
    $basePath = preg_replace('/\/admin$/', '', rtrim($scriptDir, '/'));
    
    defined('BASE_URL') or define('BASE_URL', rtrim($protocol . $host . $basePath, '/'));
    defined('SITE_NAME') or define('SITE_NAME', 'Kverify Digital Solutions');

    // ========================================
    // FIXED: ROOT_PATH - Works in both admin and frontend
    // ========================================
    
    // Calculate ROOT_PATH based on this file's location
    // This file is in /includes/, so ROOT_PATH is one level up
    $rootPath = realpath(__DIR__ . '/../') . '/';
    defined('ROOT_PATH') or define('ROOT_PATH', $rootPath);
    
    // Other paths
    defined('INCLUDES_PATH') or define('INCLUDES_PATH', ROOT_PATH . 'includes/');
    defined('ADMIN_PATH') or define('ADMIN_PATH', ROOT_PATH . 'admin/');
    
    // ========================================
    // FIXED: UPLOAD PATHS - Absolute paths from root
    // ========================================
    
    // Physical file path on server (for file operations)
    defined('UPLOAD_PATH') or define('UPLOAD_PATH', ROOT_PATH . 'assets/images/uploads/');
    
    // URL path for web access (for img src attributes)
    defined('UPLOAD_URL') or define('UPLOAD_URL', BASE_URL . '/assets/images/uploads/');
    
    // Sub-directories for different upload types
    defined('UPLOAD_PATH_PROFILES') or define('UPLOAD_PATH_PROFILES', UPLOAD_PATH . 'profiles/');
    defined('UPLOAD_URL_PROFILES') or define('UPLOAD_URL_PROFILES', UPLOAD_URL . 'profiles/');
    
    defined('UPLOAD_PATH_SETTINGS') or define('UPLOAD_PATH_SETTINGS', UPLOAD_PATH . 'settings/');
    defined('UPLOAD_URL_SETTINGS') or define('UPLOAD_URL_SETTINGS', UPLOAD_URL . 'settings/');
    
    defined('UPLOAD_PATH_PROJECTS') or define('UPLOAD_PATH_PROJECTS', UPLOAD_PATH . 'projects/');
    defined('UPLOAD_URL_PROJECTS') or define('UPLOAD_URL_PROJECTS', UPLOAD_URL . 'projects/');
    
    defined('UPLOAD_PATH_TESTIMONIALS') or define('UPLOAD_PATH_TESTIMONIALS', UPLOAD_PATH . 'testimonials/');
    defined('UPLOAD_URL_TESTIMONIALS') or define('UPLOAD_URL_TESTIMONIALS', UPLOAD_URL . 'testimonials/');
    
    defined('UPLOAD_PATH_BLOG') or define('UPLOAD_PATH_BLOG', UPLOAD_PATH . 'blog/');
    defined('UPLOAD_URL_BLOG') or define('UPLOAD_URL_BLOG', UPLOAD_URL . 'blog/');
    
    defined('UPLOAD_PATH_SECTIONS') or define('UPLOAD_PATH_SECTIONS', UPLOAD_PATH . 'sections/');
    defined('UPLOAD_URL_SECTIONS') or define('UPLOAD_URL_SECTIONS', UPLOAD_URL . 'sections/');
    
    defined('UPLOAD_PATH_PAGES') or define('UPLOAD_PATH_PAGES', UPLOAD_PATH . 'pages/');
    defined('UPLOAD_URL_PAGES') or define('UPLOAD_URL_PAGES', UPLOAD_URL . 'pages/');

    // Upload settings
    defined('MAX_FILE_SIZE') or define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
    defined('ALLOWED_EXTENSIONS') or define('ALLOWED_EXTENSIONS', serialize(['jpg', 'jpeg', 'png', 'gif', 'webp']));

    // Security
    defined('HASH_COST') or define('HASH_COST', 12);
    defined('CSRF_TOKEN_NAME') or define('CSRF_TOKEN_NAME', 'csrf_token');
    defined('SESSION_TIMEOUT') or define('SESSION_TIMEOUT', 3600); // 1 hour

    // Pagination
    defined('ITEMS_PER_PAGE') or define('ITEMS_PER_PAGE', 10);

    // Mark config as initialized
    define('CONFIG_INITIALIZED', true);
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function to get allowed extensions
function getAllowedExtensions() {
    return unserialize(ALLOWED_EXTENSIONS);
}

// Helper function to get correct upload path for a specific type
function getUploadPath($type = '') {
    $paths = [
        'profiles' => UPLOAD_PATH_PROFILES,
        'settings' => UPLOAD_PATH_SETTINGS,
        'projects' => UPLOAD_PATH_PROJECTS,
        'testimonials' => UPLOAD_PATH_TESTIMONIALS,
        'blog' => UPLOAD_PATH_BLOG,
        'sections' => UPLOAD_PATH_SECTIONS,
        'pages' => UPLOAD_PATH_PAGES,
        '' => UPLOAD_PATH
    ];
    
    return $paths[$type] ?? UPLOAD_PATH;
}

// Helper function to get correct upload URL for a specific type
function getUploadUrl($type = '') {
    $urls = [
        'profiles' => UPLOAD_URL_PROFILES,
        'settings' => UPLOAD_URL_SETTINGS,
        'projects' => UPLOAD_URL_PROJECTS,
        'testimonials' => UPLOAD_URL_TESTIMONIALS,
        'blog' => UPLOAD_URL_BLOG,
        'sections' => UPLOAD_URL_SECTIONS,
        'pages' => UPLOAD_URL_PAGES,
        '' => UPLOAD_URL
    ];
    
    return $urls[$type] ?? UPLOAD_URL;
}

// Debug function (remove in production)
function debugPath($path) {
    echo "<!-- Path: $path - Exists: " . (file_exists($path) ? 'Yes' : 'No') . " -->\n";
}
?>