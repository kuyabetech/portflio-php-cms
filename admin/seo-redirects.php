<?php
// admin/seo-redirects.php
// Redirect Manager

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_redirect'])) {
    $data = [
        'old_url' => sanitize($_POST['old_url']),
        'new_url' => sanitize($_POST['new_url']),
        'status_code' => (int)$_POST['status_code']
    ];
    
    if (!empty($_POST['id'])) {
        db()->update('seo_redirects', $data, 'id = :id', ['id' => $_POST['id']]);
        $msg = 'updated';
    } else {
        db()->insert('seo_redirects', $data);
        $msg = 'created';
    }
    
    header("Location: seo.php?type=redirects&msg=$msg");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('seo_redirects', 'id = ?', [$id]);
    header('Location: seo.php?type=redirects&msg=deleted');
    exit;
}

// Handle import
if (isset($_POST['import_redirects'])) {
    $lines = explode("\n", $_POST['redirects_list']);
    $imported = 0;
    $errors = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = preg_split('/\s+/', $line);
        if (count($parts) >= 2) {
            $old_url = trim($parts[0]);
            $new_url = trim($parts[1]);
            $status = isset($parts[2]) ? (int)$parts[2] : 301;
            
            try {
                // Check if exists
                $existing = db()->fetch("SELECT id FROM seo_redirects WHERE old_url = ?", [$old_url]);
                if ($existing) {
                    db()->update('seo_redirects', 
                        ['new_url' => $new_url, 'status_code' => $status], 
                        'id = :id', ['id' => $existing['id']]
                    );
                } else {
                    db()->insert('seo_redirects', [
                        'old_url' => $old_url,
                        'new_url' => $new_url,
                        'status_code' => $status
                    ]);
                }
                $imported++;
            } catch (Exception $e) {
                $errors++;
            }
        }
    }
    
    header("Location: seo.php?type=redirects&msg=imported&imported=$imported&errors=$errors");
    exit;
}

// Get all redirects
$redirects = db()->fetchAll("SELECT * FROM seo_redirects ORDER BY hits DESC, old_url");

// Get redirect for editing
$editRedirect = null;
if (isset($_GET['edit'])) {
    $editRedirect = db()->fetch("SELECT * FROM seo_redirects WHERE id = ?", [$_GET['edit']]);
}

// Include header
require_once 'includes/header.php';
?>

<div class="content-header">
    <h2>Redirect Manager</h2>
    <div class="header-actions">
        <button class="btn btn-primary" onclick="showRedirectForm()">
            <i class="fas fa-plus"></i>
            Add Redirect
        </button>
        <button class="btn btn-outline" onclick="showImportForm()">
            <i class="fas fa-upload"></i>
            Import
        </button>
        <a href="export-redirects.php" class="btn btn-outline">
            <i class="fas fa-download"></i>
            Export
        </a>
        <a href="seo.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            Back to SEO
        </a>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'created') echo 'Redirect created successfully!';
        if ($_GET['msg'] === 'updated') echo 'Redirect updated successfully!';
        if ($_GET['msg'] === 'deleted') echo 'Redirect deleted successfully!';
        if ($_GET['msg'] === 'imported') {
            echo "Imported {$_GET['imported']} redirects with {$_GET['errors']} errors.";
        }
        ?>
    </div>
<?php endif; ?>

