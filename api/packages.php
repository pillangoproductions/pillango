<?php
/**
 * GET /api/packages.php  → the rentable packages and the add-ons.
 *
 * Only active rows are returned — a package hidden in the admin
 * disappears here immediately.
 */

require_once __DIR__ . '/../includes/site-render.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'packages' => array_map(static function (array $p): array {
        return [
            'slug'          => $p['slug'],
            'name_it'       => $p['name_it'],
            'name_hu'       => $p['name_hu'],
            'tagline'       => $p['tagline'],
            'description'   => $p['description'],
            'highlight'     => $p['highlight'],
            'price_from'    => $p['price_from'] !== null ? (int) $p['price_from'] : null,
            'price_unit'    => $p['price_unit'],
            'price_note'    => $p['price_note'],
            'capacity_note' => $p['capacity_note'],
            'features'      => lines($p['features'] ?? null),
        ];
    }, active_packages()),
    'addons' => array_map(static function (array $a): array {
        return [
            'name'        => $a['name'],
            'description' => $a['description'],
            'price_from'  => $a['price_from'] !== null ? (int) $a['price_from'] : null,
            'price_unit'  => $a['price_unit'],
        ];
    }, active_addons()),
    'note' => setting('price_note', ''),
], JSON_UNESCAPED_UNICODE);
