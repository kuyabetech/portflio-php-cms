function sendWelcomeEmail($email, $name, $password) {
    try {
        // Get email template or use default
        $subject = "Welcome to " . SITE_NAME . " Client Portal";
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #2563eb, #7c3aed); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; background: #f9f9f9; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #999; }
                .button { display: inline-block; padding: 12px 24px; background: #2563eb; color: white; text-decoration: none; border-radius: 4px; }
                .credentials { background: #e8f4fd; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .credentials code { background: #d4e6f1; padding: 3px 6px; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to " . SITE_NAME . "!</h1>
                </div>
                <div class='content'>
                    <p>Hello " . htmlspecialchars($name) . ",</p>
                    <p>Your client portal account has been created successfully. You can now log in to track your projects, communicate with us, and manage invoices.</p>
                    
                    <div class='credentials'>
                        <h3>Your Login Credentials:</h3>
                        <p><strong>Portal URL:</strong> <a href='" . BASE_URL . "/client'>" . BASE_URL . "/client</a></p>
                        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                        <p><strong>Password:</strong> <code>" . $password . "</code></p>
                        <p><small>Please change your password after first login for security.</small></p>
                    </div>
                    
                    <p>You can now:</p>
                    <ul>
                        <li>View your projects and their progress</li>
                        <li>Access project files and documents</li>
                        <li>Communicate with our team</li>
                        <li>View and pay invoices online</li>
                    </ul>
                    
                    <p style='text-align: center;'>
                        <a href='" . BASE_URL . "/client' class='button'>Access Your Portal</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $textBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $body));
        
        // Send using PHPMailer
        return mailer()->sendHTML($email, $subject, $body, $textBody);
        
    } catch (Exception $e) {
        error_log("Failed to send welcome email: " . $e->getMessage());
        return false;
    }
}






<?php
// admin/clients.php
// Client Management - FULLY RESPONSIVE & SECURE

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Client Management';
$action   = $_GET['action'] ?? 'list';
$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// ────────────────────────────────────────────────
// Helper Functions
// ────────────────────────────────────────────────

function truncateEmail(string $email, int $length = 20): string
{
    return strlen($email) <= $length ? $email : substr($email, 0, $length - 3) . '...';
}

function generateRandomPassword(int $length = 12): string
{
    \( chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@# \)%^&*()_+-=[]{}|';
    return substr(str_shuffle(str_repeat($chars, 3)), 0, $length);
}

// ────────────────────────────────────────────────
// Handle Actions (GET)
// ────────────────────────────────────────────────

if (isset($_GET['delete']) && $id = (int)$_GET['delete']) {
    $projectsCount = db()->fetchColumn("SELECT COUNT(*) FROM projects WHERE client_id = ?", [$id]);

    if ($projectsCount > 0) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Cannot delete client — has existing projects.'];
        header('Location: clients.php');
        exit;
    }

    // Delete logo if exists
    $client = db()->fetch("SELECT logo FROM clients WHERE id = ?", [$id]);
    if ($client && $client['logo']) {
        $logoPath = UPLOAD_PATH_CLIENTS . $client['logo'];
        if (file_exists($logoPath) && is_file($logoPath)) {
            @unlink($logoPath);
        }
    }

    $deleted = db()->delete('clients', 'id = ?', [$id]);

    $_SESSION['flash'] = [
        'type'    => $deleted ? 'success' : 'error',
        'message' => $deleted ? 'Client deleted successfully' : 'Failed to delete client'
    ];
    header('Location: clients.php');
    exit;
}

