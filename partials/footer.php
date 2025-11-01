<?php
// Footer (dynamic category names; links target project root)
$year = date('Y');

// Build project root like "/DragonStone" regardless of current depth
$script = $_SERVER['SCRIPT_NAME'] ?? '/';
$parts  = explode('/', trim($script, '/'));      // e.g. ["DragonStone","admin","index.php"]
$ROOT   = isset($parts[0]) && $parts[0] !== '' ? '/'.$parts[0] : ''; // "/DragonStone"

// Fetch up to 6 categories from products table
$cats = [];
$mysqli = @new mysqli("localhost","root","","dragonstone.db");
if (!$mysqli->connect_error) {
  $tbl_ok = $mysqli->query("SHOW TABLES LIKE 'products'")->num_rows > 0;
  $col_ok = $tbl_ok && $mysqli->query("SHOW COLUMNS FROM products LIKE 'category'")->num_rows > 0;
  if ($col_ok) {
    $res = $mysqli->query("
      SELECT TRIM(category) AS category, COUNT(*) AS n
      FROM products
      WHERE category IS NOT NULL AND TRIM(category) <> ''
      GROUP BY TRIM(category)
      ORDER BY n DESC, category ASC
      LIMIT 6
    ");
    while ($res && $row = $res->fetch_assoc()) $cats[] = $row['category'];
  }
  $mysqli->close();
}
if (!$cats) {
  $cats = ['Kitchen','Bathroom','Cleaning','Storage','Home Decor','Outdoor'];
}
?>
<footer class="ds-footer">
  <div class="ds-footer-accent"></div>

  <style>
    .ds-footer{background:#0c1621;color:#cfe6dd;}
    .ds-footer a{color:#cfe6dd;text-decoration:none}
    .ds-footer a:hover{color:#ffffff;text-decoration:underline}
    .ds-footer .brand-name{color:#16a34a;font-weight:800;font-size:2rem;letter-spacing:.3px}
    .ds-footer .heading{color:#e9fff4;font-weight:700;font-size:1.4rem;margin-bottom:.75rem}
    .ds-footer .blurb{color:#b7d4c8;max-width:36ch;line-height:1.7}
    .ds-footer .link-list{list-style:none;margin:0;padding:0}
    .ds-footer .link-list li{margin:.65rem 0;font-size:1.2rem}
    .ds-footer .social{display:flex;gap:16px;margin-top:18px}
    .ds-footer .social a{
      width:56px;height:56px;border-radius:50%;
      display:inline-grid;place-items:center;
      background:#16a34a;color:#062b1a;font-size:1.35rem;
      box-shadow:0 4px 16px rgba(22,163,74,.25);
    }
    .ds-footer .social a:hover{transform:translateY(-2px);color:#03160e}
    .ds-footer .divider{border-top:1px solid #152534;opacity:1}
    .ds-footer-accent{height:10px;background:#0ea569}
    @media (max-width: 991.98px){ .ds-footer .brand-wrap{margin-bottom:1.25rem} }
  </style>

  <div class="container py-5">
    <div class="row g-4 align-items-start">
      <!-- Brand -->
      <div class="col-12 col-lg-3 brand-wrap">
        <div class="brand-name">DragonStone</div>
        <p class="blurb mt-3">
          Making sustainable living accessible, stylish, and community-driven.
          Not just a store, but a movement.
        </p>
        <div class="social">
          <a href="<?=$ROOT?>/products.php" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
          <a href="<?=$ROOT?>/products.php" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
          <a href="<?=$ROOT?>/products.php" aria-label="Twitter / X"><i class="bi bi-twitter-x"></i></a>
          <a href="<?=$ROOT?>/products.php" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
        </div>
      </div>

      <!-- Shop (dynamic names; redirect to products) -->
      <div class="col-6 col-lg-3">
        <div class="heading">Shop</div>
        <ul class="link-list">
          <?php foreach ($cats as $c): ?>
            <li><a href="<?=$ROOT?>/products.php"><?= htmlspecialchars($c) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Community -->
      <div class="col-6 col-lg-3">
        <div class="heading">Community</div>
        <ul class="link-list">
          <li><a href="<?=$ROOT?>/products.php">Challenges</a></li>
          <li><a href="<?=$ROOT?>/products.php">DIY Guides</a></li>
          <li><a href="<?=$ROOT?>/products.php">Sustainability Tips</a></li>
          <li><a href="<?=$ROOT?>/products.php">Forum</a></li>
          <li><a href="<?=$ROOT?>/products.php">EcoPoints</a></li>
          <li><a href="<?=$ROOT?>/products.php">Impact Report</a></li>
        </ul>
      </div>

      <!-- Support -->
      <div class="col-12 col-lg-3">
        <div class="heading">Support</div>
        <ul class="link-list">
          <li><a href="<?=$ROOT?>/products.php">Help Center</a></li>
          <li><a href="<?=$ROOT?>/products.php">Shipping Info</a></li>
          <li><a href="<?=$ROOT?>/products.php">Returns</a></li>
          <li><a href="<?=$ROOT?>/products.php">Contact Us</a></li>
          <li><a href="<?=$ROOT?>/products.php">Sustainability</a></li>
          <li><a href="<?=$ROOT?>/products.php">About Us</a></li>
        </ul>
      </div>
    </div>

    <hr class="divider my-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2" style="color:#9fbfb4">
      <div>© <?=$year?> DragonStone. All rights reserved.</div>
      <div>Powered by sustainable energy • Built with love</div>
    </div>
  </div>
</footer>
