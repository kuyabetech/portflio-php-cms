<?php
// reset-password.php - Run this once and DELETE it immediately after use

require_once 'includes/init.php';

// Only allow access from localhost for security
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    die('Access denied');
}

echo "<h2>Password Reset Tool</h2>";

// Check if users table exists
try {
    $tables = db()->fetchAll("SHOW TABLES");
    echo "<p>✅ Database connected</p>";
} catch (Exception $e) {
    die("<p>❌ Database error: " . $e->getMessage() . "</p>");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? 'admin';
    $password = $_POST['password'] ?? 'Admin@123';
    
    // Hash the password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if user exists
    $user = db()->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $username]);
    
    if ($user) {
        // Update existing user
        db()->update('users', ['password_hash' => $hash], 'id = :id', ['id' => $user['id']]);
        echo "<p style='color:green'>✅ Password updated for user: $username</p>";
    } else {
        // Create new user
        db()->insert('users', [
            'username' => $username,
            'email' => $username . '@kverify.com',
            'password_hash' => $hash,
            'full_name' => 'Administrator',
            'role' => 'admin'
        ]);
        echo "<p style='color:green'>✅ New user created: $username</p>";
    }
    
    echo "<p>New password: <strong>$password</strong></p>";
    echo "<p><a href='admin/login.php'>Go to Login Page</a></p>";
}
?>

<form method="POST">
    <h3>Reset Admin Password</h3>
    <div style="margin-bottom: 10px;">
        <label>Username:</label>
        <input type="text" name="username" value="admin" required>
    </div>
    <div style="margin-bottom: 10px;">
        <label>New Password:</label>
        <input type="text" name="password" value="Admin@123" required>
    </div>
    <button type="submit">Reset Password</button>
</form>

<p><strong>⚠️ IMPORTANT: Delete this file after use!</strong></p>