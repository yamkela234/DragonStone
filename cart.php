<?php
session_start();
if (!isset($_SESSION['user_id']) || (int)$_SESSION['is_admin'] !== 0) {
  header("Location: index.php");
  exit;
}

$user_id = (int)$_SESSION['user_id'];

// Build project root like "/DragonStone" regardless of where this file is included from
$script = $_SERVER['SCRIPT_NAME'] ?? '/';
$parts  = explode('/', trim($script, '/'));
$ROOT   = isset($parts[0]) && $parts[0] !== '' ? '/'.$parts[0] : '';

// DB
$conn = new mysqli("localhost", "root", "", "dragonstone.db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch cart items with product data (image + CO2 if available)
$sql = "
  SELECT c.id AS cart_id,
         c.quantity,
         p.id   AS product_id,
         p.name,
         p.price,
         p.image,
         IFNULL(p.carbon_footprint,0) AS carbon_footprint
  FROM cart_items c
  JOIN products p ON p.id = c.product_id
  WHERE c.user_id = ?
  ORDER BY c.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
$subtotal = 0.0;
$total_co2 = 0.0;

function moneyZAR($v){ return 'R ' . number_format((float)$v, 2); }

while ($res && $row = $res->fetch_assoc()) {
  $q    = max(1, (int)$row['quantity']);
  $price= (float)$row['price'];
  $line = $q * $price;

  // image URL with fallback + absolute to project root
  $imgFile = trim($row['image'] ?? '');
  $imgRel  = __DIR__ . '/images/products/' . $imgFile;
  $imgUrl  = ($imgFile !== '' && is_file($imgRel))
      ? ($ROOT . '/images/products/' . rawurlencode($imgFile))
      : ($ROOT . '/images/products/placeholder.jpg');

  $items[] = [
    'cart_id'   => (int)$row['cart_id'],
    'product_id'=> (int)$row['product_id'],
    'name'      => $row['name'],
    'price'     => $price,
    'quantity'  => $q,
    'image_url' => $imgUrl,
    'co2'       => (float)$row['carbon_footprint'],
    'line'      => $line,
  ];

  $subtotal += $line;
  $total_co2 += ((float)$row['carbon_footprint']) * $q;
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your Cart • DragonStone</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="css/styles.css">
<style>
  body { background:#f4fbf6; }
  .cart-title{ font-weight:800; letter-spacing:.4px }
  .cart-card{ border:0; border-radius:18px; background:#fff; box-shadow:0 12px 24px rgba(18,38,63,.07) }
  .item-thumb{ width:92px; height:92px; object-fit:cover; border-radius:14px; background:#f5f7f6 }
  .qty-wrap{ display:flex; align-items:center; gap:.35rem }
  .qty-wrap input[type=number]{ width:74px; text-align:center }
  .badge-eco{ background:#e8f7ee; color:#198754; border-radius:999px; }
  .remove-btn{ --bs-btn-bg:#ffe5e5; --bs-btn-color:#c62828; --bs-btn-border-color:#ffd7d7; }
  .summary-card{ position:sticky; top:20px }
  .empty-state{ border:2px dashed #cfe9dc; border-radius:18px; background:#ffffff; }
  .btn-eco{ background:#16a34a; color:#06220f; border:0; font-weight:700 }
  .btn-eco:hover{ background:#128a3e; color:#051b12 }
  .link-muted{ color:#7e8a86; text-decoration:none }
  .link-muted:hover{ color:#000; text-decoration:underline }
</style>
</head>
<body>

<div class="container py-4 py-md-5">
  <h1 class="cart-title display-6 mb-4">Your Cart</h1>

  <?php if (!$items): ?>
    <div class="empty-state p-5 text-center">
      <div class="mb-2 fs-1">🛒</div>
      <h5 class="mb-2 fw-bold">Your cart is empty</h5>
      <p class="text-muted mb-4">Discover eco-friendly products that make a real difference for you and the planet.</p>
      <a class="btn btn-eco px-4 py-2 rounded-pill" href="<?= $ROOT ?>/products.php">Browse products</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <!-- Items -->
      <div class="col-12 col-lg-8">
        <div class="cart-card p-3 p-md-4">
          <?php foreach ($items as $it): ?>
          <div class="d-flex align-items-start gap-3 py-3 border-bottom">
            <img src="<?= htmlspecialchars($it['image_url']) ?>" class="item-thumb" alt="<?= htmlspecialchars($it['name']) ?>">

            <div class="flex-grow-1">
              <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars($it['name']) ?></div>
                  <?php if ($it['co2'] > 0): ?>
                    <span class="badge badge-eco px-3 py-2 mt-2">
                      Saves <?= number_format($it['co2'], 2) ?> kg CO₂ / unit
                    </span>
                  <?php endif; ?>
                </div>
                <div class="text-end">
                  <div class="fw-bold text-success"><?= moneyZAR($it['price']) ?></div>
                  <div class="text-muted small">per item</div>
                </div>
              </div>

              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
                <!-- Quantity update (expects backend/update-cart.php) -->
                <form class="qty-wrap" action="backend/update-cart.php" method="post">
                  <input type="hidden" name="cart_id" value="<?= (int)$it['cart_id'] ?>">
                  <div class="input-group" style="width: 130px;">
                    <button class="btn btn-outline-secondary" type="submit" name="change" value="-1" title="Reduce">
                      <i class="bi bi-dash"></i>
                    </button>
                    <input class="form-control" type="number" name="quantity" min="1" value="<?= (int)$it['quantity'] ?>">
                    <button class="btn btn-outline-secondary" type="submit" name="change" value="+1" title="Increase">
                      <i class="bi bi-plus"></i>
                    </button>
                  </div>
                  <button class="btn btn-sm btn-light ms-2" type="submit" name="action" value="set">Update</button>
                </form>

                <!-- Remove (keeps your existing endpoint) -->
                <a class="btn remove-btn"
                   href="backend/remove-cart.php?cart_id=<?= (int)$it['cart_id'] ?>"
                   onclick="return confirm('Remove this item?');">Remove</a>
              </div>

              <div class="mt-2 small text-muted">
                Line total: <span class="fw-semibold text-success"><?= moneyZAR($it['line']) ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <a href="<?= $ROOT ?>/products.php" class="link-muted"><i class="bi bi-arrow-left me-1"></i>Continue shopping</a>
            <div class="fw-semibold">Subtotal: <span class="text-success"><?= moneyZAR($subtotal) ?></span></div>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div class="col-12 col-lg-4">
        <div class="cart-card p-3 p-md-4 summary-card">
          <h5 class="fw-bold mb-3">Order Summary</h5>

          <div class="d-flex justify-content-between mb-2">
            <span>Items</span>
            <span><?= count($items) ?></span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span>Subtotal</span>
            <span class="fw-semibold"><?= moneyZAR($subtotal) ?></span>
          </div>
          <?php if ($total_co2 > 0): ?>
          <div class="small text-success mb-2">
            Estimated CO₂ savings: <?= number_format($total_co2, 2) ?> kg
          </div>
          <?php endif; ?>
          <div class="small text-muted mb-3">
            Delivery calculated at checkout. Free returns within 30 days.
          </div>

          <a class="btn btn-eco w-100 py-2 rounded-pill" href="backend/checkout.php">Checkout</a>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
