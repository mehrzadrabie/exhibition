<?php
defined('ROOT') || exit;

require_once APP . '/lib/booking.php';

function aview($name, array $data = [])
{
    view('admin/' . $name, $data, 'admin/layout');
}

/* ================= Auth ================= */

function admin_login_form()
{
    if (current_admin()) redirect('/admin');
    aview('login', ['title' => 'ورود به پنل']);
}

function admin_login_submit()
{
    if (!rate_limit('alogin:ip:' . client_ip(), 10, 900)) {
        flash('err', 'تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه دیگر تلاش کنید.');
        redirect('/admin/login');
    }
    $a = db_row('SELECT * FROM admins WHERE username = ? AND is_active = 1', [input('username')]);
    if (!$a || !password_verify((string)input('password'), $a['password_hash'])) {
        usleep(300000);
        flash('err', 'نام کاربری یا رمز عبور اشتباه است.');
        redirect('/admin/login');
    }
    session_regenerate_id(true);
    $_SESSION['aid'] = (int)$a['id'];
    db_exec('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), (int)$a['id']]);
    $to = isset($_SESSION['admin_after_login']) ? $_SESSION['admin_after_login'] : null;
    unset($_SESSION['admin_after_login']);
    if ($a['role'] === 'checkin') redirect('/admin/checkin');
    if ($to && strpos($to, '/') === 0) {
        header('Location: ' . $to);
        exit;
    }
    redirect('/admin');
}

function admin_logout()
{
    unset($_SESSION['aid']);
    session_regenerate_id(true);
    redirect('/admin/login');
}

/* ================= Dashboard ================= */

function admin_dashboard()
{
    require_admin(['admin', 'operator']);
    $t = time();
    $sessions = db_all('SELECT * FROM sessions ORDER BY sort, starts_at');
    $stats = [];
    foreach ($sessions as $s) {
        $sid = (int)$s['id'];
        $r = db_row(
            'SELECT SUM(s.is_active = 1) total,
                    SUM(s.is_active = 1 AND ss.status = 2) sold,
                    SUM(s.is_active = 1 AND ss.status = 3) blocked,
                    SUM(s.is_active = 1 AND ss.status = 1 AND ss.hold_until >= ?) held
             FROM session_seats ss JOIN seats s ON s.id = ss.seat_id WHERE ss.session_id = ?',
            [$t, $sid]
        );
        $rev = db_row('SELECT COALESCE(SUM(total),0) rev, COUNT(*) cnt FROM orders WHERE session_id = ? AND status = ?', [$sid, 'paid']);
        $chk = db_row('SELECT COUNT(*) tickets, SUM(checked_in_at IS NOT NULL) inside FROM tickets WHERE session_id = ? AND status = ?', [$sid, 'valid']);
        $stats[$sid] = [
            'total' => (int)$r['total'], 'sold' => (int)$r['sold'], 'blocked' => (int)$r['blocked'], 'held' => (int)$r['held'],
            'revenue' => (int)$rev['rev'], 'orders' => (int)$rev['cnt'], 'tickets' => (int)$chk['tickets'], 'inside' => (int)$chk['inside'],
        ];
    }
    $today = date('Y-m-d 00:00:00');
    $todayStats = db_row('SELECT COUNT(*) c, COALESCE(SUM(total),0) s, COALESCE(SUM(seats_count),0) seats FROM orders WHERE status = ? AND paid_at >= ?', ['paid', $today]);
    $totals = db_row('SELECT COUNT(*) c, COALESCE(SUM(total),0) s, COALESCE(SUM(seats_count),0) seats FROM orders WHERE status = ?', ['paid']);
    $users = (int)db_val('SELECT COUNT(*) FROM users');
    $byCat = db_all(
        'SELECT c.name, c.color, COUNT(*) cnt, SUM(oi.price) rev FROM order_items oi JOIN orders o ON o.id = oi.order_id
         LEFT JOIN categories c ON c.id = oi.category_id WHERE o.status = ? GROUP BY oi.category_id, c.name, c.color ORDER BY c.name',
        ['paid']
    );
    $daily = db_all(
        'SELECT DATE(paid_at) d, COUNT(*) c, SUM(seats_count) seats, SUM(total) s FROM orders WHERE status = ? AND paid_at >= ? GROUP BY DATE(paid_at) ORDER BY d',
        ['paid', date('Y-m-d', strtotime('-13 days'))]
    );
    $recent = db_all(
        'SELECT o.*, u.mobile, u.first_name, u.last_name, s.title FROM orders o JOIN users u ON u.id = o.user_id JOIN sessions s ON s.id = o.session_id
         WHERE o.status = ? ORDER BY o.paid_at DESC LIMIT 10',
        ['paid']
    );
    aview('dashboard', compact('sessions', 'stats', 'todayStats', 'totals', 'users', 'byCat', 'daily', 'recent') + ['title' => 'داشبورد']);
}

/* ================= Sessions ================= */

function admin_sessions()
{
    require_admin();
    $sessions = db_all('SELECT * FROM sessions ORDER BY sort, starts_at');
    aview('sessions', ['title' => 'سانس‌ها', 'sessions' => $sessions]);
}

function admin_session_form($id)
{
    require_admin();
    $s = $id === 'new' ? null : db_row('SELECT * FROM sessions WHERE id = ?', [(int)$id]);
    if ($id !== 'new' && !$s) abort(404);
    $cats = db_all('SELECT * FROM categories ORDER BY sort, id');
    $prices = $s ? session_prices($s['id']) : [];
    aview('session_form', ['title' => $s ? 'ویرایش سانس' : 'سانس جدید', 's' => $s, 'cats' => $cats, 'prices' => $prices]);
}

function admin_session_save($id)
{
    require_admin();
    if ($id !== 'new' && input('delete') === '1') {
        $sold = (int)db_val('SELECT COUNT(*) FROM orders WHERE session_id = ? AND status = ?', [(int)$id, 'paid']);
        if ($sold) {
            flash('err', 'این سانس فروش دارد و قابل حذف نیست. می‌توانید آن را غیرفعال کنید.');
            redirect('/admin/sessions/' . (int)$id);
        }
        db_exec('DELETE FROM session_seats WHERE session_id = ?', [(int)$id]);
        db_exec('DELETE FROM session_prices WHERE session_id = ?', [(int)$id]);
        db_exec('DELETE FROM sessions WHERE id = ?', [(int)$id]);
        flash('ok', 'سانس حذف شد.');
        redirect('/admin/sessions');
    }
    $starts = jalali_to_datetime(input('starts_at'));
    $ends = input('ends_at') !== '' ? jalali_to_datetime(input('ends_at')) : null;
    if (!$starts || input('title') === '') {
        flash('err', 'عنوان و تاریخ شروع (مثال: ۱۴۰۵/۰۷/۲۲ ۱۶:۰۰) را درست وارد کنید.');
        back('/admin/sessions');
    }
    $data = [
        'title' => mb_substr(input('title'), 0, 160),
        'subtitle' => mb_substr(input('subtitle'), 0, 255) ?: null,
        'description' => input('description') ?: null,
        'starts_at' => $starts,
        'ends_at' => $ends,
        'sale_open' => input('sale_open') === '1' ? 1 : 0,
        'is_public' => input('is_public') === '1' ? 1 : 0,
        'max_per_order' => max(1, min(50, (int)en_digits(input('max_per_order', '10')))),
        'sort' => (int)en_digits(input('sort', '0')),
    ];
    if ($id === 'new') {
        $data['created_at'] = now();
        $sid = db_insert('sessions', $data);
        session_seats_init($sid);
    } else {
        $sid = (int)$id;
        db_update('sessions', $data, 'id = ?', [$sid]);
    }
    $prices = isset($_POST['price']) && is_array($_POST['price']) ? $_POST['price'] : [];
    db_exec('DELETE FROM session_prices WHERE session_id = ?', [$sid]);
    foreach ($prices as $cid => $p) {
        $p = preg_replace('/\D/', '', en_digits((string)$p));
        if ($p === '') continue; // empty = not for sale
        db_insert('session_prices', ['session_id' => $sid, 'category_id' => (int)$cid, 'price' => (int)$p]);
    }
    session_status_invalidate($sid);
    flash('ok', 'سانس ذخیره شد.');
    redirect('/admin/sessions/' . $sid);
}

/* ---- per-session seat management (block/unblock, who sits where) ---- */

function admin_session_seats($id)
{
    require_admin(['admin', 'operator']);
    $s = db_row('SELECT * FROM sessions WHERE id = ?', [(int)$id]);
    if (!$s) abort(404);
    require_once APP . '/lib/hall.php';
    aview('session_seats', [
        'title' => 'صندلی‌های ' . $s['title'], 's' => $s,
        'data' => ['layout' => layout_public_url(), 'api' => url('/admin/api/session-seats/' . $s['id']), 'orderUrl' => url('/admin/orders/')],
        'scripts' => ['js/seatmap.js', 'js/admin-seats.js'],
    ]);
}

function admin_api_session_seats($id)
{
    require_admin(['admin', 'operator']);
    $t = time();
    $rows = db_all(
        'SELECT ss.seat_id, ss.status, ss.hold_until, ss.order_id, u.first_name, u.last_name, u.mobile, tk.guest_name, tk.checked_in_at
         FROM session_seats ss
         LEFT JOIN orders o ON o.id = ss.order_id
         LEFT JOIN users u ON u.id = o.user_id
         LEFT JOIN tickets tk ON tk.order_id = ss.order_id AND tk.seat_id = ss.seat_id AND tk.status = \'valid\'
         WHERE ss.session_id = ? AND ss.status > 0',
        [(int)$id]
    );
    $out = [];
    foreach ($rows as $r) {
        $st = (int)$r['status'];
        if ($st === 1 && (int)$r['hold_until'] < $t) continue;
        $out[$r['seat_id']] = [
            $st, (int)$r['order_id'],
            $r['guest_name'] ?: trim($r['first_name'] . ' ' . $r['last_name']),
            (string)$r['mobile'], $r['checked_in_at'] ? 1 : 0,
        ];
    }
    json_out(['ok' => true, 'seats' => (object)$out]);
}

function admin_session_seats_save($id)
{
    require_admin();
    $sid = (int)$id;
    $ids = array_filter(array_map('intval', explode(',', (string)input('seats'))));
    $n = count($ids);
    if (!$n) back('/admin/sessions/' . $sid . '/seats');
    if (input('action') === 'block') {
        $c = db_exec('UPDATE session_seats SET status = 3, order_id = NULL, hold_until = NULL WHERE session_id = ? AND seat_id IN (' . db_in($n) . ') AND (status = 0 OR (status = 1 AND hold_until < ?))', array_merge([$sid], $ids, [time()]));
        flash('ok', fa($c) . ' صندلی مسدود (رزرو داخلی) شد.');
    } elseif (input('action') === 'unblock') {
        $c = db_exec('UPDATE session_seats SET status = 0 WHERE session_id = ? AND status = 3 AND seat_id IN (' . db_in($n) . ')', array_merge([$sid], $ids));
        flash('ok', fa($c) . ' صندلی آزاد شد.');
    }
    session_status_invalidate($sid);
    redirect('/admin/sessions/' . $sid . '/seats');
}

/* ================= Hall layout & categories ================= */

function admin_layout()
{
    require_admin();
    require_once APP . '/lib/hall.php';
    $cats = db_all('SELECT c.*, (SELECT COUNT(*) FROM seats s WHERE s.category_id = c.id AND s.is_active = 1) seats FROM categories c ORDER BY sort, id');
    $inactive = (int)db_val('SELECT COUNT(*) FROM seats WHERE is_active = 0');
    aview('layout_editor', [
        'title' => 'نقشه سالن', 'cats' => $cats, 'inactive' => $inactive,
        'layoutJson' => layout_build(true),
        'scripts' => ['js/seatmap.js', 'js/admin-layout.js'],
    ]);
}

function admin_layout_save()
{
    require_admin();
    require_once APP . '/lib/hall.php';
    $ids = array_filter(array_map('intval', explode(',', (string)input('seats'))));
    $n = count($ids);
    if ($n) {
        $act = input('action');
        if ($act === 'category') {
            db_exec('UPDATE seats SET category_id = ? WHERE id IN (' . db_in($n) . ')', array_merge([(int)input('category_id')], $ids));
        } elseif ($act === 'deactivate') {
            db_exec('UPDATE seats SET is_active = 0 WHERE id IN (' . db_in($n) . ')', $ids);
        } elseif ($act === 'activate') {
            db_exec('UPDATE seats SET is_active = 1 WHERE id IN (' . db_in($n) . ')', $ids);
        }
        layout_bump();
        flash('ok', 'تغییرات روی ' . fa($n) . ' صندلی اعمال شد.');
    }
    redirect('/admin/layout');
}

function admin_categories_save()
{
    require_admin();
    require_once APP . '/lib/hall.php';
    $id = (int)input('id');
    if (input('delete') === '1' && $id) {
        $used = (int)db_val('SELECT COUNT(*) FROM seats WHERE category_id = ?', [$id]);
        if ($used) {
            flash('err', 'ابتدا صندلی‌های این جایگاه را به جایگاه دیگری منتقل کنید.');
        } else {
            db_exec('DELETE FROM categories WHERE id = ?', [$id]);
            db_exec('DELETE FROM session_prices WHERE category_id = ?', [$id]);
            flash('ok', 'جایگاه حذف شد.');
        }
    } else {
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', input('color')) ? input('color') : '#64748b';
        $data = ['name' => mb_substr(input('name'), 0, 60), 'color' => $color, 'sort' => (int)en_digits(input('sort', '0'))];
        if ($data['name'] === '') {
            flash('err', 'نام جایگاه را وارد کنید.');
            redirect('/admin/layout');
        }
        if ($id) db_update('categories', $data, 'id = ?', [$id]);
        else db_insert('categories', $data);
        flash('ok', 'جایگاه ذخیره شد. قیمت آن را در هر سانس تعیین کنید.');
    }
    layout_bump();
    redirect('/admin/layout');
}

/* ================= Orders ================= */

function admin_orders()
{
    require_admin(['admin', 'operator']);
    $where = ['1=1'];
    $p = [];
    $status = input('status', 'paid');
    if ($status !== 'all') {
        $where[] = 'o.status = ?';
        $p[] = $status;
    }
    if (input('session') !== '') {
        $where[] = 'o.session_id = ?';
        $p[] = (int)input('session');
    }
    $q = trim(en_digits(input('q')));
    if ($q !== '') {
        $where[] = '(u.mobile LIKE ? OR CONCAT(u.first_name, \' \', u.last_name) LIKE ? OR o.ref_id = ? OR o.id = ? OR u.company LIKE ?)';
        array_push($p, '%' . $q . '%', '%' . $q . '%', $q, (int)$q, '%' . $q . '%');
    }
    $w = implode(' AND ', $where);
    $total = (int)db_val('SELECT COUNT(*) FROM orders o JOIN users u ON u.id = o.user_id WHERE ' . $w, $p);
    $pg = paginate($total, 30, input('page', 1));
    $orders = db_all(
        'SELECT o.*, u.mobile, u.first_name, u.last_name, u.company, s.title FROM orders o JOIN users u ON u.id = o.user_id JOIN sessions s ON s.id = o.session_id
         WHERE ' . $w . ' ORDER BY o.id DESC LIMIT ' . (int)$pg['per'] . ' OFFSET ' . (int)$pg['offset'],
        $p
    );
    $sessions = db_all('SELECT id, title FROM sessions ORDER BY sort, starts_at');
    aview('orders', ['title' => 'سفارش‌ها', 'orders' => $orders, 'pg' => $pg, 'sessions' => $sessions, 'status' => $status]);
}

function admin_order_view($id)
{
    require_admin(['admin', 'operator']);
    $o = db_row(
        'SELECT o.*, u.mobile, u.first_name, u.last_name, u.company, u.job_title, s.title, s.starts_at, a.name admin_name, c.code coupon_code
         FROM orders o JOIN users u ON u.id = o.user_id JOIN sessions s ON s.id = o.session_id
         LEFT JOIN admins a ON a.id = o.issued_by LEFT JOIN coupons c ON c.id = o.coupon_id WHERE o.id = ?',
        [(int)$id]
    );
    if (!$o) abort(404);
    $items = db_all('SELECT oi.*, se.row_no, se.seat_no, se.section, c.name cat FROM order_items oi JOIN seats se ON se.id = oi.seat_id LEFT JOIN categories c ON c.id = oi.category_id WHERE oi.order_id = ? ORDER BY se.row_no, se.seat_no', [(int)$id]);
    $tickets = db_all('SELECT t.*, se.row_no, se.seat_no FROM tickets t JOIN seats se ON se.id = t.seat_id WHERE t.order_id = ? ORDER BY se.row_no, se.seat_no', [(int)$id]);
    aview('order_view', ['title' => 'سفارش ' . fa($id), 'o' => $o, 'items' => $items, 'tickets' => $tickets]);
}

function admin_order_action($id)
{
    $admin = require_admin(['admin', 'operator']);
    $o = db_row('SELECT * FROM orders WHERE id = ?', [(int)$id]);
    if (!$o) abort(404);
    $act = input('action');
    if ($act === 'resend_sms' && $o['status'] === 'paid') {
        require_once APP . '/lib/sms.php';
        $ok = booking_send_sms($o['id']);
        flash($ok ? 'ok' : 'err', $ok ? 'پیامک بلیط دوباره ارسال شد.' : 'ارسال پیامک ناموفق بود.');
    } elseif ($act === 'cancel_pending' && $o['status'] === 'pending') {
        booking_cancel($o['id']);
        flash('ok', 'سفارش لغو و صندلی‌ها آزاد شدند.');
    } elseif ($act === 'refund' && $o['status'] === 'paid') {
        if ($admin['role'] !== 'admin') abort(403);
        db_tx(function () use ($o) {
            db_exec('UPDATE tickets SET status = ? WHERE order_id = ?', ['cancelled', (int)$o['id']]);
            db_exec('UPDATE session_seats SET status = 0, order_id = NULL, hold_until = NULL WHERE session_id = ? AND order_id = ? AND status = 2', [(int)$o['session_id'], (int)$o['id']]);
            db_exec('UPDATE orders SET status = ?, note = CONCAT(COALESCE(note, \'\'), ?) WHERE id = ?', ['refunded', ' | لغو توسط مدیر ' . now(), (int)$o['id']]);
        });
        session_status_invalidate($o['session_id']);
        flash('ok', 'سفارش لغو شد، بلیط‌ها باطل و صندلی‌ها آزاد شدند. استرداد وجه را به‌صورت دستی انجام دهید.');
    } elseif ($act === 'note') {
        db_update('orders', ['note' => mb_substr(input('note'), 0, 250)], 'id = ?', [(int)$o['id']]);
        flash('ok', 'یادداشت ذخیره شد.');
    }
    redirect('/admin/orders/' . (int)$id);
}

/* ================= Tickets ================= */

function admin_tickets()
{
    require_admin(['admin', 'operator']);
    $where = ['t.status = \'valid\''];
    $p = [];
    if (input('session') !== '') {
        $where[] = 't.session_id = ?';
        $p[] = (int)input('session');
    }
    if (input('checkin') === 'in') $where[] = 't.checked_in_at IS NOT NULL';
    if (input('checkin') === 'out') $where[] = 't.checked_in_at IS NULL';
    $q = trim(en_digits(input('q')));
    if ($q !== '') {
        $where[] = '(u.mobile LIKE ? OR CONCAT(u.first_name, \' \', u.last_name) LIKE ? OR t.code = ? OR t.guest_name LIKE ? OR t.guest_mobile LIKE ? OR u.company LIKE ?)';
        array_push($p, "%$q%", "%$q%", strtoupper($q), "%$q%", "%$q%", "%$q%");
    }
    $w = implode(' AND ', $where);
    $base = 'FROM tickets t JOIN users u ON u.id = t.user_id JOIN seats se ON se.id = t.seat_id JOIN sessions s ON s.id = t.session_id
             LEFT JOIN categories c ON c.id = se.category_id JOIN orders o ON o.id = t.order_id WHERE ' . $w;
    $cols = 'SELECT t.*, u.mobile, u.first_name, u.last_name, u.company, u.job_title, se.row_no, se.seat_no, se.section, s.title, c.name cat, o.method, o.paid_at ';

    if (input('export') === '1') {
        $rows = db_all($cols . $base . ' ORDER BY s.starts_at, se.row_no, se.seat_no', $p);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="tickets-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['کد بلیط', 'سانس', 'ردیف', 'صندلی', 'بخش', 'جایگاه', 'نام خریدار', 'موبایل خریدار', 'شرکت', 'سمت', 'نام مهمان', 'موبایل مهمان', 'سفارش', 'روش', 'تاریخ خرید', 'ورود']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['code'], $r['title'], $r['row_no'], $r['seat_no'], section_name($r['section']), $r['cat'],
                trim($r['first_name'] . ' ' . $r['last_name']), '="' . $r['mobile'] . '"', $r['company'], $r['job_title'],
                $r['guest_name'], $r['guest_mobile'] ? '="' . $r['guest_mobile'] . '"' : '', $r['order_id'], method_label($r['method']),
                $r['paid_at'] ? jdate('Y/m/d H:i', $r['paid_at'], false) : '', $r['checked_in_at'] ? jdate('Y/m/d H:i', $r['checked_in_at'], false) : '',
            ]);
        }
        fclose($out);
        exit;
    }

    $total = (int)db_val('SELECT COUNT(*) ' . $base, $p);
    $pg = paginate($total, 50, input('page', 1));
    $tickets = db_all($cols . $base . ' ORDER BY t.id DESC LIMIT ' . (int)$pg['per'] . ' OFFSET ' . (int)$pg['offset'], $p);
    $sessions = db_all('SELECT id, title FROM sessions ORDER BY sort, starts_at');
    aview('tickets', ['title' => 'بلیط‌ها', 'tickets' => $tickets, 'pg' => $pg, 'sessions' => $sessions]);
}

