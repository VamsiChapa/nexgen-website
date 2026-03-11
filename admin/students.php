<?php
/* ================================================================
   NEx-gEN Admin — Student Management
   URL: /admin/students.php
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$msg = ''; $msgType = '';

/* ── Load all batches for dropdowns ─────────────────────────────── */
$allBatches = $pdo->query(
    'SELECT * FROM batches WHERE is_active=1 ORDER BY sort_order ASC, start_time ASC'
)->fetchAll();
$batchMap = array_column($allBatches, null, 'id');

/* ── DELETE ──────────────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
        $msg = 'Student record deleted.'; $msgType = 'success';
    }
}

/* ── TOGGLE STATUS ───────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare(
            "UPDATE students SET status = IF(status='active','inactive','active') WHERE id = ?"
        )->execute([$id]);
        $msg = 'Status updated.'; $msgType = 'success';
    }
}

/* ── ADD / EDIT ──────────────────────────────────────────────────── */
if (isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
    $editId   = (int)($_POST['edit_id']      ?? 0);
    $name     = trim($_POST['student_name']  ?? '');
    $phone    = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
    $email    = trim($_POST['email']         ?? '') ?: null;
    $dob      = trim($_POST['date_of_birth'] ?? '') ?: null;
    $gender   = trim($_POST['gender']        ?? '') ?: null;
    $address  = trim($_POST['address']       ?? '') ?: null;
    $course   = trim($_POST['course']        ?? '');
    $batchId  = (int)($_POST['batch_id']     ?? 0);
    $enroll   = trim($_POST['enrollment_date']?? '') ?: date('Y-m-d');
    $pName    = trim($_POST['parent_name']   ?? '') ?: null;
    $pPhone   = preg_replace('/\D/', '', trim($_POST['parent_phone'] ?? '')) ?: null;
    $pEmail   = trim($_POST['parent_email']  ?? '') ?: null;
    $pRel     = trim($_POST['parent_relation']?? '') ?: null;
    $bioId    = trim($_POST['biometric_id']  ?? '') ?: null;
    $notes    = trim($_POST['notes']         ?? '') ?: null;
    $status   = trim($_POST['status']        ?? 'active');

    if (!$name || !$phone || !$course || !$batchId) {
        $msg = 'Name, phone, course and batch are required.'; $msgType = 'error';
    } else {
        /* Photo upload */
        $photoUrl = trim($_POST['photo_url_existing'] ?? '') ?: null;
        if (!empty($_FILES['photo']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp'];
            $f = $_FILES['photo'];
            if (!in_array($f['type'], $allowed)) {
                $msg = 'Photo must be JPG, PNG or WEBP.'; $msgType = 'error';
            } elseif ($f['size'] > 2 * 1024 * 1024) {
                $msg = 'Photo must be under 2 MB.'; $msgType = 'error';
            } else {
                $ext     = pathinfo($f['name'], PATHINFO_EXTENSION);
                $fname   = 'stu_' . preg_replace('/\D/', '', $phone) . '_' . time() . '.' . $ext;
                $destDir = dirname(__DIR__) . '/students/photos/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                if (move_uploaded_file($f['tmp_name'], $destDir . $fname)) {
                    $photoUrl = 'students/photos/' . $fname;
                } else {
                    $msg = 'Photo upload failed.'; $msgType = 'error';
                }
            }
        }

        if ($msgType !== 'error') {
            $fields = [
                $name, $phone, $email, $dob, $gender, $address,
                $course, $batchId, $enroll,
                $pName, $pPhone, $pEmail, $pRel,
                $bioId, $status, $notes
            ];
            try {
                if ($_POST['action'] === 'add') {
                    $pdo->prepare(
                        'INSERT INTO students
                          (student_name,phone,email,date_of_birth,gender,address,
                           course,batch_id,enrollment_date,
                           parent_name,parent_phone,parent_email,parent_relation,
                           biometric_id,status,notes,photo_url)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    )->execute(array_merge($fields, [$photoUrl]));
                    $msg = "Student \"{$name}\" registered successfully."; $msgType = 'success';
                } else {
                    $sets = 'student_name=?,phone=?,email=?,date_of_birth=?,gender=?,address=?,
                             course=?,batch_id=?,enrollment_date=?,
                             parent_name=?,parent_phone=?,parent_email=?,parent_relation=?,
                             biometric_id=?,status=?,notes=?';
                    $vals = $fields;
                    if ($photoUrl !== null) { $sets .= ',photo_url=?'; $vals[] = $photoUrl; }
                    $vals[] = $editId;
                    $pdo->prepare("UPDATE students SET {$sets} WHERE id=?")->execute($vals);
                    $msg = 'Student updated.'; $msgType = 'success';
                }
            } catch (\PDOException $e) {
                $msg = 'Error: Phone number may already exist.'; $msgType = 'error';
            }
        }
    }
}

