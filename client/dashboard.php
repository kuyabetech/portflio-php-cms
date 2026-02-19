<?php
// client/dashboard.php
// Client Portal Dashboard

require_once dirname(__DIR__) . '/includes/init.php';

// Check if client is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$clientId = $_SESSION['client_id'];

// Get client info
$client = db()->fetch("
    SELECT c.*, cu.first_name, cu.last_name, cu.email 
    FROM client_users cu
    JOIN clients c ON cu.client_id = c.id
    WHERE cu.id = ?
", [$clientId]);

// Get client's projects
$projects = db()->fetchAll("
    SELECT * FROM projects 
    WHERE client_id = ? AND is_client_visible = 1
    ORDER BY created_at DESC
", [$client['client_id']]);

// Get recent messages
$messages = db()->fetchAll("
    SELECT m.*, u.username as user_name 
    FROM project_messages m
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.project_id IN (SELECT id FROM projects WHERE client_id = ?)
    ORDER BY m.created_at DESC
    LIMIT 10
", [$client['client_id']]);

// Get recent files
$files = db()->fetchAll("
    SELECT * FROM project_files 
    WHERE project_id IN (SELECT id FROM projects WHERE client_id = ?) 
    AND is_client_visible = 1
    ORDER BY created_at DESC
    LIMIT 10
", [$client['client_id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .nav-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            text-decoration: none;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            font-weight: 500;
            color: #666;
        }
        
        .logout-btn {
            padding: 8px 16px;
            background: #f0f0f0;
            color: #666;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #e0e0e0;
        }
        
        .dashboard {
            padding: 40px 0;
        }
        
        .welcome-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .welcome-section p {
            color: #666;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-header h2 {
            font-size: 1.2rem;
        }
        
        .view-all {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .project-list {
            list-style: none;
        }
        
        .project-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .project-item:last-child {
            border-bottom: none;
        }
        
        .project-info h3 {
            font-size: 1rem;
            margin-bottom: 5px;
        }
        
        .project-info p {
            color: #666;
            font-size: 0.85rem;
        }
        
        .project-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-completed { background: #10b981; color: white; }
        .status-in_progress { background: #3b82f6; color: white; }
        .status-planning { background: #f59e0b; color: white; }
        
        .message-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .message-item:last-child {
            border-bottom: none;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.85rem;
            color: #999;
        }
        
        .message-content {
            color: #666;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .file-meta {
            font-size: 0.8rem;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-wrapper">
                <a href="dashboard.php" class="logo">Client Portal</a>
                <div class="user-menu">
                    <span class="user-name">Welcome, <?php echo htmlspecialchars($client['first_name']); ?></span>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="dashboard">
        <div class="container">
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($client['first_name']); ?>!</h1>
                <p>Here's what's happening with your projects.</p>
            </div>
            
            <?php
            $totalProjects = count($projects);
            $completedProjects = 0;
            $inProgressProjects = 0;
            $totalBudget = 0;
            
            foreach ($projects as $project) {
                if ($project['status'] === 'completed') $completedProjects++;
                if ($project['status'] === 'in_progress') $inProgressProjects++;
                $totalBudget += $project['budget'] ?? 0;
            }
            ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalProjects; ?></div>
                    <div class="stat-label">Total Projects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $inProgressProjects; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $completedProjects; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($totalBudget, 0); ?></div>
                    <div class="stat-label">Total Investment</div>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <h2>Your Projects</h2>
                        <a href="projects.php" class="view-all">View All</a>
                    </div>
                    <div class="project-list">
                        <?php foreach (array_slice($projects, 0, 5) as $project): ?>
                        <div class="project-item">
                            <div class="project-info">
                                <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($project['short_description'], 0, 50)); ?>...</p>
                            </div>
                            <span class="project-status status-<?php echo $project['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($projects)): ?>
                        <p>No projects yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Messages</h2>
                        <a href="messages.php" class="view-all">View All</a>
                    </div>
                    <div class="messages-list">
                        <?php foreach ($messages as $message): ?>
                        <div class="message-item">
                            <div class="message-header">
                                <span><?php echo htmlspecialchars($message['user_name'] ?: 'Team'); ?></span>
                                <span><?php echo timeAgo($message['created_at']); ?></span>
                            </div>
                            <div class="message-content">
                                <?php echo htmlspecialchars(substr($message['message'], 0, 100)); ?>...
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($messages)): ?>
                        <p>No messages yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Files</h2>
                        <a href="files.php" class="view-all">View All</a>
                    </div>
                    <div class="files-list">
                        <?php foreach ($files as $file): ?>
                        <div class="file-item">
                            <div class="file-icon">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="file-info">
                                <div class="file-name"><?php echo htmlspecialchars($file['original_filename']); ?></div>
                                <div class="file-meta">
                                    <?php echo date('M d, Y', strtotime($file['created_at'])); ?>
                                </div>
                            </div>
                            <a href="<?php echo UPLOAD_URL . 'projects/' . $file['project_id'] . '/' . $file['filename']; ?>" 
                               class="download-btn" download>
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($files)): ?>
                        <p>No files yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>