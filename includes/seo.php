<?php
// includes/seo.php
// SEO Helper Functions

function renderSEOMetaTags($pageUrl = null) {
    if (!$pageUrl) {
        $pageUrl = $_SERVER['REQUEST_URI'];
        // Remove query string
        $pageUrl = strtok($pageUrl, '?');
    }
    
    // Get SEO metadata for this page
    $seo = db()->fetch("SELECT * FROM seo_metadata WHERE page_url = ?", [$pageUrl]);
    
    if (!$seo) {
        // Try to find by pattern matching for dynamic pages
        if (preg_match('/^\/blog\/(.+)/', $pageUrl, $matches)) {
            $post = db()->fetch("SELECT title, excerpt FROM blog_posts WHERE slug = ?", [$matches[1]]);
            if ($post) {
                $seo = [
                    'title' => $post['title'],
                    'meta_description' => $post['excerpt'],
                    'og_title' => $post['title'],
                    'og_description' => $post['excerpt']
                ];
            }
        } elseif (preg_match('/^\/project\/(.+)/', $pageUrl, $matches)) {
            $project = db()->fetch("SELECT title, short_description FROM projects WHERE slug = ?", [$matches[1]]);
            if ($project) {
                $seo = [
                    'title' => $project['title'],
                    'meta_description' => $project['short_description'],
                    'og_title' => $project['title'],
                    'og_description' => $project['short_description']
                ];
            }
        }
    }
    
    if (!$seo) {
        // Default values
        $seo = [
            'title' => getSetting('site_title', 'Kverify Digital Solutions'),
            'meta_description' => getSetting('site_description', 'Professional web developer portfolio'),
            'og_title' => getSetting('site_title'),
            'og_description' => getSetting('site_description'),
            'og_image' => getSetting('og_image'),
            'twitter_title' => getSetting('site_title'),
            'twitter_description' => getSetting('site_description'),
            'twitter_image' => getSetting('twitter_image'),
            'canonical_url' => BASE_URL . $pageUrl,
            'noindex' => 0,
            'nofollow' => 0,
            'schema_markup' => null
        ];
    }
    
    // Render meta tags
    ?>
    <!-- Primary Meta Tags -->
    <title><?php echo htmlspecialchars($seo['title'] ?? getSetting('site_title')); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($seo['title'] ?? getSetting('site_title')); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($seo['meta_description'] ?? getSetting('site_description')); ?>">
    
    <?php if (!empty($seo['meta_keywords'])): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($seo['meta_keywords']); ?>">
    <?php endif; ?>
    
    <?php if ($seo['noindex'] ?? false): ?>
    <meta name="robots" content="noindex<?php echo $seo['nofollow'] ? ',nofollow' : ''; ?>">
    <?php else: ?>
    <meta name="robots" content="index<?php echo $seo['nofollow'] ? ',nofollow' : ''; ?>">
    <?php endif; ?>
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($seo['canonical_url'] ?? BASE_URL . $pageUrl); ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($seo['canonical_url'] ?? BASE_URL . $pageUrl); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($seo['og_title'] ?? $seo['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo['og_description'] ?? $seo['meta_description']); ?>">
    <?php if (!empty($seo['og_image'])): ?>
    <meta property="og:image" content="<?php echo UPLOAD_URL . 'seo/' . $seo['og_image']; ?>">
    <?php endif; ?>
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo htmlspecialchars($seo['canonical_url'] ?? BASE_URL . $pageUrl); ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($seo['twitter_title'] ?? $seo['title']); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($seo['twitter_description'] ?? $seo['meta_description']); ?>">
    <?php if (!empty($seo['twitter_image'])): ?>
    <meta property="twitter:image" content="<?php echo UPLOAD_URL . 'seo/' . $seo['twitter_image']; ?>">
    <?php endif; ?>
    
    <!-- Schema.org markup -->
    <?php if (!empty($seo['schema_markup'])): ?>
    <script type="application/ld+json">
    <?php echo $seo['schema_markup']; ?>
    </script>
    <?php endif; ?>
    <?php
}

// Handle 301 redirects
function checkRedirect() {
    $requestUri = $_SERVER['REQUEST_URI'];
    // Remove query string
    $path = strtok($requestUri, '?');
    
    $redirect = db()->fetch("SELECT * FROM seo_redirects WHERE old_url = ?", [$path]);
    
    if ($redirect) {
        // Update hit count
        db()->update('seo_redirects', 
            ['hits' => $redirect['hits'] + 1], 
            'id = :id', 
            ['id' => $redirect['id']]
        );
        
        header("Location: " . $redirect['new_url'], true, $redirect['status_code']);
        exit;
    }
}

// Track page view for analytics
function trackPageView() {
    if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'bot') === false) {
        $data = [
            'page_url' => $_SERVER['REQUEST_URI'],
            'page_type' => getPageType($_SERVER['REQUEST_URI']),
            'visitor_ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'referrer_url' => $_SERVER['HTTP_REFERER'] ?? null,
            'visit_date' => date('Y-m-d'),
            'visit_time' => date('H:i:s'),
            'device_type' => getDeviceType(),
            'browser' => getBrowser(),
            'os' => getOS(),
            'language' => substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2)
        ];
        
        db()->insert('seo_analytics', $data);
    }
}

function getPageType($url) {
    if ($url === '/') return 'home';
    if (strpos($url, '/blog/') === 0) return 'blog_post';
    if (strpos($url, '/project/') === 0) return 'project';
    if (strpos($url, '/projects') === 0) return 'projects';
    if (strpos($url, '/contact') === 0) return 'contact';
    return 'other';
}

function getDeviceType() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    if (preg_match('/mobile/i', $userAgent)) return 'mobile';
    if (preg_match('/tablet|ipad/i', $userAgent)) return 'tablet';
    return 'desktop';
}

function getBrowser() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
    if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
    if (strpos($userAgent, 'Safari') !== false) return 'Safari';
    if (strpos($userAgent, 'Edge') !== false) return 'Edge';
    if (strpos($userAgent, 'MSIE') !== false) return 'IE';
    return 'Other';
}

function getOS() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    if (strpos($userAgent, 'Windows') !== false) return 'Windows';
    if (strpos($userAgent, 'Mac') !== false) return 'macOS';
    if (strpos($userAgent, 'Linux') !== false) return 'Linux';
    if (strpos($userAgent, 'Android') !== false) return 'Android';
    if (strpos($userAgent, 'iOS') !== false) return 'iOS';
    return 'Other';
}
?>