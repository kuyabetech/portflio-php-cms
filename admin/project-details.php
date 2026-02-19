<?php
// admin/project-details.php
// Project Details with Client Portal Integration

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pageTitle = 'Project Details';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Projects', 'url' => 'projects.php'],
    ['title' => 'Project Details']
];

// Get project details
$project = db()->fetch("
    SELECT p.*, c.company_name, c.contact_person, c.email as client_email 
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE p.id = ?
", [$id]);

if (!$project) {
    header('Location: projects.php');
    exit;
}

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $data = [
        'project_id' => $id,
        'title' => sanitize($_POST['title']),
        'description' => sanitize($_POST['description']),
        'priority' => $_POST['priority'],
        'due_date' => $_POST['due_date'],
        'assigned_to' => !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
        'created_by' => $_SESSION['user_id']
    ];
    
    db()->insert('project_tasks', $data);
    logActivity($id, 'Task Created', 'New task: ' . $data['title']);
    header('Location: project-details.php?id=' . $id . '&msg=task_created');
    exit;
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $data = [
        'project_id' => $id,
        'user_id' => $_SESSION['user_id'],
        'message' => sanitize($_POST['message']),
        'is_client_message' => false
    ];
    
    db()->insert('project_messages', $data);
    
    // Notify client if enabled
    if (isset($_POST['notify_client']) && $project['client_email']) {
        sendClientNotification($project['client_email'], 'New message', $data['message']);
    }
    
    logActivity($id, 'Message Sent', 'Message to ' . ($project['client_email'] ?? 'team'));
    header('Location: project-details.php?id=' . $id . '&msg=message_sent');
    exit;
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['project_file'], 'projects/' . $id . '/');
        if (isset($upload['success'])) {
            db()->insert('project_files', [
                'project_id' => $id,
                'uploaded_by' => $_SESSION['user_id'],
                'filename' => $upload['filename'],
                'original_filename' => $_FILES['project_file']['name'],
                'file_size' => $_FILES['project_file']['size'],
                'file_type' => $_FILES['project_file']['type'],
                'category' => $_POST['category'] ?? 'general',
                'description' => sanitize($_POST['file_description']),
                'is_client_visible' => isset($_POST['client_visible']) ? 1 : 0
            ]);
            
            logActivity($id, 'File Uploaded', 'File: ' . $_FILES['project_file']['name']);
            header('Location: project-details.php?id=' . $id . '&msg=file_uploaded');
            exit;
        }
    }
}