<!-- Add/Edit Redirect Form -->
<div class="form-container" id="redirectForm" style="display: <?php echo $editRedirect ? 'block' : 'none'; ?>;">
    <h3><?php echo $editRedirect ? 'Edit Redirect' : 'Add New Redirect'; ?></h3>
    
    <form method="POST" class="admin-form">
        <?php if ($editRedirect): ?>
        <input type="hidden" name="id" value="<?php echo $editRedirect['id']; ?>">
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label for="old_url">Old URL *</label>
                <input type="text" id="old_url" name="old_url" required 
                       value="<?php echo $editRedirect['old_url'] ?? ''; ?>"
                       placeholder="/old-page or /old-page.html">
                <small>Enter the URL path only (e.g., /old-page)</small>
            </div>
            
            <div class="form-group">
                <label for="new_url">New URL *</label>
                <input type="text" id="new_url" name="new_url" required 
                       value="<?php echo $editRedirect['new_url'] ?? ''; ?>"
                       placeholder="/new-page or https://example.com/new-page">
            </div>
        </div>
        
        <div class="form-group">
            <label for="status_code">Redirect Type</label>
            <select id="status_code" name="status_code">
                <option value="301" <?php echo ($editRedirect['status_code'] ?? 301) == 301 ? 'selected' : ''; ?>>301 - Permanent</option>
                <option value="302" <?php echo ($editRedirect['status_code'] ?? '') == 302 ? 'selected' : ''; ?>>302 - Temporary</option>
            </select>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="save_redirect" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save Redirect
            </button>
            <button type="button" class="btn btn-outline" onclick="hideRedirectForm()">Cancel</button>
        </div>
    </form>
</div>

<!-- Import Form -->
<div class="form-container" id="importForm" style="display: none;">
    <h3>Import Redirects</h3>
    
    <form method="POST" class="admin-form">
        <div class="form-group">
            <label for="redirects_list">Redirects List</label>
            <textarea id="redirects_list" name="redirects_list" rows="10" class="form-control"
                      placeholder="/old-page /new-page 301&#10;/old-post /blog/new-post 301"></textarea>
            <small>Format: old_url new_url [status_code] (one per line)</small>
        </div>
        
        <div class="form-actions">
            <button type="submit" name="import_redirects" class="btn btn-primary">
                <i class="fas fa-upload"></i>
                Import Redirects
            </button>
            <button type="button" class="btn btn-outline" onclick="hideImportForm()">Cancel</button>
        </div>
    </form>
</div>

<!-- Redirects List -->
<div class="table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Old URL</th>
                <th>New URL</th>
                <th>Type</th>
                <th>Hits</th>
                <th>Last Used</th>
                <th width="120">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($redirects as $redirect): ?>
            <tr>
                <td><code><?php echo htmlspecialchars($redirect['old_url']); ?></code></td>
                <td><code><?php echo htmlspecialchars($redirect['new_url']); ?></code></td>
                <td>
                    <span class="status-badge <?php echo $redirect['status_code'] == 301 ? 'published' : 'draft'; ?>">
                        <?php echo $redirect['status_code']; ?>
                    </span>
                </td>
                <td><?php echo number_format($redirect['hits']); ?></td>
                <td><?php echo $redirect['updated_at'] ? date('M d, Y', strtotime($redirect['updated_at'])) : 'Never'; ?></td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn" onclick="editRedirect(<?php echo htmlspecialchars(json_encode($redirect)); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?type=redirects&delete=<?php echo $redirect['id']; ?>" 
                           class="action-btn delete-btn"
                           onclick="return confirm('Delete this redirect?')">
                            <i class="fas fa-trash"></i>
                        </a>
                        <button class="action-btn" onclick="testRedirect('<?php echo $redirect['old_url']; ?>')" title="Test">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($redirects)): ?>
            <tr>
                <td colspan="6" class="text-center">No redirects found. Add your first redirect above.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.action-buttons {
    display: flex;
    gap: 5px;
}
</style>

<script>
function showRedirectForm() {
    document.getElementById('redirectForm').style.display = 'block';
    document.getElementById('importForm').style.display = 'none';
    document.getElementById('old_url').value = '';
    document.getElementById('new_url').value = '';
    document.getElementById('status_code').value = '301';
}

function hideRedirectForm() {
    document.getElementById('redirectForm').style.display = 'none';
}

function editRedirect(redirect) {
    showRedirectForm();
    document.getElementById('old_url').value = redirect.old_url;
    document.getElementById('new_url').value = redirect.new_url;
    document.getElementById('status_code').value = redirect.status_code;
}

function showImportForm() {
    document.getElementById('importForm').style.display = 'block';
    document.getElementById('redirectForm').style.display = 'none';
}

function hideImportForm() {
    document.getElementById('importForm').style.display = 'none';
}

function testRedirect(oldUrl) {
    window.open('<?php echo BASE_URL; ?>' + oldUrl, '_blank');
}
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>