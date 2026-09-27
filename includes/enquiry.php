<?php
/**
 * Ajánlatkérés — the public enquiry form's server side.
 *
 * The public site deliberately sets no cookies, so this form is not
 * protected by a session CSRF token. Instead every render carries an
 * HMAC-signed timestamp: a bot that posts the form instantly (or
 * replays a stale one) is rejected, and a per-IP hourly cap limits
 * the rest. Submissions are always stored; the e-mail is best effort.
 */

require_once __DIR__ . '/site-render.php';

/** Seconds a human needs, at minimum, to fill the form in. */
const ENQUIRY_MIN_SECONDS = 3;
/** How long a rendered form stays valid. */
const ENQUIRY_MAX_SECONDS = 6 * 3600;

/**
 * Hidden fields that stamp and sign the moment the form was rendered.
 */
function enquiry_stamp_fields(): string
{
    $ts  = time();
    $sig = hash_hmac('sha256', (string) $ts, FORM_SECRET);
    return '<input type="hidden" name="ts" value="' . e((string) $ts) . '">'
        . '<input type="hidden" name="sig" value="' . e($sig) . '">'
        // Honeypot: invisible to people, irresistible to bots.
        . '<div class="hp" aria-hidden="true"><label>Weboldal<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
}

/**
 * Is the posted stamp genuine and plausibly human-paced?
 */
function enquiry_stamp_ok(array $post): bool
{
    $ts  = (int) ($post['ts'] ?? 0);
    $sig = (string) ($post['sig'] ?? '');
    if ($ts <= 0 || $sig === '') {
        return false;
    }
    if (!hash_equals(hash_hmac('sha256', (string) $ts, FORM_SECRET), $sig)) {
        return false;
    }
    $age = time() - $ts;
    return $age >= ENQUIRY_MIN_SECONDS && $age <= ENQUIRY_MAX_SECONDS;
}

/**
 * The visitor's IP in packed form, for the rate-limit counter.
 * NULL when it cannot be read (then the cap simply does not apply).
 */
function enquiry_client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $packed = $ip !== '' ? @inet_pton($ip) : false;
    return $packed === false ? null : $packed;
}

/**
 * Has this IP already sent its hourly allowance?
 */
function enquiry_rate_exceeded(?string $ip): bool
{
    if ($ip === null) {
        return false;
    }
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM enquiries
          WHERE ip = :ip AND created_at > (NOW() - INTERVAL 1 HOUR)'
    );
    $stmt->execute([':ip' => $ip]);
    return (int) $stmt->fetchColumn() >= ENQUIRY_RATE_PER_HOUR;
}

/**
 * Validate and store one submission.
 *
 * @return array{ok:bool, error?:string, id?:int}
 */
