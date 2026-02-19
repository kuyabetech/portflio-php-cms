<?php
// admin/analytics.php
// Analytics Dashboard

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
}

// Get overview statistics
$overview = db()->fetch("
    SELECT 
        COUNT(DISTINCT visitor_ip) as unique_visitors,
        COUNT(*) as total_visits,
        AVG(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) * 100 as mobile_percentage,
        COUNT(DISTINCT DATE(visit_date)) as active_days
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
", [$startDate, $endDate]) ?? ['unique_visitors' => 0, 'total_visits' => 0, 'mobile_percentage' => 0, 'active_days' => 0];

// Get daily visits for chart
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

// Get popular pages
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

// Get traffic sources
$trafficSources = db()->fetchAll("
    SELECT 
        CASE 
            WHEN referrer_url IS NULL OR referrer_url = '' THEN 'Direct'
            WHEN referrer_url LIKE '%google.%' THEN 'Google'
            WHEN referrer_url LIKE '%facebook.%' THEN 'Facebook'
            WHEN referrer_url LIKE '%twitter.%' THEN 'Twitter'
            WHEN referrer_url LIKE '%linkedin.%' THEN 'LinkedIn'
            WHEN referrer_url LIKE '%github.%' THEN 'GitHub'
            ELSE 'Other'
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
        device_type,
        COUNT(*) as count
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY device_type
", [$startDate, $endDate]) ?? [];

// Get browser breakdown
$browsers = db()->fetchAll("
    SELECT 
        browser,
        COUNT(*) as count
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY browser
    ORDER BY count DESC
    LIMIT 5
", [$startDate, $endDate]) ?? [];

// Get OS breakdown
$os = db()->fetchAll("
    SELECT 
        os,
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
        COUNT(*) as visits
    FROM seo_analytics 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY HOUR(visit_time)
    ORDER BY hour
", [$startDate, $endDate]) ?? [];

// Get conversions
$conversions = db()->fetch("
    SELECT 
        COUNT(*) as total_conversions,
        COUNT(DISTINCT email) as unique_conversions
    FROM contact_messages 
    WHERE DATE(created_at) BETWEEN ? AND ?
", [$startDate, $endDate]) ?? ['total_conversions' => 0, 'unique_conversions' => 0];

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>Analytics Dashboard</h2>
    <div class="header-actions">
        <form method="GET" class="date-filter-form">
            <select name="period" onchange="this.form.submit()" class="form-control">
                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90days" <?php echo $period === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Last Year</option>
            </select>
            
            <?php if ($period === 'custom'): ?>
            <input type="date" name="start_date" value="<?php echo $startDate; ?>" onchange="this.form.submit()" class="form-control">
            <input type="date" name="end_date" value="<?php echo $endDate; ?>" onchange="this.form.submit()" class="form-control">
            <?php endif; ?>
        </form>
        
        <button class="btn btn-outline" onclick="exportAnalytics()">
            <i class="fas fa-download"></i>
            Export Report
        </button>
    </div>
</div>

<!-- Overview Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-details">
            <h3>Unique Visitors</h3>
            <span class="stat-value"><?php echo number_format($overview['unique_visitors'] ?? 0); ?></span>
            <span class="stat-label">this period</span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-eye"></i>
        </div>
        <div class="stat-details">
            <h3>Total Visits</h3>
            <span class="stat-value"><?php echo number_format($overview['total_visits'] ?? 0); ?></span>
            <span class="stat-label">page views</span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-mobile-alt"></i>
        </div>
        <div class="stat-details">
            <h3>Mobile Traffic</h3>
            <span class="stat-value"><?php echo round($overview['mobile_percentage'] ?? 0); ?>%</span>
            <span class="stat-label">of visitors</span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-envelope"></i>
        </div>
        <div class="stat-details">
            <h3>Conversions</h3>
            <span class="stat-value"><?php echo number_format($conversions['total_conversions'] ?? 0); ?></span>
            <span class="stat-label">contact forms</span>
        </div>
    </div>
</div>

<!-- Traffic Chart -->
<div class="chart-container">
    <h2>Daily Traffic</h2>
    <canvas id="trafficChart"></canvas>
</div>

<div class="analytics-grid">
    <!-- Popular Pages -->
    <div class="analytics-card">
        <h2>Popular Pages</h2>
        <div class="table-responsive">
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Views</th>
                        <th>Unique</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($popularPages as $page): ?>
                    <tr>
                        <td>
                            <a href="<?php echo BASE_URL . $page['page_url']; ?>" target="_blank">
                                <?php 
                                $displayUrl = $page['page_url'] === '/' ? 'Home' : $page['page_url'];
                                echo htmlspecialchars($displayUrl);
                                ?>
                            </a>
                        </td>
                        <td><?php echo number_format($page['views']); ?></td>
                        <td><?php echo number_format($page['unique_visitors']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Traffic Sources -->
    <div class="analytics-card">
        <h2>Traffic Sources</h2>
        <div class="sources-list">
            <?php foreach ($trafficSources as $source): ?>
            <div class="source-item">
                <div class="source-name">
                    <?php if ($source['source'] === 'Direct'): ?>
                        <i class="fas fa-globe"></i>
                    <?php elseif ($source['source'] === 'Google'): ?>
                        <i class="fab fa-google"></i>
                    <?php elseif ($source['source'] === 'Facebook'): ?>
                        <i class="fab fa-facebook"></i>
                    <?php elseif ($source['source'] === 'Twitter'): ?>
                        <i class="fab fa-twitter"></i>
                    <?php elseif ($source['source'] === 'LinkedIn'): ?>
                        <i class="fab fa-linkedin"></i>
                    <?php elseif ($source['source'] === 'GitHub'): ?>
                        <i class="fab fa-github"></i>
                    <?php else: ?>
                        <i class="fas fa-link"></i>
                    <?php endif; ?>
                    <?php echo $source['source']; ?>
                </div>
                <div class="source-bar">
                    <div class="bar-fill" style="width: <?php echo ($source['count'] / max(array_sum(array_column($trafficSources, 'count')), 1)) * 100; ?>%"></div>
                    <span class="source-count"><?php echo number_format($source['count']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Device Breakdown -->
    <div class="analytics-card">
        <h2>Devices</h2>
        <canvas id="deviceChart" height="200"></canvas>
    </div>
    
    <!-- Hourly Traffic -->
    <div class="analytics-card">
        <h2>Traffic by Hour</h2>
        <canvas id="hourlyChart" height="200"></canvas>
    </div>
    
    <!-- Browser Breakdown -->
    <div class="analytics-card">
        <h2>Browsers</h2>
        <div class="breakdown-list">
            <?php foreach ($browsers as $browser): ?>
            <div class="breakdown-item">
                <span class="breakdown-label"><?php echo $browser['browser']; ?></span>
                <span class="breakdown-value"><?php echo number_format($browser['count']); ?></span>
                <span class="breakdown-percent">
                    <?php echo round(($browser['count'] / max($overview['total_visits'], 1)) * 100, 1); ?>%
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- OS Breakdown -->
    <div class="analytics-card">
        <h2>Operating Systems</h2>
        <div class="breakdown-list">
            <?php foreach ($os as $operatingSystem): ?>
            <div class="breakdown-item">
                <span class="breakdown-label"><?php echo $operatingSystem['os']; ?></span>
                <span class="breakdown-value"><?php echo number_format($operatingSystem['count']); ?></span>
                <span class="breakdown-percent">
                    <?php echo round(($operatingSystem['count'] / max($overview['total_visits'], 1)) * 100, 1); ?>%
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.date-filter-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.date-filter-form select,
.date-filter-form input {
    padding: 8px 12px;
    border: 2px solid var(--gray-200);
    border-radius: 6px;
    font-size: 0.9rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.chart-container {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    height: 400px;
}

.chart-container h2 {
    font-size: 1.1rem;
    margin-bottom: 20px;
}

.analytics-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.analytics-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.analytics-card h2 {
    font-size: 1rem;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-200);
}

.analytics-table {
    width: 100%;
    border-collapse: collapse;
}

.analytics-table th {
    text-align: left;
    padding: 8px;
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.8rem;
}

.analytics-table td {
    padding: 8px;
    border-bottom: 1px solid var(--gray-200);
}

.sources-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.source-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.source-name {
    font-size: 0.9rem;
    font-weight: 500;
}

.source-name i {
    width: 20px;
    margin-right: 8px;
    color: var(--primary);
}

.source-bar {
    position: relative;
    height: 24px;
    background: var(--gray-200);
    border-radius: 12px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 12px;
    transition: width 0.3s ease;
}

.source-count {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.breakdown-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.breakdown-item {
    display: flex;
    align-items: center;
    padding: 8px;
    background: var(--gray-100);
    border-radius: 8px;
}

.breakdown-label {
    flex: 1;
    font-weight: 500;
}

.breakdown-value {
    font-weight: 600;
    margin-right: 15px;
}

.breakdown-percent {
    color: var(--gray-600);
    font-size: 0.85rem;
    min-width: 50px;
    text-align: right;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .analytics-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .content-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .date-filter-form {
        width: 100%;
        flex-direction: column;
    }
    
    .date-filter-form select,
    .date-filter-form input {
        width: 100%;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Traffic Chart - with improved scaling to reduce "zoomed in" feeling
const trafficCtx = document.getElementById('trafficChart').getContext('2d');
new Chart(trafficCtx, {
    type: 'line',
    data: {
        labels: [<?php foreach ($dailyVisits as $day): ?>'<?php echo $day['visit_date']; ?>',<?php endforeach; ?>],
        datasets: [{
            label: 'Unique Visitors',
            data: [<?php foreach ($dailyVisits as $day): ?><?php echo $day['unique_visitors']; ?>,<?php endforeach; ?>],
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'Total Visits',
            data: [<?php foreach ($dailyVisits as $day): ?><?php echo $day['total_visits']; ?>,<?php endforeach; ?>],
            borderColor: '#7c3aed',
            backgroundColor: 'rgba(124, 58, 237, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            x: {
                ticks: {
                    maxTicksLimit: 10,
                    maxRotation: 45,
                    minRotation: 45
                }
            },
            y: {
                beginAtZero: true,
                suggestedMin: 0,
                suggestedMax: <?php 
                    $maxY = 0;
                    foreach ($dailyVisits as $day) {
                        $maxY = max($maxY, (int)($day['unique_visitors'] ?? 0), (int)($day['total_visits'] ?? 0));
                    }
                    echo max(10, (int)($maxY * 1.3)); // 30% headroom
                ?>,
                ticks: {
                    stepSize: 'auto',
                    precision: 0
                }
            }
        }
    }
});

// Device Chart
const deviceCtx = document.getElementById('deviceChart').getContext('2d');
new Chart(deviceCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($devices as $device): ?>'<?php echo ucfirst($device['device_type']); ?>',<?php endforeach; ?>],
        datasets: [{
            data: [<?php foreach ($devices as $device): ?><?php echo $device['count']; ?>,<?php endforeach; ?>],
            backgroundColor: [
                '#2563eb',
                '#7c3aed',
                '#10b981',
                '#f59e0b'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Hourly Chart
const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: [<?php for ($i = 0; $i < 24; $i++): ?>'<?php echo $i; ?>:00',<?php endfor; ?>],
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
            backgroundColor: '#2563eb'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

function exportAnalytics() {
    window.location.href = 'export-analytics.php?period=<?php echo $period; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>';
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>