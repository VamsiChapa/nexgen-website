<?php
/* ================================================================
   NEx-gEN Admin — SMS Logs Viewer
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset  = ($page - 1) * $perPage;
$search  = trim($_GET['q'] ?? '');
$type    = trim($_GET['type'] ?? '');
$status  = trim($_GET['status'] ?? '');

$where  = '1=1'; $params = [];
if ($search) { $where .= ' AND (l.recipient_name LIKE ? OR l.phone LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($type)   { $where .= ' AND l.type=?';   $params[] = $type; }
if ($status) { $where .= ' AND l.status=?'; $params[] = $status; }

$total = $pdo->prepare("SELECT COUNT(*) FROM sms_logs l WHERE $where");
$total->execute($params);
$totalCount = $total->fetchColumn();
$totalPages = (int)ceil($totalCount / $perPage);

$rows = $pdo->prepare(
    "SELECT l.*, s.student_name FROM sms_logs l
     LEFT JOIN students s ON s.id = l.student_id
     WHERE $where ORDER BY l.sent_at DESC LIMIT ? OFFSET ?"
);
$rows->execute(array_merge($params, [$perPage, $offset]));
$logs = $rows->fetchAll();

/* Summary counts */
$todaySent = $pdo->query("SELECT COUNT(*) FROM sms_logs WHERE DATE(sent_at)=CURDATE() AND status='sent'")->fetchColumn();
$todayFail = $pdo->query("SELECT COUNT(*) FROM sms_logs WHERE DATE(sent_at)=CURDATE() AND status='failed'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>SMS Logs — Admin | NEx-gEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    .sms-summary{display:flex;gap:1rem;margin-bottom:1.2rem;flex-wrap:wrap;}
    .sms-stat{flex:1;min-width:120px;background:#f8fafc;border-radius:12px;padding:.8rem 1.2rem;border:1.5px solid #e2e8f0;text-align:center;}
    .sms-stat .num{font-size:1.8rem;font-weight:700;}
    .sms-stat .lbl{font-size:.78rem;color:#64748b;}
    .badge-sent{background:#d4edda;color:#155724;padding:2px 10px;border-radius:20px;font-size:.75rem;}
    .badge-failed{background:#f8d7da;color:#721c24;padding:2px 10px;border-radius:20px;font-size:.75rem;}
    .badge-pending{background:#fff3cd;color:#856404;padding:2px 10px;border-radius:20px;font-size:.75rem;}
    .filter-row{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;}
    .filter-row select,.filter-row input{padding:.4rem .7rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.85rem;}
    td.msg-cell{max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.8rem;}
  </style>
</head>
<body class="admin-page">
<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>SMS Logs</span>
  </div>
  <nav class="admin-header__nav">
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
      <li class="active"><a href="sms-logs.php"><i class="fa-solid fa-comment-sms"></i> SMS Logs</a></li>
      <li><a href="analytics.php"><i class="fa-solid fa-chart-bar"></i> Analytics</a></li>
    </ul>
  </aside>
  <main class="admin-main">

    <div class="sms-summary">
      <div class="sms-stat"><div class="num" style="color:#155724;"><?= $todaySent ?></div><div class="lbl">Sent Today</div></div>
      <div class="sms-stat"><div class="num" style="color:#721c24;"><?= $todayFail ?></div><div class="lbl">Failed Today</div></div>
      <div class="sms-stat"><div class="num"><?= $totalCount ?></div><div class="lbl">Total Logged</div></div>
    </div>

    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-comment-sms"></i> SMS Logs</h2>
      </div>

      <form method="GET" class="filter-row">
        <input type="text" name="q" placeholder="Search name / phone…" value="<?= htmlspecialchars($search) ?>" />
        <select name="type">
          <option value="">All Types</option>
          <?php foreach (['absence','late','custom','test'] as $t): ?>
          <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status">
          <option value="">All Status</option>
          <?php foreach (['sent','failed','pending'] as $st): ?>
          <option value="<?= $st ?>" <?= $status===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary" style="padding:.4rem 1rem;">
          <i class="fa-solid fa-filter"></i> Filter
        </button>
        <?php if ($search||$type||$status): ?>
        <a href="sms-logs.php" class="btn-secondary" style="padding:.4rem 1rem;">
          <i class="fa-solid fa-xmark"></i> Clear
        </a>
        <?php endif; ?>
      </form>

      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Student</th>
              <th>Recipient</th>
              <th>Phone</th>
              <th>Type</th>
              <th>Message</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($logs): foreach ($logs as $l): ?>
            <tr>
              <td style="white-space:nowrap;font-size:.8rem;"><?= date('d M H:i', strtotime($l['sent_at'])) ?></td>
              <td><?= htmlspecialchars($l['student_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($l['recipient_name']) ?></td>
              <td><?= htmlspecialchars($l['phone']) ?></td>
              <td><span class="badge-<?= $l['type'] === 'absence' ? 'failed' : 'pending' ?>" style="background:#e0f2fe;color:#0369a1;"><?= ucfirst($l['type']) ?></span></td>
              <td class="msg-cell" title="<?= htmlspecialchars($l['message']) ?>"><?= htmlspecialchars($l['message']) ?></td>
              <td><span class="badge-<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="empty">No SMS logs found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&status=<?= urlencode($status) ?>"
           class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
