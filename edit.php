<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (!canEditPosts()) {
    redirect('index.php');
}

$error = '';
$success = '';
$post = null;

$post_id = (int)($_GET['id'] ?? 0);

if ($post_id <= 0) {
    redirect('index.php');
}

try {
    // Check if user owns the post or is admin
    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    
    if ($stmt->rowCount() === 0) {
        redirect('index.php');
    }
    
    $post = $stmt->fetch();
    
    // Check authorization
    if ($post['user_id'] != $_SESSION['user_id'] && !hasRole(ROLE_ADMIN)) {
        redirect('index.php');
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = sanitizeInput($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $content = trim($content);
        
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Title is required';
        } elseif (strlen($title) > 255) {
            $errors[] = 'Title must not exceed 255 characters';
        }
        
        if (empty($content)) {
            $errors[] = 'Content is required';
        } elseif (strlen($content) < 10) {
            $errors[] = 'Content must be at least 10 characters';
        } elseif (strlen($content) > 100000) {
            $errors[] = 'Content must not exceed 100,000 characters';
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            try {
                $stmt = $conn->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $content, $post_id]);
                
                $success = 'Story updated successfully! Redirecting...';
                $post['title'] = $title;
                $post['content'] = $content;
                header("refresh:2;url=index.php");
            } catch (PDOException $e) {
                $error = 'Failed to update story. Please try again.';
            }
        }
    }
    
} catch (PDOException $e) {
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Story</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit Story</h1>
            <p>Update your story</p>
        </div>
        
        <div class="nav">
            <a href="index.php">Back to Stories</a>
            <a href="logout.php">Logout</a>
        </div>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($post): ?>
            <form method="POST" action="" novalidate>
                <div class="form-group">
                    <label for="title">Title</label>
                    <input 
                        type="text" 
                        id="title"
                        name="title" 
                        value="<?php echo htmlspecialchars($post['title']); ?>"
                        maxlength="255"
                        required>
                    <small>Maximum 255 characters</small>
                </div>
                
                <div class="form-group">
                    <label for="content">Content</label>
                    <textarea 
                        id="content"
                        name="content" 
                        minlength="10"
                        maxlength="100000"
                        required><?php echo htmlspecialchars($post['content']); ?></textarea>
                    <small>Minimum 10, maximum 100,000 characters</small>
                </div>
                
                <button type="submit" class="btn">Update Story</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>