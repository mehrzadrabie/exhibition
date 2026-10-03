<?php
defined('ROOT') || exit;

require_once APP . '/lib/booking.php';

function booking_load_session($id, $forSale = true)
{
    $s = db_row('SELECT * FROM sessions WHERE id = ?', [(int)$id]);
    if (!$s || (!$s['is_public'] && !current_admin())) abort(404, 'سانس مورد نظر پیدا نشد.');
    return $s;
}

function booking_seat_page($id)
{
    $session = booking_load_session($id);
    $u = current_user();
    $mine = [];
    $pending = null;
    if ($u) {
        $pending = db_row('SELECT * FROM orders WHERE user_id = ? AND session_id = ? AND status = ? AND hold_until >= ? ORDER BY id DESC LIMIT 1', [(int)$u['id'], (int)$session['id'], 'pending', time()]);
        if ($pending) $mine = array_map('intval', db_col('SELECT seat_id FROM order_items WHERE order_id = ?', [(int)$pending['id']]));
    }
    $prices = session_prices($session['id']);
    if (!is_file(session_status_file($session['id']))) session_status_publish($session['id']);
    $data = [
        'sid' => (int)$session['id'],
        'layout' => layout_public_url(),
        'status' => session_status_public_url($session['id']),
        'api' => url('/api/seats/' . $session['id']),
        'prices' => (object)$prices,
        'max' => (int)$session['max_per_order'],
        'mine' => $mine,
        'open' => (bool)$session['sale_open'],
        'logged' => (bool)$u,
    ];
    view('site/seats', [
        'title' => $session['title'], 'session' => $session, 'data' => $data, 'pending' => $pending,
        'scripts' => ['js/seatmap.js', 'js/booking.js'], 'bodyClass' => 'has-mobile-bar',
    ]);
}

function booking_book()
{
    $u = require_user();
    $session = booking_load_session(input('session_id'));
    if (!$session['sale_open']) {
        flash('err', 'فروش این سانس در حال حاضر فعال نیست.');
        redirect('/session/' . $session['id']);
    }
    if (strtotime($session['starts_at']) + 3600 < time()) {
        flash('err', 'زمان این سانس گذشته است.');
        redirect('/');
    }
    if (!rate_limit('book:u:' . $u['id'], 30, 600)) {
        flash('err', 'تعداد درخواست‌ها زیاد است. چند دقیقه دیگر تلاش کنید.');
        redirect('/session/' . $session['id']);
    }
    $ids = array_filter(array_map('intval', explode(',', (string)input('seats'))));
    $r = booking_create($u, $session, $ids);
    if (!$r['ok']) {
        flash('err', $r['error']);
        redirect('/session/' . $session['id']);
    }
    redirect('/order/' . $r['order_id']);
}

function booking_load_order($id, array $u)
{
    $o = db_row('SELECT * FROM orders WHERE id = ? AND user_id = ?', [(int)$id, (int)$u['id']]);
    if (!$o) abort(404, 'سفارش پیدا نشد.');
    return $o;
}

function booking_order_page($id)
{
    $u = require_user();
    $o = booking_load_order($id, $u);
    if ($o['status'] === 'pending' && (int)$o['hold_until'] < time()) {
        // Hold expired: release unless seats are still untouched
        booking_cancel($o['id'], 'expired');
        $o['status'] = 'expired';
    }
    $session = db_row('SELECT * FROM sessions WHERE id = ?', [(int)$o['session_id']]);
    $items = db_all(
        'SELECT oi.*, se.row_no, se.seat_no, se.section, c.name cat_name, c.color FROM order_items oi
         JOIN seats se ON se.id = oi.seat_id LEFT JOIN categories c ON c.id = oi.category_id
         WHERE oi.order_id = ? ORDER BY se.row_no, se.seat_no',
        [(int)$o['id']]
    );
    $tickets = [];
    if ($o['status'] === 'paid') {
        $tickets = db_all(
            'SELECT t.*, se.row_no, se.seat_no, se.section FROM tickets t JOIN seats se ON se.id = t.seat_id
             WHERE t.order_id = ? ORDER BY se.row_no, se.seat_no',
            [(int)$o['id']]
        );
    }
    $coupon = $o['coupon_id'] ? db_row('SELECT code FROM coupons WHERE id = ?', [(int)$o['coupon_id']]) : null;
    view('site/order', [
        'title' => 'سفارش ' . fa($o['id']), 'o' => $o, 'u' => $u, 'session' => $session, 'items' => $items,
        'tickets' => $tickets, 'coupon' => $coupon, 'scripts' => ['js/order.js'],
    ]);
}

