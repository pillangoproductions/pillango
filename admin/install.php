<?php
/**
 * One-time installer: creates the first admin account.
 *
 * Only works while the users table is EMPTY — as soon as an account
 * exists, this page permanently refuses to run, so no default
 * password ever ships with the project.
 */

require_once __DIR__ . '/../includes/auth.php';

// Start the session before any output so the CSRF cookie is set on
// the first (GET) load — otherwise the token can't be validated on POST.
session_boot();

$userCount = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount > 0) {
    // Already installed — nothing to do here.
    redirect(BASE_URL . '/admin/login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username)) {
        $error = 'A felhasználónév 3–60 karakter lehet (betű, szám, pont, kötőjel).';
    } elseif (strlen($password) < 10) {
        $error = 'A jelszó legalább 10 karakter legyen.';
    } elseif ($password !== $confirm) {
        $error = 'A két jelszó nem egyezik.';
    } else {
        $stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (:u, :h)');
        $stmt->execute([
            ':u' => $username,
            ':h' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        flash_set('Az admin fiók elkészült — jelentkezz be.');
        redirect(BASE_URL . '/admin/login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Telepítés · Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500&display=swap">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/admin/admin.css">
</head>
<body>
<div class="login-box">
  <span class="adm-brand">Salone Medici <small>telepítés</small></span>
  <p>Üdv! Hozd létre az első admin fiókot — ez az oldal ezután automatikusan letiltódik.</p>
  <?php if ($error !== ''): ?><p class="err"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="">
    <?= csrf_field() ?>
    <p><label for="username">Felhasználónév</label>
       <input type="text" id="username" name="username" autocomplete="username" required autofocus></p>
    <p><label for="password">Jelszó (min. 10 karakter)</label>
       <input type="password" id="password" name="password" autocomplete="new-password" required></p>
    <p><label for="confirm">Jelszó még egyszer</label>
       <input type="password" id="confirm" name="confirm" autocomplete="new-password" required></p>
    <button class="btn red" type="submit">Fiók létrehozása</button>
  </form>
</div>
</body>
</html>
