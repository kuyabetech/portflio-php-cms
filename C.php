<?php
// admin/clients.php
// Client Management

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Client Management';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Clients']
];

if ($action === 'add') {
    $breadcrumbs[] = ['title' => 'Add New Client'];
} elseif ($action === 'edit') {
    $breadcrumbs[] = ['title' => 'Edit Client'];
} elseif ($action === 'view') {
    $breadcrumbs[] = ['title' => 'Client Details'];
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if client has projects
    $projects = db()->fetch("SELECT COUNT(*) as count FROM projects WHERE client_id = ?", [$id])['count'];
    if ($projects > 0) {
        header('Location: clients.php?msg=has_projects');
        exit;
    }
    
    db()->delete('clients', 'id = ?', [$id]);
    header('Location: clients.php?msg=deleted');
    exit;
}

// Handle status toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $client = db()->fetch("SELECT status FROM clients WHERE id = ?", [$id]);
    $newStatus = $client['status'] === 'active' ? 'inactive' : 'active';
    db()->update('clients', ['status' => $newStatus], 'id = :id', ['id' => $id]);
    header('Location: clients.php?msg=updated');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_client'])) {
    $data = [
        'company_name' => sanitize($_POST['company_name']),
        'contact_person' => sanitize($_POST['contact_person']),
        'email' => sanitize($_POST['email']),
        'phone' => sanitize($_POST['phone']),
        'address' => sanitize($_POST['address']),
        'website' => sanitize($_POST['website']),
        'status' => $_POST['status'],
        'source' => sanitize($_POST['source']),
        'notes' => sanitize($_POST['notes'])
    ];
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['logo'], 'clients/');
        if (isset($upload['success'])) {
            // Delete old logo if updating
            if (!empty($_POST['id'])) {
                $old = db()->fetch("SELECT logo FROM clients WHERE id = ?", [$_POST['id']]);
                if ($old && $old['logo']) {
                    $oldPath = UPLOAD_PATH . 'clients/' . $old['logo'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
            }
            $data['logo'] = $upload['filename'];
        }
    }
    
    if (!empty($_POST['id'])) {
        db()->update('clients', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        $clientId = db()->insert('clients', $data);
        
        // Create client user account
        if (!empty($_POST['create_login'])) {
            $password = generateRandomString(12);
            $userId = db()->insert('client_users', [
                'client_id' => $clientId,
                'email' => $data['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'first_name' => explode(' ', $data['contact_person'])[0],
                'last_name' => explode(' ', $data['contact_person'])[1] ?? '',
                'role' => 'primary'
            ]);
            // Send welcome email
if (isset($_POST['create_login'])) {
    mailer()->sendTemplate('welcome_client', [
        'email' => $data['email'],
        'name' => $data['contact_person']
    ], [
        'client_name' => $data['contact_person'],
        'email' => $data['email'],
        'password' => $password,
        'portal_url' => BASE_URL . '/client'
    ]);
}
            // Send welcome email with credentials
            sendClientWelcomeEmail($data['email'], $password, $data['contact_person']);
        }
        
        $msg = 'created';
    }
    
    header("Location: clients.php?msg=$msg");
    exit;
}

// Get client for editing/viewing
$client = null;
if ($id > 0) {
    if ($action === 'edit' || $action === 'view') {
        $client = db()->fetch("SELECT * FROM clients WHERE id = ?", [$id]);
    }
}

// Get all clients
$clients = db()->fetchAll("
    SELECT c.*, 
           COUNT(p.id) as project_count,
           SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed_projects
    FROM clients c
    LEFT JOIN projects p ON c.id = p.client_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");

// Get statistics
$stats = db()->fetch("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'lead' THEN 1 ELSE 0 END) as leads
    FROM clients
") ?? ['total' => 0, 'active' => 0, 'inactive' => 0, 'leads' => 0];

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>
        <?php 
        if ($action === 'view') echo 'Client Details';
        elseif ($action === 'edit') echo 'Edit Client';
        elseif ($action === 'add') echo 'Add New Client';
        else echo 'Client Management';
        ?>
    </h2>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add New Client
        </a>
        <a href="?action=import" class="btn btn-outline">
            <i class="fas fa-upload"></i>
            Import Clients
        </a>
        <?php elseif ($action === 'view' && $client): ?>
        <a href="?action=edit&id=<?php echo $client['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i>
            Edit Client
        </a>
        <a href="projects.php?client_id=<?php echo $client['id']; ?>" class="btn btn-outline">
            <i class="fas fa-project-diagram"></i>
            View Projects
        </a>
        <a href="invoices.php?client_id=<?php echo $client['id']; ?>" class="btn btn-outline">
            <i class="fas fa-file-invoice"></i>
            Invoices
        </a>
        <?php else: ?>
        <a href="clients.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to Clients
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Client created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Client updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Client deleted successfully!';
        if ($_GET['msg'] === 'has_projects') echo 'Cannot delete client with existing projects!';
        ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Stats Cards -->
    <div class="stats-mini-grid">
        <div class="stat-mini-card">
            <div class="stat-mini-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-mini-content">
                <h3>Total Clients</h3>
                <span class="stat-mini-value"><?php echo $stats['total']; ?></span>
            </div>
        </div>
        
        <div class="stat-mini-card">
            <div class="stat-mini-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-mini-content">
                <h3>Active</h3>
                <span class="stat-mini-value"><?php echo $stats['active']; ?></span>
            </div>
        </div>
        
        <div class="stat-mini-card">
            <div class="stat-mini-icon orange">
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="stat-mini-content">
                <h3>Inactive</h3>
                <span class="stat-mini-value"><?php echo $stats['inactive']; ?></span>
            </div>
        </div>
        
        <div class="stat-mini-card">
            <div class="stat-mini-icon purple">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-mini-content">
                <h3>Leads</h3>
                <span class="stat-mini-value"><?php echo $stats['leads']; ?></span>
            </div>
        </div>
    </div>

    <!-- Clients Table -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Contact</th>
                    <th>Projects</th>
                    <th>Status</th>
                    <th>Since</th>
                    <th width="150">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                <tr>
                    <td>
                        <div class="client-info">
                            <?php if ($client['logo']): ?>
                            <img src="<?php echo UPLOAD_URL . 'clients/' . $client['logo']; ?>" 
                                 alt="<?php echo $client['company_name']; ?>" 
                                 class="client-logo">
                            <?php endif; ?>
                            <div>
                                <strong><?php echo htmlspecialchars($client['company_name']); ?></strong>
                                <?php if ($client['website']): ?>
                                <br><small><?php echo htmlspecialchars($client['website']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($client['contact_person']); ?></strong>
                        <br><small><?php echo htmlspecialchars($client['email']); ?></small>
                        <?php if ($client['phone']): ?>
                        <br><small><?php echo htmlspecialchars($client['phone']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge"><?php echo $client['project_count']; ?> total</span>
                        <br><small><?php echo $client['completed_projects']; ?> completed</small>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $client['status']; ?>">
                            <?php echo ucfirst($client['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($client['created_at'])); ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="?action=view&id=<?php echo $client['id']; ?>" class="action-btn" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="?action=edit&id=<?php echo $client['id']; ?>" class="action-btn" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?toggle=<?php echo $client['id']; ?>" class="action-btn" title="Toggle Status">
                                <i class="fas fa-power-off"></i>
                            </a>
                            <a href="?delete=<?php echo $client['id']; ?>" 
                               class="action-btn delete-btn"
                               onclick="return confirm('Delete this client?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($clients)): ?>
                <tr>
                    <td colspan="6" class="text-center">No clients found. Add your first client!</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'view' && $client): ?>
    <!-- Client Details View -->
    <div class="client-details">
        <div class="details-header">
            <?php if ($client['logo']): ?>
            <img src="<?php echo UPLOAD_URL . 'clients/' . $client['logo']; ?>" 
                 alt="<?php echo $client['company_name']; ?>" 
                 class="details-logo">
            <?php endif; ?>
            <div class="details-title">
                <h2><?php echo htmlspecialchars($client['company_name']); ?></h2>
                <p><?php echo htmlspecialchars($client['contact_person']); ?></p>
            </div>
            <span class="status-badge <?php echo $client['status']; ?>">
                <?php echo ucfirst($client['status']); ?>
            </span>
        </div>
        
        <div class="details-grid">
            <div class="details-card">
                <h3>Contact Information</h3>
                <table class="details-table">
                    <tr>
                        <th>Email:</th>
                        <td><a href="mailto:<?php echo $client['email']; ?>"><?php echo $client['email']; ?></a></td>
                    </tr>
                    <?php if ($client['phone']): ?>
                    <tr>
                        <th>Phone:</th>
                        <td><a href="tel:<?php echo $client['phone']; ?>"><?php echo $client['phone']; ?></a></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($client['website']): ?>
                    <tr>
                        <th>Website:</th>
                        <td><a href="<?php echo $client['website']; ?>" target="_blank"><?php echo $client['website']; ?></a></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($client['address']): ?>
                    <tr>
                        <th>Address:</th>
                        <td><?php echo nl2br(htmlspecialchars($client['address'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div class="details-card">
                <h3>Project Statistics</h3>
                <?php
                $projectStats = db()->fetch("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) as planning,
                        SUM(budget) as total_budget,
                        SUM(paid_amount) as total_paid
                    FROM projects WHERE client_id = ?
                ", [$client['id']]);
                ?>
                <table class="details-table">
                    <tr>
                        <th>Total Projects:</th>
                        <td><?php echo $projectStats['total'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <th>Completed:</th>
                        <td><?php echo $projectStats['completed'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <th>In Progress:</th>
                        <td><?php echo $projectStats['in_progress'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <th>Total Budget:</th>
                        <td>$<?php echo number_format($projectStats['total_budget'] ?? 0, 2); ?></td>
                    </tr>
                    <tr>
                        <th>Paid:</th>
                        <td>$<?php echo number_format($projectStats['total_paid'] ?? 0, 2); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="details-card">
                <h3>Recent Projects</h3>
                <?php
                $recentProjects = db()->fetchAll("
                    SELECT * FROM projects 
                    WHERE client_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ", [$client['id']]);
                ?>
                <?php if ($recentProjects): ?>
                <ul class="project-list">
                    <?php foreach ($recentProjects as $project): ?>
                    <li>
                        <a href="project-details.php?id=<?php echo $project['id']; ?>">
                            <?php echo htmlspecialchars($project['title']); ?>
                        </a>
                        <span class="status-badge small <?php echo $project['status']; ?>">
                            <?php echo ucfirst($project['status']); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="no-data">No projects yet</p>
                <?php endif; ?>
                <a href="projects.php?client_id=<?php echo $client['id']; ?>" class="btn btn-outline btn-sm">
                    View All Projects
                </a>
            </div>
            
            <div class="details-card">
                <h3>Notes</h3>
                <p><?php echo nl2br(htmlspecialchars($client['notes'] ?: 'No notes')); ?></p>
            </div>
        </div>
        
        <!-- Activity Timeline -->
        <div class="activity-timeline">
            <h3>Recent Activity</h3>
            <?php
            $activities = db()->fetchAll("
                SELECT * FROM project_activity_log 
                WHERE client_id = ? OR project_id IN (SELECT id FROM projects WHERE client_id = ?)
                ORDER BY created_at DESC
                LIMIT 10
            ", [$client['id'], $client['id']]);
            ?>
            
            <?php if ($activities): ?>
            <div class="timeline">
                <?php foreach ($activities as $activity): ?>
                <div class="timeline-item">
                    <div class="timeline-time">
                        <?php echo timeAgo($activity['created_at']); ?>
                    </div>
                    <div class="timeline-content">
                        <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                        <?php if ($activity['details']): ?>
                        <p><?php echo htmlspecialchars($activity['details']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="no-data">No activity recorded</p>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Client Form -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <?php if ($client): ?>
            <input type="hidden" name="id" value="<?php echo $client['id']; ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <h3>Company Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="company_name">Company Name *</label>
                        <input type="text" id="company_name" name="company_name" required 
                               value="<?php echo $client['company_name'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input type="url" id="website" name="website" 
                               value="<?php echo $client['website'] ?? ''; ?>"
                               placeholder="https://example.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="logo">Company Logo</label>
                    <input type="file" id="logo" name="logo" accept="image/*">
                    <?php if ($client && $client['logo']): ?>
                    <div class="current-image">
                        <img src="<?php echo UPLOAD_URL . 'clients/' . $client['logo']; ?>" 
                             alt="Logo" style="max-width: 100px; margin-top: 10px;">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Contact Person</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_person">Contact Person *</label>
                        <input type="text" id="contact_person" name="contact_person" required 
                               value="<?php echo $client['contact_person'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo $client['email'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo $client['phone'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2"><?php echo $client['address'] ?? ''; ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Additional Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo ($client['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($client['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="lead" <?php echo ($client['status'] ?? '') === 'lead' ? 'selected' : ''; ?>>Lead</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="source">Source</label>
                        <input type="text" id="source" name="source" 
                               value="<?php echo $client['source'] ?? ''; ?>"
                               placeholder="e.g., Referral, Website, LinkedIn">
                    </div>
                </div>
                
                <?php if ($action === 'add'): ?>
                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="create_login" value="1" checked>
                        Create client login account (credentials will be emailed)
                    </label>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4"><?php echo $client['notes'] ?? ''; ?></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_client" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <?php echo $client ? 'Update Client' : 'Save Client'; ?>
                </button>
                <a href="clients.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
.client-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.client-logo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.stats-mini-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-mini-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stat-mini-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-mini-icon.blue { background: rgba(37,99,235,0.1); color: #2563eb; }
.stat-mini-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-mini-icon.orange { background: rgba(245,158,11,0.1); color: #f59e0b; }
.stat-mini-icon.purple { background: rgba(124,58,237,0.1); color: #7c3aed; }

.stat-mini-content h3 {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-bottom: 5px;
}

.stat-mini-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
}

/* Client Details View */
.client-details {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.details-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--gray-200);
}

.details-logo {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
}

.details-title {
    flex: 1;
}

.details-title h2 {
    font-size: 1.8rem;
    margin-bottom: 5px;
}

.details-title p {
    color: var(--gray-600);
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.details-card {
    background: var(--gray-100);
    border-radius: 10px;
    padding: 20px;
}

.details-card h3 {
    font-size: 1.1rem;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-300);
}

.details-table {
    width: 100%;
}

.details-table th {
    text-align: left;
    font-weight: 600;
    color: var(--gray-600);
    padding: 8px 0;
    width: 100px;
}

.details-table td {
    padding: 8px 0;
}

.project-list {
    list-style: none;
    margin-bottom: 15px;
}

.project-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-200);
}

.project-list li:last-child {
    border-bottom: none;
}

.project-list a {
    color: var(--primary);
    text-decoration: none;
}

.project-list a:hover {
    text-decoration: underline;
}

.status-badge.small {
    font-size: 0.7rem;
    padding: 2px 6px;
}

.activity-timeline {
    margin-top: 30px;
}

.activity-timeline h3 {
    font-size: 1.1rem;
    margin-bottom: 20px;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--gray-300);
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -34px;
    top: 0;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--primary);
    border: 2px solid white;
}

.timeline-time {
    font-size: 0.85rem;
    color: var(--gray-500);
    margin-bottom: 5px;
}

.timeline-content {
    background: var(--gray-100);
    padding: 15px;
    border-radius: 8px;
}a

.no-data {
    color: var(--gray-500);
    text-align: center;
    padding: 20px;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

@media (max-width: 768px) {
    .details-header {
        flex-direction: column;
        text-align: center;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
// Include footer
require_once 'includes/footer.php';
?>