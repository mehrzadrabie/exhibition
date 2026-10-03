<?php
defined('ROOT') || exit;

require_once APP . '/lib/hall.php';

function hold_seconds()
{
    return max(5, (int)setting('hold_minutes', 12)) * 60;
}

/**
 * Hold seats and create a pending order (atomic).
 * @return array ['ok'=>bool, 'order_id'=>int, 'error'=>string, 'taken'=>int[]]
 */
function booking_create(array $user, array $session, array $seatIds, array $opts = [])
{
    $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
    $n = count($seatIds);
    $manual = !empty($opts['manual']);
    if ($n < 1) return ['ok' => false, 'error' => 'هیچ صندلی‌ای انتخاب نشده است.'];
    if (!$manual && $n > (int)$session['max_per_order']) {
        return ['ok' => false, 'error' => 'حداکثر ' . fa($session['max_per_order']) . ' صندلی در هر سفارش قابل انتخاب است.'];
    }

    $seats = db_all('SELECT id, row_no, seat_no, category_id FROM seats WHERE is_active = 1 AND id IN (' . db_in($n) . ')', $seatIds);
    if (count($seats) !== $n) return ['ok' => false, 'error' => 'صندلی انتخاب‌شده نامعتبر است.'];
    $prices = session_prices($session['id']);
    $subtotal = 0;
    foreach ($seats as &$s) {
        $cid = (int)$s['category_id'];
        if (!$manual && !isset($prices[$cid])) return ['ok' => false, 'error' => 'برای برخی صندلی‌ها قیمتی تعریف نشده است.'];
        $s['price'] = $manual ? 0 : $prices[$cid];
        $subtotal += $s['price'];
    }
    unset($s);

    $t = time();
    $until = $t + hold_seconds();
    $sid = (int)$session['id'];

    try {
        $result = db_tx(function () use ($user, $sid, $seatIds, $n, $seats, $subtotal, $t, $until, $manual, $opts) {
        if (!$manual) {
            // One pending order per user per session: release the previous one.
            // Plain read (no FOR UPDATE on the secondary index: its gap locks would
            // serialise / deadlock unrelated buyers). Row updates below lock by primary key.
            $prev = db_col('SELECT id FROM orders WHERE user_id = ? AND session_id = ? AND status = ?', [(int)$user['id'], $sid, 'pending']);
            if ($prev) {
                $prev = array_map('intval', $prev);
                db_exec('UPDATE orders SET status = ? WHERE id IN (' . db_in(count($prev)) . ') AND status = ?', array_merge(['cancelled'], $prev, ['pending']));
                db_exec('UPDATE session_seats SET status = 0, order_id = NULL, hold_until = NULL WHERE session_id = ? AND status = 1 AND order_id IN (' . db_in(count($prev)) . ')', array_merge([$sid], $prev));
            }
        }
        $orderId = db_insert('orders', [
            'user_id' => (int)$user['id'],
            'session_id' => $sid,
            'status' => 'pending',
            'seats_count' => $n,
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'hold_until' => $until,
            'created_at' => now(),
            'note' => isset($opts['note']) ? mb_substr($opts['note'], 0, 250) : null,
            'issued_by' => isset($opts['admin_id']) ? (int)$opts['admin_id'] : null,
        ]);
        // Atomic claim: only free seats, expired holds, or (manual issue) admin-blocked seats.
        $cond = $manual ? '(status = 0 OR status = 3 OR (status = 1 AND hold_until < ?))' : '(status = 0 OR (status = 1 AND hold_until < ?))';
        $claimed = db_exec(
            'UPDATE session_seats SET status = 1, order_id = ?, hold_until = ? WHERE session_id = ? AND seat_id IN (' . db_in($n) . ') AND ' . $cond,
            array_merge([$orderId, $until, $sid], $seatIds, [$t])
        );
        if ($claimed !== $n) {
            throw new RuntimeException('SEATS_TAKEN');
        }
        $vals = [];
        $params = [];
        foreach ($seats as $s) {
            $vals[] = '(?,?,?,?)';
            array_push($params, $orderId, (int)$s['id'], (int)$s['category_id'], (int)$s['price']);
        }
        db_exec('INSERT INTO order_items (order_id, seat_id, category_id, price) VALUES ' . implode(',', $vals), $params);
        return ['ok' => true, 'order_id' => $orderId];
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'SEATS_TAKEN') throw $e;
        session_status_invalidate($sid);
        $cond = $manual ? '(status = 0 OR status = 3 OR (status = 1 AND hold_until < ?))' : '(status = 0 OR (status = 1 AND hold_until < ?))';
        $taken = db_col('SELECT seat_id FROM session_seats WHERE session_id = ? AND seat_id IN (' . db_in($n) . ') AND NOT ' . $cond, array_merge([$sid], $seatIds, [$t]));
        return ['ok' => false, 'error' => 'متأسفانه برخی از صندلی‌های انتخابی همین حالا توسط فرد دیگری رزرو شد. لطفاً صندلی دیگری انتخاب کنید.', 'taken' => array_map('intval', $taken)];
    }
    session_status_invalidate($sid);
    return $result;
}

/** Release seats of a pending order. */
function booking_cancel($orderId, $status = 'cancelled')
{
    $o = db_row('SELECT id, session_id, status FROM orders WHERE id = ?', [(int)$orderId]);
    if (!$o || $o['status'] !== 'pending') return false;
    db_tx(function () use ($o, $status) {
        db_exec('UPDATE session_seats SET status = 0, order_id = NULL, hold_until = NULL WHERE session_id = ? AND order_id = ? AND status = 1', [(int)$o['session_id'], (int)$o['id']]);
        db_exec('UPDATE orders SET status = ? WHERE id = ? AND status = ?', [$status, (int)$o['id'], 'pending']);
    });
    session_status_invalidate($o['session_id']);
    return true;
}

/** Are all seats of the order still held by it (or free)? Re-claims and extends the hold. */
function booking_reclaim(array $order, $extraSeconds)
{
    $sid = (int)$order['session_id'];
    $ids = db_col('SELECT seat_id FROM order_items WHERE order_id = ?', [(int)$order['id']]);
    $n = count($ids);
    if (!$n) return false;
    $t = time();
    $until = max($t + $extraSeconds, (int)$order['hold_until']);
    $ok = db_tx(function () use ($sid, $ids, $n, $order, $t, $until) {
        $claimed = db_exec(
            'UPDATE session_seats SET status = 1, order_id = ?, hold_until = ? WHERE session_id = ? AND seat_id IN (' . db_in($n) . ')
             AND (status = 0 OR (status = 1 AND (order_id = ? OR hold_until < ?)))',
            array_merge([(int)$order['id'], $until, $sid], $ids, [(int)$order['id'], $t])
        );
        // rowCount may be lower when values are unchanged; verify explicitly.
        $mine = (int)db_val('SELECT COUNT(*) FROM session_seats WHERE session_id = ? AND order_id = ? AND status = 1 AND seat_id IN (' . db_in($n) . ')', array_merge([$sid, (int)$order['id']], $ids));
        if ($mine !== $n) {
            // give back what we took
            db_exec('UPDATE session_seats SET status = 0, order_id = NULL, hold_until = NULL WHERE session_id = ? AND order_id = ? AND status = 1', [$sid, (int)$order['id']]);
            return false;
        }
        db_exec('UPDATE orders SET hold_until = ? WHERE id = ?', [$until, (int)$order['id']]);
        return true;
    });
    session_status_invalidate($sid);
    return $ok;
}

/**
 * Mark order paid, sell seats, issue tickets. Idempotent.
 * @return bool
 */
function booking_finalize($orderId, array $payment)
{
    try {
        $done = booking_finalize_tx($orderId, $payment);
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'SEAT_CONFLICT') throw $e;
        // Paid, but a seat was taken in between (should not happen thanks to reclaim()).
        // Keep the money trail and flag it for a manual refund / reseat by the admin.
        db_update('orders', [
            'status' => 'failed', 'ref_id' => isset($payment['ref_id']) ? $payment['ref_id'] : null,
            'card_pan' => isset($payment['card']) ? $payment['card'] : null,
            'note' => 'PAID BUT SEAT CONFLICT - refund or reseat manually',
        ], 'id = ? AND status <> ?', [(int)$orderId, 'paid']);
        log_error('SEAT_CONFLICT on paid order ' . $orderId);
        return false;
    }
    if ($done === true) {
        $o = db_row('SELECT session_id FROM orders WHERE id = ?', [(int)$orderId]);
        session_status_invalidate($o['session_id']);
        if (empty($payment['silent'])) {
            defer(function () use ($orderId) {
                booking_send_sms($orderId);
            });
        }
    }
    return (bool)$done;
}