/* ── Fetch for edit ──────────────────────────────────────────────── */
$editRow = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM students WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $editRow = $s->fetch();
}

/* ── Paginated + filtered list ───────────────────────────────────── */
$search   = trim($_GET['q']     ?? '');
$bFilter  = (int)($_GET['batch'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where  = '1=1'; $params = [];
if ($search) {
    $where   .= ' AND (s.student_name LIKE ? OR s.phone LIKE ? OR s.parent_phone LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($bFilter > 0) {
    $where .= ' AND s.batch_id = ?'; $params[] = $bFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE $where");
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();
$totalPages = (int)ceil($totalCount / $perPage);

$listStmt = $pdo->prepare(
    "SELECT s.*, b.name AS batch_name, b.start_time, b.end_time
     FROM students s
     LEFT JOIN batches b ON b.id = s.batch_id
     WHERE $where
     ORDER BY s.enrollment_date DESC, s.id DESC
     LIMIT ? OFFSET ?"
);
$listStmt->execute(array_merge($params, [$perPage, $offset]));
$students = $listStmt->fetchAll();
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
    .avatar{width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;}
    .avatar-placeholder{width:38px;height:38px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#94a3b8;}
    .filter-bar{display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;}
    .filter-bar select,.filter-bar input{padding:.45rem .8rem;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:.88rem;}
    .batch-pill{display:inline-block;background:#eff6ff;color:#2563eb;padding:2px 9px;border-radius:12px;font-size:.75rem;font-weight:600;white-space:nowrap;}
    .form-section-label{font-weight:600;font-size:.82rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:1.2rem 0 .4rem;padding-bottom:.3rem;border-bottom:1.5px solid #f1f5f9;}
  </style>
</head>
<body class="admin-page">

<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>Student Admin</span>
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
      <li class="active"><a href="students.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
      <li><a href="batches.php"><i class="fa-solid fa-clock"></i> Batch Slots</a></li>
      <li><a href="attendance.php"><i class="fa-solid fa-calendar-check"></i> Attendance</a></li>
      <li><a href="holidays.php"><i class="fa-solid fa-calendar-xmark"></i> Holidays</a></li>
      <li><a href="sms-logs.php"><i class="fa-solid fa-comment-sms"></i> SMS Logs</a></li>
    </ul>
    <div class="admin-sidebar__stats">
      <div class="sidebar-stat">
        <strong><?= $totalCount ?></strong>
        <span>Total Students</span>
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

    <!-- ADD / EDIT FORM -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-<?= $editRow ? 'pen' : 'user-plus' ?>"></i>
          <?= $editRow ? 'Edit Student' : 'Register New Student' ?></h2>
      </div>

      <form method="POST" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>" />
        <?php if ($editRow): ?>
        <input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>" />
        <input type="hidden" name="photo_url_existing" value="<?= htmlspecialchars($editRow['photo_url'] ?? '') ?>" />
        <?php endif; ?>

        <!-- Student Info -->
        <p class="form-section-label"><i class="fa-solid fa-user"></i> Student Information</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Full Name *</label>
            <input type="text" name="student_name" placeholder="Full name"
                   value="<?= htmlspecialchars($editRow['student_name'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label>Phone Number *</label>
            <input type="tel" name="phone" placeholder="10-digit mobile"
                   value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($editRow['email'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth"
                   value="<?= htmlspecialchars($editRow['date_of_birth'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Gender</label>
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
            <label>Address</label>
            <textarea name="address" rows="2"><?= htmlspecialchars($editRow['address'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Enrollment -->
        <p class="form-section-label"><i class="fa-solid fa-book-open"></i> Enrollment Details</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Course *</label>
            <input type="text" name="course" placeholder="e.g. PGDCA, DCA, Python…"
                   value="<?= htmlspecialchars($editRow['course'] ?? '') ?>" required list="course-list" />
            <datalist id="course-list">
              <?php foreach (['PGDCA','DCA','Python Programming','Java Programming','HTML & CSS','SQL / Database','Tally Prime'] as $c): ?>
              <option value="<?= $c ?>">
              <?php endforeach; ?>
            </datalist>
          </div>

          <div class="form-group">
            <label>Batch / Class Timing *</label>
            <select name="batch_id" required>
              <option value="">— Select Batch —</option>
              <?php foreach ($allBatches as $b): ?>
              <option value="<?= $b['id'] ?>"
                <?= ($editRow['batch_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <span class="hint">
              Don't see your slot?
              <a href="batches.php" target="_blank">Add it in Batch Slots</a>
            </span>
          </div>

          <div class="form-group">
            <label>Enrollment Date</label>
            <input type="date" name="enrollment_date"
                   value="<?= htmlspecialchars($editRow['enrollment_date'] ?? date('Y-m-d')) ?>" />
          </div>

          <div class="form-group">
            <label>Biometric Device ID</label>
            <input type="text" name="biometric_id" placeholder="e.g. 00042"
                   value="<?= htmlspecialchars($editRow['biometric_id'] ?? '') ?>" />
            <span class="hint">The User ID stored in the biometric device</span>
          </div>
        </div>

        <!-- Parent Info -->
        <p class="form-section-label"><i class="fa-solid fa-users"></i> Parent / Guardian</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Name</label>
            <input type="text" name="parent_name"
                   value="<?= htmlspecialchars($editRow['parent_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Phone <small style="color:#64748b;">(WhatsApp/SMS alerts go here)</small></label>
            <input type="tel" name="parent_phone"
                   value="<?= htmlspecialchars($editRow['parent_phone'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="parent_email"
                   value="<?= htmlspecialchars($editRow['parent_email'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Relation</label>
            <input type="text" name="parent_relation" placeholder="Father / Mother / Guardian"
                   value="<?= htmlspecialchars($editRow['parent_relation'] ?? '') ?>" list="rel-list" />
            <datalist id="rel-list">
              <option value="Father"><option value="Mother"><option value="Guardian"><option value="Sibling">
            </datalist>
          </div>
        </div>

        <!-- Photo & Notes -->
        <p class="form-section-label"><i class="fa-solid fa-image"></i> Photo &amp; Notes</p>
        <div class="form-grid">
          <div class="form-group">
            <label>Student Photo</label>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" />
            <span class="hint">JPG / PNG / WEBP · max 2 MB</span>
          </div>
          <div class="form-group form-group--full">
            <label>Notes</label>
            <textarea name="notes" rows="2"><?= htmlspecialchars($editRow['notes'] ?? '') ?></textarea>
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

    <!-- STUDENT LIST -->
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
              <th>#</th><th>Photo</th><th>Name</th><th>Phone</th>
              <th>Course</th><th>Batch</th><th>Parent Phone</th>
              <th>Enrolled</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($students): foreach ($students as $i => $s): ?>
            <tr class="<?= $s['status'] !== 'active' ? 'row--inactive' : '' ?>">
              <td><?= $offset + $i + 1 ?></td>
              <td>
                <?php if ($s['photo_url']): ?>
                <img src="../<?= htmlspecialchars($s['photo_url']) ?>" class="avatar" alt="" />
                <?php else: ?>
                <div class="avatar-placeholder"><i class="fa-solid fa-user"></i></div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($s['student_name']) ?></td>
              <td><?= htmlspecialchars($s['phone']) ?></td>
              <td><?= htmlspecialchars($s['course']) ?></td>
              <td>
                <span class="batch-pill">
                  <?= $s['batch_name']
                      ? date('g:i', strtotime($s['start_time'])) . '–' . date('g:i A', strtotime($s['end_time']))
                      : '—' ?>
                </span>
              </td>
              <td><?= htmlspecialchars($s['parent_phone'] ?? '—') ?></td>
              <td><?= $s['enrollment_date'] ? date('d M Y', strtotime($s['enrollment_date'])) : '—' ?></td>
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
                <a href="attendance.php?student_id=<?= (int)$s['id'] ?>" class="btn-icon"
                   style="background:#e0f2fe;color:#0369a1;" title="View Attendance">
                  <i class="fa-solid fa-calendar-check"></i>
                </a>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Delete this student and ALL their data?');">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
                  <button type="submit" class="btn-icon btn-icon--delete" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="10" class="empty">No students found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
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
