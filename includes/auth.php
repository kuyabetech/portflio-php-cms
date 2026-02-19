<?php
// includes/auth.php
// Authentication Functions

// Prevent multiple inclusions
if (!defined('AUTH_LOADED')) {

    // Load dependencies in correct order
    require_once 'config.php';
    require_once 'functions.php';
    require_once 'database.php';

    class Auth {
        
        // Start session if not started
        public static function init() {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }
        
        // Attempt login
        public static function login($username, $password) {
            self::init();
            
            try {
                $user = db()->fetch("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    db()->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $user['id']]);
                    
                    // Log login attempt
                    self::logLogin($user['id']);
                    
                    return true;
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
            }
            
            return false;
        }
        
        // Log login attempt
        private static function logLogin($userId) {
            try {
                // Create login_logs table if it doesn't exist
                db()->query("CREATE TABLE IF NOT EXISTS login_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )");
                
                db()->insert('login_logs', [
                    'user_id' => $userId,
                    'ip_address' => getClientIP(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                // Silently fail if table doesn't exist
                error_log("Login log error: " . $e->getMessage());
            }
        }
        
        // Check if user is logged in
        public static function check() {
            self::init();
            
            if (!isset($_SESSION['user_id'])) {
                return false;
            }
            
            // Check session timeout
            if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
                self::logout();
                return false;
            }
            
            return true;
        }
        
        // Get current user
        public static function user() {
            if (!self::check()) {
                return null;
            }
            
            try {
                return db()->fetch("SELECT id, username, email, full_name, profile_image, role FROM users WHERE id = ?", [$_SESSION['user_id']]);
            } catch (Exception $e) {
                return null;
            }
        }
        
        // Require authentication
        public static function requireAuth() {
            if (!self::check()) {
                redirect('/admin/login.php', 'Please login to continue', 'error');
            }
        }
        
        // Logout
        public static function logout() {
            self::init();
            
            // Clear session
            $_SESSION = array();
            
            // Destroy session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            // Destroy session
            session_destroy();
        }
        
        // Generate CSRF token
        public static function generateCSRF() {
            self::init();
            
            if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
                $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            }
            return $_SESSION[CSRF_TOKEN_NAME];
        }
        
        // Verify CSRF token
        public static function verifyCSRF($token) {
            self::init();
            
            if (!isset($_SESSION[CSRF_TOKEN_NAME]) || $token !== $_SESSION[CSRF_TOKEN_NAME]) {
                die('Invalid CSRF token');
            }
            return true;
        }
        
        // Hash password
        public static function hashPassword($password) {
            return password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
        }
    }

    // Initialize auth
    Auth::init();

    define('AUTH_LOADED', true);
}
?>