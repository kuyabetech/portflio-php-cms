<?php
// admin/dashboard-widgets.php
// Dashboard Widget Management with Complete Drag & Drop

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$pageTitle = 'Dashboard Widgets';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Widgets']
];

// Handle widget toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $widget = db()->fetch("SELECT is_active FROM dashboard_widgets WHERE id = ?", [$id]);
    if ($widget) {
        $newStatus = $widget['is_active'] ? 0 : 1;
        db()->update('dashboard_widgets', ['is_active' => $newStatus], 'id = :id', ['id' => $id]);
        header('Location: dashboard-widgets.php?msg=toggled');
        exit;
    }
}

// Handle reset user layout
if (isset($_GET['reset_layout'])) {
    db()->delete('user_dashboards', 'user_id = ?', [$_SESSION['user_id']]);
    header('Location: index.php?msg=layout_reset');
    exit;
}

// Handle save layout via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_layout') {
    header('Content-Type: application/json');
    
    $layout = json_decode($_POST['layout'], true);
    if ($layout) {
        foreach ($layout as $item) {
            db()->update('user_dashboards', [
                'position_x' => $item['position_x'],
                'position_y' => $item['position_y'],
                'width' => $item['width'],
                'height' => $item['height']
            ], 'user_id = ? AND widget_id = ?', 
            [$_SESSION['user_id'], $item['widget_id']]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false, 'error' => 'Invalid layout data']);
    exit;
}

// Handle update widget size
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resize_widget') {
    header('Content-Type: application/json');
    
    $widgetId = (int)$_POST['widget_id'];
    $width = (int)$_POST['width'];
    $height = (int)$_POST['height'];
    
    db()->update('user_dashboards', [
        'width' => $width,
        'height' => $height
    ], 'user_id = ? AND widget_id = ?', 
    [$_SESSION['user_id'], $widgetId]);
    
    echo json_encode(['success' => true]);
    exit;
}

// Get all widgets
$widgets = db()->fetchAll("SELECT * FROM dashboard_widgets ORDER BY is_system DESC, title");

// Get user's current layout
$userWidgets = db()->fetchAll("
    SELECT ud.*, dw.title, dw.widget_type, dw.widget_size, dw.icon, dw.settings
    FROM user_dashboards ud
    JOIN dashboard_widgets dw ON ud.widget_id = dw.id
    WHERE ud.user_id = ? AND ud.is_visible = 1
    ORDER BY ud.position_y, ud.position_x
", [$_SESSION['user_id']]);

// Include header
require_once 'includes/header.php';
?>

<!-- Include SortableJS for better drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<style>
/* ========================================
   DASHBOARD WIDGETS PAGE STYLES
   ======================================== */

/* Content Header */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding: 0 5px;
}

.content-header h2 {
    font-size: 1.8rem;
    font-weight: 600;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 12px;
}

/* Alert Messages */
.alert {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    animation: slideDown 0.4s ease;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
    color: #0b5e42;
    border-left: 4px solid #10b981;
    backdrop-filter: blur(10px);
}

/* Edit Mode Bar */
.edit-mode-bar {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    padding: 16px 24px;
    border-radius: 16px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    animation: slideDown 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.3);
}

.edit-mode-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.edit-mode-info i {
    font-size: 1.4rem;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
}

.edit-mode-info span {
    font-size: 1rem;
    font-weight: 500;
    letter-spacing: 0.3px;
}

/* Widgets Section */
.widgets-section {
    background: white;
    border-radius: 24px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.05);
}

.widgets-section h3 {
    font-size: 1.4rem;
    margin-bottom: 8px;
    color: #1e293b;
}

.widgets-section p {
    color: #64748b;
    margin-bottom: 25px;
    font-size: 0.95rem;
}

/* Widgets Grid */
.widgets-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 10px;
}

/* Widget Card */
.widget-card {
    background: #f8fafc;
    border-radius: 20px;
    padding: 24px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.widget-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2563eb, #7c3aed);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.widget-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 30px -10px rgba(37, 99, 235, 0.2);
    border-color: rgba(37, 99, 235, 0.2);
}

.widget-card:hover::before {
    transform: scaleX(1);
}

.widget-card.inactive {
    opacity: 0.6;
    background: #f1f5f9;
    filter: grayscale(0.5);
}

