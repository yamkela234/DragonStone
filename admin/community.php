<?php
// admin/community.php
require __DIR__ . '/_guard.php';

// ---- who am I
$USER_ID   = (int)($_SESSION['user_id'] ?? 0);
$USER_NAME = $_SESSION['name'] ?? 'User';
$USER_MAIL = $_SESSION['email'] ?? 'user@dragonstone.com';
$ROLE      = strtolower($_SESSION['role'] ?? 'customer');
$IS_ADMIN  = (int)($_SESSION['is_admin'] ?? 0);

// ---- incoming filters
$q      = trim($_GET['q'] ?? '');
$fType  = $_GET['type']   ?? 'all';
$fStat  = $_GET['status'] ?? 'all';

// ---- column probe (so we never select a missing column)
$colsRes = $mysqli->query("SHOW COLUMNS FROM community_posts");
$cols = [];
while ($colsRes && $c = $colsRes->fetch_assoc()) $cols[] = strtolower($c['Field']);

$hasType       = in_array('type', $cols);
$hasStatus     = in_array('status', $cols);
$hasAuthorCol  = in_array('author', $cols);
$hasEngagement = in_array('engagement', $cols);
$hasThumb      = in_array('thumbnail', $cols);
$hasUpdated    = in_array('updated_at', $cols);
$hasCreated    = in_array('created_at', $cols);

// ---- KPI counts (safe even if columns are absent)
if ($hasType) {
  $cntChallenge = safeCount($mysqli,"SELECT COUNT(*) FROM community_posts WHERE type='challenge'");
  $cntGuide     = safeCount($mysqli,"SELECT COUNT(*) FROM community_posts WHERE type='guide'");
  $cntSuccess   = safeCount($mysqli,"SELECT COUNT(*) FROM community_posts WHERE type='success'");
} else {
  // fallback: everything in one bucket
  $totalAll     = safeCount($mysqli,"SELECT COUNT(*) FROM community_posts");
  $cntChallenge = $totalAll;
  $cntGuide     = 0;
  $cntSuccess   = 0;
}

// total members (if users table exists)
$hasUsersTbl = $mysqli->query("SHOW TABLES LIKE 'users'")->num_rows > 0;
$cntMembers  = $hasUsersTbl ? safeCount($mysqli,"SELECT COUNT(*) FROM users") : 0;

// ---- list fetch (authors see only theirs) + filters (only if columns exist)
$whereParts = [];
$whereParts[] = ($IS_ADMIN===1) ? "1=1" : "user_id=".$USER_ID;

if ($q !== '') {
  $qq = $mysqli->real_escape_string($q);
  $likeable = $hasType ? "(title LIKE '%{$qq}%' OR type LIKE '%{$qq}%')" : "(title LIKE '%{$qq}%')";
  $whereParts[] = $likeable;
}
if ($hasType && $fType !== 'all') {
  $tt = $mysqli->real_escape_string($fType);
  $whereParts[] = "type='{$tt}'";
}
if ($hasStatus && $fStat !== 'all') {
  $ss = $mysqli->real_escape_string($fStat);
  $whereParts[] = "status='{$ss}'";
}
$WHERE = implode(' AND ', $whereParts);

// build SELECT list based on existing columns
$selectCols = ['id','user_id','title'];
if ($hasType)       $selectCols[] = 'type';
if ($hasStatus)     $selectCols[] = 'status';
if ($hasThumb)      $selectCols[] = 'thumbnail';
if ($hasAuthorCol)  $selectCols[] = 'author';
if ($hasEngagement) $selectCols[] = 'engagement';
if ($hasUpdated)    $selectCols[] = 'updated_at';
if ($hasCreated)    $selectCols[] = 'created_at';
$selectSQL = implode(',', $selectCols);

// choose an order column that exists
$orderCol = $hasUpdated ? 'updated_at' : ($hasCreated ? 'created_at' : 'id');

$sql = "SELECT {$selectSQL}
        FROM community_posts
        WHERE {$WHERE}
        ORDER BY {$orderCol} DESC
        LIMIT 200";
$rs  = $mysqli->query($sql);
$rows = [];
while($rs && $row=$rs->fetch_assoc()) $rows[]=$row;

