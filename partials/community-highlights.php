<?php
/* partials/community-highlights.php — FORCE 3 INLINE CARDS */

$DB = new mysqli("localhost","root","","dragonstone.db");
$cards = [];
if (!$DB->connect_error && $DB->query("SHOW TABLES LIKE 'community_posts'")->num_rows) {
  $hasStatus = $DB->query("SHOW COLUMNS FROM community_posts LIKE 'status'")->num_rows > 0;
  $orderCol  = $DB->query("SHOW COLUMNS FROM community_posts LIKE 'updated_at'")->num_rows ? 'updated_at'
            : ($DB->query("SHOW COLUMNS FROM community_posts LIKE 'created_at'")->num_rows ? 'created_at' : 'id');

  $sql = "SELECT id,title,excerpt,content,type,thumbnail,{$orderCol} AS dt
          FROM community_posts ".
          ($hasStatus ? "WHERE status='published' " : "").
          "ORDER BY dt DESC LIMIT 3";
  if ($res = $DB->query($sql)) while ($row = $res->fetch_assoc()) $cards[] = $row;
}
$DB->close();

$label = ['challenge'=>'Challenge','guide'=>'DIY Guide','success'=>'Success Story'];
$pts   = ['challenge'=>250,'guide'=>50,'success'=>100];

function cover_src($title,$thumb){
  if ($thumb) return 'images/community/'.htmlspecialchars($thumb);
  $map = [
    'Zero Waste Week'=>'Zero Waste Week.png',
    'Natural Cleaning Solutions'=>'Natural Cleaning Solutions.png',
    'My Plastic-Free Journey'=>'My Plastic-free Journey.png'
  ];
  foreach($map as $k=>$v){ if (stripos($title,$k)!==false) return 'images/community/'.$v; }
  return 'images/community/Zero Waste Week.png';
}
function excerpt($t,$n=140){ $t=strip_tags($t??''); return mb_strlen($t)>$n?mb_substr($t,0,$n).'…':$t; }
?>

<section class="community-highlights" style="background:#f7fcf9;padding:56px 0;">
  <div class="container-fluid" style="max-width:1400px;margin:0 auto;">
    <h2 class="fw-bold mb-2 text-center" style="letter-spacing:.2px;color:#0f172a;">Community Highlights</h2>
    <p class="text-muted mb-5 text-center">Be inspired by our amazing community members making real change happen every day</p>

    <?php if (empty($cards)): ?>
      <div class="text-muted text-center">No community content yet. Check back soon!</div>
    <?php else: ?>

      <!-- THE GRID (inline CSS so nothing can override it) -->
      <div class="ch-grid"
           style="
             display:grid !important;
             grid-template-columns:repeat(3,minmax(0,1fr)) !important;
             gap:24px !important;
             align-items:stretch !important;
             width:100% !important;
           ">

        <?php foreach ($cards as $c):
          $id    = (int)$c['id'];
          $title = $c['title'] ?? 'Untitled';
          $type  = strtolower($c['type'] ?? '');
          $badge = $label[$type] ?? ucfirst($type ?: 'Post');
          $eco   = $pts[$type] ?? null;
          $img   = cover_src($title, $c['thumbnail'] ?? '');
          $desc  = !empty($c['excerpt']) ? $c['excerpt'] : excerpt($c['content'] ?? '');
          $href  = "community-post.php?id={$id}";
        ?>

        <!-- each cell -->
        <a href="<?= htmlspecialchars($href) ?>" class="ch-cell"
           style="display:block !important; width:100% !important; text-decoration:none;">
          <div class="ch-card"
               style="width:100% !important; height:100% !important; border:1px solid #e6edf5; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 .25rem .75rem rgba(2,6,23,.06);">
            <div class="ch-media"
                 style="position:relative; width:100%; aspect-ratio:16/9; background:#f2f6f9; overflow:hidden;">
              <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($title) ?>"
                   style="width:100%; height:100%; object-fit:cover; display:block;">
              <span style="position:absolute; top:12px; left:12px; background:#16a34a; color:#fff; font-weight:700; padding:.45rem .9rem; border-radius:999px; font-size:.95rem;">
                <?= htmlspecialchars($badge) ?>
              </span>
              <?php if ($eco): ?>
              <span style="position:absolute; right:12px; bottom:12px; background:#eafff2; color:#065f46; border:1px solid #bbf7d0; font-weight:700; padding:.45rem .9rem; border-radius:999px; font-size:.95rem;">
                +<?= (int)$eco ?> EcoPoints
              </span>
              <?php endif; ?>
            </div>
            <div class="p-3" style="text-align:left;">
              <h3 class="h5 fw-bold mb-2" style="color:#0f172a; margin:0 0 .5rem 0;"><?= htmlspecialchars($title) ?></h3>
              <?php if ($desc): ?>
                <p class="mb-0" style="color:#64748b;"><?= htmlspecialchars($desc) ?></p>
              <?php endif; ?>
            </div>
          </div>
        </a>

        <?php endforeach; ?>
      </div>

      <div style="display:flex; justify-content:center; gap:12px; margin-top:36px;">
        <a href="community.php" class="btn btn-success px-4 py-2 rounded-pill fw-semibold">Join Community</a>
        <a href="community-submit.php" class="btn btn-outline-success px-4 py-2 rounded-pill fw-semibold">Share Your Story</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Only collapse to 1 column on tiny phones -->
  <style>
    @media (max-width: 599.98px){
      .community-highlights .ch-grid{ grid-template-columns:1fr !important; }
    }
  </style>
</section>
