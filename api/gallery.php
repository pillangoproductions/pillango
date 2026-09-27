<?php
/**
 * GET /api/gallery.php  → the photos on the visitor's journey.
 */

require_once __DIR__ . '/../includes/site-render.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(array_map(static function (array $p): array {
    return [
        'id'      => (int) $p['id'],
        'caption' => $p['caption'],
        'url'     => upload_url('gallery', $p['image']),
        'thumb'   => upload_url('gallery', $p['image'], true),
    ];
}, gallery_photos()), JSON_UNESCAPED_UNICODE);
