<?php
/* ================================================================
   NEx-gEN Admin — Batch Time Slots Management
   URL: /admin/batches.php
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$msg = ''; $msgType = '';

/* ── ADD ──────────────────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $startH = (int)($_POST['start_hour']   ?? 0);
    $startM = (int)($_POST['start_minute'] ?? 0);
    $endH   = (int)($_POST['end_hour']     ?? 0);
    $endM   = (int)($_POST['end_minute']   ?? 0);

    $startTime = sprintf('%02d:%02d:00', $startH, $startM);
    $endTime   = sprintf('%02d:%02d:00', $endH,   $endM);

    /* Human-readable label */
    $startLabel = date('g:i A', strtotime($startTime));
    $endLabel   = date('g:i A', strtotime($endTime));
    $name       = trim($_POST['name'] ?? '') ?: "{$startLabel} – {$endLabel}";
    $sort       = (int)($_POST['sort_order'] ?? ($startH * 60 + $startM));

    if ($startTime >= $endTime) {
        $msg = 'End time must be after start time.'; $msgType = 'error';
    } else {
        try {
            $pdo->prepare(
                'INSERT INTO batches (name, start_time, end_time, sort_order) VALUES (?,?,?,?)'
            )->execute([$name, $startTime, $endTime, $sort]);
            $msg = "Batch \"{$name}\" added."; $msgType = 'success';
        } catch (\PDOException $e) {
            $msg = 'That start time already exists.'; $msgType = 'error';
        }
    }
}

/* ── TOGGLE ACTIVE ─────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'toggle') {
    $id = (int)$_POST['id'];
    if ($id > 0) {
        $pdo->prepare('UPDATE batches SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        $msg = 'Batch status updated.'; $msgType = 'success';
    }
}

/* ── DELETE ──────────────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)$_POST['id'];
    if ($id > 0) {
        /* Check if any student is assigned */
        $count = $pdo->prepare('SELECT COUNT(*) FROM students WHERE batch_id=?');
        $count->execute([$id]);
        if ($count->fetchColumn() > 0) {
            $msg = 'Cannot delete — students are assigned to this batch. Re-assign them first.';
            $msgType = 'error';
        } else {
            $pdo->prepare('DELETE FROM batches WHERE id=?')->execute([$id]);
            $msg = 'Batch deleted.'; $msgType = 'success';
        }
    }
}

/* ── Fetch all batches ───────────────────────────────────────────── */
$batches = $pdo->query(
    'SELECT b.*, (SELECT COUNT(*) FROM students s WHERE s.batch_id=b.id AND s.status=\'active\') AS student_count
     FROM batches b ORDER BY sort_order ASC, start_time ASC'
)->fetchAll();

