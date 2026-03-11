<?php
/* ================================================================
   NEx-gEN Admin — Login Page
   URL: /admin/login.php
   ================================================================ */
session_start();
require_once '../config/db.php';   // loads DB_* and ADMIN_* constants

/* Already logged in? */
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    /* Simple brute-force throttle via session */
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
    if ($_SESSION['login_attempts'] >= 5) {
        $error = 'Too many failed attempts. Close the browser and try again.';
    } elseif ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['login_attempts']  = 0;
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['login_attempts']++;
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login | NEx-gEN</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="style.css" />
</head>
<body class="login-page">

<div class="login-wrap">
  <div class="login-card">
    <div class="login-card__logo">
      <img src="../images/logo.png" alt="NEx-gEN" onerror="this.style.display='none'" />
    </div>
    <h1>Admin Panel</h1>
    <p class="login-card__sub">NEx-gEN School of Computers</p>

    <?php if ($error): ?>
    <div class="alert alert--error">
      <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="login-form">
      <div class="form-group">
        <label for="username"><i class="fa-solid fa-user"></i> Username</label>
        <input type="text" id="username" name="username" placeholder="Admin username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus />
      </div>
      <div class="form-group">
        <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" placeholder="Password" required />
          <button type="button" class="pw-toggle" onclick="togglePw(this)" aria-label="Show password">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-primary"><i class="fa-solid fa-right-to-bracket"></i> Sign In</button>
    </form>

    <p class="login-card__back"><a href="../index.html"><i class="fa-solid fa-arrow-left"></i> Back to website</a></p>
  </div>
</div>

<script>
function togglePw(btn) {
  const inp = btn.previousElementSibling;
  const icon = btn.querySelector('i');
  if (inp.type === 'password') {
    inp.type = 'text';
    icon.className = 'fa-solid fa-eye-slash';
  } else {
    inp.type = 'password';
    icon.className = 'fa-solid fa-eye';
  }
}
</script>
</body>
</html>
