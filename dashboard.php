<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

try {
    // Get user's posts
    $stmt = $conn->prepare("
        SELECT posts.*, COUNT(comments.id) as comment_count
        FROM posts 
        LEFT JOIN comments ON posts.id = comments.post_id
        WHERE posts.user_id = ?
        GROUP BY posts.id
        ORDER BY posts.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_posts = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM posts WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $post_count = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $user_posts = [];
    $post_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Your Dashboard</h1>
            <p>Manage your stories</p>
        </div>
        
        <div class="nav">
            <a href="index.php">Back to Stories</a>
            <?php if (canEditPosts()): ?>
                <a href="create.php">Create New Story</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <span class="stat-number"><?php echo $post_count; ?></span>
                <span class="stat-label">Your Stories</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Role: <strong><?php echo ucfirst($_SESSION['user_role']); ?></strong></span>
            </div>
        </div>
        
        <?php if (!empty($user_posts)): ?>
            <div class="posts-grid">
                <h2 style="grid-column: 1 / -1; color: #a855f7; margin-bottom: 10px;">Your Stories</h2>
                <?php foreach ($user_posts as $post): ?>
                    <div class="post-card">
                        <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                        <div class="post-meta">
                            <span><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                            <span>•</span>
                            <span class="status-badge <?php echo $post['status']; ?>">
                                <?php echo ucfirst($post['status']); ?>
                            </span>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 200))); ?>...</p>
                        
                        <div class="post-actions">
                            <a href="edit.php?id=<?php echo (int)$post['id']; ?>" class="btn btn-small btn-edit">Edit</a>
                            <a href="delete.php?id=<?php echo (int)$post['id']; ?>" class="btn btn-small btn-delete" 
                               onclick="return confirm('Are you sure?')">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No stories yet</h3>
                <p>Create your first story to get started!</p>
                <?php if (canEditPosts()): ?>
                    <a href="create.php" class="btn">Create Story</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>