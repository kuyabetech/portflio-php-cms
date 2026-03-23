<?php
// admin/seo-metadata.php
// Page Metadata Management - FULLY RESPONSIVE

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_metadata'])) {
    // Prepare data array
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
        // FIX: Use direct query instead of update method to avoid parameter issues
        $sql = "UPDATE seo_metadata SET 
                page_url = ?, 
                page_type = ?, 
                title = ?, 
                meta_description = ?, 
                meta_keywords = ?, 
                og_title = ?, 
                og_description = ?, 
                og_image = ?, 
                twitter_title = ?, 
                twitter_description = ?, 
                twitter_image = ?, 
                canonical_url = ?, 
                noindex = ?, 
                nofollow = ?, 
                schema_markup = ?,
                updated_at = NOW()
                WHERE id = ?";
        
        $params = [
            $data['page_url'],
            $data['page_type'],
            $data['title'],
            $data['meta_description'],
            $data['meta_keywords'],
            $data['og_title'],
            $data['og_description'],
            $data['og_image'] ?? null,
            $data['twitter_title'],
            $data['twitter_description'],
            $data['twitter_image'] ?? null,
            $data['canonical_url'],
            $data['noindex'],
            $data['nofollow'],
            $data['schema_markup'],
            $existing['id']
        ];
        
        db()->query($sql, $params);
        $msg = 'updated';
    } else {
        // Insert new record
        $sql = "INSERT INTO seo_metadata (
                page_url, page_type, title, meta_description, meta_keywords,
                og_title, og_description, og_image, twitter_title, twitter_description,
                twitter_image, canonical_url, noindex, nofollow, schema_markup, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $data['page_url'],
            $data['page_type'],
            $data['title'],
            $data['meta_description'],
            $data['meta_keywords'],
            $data['og_title'],
            $data['og_description'],
            $data['og_image'] ?? null,
            $data['twitter_title'],
            $data['twitter_description'],
            $data['twitter_image'] ?? null,
            $data['canonical_url'],
            $data['noindex'],
            $data['nofollow'],
            $data['schema_markup']
        ];
        
        db()->query($sql, $params);
        $msg = 'created';
    }
    
    header("Location: seo-metadata.php?msg=$msg");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    db()->delete('seo_metadata', 'id = ?', [$id]);
    header('Location: seo-metadata.php?msg=deleted');
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

<!-- The rest of your HTML remains exactly the same -->

