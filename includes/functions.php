<?php
// includes/functions.php
// Core Functions - NO CONSTANTS HERE

// Redirect function
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header('Location: ' . $url);
    exit;
}

// Sanitize input
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Create slug from string
function createSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Format date
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

// Time ago function
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}

// Upload file
function uploadFile($file, $directory = 'uploads/') {
    $targetDir = UPLOAD_PATH . $directory;
    
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = getAllowedExtensions();
    
    if (!in_array($extension, $allowed)) {
        return ['error' => 'File type not allowed'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'File too large. Maximum size: 5MB'];
    }
    
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $targetFile = $targetDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => true, 'filename' => $fileName];
    }
    
    return ['error' => 'Failed to upload file'];
}

// Display flash message
function displayFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return '<div class="alert alert-' . $flash['type'] . '">' . $flash['message'] . '</div>';
    }
    return '';
}

// Truncate text
function truncate($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    $text = substr($text, 0, strrpos($text, ' '));
    return $text . $append;
}

// Generate random string
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

// Check if request is AJAX
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// Get client IP address
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}


// Add this to your includes/functions.php

/**
 * Adjust color brightness
 * @param string $hexColor Hex color code
 * @param int $percent Percent to adjust (-100 to 100)
 * @return string Adjusted hex color
 */
function adjustBrightness($hexColor, $percent) {
    if (!$hexColor) {
        return $hexColor;
    }
    
    // Remove # if present
    $hex = ltrim($hexColor, '#');
    
    // Handle shorthand hex
    if (strlen($hex) == 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    
    // Convert to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    // Convert back to hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}


// Add to includes/functions.php

/**
 * Get section icon based on type
 */
function getSectionIcon($type) {
    $icons = [
        'hero' => 'fa-laptop',
        'about' => 'fa-info-circle',
        'services' => 'fa-cogs',
        'portfolio' => 'fa-images',
        'testimonials' => 'fa-star',
        'contact' => 'fa-envelope',
        'cta' => 'fa-bullhorn',
        'features' => 'fa-list',
        'gallery' => 'fa-images',
        'team' => 'fa-users',
        'pricing' => 'fa-tags',
        'faq' => 'fa-question-circle',
        'blog' => 'fa-blog',
        'custom' => 'fa-code',
        'html' => 'fa-code',
        'text' => 'fa-paragraph',
        'image' => 'fa-image'
    ];
    return $icons[$type] ?? 'fa-puzzle-piece';
}

/**
 * Format bytes to human readable
 */
/*function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
*/


// Add this to your includes/functions.php if you want to keep the SEO function

/**
 * Render SEO meta tags for a page
 * @param string $pageUrl Optional page URL to get specific SEO data
 */
function renderSEOMetaTags($pageUrl = null) {
    if (!$pageUrl) {
        $pageUrl = $_SERVER['REQUEST_URI'];
        $pageUrl = strtok($pageUrl, '?');
    }
    
    // Get page data if it exists
    $page = null;
    if ($pageUrl !== '/') {
        $slug = ltrim($pageUrl, '/');
        $page = db()->fetch("SELECT meta_title, meta_description FROM pages WHERE slug = ? AND status = 'published'", [$slug]);
    }
    
    $title = $page['meta_title'] ?? getSetting('site_title', 'Kverify Digital Solutions');
    $description = $page['meta_description'] ?? getSetting('site_description', 'Professional web developer portfolio');
    
    ?>
    <title><?php echo htmlspecialchars($title); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($title); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
    <?php
}
?>

