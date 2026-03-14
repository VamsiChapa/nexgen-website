<?php
/* ================================================================
   NEx-gEN Admin — Holiday Management
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add') {
        $date = trim($_POST['holiday_date'] ?? '');
        $desc = trim($_POST['description']  ?? '') ?: null;
        if ($date) {
            try {
                $pdo->prepare('INSERT INTO holidays (holiday_date, description) VALUES (?, ?)')
                    ->execute([$date, $desc]);
                $msg = 'Holiday added.'; $msgType = 'success';
            } catch (\PDOException $e) {
                $msg = 'Date already exists.'; $msgType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $pdo->prepare('DELETE FROM holidays WHERE id=?')->execute([$id]);
            $msg = 'Holiday removed.'; $msgType = 'success';
        }
    }
}

$year = (int)($_GET['year'] ?? date('Y'));
$rows = $pdo->prepare('SELECT * FROM holidays WHERE YEAR(holiday_date)=? ORDER BY holiday_date ASC');
$rows->execute([$year]);
$holidays = $rows->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>Holidays — Admin | NEx-gEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="admin-page">
<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>Holiday Admin</span>
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
      <li class="active"><a href="holidays.php"><i class="fa-solid fa-calendar-xmark"></i> Holidays</a></li>
      <li><a href="sms-logs.php"><i class="fa-solid fa-comment-sms"></i> SMS Logs</a></li>
      <li><a href="analytics.php"><i class="fa-solid fa-chart-bar"></i> Analytics</a></li>
    </ul>
  </aside>
  <main class="admin-main">
    <?php if ($msg): ?>
    <div class="alert alert--<?= $msgType ?>">
      <i class="fa-solid fa-<?= $msgType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <section class="admin-card">
      <div class="admin-card__header"><h2><i class="fa-solid fa-plus"></i> Add Holiday</h2></div>
      <form method="POST" class="admin-form">
        <input type="hidden" name="action" value="add" />
        <div class="form-grid">
          <div class="form-group">
            <label>Date *</label>
            <input type="date" name="holiday_date" required />
          </div>
          <div class="form-group">
            <label>Description</label>
            <input type="text" name="description" placeholder="e.g. Diwali" />
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><i class="fa-solid fa-plus"></i> Add Holiday</button>
        </div>
      </form>
    </section>

    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-calendar-xmark"></i> Holidays in <?= $year ?></h2>
        <div style="display:flex;gap:.5rem;">
          <a href="?year=<?= $year-1 ?>" class="btn-secondary" style="padding:.3rem .8rem;">◀ <?= $year-1 ?></a>
          <a href="?year=<?= $year+1 ?>" class="btn-secondary" style="padding:.3rem .8rem;"><?= $year+1 ?> ▶</a>
        </div>
      </div>
      <div class="table-wrap">
        <table class="admin-table">
          <thead><tr><th>Date</th><th>Day</th><th>Description</th><th>Action</th></tr></thead>
          <tbody>
            <?php if ($holidays): foreach ($holidays as $h): ?>
            <tr>
              <td><?= date('d M Y', strtotime($h['holiday_date'])) ?></td>
              <td><?= date('l', strtotime($h['holiday_date'])) ?></td>
              <td><?= htmlspecialchars($h['description'] ?? '—') ?></td>
              <td>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this holiday?');">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$h['id'] ?>" />
                  <button type="submit" class="btn-icon btn-icon--delete"><i class="fa-solid fa-trash"></i></button>
                </form>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="empty">No holidays added for <?= $year ?>.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>
</body>
</html>
