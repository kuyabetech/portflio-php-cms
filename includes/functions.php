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
/**
 * Upload file with type-specific paths
 * @param array $file $_FILES array element
 * @param string $type Upload type (profiles, settings, projects, testimonials, blog, sections, pages)
 * @return array Result with success/filename or error
 */
function uploadFile($file, $type = '') {
    // Get the correct upload path based on type
    $targetDir = getUploadPath($type);
    
    // Create directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = getAllowedExtensions();
    
    if (!in_array($extension, $allowed)) {
        return ['error' => 'File type not allowed. Allowed: ' . implode(', ', $allowed)];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'File too large. Maximum size: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'];
    }
    
    // Generate unique filename
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $targetFile = $targetDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return [
            'success' => true, 
            'filename' => $fileName,
            'path' => $targetFile,
            'url' => getUploadUrl($type) . $fileName,
            'type' => $type
        ];
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



/**
 * Get a single blog post by slug
 * @param string $slug The post slug
 * @return array|null Post data or null if not found
 */
function getBlogPost($slug) {
    try {
        return db()->fetch("
            SELECT p.*, u.username as author_name,
                   c.name as category_name, c.slug as category_slug
            FROM blog_posts p
            LEFT JOIN users u ON p.author_id = u.id
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.slug = ? AND p.status = 'published'
        ", [$slug]);
    } catch (Exception $e) {
        error_log("Error in getBlogPost: " . $e->getMessage());
        return null;
    }
}

/**
 * Get blog posts with pagination
 * @param int $page Page number
 * @param int $perPage Posts per page
 * @return array Array of blog posts
 */
function getBlogPosts($page = 1, $perPage = 10) {
    try {
        $offset = ($page - 1) * $perPage;
        return db()->fetchAll("
            SELECT p.*, u.username as author_name,
                   c.name as category_name,
                   (SELECT COUNT(*) FROM blog_comments WHERE post_id = p.id AND is_approved = 1) as comment_count
            FROM blog_posts p
            LEFT JOIN users u ON p.author_id = u.id
            LEFT JOIN blog_categories c ON p.category_id = c.id
            WHERE p.status = 'published'
            ORDER BY p.published_at DESC
            LIMIT ? OFFSET ?
        ", [$perPage, $offset]);
    } catch (Exception $e) {
        error_log("Error in getBlogPosts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total count of published blog posts
 * @return int Total posts count
 */
function getTotalBlogPosts() {
    try {
        $result = db()->fetch("SELECT COUNT(*) as count FROM blog_posts WHERE status = 'published'");
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        error_log("Error in getTotalBlogPosts: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get blog posts by category
 * @param int $categoryId Category ID
 * @param int $limit Number of posts to return
 * @return array Array of blog posts
 */
function getBlogPostsByCategory($categoryId, $limit = 5) {
    try {
        return db()->fetchAll("
            SELECT p.*, u.username as author_name
            FROM blog_posts p
            LEFT JOIN users u ON p.author_id = u.id
            WHERE p.category_id = ? AND p.status = 'published'
            ORDER BY p.published_at DESC
            LIMIT ?
        ", [$categoryId, $limit]);
    } catch (Exception $e) {
        error_log("Error in getBlogPostsByCategory: " . $e->getMessage());
        return [];
    }
}

/**
 * Get blog posts by tag
 * @param string $tagSlug Tag slug
 * @param int $limit Number of posts to return
 * @return array Array of blog posts
 */
function getBlogPostsByTag($tagSlug, $limit = 5) {
    try {
        return db()->fetchAll("
            SELECT p.*, u.username as author_name
            FROM blog_posts p
            JOIN blog_post_tags pt ON p.id = pt.post_id
            JOIN blog_tags t ON pt.tag_id = t.id
            WHERE t.slug = ? AND p.status = 'published'
            ORDER BY p.published_at DESC
            LIMIT ?
        ", [$tagSlug, $limit]);
    } catch (Exception $e) {
        error_log("Error in getBlogPostsByTag: " . $e->getMessage());
        return [];
    }
}

/**
 * Get related blog posts
 * @param int $postId Current post ID
 * @param int $categoryId Category ID
 * @param int $limit Number of related posts
 * @return array Array of related posts
 */
function getRelatedBlogPosts($postId, $categoryId, $limit = 3) {
    try {
        return db()->fetchAll("
            SELECT title, slug, published_at, featured_image 
            FROM blog_posts 
            WHERE status = 'published' AND id != ? AND category_id = ?
            ORDER BY published_at DESC 
            LIMIT ?
        ", [$postId, $categoryId, $limit]);
    } catch (Exception $e) {
        error_log("Error in getRelatedBlogPosts: " . $e->getMessage());
        return [];
    }
}

function logActivity($type, $itemId, $action) {
    // Check if activity_log table exists
    $tableExists = db()->fetch("SHOW TABLES LIKE 'activity_log'");
    
    if ($tableExists) {
        db()->insert('activity_log', [
            'user_id' => $_SESSION['user_id'] ?? 0,
            'type' => $type,
            'item_id' => $itemId,
            'action' => $action,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        // Fallback to error log
        error_log("Activity Log [{$type}] ID: {$itemId} - {$action} by User: " . ($_SESSION['user_id'] ?? 'guest'));
    }
}

/**
 * Log client activity
 */
function logClientActivity($clientId, $action, $description, $icon = 'fa-circle') {
    try {
        db()->insert('client_activity_log', [
            'client_id' => $clientId,
            'action' => $action,
            'description' => $description,
            'icon' => $icon,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Add client notification
 */
function addClientNotification($clientId, $type, $title, $message, $icon = 'fa-info-circle', $link = null) {
    try {
        db()->insert('client_notifications', [
            'client_id' => $clientId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'link' => $link
        ]);
    } catch (Exception $e) {
        error_log("Failed to add notification: " . $e->getMessage());
    }
}


/**
 * Generate 2FA Secret
 */
function generate2FASecret() {
    return base64_encode(random_bytes(20));
}

/**
 * Generate 2FA QR Code URL
 */
function generate2FAQRCode($email, $secret) {
    $company = SITE_NAME;
    $qrData = "otpauth://totp/$company:$email?secret=$secret&issuer=$company";
    return "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($qrData);
}

/**
 * Verify 2FA Code
 */
function verify2FACode($secret, $code) {
    // This would use a library like Google2FA
    // For now, return true for demo
    return true;
}

/**
 * Get Device Info from User Agent
 */
function getDeviceInfo($userAgent) {
    if (preg_match('/mobile/i', $userAgent)) {
        return 'Mobile Device';
    } elseif (preg_match('/tablet|ipad/i', $userAgent)) {
        return 'Tablet';
    } elseif (preg_match('/bot|crawler|spider/i', $userAgent)) {
        return 'Bot/Crawler';
    } else {
        return 'Desktop Computer';
    }
}



/**
 * Log client session
 */
function logClientSession($clientId) {
    try {
        $sessionId = session_id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check if session exists
        $existing = db()->fetch("SELECT id FROM client_sessions WHERE session_id = ?", [$sessionId]);
        
        if ($existing) {
            // Update existing session
            db()->update('client_sessions', [
                'last_activity' => date('Y-m-d H:i:s')
            ], 'id = ?', [$existing['id']]);
        } else {
            // Create new session
            db()->insert('client_sessions', [
                'client_id' => $clientId,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'last_activity' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    } catch (Exception $e) {
        error_log("Session logging error: " . $e->getMessage());
    }
}

/**
 * Add admin notification
 */
function addAdminNotification($type, $data = []) {
    try {
        $message = '';
        
        switch ($type) {
            case 'new_ticket':
                $message = "New support ticket from {$data['client_name']}: {$data['subject']} (#{$data['ticket_number']})";
                break;
            case 'account_deletion_request':
                $message = "Account deletion request from {$data['client_name']} ({$data['client_email']})";
                break;
            case 'new_client_message':
                $message = "New message from client: {$data['client_name']} - {$data['subject']}";
                break;
        }
        
        if (!empty($message)) {
            db()->insert('admin_notifications', [
                'type' => $type,
                'message' => $message,
                'data' => json_encode($data),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    } catch (Exception $e) {
        error_log("Admin notification error: " . $e->getMessage());
    }
}

?>

