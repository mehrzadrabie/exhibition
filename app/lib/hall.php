<?php
defined('ROOT') || exit;

/*
 * Hall layout: تالار فرهنگ و هنر کرمان (digitised from the seating plan).
 * Each row: [row, right [from,to] | null, center [from,to], left [from,to] | null]
 * Numbering runs from the right side of the plan to the left, continuous across blocks.
 */
function hall_definition()
{
    return [
        [1, null, [1, 17], null],
        [2, null, [1, 19], null],
        [3, [1, 7], [8, 19], [20, 26]],
        [4, [1, 7], [8, 19], [20, 26]],
        [5, [1, 7], [8, 20], [21, 27]],
        [6, [1, 8], [9, 22], [23, 30]],
        [7, [1, 8], [9, 23], [24, 31]],
        [8, [1, 8], [9, 24], [25, 32]],
        [9, [1, 12], [13, 24], [25, 36]],
        [10, [1, 12], [13, 25], [26, 37]],
        [11, [1, 13], [14, 27], [28, 40]],
        [12, [1, 13], [14, 28], [29, 41]],
        [13, [1, 13], [14, 29], [30, 42]],
        [14, [1, 13], [14, 30], [31, 43]],
        [15, [1, 13], [14, 31], [32, 44]],
        [16, [1, 13], [14, 32], [33, 45]],
        [17, [1, 11], [14, 33], [34, 44]],
        [18, [1, 8], [14, 34], [35, 42]],
        [19, [1, 5], [14, 35], [36, 40]],
    ];
}

/** Geometry constants (SVG units) */
function hall_geo()
{
    return ['pitch' => 26, 'r1' => 560, 'row_gap' => 33, 'front_gap' => 22, 'cross_aisle' => 48, 'aisle' => 30];
}

function hall_radius($row)
{
    $g = hall_geo();
    $r = $g['r1'] + ($row - 1) * $g['row_gap'];
    if ($row >= 3) $r += $g['front_gap'];
    if ($row >= 9) $r += $g['cross_aisle'];
    return $r;
}

/**
 * Generate seat rows with polar coordinates around a centre point under the stage.
 * Returns list of [row, no, section, x, y, rot, categoryKey]
 */
function hall_generate()
{
    $g = hall_geo();
    $p = $g['pitch'];
    $out = [];
    foreach (hall_definition() as $def) {
        list($row, $right, $center, $left) = $def;
        $R = hall_radius($row);
        $cat = $row <= 2 ? 'vip' : ($row <= 8 ? 'a' : 'b');
        $n = $center[1] - $center[0] + 1;
        $half = $n * $p / 2;
        $place = function ($arc, $no, $sec) use ($R, $row, $cat, &$out) {
            $th = $arc / $R;
            $out[] = [$row, $no, $sec, round($R * sin($th), 2), round(-$R * cos($th), 2), round(rad2deg($th), 2), $cat];
        };
        // centre block: first number on the right
        for ($i = 0; $i < $n; $i++) $place($half - ($i + 0.5) * $p, $center[0] + $i, 'C');
        if ($right) {
            for ($k = $right[0]; $k <= $right[1]; $k++) $place($half + $g['aisle'] + ($right[1] - $k + 0.5) * $p, $k, 'R');
        }
        if ($left) {
            for ($k = $left[0]; $k <= $left[1]; $k++) $place(-($half + $g['aisle'] + ($k - $left[0] + 0.5) * $p), $k, 'L');
        }
    }
    return $out;
}

/* ---------------------------------------------------------------- */

/** Layout version: changes whenever seats/categories change. */
function layout_version()
{
    return setting('layout_version', '1');
}

function layout_bump()
{
    settings_save(['layout_version' => (string)time()]);
    foreach (glob(ROOT . '/assets/cache/layout-*.json') ?: [] as $f) @unlink($f);
}

/**
 * Public layout JSON (active seats only), stored as a static file so the web
 * server can serve it directly with long-lived caching.
 * @return string public URL
 */
function layout_public_url()
{
    $v = layout_version();
    $file = ROOT . '/assets/cache/layout-' . $v . '.json';
    if (!is_file($file)) {
        $json = json_encode(layout_build(false), JSON_UNESCAPED_UNICODE);
        $tmp = $file . '.' . getmypid();
        file_put_contents($tmp, $json);
        rename($tmp, $file);
    }
    return base_path() . '/assets/cache/layout-' . $v . '.json';
}

