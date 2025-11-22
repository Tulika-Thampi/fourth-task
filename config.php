<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'mysqlpass');
define('DB_NAME', 'content_sharing_db');

// Pagination settings
define('POSTS_PER_PAGE', 5);

// Security settings
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_TIMEOUT', 900); // 15 minutes in seconds

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_EDITOR', 'editor');
define('ROLE_USER', 'user');

// Create PDO connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . htmlspecialchars($e->getMessage()));
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to check user role
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Helper function to check if user is admin or editor
function canEditPosts() {
    return isset($_SESSION['user_role']) && 
           ($_SESSION['user_role'] === ROLE_ADMIN || $_SESSION['user_role'] === ROLE_EDITOR);
}

// Helper function to redirect
function redirect($url) {
    header("Location: " . htmlspecialchars($url));
    exit();
}

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Helper function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Helper function to validate password strength
function isValidPassword($password) {
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// Helper function to get password strength message
function getPasswordStrengthMessage() {
    return "Password must be at least 8 characters, contain at least one uppercase letter and one number.";
}

// Helper function to log failed login attempts
function logFailedLoginAttempt($email) {
    $_SESSION['login_attempts'][$email] = isset($_SESSION['login_attempts'][$email]) ? 
                                           $_SESSION['login_attempts'][$email] + 1 : 1;
    $_SESSION['login_attempt_time'][$email] = time();
}

// Helper function to check login attempt limit
function isLoginAttemptLimited($email) {
    if (!isset($_SESSION['login_attempts'][$email])) {
        return false;
    }
    
    $attempts = $_SESSION['login_attempts'][$email];
    $time = $_SESSION['login_attempt_time'][$email];
    
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        if (time() - $time < LOGIN_ATTEMPT_TIMEOUT) {
            return true;
        } else {
            unset($_SESSION['login_attempts'][$email]);
            unset($_SESSION['login_attempt_time'][$email]);
            return false;
        }
    }
    
    return false;
}

// Helper function to reset login attempts
function resetLoginAttempts($email) {
    unset($_SESSION['login_attempts'][$email]);
    unset($_SESSION['login_attempt_time'][$email]);
}
?>