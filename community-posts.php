<?php
session_start();

// DB
$mysqli = new mysqli("localhost", "root", "", "dragonstone.db");
if ($mysqli->connect_error) {
  http_response_code(500);
  die("Database error.");
}

// Helpers
function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  $res = $db->query("SHOW TABLES LIKE '{$name}'");
  return $res && $res->num_rows > 0;
}
function col_exists(mysqli $db, string $t, string $c): bool {
  $t = $db->real_escape_string($t);
  $c = $db->real_escape_string($c);
  $res = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return $res && $res->num_rows > 0;
}
function resolve_cover(string $title, ?string $dbThumb): ?string {
  $baseDir = __DIR__ . '/images/community/';
  $baseUrl = 'images/community/';
  $exists = function($f) use($baseDir){ return is_file($baseDir.$f); };

  if ($dbThumb && $exists($dbThumb)) return $baseUrl.$dbThumb;

  $san = preg_replace('/[^A-Za-z0-9 \-\.\(\)]/u','', $title);
  $san = preg_replace('/\s+/',' ', $san);

  foreach ([$title.'.png',$title.'.jpg',$san.'.png',$san.'.jpg'] as $f) {
    if ($exists($f)) return $baseUrl.$f;
  }
  return null;
}
// very light sanitizer: allow basic formatting tags
function safe_content(string $html): string {
  $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><h2><h3><blockquote><code><pre><a>';
  $out = strip_tags($html, $allowed);

  // force target=_blank and rel on links
  $out = preg_replace_callback(
    '/<a\s+([^>]+)>/i',
    function($m){
      $attrs = $m[1];
      // ensure target
      if (!preg_match('/\btarget\s*=/i', $attrs)) $attrs .= ' target="_blank"';
      // ensure rel
      if (!preg_match('/\brel\s*=/i', $attrs)) $attrs .= ' rel="noopener noreferrer"';
      return '<a '.$attrs.'>';
    }, $out
  );
  return $out;
}

// ───────────────── Load current post
if (!table_exists($mysqli,'community_posts')) {
  http_response_code(404);
  die("Community not available.");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); die("Post not found."); }

// Column probing (schema-safe)
$has = fn($c)=> col_exists($mysqli,'community_posts',$c);

$cols = ['id','title'];
foreach (['type','status','excerpt','content','thumbnail','user_id','updated_at','created_at'] as $c) {
  if ($has($c)) $cols[] = $c;
}
$select = implode(',', $cols);

// Only published if status exists; else fetch freely
$where = "id={$id}";
if ($has('status')) $where .= " AND status='published'";

$sql = "SELECT {$select} FROM community_posts WHERE {$where} LIMIT 1";
$res = $mysqli->query($sql);
$post = ($res && $res->num_rows===1) ? $res->fetch_assoc() : null;

if (!$post) { http_response_code(404); die("Post not found."); }

// Prepare fields
$title   = $post['title'] ?? 'Untitled';
$type    = strtolower($post['type'] ?? '');
$status  = $post['status'] ?? '';
$excerpt = $post['excerpt'] ?? '';
$contentRaw = $post['content'] ?? '';
$thumb   = $post['thumbnail'] ?? null;
$updated = $post['updated_at'] ?? ($post['created_at'] ?? null);

$typeLabel = ['challenge'=>'Challenge','guide'=>'DIY Guide','success'=>'Success Story'];
$ecoPts    = ['challenge'=>250,'guide'=>50,'success'=>100];
$badge     = $typeLabel[$type] ?? ucfirst($type ?: 'Post');
$points    = $ecoPts[$type] ?? null;

$cover = resolve_cover($title, $thumb);
$niceDate = $updated ? date('F j, Y', strtotime($updated)) : '';