function admin_ticket_action($id)
{
    $admin = require_admin(['admin', 'operator']);
    $t = db_row('SELECT * FROM tickets WHERE id = ?', [(int)$id]);
    if (!$t) abort(404);
    $act = input('action');
    if ($act === 'checkin' && !$t['checked_in_at']) {
        db_exec('UPDATE tickets SET checked_in_at = ?, checked_in_by = ?, checkin_count = checkin_count + 1 WHERE id = ? AND checked_in_at IS NULL', [now(), (int)$admin['id'], (int)$id]);
        db_insert('checkin_logs', ['ticket_id' => (int)$id, 'admin_id' => (int)$admin['id'], 'session_id' => (int)$t['session_id'], 'result' => 'ok', 'raw' => 'manual', 'created_at' => now()]);
        flash('ok', 'ورود ثبت شد.');
    } elseif ($act === 'undo_checkin') {
        db_exec('UPDATE tickets SET checked_in_at = NULL, checked_in_by = NULL WHERE id = ?', [(int)$id]);
        flash('ok', 'ورود لغو شد.');
    } elseif ($act === 'cancel' && $admin['role'] === 'admin') {
        db_tx(function () use ($t) {
            db_exec('UPDATE tickets SET status = ? WHERE id = ?', ['cancelled', (int)$t['id']]);
            db_exec('UPDATE session_seats SET status = 0, order_id = NULL WHERE session_id = ? AND seat_id = ? AND order_id = ? AND status = 2', [(int)$t['session_id'], (int)$t['seat_id'], (int)$t['order_id']]);
        });
        session_status_invalidate($t['session_id']);
        flash('ok', 'بلیط باطل و صندلی آزاد شد.');
    }
    back('/admin/tickets');
}