function booking_order_coupon($id)
{
    $u = require_user();
    $o = booking_load_order($id, $u);
    if ($o['status'] !== 'pending') redirect('/order/' . $o['id']);
    if (input('remove') === '1') {
        db_update('orders', ['coupon_id' => null, 'discount' => 0, 'total' => (int)$o['subtotal']], 'id = ?', [(int)$o['id']]);
        redirect('/order/' . $o['id']);
    }
    if (!rate_limit('coupon:u:' . $u['id'], 15, 600)) {
        flash('err', 'تعداد تلاش‌ها زیاد است.');
        redirect('/order/' . $o['id']);
    }
    list($ok, $msg, $disc, $c) = coupon_check(input('code'), $o);
    if ($ok) {
        db_update('orders', ['coupon_id' => (int)$c['id'], 'discount' => $disc, 'total' => max(0, (int)$o['subtotal'] - $disc)], 'id = ?', [(int)$o['id']]);
    }
    flash($ok ? 'ok' : 'err', $msg);
    redirect('/order/' . $o['id']);
}

function booking_order_pay($id)
{
    $u = require_user();
    $o = booking_load_order($id, $u);
    if ($o['status'] === 'paid') redirect('/order/' . $o['id']);
    if ($o['status'] !== 'pending') {
        flash('err', 'این سفارش قابل پرداخت نیست. لطفاً دوباره صندلی انتخاب کنید.');
        redirect('/session/' . $o['session_id']);
    }
    // re-validate coupon right before payment
    if ($o['coupon_id']) {
        $code = db_val('SELECT code FROM coupons WHERE id = ?', [(int)$o['coupon_id']]);
        list($ok, $msg, $disc) = coupon_check((string)$code, $o);
        if (!$ok || $disc !== (int)$o['discount']) {
            db_update('orders', ['coupon_id' => null, 'discount' => 0, 'total' => (int)$o['subtotal']], 'id = ?', [(int)$o['id']]);
            flash('err', 'کد تخفیف دیگر معتبر نیست و از سفارش حذف شد.');
            redirect('/order/' . $o['id']);
        }
    }
    // Keep the seats reserved while the user is at the bank (gateways allow ~15 minutes)
    if (!booking_reclaim($o, 17 * 60)) {
        booking_cancel($o['id'], 'expired');
        flash('err', 'مهلت رزرو صندلی‌ها تمام شده و برخی از آن‌ها توسط فرد دیگری انتخاب شده‌اند. لطفاً دوباره انتخاب کنید.');
        redirect('/session/' . $o['session_id']);
    }
    $o = db_row('SELECT * FROM orders WHERE id = ?', [(int)$o['id']]);

    if ((int)$o['total'] === 0) {
        booking_finalize($o['id'], ['method' => 'free', 'ref_id' => null]);
        flash('ok', 'بلیط‌های شما صادر شد.');
        redirect('/order/' . $o['id']);
    }

    require_once APP . '/lib/payment.php';
    require_once APP . '/lib/hall.php';
    db_close(); // the bank API can take a few seconds; free the DB slot meanwhile
    $res = gateway_request($o, $u);
    if (!$res['ok']) {
        flash('err', $res['error'] . ' لطفاً دوباره تلاش کنید.');
        redirect('/order/' . $o['id']);
    }
    db_update('orders', ['method' => gateway_name(), 'authority' => $res['authority']], 'id = ?', [(int)$o['id']]);
    header('Location: ' . $res['url'], true, 302);
    exit;
}

