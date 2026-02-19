<?php
// submit-comment.php
// Handle comment submissions

require_once 'includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = (int)$_POST['post_id'];
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $website = sanitize($_POST['website'] ?? '');
    $comment = sanitize($_POST['comment']);
    $parent_id = (int)($_POST['parent_id'] ?? 0);
    
    // Validate
    $errors = [];
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (empty($comment)) $errors[] = 'Comment is required';
    
    if (empty($errors)) {
        db()->insert('blog_comments', [
            'post_id' => $post_id,
            'parent_id' => $parent_id,
            'name' => $name,
            'email' => $email,
            'website' => $website,
            'comment' => $comment,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]);
        
        $_SESSION['comment_success'] = true;
    } else {
        $_SESSION['comment_errors'] = $errors;
        $_SESSION['comment_data'] = $_POST;
    }
    
    // Redirect back to post
    $post = db()->fetch("SELECT slug FROM blog_posts WHERE id = ?", [$post_id]);
    header('Location: blog/' . $post['slug'] . '#comments');
    exit;
}