/* ================= Manual issue (VIP / invitations / cash sales) ================= */

function admin_issue_form()
{
    require_admin(['admin', 'operator']);
    require_once APP . '/lib/hall.php';
    $sessions = db_all('SELECT id, title, starts_at FROM sessions ORDER BY sort, starts_at');
    $sid = (int)input('session', $sessions ? $sessions[0]['id'] : 0);
    aview('issue', [
        'title' => 'صدور بلیط دستی', 'sessions' => $sessions, 'sid' => $sid,
        'data' => ['layout' => layout_public_url(), 'api' => url('/admin/api/session-seats/' . $sid)],
        'scripts' => ['js/seatmap.js', 'js/admin-issue.js'],
    ]);
}

function admin_issue_submit()
{
    $admin = require_admin(['admin', 'operator']);
    $session = db_row('SELECT * FROM sessions WHERE id = ?', [(int)input('session_id')]);
    if (!$session) abort(404);
    $mobile = normalize_mobile(input('mobile'));
    if (!$mobile) {
        flash('err', 'شماره موبایل معتبر نیست.');
        redirect('/admin/issue', ['session' => $session['id']]);
    }
    $user = db_row('SELECT * FROM users WHERE mobile = ?', [$mobile]);
    if (!$user) {
        $uid = db_insert('users', ['mobile' => $mobile, 'first_name' => input('first_name') ?: null, 'last_name' => input('last_name') ?: null, 'company' => input('company') ?: null, 'created_at' => now()]);
        $user = db_row('SELECT * FROM users WHERE id = ?', [$uid]);
    } elseif (!$user['first_name'] && input('first_name') !== '') {
        db_update('users', ['first_name' => input('first_name'), 'last_name' => input('last_name') ?: null, 'company' => input('company') ?: $user['company']], 'id = ?', [(int)$user['id']]);
    }
    $ids = array_filter(array_map('intval', explode(',', (string)input('seats'))));
    $r = booking_create($user, $session, $ids, ['manual' => true, 'admin_id' => $admin['id'], 'note' => input('note')]);
    if (!$r['ok']) {
        flash('err', $r['error']);
        redirect('/admin/issue', ['session' => $session['id']]);
    }
    $paid = (int)preg_replace('/\D/', '', en_digits(input('amount')));
    db_update('orders', ['subtotal' => $paid, 'total' => $paid], 'id = ?', [(int)$r['order_id']]);
    booking_finalize($r['order_id'], ['method' => 'manual', 'ref_id' => null, 'silent' => input('send_sms') !== '1']);
    flash('ok', 'بلیط(ها) صادر شد' . (input('send_sms') === '1' ? ' و برای ' . fa($mobile) . ' پیامک شد.' : '.'));
    redirect('/admin/orders/' . $r['order_id']);
}

