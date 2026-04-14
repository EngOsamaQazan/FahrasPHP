<?php

function public_add($postdata, $primary, $xcrud){
    echo '<script>window.location.replace("?process='.$primary.'");</script>';
}

/** تاريخ البيع في جدول العملاء: dd-mm-yyyy (مثال 29-01-2026) — يدعم - / _ كفواصل */
function fahras_format_sell_date_list($value, $fieldname, $primary_key, $row, $xcrud)
{
    if ($value === null || $value === '' || $value === '0000-00-00') {
        return '';
    }
    $s = trim((string) $value);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
        $ts = mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
        return date('d-m-Y', $ts);
    }
    /* قيم قديمة مخزّنة كنص: 29_1_2026 أو 29-1-2026 (يوم-شهر-سنة) */
    if (preg_match('/^(\d{1,2})[\-\/_](\d{1,2})[\-\/_](\d{4})$/', $s, $m)) {
        $t = mktime(0, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3]);
        return date('d-m-Y', $t);
    }
    $normalized = str_replace('_', '-', $s);
    $ts = strtotime($normalized);
    if ($ts !== false) {
        return date('d-m-Y', $ts);
    }
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function fahras_xcrud_ui_lang()
{
    if (function_exists('auth_user')) {
        $u = auth_user();
        if (!empty($u['language'])) {
            return $u['language'];
        }
    }
    return $_COOKIE['language'] ?? 'ar';
}

/** قيم نوع التوظيف في العرض والقائمة */
function fahras_translate_employment_type($value, $fieldname, $primary_key, $row, $xcrud)
{
    if ($value === null || $value === '') {
        return '';
    }
    $lang = fahras_xcrud_ui_lang();
    $map = ($lang === 'en')
        ? [
            'employed' => 'Employed',
            'self_employed' => 'Self-employed',
            'unemployed' => 'Unemployed',
        ]
        : [
            'employed' => 'موظف',
            'self_employed' => 'يعمل لحسابه الخاص',
            'unemployed' => 'غير موظف',
        ];
    $s = trim((string) $value);
    $parts = preg_split('/\s+/', $s);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        $out[] = isset($map[$p]) ? $map[$p] : htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
    }
    return implode(' · ', $out);
}

/** حقل كفيل نعم/لا */
function fahras_translate_is_guarantor($value, $fieldname, $primary_key, $row, $xcrud)
{
    if ($value === null || $value === '') {
        return '';
    }
    $lang = fahras_xcrud_ui_lang();
    $yes = ($lang === 'en') ? 'Yes' : 'نعم';
    $no = ($lang === 'en') ? 'No' : 'لا';
    if (is_bool($value)) {
        return $value ? $yes : $no;
    }
    $v = strtolower(trim((string) $value));
    if ($v === '1' || $v === 'true' || $v === 'yes' || $v === 'نعم') {
        return $yes;
    }
    if ($v === '0' || $v === 'false' || $v === 'no' || $v === 'لا' || $v === '') {
        return $no;
    }
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}