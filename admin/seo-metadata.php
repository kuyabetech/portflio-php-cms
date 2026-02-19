<?php
// admin/seo-metadata.php
// Page Metadata Management

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_metadata'])) {
    $data = [
        'page_url' => sanitize($_POST['page_url']),
        'page_type' => sanitize($_POST['page_type']),
        'title' => sanitize($_POST['title']),
        'meta_description' => sanitize($_POST['meta_description']),
        'meta_keywords' => sanitize($_POST['meta_keywords']),
        'og_title' => sanitize($_POST['og_title']),
        'og_description' => sanitize($_POST['og_description']),
        'twitter_title' => sanitize($_POST['twitter_title']),
        'twitter_description' => sanitize($_POST['twitter_description']),
        'canonical_url' => sanitize($_POST['canonical_url']),
        'noindex' => isset($_POST['noindex']) ? 1 : 0,
        'nofollow' => isset($_POST['nofollow']) ? 1 : 0,
        'schema_markup' => $_POST['schema_markup']
    ];
    
    // Handle OG Image upload
    if (isset($_FILES['og_image']) && $_FILES['og_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['og_image'], 'seo/');
        if (isset($upload['success'])) {
            $data['og_image'] = $upload['filename'];
        }
    }
    
    // Handle Twitter Image upload
    if (isset($_FILES['twitter_image']) && $_FILES['twitter_image']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['twitter_image'], 'seo/');
        if (isset($upload['success'])) {
            $data['twitter_image'] = $upload['filename'];
        }
    }
    
    // Check if exists
    $existing = db()->fetch("SELECT id FROM seo_metadata WHERE page_url = ?", [$data['page_url']]);
    
    if ($existing) {
        db()->update('seo_metadata', $data, 'id = :id', ['id' => $existing['id']]);
        $msg = 'updated';
    } else {
        db()->insert('seo_metadata', $data);
        $msg = 'created';
    }
    
    header("Location: seo.php?type=metadata&msg=$msg");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('seo_metadata', 'id = ?', [$id]);
    header('Location: seo.php?type=metadata&msg=deleted');
    exit;
}

// Get all metadata
$metadata = db()->fetchAll("SELECT * FROM seo_metadata ORDER BY page_url");

// Get page for editing
$editItem = null;
if (isset($_GET['edit'])) {
    $editItem = db()->fetch("SELECT * FROM seo_metadata WHERE id = ?", [$_GET['edit']]);
}

// Get available pages
$pages = [
    '/' => 'Home Page',
    '/projects' => 'Projects Listing',
    '/blog' => 'Blog Main Page',
    '/contact' => 'Contact Page',
    '/about' => 'About Page',
    '/services' => 'Services Page'
];

// Get blog posts for SEO
$blogPosts = db()->fetchAll("SELECT id, title, slug FROM blog_posts WHERE status = 'published'");
foreach ($blogPosts as $post) {
    $pages['/blog/' . $post['slug']] = 'Blog Post: ' . $post['title'];
}

// Get projects for SEO
$projects = db()->fetchAll("SELECT id, title, slug FROM projects WHERE status = 'published'");
foreach ($projects as $project) {
    $pages['/project/' . $project['slug']] = 'Project: ' . $project['title'];
}

require_once 'includes/header.php';
?>

