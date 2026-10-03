<?php
defined('ROOT') || exit;

require_once APP . '/lib/booking.php';

function checkin_scanner()
{
    require_admin(['admin', 'operator', 'checkin']);
    $sessions = db_all('SELECT id, title, starts_at FROM sessions ORDER BY sort, starts_at');
    // default: the session closest to now
    $best = null;
    $bestD = PHP_INT_MAX;
    foreach ($sessions as $s) {
        $d = abs(strtotime($s['starts_at']) - time());
        if ($d < $bestD) {
            $bestD = $d;
            $best = (int)$s['id'];
        }
    }
    aview_checkin([
        'title' => 'اسکن ورود', 'sessions' => $sessions, 'current' => $best,
        'api' => url('/admin/api/checkin'), 'statsApi' => url('/admin/api/checkin-stats'),
        'scripts' => ['js/scanner.js'],
    ]);
}

function aview_checkin(array $data)
{
    view('admin/checkin', $data, 'admin/layout');
}

function checkin_api_checkin()
{
    $admin = require_admin(['admin', 'operator', 'checkin']);
    $in = json_input();
    $raw = isset($in['code']) ? substr((string)$in['code'], 0, 200) : '';
    $sid = isset($in['session']) ? (int)$in['session'] : 0;
    $log = function ($ticketId, $result) use ($admin, $sid, $raw) {
        db_insert('checkin_logs', ['ticket_id' => $ticketId, 'admin_id' => (int)$admin['id'], 'session_id' => $sid ?: null, 'result' => $result, 'raw' => mb_substr($raw, 0, 200), 'created_at' => now()]);
    };

    $parsed = ticket_parse($raw);
    if (!$parsed) {
        $log(null, 'invalid');
        json_out(['ok' => false, 'result' => 'invalid', 'message' => 'QR کد معتبر نیست یا جعلی است.']);
    }
    $t = db_row(
        'SELECT t.*, se.row_no, se.seat_no, se.section, c.name cat, s.title, s.starts_at, u.first_name, u.last_name, u.mobile, u.company
         FROM tickets t JOIN seats se ON se.id = t.seat_id LEFT JOIN categories c ON c.id = se.category_id
         JOIN sessions s ON s.id = t.session_id JOIN users u ON u.id = t.user_id WHERE t.code = ?',
        [$parsed[0]]
    );
    if (!$t) {
        $log(null, 'invalid');
        json_out(['ok' => false, 'result' => 'invalid', 'message' => 'بلیطی با این کد پیدا نشد.']);
    }
    $info = [
        'name' => $t['guest_name'] ?: trim($t['first_name'] . ' ' . $t['last_name']),
        'company' => (string)$t['company'],
        'row' => (int)$t['row_no'], 'seat' => (int)$t['seat_no'], 'section' => section_name($t['section']),
        'cat' => (string)$t['cat'], 'session' => $t['title'], 'code' => $t['code'],
    ];
    if ($t['status'] !== 'valid') {
        $log((int)$t['id'], 'cancelled');
        json_out(['ok' => false, 'result' => 'cancelled', 'message' => 'این بلیط باطل شده است.', 'ticket' => $info]);
    }
    if ($sid && (int)$t['session_id'] !== $sid) {
        $log((int)$t['id'], 'wrong_session');
        json_out(['ok' => false, 'result' => 'wrong_session', 'message' => 'این بلیط برای سانس دیگری است: ' . $t['title'], 'ticket' => $info]);
    }
    $now = now();
    $n = db_exec(
        'UPDATE tickets SET checked_in_at = ?, checked_in_by = ?, checkin_count = checkin_count + 1 WHERE id = ? AND checked_in_at IS NULL',
        [$now, (int)$admin['id'], (int)$t['id']]
    );
    if ($n === 0) {
        db_exec('UPDATE tickets SET checkin_count = checkin_count + 1 WHERE id = ?', [(int)$t['id']]);
        $first = db_val('SELECT checked_in_at FROM tickets WHERE id = ?', [(int)$t['id']]);
        $log((int)$t['id'], 'duplicate');
        json_out(['ok' => false, 'result' => 'duplicate', 'message' => 'این بلیط قبلاً در ساعت ' . jdate('H:i', $first) . ' استفاده شده است.', 'ticket' => $info]);
    }
    $log((int)$t['id'], 'ok');
    json_out(['ok' => true, 'result' => 'ok', 'message' => 'خوش آمدید', 'ticket' => $info]);
}

function checkin_api_stats()
{
    require_admin(['admin', 'operator', 'checkin']);
    $sid = (int)input('session');
    $r = db_row('SELECT COUNT(*) total, SUM(checked_in_at IS NOT NULL) inside FROM tickets WHERE session_id = ? AND status = ?', [$sid, 'valid']);
    json_out(['ok' => true, 'total' => (int)$r['total'], 'inside' => (int)$r['inside']]);
}
