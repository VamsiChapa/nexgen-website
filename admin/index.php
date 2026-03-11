<?php
/* ================================================================
   NEx-gEN Admin — Certificate Management Dashboard
   URL: /admin/index.php
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once '../config/db.php';

$msg     = '';
$msgType = '';

/* ── Handle DELETE ──────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        // Fetch image path first so we can delete the file
        $s = $pdo->prepare('SELECT certificate_url FROM certificates WHERE id = ?');
        $s->execute([$id]);
        $row = $s->fetch();
        if ($row && !empty($row['certificate_url']) && strpos($row['certificate_url'], 'http') !== 0) {
            $filePath = dirname(__DIR__) . '/' . ltrim($row['certificate_url'], '/');
            if (file_exists($filePath)) @unlink($filePath);
        }
        $pdo->prepare('DELETE FROM certificates WHERE id = ?')->execute([$id]);
        $msg     = 'Certificate deleted successfully.';
        $msgType = 'success';
    }
}

/* ── Handle TOGGLE ACTIVE ───────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('UPDATE certificates SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        $msg     = 'Certificate status updated.';
        $msgType = 'success';
    }
}

/* ── Handle ADD / EDIT ──────────────────────────────────────────── */
if (isset($_POST['action']) && in_array($_POST['action'], ['add', 'edit'])) {
    $editId      = (int)($_POST['edit_id'] ?? 0);
    $certNo      = trim($_POST['cert_number']  ?? '');
    $name        = trim($_POST['student_name'] ?? '');
    $course      = trim($_POST['course_name']  ?? '');
    $issueDate   = trim($_POST['issue_date']   ?? '') ?: null;
    $certUrlInput= trim($_POST['cert_url']     ?? '');

    if ($certNo && $name) {
        /* Handle file upload */
        $certUrl = $certUrlInput ?: null;
        if (!empty($_FILES['cert_image']['name'])) {
            $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize   = 5 * 1024 * 1024; // 5 MB
            $file      = $_FILES['cert_image'];
            if (!in_array($file['type'], $allowed)) {
                $msg = 'Only JPG, PNG, WEBP, GIF images allowed.';
                $msgType = 'error';
            } elseif ($file['size'] > $maxSize) {
                $msg = 'Image must be under 5 MB.';
                $msgType = 'error';
            } else {
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $certNo) . '_' . time() . '.' . $ext;
                $destDir  = dirname(__DIR__) . '/certificates/images/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $destPath = $destDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $certUrl = 'certificates/images/' . $filename;
                } else {
                    $msg = 'File upload failed.';
                    $msgType = 'error';
                }
            }
        }

        if ($msgType !== 'error') {
            if ($_POST['action'] === 'add') {
                try {
                    $s = $pdo->prepare(
                        'INSERT INTO certificates (certificate_number, student_name, course_name, issue_date, certificate_url)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $s->execute([$certNo, $name, $course ?: null, $issueDate, $certUrl]);
                    $msg     = 'Certificate added successfully! Certificate No: ' . htmlspecialchars($certNo);
                    $msgType = 'success';
                } catch (PDOException $e) {
                    $msg     = 'Error: Certificate number already exists or database error.';
                    $msgType = 'error';
                }
            } else {
                try {
                    $sets  = 'certificate_number=?, student_name=?, course_name=?, issue_date=?';
                    $vals  = [$certNo, $name, $course ?: null, $issueDate];
                    if ($certUrl !== null) { $sets .= ', certificate_url=?'; $vals[] = $certUrl; }
                    $vals[] = $editId;
                    $pdo->prepare("UPDATE certificates SET $sets WHERE id=?")->execute($vals);
                    $msg     = 'Certificate updated successfully.';
                    $msgType = 'success';
                } catch (PDOException $e) {
                    $msg     = 'Update failed: Certificate number may already exist.';
                    $msgType = 'error';
                }
            }
        }
    } else {
        $msg     = 'Certificate number and student name are required.';
        $msgType = 'error';
    }
}

/* ── Fetch for edit form ─────────────────────────────────────────── */
$editRow = null;
if (isset($_GET['edit'])) {
    $editRow = $pdo->prepare('SELECT * FROM certificates WHERE id=?');
    $editRow->execute([(int)$_GET['edit']]);
    $editRow = $editRow->fetch();
}

/* ── Paginated list ─────────────────────────────────────────────── */
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

