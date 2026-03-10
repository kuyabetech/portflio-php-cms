<?php
// admin/view-message.php
// View Single Message - FULLY RESPONSIVE

require_once dirname(__DIR__) . '/includes/init.php';
Auth::requireAuth();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$pageTitle = 'View Message';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => 'index.php'],
    ['title' => 'Messages', 'url' => 'messages.php'],
    ['title' => 'View Message']
];

// Mark as read
db()->update('contact_messages', ['is_read' => 1], 'id = :id', ['id' => $id]);

// Get message
$message = db()->fetch("SELECT * FROM contact_messages WHERE id = ?", [$id]);

if (!$message) {
    header('Location: messages.php');
    exit;
}

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply = sanitize($_POST['reply']);
    $subject = sanitize($_POST['subject']);
    
    // Send email
    $to = $message['email'];
    $headers = "From: " . getSetting('contact_email') . "\r\n";
    $headers .= "Reply-To: " . getSetting('contact_email') . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    $fullMessage = "Hello " . $message['name'] . ",\n\n";
    $fullMessage .= $reply . "\n\n";
    $fullMessage .= "Best regards,\n";
    $fullMessage .= getSetting('site_name') . " Team";
    
    if (mail($to, $subject, $fullMessage, $headers)) {
        db()->update('contact_messages', ['is_replied' => 1], 'id = :id', ['id' => $id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Reply sent successfully!'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to send reply. Please try again.'];
    }
    
    header('Location: view-message.php?id=' . $id);
    exit;
}

// Include header
require_once 'includes/header.php';
?>

<!-- Page Header -->
<div class="content-header">
    <h2>
        <i class="fas fa-envelope-open-text"></i> Message Details
    </h2>
    <div class="header-actions">
        <a href="messages.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Messages
        </a>
        <a href="?delete=<?php echo $message['id']; ?>" class="btn btn-danger" 
           onclick="return confirm('Are you sure you want to delete this message?')">
            <i class="fas fa-trash"></i> Delete
        </a>
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

<!-- Message View Container -->
<div class="message-view-container">
    <!-- Message Header -->
    <div class="message-header-card">
        <div class="message-subject">
            <h3><?php echo htmlspecialchars($message['subject'] ?: 'No Subject'); ?></h3>
            <div class="message-badges">
                <span class="status-badge <?php echo $message['is_read'] ? 'read' : 'unread'; ?>">
                    <i class="fas <?php echo $message['is_read'] ? 'fa-envelope-open' : 'fa-envelope'; ?>"></i>
                    <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                </span>
                <?php if (!empty($message['is_replied'])): ?>
                <span class="status-badge replied">
                    <i class="fas fa-reply"></i> Replied
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="message-date">
            <i class="far fa-calendar-alt"></i>
            <?php echo date('F j, Y', strtotime($message['created_at'])); ?>
            <span class="message-time">
                <i class="far fa-clock"></i>
                <?php echo date('h:i A', strtotime($message['created_at'])); ?>
            </span>
        </div>
    </div>

    <!-- Sender Information Grid -->
    <div class="sender-info-grid">
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-user"></i>
            </div>
            <div class="info-content">
                <span class="info-label">Name</span>
                <span class="info-value"><?php echo htmlspecialchars($message['name']); ?></span>
            </div>
        </div>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="info-content">
                <span class="info-label">Email</span>
                <a href="mailto:<?php echo $message['email']; ?>" class="info-value">
                    <?php echo htmlspecialchars($message['email']); ?>
                </a>
            </div>
        </div>
        
        <?php if (!empty($message['phone'])): ?>
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-phone"></i>
            </div>
            <div class="info-content">
                <span class="info-label">Phone</span>
                <a href="tel:<?php echo $message['phone']; ?>" class="info-value">
                    <?php echo htmlspecialchars($message['phone']); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($message['company'])): ?>
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-building"></i>
            </div>
            <div class="info-content">
                <span class="info-label">Company</span>
                <span class="info-value"><?php echo htmlspecialchars($message['company']); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($message['budget_range'])): ?>
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="info-content">
                <span class="info-label">Budget Range</span>
                <span class="info-value budget-badge"><?php echo htmlspecialchars($message['budget_range']); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-network-wired"></i>
            </div>
            <div class="info-content">
                <span class="info-label">IP Address</span>
                <span class="info-value ip-address"><?php echo $message['ip_address']; ?></span>
            </div>
        </div>
    </div>

    <!-- Message Body -->
    <div class="message-body-card">
        <h4><i class="fas fa-comment-dots"></i> Message</h4>
        <div class="message-content">
            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
        </div>
    </div>

    <!-- Quick Reply Section -->
    <div class="quick-reply-card" id="quick-reply">
        <div class="reply-header" onclick="toggleReplyForm()">
            <h4><i class="fas fa-reply"></i> Quick Reply</h4>
            <i class="fas fa-chevron-down toggle-icon" id="replyToggleIcon"></i>
        </div>
        
        <div class="reply-form-container" id="replyForm">
            <form method="POST" class="reply-form">
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" 
                           value="Re: <?php echo htmlspecialchars($message['subject'] ?: 'Contact Form Submission'); ?>" 
                           class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="reply">Your Reply</label>
                    <textarea id="reply" name="reply" rows="6" class="form-control" required 
                              placeholder="Write your reply here...">Dear <?php echo htmlspecialchars($message['name']); ?>,



