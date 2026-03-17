<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
requireAdminAuth();

// --- Filter params ---
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate   = $_GET['end']   ?? date('Y-m-t');
if (!strtotime($startDate)) $startDate = date('Y-m-01');
if (!strtotime($endDate))   $endDate   = date('Y-m-t');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$pdo = getPDO();

// All tags + sub_tags (for Kelola Topik panel)
$allTags = $pdo->query("SELECT id, name, usage_count FROM tags ORDER BY name ASC")->fetchAll();
$subQ    = $pdo->prepare("SELECT id, name, usage_count FROM sub_tags WHERE tag_id = ? ORDER BY name ASC");
foreach ($allTags as &$t) {
    $subQ->execute([(int)$t['id']]);
    $t['sub_tags'] = $subQ->fetchAll();
}
unset($t);

// Stats
$stmtTotal = $pdo->prepare(
    "SELECT COUNT(*) FROM feedback WHERE created_at BETWEEN ? AND ?"
);
$stmtTotal->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$totalFeedback = (int)$stmtTotal->fetchColumn();

$stmtIp = $pdo->prepare(
    "SELECT COUNT(DISTINCT ip_address) FROM feedback WHERE created_at BETWEEN ? AND ?"
);
$stmtIp->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$uniqueIps = (int)$stmtIp->fetchColumn();

// Tag distribution for chart
$stmtTagCount = $pdo->prepare(
    "SELECT t.name, COUNT(f.id) AS cnt
     FROM tags t
     LEFT JOIN feedback f ON f.tag_id = t.id
       AND f.created_at BETWEEN ? AND ?
     GROUP BY t.id, t.name
     HAVING cnt > 0
     ORDER BY cnt DESC"
);
$stmtTagCount->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$tagStats = $stmtTagCount->fetchAll();

