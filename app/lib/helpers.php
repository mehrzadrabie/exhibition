<?php
defined('ROOT') || exit;

function config($key, $default = null)
{
    return isset($GLOBALS['config'][$key]) ? $GLOBALS['config'][$key] : $default;
}

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function now()
{
    return date('Y-m-d H:i:s');
}

function is_https()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    return false;
}

function is_ajax()
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
}

function base_path()
{
    static $p = null;
    if ($p === null) {
        $p = rtrim((string)parse_url(config('base_url', ''), PHP_URL_PATH), '/');
    }
    return $p;
}

/** Site-relative URL. */
function url($path = '/', array $query = [])
{
    $path = '/' . ltrim($path, '/');
    if (config('pretty_urls', true)) {
        $u = base_path() . $path;
        if ($query) $u .= '?' . http_build_query($query);
    } else {
        $u = base_path() . '/index.php?r=' . rawurlencode($path);
        if ($query) $u .= '&' . http_build_query($query);
    }
    return $u;
}

/** Absolute URL (for SMS, gateways, QR). */
function abs_url($path = '/', array $query = [])
{
    $base = rtrim(config('base_url', ''), '/');
    $host = preg_replace('#^(https?://[^/]+).*$#', '$1', $base);
    return $host . url($path, $query);
}

function asset($path)
{
    return base_path() . '/assets/' . ltrim($path, '/') . '?v=' . APP_VERSION;
}

function redirect($path, array $query = [])
{
    $to = (strpos($path, 'http') === 0) ? $path : url($path, $query);
    header('Location: ' . $to, true, 302);
    exit;
}

function back($fallback = '/')
{
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $base = rtrim(config('base_url', ''), '/');
    $host = preg_replace('#^(https?://[^/]+).*$#', '$1', $base);
    if ($ref && strpos($ref, $host) === 0) {
        header('Location: ' . $ref, true, 302);
        exit;
    }
    redirect($fallback);
}

function input($key, $default = '')
{
    if (isset($_POST[$key])) return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
    if (isset($_GET[$key])) return is_string($_GET[$key]) ? trim($_GET[$key]) : $_GET[$key];
    return $default;
}

function json_input()
{
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents('php://input');
        $data = $raw ? json_decode($raw, true) : [];
        if (!is_array($data)) $data = [];
    }
    return $data;
}

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------------- Views ---------------- */

function view($name, array $data = [], $layout = 'site/layout')
{
    $content = render($name, $data);
    if ($layout) {
        $data['content'] = $content;
        $content = render($layout, $data);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo $content;
    exit;
}

function render($__view, array $__vars = [])
{
    extract($__vars, EXTR_SKIP);
    ob_start();
    require APP . '/views/' . $__view . '.php';
    return ob_get_clean();
}

function abort($code = 404, $msg = 'صفحه مورد نظر پیدا نشد.')
{
    http_response_code($code);
    if (is_ajax()) json_out(['ok' => false, 'error' => $msg], $code);
    view('site/error', ['code' => $code, 'message' => $msg, 'title' => 'خطا']);
}

/* ---------------- Session, flash, CSRF ---------------- */

function start_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $params = [
        'lifetime' => 0,
        'path' => base_path() ?: '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    } else {
        session_set_cookie_params(0, $params['path'] . '; samesite=Lax', '', $params['secure'], true);
    }
    session_name('CNFSID');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '86400');
    $dir = ROOT . '/storage/sessions';
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    session_start();
}

function flash($type, $msg)
{
    $_SESSION['_flash'][] = [$type, $msg];
}

function flashes()
{
    $f = isset($_SESSION['_flash']) ? $_SESSION['_flash'] : [];
    unset($_SESSION['_flash']);
    return $f;
}

function old($key, $default = '')
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : $default;
}

function csrf_token()
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check()
{
    $t = isset($_POST['_csrf']) ? $_POST['_csrf'] : (isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '');
    if (empty($_SESSION['_csrf']) || !is_string($t) || !hash_equals($_SESSION['_csrf'], $t)) {
        abort(419, 'نشست شما منقضی شده است. صفحه را دوباره بارگذاری کنید.');
    }
}

/* ---------------- Formatting ---------------- */

