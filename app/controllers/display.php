<?php
defined('ROOT') || exit;

/*
 * Welcome screen for the big monitor in the hall.
 * A browser on the venue PC opens /display/{token}; it polls the feed and shows
 * the name of every guest whose ticket was just scanned successfully.
 * Access is by a secret link (no login on the venue PC); regenerate it to revoke.
 */

function display_token()
{
    $t = setting('display_token');
    if (!preg_match('/^[A-Za-z0-9]{24}$/', $t)) {
        $t = random_code(24, 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        settings_save(['display_token' => $t]);
    }
    return $t;
}

function display_check_token($token)
{
    $t = setting('display_token');
    if ($t === '' || !hash_equals($t, $token)) {
        http_response_code(404);
        exit('Not found');
    }
}

function display_admin_page()
{
    require_admin();
    $token = display_token();
    $sessions = db_all('SELECT id, title, starts_at FROM sessions ORDER BY sort, starts_at');
    aview_display('admin/display', [
        'title' => 'نمایشگر خوش‌آمد', 'token' => $token, 'sessions' => $sessions,
        'link' => abs_url('/display/' . $token),
    ]);
}

function aview_display($name, array $data)
{
    view($name, $data, 'admin/layout');
}

function display_admin_save()
{
    require_admin();
    if (input('action') === 'regenerate') {
        settings_save(['display_token' => '']);
        display_token();
        flash('ok', 'لینک جدید ساخته شد؛ لینک قبلی دیگر کار نمی‌کند.');
        redirect('/admin/display');
    }
    settings_save([
        'display_heading' => mb_substr(input('display_heading'), 0, 80),
        'display_message' => mb_substr(input('display_message'), 0, 160),
        'display_show_company' => input('display_show_company') === '1' ? '1' : '0',
        'display_show_seat' => input('display_show_seat') === '1' ? '1' : '0',
        'display_seconds' => (string)max(2, min(20, (int)en_digits(input('display_seconds', '6')))),
    ]);
    flash('ok', 'تنظیمات نمایشگر ذخیره شد. صفحه نمایشگر را یک بار رفرش کنید.');
    redirect('/admin/display');
}

function display_screen($token)
{
    display_check_token($token);
    $sid = (int)input('s');
    $q = $sid ? ['s' => $sid] : [];
    $cfg = [
        'feed' => url('/display/' . $token . '/feed', $q),
        'seconds' => (int)setting('display_seconds', 6),
        'heading' => setting('display_heading', 'خوش آمدید'),
        'message' => setting('display_message', 'به ' . setting('site_title', 'همایش') . ' خوش آمدید'),
        'company' => setting('display_show_company', '1') === '1',
        'seat' => setting('display_show_seat', '1') === '1',
        'demo' => input('demo') === '1',
    ];
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo render('display/screen', ['cfg' => $cfg]);
}

/** New successful check-ins after a given log id. */
function display_feed($token)
{
    display_check_token($token);
    $after = (int)input('after');
    $sid = (int)input('s');
    $sessFilter = $sid ? ' AND t.session_id = ' . $sid : '';
    $out = ['ok' => true, 'items' => []];
    if ($after <= 0) {
        // first call: start from "now", don't replay old entries
        $out['last'] = (int)db_val('SELECT COALESCE(MAX(id), 0) FROM checkin_logs');
    } else {
        $max = (int)db_val('SELECT COALESCE(MAX(id), 0) FROM checkin_logs');
        $rows = db_all(
            'SELECT l.id, t.guest_name, u.first_name, u.last_name, u.company, se.row_no, se.seat_no
             FROM checkin_logs l JOIN tickets t ON t.id = l.ticket_id JOIN users u ON u.id = t.user_id JOIN seats se ON se.id = t.seat_id
             WHERE l.id > ? AND l.id <= ? AND l.result = ?' . $sessFilter . ' ORDER BY l.id LIMIT 30',
            [$after, $max, 'ok']
        );
        foreach ($rows as $r) {
            $out['items'][] = [
                'name' => $r['guest_name'] ?: trim($r['first_name'] . ' ' . $r['last_name']),
                // the company belongs to the buyer, so hide it for guest tickets
                'company' => $r['guest_name'] ? '' : (string)$r['company'],
                'row' => (int)$r['row_no'], 'seat' => (int)$r['seat_no'],
            ];
        }
        // cursor: everything up to $max was examined, unless the page was full
        $out['last'] = count($rows) === 30 ? (int)$rows[29]['id'] : max($after, $max);
    }
    $out['inside'] = (int)db_val('SELECT COUNT(*) FROM tickets t WHERE t.status = \'valid\' AND t.checked_in_at IS NOT NULL' . $sessFilter);
    json_out($out);
}