.widget-card.added {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.05);
}

.widget-card.added::before {
    background: linear-gradient(90deg, #10b981, #34d399);
    transform: scaleX(1);
}

/* Widget Header */
.widget-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.widget-header i {
    font-size: 2rem;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    filter: drop-shadow(0 4px 6px rgba(37, 99, 235, 0.2));
}

.widget-header h4 {
    flex: 1;
    font-size: 1.2rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.widget-size {
    font-size: 0.7rem;
    padding: 4px 10px;
    background: #e2e8f0;
    border-radius: 30px;
    color: #475569;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Widget Description */
.widget-description {
    color: #64748b;
    font-size: 0.9rem;
    margin-bottom: 20px;
    line-height: 1.6;
}

/* Widget Meta */
.widget-meta {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.widget-type {
    font-size: 0.75rem;
    padding: 4px 12px;
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
    border-radius: 30px;
    font-weight: 600;
}

.system-badge {
    font-size: 0.75rem;
    padding: 4px 12px;
    background: rgba(100, 116, 139, 0.1);
    color: #475569;
    border-radius: 30px;
    font-weight: 600;
}

/* Widget Actions */
.widget-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.btn-sm {
    padding: 8px 16px;
    font-size: 0.85rem;
    border-radius: 30px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    border: none;
    box-shadow: 0 4px 6px -2px rgba(37, 99, 235, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 12px -4px rgba(37, 99, 235, 0.4);
}

.btn-outline {
    background: transparent;
    border: 2px solid #e2e8f0;
    color: #475569;
}

.btn-outline:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

/* Dashboard Preview Section */
.dashboard-preview {
    background: white;
    border-radius: 24px;
    padding: 30px;
    box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.05);
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.preview-header h3 {
    font-size: 1.4rem;
    color: #1e293b;
    margin: 0;
}

.widget-count {
    padding: 6px 14px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 4px 6px -2px rgba(37, 99, 235, 0.3);
}

/* Dashboard Grid Container */
.dashboard-grid-container {
    position: relative;
    min-height: 500px;
    background: #f8fafc;
    border-radius: 20px;
    padding: 20px;
    border: 2px dashed #e2e8f0;
}

/* Grid Overlay */
.grid-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    pointer-events: none;
    z-index: 1;
    opacity: 0.5;
}

.grid-lines {
    width: 100%;
    height: 100%;
    background-image: 
        linear-gradient(to right, #94a3b8 1px, transparent 1px),
        linear-gradient(to bottom, #94a3b8 1px, transparent 1px);
    background-size: 25% 25%;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    position: relative;
    z-index: 2;
}

/* Dashboard Widget */
.dashboard-widget {
    background: white;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 6px -2px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.06);
    position: relative;
    border: 2px solid transparent;
    min-height: 180px;
    display: flex;
    flex-direction: column;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.dashboard-widget:hover {
    border-color: #2563eb;
    box-shadow: 0 20px 25px -5px rgba(37, 99, 235, 0.2);
    transform: translateY(-2px);
}

/* Widget Handle */
.widget-handle {
    position: absolute;
    top: 10px;
    left: 10px;
    cursor: move;
    color: #94a3b8;
    padding: 8px;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 10;
}

.dashboard-widget:hover .widget-handle {
    opacity: 1;
}

.widget-handle:hover {
    color: #2563eb;
    background: #f1f5f9;
    transform: scale(1.1);
}

/* Resize Handles */
.resize-handle {
    position: absolute;
    width: 12px;
    height: 12px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border: 2px solid white;
    border-radius: 50%;
    z-index: 20;
    opacity: 0;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

.dashboard-widget:hover .resize-handle {
    opacity: 1;
}

.resize-handle:hover {
    transform: scale(1.3);
    background: linear-gradient(135deg, #7c3aed, #2563eb);
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.4);
}

.resize-nw { top: -6px; left: -6px; cursor: nw-resize; }
.resize-n { top: -6px; left: 50%; transform: translateX(-50%); cursor: n-resize; }
.resize-ne { top: -6px; right: -6px; cursor: ne-resize; }
.resize-w { top: 50%; left: -6px; transform: translateY(-50%); cursor: w-resize; }
.resize-e { top: 50%; right: -6px; transform: translateY(-50%); cursor: e-resize; }
.resize-sw { bottom: -6px; left: -6px; cursor: sw-resize; }
.resize-s { bottom: -6px; left: 50%; transform: translateX(-50%); cursor: s-resize; }
.resize-se { bottom: -6px; right: -6px; cursor: se-resize; }

/* Widget Content */
.widget-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.widget-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding-left: 25px;
}

.widget-title i {
    font-size: 1.2rem;
    color: #2563eb;
}

.widget-title h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    flex: 1;
}

.widget-dimensions {
    font-size: 0.7rem;
    padding: 4px 8px;
    background: #f1f5f9;
    border-radius: 4px;
    color: #64748b;
    font-weight: 600;
}

/* Widget Body */
.widget-body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100px;
}

.placeholder-content {
    text-align: center;
    color: #94a3b8;
}

.placeholder-content i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    color: #cbd5e1;
}

.placeholder-content p {
    font-size: 0.9rem;
    margin: 0;
}

/* Widget Footer */
.widget-footer {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 15px;
    padding-top: 10px;
    border-top: 1px solid #e2e8f0;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.dashboard-widget:hover .widget-footer {
    opacity: 1;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background: #2563eb;
    color: white;
    transform: scale(1.1);
}

.btn-icon:last-child:hover {
    background: #ef4444;
}

/* Empty Dashboard */
.empty-dashboard {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 20px;
    background: #f8fafc;
    border-radius: 16px;
    color: #94a3b8;
}

.empty-dashboard i {
    font-size: 4rem;
    margin-bottom: 15px;
    color: #cbd5e1;
}

.empty-dashboard h4 {
    font-size: 1.2rem;
    color: #64748b;
    margin-bottom: 8px;
}

.empty-dashboard p {
    color: #94a3b8;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.modal-dialog {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-content {
    background: white;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    overflow: hidden;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8fafc, white);
}

.modal-header h3 {
    font-size: 1.3rem;
    color: #1e293b;
    margin: 0;
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.8rem;
    cursor: pointer;
    color: #94a3b8;
    transition: all 0.2s ease;
    line-height: 1;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.close-modal:hover {
    color: #ef4444;
    background: #f1f5f9;
}

.modal-body {
    padding: 25px;
}

/* Widget Preview in Modal */
.widget-preview {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    color: white;
    padding: 25px;
    border-radius: 16px;
    text-align: center;
    margin-bottom: 20px;
    box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.3);
}

.widget-preview i {
    font-size: 2.5rem;
    margin-bottom: 10px;
    display: block;
    filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
}

.widget-preview span {
    font-size: 1.2rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* Position Selector - Left, Center, Right */
.position-selector {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    justify-content: space-between;
}

.pos-btn {
    flex: 1;
    padding: 15px 12px;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: #475569;
}

.pos-btn i {
    font-size: 1.2rem;
}

.pos-btn:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
    transform: translateY(-2px);
}

.pos-btn.selected {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border-color: transparent;
    color: white;
    box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
}

.pos-btn.selected i {
    color: white;
}

/* Position Icons */
.pos-btn .fa-arrow-left { color: #2563eb; }
.pos-btn .fa-arrows-alt-h { color: #8b5cf6; }
.pos-btn .fa-arrow-right { color: #2563eb; }

.pos-btn.selected .fa-arrow-left,
.pos-btn.selected .fa-arrows-alt-h,
.pos-btn.selected .fa-arrow-right {
    color: white;
}

/* Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #1e293b;
    font-size: 0.9rem;
}

.form-group select,
.form-group input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.form-group select:focus,
.form-group input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-group.checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group.checkbox input {
    width: auto;
    margin: 0;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* Size Preview */
.size-preview {
    margin: 20px 0;
    padding: 20px;
    background: #f8fafc;
    border-radius: 16px;
}

.size-indicator {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding: 10px;
    background: white;
    border-radius: 8px;
}

.size-label {
    color: #64748b;
}

.size-value {
    font-weight: 700;
    color: #2563eb;
    background: rgba(37, 99, 235, 0.1);
    padding: 4px 12px;
    border-radius: 30px;
}

.size-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 6px;
}

.size-cell {
    aspect-ratio: 1;
    background: #e2e8f0;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.size-cell.active {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
}

/* Buttons */
.btn-block {
    width: 100%;
    padding: 14px;
    font-size: 1rem;
    font-weight: 600;
}

.btn-success {
    background: linear-gradient(135deg, #10b981, #34d399);
    color: white;
    border: none;
    box-shadow: 0 4px 6px -2px rgba(16, 185, 129, 0.3);
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 12px -4px rgba(16, 185, 129, 0.4);
}

/* Dragging States */
.dashboard-widget.dragging {
    opacity: 0.8;
    transform: scale(1.02) rotate(1deg);
    box-shadow: 0 20px 30px -10px rgba(37, 99, 235, 0.4);
    z-index: 1000;
    cursor: grabbing;
}

.dashboard-widget.drag-over {
    border: 2px dashed #2563eb;
    background: rgba(37, 99, 235, 0.05);
}

/* Resizing State */
.dashboard-widget.resizing {
    transition: none;
    user-select: none;
    border: 2px solid #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}

/* Animations */
@keyframes slideDown {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

/* Notification Toast */
.notification-toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: white;
    border-radius: 16px;
    padding: 16px 24px;
    box-shadow: 0 20px 30px -10px rgba(0,0,0,0.2);
    transform: translateX(120%);
    transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    z-index: 10000;
    min-width: 300px;
    border-left: 4px solid;
}

.notification-toast.show {
    transform: translateX(0);
}

.notification-toast.success {
    border-left-color: #10b981;
}

.notification-toast.error {
    border-left-color: #ef4444;
}

.notification-toast.warning {
    border-left-color: #f59e0b;
}

.notification-toast.info {
    border-left-color: #3b82f6;
}

.notification-toast strong {
    display: block;
    margin-bottom: 5px;
    color: #1e293b;
}

.notification-toast p {
    margin: 0;
    color: #64748b;
    font-size: 0.9rem;
}

/* Tooltip */
[title] {
    position: relative;
}

[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.75rem;
    white-space: nowrap;
    pointer-events: none;
    margin-bottom: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    z-index: 100;
}

/* Responsive */
@media (max-width: 1200px) {
    .dashboard-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .dashboard-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .content-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .widgets-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .edit-mode-bar {
        flex-direction: column;
        gap: 15px;
        text-align: center;
        padding: 20px;
    }
    
    .edit-mode-info {
        justify-content: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .position-selector {
        flex-direction: column;
    }
    
    .resize-handle {
        width: 15px;
        height: 15px;
    }
    
    .modal-dialog {
        width: 95%;
        margin: 10px;
    }
    
    .notification-toast {
        left: 20px;
        right: 20px;
        min-width: auto;
    }
}

/* Print Styles */
@media print {
    .widgets-section,
    .edit-mode-bar,
    .header-actions,
    .widget-actions,
    .widget-footer,
    .modal {
        display: none !important;
    }
    
    .dashboard-preview {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .dashboard-widget {
        break-inside: avoid;
        border: 1px solid #ddd;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .widget-card {
        background: #1e293b;
    }
    
    .widget-card h4 {
        color: white;
    }
    
    .widget-description {
        color: #94a3b8;
    }
    
    .dashboard-preview,
    .widgets-section {
        background: #1e293b;
    }
    
    .dashboard-grid-container {
        background: #0f172a;
    }
    
    .dashboard-widget {
        background: #334155;
    }
    
    .widget-title h4 {
        color: white;
    }
}

/* Loading Spinner */
.fa-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #1d4ed8, #6d28d9);
}
</style>

<!-- Rest of the HTML remains the same as before... -->
<!-- Include SortableJS for better drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<div class="content-header">
    <h2>Dashboard Widgets</h2>
    <div class="header-actions">
        <button class="btn btn-outline" onclick="toggleEditMode()" id="editModeBtn">
            <i class="fas fa-edit"></i> Edit Layout
        </button>
        <a href="?reset_layout=1" class="btn btn-outline" onclick="return confirm('Reset your dashboard layout?')">
            <i class="fas fa-undo"></i> Reset Layout
        </a>
        <a href="index.php" class="btn btn-primary">
            <i class="fas fa-tachometer-alt"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">
        <?php 
        if ($_GET['msg'] === 'toggled') echo 'Widget status updated!';
        ?>
    </div>
<?php endif; ?>

<!-- Edit Mode Controls -->
<div class="edit-mode-bar" id="editModeBar" style="display: none;">
    <div class="edit-mode-info">
        <i class="fas fa-info-circle"></i>
        <span>Edit Mode: Drag widgets to rearrange, use resize handles to change size</span>
    </div>
    <button class="btn btn-success btn-sm" onclick="saveLayout()">
        <i class="fas fa-save"></i> Save Layout
    </button>
    <button class="btn btn-outline btn-sm" onclick="toggleEditMode()">
        <i class="fas fa-times"></i> Cancel
    </button>
</div>

<!-- Available Widgets -->
<div class="widgets-section">
    <h3>Available Widgets</h3>
    <p>Click on a widget to add it to your dashboard. You can drag to rearrange and resize.</p>
    
    <div class="widgets-grid">
        <?php foreach ($widgets as $widget): 
            $isAdded = in_array($widget['id'], array_column($userWidgets, 'widget_id'));
        ?>
        <div class="widget-card <?php echo !$widget['is_active'] ? 'inactive' : ''; ?> <?php echo $isAdded ? 'added' : ''; ?>" 
             data-widget-id="<?php echo $widget['id']; ?>">
            <div class="widget-header">
                <i class="<?php echo $widget['icon'] ?: 'fas fa-puzzle-piece'; ?>"></i>
                <h4><?php echo htmlspecialchars($widget['title']); ?></h4>
                <span class="widget-size"><?php echo ucfirst($widget['widget_size']); ?></span>
            </div>
            
            <p class="widget-description"><?php echo htmlspecialchars($widget['description']); ?></p>
            
            <div class="widget-meta">
                <span class="widget-type"><?php echo ucfirst($widget['widget_type']); ?></span>
                <?php if ($widget['is_system']): ?>
                <span class="system-badge">System</span>
                <?php endif; ?>
            </div>
            
            <div class="widget-actions">
                <?php if (!$isAdded && $widget['is_active']): ?>
                <button class="btn btn-sm btn-primary" onclick="addWidget(<?php echo $widget['id']; ?>, '<?php echo $widget['title']; ?>')">
                    <i class="fas fa-plus"></i> Add to Dashboard
                </button>
                <?php endif; ?>
                
                <?php if (!$widget['is_system']): ?>
                <a href="?toggle=<?php echo $widget['id']; ?>" class="btn btn-sm btn-outline" title="Toggle Status">
                    <i class="fas fa-power-off"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Current Dashboard Preview -->
<div class="dashboard-preview">
    <div class="preview-header">
        <h3>Your Current Dashboard</h3>
        <span class="widget-count"><?php echo count($userWidgets); ?> widgets</span>
    </div>
    
    <div class="dashboard-grid-container">
        <div class="grid-overlay">
            <div class="grid-lines"></div>
        </div>
        
        <div class="dashboard-grid" id="dashboardGrid">
            <?php foreach ($userWidgets as $uw): 
                $gridColumn = $uw['position_x'] + 1;
                $gridRow = $uw['position_y'] + 1;
            ?>
            <div class="dashboard-widget" 
                 data-widget-id="<?php echo $uw['widget_id']; ?>"
                 data-width="<?php echo $uw['width']; ?>"
                 data-height="<?php echo $uw['height']; ?>"
                 data-title="<?php echo htmlspecialchars($uw['title']); ?>"
                 style="grid-column: <?php echo $gridColumn; ?> / span <?php echo $uw['width']; ?>; 
                        grid-row: <?php echo $gridRow; ?> / span <?php echo $uw['height']; ?>;">
                
                <div class="widget-handle" title="Drag to move">
                    <i class="fas fa-grip-vertical"></i>
                </div>
                
                <?php if ($uw['widget_type'] !== 'system'): ?>
                <div class="resize-handle resize-se" title="Drag to resize"></div>
                <div class="resize-handle resize-sw"></div>
                <div class="resize-handle resize-ne"></div>
                <div class="resize-handle resize-nw"></div>
                <div class="resize-handle resize-n"></div>
                <div class="resize-handle resize-s"></div>
                <div class="resize-handle resize-e"></div>
                <div class="resize-handle resize-w"></div>
                <?php endif; ?>
                
                <div class="widget-content">
                    <div class="widget-title">
                        <i class="<?php echo $uw['icon'] ?: 'fas fa-chart-line'; ?>"></i>
                        <h4><?php echo htmlspecialchars($uw['title']); ?></h4>
                        <span class="widget-dimensions"><?php echo $uw['width']; ?>x<?php echo $uw['height']; ?></span>
                    </div>
                    <div class="widget-body">
                        <div class="placeholder-content">
                            <i class="fas fa-chart-bar"></i>
                            <p>Widget preview</p>
                        </div>
                    </div>
                </div>
                
                <div class="widget-footer">
                    <button class="btn-icon" onclick="configureWidget(<?php echo $uw['widget_id']; ?>)" title="Configure">
                        <i class="fas fa-cog"></i>
                    </button>
                    <button class="btn-icon" onclick="removeWidget(<?php echo $uw['widget_id']; ?>)" title="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($userWidgets)): ?>
            <div class="empty-dashboard">
                <i class="fas fa-plus-circle"></i>
                <h4>Your dashboard is empty</h4>
                <p>Add widgets from the list above to get started.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Widget Modal -->
<div class="modal" id="addWidgetModal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Widget to Dashboard</h3>
                <button class="close-modal" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addWidgetForm">
                    <input type="hidden" name="widget_id" id="add_widget_id">
                    
                    <div class="form-group">
                        <label>Widget Preview</label>
                        <div class="widget-preview" id="widgetPreview">
                            <i class="fas fa-chart-line"></i>
                            <span id="previewTitle">Widget Title</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="position">Position</label>
                        <div class="position-selector">
                            <button type="button" class="pos-btn" data-pos="left" data-align="left">
                                <i class="fas fa-arrow-left"></i>
                                <span>Left</span>
                            </button>
                            <button type="button" class="pos-btn" data-pos="center" data-align="center">
                                <i class="fas fa-arrows-alt-h"></i>
                                <span>Center</span>
                            </button>
                            <button type="button" class="pos-btn" data-pos="right" data-align="right">
                                <i class="fas fa-arrow-right"></i>
                                <span>Right</span>
                            </button>
                        </div>
                        <small>Choose where to place the widget</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="width">Width (columns)</label>
                            <select id="width" name="width" onchange="updatePreviewSize()">
                                <option value="1">1 Column - Small</option>
                                <option value="2" selected>2 Columns - Medium</option>
                                <option value="3">3 Columns - Large</option>
                                <option value="4">4 Columns - Full Width</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="height">Height (rows)</label>
                            <select id="height" name="height" onchange="updatePreviewSize()">
                                <option value="1">1 Row - Small</option>
                                <option value="2" selected>2 Rows - Medium</option>
                                <option value="3">3 Rows - Large</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="size-preview">
                        <div class="size-indicator">
                            <span class="size-label">Preview Size:</span>
                            <span class="size-value" id="sizeValue">2x2</span>
                        </div>
                        <div class="size-grid">
                            <div class="size-cell"></div><div class="size-cell"></div><div class="size-cell"></div><div class="size-cell"></div>
                            <div class="size-cell"></div><div class="size-cell"></div><div class="size-cell"></div><div class="size-cell"></div>
                            <div class="size-cell"></div><div class="size-cell"></div><div class="size-cell"></div><div class="size-cell"></div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Add to Dashboard</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Widget Configuration Modal -->
<div class="modal" id="configModal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Configure Widget</h3>
                <button class="close-modal" onclick="closeConfigModal()">&times;</button>
            </div>
            <div class="modal-body" id="configBody">
                <!-- Config form will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
let editMode = false;
let currentWidgetId = null;
let isResizing = false;
let startX, startY, startWidth, startHeight;
let sortableInstance = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initSortable();
    initResizeHandles();
    
    // Initialize position selector
    document.querySelectorAll('.pos-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.pos-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
    
    // Select first position by default (Left)
    const firstPos = document.querySelector('.pos-btn');
    if (firstPos) {
        firstPos.classList.add('selected');
    }
});

function initSortable() {
    const grid = document.getElementById('dashboardGrid');
    if (!grid) return;
    
    sortableInstance = new Sortable(grid, {
        animation: 150,
        handle: '.widget-handle',
        draggable: '.dashboard-widget',
        ghostClass: 'dragging',
        dragClass: 'dragging',
        onEnd: function(evt) {
            if (editMode) {
                updatePositions();
            }
        }
    });
    
    // Disable sortable initially
    sortableInstance.option('disabled', true);
}

function initResizeHandles() {
    document.querySelectorAll('.resize-handle').forEach(handle => {
        handle.addEventListener('mousedown', initResize);
    });
}

function initResize(e) {
    if (!editMode) return;
    
    e.preventDefault();
    isResizing = true;
    
    const widget = e.target.closest('.dashboard-widget');
    const rect = widget.getBoundingClientRect();
    
    startX = e.clientX;
    startY = e.clientY;
    startWidth = parseInt(widget.dataset.width);
    startHeight = parseInt(widget.dataset.height);
    
    widget.classList.add('resizing');
    
    document.addEventListener('mousemove', resize);
    document.addEventListener('mouseup', stopResize);
}

function resize(e) {
    if (!isResizing) return;
    
    const widget = document.querySelector('.dashboard-widget.resizing');
    if (!widget) return;
    
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;
    
    // Calculate new dimensions (based on grid cell size)
    const cellSize = 150; // Approximate cell size in pixels
    const newWidth = Math.max(1, Math.min(4, Math.round(startWidth + dx / cellSize)));
    const newHeight = Math.max(1, Math.min(3, Math.round(startHeight + dy / cellSize)));
    
    // Update widget style
    widget.style.gridColumn = `${parseInt(widget.style.gridColumn.split('/')[0])} / span ${newWidth}`;
    widget.style.gridRow = `${parseInt(widget.style.gridRow.split('/')[0])} / span ${newHeight}`;
    
    // Update data attributes
    widget.dataset.width = newWidth;
    widget.dataset.height = newHeight;
    
    // Update dimensions display
    const dimSpan = widget.querySelector('.widget-dimensions');
    if (dimSpan) {
        dimSpan.textContent = `${newWidth}x${newHeight}`;
    }
}

function stopResize() {
    if (!isResizing) return;
    
    isResizing = false;
    const widget = document.querySelector('.dashboard-widget.resizing');
    if (widget) {
        widget.classList.remove('resizing');
        saveWidgetSize(
            widget.dataset.widgetId,
            parseInt(widget.dataset.width),
            parseInt(widget.dataset.height)
        );
    }
    
    document.removeEventListener('mousemove', resize);
    document.removeEventListener('mouseup', stopResize);
}

function toggleEditMode() {
    editMode = !editMode;
    const editBar = document.getElementById('editModeBar');
    const btn = document.getElementById('editModeBtn');
    
    if (editMode) {
        editBar.style.display = 'flex';
        btn.innerHTML = '<i class="fas fa-check"></i> Exit Edit Mode';
        btn.classList.add('btn-success');
        btn.classList.remove('btn-outline');
        
        // Enable sortable
        if (sortableInstance) {
            sortableInstance.option('disabled', false);
        }
        
        // Show resize handles
        document.querySelectorAll('.resize-handle').forEach(handle => {
            handle.style.opacity = '1';
        });
    } else {
        editBar.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-edit"></i> Edit Layout';
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline');
        
        // Disable sortable
        if (sortableInstance) {
            sortableInstance.option('disabled', true);
        }
        
        // Hide resize handles
        document.querySelectorAll('.resize-handle').forEach(handle => {
            handle.style.opacity = '0';
        });
    }
}

function updatePositions() {
    const widgets = document.querySelectorAll('.dashboard-widget');
    const layout = [];
    
    widgets.forEach((widget, index) => {
        const col = Math.floor(index % 4);
        const row = Math.floor(index / 4);
        
        layout.push({
            widget_id: widget.dataset.widgetId,
            position_x: col,
            position_y: row,
            width: parseInt(widget.dataset.width),
            height: parseInt(widget.dataset.height)
        });
    });
    
    saveLayout(layout);
}

function saveLayout(layout) {
    const formData = new FormData();
    formData.append('action', 'save_layout');
    formData.append('layout', JSON.stringify(layout));
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Success', 'Layout saved successfully', 'success');
        }
    });
}

function saveWidgetSize(widgetId, width, height) {
    const formData = new FormData();
    formData.append('action', 'resize_widget');
    formData.append('widget_id', widgetId);
    formData.append('width', width);
    formData.append('height', height);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Widget resized');
        }
    });
}

