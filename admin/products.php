<?php
// admin/products.php
require __DIR__ . '/_guard.php';

// ---- session bits
$USER_NAME = $_SESSION['name']  ?? 'Admin User';
$USER_MAIL = $_SESSION['email'] ?? 'admin@dragonstone.com';
$AVATAR    = strtoupper(substr($USER_NAME,0,1));

// ---- incoming filters
$q       = trim($_GET['q'] ?? '');
$fCat    = $_GET['category'] ?? 'all';

// ---- probe columns
$colsRes = $mysqli->query("SHOW COLUMNS FROM products");
$cols = [];
while ($colsRes && $c = $colsRes->fetch_assoc()) $cols[] = strtolower($c['Field']);

$hasName    = in_array('name', $cols);
$hasCat     = in_array('category', $cols);
$hasPrice   = in_array('price', $cols);
$hasImage   = in_array('image', $cols);
$hasStock   = in_array('stock', $cols);
$hasTag     = in_array('tag_label', $cols);
$hasCFP     = in_array('carbon_footprint', $cols);
$hasUpdated = in_array('updated_at', $cols);
$hasCreated = in_array('created_at', $cols);

// ---- category options
$categoryOptions = ['all'];
if ($hasCat) {
  $res = $mysqli->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category<>'' ORDER BY category");
  while ($res && $r = $res->fetch_row()) $categoryOptions[] = $r[0];
}

// ---- WHERE filters
$where = [];
if ($q !== '') {
  $qq = $mysqli->real_escape_string($q);
  $like = [];
  if ($hasName) $like[] = "name LIKE '%{$qq}%'";
  if ($hasCat)  $like[] = "category LIKE '%{$qq}%'";
  if ($hasTag)  $like[] = "tag_label LIKE '%{$qq}%'";
  $where[] = $like ? '(' . implode(' OR ', $like) . ')' : '1=1';
}
if ($hasCat && $fCat !== 'all') {
  $cc = $mysqli->real_escape_string($fCat);
  $where[] = "category='{$cc}'";
}
$WHERE = $where ? 'WHERE '.implode(' AND ', $where) : '';

// ---- SELECT
$select = ['id'];
if ($hasName)    $select[] = 'name';
if ($hasCat)     $select[] = 'category';
if ($hasPrice)   $select[] = 'price';
if ($hasImage)   $select[] = 'image';
if ($hasStock)   $select[] = 'stock';
if ($hasTag)     $select[] = 'tag_label';
if ($hasCFP)     $select[] = 'carbon_footprint';
if ($hasUpdated) $select[] = 'updated_at';
if ($hasCreated) $select[] = 'created_at';
$selectSQL = implode(',', $select);

// ---- ORDER
$orderCol = $hasUpdated ? 'updated_at' : ($hasCreated ? 'created_at' : 'id');

// ---- Query
$sql = "SELECT {$selectSQL} FROM products {$WHERE} ORDER BY {$orderCol} DESC LIMIT 500";
$res = $mysqli->query($sql);
$rows = [];
while ($res && $row = $res->fetch_assoc()) $rows[] = $row;