Best regards,
<?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="send_reply" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Reply
                    </button>
                    <button type="button" class="btn btn-outline" onclick="toggleReplyForm()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ========================================
   VIEW MESSAGE PAGE - RESPONSIVE STYLES
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
}

/* Content Header */
.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.content-header h2 {
    font-size: 1.5rem;
    color: var(--dark);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 10px;
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
    font-size: 0.95rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
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

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239,68,68,0.2);
}

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 10px;
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
    font-size: 1.2rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
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

/* Message View Container */
.message-view-container {
    max-width: 1000px;
    margin: 0 auto;
}

/* Message Header Card */
.message-header-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-left: 4px solid var(--primary);
}

.message-subject {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
    gap: 15px;
}

.message-subject h3 {
    font-size: 1.3rem;
    color: var(--dark);
    margin: 0;
    word-break: break-word;
}

.message-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-badge.read {
    background: rgba(16,185,129,0.1);
    color: #10b981;
}

.status-badge.unread {
    background: rgba(37,99,235,0.1);
    color: var(--primary);
}

.status-badge.replied {
    background: rgba(124,58,237,0.1);
    color: #7c3aed;
}

.message-date {
    color: var(--gray-500);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.message-date i {
    margin-right: 5px;
}

.message-time {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Sender Info Grid */
.sender-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.info-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: transform 0.2s ease;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.info-icon {
    width: 45px;
    height: 45px;
    background: rgba(37,99,235,0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 1.2rem;
    flex-shrink: 0;
}

.info-content {
    flex: 1;
    min-width: 0;
}

.info-label {
    display: block;
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.info-value {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--dark);
    word-break: break-word;
}

.info-value a {
    color: var(--primary);
    text-decoration: none;
}

.info-value a:hover {
    text-decoration: underline;
}

.budget-badge {
    display: inline-block;
    padding: 3px 10px;
    background: rgba(16,185,129,0.1);
    color: #10b981;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.ip-address {
    font-family: monospace;
    background: var(--gray-100);
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-block;
}

/* Message Body Card */
.message-body-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.message-body-card h4 {
    font-size: 1.1rem;
    margin-bottom: 15px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-200);
}

.message-body-card h4 i {
    color: var(--primary);
}

.message-content {
    background: var(--gray-100);
    padding: 25px;
    border-radius: 10px;
    line-height: 1.8;
    font-size: 1rem;
    white-space: pre-wrap;
    word-break: break-word;
    color: var(--dark);
}

/* Quick Reply Card */
.quick-reply-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.reply-header {
    padding: 20px 25px;
    background: linear-gradient(135deg, #f8fafc, #ffffff);
    border-bottom: 2px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: background 0.2s ease;
}

.reply-header:hover {
    background: var(--gray-100);
}

.reply-header h4 {
    font-size: 1.1rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--dark);
}

.reply-header h4 i {
    color: var(--primary);
}

.toggle-icon {
    font-size: 1.2rem;
    color: var(--gray-500);
    transition: transform 0.3s ease;
}

.reply-header.active .toggle-icon {
    transform: rotate(180deg);
}

.reply-form-container {
    padding: 25px;
    display: block;
}

.reply-form {
    max-width: 800px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--gray-700);
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 150px;
    line-height: 1.6;
}

.form-actions {
    display: flex;
    gap: 15px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.form-actions .btn {
    min-width: 150px;
}

/* ========================================
   RESPONSIVE BREAKPOINTS
   ======================================== */

/* Tablet (768px - 1023px) */
@media (max-width: 1023px) {
    .sender-info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .message-subject {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Mobile Landscape (576px - 767px) */
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
    
    .sender-info-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .info-card {
        padding: 12px;
    }
    
    .message-header-card {
        padding: 20px;
    }
    
    .message-body-card {
        padding: 20px;
    }
    
    .message-content {
        padding: 20px;
        font-size: 0.95rem;
    }
    
    .reply-header {
        padding: 15px 20px;
    }
    
    .reply-form-container {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}

/* Mobile Portrait (up to 575px) */
@media (max-width: 575px) {
    .message-header-card {
        padding: 15px;
    }
    
    .message-subject h3 {
        font-size: 1.1rem;
    }
    
    .message-badges {
        width: 100%;
    }
    
    .status-badge {
        flex: 1;
        justify-content: center;
    }
    
    .message-date {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .info-card {
        flex-direction: column;
        text-align: center;
    }
    
    .info-icon {
        margin: 0 auto;
    }
    
    .info-content {
        text-align: center;
        width: 100%;
    }
    
    .message-body-card {
        padding: 15px;
    }
    
    .message-content {
        padding: 15px;
        font-size: 0.9rem;
    }
    
    .reply-header h4 {
        font-size: 1rem;
    }
}

/* Small Mobile (up to 375px) */
@media (max-width: 375px) {
    .header-actions {
        flex-direction: column;
    }
    
    .header-actions .btn {
        width: 100%;
    }
    
    .message-subject h3 {
        font-size: 1rem;
    }
    
    .status-badge {
        font-size: 0.75rem;
    }
    
    .info-value {
        font-size: 0.85rem;
    }
    
    .form-control {
        padding: 10px 12px;
        font-size: 0.9rem;
    }
}

/* Print Styles */
@media print {
    .header-actions,
    .quick-reply-card,
    .alert-close,
    .form-actions {
        display: none !important;
    }
    
    .message-view-container {
        max-width: 100%;
    }
    
    .message-header-card,
    .info-card,
    .message-body-card {
        box-shadow: none;
        border: 1px solid #ddd;
        break-inside: avoid;
    }
    
    .info-icon {
        background: none;
        color: black;
    }
}
</style>

<script>
// Toggle reply form
function toggleReplyForm() {
    const form = document.getElementById('replyForm');
    const icon = document.getElementById('replyToggleIcon');
    const header = document.querySelector('.reply-header');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        icon.style.transform = 'rotate(0deg)';
        header.classList.remove('active');
    } else {
        form.style.display = 'none';
        icon.style.transform = 'rotate(180deg)';
        header.classList.add('active');
    }
}

// Auto-resize textarea
const textarea = document.getElementById('reply');
if (textarea) {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
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

// Copy email to clipboard
function copyEmail(email) {
    navigator.clipboard.writeText(email).then(() => {
        alert('Email copied to clipboard!');
    });
}

// Format phone number as link
document.querySelectorAll('a[href^="tel:"]').forEach(link => {
    link.addEventListener('click', function(e) {
        // Optional: track click
        console.log('Phone number clicked:', this.href);
    });
});

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    // Start with reply form hidden on mobile?
    if (window.innerWidth <= 575) {
        const form = document.getElementById('replyForm');
        const icon = document.getElementById('replyToggleIcon');
        if (form && icon) {
            form.style.display = 'none';
            icon.style.transform = 'rotate(180deg)';
            document.querySelector('.reply-header').classList.add('active');
        }
    }
});
</script>

<?php
// Include footer
require_once 'includes/footer.php';
?>