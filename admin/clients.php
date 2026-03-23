<?php
// admin/clients.php - Client Management with Project Assignment
require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Client Management';
$action    = $_GET['action'] ?? 'list';
$id        = (int)($_GET['id'] ?? 0);

// ────────────────────────────────────────────────
// CSRF PROTECTION
// ────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ────────────────────────────────────────────────
// ACTION HANDLERS
// ────────────────────────────────────────────────
$redirect = false;
$successMsgKey = null;
$errors = [];

switch (true) {
    // ───────────── DELETE CLIENT ─────────────
    case isset($_GET['delete']):
        $deleteId = (int)$_GET['delete'];
        
        // Check for associated projects
        $projectCount = db()->fetchColumn("SELECT COUNT(*) FROM projects WHERE client_id = ?", [$deleteId]);

        if ($projectCount > 0) {
            $successMsgKey = 'has_projects';
        } else {
            // Delete client logo
            $client = db()->fetch("SELECT logo FROM clients WHERE id = ?", [$deleteId]);
            if ($client && !empty($client['logo'])) {
                $path = UPLOAD_PATH . 'clients/' . $client['logo'];
                if (file_exists($path)) @unlink($path);
            }
            
            // Delete client portal users
            db()->delete('client_users', 'client_id = ?', [$deleteId]);
            
            // Delete client
            db()->delete('clients', 'id = ?', [$deleteId]);
            $successMsgKey = 'deleted';
        }
        $redirect = true;
        break;

    // ───────────── TOGGLE STATUS ─────────────
    case isset($_GET['toggle']):
        $toggleId = (int)$_GET['toggle'];
        $status = db()->fetchColumn("SELECT status FROM clients WHERE id = ?", [$toggleId]);

        if ($status) {
            $newStatus = $status === 'active' ? 'inactive' : 'active';
            
            // Update client status
            db()->update('clients', ['status' => $newStatus], 'id = ?', [$toggleId]);
            
            // Also update client_users status if exists
            db()->update('client_users', 
                ['is_active' => $newStatus === 'active' ? 1 : 0], 
                'client_id = ?', 
                [$toggleId]
            );
            
            $successMsgKey = 'updated';
        }
        $redirect = true;
        break;

    // ───────────── ASSIGN PROJECT ─────────────
    case isset($_POST['assign_project']):
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
            $errors[] = 'Security check failed. Please try again.';
        } else {
            $clientId = (int)$_POST['client_id'];
            $projectId = (int)$_POST['project_id'];
            
            // Check if project already assigned
            $existing = db()->fetch("SELECT id FROM projects WHERE id = ? AND client_id IS NOT NULL", [$projectId]);
            
            if ($existing) {
                $errors[] = 'This project is already assigned to another client.';
            } else {
                db()->update('projects', ['client_id' => $clientId], 'id = ?', [$projectId]);
                $successMsgKey = 'project_assigned';
            }
        }
        $redirect = true;
        break;

    // ───────────── UNASSIGN PROJECT ─────────────
    case isset($_GET['unassign']):
        $projectId = (int)$_GET['unassign'];
        $clientId = (int)$_GET['cid'];
        
        db()->update('projects', ['client_id' => null], 'id = ?', [$projectId]);
        $successMsgKey = 'project_unassigned';
        $redirect = true;
        break;

    // ───────────── SAVE CLIENT ─────────────
    case $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_client']):
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
            $errors[] = 'Security check failed. Please try again.';
        } else {
            $data = [
                'company_name'   => trim($_POST['company_name'] ?? ''),
                'contact_person' => trim($_POST['contact_person'] ?? ''),
                'email'          => trim(strtolower($_POST['email'] ?? '')),
                'phone'          => trim($_POST['phone'] ?? ''),
                'address'        => trim($_POST['address'] ?? ''),
                'website'        => trim(rtrim($_POST['website'] ?? '', '/')),
                'status'         => in_array($_POST['status'] ?? '', ['active','inactive','lead']) ? $_POST['status'] : 'lead',
                'source'         => trim($_POST['source'] ?? ''),
                'notes'          => trim($_POST['notes'] ?? '')
            ];

            // ───────────── VALIDATION ─────────────
            if (empty($data['company_name']))   $errors[] = 'Company name is required.';
            if (empty($data['contact_person'])) $errors[] = 'Contact person is required.';
            if (empty($data['email']))          $errors[] = 'Email is required.';
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';

            // ───────────── DUPLICATE EMAIL CHECK ─────────────
            if (!empty($_POST['id'])) {
                $existing = db()->fetch("SELECT id FROM clients WHERE email = ? AND id != ?", 
                    [$data['email'], $_POST['id']]);
            } else {
                $existing = db()->fetch("SELECT id FROM clients WHERE email = ?", [$data['email']]);
            }
            if ($existing) $errors[] = 'A client with this email already exists.';

            // ───────────── LOGO UPLOAD ─────────────
            if (empty($errors) && !empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['logo'], 'clients/', ['jpg','jpeg','png','gif','webp'], 4);
                if (isset($upload['success'])) {
                    if (!empty($_POST['id'])) {
                        $old = db()->fetchColumn("SELECT logo FROM clients WHERE id = ?", [$_POST['id']]);
                        if ($old) {
                            $path = UPLOAD_PATH . 'clients/' . $old;
                            if (file_exists($path)) @unlink($path);
                        }
                    }
                    $data['logo'] = $upload['filename'];
                } else {
                    $errors[] = $upload['error'] ?? 'Logo upload failed.';
                }
            }

            // ───────────── INSERT / UPDATE ─────────────
            if (empty($errors)) {
                if (!empty($_POST['id'])) {
                    db()->update('clients', $data, 'id = ?', [$_POST['id']]);
                    $successMsgKey = 'updated';
                } else {
                    $clientId = db()->insert('clients', $data + ['created_at' => date('Y-m-d H:i:s')]);
                    $successMsgKey = 'created';

                    // ───────────── CREATE CLIENT PORTAL & SEND WELCOME EMAIL ─────────────
                    if (!empty($_POST['create_login'])) {
                        $plainPassword = bin2hex(random_bytes(8));
                        $nameParts = array_pad(explode(' ', trim($data['contact_person']), 2), 2, '');

                        db()->insert('client_users', [
                            'client_id'     => $clientId,
                            'email'         => $data['email'],
                            'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
                            'first_name'    => $nameParts[0],
                            'last_name'     => $nameParts[1],
                            'role'          => 'primary',
                            'is_active'     => $data['status'] === 'active' ? 1 : 0,
                            'created_at'    => date('Y-m-d H:i:s')
                        ]);

                        // Send welcome email using Mailer class
                        $subject = "Welcome to " . (defined('SITE_NAME') ? SITE_NAME : 'Client Portal');
                        
                        // Get company name from settings
                        $companyName = function_exists('getSetting') ? getSetting('site_name', (defined('SITE_NAME') ? SITE_NAME : 'Client Portal')) : (defined('SITE_NAME') ? SITE_NAME : 'Client Portal');
                        
                        // Build HTML email body
                        $htmlBody = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                            <style>
                                body {
                                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                                    line-height: 1.6;
                                    color: #1e293b;
                                    margin: 0;
                                    padding: 0;
                                    background-color: #f8fafc;
                                }
                                .container {
                                    max-width: 600px;
                                    margin: 20px auto;
                                    background: #ffffff;
                                    border-radius: 12px;
                                    overflow: hidden;
                                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                                }
                                .header {
                                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                    color: white;
                                    padding: 30px;
                                    text-align: center;
                                }
                                .header h1 {
                                    margin: 0;
                                    font-size: 28px;
                                    font-weight: 600;
                                }
                                .header p {
                                    margin: 10px 0 0;
                                    opacity: 0.9;
                                    font-size: 16px;
                                }
                                .content {
                                    padding: 30px;
                                }
                                .greeting {
                                    font-size: 18px;
                                    margin-bottom: 20px;
                                }
                                .credentials-box {
                                    background: #f1f5f9;
                                    padding: 25px;
                                    border-radius: 8px;
                                    margin: 25px 0;
                                    border-left: 4px solid #667eea;
                                }
                                .credentials-box h3 {
                                    margin: 0 0 15px 0;
                                    color: #1e293b;
                                    font-size: 18px;
                                }
                                .credential-item {
                                    margin-bottom: 12px;
                                    padding-bottom: 12px;
                                    border-bottom: 1px solid #e2e8f0;
                                }
                                .credential-item:last-child {
                                    border-bottom: none;
                                    margin-bottom: 0;
                                    padding-bottom: 0;
                                }
                                .credential-label {
                                    font-weight: 600;
                                    color: #475569;
                                    display: block;
                                    margin-bottom: 4px;
                                    font-size: 14px;
                                }
                                .credential-value {
                                    font-size: 16px;
                                    color: #1e293b;
                                    font-family: monospace;
                                    background: #ffffff;
                                    padding: 8px 12px;
                                    border-radius: 4px;
                                    border: 1px solid #e2e8f0;
                                    display: inline-block;
                                }
                                .password-warning {
                                    background: #fef3c7;
                                    padding: 12px 15px;
                                    border-radius: 6px;
                                    margin: 15px 0;
                                    font-size: 14px;
                                    color: #92400e;
                                    border-left: 3px solid #f59e0b;
                                }
                                .button {
                                    display: inline-block;
                                    background: #667eea;
                                    color: white;
                                    padding: 14px 30px;
                                    text-decoration: none;
                                    border-radius: 8px;
                                    font-weight: 600;
                                    font-size: 16px;
                                    margin: 20px 0 10px;
                                    transition: background 0.2s ease;
                                }
                                .button:hover {
                                    background: #5a67d8;
                                }
                                .footer {
                                    padding: 20px 30px;
                                    border-top: 1px solid #e2e8f0;
                                    text-align: center;
                                    color: #94a3b8;
                                    font-size: 14px;
                                }
                                .footer p {
                                    margin: 5px 0;
                                }
                                .note {
                                    font-size: 13px;
                                    color: #64748b;
                                    margin-top: 20px;
                                    padding-top: 15px;
                                    border-top: 1px solid #e2e8f0;
                                }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>Welcome to " . htmlspecialchars($companyName) . "!</h1>
                                    <p>Your client portal has been created</p>
                                </div>
                                
                                <div class='content'>
                                    <p class='greeting'>Dear " . htmlspecialchars($data['contact_person']) . ",</p>
                                    
                                    <p>We're excited to welcome you to our client portal! Your account has been successfully created and you can now access all our services online.</p>
                                    
                                    <div class='credentials-box'>
                                        <h3>🔐 Your Login Credentials</h3>
                                        
                                        <div class='credential-item'>
                                            <span class='credential-label'>📧 Email Address:</span>
                                            <span class='credential-value'>" . htmlspecialchars($data['email']) . "</span>
                                        </div>
                                        
                                        <div class='credential-item'>
                                            <span class='credential-label'>🔑 Temporary Password:</span>
                                            <span class='credential-value'>" . $plainPassword . "</span>
                                        </div>
                                        
                                        <div class='password-warning'>
                                            <strong>⚠️ Important:</strong> Please change your password after your first login for security reasons.
                                        </div>
                                    </div>
                                    
                                    <div style='text-align: center;'>
                                        <a href='" . (defined('BASE_URL') ? BASE_URL : '') . "/client/login.php' class='button'>🚀 Access Your Portal</a>
                                    </div>
                                    
                                    <div class='note'>
                                        <p><strong>What you can do in the portal:</strong></p>
                                        <ul style='color: #475569; margin-top: 5px;'>
                                            <li>View your projects and their status</li>
                                            <li>Communicate with our team</li>
                                            <li>Access invoices and documents</li>
                                            <li>Track progress in real-time</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class='footer'>
                                    <p>&copy; " . date('Y') . " " . htmlspecialchars($companyName) . ". All rights reserved.</p>
                                    <p>If you didn't request this account, please contact us immediately.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        // Build plain text version
                        $textBody = "Dear " . $data['contact_person'] . ",\n\n";
                        $textBody .= "Welcome to " . $companyName . "! Your client portal has been created.\n\n";
                        $textBody .= "LOGIN CREDENTIALS:\n";
                        $textBody .= "Email: " . $data['email'] . "\n";
                        $textBody .= "Password: " . $plainPassword . "\n\n";
                        $textBody .= "IMPORTANT: Please change your password after first login.\n\n";
                        $textBody .= "Login URL: " . (defined('BASE_URL') ? BASE_URL : '') . "/client/login.php\n\n";
                        $textBody .= "What you can do:\n";
                        $textBody .= "- View your projects and their status\n";
                        $textBody .= "- Communicate with our team\n";
                        $textBody .= "- Access invoices and documents\n";
                        $textBody .= "- Track progress in real-time\n\n";
                        $textBody .= "Best regards,\n";
                        $textBody .= $companyName . " Team";
                        
                        // Set reply-to options
                        $replyTo = [
                            'email' => function_exists('getSetting') ? getSetting('contact_email', '') : '',
                            'name' => $companyName . ' Support'
                        ];
                        
                        // Send email using Mailer class
                        try {
                            $mailSent = mailer()->sendHTML(
                                $data['email'],                    // to
                                $subject,                           // subject
                                $htmlBody,                          // HTML body
                                $textBody,                          // plain text body
                                [
                                    'reply_to' => $replyTo,        // reply-to address
                                    'is_html' => true               // HTML format
                                ]
                            );
                            
                            if ($mailSent) {
                                // Log success
                                error_log("Welcome email sent successfully to: " . $data['email']);
                            } else {
                                error_log("Failed to send welcome email to: " . $data['email']);
                            }
                        } catch (Exception $e) {
                            error_log("Welcome email error: " . $e->getMessage());
                        }
                        
                        // Optional: Send a copy to admin
                        if (function_exists('getSetting')) {
                            $adminEmail = getSetting('admin_email', '');
                            if (!empty($adminEmail)) {
                                try {
                                    mailer()->sendHTML(
                                        $adminEmail,
                                        'New Client Created: ' . $data['company_name'],
                                        "<p>A new client has been created:</p>
                                         <ul>
                                             <li><strong>Company:</strong> " . htmlspecialchars($data['company_name']) . "</li>
                                             <li><strong>Contact:</strong> " . htmlspecialchars($data['contact_person']) . "</li>
                                             <li><strong>Email:</strong> " . htmlspecialchars($data['email']) . "</li>
                                             <li><strong>Portal Login:</strong> Created</li>
                                         </ul>",
                                        "New Client Created:\nCompany: " . $data['company_name'] . "\nContact: " . $data['contact_person'] . "\nEmail: " . $data['email'] . "\nPortal Login: Created"
                                    );
                                } catch (Exception $e) {
                                    error_log("Admin notification email error: " . $e->getMessage());
                                }
                            }
                        }
                    }
                }
                $redirect = true;
            }
        }
        break;
}