// ---- helpers
function _money($v){
  if ($v === null || $v === '') return '—';
  // South African R format (R1 234.50)
  $num = number_format((float)$v, 2, '.', ' ');
  return 'R' . $num;
}
function _statusChipFromStock($stock){
  $s = (int)$stock;
  $label = $s > 0 ? 'Active' : 'Out of Stock';
  $cls   = $s > 0 ? 'success' : 'danger';
  return '<span class="badge rounded-pill text-bg-'.$cls.'">'.$label.'</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Products • DragonStone Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../css/admin.css">
<style>
  body{background:#0b0f12;}
  .admin-sidebar{width:270px;min-height:100vh;background:#12181d;color:#e8edf2}
  .admin-sidebar .nav-link{color:#cfd8e3;border-radius:.6rem}
  .admin-sidebar .nav-link.active{background:rgba(22,163,74,.16);color:#c8facc;border:1px solid rgba(22,163,74,.35)}
  .brand-badge{display:inline-grid;place-items:center;width:36px;height:36px;border-radius:.6rem;background:linear-gradient(135deg,#10b981,#34d399);color:#052b1e;font-weight:800}
  .admin-topbar{height:64px;background:#12181d;color:#e8edf2}
  .avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-weight:700}
  .card{border-color:#1f2a34;background:#0f1419;color:#e8edf2}
  .table>thead{color:#97a3af}
  .form-control, .form-select{background:#0e151a;border-color:#1f2a34;color:#e8edf2}
  .form-control::placeholder{color:#708090}
  .btn-success{background:#16a34a;border-color:#16a34a;color:#06220f;font-weight:700}
  .text-muted{color:#97a3af!important}
  .stock-pill{display:inline-grid;place-items:center;min-width:34px;height:34px;border-radius:999px;padding:0 .6rem;font-weight:700;background:#06220f;color:#86efac;border:1px solid #164e35}
  .img-td{width:56px}
  .img-td img{width:48px;height:48px;object-fit:cover;border-radius:.5rem}
  .td-title{font-weight:700}
  .td-sub{font-size:.9rem;color:#97a3af}
</style>
</head>
<body>
<div class="d-flex">
  <!-- Sidebar -->
  <aside class="admin-sidebar p-3 border-end border-dark">
    <div class="d-flex align-items-center mb-4">
      <span class="brand-badge me-2">DS</span>
      <div>
        <div class="fw-semibold">DragonStone Admin</div>
        <small class="text-muted">Sustainable Living Management</small>
      </div>
    </div>
    <ul class="nav flex-column gap-1">
      <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-grid me-2"></i>Dashboard</a></li>
      <li class="nav-item"><a class="nav-link active" href="products.php"><i class="bi bi-bag me-2"></i>Products</a></li>
      <li class="nav-item"><a class="nav-link" href="community.php"><i class="bi bi-people me-2"></i>Community</a></li>
      <li class="nav-item"><a class="nav-link" href="newsletter.php"><i class="bi bi-envelope-open me-2"></i>Newsletter</a></li>
      <li class="nav-item"><a class="nav-link" href="analytics.php"><i class="bi bi-graph-up-arrow me-2"></i>Analytics</a></li>
      <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
    </ul>
  </aside>

  <!-- Main -->
  <main class="flex-grow-1">
    <!-- Topbar -->
    <div class="admin-topbar d-flex align-items-center justify-content-between px-4 border-bottom border-dark">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-bag text-success"></i>
        <span class="fw-semibold">Products Management</span>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div class="d-none d-md-block text-end small lh-1">
          <div class="fw-semibold"><?= htmlspecialchars($USER_NAME) ?></div>
          <div class="text-muted"><?= htmlspecialchars($USER_MAIL) ?></div>
        </div>
        <div class="avatar bg-success text-white"><?= $AVATAR; ?></div>
      </div>
    </div>

    <div class="p-4">

      <!-- Search + Category filter + Add Product -->
      <form method="get" class="row g-2 align-items-center mb-3">
        <div class="col-12 col-xl">
          <input type="text" class="form-control" name="q" placeholder="Search products..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-12 col-sm-6 col-xl-auto">
          <select class="form-select" name="category" <?= $hasCat?'':'disabled' ?>>
            <?php foreach ($categoryOptions as $opt): ?>
              <option value="<?= htmlspecialchars($opt) ?>" <?= $fCat===$opt?'selected':'' ?>>
                <?= $opt==='all' ? 'All Categories' : htmlspecialchars($opt) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-sm-6 col-xl-auto ms-xl-auto">
          <a href="product-edit.php" class="btn btn-success w-100">
            <i class="bi bi-plus-lg me-1"></i>Add Product
          </a>
        </div>
      </form>

      <!-- Table -->
      <div class="card rounded-4 shadow-sm">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="img-td"></th>
                <th>Product</th>
                <?php if ($hasCat):   ?><th>Category</th><?php endif; ?>
                <?php if ($hasPrice): ?><th>Price</th><?php endif; ?>
                <?php if ($hasStock): ?><th>Stock</th><?php endif; ?>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No products found. Adjust filters or click “Add Product”.</td></tr>
              <?php else: foreach ($rows as $r): ?>
                <?php
                  $img   = $hasImage ? ($r['image'] ?? '') : '';
                  $name  = $hasName  ? ($r['name'] ?? '(Untitled)') : '(Untitled)';
                  $cat   = $hasCat   ? ($r['category'] ?? '—') : '—';
                  $price = $hasPrice ? _money($r['price'] ?? null) : '—';
                  $stock = $hasStock ? (int)($r['stock'] ?? 0) : null;
                  $sub   = '';
                  if ($hasTag && !empty($r['tag_label'])) {
                    $sub = $r['tag_label'];
                  } elseif ($hasCFP && $r['carbon_footprint']!=='') {
                    $cfp = (float)$r['carbon_footprint'];
                    if ($cfp > 0) $sub = 'Saves '.rtrim(rtrim(number_format($cfp,1), '0'), '.').'kg CO2';
                  }
                ?>
                <tr>
                  <td class="img-td">
                    <?php if ($img): ?>
                      <img src="../images/products/<?= htmlspecialchars($img) ?>" alt="">
                    <?php else: ?>
                      <div class="bg-secondary rounded" style="opacity:.25;width:48px;height:48px;"></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="td-title"><?= htmlspecialchars($name) ?></div>
                    <?php if ($sub): ?><div class="td-sub"><?= htmlspecialchars($sub) ?></div><?php endif; ?>
                  </td>
                  <?php if ($hasCat):   ?><td><?= htmlspecialchars($cat) ?></td><?php endif; ?>
                  <?php if ($hasPrice): ?><td class="fw-semibold"><?= $price ?></td><?php endif; ?>
                  <?php if ($hasStock): ?><td><span class="stock-pill"><?= (int)$stock ?></span></td><?php endif; ?>
                  <td><?= _statusChipFromStock($stock ?? 0) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="product-edit.php?id=<?= (int)$r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    <a class="btn btn-sm btn-outline-secondary" href="product-view.php?id=<?= (int)$r['id'] ?>" title="Preview"><i class="bi bi-eye"></i></a>
                    <a class="btn btn-sm btn-outline-danger" href="product-delete.php?id=<?= (int)$r['id'] ?>" title="Delete" onclick="return confirm('Delete this product?');"><i class="bi bi-trash"></i></a>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
