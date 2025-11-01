<?php
// admin/community-edit.php
require __DIR__ . '/_guard.php';

$USER_ID   = (int)($_SESSION['user_id'] ?? 0);
$IS_ADMIN  = (int)($_SESSION['is_admin'] ?? 0);
$ROLE      = strtolower($_SESSION['role'] ?? 'customer');

// load if editing
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post = [
  'id'=>0,'user_id'=>$USER_ID,'type'=>'guide','status'=>'draft',
  'title'=>'','slug'=>'','excerpt'=>'','content'=>'','thumbnail'=>''
];
if ($id>0) {
  $rs = $mysqli->query("SELECT * FROM community_posts WHERE id={$id} LIMIT 1");
  if ($rs && $row=$rs->fetch_assoc()) {
    // authors can only open their own
    if ($IS_ADMIN===1 || (int)$row['user_id']===$USER_ID) {
      $post = $row;
    } else {
      http_response_code(403); exit('Forbidden');
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $post['id']? 'Edit' : 'Create' ?> Community Content</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../css/admin.css">
</head>
<body>
<div class="d-flex">
  <aside class="admin-sidebar p-3 border-end">
    <div class="d-flex align-items-center mb-4">
      <span class="brand-badge me-2">DS</span>
      <div><div class="fw-semibold">DragonStone Admin</div><small class="text-muted">Sustainable Living</small></div>
    </div>
    <ul class="nav flex-column gap-1">
      <li class="nav-item"><a class="nav-link" href="community.php"><i class="bi bi-people me-2"></i>Community</a></li>
    </ul>
  </aside>

  <main class="flex-grow-1">
    <div class="admin-topbar d-flex align-items-center justify-content-between px-4 border-bottom">
      <div class="d-flex align-items-center gap-2"><i class="bi bi-pencil text-success"></i>
        <span class="fw-semibold"><?= $post['id']? 'Edit' : 'Create' ?> Content</span>
      </div>
      <a href="community.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="p-4">
      <form action="community-save.php" method="post" enctype="multipart/form-data" class="card rounded-4 shadow-sm p-3 p-md-4">
        <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" value="<?= htmlspecialchars($post['title']) ?>" required>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Type</label>
            <select class="form-select" name="type" required>
              <option value="challenge" <?= $post['type']==='challenge'?'selected':'' ?>>Challenge</option>
              <option value="guide" <?= $post['type']==='guide'?'selected':'' ?>>DIY Guide</option>
              <option value="success" <?= $post['type']==='success'?'selected':'' ?>>Success Story</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Status</label>
            <select class="form-select" name="status" required>
              <option value="draft" <?= $post['status']==='draft'?'selected':'' ?>>Draft</option>
              <option value="published" <?= $post['status']==='published'?'selected':'' ?>>Published</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Excerpt</label>
            <textarea class="form-control" name="excerpt" rows="2"><?= htmlspecialchars($post['excerpt']) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Content</label>
            <textarea class="form-control" name="content" rows="10" required><?= htmlspecialchars($post['content']) ?></textarea>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Thumbnail (PNG/JPG)</label>
            <input type="file" class="form-control" name="thumb">
            <?php if ($post['thumbnail']): ?>
              <div class="small text-muted mt-1">Current: <?= htmlspecialchars($post['thumbnail']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button class="btn btn-success" type="submit"><i class="bi bi-save me-1"></i>Save</button>
          <a class="btn btn-outline-secondary" href="community.php">Cancel</a>
        </div>
      </form>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