if ($search) {
    $total = $pdo->prepare('SELECT COUNT(*) FROM certificates WHERE certificate_number LIKE ? OR student_name LIKE ?');
    $total->execute(["%$search%", "%$search%"]);
    $rows  = $pdo->prepare('SELECT * FROM certificates WHERE certificate_number LIKE ? OR student_name LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $rows->execute(["%$search%", "%$search%", $perPage, $offset]);
} else {
    $total = $pdo->query('SELECT COUNT(*) FROM certificates');
    $rows  = $pdo->prepare('SELECT * FROM certificates ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $rows->execute([$perPage, $offset]);
}
$totalCount = $total->fetchColumn();
$certs      = $rows->fetchAll();
$totalPages = (int)ceil($totalCount / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin — Certificate Management | NEx-gEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="admin-page">

<!-- ── TOP BAR ── -->
<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>Certificate Admin</span>
  </div>
  <nav class="admin-header__nav">
    <a href="../index.html" target="_blank"><i class="fa-solid fa-globe"></i> View Site</a>
    <a href="../certificates.html" target="_blank"><i class="fa-solid fa-certificate"></i> Cert Page</a>
    <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </nav>
</header>

<div class="admin-body">

  <!-- ── SIDEBAR ── -->
  <aside class="admin-sidebar">
    <ul>
      <li class="active"><a href="index.php"><i class="fa-solid fa-certificate"></i> Certificates</a></li>
      <li><a href="index.php?view=add"><i class="fa-solid fa-plus"></i> Add Certificate</a></li>
      <li><a href="../certificates.html" target="_blank"><i class="fa-solid fa-magnifying-glass"></i> Verify Page</a></li>
      <li><a href="banners.php"><i class="fa-solid fa-images"></i> Banners</a></li>
      <li><a href="banners.php?add=1"><i class="fa-solid fa-image"></i> Add Banner</a></li>
    </ul>
    <div class="admin-sidebar__stats">
      <div class="sidebar-stat">
        <strong><?= $totalCount ?></strong>
        <span>Total Certificates</span>
      </div>
    </div>
  </aside>

  <!-- ── MAIN ── -->
  <main class="admin-main">

    <?php if ($msg): ?>
    <div class="alert alert--<?= $msgType ?>">
      <i class="fa-solid fa-<?= $msgType === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- ── ADD / EDIT FORM ── -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-<?= $editRow ? 'pen' : 'plus' ?>"></i>
          <?= $editRow ? 'Edit Certificate' : 'Add New Certificate' ?></h2>
      </div>
      <form method="POST" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="action" value="<?= $editRow ? 'edit' : 'add' ?>" />
        <?php if ($editRow): ?>
        <input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>" />
        <?php endif; ?>

        <div class="form-grid">
          <div class="form-group">
            <label>Certificate Number *</label>
            <input type="text" name="cert_number" placeholder="e.g. NGN-2024-0001"
                   value="<?= htmlspecialchars($editRow['certificate_number'] ?? $_POST['cert_number'] ?? '') ?>" required />
            <span class="hint">Must be unique. Use format NGN-YEAR-NNNN</span>
          </div>
          <div class="form-group">
            <label>Student Full Name *</label>
            <input type="text" name="student_name" placeholder="Full name as on certificate"
                   value="<?= htmlspecialchars($editRow['student_name'] ?? $_POST['student_name'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label>Course Name</label>
            <input type="text" name="course_name" placeholder="e.g. PGDCA"
                   value="<?= htmlspecialchars($editRow['course_name'] ?? $_POST['course_name'] ?? '') ?>" />
          </div>
          <div class="form-group">
            <label>Issue Date</label>
            <input type="date" name="issue_date"
                   value="<?= htmlspecialchars($editRow['issue_date'] ?? $_POST['issue_date'] ?? '') ?>" />
          </div>
          <div class="form-group form-group--full">
            <label>Certificate Image — Upload File</label>
            <input type="file" name="cert_image" accept="image/*" />
            <span class="hint">JPG / PNG / WEBP, max 5 MB. <?= $editRow && $editRow['certificate_url'] ? 'Leave empty to keep existing image.' : '' ?></span>
          </div>
          <div class="form-group form-group--full">
            <label>— OR — External Image URL</label>
            <input type="url" name="cert_url" placeholder="https://…"
                   value="<?= htmlspecialchars($editRow['certificate_url'] ?? $_POST['cert_url'] ?? '') ?>" />
            <span class="hint">Paste a full URL if the image is hosted externally (Google Drive link, etc.)</span>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-<?= $editRow ? 'floppy-disk' : 'plus' ?>"></i>
            <?= $editRow ? 'Update Certificate' : 'Add Certificate' ?>
          </button>
          <?php if ($editRow): ?>
          <a href="index.php" class="btn-secondary">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <!-- ── CERTIFICATES LIST ── -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-list"></i> All Certificates (<?= $totalCount ?>)</h2>
        <form method="GET" class="search-bar">
          <input type="text" name="q" placeholder="Search cert no. or name…" value="<?= htmlspecialchars($search) ?>" />
          <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
          <?php if ($search): ?><a href="index.php" class="clear-search"><i class="fa-solid fa-xmark"></i></a><?php endif; ?>
        </form>
      </div>

      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Certificate No.</th>
              <th>Student Name</th>
              <th>Course</th>
              <th>Issue Date</th>
              <th>Image</th>
              <th>Active</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($certs): foreach ($certs as $i => $c): ?>
            <tr class="<?= $c['is_active'] ? '' : 'row--inactive' ?>">
              <td><?= $offset + $i + 1 ?></td>
              <td><code><?= htmlspecialchars($c['certificate_number']) ?></code></td>
              <td><?= htmlspecialchars($c['student_name']) ?></td>
              <td><?= htmlspecialchars($c['course_name'] ?? '—') ?></td>
              <td><?= $c['issue_date'] ? date('d M Y', strtotime($c['issue_date'])) : '—' ?></td>
              <td>
                <?php if ($c['certificate_url']): ?>
                  <a href="../<?= htmlspecialchars($c['certificate_url']) ?>" target="_blank" class="img-preview-link">
                    <i class="fa-solid fa-image"></i> View
                  </a>
                <?php else: ?><span class="no-img">—</span><?php endif; ?>
              </td>
              <td>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="toggle" />
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                  <button type="submit" class="badge badge--<?= $c['is_active'] ? 'green' : 'grey' ?>">
                    <?= $c['is_active'] ? 'Active' : 'Inactive' ?>
                  </button>
                </form>
              </td>
              <td class="td-actions">
                <a href="?edit=<?= (int)$c['id'] ?>" class="btn-icon btn-icon--edit" title="Edit">
                  <i class="fa-solid fa-pen"></i>
                </a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this certificate?');">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
                  <button type="submit" class="btn-icon btn-icon--delete" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" class="empty">No certificates found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </section>

  </main>
</div><!-- /.admin-body -->
</body>
</html>
