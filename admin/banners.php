<?php
/* ================================================================
   NEx-gEN Admin — Banner Management
   URL: /admin/banners.php
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once '../config/db.php';

$msg = $msgType = '';

/* ── DELETE ─────────────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $s = $pdo->prepare('SELECT image_url FROM banners WHERE id=?'); $s->execute([$id]);
        $row = $s->fetch();
        if ($row && !empty($row['image_url']) && strpos($row['image_url'], 'http') !== 0) {
            $fp = dirname(__DIR__) . '/' . ltrim($row['image_url'], '/');
            if (file_exists($fp)) @unlink($fp);
        }
        $pdo->prepare('DELETE FROM banners WHERE id=?')->execute([$id]);
        $msg = 'Banner deleted.'; $msgType = 'success';
    }
}

/* ── TOGGLE ─────────────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) { $pdo->prepare('UPDATE banners SET is_active=1-is_active WHERE id=?')->execute([$id]); }
}

/* ── REORDER ────────────────────────────────────────────────────── */
if (isset($_POST['action']) && $_POST['action'] === 'reorder') {
    $id  = (int)($_POST['id']    ?? 0);
    $ord = (int)($_POST['order'] ?? 0);
    if ($id > 0) { $pdo->prepare('UPDATE banners SET sort_order=? WHERE id=?')->execute([$ord, $id]); }
    echo json_encode(['ok' => true]); exit;
}