function booking_finalize_tx($orderId, array $payment)
{
    return db_tx(function () use ($orderId, $payment) {
        $o = db_row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [(int)$orderId]);
        if (!$o) return false;
        if ($o['status'] === 'paid') return 'already';
        $sid = (int)$o['session_id'];
        $items = db_all('SELECT seat_id FROM order_items WHERE order_id = ?', [(int)$o['id']]);
        $ids = array_map('intval', array_column($items, 'seat_id'));
        $n = count($ids);
        // Sell only seats that are held by this order (or free). Never overwrite a seat
        // sold to / blocked for someone else, even if this payment was already settled.
        $sold = db_exec(
            'UPDATE session_seats SET status = 2, order_id = ?, hold_until = NULL WHERE session_id = ? AND seat_id IN (' . db_in($n) . ')
             AND (status = 0 OR (status = 1 AND order_id = ?))',
            array_merge([(int)$o['id'], $sid], $ids, [(int)$o['id']])
        );
        if ($sold !== $n) {
            $mine = (int)db_val('SELECT COUNT(*) FROM session_seats WHERE session_id = ? AND order_id = ? AND status = 2', [$sid, (int)$o['id']]);
            if ($mine !== $n) throw new RuntimeException('SEAT_CONFLICT');
        }
        db_update('orders', [
            'status' => 'paid',
            'paid_at' => now(),
            'ref_id' => isset($payment['ref_id']) ? $payment['ref_id'] : null,
            'card_pan' => isset($payment['card']) ? $payment['card'] : null,
            'method' => isset($payment['method']) ? $payment['method'] : $o['method'],
            'hold_until' => null,
        ], 'id = ?', [(int)$o['id']]);
        if ($o['coupon_id']) db_exec('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [(int)$o['coupon_id']]);
        foreach ($ids as $seatId) {
            for ($try = 0; $try < 5; $try++) {
                try {
                    db_insert('tickets', [
                        'code' => random_code(10), 'order_id' => (int)$o['id'], 'user_id' => (int)$o['user_id'],
                        'session_id' => $sid, 'seat_id' => $seatId, 'status' => 'valid', 'created_at' => now(),
                    ]);
                    break;
                } catch (PDOException $e) {
                    if ($try === 4 || strpos($e->getMessage(), 'Duplicate') === false) throw $e;
                }
            }
        }
        return true;
    });
}

