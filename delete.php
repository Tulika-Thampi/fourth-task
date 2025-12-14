<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$post_id = (int)($_GET['id'] ?? 0);

if ($post_id > 0) {
    try {
        // Check if user owns the post or is admin
        $stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);

        if ($stmt->rowCount() > 0) {
            $post = $stmt->fetch();

            if ($post['user_id'] == $_SESSION['user_id'] || hasRole(ROLE_ADMIN)) {
                $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
                $stmt->execute([$post_id]);
            }
        }
        // After DELETE
        logAuditEvent($conn, 'post_delete', "Deleted post ID: $post_id");
    } catch (PDOException $e) {
        // Silently fail
    }
}

redirect('index.php');