if (isset($_GET['toggle']) && $id = (int)$_GET['toggle']) {
    $client = db()->fetch("SELECT status FROM clients WHERE id = ?", [$id]);
    if ($client) {
        $newStatus = $client['status'] === 'active' ? 'inactive' : 'active';
        db()->update('clients', ['status' => $newStatus], 'id = ?', [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Client status updated'];
    }
    header('Location: clients.php');
    exit;
}

if (isset($_GET['export'])) {
    exportClients();
    exit;
}

function exportClients(): never
{
    $clients = db()->fetchAll("
        SELECT c.*, 
               COUNT(p.id) AS project_count,
               COALESCE(SUM(p.budget), 0) AS total_budget
        FROM clients c
        LEFT JOIN projects p ON c.id = p.client_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clients-export-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID','Company','Contact Person','Email','Phone','Website','Status','Source','Projects','Total Budget','Created At']);

    foreach ($clients as $row) {
        fputcsv($output, [
            $row['id'],
            $row['company_name'],
            $row['contact_person'],
            $row['email'],
            $row['phone'],
            $row['website'],
            $row['status'],
            $row['source'],
            $row['project_count'],
            number_format($row['total_budget'], 2),
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;
}

// ────────────────────────────────────────────────
// Handle Form Submission (POST)
// ────────────────────────────────────────────────

$errors = [];
$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_client'])) {
    // TODO: Add CSRF check here
    // if (!verifyCsrfToken($_POST['csrf_token'])) { die('CSRF validation failed'); }

    $data = [
        'company_name'  => trim($_POST['company_name'] ?? ''),
        'contact_person'=> trim($_POST['contact_person'] ?? ''),
        'email'         => trim(strtolower($_POST['email'] ?? '')),
        'phone'         => trim($_POST['phone'] ?? ''),
        'address'       => trim($_POST['address'] ?? ''),
        'website'       => trim($_POST['website'] ?? ''),
        'status'        => in_array($_POST['status'] ?? '', ['lead','active','inactive']) 
                            ? $_POST['status'] : 'lead',
        'source'        => trim($_POST['source'] ?? ''),
        'notes'         => trim($_POST['notes'] ?? '')
    ];

    // Validation
    if (empty($data['company_name']))   $errors[] = 'Company name is required';
    if (empty($data['contact_person'])) $errors[] = 'Contact person is required';
    if (empty($data['email']))          $errors[] = 'Email is required';
    elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }

    if ($errors) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => implode('<br>', $errors)];
        $_SESSION['old_input'] = $data;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Handle logo upload
    $logoUploaded = false;
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['logo'], 'clients/', ['jpg','jpeg','png','gif','webp'], 2 * 1024 * 1024); // 2MB max example

        if (isset($uploadResult['success'])) {
            $data['logo'] = $uploadResult['filename'];
            $logoUploaded = true;

            // Delete old logo when updating
            if (!empty($_POST['id'])) {
                $oldLogo = db()->fetchColumn("SELECT logo FROM clients WHERE id = ?", [$_POST['id']]);
                if ($oldLogo) {
                    $oldPath = UPLOAD_PATH_CLIENTS . $oldLogo;
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
            }
        } else {
            $errors[] = $uploadResult['error'] ?? 'Logo upload failed';
        }
    }

    if ($errors) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => implode('<br>', $errors)];
        $_SESSION['old_input'] = $data;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (!empty($_POST['id'])) {
        // Update
        $updated = db()->update('clients', $data, 'id = ?', [(int)$_POST['id']]);
        $_SESSION['flash'] = [
            'type'    => $updated ? 'success' : 'error',
            'message' => $updated ? 'Client updated successfully' : 'Failed to update client'
        ];
    } else {
        // Create
        $clientId = db()->insert('clients', $data + ['created_at' => date('Y-m-d H:i:s')]);

        if ($clientId && !empty($_POST['create_login'])) {
            $password = generateRandomPassword();
            $nameParts = preg_split('/\s+/', trim($data['contact_person']), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName  = $nameParts[1] ?? '';

            $userData = [
                'client_id'     => $clientId,
                'email'         => $data['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'role'          => 'primary',
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s')
            ];

            $userId = db()->insert('client_users', $userData);

            if ($userId) {
                sendWelcomeEmail($data['email'], $data['contact_person'], $password);
            } else {
                error_log("Failed to create client user account for client #$clientId");
            }
        }

        $_SESSION['flash'] = [
            'type'    => $clientId ? 'success' : 'error',
            'message' => $clientId ? 'Client created successfully' : 'Failed to create client'
        ];
    }

    header('Location: clients.php');
    exit;
}

// ────────────────────────────────────────────────
// Load data for display
// ────────────────────────────────────────────────

$client = null;
if ($id > 0 && in_array($action, ['edit', 'view'])) {
    $client = db()->fetch("SELECT * FROM clients WHERE id = ?", [$id]);
    if (!$client) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Client not found'];
        header('Location: clients.php');
        exit;
    }
}

$clients = db()->fetchAll("
    SELECT 
        c.*,
        COUNT(p.id) AS project_count,
        SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END) AS completed_projects,
        COALESCE(SUM(p.budget), 0) AS total_budget,
        COALESCE(SUM(p.paid_amount), 0) AS total_paid
    FROM clients c
    LEFT JOIN projects p ON c.id = p.client_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
") ?: [];

$stats = db()->fetch("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'active'   THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
        SUM(CASE WHEN status = 'lead'     THEN 1 ELSE 0 END) AS leads
    FROM clients
") ?: ['total' => 0, 'active' => 0, 'inactive' => 0, 'leads' => 0];

// ────────────────────────────────────────────────
// Render
// ────────────────────────────────────────────────

require_once 'includes/header.php';
?>
