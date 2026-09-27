<?php
/**
 * Shared helpers: escaping, formatting, settings access, uploads.
 * Used by both the public site and the admin panel.
 * Included by both the public site and the admin panel.
 */

require_once __DIR__ . '/database.php';

/**
 * HTML-escape a value for safe output. Alias used everywhere.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a HUF price the way the design does: 1690 → "1.690 Ft".
 * Returns '' for NULL so empty price cells render as designed.
 */
function format_price(?int $huf): string
{
    if ($huf === null) {
        return '';
    }
    return number_format($huf, 0, ',', '.') . ' Ft';
}

/**
 * Read one setting value (cached per request). NULL when missing.
 */
function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

/**
 * Write one setting value (insert or update).
 */
function set_setting(string $key, string $value): void
{
    $sql = 'INSERT INTO site_settings (setting_key, setting_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
    $stmt = db()->prepare($sql);
    $stmt->execute([':k' => $key, ':v' => $value]);
}

/**
 * Validate and store an uploaded image.
 *
 * @param array  $file      One entry of $_FILES.
 * @param string $targetDir Absolute directory (must exist, writable).
 * @return array{ok:bool, error?:string, filename?:string}
 */
function handle_image_upload(array $file, string $targetDir): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Érvénytelen feltöltés.'];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Nem érkezett fájl.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Feltöltési hiba (kód: ' . (int) $file['error'] . ').'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'A fájl nagyobb 5 MB-nál.'];
    }

    // Determine the real type from file content, never from the extension.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $extByMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extByMime[$mime])) {
        return ['ok' => false, 'error' => 'Csak JPG, PNG vagy WEBP tölthető fel.'];
    }

    // Random, collision-free name; never reuse the client's filename.
    $filename = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $extByMime[$mime];
    $target   = rtrim($targetDir, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'A fájl mentése nem sikerült (jogosultság?).'];
    }
    @chmod($target, 0644);

    make_thumbnail($target, rtrim($targetDir, '/') . '/thumbs/' . $filename, $mime);

    return ['ok' => true, 'filename' => $filename];
}

/**
 * Generate a width-limited thumbnail with GD. Silently skips when GD
 * is unavailable — the full-size image is used as a fallback then.
 */
function make_thumbnail(string $srcPath, string $destPath, string $mime): void
{
    if (!function_exists('imagecreatetruecolor')) {
        return;
    }

    // The thumbs/ folder can go missing on a fresh upload of the project.
    $destDir = dirname($destPath);
    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return;
    }

    $create = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ][$mime] ?? null;
    if ($create === null || !function_exists($create)) {
        return;
    }

    $src = @$create($srcPath);
    if (!$src) {
        return;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w <= THUMB_MAX_WIDTH) {
        // Already small — copy as-is.
        @copy($srcPath, $destPath);
        imagedestroy($src);
        return;
    }

    $nw = THUMB_MAX_WIDTH;
    $nh = (int) round($h * ($nw / $w));
    $thumb = imagecreatetruecolor($nw, $nh);

    // Keep PNG/WEBP transparency.
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);

    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($thumb, $destPath, 82);
            break;
        case 'image/png':
            imagepng($thumb, $destPath, 6);
            break;
        case 'image/webp':
            imagewebp($thumb, $destPath, 82);
            break;
    }

    imagedestroy($thumb);
    imagedestroy($src);
}

/**
 * Delete an uploaded image and its thumbnail. Only ever acts inside
 * the given uploads directory — path traversal is rejected.
 */
function delete_upload(string $dir, ?string $filename): void
{
    if ($filename === null || $filename === '' || basename($filename) !== $filename) {
        return;
    }
    @unlink(rtrim($dir, '/') . '/' . $filename);
    @unlink(rtrim($dir, '/') . '/thumbs/' . $filename);
}

/**
 * Public URL for an uploaded file (or '' when none).
 */
function upload_url(string $subdir, ?string $filename, bool $thumb = false): string
{
    if ($filename === null || $filename === '') {
        return '';
    }
    // Thumbnails are generated by GD on upload. Pre-seeded images (and
    // uploads made on a host without GD) have none — fall back to the
    // full-size file rather than rendering a broken image.
    $t = '';
    if ($thumb && is_file(BASE_PATH . '/uploads/' . $subdir . '/thumbs/' . $filename)) {
        $t = 'thumbs/';
    }
    return BASE_URL . '/uploads/' . $subdir . '/' . $t . rawurlencode($filename);
}

/**
 * Redirect and stop. (void + exit keeps PHP 8.0 compatibility.)
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Split a textarea value into trimmed, non-empty lines. Feature lists
 * (package bullet points, opening-hours lines) are stored one per line,
 * so the owner edits them in a plain textarea.
 *
 * @return string[]
 */
function lines(?string $text): array
{
    if ($text === null || trim($text) === '') {
        return [];
    }
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

/**
 * Price the way the printed offer sheet writes it: 42000 → "42 000 Ft".
 * A csoportosító elválasztó nem törhető szóköz, hogy az ár egyben maradjon.
 */
function format_huf(?int $huf): string
{
    if ($huf === null) {
        return '';
    }
    return number_format($huf, 0, ',', "\u{00A0}") . "\u{00A0}Ft";
}

/**
 * A date the way Hungarian reads it: 2026-02-14 → "2026. február 14."
 * Month names are spelled out here so the site never depends on the
 * server's locale being installed.
 */
function format_date_hu(string $isoDate, bool $withYear = true): string
{
    $months = ['január', 'február', 'március', 'április', 'május', 'június',
               'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
    $ts = strtotime($isoDate);
    if ($ts === false) {
        return $isoDate;
    }
    $m = $months[(int) date('n', $ts) - 1];
    return ($withYear ? date('Y', $ts) . '. ' : '') . $m . ' ' . (int) date('j', $ts) . '.';
}

/**
 * Roman numeral for the offer sheet's package markers (I … VI).
 */
function roman(int $n): string
{
    $map = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    return $map[$n] ?? (string) $n;
}
