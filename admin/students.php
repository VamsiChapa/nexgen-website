<?php
/* ================================================================
   NEx-gEN Admin — Student Management
   URL: /admin/students.php
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$msg = ''; $msgType = '';

/* ── Auto-generate unique admission number ───────────────────────── */
function generateAdmissionNumber($pdo) {
    $year = date('Y');
    $last = $pdo->query(
        "SELECT admission_number FROM students
         WHERE admission_number LIKE 'NXG{$year}%'
         ORDER BY id DESC LIMIT 1"
    )->fetchColumn();
    $seq = $last ? ((int)substr($last, -4) + 1) : 1;
    return 'NXG' . $year . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/* ── Load active batches ─────────────────────────────────────────── */
$allBatches = $pdo->query(
    'SELECT * FROM batches WHERE is_active=1 ORDER BY sort_order ASC, start_time ASC'
)->fetchAll();
$batchMap = array_column($allBatches, null, 'id');

/* batch lookup by name (for CSV import) */
$batchByName = [];
foreach ($allBatches as $b) { $batchByName[strtolower(trim($b['name']))] = $b['id']; }

/* ── Load enquiry for pre-fill (conversion: enquiries → student) ─── */
$fromEnquiry   = null;
$fromEnquiryId = 0;
if (!empty($_GET['from_enquiry'])) {
    $feq = $pdo->prepare(
        'SELECT * FROM enquiries WHERE id=? AND converted_to_student_id IS NULL LIMIT 1'
    );
    $feq->execute([(int)$_GET['from_enquiry']]);
    $fromEnquiry = $feq->fetch() ?: null;
    if ($fromEnquiry) {
        $fromEnquiryId = (int)$fromEnquiry['id'];
    }
}

/* ── DELETE ──────────────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM students WHERE id=?')->execute([$id]);
        $msg = 'Student deleted.'; $msgType = 'success';
    }
}

/* ── TOGGLE STATUS ───────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE students SET status=IF(status='active','inactive','active') WHERE id=?")
            ->execute([$id]);
        $msg = 'Status updated.'; $msgType = 'success';
    }
}

/* ── TOGGLE SMS ──────────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'toggle_sms') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('UPDATE students SET sms_enabled=1-sms_enabled WHERE id=?')->execute([$id]);
        $msg = 'SMS preference updated.'; $msgType = 'success';
    }
}

/* ── ADD / EDIT ──────────────────────────────────────────────────── */
if (isset($_POST['action']) && in_array($_POST['action'], ['add','edit'])) {
    $editId     = (int)($_POST['edit_id']        ?? 0);
    $name       = trim($_POST['student_name']    ?? '');
    $phone      = preg_replace('/\D/','',trim($_POST['phone'] ?? ''));
    $email      = trim($_POST['email']           ?? '') ?: null;
    $dob        = trim($_POST['date_of_birth']   ?? '') ?: null;
    $gender     = trim($_POST['gender']          ?? '') ?: null;
    $address    = trim($_POST['address']         ?? '') ?: null;
    $course     = trim($_POST['course']          ?? '');
    $batchId    = (int)($_POST['batch_id']       ?? 0);
    $enroll     = trim($_POST['enrollment_date'] ?? '') ?: date('Y-m-d');
    $pName      = trim($_POST['parent_name']     ?? '') ?: null;
    $pPhone     = preg_replace('/\D/','',trim($_POST['parent_phone'] ?? '')) ?: null;
    $pEmail     = trim($_POST['parent_email']    ?? '') ?: null;
    $pRel       = trim($_POST['parent_relation'] ?? '') ?: null;
    $bioId      = trim($_POST['biometric_id']    ?? '') ?: null;
    $smsEnabled      = isset($_POST['sms_enabled']) ? 1 : 0;
    $notes           = trim($_POST['notes']           ?? '') ?: null;
    $status          = trim($_POST['status']          ?? 'active');
    $sourceEnquiryId = (int)($_POST['source_enquiry_id'] ?? 0);

    if (!$name || !$course || !$batchId) {
        $msg = 'Name, course and batch are required.'; $msgType = 'error';
    } else {
        /* Optional photo upload */
        $photoUrl = trim($_POST['photo_url_existing'] ?? '') ?: null;
        if (!empty($_FILES['photo']['name'])) {
            $f = $_FILES['photo'];
            if (!in_array($f['type'], ['image/jpeg','image/png','image/webp'])) {
                $msg = 'Photo must be JPG, PNG or WEBP.'; $msgType = 'error';
            } elseif ($f['size'] > 2*1024*1024) {
                $msg = 'Photo must be under 2 MB.'; $msgType = 'error';
            } else {
                $ext    = pathinfo($f['name'], PATHINFO_EXTENSION);
                $fname  = 'stu_' . $phone . '_' . time() . '.' . $ext;
                $dir    = dirname(__DIR__) . '/students/photos/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if (move_uploaded_file($f['tmp_name'], $dir . $fname)) {
                    $photoUrl = 'students/photos/' . $fname;
                } else {
                    $msg = 'Photo upload failed.'; $msgType = 'error';
                }
            }
        }

        if ($msgType !== 'error') {
            $fields = [
                $name,$phone,$email,$dob,$gender,$address,
                $course,$batchId,$enroll,
                $pName,$pPhone,$pEmail,$pRel,
                $bioId,$smsEnabled,$status,$notes
            ];
            try {
                if ($_POST['action'] === 'add') {
                    $admNo    = generateAdmissionNumber($pdo);
                    $srcEnqId = ($sourceEnquiryId > 0) ? $sourceEnquiryId : null;
                    $pdo->prepare(
                        'INSERT INTO students
                         (admission_number,student_name,phone,email,date_of_birth,gender,address,
                          course,batch_id,enrollment_date,
                          parent_name,parent_phone,parent_email,parent_relation,
                          biometric_id,sms_enabled,status,notes,source_enquiry_id,photo_url)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute(array_merge([$admNo], $fields, [$srcEnqId, $photoUrl]));
                    $msg = "Student \"{$name}\" registered. Admission No: <strong>{$admNo}</strong>";
                    $msgType = 'success';
                    /* ── Link back to enquiry ── */
                    if ($sourceEnquiryId > 0) {
                        $newStudentId = (int)$pdo->lastInsertId();
                        $pdo->prepare(
                            "UPDATE enquiries SET status='enrolled', converted_to_student_id=?
                             WHERE id=? AND converted_to_student_id IS NULL"
                        )->execute([$newStudentId, $sourceEnquiryId]);
                        $msg .= ' — Enquiry <strong>converted &amp; marked Enrolled</strong> ✓';
                    }
                } else {
                    $sets = 'student_name=?,phone=?,email=?,date_of_birth=?,gender=?,address=?,
                             course=?,batch_id=?,enrollment_date=?,
                             parent_name=?,parent_phone=?,parent_email=?,parent_relation=?,
                             biometric_id=?,sms_enabled=?,status=?,notes=?';
                    $vals = $fields;
                    if ($photoUrl !== null) { $sets .= ',photo_url=?'; $vals[] = $photoUrl; }
                    $vals[] = $editId;
                    $pdo->prepare("UPDATE students SET {$sets} WHERE id=?")->execute($vals);
                    $msg = 'Student updated.'; $msgType = 'success';
                }
            } catch (\PDOException $e) {
                $msg = 'Database error: ' . $e->getMessage(); $msgType = 'error';
            }
        }
    }
}

/* ── CSV BULK IMPORT ─────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'csv_import') {
    if (!empty($_FILES['import_csv']['tmp_name'])) {
        $handle  = fopen($_FILES['import_csv']['tmp_name'], 'r');
        $headers = fgetcsv($handle);

        if (!$headers) {
            $msg = 'CSV appears empty.'; $msgType = 'error';
        } else {
            /* Normalise header keys — PHP 7.4 compatible (no int|false union type) */
            $hdr = array_map(function($h) { return strtolower(trim($h)); }, $headers);
            $col = function($name) use ($hdr) {
                $idx = array_search($name, $hdr);
                return ($idx !== false) ? (int)$idx : null;
            };

            $inserted = 0; $skipped = 0; $errors = [];

            $stmt = $pdo->prepare(
                'INSERT INTO students
                 (admission_number,student_name,phone,email,date_of_birth,gender,address,
                  course,batch_id,enrollment_date,
                  parent_name,parent_phone,parent_email,parent_relation,
                  sms_enabled,status,notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,\'active\',?)'
            );

            $rowNum = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (implode('', $row) === '') continue;

                /* Get cell value by column name — PHP 7.4 compatible closure */
                $get = function($k) use ($row, $col) {
                    $i = $col($k);
                    return ($i !== null && isset($row[$i])) ? trim($row[$i]) : '';
                };

                $name   = $get('student_name');
                $phone  = preg_replace('/\D/', '', $get('phone'));
                $course = $get('course');
                $bname  = strtolower(trim($get('batch_name')));

                if (!$name || !$phone || !$course || !$bname) {
                    $errors[] = "Row {$rowNum}: Missing required field (name, phone, course or batch_name).";
                    $skipped++; continue;
                }

                /* Match batch by name — PHP 7.4: strpos instead of str_contains */
                $bId = null;
                foreach ($batchByName as $bn => $bid) {
                    if (strpos($bn, $bname) !== false || strpos($bname, $bn) !== false) {
                        $bId = $bid; break;
                    }
                }
                if (!$bId) {
                    $errors[] = "Row {$rowNum}: Batch \"{$get('batch_name')}\" not found. Check Batch Slots.";
                    $skipped++; continue;
                }

                $dob    = $get('date_of_birth') ?: null;
                $enroll = $get('enrollment_date') ?: date('Y-m-d');
                $pPhone = preg_replace('/\D/', '', $get('parent_phone')) ?: null;

                try {
                    $admNo = generateAdmissionNumber($pdo);
                    $stmt->execute([
                        $admNo, $name, $phone,
                        $get('email')           ?: null, $dob,
                        $get('gender')          ?: null,
                        $get('address')         ?: null,
                        $course, $bId, $enroll,
                        $get('parent_name')     ?: null, $pPhone,
                        $get('parent_email')    ?: null,
                        $get('parent_relation') ?: null,
                        $get('notes')           ?: null,
                    ]);
                    if ($stmt->rowCount()) {
                        $inserted++;
                    } else {
                        $errors[] = "Row {$rowNum}: Phone {$phone} already exists — skipped.";
                        $skipped++;
                    }
                } catch (\PDOException $e) {
                    $errors[] = "Row {$rowNum}: DB error — " . $e->getMessage();
                    $skipped++;
                }
            }
            fclose($handle);

            $msg = "Import complete: {$inserted} added, {$skipped} skipped.";
            if ($errors) $msg .= '<br><small>' . implode('<br>', array_slice($errors, 0, 10)) . '</small>';
            $msgType = ($skipped > 0) ? 'warning' : 'success';
        }
    } else {
        $msg = 'Please choose a CSV file.'; $msgType = 'error';
    }
}

