<?php
defined('ROOT') || exit;

/* Jalali (Solar Hijri) calendar helpers. */

function g2j($gy, $gm, $gd)
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function j2g($jy, $jm, $jd)
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $days--;
        $gy += 100 * intdiv($days, 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $leap = (($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0);
    $sal = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13 && $gd > $sal[$gm]; $gm++) $gd -= $sal[$gm];
    return [$gy, $gm, $gd];
}

function jmonth_name($m)
{
    $n = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    return $n[(int)$m];
}

function jweekday_name($ts)
{
    $n = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];
    return $n[(int)date('w', $ts)];
}

/**
 * Format a timestamp / datetime string in Jalali.
 * Tokens: Y y m n d j F l H i s
 */
function jdate($format, $time = null, $persianDigits = true)
{
    if ($time === null) $time = time();
    if (!is_int($time)) $time = strtotime((string)$time);
    list($jy, $jm, $jd) = g2j((int)date('Y', $time), (int)date('n', $time), (int)date('j', $time));
    $out = '';
    $len = strlen($format);
    for ($i = 0; $i < $len; $i++) {
        $c = $format[$i];
        switch ($c) {
            case 'Y': $out .= $jy; break;
            case 'y': $out .= substr((string)$jy, 2); break;
            case 'm': $out .= str_pad($jm, 2, '0', STR_PAD_LEFT); break;
            case 'n': $out .= $jm; break;
            case 'd': $out .= str_pad($jd, 2, '0', STR_PAD_LEFT); break;
            case 'j': $out .= $jd; break;
            case 'F': $out .= jmonth_name($jm); break;
            case 'l': $out .= jweekday_name($time); break;
            case 'H': $out .= date('H', $time); break;
            case 'i': $out .= date('i', $time); break;
            case 's': $out .= date('s', $time); break;
            case '\\': $i++; $out .= isset($format[$i]) ? $format[$i] : ''; break;
            default: $out .= $c;
        }
    }
    return $persianDigits ? fa($out) : $out;
}

/** "1405/07/22 16:30" -> "2026-10-14 16:30:00" (null when invalid) */
function jalali_to_datetime($s)
{
    $s = trim(en_digits((string)$s));
    if (!preg_match('#^(\d{4})[/\-](\d{1,2})[/\-](\d{1,2})(?:\s+(\d{1,2}):(\d{2}))?$#', $s, $m)) return null;
    list($gy, $gm, $gd) = j2g((int)$m[1], (int)$m[2], (int)$m[3]);
    $h = isset($m[4]) && $m[4] !== '' ? (int)$m[4] : 0;
    $i = isset($m[5]) && $m[5] !== '' ? (int)$m[5] : 0;
    if (!checkdate($gm, $gd, $gy) || $h > 23 || $i > 59) return null;
    return sprintf('%04d-%02d-%02d %02d:%02d:00', $gy, $gm, $gd, $h, $i);
}

/** "2026-10-14 16:30:00" -> "1405/07/22 16:30" (latin digits, for form inputs) */
function datetime_to_jalali($dt)
{
    if (!$dt) return '';
    return jdate('Y/m/d H:i', strtotime($dt), false);
}
