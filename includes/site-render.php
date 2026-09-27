<?php
/**
 * Public-site data access + HTML rendering.
 *
 * Everything the visitor sees comes from the database; the design
 * itself lives in css/style.css. Nothing here should carry styling
 * decisions beyond choosing class names.
 */

require_once __DIR__ . '/functions.php';

// ---------------------------------------------------------------------------
// Data access
// ---------------------------------------------------------------------------

/**
 * The rentable packages ("A kínálat"), in display order.
 */
function active_packages(): array
{
    return db()->query(
        'SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order, id'
    )->fetchAll();
}

/**
 * Optional extras that can be added to any package.
 */
function active_addons(): array
{
    return db()->query(
        'SELECT * FROM addons WHERE is_active = 1 ORDER BY sort_order, id'
    )->fetchAll();
}

/**
 * The recurring evening formats (Salotto Musicale, Degustazione…).
 * These are evergreen: they show even when no date is pinned yet.
 */
function active_series(): array
{
    return db()->query(
        'SELECT * FROM event_series WHERE is_active = 1 ORDER BY sort_order, id'
    )->fetchAll();
}

/**
 * Dated evenings still to come, soonest first. Past events drop off
 * the site by themselves — nobody has to go and hide them.
 *
 * @param int $limit 0 = no limit
 */
function upcoming_events(int $limit = 0): array
{
    $sql = 'SELECT e.*, s.name AS series_name, s.slug AS series_slug
              FROM events e
              LEFT JOIN event_series s ON s.id = e.series_id
             WHERE e.is_active = 1 AND e.event_date >= CURDATE()
             ORDER BY e.event_date, e.start_time, e.id';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return db()->query($sql)->fetchAll();
}

/**
 * Active gallery photos, in display order.
 */
function gallery_photos(): array
{
    return db()->query(
        'SELECT * FROM gallery WHERE is_active = 1 ORDER BY sort_order, id'
    )->fetchAll();
}

// ---------------------------------------------------------------------------
// The journey: chapter positions along the scroll
// ---------------------------------------------------------------------------

/** Depth in px of one unit of "gap" between chapters. */
const JOURNEY_UNIT_DEPTH = 1150;

/**
 * Turn an ordered chapter spine into positions on the 0..1 journey.
 *
 * Each chapter declares the gap that precedes it, so inserting photos
 * (or a new chapter) lengthens the flight instead of crowding what is
 * already there — the spacing between any two beats stays constant.
 *
 * @param array $spine list of ['id'=>string, 'gap'=>float, 'sky'=>string, 'rail'=>?string]
 * @return array{chapters: array, totalDepth: int}
 */
function build_journey(array $spine): array
{
    $total = 0.0;
    foreach ($spine as $ch) {
        $total += (float) ($ch['gap'] ?? 1.0);
    }
    if ($total <= 0) {
        $total = 1.0;
    }

    $chapters = [];
    $cum = 0.0;
    foreach ($spine as $ch) {
        $cum += (float) ($ch['gap'] ?? 1.0);
        $chapters[] = [
            'id'   => $ch['id'],
            'p'    => round($cum / $total, 5),
            'sky'  => $ch['sky'],
            'rail' => $ch['rail'] ?? null,
        ];
    }

    return [
        'chapters'   => $chapters,
        'totalDepth' => (int) round($total * JOURNEY_UNIT_DEPTH),
    ];
}

// ---------------------------------------------------------------------------
// Rendering
// ---------------------------------------------------------------------------

/**
 * The price block of a package: "-tól · 42 000 Ft · / fő".
 * Packages priced only in words render just their note.
 */
function render_price_block(array $row, string $prefix = '-tól'): string
{
    $from = $row['price_from'] !== null ? (int) $row['price_from'] : null;
    if ($from === null && ($row['price_note'] ?? '') === '') {
        return '';
    }

    $html = '<div class="price">';
    if ($from !== null) {
        $html .= '<span class="price-pre">' . e($prefix) . '</span>'
            . '<span class="price-num">' . e(format_huf($from)) . '</span>';
        if (!empty($row['price_unit'])) {
            $html .= '<span class="price-unit">' . e($row['price_unit']) . '</span>';
        }
    }
    if (!empty($row['price_note'])) {
        $html .= '<span class="price-note">' . e($row['price_note']) . '</span>';
    }
    return $html . '</div>';
}