/* ── Edit row ────────────────────────────────────────────────────── */
$editRow = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM students WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $editRow = $s->fetch();
}

/* ── Paginated list ──────────────────────────────────────────────── */
$search  = trim($_GET['q']       ?? '');
$bFilter = (int)($_GET['batch']  ?? 0);
$page    = max(1,(int)($_GET['page'] ?? 1));
$perPage = 20; $offset = ($page-1)*$perPage;

$where = '1=1'; $params = [];
if ($search) {
    $where   .= ' AND (s.student_name LIKE ? OR s.phone LIKE ? OR s.parent_phone LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($bFilter > 0) { $where .= ' AND s.batch_id=?'; $params[] = $bFilter; }

$totalCount = 0; $totalPages = 0; $students = [];
try {
    $total = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE $where");
    $total->execute($params);
    $totalCount = (int)$total->fetchColumn();
    $totalPages = (int)ceil($totalCount / $perPage);

    $list = $pdo->prepare(
        "SELECT s.*, b.name AS batch_name, b.start_time, b.end_time,
                eq.enquiry_number AS source_enq_number
         FROM students s
         LEFT JOIN batches b ON b.id = s.batch_id
         LEFT JOIN enquiries eq ON eq.id = s.source_enquiry_id
         WHERE $where ORDER BY s.enrollment_date DESC, s.id DESC LIMIT ? OFFSET ?"
    );
    $list->execute(array_merge($params, [$perPage, $offset]));
    $students = $list->fetchAll();
} catch (\PDOException $e) {
    $msg     = 'Database error: ' . $e->getMessage()
             . ' — Did you run db-setup-students.sql and db-migrate-sms-optional.sql in phpMyAdmin?';
    $msgType = 'error';
}
/* ── Pre-fill helper for enquiry → registration conversion ──────── */
$prefill = $editRow ?? [];
if (!$editRow && $fromEnquiry) {
    $coursesList = array_filter(array_map('trim', explode(',', $fromEnquiry['courses_interested'] ?? '')));
    $firstCourse = (string)(reset($coursesList) ?: '');
    $prefill = [
        'student_name' => $fromEnquiry['name'],
        'phone'        => $fromEnquiry['phone'],
        'email'        => $fromEnquiry['email'] ?? '',
        'course'       => $firstCourse,
        'notes'        => 'Enquiry Ref: ' . $fromEnquiry['enquiry_number'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Students — Admin | NEx-gEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    .avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;}
    .avatar-placeholder{width:36px;height:36px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#94a3b8;}
    .filter-bar{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;}
    .filter-bar select,.filter-bar input{padding:.42rem .8rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.87rem;}
    .batch-pill{display:inline-block;background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:10px;font-size:.74rem;font-weight:600;white-space:nowrap;}
    .sec-label{font-weight:600;font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:1.1rem 0 .35rem;padding-bottom:.3rem;border-bottom:1.5px solid #f1f5f9;}
    .opt-tag{font-size:.72rem;color:#94a3b8;font-weight:400;margin-left:4px;}
    .import-box{background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:12px;padding:1.2rem 1.4rem;}
    .import-box p{margin:0 0 .7rem;font-size:.87rem;color:#475569;line-height:1.6;}
    .import-row{display:flex;gap:.7rem;flex-wrap:wrap;align-items:center;}
    .badge-sms-on{background:#d4edda;color:#155724;padding:2px 9px;border-radius:20px;font-size:.74rem;font-weight:600;cursor:pointer;border:none;}
    .badge-sms-off{background:#f1f5f9;color:#94a3b8;padding:2px 9px;border-radius:20px;font-size:.74rem;font-weight:600;cursor:pointer;border:none;text-decoration:line-through;}
    .hint{font-size:.75rem;color:#94a3b8;}
    .alert--warning{background:#fffbeb;border-color:#fcd34d;color:#92400e;}
  </style>
</head>
<body class="admin-page">

<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>Students</span>
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
      <li class="active"><a href="students.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
      <li><a href="batches.php"><i class="fa-solid fa-clock"></i> Batch Slots</a></li>
      <li><a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> Attendance</a></li>
      <li><a href="holidays.php"><i class="fa-solid fa-calendar-xmark"></i> Holidays</a></li>
      <li><a href="sms-logs.php"><i class="fa-solid fa-comment-sms"></i> SMS Logs</a></li>
      <li><a href="analytics.php"><i class="fa-solid fa-chart-bar"></i> Analytics</a></li>
    </ul>
    <div class="admin-sidebar__stats">
      <div class="sidebar-stat"><strong><?= $totalCount ?></strong><span>Total Students</span></div>
    </div>
  </aside>

  <main class="admin-main">

    <?php if ($msg): ?>
    <div class="alert alert--<?= $msgType ?>">
      <i class="fa-solid fa-<?= $msgType === 'success' ? 'circle-check' : ($msgType === 'warning' ? 'triangle-exclamation' : 'circle-exclamation') ?>"></i>
      <?= $msg ?>
    </div>
    <?php endif; ?>

    <!-- ══ SECTION 1: CSV BULK IMPORT ══════════════════════════════ -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-file-csv"></i> Bulk Import Students via CSV</h2>
        <a href="student-template.php" class="btn-secondary" style="padding:.38rem .9rem;font-size:.82rem;">
          <i class="fa-solid fa-download"></i> Download Template
        </a>
      </div>
      <div class="import-box">
        <p>
          <strong>How to use:</strong> Click <em>Download Template</em> above to get a sample CSV with all columns.
          Fill it in (only <strong>Name, Phone, Course, Batch</strong> are required — everything else is optional).
          Then upload it below. Duplicate phone numbers are automatically skipped.
        </p>
        <p style="margin-bottom:.5rem;">
          <strong>Batch name must match exactly</strong> — e.g. <code>8:00 AM – 9:00 AM</code>.
          Check <a href="batches.php" target="_blank">Batch Slots</a> for the exact names.
        </p>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="csv_import" />
          <div class="import-row">
            <input type="file" name="import_csv" accept=".csv,text/csv" required />
            <button type="submit" class="btn-primary">
              <i class="fa-solid fa-upload"></i> Import CSV
            </button>
          </div>
        </form>
      </div>
    </section>

    <!-- ══ SECTION 2: SINGLE REGISTER / EDIT FORM ═════════════════ -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-<?= $editRow ? 'pen' : 'user-plus' ?>"></i>
          <?= $editRow ? 'Edit Student' : 'Register Single Student' ?></h2>
      </div>

      <?php if ($fromEnquiry): ?>
      <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:10px;
                  padding:12px 18px;margin:16px 20px 0;display:flex;align-items:center;
                  gap:12px;font-size:.87rem;flex-wrap:wrap">
        <i class="fa-solid fa-clipboard-list" style="color:#2563eb;font-size:1.1rem"></i>
        <span>
          Converting enquiry
          <strong style="color:#1d4ed8"><?= htmlspecialchars($fromEnquiry['enquiry_number']) ?></strong>
          — <strong><?= htmlspecialchars($fromEnquiry['name']) ?></strong>
          <?php if ($fromEnquiry['courses_interested']): ?>
          &nbsp;|&nbsp; Interested in: <em><?= htmlspecialchars($fromEnquiry['courses_interested']) ?></em>
          <?php endif; ?>
        </span>
        <a href="enquiries.php" style="margin-left:auto;color:#3b82f6;font-size:.8rem;white-space:nowrap">
          ← Back to Enquiries
        </a>
      </div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>" />
        <?php if ($editRow): ?>
        <input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>" />
        <input type="hidden" name="photo_url_existing" value="<?= htmlspecialchars($editRow['photo_url'] ?? '') ?>" />
        <?php endif; ?>
        <?php if ($fromEnquiryId > 0): ?>
        <input type="hidden" name="source_enquiry_id" value="<?= $fromEnquiryId ?>" />
        <?php endif; ?>

        <!-- Student Info -->
        <p class="sec-label"><i class="fa-solid fa-user"></i> Student Information</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name <span style="color:#ef4444;">*</span></label>
            <input type="text" name="student_name" placeholder="Full name"
                   value="<?= htmlspecialchars($prefill['student_name'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label>Phone <span class="opt-tag">(optional — siblings may share same number)</span></label>
            <input type="tel" name="phone" placeholder="10-digit mobile"
                   value="<?= htmlspecialchars($prefill['phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Email <span class="opt-tag">(optional)</span></label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($prefill['email'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Date of Birth <span class="opt-tag">(optional)</span></label>
            <input type="date" name="date_of_birth"
                   value="<?= htmlspecialchars($editRow['date_of_birth'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Gender <span class="opt-tag">(optional)</span></label>
            <select name="gender">
              <option value="">— Select —</option>
              <?php foreach (['male'=>'Male','female'=>'Female','other'=>'Other'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($editRow['gender'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <?php foreach (['active'=>'Active','inactive'=>'Inactive','completed'=>'Completed','dropped'=>'Dropped'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($editRow['status'] ?? 'active') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-group--full">
            <label>Address <span class="opt-tag">(optional)</span></label>
            <textarea name="address" rows="2"><?= htmlspecialchars($editRow['address'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Enrollment -->
        <p class="sec-label"><i class="fa-solid fa-book-open"></i> Enrollment Details</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Course <span style="color:#ef4444;">*</span></label>
            <input type="text" name="course" placeholder="PGDCA / DCA / MS OFFICE…"
                   value="<?= htmlspecialchars($prefill['course'] ?? '') ?>" required list="course-list" />
            <datalist id="course-list">
              <?php foreach ([
                  'MS OFFICE',
                  'PROG IN C',
                  'CORE JAVA',
                  'PYTHON',
                  'TALLY PRIME',
                  'WEB DESIGNING',
                  'MSO, C',
                  'MSO, TALLY',
                  'DCA',
                  'PGDCA',
                  'HAND WRITING',
              ] as $c): ?>
              <option value="<?= $c ?>">
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="form-group">
            <label>Batch / Class Timing <span style="color:#ef4444;">*</span></label>
            <select name="batch_id" required>
              <option value="">— Select Batch —</option>
              <?php foreach ($allBatches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= ($editRow['batch_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <span class="hint">
              Slot missing? <a href="batches.php" target="_blank">Add it in Batch Slots →</a>
            </span>
          </div>
          <div class="form-group">
            <label>Enrollment Date</label>
            <input type="date" name="enrollment_date"
                   value="<?= htmlspecialchars($editRow['enrollment_date'] ?? date('Y-m-d')) ?>" />
          </div>
          <div class="form-group">
            <label>
              Biometric Device ID
              <span class="opt-tag">(optional — future scope)</span>
            </label>
            <input type="text" name="biometric_id" placeholder="e.g. 00042"
                   value="<?= htmlspecialchars($editRow['biometric_id'] ?? '') ?>" />
            <span class="hint">Leave blank if not using biometric device</span>
          </div>
        </div>

        <!-- Parent Info -->
        <p class="sec-label"><i class="fa-solid fa-users"></i> Parent / Guardian <span class="opt-tag">(all optional)</span></p>
        <div class="form-grid">
          <div class="form-group">
            <label>Parent Name</label>
            <input type="text" name="parent_name"
                   value="<?= htmlspecialchars($editRow['parent_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>
              Parent Phone
              <span class="opt-tag">(WhatsApp/SMS alerts go here)</span>
            </label>
            <input type="tel" name="parent_phone"
                   value="<?= htmlspecialchars($editRow['parent_phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Parent Email <span class="opt-tag">(optional)</span></label>
            <input type="email" name="parent_email"
                   value="<?= htmlspecialchars($editRow['parent_email'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Relation <span class="opt-tag">(optional)</span></label>
            <input type="text" name="parent_relation" placeholder="Father / Mother / Guardian"
                   value="<?= htmlspecialchars($editRow['parent_relation'] ?? '') ?>" list="rel-list" />
            <datalist id="rel-list">
              <option value="Father"><option value="Mother"><option value="Guardian"><option value="Sibling">
            </datalist>
          </div>
        </div>

        <!-- Alerts + Photo -->
        <p class="sec-label"><i class="fa-solid fa-bell"></i> Alerts &amp; Photo</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Absence SMS / WhatsApp Alerts</label>
            <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;margin-top:.3rem;">
              <input type="checkbox" name="sms_enabled" value="1"
                     <?= ($editRow['sms_enabled'] ?? 1) ? 'checked' : '' ?>
                     style="width:16px;height:16px;accent-color:#2563eb;" />
              <span style="font-size:.9rem;">Send absence alerts for this student</span>
            </label>
            <span class="hint">Uncheck to opt this student out of all automated alerts</span>
          </div>
          <div class="form-group">
            <label>Photo <span class="opt-tag">(optional · JPG/PNG · max 2MB)</span></label>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" />
            <?php if (!empty($editRow['photo_url'])): ?>
            <span class="hint">
              <img src="../<?= htmlspecialchars($editRow['photo_url']) ?>"
                   style="height:28px;width:28px;border-radius:50%;object-fit:cover;vertical-align:middle;" />
              Existing photo kept if blank.
            </span>
            <?php endif; ?>
          </div>
          <div class="form-group form-group--full">
            <label>Notes <span class="opt-tag">(optional)</span></label>
            <textarea name="notes" rows="2"><?= htmlspecialchars($prefill['notes'] ?? '') ?></textarea>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-<?= $editRow ? 'floppy-disk' : 'user-plus' ?>"></i>
            <?= $editRow ? 'Update Student' : 'Register Student' ?>
          </button>
          <?php if ($editRow): ?>
          <a href="students.php" class="btn-secondary">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <!-- ══ SECTION 3: STUDENT LIST ═════════════════════════════════ -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-list"></i> All Students (<?= $totalCount ?>)</h2>
      </div>

      <form method="GET" class="filter-bar">
        <input type="text" name="q" placeholder="Search name / phone…" value="<?= htmlspecialchars($search) ?>" />
        <select name="batch">
          <option value="">All Batches</option>
          <?php foreach ($allBatches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= $bFilter == $b['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($b['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary" style="padding:.4rem 1rem;">
          <i class="fa-solid fa-magnifying-glass"></i> Filter
        </button>
        <?php if ($search || $bFilter): ?>
        <a href="students.php" class="btn-secondary" style="padding:.4rem 1rem;">
          <i class="fa-solid fa-xmark"></i> Clear
        </a>
        <?php endif; ?>
      </form>

      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th><th>Adm. No</th><th>Photo</th><th>Name</th><th>Phone</th>
              <th>Course</th><th>Batch</th><th>Parent Phone</th>
              <th>Alerts</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($students): foreach ($students as $i => $s): ?>
            <tr class="<?= $s['status'] !== 'active' ? 'row--inactive' : '' ?>">
              <td><?= $offset + $i + 1 ?></td>
              <td style="font-size:.78rem;font-weight:600;color:#2563eb;white-space:nowrap;">
                <?= htmlspecialchars($s['admission_number'] ?? '—') ?>
              </td>
              <td>
                <?php if ($s['photo_url']): ?>
                <img src="../<?= htmlspecialchars($s['photo_url']) ?>" class="avatar" alt="" />
                <?php else: ?>
                <div class="avatar-placeholder"><i class="fa-solid fa-user fa-sm"></i></div>
                <?php endif; ?>
              </td>
              <td>
                <?= htmlspecialchars($s['student_name']) ?>
                <?php if (!empty($s['source_enq_number'])): ?>
                <br>
                <a href="enquiries.php?q=<?= urlencode($s['source_enq_number']) ?>"
                   class="enq-source-tag" title="Came via enquiry — click to view">
                  <i class="fa-solid fa-clipboard-list fa-xs"></i>
                  <?= htmlspecialchars($s['source_enq_number']) ?>
                </a>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($s['phone']) ?></td>
              <td><?= htmlspecialchars($s['course']) ?></td>
              <td>
                <span class="batch-pill">
                  <?= $s['start_time']
                      ? date('g:i', strtotime($s['start_time'])) . '–' . date('g:i A', strtotime($s['end_time']))
                      : '—' ?>
                </span>
              </td>
              <td><?= htmlspecialchars($s['parent_phone'] ?? '—') ?></td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_sms" />
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                  <button type="submit"
                    class="<?= ($s['sms_enabled'] ?? 1) ? 'badge-sms-on' : 'badge-sms-off' ?>"
                    title="Click to toggle SMS alerts">
                    <?= ($s['sms_enabled'] ?? 1) ? '🔔 On' : '🔕 Off' ?>
                  </button>
                </form>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle" />
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                  <button type="submit" class="badge badge--<?= $s['status'] === 'active' ? 'green' : 'grey' ?>">
                    <?= ucfirst($s['status']) ?>
                  </button>
                </form>
              </td>
              <td class="td-actions">
                <a href="?edit=<?= (int)$s['id'] ?>" class="btn-icon btn-icon--edit" title="Edit">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <a href="attendance.php?student_id=<?= (int)$s['id'] ?>"
                   class="btn-icon" style="background:#e0f2fe;color:#0369a1;" title="Attendance">
                  <i class="fa-solid fa-calendar-check"></i>
                </a>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Delete this student and ALL their records?');">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                  <button type="submit" class="btn-icon btn-icon--delete" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="11" class="empty">No students found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($p=1; $p<=$totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&batch=<?= $bFilter ?>"
           class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </section>

  </main>
</div>
</body>
</html>
