<?php
defined('ROOT') || exit;

/*
 * SMS via Melipayamak (ملی پیامک).
 *
 * Drivers (setting sms_driver):
 *   console : console.melipayamak.com REST API with an API key (recommended)
 *   rest    : rest.payamak-panel.com with panel username / password
 *   log     : no SMS is sent; messages are written to storage/logs/sms.log (testing)
 *
 * For OTP / ticket messages a "shared service pattern" (خط خدماتی اشتراکی / پترن)
 * is used when its bodyId is configured; patterns are delivered even to numbers
 * that blocked advertising SMS. Otherwise plain text is sent from sms_from.
 */

function sms_send_otp($mobile, $code)
{
    $bodyId = setting('sms_otp_body_id');
    if ($bodyId !== '') {
        return sms_pattern($mobile, (int)$bodyId, [$code], 'otp');
    }
    $text = str_replace('{code}', $code, setting('sms_otp_text', "کد ورود شما: {code}\n" . setting('site_title', 'همایش')));
    return sms_text($mobile, $text, 'otp');
}

/** Ticket notification. $vars: name, session, seats, link */
function sms_send_ticket($mobile, array $vars)
{
    $bodyId = setting('sms_ticket_body_id');
    if ($bodyId !== '') {
        return sms_pattern($mobile, (int)$bodyId, [$vars['name'], $vars['session'], $vars['seats'], $vars['link']], 'ticket');
    }
    $tpl = setting('sms_ticket_text', "{name} عزیز، بلیط شما برای {session} صادر شد.\n{seats}\nمشاهده بلیط:\n{link}");
    $text = strtr($tpl, ['{name}' => $vars['name'], '{session}' => $vars['session'], '{seats}' => $vars['seats'], '{link}' => $vars['link']]);
    return sms_text($mobile, $text, 'ticket');
}

function sms_pattern($mobile, $bodyId, array $args, $kind)
{
    $driver = setting('sms_driver', 'log');
    $args = array_map('strval', $args);
    if ($driver === 'console') {
        $key = setting('sms_api_key');
        list($st, $body, $err) = http_post_json('https://console.melipayamak.com/api/send/shared/' . rawurlencode($key), [
            'bodyId' => $bodyId, 'to' => $mobile, 'args' => $args,
        ], 12);
        $j = json_decode($body, true);
        $ok = $st >= 200 && $st < 300 && is_array($j) && !empty($j['recId']) && (int)$j['recId'] > 1000;
        return sms_log($mobile, $kind, $ok, $err ?: $body);
    }
    if ($driver === 'rest') {
        list($st, $body, $err) = http_post_form('https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', [
            'username' => setting('sms_username'), 'password' => setting('sms_password'),
            'text' => implode(';', $args), 'to' => $mobile, 'bodyId' => $bodyId,
        ], 12);
        $j = json_decode($body, true);
        $ok = is_array($j) && isset($j['RetStatus']) && (int)$j['RetStatus'] === 1 && strlen((string)$j['Value']) > 3;
        return sms_log($mobile, $kind, $ok, $err ?: $body);
    }
    return sms_log($mobile, $kind, true, 'LOG pattern ' . $bodyId . ': ' . implode(' | ', $args));
}

function sms_text($mobile, $text, $kind)
{
    $driver = setting('sms_driver', 'log');
    if ($driver === 'console') {
        $key = setting('sms_api_key');
        list($st, $body, $err) = http_post_json('https://console.melipayamak.com/api/send/simple/' . rawurlencode($key), [
            'from' => setting('sms_from'), 'to' => $mobile, 'text' => $text,
        ], 12);
        $j = json_decode($body, true);
        $ok = $st >= 200 && $st < 300 && is_array($j) && !empty($j['recId']) && (int)$j['recId'] > 1000;
        return sms_log($mobile, $kind, $ok, $err ?: $body);
    }
    if ($driver === 'rest') {
        list($st, $body, $err) = http_post_form('https://rest.payamak-panel.com/api/SendSMS/SendSMS', [
            'username' => setting('sms_username'), 'password' => setting('sms_password'),
            'to' => $mobile, 'from' => setting('sms_from'), 'text' => $text, 'isflash' => 'false',
        ], 12);
        $j = json_decode($body, true);
        $ok = is_array($j) && isset($j['RetStatus']) && (int)$j['RetStatus'] === 1;
        return sms_log($mobile, $kind, $ok, $err ?: $body);
    }
    return sms_log($mobile, $kind, true, 'LOG: ' . $text);
}

function sms_log($mobile, $kind, $ok, $response)
{
    try {
        db_insert('sms_logs', [
            'mobile' => $mobile, 'kind' => $kind, 'ok' => $ok ? 1 : 0,
            'response' => mb_substr((string)$response, 0, 250), 'created_at' => now(),
        ]);
    } catch (Throwable $e) {
        log_error('sms_log: ' . $e->getMessage());
    }
    if (setting('sms_driver', 'log') === 'log') {
        @file_put_contents(ROOT . '/storage/logs/sms.log', '[' . now() . "] $mobile ($kind) $response\n", FILE_APPEND | LOCK_EX);
    }
    if (!$ok) log_error("SMS failed to $mobile ($kind): $response");
    return $ok;
}