/**
 * One package as a card in the chapter preview grid.
 */
function render_package_card(array $pkg, int $index): string
{
    return '<li class="pkg-card">'
        . '<span class="pkg-roman" aria-hidden="true">' . e(roman($index)) . '</span>'
        . '<p class="pkg-it">' . e($pkg['name_it']) . '</p>'
        . '<h3 class="pkg-hu">' . e($pkg['name_hu']) . '</h3>'
        . '<p class="pkg-tag">' . e($pkg['tagline'] ?? '') . '</p>'
        . render_price_block($pkg)
        . '</li>' . "\n";
}

/**
 * One package as a full entry inside the offer overlay.
 */
function render_package_entry(array $pkg, int $index): string
{
    $html = '<section class="offer-entry" id="pacchetto-' . e($pkg['slug']) . '">' . "\n"
        . '<header class="offer-head">' . "\n"
        . '<p class="eyebrow">' . e($pkg['name_it']) . '</p>' . "\n"
        . '<h3 class="offer-title">' . e($pkg['name_hu']) . '</h3>' . "\n"
        . '<p class="offer-tag">' . e($pkg['tagline'] ?? '') . '</p>' . "\n"
        . '<span class="offer-roman" aria-hidden="true">' . e(roman($index)) . '</span>' . "\n"
        . '</header>' . "\n"
        . '<div class="offer-body">' . "\n"
        . '<div class="offer-prose">' . "\n";

    if (!empty($pkg['description'])) {
        $html .= '<p>' . e($pkg['description']) . '</p>' . "\n";
    }
    if (!empty($pkg['highlight'])) {
        $html .= '<p class="offer-highlight">' . e($pkg['highlight']) . '</p>' . "\n";
    }

    $html .= render_price_block($pkg);
    if (!empty($pkg['capacity_note'])) {
        $html .= '<p class="offer-capacity">' . e($pkg['capacity_note']) . '</p>' . "\n";
    }
    $html .= '<button class="btn-line" type="button" data-enquire="' . e($pkg['slug']) . '">Ajánlatot kérek erre</button>' . "\n"
        . '</div>' . "\n";

    $features = lines($pkg['features'] ?? null);
    if ($features) {
        $html .= '<ul class="offer-features">' . "\n";
        foreach ($features as $feature) {
            $html .= '<li>' . e($feature) . '</li>' . "\n";
        }
        $html .= '</ul>' . "\n";
    }

    return $html . '</div>' . "\n</section>" . "\n";
}

/**
 * The full offer sheet: every package, then the add-ons and the
 * pricing footnote. Opens over the flight from the "A kínálat" chapter.
 */
function render_offer_overlay(): string
{
    $packages = active_packages();
    $addons   = active_addons();

    $html = '<div class="sheet" id="offer-sheet" role="dialog" aria-modal="true" aria-label="A kínálat" hidden>' . "\n"
        . '<button class="sheet-close" type="button" data-close-sheet aria-label="Bezárás">×</button>' . "\n"
        . '<div class="sheet-wrap">' . "\n"
        . '<header class="sheet-head">' . "\n"
        . '<p class="eyebrow">' . e(setting('offerta_eyebrow', '')) . '</p>' . "\n"
        . '<h2 class="sheet-heading">' . e(setting('offerta_heading', '')) . '</h2>' . "\n"
        . '<p class="sheet-lede">' . e(setting('offerta_text', '')) . '</p>' . "\n"
        . '</header>' . "\n";

    foreach ($packages as $i => $pkg) {
        $html .= render_package_entry($pkg, $i + 1);
    }

    if ($addons) {
        $html .= '<section class="offer-addons">' . "\n"
            . '<header class="offer-head">' . "\n"
            . '<h3 class="offer-title">' . e(setting('addons_heading', '')) . '</h3>' . "\n"
            . '<p class="offer-tag">' . e(setting('addons_text', '')) . '</p>' . "\n"
            . '</header>' . "\n"
            . '<ul class="addon-grid">' . "\n";
        foreach ($addons as $addon) {
            $unit  = trim((string) ($addon['price_unit'] ?? ''));
            // "18 000 Ft/fő-től" reads better than "18 000 Ft / fő -tól".
            $price = $addon['price_from'] !== null
                ? format_huf((int) $addon['price_from']) . ($unit !== '' ? '/' . ltrim($unit, '/ ') : '') . '-tól'
                : '';
            $html .= '<li><h4>' . e($addon['name']) . '</h4>'
                . '<p>' . e($addon['description'] ?? '') . '</p>'
                . ($price !== '' ? '<p class="addon-price">' . e($price) . '</p>' : '')
                . '</li>' . "\n";
        }
        $html .= '</ul>' . "\n</section>" . "\n";
    }

    $note = setting('price_note', '');
    if ($note !== '') {
        $html .= '<footer class="sheet-foot"><p class="eyebrow">Jó tudni</p><p>' . e($note) . '</p></footer>' . "\n";
    }

    return $html . '</div>' . "\n</div>" . "\n";
}