function enquiry_handle(array $post): array
{
    // A filled honeypot is a bot — answer as if all went well, so it
    // has nothing to learn, but store nothing.
    if (trim((string) ($post['website'] ?? '')) !== '') {
        return ['ok' => true, 'id' => 0];
    }
    if (!enquiry_stamp_ok($post)) {
        return ['ok' => false, 'error' => 'Az űrlap lejárt vagy túl gyorsan érkezett. Frissítsd az oldalt, és próbáld újra.'];
    }

    $name    = trim((string) ($post['name'] ?? ''));
    $email   = trim((string) ($post['email'] ?? ''));
    $phone   = trim((string) ($post['phone'] ?? ''));
    $company = trim((string) ($post['company'] ?? ''));
    $message = trim((string) ($post['message'] ?? ''));
    $slug    = trim((string) ($post['package'] ?? ''));
    $dateRaw = trim((string) ($post['event_date'] ?? ''));
    $guests  = (int) ($post['guests'] ?? 0);

    if ($name === '' || mb_strlen($name) > 120) {
        return ['ok' => false, 'error' => 'Kérjük, add meg a nevedet.'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        return ['ok' => false, 'error' => 'Kérjük, adj meg egy érvényes e-mail címet.'];
    }
    if (mb_strlen($message) > 4000) {
        return ['ok' => false, 'error' => 'Az üzenet túl hosszú.'];
    }

    // A date must be a real date, and not in the past.
    $eventDate = null;
    if ($dateRaw !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $dateRaw);
        if ($d === false || $d->format('Y-m-d') !== $dateRaw) {
            return ['ok' => false, 'error' => 'Az időpont formátuma érvénytelen.'];
        }
        if ($dateRaw < date('Y-m-d')) {
            return ['ok' => false, 'error' => 'A megadott időpont már elmúlt.'];
        }
        $eventDate = $dateRaw;
    }

    $ip = enquiry_client_ip();
    if (enquiry_rate_exceeded($ip)) {
        return ['ok' => false, 'error' => 'Túl sok kérés érkezett rövid idő alatt. Kérjük, próbáld újra később, vagy hívj minket.'];
    }

    // Resolve the chosen package by slug — never trust a posted id.
    $packageId = null;
    if ($slug !== '') {
        $stmt = db()->prepare('SELECT id FROM packages WHERE slug = :s AND is_active = 1 LIMIT 1');
        $stmt->execute([':s' => $slug]);
        $found = $stmt->fetchColumn();
        if ($found !== false) {
            $packageId = (int) $found;
        }
    }

    $stmt = db()->prepare(
        'INSERT INTO enquiries (name, email, phone, company, package_id, event_date, guests, message, ip)
         VALUES (:n, :e, :p, :c, :pkg, :d, :g, :m, :ip)'
    );
    $stmt->execute([
        ':n'   => $name,
        ':e'   => $email,
        ':p'   => $phone !== '' ? mb_substr($phone, 0, 60) : null,
        ':c'   => $company !== '' ? mb_substr($company, 0, 160) : null,
        ':pkg' => $packageId,
        ':d'   => $eventDate,
        ':g'   => $guests > 0 ? min($guests, 2000) : null,
        ':m'   => $message !== '' ? $message : null,
        ':ip'  => $ip,
    ]);
    $id = (int) db()->lastInsertId();

    enquiry_notify($id);

    return ['ok' => true, 'id' => $id];
}

/**
 * Best-effort e-mail notification to the venue. A mail server that is
 * unavailable must never lose the enquiry — it is already in the
 * database, and admin → Ajánlatkérések lists it either way.
 */
function enquiry_notify(int $id): void
{
    $to = trim((string) setting('enquiry_recipient', ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) {
        return;
    }

    $stmt = db()->prepare(
        'SELECT e.*, p.name_hu AS package_name
           FROM enquiries e LEFT JOIN packages p ON p.id = e.package_id
          WHERE e.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }

    $venue = (string) setting('venue_name', 'Salone Medici');
    $lines = [
        'Új ajánlatkérés érkezett a weboldalról.',
        '',
        'Név:        ' . $row['name'],
        'E-mail:     ' . $row['email'],
        'Telefon:    ' . ($row['phone'] ?? '—'),
        'Cég:        ' . ($row['company'] ?? '—'),
        'Csomag:     ' . ($row['package_name'] ?? '—'),
        'Időpont:    ' . ($row['event_date'] ?? '—'),
        'Létszám:    ' . ($row['guests'] ?? '—'),
        '',
        'Üzenet:',
        (string) ($row['message'] ?? '—'),
        '',
        '— ' . $venue . ' · ' . date('Y-m-d H:i'),
    ];

    // The From must be a domain the server may send for; the visitor's
    // address goes in Reply-To so a reply reaches them directly.
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $from = 'noreply@' . preg_replace('/^www\./', '', preg_replace('/[^A-Za-z0-9.\-]/', '', $host));

    @mail(
        $to,
        '=?UTF-8?B?' . base64_encode('Ajánlatkérés — ' . $venue) . '?=',
        implode("\n", $lines),
        implode("\r\n", [
            'From: =?UTF-8?B?' . base64_encode($venue) . '?= <' . $from . '>',
            'Reply-To: ' . str_replace(["\r", "\n"], '', $row['email']),
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: salone-medici-site',
        ])
    );
}
