<?php
defined('ROOT') || exit;

function site_home()
{
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
    view('site/home', compact('sessions', 'minPrice', 'avail', 'speakers'));
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
    echo session_status_json((int)$id);
}
