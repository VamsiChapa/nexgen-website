<?php
/* ================================================================
   NEx-gEN Admin — Attendance Management
   URL: /admin/attendance.php
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$msg = ''; $msgType = '';

/* ── Load all batches ────────────────────────────────────────────── */
$allBatches = $pdo->query(
    'SELECT * FROM batches WHERE is_active=1 ORDER BY sort_order ASC, start_time ASC'
)->fetchAll();
$batchMap = array_column($allBatches, null, 'id');

$statuses = ['present'=>'Present','absent'=>'Absent','late'=>'Late','leave'=>'Leave'];

/* ── Manual mark ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark') {
    $sid    = (int)$_POST['student_id'];
    $date   = $_POST['att_date']   ?? date('Y-m-d');
    $status = $_POST['att_status'] ?? 'present';
    if ($sid > 0 && array_key_exists($status, $statuses)) {
        $pdo->prepare(
            'INSERT INTO attendance (student_id, attendance_date, status, source)
             VALUES (?, ?, ?, \'manual\')
             ON DUPLICATE KEY UPDATE status=VALUES(status), source=\'manual\', updated_at=NOW()'
        )->execute([$sid, $date, $status]);
        $msg = 'Attendance updated.'; $msgType = 'success';
    }
}

/* ── Bulk mark present for a batch ──────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_present') {
    $date    = $_POST['bulk_date']    ?? date('Y-m-d');
    $batchId = (int)($_POST['bulk_batch_id'] ?? 0);
    if ($batchId > 0) {
        $ids = $pdo->prepare("SELECT id FROM students WHERE batch_id=? AND status='active'");
        $ids->execute([$batchId]);
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO attendance (student_id,attendance_date,status,source)
             VALUES (?,'$date','present','manual')"
        );
        foreach ($ids->fetchAll() as $row) $stmt->execute([$row['id']]);
        $bn = $batchMap[$batchId]['name'] ?? '';
        $msg = "All active students in \"{$bn}\" marked Present for " . date('d M Y', strtotime($date)) . '.';
        $msgType = 'success';
    }
}

/* ── CSV Import ──────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'csv_import') {
    if (!empty($_FILES['csv_file']['tmp_name'])) {
        $handle  = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $headers = fgetcsv($handle);
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers ?? []);
        $bioCol  = array_search('biometric_id', $headers)
                   ?? array_search('user_id', $headers)
                   ?? array_search('id', $headers) ?? 0;
        $dateCol = array_search('date', $headers) ?? 1;
        $timeCol = array_search('time', $headers);

        $inserted = 0; $skipped = 0;
        $lookup = $pdo->prepare('SELECT id FROM students WHERE biometric_id=? LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO attendance (student_id,attendance_date,check_in_time,status,source)
             VALUES (?,?,?,\'present\',\'csv_import\')
             ON DUPLICATE KEY UPDATE
               status=IF(status=\'absent\',\'present\',status),
               check_in_time=COALESCE(VALUES(check_in_time),check_in_time),
               source=\'csv_import\', updated_at=NOW()'
        );
        while (($row = fgetcsv($handle)) !== false) {
            $bioId   = trim($row[$bioCol] ?? '');
            $rawDate = trim($row[$dateCol] ?? '');
            if (!$bioId || !$rawDate) { $skipped++; continue; }
            if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $rawDate, $m)) {
                $attDate = "{$m[3]}-{$m[2]}-{$m[1]}";
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
                $attDate = $rawDate;
            } else { $skipped++; continue; }
            $checkIn = ($timeCol !== false && !empty($row[$timeCol])) ? trim($row[$timeCol]) : null;
            $lookup->execute([$bioId]);
            $sid = $lookup->fetchColumn();
            if (!$sid) { $skipped++; continue; }
            $insert->execute([$sid, $attDate, $checkIn]);
            $inserted++;
        }
        fclose($handle);
        $msg = "CSV imported: {$inserted} saved, {$skipped} skipped."; $msgType = 'success';
    } else {
        $msg = 'Please choose a CSV file.'; $msgType = 'error';
    }
}

/* ── View filters ────────────────────────────────────────────────── */
$viewDate    = $_GET['date']     ?? date('Y-m-d');
$viewBatchId = (int)($_GET['batch_id'] ?? ($allBatches[0]['id'] ?? 0));
$viewStuId   = (int)($_GET['student_id'] ?? 0);

/* ── Single student history ──────────────────────────────────────── */
$stuInfo = null; $stuHistory = [];
if ($viewStuId > 0) {
    $s = $pdo->prepare('SELECT s.*, b.name AS batch_name FROM students s LEFT JOIN batches b ON b.id=s.batch_id WHERE s.id=?');
    $s->execute([$viewStuId]);
    $stuInfo = $s->fetch();
    $h = $pdo->prepare('SELECT * FROM attendance WHERE student_id=? ORDER BY attendance_date DESC LIMIT 90');
    $h->execute([$viewStuId]);
    $stuHistory = $h->fetchAll();
}