/* ================= Users ================= */

function admin_users()
{
    require_admin(['admin', 'operator']);
    $q = trim(en_digits(input('q')));
    $where = '1=1';
    $p = [];
    if ($q !== '') {
        $where = '(u.mobile LIKE ? OR CONCAT(u.first_name, \' \', u.last_name) LIKE ? OR u.company LIKE ?)';
        $p = ["%$q%", "%$q%", "%$q%"];
    }
    $total = (int)db_val('SELECT COUNT(*) FROM users u WHERE ' . $where, $p);
    $pg = paginate($total, 50, input('page', 1));
    $users = db_all(
        'SELECT u.*, (SELECT COUNT(*) FROM tickets t WHERE t.user_id = u.id AND t.status = \'valid\') tickets
         FROM users u WHERE ' . $where . ' ORDER BY u.id DESC LIMIT ' . (int)$pg['per'] . ' OFFSET ' . (int)$pg['offset'],
        $p
    );
    aview('users', ['title' => 'کاربران', 'users' => $users, 'pg' => $pg]);
}

function admin_user_action($id)
{
    require_admin();
    if (input('action') === 'block') db_exec('UPDATE users SET is_blocked = 1 WHERE id = ?', [(int)$id]);
    if (input('action') === 'unblock') db_exec('UPDATE users SET is_blocked = 0 WHERE id = ?', [(int)$id]);
    flash('ok', 'انجام شد.');
    back('/admin/users');
}