// For “More Highlights” (related)
$related = [];
if ($type && $has('type')) {
  $typeEsc = $mysqli->real_escape_string($type);
  $whereR = "id <> {$id} AND type='{$typeEsc}'";
  if ($has('status')) $whereR .= " AND status='published'";
  $order = $has('updated_at') ? 'updated_at' : ($has('created_at') ? 'created_at' : 'id');
  $sqlR = "SELECT id, title".($has('thumbnail')?', thumbnail':'')." FROM community_posts WHERE {$whereR} ORDER BY {$order} DESC LIMIT 3";
  $resR = $mysqli->query($sqlR);
  while ($resR && $row = $resR->fetch_assoc()) $related[] = $row;
} else {
  // fallback: latest others
  $whereR = "id <> {$id}";
  if ($has('status')) $whereR .= " AND status='published'";
  $order = $has('updated_at') ? 'updated_at' : ($has('created_at') ? 'created_at' : 'id');
  $sqlR = "SELECT id, title".($has('thumbnail')?', thumbnail':'')." FROM community_posts WHERE {$whereR} ORDER BY {$order} DESC LIMIT 3";
  $resR = $mysqli->query($sqlR);
  while ($resR && $row = $resR->fetch_assoc()) $related[] = $row;
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title) ?> • DragonStone Community</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <style>
    body{background:#fff;}
    .page-hero{background:#f7fcf9;}
    .badge-type{background:#16a34a;color:#fff;border-radius:999px;padding:.45rem .9rem;font-weight:700}
    .badge-eco{background:#eafff2;color:#065f46;border:1px solid #bbf7d0;border-radius:999px;padding:.45rem .9rem;font-weight:700}
    .cover-wrap{aspect-ratio:16/9;background:#eef5f1;border-radius:1rem;overflow:hidden}
    .cover-wrap img{width:100%;height:100%;object-fit:cover}
    .post-content p{line-height:1.7;margin-bottom:1rem}
    .post-content h2,.post-content h3{margin-top:1.5rem;margin-bottom:.75rem}
    .post-content ul, .post-content ol{padding-left:1.25rem}
    .rel-card{border:1px solid #e6edf5;border-radius:1rem;overflow:hidden;background:#fff}
    .rel-thumb{aspect-ratio:16/9;background:#f2f6f9}
    .rel-thumb img{width:100%;height:100%;object-fit:cover}
  </style>
</head>
<body>

<!-- Simple top bar (optional – reuse your site navbar if you prefer) -->
<nav class="navbar navbar-light bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">DragonStone</a>
    <a class="btn btn-outline-success rounded-pill" href="community.php">All Community</a>
  </div>
</nav>

<section class="page-hero py-4">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-3">
        <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Home</a></li>
        <li class="breadcrumb-item"><a href="community.php" class="text-decoration-none">Community</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($title) ?></li>
      </ol>
    </nav>

    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
      <span class="badge-type"><?= htmlspecialchars($badge) ?></span>
      <?php if ($points): ?>
        <span class="badge-eco">+<?= (int)$points ?> EcoPoints</span>
      <?php endif; ?>
      <?php if ($niceDate): ?>
        <span class="text-muted ms-1">Updated <?= htmlspecialchars($niceDate) ?></span>
      <?php endif; ?>
    </div>

    <h1 class="display-6 fw-bold mb-3"><?= htmlspecialchars($title) ?></h1>

    <div class="cover-wrap mb-4">
      <?php if ($cover): ?>
        <img src="<?= htmlspecialchars($cover) ?>" alt="">
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="py-4">
  <div class="container">
    <div class="row g-4">
      <div class="col-12 col-lg-8">
        <article class="post-content">
          <?php
            $content = $contentRaw ?: $excerpt;
            echo $content ? safe_content($content) : '<p class="text-muted">No content available.</p>';
          ?>
        </article>
      </div>
      <div class="col-12 col-lg-4">
        <div class="p-3 border rounded-3">
          <h5 class="fw-bold">Share</h5>
          <div class="d-flex gap-2">
            <?php
              $shareUrl = urlencode((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
              $shareTxt = urlencode($title.' • DragonStone Community');
            ?>
            <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url=<?= $shareUrl ?>&text=<?= $shareTxt ?>"><i class="bi bi-twitter-x"></i> X</a>
            <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>"><i class="bi bi-facebook"></i> Facebook</a>
            <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="https://www.linkedin.com/shareArticle?mini=true&url=<?= $shareUrl ?>&title=<?= $shareTxt ?>"><i class="bi bi-linkedin"></i> LinkedIn</a>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($related)): ?>
      <hr class="my-5">
      <h3 class="fw-bold mb-3">More Highlights</h3>
      <div class="row g-4">
        <?php foreach ($related as $r):
          $rid   = (int)$r['id'];
          $rtitle= $r['title'] ?? 'Untitled';
          $rthumb= isset($r['thumbnail']) ? $r['thumbnail'] : null;
          $rimg  = resolve_cover($rtitle, $rthumb);
        ?>
        <div class="col-12 col-md-6 col-xl-4">
          <a href="community-post.php?id=<?= $rid ?>" class="rel-card text-decoration-none d-block">
            <div class="rel-thumb">
              <?php if ($rimg): ?><img src="<?= htmlspecialchars($rimg) ?>" alt=""><?php endif; ?>
            </div>
            <div class="p-3">
              <div class="fw-semibold"><?= htmlspecialchars($rtitle) ?></div>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="mt-5">
      <a href="community.php" class="btn btn-outline-success rounded-pill px-4">Back to Community</a>
    </div>
  </div>
</section>

<footer class="py-4 border-top">
  <div class="container small text-muted">
    © <?= date('Y') ?> DragonStone • Community
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
