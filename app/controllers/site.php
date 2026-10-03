<?php
defined('ROOT') || exit;

function site_home()
{
    // The landing page of a campaign gets the burst: serve its data from a 10s cache
    $cached = kv_get('home:v1');
    if ($cached !== null && ($d = @unserialize($cached)) !== false) {
        view('site/home', $d);
    }
    $sessions = db_all('SELECT * FROM sessions WHERE is_public = 1 ORDER BY sort, starts_at');
    $minPrice = [];
    $avail = [];
    if ($sessions) {
        $ids = array_column($sessions, 'id');
        foreach (db_all('SELECT session_id, MIN(price) p FROM session_prices WHERE session_id IN (' . db_in(count($ids)) . ') GROUP BY session_id', $ids) as $r) {
            $minPrice[$r['session_id']] = (int)$r['p'];
        }
        $t = time();
        foreach (db_all(
            'SELECT ss.session_id, COUNT(*) c FROM session_seats ss JOIN seats s ON s.id = ss.seat_id
             JOIN session_prices sp ON sp.session_id = ss.session_id AND sp.category_id = s.category_id
             WHERE s.is_active = 1 AND ss.session_id IN (' . db_in(count($ids)) . ') AND (ss.status = 0 OR (ss.status = 1 AND ss.hold_until < ?))
             GROUP BY ss.session_id',
            array_merge($ids, [$t])
        ) as $r) {
            $avail[$r['session_id']] = (int)$r['c'];
        }
    }
    $speakers = db_all('SELECT * FROM speakers WHERE is_active = 1 ORDER BY sort, id');
    $d = compact('sessions', 'minPrice', 'avail', 'speakers');
    kv_set('home:v1', serialize($d), 10);
    view('site/home', $d);
}

function site_rewrite_test()
{
    header('Content-Type: text/plain');
    echo 'ok';
}

/** Public seat states (no session, micro-cached). */
function site_api_seats($id)
{
    require_once APP . '/lib/hall.php';
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    // Serve the published file when it is fresh; rebuild at most every few seconds
    $f = session_status_file((int)$id);
    $m = @filemtime($f);
    if ($m && time() - $m < 5) {
        readfile($f);
        return;
    }
    echo session_status_json((int)$id);
}
