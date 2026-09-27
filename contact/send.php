<?php
// Contact form → e-mail. Settings in config.php (see config.sample.php).
declare(strict_types=1);

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function finish(bool $ok, string $error = ''): never {
    global $wantsJson;
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    } else {
        header('Location: /contact?' . ($ok ? 'sent=1' : 'error=' . rawurlencode($error)), true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Location: /contact', true, 303); exit; }

$cfgFile = __DIR__ . '/config.php';
$cfg = is_file($cfgFile) ? require $cfgFile : [];
$to = trim((string)($cfg['to'] ?? ''));
$from = trim((string)($cfg['from'] ?? 'website@pillangoprod.com'));
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) finish(false, 'not-configured');

// Bots fill the hidden field; pretend it worked.
if (trim((string)($_POST['website'] ?? '')) !== '') finish(true);

$line = fn(string $k, int $max) => mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', (string)($_POST[$k] ?? ''))), 0, $max);
$name = $line('name', 120);
$email = $line('email', 160);
$message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 5000);
$topics = [
    'general' => 'General enquiry', 'production' => 'Film production',
    'post-production' => 'Post-production / booking the stage', 'financing' => 'Post-production financing',
    'consulting' => 'Production consulting', 'partnership' => 'Partnerships',
    'projects' => 'Projects & screenings', 'blog' => 'Blog & press', 'book' => 'The Book',
    'privacy' => 'Privacy / GDPR request',
];
$topic = $topics[(string)($_POST['topic'] ?? '')] ?? $topics['general'];

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) finish(false, 'invalid');
if (empty($_POST['consent'])) finish(false, 'consent');

// At most 5 messages per hour from one address.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$log = sys_get_temp_dir() . '/pillango-contact-' . hash('sha256', $ip);
$now = time();
$hits = array_filter(array_map('intval', is_file($log) ? file($log, FILE_IGNORE_NEW_LINES) : []), fn($t) => $t > $now - 3600);
if (count($hits) >= 5) finish(false, 'too-many');
$hits[] = $now;
file_put_contents($log, implode("\n", $hits), LOCK_EX);

$subject = '=?UTF-8?B?' . base64_encode("[pillangoprod.com] $topic — $name") . '?=';
$body = "Topic: $topic\nName: $name\nE-mail: $email\n\n$message\n\n-- \nSent from the contact form on pillangoprod.com\n";
$headers = [
    'From' => "Pillango website <$from>",
    'Reply-To' => $email,
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => '8bit',
];
finish(mail($to, $subject, $body, $headers, '-f' . $from), 'failed');
