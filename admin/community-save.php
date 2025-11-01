<?php
// admin/community-save.php
require __DIR__ . '/_guard.php';

$USER_ID  = (int)($_SESSION['user_id'] ?? 0);
$IS_ADMIN = (int)($_SESSION['is_admin'] ?? 0);

$id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title    = trim($_POST['title'] ?? '');
$type     = trim($_POST['type'] ?? 'guide');
$status   = trim($_POST['status'] ?? 'draft');
$excerpt  = trim($_POST['excerpt'] ?? '');
$content  = trim($_POST['content'] ?? '');

if ($title === '' || $content === '') {
  header('Location: community.php'); exit;
}

// prepare thumbnail upload (optional)
$thumbName = null;
if (!empty($_FILES['thumb']['name']) && $_FILES['thumb']['error'] === UPLOAD_ERR_OK) {
  $ext = strtolower(pathinfo($_FILES['thumb']['name'], PATHINFO_EXTENSION));
  if (in_array($ext, ['png','jpg','jpeg'])) {
    $thumbName = uniqid('community_',true).'.'.$ext;
    $dest = dirname(__DIR__).'/images/community/'.$thumbName;
    @move_uploaded_file($_FILES['thumb']['tmp_name'], $dest);
  }
}

if ($id > 0) {
  // update — ensure ownership for authors
  $ownerCheck = $IS_ADMIN===1 ? "" : " AND user_id={$USER_ID} ";
  $setThumb = $thumbName ? ", thumbnail='".$mysqli->real_escape_string($thumbName)."'" : "";
  $sql = "UPDATE community_posts
          SET title='".$mysqli->real_escape_string($title)."',
              type='".$mysqli->real_escape_string($type)."',
              status='".$mysqli->real_escape_string($status)."',
              excerpt='".$mysqli->real_escape_string($excerpt)."',
              content='".$mysqli->real_escape_string($content)."'
              {$setThumb},
              updated_at=NOW()
          WHERE id={$id} {$ownerCheck}
          LIMIT 1";
  $mysqli->query($sql);
} else {
  // insert
  $thumbCol = $thumbName ? ", thumbnail" : "";
  $thumbVal = $thumbName ? ", '".$mysqli->real_escape_string($thumbName)."'" : "";
  $sql = "INSERT INTO community_posts
          (user_id, type, status, title, excerpt, content{$thumbCol}, created_at, updated_at)
          VALUES
          ({$USER_ID},
           '".$mysqli->real_escape_string($type)."',
           '".$mysqli->real_escape_string($status)."',
           '".$mysqli->real_escape_string($title)."',
           '".$mysqli->real_escape_string($excerpt)."',
           '".$mysqli->real_escape_string($content)."'{$thumbVal},
           NOW(), NOW())";
  $mysqli->query($sql);
}

header('Location: community.php');