/* Hours for dropdowns */
$hours   = range(8, 20);
$minutes = [0, 15, 30, 45];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Batches — Admin | NEx-gEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    .batch-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-top:1rem;}
    .batch-card{background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;padding:1.1rem 1.2rem;position:relative;transition:box-shadow .2s;}
    .batch-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.07);}
    .batch-card.inactive{opacity:.55;}
    .batch-card__time{font-size:1.05rem;font-weight:700;color:#1e293b;margin-bottom:.3rem;}
    .batch-card__name{font-size:.8rem;color:#64748b;margin-bottom:.6rem;}
    .batch-card__count{display:inline-flex;align-items:center;gap:.35rem;background:#eff6ff;color:#2563eb;border-radius:20px;padding:2px 10px;font-size:.78rem;font-weight:600;}
    .batch-card__actions{display:flex;gap:.5rem;margin-top:.9rem;}
    .time-picker{display:flex;gap:.5rem;align-items:center;}
    .time-picker select{padding:.4rem .6rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;}
    .note-box{background:#fffbeb;border:1.5px solid #fcd34d;border-radius:10px;padding:.8rem 1rem;font-size:.85rem;color:#92400e;margin-bottom:1rem;}
  </style>
</head>
<body class="admin-page">

<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>Batch Slots</span>
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
      <li class="active"><a href="batches.php"><i class="fa-solid fa-clock"></i> Batch Slots</a></li>
      <li><a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> Attendance</a></li>
      <li><a href="holidays.php"><i class="fa-solid fa-calendar-xmark"></i> Holidays</a></li>
      <li><a href="sms-logs.php"><i class="fa-solid fa-comment-sms"></i> SMS Logs</a></li>
    </ul>
    <div class="admin-sidebar__stats">
      <div class="sidebar-stat">
        <strong><?= count($batches) ?></strong>
        <span>Total Batch Slots</span>
      </div>
    </div>
  </aside>

  <main class="admin-main">

    <?php if ($msg): ?>
    <div class="alert alert--<?= $msgType ?>">
      <i class="fa-solid fa-<?= $msgType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <div class="note-box">
      <i class="fa-solid fa-circle-info"></i>
      <strong>How batches work:</strong> Each slot is a 1-hour class window.
      The SMS cron automatically sends absence alerts <strong>30–60 minutes after each batch starts</strong>.
      Toggle a batch OFF to pause SMS for that slot without deleting it.
    </div>

    <!-- ADD FORM -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-plus"></i> Add New Batch Slot</h2>
      </div>
      <form method="POST" class="admin-form">
        <input type="hidden" name="action" value="add" />
        <div class="form-grid">
          <div class="form-group">
            <label>Start Time</label>
            <div class="time-picker">
              <select name="start_hour">
                <?php foreach ($hours as $h): ?>
                <option value="<?= $h ?>"><?= date('g A', mktime($h,0)) ?></option>
                <?php endforeach; ?>
              </select>
              <span>:</span>
              <select name="start_minute">
                <?php foreach ($minutes as $m): ?>
                <option value="<?= $m ?>"><?= sprintf('%02d', $m) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>End Time</label>
            <div class="time-picker">
              <select name="end_hour">
                <?php foreach ($hours as $h): ?>
                <option value="<?= $h ?>" <?= $h === 9 ? 'selected' : '' ?>><?= date('g A', mktime($h,0)) ?></option>
                <?php endforeach; ?>
              </select>
              <span>:</span>
              <select name="end_minute">
                <?php foreach ($minutes as $m): ?>
                <option value="<?= $m ?>"><?= sprintf('%02d', $m) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Custom Label <span class="hint">(optional)</span></label>
            <input type="text" name="name" placeholder="Auto-generated if blank" />
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-plus"></i> Add Batch Slot
          </button>
        </div>
      </form>
    </section>

    <!-- BATCH CARDS -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-clock"></i> All Batch Slots (<?= count($batches) ?>)</h2>
      </div>
      <div class="batch-grid">
        <?php if ($batches): foreach ($batches as $b): ?>
        <div class="batch-card <?= !$b['is_active'] ? 'inactive' : '' ?>">
          <div class="batch-card__time">
            <?= date('g:i A', strtotime($b['start_time'])) ?> –
            <?= date('g:i A', strtotime($b['end_time'])) ?>
          </div>
          <div class="batch-card__name"><?= htmlspecialchars($b['name']) ?></div>
          <span class="batch-card__count">
            <i class="fa-solid fa-user-graduate"></i>
            <?= $b['student_count'] ?> active student<?= $b['student_count'] != 1 ? 's' : '' ?>
          </span>
          <div class="batch-card__actions">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="toggle" />
              <input type="hidden" name="id"     value="<?= (int)$b['id'] ?>" />
              <button type="submit" class="badge badge--<?= $b['is_active'] ? 'green' : 'grey' ?>">
                <?= $b['is_active'] ? '● Active' : '○ Inactive' ?>
              </button>
            </form>
            <?php if ($b['student_count'] == 0): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this batch slot?');">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="id"     value="<?= (int)$b['id'] ?>" />
              <button type="submit" class="btn-icon btn-icon--delete">
                <i class="fa-solid fa-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; else: ?>
        <p class="empty">No batch slots yet. Add one above.</p>
        <?php endif; ?>
      </div>
    </section>

  </main>
</div>
</body>
</html>
