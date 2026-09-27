<?php
/**
 * Admin login. Redirects to the dashboard when already logged in.
 * When no admin account exists yet, points to the one-time installer.
 */

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    redirect(BASE_URL . '/admin/index.php');
}

// First run: no users yet → send to the installer.
$userCount = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount === 0) {
    redirect(BASE_URL . '/admin/install.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Add meg a felhasználónevet és a jelszót.';
    } elseif (login_user($username, $password)) {
        redirect(BASE_URL . '/admin/index.php');
    } else {
        // Small fixed delay blunts brute-force attempts.
        usleep(400000);
        $error = 'Hibás felhasználónév vagy jelszó.';
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Belépés · <?= e(setting('venue_name', 'Salone Medici')) ?> admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500&display=swap">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/admin/admin.css">
</head>
<body>
<div class="login-box">
  <span class="adm-brand"><?= e(setting('venue_name', 'Salone Medici')) ?> <small>admin</small></span>
  <?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="">
    <?= csrf_field() ?>
    <p><label for="username">Felhasználónév</label>
       <input type="text" id="username" name="username" autocomplete="username" required autofocus></p>
    <p><label for="password">Jelszó</label>
       <input type="password" id="password" name="password" autocomplete="current-password" required></p>
    <button class="btn red" type="submit">Belépés</button>
  </form>
</div>
</body>
</html>
