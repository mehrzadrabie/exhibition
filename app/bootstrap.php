<?php
defined('ROOT') || exit;

define('APP_VERSION', '1.0.0');

$GLOBALS['config'] = require APP . '/config.php';

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

if (!empty($GLOBALS['config']['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');

require APP . '/lib/helpers.php';
require APP . '/lib/db.php';
require APP . '/lib/jdate.php';
require APP . '/lib/auth.php';

set_exception_handler(function ($e) {
    if ($e instanceof DbBusyException) {
        // Peak traffic: ask the browser to retry shortly instead of showing an error
        log_error('BUSY ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
        if (!headers_sent()) {
            http_response_code(503);
            header('Retry-After: 3');
            header('Cache-Control: no-store');
        }
        if (is_ajax()) {
            if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'busy' => true, 'error' => 'سرور در حال حاضر شلوغ است. چند ثانیه دیگر دوباره تلاش کنید.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $isGet = !isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] === 'GET';
        echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . ($isGet ? '<meta http-equiv="refresh" content="4">' : '')
            . '<title>لطفاً چند لحظه صبر کنید</title><body style="font-family:Vazirmatn,tahoma;padding:60px 20px;text-align:center;background:#f6f7f9;color:#16181d">'
            . '<h2>استقبال از همایش زیاد است!</h2><p>' . ($isGet ? 'صفحه تا چند ثانیه دیگر خودکار دوباره بارگذاری می‌شود…' : 'لطفاً چند ثانیه دیگر دوباره تلاش کنید. اطلاعات شما ثبت نشده است.') . '</p>'
            . '<p><a href="javascript:location.reload()">تلاش مجدد</a></p></body></html>';
        return;
    }
    log_error((string)$e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (is_ajax()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'خطای داخلی سرور. لطفاً دوباره تلاش کنید.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    $msg = !empty($GLOBALS['config']['debug']) ? e((string)$e) : 'خطای داخلی سرور رخ داد. لطفاً چند لحظه دیگر دوباره تلاش کنید.';
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>خطا</title><body style="font-family:tahoma;padding:40px;text-align:center"><h2>خطا</h2><pre style="white-space:pre-wrap;text-align:left;direction:ltr">'
        . $msg . '</pre><a href="' . e(url('/')) . '">بازگشت به صفحه اصلی</a></body></html>';
});
