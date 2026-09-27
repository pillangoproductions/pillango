<?php
/**
 * GET /api/settings.php  → the venue's public details.
 *
 * Only an explicit allow-list is exposed: the settings table also
 * holds the notification address and other operational values, and
 * those must never leave the admin.
 */

require_once __DIR__ . '/../includes/site-render.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const PUBLIC_SETTINGS = [
    'venue_name', 'venue_city', 'address_line1', 'address_line2',
    'phone', 'email', 'map_url', 'availability',
    'facebook_url', 'instagram_url', 'tickets_url',
    'hero_tagline',
];

$out = [];
foreach (PUBLIC_SETTINGS as $key) {
    $out[$key] = setting($key, '');
}

$out['capacity'] = [];
foreach ([1, 2, 3] as $n) {
    $num = setting('cap' . $n . '_num', '');
    if ($num !== '' && $num !== null) {
        $out['capacity'][] = [
            'value' => $num,
            'label' => setting('cap' . $n . '_label', ''),
            'note'  => setting('cap' . $n . '_note', ''),
        ];
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