/* ================= Coupons ================= */

function admin_coupons()
{
    require_admin();
    $coupons = db_all('SELECT c.*, s.title FROM coupons c LEFT JOIN sessions s ON s.id = c.session_id ORDER BY c.id DESC');
    $sessions = db_all('SELECT id, title FROM sessions ORDER BY sort, starts_at');
    aview('coupons', ['title' => 'کدهای تخفیف', 'coupons' => $coupons, 'sessions' => $sessions]);
}

function admin_coupons_save()
{
    require_admin();
    $id = (int)input('id');
    if ($id && input('action') === 'toggle') {
        db_exec('UPDATE coupons SET is_active = 1 - is_active WHERE id = ?', [$id]);
        redirect('/admin/coupons');
    }
    if ($id && input('action') === 'delete') {
        db_exec('DELETE FROM coupons WHERE id = ? AND used_count = 0', [$id]);
        flash('ok', 'حذف شد (کدهای استفاده‌شده فقط غیرفعال می‌شوند).');
        redirect('/admin/coupons');
    }
    $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', en_digits(input('code'))));
    $val = (int)preg_replace('/\D/', '', en_digits(input('value')));
    if ($code === '' || $val < 1) {
        flash('err', 'کد (حروف و اعداد لاتین) و مقدار تخفیف را وارد کنید.');
        redirect('/admin/coupons');
    }
    $exp = input('expires_at') !== '' ? jalali_to_datetime(input('expires_at')) : null;
    try {
        db_insert('coupons', [
            'code' => $code, 'type' => input('type') === 'fixed' ? 'fixed' : 'percent', 'value' => $val,
            'max_uses' => input('max_uses') !== '' ? (int)en_digits(input('max_uses')) : null,
            'min_seats' => max(1, (int)en_digits(input('min_seats', '1'))),
            'session_id' => input('session_id') !== '' ? (int)input('session_id') : null,
            'expires_at' => $exp, 'is_active' => 1, 'created_at' => now(),
        ]);
        flash('ok', 'کد تخفیف ساخته شد.');
    } catch (PDOException $e) {
        flash('err', 'این کد قبلاً ثبت شده است.');
    }
    redirect('/admin/coupons');
}

