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

/* ========= DB ========= */
$conn = new mysqli("localhost", "root", "", "dragonstone.db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

/* ========= Filters ========= */
/* Accept BOTH ?category=... (footer) and ?cat=... (sidebar) */
$catParam = isset($_GET['category']) ? $_GET['category'] : (isset($_GET['cat']) ? $_GET['cat'] : '');
$cat   = trim((string)$catParam);
$price = isset($_GET['price']) ? trim($_GET['price']) : ''; // 'under-250','250-500','500-1000','over-1000','all'
$sort  = isset($_GET['sort'])  ? trim($_GET['sort'])  : 'featured';

$where = [];

/* Category filter (use TRIM in SQL so values match exactly even if stored with spaces) */
if ($cat !== '' && strtolower($cat) !== 'all') {
  $safe = $conn->real_escape_string($cat);
  $where[] = "TRIM(category) = '{$safe}'";
}

/* Price (Rands) */
$minPrice = null; $maxPrice = null;
switch ($price) {
  case 'under-250':  $maxPrice = 250; break;
  case '250-500':    $minPrice = 250; $maxPrice = 500; break;
  case '500-1000':   $minPrice = 500; $maxPrice = 1000; break;
  case 'over-1000':  $minPrice = 1000; break;
  // 'all' or '' = no price filter
}
if (!is_null($minPrice)) $where[] = "price >= " . (float)$minPrice;
if (!is_null($maxPrice)) $where[] = "price <= " . (float)$maxPrice;

/* Sort */
switch ($sort) {
  case 'newest':     $orderBy = "id DESC"; break;
  case 'price-asc':  $orderBy = "price ASC"; break;
  case 'price-desc': $orderBy = "price DESC"; break;
  case 'featured':
  default:           $orderBy = "featured DESC, id DESC"; break;
}

$whereSql = count($where) ? ("WHERE " . implode(" AND ", $where)) : "";

/* Counts & Categories (TRIM to avoid duplicates with trailing spaces) */
$countRes = $conn->query("SELECT COUNT(*) AS c FROM products {$whereSql}");
$totalCount = ($countRes && $countRes->num_rows) ? (int)$countRes->fetch_assoc()['c'] : 0;

$cats = [];
$cRes = $conn->query("
  SELECT TRIM(category) AS category, COUNT(*) AS cnt
  FROM products
  WHERE TRIM(category) <> ''
  GROUP BY TRIM(category)
  ORDER BY category ASC
");
if ($cRes) while ($r = $cRes->fetch_assoc()) $cats[] = $r;

/* Products */
$sql = "
  SELECT id, name, TRIM(category) AS category, price, stock, image, carbon_footprint,
         IFNULL(tag_label,'') AS tag_label,
         IFNULL(featured,0)   AS featured
  FROM products
  {$whereSql}
  ORDER BY {$orderBy}
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>All Products • DragonStone</title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
  <link href="https://fonts.cdnfonts.com/css/geist-sans" rel="stylesheet">
  <link href="https://fonts.cdnfonts.com/css/geist-mono" rel="stylesheet">

  <!-- Bootstrap CSS & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/styles.css" />

  <style>
    /* Hero banner */
    .products-hero {
      background: url('images/hero.jpg') center/cover no-repeat;
      min-height: 260px; position: relative; display: grid; place-items: center; color: #fff;
    }
    .products-hero::after { content: ""; position: absolute; inset: 0; background: rgba(0,0,0,.35); }
    .products-hero .inner { position: relative; z-index: 1; text-align: center; }
    .products-hero h1 { font-weight: 800; letter-spacing: .5px; }
    .products-hero p { opacity: .95; }

    /* Sidebar */
    .filter-card { border:0; box-shadow:0 6px 18px rgba(18,38,63,.06); border-radius:18px; }
    .filter-card .list-group-item { border:0; border-radius:10px!important; padding:.6rem .85rem; cursor:pointer; }
    .filter-card .list-group-item.active { background:#e8f7ee; color:#198754; font-weight:600; }

    /* Product cards like Featured */
    .prod-card { border:0; box-shadow:0 10px 22px rgba(18,38,63,.07); border-radius:18px; overflow:hidden; }
    .prod-tag { position:absolute; top:.65rem; left:.65rem; }
    .prod-img { object-fit:cover; width:100%; height:220px; background:#f9fbf9; }
    @media (min-width:992px){ .prod-img{ height:240px; } }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-light bg-white custom-navbar sticky-top shadow-sm">
  <div class="container container-xl">
    <a class="navbar-brand" href="index.php" aria-label="DragonStone homepage">
      <span class="logo-text">DragonStone</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
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
            <?php if ((int)$cart_count > 0): ?><span class="cart-badge"><?= (int)$cart_count ?></span><?php endif; ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="products-hero">
  <div class="inner container">
    <h1 class="display-5 mb-2">Sustainable Products</h1>
    <p class="lead mb-0">Discover eco-friendly solutions for every aspect of your life</p>
  </div>
</section>

<!-- CONTENT -->
<section class="py-5">
  <div class="container">
    <div class="row g-4">
      <!-- SIDEBAR -->
      <div class="col-12 col-lg-3">
        <div class="card filter-card p-3">
          <h6 class="fw-bold ps-2 pt-2 pb-2">Filter Products</h6>

          <!-- Categories -->
          <div class="mb-3">
            <div class="small text-muted ps-2 mb-2">Categories</div>
            <div class="list-group">
              <?php
                $totalAll = array_sum(array_map(fn($x)=>(int)$x['cnt'], $cats));
                $isAll    = ($cat==='' || strtolower($cat)==='all');
              ?>
              <a class="list-group-item <?= $isAll ? 'active' : '' ?>"
                 href="products.php?cat=all&price=<?= urlencode($price ?: 'all') ?>&sort=<?= urlencode($sort) ?>">
                 All Products
                 <span class="badge bg-light text-dark float-end"><?= (int)$totalAll ?></span>
              </a>
              <?php foreach ($cats as $c): ?>
                <a class="list-group-item <?= (trim($cat) === $c['category']) ? 'active' : '' ?>"
                   href="products.php?cat=<?= urlencode($c['category']) ?>&price=<?= urlencode($price ?: 'all') ?>&sort=<?= urlencode($sort) ?>">
                   <?= htmlspecialchars($c['category']) ?>
                   <span class="badge bg-light text-dark float-end"><?= (int)$c['cnt'] ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Price Range -->
          <div class="mb-2">
            <div class="small text-muted ps-2 mb-2">Price Range</div>
            <?php
              $priceOpts = [
                'all'        => 'All Prices',
                'under-250'  => 'Under R 250',
                '250-500'    => 'R 250 – R 500',
                '500-1000'   => 'R 500 – R 1 000',
                'over-1000'  => 'Over R 1 000',
              ];
            ?>
            <div class="list-group">
              <?php foreach ($priceOpts as $k=>$label): ?>
                <a class="list-group-item <?= ($price===$k || ($price==='' && $k==='all')) ? 'active' : '' ?>"
                   href="products.php?cat=<?= urlencode($isAll ? 'all' : $cat) ?>&price=<?= urlencode($k) ?>&sort=<?= urlencode($sort) ?>">
                   <?= $label ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- MAIN -->
      <div class="col-12 col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <h4 class="fw-bold mb-1">All Products</h4>
            <div class="text-muted small">
              Showing <?= (int)$totalCount ?> sustainable product<?= $totalCount==1?'':'s' ?>
              <?php if ($cat && strtolower($cat)!=='all'): ?> in <span class="fw-semibold"><?= htmlspecialchars($cat) ?></span><?php endif; ?>
            </div>
          </div>

          <!-- Sort -->
          <form method="get" class="d-flex align-items-center gap-2">
            <!-- keep both keys in sync -->
            <input type="hidden" name="cat" value="<?= htmlspecialchars($cat !== '' ? $cat : 'all') ?>">
            <input type="hidden" name="price" value="<?= htmlspecialchars($price !== '' ? $price : 'all') ?>">
            <select name="sort" class="form-select" onchange="this.form.submit()">
              <option value="featured"   <?= $sort==='featured'?'selected':'' ?>>Featured</option>
              <option value="newest"     <?= $sort==='newest'?'selected':'' ?>>Newest</option>
              <option value="price-asc"  <?= $sort==='price-asc'?'selected':'' ?>>Price: Low to High</option>
              <option value="price-desc" <?= $sort==='price-desc'?'selected':'' ?>>Price: High to Low</option>
            </select>
          </form>
        </div>

        <!-- GRID -->
        <div class="row g-4">
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()):
              $id    = (int)$row['id'];
              $name  = $row['name'];
              $img   = $row['image'] ?: 'placeholder.jpg';
              $catg  = $row['category'];
              $priceVal = (float)$row['price'];
              $stock = (int)$row['stock'];
              $cf    = isset($row['carbon_footprint']) ? (float)$row['carbon_footprint'] : null;
              $tag   = trim((string)$row['tag_label']);
            ?>
            <div class="col-12 col-sm-6 col-lg-4">
              <div class="card prod-card h-100 position-relative">
                <?php if ($tag !== ''): ?>
                  <span class="badge bg-success prod-tag rounded-pill px-3 py-2"><?= htmlspecialchars($tag) ?></span>
                <?php endif; ?>

                <a href="product.php?id=<?= $id ?>">
                  <img class="prod-img" src="images/products/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($name) ?>">
                </a>

                <div class="card-body">
                  <h6 class="fw-semibold mb-1">
                    <a class="link-body-emphasis text-decoration-none" href="product.php?id=<?= $id ?>">
                      <?= htmlspecialchars($name) ?>
                    </a>
                  </h6>
                  <p class="mb-1 text-muted"><?= htmlspecialchars($catg) ?></p>
                  <small class="d-block mb-2 text-muted">Stock: <?= $stock ?></small>

                  <p class="fs-5 fw-bold text-success mb-3">R <?= number_format($priceVal, 2) ?></p>

                  <?php if (!is_null($cf)): ?>
                    <div class="badge bg-light text-success rounded-pill px-3 py-2 mb-3">
                      Saves <?= number_format($cf, 1) ?> kg CO₂
                    </div>
                  <?php endif; ?>

                  <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['is_admin'] === 0): ?>
                    <form action="backend/add-to-cart.php" method="POST" class="m-0">
                      <input type="hidden" name="product_id" value="<?= $id ?>">
                      <input type="hidden" name="quantity" value="1">
                      <button class="btn btn-success rounded-pill px-4 py-2" type="submit" title="Add to cart">
                        Add to Cart
                      </button>
                    </form>
                  <?php else: ?>
                    <a class="btn btn-outline-success rounded-pill px-4 py-2" href="login.html">Login to buy</a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="alert alert-info">No products matched your filters.</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