function booking_order_cancel($id)
{
    $u = require_user();
    $o = booking_load_order($id, $u);
    if ($o['status'] === 'pending') {
        booking_cancel($o['id']);
        flash('ok', 'سفارش لغو شد و صندلی‌ها آزاد شدند.');
    }
    redirect('/session/' . $o['session_id']);
}

function booking_order_guests($id)
{
    $u = require_user();
    $o = booking_load_order($id, $u);
    if ($o['status'] !== 'paid') redirect('/order/' . $o['id']);
    $names = isset($_POST['guest_name']) && is_array($_POST['guest_name']) ? $_POST['guest_name'] : [];
    $mobiles = isset($_POST['guest_mobile']) && is_array($_POST['guest_mobile']) ? $_POST['guest_mobile'] : [];
    $send = input('send_sms') === '1';
    $session = db_row('SELECT title FROM sessions WHERE id = ?', [(int)$o['session_id']]);
    $tickets = db_all('SELECT t.*, se.row_no, se.seat_no FROM tickets t JOIN seats se ON se.id = t.seat_id WHERE t.order_id = ? AND t.status = ?', [(int)$o['id'], 'valid']);
    $bad = 0;
    $toSend = [];
    foreach ($tickets as $t) {
        $tid = $t['id'];
        $name = isset($names[$tid]) ? mb_substr(trim((string)$names[$tid]), 0, 120) : '';
        $rawM = isset($mobiles[$tid]) ? trim((string)$mobiles[$tid]) : '';
        $mob = $rawM === '' ? null : normalize_mobile($rawM);
        if ($rawM !== '' && !$mob) {
            $bad++;
            continue;
        }
        db_update('tickets', ['guest_name' => $name !== '' ? $name : null, 'guest_mobile' => $mob], 'id = ?', [(int)$tid]);
        if ($send && $mob && ($mob !== $t['guest_mobile'] || input('resend') === '1')) $toSend[] = [$mob, $name, $t];
    }
    if ($toSend) {
        if (!rate_limit('guestsms:u:' . $u['id'], 40, 86400)) {
            flash('err', 'سقف ارسال پیامک برای امروز تکمیل شده است.');
            redirect('/order/' . $o['id']);
        }
        defer(function () use ($toSend, $session) {
            require_once APP . '/lib/sms.php';
            foreach ($toSend as $x) {
                list($mob, $name, $t) = $x;
                sms_send_ticket($mob, [
                    'name' => $name ?: 'مهمان گرامی', 'session' => $session['title'],
                    'seats' => 'ردیف ' . $t['row_no'] . ' صندلی ' . $t['seat_no'],
                    'link' => abs_url('/t/' . ticket_token($t['code'])),
                ]);
            }
        });
    }
    flash($bad ? 'warn' : 'ok', $bad ? 'برخی شماره‌ها نامعتبر بودند و ذخیره نشدند.' : ('اطلاعات مهمانان ذخیره شد' . ($toSend ? ' و بلیط برای ' . fa(count($toSend)) . ' نفر پیامک شد.' : '.')));
    redirect('/order/' . $o['id']);
}