/* ================= Speakers ================= */

function admin_speakers()
{
    require_admin();
    $speakers = db_all('SELECT * FROM speakers ORDER BY sort, id');
    aview('speakers', ['title' => 'سخنرانان', 'speakers' => $speakers]);
}

function admin_speakers_save()
{
    require_admin();
    $id = (int)input('id');
    if ($id && input('action') === 'delete') {
        db_exec('DELETE FROM speakers WHERE id = ?', [$id]);
        flash('ok', 'حذف شد.');
        redirect('/admin/speakers');
    }
    $data = [
        'name' => mb_substr(input('name'), 0, 120), 'title' => mb_substr(input('title'), 0, 255) ?: null,
        'bio' => input('bio') ?: null, 'sort' => (int)en_digits(input('sort', '0')), 'is_active' => input('is_active') === '1' ? 1 : 0,
    ];
    if ($data['name'] === '') {
        flash('err', 'نام را وارد کنید.');
        redirect('/admin/speakers');
    }
    if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $info = @getimagesize($_FILES['photo']['tmp_name']);
        $ext = $info ? [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null : null;
        if (!$ext || $_FILES['photo']['size'] > 2 * 1024 * 1024) {
            flash('err', 'تصویر باید JPG/PNG/WEBP و حداکثر ۲ مگابایت باشد.');
            redirect('/admin/speakers');
        }
        $name = 'uploads/sp_' . bin2hex(random_bytes(6)) . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], ROOT . '/' . $name);
        $data['photo'] = $name;
    }
    if ($id) db_update('speakers', $data, 'id = ?', [$id]);
    else db_insert('speakers', $data);
    flash('ok', 'ذخیره شد.');
    redirect('/admin/speakers');
}

