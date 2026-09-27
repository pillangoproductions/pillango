<?php
/**
 * GET /api/events.php  → upcoming evenings + the recurring formats.
 *
 * Past evenings are never returned; the list is what the public
 * season sheet shows.
 */

require_once __DIR__ . '/../includes/site-render.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'upcoming' => array_map(static function (array $e): array {
        return [
            'id'            => (int) $e['id'],
            'title'         => $e['title'],
            'series'        => $e['series_name'],
            'date'          => $e['event_date'],
            'time'          => $e['start_time'] !== null ? substr((string) $e['start_time'], 0, 5) : null,
            'lead'          => $e['lead_text'],
            'body'          => $e['body'],
            'price_note'    => $e['price_note'],
            'capacity_note' => $e['capacity_note'],
            'ticket_url'    => $e['ticket_url'],
            'sold_out'      => (bool) $e['is_sold_out'],
        ];
    }, upcoming_events()),
    'series' => array_map(static function (array $s): array {
        return [
            'slug'        => $s['slug'],
            'name'        => $s['name'],
            'cadence'     => $s['cadence'],
            'tagline'     => $s['tagline'],
            'description' => $s['description'],
        ];
    }, active_series()),
], JSON_UNESCAPED_UNICODE);