function booking_payment_callback()
{
    require_once APP . '/lib/payment.php';
    $oid = (int)input('oid');
    $o = db_row('SELECT * FROM orders WHERE id = ?', [$oid]);
    if (!$o) abort(404, 'سفارش پیدا نشد.');
    if ($o['status'] === 'paid') redirect('/order/' . $o['id']);

    $p = gateway_callback_params($o['method']);
    if (!$o['authority'] || !hash_equals((string)$o['authority'], (string)$p['authority'])) {
        flash('err', 'اطلاعات بازگشتی از درگاه معتبر نیست.');
        redirect('/order/' . $o['id']);
    }
    if ($o['method'] === 'fake' && gateway_name() !== 'fake') abort(400, 'درگاه آزمایشی غیرفعال است.');
    if (!$p['success']) {
        flash('err', 'پرداخت انجام نشد یا توسط شما لغو شد. می‌توانید دوباره تلاش کنید.');
        redirect('/order/' . $o['id']);
    }
    if (!in_array($o['status'], ['pending', 'expired', 'cancelled'], true)) {
        flash('err', 'وضعیت سفارش برای تأیید پرداخت مناسب نیست.');
        redirect('/order/' . $o['id']);
    }
    // Make sure the seats are still ours BEFORE settling the payment.
    // If not, we do not verify; the gateway automatically refunds unverified payments.
    if (!booking_reclaim($o, 10 * 60)) {
        // A parallel callback (double click / refresh) may have just completed the order
        if (db_val('SELECT status FROM orders WHERE id = ?', [(int)$o['id']]) === 'paid') redirect('/order/' . $o['id']);
        db_update('orders', ['status' => 'failed', 'note' => 'seats lost before verify'], 'id = ? AND status <> ?', [(int)$o['id'], 'paid']);
        flash('err', 'متأسفانه صندلی‌های این سفارش در این فاصله به فروش رفتند. پرداخت شما تأیید نشد و مبلغ حداکثر ظرف ۷۲ ساعت توسط بانک به حساب شما بازمی‌گردد.');
        redirect('/order/' . $o['id']);
    }
    if ($o['status'] !== 'pending') db_update('orders', ['status' => 'pending'], 'id = ?', [(int)$o['id']]);
    $v = gateway_verify($o);
    if (!$v['ok']) {
        flash('err', $v['error'] . ' در صورت کسر وجه، مبلغ ظرف ۷۲ ساعت بازمی‌گردد.');
        redirect('/order/' . $o['id']);
    }
    if (!booking_finalize($o['id'], ['method' => $o['method'], 'ref_id' => $v['ref_id'], 'card' => $v['card']])) {
        flash('err', 'پرداخت شما ثبت شد اما در تخصیص صندلی مشکلی پیش آمد. پشتیبانی با شما تماس می‌گیرد (کد پیگیری: ' . $v['ref_id'] . ').');
        redirect('/order/' . $o['id']);
    }
    flash('ok', 'پرداخت با موفقیت انجام شد و بلیط‌های شما صادر شد.');
    redirect('/order/' . $o['id']);
}

/** Simulated bank page (only when gateway = fake). */
function booking_payment_fake()
{
    require_once APP . '/lib/payment.php';
    if (gateway_name() !== 'fake') abort(404);
    $o = db_row('SELECT * FROM orders WHERE id = ?', [(int)input('oid')]);
    if (!$o || $o['authority'] !== input('a')) abort(404);
    view('site/fake_gateway', ['title' => 'درگاه آزمایشی', 'o' => $o, 'wrap' => 'narrow']);
}

function booking_ticket_page($token)
{
    $parsed = ticket_parse($token);
    if (!$parsed || !$parsed[1]) abort(404, 'بلیط پیدا نشد.');
    $t = db_row(
        'SELECT t.*, se.row_no, se.seat_no, se.section, se.id seat_id, c.name cat_name, c.color, s.title, s.subtitle, s.starts_at,
                u.first_name, u.last_name, u.mobile, u.company
         FROM tickets t JOIN seats se ON se.id = t.seat_id LEFT JOIN categories c ON c.id = se.category_id
         JOIN sessions s ON s.id = t.session_id JOIN users u ON u.id = t.user_id WHERE t.code = ?',
        [$parsed[0]]
    );
    if (!$t) abort(404, 'بلیط پیدا نشد.');
    require_once APP . '/lib/hall.php';
    $holder = $t['guest_name'] ?: trim($t['first_name'] . ' ' . $t['last_name']);
    view('site/ticket', [
        'title' => 'بلیط ' . $holder, 't' => $t, 'holder' => $holder, 'token' => $token,
        'qrText' => abs_url('/t/' . $token), 'layout' => layout_public_url(),
        'scripts' => ['vendor/qrcode.min.js', 'js/seatmap.js', 'js/ticket.js'],
    ]);
}
