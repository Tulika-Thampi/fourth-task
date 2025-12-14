<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$post_id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($post_id <= 0) {
    redirect('index.php');
}

try {
    // Get post with author info
    $stmt = $conn->prepare("
        SELECT posts.*, users.username 
        FROM posts 
        JOIN users ON posts.user_id = users.id 
        WHERE posts.id = ? AND posts.status = 'published'
    ");
    $stmt->execute([$post_id]);
    
    if ($stmt->rowCount() === 0) {
        redirect('index.php');
    }
    
    $post = $stmt->fetch();
    
    // Get comments
    $stmt = $conn->prepare("
        SELECT comments.*, users.username 
        FROM comments 
        JOIN users ON comments.user_id = users.id 
        WHERE comments.post_id = ? 
        ORDER BY comments.created_at DESC
    ");
    $stmt->execute([$post_id]);
    $comments = $stmt->fetchAll();
    
    // Handle comment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
        $comment_content = trim($_POST['comment_content'] ?? '');
        
        if (empty($comment_content)) {
            $error = 'Comment cannot be empty';
        } elseif (strlen($comment_content) < 3) {
            $error = 'Comment must be at least 3 characters';
        } elseif (strlen($comment_content) > 5000) {
            $error = 'Comment must not exceed 5,000 characters';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO comments (post_id, user_id, content) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$post_id, $_SESSION['user_id'], $comment_content]);
                
                logAuditEvent($conn, 'comment_create', "Added comment on post ID: $post_id");
                
                $success = 'Comment added successfully!';
                // Refresh comments
                $stmt = $conn->prepare("
                    SELECT comments.*, users.username 
                    FROM comments 
                    JOIN users ON comments.user_id = users.id 
                    WHERE comments.post_id = ? 
                    ORDER BY comments.created_at DESC
                ");
                $stmt->execute([$post_id]);
                $comments = $stmt->fetchAll();
                
            } catch (PDOException $e) {
                $error = 'Failed to add comment. Please try again.';
            }
        }
    }
    
    // Handle comment deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        
        if ($comment_id > 0) {
            // Check if user owns the comment or is admin
            $stmt = $conn->prepare("SELECT user_id FROM comments WHERE id = ?");
            $stmt->execute([$comment_id]);
            
            if ($stmt->rowCount() > 0) {
                $comment = $stmt->fetch();
                
                if ($comment['user_id'] == $_SESSION['user_id'] || hasRole(ROLE_ADMIN)) {
                    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
                    $stmt->execute([$comment_id]);
                    
                    logAuditEvent($conn, 'comment_delete', "Deleted comment ID: $comment_id");
                    
                    $success = 'Comment deleted successfully!';
                    // Refresh comments
                    $stmt = $conn->prepare("
                        SELECT comments.*, users.username 
                        FROM comments 
                        JOIN users ON comments.user_id = users.id 
                        WHERE comments.post_id = ? 
                        ORDER BY comments.created_at DESC
                    ");
                    $stmt->execute([$post_id]);
                    $comments = $stmt->fetchAll();
                }
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
    <title><?php echo htmlspecialchars($post['title']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($post['title']); ?></h1>
            <div class="post-meta">
                <span>By <?php echo htmlspecialchars($post['username']); ?></span>
                <span>•</span>
                <span><?php echo date('F j, Y \a\t g:i A', strtotime($post['created_at'])); ?></span>
            </div>
        </div>
        
        <div class="nav">
            <a href="index.php">← Back to Stories</a>
            <?php if ($post['user_id'] == $_SESSION['user_id'] || hasRole(ROLE_ADMIN)): ?>
                <a href="edit.php?id=<?php echo $post_id; ?>">Edit Post</a>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="post-content">
            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
        </div>
        
        <div class="comments-section">
            <h2>Comments (<?php echo count($comments); ?>)</h2>
            
            <form method="POST" class="comment-form">
                <div class="form-group">
                    <label for="comment_content">Add a comment</label>
                    <textarea 
                        id="comment_content"
                        name="comment_content" 
                        rows="4"
                        minlength="3"
                        maxlength="5000"
                        placeholder="Share your thoughts..."
                        required></textarea>
                    <small>3-5,000 characters</small>
                </div>
                <button type="submit" name="add_comment" class="btn">Post Comment</button>
            </form>
            
            <div class="comments-list">
                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $comment): ?>
                        <div class="comment-card">
                            <div class="comment-header">
                                <strong><?php echo htmlspecialchars($comment['username']); ?></strong>
                                <span class="comment-date">
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?>
                                </span>
                            </div>
                            <div class="comment-content">
                                <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                            </div>
                            <?php if ($comment['user_id'] == $_SESSION['user_id'] || hasRole(ROLE_ADMIN)): ?>
                                <form method="POST" class="comment-delete-form">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                    <button 
                                        type="submit" 
                                        name="delete_comment" 
                                        class="btn-delete-comment"
                                        onclick="return confirm('Delete this comment?')">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-comments">No comments yet. Be the first to comment!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