// Paginated feedback list
$stmtFeedback = $pdo->prepare(
    "SELECT f.id, f.content, f.created_at,
            t.name AS tag_name, st.name AS sub_tag_name
     FROM feedback f
     LEFT JOIN tags t      ON t.id  = f.tag_id
     LEFT JOIN sub_tags st ON st.id = f.sub_tag_id
     WHERE f.created_at BETWEEN ? AND ?
     ORDER BY f.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmtFeedback->execute([
    $startDate . ' 00:00:00',
    $endDate   . ' 23:59:59',
    $perPage,
    $offset,
]);
$feedbackList = $stmtFeedback->fetchAll();

$totalPages  = (int)ceil($totalFeedback / $perPage);
$chartLabels = array_column($tagStats, 'name');
$chartValues = array_map(fn($r) => (int)$r['cnt'], $tagStats);

// Sub-tag distribution per tag (for per-tag pie charts)
$stmtSubTagDist = $pdo->prepare(
    "SELECT t.id AS tag_id, t.name AS tag_name,
            st.id AS sub_tag_id, st.name AS sub_tag_name,
            COUNT(f.id) AS cnt
     FROM tags t
     JOIN sub_tags st ON st.tag_id = t.id
     LEFT JOIN feedback f ON f.sub_tag_id = st.id
       AND f.created_at BETWEEN ? AND ?
     GROUP BY t.id, t.name, st.id, st.name
     ORDER BY t.name ASC, cnt DESC"
);
$stmtSubTagDist->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$perTagCharts = [];
foreach ($stmtSubTagDist->fetchAll() as $row) {
    $tid = (int)$row['tag_id'];
    if (!isset($perTagCharts[$tid])) {
        $perTagCharts[$tid] = ['name' => $row['tag_name'], 'labels' => [], 'values' => []];
    }
    $perTagCharts[$tid]['labels'][] = $row['sub_tag_name'];
    $perTagCharts[$tid]['values'][] = (int)$row['cnt'];
}

// All feedback for PDF export (no pagination)
$stmtAllFb = $pdo->prepare(
    "SELECT f.id, f.content, f.created_at,
            t.name AS tag_name, st.name AS sub_tag_name
     FROM feedback f
     LEFT JOIN tags t      ON t.id  = f.tag_id
     LEFT JOIN sub_tags st ON st.id = f.sub_tag_id
     WHERE f.created_at BETWEEN ? AND ?
     ORDER BY f.created_at DESC"
);
$stmtAllFb->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$allFeedbackForPdf = $stmtAllFb->fetchAll(PDO::FETCH_ASSOC);

// Logo base64 for PDF
$_logoPath   = dirname(__DIR__) . '/assets/logo-ia-ia-copy.png';
$_logoBase64 = is_readable($_logoPath)
    ? 'data:image/png;base64,' . base64_encode(file_get_contents($_logoPath))
    : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — AMANAH Digital</title>
  <link rel="icon" type="image/png" href="../assets/logo-ia-ia-copy.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <!-- Chart.js (no extra CSS needed) -->
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #f0f4f8; color: #1a202c; font-size: 0.9rem; }

    /* ── Header ─────────────────────────────── */
    .header {
      background: linear-gradient(135deg, #00acc1, #0288d1);
      color: #fff; padding: 0 2rem; height: 60px;
      display: flex; align-items: center; justify-content: space-between;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15); position: sticky; top: 0; z-index: 100;
    }
    .header-brand { display: flex; align-items: center; gap: 0.75rem; }
    .header-brand img { width: 36px; height: 36px; object-fit: contain; border-radius: 6px; }
    .header-brand h1 { font-size: 1.05rem; font-weight: 600; }
    .btn-logout {
      background: rgba(255,255,255,0.2); color: #fff;
      border: 1px solid rgba(255,255,255,0.4);
      padding: 0.38rem 1rem; border-radius: 6px;
      cursor: pointer; font-size: 0.83rem;
      transition: background 0.2s;
    }
    .btn-logout:hover { background: rgba(255,255,255,0.3); }

    /* ── Layout ──────────────────────────────── */
    .main { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }

    /* ── Filter bar ──────────────────────────── */
    .filter-bar {
      background: #fff; border-radius: 10px; padding: 1rem 1.5rem;
      display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap;
      box-shadow: 0 1px 4px rgba(0,0,0,0.07); margin-bottom: 1.5rem;
    }
    .filter-group label { display: block; font-size: 0.78rem; font-weight: 500; color: #6b7280; margin-bottom: 0.3rem; }
    .filter-group input[type="date"] {
      padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px;
      font-family: inherit; font-size: 0.875rem; outline: none;
    }
    .filter-group input:focus { border-color: #00acc1; }
    .btn-filter {
      padding: 0.5rem 1.5rem; background: #00acc1; color: #fff;
      border: none; border-radius: 6px; cursor: pointer; font-weight: 500;
      transition: filter 0.2s;
    }
    .btn-filter:hover { filter: brightness(1.1); }

    /* ── Stat cards ──────────────────────────── */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-card { background: #fff; border-radius: 10px; padding: 1.25rem 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    .stat-card .label { font-size: 0.78rem; color: #6b7280; margin-bottom: 0.4rem; }
    .stat-card .value { font-size: 2rem; font-weight: 700; color: #0288d1; }
    .stat-card .sub   { font-size: 0.72rem; color: #9ca3af; margin-top: 0.2rem; }

    /* ── Card ────────────────────────────────── */
    .card { background: #fff; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.07); margin-bottom: 1.5rem; }
    .card h3 { font-size: 0.95rem; font-weight: 600; color: #374151; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #e5e7eb; }

    /* ── Two-col grid ────────────────────────── */
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
    @media (max-width: 768px) { .two-col { grid-template-columns: 1fr; } }

    /* ── Chart ───────────────────────────────── */
    .chart-wrap { max-width: 300px; margin: 0 auto; }

    /* ── Tables ──────────────────────────────── */
    table { width: 100%; border-collapse: collapse; }
    thead th { text-align: left; padding: 0.6rem 0.75rem; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #6b7280; background: #f9fafb; border-bottom: 2px solid #e5e7eb; }
    tbody td { padding: 0.65rem 0.75rem; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    tbody tr:hover td { background: #f9fafb; }
    .badge { display: inline-block; padding: 0.2rem 0.55rem; border-radius: 20px; font-size: 0.73rem; font-weight: 500; background: #e0f7fa; color: #006064; }
    .badge-sub { background: #f3e8ff; color: #6b21a8; }
    .badge-new { background: #dcfce7; color: #15803d; }

    /* ── Buttons ─────────────────────────────── */
    .btn-sm { padding: 0.25rem 0.6rem; border-radius: 5px; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 500; transition: filter 0.15s; }
    .btn-sm:hover { filter: brightness(0.9); }
    .btn-add    { background: #dcfce7; color: #166534; }
    .btn-danger { background: #fee2e2; color: #991b1b; }
    .btn-primary { background: #00acc1; color: #fff; padding: 0.45rem 1rem; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500; transition: filter 0.2s; }
    .btn-primary:hover { filter: brightness(1.1); }

    /* ── Kelola Topik tree ───────────────────── */
    .tag-tree-item { border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 0.6rem; overflow: hidden; }
    .tag-tree-header { display: flex; align-items: center; justify-content: space-between; padding: 0.65rem 1rem; background: #f9fafb; cursor: pointer; }
    .tag-tree-header:hover { background: #f3f4f6; }
    .tag-tree-header .tag-name { font-weight: 500; color: #1f2937; font-size: 0.88rem; }
    .tag-tree-header .tag-meta { font-size: 0.73rem; color: #9ca3af; margin-left: 0.5rem; }
    .tag-actions { display: flex; gap: 0.4rem; align-items: center; }
    .subtag-body { padding: 0.5rem 1rem 0.75rem 2rem; display: none; }
    .subtag-body.open { display: block; }
    .subtag-row { display: flex; align-items: center; justify-content: space-between; padding: 0.3rem 0.5rem; border-radius: 5px; font-size: 0.83rem; color: #4b5563; }
    .subtag-row:hover { background: #f9fafb; }
    .subtag-add-row { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
    .subtag-add-row input { flex: 1; padding: 0.38rem 0.65rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.83rem; font-family: inherit; outline: none; }
    .subtag-add-row input:focus { border-color: #00acc1; }

    /* Add-tag form */
    .add-tag-row { display: flex; gap: 0.6rem; margin-top: 1rem; }
    .add-tag-row input { flex: 1; padding: 0.45rem 0.75rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; font-family: inherit; outline: none; }
    .add-tag-row input:focus { border-color: #00acc1; }

    /* ── Pagination ──────────────────────────── */
    .pagination { display: flex; gap: 0.4rem; align-items: center; justify-content: center; margin-top: 1rem; flex-wrap: wrap; }
    .pagination a, .pagination span { padding: 0.38rem 0.7rem; border-radius: 6px; font-size: 0.83rem; text-decoration: none; color: #374151; border: 1px solid #e5e7eb; }
    .pagination a:hover { background: #e0f7fa; border-color: #00acc1; }
    .pagination span.active { background: #00acc1; color: #fff; border-color: #00acc1; }

    /* ── Feedback content cell ───────────────── */
    .content-preview { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; color: #374151; max-width: 380px; }
    .ts { font-size: 0.73rem; color: #9ca3af; white-space: nowrap; }

    /* ── Select2 override ────────────────────── */
    .select2-container--default .select2-selection--single { border-color: #d1d5db; border-radius: 6px; height: 36px; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 34px; }

    /* ── Per-tag sub-tag pie charts ─────────── */
    .charts-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem; }
    .subtag-chart-wrap { background: #fff; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 4px rgba(0,0,0,0.07); display: flex; flex-direction: column; align-items: center; }
    .subtag-chart-title { font-size: 0.88rem; font-weight: 600; color: #1f2937; margin-bottom: 0.85rem; text-align: center; padding-bottom: 0.6rem; border-bottom: 1px solid #e5e7eb; width: 100%; }
    .no-chart-data { font-size: 0.78rem; color: #9ca3af; text-align: center; padding: 1.5rem 0; }
  </style>
</head>
<body>

<header class="header">
  <div class="header-brand">
    <img src="../assets/logo-ia-ia-copy.png" alt="Logo">
    <h1>AMANAH Digital — Dashboard Admin</h1>
  </div>
  <a href="logout.php">
    <button class="btn-logout">Keluar</button>
  </a>
</header>

<div class="main">

  <!-- ── Filter Bar ─────────────────────── -->
  <form method="GET" action="" class="filter-bar">
    <div class="filter-group">
      <label>Dari Tanggal</label>
      <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>">
    </div>
    <div class="filter-group">
      <label>Sampai Tanggal</label>
      <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>">
    </div>
    <button type="submit" class="btn-filter">Tampilkan</button>
  </form>

  <!-- ── Stats ──────────────────────────── -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="label">Total Masukan</div>
      <div class="value"><?= $totalFeedback ?></div>
      <div class="sub"><?= htmlspecialchars($startDate) ?> s/d <?= htmlspecialchars($endDate) ?></div>
    </div>
    <div class="stat-card">
      <div class="label">IP Unik</div>
      <div class="value"><?= $uniqueIps ?></div>
      <div class="sub">Pengirim berbeda</div>
    </div>
    <div class="stat-card">
      <div class="label">Total Topik</div>
      <div class="value"><?= count($allTags) ?></div>
      <div class="sub">Terdaftar di sistem</div>
    </div>
  </div>

  <!-- ── Chart + Tag recap ──────────────── -->
  <div class="two-col">
    <div class="card">
      <h3>Distribusi Topik (Periode)</h3>
      <?php if (empty($chartLabels)): ?>
        <p style="color:#9ca3af;text-align:center;padding:2rem 0">Belum ada data pada periode ini.</p>
      <?php else: ?>
        <div class="chart-wrap">
          <canvas id="topicChart"></canvas>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Rekap per Topik</h3>
      <?php if (empty($tagStats)): ?>
        <p style="color:#9ca3af">Tidak ada data untuk periode ini.</p>
      <?php else: ?>
        <table>
          <thead><tr><th>#</th><th>Topik</th><th>Jumlah</th></tr></thead>
          <tbody>
            <?php foreach ($tagStats as $i => $row): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><span class="badge"><?= (int)$row['cnt'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Per-tag Sub-tag Pie Charts ──────── -->
  <?php if (!empty($perTagCharts)): ?>
  <div class="card" style="margin-bottom:1.5rem">
    <h3>Distribusi Sub-Topik per Topik (Periode)</h3>
    <div class="charts-grid">
      <?php foreach ($perTagCharts as $tagId => $data):
            $total = array_sum($data['values']); ?>
      <div class="subtag-chart-wrap">
        <div class="subtag-chart-title"><?= htmlspecialchars($data['name']) ?></div>
        <?php if ($total === 0): ?>
          <p class="no-chart-data">Belum ada masukan pada periode ini.</p>
        <?php else: ?>
          <canvas id="subChart-<?= $tagId ?>" style="max-height:220px"></canvas>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Kelola Topik ───────────────────── -->
  <div class="card">
    <h3>Kelola Topik &amp; Sub-Topik</h3>

    <div id="tag-tree">
      <?php foreach ($allTags as $tag): ?>
      <div class="tag-tree-item" id="tag-item-<?= $tag['id'] ?>">
        <div class="tag-tree-header" onclick="toggleSubtags(<?= $tag['id'] ?>)">
          <div>
            <span class="tag-name"><?= htmlspecialchars($tag['name']) ?></span>
            <span class="tag-meta">(<?= count($tag['sub_tags']) ?> sub-topik &bull; dipakai <?= (int)$tag['usage_count'] ?>×)</span>
          </div>
          <div class="tag-actions" onclick="event.stopPropagation()">
            <button class="btn-sm btn-danger" onclick="deleteTag(<?= $tag['id'] ?>, '<?= htmlspecialchars(addslashes($tag['name'])) ?>')">Hapus</button>
          </div>
        </div>
        <div class="subtag-body" id="subtags-<?= $tag['id'] ?>">
          <?php foreach ($tag['sub_tags'] as $sub): ?>
          <div class="subtag-row" id="subtag-row-<?= $sub['id'] ?>">
            <span><?= htmlspecialchars($sub['name']) ?> <span style="font-size:0.7rem;color:#9ca3af">(<?= (int)$sub['usage_count'] ?>×)</span></span>
            <button class="btn-sm btn-danger" onclick="deleteSubtag(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['name'])) ?>')">Hapus</button>
          </div>
          <?php endforeach; ?>
          <div class="subtag-add-row">
            <input type="text" id="new-sub-<?= $tag['id'] ?>" placeholder="Nama sub-topik baru...">
            <button class="btn-sm btn-add" onclick="addSubtag(<?= $tag['id'] ?>)">+ Tambah</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="add-tag-row">
      <input type="text" id="new-tag-name" placeholder="Nama topik baru...">
      <button class="btn-primary" onclick="addTag()">+ Tambah Topik</button>
    </div>
  </div>

  <!-- ── Feedback List ──────────────────── -->
  <div class="card">
    <h3>Daftar Masukan (<?= $totalFeedback ?> total)</h3>

    <?php if (empty($feedbackList)): ?>
      <p style="color:#9ca3af;padding:1rem 0">Belum ada masukan untuk periode ini.</p>
    <?php else: ?>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>No</th>
              <th>Waktu</th>
              <th>Topik</th>
              <th>Sub-Topik</th>
              <th>Isi Masukan</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($feedbackList as $i => $fb): ?>
            <tr>
              <td><?= $offset + $i + 1 ?></td>
              <td class="ts"><?= date('d M Y H:i', strtotime($fb['created_at'])) ?></td>
              <td><?php if ($fb['tag_name']): ?><span class="badge"><?= htmlspecialchars($fb['tag_name']) ?></span><?php else: ?>—<?php endif; ?></td>
              <td><?php if ($fb['sub_tag_name']): ?><span class="badge badge-sub"><?= htmlspecialchars($fb['sub_tag_name']) ?></span><?php else: ?>—<?php endif; ?></td>
              <td><div class="content-preview"><?= htmlspecialchars($fb['content']) ?></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php if ($page > 1): ?>
          <a href="?start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>&page=<?= $page - 1 ?>">‹ Prev</a>
        <?php endif; ?>

        <?php
        $range = 2;
        $from  = max(1, $page - $range);
        $to    = min($totalPages, $page + $range);
        if ($from > 1) echo '<span>…</span>';
        for ($p = $from; $p <= $to; $p++):
        ?>
          <?php if ($p === $page): ?>
            <span class="active"><?= $p ?></span>
          <?php else: ?>
            <a href="?start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>&page=<?= $p ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($to < $totalPages) echo '<span>…</span>'; ?>

        <?php if ($page < $totalPages): ?>
          <a href="?start=<?= urlencode($startDate) ?>&end=<?= urlencode($endDate) ?>&page=<?= $page + 1 ?>">Next ›</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- ── Export PDF Button ─────────────── -->
  <div style="text-align:center;margin:0.5rem 0 1.5rem">
    <button class="btn-primary" onclick="exportPDF()" style="padding:0.7rem 2.5rem;font-size:0.95rem;letter-spacing:0.02em">
      &#8675; Export PDF
    </button>
  </div>

</div><!-- /.main -->

<!-- ── Scripts ──────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
/* ── Chart ─────────────────────────────── */
<?php if (!empty($chartLabels)): ?>
(function() {
  const ctx = document.getElementById('topicChart').getContext('2d');
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
      datasets: [{
        data: <?= json_encode($chartValues) ?>,
        backgroundColor: [
          '#00acc1','#0288d1','#26c6da','#4fc3f7','#81d4fa',
          '#b3e5fc','#e0f7fa','#006064','#01579b','#80deea',
        ],
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } }
      }
    }
  });
})();
<?php endif; ?>

/* ── Per-tag sub-tag pie charts ─────────── */
const CHART_PALETTE = [
  '#FF6384','#36A2EB','#FFCE56','#4BC0C0','#9966FF','#FF9F40',
  '#F7464A','#46BFBD','#FDB45C','#949FB1','#AC64AD','#00acc1',
  '#0288d1','#26c6da','#7C4DFF','#00BFA5','#FFD740','#FF6D00',
  '#D50000','#00C853','#2979FF','#AA00FF','#F50057','#00B0FF',
];
<?php foreach ($perTagCharts as $tagId => $data): ?>
<?php if (array_sum($data['values']) > 0): ?>
(function() {
  const n = <?= count($data['labels']) ?>;
  const colors = Array.from({length: n}, (_, i) => CHART_PALETTE[i % CHART_PALETTE.length]);
  const ctx = document.getElementById('subChart-<?= $tagId ?>').getContext('2d');
  new Chart(ctx, {
    type: 'pie',
    data: {
      labels: <?= json_encode($data['labels'], JSON_UNESCAPED_UNICODE) ?>,
      datasets: [{
        data: <?= json_encode($data['values']) ?>,
        backgroundColor: colors,
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 8, boxWidth: 12 } },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
              const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
              return ` ${ctx.label}: ${ctx.parsed}× (${pct}%)`;
            }
          }
        }
      }
    }
  });
})();
<?php endif; ?>
<?php endforeach; ?>

/* ── Toggle sub-tags panel ──────────────── */
function toggleSubtags(tagId) {
  const body = document.getElementById('subtags-' + tagId);
  body.classList.toggle('open');
}

/* ── Kelola: Add Tag ────────────────────── */
function addTag() {
  const input = document.getElementById('new-tag-name');
  const name  = input.value.trim();
  if (!name) { Swal.fire({ icon:'warning', title:'Nama topik kosong', text:'Masukkan nama topik terlebih dahulu.', confirmButtonColor:'#00acc1' }); return; }

  fetch('api/manage-tags.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action: 'add_tag', name })
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) { Swal.fire({ icon:'error', title:'Gagal', text: data.error, confirmButtonColor:'#00acc1' }); return; }
    Swal.fire({ icon:'success', title:'Berhasil', text: data.message, confirmButtonColor:'#00acc1', timer:1800, showConfirmButton:false });
    input.value = '';
    appendTagToTree(data.id, data.name);
  })
  .catch(() => Swal.fire({ icon:'error', title:'Kesalahan jaringan', confirmButtonColor:'#00acc1' }));
}

function appendTagToTree(id, name) {
  const tree = document.getElementById('tag-tree');
  const html = `
    <div class="tag-tree-item" id="tag-item-${id}">
      <div class="tag-tree-header" onclick="toggleSubtags(${id})">
        <div>
          <span class="tag-name">${escHtml(name)}</span>
          <span class="tag-meta">(0 sub-topik &bull; dipakai 0×)</span>
        </div>
        <div class="tag-actions" onclick="event.stopPropagation()">
          <button class="btn-sm btn-danger" onclick="deleteTag(${id}, '${escJs(name)}')">Hapus</button>
        </div>
      </div>
      <div class="subtag-body" id="subtags-${id}">
        <div class="subtag-add-row">
          <input type="text" id="new-sub-${id}" placeholder="Nama sub-topik baru...">
          <button class="btn-sm btn-add" onclick="addSubtag(${id})">+ Tambah</button>
        </div>
      </div>
    </div>`;
  tree.insertAdjacentHTML('beforeend', html);
}

/* ── Kelola: Delete Tag ─────────────────── */
function deleteTag(id, name) {
  Swal.fire({
    icon: 'warning', title: 'Hapus Topik?',
    text: `Menghapus "${name}" akan menghapus semua sub-topiknya juga.`,
    showCancelButton: true, confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal', confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280'
  }).then(result => {
    if (!result.isConfirmed) return;
    fetch('api/manage-tags.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'delete_tag', id })
    })
    .then(r => r.json())
    .then(data => {
      if (data.error) { Swal.fire({ icon:'error', title:'Gagal', text: data.error, confirmButtonColor:'#00acc1' }); return; }
      document.getElementById('tag-item-' + id)?.remove();
      Swal.fire({ icon:'success', title:'Dihapus', text: data.message, timer:1600, showConfirmButton:false });
    });
  });
}

/* ── Kelola: Add Sub-tag ────────────────── */
function addSubtag(tagId) {
  const input = document.getElementById('new-sub-' + tagId);
  const name  = input.value.trim();
  if (!name) { Swal.fire({ icon:'warning', title:'Nama kosong', text:'Masukkan nama sub-topik.', confirmButtonColor:'#00acc1' }); return; }

  fetch('api/manage-tags.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action: 'add_subtag', tag_id: tagId, name })
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) { Swal.fire({ icon:'error', title:'Gagal', text: data.error, confirmButtonColor:'#00acc1' }); return; }
    Swal.fire({ icon:'success', title:'Berhasil', text: data.message, confirmButtonColor:'#00acc1', timer:1600, showConfirmButton:false });
    input.value = '';
    const addRow = document.querySelector(`#subtags-${tagId} .subtag-add-row`);
    const html = `
      <div class="subtag-row" id="subtag-row-${data.id}">
        <span>${escHtml(name)} <span style="font-size:0.7rem;color:#9ca3af">(0×)</span></span>
        <button class="btn-sm btn-danger" onclick="deleteSubtag(${data.id}, '${escJs(name)}')">Hapus</button>
      </div>`;
    addRow.insertAdjacentHTML('beforebegin', html);
  })
  .catch(() => Swal.fire({ icon:'error', title:'Kesalahan jaringan', confirmButtonColor:'#00acc1' }));
}

/* ── Kelola: Delete Sub-tag ─────────────── */
function deleteSubtag(id, name) {
  Swal.fire({
    icon: 'warning', title: 'Hapus Sub-topik?',
    text: `"${name}" akan dihapus dari sistem.`,
    showCancelButton: true, confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal', confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280'
  }).then(result => {
    if (!result.isConfirmed) return;
    fetch('api/manage-tags.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'delete_subtag', id })
    })
    .then(r => r.json())
    .then(data => {
      if (data.error) { Swal.fire({ icon:'error', title:'Gagal', text: data.error, confirmButtonColor:'#00acc1' }); return; }
      document.getElementById('subtag-row-' + id)?.remove();
      Swal.fire({ icon:'success', title:'Dihapus', text: data.message, timer:1600, showConfirmButton:false });
    });
  });
}

/* ── Helpers ────────────────────────────── */
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(s) {
  return String(s).replace(/'/g,"\\'").replace(/\\/g,'\\\\');
}

/* ── Export PDF ─────────────────────────── */
function exportPDF() {
  const logoSrc    = <?= json_encode($_logoBase64) ?>;
  const startDate  = <?= json_encode($startDate) ?>;
  const endDate    = <?= json_encode($endDate) ?>;
  const totalFb    = <?= $totalFeedback ?>;
  const uniqueIps  = <?= $uniqueIps ?>;
  const totalTopics= <?= count($allTags) ?>;
  const feedback   = <?= json_encode($allFeedbackForPdf, JSON_UNESCAPED_UNICODE) ?>;

  // Capture chart canvas images
  const topicCanvas = document.getElementById('topicChart');
  const topicImg    = topicCanvas ? topicCanvas.toDataURL('image/png') : null;

  const perTagImgs = [];
  <?php foreach ($perTagCharts as $tagId => $data): ?>
  <?php if (array_sum($data['values']) > 0): ?>
  (function(){
    const c = document.getElementById('subChart-<?= $tagId ?>');
    if (c) perTagImgs.push({ name: <?= json_encode($data['name'], JSON_UNESCAPED_UNICODE) ?>, img: c.toDataURL('image/png') });
  })();
  <?php endif; ?>
  <?php endforeach; ?>

  // Build chart section
  let chartsHtml = '';
  if (topicImg) {
    chartsHtml += `<div class="chart-item"><div class="chart-label">Distribusi Topik (Semua)</div><img src="${topicImg}"></div>`;
  }
  perTagImgs.forEach(c => {
    chartsHtml += `<div class="chart-item"><div class="chart-label">${escHtml(c.name)}</div><img src="${c.img}"></div>`;
  });

  // Build table rows
  let rows = '';
  feedback.forEach((f, i) => {
    const dt = new Date(f.created_at.replace(' ', 'T'));
    const fmtDate = isNaN(dt) ? f.created_at : dt.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'}) + ' ' + dt.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
    rows += `<tr>
      <td style="white-space:nowrap">${i+1}</td>
      <td style="white-space:nowrap">${escHtml(fmtDate)}</td>
      <td><span class="badge">${f.tag_name ? escHtml(f.tag_name) : '—'}</span></td>
      <td><span class="badge badge-sub">${f.sub_tag_name ? escHtml(f.sub_tag_name) : '—'}</span></td>
      <td>${escHtml(f.content)}</td>
    </tr>`;
  });

  const printHtml = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Laporan AMANAH Digital</title>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Arial,sans-serif;font-size:11px;color:#1a202c;padding:24px 28px;background:#fff}
    .pdf-header{display:flex;align-items:center;gap:16px;border-bottom:3px solid #0288d1;padding-bottom:14px;margin-bottom:18px}
    .pdf-header img{width:72px;height:72px;object-fit:contain;border-radius:10px}
    .pdf-header-text h1{font-size:15px;color:#0288d1;margin-bottom:3px}
    .pdf-header-text h2{font-size:12px;color:#1a202c;font-weight:600;margin-bottom:6px}
    .pdf-header-text p{font-size:10px;color:#6b7280;margin-bottom:2px}
    .stats{display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap}
    .stat-box{border:1px solid #e5e7eb;border-radius:8px;padding:10px 18px;min-width:130px;background:#f0f8ff}
    .stat-box .lbl{font-size:10px;color:#6b7280;margin-bottom:4px}
    .stat-box .val{font-size:22px;font-weight:700;color:#0288d1}
    .section-title{font-size:12px;font-weight:700;color:#1f2937;border-bottom:1px solid #e5e7eb;padding-bottom:6px;margin:18px 0 12px}
    .charts-grid{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:8px}
    .chart-item{text-align:center;border:1px solid #e5e7eb;border-radius:8px;padding:10px;background:#fafafa}
    .chart-item img{max-width:200px;max-height:200px;display:block;margin:0 auto}
    .chart-label{font-size:10px;font-weight:600;color:#374151;margin-bottom:6px}
    table{width:100%;border-collapse:collapse;font-size:10px}
    thead th{background:#e0f7fa;padding:6px 8px;text-align:left;border-bottom:2px solid #0288d1;font-size:10px;color:#006064}
    tbody td{padding:5px 8px;border-bottom:1px solid #f3f4f6;vertical-align:top;line-height:1.5}
    tbody tr:nth-child(even) td{background:#f9fafb}
    .badge{display:inline-block;padding:1px 7px;border-radius:20px;font-size:9px;font-weight:600;background:#e0f7fa;color:#006064;border:1px solid #b2ebf2}
    .badge-sub{background:#f3e8ff;color:#6b21a8;border-color:#e9d5ff}
    .footer{margin-top:24px;text-align:center;font-size:9px;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:8px}
    @media print{
      body{padding:0}
      @page{margin:14mm 12mm;size:A4 portrait}
      thead{display:table-header-group}
    }
  </style>
</head>
<body>
  <div class="pdf-header">
    ${logoSrc ? `<img src="${logoSrc}" alt="Logo Ihsanul Amal">` : ''}
    <div class="pdf-header-text">
      <h1>Sistem Aduan &amp; Aspirasi Orang Tua 2026</h1>
      <h2>Yayasan Ihsanul Amal Alabio</h2>
      <p>Periode&nbsp;: ${startDate} s/d ${endDate}</p>
      <p>Dicetak&nbsp;: ${new Date().toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'})}</p>
    </div>
  </div>

  <div class="stats">
    <div class="stat-box"><div class="lbl">Total Masukan</div><div class="val">${totalFb}</div></div>
    <div class="stat-box"><div class="lbl">IP Unik</div><div class="val">${uniqueIps}</div></div>
    <div class="stat-box"><div class="lbl">Total Topik</div><div class="val">${totalTopics}</div></div>
  </div>

  ${chartsHtml ? `<div class="section-title">Grafik Distribusi</div><div class="charts-grid">${chartsHtml}</div>` : ''}

  <div class="section-title">Daftar Masukan (${feedback.length} entri)</div>
  <table>
    <thead>
      <tr><th style="width:28px">#</th><th style="width:100px">Waktu</th><th style="width:90px">Topik</th><th style="width:90px">Sub-Topik</th><th>Isi Masukan</th></tr>
    </thead>
    <tbody>${rows}</tbody>
  </table>

  <div class="footer">AMANAH Digital &mdash; Yayasan Ihsanul Amal Alabio &mdash; Dokumen ini dicetak otomatis oleh sistem</div>
</body>
</html>`;

  const win = window.open('', '_blank');
  if (!win) {
    Swal.fire({ icon:'warning', title:'Pop-up Diblokir', text:'Izinkan pop-up untuk browser ini agar ekspor PDF dapat berjalan.', confirmButtonColor:'#00acc1' });
    return;
  }
  win.document.write(printHtml);
  win.document.close();
  win.addEventListener('load', () => setTimeout(() => win.print(), 400));
}
</script>

</body>
</html>
