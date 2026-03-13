<?php
/* ================================================================
   NEx-gEN Admin — Enquiry Management
   URL: /admin/enquiries.php

   STATUS FLOW:
   new → contacted → interested → enrolled (auto on conversion)
                                → not-interested
                                → dropped
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once dirname(__DIR__) . '/config/db.php';

$msg = ''; $msgType = '';

/* ── Course list (keep in sync with students.php) ────────────── */
$COURSES = [
    'MS OFFICE','PROG IN C','CORE JAVA','PYTHON','TALLY PRIME',
    'WEB DESIGNING','MSO, C','MSO, TALLY','DCA','PGDCA','HAND WRITING',
];

$SOURCE_LABELS = [
    'walk-in'     => 'Walk-in',
    'phone-call'  => 'Phone Call',
    'referral'    => 'Referral',
    'website'     => 'Website',
    'social-media'=> 'Social Media',
    'other'       => 'Other',
];

/* ── Auto-generate enquiry number ────────────────────────────── */
function generateEnquiryNumber($pdo) {
    $year = date('Y');
    $last = $pdo->query(
        "SELECT enquiry_number FROM enquiries
         WHERE enquiry_number LIKE 'ENQ{$year}%'
         ORDER BY id DESC LIMIT 1"
    )->fetchColumn();
    $seq = $last ? ((int)substr($last, -4) + 1) : 1;
    return 'ENQ' . $year . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/* ── ADD / EDIT ───────────────────────────────────────────────── */
if (isset($_POST['action']) && in_array($_POST['action'], ['add','edit'])) {
    $editId    = (int)($_POST['id']             ?? 0);
    $name      = trim($_POST['name']            ?? '');
    $phone     = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
    $email     = trim($_POST['email']           ?? '') ?: null;
    $courses   = implode(',', array_filter(array_map('trim', (array)($_POST['courses_interested'] ?? []))));
    $prefBatch = trim($_POST['preferred_batch'] ?? '') ?: null;
    $source    = trim($_POST['source']          ?? 'walk-in');
    $message   = trim($_POST['message']         ?? '') ?: null;
    $status    = trim($_POST['status']          ?? 'new');
    $followUp  = trim($_POST['follow_up_date']  ?? '') ?: null;
    $enqDate   = trim($_POST['enquiry_date']    ?? '') ?: date('Y-m-d');

    if (!$name || !$phone) {
        $msg = 'Name and Phone are required.'; $msgType = 'error';
    } elseif ($_POST['action'] === 'add') {
        try {
            $enqNo = generateEnquiryNumber($pdo);
            $pdo->prepare(
                'INSERT INTO enquiries
                 (enquiry_number,name,phone,email,courses_interested,preferred_batch,
                  source,message,status,follow_up_date,enquiry_date)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([$enqNo,$name,$phone,$email,$courses,$prefBatch,
                         $source,$message,$status,$followUp,$enqDate]);
            $msg = "Enquiry <strong>{$enqNo}</strong> added for <strong>" . htmlspecialchars($name) . "</strong>.";
            $msgType = 'success';
        } catch (\PDOException $e) {
            $msg = 'DB error: ' . $e->getMessage(); $msgType = 'error';
        }
    } else {
        try {
            $pdo->prepare(
                'UPDATE enquiries SET name=?,phone=?,email=?,courses_interested=?,preferred_batch=?,
                 source=?,message=?,status=?,follow_up_date=?,enquiry_date=? WHERE id=?'
            )->execute([$name,$phone,$email,$courses,$prefBatch,
                         $source,$message,$status,$followUp,$enqDate,$editId]);
            $msg = 'Enquiry updated.'; $msgType = 'success';
        } catch (\PDOException $e) {
            $msg = 'DB error: ' . $e->getMessage(); $msgType = 'error';
        }
    }
}

/* ── DELETE ───────────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $chk = $pdo->prepare('SELECT converted_to_student_id FROM enquiries WHERE id=?');
    $chk->execute([$id]);
    $chkRow = $chk->fetch();
    if ($chkRow && $chkRow['converted_to_student_id']) {
        $msg = 'Cannot delete — this enquiry was already converted to a registered student.';
        $msgType = 'error';
    } else {
        $pdo->prepare('DELETE FROM enquiries WHERE id=?')->execute([$id]);
        $msg = 'Enquiry deleted.'; $msgType = 'success';
    }
}

/* ── QUICK STATUS (AJAX) ──────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'quick_status') {
    $id     = (int)($_POST['id']    ?? 0);
    $status = trim($_POST['status'] ?? '');
    if ($id && $status) {
        $pdo->prepare('UPDATE enquiries SET status=? WHERE id=?')->execute([$status, $id]);
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

/* ── CSV EXPORT ───────────────────────────────────────────────── */
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="nexgen_enquiries_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Enquiry No','Date','Name','Phone','Email','Courses Interested',
                   'Preferred Batch','Source','Status','Follow-up Date','Message','Converted To']);
    $rows = $pdo->query(
        "SELECT e.*, s.admission_number AS student_adm
         FROM enquiries e LEFT JOIN students s ON s.id = e.converted_to_student_id
         ORDER BY e.created_at DESC"
    )->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['enquiry_number'], $r['enquiry_date'],
            $r['name'], $r['phone'], $r['email'] ?? '',
            $r['courses_interested'] ?? '', $r['preferred_batch'] ?? '',
            $r['source'], $r['status'], $r['follow_up_date'] ?? '',
            $r['message'] ?? '', $r['student_adm'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

/* ── FILTERS ──────────────────────────────────────────────────── */
$filterStatus = $_GET['status'] ?? 'all';
$filterCourse = trim($_GET['course'] ?? '');
$search       = trim($_GET['q']     ?? '');

$where  = ['1=1'];
$params = [];
if ($filterStatus !== 'all') {
    $where[] = 'e.status = ?';    $params[] = $filterStatus;
}
if ($filterCourse) {
    $where[] = 'FIND_IN_SET(?, e.courses_interested) > 0';
    $params[] = $filterCourse;
}
if ($search) {
    $where[] = '(e.name LIKE ? OR e.phone LIKE ? OR e.enquiry_number LIKE ?)';
    $params  = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}
$whereSQL = implode(' AND ', $where);

/* ── LOAD LIST ────────────────────────────────────────────────── */
$enquiries = [];
try {
    $stmt = $pdo->prepare(
        "SELECT e.*,
                s.admission_number AS student_adm_no,
                s.student_name     AS student_name_reg
         FROM enquiries e
         LEFT JOIN students s ON s.id = e.converted_to_student_id
         WHERE {$whereSQL}
         ORDER BY
           CASE
             WHEN e.follow_up_date = CURDATE()
              AND e.status NOT IN ('enrolled','not-interested','dropped') THEN 0
             ELSE 1
           END,
           e.created_at DESC"
    );
    $stmt->execute($params);
    $enquiries = $stmt->fetchAll();
} catch (\PDOException $e) {
    $msg     = 'Database error: ' . $e->getMessage()
             . ' — Did you run db-setup-enquiries.sql (or db-migrate-enquiries.sql) in phpMyAdmin?';
    $msgType = 'error';
}

/* ── STATUS COUNTS for tabs ───────────────────────────────────── */
$counts = ['all' => 0];
try {
    foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM enquiries GROUP BY status")->fetchAll() as $cr) {
        $counts[$cr['status']] = (int)$cr['n'];
        $counts['all'] += (int)$cr['n'];
    }
} catch (\PDOException $e) {}

