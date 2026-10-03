<?php
defined('ROOT') || exit;

/* ---------- Site users (mobile + OTP) ---------- */

function current_user()
{
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $user = db_row('SELECT * FROM users WHERE id = ? AND is_blocked = 0', [(int)$_SESSION['uid']]);
        if (!$user) unset($_SESSION['uid']);
    }
    return $user;
}

function require_user()
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : url('/');
        if (is_ajax()) json_out(['ok' => false, 'error' => 'ابتدا وارد شوید.', 'login' => url('/login')], 401);
        redirect('/login');
    }
    if (empty($u['first_name']) && !in_array(route_path(), ['/profile', '/logout'], true)) {
        $_SESSION['after_login'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : url('/');
        redirect('/profile');
    }
    return $u;
}

function login_user($userId)
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$userId;
    db_exec('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), (int)$userId]);
}

function user_display_name($u)
{
    $n = trim((isset($u['first_name']) ? $u['first_name'] : '') . ' ' . (isset($u['last_name']) ? $u['last_name'] : ''));
    return $n !== '' ? $n : fa($u['mobile']);
}

/* ---------- OTP ---------- */

function otp_hash($mobile, $code)
{
    return hash_hmac('sha256', $mobile . '|' . $code, config('app_key'));
}

/** @return array [ok(bool), message, wait seconds] */
function otp_send($mobile)
{
    $t = time();
    $row = db_row('SELECT sent_at FROM otp_codes WHERE mobile = ?', [$mobile]);
    $cool = (int)setting('otp_resend_seconds', 90);
    if ($row && $t - (int)$row['sent_at'] < $cool) {
        return [true, 'کد قبلاً ارسال شده است.', $cool - ($t - (int)$row['sent_at'])];
    }
    if (!rate_limit('otp:m:' . $mobile, 6, 3600)) return [false, 'تعداد درخواست‌های این شماره زیاد است. یک ساعت دیگر تلاش کنید.', 0];
    if (!rate_limit('otp:ip:' . client_ip(), 30, 3600)) return [false, 'تعداد درخواست‌ها از این شبکه زیاد است. کمی بعد تلاش کنید.', 0];

    $len = (int)setting('otp_length', 5);
    $code = (string)random_int((int)pow(10, $len - 1), (int)pow(10, $len) - 1);
    db_exec(
        'INSERT INTO otp_codes (mobile, code_hash, expires_at, attempts, sent_at) VALUES (?, ?, ?, 0, ?)
         ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash), expires_at = VALUES(expires_at), attempts = 0, sent_at = VALUES(sent_at)',
        [$mobile, otp_hash($mobile, $code), $t + 300, $t]
    );
    require_once APP . '/lib/sms.php';
    $ok = sms_send_otp($mobile, $code);
    if (!$ok) return [false, 'ارسال پیامک با خطا مواجه شد. لطفاً دوباره تلاش کنید.', 0];
    if (setting('sms_driver', 'log') === 'log') {
        // Test mode: show the code so the flow can be tried without an SMS panel.
        $_SESSION['otp_debug'] = $code;
    }
    return [true, 'کد تأیید ارسال شد.', $cool];
}

/** @return array [ok(bool), message] */
function otp_verify($mobile, $code)
{
    $code = preg_replace('/\D/', '', en_digits($code));
    $row = db_row('SELECT * FROM otp_codes WHERE mobile = ?', [$mobile]);
    if (!$row || (int)$row['expires_at'] < time()) return [false, 'کد منقضی شده است. دوباره درخواست کد دهید.'];
    if ((int)$row['attempts'] >= 5) return [false, 'تعداد تلاش‌ها بیش از حد مجاز است. کد جدید درخواست کنید.'];
    if (!hash_equals($row['code_hash'], otp_hash($mobile, $code))) {
        db_exec('UPDATE otp_codes SET attempts = attempts + 1 WHERE mobile = ?', [$mobile]);
        return [false, 'کد وارد‌شده صحیح نیست.'];
    }
    db_exec('DELETE FROM otp_codes WHERE mobile = ?', [$mobile]);
    return [true, ''];
}

/* ---------- Admin / staff ---------- */

function current_admin()
{
    static $admin = false;
    if ($admin !== false) return $admin;
    $admin = null;
    if (!empty($_SESSION['aid'])) {
        $admin = db_row('SELECT id, username, name, role FROM admins WHERE id = ? AND is_active = 1', [(int)$_SESSION['aid']]);
        if (!$admin) unset($_SESSION['aid']);
    }
    return $admin;
}

/** Roles: admin > operator > checkin */
function require_admin($roles = ['admin'])
{
    $a = current_admin();
    if (!$a) {
        if (is_ajax()) json_out(['ok' => false, 'error' => 'ابتدا وارد پنل شوید.'], 401);
        $_SESSION['admin_after_login'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : url('/admin');
        redirect('/admin/login');
    }
    if (!in_array($a['role'], (array)$roles, true)) {
        if ($a['role'] === 'checkin') redirect('/admin/checkin');
        abort(403, 'شما به این بخش دسترسی ندارید.');
    }
    return $a;
}

function admin_can($roles)
{
    $a = current_admin();
    return $a && in_array($a['role'], (array)$roles, true);
}

function role_label($r)
{
    $m = ['admin' => 'مدیر کل', 'operator' => 'اپراتور فروش', 'checkin' => 'کنترل ورود'];
    return isset($m[$r]) ? $m[$r] : $r;
}
