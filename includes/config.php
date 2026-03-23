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
    // Newsletter settings
define('NEWSLETTER_CONFIRMATION', false); // Set to true for double opt-in
define('NEWSLETTER_POPUP_DELAY', 5000); // Milliseconds before popup appears
    
    
    
    // Database
    //define('DB_HOST', 'localhost');
    //define('DB_NAME', 'eceklebg_kverify');
    //define('DB_USER', 'eceklebg_mmke');           // ← change
    //define('DB_PASS', 'Abdulx32@/@!');               // ← change


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
// UPLOAD PATHS - Absolute paths from root
// ========================================

// Ensure ROOT_PATH and BASE_URL are defined
defined('UPLOAD_PATH') or define('UPLOAD_PATH', rtrim(ROOT_PATH, '/') . '/assets/images/uploads/');
defined('UPLOAD_URL') or define('UPLOAD_URL', rtrim(BASE_URL, '/') . '/assets/images/uploads/');

// Helper function to define subdirectory paths and create folders
function define_upload_subdir($name) {
    $sub_path = UPLOAD_PATH . $name . '/';
    $sub_url  = UPLOAD_URL  . $name . '/';
    
    defined('UPLOAD_PATH_' . strtoupper($name)) or define('UPLOAD_PATH_' . strtoupper($name), $sub_path);
    defined('UPLOAD_URL_' . strtoupper($name)) or define('UPLOAD_URL_' . strtoupper($name), $sub_url);
    
    if (!is_dir($sub_path)) {
        mkdir($sub_path, 0755, true);
    }
}

// Define all subfolders
$upload_dirs = ['profiles', 'settings', 'projects', 'testimonials', 'blog', 'sections', 'pages'];
foreach ($upload_dirs as $dir) {
    define_upload_subdir($dir);
}

// ========================================
// UPLOAD FUNCTION - Handles all subfolders
// ========================================
function upload_file($file, $type) {
    $type = strtolower($type); // normalize type

    // Map type to constants
    $path_const = 'UPLOAD_PATH_' . strtoupper($type);
    $url_const  = 'UPLOAD_URL_' . strtoupper($type);

    if (!defined($path_const) || !defined($url_const)) {
        throw new Exception("Invalid upload type: $type");
    }

    $upload_path = constant($path_const);
    $upload_url  = constant($url_const);

    // Create unique filename
    $filename = time() . '_' . preg_replace('/\s+/', '_', basename($file['name']));

    // Move file to correct subfolder
    if (move_uploaded_file($file['tmp_name'], $upload_path . $filename)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $upload_path . $filename,
            'url' => $upload_url . $filename
        ];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
}

// ========================================
// USAGE EXAMPLE
// ========================================
/*
if(isset($_FILES['image'])){
    $result = upload_file($_FILES['image'], 'profiles'); // specify type
    if($result['success']){
        echo "File uploaded to: " . $result['url'];
    } else {
        echo "Error: " . $result['error'];
    }
}
*/

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
    define('MSG_STATUS_UNREAD', 0);
    define('MSG_STATUS_READ', 1);
    define('MSG_STATUS_REPLIED', 2);
    // Message sender types
    define('SENDER_ADMIN', 'admin');
    define('SENDER_CLIENT', 'client');
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