<?php
// admin/analytics.php
// Enhanced Analytics Dashboard with Improved Visualizations

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Analytics Dashboard';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Analytics']
];

$period = $_GET['period'] ?? '30days';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get date range based on period
switch ($period) {
    case 'today':
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        break;
    case 'yesterday':
        $startDate = date('Y-m-d', strtotime('-1 day'));
        $endDate = date('Y-m-d', strtotime('-1 day'));
        break;
    case '7days':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        break;
    case '30days':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        break;
    case '90days':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        $endDate = date('Y-m-d');
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        $endDate = date('Y-m-d');
        break;
    case 'custom':
        // Keep custom dates from GET
        break;
}

// Ensure seo_analytics table exists
$tableExists = db()->fetch("SHOW TABLES LIKE 'seo_analytics'");

if (!$tableExists) {
    // Create the table if it doesn't exist
    db()->getConnection()->exec("
        CREATE TABLE IF NOT EXISTS `seo_analytics` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `visitor_ip` varchar(45) DEFAULT NULL,
            `visit_date` date NOT NULL,
            `visit_time` time NOT NULL,
            `page_url` varchar(500) NOT NULL,
            `page_type` varchar(50) DEFAULT NULL,
            `referrer_url` varchar(500) DEFAULT NULL,
            `device_type` varchar(20) DEFAULT NULL,
            `browser` varchar(50) DEFAULT NULL,
            `os` varchar(50) DEFAULT NULL,
            `country` varchar(100) DEFAULT NULL,
            `city` varchar(100) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_visit_date` (`visit_date`),
            KEY `idx_page_url` (`page_url`(191)),
            KEY `idx_visitor_ip` (`visitor_ip`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// Get overview statistics with better null handling
$overview = db()->fetch("
    SELECT 
        COUNT(DISTINCT visitor_ip) as unique_visitors,
        COUNT(*) as total_visits,
        COALESCE(AVG(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) * 100, 0) as mobile_percentage,
        COUNT(DISTINCT visit_date) as active_days,
        COALESCE(SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END), 0) as mobile_visits,
        COALESCE(SUM(CASE WHEN device_type = 'tablet' THEN 1 ELSE 0 END), 0) as tablet_visits,
        COALESCE(SUM(CASE WHEN device_type = 'desktop' THEN 1 ELSE 0 END), 0) as desktop_visits
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
", [$startDate, $endDate]) ?? [
    'unique_visitors' => 0, 
    'total_visits' => 0, 
    'mobile_percentage' => 0, 
    'active_days' => 0,
    'mobile_visits' => 0,
    'tablet_visits' => 0,
    'desktop_visits' => 0
];

// Get daily visits for chart with better formatting
$dailyVisits = db()->fetchAll("
    SELECT 
        visit_date,
        COUNT(DISTINCT visitor_ip) as unique_visitors,
        COUNT(*) as total_visits
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY visit_date
    ORDER BY visit_date
", [$startDate, $endDate]) ?? [];

// Calculate bounce rate (visits with only one page view)
$bounceRate = db()->fetch("
    SELECT 
        COALESCE(
            (COUNT(CASE WHEN page_views = 1 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)), 
            0
        ) as bounce_rate
    FROM (
        SELECT visitor_ip, visit_date, COUNT(*) as page_views
        FROM seo_analytics 
        WHERE visit_date BETWEEN ? AND ?
        GROUP BY visitor_ip, visit_date
    ) as sessions
", [$startDate, $endDate]) ?? ['bounce_rate' => 0];

// Get popular pages with better formatting (remove time_on_page)
$popularPages = db()->fetchAll("
    SELECT 
        page_url,
        ANY_VALUE(page_type) AS page_type,
        COUNT(*) as views,
        COUNT(DISTINCT visitor_ip) as unique_visitors
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY page_url
    ORDER BY views DESC
    LIMIT 10
", [$startDate, $endDate]) ?? [];

// Get traffic sources with better categorization
$trafficSources = db()->fetchAll("
    SELECT 
        CASE 
            WHEN referrer_url IS NULL OR referrer_url = '' THEN 'Direct'
            WHEN referrer_url LIKE '%google.%' THEN 'Google'
            WHEN referrer_url LIKE '%facebook.%' THEN 'Facebook'
            WHEN referrer_url LIKE '%twitter.%' THEN 'Twitter'
            WHEN referrer_url LIKE '%linkedin.%' THEN 'LinkedIn'
            WHEN referrer_url LIKE '%instagram.%' THEN 'Instagram'
            WHEN referrer_url LIKE '%pinterest.%' THEN 'Pinterest'
            WHEN referrer_url LIKE '%github.%' THEN 'GitHub'
            WHEN referrer_url LIKE '%bing.%' THEN 'Bing'
            WHEN referrer_url LIKE '%yahoo.%' THEN 'Yahoo'
            WHEN referrer_url LIKE '%duckduckgo.%' THEN 'DuckDuckGo'
            WHEN referrer_url LIKE '%mail.%' OR referrer_url LIKE '%outlook.%' OR referrer_url LIKE '%gmail.%' THEN 'Email'
            ELSE 'Other Referrals'
        END as source,
        COUNT(*) as count
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY source
    ORDER BY count DESC
", [$startDate, $endDate]) ?? [];

// Get device breakdown
$devices = db()->fetchAll("
    SELECT 
        COALESCE(device_type, 'Unknown') as device_type,
        COUNT(*) as count
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY device_type
    ORDER BY count DESC
", [$startDate, $endDate]) ?? [];

// Get browser breakdown
$browsers = db()->fetchAll("
    SELECT 
        COALESCE(browser, 'Unknown') as browser,
        COUNT(*) as count
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY browser
    ORDER BY count DESC
    LIMIT 5
", [$startDate, $endDate]) ?? [];

// Get OS breakdown
$operatingSystems = db()->fetchAll("
    SELECT 
        COALESCE(os, 'Unknown') as os,
        COUNT(*) as count
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY os
    ORDER BY count DESC
    LIMIT 5
", [$startDate, $endDate]) ?? [];

// Get hourly traffic pattern
$hourlyTraffic = db()->fetchAll("
    SELECT 
        HOUR(visit_time) as hour,
        COUNT(*) as visits,
        COUNT(DISTINCT visitor_ip) as unique_visitors
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY HOUR(visit_time)
    ORDER BY hour
", [$startDate, $endDate]) ?? [];

// Get weekday distribution
$weekdayTraffic = db()->fetchAll("
    SELECT 
        DAYOFWEEK(visit_date) as day_of_week,
        COUNT(*) as visits,
        COUNT(DISTINCT visitor_ip) as unique_visitors
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(visit_date)
    ORDER BY day_of_week
", [$startDate, $endDate]) ?? [];

// Get conversions with trend
$conversions = db()->fetch("
    SELECT 
        COUNT(*) as total_conversions,
        COUNT(DISTINCT email) as unique_conversions,
        COUNT(DISTINCT DATE(created_at)) as conversion_days
    FROM contact_messages 
    WHERE DATE(created_at) BETWEEN ? AND ?
", [$startDate, $endDate]) ?? [
    'total_conversions' => 0, 
    'unique_conversions' => 0,
    'conversion_days' => 0
];

// Get conversion trend
$conversionTrend = db()->fetchAll("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as conversions
    FROM contact_messages 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
", [$startDate, $endDate]) ?? [];

// Get top countries
$topCountries = db()->fetchAll("
    SELECT 
        COALESCE(country, 'Unknown') as country,
        COUNT(*) as visits,
        COUNT(DISTINCT visitor_ip) as unique_visitors
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ? AND country IS NOT NULL
    GROUP BY country
    ORDER BY visits DESC
    LIMIT 5
", [$startDate, $endDate]) ?? [];

// Include header
require_once 'includes/header.php';
?>

<div class="analytics-header">
    <div class="header-left">
        <h1><i class="fas fa-chart-line"></i> Analytics Dashboard</h1>
        <p class="date-range">
            <?php echo date('M j, Y', strtotime($startDate)); ?> - <?php echo date('M j, Y', strtotime($endDate)); ?>
        </p>
    </div>
    
    <div class="header-actions">
        <div class="period-selector">
            <form method="GET" class="period-form">
                <div class="period-tabs">
                    <a href="?period=today" class="period-tab <?php echo $period === 'today' ? 'active' : ''; ?>">Today</a>
                    <a href="?period=yesterday" class="period-tab <?php echo $period === 'yesterday' ? 'active' : ''; ?>">Yesterday</a>
                    <a href="?period=7days" class="period-tab <?php echo $period === '7days' ? 'active' : ''; ?>">7 Days</a>
                    <a href="?period=30days" class="period-tab <?php echo $period === '30days' ? 'active' : ''; ?>">30 Days</a>
                    <a href="?period=90days" class="period-tab <?php echo $period === '90days' ? 'active' : ''; ?>">90 Days</a>
                    <a href="?period=year" class="period-tab <?php echo $period === 'year' ? 'active' : ''; ?>">Year</a>
                </div>
                
                <div class="custom-date">
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="date-input">
                    <span>to</span>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="date-input">
                    <input type="hidden" name="period" value="custom">
                    <button type="submit" class="btn btn-sm btn-outline">Apply</button>
                </div>
            </form>
        </div>
        
        <button class="btn btn-primary" onclick="exportAnalytics()">
            <i class="fas fa-download"></i> Export Report
        </button>
    </div>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-icon blue">
            <i class="fas fa-users"></i>
        </div>
        <div class="kpi-content">
            <div class="kpi-label">Unique Visitors</div>
            <div class="kpi-value"><?php echo number_format($overview['unique_visitors']); ?></div>
            <div class="kpi-trend">
                <span class="kpi-sub">Total Visits: <?php echo number_format($overview['total_visits']); ?></span>
            </div>
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-icon green">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="kpi-content">
            <div class="kpi-label">Avg. Daily Visits</div>
            <div class="kpi-value">
                <?php 
                $avgDaily = $overview['active_days'] > 0 
                    ? round($overview['total_visits'] / $overview['active_days'], 1) 
                    : 0;
                echo number_format($avgDaily, 1);
                ?>
            </div>
            <div class="kpi-trend">
                <span class="kpi-sub">Active Days: <?php echo $overview['active_days']; ?></span>
            </div>
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-icon purple">
            <i class="fas fa-mobile-alt"></i>
        </div>
        <div class="kpi-content">
            <div class="kpi-label">Mobile Traffic</div>
            <div class="kpi-value"><?php echo round($overview['mobile_percentage']); ?>%</div>
            <div class="kpi-trend">
                <span class="kpi-sub"><?php echo number_format($overview['mobile_visits']); ?> visits</span>
            </div>
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-icon orange">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="kpi-content">
            <div class="kpi-label">Conversions</div>
            <div class="kpi-value"><?php echo number_format($conversions['total_conversions']); ?></div>
            <div class="kpi-trend">
                <span class="kpi-sub">Rate: <?php 
                    $conversionRate = $overview['total_visits'] > 0 
                        ? round(($conversions['total_conversions'] / $overview['total_visits']) * 100, 2) 
                        : 0;
                    echo $conversionRate; ?>%
                </span>
            </div>
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-icon teal">
            <i class="fas fa-clock"></i>
        </div>
        <div class="kpi-content">
            <div class="kpi-label">Bounce Rate</div>
            <div class="kpi-value"><?php echo round($bounceRate['bounce_rate'] ?? 0, 1); ?>%</div>
            <div class="kpi-trend">
                <span class="kpi-sub">Single page sessions</span>
            </div>
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-icon pink">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="kpi-content">
            <div class="kpi-label">Avg. Session</div>
            <div class="kpi-value">
                <?php 
                $avgPagesPerSession = $overview['unique_visitors'] > 0 
                    ? round($overview['total_visits'] / $overview['unique_visitors'], 1) 
                    : 0;
                echo number_format($avgPagesPerSession, 1);
                ?>
            </div>
            <div class="kpi-trend">
                <span class="kpi-sub">pages per session</span>
            </div>
        </div>
    </div>
</div>

<!-- Main Charts Row -->
<div class="charts-row">
    <div class="chart-card large">
        <div class="card-header">
            <h2><i class="fas fa-chart-line"></i> Daily Traffic</h2>
            <div class="chart-legend">
                <span class="legend-item"><span class="color-dot" style="background: #2563eb;"></span> Unique Visitors</span>
                <span class="legend-item"><span class="color-dot" style="background: #7c3aed;"></span> Total Visits</span>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="trafficChart"></canvas>
        </div>
    </div>
</div>

<!-- Secondary Charts Row -->
<div class="charts-row">
    <div class="chart-card">
        <div class="card-header">
            <h2><i class="fas fa-chart-pie"></i> Traffic Sources</h2>
        </div>
        <div class="chart-container small">
            <canvas id="sourcesChart"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="card-header">
            <h2><i class="fas fa-mobile-alt"></i> Devices</h2>
        </div>
        <div class="chart-container small">
            <canvas id="devicesChart"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="card-header">
            <h2><i class="fas fa-clock"></i> Hourly Traffic</h2>
        </div>
        <div class="chart-container small">
            <canvas id="hourlyChart"></canvas>
        </div>
    </div>
</div>

<!-- Detailed Analytics Grid -->
<div class="analytics-grid">
    <!-- Popular Pages -->
    <div class="data-card">
        <div class="card-header">
            <h2><i class="fas fa-file-alt"></i> Popular Pages</h2>
            <span class="badge">Top 10</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Views</th>
                        <th>Unique</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalViews = array_sum(array_column($popularPages, 'views'));
                    foreach ($popularPages as $page): 
                        $percentage = $totalViews > 0 ? round(($page['views'] / $totalViews) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo BASE_URL . $page['page_url']; ?>" target="_blank" class="page-link">
                                <?php 
                                $displayUrl = $page['page_url'] === '/' ? 'Home' : $page['page_url'];
                                echo htmlspecialchars($displayUrl);
                                ?>
                            </a>
                            <?php if (!empty($page['page_type'])): ?>
                            <span class="page-type"><?php echo htmlspecialchars($page['page_type']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?php echo number_format($page['views']); ?></td>
                        <td class="text-right"><?php echo number_format($page['unique_visitors']); ?></td>
                        <td class="text-right"><?php echo $percentage; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($popularPages)): ?>
                    <tr>
                        <td colspan="4" class="text-center empty-message">
                            <i class="fas fa-chart-line"></i>
                            <p>No data available for this period</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Weekday Distribution -->
    <div class="data-card">
        <div class="card-header">
            <h2><i class="fas fa-calendar-alt"></i> Traffic by Day</h2>
        </div>
        <div class="chart-container small">
            <canvas id="weekdayChart"></canvas>
        </div>
    </div>
    
    <!-- Browser & OS Stats -->
    <div class="data-card">
        <div class="card-header">
            <h2><i class="fas fa-laptop"></i> Browsers</h2>
        </div>
        <div class="stats-list">
            <?php foreach ($browsers as $browser): ?>
            <div class="stat-item">
                <div class="stat-label">
                    <i class="fas fa-globe"></i>
                    <span><?php echo htmlspecialchars($browser['browser']); ?></span>
                </div>
                <div class="stat-bar">
                    <div class="bar-fill" style="width: <?php echo ($browser['count'] / max($overview['total_visits'], 1)) * 100; ?>%"></div>
                    <span class="stat-count"><?php echo number_format($browser['count']); ?></span>
                </div>
                <div class="stat-percent">
                    <?php echo round(($browser['count'] / max($overview['total_visits'], 1)) * 100, 1); ?>%
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="card-header" style="margin-top: 20px;">
            <h2><i class="fas fa-server"></i> Operating Systems</h2>
        </div>
        <div class="stats-list">
            <?php foreach ($operatingSystems as $os): ?>
            <div class="stat-item">
                <div class="stat-label">
                    <i class="fas fa-desktop"></i>
                    <span><?php echo htmlspecialchars($os['os']); ?></span>
                </div>
                <div class="stat-bar">
                    <div class="bar-fill" style="width: <?php echo ($os['count'] / max($overview['total_visits'], 1)) * 100; ?>%"></div>
                    <span class="stat-count"><?php echo number_format($os['count']); ?></span>
                </div>
                <div class="stat-percent">
                    <?php echo round(($os['count'] / max($overview['total_visits'], 1)) * 100, 1); ?>%
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Conversion Trend -->
    <div class="data-card">
        <div class="card-header">
            <h2><i class="fas fa-envelope"></i> Conversion Trend</h2>
            <span class="badge success">Total: <?php echo number_format($conversions['total_conversions']); ?></span>
        </div>
        <div class="chart-container small">
            <canvas id="conversionChart"></canvas>
        </div>
        <div class="conversion-stats">
            <div class="conversion-stat">
                <span class="label">Unique Conversions</span>
                <span class="value"><?php echo number_format($conversions['unique_conversions']); ?></span>
            </div>
            <div class="conversion-stat">
                <span class="label">Conversion Days</span>
                <span class="value"><?php echo number_format($conversions['conversion_days']); ?></span>
            </div>
            <div class="conversion-stat">
                <span class="label">Avg. Daily</span>
                <span class="value">
                    <?php 
                    $avgDailyConversions = $conversions['conversion_days'] > 0 
                        ? round($conversions['total_conversions'] / $conversions['conversion_days'], 1) 
                        : 0;
                    echo number_format($avgDailyConversions, 1);
                    ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Geographic Distribution -->
    <?php if (!empty($topCountries)): ?>
    <div class="data-card full-width">
        <div class="card-header">
            <h2><i class="fas fa-globe-americas"></i> Top Countries</h2>
        </div>
        <div class="geo-grid">
            <?php foreach ($topCountries as $country): ?>
            <div class="geo-item">
                <div class="country-name">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($country['country']); ?>
                </div>
                <div class="country-stats">
                    <span class="visits"><?php echo number_format($country['visits']); ?> visits</span>
                    <span class="unique">(<?php echo number_format($country['unique_visitors']); ?> unique)</span>
                </div>
                <div class="country-bar">
                    <div class="bar-fill" style="width: <?php echo ($country['visits'] / max($overview['total_visits'], 1)) * 100; ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* ========================================
   ANALYTICS DASHBOARD STYLES
   ======================================== */

:root {
    --primary: #2563eb;
    --secondary: #7c3aed;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #1e293b;
    --gray-600: #475569;
    --gray-500: #64748b;
    --gray-400: #94a3b8;
    --gray-300: #cbd5e1;
    --gray-200: #e2e8f0;
    --gray-100: #f1f5f9;
    --white: #ffffff;
    
    --blue: #2563eb;
    --purple: #7c3aed;
    --green: #10b981;
    --orange: #f59e0b;
    --pink: #ec4899;
    --teal: #14b8a6;
}

/* Analytics Header */
.analytics-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 20px;
}

.header-left h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--dark);
    margin: 0 0 5px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-left h1 i {
    color: var(--primary);
}

.date-range {
    color: var(--gray-500);
    font-size: 14px;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

/* Period Selector */
.period-selector {
    background: var(--white);
    border-radius: 12px;
    padding: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.period-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.period-tabs {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.period-tab {
    padding: 6px 12px;
    color: var(--gray-600);
    text-decoration: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.period-tab:hover,
.period-tab.active {
    background: var(--primary);
    color: white;
}

.custom-date {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.date-input {
    padding: 6px 10px;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
    font-size: 13px;
}

/* KPI Cards */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.kpi-card {
    background: var(--white);
    border-radius: 12px;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: transform 0.2s ease;
}

.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.kpi-icon.blue { background: rgba(37,99,235,0.1); color: var(--blue); }
.kpi-icon.green { background: rgba(16,185,129,0.1); color: var(--green); }
.kpi-icon.purple { background: rgba(124,58,237,0.1); color: var(--purple); }
.kpi-icon.orange { background: rgba(245,158,11,0.1); color: var(--orange); }
.kpi-icon.teal { background: rgba(20,184,166,0.1); color: var(--teal); }
.kpi-icon.pink { background: rgba(236,72,153,0.1); color: var(--pink); }

.kpi-content {
    flex: 1;
}

.kpi-label {
    font-size: 12px;
    color: var(--gray-500);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.kpi-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
    margin-bottom: 4px;
}

.kpi-trend {
    font-size: 11px;
    color: var(--gray-500);
}

.kpi-sub {
    color: var(--gray-600);
}

/* Charts Row */
.charts-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.chart-card {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.chart-card.large {
    grid-column: span 3;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.card-header h2 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-header h2 i {
    color: var(--primary);
}

.chart-legend {
    display: flex;
    gap: 15px;
}

.legend-item {
    font-size: 12px;
    color: var(--gray-600);
    display: flex;
    align-items: center;
    gap: 5px;
}

.color-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.chart-container {
    height: 300px;
    position: relative;
}

.chart-container.small {
    height: 200px;
}

/* Analytics Grid */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-top: 25px;
}

.data-card {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.data-card.full-width {
    grid-column: span 3;
}

.badge {
    padding: 4px 10px;
    background: var(--gray-200);
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    color: var(--gray-700);
}

.badge.success {
    background: rgba(16,185,129,0.1);
    color: var(--success);
}

/* Data Tables */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    text-align: left;
    padding: 10px 8px;
    font-size: 12px;
    font-weight: 600;
    color: var(--gray-500);
    border-bottom: 2px solid var(--gray-200);
}

.data-table td {
    padding: 10px 8px;
    font-size: 13px;
    border-bottom: 1px solid var(--gray-200);
}

.data-table tr:last-child td {
    border-bottom: none;
}

.text-right {
    text-align: right;
}

.page-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.page-link:hover {
    text-decoration: underline;
}

.page-type {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 6px;
    background: var(--gray-200);
    border-radius: 4px;
    font-size: 10px;
    color: var(--gray-600);
}

/* Stats List */
.stats-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.stat-label {
    min-width: 120px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--gray-700);
}

.stat-label i {
    color: var(--primary);
    width: 16px;
}

.stat-bar {
    flex: 1;
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    position: relative;
    overflow: hidden;
}

.stat-bar .bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 4px;
}

.stat-count {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 11px;
    font-weight: 600;
    color: var(--dark);
}

.stat-percent {
    min-width: 45px;
    font-size: 12px;
    font-weight: 600;
    color: var(--primary);
    text-align: right;
}

/* Conversion Stats */
.conversion-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--gray-200);
}

.conversion-stat {
    text-align: center;
}

.conversion-stat .label {
    display: block;
    font-size: 11px;
    color: var(--gray-500);
    margin-bottom: 4px;
}

.conversion-stat .value {
    font-size: 16px;
    font-weight: 700;
    color: var(--dark);
}

/* Geographic Grid */
.geo-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.geo-item {
    padding: 10px;
    background: var(--gray-100);
    border-radius: 8px;
}

.country-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.country-name i {
    color: var(--primary);
}

.country-stats {
    font-size: 12px;
    color: var(--gray-600);
    margin-bottom: 8px;
}

.country-stats .visits {
    font-weight: 600;
    color: var(--dark);
    margin-right: 5px;
}

.country-bar {
    height: 6px;
    background: var(--gray-300);
    border-radius: 3px;
    overflow: hidden;
}

.country-bar .bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 3px;
}

/* Empty States */
.empty-message {
    padding: 40px 20px;
    text-align: center;
    color: var(--gray-500);
}

.empty-message i {
    font-size: 32px;
    color: var(--gray-300);
    margin-bottom: 10px;
}

/* Responsive */
@media (max-width: 1400px) {
    .kpi-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 1200px) {
    .analytics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .data-card.full-width {
        grid-column: span 2;
    }
    
    .charts-row {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .chart-card.large {
        grid-column: span 2;
    }
}

@media (max-width: 992px) {
    .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .analytics-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .period-selector {
        width: 100%;
    }
    
    .custom-date {
        flex-wrap: wrap;
    }
    
    .date-input {
        flex: 1;
    }
    
    .charts-row {
        grid-template-columns: 1fr;
    }
    
    .chart-card.large {
        grid-column: span 1;
    }
    
    .analytics-grid {
        grid-template-columns: 1fr;
    }
    
    .data-card.full-width {
        grid-column: span 1;
    }
    
    .geo-grid {
        grid-template-columns: 1fr;
    }
    
    .kpi-grid {
        grid-template-columns: 1fr;
    }
    
    .kpi-card {
        padding: 15px;
    }
    
    .kpi-value {
        font-size: 20px;
    }
}

@media (max-width: 480px) {
    .stat-item {
        flex-wrap: wrap;
    }
    
    .stat-label {
        min-width: 100%;
    }
    
    .stat-percent {
        min-width: auto;
    }
    
    .conversion-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Traffic Chart
const trafficCtx = document.getElementById('trafficChart').getContext('2d');
new Chart(trafficCtx, {
    type: 'line',
    data: {
        labels: [<?php foreach ($dailyVisits as $day): ?>'<?php echo date('M d', strtotime($day['visit_date'])); ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Unique Visitors',
            data: [<?php foreach ($dailyVisits as $day): ?><?php echo $day['unique_visitors']; ?>,<?php endforeach; ?>],
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 5
        }, {
            label: 'Total Visits',
            data: [<?php foreach ($dailyVisits as $day): ?><?php echo $day['total_visits']; ?>,<?php endforeach; ?>],
            borderColor: '#7c3aed',
            backgroundColor: 'rgba(124, 58, 237, 0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    maxTicksLimit: 8
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)'
                },
                ticks: {
                    stepSize: 5,
                    callback: function(value) {
                        return value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Traffic Sources Chart
const sourcesCtx = document.getElementById('sourcesChart').getContext('2d');
new Chart(sourcesCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($trafficSources as $source): ?>'<?php echo $source['source']; ?>',<?php endforeach; ?>],
        datasets: [{
            data: [<?php foreach ($trafficSources as $source): ?><?php echo $source['count']; ?>,<?php endforeach; ?>],
            backgroundColor: [
                '#2563eb',
                '#7c3aed',
                '#10b981',
                '#f59e0b',
                '#ec4899',
                '#14b8a6',
                '#8b5cf6',
                '#ef4444'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 15,
                    font: {
                        size: 11
                    }
                }
            }
        },
        cutout: '65%'
    }
});

// Devices Chart
const devicesCtx = document.getElementById('devicesChart').getContext('2d');
new Chart(devicesCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($devices as $device): ?>'<?php echo ucfirst($device['device_type'] ?: 'Unknown'); ?>',<?php endforeach; ?>],
        datasets: [{
            data: [<?php foreach ($devices as $device): ?><?php echo $device['count']; ?>,<?php endforeach; ?>],
            backgroundColor: ['#2563eb', '#7c3aed', '#10b981'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 15,
                    font: {
                        size: 11
                    }
                }
            }
        },
        cutout: '65%'
    }
});

// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: [<?php for ($i = 0; $i < 24; $i++): ?>'<?php echo sprintf('%02d:00', $i); ?>',<?php endfor; ?>],
        datasets: [{
            label: 'Visits',
            data: [<?php 
                $hourlyData = [];
                foreach ($hourlyTraffic as $h) {
                    $hourlyData[$h['hour']] = $h['visits'];
                }
                for ($i = 0; $i < 24; $i++): 
                    echo ($hourlyData[$i] ?? 0) . ',';
                endfor; 
            ?>],
            backgroundColor: '#2563eb',
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(0,0,0,0.05)'
                },
                ticks: {
                    stepSize: 1,
                    callback: function(value) {
                        return value.toLocaleString();
                    }
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    maxRotation: 45,
                    minRotation: 45,
                    font: {
                        size: 10
                    }
                }
            }
        }
    }
});

// Weekday Chart
const weekdayCtx = document.getElementById('weekdayChart')?.getContext('2d');
if (weekdayCtx) {
    const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    new Chart(weekdayCtx, {
        type: 'bar',
        data: {
            labels: weekdayNames,
            datasets: [{
                label: 'Visits',
                data: [<?php 
                    $weekdayData = array_fill(0, 7, 0);
                    foreach ($weekdayTraffic as $w) {
                        $weekdayData[$w['day_of_week'] - 1] = $w['visits'];
                    }
                    echo implode(',', $weekdayData);
                ?>],
                backgroundColor: '#2563eb',
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Conversion Chart
const conversionCtx = document.getElementById('conversionChart')?.getContext('2d');
if (conversionCtx) {
    new Chart(conversionCtx, {
        type: 'line',
        data: {
            labels: [<?php foreach ($conversionTrend as $c): ?>'<?php echo date('M d', strtotime($c['date'])); ?>',<?php endforeach; ?>],
            datasets: [{
                label: 'Conversions',
                data: [<?php foreach ($conversionTrend as $c): ?><?php echo $c['conversions']; ?>,<?php endforeach; ?>],
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    }
                },
                x: {
                    ticks: {
                        maxTicksLimit: 5
                    }
                }
            }
        }
    });
}

function exportAnalytics() {
    window.location.href = 'export-analytics.php?period=<?php echo $period; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>';
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>