<div class="content-section">
    <div class="section-header">
        <h2>SEO Metadata Management</h2>
        <button class="btn btn-primary" onclick="showMetadataForm()">
            <i class="fas fa-plus"></i>
            Add Page Metadata
        </button>
    </div>
    
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
            <?php 
            if ($_GET['msg'] === 'created') echo 'Metadata created successfully!';
            if ($_GET['msg'] === 'updated') echo 'Metadata updated successfully!';
            if ($_GET['msg'] === 'deleted') echo 'Metadata deleted successfully!';
            ?>
        </div>
    <?php endif; ?>
    
    <!-- Metadata Form -->
    <div class="form-container" id="metadataForm" style="display: <?php echo $editItem ? 'block' : 'none'; ?>;">
        <h3><?php echo $editItem ? 'Edit Metadata' : 'Add New Page Metadata'; ?></h3>
        
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <input type="hidden" name="id" id="meta_id" value="<?php echo $editItem['id'] ?? ''; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="page_url">Page URL *</label>
                    <select id="page_url" name="page_url" required>
                        <option value="">Select a page</option>
                        <?php foreach ($pages as $url => $label): ?>
                        <option value="<?php echo $url; ?>" 
                            <?php echo ($editItem['page_url'] ?? '') === $url ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="page_type">Page Type</label>
                    <select id="page_type" name="page_type">
                        <option value="home" <?php echo ($editItem['page_type'] ?? '') === 'home' ? 'selected' : ''; ?>>Home</option>
                        <option value="projects" <?php echo ($editItem['page_type'] ?? '') === 'projects' ? 'selected' : ''; ?>>Projects</option>
                        <option value="blog" <?php echo ($editItem['page_type'] ?? '') === 'blog' ? 'selected' : ''; ?>>Blog</option>
                        <option value="contact" <?php echo ($editItem['page_type'] ?? '') === 'contact' ? 'selected' : ''; ?>>Contact</option>
                        <option value="custom" <?php echo ($editItem['page_type'] ?? '') === 'custom' ? 'selected' : ''; ?>>Custom</option>
                    </select>
                </div>
            </div>
            
            <div class="form-section">
                <h4>Basic SEO</h4>
                
                <div class="form-group">
                    <label for="title">Meta Title</label>
                    <input type="text" id="title" name="title" value="<?php echo $editItem['title'] ?? ''; ?>"
                           placeholder="Title (50-60 characters recommended)">
                    <small class="char-counter" data-target="title" data-max="60"></small>
                </div>
                
                <div class="form-group">
                    <label for="meta_description">Meta Description</label>
                    <textarea id="meta_description" name="meta_description" rows="3" 
                              placeholder="Description (150-160 characters recommended)"><?php echo $editItem['meta_description'] ?? ''; ?></textarea>
                    <small class="char-counter" data-target="meta_description" data-max="160"></small>
                </div>
                
                <div class="form-group">
                    <label for="meta_keywords">Meta Keywords</label>
                    <input type="text" id="meta_keywords" name="meta_keywords" value="<?php echo $editItem['meta_keywords'] ?? ''; ?>"
                           placeholder="keyword1, keyword2, keyword3">
                </div>
            </div>
            
            <div class="form-section">
                <h4>Open Graph (Facebook, LinkedIn)</h4>
                
                <div class="form-group">
                    <label for="og_title">OG Title</label>
                    <input type="text" id="og_title" name="og_title" value="<?php echo $editItem['og_title'] ?? ''; ?>"
                           placeholder="Leave empty to use Meta Title">
                </div>
                
                <div class="form-group">
                    <label for="og_description">OG Description</label>
                    <textarea id="og_description" name="og_description" rows="2"><?php echo $editItem['og_description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="og_image">OG Image</label>
                    <input type="file" id="og_image" name="og_image" accept="image/*">
                    <?php if ($editItem && $editItem['og_image']): ?>
                    <div class="current-image">
                        <img src="<?php echo UPLOAD_URL . 'seo/' . $editItem['og_image']; ?>" 
                             alt="OG Image" style="max-width: 200px; margin-top: 10px;">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-section">
                <h4>Twitter Card</h4>
                
                <div class="form-group">
                    <label for="twitter_title">Twitter Title</label>
                    <input type="text" id="twitter_title" name="twitter_title" value="<?php echo $editItem['twitter_title'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="twitter_description">Twitter Description</label>
                    <textarea id="twitter_description" name="twitter_description" rows="2"><?php echo $editItem['twitter_description'] ?? ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="twitter_image">Twitter Image</label>
                    <input type="file" id="twitter_image" name="twitter_image" accept="image/*">
                    <?php if ($editItem && $editItem['twitter_image']): ?>
                    <div class="current-image">
                        <img src="<?php echo UPLOAD_URL . 'seo/' . $editItem['twitter_image']; ?>" 
                             alt="Twitter Image" style="max-width: 200px; margin-top: 10px;">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-section">
                <h4>Advanced SEO</h4>
                
                <div class="form-group">
                    <label for="canonical_url">Canonical URL</label>
                    <input type="url" id="canonical_url" name="canonical_url" value="<?php echo $editItem['canonical_url'] ?? ''; ?>"
                           placeholder="Leave empty to use current URL">
                </div>
                
                <div class="form-row">
                    <div class="form-group checkbox">
                        <label>
                            <input type="checkbox" name="noindex" <?php echo ($editItem['noindex'] ?? 0) ? 'checked' : ''; ?>>
                            No Index (hide from search engines)
                        </label>
                    </div>
                    
                    <div class="form-group checkbox">
                        <label>
                            <input type="checkbox" name="nofollow" <?php echo ($editItem['nofollow'] ?? 0) ? 'checked' : ''; ?>>
                            No Follow (don't follow links on this page)
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="schema_markup">JSON-LD Schema Markup</label>
                    <textarea id="schema_markup" name="schema_markup" rows="8" 
                              placeholder='{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Your Name",
  "url": "<?php echo BASE_URL; ?>"
}'><?php echo $editItem['schema_markup'] ?? ''; ?></textarea>
                    <small>Custom JSON-LD structured data</small>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_metadata" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Save Metadata
                </button>
                <button type="button" class="btn btn-outline" onclick="hideMetadataForm()">Cancel</button>
            </div>
        </form>
    </div>
    
    <!-- Metadata List -->
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Page URL</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>No Index</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($metadata as $item): ?>
                <tr>
                    <td>
                        <a href="<?php echo BASE_URL . $item['page_url']; ?>" target="_blank">
                            <?php echo $item['page_url']; ?>
                            <i class="fas fa-external-link-alt" style="font-size: 0.8rem;"></i>
                        </a>
                    </td>
                    <td><?php echo ucfirst($item['page_type']); ?></td>
                    <td>
                        <?php echo htmlspecialchars(substr($item['title'] ?? '', 0, 50)); ?>
                        <?php if (strlen($item['title'] ?? '') > 50): ?>...<?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars(substr($item['meta_description'] ?? '', 0, 80)); ?>...
                    </td>
                    <td>
                        <?php if ($item['noindex']): ?>
                        <span class="status-badge draft">Yes</span>
                        <?php else: ?>
                        <span class="status-badge published">No</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($item['updated_at'])); ?></td>
                    <td>
                        <a href="?type=metadata&edit=<?php echo $item['id']; ?>" class="action-btn" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?type=metadata&delete=<?php echo $item['id']; ?>" 
                           class="action-btn delete-btn"
                           onclick="return confirm('Delete this metadata?')">
                            <i class="fas fa-trash"></i>
                        </a>
                        <button class="action-btn" onclick="previewSEO(<?php echo htmlspecialchars(json_encode($item)); ?>)" title="Preview">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($metadata)): ?>
                <tr>
                    <td colspan="7" class="text-center">No SEO metadata found. Click "Add Page Metadata" to create one.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SEO Preview Modal -->
