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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';

    // Don't sanitize content as strictly, but trim it
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
            $stmt = $conn->prepare("
                INSERT INTO posts (title, content, user_id, status) 
                VALUES (?, ?, ?, 'published')
            ");

            $stmt->execute([$title, $content, $_SESSION['user_id']]);

            // After INSERT
            logAuditEvent($conn, 'post_create', "Created post: $title");

            $success = 'Story created successfully! Redirecting...';
            header("refresh:2;url=index.php");
        } catch (PDOException $e) {
            $error = 'Failed to create story. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Story</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Create New Story</h1>
            <p>Share your thoughts and ideas</p>
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

        <form method="POST" action="" novalidate>
            <div class="form-group">
                <label for="title">Title</label>
                <input
                    type="text"
                    id="title"
                    name="title"
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
                    required></textarea>
                <small>Minimum 10, maximum 100,000 characters</small>
            </div>

            <button type="submit" class="btn">Create Story</button>
        </form>
    </div>
</body>

</html>
