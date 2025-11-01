<?php
session_start();
$conn = new mysqli("localhost", "root", "", "dragonstone.db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$result = $conn->query("SELECT * FROM products WHERE id=$product_id");

if (!$result || $result->num_rows == 0) {
    echo "<div style='text-align:center; margin-top:50px;'>Product not found.</div>";
    exit();
}
$product = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($product['name']) ?> • DragonStone</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background-color: #f5fef7; font-family: "Geist", system-ui, sans-serif; }
.product-title { font-weight: 600; }
.co2-badge { background: #e9f7ef; color: #198754; border-radius: 20px; padding: 6px 14px; font-weight: 500; }
.btn-eco { background-color: #198754; color: white; border-radius: 30px; padding: 10px 20px; font-weight: 500; border: none; }
.btn-eco:hover { background-color: #157347; }
</style>
</head>
<body>
<div class="container py-5">
  <div class="row align-items-center g-4">

    <!-- Product Image -->
    <div class="col-md-6 text-center">
      <?php if (!empty($product['image'])): ?>
        <img src="./images/products/<?= htmlspecialchars($product['image']) ?>"
             class="img-fluid rounded shadow-sm"
             alt="<?= htmlspecialchars($product['name']) ?>">
      <?php else: ?>
        <img src="https://via.placeholder.com/400x400?text=Product+Image"
             class="img-fluid rounded shadow-sm"
             alt="<?= htmlspecialchars($product['name']) ?>">
      <?php endif; ?>
    </div>

    <!-- Product Info -->
    <div class="col-md-6">
      <h1 class="product-title mb-3"><?= htmlspecialchars($product['name']) ?></h1>
      <p class="text-muted mb-1">Category: <?= htmlspecialchars($product['category']) ?></p>

      <p class="fs-3 fw-bold text-success mb-1">
        R <?= number_format((float)$product['price'], 2) ?>
      </p>

      <p class="mb-2">Stock available: <?= (int)$product['stock'] ?></p>

      <?php if (isset($product['carbon_footprint'])): ?>
        <p class="co2-badge mb-3">
          Saves <?= number_format((float)$product['carbon_footprint'], 2) ?> kg CO₂ per unit
        </p>
      <?php endif; ?>

      <!-- Quantity & Total CO₂ -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Quantity</label>
        <input type="number" id="quantity" value="1" min="1" max="<?= (int)$product['stock'] ?>" class="form-control w-25">
      </div>

      <p class="mb-4 fw-medium">
        Total Carbon Footprint:
        <span id="cf-total"><?= number_format((float)($product['carbon_footprint'] ?? 0), 2) ?></span> kg CO₂
      </p>

      <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['is_admin'] === 0): ?>
        <!-- Add to Cart -->
        <form action="add-to-cart.php" method="POST" class="mb-3">
          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
          <input type="hidden" id="quantity-hidden" name="quantity" value="1">
          <button type="submit" class="btn btn-eco">Add to Cart</button>
        </form>

        <!-- Subscription -->
        <form action="add-subscription.php" method="POST">
          <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
          <div class="mb-2">
            <label class="form-label">Subscribe Quantity</label>
            <input type="number" name="quantity" value="1" min="1" max="<?= (int)$product['stock'] ?>" class="form-control w-25">
          </div>
          <div class="mb-3">
            <label class="form-label">Subscription Interval</label>
            <select name="interval_days" class="form-select w-50">
              <option value="7">Weekly</option>
              <option value="30" selected>Monthly</option>
              <option value="90">Quarterly</option>
            </select>
          </div>
          <button type="submit" class="btn btn-outline-success">Subscribe</button>
        </form>
      <?php else: ?>
        <p><a href="../login.html" class="text-success fw-semibold">Login</a> to purchase or subscribe.</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const cfPerUnit = parseFloat(document.getElementById('cf-total').textContent || '0');
const quantityInput = document.getElementById('quantity');
const cfTotal = document.getElementById('cf-total');
const hiddenQty = document.getElementById('quantity-hidden');

quantityInput.addEventListener('input', function() {
  let qty = parseInt(this.value) || 1;
  if (qty < 1) qty = 1;
  if (qty > <?= (int)$product['stock'] ?>) qty = <?= (int)$product['stock'] ?>;
  this.value = qty;

  cfTotal.textContent = (qty * cfPerUnit).toFixed(2);
  if (hiddenQty) hiddenQty.value = qty;
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
