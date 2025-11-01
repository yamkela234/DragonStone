<?php

session_start();

/* ========= Top-bar data ========= */
$cart_count = 0;
$user_name  = '';

if (isset($_SESSION['user_id'])) {
    $connTop = new mysqli("localhost", "root", "", "dragonstone.db");
    if (!$connTop->connect_error) {
        $user_id  = (int)$_SESSION['user_id'];
        $is_admin = (int)$_SESSION['is_admin'];

        if ($is_admin === 0) {
            $res = $connTop->query("SELECT COALESCE(SUM(quantity),0) AS total FROM cart_items WHERE user_id=$user_id");
            $row = $res ? $res->fetch_assoc() : null;
            $cart_count = (int)($row['total'] ?? 0);
        }

        $user_res = $connTop->query("SELECT name FROM users WHERE id=$user_id");
        if ($user_res && $user_res->num_rows > 0) {
            $user_row  = $user_res->fetch_assoc();
            $user_name = $user_row['name'] ?? '';
        }
        $connTop->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>DragonStone • Sustainable Living Made Simple</title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
  <link href="https://fonts.cdnfonts.com/css/geist-sans" rel="stylesheet">
  <link href="https://fonts.cdnfonts.com/css/geist-mono" rel="stylesheet">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- Custom CSS -->
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white custom-navbar sticky-top shadow-sm">
  <div class="container container-xl">
    <a class="navbar-brand" href="index.php" aria-label="DragonStone homepage">
      <span class="logo-text">DragonStone</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mx-auto gap-lg-3">
        <li class="nav-item"><a class="nav-link active" href="products.php">Products</a></li>
        <li class="nav-item"><a class="nav-link" href="community.php">Community</a></li>
        <li class="nav-item"><a class="nav-link" href="impact.php">Impact</a></li>
        <li class="nav-item"><a class="nav-link" href="ecopoints.php">EcoPoints</a></li>
      </ul>

      <div class="d-flex align-items-center gap-3">
        <?php if (isset($_SESSION['user_id'])): ?>
          <span class="nav-user fw-semibold text-success">Hello, <?= htmlspecialchars($user_name) ?></span>
          <a href="backend/logout.php" class="nav-icon" title="Logout" aria-label="Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        <?php else: ?>
          <a href="login.html" class="nav-icon" title="Account" aria-label="Account">
            <i class="bi bi-person"></i>
          </a>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['is_admin'] === 0): ?>
          <a href="cart.php" class="nav-icon position-relative" title="Cart" aria-label="Cart">
            <i class="bi bi-cart3 fs-5"></i>
            <?php if ((int)$cart_count > 0): ?>
              <span class="cart-badge"><?= (int)$cart_count ?></span>
            <?php endif; ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- HERO SECTION -->
<section class="hero">
  <div class="hero-content text-center">
    <h1 class="hero-title">Sustainable Living<br class="d-none d-md-block"> Made Simple</h1>
    <p class="hero-sub">
      Join the movement making eco-conscious choices accessible, stylish, and community-driven.
      Transform your lifestyle with products that care for you and the planet.
    </p>
    <div class="d-flex justify-content-center gap-3 flex-wrap">
      <a href="#featured-eco" class="btn btn-pill btn-eco">Shop Sustainable</a>
      <a href="impact.php" class="btn btn-pill btn-ghost">Learn Our Impact</a>
    </div>
  </div>
</section>

<!-- FEATURED ECO-PRODUCTS -->
<?php
$fc = new mysqli("localhost", "root", "", "dragonstone.db");
$featured = null;
if (!$fc->connect_error) {
  $featured = $fc->query("
    SELECT id, name, category, price, stock, image, carbon_footprint, tag_label
    FROM products
    WHERE name IN (
      'Bamboo Kitchen Utensil Set',
      'Organic Cotton Towel Set',
      'Plant-Based Cleaning Kit',
      'Recycled Glass Storage Jars'
    )
    ORDER BY FIELD(name,
      'Bamboo Kitchen Utensil Set',
      'Organic Cotton Towel Set',
      'Plant-Based Cleaning Kit',
      'Recycled Glass Storage Jars'
    )
    LIMIT 4
  ");
}
?>
<?php if ($featured && $featured->num_rows > 0): ?>
<section id="featured-eco" class="featured-eco py-5" style="background:#f5fef7;">
  <div class="container text-center">
    <h2 class="fw-bold mb-2" style="letter-spacing:.2px;">Featured Eco-Products</h2>
    <p class="text-muted mb-5">
      Discover our most loved sustainable products that make a real difference for you<br class="d-none d-md-inline">
      and the planet
    </p>

    <div class="row g-4 justify-content-center">
      <?php while ($p = $featured->fetch_assoc()): ?>
        <div class="col-12 col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden position-relative">
            <?php if (!empty($p['tag_label'])): ?>
              <span class="badge bg-success position-absolute m-2 rounded-pill px-3 py-2">
                <?= htmlspecialchars($p['tag_label']) ?>
              </span>
            <?php endif; ?>

            <a href="product.php?id=<?= (int)$p['id'] ?>">
              <img
                src="images/products/<?= htmlspecialchars($p['image'] ?: 'placeholder.jpg') ?>"
                alt="<?= htmlspecialchars($p['name']) ?>"
                class="card-img-top">
            </a>

            <div class="card-body text-start">
              <h6 class="fw-semibold mb-1">
                <a class="link-body-emphasis text-decoration-none" href="product.php?id=<?= (int)$p['id'] ?>">
                  <?= htmlspecialchars($p['name']) ?>
                </a>
              </h6>

              <p class="mb-1 text-muted"><?= htmlspecialchars($p['category']) ?></p>
              <small class="d-block mb-2 text-muted">Stock: <?= (int)$p['stock'] ?></small>

              <p class="fs-5 fw-bold text-success mb-3">R <?= number_format((float)$p['price'], 2) ?></p>

              <?php if (!empty($p['carbon_footprint'])): ?>
                <div class="badge bg-light text-success rounded-pill px-3 py-2 mb-3">
                  Saves <?= number_format((float)$p['carbon_footprint'], 1) ?> kg CO₂
                </div>
              <?php endif; ?>

              <!-- Add to Cart button BELOW CO₂ badge -->
              <div class="d-flex justify-content-start">
                <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['is_admin'] === 0): ?>
                  <form action="backend/add-to-cart.php" method="post" class="m-0">
                    <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="quantity" value="1">
                    <button class="btn btn-success rounded-pill px-4 py-2" type="submit">
                      Add to Cart
                    </button>
                  </form>
                <?php else: ?>
                  <a class="btn btn-outline-success rounded-pill px-4 py-2" href="login.html">
                    Login to Buy
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

    <!-- View All Products Button -->
    <div class="mt-5">
      <a href="products.php" class="btn btn-success px-5 py-2 rounded-pill fw-semibold">
        View All Products
      </a>
    </div>
  </div>
</section>
<?php endif; ?>
<?php if ($fc && !$fc->connect_error) { $fc->close(); } ?>

<!-- COMMUNITY HIGHLIGHTS -->
<?php
$ch = new mysqli("localhost", "root", "", "dragonstone.db");

/* helpers */
$tbl_ok = function(mysqli $db, string $t){
  $r=$db->query("SHOW TABLES LIKE '".$db->real_escape_string($t)."'"); return $r && $r->num_rows>0;
};
$col_ok = function(mysqli $db, string $t, string $c){
  $r=$db->query("SHOW COLUMNS FROM `".$db->real_escape_string($t)."` LIKE '".$db->real_escape_string($c)."'"); return $r && $r->num_rows>0;
};
$trim_excerpt = function($txt,$len=140){
  $t = trim(strip_tags((string)$txt));
  if (mb_strlen($t) <= $len) return $t;
  $cut = mb_substr($t,0,$len);
  $sp = mb_strrpos($cut,' ');
  if ($sp!==false) $cut = mb_substr($cut,0,$sp);
  return $cut.'…';
};
/* resolve cover: thumbnail OR "<Title>.png/.jpg" in /images/community */
$cover = function(string $title, ?string $thumb) {
  $dir  = __DIR__ . '/images/community/';
  $urlB = 'images/community/';

  $ok = function($f) use ($dir){ return is_file($dir.$f); };

  if ($thumb && $ok($thumb)) return $urlB.$thumb;

  $san = preg_replace('/[^A-Za-z0-9 \-\.\(\)]/u','', $title);
  $san = preg_replace('/\s+/',' ', $san);

  foreach ([$title.'.png',$title.'.jpg',$san.'.png',$san.'.jpg'] as $f) {
    if ($ok($f)) return $urlB.$f;
  }
  return null;
};

if (!$ch->connect_error && $tbl_ok($ch,'community_posts')) {
  $hasStatus  = $col_ok($ch,'community_posts','status');
  $hasType    = $col_ok($ch,'community_posts','type');
  $hasExcerpt = $col_ok($ch,'community_posts','excerpt');
  $hasContent = $col_ok($ch,'community_posts','content');
  $hasThumb   = $col_ok($ch,'community_posts','thumbnail');
  $hasUpd     = $col_ok($ch,'community_posts','updated_at');
  $hasCre     = $col_ok($ch,'community_posts','created_at');

  $sel = ['id','title'];
  if ($hasType)    $sel[]='type';
  if ($hasExcerpt) $sel[]='excerpt';
  if ($hasContent) $sel[]='content';
  if ($hasThumb)   $sel[]='thumbnail';
  if ($hasStatus)  $sel[]='status';
  if ($hasUpd)     $sel[]='updated_at';
  if ($hasCre)     $sel[]='created_at';
  $selSQL = implode(',', $sel);

  $w = [];
  if ($hasStatus) $w[] = "status='published'";
  $WHERE = $w ? 'WHERE '.implode(' AND ',$w) : '';

  $order = $hasUpd ? 'updated_at' : ($hasCre ? 'created_at' : 'id');
  $sql   = "SELECT {$selSQL} FROM community_posts {$WHERE} ORDER BY {$order} DESC LIMIT 3";
  $res   = $ch->query($sql);

  $cards = [];
  while ($res && $r=$res->fetch_assoc()) $cards[]=$r;

  $typeLabel = ['challenge'=>'Challenge','guide'=>'DIY Guide','success'=>'Success Story'];
  $ecoPts    = ['challenge'=>250,'guide'=>50,'success'=>100];
?>
<section class="community-highlights py-5" style="background:#f7fcf9;">
  <div class="container text-center">
    <h2 class="fw-bold mb-2" style="letter-spacing:.2px;color:#0f172a">Community Highlights</h2>
    <p class="text-muted mb-5">Be inspired by our amazing community members making real change happen every day</p>

    <?php if (!$cards): ?>
      <div class="text-muted">No community content yet. Check back soon!</div>
    <?php else: ?>
      <div class="row g-4 justify-content-center">
        <?php foreach ($cards as $c):
          $id    = (int)$c['id'];
          $title = $c['title'] ?? 'Untitled';
          $type  = strtolower($c['type'] ?? '');
          $badge = $typeLabel[$type] ?? ucfirst($type ?: 'Post');
          $pts   = $ecoPts[$type] ?? null;
          $thumbField = $hasThumb ? ($c['thumbnail'] ?? null) : null;
          $img   = $cover($title, $thumbField);
          $desc  = $hasExcerpt && !empty($c['excerpt'])
                    ? $c['excerpt']
                    : ($hasContent ? $trim_excerpt($c['content'] ?? '') : '');
          $href  = "community-post.php?id={$id}";
        ?>
        <div class="col-12 col-md-6 col-xl-4">
          <a href="<?= htmlspecialchars($href) ?>"
             class="card h-100 text-decoration-none shadow-sm overflow-hidden rounded-4"
             style="border:1px solid #e6edf5;background:#ffffff">
            <div class="position-relative" style="aspect-ratio:16/9;background:#f2f6f9;">
              <?php if ($img): ?>
                <img src="<?= htmlspecialchars($img) ?>" alt="" class="w-100 h-100" style="object-fit:cover;">
              <?php endif; ?>
              <span class="position-absolute top-0 start-0 m-3 px-3 py-2 rounded-pill fw-semibold"
                    style="background:#16a34a;color:#fff;font-size:.95rem;">
                <?= htmlspecialchars($badge) ?>
              </span>
              <?php if ($pts): ?>
                <span class="position-absolute bottom-0 end-0 m-3 px-3 py-2 rounded-pill fw-semibold"
                      style="background:#eafff2;color:#065f46;border:1px solid #bbf7d0;font-size:.95rem;">
                  +<?= (int)$pts ?> EcoPoints
                </span>
              <?php endif; ?>
            </div>
            <div class="p-3 text-start">
              <h3 class="h4 fw-bold mb-2" style="color:#0f172a"><?= htmlspecialchars($title) ?></h3>
              <?php if ($desc): ?><p class="mb-0 text-muted"><?= htmlspecialchars($desc) ?></p><?php endif; ?>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="d-flex flex-wrap justify-content-center gap-3 mt-5">
        <a href="community.php" class="btn btn-success px-4 py-2 rounded-pill fw-semibold">Join Community</a>
        <a href="community-submit.php" class="btn btn-outline-success px-4 py-2 rounded-pill fw-semibold">Share Your Story</a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php
}
if ($ch && !$ch->connect_error) $ch->close();
?>

<?php include 'partials/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
