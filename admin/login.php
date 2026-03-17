<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/config/env.php';

startSession();

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $adminUser = $_ENV['ADMIN_USERNAME']      ?? 'admin';
    $adminHash = $_ENV['ADMIN_PASSWORD_HASH'] ?? '';

    if (
        $username !== '' &&
        $password !== '' &&
        $username === $adminUser &&
        password_verify($password, $adminHash)
    ) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Admin — AMANAH Digital</title>
  <link rel="icon" type="image/png" href="../assets/logo-ia-ia-copy.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #e0f7fa 0%, #4dd0e1 60%, #0288d1 100%);
    }
    .card {
      background: #fff;
      border-radius: 16px;
      padding: 2.5rem 2.25rem;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 16px 48px rgba(0,0,0,0.14);
    }
    .logo-wrap { text-align: center; margin-bottom: 1.25rem; }
    .logo-wrap img { width: 76px; height: 76px; object-fit: contain; border-radius: 12px; }
    h2 { text-align: center; font-size: 1.35rem; font-weight: 700; color: #1a1a2e; margin-bottom: 0.2rem; }
    .subtitle { text-align: center; font-size: 0.78rem; color: #6b7280; margin-bottom: 2rem; }
    .form-group { margin-bottom: 1.15rem; }
    label { display: block; font-size: 0.83rem; font-weight: 500; color: #374151; margin-bottom: 0.35rem; }
    input[type="text"], input[type="password"] {
      width: 100%; padding: 0.72rem 1rem;
      border: 1px solid #d1d5db; border-radius: 8px;
      font-size: 0.95rem; font-family: inherit;
      outline: none; transition: border-color 0.2s, box-shadow 0.2s;
      color: #1a202c;
    }
    input:focus { border-color: #00acc1; box-shadow: 0 0 0 3px rgba(0,172,193,0.18); }
    .btn-submit {
      width: 100%; padding: 0.82rem;
      background: linear-gradient(135deg, #00acc1, #0288d1);
      color: #fff; border: none; border-radius: 8px;
      font-size: 1rem; font-weight: 600; cursor: pointer;
      transition: filter 0.2s, transform 0.1s;
      margin-top: 0.5rem;
    }
    .btn-submit:hover { filter: brightness(1.08); transform: translateY(-1px); }
    .btn-submit:active { transform: translateY(0); }
  </style>
</head>
<body>
  <div class="card">
    <div class="logo-wrap">
      <img src="../assets/logo-ia-ia-copy.png" alt="Logo Ihsanul Amal">
    </div>
    <h2>AMANAH Digital</h2>
    <p class="subtitle">Dashboard Admin — masuk untuk melanjutkan</p>

    <form method="POST" action="">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES) ?>"
               placeholder="Masukkan username">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password"
               placeholder="Masukkan password">
      </div>
      <button type="submit" class="btn-submit">Masuk</button>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php if ($error !== ''): ?>
  <script>
    Swal.fire({
      icon: 'error',
      title: 'Gagal Masuk',
      text: <?= json_encode($error) ?>,
      confirmButtonColor: '#00acc1'
    });
  </script>
  <?php endif; ?>
</body>
</html>
