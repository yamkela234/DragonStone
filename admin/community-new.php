<?php
// admin/community-new.php
session_start();

// Author/Admin gate
if (!isset($_SESSION['user_id'])) { header("Location: ../login.html"); exit; }
$role = strtolower($_SESSION['role'] ?? 'customer');
$isAdmin = (int)($_SESSION['is_admin'] ?? 0) === 1;
if (!$isAdmin && $role !== 'author') { http_response_code(403); exit('Forbidden'); }

// DB
$mysqli = new mysqli("localhost", "root", "", "dragonstone.db");
if ($mysqli->connect_error) { die("DB connection failed"); }

// Ensure table exists
$mysqli->query("
CREATE TABLE IF NOT EXISTS community_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  type ENUM('challenge','diy','success') NOT NULL DEFAULT 'diy',
  excerpt TEXT,
  content LONGTEXT,
  image_path VARCHAR(255),
  ecopoints INT DEFAULT 0,
  status ENUM('draft','published') DEFAULT 'published',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_posts_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Handle submit
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $uid     = (int)$_SESSION['user_id'];
  $title   = trim($_POST['title'] ?? '');
  $type    = $_POST['type'] ?? 'diy';
  $excerpt = trim($_POST['excerpt'] ?? '');
  $content = trim($_POST['content'] ?? '');
  $eco     = (int)($_POST['ecopoints'] ?? 0);
  $status  = in_array(($_POST['status'] ?? 'published'), ['draft','published']) ? $_POST['status'] : 'published';

  // Image upload (optional)
  $imageRelPath = null; // e.g. images/community/filename.png
  if (!empty($_FILES['cover']['name'])) {
    $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
    $mime = mime_content_type($_FILES['cover']['tmp_name']);
    if (isset($allowed[$mime])) {
      $ext = $allowed[$mime];
      // Safe filename
      $base = preg_replace('/[^a-zA-Z0-9-_]/','-', strtolower(pathinfo($_FILES['cover']['name'], PATHINFO_FILENAME)));
      if ($base === '') $base = 'cover';
      $newName = $base.'-'.time().'.'.$ext;

      // Ensure folder exists
      $uploadDir = realpath(__DIR__ . '/../images/community');
      if ($uploadDir === false) {
        // Try to create the folder if it doesn't exist
        @mkdir(__DIR__ . '/../images/community', 0775, true);
        $uploadDir = realpath(__DIR__ . '/../images/community');
      }

      $dest = $uploadDir . DIRECTORY_SEPARATOR . $newName;
      if (move_uploaded_file($_FILES['cover']['tmp_name'], $dest)) {
        $imageRelPath = 'images/community/' . $newName;
      } else {
        $notice = '❌ Failed to upload image.';
      }
    } else {
      $notice = '❌ Unsupported image type. Please upload PNG, JPG, or WEBP.';
    }
  }

  if ($title === '') {
    $notice = '❌ Title is required.';
  }

  if ($notice === '') {
    $stmt = $mysqli->prepare("
      INSERT INTO community_posts (user_id, title, type, excerpt, content, image_path, ecopoints, status)
      VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param("isssssis", $uid, $title, $type, $excerpt, $content, $imageRelPath, $eco, $status);
    if ($stmt->execute()) {
      header("Location: community.php?msg=created");
      exit;
    } else {
      $notice = "❌ Failed to create post: " . $stmt->error;
    }
    $stmt->close();
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Community Content • DragonStone</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f7f9fb; }
    .wrap { max-width: 980px; margin: 30px auto; }
    .card { border:0; box-shadow: 0 10px 22px rgba(18,38,63,.06); border-radius: 16px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h3 class="fw-bold mb-0">Create Community Content</h3>
      <div class="d-flex gap-2">
        <a href="community.php" class="btn btn-outline-secondary">Back</a>
      </div>
    </div>

    <?php if ($notice): ?>
      <div class="alert alert-info"><?=$notice?></div>
    <?php endif; ?>

    <div class="card p-4 bg-white">
      <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Title</label>
          <input name="title" class="form-control" placeholder="e.g. Natural Cleaning Solutions" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Type</label>
          <select name="type" class="form-select">
            <option value="challenge">Challenge</option>
            <option value="diy" selected>DIY Guide</option>
            <option value="success">Success Story</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">Excerpt</label>
          <textarea name="excerpt" class="form-control" rows="2" placeholder="Short summary shown on cards..."></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">Content</label>
          <textarea name="content" class="form-control" rows="7" placeholder="Full content for the article..."></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Cover Image (PNG/JPG/WEBP)</label>
          <input type="file" name="cover" class="form-control" accept=".png,.jpg,.jpeg,.webp">
          <div class="form-text">Will upload into <code>images/community/</code>.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label">EcoPoints</label>
          <input type="number" min="0" name="ecopoints" class="form-control" placeholder="e.g. 50">
        </div>

        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="published" selected>Published</option>
            <option value="draft">Draft</option>
          </select>
        </div>

        <div class="col-12">
          <button class="btn btn-success px-4">Create Post</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
