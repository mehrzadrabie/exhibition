<?php
defined('ROOT') || exit;

/*
 * Payment gateways. Amounts in the DB are Toman; gateways receive Rial.
 * gateway setting: zarinpal | zibal | fake
 */

function gateway_name()
{
    return setting('gateway', 'fake');
}

/** @return array ['ok'=>bool, 'url'=>string, 'authority'=>string, 'error'=>string] */
function gateway_request(array $order, array $user)
{
    $gw = gateway_name();
    $rial = (int)$order['total'] * 10;
    $callback = abs_url('/payment/callback', ['oid' => $order['id']]);
    $desc = 'خرید بلیط ' . setting('site_title', 'همایش') . ' - سفارش ' . $order['id'];

    if ($gw === 'zarinpal') {
        $sandbox = setting('zarinpal_sandbox') === '1';
        $host = $sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
        list($st, $body, $err) = http_post_json($host . '/pg/v4/payment/request.json', [
            'merchant_id' => setting('zarinpal_merchant'),
            'amount' => $rial,
            'callback_url' => $callback,
            'description' => $desc,
            'metadata' => ['mobile' => $user['mobile'], 'order_id' => (string)$order['id']],
        ]);
        $j = json_decode($body, true);
        if (isset($j['data']['code']) && (int)$j['data']['code'] === 100 && !empty($j['data']['authority'])) {
            $a = $j['data']['authority'];
            return ['ok' => true, 'authority' => $a, 'url' => $host . '/pg/StartPay/' . $a];
        }
        log_error('zarinpal request failed: ' . $st . ' ' . $err . ' ' . $body);
        return ['ok' => false, 'error' => 'اتصال به درگاه زرین‌پال ناموفق بود' . (isset($j['errors']['code']) ? ' (کد ' . $j['errors']['code'] . ')' : '') . '.'];
    }

    if ($gw === 'zibal') {
        list($st, $body, $err) = http_post_json('https://gateway.zibal.ir/v1/request', [
            'merchant' => setting('zibal_merchant', 'zibal'),
            'amount' => $rial,
            'callbackUrl' => $callback,
            'description' => $desc,
            'orderId' => (string)$order['id'],
            'mobile' => $user['mobile'],
        ]);
        $j = json_decode($body, true);
        if (isset($j['result']) && (int)$j['result'] === 100 && !empty($j['trackId'])) {
            return ['ok' => true, 'authority' => (string)$j['trackId'], 'url' => 'https://gateway.zibal.ir/start/' . $j['trackId']];
        }
        log_error('zibal request failed: ' . $st . ' ' . $err . ' ' . $body);
        return ['ok' => false, 'error' => 'اتصال به درگاه زیبال ناموفق بود' . (isset($j['result']) ? ' (کد ' . $j['result'] . ')' : '') . '.'];
    }

    // fake gateway for testing
    $a = 'FAKE' . random_code(12);
    return ['ok' => true, 'authority' => $a, 'url' => url('/payment/fake', ['oid' => $order['id'], 'a' => $a])];
}

/**
 * Read callback params and decide whether the user reports success.
 * @return array ['authority'=>string, 'success'=>bool]
 */
function gateway_callback_params($method)
{
    if ($method === 'zarinpal') {
        return ['authority' => (string)input('Authority'), 'success' => input('Status') === 'OK'];
    }
    if ($method === 'zibal') {
        return ['authority' => (string)input('trackId'), 'success' => input('success') === '1'];
    }
    return ['authority' => (string)input('a'), 'success' => input('status') === 'ok'];
}

/** @return array ['ok'=>bool, 'ref_id'=>string, 'card'=>string, 'error'=>string] */
function gateway_verify(array $order)
{
    $method = $order['method'];
    $rial = (int)$order['total'] * 10;

    if ($method === 'zarinpal') {
        $sandbox = setting('zarinpal_sandbox') === '1';
        $host = $sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
        list($st, $body, $err) = http_post_json($host . '/pg/v4/payment/verify.json', [
            'merchant_id' => setting('zarinpal_merchant'),
            'amount' => $rial,
            'authority' => $order['authority'],
        ]);
        $j = json_decode($body, true);
        $code = isset($j['data']['code']) ? (int)$j['data']['code'] : 0;
        if ($code === 100 || $code === 101) {
            return ['ok' => true, 'ref_id' => (string)$j['data']['ref_id'], 'card' => isset($j['data']['card_pan']) ? (string)$j['data']['card_pan'] : ''];
        }
        log_error('zarinpal verify failed: ' . $st . ' ' . $err . ' ' . $body);
        return ['ok' => false, 'error' => 'تأیید پرداخت ناموفق بود' . (isset($j['errors']['code']) ? ' (کد ' . $j['errors']['code'] . ')' : '') . '.'];
    }

    if ($method === 'zibal') {
        list($st, $body, $err) = http_post_json('https://gateway.zibal.ir/v1/verify', [
            'merchant' => setting('zibal_merchant', 'zibal'),
            'trackId' => $order['authority'],
        ]);
        $j = json_decode($body, true);
        $res = isset($j['result']) ? (int)$j['result'] : 0;
        if (($res === 100 || $res === 201) && (!isset($j['amount']) || (int)$j['amount'] === $rial)) {
            return ['ok' => true, 'ref_id' => isset($j['refNumber']) ? (string)$j['refNumber'] : (string)$order['authority'], 'card' => isset($j['cardNumber']) ? (string)$j['cardNumber'] : ''];
        }
        log_error('zibal verify failed: ' . $st . ' ' . $err . ' ' . $body);
        return ['ok' => false, 'error' => 'تأیید پرداخت ناموفق بود' . ($res ? ' (کد ' . $res . ')' : '') . '.'];
    }

    if ($method === 'fake') {
        return ['ok' => true, 'ref_id' => 'TEST-' . random_code(8, '0123456789'), 'card' => '6037****1234'];
    }
    return ['ok' => false, 'error' => 'درگاه نامعتبر است.'];
}