<div id="seoPreviewModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>SEO Preview</h3>
            <button class="close-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="google-preview">
                <div class="preview-url" id="previewUrl"></div>
                <div class="preview-title" id="previewTitle"></div>
                <div class="preview-description" id="previewDescription"></div>
            </div>
            
            <div class="social-preview">
                <h4>Facebook Preview</h4>
                <div class="facebook-preview">
                    <div class="fb-image" id="fbImage"></div>
                    <div class="fb-content">
                        <div class="fb-url" id="fbUrl"></div>
                        <div class="fb-title" id="fbTitle"></div>
                        <div class="fb-description" id="fbDescription"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showMetadataForm() {
    document.getElementById('metadataForm').style.display = 'block';
    document.getElementById('meta_id').value = '';
    document.getElementById('page_url').value = '';
    document.getElementById('page_type').value = 'custom';
    document.getElementById('title').value = '';
    document.getElementById('meta_description').value = '';
    document.getElementById('meta_keywords').value = '';
    document.getElementById('og_title').value = '';
    document.getElementById('og_description').value = '';
    document.getElementById('twitter_title').value = '';
    document.getElementById('twitter_description').value = '';
    document.getElementById('canonical_url').value = '';
    document.getElementById('schema_markup').value = '';
    document.querySelector('input[name="noindex"]').checked = false;
    document.querySelector('input[name="nofollow"]').checked = false;
}

function hideMetadataForm() {
    document.getElementById('metadataForm').style.display = 'none';
}

function previewSEO(item) {
    document.getElementById('previewUrl').textContent = '<?php echo BASE_URL; ?>' + item.page_url;
    document.getElementById('previewTitle').textContent = item.title || 'No title set';
    document.getElementById('previewDescription').textContent = item.meta_description || 'No description set';
    
    document.getElementById('fbUrl').textContent = '<?php echo BASE_URL; ?>' + item.page_url;
    document.getElementById('fbTitle').textContent = item.og_title || item.title || 'No title';
    document.getElementById('fbDescription').textContent = item.og_description || item.meta_description || 'No description';
    
    if (item.og_image) {
        document.getElementById('fbImage').innerHTML = '<img src="<?php echo UPLOAD_URL; ?>seo/' + item.og_image + '" alt="OG Image">';
    } else {
        document.getElementById('fbImage').innerHTML = '<div class="no-image">No image</div>';
    }
    
    document.getElementById('seoPreviewModal').style.display = 'block';
}

// Character counters
document.querySelectorAll('.char-counter').forEach(counter => {
    const target = document.getElementById(counter.dataset.target);
    const max = counter.dataset.max;
    
    if (target) {
        const updateCounter = () => {
            const len = target.value.length;
            counter.textContent = `${len} / ${max} characters`;
            if (len > max) {
                counter.style.color = 'red';
            } else if (len > max * 0.8) {
                counter.style.color = 'orange';
            } else {
                counter.style.color = '#666';
            }
        };
        
        target.addEventListener('input', updateCounter);
        updateCounter();
    }
});

// Modal close
document.querySelector('.close-modal').addEventListener('click', function() {
    document.getElementById('seoPreviewModal').style.display = 'none';
});
</script>

<style>
.google-preview {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.preview-url {
    color: #006621;
    font-size: 14px;
    margin-bottom: 5px;
}

.preview-title {
    color: #1a0dab;
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 5px;
    cursor: pointer;
}

.preview-description {
    color: #545454;
    font-size: 13px;
    line-height: 1.4;
}

.facebook-preview {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    max-width: 500px;
}

.fb-image {
    height: 200px;
    background: #f0f2f5;
    overflow: hidden;
}

.fb-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.fb-content {
    padding: 15px;
}

.fb-url {
    color: #606770;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.fb-title {
    color: #1d2129;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 5px;
}

.fb-description {
    color: #606770;
    font-size: 14px;
    line-height: 1.4;
}

.no-image {
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f0f2f5;
    color: #606770;
}
</style>
<?php require_once 'includes/footer.php';