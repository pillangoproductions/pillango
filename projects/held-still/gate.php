<?php
/**
 * Held Still — the password gate.
 *
 * POST ?action=login   (from the password box on /projects) → JSON
 *                      {ok:true,url} or {ok:false,error:wrong|too-many|not-configured}
 * GET  ?action=logout  → ends the session, back to /projects
 * GET  ?f=<path>       → serves ./site/<path> to signed-in visitors only;
 *                        everyone else is sent to /projects#held-still,
 *                        where the password box opens.
 *
 * .htaccess routes every request in this folder through here, so the
 * pages, images and videos of the Held Still site are all protected.
 */
declare(strict_types=1);

const SITE_DIR   = __DIR__ . '/site';
const MAX_FAILS  = 8;      // wrong passwords per IP…
const FAIL_TTL   = 600;    // …within this many seconds

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('pillango_hs');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/projects/held-still',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$action = $_GET['action'] ?? '';

/* ---------- sign in ---------- */
if ($action === 'login') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit(json_encode(['ok' => false, 'error' => 'method']));
    }
    $cfgFile = __DIR__ . '/config.php';
    $cfg = is_file($cfgFile) ? require $cfgFile : null;
    if (!is_array($cfg) || empty($cfg['password_hash'])) {
        http_response_code(503);
        exit(json_encode(['ok' => false, 'error' => 'not-configured']));
    }

    // Per-IP throttle, kept in the system temp folder.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $throttleFile = sys_get_temp_dir() . '/pillango_hs_' . hash('sha256', $ip) . '.json';
    $now = time();
    $fails = [];
    if (is_file($throttleFile)) {
        $fails = json_decode((string) file_get_contents($throttleFile), true) ?: [];
    }
    $fails = array_values(array_filter($fails, fn($t) => $t > $now - FAIL_TTL));
    if (count($fails) >= MAX_FAILS) {
        http_response_code(429);
        exit(json_encode(['ok' => false, 'error' => 'too-many']));
    }

    $pw = (string) ($_POST['password'] ?? '');
    if ($pw !== '' && password_verify($pw, $cfg['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['held_still'] = true;
        @unlink($throttleFile);
        exit(json_encode(['ok' => true, 'url' => $cfg['redirect'] ?? '/projects/held-still/']));
    }

    $fails[] = $now;
    @file_put_contents($throttleFile, json_encode($fails), LOCK_EX);
    usleep(600000);   // slow down guessing
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'wrong']));
}

/* ---------- sign out ---------- */
if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', ['expires' => 1, 'path' => '/projects/held-still']);
    header('Location: /projects', true, 303);
    exit;
}

/* ---------- serve the protected site ---------- */
if (empty($_SESSION['held_still'])) {
    header('Location: /projects#held-still', true, 303);
    exit;
}

$rel = str_replace('\\', '/', (string) ($_GET['f'] ?? ''));
$rel = ltrim($rel, '/');
if ($rel === '' || substr($rel, -1) === '/') {
    $rel .= 'index.html';
}
$base = realpath(SITE_DIR);
$path = realpath(SITE_DIR . '/' . $rel);
if ($path !== false && is_dir($path)) {
    $path = realpath($path . '/index.html');
}
if ($base === false || $path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found');
}

$types = [
    'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
    'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'avif' => 'image/avif', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2', 'woff' => 'font/woff',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'm4v' => 'video/mp4',
    'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'vtt' => 'text/vtt',
    'pdf' => 'application/pdf', 'txt' => 'text/plain; charset=utf-8',
];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Accept-Ranges: bytes');

// Byte ranges, so video can stream and seek (Safari needs this).
$size = filesize($path);
$start = 0;
$end = $size - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
    if ($m[1] === '' && $m[2] !== '') {            // last N bytes
        $start = max(0, $size - (int) $m[2]);
    } else {
        $start = (int) $m[1];
        if ($m[2] !== '') {
            $end = min((int) $m[2], $size - 1);
        }
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header("Content-Range: bytes */$size");
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
$length = $end - $start + 1;
header('Content-Length: ' . $length);
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}
$fh = fopen($path, 'rb');
fseek($fh, $start);
$left = $length;
while ($left > 0 && !feof($fh)) {
    $chunk = fread($fh, (int) min(1 << 16, $left));
    echo $chunk;
    $left -= strlen($chunk);
    flush();
}
fclose($fh);