/* ================= Settings ================= */

function settings_fields()
{
    return [
        'عمومی' => [
            'site_title' => ['عنوان رویداد', 'text'],
            'site_tagline' => ['شعار / زیرعنوان', 'text'],
            'event_dates' => ['تاریخ رویداد (نمایشی)', 'text'],
            'venue' => ['محل برگزاری', 'text'],
            'support_phone' => ['تلفن پشتیبانی', 'text'],
            'footer_text' => ['متن فوتر', 'text'],
            'about_title' => ['عنوان بخش معرفی', 'text'],
            'about_text' => ['متن معرفی رویداد', 'textarea'],
            'audience_text' => ['مخاطبان رویداد', 'textarea'],
        ],
        'فروش' => [
            'hold_minutes' => ['مدت نگهداری صندلی پیش از پرداخت (دقیقه)', 'number'],
        ],
        'درگاه پرداخت' => [
            'gateway' => ['درگاه فعال', 'select', ['fake' => 'آزمایشی (بدون پرداخت واقعی)', 'zarinpal' => 'زرین‌پال', 'zibal' => 'زیبال']],
            'zarinpal_merchant' => ['مرچنت کد زرین‌پال', 'text'],
            'zarinpal_sandbox' => ['زرین‌پال در حالت Sandbox', 'select', ['0' => 'خیر', '1' => 'بله']],
            'zibal_merchant' => ['مرچنت زیبال (برای تست: zibal)', 'text'],
        ],
        'پیامک (ملی پیامک)' => [
            'sms_driver' => ['روش ارسال', 'select', ['log' => 'آزمایشی (بدون ارسال، کد روی صفحه)', 'console' => 'ملی پیامک – کلید API کنسول', 'rest' => 'ملی پیامک – نام کاربری و رمز']],
            'sms_api_key' => ['کلید API کنسول ملی پیامک', 'text'],
            'sms_username' => ['نام کاربری پنل', 'text'],
            'sms_password' => ['رمز عبور پنل / کلید API', 'password'],
            'sms_from' => ['شماره خط ارسال (برای پیامک متنی)', 'text'],
            'sms_otp_body_id' => ['کد الگوی (bodyId) پیامک کد ورود', 'text'],
            'sms_ticket_body_id' => ['کد الگوی (bodyId) پیامک بلیط', 'text'],
            'sms_otp_text' => ['متن پیامک کد ورود (اگر الگو ندارید) – {code}', 'textarea'],
            'sms_ticket_text' => ['متن پیامک بلیط (اگر الگو ندارید) – {name} {session} {seats} {link}', 'textarea'],
            'otp_length' => ['تعداد ارقام کد ورود', 'select', ['4' => '۴', '5' => '۵', '6' => '۶']],
            'otp_resend_seconds' => ['فاصله ارسال مجدد کد (ثانیه)', 'number'],
        ],
    ];
}

