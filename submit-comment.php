<?php
// submit-comment.php
// Handle blog comment submissions - FIXED WITH CORRECT URL FORMAT

require_once 'includes/init.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug log
error_log("Comment submission started");

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Not a POST request");
    header('Location: ' . BASE_URL . '/blog');
    exit;
}

// Get form data
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$website = isset($_POST['website']) ? trim($_POST['website']) : '';
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

// Debug log
error_log("Comment data: post_id=$post_id, name=$name, email=$email");

// Initialize errors array
$errors = [];

// Validate post exists
if ($post_id <= 0) {
    $errors[] = 'Invalid post';
    error_log("Invalid post ID: $post_id");
    header('Location: ' . BASE_URL . '/blog');
    exit;
}

// Get post details for redirect
$post = null;
try {
    $post = db()->fetch("SELECT id, title, slug FROM blog_posts WHERE id = ? AND status = 'published'", [$post_id]);
    if (!$post) {
        $errors[] = 'Post not found';
        error_log("Post not found for ID: $post_id");
        header('Location: ' . BASE_URL . '/blog');
        exit;
    }
    error_log("Found post: " . $post['title'] . " with slug: " . $post['slug']);
} catch (Exception $e) {
    error_log("Database error checking post: " . $e->getMessage());
    $errors[] = 'Database error';
    header('Location: ' . BASE_URL . '/blog');
    exit;
}

// Validate name
if (empty($name)) {
    $errors[] = 'Name is required';
} elseif (strlen($name) < 2) {
    $errors[] = 'Name must be at least 2 characters';
} elseif (strlen($name) > 100) {
    $errors[] = 'Name must be less than 100 characters';
}

// Validate email
if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address';
}

// Validate website (if provided)
if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
    $errors[] = 'Please enter a valid website URL';
}

// Validate comment
if (empty($comment)) {
    $errors[] = 'Comment is required';
} elseif (strlen($comment) < 5) {
    $errors[] = 'Comment must be at least 5 characters';
} elseif (strlen($comment) > 2000) {
    $errors[] = 'Comment must be less than 2000 characters';
}

// If there are errors, store them in session and redirect back
if (!empty($errors)) {
    error_log("Comment validation errors: " . implode(", ", $errors));
    $_SESSION['comment_errors'] = $errors;
    $_SESSION['comment_data'] = [
        'name' => $name,
        'email' => $email,
        'website' => $website,
        'comment' => $comment
    ];
    
    // Redirect back to the post using the correct URL format
    $redirect_url = BASE_URL . '/index.php?url=blog/' . $post['slug'] . '#comment-form';
    error_log("Redirecting with errors to: " . $redirect_url);
    header('Location: ' . $redirect_url);
    exit;
}

// Check for spam (basic honeypot)
if (!empty($_POST['honeypot'])) {
    error_log("Spam detected - honeypot filled");
    // Silently redirect - don't show error to bot
    $redirect_url = BASE_URL . '/index.php?url=blog/' . $post['slug'] . '#comments';
    header('Location: ' . $redirect_url);
    exit;
}

// Rate limiting - check comments from same IP in last hour
$ip = $_SERVER['REMOTE_ADDR'];
$oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));

try {
    $recentComments = db()->fetch(
        "SELECT COUNT(*) as count FROM blog_comments WHERE ip_address = ? AND created_at > ?",
        [$ip, $oneHourAgo]
    );
    
    if ($recentComments && $recentComments['count'] >= 5) {
        error_log("Rate limit exceeded for IP: $ip");
        $_SESSION['comment_error'] = 'Too many comments from your IP address. Please wait before posting again.';
        $redirect_url = BASE_URL . '/index.php?url=blog/' . $post['slug'] . '#comment-form';
        header('Location: ' . $redirect_url);
        exit;
    }
} catch (Exception $e) {
    error_log("Rate limiting error: " . $e->getMessage());
    // Continue anyway - rate limiting is optional
}

// Prepare comment data
$commentData = [
    'post_id' => $post_id,
    'parent_id' => $parent_id,
    'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
    'email' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
    'website' => !empty($website) ? htmlspecialchars($website, ENT_QUOTES, 'UTF-8') : null,
    'comment' => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'is_approved' => 0 // Comments need moderation by default
];

// Save comment to database
try {
    error_log("Attempting to save comment for post ID: $post_id");
    
    // Check if comments table exists
    $tables = db()->fetchAll("SHOW TABLES LIKE 'blog_comments'");
    if (empty($tables)) {
        error_log("blog_comments table does not exist");
        
        // Create the table if it doesn't exist
        db()->query("
            CREATE TABLE IF NOT EXISTS blog_comments (
                id INT PRIMARY KEY AUTO_INCREMENT,
                post_id INT NOT NULL,
                parent_id INT DEFAULT 0,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                website VARCHAR(255),
                comment TEXT NOT NULL,
                is_approved BOOLEAN DEFAULT FALSE,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
            )
        ");
        error_log("Created blog_comments table");
    }
    
    $result = db()->insert('blog_comments', $commentData);
    
    if ($result) {
        error_log("Comment saved successfully with ID: $result");
        
        // Send notification email to admin (optional)
        $admin_email = getSetting('contact_email');
        if ($admin_email && filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $subject = "New Comment on: " . $post['title'];
            $message = "A new comment has been posted on your blog post.\n\n";
            $message .= "Post: " . $post['title'] . "\n";
            $message .= "Name: " . $name . "\n";
            $message .= "Email: " . $email . "\n";
            $message .= "Comment: " . $comment . "\n\n";
            $message .= "View comment: " . BASE_URL . "/admin/comments.php\n";
            
            @mail($admin_email, $subject, $message, "From: " . getSetting('site_name') . " <noreply@" . $_SERVER['HTTP_HOST'] . ">");
        }
        
        $_SESSION['comment_success'] = 'Thank you for your comment! It will be displayed after moderation.';
    } else {
        error_log("Failed to save comment - insert returned false");
        $_SESSION['comment_error'] = 'Failed to save comment. Please try again.';
    }
} catch (Exception $e) {
    error_log("Comment submission exception: " . $e->getMessage());
    $_SESSION['comment_error'] = 'An error occurred. Please try again later.';
}

// Redirect back to the post using the correct URL format
$redirect_url = BASE_URL . '/index.php?url=blog/' . $post['slug'] . '#comments';
error_log("Final redirect to: " . $redirect_url);
header('Location: ' . $redirect_url);
exit;
?>