// ───────────── REDIRECT AFTER ACTION ─────────────
if ($redirect) {
    $query = $successMsgKey ? "?msg=$successMsgKey" : '';
    if (isset($clientId) && $successMsgKey === 'created') {
        $query .= "&id=$clientId&action=view";
    }
    header("Location: clients.php$query");
    exit;
}

// ───────────── LOAD CLIENT DATA ─────────────
$client = null;
if ($id > 0 && in_array($action, ['view','edit'])) {
    $client = db()->fetch("SELECT * FROM clients WHERE id = ?", [$id]);
}

// ───────────── LOAD CLIENT PORTAL USERS ─────────────
$portalUsers = [];
if ($id > 0) {
    $portalUsers = db()->fetchAll("
        SELECT * FROM client_users 
        WHERE client_id = ? 
        ORDER BY created_at DESC
    ", [$id]);
}

// ───────────── LOAD AVAILABLE PROJECTS ─────────────
$availableProjects = [];
if ($id > 0) {
    $availableProjects = db()->fetchAll("
        SELECT * FROM projects 
        WHERE client_id IS NULL OR client_id = ?
        ORDER BY created_at DESC
    ", [$id]);
}

// ───────────── LOAD CLIENT PROJECTS ─────────────
$clientProjects = [];
if ($id > 0) {
    $clientProjects = db()->fetchAll("
        SELECT * FROM projects 
        WHERE client_id = ?
        ORDER BY created_at DESC
    ", [$id]);
}

// ───────────── LOAD ALL CLIENTS ─────────────
$allClients = db()->fetchAll("
    SELECT c.*,
           COUNT(p.id) AS project_count,
           SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_projects,
           (SELECT COUNT(*) FROM client_users WHERE client_id = c.id) AS portal_users
    FROM clients c
    LEFT JOIN projects p ON p.client_id = c.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");

// ───────────── CLIENT STATISTICS ─────────────
$stats = db()->fetch("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'active'   THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
        SUM(CASE WHEN status = 'lead'     THEN 1 ELSE 0 END) AS leads
    FROM clients
") ?: ['total'=>0,'active'=>0,'inactive'=>0,'leads'=>0];

// ───────────── HEADER ─────────────
require_once 'includes/header.php';
?>

<!-- Rest of your HTML content remains the same -->

<!-- Modern Admin Dashboard Styles -->
<style>
:root {
    --primary: #4361ee;
    --secondary: #6c757d;
    --success: #2ecc71;
    --danger: #e74c3c;
    --warning: #f39c12;
    --info: #3498db;
    --dark: #2c3e50;
    --light: #f8f9fa;
    --white: #ffffff;
    --sidebar-width: 250px;
    --header-height: 70px;
    --border-radius: 12px;
    --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background: #f0f2f5;
    color: var(--dark);
    line-height: 1.6;
}

/* Layout */
.admin-wrapper {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: var(--sidebar-width);
    background: linear-gradient(135deg, var(--dark) 0%, #34495e 100%);
    color: var(--white);
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    transition: var(--transition);
    z-index: 1000;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    color: var(--white);
}

.sidebar-menu {
    padding: 1rem 0;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: var(--transition);
    gap: 0.75rem;
}

.sidebar-menu a:hover,
.sidebar-menu a.active {
    background: rgba(255,255,255,0.1);
    color: var(--white);
    border-left: 3px solid var(--primary);
}

.sidebar-menu i {
    width: 20px;
    font-size: 1.1rem;
}

/* Main Content */
.main-content {
    flex: 1;
    margin-left: var(--sidebar-width);
    padding: 2rem;
    transition: var(--transition);
}

/* Header Actions */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.content-header h2 {
    font-size: 1.75rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    background: transparent;
}

.btn i {
    font-size: 1rem;
}

.btn-primary {
    background: var(--primary);
    color: var(--white);
}

.btn-primary:hover {
    background: #3651c9;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.btn-outline {
    border: 1px solid var(--secondary);
    color: var(--secondary);
}

.btn-outline:hover {
    background: var(--secondary);
    color: var(--white);
}

.btn-outline-danger {
    border: 1px solid var(--danger);
    color: var(--danger);
}

.btn-outline-danger:hover {
    background: var(--danger);
    color: var(--white);
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
}

.btn-success {
    background: var(--success);
    color: var(--white);
}

.btn-success:hover {
    background: #27ae60;
}

/* Cards */
.card {
    background: var(--white);
    border-radius: var(--border-radius);
    box-shadow: var(--box-shadow);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    box-shadow: var(--box-shadow);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.stat-card i {
    font-size: 2.5rem;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: var(--white);
}

.stat-card.blue i { background: var(--info); }
.stat-card.green i { background: var(--success); }
.stat-card.orange i { background: var(--warning); }
.stat-card.purple i { background: #9b59b6; }

.stat-card div {
    flex: 1;
}

.stat-card h4 {
    font-size: 0.9rem;
    color: var(--secondary);
    margin-bottom: 0.25rem;
    font-weight: 500;
}

.stat-card span {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--dark);
}

/* Tables */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -1rem;
    padding: 0 1rem;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.admin-table th {
    text-align: left;
    padding: 1rem;
    background: #f8fafc;
    color: var(--secondary);
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.admin-table td {
    padding: 1rem;
    border-bottom: 1px solid #eef2f6;
    color: var(--dark);
}

.admin-table tbody tr:hover {
    background: #f8fafc;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
}

.bg-success { background: #d4edda; color: #155724; }
.bg-secondary { background: #e2e3e5; color: #383d41; }
.bg-info { background: #d1ecf1; color: #0c5460; }
.bg-warning { background: #fff3cd; color: #856404; }

/* Forms */
.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--dark);
    font-size: 0.9rem;
}

.form-control,
.form-select {
    width: 100%;
    padding: 0.625rem 0.75rem;
    border: 1px solid #dde1e6;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: var(--transition);
    background: var(--white);
}

.form-control:focus,
.form-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.form-check {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-check-input {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.form-check-label {
    font-size: 0.95rem;
    color: var(--dark);
    cursor: pointer;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

/* Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    animation: slideIn 0.3s ease;
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

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert ul {
    margin: 0;
    padding-left: 1.5rem;
}

/* Tabs */
.tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #eef2f6;
    padding-bottom: 0.5rem;
}

.tab-btn {
    padding: 0.5rem 1.5rem;
    border: none;
    background: transparent;
    color: var(--secondary);
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    border-radius: 6px 6px 0 0;
}

.tab-btn:hover {
    color: var(--primary);
    background: #f8fafc;
}

.tab-btn.active {
    color: var(--primary);
    border-bottom: 2px solid var(--primary);
    margin-bottom: -0.5rem;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Images */
.rounded-circle {
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--white);
    box-shadow: var(--box-shadow);
}

/* Grid System */
.row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.g-2 { gap: 0.5rem; }
.g-3 { gap: 1rem; }
.mb-3 { margin-bottom: 1rem; }
.mb-4 { margin-bottom: 1.5rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-3 { margin-top: 1rem; }
.p-4 { padding: 1.5rem; }
.p-5 { padding: 3rem; }
.text-center { text-align: center; }
.text-muted { color: var(--secondary); }
.fw-bold { font-weight: 600; }
.d-flex { display: flex; }
.align-items-center { align-items: center; }
.justify-content-end { justify-content: flex-end; }
.justify-content-between { justify-content: space-between; }
.gap-2 { gap: 0.5rem; }

/* Responsive Design */
@media (max-width: 1024px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }
    
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

@media (max-width: 768px) {
    .content-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-card i {
        font-size: 2rem;
        width: 50px;
        height: 50px;
    }
    
    .row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .main-content {
        padding: 0.75rem;
    }
    
    .content-header h2 {
        font-size: 1.5rem;
    }
    
    .card {
        padding: 1rem;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
    
    .stat-card i {
        margin: 0 auto;
    }
    
    .admin-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .btn {
        padding: 0.4rem 0.8rem;
        font-size: 0.85rem;
    }
    
    .d-none {
        display: none;
    }
}

/* Loading State */
.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: var(--white);
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Print Styles */
@media print {
    .sidebar,
    .header-actions,
    .btn {
        display: none !important;
    }
    
    .main-content {
        margin: 0;
        padding: 0;
    }
    
    .card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>


  


    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h2>
                <?= match($action) {
                    'add'   => 'Add New Client',
                    'edit'  => 'Edit Client',
                    'view'  => 'Client Details',
                    'import'=> 'Import Clients',
                    default => 'Client Management'
                } ?>
            </h2>

            <div class="header-actions">
                <?php if ($action === 'list'): ?>
                    <a href="?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> 
                        <span class="d-none d-sm-inline">New Client</span>
                    </a>
                    <a href="?action=import" class="btn btn-outline">
                        <i class="fas fa-upload"></i> 
                        <span class="d-none d-sm-inline">Import</span>
                    </a>
                <?php elseif ($action === 'view' && $client): ?>
                    <a href="?action=edit&id=<?= $client['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="projects.php?client_id=<?= $client['id'] ?>" class="btn btn-outline">
                        <i class="fas fa-folder"></i> Projects
                    </a>
                <?php else: ?>
                    <a href="clients.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($errors): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg'])): $msg = $_GET['msg']; ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= match($msg) {
                'created'      => 'Client created successfully. Welcome email with login credentials has been sent.',
                'updated'      => 'Client updated successfully.',
                'deleted'      => 'Client deleted successfully.',
                'has_projects' => 'Cannot delete — this client has associated projects.',
                'project_assigned' => 'Project assigned successfully.',
                'project_unassigned' => 'Project unassigned successfully.',
                default        => 'Operation completed.'
            } ?>
        </div>
        <?php endif; ?>

        <?php if ($action === 'import'): ?>
        <div class="card p-5 text-center">
            <i class="fas fa-file-import fa-3x text-muted mb-3"></i>
            <h4>Client CSV Import</h4>
            <p class="text-muted mt-3">Feature coming soon.<br>Planned columns: company_name, contact_person, email, phone, website, address, status, source, notes, logo_url (optional)</p>
        </div>

        <?php elseif ($action === 'list'): ?>
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <i class="fas fa-users"></i>
                <div>
                    <h4>Total Clients</h4>
                    <span><?= number_format($stats['total'] ?? 0) ?></span>
                </div>
            </div>

            <div class="stat-card green">
                <i class="fas fa-check-circle"></i>
                <div>
                    <h4>Active</h4>
                    <span><?= number_format($stats['active'] ?? 0) ?></span>
                </div>
            </div>

            <div class="stat-card orange">
                <i class="fas fa-pause-circle"></i>
                <div>
                    <h4>Inactive</h4>
                    <span><?= number_format($stats['inactive'] ?? 0) ?></span>
                </div>
            </div>

            <div class="stat-card purple">
                <i class="fas fa-user-plus"></i>
                <div>
                    <h4>Leads</h4>
                    <span><?= number_format($stats['leads'] ?? 0) ?></span>
                </div>
            </div>
        </div>

        <!-- Clients Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Projects</th>
                            <th>Portal Users</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allClients as $c): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($c['logo']): ?>
                                    <img src="<?= UPLOAD_URL ?>clients/<?= htmlspecialchars($c['logo']) ?>" 
                                         class="rounded-circle" 
                                         width="40" 
                                         height="40" 
                                         alt="<?= htmlspecialchars($c['company_name']) ?>"
                                         loading="lazy">
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($c['company_name']) ?></div>
                                        <?php if ($c['website']): ?>
                                        <small>
                                            <a href="<?= htmlspecialchars($c['website']) ?>" target="_blank" class="text-muted">
                                                <?= parse_url($c['website'], PHP_URL_HOST) ?>
                                            </a>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($c['contact_person']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($c['email']) ?></small>
                            </td>
                            <td>
                                <div><?= $c['project_count'] ?? 0 ?></div>
                                <small class="text-muted"><?= $c['completed_projects'] ?? 0 ?> completed</small>
                            </td>
                            <td>
                                <span class="badge bg-<?= ($c['portal_users'] ?? 0) > 0 ? 'success' : 'secondary' ?>">
                                    <?= $c['portal_users'] ?? 0 ?> user(s)
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= match($c['status']) { 
                                    'active'=>'success', 
                                    'inactive'=>'secondary', 
                                    default=>'info' 
                                } ?>">
                                    <?= ucfirst($c['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="?action=view&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?toggle=<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="Toggle Status">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                    <a href="?delete=<?= $c['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Delete this client? This will also delete their portal access. This action cannot be undone.')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($allClients)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No clients yet. Click "New Client" to add one.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($action === 'view' && $client): ?>
            <!-- Client Detail View with Tabs -->
            <div class="card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($client['logo']): ?>
                        <img src="<?= UPLOAD_URL ?>clients/<?= htmlspecialchars($client['logo']) ?>" 
                             class="rounded-circle" 
                             width="60" 
                             height="60" 
                             alt="<?= htmlspecialchars($client['company_name']) ?>">
                        <?php endif; ?>
                        <div>
                            <h3 class="mb-1"><?= htmlspecialchars($client['company_name']) ?></h3>
                            <span class="badge bg-<?= match($client['status']) { 
                                'active'=>'success', 
                                'inactive'=>'secondary', 
                                default=>'info' 
                            } ?>">
                                <?= ucfirst($client['status']) ?>
                            </span>
                        </div>
                    </div>
                    <a href="?action=edit&id=<?= $client['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Client
                    </a>
                </div>

                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab-btn active" onclick="showTab('overview')">Overview</button>
                    <button class="tab-btn" onclick="showTab('projects')">Projects (<?= count($clientProjects) ?>)</button>
                    <button class="tab-btn" onclick="showTab('portal')">Portal Users (<?= count($portalUsers) ?>)</button>
                    <button class="tab-btn" onclick="showTab('assign')">Assign Projects</button>
                </div>

                <!-- Overview Tab -->
                <div id="tab-overview" class="tab-content active">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Contact Information</h5>
                            <table class="table">
                                <tr>
                                    <th style="width: 120px;">Contact Person:</th>
                                    <td><?= htmlspecialchars($client['contact_person']) ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><a href="mailto:<?= htmlspecialchars($client['email']) ?>"><?= htmlspecialchars($client['email']) ?></a></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?= htmlspecialchars($client['phone'] ?: 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th>Website:</th>
                                    <td><?= $client['website'] ? '<a href="'.htmlspecialchars($client['website']).'" target="_blank">'.parse_url($client['website'], PHP_URL_HOST).'</a>' : 'N/A' ?></td>
                                </tr>
                                <tr>
                                    <th>Address:</th>
                                    <td><?= nl2br(htmlspecialchars($client['address'] ?: 'N/A')) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3">Additional Information</h5>
                            <table class="table">
                                <tr>
                                    <th style="width: 120px;">Source:</th>
                                    <td><?= htmlspecialchars($client['source'] ?: 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th>Created:</th>
                                    <td><?= date('F j, Y \a\t g:i A', strtotime($client['created_at'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Last Updated:</th>
                                    <td><?= isset($client['updated_at']) ? date('F j, Y', strtotime($client['updated_at'])) : 'N/A' ?></td>
                                </tr>
                            </table>
                            
                            <?php if ($client['notes']): ?>
                            <h5 class="mb-3 mt-4">Notes</h5>
                            <div class="p-3 bg-light rounded">
                                <?= nl2br(htmlspecialchars($client['notes'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Projects Tab -->
                <div id="tab-projects" class="tab-content">
                    <h5 class="mb-3">Assigned Projects</h5>
                    <?php if (empty($clientProjects)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No projects assigned yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Status</th>
                                        <th>Budget</th>
                                        <th>Timeline</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientProjects as $project): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($project['title']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($project['short_description'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= match($project['status'] ?? 'draft') {
                                                'published' => 'success',
                                                'draft' => 'secondary',
                                                'in_progress' => 'info',
                                                'completed' => 'success',
                                                default => 'secondary'
                                            } ?>">
                                                <?= ucfirst($project['status'] ?? 'draft') ?>
                                            </span>
                                        </td>
                                        <td>$<?= number_format($project['budget'] ?? 0, 2) ?></td>
                                        <td>
                                            <?php if (!empty($project['start_date'])): ?>
                                                Start: <?= date('M j, Y', strtotime($project['start_date'])) ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($project['end_date'])): ?>
                                                End: <?= date('M j, Y', strtotime($project['end_date'])) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="projects.php?action=view&id=<?= $project['id'] ?>" class="btn btn-sm btn-outline" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?unassign=<?= $project['id'] ?>&cid=<?= $client['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   onclick="return confirm('Unassign this project from the client?')"
                                                   title="Unassign">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Portal Users Tab -->
                <div id="tab-portal" class="tab-content">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5>Portal Users</h5>
                        <a href="client-users.php?client_id=<?= $client['id'] ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-user-plus"></i> Add User
                        </a>
                    </div>
                    
                    <?php if (empty($portalUsers)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No portal users yet.</p>
                            <p class="text-muted small">When you create a client, a portal user is automatically created if you check "Create client portal account".</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($portalUsers as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= ucfirst($user['role'] ?? 'client') ?></td>
                                        <td>
                                            <span class="badge bg-<?= ($user['is_active'] ?? 1) ? 'success' : 'secondary' ?>">
                                                <?= ($user['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td><?= $user['last_login'] ? date('M j, Y', strtotime($user['last_login'])) : 'Never' ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="client-users.php?action=edit&id=<?= $user['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="client-users.php?action=reset&id=<?= $user['id'] ?>" class="btn btn-sm btn-outline" title="Reset Password">
                                                    <i class="fas fa-key"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Assign Projects Tab -->
                <div id="tab-assign" class="tab-content">
                    <h5 class="mb-3">Assign Available Projects</h5>
                    
                    <?php if (empty($availableProjects)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No available projects to assign.</p>
                            <a href="projects.php?action=add" class="btn btn-primary">Create New Project</a>
                        </div>
                    <?php else: ?>
                        <form method="post" class="mb-4">
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <select name="project_id" class="form-select" required>
                                        <option value="">Select a project to assign...</option>
                                        <?php foreach ($availableProjects as $project): ?>
                                            <?php 
                                            $isAssigned = $project['client_id'] == $client['id'];
                                            $status = $isAssigned ? '(Currently Assigned)' : '';
                                            ?>
                                            <option value="<?= $project['id'] ?>" <?= $isAssigned ? 'disabled' : '' ?>>
                                                <?= htmlspecialchars($project['title']) ?> - $<?= number_format($project['budget'] ?? 0, 2) ?> <?= $status ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="assign_project" class="btn btn-success w-100">
                                        <i class="fas fa-link"></i> Assign Project
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Project</th>
                                        <th>Status</th>
                                        <th>Budget</th>
                                        <th>Assigned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($availableProjects as $project): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($project['title']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= match($project['status'] ?? 'draft') {
                                                'published' => 'success',
                                                'draft' => 'secondary',
                                                default => 'secondary'
                                            } ?>">
                                                <?= ucfirst($project['status'] ?? 'draft') ?>
                                            </span>
                                        </td>
                                        <td>$<?= number_format($project['budget'] ?? 0, 2) ?></td>
                                        <td>
                                            <?php if ($project['client_id'] == $client['id']): ?>
                                                <span class="badge bg-success">Assigned to this client</span>
                                            <?php elseif ($project['client_id']): ?>
                                                <span class="badge bg-warning">Assigned to another client</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif (in_array($action, ['add','edit']) && ($action === 'add' || $client)): ?>
        <form method="post" enctype="multipart/form-data" class="card">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <?php if ($client): ?><input type="hidden" name="id" value="<?= $client['id'] ?>"><?php endif; ?>

            <!-- Company -->
            <div class="mb-4">
                <h5 class="mb-3">Company Information</h5>
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" required 
                               value="<?= htmlspecialchars($client['company_name'] ?? '') ?>"
                               placeholder="Enter company name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" 
                               placeholder="https://example.com" 
                               value="<?= htmlspecialchars($client['website'] ?? '') ?>">
                    </div>
                    <div class="col-12 mt-3">
                        <label class="form-label">Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <?php if ($client && $client['logo']): ?>
                        <div class="mt-2">
                            <img src="<?= UPLOAD_URL ?>clients/<?= htmlspecialchars($client['logo']) ?>" 
                                 alt="Current logo" 
                                 style="max-height:80px; border-radius: 8px;">
                            <small class="text-muted d-block">Current logo (leave empty to keep)</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div class="mb-4">
                <h5 class="mb-3">Primary Contact</h5>
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                        <input type="text" name="contact_person" class="form-control" required 
                               value="<?= htmlspecialchars($client['contact_person'] ?? '') ?>"
                               placeholder="Full name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required 
                               value="<?= htmlspecialchars($client['email'] ?? '') ?>"
                               placeholder="contact@example.com">
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" 
                               value="<?= htmlspecialchars($client['phone'] ?? '') ?>"
                               placeholder="+1 (555) 000-0000">
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" 
                               value="<?= htmlspecialchars($client['address'] ?? '') ?>"
                               placeholder="Street address, city, state, zip">
                    </div>
                </div>
            </div>

            <!-- Additional -->
            <div class="mb-4">
                <h5 class="mb-3">Additional Information</h5>
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="lead" <?= ($client['status']??'')==='lead' ? 'selected' : '' ?>>Lead</option>
                            <option value="active" <?= ($client['status']??'')==='active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($client['status']??'')==='inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Source</label>
                        <input type="text" name="source" class="form-control" 
                               value="<?= htmlspecialchars($client['source'] ?? '') ?>" 
                               placeholder="Referral, Google, Event...">
                    </div>
                    <div class="col-12 mt-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="4" class="form-control" 
                                  placeholder="Additional notes about the client..."><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
                    </div>
                    <?php if ($action === 'add'): ?>
                    <div class="col-12 mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="create_login" id="create_login" checked>
                            <label class="form-check-label" for="create_login">
                                Create client portal account (login credentials will be emailed)
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="save_client" class="btn btn-primary px-4">
                    <i class="fas fa-save me-2"></i>
                    <?= $client ? 'Update Client' : 'Create Client' ?>
                </button>
                <a href="clients.php" class="btn btn-outline px-4">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>


<!-- Mobile Menu Toggle and JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add mobile menu toggle button
    const header = document.querySelector('.content-header');
    const sidebar = document.querySelector('.sidebar');
    
    if (window.innerWidth <= 1024 && header && sidebar) {
        const menuBtn = document.createElement('button');
        menuBtn.className = 'btn btn-outline d-lg-none';
        menuBtn.innerHTML = '<i class="fas fa-bars"></i> Menu';
        menuBtn.style.marginRight = 'auto';
        menuBtn.onclick = function() {
            sidebar.classList.toggle('active');
        };
        header.insertBefore(menuBtn, header.firstChild);
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && sidebar && sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !e.target.closest('.btn')) {
                sidebar.classList.remove('active');
            }
        }
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[onclick*="confirm"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('onclick').match(/'([^']+)'/)[1])) {
                e.preventDefault();
            }
        });
    });
});

// Tab switching
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>