/* ── FOLLOW-UPS DUE TODAY ─────────────────────────────────────── */
$followUpsToday = 0;
try {
    $followUpsToday = (int)$pdo->query(
        "SELECT COUNT(*) FROM enquiries
         WHERE follow_up_date = CURDATE()
           AND status NOT IN ('enrolled','not-interested','dropped')"
    )->fetchColumn();
} catch (\PDOException $e) {}

/* ── EDIT ROW ─────────────────────────────────────────────────── */
$editRow = [];
if (isset($_GET['edit'])) {
    $er = $pdo->prepare('SELECT * FROM enquiries WHERE id=?');
    $er->execute([(int)$_GET['edit']]);
    $editRow = $er->fetch() ?: [];
}

/* ── STATUS CSS MAP ───────────────────────────────────────────── */
$statusCss = [
    'new'           => 'eq-new',
    'contacted'     => 'eq-contacted',
    'interested'    => 'eq-interested',
    'enrolled'      => 'eq-enrolled',
    'not-interested'=> 'eq-not-interested',
    'dropped'       => 'eq-dropped',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Enquiries — Admin | NEx-gEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    /* ── Tab bar ── */
    .tab-bar { display:flex; gap:.3rem; flex-wrap:wrap; }
    .tab-bar a { padding:.3rem .85rem; border-radius:20px; font-size:.79rem; font-weight:600;
                 text-decoration:none; background:#f1f5f9; color:#64748b; border:1.5px solid transparent; transition:.15s; }
    .tab-bar a.active { background:#0f4e8a; color:#fff; }
    .tab-count { display:inline-block; background:rgba(0,0,0,.09); border-radius:10px;
                 padding:1px 6px; font-size:.68rem; margin-left:3px; }
    .tab-bar a.active .tab-count { background:rgba(255,255,255,.25); }

    /* ── Stat mini-cards ── */
    .stat-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:.7rem; margin-bottom:1.2rem; }
    .stat-card { background:#fff; border-radius:10px; padding:.9rem 1rem; box-shadow:0 1px 4px rgba(0,0,0,.07);
                 text-align:center; }
    .stat-card strong { display:block; font-size:1.6rem; font-weight:700; color:#0f4e8a; }
    .stat-card span { font-size:.72rem; color:#64748b; }

    /* ── Course checkboxes ── */
    .course-checks { display:flex; flex-wrap:wrap; gap:.35rem .9rem; }
    .course-checks label { display:flex; align-items:center; gap:.3rem; font-size:.83rem; cursor:pointer; white-space:nowrap; }

    /* ── Courses cell in table ── */
    .courses-cell { display:flex; flex-wrap:wrap; gap:3px; }
    .course-chip { background:#eff6ff; color:#2563eb; border-radius:6px; padding:2px 7px; font-size:.7rem; font-weight:600; }

    /* ── Follow-up chip ── */
    .followup-today { display:inline-flex; align-items:center; gap:4px; background:#fef3c7; color:#92400e;
                      border-radius:8px; padding:2px 9px; font-size:.71rem; font-weight:600; white-space:nowrap; }

    /* ── Action buttons ── */
    .convert-btn { background:#4f46e5; color:#fff; border:none; padding:4px 11px; border-radius:8px;
                   font-size:.75rem; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; white-space:nowrap; }
    .convert-btn:hover { background:#3730a3; color:#fff; }
    .view-stu-btn { background:#0891b2; color:#fff; border:none; padding:4px 11px; border-radius:8px;
                    font-size:.75rem; font-weight:600; text-decoration:none; display:inline-block; white-space:nowrap; }

    /* ── Quick status select (inline) ── */
    .quick-status { font-family:inherit; font-size:.76rem; font-weight:600; border:1.5px solid transparent;
                    border-radius:14px; padding:3px 8px; cursor:pointer; }
    .quick-status.eq-new           { background:#dbeafe; color:#1d4ed8; }
    .quick-status.eq-contacted     { background:#fef3c7; color:#92400e; }
    .quick-status.eq-interested    { background:#d1fae5; color:#065f46; }
    .quick-status.eq-not-interested{ background:#fee2e2; color:#b91c1c; }
    .quick-status.eq-dropped       { background:#f1f5f9; color:#64748b; }

    /* ── Form helpers ── */
    .sec-label { font-weight:600; font-size:.8rem; text-transform:uppercase; letter-spacing:.06em;
                 color:#64748b; margin:1.1rem 0 .35rem; padding-bottom:.3rem; border-bottom:1.5px solid #f1f5f9; }
    .opt-tag   { font-size:.72rem; color:#94a3b8; font-weight:400; margin-left:4px; }
    .hint      { font-size:.75rem; color:#94a3b8; }
    .filter-bar { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; margin-bottom:.9rem; }
    .filter-bar input, .filter-bar select { padding:.4rem .8rem; border:1.5px solid #e2e8f0;
                                            border-radius:8px; font-family:inherit; font-size:.87rem; }
    .row--followup-today { background:#fffbeb; }
  </style>
</head>
<body class="admin-page">

<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>Enquiries</span>
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
      <li class="active"><a href="enquiries.php"><i class="fa-solid fa-clipboard-list"></i> Enquiries</a></li>
      <li><a href="students.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
      <li><a href="batches.php"><i class="fa-solid fa-clock"></i> Batch Slots</a></li>
      <li><a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> Attendance</a></li>
      <li><a href="holidays.php"><i class="fa-solid fa-calendar-xmark"></i> Holidays</a></li>
      <li><a href="sms-logs.php"><i class="fa-solid fa-comment-sms"></i> SMS Logs</a></li>
    </ul>
    <div class="admin-sidebar__stats">
      <div class="sidebar-stat">
        <strong><?= $counts['all'] ?></strong>
        <span>Total Enquiries</span>
      </div>
      <?php if ($followUpsToday > 0): ?>
      <div class="sidebar-stat" style="margin-top:8px">
        <strong style="color:#d97706"><?= $followUpsToday ?></strong>
        <span>Follow-ups Today</span>
      </div>
      <?php endif; ?>
    </div>
  </aside>

  <main class="admin-main">

    <?php if ($msg): ?>
    <div class="alert alert--<?= $msgType ?>">
      <i class="fa-solid fa-<?= $msgType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
      <?= $msg ?>
    </div>
    <?php endif; ?>

    <!-- ══ STAT CARDS ════════════════════════════════════════════ -->
    <div class="stat-cards">
      <div class="stat-card">
        <strong><?= $counts['all'] ?></strong><span>Total</span>
      </div>
      <div class="stat-card">
        <strong style="color:#1d4ed8"><?= $counts['new'] ?? 0 ?></strong><span>New</span>
      </div>
      <div class="stat-card">
        <strong style="color:#92400e"><?= $counts['contacted'] ?? 0 ?></strong><span>Contacted</span>
      </div>
      <div class="stat-card">
        <strong style="color:#065f46"><?= $counts['interested'] ?? 0 ?></strong><span>Interested</span>
      </div>
      <div class="stat-card">
        <strong style="color:#5b21b6"><?= $counts['enrolled'] ?? 0 ?></strong><span>Enrolled</span>
      </div>
      <?php if ($followUpsToday > 0): ?>
      <div class="stat-card" style="border:2px solid #fcd34d">
        <strong style="color:#d97706"><?= $followUpsToday ?></strong>
        <span>Follow-ups Today</span>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ ADD / EDIT FORM ═══════════════════════════════════════ -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2>
          <i class="fa-solid fa-<?= $editRow ? 'pen' : 'plus' ?>"></i>
          <?= $editRow
              ? 'Edit Enquiry — ' . htmlspecialchars($editRow['name'])
              : 'Add Enquiry' ?>
        </h2>
        <?php if (!$editRow): ?>
        <button type="button"
                onclick="var f=document.getElementById('enq-form');f.style.display=f.style.display==='none'?'block':'none'"
                class="btn btn--sm btn--outline">
          <i class="fa-solid fa-chevron-down"></i> New Enquiry
        </button>
        <?php endif; ?>
      </div>

      <div id="enq-form" style="<?= $editRow ? '' : 'display:none' ?>">
        <form method="POST" class="admin-form">
          <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>" />
          <?php if ($editRow): ?>
          <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>" />
          <?php endif; ?>

          <p class="sec-label"><i class="fa-solid fa-user"></i> Personal Details</p>
          <div class="form-grid">
            <div class="form-group">
              <label>Full Name <span style="color:#ef4444">*</span></label>
              <input type="text" name="name" placeholder="Prospect / student name" required
                     value="<?= htmlspecialchars($editRow['name'] ?? '') ?>" />
            </div>
            <div class="form-group">
              <label>Phone <span style="color:#ef4444">*</span></label>
              <input type="tel" name="phone" placeholder="10-digit mobile" required
                     value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>" />
            </div>
            <div class="form-group">
              <label>Email <span class="opt-tag">(optional)</span></label>
              <input type="email" name="email"
                     value="<?= htmlspecialchars($editRow['email'] ?? '') ?>" />
            </div>
            <div class="form-group">
              <label>Enquiry Date</label>
              <input type="date" name="enquiry_date"
                     value="<?= htmlspecialchars($editRow['enquiry_date'] ?? date('Y-m-d')) ?>" />
            </div>
          </div>

          <p class="sec-label"><i class="fa-solid fa-book-open"></i> Courses Interested
            <span class="opt-tag">(tick all that apply)</span>
          </p>
          <div class="form-group form-group--full" style="padding:0 20px 4px">
            <div class="course-checks">
              <?php
              $selCourses = array_map('trim', explode(',', $editRow['courses_interested'] ?? ''));
              foreach ($COURSES as $c):
              ?>
              <label>
                <input type="checkbox" name="courses_interested[]"
                       value="<?= htmlspecialchars($c) ?>"
                       <?= in_array($c, $selCourses) ? 'checked' : '' ?> />
                <?= htmlspecialchars($c) ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <p class="sec-label"><i class="fa-solid fa-clipboard"></i> Enquiry Details</p>
          <div class="form-grid">
            <div class="form-group">
              <label>Preferred Batch / Timing <span class="opt-tag">(optional)</span></label>
              <input type="text" name="preferred_batch" placeholder="e.g. Morning, 8 AM batch"
                     value="<?= htmlspecialchars($editRow['preferred_batch'] ?? '') ?>" />
            </div>
            <div class="form-group">
              <label>How did they find us?</label>
              <select name="source">
                <?php foreach ($SOURCE_LABELS as $v => $l): ?>
                <option value="<?= $v ?>"
                  <?= ($editRow['source'] ?? 'walk-in') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Current Status</label>
              <select name="status">
                <?php foreach (['new'=>'New','contacted'=>'Contacted','interested'=>'Interested',
                                'not-interested'=>'Not Interested','dropped'=>'Dropped'] as $v=>$l): ?>
                <option value="<?= $v ?>"
                  <?= ($editRow['status'] ?? 'new') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Follow-up Date
                <span class="opt-tag">(when to contact again)</span>
              </label>
              <input type="date" name="follow_up_date"
                     value="<?= htmlspecialchars($editRow['follow_up_date'] ?? '') ?>" />
            </div>
            <div class="form-group form-group--full">
              <label>Notes / Message <span class="opt-tag">(optional)</span></label>
              <textarea name="message" rows="2"
                        placeholder="Questions they asked, fees discussion, any relevant details…"><?= htmlspecialchars($editRow['message'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">
              <i class="fa-solid fa-<?= $editRow ? 'save' : 'plus' ?>"></i>
              <?= $editRow ? 'Save Changes' : 'Add Enquiry' ?>
            </button>
            <?php if ($editRow): ?>
            <a href="enquiries.php" class="btn btn--outline">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </section>

    <!-- ══ ENQUIRY LIST ══════════════════════════════════════════ -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-clipboard-list"></i>
          All Enquiries
          <?php if ($filterStatus !== 'all'): ?>
          <span style="font-weight:400;color:#64748b;font-size:.82rem">
            — <?= ucfirst(str_replace('-',' ',$filterStatus)) ?>
          </span>
          <?php endif; ?>
        </h2>
        <a href="enquiries.php?action=export_csv" class="btn btn--sm btn--outline">
          <i class="fa-solid fa-file-csv"></i> Export CSV
        </a>
      </div>

      <!-- Status tabs + search -->
      <div style="padding:.9rem 1.2rem 0">
        <div class="tab-bar" style="margin-bottom:.7rem">
          <?php
          $TAB_LABELS = [
            'all'           => 'All',
            'new'           => 'New',
            'contacted'     => 'Contacted',
            'interested'    => 'Interested',
            'enrolled'      => 'Enrolled',
            'not-interested'=> 'Not Interested',
            'dropped'       => 'Dropped',
          ];
          foreach ($TAB_LABELS as $val => $label):
            $active = ($filterStatus === $val) ? 'active' : '';
            $n = $counts[$val] ?? 0;
            $qs = http_build_query(['status'=>$val] + ($search ? ['q'=>$search] : [])
                                                    + ($filterCourse ? ['course'=>$filterCourse] : []));
          ?>
          <a href="enquiries.php?<?= $qs ?>" class="<?= $active ?>">
            <?= $label ?><span class="tab-count"><?= $n ?></span>
          </a>
          <?php endforeach; ?>
        </div>

        <!-- Search / course filter -->
        <form method="GET" class="filter-bar">
          <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>" />
          <input type="text" name="q" placeholder="Search name, phone, ENQ#…"
                 value="<?= htmlspecialchars($search) ?>" style="min-width:200px" />
          <select name="course">
            <option value="">— All Courses —</option>
            <?php foreach ($COURSES as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"
              <?= $filterCourse === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn--primary btn--sm">
            <i class="fa-solid fa-magnifying-glass"></i> Search
          </button>
          <?php if ($search || $filterCourse): ?>
          <a href="enquiries.php?status=<?= urlencode($filterStatus) ?>" class="btn btn--sm btn--outline">
            <i class="fa-solid fa-xmark"></i> Clear
          </a>
          <?php endif; ?>
        </form>
      </div>

      <!-- Table -->
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Enq. No</th>
              <th>Date</th>
              <th>Name &amp; Contact</th>
              <th>Courses Interested</th>
              <th>Source</th>
              <th>Follow-up</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($enquiries)): ?>
            <tr>
              <td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem">
                No enquiries found.
              </td>
            </tr>
            <?php endif; ?>
            <?php foreach ($enquiries as $e):
              $eId        = (int)$e['id'];
              $eStatus    = $e['status'];
              $isEnrolled = !empty($e['converted_to_student_id']);
              $followUp   = $e['follow_up_date'];
              $isTodayFU  = ($followUp === date('Y-m-d')
                             && !in_array($eStatus, ['enrolled','not-interested','dropped']));
              $courseList = array_filter(array_map('trim', explode(',', $e['courses_interested'] ?? '')));
              $badgeCss   = $statusCss[$eStatus] ?? 'eq-new';
            ?>
            <tr class="<?= $isTodayFU ? 'row--followup-today' : '' ?>">
              <td>
                <code style="font-size:.78rem;font-weight:700;color:#0f4e8a">
                  <?= htmlspecialchars($e['enquiry_number']) ?>
                </code>
              </td>
              <td style="font-size:.82rem;white-space:nowrap">
                <?= date('d M Y', strtotime($e['enquiry_date'])) ?>
              </td>
              <td>
                <strong><?= htmlspecialchars($e['name']) ?></strong>
                <br>
                <span style="font-size:.8rem;color:#475569"><?= htmlspecialchars($e['phone']) ?></span>
                <?php if ($e['email']): ?>
                <br><span class="hint"><?= htmlspecialchars($e['email']) ?></span>
                <?php endif; ?>
                <?php if ($e['preferred_batch']): ?>
                <br><span class="hint"><i class="fa-solid fa-clock fa-xs"></i>
                  <?= htmlspecialchars($e['preferred_batch']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <div class="courses-cell">
                  <?php foreach ($courseList as $c): ?>
                  <span class="course-chip"><?= htmlspecialchars($c) ?></span>
                  <?php endforeach; ?>
                  <?php if (empty($courseList)): ?>
                  <span style="color:#cbd5e1;font-size:.8rem">Not specified</span>
                  <?php endif; ?>
                </div>
              </td>
              <td style="font-size:.8rem;white-space:nowrap">
                <?= $SOURCE_LABELS[$e['source']] ?? $e['source'] ?>
              </td>
              <td>
                <?php if ($followUp): ?>
                  <?php if ($isTodayFU): ?>
                  <span class="followup-today">
                    <i class="fa-solid fa-bell"></i> Today!
                  </span>
                  <?php else: ?>
                  <span style="font-size:.82rem;white-space:nowrap">
                    <?= date('d M', strtotime($followUp)) ?>
                  </span>
                  <?php endif; ?>
                <?php else: ?>
                <span style="color:#cbd5e1;font-size:.8rem">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isEnrolled): ?>
                <!-- Enrolled: show static badge only -->
                <span class="eq-badge eq-enrolled">Enrolled</span>
                <?php else: ?>
                <!-- Inline quick-status dropdown -->
                <form method="POST" style="display:inline"
                      onsubmit="return quickStatus(event, <?= $eId ?>)">
                  <input type="hidden" name="action" value="quick_status" />
                  <input type="hidden" name="id" value="<?= $eId ?>" />
                  <select name="status"
                          class="quick-status <?= $badgeCss ?>"
                          onchange="this.form.requestSubmit()"
                          title="Change status">
                    <?php foreach (['new'=>'New','contacted'=>'Contacted','interested'=>'Interested',
                                    'not-interested'=>'Not Interested','dropped'=>'Dropped'] as $sv=>$sl): ?>
                    <option value="<?= $sv ?>"
                      <?= $eStatus === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap">
                <?php if ($isEnrolled): ?>
                <!-- Already registered: link to student record -->
                <a href="students.php?q=<?= urlencode($e['student_name_reg'] ?? $e['name']) ?>"
                   class="view-stu-btn">
                  <i class="fa-solid fa-user-graduate"></i>
                  <?= htmlspecialchars($e['student_adm_no'] ?? 'View') ?>
                </a>
                <?php else: ?>
                <!-- Convert to Student button -->
                <a href="students.php?from_enquiry=<?= $eId ?>"
                   class="convert-btn"
                   title="Open registration form pre-filled from this enquiry">
                  <i class="fa-solid fa-arrow-right-to-bracket"></i> Register →
                </a>
                <?php endif; ?>

                <!-- Edit -->
                <a href="enquiries.php?edit=<?= $eId ?>"
                   class="btn-icon btn-icon--edit" title="Edit" style="margin-left:4px">
                  <i class="fa-solid fa-pen"></i>
                </a>

                <!-- Delete (only if not enrolled) -->
                <?php if (!$isEnrolled): ?>
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Delete enquiry for <?= htmlspecialchars(addslashes($e['name'])) ?>?')">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= $eId ?>" />
                  <button type="submit" class="btn-icon btn-icon--delete" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>
</div>

<script>
/* Inline quick-status update via fetch, then reload */
function quickStatus(e, id) {
    e.preventDefault();
    var form   = e.target;
    var status = form.querySelector('[name=status]').value;
    fetch('enquiries.php', {
        method : 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body   : 'action=quick_status&id=' + id + '&status=' + encodeURIComponent(status)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.ok) location.reload(); });
    return false;
}
</script>
</body>
</html>