/* ── Daily grid ──────────────────────────────────────────────────── */
$grid = [];
if ($viewBatchId > 0) {
    $q = $pdo->prepare(
        'SELECT s.id, s.student_name, s.phone,
                a.status, a.check_in_time, a.source
         FROM   students s
         LEFT JOIN attendance a
           ON  a.student_id      = s.id
           AND a.attendance_date = ?
         WHERE  s.batch_id=? AND s.status=\'active\'
         ORDER  BY s.student_name ASC'
    );
    $q->execute([$viewDate, $viewBatchId]);
    $grid = $q->fetchAll();
}

/* ── Summary counts ──────────────────────────────────────────────── */
$totalToday   = 0; $presentToday = 0;
foreach ($allBatches as $b) {
    $t = $pdo->prepare("SELECT COUNT(*) FROM students WHERE batch_id=? AND status='active'");
    $t->execute([$b['id']]); $totalToday += $t->fetchColumn();
}
$p = $pdo->prepare(
    "SELECT COUNT(*) FROM attendance a JOIN students s ON s.id=a.student_id
     WHERE a.attendance_date=? AND a.status IN ('present','late')"
);
$p->execute([$viewDate]); $presentToday = $p->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Attendance — Admin | NEx-gEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    .att-summary{display:flex;gap:1rem;margin-bottom:1.2rem;flex-wrap:wrap;}
    .att-stat{flex:1;min-width:130px;background:#f8fafc;border-radius:12px;padding:.8rem 1.2rem;border:1.5px solid #e2e8f0;text-align:center;}
    .att-stat .big{font-size:1.7rem;font-weight:700;color:#1e293b;line-height:1;}
    .att-stat .sub{font-size:.78rem;color:#94a3b8;}
    .batch-scroll{display:flex;gap:.4rem;overflow-x:auto;padding-bottom:.3rem;margin-bottom:.8rem;flex-wrap:wrap;}
    .batch-tab{padding:.35rem .9rem;border-radius:20px;border:1.5px solid #e2e8f0;background:#fff;font-size:.8rem;white-space:nowrap;cursor:pointer;text-decoration:none;color:#1e293b;transition:all .2s;flex-shrink:0;}
    .batch-tab.active{background:#2563eb;color:#fff;border-color:#2563eb;}
    .status-pill{padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:600;}
    .status-pill--present{background:#d4edda;color:#155724;}
    .status-pill--absent{background:#f8d7da;color:#721c24;}
    .status-pill--late{background:#fff3cd;color:#856404;}
    .status-pill--leave{background:#d1ecf1;color:#0c5460;}
    .status-pill--none{background:#f1f5f9;color:#94a3b8;}
    .inline-form{display:flex;gap:.35rem;align-items:center;}
    .inline-form select{padding:3px 6px;border:1.5px solid #e2e8f0;border-radius:6px;font-family:inherit;font-size:.8rem;}
    .src-tag{font-size:.68rem;color:#94a3b8;margin-left:3px;}
    .csv-box{background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:12px;padding:1.2rem;margin-top:1rem;}
  </style>
</head>
<body class="admin-page">

<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>Attendance</span>
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
      <li class="active"><a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> Attendance</a></li>
      <li><a href="holidays.php"><i class="fa-solid fa-calendar-xmark"></i> Holidays</a></li>
      <li><a href="sms-logs.php"><i class="fa-solid fa-comment-sms"></i> SMS Logs</a></li>
    </ul>
  </aside>

  <main class="admin-main">

    <?php if ($msg): ?>
    <div class="alert alert--<?= $msgType ?>">
      <i class="fa-solid fa-<?= $msgType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- STUDENT HISTORY PANEL -->
    <?php if ($stuInfo): ?>
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-clock-rotate-left"></i>
          History: <?= htmlspecialchars($stuInfo['student_name']) ?>
          <small style="font-weight:400;font-size:.82rem;color:#64748b;">
            (<?= htmlspecialchars($stuInfo['batch_name'] ?? '') ?> · last 90 days)
          </small>
        </h2>
        <a href="attendance.php?date=<?= urlencode($viewDate) ?>&batch_id=<?= $viewBatchId ?>"
           class="btn-secondary" style="padding:.35rem .9rem;">
          <i class="fa-solid fa-xmark"></i> Close
        </a>
      </div>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Date</th><th>Day</th><th>Status</th><th>Check-in</th><th>Source</th></tr></thead>
          <tbody>
            <?php if ($stuHistory): foreach ($stuHistory as $ah): ?>
            <tr>
              <td><?= date('d M Y', strtotime($ah['attendance_date'])) ?></td>
              <td style="color:#64748b;"><?= date('D', strtotime($ah['attendance_date'])) ?></td>
              <td><span class="status-pill status-pill--<?= $ah['status'] ?>"><?= ucfirst($ah['status']) ?></span></td>
              <td><?= $ah['check_in_time'] ? date('h:i A', strtotime($ah['check_in_time'])) : '—' ?></td>
              <td><?= $ah['source'] ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="empty">No records.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <!-- DAILY GRID -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-calendar-day"></i> Daily Attendance</h2>
      </div>

      <!-- Date picker -->
      <form method="GET" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem;">
        <input type="date" name="date" value="<?= htmlspecialchars($viewDate) ?>"
               style="padding:.4rem .7rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;" />
        <input type="hidden" name="batch_id" value="<?= $viewBatchId ?>" />
        <button type="submit" class="btn-primary" style="padding:.4rem 1rem;">
          <i class="fa-solid fa-search"></i> Load
        </button>
        <span style="font-size:.85rem;color:#64748b;"><?= date('l, d F Y', strtotime($viewDate)) ?></span>
      </form>

      <!-- Summary -->
      <div class="att-summary">
        <div class="att-stat">
          <div class="big" style="color:#155724;"><?= $presentToday ?></div>
          <div class="sub">Present (all batches)</div>
        </div>
        <div class="att-stat">
          <div class="big" style="color:#721c24;"><?= $totalToday - $presentToday ?></div>
          <div class="sub">Not marked / Absent</div>
        </div>
        <div class="att-stat">
          <div class="big"><?= $totalToday ?></div>
          <div class="sub">Total Active Students</div>
        </div>
      </div>

      <!-- Batch tabs -->
      <div class="batch-scroll">
        <?php foreach ($allBatches as $b): ?>
        <a href="?date=<?= urlencode($viewDate) ?>&batch_id=<?= $b['id'] ?>"
           class="batch-tab <?= $viewBatchId == $b['id'] ? 'active' : '' ?>">
          <?= date('g:i', strtotime($b['start_time'])) ?>–<?= date('g:i A', strtotime($b['end_time'])) ?>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Bulk present -->
      <form method="POST" style="margin-bottom:.8rem;">
        <input type="hidden" name="action"       value="bulk_present" />
        <input type="hidden" name="bulk_date"     value="<?= htmlspecialchars($viewDate) ?>" />
        <input type="hidden" name="bulk_batch_id" value="<?= $viewBatchId ?>" />
        <button type="submit" class="btn-secondary" style="padding:.4rem 1rem;font-size:.82rem;"
          onclick="return confirm('Mark ALL active students in this batch as Present?')">
          <i class="fa-solid fa-check-double"></i>
          Mark All Present — <?= $batchMap[$viewBatchId]['name'] ?? '' ?>
        </button>
      </form>

      <!-- Grid -->
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>#</th><th>Student</th><th>Phone</th><th>Status</th><th>Update</th></tr>
          </thead>
          <tbody>
            <?php if ($grid): foreach ($grid as $i => $row): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <?= htmlspecialchars($row['student_name']) ?>
                <?php if ($row['source']): ?>
                <span class="src-tag">(<?= $row['source'] ?>)</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['phone']) ?></td>
              <td>
                <?php if ($row['status']): ?>
                <span class="status-pill status-pill--<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span>
                <?php if ($row['check_in_time']): ?>
                <small style="color:#64748b;"> <?= date('h:i A', strtotime($row['check_in_time'])) ?></small>
                <?php endif; ?>
                <?php else: ?>
                <span class="status-pill status-pill--none">Not marked</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="POST" class="inline-form">
                  <input type="hidden" name="action"     value="mark" />
                  <input type="hidden" name="student_id" value="<?= (int)$row['id'] ?>" />
                  <input type="hidden" name="att_date"   value="<?= htmlspecialchars($viewDate) ?>" />
                  <select name="att_status">
                    <?php foreach ($statuses as $sv => $sl): ?>
                    <option value="<?= $sv ?>" <?= ($row['status'] ?? '') === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn-primary" style="padding:3px 10px;font-size:.8rem;">
                    <i class="fa-solid fa-floppy-disk"></i>
                  </button>
                  <a href="?student_id=<?= (int)$row['id'] ?>&date=<?= urlencode($viewDate) ?>&batch_id=<?= $viewBatchId ?>"
                     class="btn-icon" style="background:#f1f5f9;color:#64748b;" title="View history">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                  </a>
                </form>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="empty">No active students in this batch.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- CSV IMPORT -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-file-csv"></i> Import from Biometric CSV</h2>
      </div>
      <div class="csv-box">
        <p style="margin:0 0 .8rem;font-size:.88rem;color:#475569;">
          Export the attendance report from your biometric software and upload below.
          <br /><strong>Required columns:</strong> <code>biometric_id</code> (or <code>user_id</code>),
          <code>date</code> (YYYY-MM-DD or DD/MM/YYYY), <code>time</code> (optional).
          <br />Each student must have their Biometric ID filled in the Students page.
        </p>
        <form method="POST" enctype="multipart/form-data" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;">
          <input type="hidden" name="action" value="csv_import" />
          <input type="file" name="csv_file" accept=".csv,text/csv" required />
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-upload"></i> Import CSV
          </button>
        </form>
      </div>
    </section>

  </main>
</div>
</body>
</html>