<div class="seo-metadata-page">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h2>
                <i class="fas fa-search"></i> 
                SEO Metadata Management
            </h2>
            <p>Manage meta tags and social sharing for all pages</p>
        </div>
        
        <!-- Mobile Filter Button -->
        <button class="mobile-add-btn" id="mobileAddBtn" onclick="showMetadataForm()">
            <i class="fas fa-plus"></i> Add Metadata
        </button>
        
        <div class="header-actions">
            <button class="btn btn-primary" onclick="showMetadataForm()">
                <i class="fas fa-plus"></i>
                Add Page Metadata
            </button>
        </div>
    </div>
    
    <!-- Flash Messages -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success" id="flashMessage">
            <div class="alert-content">
                <i class="fas fa-check-circle"></i>
                <span>
                    <?php 
                    if ($_GET['msg'] === 'created') echo 'Metadata created successfully!';
                    if ($_GET['msg'] === 'updated') echo 'Metadata updated successfully!';
                    if ($_GET['msg'] === 'deleted') echo 'Metadata deleted successfully!';
                    ?>
                </span>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>
    
    <!-- Metadata Form -->
    <div class="form-container" id="metadataForm" style="display: <?php echo $editItem ? 'block' : 'none'; ?>;">
        <div class="form-header">
            <h3><i class="fas fa-<?php echo $editItem ? 'edit' : 'plus-circle'; ?>"></i> 
                <?php echo $editItem ? 'Edit Metadata' : 'Add New Page Metadata'; ?>
            </h3>
            <button type="button" class="close-form" onclick="hideMetadataForm()">&times;</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="admin-form">
            <input type="hidden" name="id" id="meta_id" value="<?php echo $editItem['id'] ?? ''; ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="page_url">Page URL <span class="required">*</span></label>
                    <select id="page_url" name="page_url" required class="form-control">
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
                    <select id="page_type" name="page_type" class="form-control">
                        <option value="home" <?php echo ($editItem['page_type'] ?? '') === 'home' ? 'selected' : ''; ?>>Home</option>
                        <option value="projects" <?php echo ($editItem['page_type'] ?? '') === 'projects' ? 'selected' : ''; ?>>Projects</option>
                        <option value="blog" <?php echo ($editItem['page_type'] ?? '') === 'blog' ? 'selected' : ''; ?>>Blog</option>
                        <option value="contact" <?php echo ($editItem['page_type'] ?? '') === 'contact' ? 'selected' : ''; ?>>Contact</option>
                        <option value="custom" <?php echo ($editItem['page_type'] ?? '') === 'custom' ? 'selected' : ''; ?>>Custom</option>
                    </select>
                </div>
            </div>
            
            <!-- Accordion Sections for Mobile -->
            <div class="form-accordion">
                <div class="accordion-item active">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h4><i class="fas fa-search"></i> Basic SEO</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="accordion-content" style="display: block;">
                        <div class="form-group">
                            <label for="title">Meta Title</label>
                            <input type="text" id="title" name="title" value="<?php echo $editItem['title'] ?? ''; ?>"
                                   placeholder="Title (50-60 characters recommended)" class="form-control">
                            <small class="char-counter" data-target="title" data-max="60"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="meta_description">Meta Description</label>
                            <textarea id="meta_description" name="meta_description" rows="3" 
                                      placeholder="Description (150-160 characters recommended)" class="form-control"><?php echo $editItem['meta_description'] ?? ''; ?></textarea>
                            <small class="char-counter" data-target="meta_description" data-max="160"></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="meta_keywords">Meta Keywords</label>
                            <input type="text" id="meta_keywords" name="meta_keywords" value="<?php echo $editItem['meta_keywords'] ?? ''; ?>"
                                   placeholder="keyword1, keyword2, keyword3" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h4><i class="fab fa-facebook"></i> Open Graph (Facebook, LinkedIn)</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="accordion-content" style="display: none;">
                        <div class="form-group">
                            <label for="og_title">OG Title</label>
                            <input type="text" id="og_title" name="og_title" value="<?php echo $editItem['og_title'] ?? ''; ?>"
                                   placeholder="Leave empty to use Meta Title" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="og_description">OG Description</label>
                            <textarea id="og_description" name="og_description" rows="2" class="form-control"><?php echo $editItem['og_description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="og_image">OG Image</label>
                            <input type="file" id="og_image" name="og_image" accept="image/*" class="form-control-file">
                            <?php if ($editItem && $editItem['og_image']): ?>
                            <div class="current-image">
                                <img src="<?php echo UPLOAD_URL . 'seo/' . $editItem['og_image']; ?>" 
                                     alt="OG Image">
                                <span class="image-filename"><?php echo $editItem['og_image']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h4><i class="fab fa-twitter"></i> Twitter Card</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="accordion-content" style="display: none;">
                        <div class="form-group">
                            <label for="twitter_title">Twitter Title</label>
                            <input type="text" id="twitter_title" name="twitter_title" value="<?php echo $editItem['twitter_title'] ?? ''; ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="twitter_description">Twitter Description</label>
                            <textarea id="twitter_description" name="twitter_description" rows="2" class="form-control"><?php echo $editItem['twitter_description'] ?? ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="twitter_image">Twitter Image</label>
                            <input type="file" id="twitter_image" name="twitter_image" accept="image/*" class="form-control-file">
                            <?php if ($editItem && $editItem['twitter_image']): ?>
                            <div class="current-image">
                                <img src="<?php echo UPLOAD_URL . 'seo/' . $editItem['twitter_image']; ?>" 
                                     alt="Twitter Image">
                                <span class="image-filename"><?php echo $editItem['twitter_image']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="accordion-item">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h4><i class="fas fa-cog"></i> Advanced SEO</h4>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="accordion-content" style="display: none;">
                        <div class="form-group">
                            <label for="canonical_url">Canonical URL</label>
                            <input type="url" id="canonical_url" name="canonical_url" value="<?php echo $editItem['canonical_url'] ?? ''; ?>"
                                   placeholder="Leave empty to use current URL" class="form-control">
                        </div>
                        
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="noindex" name="noindex" <?php echo ($editItem['noindex'] ?? 0) ? 'checked' : ''; ?>>
                                <label for="noindex">No Index - Hide from search engines</label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="nofollow" name="nofollow" <?php echo ($editItem['nofollow'] ?? 0) ? 'checked' : ''; ?>>
                                <label for="nofollow">No Follow - Don't follow links on this page</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="schema_markup">JSON-LD Schema Markup</label>
                            <textarea id="schema_markup" name="schema_markup" rows="6" 
                                      placeholder='{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Your Name",
  "url": "<?php echo BASE_URL; ?>"
}' class="form-control code-editor"><?php echo $editItem['schema_markup'] ?? ''; ?></textarea>
                            <small>Custom JSON-LD structured data</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="save_metadata" class="btn btn-primary btn-large">
                    <i class="fas fa-save"></i>
                    Save Metadata
                </button>
                <button type="button" class="btn btn-outline btn-large" onclick="hideMetadataForm()">Cancel</button>
            </div>
        </form>
    </div>
    
    <!-- Metadata List -->
    <div class="metadata-list">
        <div class="list-header">
            <h3><i class="fas fa-list"></i> All Metadata</h3>
            <div class="list-search">
                <input type="text" id="searchMetadata" placeholder="Search pages..." class="search-input">
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>
        
        <!-- Desktop Table View -->
        <div class="table-responsive desktop-view">
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
                <tbody id="metadataTableBody">
                    <?php foreach ($metadata as $item): ?>
                    <tr class="metadata-row">
                        <td>
                            <a href="<?php echo BASE_URL . $item['page_url']; ?>" target="_blank" class="page-link">
                                <?php echo $item['page_url']; ?>
                                <i class="fas fa-external-link-alt external-icon"></i>
                            </a>
                        </td>
                        <td><span class="type-badge type-<?php echo $item['page_type']; ?>"><?php echo ucfirst($item['page_type']); ?></span></td>
                        <td>
                            <span class="title-text" title="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                                <?php echo htmlspecialchars(substr($item['title'] ?? '', 0, 40)); ?>
                                <?php if (strlen($item['title'] ?? '') > 40): ?>...<?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <span class="desc-text" title="<?php echo htmlspecialchars($item['meta_description'] ?? ''); ?>">
                                <?php echo htmlspecialchars(substr($item['meta_description'] ?? '', 0, 60)); ?>...
                            </span>
                        </td>
                        <td>
                            <?php if ($item['noindex']): ?>
                            <span class="status-badge warning">Yes</span>
                            <?php else: ?>
                            <span class="status-badge success">No</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($item['updated_at'] ?? $item['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="?edit=<?php echo $item['id']; ?>" class="action-btn edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?php echo $item['id']; ?>" 
                                   class="action-btn delete"
                                   onclick="return confirm('Delete this metadata?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <button class="action-btn preview" onclick="previewSEO(<?php echo htmlspecialchars(json_encode($item)); ?>)" title="Preview">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($metadata)): ?>
                    <tr>
                        <td colspan="7" class="text-center empty-state">
                            <i class="fas fa-search"></i>
                            <p>No SEO metadata found</p>
                            <button class="btn btn-primary btn-sm" onclick="showMetadataForm()">Add your first metadata</button>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Card View -->
        <div class="mobile-cards-view" id="mobileCardsView">
            <?php foreach ($metadata as $item): ?>
            <div class="metadata-card" data-url="<?php echo $item['page_url']; ?>" data-title="<?php echo strtolower($item['title'] ?? ''); ?>">
                <div class="card-header">
                    <div class="page-info">
                        <a href="<?php echo BASE_URL . $item['page_url']; ?>" target="_blank" class="page-url">
                            <i class="fas fa-link"></i> <?php echo $item['page_url']; ?>
                        </a>
                        <span class="type-badge type-<?php echo $item['page_type']; ?>"><?php echo ucfirst($item['page_type']); ?></span>
                    </div>
                    <div class="card-actions">
                        <button class="card-action-btn" onclick="previewSEO(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                            <i class="fas fa-eye"></i>
                        </button>
                        <a href="?edit=<?php echo $item['id']; ?>" class="card-action-btn">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?delete=<?php echo $item['id']; ?>" class="card-action-btn delete" onclick="return confirm('Delete this metadata?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Title:</span>
                        <span class="info-value"><?php echo htmlspecialchars($item['title'] ?? 'Not set'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Description:</span>
                        <span class="info-value"><?php echo htmlspecialchars(substr($item['meta_description'] ?? '', 0, 80)); ?>...</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">No Index:</span>
                        <span class="info-value">
                            <?php if ($item['noindex']): ?>
                            <span class="status-badge warning">Yes</span>
                            <?php else: ?>
                            <span class="status-badge success">No</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Updated:</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($item['updated_at'] ?? $item['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($metadata)): ?>
            <div class="empty-state-card">
                <i class="fas fa-search"></i>
                <p>No SEO metadata found</p>
                <button class="btn btn-primary" onclick="showMetadataForm()">Add your first metadata</button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SEO Preview Modal -->
<div id="seoPreviewModal" class="modal">
    <div class="modal-overlay" onclick="closePreviewModal()"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-eye"></i> SEO Preview</h3>
            <button class="close-modal" onclick="closePreviewModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="preview-tabs">
                <button class="tab-btn active" onclick="showPreviewTab('google')">Google</button>
                <button class="tab-btn" onclick="showPreviewTab('facebook')">Facebook</button>
                <button class="tab-btn" onclick="showPreviewTab('twitter')">Twitter</button>
            </div>
            
            <div class="preview-content active" id="googlePreview">
                <div class="google-preview">
                    <div class="preview-url" id="previewUrl"></div>
                    <div class="preview-title" id="previewTitle"></div>
                    <div class="preview-description" id="previewDescription"></div>
                </div>
            </div>
            
            <div class="preview-content" id="facebookPreview">
                <div class="facebook-preview">
                    <div class="fb-image" id="fbImage">
                        <div class="no-image">No Image</div>
                    </div>
                    <div class="fb-content">
                        <div class="fb-url" id="fbUrl"></div>
                        <div class="fb-title" id="fbTitle"></div>
                        <div class="fb-description" id="fbDescription"></div>
                    </div>
                </div>
            </div>
            
            <div class="preview-content" id="twitterPreview">
                <div class="twitter-preview">
                    <div class="tw-image" id="twImage">
                        <div class="no-image">No Image</div>
                    </div>
                    <div class="tw-content">
                        <div class="tw-title" id="twTitle"></div>
                        <div class="tw-description" id="twDescription"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closePreviewModal()">Close</button>
        </div>
    </div>
</div>

<style>
/* ========================================
   MOBILE-FIRST RESPONSIVE STYLES
   ======================================== */

:root {
    --primary: #667eea;
    --primary-dark: #5a67d8;
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
    --white: #ffffff;
}

.seo-metadata-page {
    padding: 15px;
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
    background: var(--white);
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.header-content h2 {
    font-size: 24px;
    color: var(--dark);
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-content h2 i {
    color: var(--primary);
}

.header-content p {
    color: var(--gray-500);
    font-size: 14px;
}

.header-actions {
    display: none;
}

.mobile-add-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    background: var(--primary);
    color: var(--white);
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    transition: all 0.2s ease;
}

.mobile-add-btn:active {
    background: var(--primary-dark);
    transform: scale(0.98);
}

/* Alerts */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    animation: slideIn 0.3s ease;
    background: var(--white);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border-left: 4px solid var(--success);
}

.alert-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: currentColor;
    opacity: 0.5;
}

/* Form Container */
.form-container {
    background: var(--white);
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
}

.form-header {
    padding: 20px;
    background: var(--gray-100);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.form-header h3 {
    font-size: 18px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-header h3 i {
    color: var(--primary);
}

.close-form {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--gray-500);
    padding: 0 5px;
}

.close-form:hover {
    color: var(--danger);
}

.admin-form {
    padding: 20px;
}

.form-row {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--gray-600);
    font-size: 14px;
}

.required {
    color: var(--danger);
    margin-left: 3px;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 15px;
    transition: all 0.2s ease;
    background: var(--white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.form-control-file {
    width: 100%;
    padding: 10px 0;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

textarea.code-editor {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 13px;
    background: var(--gray-100);
}

/* Accordion */
.form-accordion {
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 20px;
}

.accordion-item {
    border-bottom: 1px solid var(--gray-200);
}

.accordion-item:last-child {
    border-bottom: none;
}

.accordion-header {
    padding: 15px 20px;
    background: var(--gray-100);
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: background 0.2s ease;
}

.accordion-header:hover {
    background: var(--gray-200);
}

.accordion-header h4 {
    margin: 0;
    font-size: 15px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.accordion-header h4 i {
    color: var(--primary);
    width: 20px;
}

.accordion-header .fa-chevron-down {
    color: var(--gray-500);
    transition: transform 0.3s ease;
}

.accordion-item.active .fa-chevron-down {
    transform: rotate(180deg);
}

.accordion-content {
    padding: 20px;
    background: var(--white);
}

/* Current Image */
.current-image {
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px;
    background: var(--gray-100);
    border-radius: 6px;
}

.current-image img {
    max-width: 60px;
    max-height: 60px;
    border-radius: 4px;
    border: 1px solid var(--gray-200);
}

.image-filename {
    font-size: 12px;
    color: var(--gray-600);
    word-break: break-all;
}

/* Checkbox Group */
.checkbox-group {
    background: var(--gray-100);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.checkbox-item:last-child {
    margin-bottom: 0;
}

.checkbox-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

.checkbox-item label {
    font-size: 14px;
    color: var(--gray-600);
    cursor: pointer;
}

/* Character Counter */
.char-counter {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--gray-500);
    text-align: right;
}

/* Form Actions */
.form-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
    white-space: nowrap;
}

.btn-large {
    padding: 14px 24px;
    font-size: 16px;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 13px;
}

.btn-primary {
    background: var(--primary);
    color: var(--white);
}

.btn-primary:active {
    background: var(--primary-dark);
    transform: scale(0.98);
}

.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-outline:active {
    background: var(--primary);
    color: var(--white);
}

/* Metadata List */
.metadata-list {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.list-header {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.list-header h3 {
    font-size: 18px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
}

.list-header h3 i {
    color: var(--primary);
}

.list-search {
    position: relative;
    width: 100%;
}

.search-input {
    width: 100%;
    padding: 12px 15px 12px 40px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 14px;
}

.search-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-400);
}

/* Desktop Table View */
.desktop-view {
    display: none;
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}

.admin-table th {
    background: var(--gray-100);
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--gray-600);
    font-size: 13px;
    white-space: nowrap;
}

.admin-table td {
    padding: 15px;
    border-bottom: 1px solid var(--gray-200);
    font-size: 14px;
}

.page-link {
    color: var(--primary);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.external-icon {
    font-size: 11px;
    opacity: 0.6;
}

.type-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.type-home { background: #e0f2fe; color: #0369a1; }
.type-projects { background: #fef3c7; color: #92400e; }
.type-blog { background: #d1fae5; color: #065f46; }
.type-contact { background: #ede9fe; color: #5b21b6; }
.type-custom { background: #f1f5f9; color: #475569; }

.title-text, .desc-text {
    max-width: 200px;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.success {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.warning {
    background: #fef3c7;
    color: #92400e;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    background: var(--gray-100);
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.action-btn:hover {
    background: var(--primary);
    color: var(--white);
}

.action-btn.delete:hover {
    background: var(--danger);
}

.action-btn.preview:hover {
    background: var(--success);
}

/* Mobile Cards View */
.mobile-cards-view {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.metadata-card {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--gray-200);
}

.page-info {
    flex: 1;
    min-width: 0;
}

.page-url {
    display: block;
    font-size: 14px;
    color: var(--primary);
    text-decoration: none;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.card-actions {
    display: flex;
    gap: 8px;
    margin-left: 10px;
}

.card-action-btn {
    width: 36px;
    height: 36px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-600);
    background: var(--gray-100);
    text-decoration: none;
    border: none;
    cursor: pointer;
}

.card-action-btn.delete {
    color: var(--danger);
}

.card-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.info-row {
    display: flex;
    font-size: 13px;
}

.info-label {
    width: 80px;
    color: var(--gray-500);
    flex-shrink: 0;
}

.info-value {
    color: var(--dark);
    word-break: break-word;
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-400);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
}

.empty-state-card {
    text-align: center;
    padding: 40px 20px;
    background: var(--gray-100);
    border-radius: 10px;
    color: var(--gray-500);
}

.empty-state-card i {
    font-size: 48px;
    margin-bottom: 15px;
    color: var(--gray-300);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-container {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 95%;
    max-width: 600px;
    max-height: 90vh;
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: var(--gray-500);
}

.modal-body {
    padding: 20px;
    max-height: calc(90vh - 140px);
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid var(--gray-200);
    text-align: right;
}

/* Preview Tabs */
.preview-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    background: var(--gray-100);
    padding: 5px;
    border-radius: 8px;
}

.tab-btn {
    flex: 1;
    padding: 10px;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    color: var(--gray-600);
    cursor: pointer;
    transition: all 0.2s ease;
}

.tab-btn.active {
    background: var(--white);
    color: var(--primary);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.preview-content {
    display: none;
}

.preview-content.active {
    display: block;
}

/* Preview Styles */
.google-preview {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    padding: 20px;
}

.preview-url {
    color: #006621;
    font-size: 14px;
    margin-bottom: 5px;
    word-break: break-all;
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
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    overflow: hidden;
}

.fb-image {
    height: 200px;
    background: var(--gray-100);
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
    word-break: break-all;
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

.twitter-preview {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    overflow: hidden;
    max-width: 500px;
}

.tw-image {
    height: 200px;
    background: var(--gray-100);
    overflow: hidden;
}

.tw-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tw-content {
    padding: 15px;
}

.tw-title {
    color: #1d2129;
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 5px;
}

.tw-description {
    color: #606770;
    font-size: 14px;
    line-height: 1.4;
}

.no-image {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gray-100);
    color: var(--gray-500);
}

/* Animations */
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

/* Tablet Styles */
@media (min-width: 768px) {
    .seo-metadata-page {
        padding: 20px;
    }
    
    .page-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .mobile-add-btn {
        display: none;
    }
    
    .header-actions {
        display: block;
    }
    
    .form-row {
        flex-direction: row;
    }
    
    .form-row .form-group {
        flex: 1;
    }
    
    .form-actions {
        flex-direction: row;
        justify-content: flex-end;
    }
    
    .list-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
    
    .list-search {
        width: 300px;
    }
    
    .desktop-view {
        display: block;
    }
    
    .mobile-cards-view {
        display: none;
    }
}

/* Desktop Styles */
@media (min-width: 1024px) {
    .seo-metadata-page {
        padding: 24px;
    }
    
    .form-container {
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
    }
    
    .admin-form {
        padding: 30px;
    }
    
    .accordion-header {
        padding: 18px 25px;
    }
    
    .accordion-header h4 {
        font-size: 16px;
    }
    
    .accordion-content {
        padding: 25px;
    }
    
    .btn-large {
        min-width: 200px;
    }
}
</style>

<script>
// Show/Hide Form
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
    
    // Scroll to form
    setTimeout(() => {
        document.getElementById('metadataForm').scrollIntoView({ behavior: 'smooth' });
    }, 100);
}

function hideMetadataForm() {
    document.getElementById('metadataForm').style.display = 'none';
}

// Accordion
function toggleAccordion(header) {
    const item = header.closest('.accordion-item');
    const content = item.querySelector('.accordion-content');
    const isActive = item.classList.contains('active');
    
    if (isActive) {
        item.classList.remove('active');
        content.style.display = 'none';
    } else {
        item.classList.add('active');
        content.style.display = 'block';
    }
}

// Character Counters
document.querySelectorAll('.char-counter').forEach(counter => {
    const target = document.getElementById(counter.dataset.target);
    const max = parseInt(counter.dataset.max);
    
    if (target) {
        const updateCounter = () => {
            const len = target.value.length;
            counter.textContent = `${len} / ${max} characters`;
            if (len > max) {
                counter.style.color = '#ef4444';
            } else if (len > max * 0.8) {
                counter.style.color = '#f59e0b';
            } else {
                counter.style.color = '#64748b';
            }
        };
        
        target.addEventListener('input', updateCounter);
        updateCounter();
    }
});

// Search functionality
const searchInput = document.getElementById('searchMetadata');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        
        // Filter table rows
        document.querySelectorAll('.metadata-row').forEach(row => {
            const url = row.querySelector('.page-link').textContent.toLowerCase();
            const title = row.querySelector('.title-text').textContent.toLowerCase();
            if (url.includes(searchTerm) || title.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Filter mobile cards
        document.querySelectorAll('.metadata-card').forEach(card => {
            const url = card.dataset.url?.toLowerCase() || '';
            const title = card.dataset.title?.toLowerCase() || '';
            if (url.includes(searchTerm) || title.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
}

// Preview Modal
function previewSEO(item) {
    // Parse item if it's a string
    if (typeof item === 'string') {
        item = JSON.parse(item);
    }
    
    // Google Preview
    document.getElementById('previewUrl').textContent = '<?php echo BASE_URL; ?>' + item.page_url;
    document.getElementById('previewTitle').textContent = item.title || 'No title set';
    document.getElementById('previewDescription').textContent = item.meta_description || 'No description set';
    
    // Facebook Preview
    document.getElementById('fbUrl').textContent = '<?php echo BASE_URL; ?>' + item.page_url;
    document.getElementById('fbTitle').textContent = item.og_title || item.title || 'No title';
    document.getElementById('fbDescription').textContent = item.og_description || item.meta_description || 'No description';
    
    if (item.og_image) {
        document.getElementById('fbImage').innerHTML = '<img src="<?php echo UPLOAD_URL; ?>seo/' + item.og_image + '" alt="OG Image">';
    } else {
        document.getElementById('fbImage').innerHTML = '<div class="no-image">No Image</div>';
    }
    
    // Twitter Preview
    document.getElementById('twTitle').textContent = item.twitter_title || item.title || 'No title';
    document.getElementById('twDescription').textContent = item.twitter_description || item.meta_description || 'No description';
    
    if (item.twitter_image) {
        document.getElementById('twImage').innerHTML = '<img src="<?php echo UPLOAD_URL; ?>seo/' + item.twitter_image + '" alt="Twitter Image">';
    } else if (item.og_image) {
        document.getElementById('twImage').innerHTML = '<img src="<?php echo UPLOAD_URL; ?>seo/' + item.og_image + '" alt="Twitter Image">';
    } else {
        document.getElementById('twImage').innerHTML = '<div class="no-image">No Image</div>';
    }
    
    document.getElementById('seoPreviewModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closePreviewModal() {
    document.getElementById('seoPreviewModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Preview Tabs
function showPreviewTab(tab) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Show corresponding content
    document.querySelectorAll('.preview-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(tab + 'Preview').classList.add('active');
}

// Close modal when clicking overlay
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        closePreviewModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreviewModal();
    }
});

// Auto-hide flash message
document.addEventListener('DOMContentLoaded', function() {
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.opacity = '0';
            setTimeout(() => {
                flashMessage.style.display = 'none';
            }, 300);
        }, 5000);
    }
});

// Responsive handling
function handleResponsive() {
    const width = window.innerWidth;
    const desktopView = document.querySelector('.desktop-view');
    const mobileView = document.querySelector('.mobile-cards-view');
    
    if (width >= 768) {
        if (desktopView) desktopView.style.display = 'block';
        if (mobileView) mobileView.style.display = 'none';
    } else {
        if (desktopView) desktopView.style.display = 'none';
        if (mobileView) mobileView.styl.display = 'flex';
    }
}

window.addEventListener('load', handleResponsive);
window.addEventListener('resize', handleResponsive);
</script>

<?php require_once 'includes/footer.php'; ?>