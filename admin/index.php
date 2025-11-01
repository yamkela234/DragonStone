<?php
// admin/index.php
require __DIR__ . '/_guard.php'; // expects $mysqli, session, safeCount()

// ─────────────────────────────────────────────────────────────────────────────
// Session bits
$USER_NAME = $_SESSION['name']  ?? 'Admin User';
$USER_MAIL = $_SESSION['email'] ?? 'admin@dragonstone.com';
$AVATAR    = strtoupper(substr($USER_NAME,0,1));

// Helpers
if (!function_exists('table_exists')) {
  function table_exists(mysqli $db, string $name): bool {
    $name = $db->real_escape_string($name);
    $res = $db->query("SHOW TABLES LIKE '{$name}'");
    return $res && $res->num_rows > 0;
  }
}
if (!function_exists('col_exists')) {
  function col_exists(mysqli $db, string $table, string $col): bool {
    $t = $db->real_escape_string($table);
    $c = $db->real_escape_string($col);
    $res = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return $res && $res->num_rows > 0;
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// KPI: Total Products
$TOTAL_PRODUCTS = table_exists($mysqli,'products') ? safeCount($mysqli,"SELECT COUNT(*) FROM products") : 0;

// KPI: Community Members
$TOTAL_MEMBERS  = table_exists($mysqli,'users') ? safeCount($mysqli,"SELECT COUNT(*) FROM users") : 0;

// KPI: Newsletter Subscribers (pick first table that exists)
$TOTAL_SUBS = 0;
foreach (['newsletter_subscribers','subscribers','mailing_list','newsletter_list','subscriptions'] as $tbl) {
  if (table_exists($mysqli,$tbl)) { $TOTAL_SUBS = safeCount($mysqli,"SELECT COUNT(*) FROM `{$tbl}`"); break; }
}

// KPI: CO2 Saved This Month (best-effort)
$CO2_MONTH = 0.0;
$year = (int)date('Y'); $month = (int)date('m');
$start = sprintf('%04d-%02d-01', $year, $month);
$end   = date('Y-m-d', strtotime('first day of next month'));

if (table_exists($mysqli,'order_items') && table_exists($mysqli,'orders') && table_exists($mysqli,'products')
    && col_exists($mysqli,'order_items','product_id') && col_exists($mysqli,'order_items','quantity')
    && col_exists($mysqli,'orders','created_at')
    && col_exists($mysqli,'products','id') && col_exists($mysqli,'products','carbon_footprint')) {

  $sql = "
    SELECT SUM(oi.quantity * p.carbon_footprint) AS co2
    FROM order_items oi
    JOIN orders o   ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id
    WHERE o.created_at >= '{$start}' AND o.created_at < '{$end}'
  ";
  if ($res = $mysqli->query($sql)) { $row = $res->fetch_assoc(); $CO2_MONTH = (float)($row['co2'] ?? 0); }
}
elseif (table_exists($mysqli,'products') && col_exists($mysqli,'products','carbon_footprint') && col_exists($mysqli,'products','updated_at')) {
  $sql = "
    SELECT SUM(carbon_footprint) AS co2
    FROM products
    WHERE updated_at >= '{$start}' AND updated_at < '{$end}'
  ";
  if ($res = $mysqli->query($sql)) { $row = $res->fetch_assoc(); $CO2_MONTH = (float)($row['co2'] ?? 0); }
}

// Month-over-month growth (only if the date column exists)
function month_over_month(mysqli $db, string $table, string $dateCol): ?float {
  $y = (int)date('Y'); $m = (int)date('m');
  $start = sprintf('%04d-%02d-01',$y,$m);
  $end   = date('Y-m-d', strtotime('first day of next month'));
  $pstart= date('Y-m-d', strtotime('first day of previous month'));
  $pend  = sprintf('%04d-%02d-01',$y,$m);

  $s1 = "SELECT COUNT(*) FROM `{$table}` WHERE `{$dateCol}` >= '{$start}' AND `{$dateCol}` < '{$end}'";
  $s0 = "SELECT COUNT(*) FROM `{$table}` WHERE `{$dateCol}` >= '{$pstart}' AND `{$dateCol}` < '{$pend}'";
  try {
    $curr = safeCount($db,$s1);
    $prev = max(1, safeCount($db,$s0));
    return (($curr - $prev) / $prev) * 100.0;
  } catch (Throwable $e) { return null; }
}
$GROWTH_PRODUCTS = (table_exists($mysqli,'products') && col_exists($mysqli,'products','created_at')) ? month_over_month($mysqli,'products','created_at') : null;
$GROWTH_USERS    = (table_exists($mysqli,'users') && col_exists($mysqli,'users','created_at')) ? month_over_month($mysqli,'users','created_at') : null;
$GROWTH_SUBS     = null;
foreach (['newsletter_subscribers','subscribers','mailing_list','newsletter_list','subscriptions'] as $tbl) {
  if (table_exists($mysqli,$tbl) && col_exists($mysqli,$tbl,'created_at')) { $GROWTH_SUBS = month_over_month($mysqli,$tbl,'created_at'); break; }
}

// ─────────────────────────────────────────────────────────────────────────────
// Recent Activities (schema-safe)
// Build list from products + community_posts + newsletters if present
$activities = [];
function push_activity(&$arr, $when, $icon, $text, $by='System') {
  if (!$when) return;
  $ts = strtotime($when);
  if (!$ts) $ts = time();
  $arr[] = ['ts'=>$ts,'icon'=>$icon,'text'=>$text,'by'=>$by];
}

// Products
if (table_exists($mysqli,'products')) {
  $res = $mysqli->query("SELECT id, name, updated_at, created_at FROM products ORDER BY COALESCE(updated_at,created_at) DESC LIMIT 5");
  while ($res && $r = $res->fetch_assoc()) {
    $when = $r['updated_at'] ?: $r['created_at'];
    push_activity($activities, $when, 'bi-bag', 'New/updated product “'.($r['name'] ?: 'Unnamed').'”', 'System');
  }
}

// Community posts — NO author column required.
// If users table exists and has a name-like column, join to show who.
if (table_exists($mysqli,'community_posts')) {
  $hasUsers     = table_exists($mysqli,'users');
  $hasCPUserId  = col_exists($mysqli,'community_posts','user_id');

  $userIdColOk  = $hasUsers && col_exists($mysqli,'users','id');
  $nameCols = [];
  foreach (['name','full_name','username','email'] as $cand) {
    if ($hasUsers && col_exists($mysqli,'users',$cand)) $nameCols[] = "u.`{$cand}`";
  }
  $authorExpr = $nameCols ? ("COALESCE(".implode(',', $nameCols).", 'Community')") : "'Community'";

  if ($hasUsers && $hasCPUserId && $userIdColOk && $nameCols) {
    // Join users for author display
    $sql = "
      SELECT p.title, p.updated_at, p.created_at, {$authorExpr} AS author_name
      FROM community_posts p
      LEFT JOIN users u ON u.id = p.user_id
      ORDER BY COALESCE(p.updated_at, p.created_at) DESC
      LIMIT 5
    ";
  } else {
    // No usable users table/columns; skip author entirely
    $sql = "
      SELECT title, updated_at, created_at
      FROM community_posts
      ORDER BY COALESCE(updated_at, created_at) DESC
      LIMIT 5
    ";
  }

  if ($res = $mysqli->query($sql)) {
    while ($r = $res->fetch_assoc()) {
      $when = $r['updated_at'] ?? $r['created_at'] ?? null;
      $by   = $r['author_name'] ?? 'Community';
      push_activity($activities, $when, 'bi-people', 'Community post “'.(($r['title'] ?? '') ?: 'Untitled').'” activity', $by);
    }
  }
}

// Newsletter sends (if there’s a table)
foreach (['newsletters','newsletter_sends'] as $tbl) {
  if (!table_exists($mysqli,$tbl)) continue;
  $dateCol = col_exists($mysqli,$tbl,'sent_at') ? 'sent_at' : (col_exists($mysqli,$tbl,'created_at') ? 'created_at' : null);
  if (!$dateCol) continue;
  $subjectCol = col_exists($mysqli,$tbl,'subject') ? 'subject' : null;

  $sql = "SELECT {$dateCol} AS sent_at" . ($subjectCol ? ", {$subjectCol} AS subject" : "") .
         " FROM `{$tbl}` ORDER BY {$dateCol} DESC LIMIT 5";
  if ($res = $mysqli->query($sql)) {
    while ($r = $res->fetch_assoc()) {
      $subj = $r['subject'] ?? 'Campaign';
      push_activity($activities, $r['sent_at'] ?? null, 'bi-envelope', 'Newsletter “'.$subj.'” sent', 'Marketing');
    }
  }
}

// Sort newest first, take top 6
usort($activities, fn($a,$b)=> $b['ts'] <=> $a['ts']);
$activities = array_slice($activities, 0, 6);

// Pretty helpers
function fmt_delta(?float $v): string {
  if ($v===null) return '—';
  $sign = $v >= 0 ? '+' : '';
  return $sign . number_format($v,1) . '%';
}
function ago($ts): string {
  $d = time() - $ts;
  if ($d < 60) return $d.'s ago';
  if ($d < 3600) return floor($d/60).'m ago';
  if ($d < 86400) return floor($d/3600).'h ago';
  return floor($d/86400).'d ago';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Dashboard • DragonStone Admin</title>
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

  .kpi-card{border:1px solid #1f2a34;background:#0f1419;color:#e8edf2;border-radius:1rem}
  .kpi-ico{display:inline-grid;place-items:center;width:44px;height:44px;border-radius:.9rem;background:#d1fadf0f;color:#86efac;font-size:1.2rem}
  .kpi-num{font-size:2rem;font-weight:800}
  .kpi-sub{color:#97a3af}
  .delta.up{color:#22c55e}
  .delta.down{color:#ef4444}

  .card{border-color:#1f2a34;background:#0f1419;color:#e8edf2;border-radius:1rem}
  .text-muted{color:#97a3af!important}
  .btn-success{background:#16a34a;border-color:#16a34a;color:#06220f;font-weight:700}
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
      <li class="nav-item"><a class="nav-link active" href="index.php"><i class="bi bi-grid me-2"></i>Dashboard</a></li>
      <li class="nav-item"><a class="nav-link" href="products.php"><i class="bi bi-bag me-2"></i>Products</a></li>
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
        <i class="bi bi-grid text-success"></i>
        <span class="fw-semibold">Dashboard Overview</span>
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

      <!-- KPI row -->
      <div class="row g-3 mb-3">
        <div class="col-12 col-xl-3 col-md-6">
          <div class="kpi-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div class="kpi-sub">Total Products</div>
              <div class="kpi-ico"><i class="bi bi-bag"></i></div>
            </div>
            <div class="kpi-num mt-2"><?= number_format($TOTAL_PRODUCTS) ?></div>
            <div class="<?= ($GROWTH_PRODUCTS??0)>=0?'delta up':'delta down' ?>">
              <?= $GROWTH_PRODUCTS!==null ? ( ($GROWTH_PRODUCTS>=0? '↑ ':'↓ ').fmt_delta(abs($GROWTH_PRODUCTS)) ) : '—' ?>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-3 col-md-6">
          <div class="kpi-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div class="kpi-sub">Community Members</div>
              <div class="kpi-ico"><i class="bi bi-people"></i></div>
            </div>
            <div class="kpi-num mt-2"><?= number_format($TOTAL_MEMBERS) ?></div>
            <div class="<?= ($GROWTH_USERS??0)>=0?'delta up':'delta down' ?>">
              <?= $GROWTH_USERS!==null ? ( ($GROWTH_USERS>=0? '↑ ':'↓ ').fmt_delta(abs($GROWTH_USERS)) ) : '—' ?>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-3 col-md-6">
          <div class="kpi-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div class="kpi-sub">Newsletter Subscribers</div>
              <div class="kpi-ico"><i class="bi bi-envelope"></i></div>
            </div>
            <div class="kpi-num mt-2"><?= number_format($TOTAL_SUBS) ?></div>
            <div class="<?= ($GROWTH_SUBS??0)>=0?'delta up':'delta down' ?>">
              <?= $GROWTH_SUBS!==null ? ( ($GROWTH_SUBS>=0? '↑ ':'↓ ').fmt_delta(abs($GROWTH_SUBS)) ) : '—' ?>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-3 col-md-6">
          <div class="kpi-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <div class="kpi-sub">CO2 Saved This Month</div>
              <div class="kpi-ico"><i class="bi bi-leaf"></i></div>
            </div>
            <div class="kpi-num mt-2"><?= number_format($CO2_MONTH, 1) ?>kg</div>
            <div class="delta up">+—</div>
          </div>
        </div>
      </div>

      <!-- Content row -->
      <div class="row g-3">
        <div class="col-12 col-xl-8">
          <div class="card p-3">
            <div class="fw-bold mb-2">Recent Activities</div>
            <?php if (!$activities): ?>
              <div class="text-muted">No recent activity yet.</div>
            <?php else: foreach ($activities as $ev): ?>
              <div class="d-flex align-items-start gap-3 py-2">
                <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                  <i class="bi <?= htmlspecialchars($ev['icon']) ?>"></i>
                </div>
                <div class="flex-grow-1">
                  <div><?= htmlspecialchars($ev['text']) ?></div>
                  <div class="small text-muted"><?= ago($ev['ts']) ?> • by <?= htmlspecialchars($ev['by']) ?></div>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <div class="col-12 col-xl-4">
          <div class="card p-3">
            <div class="fw-bold mb-2">Quick Actions</div>
            <div class="d-grid gap-2">
              <a href="product-edit.php" class="btn btn-success btn-lg"><i class="bi bi-plus-lg me-2"></i>Add New Product</a>
              <a href="newsletter.php" class="btn btn-outline-light"><i class="bi bi-envelope me-2"></i>Send Newsletter</a>
              <a href="community-edit.php" class="btn btn-outline-light"><i class="bi bi-trophy me-2"></i>Create Challenge</a>
            </div>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