function admin_settings()
{
    require_admin();
    aview('settings', ['title' => 'تنظیمات', 'fields' => settings_fields(), 'values' => settings_all()]);
}

function admin_settings_save_action()
{
    require_admin();
    $save = [];
    foreach (settings_fields() as $group) {
        foreach ($group as $k => $f) {
            if (!isset($_POST[$k])) continue;
            $v = trim((string)$_POST[$k]);
            if ($f[1] === 'password' && $v === '') continue; // keep existing secret
            if ($f[1] === 'number') $v = (string)(int)en_digits($v);
            $save[$k] = $v;
        }
    }
    settings_save($save);
    flash('ok', 'تنظیمات ذخیره شد.');
    redirect('/admin/settings');
}

function admin_settings_test_sms()
{
    require_admin();
    require_once APP . '/lib/sms.php';
    $m = normalize_mobile(input('mobile'));
    if (!$m) {
        flash('err', 'شماره معتبر نیست.');
        redirect('/admin/settings');
    }
    $ok = sms_send_otp($m, '12345');
    flash($ok ? 'ok' : 'err', $ok ? 'پیامک آزمایشی (کد ۱۲۳۴۵) ارسال شد.' : 'ارسال ناموفق بود. جزئیات در «گزارش پیامک‌ها».');
    redirect('/admin/settings');
}

/* ================= Staff ================= */

function admin_staff()
{
    require_admin();
    $staff = db_all('SELECT * FROM admins ORDER BY id');
    aview('staff', ['title' => 'کاربران پنل', 'staff' => $staff]);
}

function admin_staff_save()
{
    $me = require_admin();
    $id = (int)input('id');
    if ($id && input('action') === 'toggle') {
        if ($id === (int)$me['id']) {
            flash('err', 'نمی‌توانید حساب خودتان را غیرفعال کنید.');
        } else {
            db_exec('UPDATE admins SET is_active = 1 - is_active WHERE id = ?', [$id]);
        }
        redirect('/admin/staff');
    }
    $role = in_array(input('role'), ['admin', 'operator', 'checkin'], true) ? input('role') : 'checkin';
    $pass = (string)input('password');
    if ($id) {
        $d = ['name' => input('name'), 'role' => $id === (int)$me['id'] ? 'admin' : $role];
        if ($pass !== '') {
            if (strlen($pass) < 8) {
                flash('err', 'رمز عبور باید حداقل ۸ کاراکتر باشد.');
                redirect('/admin/staff');
            }
            $d['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
        }
        db_update('admins', $d, 'id = ?', [$id]);
    } else {
        $u = preg_replace('/[^a-zA-Z0-9_.-]/', '', input('username'));
        if ($u === '' || strlen($pass) < 8 || input('name') === '') {
            flash('err', 'نام، نام کاربری (لاتین) و رمز حداقل ۸ کاراکتری لازم است.');
            redirect('/admin/staff');
        }
        try {
            db_insert('admins', ['username' => $u, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'name' => input('name'), 'role' => $role, 'is_active' => 1, 'created_at' => now()]);
        } catch (PDOException $e) {
            flash('err', 'این نام کاربری تکراری است.');
            redirect('/admin/staff');
        }
    }
    flash('ok', 'ذخیره شد.');
    redirect('/admin/staff');
}

function admin_sms_logs()
{
    require_admin();
    $logs = db_all('SELECT * FROM sms_logs ORDER BY id DESC LIMIT 200');
    aview('sms_logs', ['title' => 'گزارش پیامک‌ها', 'logs' => $logs]);
}