function booking_send_sms($orderId)
{
    require_once APP . '/lib/sms.php';
    $o = db_row('SELECT o.id, o.user_id, u.mobile, u.first_name, u.last_name, s.title FROM orders o JOIN users u ON u.id = o.user_id JOIN sessions s ON s.id = o.session_id WHERE o.id = ?', [(int)$orderId]);
    if (!$o) return false;
    $tickets = db_all('SELECT t.code, t.guest_mobile, t.guest_name, se.row_no, se.seat_no FROM tickets t JOIN seats se ON se.id = t.seat_id WHERE t.order_id = ? AND t.status = ? ORDER BY se.row_no, se.seat_no', [(int)$orderId, 'valid']);
    if (!$tickets) return false;
    $parts = [];
    foreach ($tickets as $t) $parts[] = 'ر' . $t['row_no'] . ' ص' . $t['seat_no'];
    $link = count($tickets) === 1 ? abs_url('/t/' . ticket_token($tickets[0]['code'])) : abs_url('/order/' . $o['id']);
    db_close();
    return sms_send_ticket($o['mobile'], [
        'name' => trim($o['first_name'] . ' ' . $o['last_name']) ?: 'کاربر',
        'session' => $o['title'],
        'seats' => implode('، ', $parts),
        'link' => $link,
    ]);
}

/* ---------------- Coupons ---------------- */

/** @return array [ok, message, discount] */
function coupon_check($code, array $order)
{
    $code = strtoupper(trim(en_digits($code)));
    if ($code === '') return [false, 'کد تخفیف را وارد کنید.', 0, null];
    $c = db_row('SELECT * FROM coupons WHERE code = ? AND is_active = 1', [$code]);
    if (!$c) return [false, 'کد تخفیف معتبر نیست.', 0, null];
    if ($c['expires_at'] && strtotime($c['expires_at']) < time()) return [false, 'مهلت استفاده از این کد تمام شده است.', 0, null];
    if ($c['max_uses'] !== null && (int)$c['used_count'] >= (int)$c['max_uses']) return [false, 'ظرفیت استفاده از این کد تکمیل شده است.', 0, null];
    if ($c['session_id'] && (int)$c['session_id'] !== (int)$order['session_id']) return [false, 'این کد برای این سانس معتبر نیست.', 0, null];
    if ((int)$order['seats_count'] < (int)$c['min_seats']) return [false, 'این کد برای خرید حداقل ' . fa($c['min_seats']) . ' بلیط معتبر است.', 0, null];
    $sub = (int)$order['subtotal'];
    $d = $c['type'] === 'percent' ? (int)floor($sub * min(100, (int)$c['value']) / 100) : min($sub, (int)$c['value']);
    return [true, 'کد تخفیف اعمال شد.', $d, $c];
}

/* ---------------- Tickets ---------------- */

function ticket_sig($code)
{
    $h = hash_hmac('sha256', $code, config('app_key'), true);
    $a = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $s = '';
    for ($i = 0; $i < 6; $i++) $s .= $a[ord($h[$i]) & 31];
    return $s;
}

function ticket_token($code)
{
    return $code . ticket_sig($code);
}

/** Extract ticket code from scanned/typed text. Returns [code, signed(bool)] or null. */
function ticket_parse($raw)
{
    $raw = strtoupper(trim(en_digits((string)$raw)));
    if (preg_match('#([2-9A-HJ-NP-Z]{16})/?$#', $raw, $m)) {
        $code = substr($m[1], 0, 10);
        if (hash_equals(ticket_sig($code), substr($m[1], 10))) return [$code, true];
        return null;
    }
    if (preg_match('#^[2-9A-HJ-NP-Z]{10}$#', $raw)) return [$raw, false];
    return null;
}
