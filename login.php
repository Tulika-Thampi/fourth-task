<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Invalid email address';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    } else {
        // Check login attempts
        if (isLoginAttemptLimited($email)) {
            $error = 'Too many login attempts. Please try again in 15 minutes.';
        } else {
            try {
                $stmt = $conn->prepare("SELECT id, username, email, password, role, is_active FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->rowCount() === 1) {
                    $user = $stmt->fetch();
                    
                    if (!$user['is_active']) {
                        $error = 'Your account has been disabled';
                    } elseif (password_verify($password, $user['password'])) {
                        // Reset login attempts on success
                        resetLoginAttempts($email);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];

                        // After setting session variables
                        logAuditEvent($conn, 'user_login', "User {$user['username']} logged in");
                        
                        redirect('index.php');
                    } else {
                        logFailedLoginAttempt($email);
                        $error = 'Invalid email or password';
                    }
                } else {
                    logFailedLoginAttempt($email);
                    $error = 'Invalid email or password';
                }
            } catch (PDOException $e) {
                $error = 'Login failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome Back</h1>
            <p>Login to continue sharing</p>
        </div>
        
        <div class="nav">
            <a href="register.php">Need an account? Register</a>
        </div>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" novalidate>
            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email"
                    name="email" 
                    value="<?php echo $email; ?>"
                    required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password"
                    name="password" 
                    required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
    </div>
</body>
</html>