function fa($s)
{
    return strtr((string)$s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function en_digits($s)
{
    return strtr((string)$s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

/** Toman amount -> "۱۲٬۰۰۰ تومان" */
function money($toman, $unit = true)
{
    $s = fa(number_format((int)$toman, 0, '.', '٬'));
    return $unit ? $s . ' تومان' : $s;
}

function normalize_mobile($m)
{
    $m = preg_replace('/\D+/', '', en_digits((string)$m));
    if (strpos($m, '0098') === 0) $m = substr($m, 4);
    elseif (strpos($m, '98') === 0 && strlen($m) === 12) $m = substr($m, 2);
    if (strlen($m) === 10 && $m[0] === '9') $m = '0' . $m;
    return preg_match('/^09\d{9}$/', $m) ? $m : null;
}

function section_name($s)
{
    $map = ['R' => 'راست', 'C' => 'وسط', 'L' => 'چپ'];
    return isset($map[$s]) ? $map[$s] : '';
}

function seat_label($row, $no)
{
    return 'ردیف ' . fa($row) . ' – صندلی ' . fa($no);
}

function order_status_label($s)
{
    $map = [
        'pending' => ['در انتظار پرداخت', 'warn'],
        'paid' => ['پرداخت‌شده', 'ok'],
        'cancelled' => ['لغو‌شده', 'muted'],
        'expired' => ['منقضی', 'muted'],
        'failed' => ['ناموفق', 'bad'],
        'refunded' => ['مسترد‌شده', 'bad'],
    ];
    return isset($map[$s]) ? $map[$s] : [$s, 'muted'];
}

function method_label($m)
{
    $map = ['zarinpal' => 'زرین‌پال', 'zibal' => 'زیبال', 'fake' => 'درگاه آزمایشی', 'free' => 'رایگان', 'manual' => 'صدور دستی'];
    return isset($map[$m]) ? $map[$m] : (string)$m;
}

/* ---------------- Settings (cached in a PHP file -> OPcache) ---------------- */

function settings_all()
{
    static $all = null;
    if ($all !== null) return $all;
    $file = ROOT . '/storage/cache/settings.php';
    if (is_file($file)) {
        $all = include $file;
        if (is_array($all)) return $all;
    }
    $all = [];
    foreach (db_all('SELECT k, v FROM settings') as $r) $all[$r['k']] = $r['v'];
    cache_write_php($file, $all);
    return $all;
}

function setting($key, $default = '')
{
    $all = settings_all();
    return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
}

function settings_save(array $pairs)
{
    foreach ($pairs as $k => $v) {
        db_exec('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, (string)$v]);
    }
    @unlink(ROOT . '/storage/cache/settings.php');
    if (function_exists('opcache_invalidate')) @opcache_invalidate(ROOT . '/storage/cache/settings.php', true);
}

function cache_write_php($file, $data)
{
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, '<?php return ' . var_export($data, true) . ';', LOCK_EX) !== false) {
        @rename($tmp, $file);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
    }
}

/* ---------------- Small key/value cache (APCu or files) ---------------- */

function kv_get($key)
{
    if (function_exists('apcu_fetch') && ini_get('apc.enabled')) {
        $ok = false;
        $v = apcu_fetch(cache_prefix() . $key, $ok);
        return $ok ? $v : null;
    }
    $f = ROOT . '/storage/cache/kv_' . md5($key);
    $raw = @file_get_contents($f);
    if ($raw === false) return null;
    $exp = (int)substr($raw, 0, 10);
    if ($exp < time()) return null;
    return substr($raw, 10);
}

function kv_set($key, $value, $ttl)
{
    if (function_exists('apcu_store') && ini_get('apc.enabled')) {
        apcu_store(cache_prefix() . $key, $value, $ttl);
        return;
    }
    $f = ROOT . '/storage/cache/kv_' . md5($key);
    $tmp = $f . '.' . getmypid();
    if (@file_put_contents($tmp, str_pad((string)(time() + $ttl), 10, '0', STR_PAD_LEFT) . $value) !== false) @rename($tmp, $f);
}

function kv_del($key)
{
    if (function_exists('apcu_delete') && ini_get('apc.enabled')) {
        apcu_delete(cache_prefix() . $key);
        return;
    }
    @unlink(ROOT . '/storage/cache/kv_' . md5($key));
}

function cache_prefix()
{
    return 'cnf_' . substr(md5(ROOT), 0, 8) . '_';
}

/* ---------------- Rate limiting ---------------- */

/** Returns true when allowed. */
function rate_limit($key, $max, $window)
{
    $t = time();
    db_exec(
        'INSERT INTO rate_limits (k, hits, reset_at) VALUES (?, 1, ?)
         ON DUPLICATE KEY UPDATE hits = IF(reset_at < ?, 1, hits + 1), reset_at = IF(reset_at < ?, ?, reset_at)',
        [$key, $t + $window, $t, $t, $t + $window]
    );
    $hits = (int)db_val('SELECT hits FROM rate_limits WHERE k = ?', [$key]);
    return $hits <= $max;
}

function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/* ---------------- After-response tasks ---------------- */

function defer(callable $fn)
{
    static $registered = false;
    $GLOBALS['_deferred'][] = $fn;
    if (!$registered) {
        $registered = true;
        register_shutdown_function(function () {
            if (function_exists('fastcgi_finish_request')) {
                @session_write_close();
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                @session_write_close();
                litespeed_finish_request();
            }
            ignore_user_abort(true);
            foreach ($GLOBALS['_deferred'] as $f) {
                try {
                    $f();
                } catch (Throwable $e) {
                    log_error((string)$e);
                }
            }
        });
    }
}

/* ---------------- HTTP client ---------------- */

function http_post_json($url, array $body, $timeout = 15)
{
    return http_request($url, json_encode($body, JSON_UNESCAPED_UNICODE), ['Content-Type: application/json', 'Accept: application/json'], $timeout);
}

function http_post_form($url, array $body, $timeout = 15)
{
    return http_request($url, http_build_query($body), ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], $timeout);
}

/** @return array [status, body, error] */
function http_request($url, $payload, array $headers, $timeout = 15)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'ConferenceApp/' . APP_VERSION,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, $body === false ? '' : $body, $err];
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $payload,
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    if (!empty($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $status = (int)$m[1];
    return [$status, $body === false ? '' : $body, $body === false ? 'request failed' : ''];
}

/* ---------------- Logging ---------------- */

function log_error($msg)
{
    @file_put_contents(ROOT . '/storage/logs/app-' . date('Y-m') . '.log', '[' . now() . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function random_code($len, $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ')
{
    $max = strlen($alphabet) - 1;
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $alphabet[random_int(0, $max)];
    return $s;
}

function paginate($total, $perPage, $page)
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, (int)$page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}
