<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$search = sanitizeInput($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * POSTS_PER_PAGE;

try {
    if (!empty($search)) {
        // Search query with prepared statement
        $search_param = "%$search%";
        $count_stmt = $conn->prepare("
            SELECT COUNT(*) as total FROM posts 
            WHERE status = 'published' AND (title LIKE ? OR content LIKE ?)
        ");
        $count_stmt->execute([$search_param, $search_param]);
        $total_posts = $count_stmt->fetch()['total'];
        
        $stmt = $conn->prepare("
            SELECT posts.*, users.username FROM posts 
            JOIN users ON posts.user_id = users.id 
            WHERE posts.status = 'published' AND (posts.title LIKE ? OR posts.content LIKE ?)
            ORDER BY posts.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$search_param, $search_param, POSTS_PER_PAGE, $offset]);
    } else {
        // Regular query
        $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM posts WHERE status = 'published'");
        $count_stmt->execute();
        $total_posts = $count_stmt->fetch()['total'];
        
        $stmt = $conn->prepare("
            SELECT posts.*, users.username FROM posts 
            JOIN users ON posts.user_id = users.id 
            WHERE posts.status = 'published'
            ORDER BY posts.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([POSTS_PER_PAGE, $offset]);
    }
    
    $posts = $stmt->fetchAll();
    $total_pages = ceil($total_posts / POSTS_PER_PAGE);
    $start_post = $offset + 1;
    $end_post = min($offset + POSTS_PER_PAGE, $total_posts);
    
} catch (PDOException $e) {
    $posts = [];
    $total_posts = 0;
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Stories - Home</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Shared Stories</h1>
            <p>Discover and share amazing tales</p>
        </div>
        
        <div class="top-section">
            <div class="user-info">
                Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                <?php if (hasRole(ROLE_ADMIN)): ?>
                    <span class="role-badge admin">Admin</span>
                <?php elseif (hasRole(ROLE_EDITOR)): ?>
                    <span class="role-badge editor">Editor</span>
                <?php endif; ?>
            </div>
            <div class="nav">
                <?php if (canEditPosts()): ?>
                    <a href="create.php">Create New Post</a>
                <?php endif; ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
        
        <div class="search-container">
            <form method="GET" action="" class="search-form">
                <input 
                    type="text" 
                    name="search" 
                    class="search-input" 
                    placeholder="Search posts by title or content..." 
                    value="<?php echo $search; ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>
            
            <?php if (!empty($search)): ?>
                <div class="search-info">
                    <strong>Searching for:</strong> "<?php echo $search; ?>" 
                    <a href="index.php" class="clear-search">Clear Search</a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($search)): ?>
            <div class="stats-container">
                <div class="stat-card">
                    <span class="stat-number"><?php echo $total_posts; ?></span>
                    <span class="stat-label">Published Stories</span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($posts)): ?>
            <div class="posts-grid">
                <?php foreach ($posts as $post): ?>
                    <div class="post-card">
                        <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                        <div class="post-meta">
                            <span>By <?php echo htmlspecialchars($post['username']); ?></span>
                            <span>•</span>
                            <span><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars(substr($post['content'], 0, 300))); ?><?php echo strlen($post['content']) > 300 ? '...' : ''; ?></p>
                        
                        <?php if ($post['user_id'] == $_SESSION['user_id'] || hasRole(ROLE_ADMIN)): ?>
                            <div class="post-actions">
                                <a href="edit.php?id=<?php echo (int)$post['id']; ?>" class="btn btn-small btn-edit">Edit</a>
                                <a href="delete.php?id=<?php echo (int)$post['id']; ?>" class="btn btn-small btn-delete" 
                                   onclick="return confirm('Are you sure you want to delete this post?')">Delete</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                    <?php else: ?>
                        <span class="disabled">Previous</span>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="disabled">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                        if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                        <?php endif;
                    endfor;
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="disabled">...</span>';
                        }
                        echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                    <?php else: ?>
                        <span class="disabled">Next</span>
                    <?php endif; ?>
                </div>
                
                <div class="pagination-info">
                    Showing <?php echo $start_post; ?>-<?php echo $end_post; ?> of <?php echo $total_posts; ?> stories
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <h3><?php echo !empty($search) ? 'No results found' : 'No stories yet'; ?></h3>
                <p><?php echo !empty($search) ? 'Try a different search term' : 'Be the first to create a story!'; ?></p>
                <?php if (!empty($search)): ?>
                    <a href="index.php" class="btn">View All Stories</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>