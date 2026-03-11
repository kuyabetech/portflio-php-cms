<?php
/**
 * Client Documents - View and download project documents
 */

require_once dirname(__DIR__) . '/includes/init.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if client is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit;
}

$clientId = $_SESSION['client_id'];

// Get client information
$client = db()->fetch("SELECT * FROM client_users WHERE id = ?", [$clientId]);

// Handle file download
if (isset($_GET['download']) && isset($_GET['id'])) {
    $documentId = (int)$_GET['id'];
    
    $document = db()->fetch("
        SELECT d.*, p.title as project_title 
        FROM client_documents d
        LEFT JOIN projects p ON d.project_id = p.id
        WHERE d.id = ? AND d.client_id = ?
    ", [$documentId, $clientId]);
    
    if ($document) {
        $filePath = UPLOAD_PATH . 'documents/' . $document['filename'];
        
        if (file_exists($filePath)) {
            // Update download count
            db()->update('client_documents', [
                'download_count' => $document['download_count'] + 1,
                'last_downloaded_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$documentId]);
            
            // Log activity
            logClientActivity($clientId, 'document_download', 'Downloaded document: ' . $document['title']);
            
            // Send file to browser
            header('Content-Type: ' . $document['file_type']);
            header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            readfile($filePath);
            exit;
        } else {
            $error = 'File not found';
        }
    } else {
        $error = 'Document not found';
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    
    if (empty($title)) {
        $error = 'Please enter a document title';
    } elseif (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a file to upload';
    } else {
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/gif',
            'text/plain'
        ];
        
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($_FILES['document']['type'], $allowedTypes)) {
            $error = 'File type not allowed. Please upload PDF, Word, Excel, images, or text files.';
        } elseif ($_FILES['document']['size'] > $maxSize) {
            $error = 'File size must be less than 10MB';
        } else {
            $originalFilename = $_FILES['document']['name'];
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $filename = 'doc_' . $clientId . '_' . time() . '_' . uniqid() . '.' . $extension;
            $uploadPath = UPLOAD_PATH . 'documents/' . $filename;
            
            // Create directory if it doesn't exist
            if (!is_dir(UPLOAD_PATH . 'documents/')) {
                mkdir(UPLOAD_PATH . 'documents/', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $uploadPath)) {
                try {
                    db()->insert('client_documents', [
                        'client_id' => $clientId,
                        'project_id' => $projectId,
                        'title' => $title,
                        'description' => $description,
                        'filename' => $filename,
                        'original_filename' => $originalFilename,
                        'file_size' => $_FILES['document']['size'],
                        'file_type' => $_FILES['document']['type'],
                        'uploaded_by' => $clientId,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $success = 'Document uploaded successfully';
                    
                    // Log activity
                    logClientActivity($clientId, 'document_upload', 'Uploaded document: ' . $title);
                    
                } catch (Exception $e) {
                    error_log("Document upload error: " . $e->getMessage());
                    $error = 'Failed to save document information';
                    
                    // Delete uploaded file
                    if (file_exists($uploadPath)) {
                        unlink($uploadPath);
                    }
                }
            } else {
                $error = 'Failed to upload file';
            }
        }
    }
}

// Handle document deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $documentId = (int)$_GET['id'];
    
    $document = db()->fetch("SELECT * FROM client_documents WHERE id = ? AND client_id = ?", [$documentId, $clientId]);
    
    if ($document) {
        $filePath = UPLOAD_PATH . 'documents/' . $document['filename'];
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        db()->delete('client_documents', 'id = ?', [$documentId]);
        
        // Log activity
        logClientActivity($clientId, 'document_delete', 'Deleted document: ' . $document['title']);
        
        $success = 'Document deleted successfully';
    }
    
    header('Location: documents.php');
    exit;
}

// Get filter parameters
$projectFilter = isset($_GET['project']) ? (int)$_GET['project'] : 0;
$type = $_GET['type'] ?? 'all';

// Build query
$where = ["client_id = ?"];
$params = [$clientId];

if ($projectFilter > 0) {
    $where[] = "project_id = ?";
    $params[] = $projectFilter;
}

if ($type !== 'all') {
    switch ($type) {
        case 'pdf':
            $where[] = "file_type LIKE '%pdf%'";
            break;
        case 'image':
            $where[] = "file_type LIKE 'image%'";
            break;
        case 'document':
            $where[] = "(file_type LIKE '%word%' OR file_type LIKE '%document%')";
            break;
        case 'spreadsheet':
            $where[] = "(file_type LIKE '%excel%' OR file_type LIKE '%spreadsheet%')";
            break;
    }
}

$whereClause = implode(' AND ', $where);

// Get documents
$documents = db()->fetchAll("
    SELECT d.*, p.title as project_title 
    FROM client_documents d
    LEFT JOIN projects p ON d.project_id = p.id
    WHERE $whereClause 
    ORDER BY d.created_at DESC
", $params);

// Get projects for filter
$projects = db()->fetchAll("
    SELECT id, title FROM projects 
    WHERE client_id = ? 
    ORDER BY created_at DESC
", [$clientId]);

// Calculate statistics
$stats = [
    'total' => count($documents),
    'total_size' => 0,
    'pdf_count' => 0,
    'image_count' => 0,
    'total_downloads' => 0
];

foreach ($documents as $doc) {
    $stats['total_size'] += $doc['file_size'];
    $stats['total_downloads'] += $doc['download_count'];
    
    if (strpos($doc['file_type'], 'pdf') !== false) {
        $stats['pdf_count']++;
    } elseif (strpos($doc['file_type'], 'image') !== false) {
        $stats['image_count']++;
    }
}

$pageTitle = 'Documents';
require_once '../includes/client-header.php';
?>

<div class="documents-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1><i class="fas fa-file-alt"></i> Documents</h1>
            <p>Manage and access your project documents</p>
        </div>
        
        <button class="btn-primary" onclick="showUploadForm()">
            <i class="fas fa-cloud-upload-alt"></i> Upload Document
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-file"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['total']; ?></span>
                <span class="stat-label">Total Documents</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-file-pdf"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['pdf_count']; ?></span>
                <span class="stat-label">PDF Files</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-image"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['image_count']; ?></span>
                <span class="stat-label">Images</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-download"></i>
            </div>
            <div class="stat-content">
                <span class="stat-value"><?php echo $stats['total_downloads']; ?></span>
                <span class="stat-label">Total Downloads</span>
            </div>
        </div>
    </div>

    <!-- Upload Form (hidden by default) -->
    <div class="upload-form" id="uploadForm" style="display: none;">
        <div class="form-card">
            <h3><i class="fas fa-cloud-upload-alt"></i> Upload New Document</h3>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Document Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required 
                           placeholder="e.g., Project Proposal, Contract, Design Mockup">
                </div>
                
                <div class="form-group">
                    <label for="project_id">Related Project (Optional)</label>
                    <select id="project_id" name="project_id">
                        <option value="">-- General Document --</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>">
                            <?php echo htmlspecialchars($project['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" 
                              placeholder="Brief description of the document..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="document">File <span class="required">*</span></label>
                    <div class="file-input-wrapper">
                        <input type="file" id="document" name="document" required>
                        <div class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span id="file-name">Choose a file...</span>
                        </div>
                    </div>
                    <p class="help-text">Allowed: PDF, Word, Excel, Images, Text (Max: 10MB)</p>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="hideUploadForm()">
                        Cancel
                    </button>
                    <button type="submit" name="upload_document" class="btn-primary">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <div class="filter-group">
            <label for="project_filter">Project:</label>
            <select id="project_filter" onchange="applyFilter()">
                <option value="0">All Projects</option>
                <?php foreach ($projects as $project): ?>
                <option value="<?php echo $project['id']; ?>" <?php echo $projectFilter == $project['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($project['title']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label for="type_filter">Type:</label>
            <select id="type_filter" onchange="applyFilter()">
                <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                <option value="pdf" <?php echo $type === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                <option value="image" <?php echo $type === 'image' ? 'selected' : ''; ?>>Images</option>
                <option value="document" <?php echo $type === 'document' ? 'selected' : ''; ?>>Documents</option>
                <option value="spreadsheet" <?php echo $type === 'spreadsheet' ? 'selected' : ''; ?>>Spreadsheets</option>
            </select>
        </div>
        
        <div class="filter-group">
            <span class="total-size">
                Total Size: <?php echo formatFileSize($stats['total_size']); ?>
            </span>
        </div>
    </div>

    <!-- Documents Grid -->
    <?php if (!empty($documents)): ?>
    <div class="documents-grid">
        <?php foreach ($documents as $doc): ?>
        <div class="document-card">
            <div class="document-icon">
                <?php
                $icon = 'fa-file';
                if (strpos($doc['file_type'], 'pdf') !== false) {
                    $icon = 'fa-file-pdf';
                } elseif (strpos($doc['file_type'], 'word') !== false || strpos($doc['file_type'], 'document') !== false) {
                    $icon = 'fa-file-word';
                } elseif (strpos($doc['file_type'], 'excel') !== false || strpos($doc['file_type'], 'spreadsheet') !== false) {
                    $icon = 'fa-file-excel';
                } elseif (strpos($doc['file_type'], 'image') !== false) {
                    $icon = 'fa-file-image';
                } elseif (strpos($doc['file_type'], 'text') !== false) {
                    $icon = 'fa-file-alt';
                }
                ?>
                <i class="fas <?php echo $icon; ?>"></i>
            </div>
            
            <div class="document-info">
                <h3 class="document-title">
                    <a href="?download=1&id=<?php echo $doc['id']; ?>">
                        <?php echo htmlspecialchars($doc['title']); ?>
                    </a>
                </h3>
                
                <?php if (!empty($doc['description'])): ?>
                <p class="document-description"><?php echo htmlspecialchars($doc['description']); ?></p>
                <?php endif; ?>
                
                <?php if (!empty($doc['project_title'])): ?>
                <div class="document-project">
                    <i class="fas fa-project-diagram"></i>
                    <?php echo htmlspecialchars($doc['project_title']); ?>
                </div>
                <?php endif; ?>
                
                <div class="document-meta">
                    <span class="meta-item">
                        <i class="fas fa-file"></i>
                        <?php echo formatFileSize($doc['file_size']); ?>
                    </span>
                    <span class="meta-item">
                        <i class="fas fa-download"></i>
                        <?php echo $doc['download_count']; ?> downloads
                    </span>
                    <span class="meta-item">
                        <i class="far fa-calendar"></i>
                        <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="document-actions">
                <a href="?download=1&id=<?php echo $doc['id']; ?>" class="action-btn" title="Download">
                    <i class="fas fa-download"></i>
                </a>
                <a href="?delete=1&id=<?php echo $doc['id']; ?>" class="action-btn delete" 
                   onclick="return confirm('Delete this document?')" title="Delete">
                    <i class="fas fa-trash"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Empty State -->
    <div class="empty-state">
        <i class="fas fa-folder-open"></i>
        <h3>No Documents</h3>
        <p>You haven't uploaded any documents yet.</p>
        <button class="btn-primary" onclick="showUploadForm()">
            <i class="fas fa-cloud-upload-alt"></i> Upload First Document
        </button>
    </div>
    <?php endif; ?>
</div>

<style>
.documents-page {
    max-width: 1200px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
}

.header-content h1 {
    font-size: 28px;
    color: #1e293b;
    margin-bottom: 5px;
}

.header-content p {
    color: #64748b;
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
    border-radius: 15px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon.blue { background: rgba(102,126,234,0.1); color: #667eea; }
.stat-icon.green { background: rgba(16,185,129,0.1); color: #10b981; }
.stat-icon.purple { background: rgba(124,58,237,0.1); color: #7c3aed; }
.stat-icon.orange { background: rgba(245,158,11,0.1); color: #f59e0b; }

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    display: block;
}

.stat-label {
    font-size: 13px;
    color: #64748b;
}

/* Upload Form */
.upload-form {
    margin-bottom: 30px;
}

.form-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.form-card h3 {
    font-size: 18px;
    color: #1e293b;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-card h3 i {
    color: #667eea;
}

/* File Input */
.file-input-wrapper {
    position: relative;
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
    gap: 10px;
    padding: 12px 15px;
    background: #f8fafc;
    border: 2px dashed #e2e8f0;
    border-radius: 10px;
    color: #64748b;
    transition: all 0.2s ease;
}

.file-input-wrapper:hover .file-input-label {
    background: #f1f5f9;
    border-color: #667eea;
    color: #667eea;
}

/* Filters Bar */
.filters-bar {
    background: white;
    border-radius: 12px;
    padding: 15px 20px;
    margin-bottom: 25px;
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    align-items: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-group label {
    font-weight: 500;
    color: #475569;
    font-size: 14px;
}

.filter-group select {
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    font-size: 14px;
    min-width: 150px;
}

.total-size {
    color: #667eea;
    font-weight: 600;
    font-size: 14px;
}

/* Documents Grid */
.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.document-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    gap: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
    border: 1px solid #e2e8f0;
}

.document-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    border-color: #667eea;
}

.document-icon {
    font-size: 32px;
    color: #667eea;
    flex-shrink: 0;
}

.document-info {
    flex: 1;
}

.document-title {
    font-size: 16px;
    margin-bottom: 8px;
}

.document-title a {
    color: #1e293b;
    text-decoration: none;
    font-weight: 600;
}

.document-title a:hover {
    color: #667eea;
}

.document-description {
    color: #64748b;
    font-size: 13px;
    margin-bottom: 10px;
    line-height: 1.5;
}

.document-project {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #667eea;
    font-size: 12px;
    margin-bottom: 10px;
}

.document-project i {
    font-size: 12px;
}

.document-meta {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: #94a3b8;
}

.meta-item i {
    font-size: 11px;
}

.document-actions {
    display: flex;
    gap: 5px;
    flex-shrink: 0;
}

.action-btn {
    width: 32px;
    height: 32px;
    background: #f1f5f9;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s ease;
}

.action-btn:hover {
    background: #667eea;
    color: white;
}

.action-btn.delete:hover {
    background: #ef4444;
}

/* Alerts */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 60px;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #1e293b;
    margin-bottom: 10px;
}

.empty-state p {
    color: #64748b;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .btn-primary {
        width: 100%;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-group select {
        width: 100%;
    }
    
    .documents-grid {
        grid-template-columns: 1fr;
    }
    
    .document-card {
        flex-direction: column;
    }
    
    .document-actions {
        justify-content: flex-end;
    }
}
</style>

<script>
function showUploadForm() {
    document.getElementById('uploadForm').style.display = 'block';
    document.getElementById('uploadForm').scrollIntoView({ behavior: 'smooth' });
}

function hideUploadForm() {
    document.getElementById('uploadForm').style.display = 'none';
}

function applyFilter() {
    const project = document.getElementById('project_filter').value;
    const type = document.getElementById('type_filter').value;
    window.location.href = `?project=${project}&type=${type}`;
}

// Update filename when file is selected
document.getElementById('document')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0] ? e.target.files[0].name : 'Choose a file...';
    document.getElementById('file-name').textContent = fileName;
});
</script>

<?php require_once '../includes/client-footer.php'; ?>