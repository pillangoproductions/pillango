<?php
/**
 * Admin page header. Expects:
 *   $pageTitle  — string, shown in <title> and as <h1>
 *   $current    — nav key: dashboard|packages|addons|events|series|gallery|enquiries|settings
 * Must be included after require_login().
 */

$current   = $current   ?? '';
$pageTitle = $pageTitle ?? 'Admin';
$flash     = flash_get();

/* An unread enquiry gets a count badge in the nav — the owner should
   never have to go looking for one. */
$newEnquiries = (int) db()->query("SELECT COUNT(*) FROM enquiries WHERE status = 'new'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> · <?= e(setting('venue_name', 'Salone Medici')) ?> admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500&display=swap">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/admin/admin.css?v=<?= e((string) @filemtime(BASE_PATH . '/admin/admin.css')) ?>">
</head>
<body>
<header class="adm-top">
  <a class="adm-brand" href="<?= e(BASE_URL) ?>/admin/index.php"><?= e(setting('venue_name', 'Salone Medici')) ?> <small>admin</small></a>
  <nav class="adm-nav">
    <a href="<?= e(BASE_URL) ?>/admin/index.php"     class="<?= $current === 'dashboard' ? 'current' : '' ?>">Vezérlőpult</a>
    <a href="<?= e(BASE_URL) ?>/admin/packages.php"  class="<?= $current === 'packages'  ? 'current' : '' ?>">Csomagok</a>
    <a href="<?= e(BASE_URL) ?>/admin/addons.php"    class="<?= $current === 'addons'    ? 'current' : '' ?>">Kiegészítők</a>
    <a href="<?= e(BASE_URL) ?>/admin/events.php"    class="<?= $current === 'events'    ? 'current' : '' ?>">Események</a>
    <a href="<?= e(BASE_URL) ?>/admin/series.php"    class="<?= $current === 'series'    ? 'current' : '' ?>">Sorozatok</a>
    <a href="<?= e(BASE_URL) ?>/admin/gallery.php"   class="<?= $current === 'gallery'   ? 'current' : '' ?>">Galéria</a>
    <a href="<?= e(BASE_URL) ?>/admin/enquiries.php" class="<?= $current === 'enquiries' ? 'current' : '' ?>">Ajánlatkérések<?php if ($newEnquiries > 0): ?> <span class="badge"><?= $newEnquiries ?></span><?php endif; ?></a>
    <a href="<?= e(BASE_URL) ?>/admin/settings.php"  class="<?= $current === 'settings'  ? 'current' : '' ?>">Beállítások</a>
    <a href="<?= e(BASE_URL) ?>/" target="_blank" rel="noopener">Weboldal ↗</a>
    <a href="<?= e(BASE_URL) ?>/admin/logout.php" class="logout">Kilépés</a>
  </nav>
</header>
<main class="adm-wrap">
<h1><?= e($pageTitle) ?></h1>
<?php if ($flash !== null): ?>
<div class="flash <?= $flash['type'] === 'err' ? 'err' : '' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