function layout_build($includeInactive)
{
    $rows = db_all('SELECT id, row_no, seat_no, section, x, y, rot, category_id, is_active FROM seats' . ($includeInactive ? '' : ' WHERE is_active = 1') . ' ORDER BY row_no, seat_no');
    $g = hall_geo();
    $seats = [];
    $minx = $miny = PHP_INT_MAX;
    $maxx = $maxy = -PHP_INT_MAX;
    $ends = [];
    foreach ($rows as $r) {
        $x = (float)$r['x'];
        $y = (float)$r['y'];
        $s = [(int)$r['id'], (int)$r['row_no'], (int)$r['seat_no'], $r['section'], $x, $y, (float)$r['rot'], (int)$r['category_id']];
        if ($includeInactive) $s[] = (int)$r['is_active'];
        $seats[] = $s;
        $minx = min($minx, $x);
        $maxx = max($maxx, $x);
        $miny = min($miny, $y);
        $maxy = max($maxy, $y);
        $row = (int)$r['row_no'];
        $th = atan2($x, -$y);
        if (!isset($ends[$row])) $ends[$row] = [$th, $th];
        $ends[$row][0] = min($ends[$row][0], $th);
        $ends[$row][1] = max($ends[$row][1], $th);
    }
    // Row labels just outside both ends of each row
    $labels = [];
    foreach ($ends as $row => $e) {
        $R = hall_radius($row);
        $d = ($g['pitch'] * 1.15) / $R;
        foreach ([$e[0] - $d, $e[1] + $d] as $th) {
            $lx = round($R * sin($th), 1);
            $ly = round(-$R * cos($th), 1);
            $labels[] = [$row, $lx, $ly];
            $minx = min($minx, $lx);
            $maxx = max($maxx, $lx);
        }
    }
    $cats = [];
    foreach (db_all('SELECT id, name, color FROM categories ORDER BY sort, id') as $c) $cats[] = [(int)$c['id'], $c['name'], $c['color']];
    $stageR = $g['r1'] - 70;
    $pad = 30;
    return [
        'v' => layout_version(),
        'box' => [round($minx - $pad), round($miny - $pad), round($maxx - $minx + 2 * $pad), round(-$stageR + 120 - $miny + $pad)],
        'stage' => ['r' => $stageR, 'a' => 0.42],
        'seats' => $seats,
        'labels' => $labels,
        'cats' => $cats,
    ];
}

/** Seed seats + default categories (installer). */
function hall_seed()
{
    $catIds = [];
    foreach ([['vip', 'VIP', '#c9a227', 1], ['a', 'جایگاه ویژه', '#e0464f', 2], ['b', 'جایگاه عمومی', '#2f8f83', 3]] as $c) {
        $catIds[$c[0]] = db_insert('categories', ['name' => $c[1], 'color' => $c[2], 'sort' => $c[3]]);
    }
    $vals = [];
    $params = [];
    foreach (hall_generate() as $s) {
        $vals[] = '(?,?,?,?,?,?,?,1)';
        array_push($params, $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $catIds[$s[6]]);
    }
    db_exec('INSERT INTO seats (row_no, seat_no, section, x, y, rot, category_id, is_active) VALUES ' . implode(',', $vals), $params);
    return $catIds;
}

/* ---------------------------------------------------------------- */

/**
 * Current public seat states for a session, micro-cached.
 * @return string JSON {"t":ts,"u":[unavailable ids],"h":[held ids]}
 */
function session_status_json($sessionId)
{
    $key = 'seats:' . $sessionId;
    $cached = kv_get($key);
    if ($cached !== null) return $cached;
    $t = time();
    $u = [];
    $h = [];
    foreach (db_all('SELECT seat_id, status, hold_until FROM session_seats WHERE session_id = ? AND status > 0', [(int)$sessionId]) as $r) {
        $st = (int)$r['status'];
        if ($st === 1) {
            if ((int)$r['hold_until'] >= $t) $h[] = (int)$r['seat_id'];
        } else {
            $u[] = (int)$r['seat_id'];
        }
    }
    $json = json_encode(['t' => $t, 'u' => $u, 'h' => $h]);
    kv_set($key, $json, 4);
    return $json;
}

function session_status_invalidate($sessionId)
{
    kv_del('seats:' . $sessionId);
}

function session_seats_init($sessionId)
{
    db_exec('INSERT IGNORE INTO session_seats (session_id, seat_id, status) SELECT ?, id, 0 FROM seats', [(int)$sessionId]);
}

function session_prices($sessionId)
{
    $p = [];
    foreach (db_all('SELECT category_id, price FROM session_prices WHERE session_id = ?', [(int)$sessionId]) as $r) $p[(int)$r['category_id']] = (int)$r['price'];
    return $p;
}