// Get tasks
$tasks = db()->fetchAll("
    SELECT t.*, u.username as assigned_to_name 
    FROM project_tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.project_id = ?
    ORDER BY 
        CASE t.status
            WHEN 'pending' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'blocked' THEN 3
            WHEN 'completed' THEN 4
        END,
        t.due_date ASC
", [$id]);

// Get messages
$messages = db()->fetchAll("
    SELECT m.*, u.username as user_name, c.contact_person as client_name
    FROM project_messages m
    LEFT JOIN users u ON m.user_id = u.id
    LEFT JOIN clients c ON m.client_id = c.id
    WHERE m.project_id = ?
    ORDER BY m.created_at DESC
    LIMIT 20
", [$id]);

// Get files
$files = db()->fetchAll("
    SELECT f.*, u.username as uploaded_by_name
    FROM project_files f
    LEFT JOIN users u ON f.uploaded_by = u.id
    WHERE f.project_id = ?
    ORDER BY f.created_at DESC
", [$id]);

// Get milestones
$milestones = db()->fetchAll("
    SELECT * FROM project_milestones 
    WHERE project_id = ? 
    ORDER BY due_date ASC
", [$id]);

// Get team members for assignment
$team = db()->fetchAll("SELECT id, username FROM users WHERE role IN ('admin', 'editor')");

// Include header
require_once 'includes/header.php';
?>

<div class="project-header">
    <div class="header-left">
        <h1><?php echo htmlspecialchars($project['title']); ?></h1>
        <?php if ($project['client_id']): ?>
        <p class="client-info">
            <i class="fas fa-building"></i>
            <?php echo htmlspecialchars($project['company_name'] ?: 'Client'); ?>
        </p>
        <?php endif; ?>
    </div>
    <div class="header-right">
        <span class="status-badge <?php echo $project['status']; ?>">
            <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
        </span>
        <a href="projects.php?action=edit&id=<?php echo $id; ?>" class="btn btn-outline">
            <i class="fas fa-edit"></i>
            Edit Project
        </a>
    </div>
</div>

<!-- Project Progress -->
<div class="project-progress">
    <div class="progress-stats">
        <div class="stat">
            <label>Progress</label>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?php echo calculateProgress($tasks); ?>%"></div>
            </div>
        </div>
        <div class="stat">
            <label>Budget</label>
            <strong>$<?php echo number_format($project['budget'] ?? 0, 2); ?></strong>
        </div>
        <div class="stat">
            <label>Paid</label>
            <strong>$<?php echo number_format($project['paid_amount'] ?? 0, 2); ?></strong>
        </div>
        <div class="stat">
            <label>Deadline</label>
            <strong><?php echo $project['deadline'] ? date('M d, Y', strtotime($project['deadline'])) : 'Not set'; ?></strong>
        </div>
    </div>
</div>

<!-- Project Tabs -->
<div class="project-tabs">
    <button class="tab-btn active" onclick="showTab('overview')">Overview</button>
    <button class="tab-btn" onclick="showTab('tasks')">Tasks</button>
    <button class="tab-btn" onclick="showTab('files')">Files</button>
    <button class="tab-btn" onclick="showTab('messages')">Messages</button>
    <button class="tab-btn" onclick="showTab('invoices')">Invoices</button>
</div>

<!-- Tab Content -->
<div class="tab-content">
    <!-- Overview Tab -->
    <div id="overview" class="tab-pane active">
        <div class="overview-grid">
            <div class="info-card">
                <h3>Project Details</h3>
                <p><?php echo nl2br(htmlspecialchars($project['full_description'] ?: 'No description')); ?></p>
                
                <h4>Technologies</h4>
                <div class="tech-tags">
                    <?php 
                    $techs = explode(',', $project['technologies'] ?? '');
                    foreach ($techs as $tech): 
                        if (trim($tech)):
                    ?>
                    <span class="tech-tag"><?php echo trim($tech); ?></span>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>
            
            <div class="info-card">
                <h3>Milestones</h3>
                <?php if ($milestones): ?>
                <ul class="milestone-list">
                    <?php foreach ($milestones as $milestone): ?>
                    <li class="<?php echo $milestone['status']; ?>">
                        <div class="milestone-header">
                            <strong><?php echo htmlspecialchars($milestone['title']); ?></strong>
                            <span class="status-badge small <?php echo $milestone['status']; ?>">
                                <?php echo ucfirst($milestone['status']); ?>
                            </span>
                        </div>
                        <?php if ($milestone['description']): ?>
                        <p><?php echo htmlspecialchars($milestone['description']); ?></p>
                        <?php endif; ?>
                        <small>Due: <?php echo date('M d, Y', strtotime($milestone['due_date'])); ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="no-data">No milestones set</p>
                <?php endif; ?>
                
                <button class="btn btn-outline btn-sm" onclick="addMilestone()">
                    <i class="fas fa-plus"></i>
                    Add Milestone
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tasks Tab -->
    <div id="tasks" class="tab-pane">
        <div class="tasks-header">
            <h3>Project Tasks</h3>
            <button class="btn btn-primary" onclick="showTaskForm()">
                <i class="fas fa-plus"></i>
                Add Task
            </button>
        </div>
        
        <!-- Task Form (hidden by default) -->
        <div id="taskForm" class="task-form" style="display: none;">
            <form method="POST" class="admin-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="task_title">Task Title *</label>
                        <input type="text" id="task_title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select id="priority" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="task_description">Description</label>
                    <textarea id="task_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="assigned_to">Assign To</label>
                        <select id="assigned_to" name="assigned_to">
                            <option value="">-- Select Team Member --</option>
                            <?php foreach ($team as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" id="due_date" name="due_date">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_task" class="btn btn-primary">Add Task</button>
                    <button type="button" class="btn btn-outline" onclick="hideTaskForm()">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Tasks List -->
        <div class="tasks-board">
            <div class="task-column">
                <h4>To Do</h4>
                <?php foreach ($tasks as $task): if ($task['status'] === 'pending'): ?>
                <div class="task-card priority-<?php echo $task['priority']; ?>">
                    <div class="task-header">
                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                        <span class="priority-badge <?php echo $task['priority']; ?>">
                            <?php echo ucfirst($task['priority']); ?>
                        </span>
                    </div>
                    <?php if ($task['description']): ?>
                    <p><?php echo htmlspecialchars($task['description']); ?></p>
                    <?php endif; ?>
                    <div class="task-footer">
                        <?php if ($task['assigned_to_name']): ?>
                        <span><i class="fas fa-user"></i> <?php echo $task['assigned_to_name']; ?></span>
                        <?php endif; ?>
                        <?php if ($task['due_date']): ?>
                        <span><i class="fas fa-calendar"></i> <?php echo date('M d', strtotime($task['due_date'])); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="task-actions">
                        <button class="action-btn" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'in_progress')">
                            <i class="fas fa-play"></i>
                        </button>
                        <button class="action-btn" onclick="editTask(<?php echo $task['id']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </div>
                <?php endif; endforeach; ?>
            </div>
            
            <div class="task-column">
                <h4>In Progress</h4>
                <?php foreach ($tasks as $task): if ($task['status'] === 'in_progress'): ?>
                <div class="task-card priority-<?php echo $task['priority']; ?>">
                    <div class="task-header">
                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                        <span class="priority-badge <?php echo $task['priority']; ?>">
                            <?php echo ucfirst($task['priority']); ?>
                        </span>
                    </div>
                    <?php if ($task['description']): ?>
                    <p><?php echo htmlspecialchars($task['description']); ?></p>
                    <?php endif; ?>
                    <div class="task-footer">
                        <?php if ($task['assigned_to_name']): ?>
                        <span><i class="fas fa-user"></i> <?php echo $task['assigned_to_name']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="task-actions">
                        <button class="action-btn" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'completed')">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="action-btn" onclick="updateTaskStatus(<?php echo $task['id']; ?>, 'blocked')">
                            <i class="fas fa-ban"></i>
                        </button>
                    </div>
                </div>
                <?php endif; endforeach; ?>
            </div>
            
            <div class="task-column">
                <h4>Completed</h4>
                <?php foreach ($tasks as $task): if ($task['status'] === 'completed'): ?>
                <div class="task-card completed">
                    <div class="task-header">
                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                    </div>
                    <div class="task-footer">
                        <span><i class="fas fa-check-circle"></i> Done</span>
                    </div>
                </div>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Files Tab -->
    <div id="files" class="tab-pane">
        <div class="files-header">
            <h3>Project Files</h3>
            <button class="btn btn-primary" onclick="showFileUploadForm()">
                <i class="fas fa-upload"></i>
                Upload File
            </button>
        </div>
        
        <!-- Upload Form (hidden by default) -->
        <div id="fileUploadForm" class="upload-form" style="display: none;">
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <div class="form-group">
                    <label for="project_file">Select File</label>
                    <input type="file" id="project_file" name="project_file" required>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="general">General</option>
                        <option value="design">Design</option>
                        <option value="development">Development</option>
                        <option value="documentation">Documentation</option>
                        <option value="contracts">Contracts</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="file_description">Description</label>
                    <textarea id="file_description" name="file_description" rows="2"></textarea>
                </div>
                
                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="client_visible" checked>
                        Visible to client
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="upload_file" class="btn btn-primary">Upload</button>
                    <button type="button" class="btn btn-outline" onclick="hideFileUploadForm()">Cancel</button>
                </div>
            </form>
        </div>
        
        <!-- Files List -->
        <div class="files-grid">
            <?php foreach ($files as $file): ?>
            <div class="file-card">
                <div class="file-icon">
                    <i class="fas <?php echo getFileIcon($file['file_type']); ?>"></i>
                </div>
                <div class="file-info">
                    <h4><?php echo htmlspecialchars($file['original_filename']); ?></h4>
                    <p><?php echo htmlspecialchars($file['description'] ?: 'No description'); ?></p>
                    <div class="file-meta">
                        <span><i class="fas fa-user"></i> <?php echo $file['uploaded_by_name']; ?></span>
                        <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($file['created_at'])); ?></span>
                        <span><i class="fas fa-database"></i> <?php echo formatBytes($file['file_size']); ?></span>
                        <?php if ($file['is_client_visible']): ?>
                        <span class="client-badge"><i class="fas fa-eye"></i> Client</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="file-actions">
                    <a href="<?php echo UPLOAD_URL . 'projects/' . $id . '/' . $file['filename']; ?>" 
                       class="action-btn" download title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    <button class="action-btn delete-btn" onclick="deleteFile(<?php echo $file['id']; ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($files)): ?>
            <p class="no-data">No files uploaded yet</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Messages Tab -->
    <div id="messages" class="tab-pane">
        <div class="messages-container">
            <div class="messages-list" id="messagesList">
                <?php foreach ($messages as $message): ?>
                <div class="message <?php echo $message['is_client_message'] ? 'client' : 'team'; ?>">
                    <div class="message-header">
                        <strong>
                            <?php 
                            if ($message['is_client_message']) {
                                echo $message['client_name'] ?: 'Client';
                            } else {
                                echo $message['user_name'] ?: 'Team';
                            }
                            ?>
                        </strong>
                        <span><?php echo timeAgo($message['created_at']); ?></span>
                    </div>
                    <div class="message-body">
                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="message-input">
                <form method="POST" class="message-form">
                    <textarea name="message" placeholder="Type your message..." required></textarea>
                    <div class="message-options">
                        <label class="checkbox">
                            <input type="checkbox" name="notify_client">
                            Notify client via email
                        </label>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Invoices Tab -->
    <div id="invoices" class="tab-pane">
        <div class="invoices-header">
            <h3>Invoices</h3>
            <button class="btn btn-primary" onclick="createInvoice(<?php echo $id; ?>)">
                <i class="fas fa-file-invoice"></i>
                Create Invoice
            </button>
        </div>
        
        <?php
        $invoices = db()->fetchAll("SELECT * FROM project_invoices WHERE project_id = ? ORDER BY created_at DESC", [$id]);
        ?>
        
        <div class="invoices-list">
            <?php if ($invoices): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><strong><?php echo $invoice['invoice_number']; ?></strong></td>
                        <td><?php echo date('M d, Y', strtotime($invoice['created_at'])); ?></td>
                        <td><?php echo $invoice['due_date'] ? date('M d, Y', strtotime($invoice['due_date'])) : '-'; ?></td>
                        <td>$<?php echo number_format($invoice['total'], 2); ?></td>
                        <td>
                            <span class="status-badge <?php echo $invoice['status']; ?>">
                                <?php echo ucfirst($invoice['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="action-btn">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="download-invoice.php?id=<?php echo $invoice['id']; ?>" class="action-btn">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="no-data">No invoices created yet</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.project-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-left h1 {
    font-size: 2rem;
    margin-bottom: 5px;
}

.client-info {
    color: var(--gray-600);
}

.client-info i {
    margin-right: 5px;
}

/* Project Progress */
.project-progress {
    background: white;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.progress-stats {
    display: flex;
    gap: 30px;
    align-items: center;
    flex-wrap: wrap;
}

.stat {
    flex: 1;
    min-width: 150px;
}

.stat label {
    display: block;
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-bottom: 5px;
}

.stat strong {
    font-size: 1.2rem;
    color: var(--dark);
}

.progress-bar-container {
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
    margin-top: 5px;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* Tabs */
.project-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--gray-200);
    padding-bottom: 10px;
}

.tab-btn {
    padding: 10px 20px;
    background: none;
    border: none;
    cursor: pointer;
    font-weight: 500;
    color: var(--gray-600);
    border-radius: 6px;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    background: var(--gray-100);
    color: var(--primary);
}

.tab-btn.active {
    background: var(--primary);
    color: white;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Overview Tab */
.overview-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.info-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.info-card h3 {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-200);
}

.info-card h4 {
    margin: 15px 0 10px;
    font-size: 1rem;
}

/* Milestones */
.milestone-list {
    list-style: none;
    margin-bottom: 15px;
}

.milestone-list li {
    padding: 10px;
    margin-bottom: 10px;
    background: var(--gray-100);
    border-radius: 8px;
    border-left: 3px solid transparent;
}

.milestone-list li.completed {
    border-left-color: var(--success);
}

.milestone-list li.delayed {
    border-left-color: var(--danger);
}

.milestone-list li.pending {
    border-left-color: var(--warning);
}

.milestone-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

/* Tasks Tab */
.tasks-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.tasks-board {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.task-column {
    background: var(--gray-100);
    border-radius: 10px;
    padding: 15px;
}

.task-column h4 {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-300);
}

.task-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    position: relative;
}

.task-card.priority-urgent { border-left: 3px solid var(--danger); }
.task-card.priority-high { border-left: 3px solid var(--warning); }
.task-card.priority-medium { border-left: 3px solid var(--info); }
.task-card.priority-low { border-left: 3px solid var(--success); }
.task-card.completed { opacity: 0.7; }

.task-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.priority-badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 4px;
}

.priority-badge.urgent { background: rgba(239,68,68,0.1); color: #ef4444; }
.priority-badge.high { background: rgba(245,158,11,0.1); color: #f59e0b; }
.priority-badge.medium { background: rgba(59,130,246,0.1); color: #3b82f6; }
.priority-badge.low { background: rgba(16,185,129,0.1); color: #10b981; }

.task-footer {
    display: flex;
    gap: 10px;
    font-size: 0.8rem;
    color: var(--gray-600);
    margin-top: 10px;
}

.task-actions {
    position: absolute;
    top: 10px;
    right: 10px;
    display: none;
    gap: 5px;
}

.task-card:hover .task-actions {
    display: flex;
}

/* Files Tab */
.files-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.files-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

.file-card {
    background: white;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.file-icon {
    font-size: 2rem;
    color: var(--primary);
}

.file-info {
    flex: 1;
}

.file-info h4 {
    font-size: 0.95rem;
    margin-bottom: 5px;
}

.file-info p {
    font-size: 0.85rem;
    color: var(--gray-600);
    margin-bottom: 5px;
}

.file-meta {
    display: flex;
    gap: 10px;
    font-size: 0.7rem;
    color: var(--gray-500);
}

.client-badge {
    background: rgba(16,185,129,0.1);
    color: #10b981;
    padding: 2px 6px;
    border-radius: 4px;
}

.file-actions {
    display: flex;
    gap: 5px;
    align-items: start;
}

/* Messages Tab */
.messages-container {
    background: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.messages-list {
    height: 400px;
    overflow-y: auto;
    padding: 20px;
    background: var(--gray-100);
}

.message {
    margin-bottom: 15px;
    max-width: 70%;
}

.message.client {
    margin-left: auto;
}

.message.team {
    margin-right: auto;
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
    font-size: 0.85rem;
    color: var(--gray-600);
}

.message-body {
    padding: 10px 15px;
    border-radius: 10px;
    line-height: 1.5;
}

.message.team .message-body {
    background: var(--primary);
    color: white;
}

.message.client .message-body {
    background: white;
    border: 1px solid var(--gray-300);
}

.message-input {
    padding: 20px;
    border-top: 2px solid var(--gray-200);
}

.message-form textarea {
    width: 100%;
    padding: 10px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    margin-bottom: 10px;
    resize: vertical;
}

.message-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Invoices Tab */
.invoices-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

/* Utility Classes */
.no-data {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
    background: var(--gray-100);
    border-radius: 8px;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

/* Responsive */
@media (max-width: 1024px) {
    .tasks-board {
        grid-template-columns: 1fr;
    }
    
    .overview-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .project-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .progress-stats {
        flex-direction: column;
    }
    
    .project-tabs {
        flex-wrap: wrap;
    }
}
</style>

<script>
let currentTab = 'overview';

function showTab(tabId) {
    // Update tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Update content
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');
    
    currentTab = tabId;
}

function showTaskForm() {
    document.getElementById('taskForm').style.display = 'block';
}

function hideTaskForm() {
    document.getElementById('taskForm').style.display = 'none';
}

function showFileUploadForm() {
    document.getElementById('fileUploadForm').style.display = 'block';
}

function hideFileUploadForm() {
    document.getElementById('fileUploadForm').style.display = 'none';
}

function updateTaskStatus(taskId, status) {
    fetch('ajax/update-task.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            task_id: taskId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

function deleteFile(fileId) {
    if (confirm('Delete this file?')) {
        fetch('ajax/delete-file.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                file_id: fileId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

function addMilestone() {
    // Implementation for adding milestone
}

function createInvoice(projectId) {
    window.location.href = 'create-invoice.php?project_id=' + projectId;
}

// Auto-refresh messages every 30 seconds
setInterval(() => {
    if (currentTab === 'messages') {
        location.reload();
    }
}, 30000);

// Scroll to bottom of messages
function scrollToBottom() {
    const messagesList = document.getElementById('messagesList');
    if (messagesList) {
        messagesList.scrollTop = messagesList.scrollHeight;
    }
}

scrollToBottom();
</script>

<?php
// Helper functions
function calculateProgress($tasks) {
    $total = count($tasks);
    if ($total === 0) return 0;
    
    $completed = 0;
    foreach ($tasks as $task) {
        if ($task['status'] === 'completed') $completed++;
    }
    return ($completed / $total) * 100;
}

function getFileIcon($mimeType) {
    if (strpos($mimeType, 'image/') === 0) return 'fa-file-image';
    if (strpos($mimeType, 'video/') === 0) return 'fa-file-video';
    if (strpos($mimeType, 'audio/') === 0) return 'fa-file-audio';
    if (strpos($mimeType, 'pdf') !== false) return 'fa-file-pdf';
    if (strpos($mimeType, 'word') !== false) return 'fa-file-word';
    if (strpos($mimeType, 'excel') !== false) return 'fa-file-excel';
    if (strpos($mimeType, 'zip') !== false) return 'fa-file-archive';
    return 'fa-file';
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function logActivity($projectId, $action, $details) {
    db()->insert('project_activity_log', [
        'project_id' => $projectId,
        'user_id' => $_SESSION['user_id'],
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}

function sendClientNotification($email, $subject, $message) {
    $headers = "From: " . SITE_NAME . " <" . getSetting('contact_email') . ">\r\n";
    mail($email, $subject, $message, $headers);
}

// Include footer
require_once 'includes/footer.php';
?>