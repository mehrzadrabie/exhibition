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