/* ── ADD / EDIT ─────────────────────────────────────────────────── */
if (isset($_POST['action']) && in_array($_POST['action'], ['add','edit'])) {
    $editId   = (int)($_POST['edit_id']    ?? 0);
    $badge    = trim($_POST['badge_text']  ?? '');
    $title    = trim($_POST['title']       ?? '');
    $span     = trim($_POST['title_span']  ?? '');
    $sub      = trim($_POST['subtitle']    ?? '');
    $bgColor  = trim($_POST['bg_color']    ?? '#0f4e8a');
    $btn1t    = trim($_POST['btn1_text']   ?? 'Explore Courses');
    $btn1l    = trim($_POST['btn1_link']   ?? '#courses');
    $btn2t    = trim($_POST['btn2_text']   ?? '');
    $btn2l    = trim($_POST['btn2_link']   ?? '#contact');
    $from     = trim($_POST['display_from']  ?? '') ?: null;
    $until    = trim($_POST['display_until'] ?? '') ?: null;
    $sort     = (int)($_POST['sort_order'] ?? 0);
    $imgUrl   = trim($_POST['image_url_existing'] ?? '');

    if (!$title) { $msg = 'Title is required.'; $msgType = 'error'; }
    else {
        /* Handle file upload */
        if (!empty($_FILES['banner_image']['name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $file    = $_FILES['banner_image'];
            if (!in_array($file['type'], $allowed)) { $msg = 'Only JPG/PNG/WEBP/GIF allowed.'; $msgType = 'error'; }
            elseif ($file['size'] > 8*1024*1024) { $msg = 'Max file size is 8 MB.'; $msgType = 'error'; }
            else {
                $ext   = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fname = 'banner_' . time() . '_' . rand(100,999) . '.' . $ext;
                $dir   = dirname(__DIR__) . '/banners/images/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                    $imgUrl = 'banners/images/' . $fname;
                } else { $msg = 'Upload failed.'; $msgType = 'error'; }
            }
        }

        if ($msgType !== 'error') {
            if ($_POST['action'] === 'add') {
                $pdo->prepare(
                    'INSERT INTO banners (badge_text,title,title_span,subtitle,image_url,bg_color,btn1_text,btn1_link,btn2_text,btn2_link,display_from,display_until,sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$badge,$title,$span,$sub,$imgUrl,$bgColor,$btn1t,$btn1l,$btn2t?:null,$btn2l,$from,$until,$sort]);
                $msg = '✅ Banner added! It will appear in the hero carousel.'; $msgType = 'success';
            } else {
                $sets  = 'badge_text=?,title=?,title_span=?,subtitle=?,bg_color=?,btn1_text=?,btn1_link=?,btn2_text=?,btn2_link=?,display_from=?,display_until=?,sort_order=?';
                $vals  = [$badge,$title,$span,$sub,$bgColor,$btn1t,$btn1l,$btn2t?:null,$btn2l,$from,$until,$sort];
                if ($imgUrl) { $sets .= ',image_url=?'; $vals[] = $imgUrl; }
                $vals[] = $editId;
                $pdo->prepare("UPDATE banners SET $sets WHERE id=?")->execute($vals);
                $msg = 'Banner updated.'; $msgType = 'success';
            }
        }
    }
}

/* ── Fetch for edit ──────────────────────────────────────────────── */
$editRow = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM banners WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $editRow = $s->fetch();
}

/* ── All banners ──────────────────────────────────────────────────── */
$banners = $pdo->query('SELECT * FROM banners ORDER BY sort_order ASC, id ASC')->fetchAll();
$today   = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Banners | NEx-gEN Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
  <style>
    /* ── Banner-specific styles ── */
    .banner-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:20px; padding:20px; }
    .banner-card { border-radius:10px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.1); background:#fff; transition:.2s; }
    .banner-card:hover { transform:translateY(-3px); box-shadow:0 6px 24px rgba(0,0,0,.14); }
    .banner-card__preview {
      height:160px; position:relative; display:flex; align-items:center; justify-content:center;
      background:#0f4e8a; overflow:hidden;
    }
    .banner-card__preview img { width:100%; height:100%; object-fit:cover; }
    .banner-card__preview-text {
      position:absolute; inset:0; display:flex; flex-direction:column;
      align-items:center; justify-content:center; padding:16px; text-align:center;
      background:rgba(0,0,0,.42); color:#fff;
    }
    .banner-card__preview-text .badge-preview {
      font-size:.72rem; background:rgba(255,255,255,.2); border-radius:20px;
      padding:3px 12px; margin-bottom:8px; letter-spacing:.05em;
    }
    .banner-card__preview-text h3 { font-size:1.1rem; font-weight:700; line-height:1.3; }
    .banner-card__preview-text h3 span { color:#ffd369; }
    .banner-card__preview-text p  { font-size:.78rem; opacity:.85; margin-top:5px; }

    .banner-card__body { padding:14px 16px; }
    .banner-card__meta { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:10px; }
    .banner-card__meta .tag {
      font-size:.72rem; padding:3px 10px; border-radius:20px; font-weight:600;
    }
    .tag--active   { background:#d1fae5; color:#065f46; }
    .tag--inactive { background:#f0f2f5; color:#888; }
    .tag--dated    { background:#fef3c7; color:#92400e; }
    .tag--live     { background:#dbeafe; color:#1e40af; }

    .banner-card__dates { font-size:.75rem; color:#888; display:flex; gap:12px; margin-bottom:12px; }
    .banner-card__dates i { color:#1a6eb5; }

    .banner-card__actions { display:flex; gap:8px; align-items:center; }
    .banner-card__actions form { display:contents; }
    .banner-card__order { width:56px; padding:5px 8px; border:1.5px solid #e0e4ef; border-radius:6px; font-size:.8rem; text-align:center; }

    .color-swatch { width:18px; height:18px; border-radius:4px; border:1px solid #e0e4ef; display:inline-block; vertical-align:middle; margin-right:4px; }
    .empty-banners { text-align:center; padding:60px 20px; color:#aaa; }
    .empty-banners i { font-size:3rem; margin-bottom:14px; display:block; }

    .form-section { border:none; padding:0; }
    .form-section-title { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:#888; margin:20px 0 10px; padding-bottom:6px; border-bottom:1.5px solid #f0f2f5; }
    input[type="color"] { padding:2px; height:36px; width:60px; border:2px solid #e0e4ef; border-radius:6px; cursor:pointer; }
    .preview-box {
      border:2px dashed #e0e4ef; border-radius:10px; padding:24px;
      text-align:center; color:#888; font-size:.85rem;
      background:linear-gradient(135deg,#f8f9fc,#f0f5ff);
      margin-top:8px;
    }
    .preview-box strong { display:block; font-size:1.1rem; color:#0f4e8a; margin-bottom:4px; }
    .live-indicator { display:inline-flex; align-items:center; gap:5px; }
    .live-dot { width:8px; height:8px; border-radius:50%; background:#10b981; animation:pulse 1.5s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
  </style>
</head>
<body class="admin-page">

<!-- HEADER -->
<header class="admin-header">
  <div class="admin-header__left">
    <img src="../images/logo.png" alt="NEx-gEN" class="admin-logo" onerror="this.style.display='none'" />
    <span>Admin Panel</span>
  </div>
  <nav class="admin-header__nav">
    <a href="../index.html" target="_blank"><i class="fa-solid fa-globe"></i> View Site</a>
    <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </nav>
</header>

<div class="admin-body">

  <!-- SIDEBAR -->
  <aside class="admin-sidebar">
    <ul>
      <li><a href="index.php"><i class="fa-solid fa-certificate"></i> Certificates</a></li>
      <li class="active"><a href="banners.php"><i class="fa-solid fa-images"></i> Banners</a></li>
      <li><a href="banners.php?add=1"><i class="fa-solid fa-plus"></i> Add Banner</a></li>
      <li><a href="../index.html" target="_blank"><i class="fa-solid fa-eye"></i> Live Preview</a></li>
    </ul>
    <div class="admin-sidebar__stats">
      <div class="sidebar-stat">
        <strong><?= count($banners) ?></strong>
        <span>Total Banners</span>
      </div>
      <div class="sidebar-stat" style="margin-top:10px;">
        <strong style="color:#10b981;"><?= count(array_filter($banners, fn($b) => $b['is_active'])) ?></strong>
        <span>Active Now</span>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="admin-main">

    <?php if ($msg): ?>
    <div class="alert alert--<?= $msgType ?>">
      <i class="fa-solid fa-<?= $msgType==='success'?'circle-check':'circle-exclamation' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- ── ADD / EDIT FORM ── -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2>
          <i class="fa-solid fa-<?= $editRow ? 'pen' : 'plus' ?>"></i>
          <?= $editRow ? 'Edit Banner' : 'Add New Banner' ?>
        </h2>
        <?php if ($editRow): ?>
          <a href="banners.php" class="btn-secondary" style="font-size:.8rem;padding:6px 14px;">
            <i class="fa-solid fa-arrow-left"></i> Back to list
          </a>
        <?php endif; ?>
      </div>

      <form method="POST" enctype="multipart/form-data" class="admin-form" id="bannerForm">
        <input type="hidden" name="action"  value="<?= $editRow ? 'edit' : 'add' ?>" />
        <?php if ($editRow): ?>
        <input type="hidden" name="edit_id" value="<?= (int)$editRow['id'] ?>" />
        <input type="hidden" name="image_url_existing" value="<?= htmlspecialchars($editRow['image_url'] ?? '') ?>" />
        <?php endif; ?>

        <!-- PREVIEW BOX -->
        <div class="preview-box" id="livePreview">
          <?php if ($editRow && $editRow['badge_text']): ?>
            <span style="font-size:.72rem;background:rgba(255,255,255,.15);border-radius:20px;padding:2px 12px;display:inline-block;margin-bottom:6px;background:#e0e8f0;color:#555;">
              <?= htmlspecialchars($editRow['badge_text']) ?>
            </span><br/>
          <?php endif; ?>
          <strong id="previewTitle"><?= htmlspecialchars($editRow['title'] ?? 'Your Banner Title') ?>
            <?php if ($editRow && $editRow['title_span']): ?>
              <span style="color:#1a6eb5;"> <?= htmlspecialchars($editRow['title_span']) ?></span>
            <?php endif; ?></strong>
          <span id="previewSub" style="display:block;font-size:.82rem;margin-top:4px;">
            <?= htmlspecialchars($editRow['subtitle'] ?? 'Subtitle / tagline will appear here') ?>
          </span>
        </div>

        <div class="form-grid" style="margin-top:16px;">

          <!-- CONTENT -->
          <fieldset class="form-section form-group--full">
            <p class="form-section-title"><i class="fa-solid fa-heading"></i> Content</p>
            <div class="form-grid">
              <div class="form-group">
                <label>Badge / Event Tag
                  <span style="font-weight:400;color:#aaa;font-size:.75rem;">(optional, e.g. 🌸 Women's Day)</span>
                </label>
                <input type="text" name="badge_text" maxlength="80" placeholder="🌸 Women's Day Special"
                       value="<?= htmlspecialchars($editRow['badge_text'] ?? '') ?>"
                       oninput="document.getElementById('previewBadge').textContent=this.value" />
              </div>
              <div class="form-group">
                <label>Sort Order <span style="font-weight:400;color:#aaa;font-size:.75rem;">(0 = first)</span></label>
                <input type="number" name="sort_order" min="0" max="99" value="<?= (int)($editRow['sort_order'] ?? 0) ?>" />
              </div>
              <div class="form-group">
                <label>Main Title * <span style="font-weight:400;color:#aaa;font-size:.75rem;">(e.g. Happy Women's Day)</span></label>
                <input type="text" name="title" maxlength="120" required placeholder="e.g. Happy Women's Day"
                       value="<?= htmlspecialchars($editRow['title'] ?? '') ?>"
                       oninput="document.getElementById('previewTitle').childNodes[0].textContent=this.value+' '" />
              </div>
              <div class="form-group">
                <label>Highlighted Word/Phrase <span style="font-weight:400;color:#aaa;font-size:.75rem;">(shown in blue, optional)</span></label>
                <input type="text" name="title_span" maxlength="80" placeholder="e.g. Through Education"
                       value="<?= htmlspecialchars($editRow['title_span'] ?? '') ?>" />
                <span class="hint">Appears as coloured text after the main title</span>
              </div>
              <div class="form-group form-group--full">
                <label>Subtitle / Tagline</label>
                <input type="text" name="subtitle" maxlength="220" placeholder="e.g. Special discounts on all courses this Women's Day!"
                       value="<?= htmlspecialchars($editRow['subtitle'] ?? '') ?>"
                       oninput="document.getElementById('previewSub').textContent=this.value" />
              </div>
            </div>
          </fieldset>

          <!-- BACKGROUND -->
          <fieldset class="form-section form-group--full">
            <p class="form-section-title"><i class="fa-solid fa-image"></i> Background Image</p>
            <div class="form-grid">
              <div class="form-group form-group--full">
                <label>Upload Image <span style="font-weight:400;color:#aaa;">(JPG/PNG/WEBP, max 8 MB — replaces existing)</span></label>
                <input type="file" name="banner_image" accept="image/*" />
                <?php if ($editRow && $editRow['image_url']): ?>
                <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                  <img src="../<?= htmlspecialchars($editRow['image_url']) ?>"
                       style="height:60px;width:100px;object-fit:cover;border-radius:6px;border:1px solid #e0e4ef;" />
                  <span class="hint">Current image — upload a new one to replace</span>
                </div>
                <?php endif; ?>
              </div>
              <div class="form-group">
                <label>Fallback Background Colour <span style="font-weight:400;color:#aaa;">(shown if no image)</span></label>
                <div style="display:flex;align-items:center;gap:10px;">
                  <input type="color" name="bg_color" value="<?= htmlspecialchars($editRow['bg_color'] ?? '#0f4e8a') ?>" />
                  <span class="hint">Pick a colour that matches the event theme</span>
                </div>
              </div>
            </div>
          </fieldset>

          <!-- BUTTONS -->
          <fieldset class="form-section form-group--full">
            <p class="form-section-title"><i class="fa-solid fa-arrow-pointer"></i> Call-to-Action Buttons</p>
            <div class="form-grid">
              <div class="form-group">
                <label>Primary Button Text</label>
                <input type="text" name="btn1_text" placeholder="Explore Courses" maxlength="60"
                       value="<?= htmlspecialchars($editRow['btn1_text'] ?? 'Explore Courses') ?>" />
              </div>
              <div class="form-group">
                <label>Primary Button Link</label>
                <input type="text" name="btn1_link" placeholder="#courses"
                       value="<?= htmlspecialchars($editRow['btn1_link'] ?? '#courses') ?>" />
              </div>
              <div class="form-group">
                <label>Secondary Button Text <span style="font-weight:400;color:#aaa;">(optional)</span></label>
                <input type="text" name="btn2_text" placeholder="Contact Us" maxlength="60"
                       value="<?= htmlspecialchars($editRow['btn2_text'] ?? '') ?>" />
              </div>
              <div class="form-group">
                <label>Secondary Button Link</label>
                <input type="text" name="btn2_link" placeholder="#contact"
                       value="<?= htmlspecialchars($editRow['btn2_link'] ?? '#contact') ?>" />
              </div>
            </div>
          </fieldset>

          <!-- SCHEDULE -->
          <fieldset class="form-section form-group--full">
            <p class="form-section-title"><i class="fa-solid fa-calendar"></i> Schedule (Optional)</p>
            <div class="form-grid">
              <div class="form-group">
                <label>Show From Date</label>
                <input type="date" name="display_from" value="<?= htmlspecialchars($editRow['display_from'] ?? '') ?>" />
                <span class="hint">Leave empty to show always</span>
              </div>
              <div class="form-group">
                <label>Hide After Date</label>
                <input type="date" name="display_until" value="<?= htmlspecialchars($editRow['display_until'] ?? '') ?>" />
                <span class="hint">Leave empty to never auto-hide</span>
              </div>
            </div>
          </fieldset>

        </div><!-- /.form-grid -->

        <div class="form-actions">
          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-<?= $editRow ? 'floppy-disk' : 'plus' ?>"></i>
            <?= $editRow ? 'Update Banner' : 'Add Banner to Carousel' ?>
          </button>
          <?php if ($editRow): ?>
          <a href="banners.php" class="btn-secondary">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <!-- ── BANNERS LIST ── -->
    <section class="admin-card">
      <div class="admin-card__header">
        <h2><i class="fa-solid fa-images"></i> All Banners (<?= count($banners) ?>)</h2>
        <span style="font-size:.8rem;color:#888;">Drag sort-order number to reorder · Toggle to activate/deactivate</span>
      </div>

      <?php if (empty($banners)): ?>
      <div class="empty-banners">
        <i class="fa-solid fa-images"></i>
        <p>No banners yet. Add your first banner above — it will appear in the homepage carousel!</p>
      </div>
      <?php else: ?>

      <div class="banner-grid">
        <?php foreach ($banners as $b):
          $isLive   = $b['is_active']
                   && ($b['display_from']  === null || $b['display_from']  <= $today)
                   && ($b['display_until'] === null || $b['display_until'] >= $today);
          $hasDates = $b['display_from'] || $b['display_until'];
          $bgStyle  = $b['image_url'] ? '' : "background:{$b['bg_color']};";
        ?>
        <div class="banner-card">
          <!-- Preview -->
          <div class="banner-card__preview" style="<?= $bgStyle ?>">
            <?php if ($b['image_url']): ?>
              <?php $imgSrc = strpos($b['image_url'],'http')===0 ? $b['image_url'] : '../'.$b['image_url']; ?>
              <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" />
            <?php endif; ?>
            <div class="banner-card__preview-text">
              <?php if ($b['badge_text']): ?>
                <span class="badge-preview"><?= htmlspecialchars($b['badge_text']) ?></span>
              <?php endif; ?>
              <h3>
                <?= htmlspecialchars($b['title']) ?>
                <?php if ($b['title_span']): ?>
                  <span><?= htmlspecialchars($b['title_span']) ?></span>
                <?php endif; ?>
              </h3>
              <?php if ($b['subtitle']): ?>
                <p><?= htmlspecialchars($b['subtitle']) ?></p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Card body -->
          <div class="banner-card__body">
            <div class="banner-card__meta">
              <?php if ($isLive): ?>
                <span class="tag tag--live live-indicator"><span class="live-dot"></span> Live Now</span>
              <?php elseif ($b['is_active']): ?>
                <span class="tag tag--active">Active</span>
              <?php else: ?>
                <span class="tag tag--inactive">Inactive</span>
              <?php endif; ?>
              <?php if ($hasDates): ?>
                <span class="tag tag--dated"><i class="fa-solid fa-clock"></i> Scheduled</span>
              <?php endif; ?>
            </div>

            <?php if ($hasDates): ?>
            <div class="banner-card__dates">
              <?php if ($b['display_from']): ?>
                <span><i class="fa-solid fa-calendar-plus"></i> From <?= date('d M Y', strtotime($b['display_from'])) ?></span>
              <?php endif; ?>
              <?php if ($b['display_until']): ?>
                <span><i class="fa-solid fa-calendar-xmark"></i> Until <?= date('d M Y', strtotime($b['display_until'])) ?></span>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="banner-card__actions">
              <!-- Sort order input -->
              <input type="number" class="banner-card__order" value="<?= (int)$b['sort_order'] ?>"
                     min="0" max="99" title="Sort order (lower = first)"
                     onchange="updateOrder(<?= (int)$b['id'] ?>, this.value)" />

              <!-- Toggle active -->
              <form method="POST" style="display:contents;">
                <input type="hidden" name="action" value="toggle" />
                <input type="hidden" name="id"     value="<?= (int)$b['id'] ?>" />
                <button type="submit" class="badge <?= $b['is_active'] ? 'badge--green' : 'badge--grey' ?>"
                        title="Click to toggle">
                  <?= $b['is_active'] ? 'ON' : 'OFF' ?>
                </button>
              </form>

              <!-- Edit -->
              <a href="?edit=<?= (int)$b['id'] ?>" class="btn-icon btn-icon--edit" title="Edit">
                <i class="fa-solid fa-pen"></i>
              </a>

              <!-- Delete -->
              <form method="POST" style="display:contents;"
                    onsubmit="return confirm('Delete this banner permanently?');">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id"     value="<?= (int)$b['id'] ?>" />
                <button type="submit" class="btn-icon btn-icon--delete" title="Delete">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php endif; ?>
    </section>

  </main>
</div>

<script>
/* Update sort order via AJAX */
function updateOrder(id, order) {
  const fd = new FormData();
  fd.append('action', 'reorder');
  fd.append('id', id);
  fd.append('order', order);
  fetch('banners.php', { method: 'POST', body: fd })
    .then(() => {/* subtle feedback */});
}
</script>
</body>
</html>
