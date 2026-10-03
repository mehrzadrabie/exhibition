<?php
defined('ROOT') || exit;

function route_path()
{
    static $p = null;
    if ($p !== null) return $p;
    if (isset($_GET['r']) && is_string($_GET['r'])) {
        $p = '/' . trim($_GET['r'], '/');
    } else {
        $uri = rawurldecode((string)parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH));
        $bp = base_path();
        if ($bp !== '' && strpos($uri, $bp) === 0) $uri = substr($uri, strlen($bp));
        if (strpos($uri, '/index.php') === 0) $uri = substr($uri, 10);
        $p = '/' . trim($uri, '/');
    }
    return $p;
}

/*
 * [method, pattern, controller file, function, flags]
 * flags: 'nosess' = do not start a PHP session (fast, lock-free endpoints)
 */
$routes = [
    ['GET', '#^/$#', 'site', 'home'],
    ['GET', '#^/__rewrite_test$#', 'site', 'rewrite_test', 'nosess'],
    ['GET', '#^/api/seats/(\d+)$#', 'site', 'api_seats', 'nosess'],

    ['GET', '#^/login$#', 'account', 'login_form'],
    ['POST', '#^/login$#', 'account', 'login_send'],
    ['GET', '#^/login/verify$#', 'account', 'verify_form'],
    ['POST', '#^/login/verify$#', 'account', 'verify_submit'],
    ['POST', '#^/login/resend$#', 'account', 'resend'],
    ['POST', '#^/logout$#', 'account', 'logout'],
    ['GET', '#^/profile$#', 'account', 'profile_form'],
    ['POST', '#^/profile$#', 'account', 'profile_save'],
    ['GET', '#^/my$#', 'account', 'my'],

    ['GET', '#^/session/(\d+)$#', 'booking', 'seat_page'],
    ['POST', '#^/book$#', 'booking', 'book'],
    ['GET', '#^/order/(\d+)$#', 'booking', 'order_page'],
    ['POST', '#^/order/(\d+)/coupon$#', 'booking', 'order_coupon'],
    ['POST', '#^/order/(\d+)/pay$#', 'booking', 'order_pay'],
    ['POST', '#^/order/(\d+)/cancel$#', 'booking', 'order_cancel'],
    ['POST', '#^/order/(\d+)/guests$#', 'booking', 'order_guests'],
    ['GET|POST', '#^/payment/callback$#', 'booking', 'payment_callback', 'nocsrf'],
    ['GET|POST', '#^/payment/fake$#', 'booking', 'payment_fake'],
    ['GET', '#^/t/([2-9A-HJ-NP-Z]{16})$#', 'booking', 'ticket_page'],

    ['GET', '#^/admin/login$#', 'admin', 'login_form'],
    ['POST', '#^/admin/login$#', 'admin', 'login_submit'],
    ['POST', '#^/admin/logout$#', 'admin', 'logout'],
    ['GET', '#^/admin$#', 'admin', 'dashboard'],
    ['GET', '#^/admin/sessions$#', 'admin', 'sessions'],
    ['GET', '#^/admin/sessions/(new|\d+)$#', 'admin', 'session_form'],
    ['POST', '#^/admin/sessions/(new|\d+)$#', 'admin', 'session_save'],
    ['GET', '#^/admin/sessions/(\d+)/seats$#', 'admin', 'session_seats'],
    ['POST', '#^/admin/sessions/(\d+)/seats$#', 'admin', 'session_seats_save'],
    ['GET', '#^/admin/api/session-seats/(\d+)$#', 'admin', 'api_session_seats'],
    ['GET', '#^/admin/layout$#', 'admin', 'layout'],
    ['POST', '#^/admin/layout$#', 'admin', 'layout_save'],
    ['POST', '#^/admin/categories$#', 'admin', 'categories_save'],
    ['GET', '#^/admin/orders$#', 'admin', 'orders'],
    ['GET', '#^/admin/orders/(\d+)$#', 'admin', 'order_view'],
    ['POST', '#^/admin/orders/(\d+)$#', 'admin', 'order_action'],
    ['GET', '#^/admin/tickets$#', 'admin', 'tickets'],
    ['POST', '#^/admin/tickets/(\d+)$#', 'admin', 'ticket_action'],
    ['GET', '#^/admin/issue$#', 'admin', 'issue_form'],
    ['POST', '#^/admin/issue$#', 'admin', 'issue_submit'],
    ['GET', '#^/admin/users$#', 'admin', 'users'],
    ['POST', '#^/admin/users/(\d+)$#', 'admin', 'user_action'],
    ['GET', '#^/admin/coupons$#', 'admin', 'coupons'],
    ['POST', '#^/admin/coupons$#', 'admin', 'coupons_save'],
    ['GET', '#^/admin/speakers$#', 'admin', 'speakers'],
    ['POST', '#^/admin/speakers$#', 'admin', 'speakers_save'],
    ['GET', '#^/admin/settings$#', 'admin', 'settings'],
    ['POST', '#^/admin/settings$#', 'admin', 'settings_save_action'],
    ['POST', '#^/admin/settings/test-sms$#', 'admin', 'settings_test_sms'],
    ['GET', '#^/admin/staff$#', 'admin', 'staff'],
    ['POST', '#^/admin/staff$#', 'admin', 'staff_save'],
    ['GET', '#^/admin/sms$#', 'admin', 'sms_logs'],
    ['GET', '#^/admin/display$#', 'display', 'admin_page'],
    ['POST', '#^/admin/display$#', 'display', 'admin_save'],
    ['GET', '#^/display/([A-Za-z0-9]{24})$#', 'display', 'screen', 'nosess'],
    ['GET', '#^/display/([A-Za-z0-9]{24})/feed$#', 'display', 'feed', 'nosess'],
    ['GET', '#^/admin/checkin$#', 'checkin', 'scanner'],
    ['POST', '#^/admin/api/checkin$#', 'checkin', 'api_checkin'],
    ['GET', '#^/admin/api/checkin-stats$#', 'checkin', 'api_stats'],
];

$path = route_path();
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
if ($method === 'HEAD') $method = 'GET';

foreach ($routes as $r) {
    if (strpos($r[0], $method) === false) continue;
    if (!preg_match($r[1], $path, $m)) continue;
    array_shift($m);
    if (empty($r[4]) || $r[4] !== 'nosess') {
        start_session();
        if ($method === 'POST' && (empty($r[4]) || $r[4] !== 'nocsrf')) csrf_check();
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
    }
    require APP . '/controllers/' . $r[2] . '.php';
    $fn = $r[2] . '_' . $r[3];
    $fn(...$m);
    exit;
}

start_session();
abort(404);
