<?php
// admin/clients.php
// Client Management - FULLY RESPONSIVE & FIXED

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

// Helper function for truncating email
function truncateEmail($email, $length = 20) {
    if (strlen($email) <= $length) return $email;
    return substr($email, 0, $length) . '...';
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if client has projects
    $projects = db()->fetch("SELECT COUNT(*) as count FROM projects WHERE client_id = ?", [$id])['count'] ?? 0;
    if ($projects > 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Cannot delete client with existing projects!'];
        header('Location: clients.php');
        exit;
    }
    
    // Delete client logo if exists
    $client = db()->fetch("SELECT logo FROM clients WHERE id = ?", [$id]);
    if ($client && !empty($client['logo'])) {
        $logoPath = UPLOAD_PATH_CLIENTS . $client['logo'];
        if (file_exists($logoPath)) {
            unlink($logoPath);
        }
    }
    
    db()->delete('clients', 'id = ?', [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Client deleted successfully'];
    header('Location: clients.php');
    exit;
}

// Handle status toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $client = db()->fetch("SELECT status FROM clients WHERE id = ?", [$id]);
    if ($client) {
        $newStatus = $client['status'] === 'active' ? 'inactive' : 'active';
        db()->update('clients', ['status' => $newStatus], 'id = :id', ['id' => $id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Client status updated'];
    }
    header('Location: clients.php');
    exit;
}

// Handle export
if (isset($_GET['export'])) {
    exportClients();
    exit;
}

// Export function
function exportClients() {
    $clients = db()->fetchAll("
        SELECT c.*, 
               COUNT(p.id) as project_count,
               SUM(p.budget) as total_budget
        FROM clients c
        LEFT JOIN projects p ON c.id = p.client_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="clients-' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Company Name', 'Contact Person', 'Email', 'Phone', 'Website', 'Status', 'Source', 'Projects', 'Total Budget', 'Created']);
    
    foreach ($clients as $client) {
        fputcsv($output, [
            $client['id'],
            $client['company_name'],
            $client['contact_person'],
            $client['email'],
            $client['phone'],
            $client['website'],
            $client['status'],
            $client['source'],
            $client['project_count'] ?? 0,
            $client['total_budget'] ?? 0,
            $client['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// Helper function to generate random password
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    return substr(str_shuffle($chars), 0, $length);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_client'])) {
    $data = [
        'company_name' => sanitize($_POST['company_name']),
        'contact_person' => sanitize($_POST['contact_person']),
        'email' => sanitize($_POST['email']),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'address' => sanitize($_POST['address'] ?? ''),
        'website' => sanitize($_POST['website'] ?? ''),
        'status' => $_POST['status'] ?? 'lead',
        'source' => sanitize($_POST['source'] ?? ''),
        'notes' => sanitize($_POST['notes'] ?? '')
    ];
    
    // Validate required fields
    $errors = [];
    if (empty($data['company_name'])) {
        $errors[] = 'Company name is required';
    }
    if (empty($data['contact_person'])) {
        $errors[] = 'Contact person is required';
    }
    if (empty($data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    if (!empty($errors)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => implode('<br>', $errors)];
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['logo'], 'clients/');
        if (isset($upload['success'])) {
            // Delete old logo if updating
            if (!empty($_POST['id'])) {
                $old = db()->fetch("SELECT logo FROM clients WHERE id = ?", [$_POST['id']]);
                if ($old && !empty($old['logo'])) {
                    $oldPath = UPLOAD_PATH_CLIENTS . $old['logo'];
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
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Client updated successfully'];
        header('Location: clients.php');
        exit;
    } else {
        $clientId = db()->insert('clients', $data);
        
        if ($clientId) {
            // Create client user account if requested
            if (!empty($_POST['create_login'])) {
                $password = generateRandomPassword();
                $nameParts = explode(' ', $data['contact_person'], 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
                
                try {
                    $userId = db()->insert('client_users', [
                        'client_id' => $clientId,
                        'email' => $data['email'],
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'role' => 'primary',
                        'is_active' => 1
                    ]);
                    
                    if ($userId) {
                        // Send welcome email
                        $subject = "Welcome to " . SITE_NAME . " Client Portal";
                        $message = "
                        <html>
                        <body style='font-family: Arial, sans-serif;'>
                            <h2>Welcome to " . SITE_NAME . "!</h2>
                            <p>Hello " . htmlspecialchars($data['contact_person']) . ",</p>
                            <p>Your client portal account has been created successfully.</p>
                            <p><strong>Login credentials:</strong></p>
                            <ul>
                                <li>Email: " . htmlspecialchars($data['email']) . "</li>
                                <li>Password: " . htmlspecialchars($password) . "</li>
                            </ul>
                            <p><a href='" . BASE_URL . "/client' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Portal</a></p>
                            <p>Please change your password after first login.</p>
                        </body>
                        </html>
                        ";
                        
                        $headers = "MIME-Version: 1.0\r\n";
                        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                        $headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                        
                        mail($data['email'], $subject, $message, $headers);
                    }
                } catch (Exception $e) {
                    error_log("Failed to create client user: " . $e->getMessage());
                }
            }
            
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Client created successfully'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to create client'];
        }
        
        header('Location: clients.php');
        exit;
    }
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
           SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
           SUM(p.budget) as total_budget,
           SUM(p.paid_amount) as total_paid
    FROM clients c
    LEFT JOIN projects p ON c.id = p.client_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
") ?? [];

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

<!-- Page Header -->
<div class="content-header">
    <h1>
        <i class="fas fa-users"></i> 
        <?php 
        if ($action === 'view') echo 'Client Details';
        elseif ($action === 'edit') echo 'Edit Client';
        elseif ($action === 'add') echo 'Add New Client';
        else echo 'Client Management';
        ?>
        <?php if ($action === 'list' && $stats['total'] > 0): ?>
        <span class="header-badge"><?php echo $stats['active']; ?> active</span>
        <?php endif; ?>
    </h1>
    <div class="header-actions">
        <?php if ($action === 'list'): ?>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Client
        </a>
        <a href="?export=1" class="btn btn-outline">
            <i class="fas fa-download"></i> Export
        </a>
        <?php elseif ($action === 'view' && $client): ?>
        <a href="?action=edit&id=<?php echo $client['id']; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Edit Client
        </a>
        <a href="projects.php?client_id=<?php echo $client['id']; ?>" class="btn btn-outline">
            <i class="fas fa-project-diagram"></i> Projects
        </a>
        <a href="invoices.php?client_id=<?php echo $client['id']; ?>" class="btn btn-outline">
            <i class="fas fa-file-invoice"></i> Invoices
        </a>
        <?php else: ?>
        <a href="clients.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash']['type']; ?> alert-dismissible">
        <i class="fas <?php echo $_SESSION['flash']['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        <?php echo $_SESSION['flash']['message']; ?>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Total Clients</span>
                <span class="stat-value"><?php echo $stats['total']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Active</span>
                <span class="stat-value"><?php echo $stats['active']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Inactive</span>
                <span class="stat-value"><?php echo $stats['inactive']; ?></span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-details">
                <span class="stat-label">Leads</span>
                <span class="stat-value"><?php echo $stats['leads']; ?></span>
            </div>
        </div>
    </div>

    <!-- Desktop Table View -->
    <div class="desktop-table">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Projects</th>
                        <th>Revenue</th>
                        <th>Status</th>
                        <th>Since</th>
                        <th width="180">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                    <tr>
                        <td>
                            <div class="client-info">
                                <?php if (!empty($client['logo'])): ?>
                                <img src="<?php echo UPLOAD_URL_CLIENTS . $client['logo']; ?>" 
                                     alt="<?php echo htmlspecialchars($client['company_name']); ?>" 
                                     class="client-logo">
                                <?php else: ?>
                                <div class="client-logo-placeholder">
                                    <?php echo strtoupper(substr($client['company_name'] ?: 'C', 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($client['company_name'] ?: 'No Company'); ?></strong>
                                    <?php if (!empty($client['website'])): ?>
                                    <br><small class="website-link"><?php echo parse_url($client['website'], PHP_URL_HOST) ?: $client['website']; ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="contact-info">
                                <strong><?php echo htmlspecialchars($client['contact_person'] ?: 'No Contact'); ?></strong>
                                <br><small><a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"><?php echo htmlspecialchars(truncateEmail($client['email'], 20)); ?></a></small>
                                <?php if (!empty($client['phone'])): ?>
                                <br><small><a href="tel:<?php echo htmlspecialchars($client['phone']); ?>"><?php echo htmlspecialchars($client['phone']); ?></a></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge"><?php echo $client['project_count'] ?? 0; ?> total</span>
                            <?php if (!empty($client['completed_projects'])): ?>
                            <br><small><?php echo $client['completed_projects']; ?> completed</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong>$<?php echo number_format($client['total_paid'] ?? 0, 0); ?></strong>
                            <?php if (!empty($client['total_budget'])): ?>
                            <br><small class="text-muted">of $<?php echo number_format($client['total_budget'], 0); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $client['status'] ?? 'lead'; ?>">
                                <?php echo ucfirst($client['status'] ?? 'lead'); ?>
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
                                <a href="projects.php?client_id=<?php echo $client['id']; ?>" class="action-btn" title="Projects">
                                    <i class="fas fa-project-diagram"></i>
                                </a>
                                <a href="?delete=<?php echo $client['id']; ?>" class="action-btn delete-btn" 
                                   onclick="return confirm('Are you sure you want to delete this client? This action cannot be undone.')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h3>No Clients Found</h3>
                                <p>Get started by adding your first client.</p>
                                <a href="?action=add" class="btn btn-primary">Add New Client</a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile Cards View -->
    <div class="mobile-cards">
        <?php foreach ($clients as $client): ?>
        <div class="client-card">
            <div class="card-header">
                <div class="client-avatar">
                    <?php if (!empty($client['logo'])): ?>
                    <img src="<?php echo UPLOAD_URL_CLIENTS . $client['logo']; ?>" 
                         alt="<?php echo htmlspecialchars($client['company_name']); ?>">
                    <?php else: ?>
                    <div class="avatar-placeholder">
                        <?php echo strtoupper(substr($client['company_name'] ?: 'C', 0, 1)); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="client-title">
                    <h3><?php echo htmlspecialchars($client['company_name'] ?: 'No Company'); ?></h3>
                    <span class="status-badge <?php echo $client['status'] ?? 'lead'; ?>">
                        <?php echo ucfirst($client['status'] ?? 'lead'); ?>
                    </span>
                </div>
            </div>
            
            <div class="card-body">
                <div class="info-row">
                    <i class="fas fa-user"></i>
                    <span><?php echo htmlspecialchars($client['contact_person'] ?: 'No Contact'); ?></span>
                </div>
                <div class="info-row">
                    <i class="fas fa-envelope"></i>
                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"><?php echo htmlspecialchars(truncateEmail($client['email'], 25)); ?></a>
                </div>
                <?php if (!empty($client['phone'])): ?>
                <div class="info-row">
                    <i class="fas fa-phone"></i>
                    <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>"><?php echo htmlspecialchars($client['phone']); ?></a>
                </div>
                <?php endif; ?>
                <?php if (!empty($client['website'])): ?>
                <div class="info-row">
                    <i class="fas fa-globe"></i>
                    <a href="<?php echo htmlspecialchars($client['website']); ?>" target="_blank"><?php echo parse_url($client['website'], PHP_URL_HOST) ?: $client['website']; ?></a>
                </div>
                <?php endif; ?>
                
                <div class="stats-row">
                    <div class="stat">
                        <span class="stat-label">Projects</span>
                        <span class="stat-number"><?php echo $client['project_count'] ?? 0; ?></span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Completed</span>
                        <span class="stat-number"><?php echo $client['completed_projects'] ?? 0; ?></span>
                    </div>
                    <div class="stat">
                        <span class="stat-label">Revenue</span>
                        <span class="stat-number">$<?php echo number_format($client['total_paid'] ?? 0, 0); ?></span>
                    </div>
                </div>
                
                <div class="date-row">
                    <i class="far fa-calendar-alt"></i>
                    <span>Client since: <?php echo date('M d, Y', strtotime($client['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="card-footer">
                <div class="action-buttons">
                    <a href="?action=view&id=<?php echo $client['id']; ?>" class="btn btn-sm btn-outline" title="View">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="?action=edit&id=<?php echo $client['id']; ?>" class="btn btn-sm btn-outline" title="Edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?toggle=<?php echo $client['id']; ?>" class="btn btn-sm btn-outline" title="Toggle Status">
                        <i class="fas fa-power-off"></i> Toggle
                    </a>
                    <a href="?delete=<?php echo $client['id']; ?>" class="btn btn-sm btn-outline-danger" 
                       onclick="return confirm('Are you sure you want to delete this client? This action cannot be undone.')" title="Delete">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($clients)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>No Clients Found</h3>
            <p>Get started by adding your first client.</p>
            <a href="?action=add" class="btn btn-primary">Add New Client</a>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'view' && $client): ?>
    <!-- Client Details View -->
    <div class="details-container">
        <div class="details-header">
            <?php if (!empty($client['logo'])): ?>
            <img src="<?php echo UPLOAD_URL_CLIENTS . $client['logo']; ?>" 
                 alt="<?php echo htmlspecialchars($client['company_name']); ?>" 
                 class="details-logo">
            <?php else: ?>
            <div class="details-logo-placeholder">
                <?php echo strtoupper(substr($client['company_name'] ?: 'C', 0, 1)); ?>
            </div>
            <?php endif; ?>
            <div class="details-title">
                <h2><?php echo htmlspecialchars($client['company_name'] ?: 'No Company Name'); ?></h2>
                <p><?php echo htmlspecialchars($client['contact_person'] ?: 'No Contact Person'); ?></p>
            </div>
            <span class="status-badge <?php echo $client['status'] ?? 'lead'; ?>">
                <?php echo ucfirst($client['status'] ?? 'lead'); ?>
            </span>
        </div>
        
        <div class="details-grid">
            <div class="details-card">
                <h3><i class="fas fa-address-card"></i> Contact Information</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"><?php echo htmlspecialchars($client['email']); ?></a></span>
                    </div>
                    <?php if (!empty($client['phone'])): ?>
                    <div class="info-item">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><a href="tel:<?php echo htmlspecialchars($client['phone']); ?>"><?php echo htmlspecialchars($client['phone']); ?></a></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['website'])): ?>
                    <div class="info-item">
                        <span class="info-label">Website:</span>
                        <span class="info-value"><a href="<?php echo htmlspecialchars($client['website']); ?>" target="_blank"><?php echo htmlspecialchars($client['website']); ?></a></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['address'])): ?>
                    <div class="info-item">
                        <span class="info-label">Address:</span>
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($client['address'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="details-card">
                <h3><i class="fas fa-chart-bar"></i> Project Statistics</h3>
                <?php
                $projectStats = db()->fetch("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) as planning,
                        COALESCE(SUM(budget), 0) as total_budget,
                        COALESCE(SUM(paid_amount), 0) as total_paid
                    FROM projects WHERE client_id = ?
                ", [$client['id']]);
                ?>
                <div class="stats-list">
                    <div class="stat-row">
                        <span class="stat-label">Total Projects:</span>
                        <span class="stat-value"><?php echo $projectStats['total'] ?? 0; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Completed:</span>
                        <span class="stat-value"><?php echo $projectStats['completed'] ?? 0; ?></span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">In Progress:</span>
                        <span class="stat-value"><?php echo $projectStats['in_progress'] ?? 0; ?></span>
                    </div>
                    <div class="stat-row highlight">
                        <span class="stat-label">Total Budget:</span>
                        <span class="stat-value">$<?php echo number_format($projectStats['total_budget'] ?? 0, 2); ?></span>
                    </div>
                    <div class="stat-row highlight">
                        <span class="stat-label">Paid:</span>
                        <span class="stat-value text-success">$<?php echo number_format($projectStats['total_paid'] ?? 0, 2); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="details-card">
                <h3><i class="fas fa-clock"></i> Recent Projects</h3>
                <?php
                $recentProjects = db()->fetchAll("
                    SELECT id, title, status, created_at 
                    FROM projects 
                    WHERE client_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ", [$client['id']]);
                ?>
                <?php if (!empty($recentProjects)): ?>
                <ul class="project-list">
                    <?php foreach ($recentProjects as $project): ?>
                    <li>
                        <a href="project-details.php?id=<?php echo $project['id']; ?>">
                            <?php echo htmlspecialchars($project['title']); ?>
                        </a>
                        <span class="status-badge small <?php echo $project['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="projects.php?client_id=<?php echo $client['id']; ?>" class="btn btn-outline btn-sm btn-block">
                    View All Projects
                </a>
                <?php else: ?>
                <p class="no-data">No projects yet</p>
                <?php endif; ?>
            </div>
            
            <div class="details-card">
                <h3><i class="fas fa-sticky-note"></i> Notes</h3>
                <div class="notes-content">
                    <?php if (!empty($client['notes'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($client['notes'])); ?></p>
                    <?php else: ?>
                    <p class="text-muted">No notes available</p>
                    <?php endif; ?>
                </div>
                <div class="meta-info">
                    <p class="text-muted small">
                        <i class="fas fa-calendar-alt"></i> Client since: <?php echo date('F d, Y', strtotime($client['created_at'])); ?>
                    </p>
                    <?php if (!empty($client['source'])): ?>
                    <p class="text-muted small">
                        <i class="fas fa-tag"></i> Source: <?php echo htmlspecialchars($client['source']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Client Form -->
    <div class="form-container">
        <form method="POST" enctype="multipart/form-data" class="admin-form" id="clientForm">
            <?php if ($client): ?>
            <input type="hidden" name="id" value="<?php echo $client['id']; ?>">
            <?php endif; ?>
            
            <div class="form-section">
                <h2><i class="fas fa-building"></i> Company Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="company_name">Company Name <span class="required">*</span></label>
                        <input type="text" id="company_name" name="company_name" required 
                               value="<?php echo htmlspecialchars($client['company_name'] ?? ''); ?>"
                               placeholder="e.g., Acme Corporation">
                    </div>
                    
                    <div class="form-group">
                        <label for="website">Website</label>
                        <input type="url" id="website" name="website" 
                               value="<?php echo htmlspecialchars($client['website'] ?? ''); ?>"
                               placeholder="https://example.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="logo">Company Logo</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="logo" name="logo" accept="image/*">
                        <div class="file-input-label">
                            <i class="fas fa-upload"></i> Choose File
                        </div>
                    </div>
                    <?php if ($client && !empty($client['logo'])): ?>
                    <div class="current-image">
                        <img src="<?php echo UPLOAD_URL_CLIENTS . $client['logo']; ?>" 
                             alt="Logo" class="preview-image">
                        <p class="small">Current logo</p>
                    </div>
                    <?php endif; ?>
                    <p class="help-text">Recommended size: 200x200px. Max size: 2MB</p>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-user"></i> Contact Person</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contact_person">Contact Person <span class="required">*</span></label>
                        <input type="text" id="contact_person" name="contact_person" required 
                               value="<?php echo htmlspecialchars($client['contact_person'] ?? ''); ?>"
                               placeholder="e.g., John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>"
                               placeholder="john@example.com">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>"
                               placeholder="+1 234 567 890">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2" 
                                  placeholder="Street, City, State, ZIP"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-cog"></i> Additional Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo (isset($client['status']) && $client['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (isset($client['status']) && $client['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="lead" <?php echo (isset($client['status']) && $client['status'] === 'lead') ? 'selected' : ''; ?>>Lead</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="source">Source</label>
                        <input type="text" id="source" name="source" 
                               value="<?php echo htmlspecialchars($client['source'] ?? ''); ?>"
                               placeholder="e.g., Referral, Website, LinkedIn">
                    </div>
                </div>
                
                <?php if ($action === 'add'): ?>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="create_login" value="1" checked>
                        <span class="checkbox-text">Create client login account (credentials will be emailed)</span>
                    </label>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4" 
                              placeholder="Any additional information about this client..."><?php echo htmlspecialchars($client['notes'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_client" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    <?php echo $client ? 'Update Client' : 'Save Client'; ?>
                </button>
                <a href="clients.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<style>
/* ========================================
   CLIENTS PAGE - COMPACT & MODERN STYLES
   ======================================== */

:root {
    --primary: #2563eb;
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
    --gray-50: #f8fafc;
}

/* Content Header */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.content-header h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.content-header h1 i {
    color: var(--primary);
}

.header-badge {
    display: inline-block;
    padding: 4px 12px;
    background: var(--success);
    color: white;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    line-height: 1;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37,99,235,0.2);
}

.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: white;
}

.btn-outline-danger {
    background: transparent;
    color: var(--danger);
    border: 2px solid var(--danger);
}

.btn-outline-danger:hover {
    background: var(--danger);
    color: white;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

.btn-lg {
    padding: 14px 28px;
    font-size: 16px;
}

.btn-block {
    display: block;
    width: 100%;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.blue { background: rgba(37,99,235,0.1); color: #2563eb; }
.stat-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-icon.orange { background: rgba(245,158,11,0.1); color: #f59e0b; }
.stat-icon.purple { background: rgba(124,58,237,0.1); color: #7c3aed; }

.stat-details {
    display: flex;
    flex-direction: column;
}

.stat-label {
    font-size: 14px;
    color: var(--gray-500);
    margin-bottom: 4px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

/* Desktop Table */
.desktop-table {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

.data-table th {
    background: var(--gray-100);
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 16px;
    border-bottom: 1px solid var(--gray-200);
    vertical-align: middle;
    font-size: 14px;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background: var(--gray-50);
}

/* Client Info */
.client-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.client-logo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--gray-200);
}

.client-logo-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
}

.website-link {
    color: var(--gray-500);
    text-decoration: none;
    font-size: 12px;
}

.contact-info {
    line-height: 1.5;
}

.contact-info a {
    color: var(--gray-600);
    text-decoration: none;
}

.contact-info a:hover {
    color: var(--primary);
}

/* Badge */
.badge {
    display: inline-block;
    padding: 4px 8px;
    background: var(--gray-200);
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    color: var(--gray-700);
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.active { background: rgba(16,185,129,0.1); color: #10b981; }
.status-badge.inactive { background: rgba(107,114,128,0.1); color: #6b7280; }
.status-badge.lead { background: rgba(245,158,11,0.1); color: #f59e0b; }
.status-badge.small { font-size: 10px; padding: 4px 8px; }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.action-btn {
    width: 36px;
    height: 36px;
    background: var(--gray-100);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: var(--primary);
    color: white;
    transform: scale(1.1);
}

.action-btn.delete-btn:hover {
    background: var(--danger);
}

/* Mobile Cards */
.mobile-cards {
    display: none;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 30px;
}

.client-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.card-header {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.client-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    flex-shrink: 0;
}

.client-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 600;
}

.client-title {
    flex: 1;
}

.client-title h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--dark);
}

.card-body {
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    font-size: 14px;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-200);
}

.info-row:last-child {
    border-bottom: none;
}

.info-row i {
    width: 18px;
    color: var(--primary);
}

.info-row a {
    color: var(--gray-600);
    text-decoration: none;
    flex: 1;
    word-break: break-word;
}

.info-row a:hover {
    color: var(--primary);
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin: 16px 0;
}

.stat {
    background: var(--gray-100);
    padding: 12px;
    border-radius: 12px;
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 11px;
    color: var(--gray-500);
    margin-bottom: 4px;
    text-transform: uppercase;
}

.stat-number {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
}

.date-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--gray-500);
    padding: 8px 0;
}

.date-row i {
    color: var(--primary);
}

.card-footer .action-buttons {
    justify-content: flex-end;
}

/* Client Details View */
.details-container {
    background: white;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.details-header {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 2px solid var(--gray-200);
    flex-wrap: wrap;
}

.details-logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--primary);
}

.details-logo-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: 600;
    border: 4px solid var(--primary);
}

.details-title {
    flex: 1;
}

.details-title h2 {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
    color: var(--dark);
}

.details-title p {
    font-size: 16px;
    color: var(--gray-600);
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
}

.details-card {
    background: var(--gray-100);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid var(--gray-200);
}

.details-card h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--gray-300);
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--dark);
}

.details-card h3 i {
    color: var(--primary);
}

/* Info List */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-item {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-200);
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    width: 90px;
    font-weight: 600;
    color: var(--gray-600);
    font-size: 14px;
}

.info-value {
    flex: 1;
    color: var(--dark);
    word-break: break-word;
    font-size: 14px;
}

.info-value a {
    color: var(--primary);
    text-decoration: none;
}

.info-value a:hover {
    text-decoration: underline;
}

/* Stats List */
.stats-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--gray-200);
}

.stat-row.highlight {
    font-weight: 600;
    color: var(--dark);
}

.stat-row .stat-label {
    color: var(--gray-600);
    font-size: 14px;
}

.stat-row .stat-value {
    font-weight: 600;
    font-size: 14px;
}

.text-success {
    color: var(--success);
}

.text-muted {
    color: var(--gray-500);
}

/* Project List */
.project-list {
    list-style: none;
    margin-bottom: 16px;
}

.project-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-200);
}

.project-list li:last-child {
    border-bottom: none;
}

.project-list a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
}

.project-list a:hover {
    text-decoration: underline;
}

/* Notes */
.notes-content {
    min-height: 60px;
    margin-bottom: 16px;
    color: var(--gray-700);
    line-height: 1.6;
    font-size: 14px;
}

.meta-info {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}

.meta-info p {
    margin-bottom: 8px;
}

.meta-info p:last-child {
    margin-bottom: 0;
}

.meta-info i {
    width: 18px;
    color: var(--primary);
    margin-right: 8px;
}

.no-data {
    color: var(--gray-500);
    text-align: center;
    padding: 24px;
    font-size: 14px;
}

/* Form Container */
.form-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-section {
    background: white;
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}

.form-section h2 {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--dark);
}

.form-section h2 i {
    color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--dark);
    font-size: 14px;
}

.required {
    color: var(--danger);
    margin-left: 2px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group input[type="url"],
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

/* File Input */
.file-input-wrapper {
    position: relative;
    margin-bottom: 12px;
}

.file-input-wrapper input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
}

.file-input-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    background: var(--gray-100);
    border: 2px dashed var(--gray-300);
    border-radius: 10px;
    color: var(--gray-600);
    transition: all 0.2s ease;
    font-size: 14px;
}

.file-input-wrapper:hover .file-input-label {
    background: var(--gray-200);
    border-color: var(--primary);
    color: var(--primary);
}

/* Current Image */
.current-image {
    margin-top: 12px;
    text-align: center;
}

.preview-image {
    max-width: 100px;
    max-height: 100px;
    border-radius: 8px;
    border: 2px solid var(--gray-200);
}

.help-text {
    font-size: 12px;
    color: var(--gray-500);
    margin-top: 8px;
}

/* Checkbox */
.checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
}

.checkbox-text {
    font-size: 14px;
    color: var(--dark);
}

/* Alert */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

.alert-success {
    background: rgba(16,185,129,0.1);
    color: #065f46;
    border: 1px solid rgba(16,185,129,0.2);
}

.alert-error {
    background: rgba(239,68,68,0.1);
    color: #991b1b;
    border: 1px solid rgba(239,68,68,0.2);
}

.alert-close {
    margin-left: auto;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
    padding: 0;
    line-height: 1;
}

.alert-close:hover {
    opacity: 1;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 16px;
    justify-content: flex-end;
    margin-top: 24px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 48px;
    color: var(--gray-300);
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 20px;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--gray-500);
    margin-bottom: 24px;
}

/* Small text */
.small {
    font-size: 12px;
}

/* ========================================
   RESPONSIVE BREAKPOINTS
   ======================================== */

/* Tablet */
@media (max-width: 1023px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
    
    .form-section {
        padding: 24px;
    }
}

/* Mobile Landscape & Portrait */
@media (max-width: 767px) {
    .content-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .header-actions .btn {
        flex: 1;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .desktop-table {
        display: none;
    }
    
    .mobile-cards {
        display: flex;
    }
    
    .details-header {
        flex-direction: column;
        text-align: center;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .info-item {
        flex-direction: column;
        gap: 4px;
    }
    
    .info-label {
        width: auto;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .details-container {
        padding: 20px;
    }
}

/* Small Mobile */
@media (max-width: 480px) {
    .header-actions {
        flex-direction: column;
    }
    
    .header-actions .btn {
        width: 100%;
    }
    
    .client-card .action-buttons {
        flex-direction: column;
    }
    
    .client-card .btn {
        width: 100%;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .form-section {
        padding: 20px;
    }
}

/* Print */
@media print {
    .header-actions,
    .action-buttons,
    .btn,
    .form-actions,
    .mobile-cards {
        display: none !important;
    }
    
    .desktop-table {
        display: block !important;
    }
    
    .details-container {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<script>
// File input preview
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const label = this.nextElementSibling;
            if (label && label.classList.contains('file-input-label')) {
                const fileName = this.files[0] ? this.files[0].name : 'Choose File';
                label.innerHTML = `<i class="fas fa-check"></i> ${fileName}`;
            }
        });
    });

    // Form validation
    const clientForm = document.getElementById('clientForm');
    if (clientForm) {
        clientForm.addEventListener('submit', function(e) {
            const company = document.getElementById('company_name');
            const contact = document.getElementById('contact_person');
            const email = document.getElementById('email');
            
            if (!company.value.trim()) {
                alert('Company name is required');
                e.preventDefault();
                return;
            }
            
            if (!contact.value.trim()) {
                alert('Contact person is required');
                e.preventDefault();
                return;
            }
            
            if (!email.value.trim()) {
                alert('Email is required');
                e.preventDefault();
                return;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value)) {
                alert('Please enter a valid email address');
                e.preventDefault();
                return;
            }
        });
    }

    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});

// Handle responsive behavior
function handleResponsive() {
    const width = window.innerWidth;
    const desktopTable = document.querySelector('.desktop-table');
    const mobileCards = document.querySelector('.mobile-cards');
    
    if (width <= 767) {
        if (desktopTable) desktopTable.style.display = 'none';
        if (mobileCards) mobileCards.style.display = 'flex';
    } else {
        if (desktopTable) desktopTable.style.display = 'block';
        if (mobileCards) mobileCards.style.display = 'none';
    }
}

// Run on load and resize
window.addEventListener('load', handleResponsive);
window.addEventListener('resize', handleResponsive);
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>