/**
 * One dated evening, as a row in the season list.
 */
function render_event_row(array $event): string
{
    $ts   = strtotime($event['event_date']);
    $time = $event['start_time'] !== null ? substr((string) $event['start_time'], 0, 5) : '';

    $html = '<li class="evt-row' . ($event['is_sold_out'] ? ' is-soldout' : '') . '">'
        . '<div class="evt-when">'
        . '<span class="evt-day">' . e((string) (int) date('j', $ts)) . '</span>'
        . '<span class="evt-month">' . e(format_date_hu($event['event_date'], false)) . '</span>'
        . '<span class="evt-year">' . e(date('Y', $ts)) . ($time !== '' ? ' · ' . e($time) : '') . '</span>'
        . '</div>'
        . '<div class="evt-what">';

    if (!empty($event['series_name'])) {
        $html .= '<p class="evt-series">' . e($event['series_name']) . '</p>';
    }
    $html .= '<h3 class="evt-title">' . e($event['title']) . '</h3>';
    if (!empty($event['lead_text'])) {
        $html .= '<p class="evt-lead">' . e($event['lead_text']) . '</p>';
    }

    $meta = array_filter([$event['price_note'] ?? '', $event['capacity_note'] ?? '']);
    if ($meta) {
        $html .= '<p class="evt-meta">' . e(implode(' · ', $meta)) . '</p>';
    }
    $html .= '</div><div class="evt-go">';

    if ($event['is_sold_out']) {
        $html .= '<span class="evt-soldout">Elkelt</span>';
    } elseif (!empty($event['ticket_url'])) {
        $html .= '<a class="btn-line" href="' . e($event['ticket_url']) . '" target="_blank" rel="noopener">Jegyet váltok</a>';
    }

    return $html . '</div></li>' . "\n";
}

/**
 * The season sheet: what is coming up, then the recurring formats.
 */
function render_season_overlay(): string
{
    $events = upcoming_events();
    $series = active_series();

    $html = '<div class="sheet" id="season-sheet" role="dialog" aria-modal="true" aria-label="Az évad" hidden>' . "\n"
        . '<button class="sheet-close" type="button" data-close-sheet aria-label="Bezárás">×</button>' . "\n"
        . '<div class="sheet-wrap">' . "\n"
        . '<header class="sheet-head">' . "\n"
        . '<p class="eyebrow">' . e(setting('eventi_eyebrow', '')) . '</p>' . "\n"
        . '<h2 class="sheet-heading">' . e(setting('eventi_heading', '')) . '</h2>' . "\n"
        . '<p class="sheet-lede">' . e(setting('eventi_text', '')) . '</p>' . "\n"
        . '</header>' . "\n";

    if ($events) {
        $html .= '<ul class="evt-list">' . "\n";
        foreach ($events as $event) {
            $html .= render_event_row($event);
        }
        $html .= '</ul>' . "\n";
    } else {
        $html .= '<p class="sheet-empty">' . e(setting('eventi_empty', '')) . '</p>' . "\n";
    }

    if ($series) {
        $html .= '<section class="series-block">' . "\n"
            . '<header class="offer-head"><h3 class="offer-title">A visszatérő esték</h3></header>' . "\n"
            . '<ul class="series-grid">' . "\n";
        foreach ($series as $s) {
            $html .= '<li><p class="series-cadence">' . e($s['cadence'] ?? '') . '</p>'
                . '<h4>' . e($s['name']) . '</h4>'
                . '<p class="series-tag">' . e($s['tagline'] ?? '') . '</p>'
                . '<p>' . e($s['description'] ?? '') . '</p></li>' . "\n";
        }
        $html .= '</ul>' . "\n</section>" . "\n";
    }

    return $html . '</div>' . "\n</div>" . "\n";
}
