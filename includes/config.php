<?php
// includes/config.php
// Site Configuration - WITH PROPER GUARD CLAUSE

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
    
    // Get the base path correctly
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim(str_replace('/includes', '', $scriptDir), '/');
    
    defined('BASE_URL') or define('BASE_URL', $protocol . $host . $basePath);
    defined('SITE_NAME') or define('SITE_NAME', 'Kverify Digital Solutions');

    // Paths - Calculate ROOT_PATH based on this file's location
    defined('ROOT_PATH') or define('ROOT_PATH', dirname(__DIR__) . '/');
    defined('INCLUDES_PATH') or define('INCLUDES_PATH', ROOT_PATH . 'includes/');
    defined('ADMIN_PATH') or define('ADMIN_PATH', ROOT_PATH . 'admin/');
    defined('UPLOAD_PATH') or define('UPLOAD_PATH', 'assets/images/uploads/');
    defined('UPLOAD_URL') or define('UPLOAD_URL', '/assets/images/uploads/');

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
?>