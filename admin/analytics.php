<?php
/* ================================================================
   NEx-gEN Admin — Page Hit Analytics
   URL: /admin/analytics.php
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once dirname(__DIR__) . '/config/db.php';

/* ── Date range selector ─────────────────────────────────────── */
$range = $_GET['range'] ?? '7';           /* days */
$range = in_array($range, ['1','7','14','30','90']) ? (int)$range : 7;

/* ── Helper: run a query safely, return empty array on error ──── */
function qry($pdo, $sql, $params = []) {
    try {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    } catch (\PDOException $e) {
        return [];
    }
}
function qryOne($pdo, $sql, $params = []) {
    $rows = qry($pdo, $sql, $params);
    return $rows ? array_values($rows[0])[0] : 0;
}

/* ── Summary counts ──────────────────────────────────────────── */
$totalAll    = qryOne($pdo, "SELECT COUNT(*) FROM page_hits");
$totalToday  = qryOne($pdo, "SELECT COUNT(*) FROM page_hits WHERE DATE(created_at) = CURDATE()");
$totalWeek   = qryOne($pdo, "SELECT COUNT(*) FROM page_hits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$totalMonth  = qryOne($pdo, "SELECT COUNT(*) FROM page_hits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$uniqueToday = qryOne($pdo, "SELECT COUNT(DISTINCT ip_address) FROM page_hits WHERE DATE(created_at) = CURDATE()");
$uniqueRange = qryOne($pdo, "SELECT COUNT(DISTINCT ip_address) FROM page_hits WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$range]);

/* ── Top pages ───────────────────────────────────────────────── */
$topPages = qry($pdo,
    "SELECT page_path, COUNT(*) AS hits, COUNT(DISTINCT ip_address) AS unique_ips
     FROM page_hits
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY page_path
     ORDER BY hits DESC
     LIMIT 10",
    [$range]
);

/* ── Daily chart data (last $range days) ────────────────────── */
$dailyRows = qry($pdo,
    "SELECT DATE(created_at) AS day, COUNT(*) AS hits
     FROM page_hits
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY day
     ORDER BY day ASC",
    [$range]
);
/* Fill missing days with 0 */
$dailyMap = [];
foreach ($dailyRows as $r) { $dailyMap[$r['day']] = (int)$r['hits']; }
$dailyChart = [];
for ($d = $range - 1; $d >= 0; $d--) {
    $day = date('Y-m-d', strtotime("-{$d} days"));
    $dailyChart[] = ['day' => $day, 'hits' => $dailyMap[$day] ?? 0];
}
$dailyMax = max(1, max(array_column($dailyChart, 'hits')));

/* ── Hourly chart (today) ────────────────────────────────────── */
$hourlyRows = qry($pdo,
    "SELECT HOUR(created_at) AS hr, COUNT(*) AS hits
     FROM page_hits
     WHERE DATE(created_at) = CURDATE()
     GROUP BY hr
     ORDER BY hr ASC"
);
$hourlyMap = [];
foreach ($hourlyRows as $r) { $hourlyMap[(int)$r['hr']] = (int)$r['hits']; }
$hourlyMax = max(1, max($hourlyMap ?: [1]));

/* ── Top referrers ───────────────────────────────────────────── */
$referrers = qry($pdo,
    "SELECT referrer, COUNT(*) AS hits
     FROM page_hits
     WHERE referrer IS NOT NULL AND referrer != ''
       AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY referrer
     ORDER BY hits DESC
     LIMIT 10",
    [$range]
);

/* ── Top IPs ─────────────────────────────────────────────────── */
$topIPs = qry($pdo,
    "SELECT ip_address, COUNT(*) AS hits,
            COUNT(DISTINCT page_path) AS pages_visited,
            MAX(created_at) AS last_seen
     FROM page_hits
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
     GROUP BY ip_address
     ORDER BY hits DESC
     LIMIT 20",
    [$range]
);

/* ── Recent visitors (last 50) ───────────────────────────────── */
$recent = qry($pdo,
    "SELECT page_path, ip_address, user_agent, referrer, created_at
     FROM page_hits
     ORDER BY created_at DESC
     LIMIT 50"
);

/* ── Helper: parse browser name from user-agent ──────────────── */
function parseBrowser($ua) {
    if (!$ua) return 'Unknown';
    if (stripos($ua, 'Edg/')    !== false) return 'Edge';
    if (stripos($ua, 'Chrome')  !== false) return 'Chrome';
    if (stripos($ua, 'Firefox') !== false) return 'Firefox';
    if (stripos($ua, 'Safari')  !== false && stripos($ua, 'Chrome') === false) return 'Safari';
    if (stripos($ua, 'MSIE')    !== false || stripos($ua, 'Trident') !== false) return 'IE';
    if (stripos($ua, 'Mobile')  !== false) return 'Mobile';
    return 'Other';
}
function parseDevice($ua) {
    if (!$ua) return 'Unknown';
    if (stripos($ua, 'Mobi') !== false || stripos($ua, 'Android') !== false) return 'Mobile';
    if (stripos($ua, 'Tablet') !== false || stripos($ua, 'iPad') !== false)  return 'Tablet';
    return 'Desktop';
}

/* DB error check */
$dbError = '';
try { $pdo->query("SELECT 1 FROM page_hits LIMIT 1"); }
catch (\PDOException $e) {
    $dbError = 'Table not found — run db-setup-analytics.sql in phpMyAdmin first.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Analytics — Admin | NEx-gEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    /* ── Stat cards ── */
    .stat-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:.8rem; margin-bottom:1.4rem; }
    .stat-card { background:#fff; border-radius:12px; padding:1.1rem 1.2rem; box-shadow:0 1px 5px rgba(0,0,0,.07); text-align:center; }
    .stat-card strong { display:block; font-size:2rem; font-weight:700; color:#0f4e8a; line-height:1.1; }
    .stat-card span   { font-size:.73rem; color:#64748b; }

    /* ── Range selector ── */
    .range-bar { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1rem; }
    .range-bar a { padding:.3rem .9rem; border-radius:20px; font-size:.8rem; font-weight:600;
                   text-decoration:none; background:#f1f5f9; color:#64748b; border:1.5px solid transparent; }
    .range-bar a.active { background:#0f4e8a; color:#fff; }

    /* ── Bar chart ── */
    .chart-wrap { display:flex; align-items:flex-end; gap:2px; height:90px; padding:0 4px 4px;
                  border-bottom:1.5px solid #e2e8f0; }
    .chart-bar-col { display:flex; flex-direction:column; align-items:center; flex:1; }
    .chart-bar { width:100%; background:#bfdbfe; border-radius:3px 3px 0 0; transition:height .3s;
                 min-height:2px; }
    .chart-bar:hover { background:#3b82f6; }
    .chart-label { font-size:.6rem; color:#94a3b8; margin-top:3px; white-space:nowrap; overflow:hidden;
                   width:100%; text-align:center; }
    .chart-value { font-size:.65rem; color:#475569; font-weight:600; margin-bottom:2px; }

    /* Hourly chart — same style but wider bars since only 24 max */
    .chart-wrap--hourly .chart-bar { background:#a7f3d0; }
    .chart-wrap--hourly .chart-bar:hover { background:#10b981; }

    /* ── Tables ── */
    .analytics-table { width:100%; border-collapse:collapse; font-size:.84rem; }
    .analytics-table th { padding:.5rem .8rem; background:#f8fafc; font-weight:600; font-size:.76rem;
                          color:#475569; text-align:left; border-bottom:1.5px solid #e2e8f0; }
    .analytics-table td { padding:.5rem .8rem; border-bottom:1px solid #f1f5f9; vertical-align:top; }
    .analytics-table tr:last-child td { border-bottom:none; }
    .analytics-table tr:hover td { background:#f8fafc; }
    .bar-inline { display:inline-block; height:8px; background:#bfdbfe; border-radius:4px; vertical-align:middle; }

    /* ── IP badge ── */
    .ip-code { font-family:monospace; font-size:.8rem; background:#f1f5f9; padding:2px 7px;
               border-radius:6px; color:#334155; white-space:nowrap; }
    .page-tag { background:#eff6ff; color:#2563eb; border-radius:6px; padding:2px 7px;
                font-size:.76rem; font-weight:600; }
    .device-tag { font-size:.72rem; border-radius:6px; padding:2px 6px; font-weight:600; }
    .device-desktop { background:#d1fae5; color:#065f46; }
    .device-mobile  { background:#fef3c7; color:#92400e; }
    .device-tablet  { background:#dbeafe; color:#1d4ed8; }
    .browser-tag    { font-size:.72rem; color:#64748b; }

    /* ── Section header ── */
    .sec-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
                 color:#64748b; margin:0 0 .6rem; padding-bottom:.4rem; border-bottom:1.5px solid #f1f5f9; }
    .two-col { display:grid; grid-template-columns:1fr 1fr; gap:1.2rem; margin-bottom:1.2rem; }
    @media (max-width:768px) { .two-col { grid-template-columns:1fr; } }
    .db-error { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca;
                border-radius:8px; padding:14px 18px; font-size:.88rem; margin-bottom:18px;
                display:flex; align-items:center; gap:10px; }
  </style>
</head>
<body class="admin-page">

<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>Analytics</span>
  </div>
  <nav class="admin-header__nav">
    <a href="../index.html" target="_blank"><i class="fa-solid fa-globe"></i> View Site</a>
    <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </nav>
</header>

<div class="admin-body">
  <aside class="admin-sidebar">
    <ul>
      <li><a href="index.php"><i class="fa-solid fa-certificate"></i> Certificates</a></li>
      <li><a href="banners.php"><i class="fa-solid fa-images"></i> Banners</a></li>
      <li><a href="enquiries.php"><i class="fa-solid fa-clipboard-list"></i> Enquiries</a></li>
      <li><a href="students.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
      <li><a href="batches.php"><i class="fa-solid fa-clock"></i> Batch Slots</a></li>
      <li><a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> Attendance</a></li>
      <li><a href="holidays.php"><i class="fa-solid fa-calendar-xmark"></i> Holidays</a></li>
      <li><a href="sms-logs.php"><i class="fa-solid fa-comment-sms"></i> SMS Logs</a></li>
      <li class="active"><a href="analytics.php"><i class="fa-solid fa-chart-bar"></i> Analytics</a></li>
    </ul>
    <div class="admin-sidebar__stats">
      <div class="sidebar-stat">
        <strong><?= number_format((int)$totalAll) ?></strong>
        <span>Total Page Hits</span>
      </div>
      <div class="sidebar-stat" style="margin-top:8px">
        <strong style="color:#2563eb"><?= number_format((int)$totalToday) ?></strong>
        <span>Today</span>
      </div>
    </div>
  </aside>

  <main class="admin-main">

    <?php if ($dbError): ?>
    <div class="db-error">
      <i class="fa-solid fa-circle-exclamation"></i> <?= $dbError ?>
    </div>
    <?php endif; ?>

    <!-- ══ STAT CARDS ════════════════════════════════════════════ -->
    <div class="stat-cards">
      <div class="stat-card">
        <strong><?= number_format((int)$totalAll) ?></strong>
        <span>All Time</span>
      </div>
      <div class="stat-card">
        <strong style="color:#2563eb"><?= number_format((int)$totalToday) ?></strong>
        <span>Today</span>
      </div>
      <div class="stat-card">
        <strong style="color:#059669"><?= number_format((int)$totalWeek) ?></strong>
        <span>Last 7 Days</span>
      </div>
      <div class="stat-card">
        <strong style="color:#d97706"><?= number_format((int)$totalMonth) ?></strong>
        <span>Last 30 Days</span>
      </div>
      <div class="stat-card">
        <strong style="color:#7c3aed"><?= number_format((int)$uniqueToday) ?></strong>
        <span>Unique IPs Today</span>
      </div>
      <div class="stat-card">
        <strong style="color:#0891b2"><?= number_format((int)$uniqueRange) ?></strong>
        <span>Unique IPs (<?= $range ?>d)</span>
      </div>
    </div>

    <!-- ══ RANGE SELECTOR ════════════════════════════════════════ -->
    <div class="range-bar">
      <strong style="font-size:.8rem;color:#64748b;align-self:center">Show last:</strong>
      <?php foreach (['1'=>'Today','7'=>'7 Days','14'=>'14 Days','30'=>'30 Days','90'=>'90 Days'] as $v=>$l): ?>
      <a href="?range=<?= $v ?>" class="<?= $range == $v ? 'active' : '' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <!-- ══ DAILY CHART + HOURLY CHART ════════════════════════════ -->
    <div class="two-col">

      <!-- Daily chart -->
      <section class="admin-card">
        <div class="admin-card__header">
          <h2><i class="fa-solid fa-chart-bar"></i> Daily Hits — Last <?= $range ?> Days</h2>
        </div>
        <div style="padding:1rem 1.2rem">
          <div class="chart-wrap">
            <?php foreach ($dailyChart as $d):
              $pct = round(($d['hits'] / $dailyMax) * 100);
              $lbl = date('d M', strtotime($d['day']));
            ?>
            <div class="chart-bar-col" title="<?= $lbl ?>: <?= $d['hits'] ?> hits">
              <?php if ($d['hits'] > 0): ?>
              <span class="chart-value"><?= $d['hits'] ?></span>
              <?php endif; ?>
              <div class="chart-bar" style="height:<?= max(2,$pct) ?>%"></div>
              <span class="chart-label"><?= $range <= 14 ? $lbl : date('d', strtotime($d['day'])) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <!-- Hourly chart (today) -->
      <section class="admin-card">
        <div class="admin-card__header">
          <h2><i class="fa-solid fa-clock"></i> Hourly Hits — Today</h2>
        </div>
        <div style="padding:1rem 1.2rem">
          <div class="chart-wrap chart-wrap--hourly">
            <?php for ($h = 0; $h < 24; $h++):
              $hits = $hourlyMap[$h] ?? 0;
              $pct  = round(($hits / $hourlyMax) * 100);
              $lbl  = ($h % 6 === 0) ? ($h < 12 ? $h.'am' : ($h === 12 ? '12pm' : ($h-12).'pm')) : '';
            ?>
            <div class="chart-bar-col"
                 title="<?= str_pad($h,2,'0',STR_PAD_LEFT) ?>:00 — <?= $hits ?> hits">
              <?php if ($hits > 0): ?>
              <span class="chart-value"><?= $hits ?></span>
              <?php endif; ?>
              <div class="chart-bar" style="height:<?= max(2,$pct) ?>%"></div>
              <span class="chart-label"><?= $lbl ?></span>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </section>

    </div>

    <!-- ══ TOP PAGES + TOP REFERRERS ════════════════════════════ -->
    <div class="two-col">

      <!-- Top pages -->
      <section class="admin-card">
        <div class="admin-card__header">
          <h2><i class="fa-solid fa-file-lines"></i> Top Pages (<?= $range ?> days)</h2>
        </div>
        <div style="overflow-x:auto">
          <table class="analytics-table">
            <thead>
              <tr>
                <th>Page</th>
                <th style="text-align:right">Hits</th>
                <th style="text-align:right">Unique IPs</th>
                <th>Bar</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($topPages)): ?>
              <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem">
                No data yet — add tracking to pages first.
              </td></tr>
              <?php endif; ?>
              <?php
              $maxHits = max(1, (int)($topPages[0]['hits'] ?? 1));
              foreach ($topPages as $p):
                $barPct = round(($p['hits'] / $maxHits) * 100);
              ?>
              <tr>
                <td>
                  <span class="page-tag">
                    <?= htmlspecialchars(basename($p['page_path']) ?: '/') ?>
                  </span>
                  <br>
                  <span style="font-size:.73rem;color:#94a3b8">
                    <?= htmlspecialchars($p['page_path']) ?>
                  </span>
                </td>
                <td style="text-align:right;font-weight:700"><?= number_format((int)$p['hits']) ?></td>
                <td style="text-align:right;color:#64748b"><?= number_format((int)$p['unique_ips']) ?></td>
                <td>
                  <div class="bar-inline" style="width:<?= $barPct ?>%;max-width:60px"></div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Top referrers -->
      <section class="admin-card">
        <div class="admin-card__header">
          <h2><i class="fa-solid fa-arrow-up-right-from-square"></i> Top Referrers (<?= $range ?> days)</h2>
        </div>
        <div style="overflow-x:auto">
          <table class="analytics-table">
            <thead>
              <tr>
                <th>Referrer</th>
                <th style="text-align:right">Hits</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($referrers)): ?>
              <tr><td colspan="2" style="text-align:center;color:#94a3b8;padding:1.5rem">
                No referrer data yet.
              </td></tr>
              <?php endif; ?>
              <?php
              $maxRef = max(1, (int)($referrers[0]['hits'] ?? 1));
              foreach ($referrers as $r):
                $domain = parse_url($r['referrer'], PHP_URL_HOST) ?: $r['referrer'];
                $barPct = round(($r['hits'] / $maxRef) * 100);
              ?>
              <tr>
                <td>
                  <span style="font-size:.8rem;color:#334155;word-break:break-all">
                    <?= htmlspecialchars($domain) ?>
                  </span>
                </td>
                <td style="text-align:right">
                  <strong><?= number_format((int)$r['hits']) ?></strong>
                  <div class="bar-inline" style="width:<?= $barPct ?>%;max-width:50px;margin-left:4px"></div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

    </div>

    <!-- ══ TOP IP ADDRESSES ═══════════════════════════════════════ -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-network-wired"></i>
          IP Addresses — Last <?= $range ?> Days
        </h2>
        <span style="font-size:.78rem;color:#94a3b8">
          Top 20 by hit count
        </span>
      </div>
      <div style="overflow-x:auto">
        <table class="analytics-table">
          <thead>
            <tr>
              <th>#</th>
              <th>IP Address</th>
              <th style="text-align:right">Hits</th>
              <th style="text-align:right">Pages Visited</th>
              <th>Last Seen</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($topIPs)): ?>
            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem">
              No data yet.
            </td></tr>
            <?php endif; ?>
            <?php foreach ($topIPs as $i => $ip): ?>
            <tr>
              <td style="color:#94a3b8"><?= $i + 1 ?></td>
              <td>
                <code class="ip-code"><?= htmlspecialchars($ip['ip_address'] ?? '—') ?></code>
              </td>
              <td style="text-align:right;font-weight:700"><?= number_format((int)$ip['hits']) ?></td>
              <td style="text-align:right;color:#64748b"><?= (int)$ip['pages_visited'] ?></td>
              <td style="font-size:.8rem;color:#64748b;white-space:nowrap">
                <?= $ip['last_seen'] ? date('d M H:i', strtotime($ip['last_seen'])) : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ══ RECENT VISITORS ════════════════════════════════════════ -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-list-ul"></i> Recent Visitors (last 50)</h2>
        <form method="POST" style="display:inline"
              onsubmit="return confirm('This will permanently DELETE all analytics data. Are you sure?')">
          <input type="hidden" name="_purge_analytics" value="1" />
          <button type="submit"
                  style="background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;padding:4px 12px;
                         border-radius:6px;font-size:.76rem;font-weight:600;cursor:pointer;font-family:inherit">
            <i class="fa-solid fa-trash"></i> Clear All Data
          </button>
        </form>
      </div>
      <div style="overflow-x:auto">
        <table class="analytics-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>IP Address</th>
              <th>Page</th>
              <th>Device / Browser</th>
              <th>Referrer</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent)): ?>
            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem">
              No visitor data yet. Add the tracking snippet to your public pages.
            </td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $v):
              $device  = parseDevice($v['user_agent']);
              $browser = parseBrowser($v['user_agent']);
              $devCss  = ['Desktop'=>'device-desktop','Mobile'=>'device-mobile','Tablet'=>'device-tablet'];
              $refDomain = $v['referrer'] ? (parse_url($v['referrer'], PHP_URL_HOST) ?: $v['referrer']) : '';
            ?>
            <tr>
              <td style="white-space:nowrap;font-size:.8rem;color:#64748b">
                <?= date('d M H:i', strtotime($v['created_at'])) ?>
              </td>
              <td>
                <code class="ip-code"><?= htmlspecialchars($v['ip_address'] ?? '—') ?></code>
              </td>
              <td>
                <span class="page-tag">
                  <?= htmlspecialchars(basename($v['page_path']) ?: '/') ?>
                </span>
              </td>
              <td style="white-space:nowrap">
                <span class="device-tag <?= $devCss[$device] ?? '' ?>"><?= $device ?></span>
                <span class="browser-tag"> <?= $browser ?></span>
              </td>
              <td style="font-size:.78rem;color:#64748b;word-break:break-all;max-width:180px">
                <?= htmlspecialchars($refDomain ?: '—') ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>
</div>

<?php
/* ── Handle clear-all (POST _purge_analytics) ─────────────────── */
if (isset($_POST['_purge_analytics'])) {
    try { $pdo->exec('TRUNCATE TABLE page_hits'); }
    catch (\PDOException $e) {}
    header('Location: analytics.php');
    exit;
}
?>
</body>
</html>