// ---- helpers (unique to this file)
function _typeBadge($t){
  $map=['challenge'=>'Challenge','guide'=>'DIY Guide','success'=>'Success Story'];
  $t = strtolower((string)$t);
  return $map[$t] ?? ucfirst($t ?: '—');
}
function _statusPill($s){
  $s = strtolower((string)$s);
  if ($s==='published' || $s==='active') $c='success';
  elseif ($s==='draft') $c='secondary';
  elseif ($s==='archived') $c='dark';
  else $c='warning';
  return '<span class="badge rounded-pill text-bg-'.$c.'">'.($s?ucfirst($s):'—').'</span>';
}
function _safeDate($s){ $t=strtotime($s??''); return $t?date('Y-m-d', $t):'—'; }
$AVATAR = strtoupper(substr($USER_NAME,0,1));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Community • DragonStone Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../css/admin.css">
<style>
  /* Visual match to reference (dark UI, inline KPIs, compact table) */
  body{background:#0b0f12;}
  .admin-sidebar{width:270px;min-height:100vh;background:#12181d;color:#e8edf2}
  .admin-sidebar .nav-link{color:#cfd8e3;border-radius:.6rem}
  .admin-sidebar .nav-link.active{background:rgba(22,163,74,.16);color:#c8facc;border:1px solid rgba(22,163,74,.35)}
  .brand-badge{display:inline-grid;place-items:center;width:36px;height:36px;border-radius:.6rem;background:linear-gradient(135deg,#10b981,#34d399);color:#052b1e;font-weight:800}
  .admin-topbar{height:64px;background:#12181d;color:#e8edf2}
  .avatar{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;font-weight:700}
  .kpi-card{border:1px solid #1f2a34;background:#0f1419;color:#e8edf2}
  .kpi-ico{display:inline-grid;place-items:center;width:36px;height:36px;border-radius:.75rem}
  .card{border-color:#1f2a34;background:#0f1419;color:#e8edf2}
  .table>thead{color:#97a3af}
  .form-control, .form-select{background:#0e151a;border-color:#1f2a34;color:#e8edf2}
  .form-control::placeholder{color:#708090}
  .btn-success{background:#16a34a;border-color:#16a34a;color:#06220f;font-weight:700}
  .text-muted{color:#97a3af!important}
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
      <li class="nav-item"><a class="nav-link" href="products.php"><i class="bi bi-box-seam me-2"></i>Products</a></li>
      <li class="nav-item"><a class="nav-link active" href="community.php"><i class="bi bi-people me-2"></i>Community</a></li>
      <li class="nav-item"><a class="nav-link" href="newsletter.php"><i class="bi bi-envelope-open me-2"></i>Newsletter</a></li>
      <li class="nav-item"><a class="nav-link" href="analytics.php"><i class="bi bi-graph-up-arrow me-2"></i>Analytics</a></li>
      <li class="nav-item"><a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
      <li class="nav-item mt-2">
        <a class="nav-link text-danger" href="../backend/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
      </li>
    </ul>
  </aside>

  <!-- Main -->
  <main class="flex-grow-1">
    <!-- Topbar -->
    <div class="admin-topbar d-flex align-items-center justify-content-between px-4 border-bottom border-dark">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-people text-success"></i>
        <span class="fw-semibold">Community Management</span>
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

      <!-- KPI row: 4 inline cards -->
      <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
          <div class="kpi-card p-3 rounded-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted small">Active Challenges</span>
              <span class="kpi-ico bg-success-subtle text-success"><i class="bi bi-trophy"></i></span>
            </div>
            <div class="h4 mb-0"><?= number_format($cntChallenge) ?></div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="kpi-card p-3 rounded-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted small">DIY Guides</span>
              <span class="kpi-ico bg-success-subtle text-success"><i class="bi bi-clipboard-check"></i></span>
            </div>
            <div class="h4 mb-0"><?= number_format($cntGuide) ?></div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="kpi-card p-3 rounded-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted small">Success Stories</span>
              <span class="kpi-ico bg-success-subtle text-success"><i class="bi bi-heart"></i></span>
            </div>
            <div class="h4 mb-0"><?= number_format($cntSuccess) ?></div>
          </div>
        </div>
        <div class="col-12 col-md-3">
          <div class="kpi-card p-3 rounded-4 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="text-muted small">Total Members</span>
              <span class="kpi-ico bg-success-subtle text-success"><i class="bi bi-people-fill"></i></span>
            </div>
            <div class="h4 mb-0"><?= number_format($cntMembers) ?></div>
          </div>
        </div>
      </div>

      <!-- Action row: search + filters + create button -->
      <form method="get" class="row g-2 align-items-center mb-3">
        <div class="col-12 col-lg">
          <input type="text" class="form-control" name="q" placeholder="Search community content..." value="<?= htmlspecialchars($q) ?>">
        </div>
        <div class="col-6 col-lg-auto">
          <select class="form-select" name="type" <?= $hasType?'':'disabled' ?>>
            <option value="all" <?= $fType==='all'?'selected':''; ?>>All Types</option>
            <?php if ($hasType): ?>
              <option value="challenge" <?= $fType==='challenge'?'selected':''; ?>>Challenge</option>
              <option value="guide"     <?= $fType==='guide'?'selected':''; ?>>DIY Guide</option>
              <option value="success"   <?= $fType==='success'?'selected':''; ?>>Success Story</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-6 col-lg-auto">
          <select class="form-select" name="status" <?= $hasStatus?'':'disabled' ?>>
            <option value="all" <?= $fStat==='all'?'selected':''; ?>>All Status</option>
            <?php if ($hasStatus): ?>
              <option value="published" <?= $fStat==='published'?'selected':''; ?>>Published</option>
              <option value="draft"     <?= $fStat==='draft'?'selected':''; ?>>Draft</option>
              <option value="archived"  <?= $fStat==='archived'?'selected':''; ?>>Archived</option>
              <option value="active"    <?= $fStat==='active'?'selected':''; ?>>Active</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-12 col-lg-auto">
          <button class="btn btn-outline-secondary w-100" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
        </div>
        <div class="col-12 col-lg-auto ms-lg-auto">
          <a href="community-edit.php" class="btn btn-success w-100"><i class="bi bi-plus-lg me-1"></i>Create Content</a>
        </div>
      </form>

      <!-- Content list -->
      <div class="card rounded-4 shadow-sm">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-dark">
              <tr>
                <th style="width:56px"></th>
                <th>Title</th>
                <?php if ($hasType):   ?><th>Type</th><?php endif; ?>
                <?php if ($hasStatus): ?><th>Status</th><?php endif; ?>
                <th>Author</th>
                <th class="text-nowrap">Engagement</th>
                <th class="text-nowrap">Updated</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No content yet. Click “Create Content”.</td></tr>
              <?php else: foreach($rows as $r): ?>
                <tr>
                  <td>
                    <?php if (!empty($r['thumbnail'] ?? '')): ?>
                      <img src="../images/community/<?= htmlspecialchars($r['thumbnail']) ?>" alt="" class="rounded" style="width:48px;height:48px;object-fit:cover">
                    <?php else: ?>
                      <div class="bg-secondary rounded" style="opacity:.25;width:48px;height:48px;"></div>
                    <?php endif; ?>
                  </td>
                  <td class="fw-medium"><?= htmlspecialchars($r['title'] ?? '(untitled)') ?></td>

                  <?php if ($hasType): ?>
                    <td><span class="badge text-bg-light"><?= htmlspecialchars(_typeBadge($r['type'] ?? '')) ?></span></td>
                  <?php endif; ?>

                  <?php if ($hasStatus): ?>
                    <td><?= _statusPill($r['status'] ?? '') ?></td>
                  <?php endif; ?>

                  <td class="text-muted small">
                    <?php
                      $author = $hasAuthorCol ? ($r['author'] ?? '') : '';
                      echo htmlspecialchars($author !== '' ? $author : 'DragonStone Team');
                    ?>
                  </td>

                  <td class="text-muted small">
                    <?php
                      echo $hasEngagement && isset($r['engagement']) && $r['engagement']!==''
                           ? ((int)$r['engagement']).'%'
                           : '—';
                    ?>
                  </td>

                  <td class="text-muted small">
                    <?php
                      $ud = $hasUpdated ? ($r['updated_at'] ?? '') : ($hasCreated ? ($r['created_at'] ?? '') : '');
                      echo _safeDate($ud);
                    ?>
                  </td>

                  <td class="text-end">
                    <a href="community-edit.php?id=<?= (int)($r['id'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <?php if ($IS_ADMIN===1 || (int)($r['user_id'] ?? -1)===$USER_ID): ?>
                      <a href="community-delete.php?id=<?= (int)($r['id'] ?? 0) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this post?');"><i class="bi bi-trash"></i></a>
                    <?php endif; ?>
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
