<?php
// admin/seo-sitemap.php
// Sitemap Generator

$sitemapPath = ROOT_PATH . 'sitemap.xml';
$lastGenerated = file_exists($sitemapPath) ? filemtime($sitemapPath) : null;

// Handle sitemap generation
if (isset($_POST['generate_sitemap'])) {
    generateSitemap();
    $lastGenerated = time();
    $msg = 'generated';
}

// Handle sitemap submission to search engines
if (isset($_POST['submit_sitemap'])) {
    submitToSearchEngines();
    $msg = 'submitted';
}

function generateSitemap() {
    global $pdo;
    
    $sitemap = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
    
    // Add static pages
    $staticPages = [
        ['url' => '/', 'priority' => 1.0, 'freq' => 'daily'],
        ['url' => '/projects', 'priority' => 0.9, 'freq' => 'weekly'],
        ['url' => '/blog', 'priority' => 0.8, 'freq' => 'daily'],
        ['url' => '/contact', 'priority' => 0.7, 'freq' => 'monthly'],
    ];
    
    foreach ($staticPages as $page) {
        $url = $sitemap->addChild('url');
        $url->addChild('loc', BASE_URL . $page['url']);
        $url->addChild('lastmod', date('Y-m-d'));
        $url->addChild('changefreq', $page['freq']);
        $url->addChild('priority', $page['priority']);
    }
    
    // Add blog posts
    $posts = $pdo->query("SELECT slug, updated_at FROM blog_posts WHERE status = 'published'");
    foreach ($posts as $post) {
        $url = $sitemap->addChild('url');
        $url->addChild('loc', BASE_URL . '/blog/' . $post['slug']);
        $url->addChild('lastmod', date('Y-m-d', strtotime($post['updated_at'])));
        $url->addChild('changefreq', 'weekly');
        $url->addChild('priority', '0.6');
    }
    
    // Add projects
    $projects = $pdo->query("SELECT slug, updated_at FROM projects WHERE status = 'published'");
    foreach ($projects as $project) {
        $url = $sitemap->addChild('url');
        $url->addChild('loc', BASE_URL . '/project/' . $project['slug']);
        $url->addChild('lastmod', date('Y-m-d', strtotime($project['updated_at'])));
        $url->addChild('changefreq', 'monthly');
        $url->addChild('priority', '0.8');
    }
    
    // Save sitemap
    $sitemap->asXML(ROOT_PATH . 'sitemap.xml');
}

function submitToSearchEngines() {
    $sitemapUrl = BASE_URL . '/sitemap.xml';
    $engines = [
        'google' => 'https://www.google.com/ping?sitemap=' . urlencode($sitemapUrl),
        'bing' => 'https://www.bing.com/ping?sitemap=' . urlencode($sitemapUrl)
    ];
    
    foreach ($engines as $name => $url) {
        @file_get_contents($url);
    }
}

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>Sitemap Generator</h2>
    <div class="header-actions">
        <a href="seo.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to SEO
        </a>
    </div>
</div>

<?php if (isset($msg)): ?>
    <div class="alert alert-success">
        <?php if ($msg === 'generated') echo 'Sitemap generated successfully!'; ?>
        <?php if ($msg === 'submitted') echo 'Sitemap submitted to search engines!'; ?>
    </div>
<?php endif; ?>

<div class="sitemap-info">
    <div class="info-card">
        <h3>Current Sitemap</h3>
        <?php if ($lastGenerated): ?>
            <p><strong>Last generated:</strong> <?php echo date('F d, Y H:i:s', $lastGenerated); ?></p>
            <p><strong>Sitemap URL:</strong> <a href="<?php echo BASE_URL; ?>/sitemap.xml" target="_blank"><?php echo BASE_URL; ?>/sitemap.xml</a></p>
            <p><strong>File size:</strong> <?php echo file_exists($sitemapPath) ? round(filesize($sitemapPath) / 1024, 2) : 0; ?> KB</p>
        <?php else: ?>
            <p>Sitemap not generated yet.</p>
        <?php endif; ?>
    </div>
    
    <div class="info-card">
        <h3>Sitemap Statistics</h3>
        <?php
        $totalUrls = db()->fetch("SELECT COUNT(*) as count FROM blog_posts WHERE status = 'published'")['count'] +
                    db()->fetch("SELECT COUNT(*) as count FROM projects WHERE status = 'published'")['count'] +
                    count($staticPages);
        ?>
        <p><strong>Total URLs:</strong> <?php echo $totalUrls; ?></p>
        <p><strong>Blog Posts:</strong> <?php echo db()->fetch("SELECT COUNT(*) as count FROM blog_posts WHERE status = 'published'")['count']; ?></p>
        <p><strong>Projects:</strong> <?php echo db()->fetch("SELECT COUNT(*) as count FROM projects WHERE status = 'published'")['count']; ?></p>
    </div>
</div>

<div class="sitemap-actions">
    <form method="POST" class="inline-form">
        <button type="submit" name="generate_sitemap" class="btn btn-primary">
            <i class="fas fa-sync-alt"></i>
            Generate Sitemap
        </button>
    </form>
    
    <?php if ($lastGenerated): ?>
    <form method="POST" class="inline-form">
        <button type="submit" name="submit_sitemap" class="btn btn-outline">
            <i class="fas fa-paper-plane"></i>
            Submit to Search Engines
        </button>
    </form>
    
    <a href="<?php echo BASE_URL; ?>/sitemap.xml" download class="btn btn-outline">
        <i class="fas fa-download"></i>
        Download Sitemap
    </a>
    <?php endif; ?>
</div>

<?php if ($lastGenerated): ?>
<div class="sitemap-preview">
    <h3>Sitemap Preview</h3>
    <pre class="sitemap-code"><?php echo htmlspecialchars(file_get_contents($sitemapPath)); ?></pre>
</div>
<?php endif; ?>

<style>
.sitemap-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.info-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.info-card h3 {
    margin-bottom: 15px;
    font-size: 1.1rem;
}

.sitemap-actions {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.inline-form {
    display: inline-block;
}

.sitemap-preview {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.sitemap-code {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 0.85rem;
    max-height: 400px;
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>