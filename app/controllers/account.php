<?php
defined('ROOT') || exit;

function account_login_form()
{
    if (current_user()) redirect('/my');
    view('site/login', ['title' => 'ورود', 'wrap' => 'narrow']);
}

function account_login_send()
{
    $mobile = normalize_mobile(input('mobile'));
    if (!$mobile) {
        flash('err', 'شماره موبایل معتبر نیست. نمونه: ۰۹۱۲۳۴۵۶۷۸۹');
        redirect('/login');
    }
    list($ok, $msg) = otp_send($mobile);
    if (!$ok) {
        flash('err', $msg);
        redirect('/login');
    }
    $_SESSION['otp_mobile'] = $mobile;
    redirect('/login/verify');
}

function account_verify_form()
{
    if (empty($_SESSION['otp_mobile'])) redirect('/login');
    $mobile = $_SESSION['otp_mobile'];
    $row = db_row('SELECT sent_at FROM otp_codes WHERE mobile = ?', [$mobile]);
    $wait = $row ? max(0, (int)setting('otp_resend_seconds', 90) - (time() - (int)$row['sent_at'])) : 0;
    $debug = isset($_SESSION['otp_debug']) ? $_SESSION['otp_debug'] : null;
    view('site/verify', ['title' => 'تأیید شماره', 'wrap' => 'narrow', 'mobile' => $mobile, 'wait' => $wait, 'debugCode' => $debug, 'scripts' => ['js/otp.js']]);
}

function account_verify_submit()
{
    if (empty($_SESSION['otp_mobile'])) redirect('/login');
    $mobile = $_SESSION['otp_mobile'];
    if (!rate_limit('otpv:ip:' . client_ip(), 40, 600)) {
        flash('err', 'تعداد تلاش‌ها زیاد است. چند دقیقه دیگر تلاش کنید.');
        redirect('/login/verify');
    }
    list($ok, $msg) = otp_verify($mobile, input('code'));
    if (!$ok) {
        flash('err', $msg);
        redirect('/login/verify');
    }
    $user = db_row('SELECT * FROM users WHERE mobile = ?', [$mobile]);
    if (!$user) {
        $id = db_insert('users', ['mobile' => $mobile, 'created_at' => now()]);
        $user = db_row('SELECT * FROM users WHERE id = ?', [$id]);
    }
    if ($user['is_blocked']) {
        flash('err', 'حساب کاربری شما مسدود شده است.');
        redirect('/login');
    }
    unset($_SESSION['otp_mobile'], $_SESSION['otp_debug']);
    $after = isset($_SESSION['after_login']) ? $_SESSION['after_login'] : url('/');
    unset($_SESSION['after_login']);
    login_user($user['id']);
    if (empty($user['first_name'])) {
        $_SESSION['after_login'] = $after;
        redirect('/profile');
    }
    header('Location: ' . (strpos($after, '/') === 0 ? $after : url('/')));
    exit;
}

function account_resend()
{
    if (empty($_SESSION['otp_mobile'])) redirect('/login');
    list($ok, $msg) = otp_send($_SESSION['otp_mobile']);
    flash($ok ? 'ok' : 'err', $msg);
    redirect('/login/verify');
}

function account_logout()
{
    unset($_SESSION['uid']);
    session_regenerate_id(true);
    redirect('/');
}

function account_profile_form()
{
    $u = require_user();
    view('site/profile', ['title' => 'مشخصات', 'wrap' => 'narrow', 'u' => $u]);
}

function account_profile_save()
{
    $u = require_user();
    $first = mb_substr(input('first_name'), 0, 60);
    $last = mb_substr(input('last_name'), 0, 80);
    if (mb_strlen($first) < 2 || mb_strlen($last) < 2) {
        flash('err', 'نام و نام خانوادگی را کامل وارد کنید.');
        redirect('/profile');
    }
    db_update('users', [
        'first_name' => $first,
        'last_name' => $last,
        'company' => mb_substr(input('company'), 0, 120) ?: null,
        'job_title' => mb_substr(input('job_title'), 0, 120) ?: null,
    ], 'id = ?', [(int)$u['id']]);
    $after = isset($_SESSION['after_login']) ? $_SESSION['after_login'] : null;
    unset($_SESSION['after_login']);
    flash('ok', 'مشخصات شما ذخیره شد.');
    if ($after && strpos($after, '/') === 0) {
        header('Location: ' . $after);
        exit;
    }
    redirect('/my');
}

function account_my()
{
    $u = require_user();
    require_once APP . '/lib/booking.php';
    $orders = db_all(
        'SELECT o.*, s.title, s.starts_at FROM orders o JOIN sessions s ON s.id = o.session_id
         WHERE o.user_id = ? AND o.status IN (\'paid\', \'pending\') ORDER BY o.id DESC LIMIT 50',
        [(int)$u['id']]
    );
    $tickets = db_all(
        'SELECT t.*, se.row_no, se.seat_no, se.section, s.title, s.starts_at FROM tickets t
         JOIN seats se ON se.id = t.seat_id JOIN sessions s ON s.id = t.session_id
         WHERE t.user_id = ? AND t.status = ? ORDER BY s.starts_at, se.row_no, se.seat_no',
        [(int)$u['id'], 'valid']
    );
    view('site/my', ['title' => 'بلیط‌های من', 'u' => $u, 'orders' => $orders, 'tickets' => $tickets]);
}