function addWidget(widgetId, title) {
    document.getElementById('add_widget_id').value = widgetId;
    document.getElementById('previewTitle').textContent = title;
    document.getElementById('addWidgetModal').style.display = 'block';
    updatePreviewSize();
}

function closeAddModal() {
    document.getElementById('addWidgetModal').style.display = 'none';
    // Reset form
    document.getElementById('addWidgetForm').reset();
    // Reset position selection
    document.querySelectorAll('.pos-btn').forEach(btn => btn.classList.remove('selected'));
    const firstPos = document.querySelector('.pos-btn');
    if (firstPos) firstPos.classList.add('selected');
}

function updatePreviewSize() {
    const width = document.getElementById('width').value;
    const height = document.getElementById('height').value;
    document.getElementById('sizeValue').textContent = width + 'x' + height;
    
    // Update size grid
    const cells = document.querySelectorAll('.size-cell');
    cells.forEach((cell, index) => {
        const col = index % 4;
        const row = Math.floor(index / 4);
        if (col < width && row < height) {
            cell.classList.add('active');
        } else {
            cell.classList.remove('active');
        }
    });
}

// Handle form submission
document.getElementById('addWidgetForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const selectedPos = document.querySelector('.pos-btn.selected');
    const position = selectedPos ? selectedPos.dataset.pos : 'center';
    
    // Calculate position based on alignment
    let positionX = 0;
    const width = parseInt(document.getElementById('width').value);
    
    switch(position) {
        case 'left':
            positionX = 0;
            break;
        case 'center':
            // Center the widget (roughly)
            positionX = Math.floor((4 - width) / 2);
            break;
        case 'right':
            positionX = 4 - width;
            break;
        default:
            positionX = 0;
    }
    
    // Find first available row at the bottom
    const widgets = document.querySelectorAll('.dashboard-widget');
    let maxRow = 0;
    widgets.forEach(w => {
        const row = parseInt(w.style.gridRow.split('/')[0]);
        if (row > maxRow) maxRow = row;
    });
    const positionY = maxRow; // Place at the bottom
    
    const formData = new FormData();
    formData.append('widget_id', document.getElementById('add_widget_id').value);
    formData.append('position_x', positionX);
    formData.append('position_y', positionY);
    formData.append('width', width);
    formData.append('height', document.getElementById('height').value);
    formData.append('action', 'add_widget');
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    submitBtn.disabled = true;
    
    fetch('ajax/dashboard-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Success', 'Widget added successfully', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            alert('Error adding widget: ' + (data.error || 'Unknown error'));
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error: ' + error.message);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

function removeWidget(widgetId) {
    if (confirm('Remove this widget from your dashboard?')) {
        const formData = new FormData();
        formData.append('widget_id', widgetId);
        formData.append('action', 'remove_widget');
        
        fetch('ajax/dashboard-ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

function configureWidget(widgetId) {
    currentWidgetId = widgetId;
    const modal = document.getElementById('configModal');
    const body = document.getElementById('configBody');
    
    body.innerHTML = `
        <div class="config-form">
            <div class="form-group">
                <label for="widget_title">Widget Title</label>
                <input type="text" id="widget_title" class="form-control" value="Widget Settings">
            </div>
            <div class="form-group">
                <label for="refresh_interval">Refresh Interval (seconds)</label>
                <input type="number" id="refresh_interval" class="form-control" value="30" min="0">
            </div>
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" id="show_title" checked> Show Title
                </label>
            </div>
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" id="auto_refresh" checked> Auto Refresh
                </label>
            </div>
            <button class="btn btn-primary" onclick="saveWidgetConfig()">Save Settings</button>
        </div>
    `;
    
    modal.style.display = 'block';
}

function closeConfigModal() {
    document.getElementById('configModal').style.display = 'none';
}

function saveWidgetConfig() {
    // Save widget configuration
    showNotification('Success', 'Widget configuration saved', 'success');
    closeConfigModal();
}

function showNotification(title, message, type) {
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    notification.innerHTML = `
        <strong>${title}</strong>
        <p>${message}</p>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Re-initialize resize handles after any DOM changes
const observer = new MutationObserver(function(mutations) {
    initResizeHandles();
});

observer.observe(document.getElementById('dashboardGrid'), {
    childList: true,
    subtree: true
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>