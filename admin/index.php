<?php
$token = 'mojeer';
$page_title = 'لوحة التحكم';
include 'header.php';

require_permission('dashboard', 'view');

require_once __DIR__ . '/../includes/violation_engine.php';

// formatTo12h() is now in bootstrap.php

function safe_remote_search($url, $label = '') {
    $raw = curl_load($url);

    if ($raw === false || $raw === null || trim($raw) === '') {
        return ['ok' => false, 'label' => $label, 'data' => [], 'error' => _e('لا استجابة من الخادم')];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return ['ok' => false, 'label' => $label, 'data' => [], 'error' => _e('صيغة استجابة غير صالحة')];
    }

    if (isset($decoded['data']) && is_array($decoded['data'])) {
        $decoded = $decoded['data'];
    }

    return ['ok' => true, 'label' => $label, 'data' => is_array($decoded) ? $decoded : [], 'error' => null];
}

function isPhoneNumber($number) {
    return preg_match('/^0\d{9}$/', $number);
}

function normalizeArabic($text) {
    $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
    $text = str_replace('ة', 'ه', $text);
    $text = str_replace('ى', 'ي', $text);
    $text = mb_strtolower($text, 'UTF-8');
    return $text;
}

$totalClients = 0;
$monthViolations = 0;
$unpaidViolations = 0;
$totalAccounts = 0;

if (user_can('clients', 'view')) {
    $totalClients = $db->get_count('clients');
    $thisMonth = date('Y-m-01');
    try {
        $monthViolations = $db->get_count('violations', ['violation_month' => $thisMonth]);
    } catch (Throwable $e) { $monthViolations = 0; }
    try {
        $unpaidViolations = $db->get_count('violations', ['is_paid' => 0, 'status' => 'active']);
    } catch (Throwable $e) { $unpaidViolations = 0; }
    $totalAccounts = $db->get_count('accounts');
}
?>

<style>
/* ═══════════════════════════════════════════════════ */
/*  Fahras — Full-page dark gradient theme            */
/* ═══════════════════════════════════════════════════ */

.fahras-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}

/* ─── Brand ─── */
.fahras-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-bottom: 28px;
}
.fahras-brand img {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: rgba(255,255,255,0.1);
    padding: 5px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.fahras-brand-text h1 {
    margin: 0;
    font-size: 26px;
    font-weight: 800;
    color: #fff;
    letter-spacing: 1px;
}
.fahras-brand-text p {
    margin: 2px 0 0;
    font-size: 12px;
    opacity: 0.5;
    color: #cbd5e1;
}

/* ─── Stats ─── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 12px;
    margin-bottom: 28px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}
.stat-card {
    background: rgba(255,255,255,0.06);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 14px;
    text-align: center;
    transition: transform 0.2s, background 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    background: rgba(255,255,255,0.12);
}
.stat-card .stat-icon { font-size: 20px; opacity: 0.6; margin-bottom: 4px; }
.stat-card .stat-value { font-size: 24px; font-weight: 800; color: #fff; }
.stat-card .stat-label { font-size: 10px; opacity: 0.5; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-card.danger .stat-value { color: #fc8181; }

/* ─── Search ─── */
.search-container {
    max-width: 640px;
    margin: 0 auto 32px;
}
.search-box {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
    backdrop-filter: blur(12px);
}
.search-box:focus-within {
    border-color: rgba(99,179,237,0.5);
    box-shadow: 0 8px 40px rgba(31,98,185,0.35), 0 0 0 1px rgba(99,179,237,0.2);
}
.search-box .search-icon {
    position: absolute;
    right: 18px;
    color: rgba(255,255,255,0.3);
    font-size: 18px;
    pointer-events: none;
    z-index: 2;
}
.search-box input {
    flex: 1;
    border: none;
    padding: 16px 50px 16px 18px;
    font-size: 15px;
    font-family: 'Almarai', sans-serif;
    background: transparent;
    color: #e2e8f0;
    outline: none;
    min-width: 0;
}
.search-box input::placeholder { color: rgba(255,255,255,0.3); font-size: 13px; }
.search-box button {
    background: linear-gradient(135deg, #1f62b9 0%, #2980b9 100%);
    border: none;
    border-right: 1px solid rgba(255,255,255,0.08);
    color: #fff;
    padding: 16px 28px;
    font-size: 14px;
    font-family: 'Almarai', sans-serif;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.search-box button:hover { background: linear-gradient(135deg, #2980b9 0%, #3498db 100%); }
.search-box button:disabled { opacity: 0.5; cursor: not-allowed; }
.search-hint {
    text-align: center;
    margin-top: 10px;
    font-size: 11px;
    opacity: 0.35;
    color: #cbd5e1;
}
.search-loading {
    display: none;
    text-align: center;
    padding: 60px 20px;
    color: rgba(255,255,255,0.5);
}
.search-loading.active { display: block; }
.search-loading i { font-size: 32px; margin-bottom: 14px; display: block; animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* ─── Results header ─── */
.results-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    margin-bottom: 18px;
}
.results-bar h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #e2e8f0;
}
.results-bar h4 b { color: #63b3ed; }
.results-bar .badge-count {
    background: #1f62b9;
    color: #fff;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

/* ─── Remote errors ─── */
.remote-warning {
    background: rgba(251,191,36,0.08);
    border: 1px solid rgba(251,191,36,0.2);
    border-right: 4px solid rgba(251,191,36,0.6);
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 14px;
    font-size: 12px;
    color: #fbd38d;
    line-height: 1.8;
}
.remote-warning b { color: #f6e05e; }
.remote-warning .api-error-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    margin-top: 6px;
    background: rgba(0,0,0,0.15);
    border-radius: 6px;
    font-size: 11px;
}
.remote-warning .api-error-item .api-co { font-weight: 700; color: #f6e05e; min-width: 40px; }
.remote-warning .api-error-item .api-msg { color: #fbd38d; flex: 1; }

/* ─── Screenshot button ─── */
.results-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}
.btn-screenshot {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    color: rgba(255,255,255,0.6);
    font-size: 12px;
    font-family: 'Almarai', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-screenshot:hover {
    background: rgba(255,255,255,0.12);
    color: #fff;
    border-color: rgba(255,255,255,0.2);
}

/* ─── Dark modal ─── */
#attachments-modal .modal-backdrop,
.fahras-page ~ .modal-backdrop { background: rgba(10,15,25,0.85); }
#attachments-modal .modal-content {
    background: linear-gradient(180deg, #1b2838 0%, #162030 100%) !important;
    border: 1px solid rgba(99,179,237,0.15) !important;
    border-radius: 14px;
    color: #e8edf3 !important;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
}
#attachments-modal .modal-header {
    border-bottom: 1px solid rgba(99,179,237,0.1) !important;
    padding: 16px 20px;
    background: rgba(255,255,255,0.03) !important;
}
#attachments-modal .modal-header .close {
    color: rgba(255,255,255,0.6) !important;
    text-shadow: none !important;
    opacity: 1 !important;
    font-size: 24px;
}
#attachments-modal .modal-header .close:hover { color: #fff !important; }
#attachments-modal .modal-title {
    color: #fff !important;
    font-weight: 700;
    font-size: 15px;
}
#attachments-modal .modal-body {
    padding: 20px;
    max-height: 70vh;
    overflow-y: auto;
    color: #e8edf3 !important;
    background: transparent !important;
}
#attachments-modal .modal-body table {
    width: 100%;
    border-collapse: separate !important;
    border-spacing: 0 !important;
    border: 1px solid rgba(255,255,255,0.06) !important;
    border-radius: 8px;
    overflow: hidden;
    background: transparent !important;
}
#attachments-modal .modal-body table thead,
#attachments-modal .modal-body table thead tr {
    background: transparent !important;
}
#attachments-modal .modal-body table th {
    background: rgba(99,179,237,0.08) !important;
    color: #93c5fd !important;
    padding: 10px 14px !important;
    font-size: 12px;
    font-weight: 700;
    text-align: right;
    border: none !important;
    border-bottom: 1px solid rgba(99,179,237,0.1) !important;
    text-shadow: none !important;
}
#attachments-modal .modal-body table th:first-child { border-radius: 0 8px 0 0; }
#attachments-modal .modal-body table th:last-child { border-radius: 8px 0 0 0; }
#attachments-modal .modal-body table tbody,
#attachments-modal .modal-body table tbody tr {
    background: transparent !important;
}
#attachments-modal .modal-body table td {
    padding: 10px 14px !important;
    font-size: 13px;
    color: #e8edf3 !important;
    background: transparent !important;
    border: none !important;
    border-bottom: 1px solid rgba(255,255,255,0.05) !important;
    text-shadow: none !important;
}
#attachments-modal .modal-body table tr:nth-child(odd) td,
#attachments-modal .modal-body table tr:nth-child(even) td {
    background: transparent !important;
}
#attachments-modal .modal-body table tr:hover td {
    background: rgba(99,179,237,0.05) !important;
}
#attachments-modal .modal-body table tr:last-child td {
    border-bottom: none !important;
}
#attachments-modal .modal-body img {
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

/* ─── Footer ─── */
.fahras-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.fahras-footer a {
    color: rgba(255,255,255,0.35);
    text-decoration: none;
    transition: color 0.2s;
}
.fahras-footer a:hover { color: rgba(255,255,255,0.6); }
.fahras-footer .fa-heart { color: #e53e3e; }

/* ─── Party violations ─── */
.party-alert {
    background: rgba(229,62,62,0.1);
    border: 1px solid rgba(229,62,62,0.25);
    border-right: 4px solid #e53e3e;
    border-radius: 10px;
    padding: 16px 18px;
    margin-bottom: 16px;
}
.party-alert h5 { margin: 0 0 10px; font-size: 14px; color: #fc8181; }
.party-alert .party-item {
    padding: 8px 12px;
    margin-bottom: 6px;
    background: rgba(0,0,0,0.15);
    border-radius: 8px;
    font-size: 13px;
    color: #fed7d7;
    line-height: 1.6;
}
.party-alert .party-item b { color: #fff; }
.party-alert .party-item .label { font-size: 11px; }

/* ─── No results ─── */
.no-results {
    text-align: center;
    padding: 50px 20px;
    color: rgba(255,255,255,0.3);
}
.no-results i { font-size: 48px; margin-bottom: 14px; display: block; }
.no-results p { font-size: 15px; margin: 0; }

/* ═══════════════════════════════════════════════════ */
/*  Result group card                                 */
/* ═══════════════════════════════════════════════════ */
.group-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    margin-bottom: 18px;
    overflow: hidden;
    transition: border-color 0.2s;
}
.group-card:hover { border-color: rgba(255,255,255,0.18); }

/* Recommendation banner */
.group-banner {
    padding: 14px 18px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}
.group-banner.can-sell   { background: rgba(72,187,120,0.12); border-bottom: 2px solid rgba(72,187,120,0.3); }
.group-banner.cannot-sell{ background: rgba(245,101,101,0.12); border-bottom: 2px solid rgba(245,101,101,0.3); }
.group-banner.contact    { background: rgba(236,201,75,0.12);  border-bottom: 2px solid rgba(236,201,75,0.3); }

.group-banner .banner-top {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
}
.group-banner .banner-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.group-banner.can-sell .banner-icon    { background: rgba(72,187,120,0.2); color: #68d391; }
.group-banner.cannot-sell .banner-icon { background: rgba(245,101,101,0.2); color: #fc8181; }
.group-banner.contact .banner-icon     { background: rgba(236,201,75,0.2); color: #f6e05e; }

.group-banner .banner-text {
    flex: 1;
    font-weight: 700;
    font-size: 13px;
}
.group-banner.can-sell .banner-text    { color: #68d391; }
.group-banner.cannot-sell .banner-text { color: #fc8181; }
.group-banner.contact .banner-text     { color: #f6e05e; }

.banner-summary {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 4px;
}
.company-chip {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 10px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    font-size: 12px;
    flex-wrap: wrap;
}
.company-chip .chip-name {
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    min-width: 50px;
    text-align: center;
}
.chip-name.zajal  { background: rgba(59,130,246,0.2);  color: #93c5fd; }
.chip-name.jadal  { background: rgba(34,197,94,0.2);   color: #86efac; }
.chip-name.namaa  { background: rgba(234,179,8,0.2);   color: #fde68a; }
.chip-name.bseel  { background: rgba(236,72,153,0.2);  color: #f9a8d4; }
.chip-name.watar  { background: rgba(139,92,246,0.2);  color: #c4b5fd; }
.chip-name.local  { background: rgba(148,163,184,0.15); color: #94a3b8; }

.company-chip .chip-detail {
    color: rgba(255,255,255,0.6);
}
.company-chip .chip-detail span {
    color: rgba(255,255,255,0.9);
    font-weight: 600;
}
.company-chip .chip-remaining-high span { color: #fc8181; font-weight: 700; }
.company-chip .chip-remaining-low span  { color: #68d391; }

.chip-status-tag {
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}
.chip-status-tag.blocker   { background: rgba(245,101,101,0.2); color: #fc8181; }
.chip-status-tag.clear     { background: rgba(72,187,120,0.15); color: #68d391; }
.chip-status-tag.finished  { background: rgba(148,163,184,0.15); color: #94a3b8; }
.chip-status-tag.unknown   { background: rgba(236,201,75,0.15); color: #f6e05e; }

.company-chip .chip-phone {
    margin-right: auto;
    direction: ltr;
    color: rgba(255,255,255,0.5);
    text-decoration: none;
    transition: color 0.2s;
}
.company-chip .chip-phone:hover { color: #fff; }

.group-banner .banner-phone {
    font-size: 12px;
    color: rgba(255,255,255,0.7);
    direction: ltr;
    background: rgba(255,255,255,0.1);
    padding: 6px 14px;
    border-radius: 20px;
    transition: all 0.2s;
    white-space: nowrap;
}
.group-banner .banner-phone:hover {
    background: rgba(255,255,255,0.2);
    color: #fff;
}

/* ─── Contract entry cards (replaces table rows) ─── */
.contracts-list { padding: 12px 14px; }

.contract-entry {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 10px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    transition: background 0.15s;
}
.contract-entry:last-child { margin-bottom: 0; }
.contract-entry:hover { background: rgba(255,255,255,0.08); }

.contract-num {
    width: 28px;
    height: 28px;
    background: rgba(255,255,255,0.06);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: rgba(255,255,255,0.4);
    flex-shrink: 0;
    margin-top: 2px;
}

.contract-body { flex: 1; min-width: 0; }

.contract-top {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

/* Company badges */
.co-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.co-badge.zajal  { background: rgba(59,130,246,0.2);  color: #93c5fd; }
.co-badge.jadal  { background: rgba(34,197,94,0.2);   color: #86efac; }
.co-badge.namaa  { background: rgba(234,179,8,0.2);   color: #fde68a; }
.co-badge.bseel  { background: rgba(236,72,153,0.2);  color: #f9a8d4; }
.co-badge.watar  { background: rgba(139,92,246,0.2);  color: #c4b5fd; }
.co-badge.local  { background: rgba(148,163,184,0.15); color: #94a3b8; }

.contract-name {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
}

.contract-id-link {
    font-size: 12px;
    color: #63b3ed;
    cursor: pointer;
    text-decoration: underline;
}

.contract-details {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 6px 16px;
}
.contract-detail {
    font-size: 12px;
    color: rgba(255,255,255,0.45);
}
.contract-detail span {
    color: rgba(255,255,255,0.8);
    font-weight: 600;
    margin-right: 2px;
}
.contract-remaining {
    font-weight: 700;
    font-size: 13px;
}
.contract-remaining.remaining-high span {
    color: #ff6b6b;
}
.contract-remaining.remaining-low span {
    color: #51cf66;
}

.contract-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
    margin-top: 2px;
}
.contract-actions .btn-icon {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.15s;
}
.contract-actions .btn-icon:hover {
    background: rgba(255,255,255,0.12);
    color: #fff;
    border-color: rgba(255,255,255,0.2);
}

/* ─── Hide original footer ─── */
.fahras-page ~ footer.footer { display: none !important; }

/* ─── Responsive ─── */
@media (max-width: 768px) {
    .fahras-page { padding: 16px 10px 60px; margin: -10px -15px -60px; }
    .fahras-brand h1 { font-size: 20px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
    .stat-card .stat-value { font-size: 18px; }
    .search-box input { padding: 12px 40px 12px 12px; font-size: 13px; }
    .search-box button { padding: 12px 16px; font-size: 12px; }
    .contract-entry { flex-direction: column; gap: 8px; }
    .contract-top { flex-direction: column; align-items: flex-start; }
    .contract-details { grid-template-columns: 1fr 1fr; }
    .results-bar { flex-direction: column; gap: 8px; align-items: flex-start; }
}
</style>

<div class="fahras-page">
    <div class="container">
        <div class="fahras-brand">
            <img src="img/fahras-logo.png" alt="<?= _e('فهرس') ?>">
            <div class="fahras-brand-text">
                <h1><?= _e('فهرس') ?></h1>
                <p><?= _e('نظام فهرسة العملاء واكتشاف المخالفات') ?></p>
                </div>
            </div>

        <?php if (user_can('clients', 'view')) { ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fad fa-users"></i></div>
                <div class="stat-value"><?= number_format($totalClients) ?></div>
                <div class="stat-label"><?= _e('إجمالي العملاء') ?></div>
        </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fad fa-building"></i></div>
                <div class="stat-value"><?= number_format($totalAccounts) ?></div>
                <div class="stat-label"><?= _e('الشركات') ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fad fa-exclamation-triangle"></i></div>
                <div class="stat-value"><?= $monthViolations ?></div>
                <div class="stat-label"><?= _e('مخالفات هذا الشهر') ?></div>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fad fa-file-invoice-dollar"></i></div>
                <div class="stat-value"><?= $unpaidViolations ?></div>
                <div class="stat-label"><?= _e('مخالفات غير مدفوعة') ?></div>
            </div>
        </div>
        <?php } ?>

        <form action="" method="get" class="search-container" id="search-form">
            <div class="search-box">
                <i class="fa fa-search search-icon"></i>
                <input type="text" id="search-input" name="search"
                       value="<?= htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= _e('البحث بالاسم أو الرقم الوطني أو رقم الهاتف...') ?>"
                       autocomplete="off" autofocus />
                <button type="submit"><i class="fa fa-search"></i> <?= _e('بحث') ?></button>
            </div>
            <div class="search-hint"><?= _e('يدعم البحث التقريبي - يتعامل مع الأخطاء الإملائية والتشكيل تلقائياً') ?></div>
    </form>

<?php
$allResults = [];

if (!empty($_GET['search'])) {
    $q = trim($_GET['search']);
    $words = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);

    log_activity('search', null, null, $q);

    $nameNorm = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(a.name, 'ة', 'ه'), 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي')";
    $nameNormNoSpace = "REPLACE($nameNorm, ' ', '')";

    $whereParts = [];
    $params = [];

    foreach ($words as $i => $w) {
        $wNorm = normalizeArabic($w);
        $pName = ":wn{$i}";
        $pNid  = ":wi{$i}";
        $pPh   = ":wp{$i}";

        if (isPhoneNumber($w)) {
            $wPhone = ltrim($w, '0');
            $params["wi{$i}"] = "%{$wNorm}%";
            $params["wp{$i}"] = "%{$wPhone}%";
            $params["wn{$i}"] = "%{$wNorm}%";
        } else {
            $params["wn{$i}"] = "%{$wNorm}%";
            $params["wi{$i}"] = "%{$wNorm}%";
            $params["wp{$i}"] = "%{$wNorm}%";
        }

        $whereParts[] = "($nameNorm LIKE $pName OR $nameNormNoSpace LIKE $pName OR a.national_id LIKE $pNid OR a.phone LIKE $pPh)";
    }

    $whereSQL = implode(' AND ', $whereParts);

    $perSourceLimit = 5;

    try {
        $stmt = $db->prepare("
            SELECT a.*, (SELECT COUNT(*) FROM attachments WHERE client = a.id) AS attachments,
                   'local' AS _source
        FROM clients a
            WHERE $whereSQL
            LIMIT $perSourceLimit
        ");
        $stmt->execute($params);
        $allResults = $stmt->fetchAll();
    } catch (Throwable $e) {
        $allResults = [];
    }

    $remote_errors = [];
    $searchEncoded = urlencode($q);

    $remoteApis = [
        'zajal' => ['url' => 'https://zajal.cc/fahras-api.php?token=354afdf5357c&search=' . $searchEncoded, 'label' => 'زجل'],
        'jadal' => ['url' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=jadal&search=' . $searchEncoded, 'label' => 'جدل'],
        'namaa' => ['url' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=erp&search=' . $searchEncoded, 'label' => 'نماء'],
        'bseel' => ['url' => 'https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&search=' . $searchEncoded, 'label' => 'بسيل'],
        'watar' => ['url' => 'https://watar.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=watar&search=' . $searchEncoded, 'label' => 'وتر'],
    ];

    $remoteUrls = [];
    foreach ($remoteApis as $k => $v) $remoteUrls[$k] = $v['url'];
    $multiResults = curl_multi_load($remoteUrls);

    foreach ($remoteApis as $srcKey => $api) {
        $raw = $multiResults[$srcKey]['body'] ?? null;
        $error = $multiResults[$srcKey]['error'] ?? null;

        if ($error || $raw === null || trim($raw) === '') {
            $remote_errors[] = ['ok' => false, 'label' => $api['label'], 'data' => [], 'error' => $error ?: _e('لا استجابة من الخادم')];
            continue;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $remote_errors[] = ['ok' => false, 'label' => $api['label'], 'data' => [], 'error' => _e('صيغة استجابة غير صالحة')];
            continue;
        }

        if (isset($decoded['error'])) {
            $remote_errors[] = ['ok' => false, 'label' => $api['label'], 'data' => [], 'error' => $decoded['error']];
            continue;
        }

        if (isset($decoded['data']) && is_array($decoded['data'])) $decoded = $decoded['data'];

        $decoded = array_slice($decoded, 0, $perSourceLimit);
        foreach ($decoded as &$r) { $r['_source'] = $srcKey; }
        $allResults = array_merge($allResults, $decoded);
    }

    if (!empty($remote_errors)) {
        $failedNames = [];
        foreach ($remote_errors as $e) { $failedNames[] = $e['label']; }
        $failedList = implode('، ', $failedNames);
        echo '<div class="remote-warning">';
        echo '<b><i class="fa fa-exclamation-triangle"></i> ' . _e('تحذير') . ':</b> ';
        echo _e('هذا البحث لا يشمل') . ' <b>' . htmlspecialchars($failedList, ENT_QUOTES, 'UTF-8') . '</b> ';
        echo _e('بسبب فشل الاتصال. يرجى المحاولة لاحقاً أو التواصل مع الشركة للتأكد.');
        foreach ($remote_errors as $e) {
            echo '<div class="api-error-item">';
            echo '<span class="api-co">' . htmlspecialchars($e['label'], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '<span class="api-msg">' . htmlspecialchars($e['error'], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    echo '<div class="results-toolbar">';
    echo '<div class="results-bar" style="flex:1">';
    echo '<h4><i class="fa fa-list-ul"></i> ' . _e('نتائج البحث عن') . ': <b>' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '</b></h4>';
    echo '<span class="badge-count">' . count($allResults) . ' ' . _e('نتيجة') . '</span>';
    echo '</div>';
    echo '<button class="btn-screenshot" id="btn-report" onclick="captureResults()"><i class="fa fa-clipboard"></i> نسخ التقرير</button>';
    echo '</div>';

    echo '<div id="results-content">';

    try { cacheRemoteResults($allResults); } catch (Throwable $e) {}

    $analyzed = [];
    try {
        $analyzed = analyzeSearchResults($allResults);
    } catch (Throwable $e) {
        echo '<div class="remote-warning"><b>' . _e('خطأ في التحليل') . ':</b> ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    $partyWarnings = [];
    if (count($allResults) <= 50) {
        try {
            $partyResults = fetchContractParties($allResults);
            if (!empty($partyResults)) {
                $partyWarnings = checkPartiesForViolations($partyResults, $allResults);
            }
        } catch (Throwable $e) {}
    }

    $sourceCounts = [];
    foreach ($allResults as $_r) {
        $_s = $_r['_source'] ?? 'local';
        $sourceCounts[$_s] = ($sourceCounts[$_s] ?? 0) + 1;
    }
    $_failKeys = array_map(fn($e) => $e['label'], $remote_errors);
    $_failLabelsSet = array_flip($_failKeys);
    $_responded = [];
    foreach (['zajal' => 'زجل', 'jadal' => 'جدل', 'namaa' => 'نماء', 'bseel' => 'بسيل', 'watar' => 'وتر'] as $_rk => $_rl) {
        if (!isset($_failLabelsSet[$_rl])) $_responded[] = $_rk;
    }
    $_failLabels = $_failKeys;
    $_rGroups = [];
    $_finalRec = empty($analyzed) ? 'no_results' : 'can_sell';
    foreach ($analyzed as $_g) {
        $_rGroups[] = ['rec' => $_g['recommendation'], 'msg' => $_g['message']];
        if ($_g['recommendation'] === 'cannot_sell') $_finalRec = 'cannot_sell';
        elseif ($_g['recommendation'] === 'contact_first' && $_finalRec !== 'cannot_sell') $_finalRec = 'contact_first';
    }
    if (!empty($partyWarnings) && $_finalRec === 'can_sell') $_finalRec = 'contact_first';
    $_pwMsgs = [];
    foreach ($partyWarnings as $_pw) {
        $_pwMsgs[] = ($_pw['party_name'] ?? '') . ' - ' . ($_pw['violating_account'] ?? '');
    }
    $reportJson = json_encode([
        'q' => $q, 'total' => count($allResults), 'src' => $sourceCounts,
        'ok' => $_responded, 'fail' => $_failLabels,
        'grp' => $_rGroups, 'pw' => $_pwMsgs, 'rec' => $_finalRec,
    ], JSON_UNESCAPED_UNICODE);

    if (empty($analyzed)) {
        echo '<div class="no-results">';
        echo '<i class="fa fa-search"></i>';
        echo '<p>' . _e('لم يتم العثور على نتائج') . '</p>';
        echo '</div>';
    }

    if (!empty($partyWarnings)) {
        echo '<div class="party-alert">';
        echo '<h5><i class="fa fa-users"></i> ' . _e('مخالفات أطراف العقد') . '</h5>';
        foreach ($partyWarnings as $pw) {
            echo '<div class="party-item">';
            echo '<i class="fa fa-exclamation-triangle"></i> ';
            echo _e('طرف') . ' <b>' . htmlspecialchars($pw['party_name'], ENT_QUOTES, 'UTF-8') . '</b>';
            if (!empty($pw['party_nid'])) {
                echo ' (' . htmlspecialchars($pw['party_nid'], ENT_QUOTES, 'UTF-8') . ')';
            }
            echo ' - ' . _e('نشط مع') . ' <b>' . htmlspecialchars($pw['found_account'], ENT_QUOTES, 'UTF-8') . '</b>';
            echo ' - ' . _e('المبلغ المتبقي يتجاوز') . ' 150';
            echo ' &rarr; <span class="label label-danger">' . htmlspecialchars($pw['violating_account'], ENT_QUOTES, 'UTF-8') . ' ' . _e('مخالف') . '</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    foreach ($analyzed as $group) {
        $rec = $group['recommendation'];
        $bannerClass = 'contact';
        $recIcon = '<i class="fa fa-phone"></i>';
        if ($rec === 'can_sell') {
            $bannerClass = 'can-sell';
            $recIcon = '<i class="fa fa-check-circle"></i>';
        } elseif ($rec === 'cannot_sell') {
            $bannerClass = 'cannot-sell';
            $recIcon = '<i class="fa fa-ban"></i>';
        }

        echo '<div class="group-card">';
        echo '<div class="group-banner ' . $bannerClass . '">';
        echo '<div class="banner-top">';
        echo '<div class="banner-icon">' . $recIcon . '</div>';
        echo '<div class="banner-text">' . htmlspecialchars($group['message'], ENT_QUOTES, 'UTF-8') . '</div>';
        echo '</div>';

        $companySummary = [];
        foreach ($group['results'] as $_ent) {
            $accName = resolveAccountName($_ent);
            if (isExcludedAccount($accName)) continue;
            if (!isset($companySummary[$accName])) {
                $companySummary[$accName] = [
                    'remaining' => null,
                    'status' => '',
                    'phone' => getAccountPhone($_ent),
                    'source' => $_ent['_source'] ?? 'local',
                ];
            }
            $entRemaining = $_ent['remaining_amount'] ?? null;
            $entStatus = strtolower(trim($_ent['status'] ?? ''));
            if ($entRemaining !== null) {
                $prev = $companySummary[$accName]['remaining'];
                if ($prev === null || (float)$entRemaining > (float)$prev) {
                    $companySummary[$accName]['remaining'] = $entRemaining;
                    $companySummary[$accName]['status'] = $_ent['status'] ?? '';
                }
            }
            if (empty($companySummary[$accName]['status'])) {
                $companySummary[$accName]['status'] = $_ent['status'] ?? '';
            }
            if (empty($companySummary[$accName]['phone'])) {
                $companySummary[$accName]['phone'] = getAccountPhone($_ent);
            }
        }

        if (!empty($companySummary)) {
            echo '<div class="banner-summary">';
            foreach ($companySummary as $compName => $compData) {
                $src = $compData['source'];
                $chipBadge = 'local';
                if ($src === 'zajal' || $compName === 'زجل') $chipBadge = 'zajal';
                elseif ($src === 'jadal' || $compName === 'جدل') $chipBadge = 'jadal';
                elseif ($src === 'namaa' || $compName === 'نماء') $chipBadge = 'namaa';
                elseif ($src === 'bseel' || $compName === 'بسيل' || $compName === 'عمار') $chipBadge = 'bseel';
                elseif ($src === 'watar' || $compName === 'وتر') $chipBadge = 'watar';

                $rem = $compData['remaining'];
                $rawStatus = strtolower(trim($compData['status']));
                $isFinished = in_array($rawStatus, ['منتهي', 'finished', 'completed', 'closed']);
                $isCanceled = in_array($rawStatus, ['ملغي', 'canceled', 'cancelled']);
                $isBlocker = ($rem !== null && (float)$rem >= 150 && !$isFinished && !$isCanceled);
                $isClear = ($rem !== null && (float)$rem < 150 && !$isFinished && !$isCanceled);

                if ($isBlocker) {
                    $tagClass = 'blocker';
                    $tagText = _e('مانع');
                    $tagIcon = '<i class="fa fa-ban"></i> ';
                } elseif ($isFinished || $isCanceled) {
                    $tagClass = 'finished';
                    $tagText = $isFinished ? _e('منتهي') : _e('ملغي');
                    $tagIcon = '<i class="fa fa-check"></i> ';
                } elseif ($isClear) {
                    $tagClass = 'clear';
                    $tagText = _e('تحت الحد');
                    $tagIcon = '<i class="fa fa-check-circle"></i> ';
                } else {
                    $tagClass = 'unknown';
                    $tagText = _e('يحتاج تحقق');
                    $tagIcon = '<i class="fa fa-question-circle"></i> ';
                }

                $remClass = $isBlocker ? 'chip-remaining-high' : 'chip-remaining-low';

                echo '<div class="company-chip">';
                echo '<span class="chip-name ' . $chipBadge . '">' . htmlspecialchars($compName, ENT_QUOTES, 'UTF-8') . '</span>';
                if ($rem !== null) {
                    echo '<span class="chip-detail ' . $remClass . '">' . _e('المتبقي') . ': <span>' . number_format((float)$rem, 2) . '</span></span>';
                }
                if (!empty($compData['status'])) {
                    echo '<span class="chip-detail">' . _e('الحالة') . ': <span>' . htmlspecialchars($compData['status'], ENT_QUOTES, 'UTF-8') . '</span></span>';
                }
                echo '<span class="chip-status-tag ' . $tagClass . '">' . $tagIcon . $tagText . '</span>';
                if (!empty($compData['phone'])) {
                    $phSafe = htmlspecialchars($compData['phone'], ENT_QUOTES, 'UTF-8');
                    $telLink = 'tel:' . preg_replace('/[^\d+]/', '', $compData['phone']);
                    echo '<a href="' . $telLink . '" class="chip-phone"><i class="fa fa-phone"></i> ' . $phSafe . '</a>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="contracts-list">';

        $count = 0;
        foreach ($group['results'] as $key) {
        $key = array_merge([
                'account' => '', 'id' => '', 'sell_date' => '', 'name' => '',
                'national_id' => '', 'work' => '', 'phone' => '', 'status' => '',
                'court_status' => '', 'attachments' => 0, 'cid' => 0, '_source' => 'local',
                'created_on' => '', 'remaining_amount' => null,
        ], $key);

            $count++;
            $account_name = resolveAccountName($key);
            $source = $key['_source'] ?? 'local';

            $badgeClass = 'local';
            if ($source === 'zajal' || $account_name === 'زجل') $badgeClass = 'zajal';
            elseif ($source === 'jadal' || $account_name === 'جدل') $badgeClass = 'jadal';
            elseif ($source === 'namaa' || $account_name === 'نماء') $badgeClass = 'namaa';
            elseif ($source === 'bseel' || $account_name === 'بسيل' || $account_name === 'عمار') { $badgeClass = 'bseel'; $account_name = 'بسيل'; }
            elseif ($source === 'watar' || $account_name === 'وتر') $badgeClass = 'watar';

        $account_id = 0;
        $people_link = '';
            $relations_link = '';
        $attachments_link = '';

            if ($account_name === 'زجل' || $source === 'zajal') {
            $account_id = 1;
            $attachments_link = $key['attachments'];
            $people_link = 'https://zajal.cc/people-api.php?token=354afdf5357c&client=' . $key['id'];
            $relations_link = 'https://zajal.cc/fahras-parties-api.php?token=354afdf5357c&contract=' . $key['id'];
            } elseif ($account_name === 'جدل' || $source === 'jadal') {
            $attachments_link = 'https://jadal.aqssat.co/fahras/client-attachments.php?db=jadal&id=' . $key['cid'];
            $people_link = 'https://jadal.aqssat.co/fahras/people.php?token=b83ba7a49b72&db=jadal&client=' . $key['cid'];
            $relations_link = 'https://jadal.aqssat.co/fahras/relations.php?token=b83ba7a49b72&db=jadal&client=' . $key['cid'];
            } elseif ($account_name === 'نماء' || $source === 'namaa') {
            $attachments_link = 'https://jadal.aqssat.co/fahras/client-attachments.php?db=namaa&id=' . $key['cid'];
            $people_link = 'https://jadal.aqssat.co/fahras/people.php?token=b83ba7a49b72&db=erp&client=' . $key['cid'];
            $relations_link = 'https://jadal.aqssat.co/fahras/relations.php?token=b83ba7a49b72&db=erp&client=' . $key['cid'];
            } elseif ($account_name === 'عمار' || $account_name === 'بسيل' || $source === 'bseel') {
                $people_link = 'https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&action=people&client=' . $key['id'];
                $attachments_link = 'https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&action=attachments&client=' . $key['id'];
                $relations_link = 'https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&action=parties&contract=' . $key['id'];
            } elseif ($account_name === 'وتر' || $source === 'watar') {
            $attachments_link = 'https://watar.aqssat.co/fahras/client-attachments.php?db=watar&id=' . $key['cid'];
            $people_link = 'https://watar.aqssat.co/fahras/people.php?token=b83ba7a49b72&db=watar&client=' . $key['cid'];
            $relations_link = 'https://watar.aqssat.co/fahras/relations.php?token=b83ba7a49b72&db=watar&client=' . $key['cid'];
        } else {
            $attachments_link = '/admin/attachments?client=' . $key['id'];
        }

            $raw_ids = trim((string)$key['id']);
            if ($raw_ids === '' || $raw_ids === '0') $raw_ids = '-';
            $ids = array_filter(array_map('trim', explode(',', $raw_ids)));
            $contractItems = [];
            foreach ($ids as $id) {
                if (!empty($relations_link)) {
                    $link = $relations_link . '&contract=' . urlencode($id);
                    $contractItems[] = '<a class="contract-id-link" onclick="showPeople(this, \'' . _e('أطراف العقد') . '\');" account-link="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">#' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '</a>';
                } else {
                    $contractItems[] = '#' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
                }
            }

            $displayDate = !empty($key['created_on']) ? $key['created_on'] : ($key['sell_date'] ?? '');
            $displayDate = formatTo12h($displayDate);

            echo '<div class="contract-entry">';
            echo '<div class="contract-num">' . $count . '</div>';
            echo '<div class="contract-body">';

            echo '<div class="contract-top">';
            echo '<span class="co-badge ' . $badgeClass . '"><i class="fa fa-building"></i> ' . htmlspecialchars($account_name, ENT_QUOTES, 'UTF-8') . '</span>';
            echo '<span class="contract-name">' . htmlspecialchars((string)$key['name'], ENT_QUOTES, 'UTF-8') . '</span>';
            if (!empty($contractItems)) {
                echo '<span style="color:rgba(255,255,255,0.3);font-size:12px;">' . implode(' ', $contractItems) . '</span>';
            }
            echo '</div>';

            echo '<div class="contract-details">';
            if (!empty($key['national_id'])) {
                echo '<div class="contract-detail">' . _e('الرقم الوطني') . ': <span>' . htmlspecialchars((string)$key['national_id'], ENT_QUOTES, 'UTF-8') . '</span></div>';
            }
            if (!empty($displayDate)) {
                echo '<div class="contract-detail">' . _e('تاريخ العقد') . ': <span>' . htmlspecialchars((string)$displayDate, ENT_QUOTES, 'UTF-8') . '</span></div>';
            }
            if (!empty($key['phone'])) {
                echo '<div class="contract-detail">' . _e('الهاتف') . ': <span dir="ltr">' . htmlspecialchars((string)$key['phone'], ENT_QUOTES, 'UTF-8') . '</span></div>';
            }
            if (!empty($key['work'])) {
                echo '<div class="contract-detail">' . _e('جهة العمل') . ': <span>' . htmlspecialchars((string)$key['work'], ENT_QUOTES, 'UTF-8') . '</span></div>';
            }
            if (!empty($key['status'])) {
                echo '<div class="contract-detail">' . _e('الحالة') . ': <span>' . htmlspecialchars((string)$key['status'], ENT_QUOTES, 'UTF-8') . '</span></div>';
            }
            if (!empty($key['court_status'])) {
                echo '<div class="contract-detail">' . _e('حالة المحكمة') . ': <span>' . htmlspecialchars((string)$key['court_status'], ENT_QUOTES, 'UTF-8') . '</span></div>';
            }
            if ($key['remaining_amount'] !== null) {
                $remAmt = number_format((float)$key['remaining_amount'], 2);
                $remClass = (float)$key['remaining_amount'] >= 150 ? 'remaining-high' : 'remaining-low';
                echo '<div class="contract-detail contract-remaining ' . $remClass . '">' . _e('المتبقي') . ': <span>' . $remAmt . '</span></div>';
            }
            echo '</div>';

            echo '</div>'; // contract-body

            echo '<div class="contract-actions">';
            if (!empty($attachments_link) && !empty($key['attachments']) && $key['attachments'] != '0' && user_can('clients', 'view_attachments')) {
                echo '<div class="btn-icon" onclick="showAttachments(this,' . (int)$account_id . ');" account-link="' . htmlspecialchars($attachments_link, ENT_QUOTES, 'UTF-8') . '" title="' . _e('المرفقات') . '"><i class="fa fa-images"></i></div>';
            }
            if (!empty($people_link)) {
                echo '<div class="btn-icon" onclick="showPeople(this);" account-link="' . htmlspecialchars($people_link, ENT_QUOTES, 'UTF-8') . '" title="' . _e('المراجع') . '"><i class="fa fa-users"></i></div>';
            }
            echo '</div>';

            echo '</div>'; // contract-entry
        }

        echo '</div>'; // contracts-list
        echo '</div>'; // group-card
    }

    echo '<div id="report-data" style="display:none" data-report="' . htmlspecialchars($reportJson, ENT_QUOTES, 'UTF-8') . '"></div>';
    echo '</div>'; // results-content
}
?>

        <div id="results-area">
            <div class="search-loading" id="search-loading">
                <i class="fa fa-circle-notch"></i>
                <p><?= _e('جاري البحث...') ?></p>
            </div>
        </div>

        <div class="fahras-footer">
            <a href="https://fb.com/mujeer.world" target="_blank"><?= _e('صُنع بـ') ?> <i class="fa fa-heart"></i> <?= _e('بواسطة MÜJEER') ?></a>
            &nbsp;&middot;&nbsp;
            &copy; <?= _e('فهرس') ?> <?= date('Y') ?>
        </div>
    </div>
    </div>

<div class="modal fade" id="attachments-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><?= _e('المرفقات') ?></h4>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function showAttachments(obj, account) {
    var modal = $('#attachments-modal');
    modal.find('.modal-title').html('<?= _e('المرفقات') ?>');
    modal.find('.modal-body').html('<div style="text-align:center;padding:30px;color:rgba(255,255,255,0.5)"><i class="fa fa-circle-notch fa-spin fa-2x"></i><br><small style="margin-top:10px;display:block"><?= _e('يرجى الانتظار') ?></small></div>');
    if (account == 1) {
        modal.find('.modal-body').html('<img src="https://zajal.cc/uploads/' + $(obj).attr('account-link') + '" style="max-width:100%;border-radius:10px;" />');
        modal.modal('show');
    } else {
        modal.modal('show');
        $.get($(obj).attr('account-link'), function(result) {
            modal.find('.modal-body').html(result);
        });
    }
}

function showPeople(obj, title) {
    title = title || '<?= _e('المراجع') ?>';
    var modal = $('#attachments-modal');
    modal.find('.modal-title').html(title);
    modal.find('.modal-body').html('<div style="text-align:center;padding:30px;color:rgba(255,255,255,0.5)"><i class="fa fa-circle-notch fa-spin fa-2x"></i><br><small style="margin-top:10px;display:block"><?= _e('يرجى الانتظار') ?></small></div>');
    modal.modal('show');
    $.get($(obj).attr('account-link'), function(result) {
        modal.find('.modal-body').html(result);
    });
}

(function() {
    var form = document.getElementById('search-form');
    var input = document.getElementById('search-input');
    var loading = document.getElementById('search-loading');
    var resultsArea = document.getElementById('results-area');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var q = input.value.trim();
        if (!q) return;

        var url = '/admin/index.php?search=' + encodeURIComponent(q);
        history.pushState({ search: q }, '', url);

        var existingContent = document.getElementById('results-content');
        if (existingContent) existingContent.remove();

        var existingToolbar = document.querySelector('.results-toolbar');
        if (existingToolbar) existingToolbar.remove();

        var existingWarnings = document.querySelectorAll('.remote-warning, .party-alert');
        existingWarnings.forEach(function(el) { el.remove(); });

        loading.classList.add('active');
        form.querySelector('button').disabled = true;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');

                var pageContainer = doc.querySelector('.fahras-page > .container');
                if (!pageContainer) {
                    loading.classList.remove('active');
                    form.querySelector('button').disabled = false;
                    return;
                }

                var warnings = pageContainer.querySelectorAll('.remote-warning');
                var toolbar = pageContainer.querySelector('.results-toolbar');
                var partyAlert = pageContainer.querySelector('.party-alert');
                var content = pageContainer.querySelector('#results-content');
                var noResults = pageContainer.querySelector('.no-results');

                loading.classList.remove('active');
                form.querySelector('button').disabled = false;

                var insertPoint = resultsArea;

                warnings.forEach(function(w) {
                    insertPoint.parentNode.insertBefore(w, insertPoint);
                });
                if (toolbar) {
                    insertPoint.parentNode.insertBefore(toolbar, insertPoint);
                }
                if (partyAlert) {
                    insertPoint.parentNode.insertBefore(partyAlert, insertPoint);
                }
                if (content) {
                    insertPoint.parentNode.insertBefore(content, insertPoint);
                }
                if (noResults && !content) {
                    var wrapper = document.createElement('div');
                    wrapper.id = 'results-content';
                    wrapper.appendChild(noResults);
                    insertPoint.parentNode.insertBefore(wrapper, insertPoint);
                }

                var newStats = doc.querySelectorAll('.stat-card .stat-value');
                var oldStats = document.querySelectorAll('.stat-card .stat-value');
                newStats.forEach(function(ns, i) {
                    if (oldStats[i]) oldStats[i].textContent = ns.textContent;
                });
            })
            .catch(function() {
                loading.classList.remove('active');
                form.querySelector('button').disabled = false;
            });
    });

    window.addEventListener('popstate', function(e) {
        var params = new URLSearchParams(window.location.search);
        var q = params.get('search') || '';
        input.value = q;
        if (q) {
            form.dispatchEvent(new Event('submit'));
    } else {
            var c = document.getElementById('results-content');
            if (c) c.remove();
            var t = document.querySelector('.results-toolbar');
            if (t) t.remove();
            document.querySelectorAll('.remote-warning, .party-alert').forEach(function(el) { el.remove(); });
        }
    });
})();

function _rPad(n){return n<10?'0'+n:''+n}

function captureResults(){
    var el=document.getElementById('report-data');
    if(!el)return;
    var rpt;
    try{rpt=JSON.parse(el.dataset.report)}catch(e){return}

    var btn=document.getElementById('btn-report');
    var origHTML='';
    if(btn){origHTML=btn.innerHTML;btn.innerHTML='<i class="fa fa-spinner fa-spin"></i> جاري التحضير...';btn.disabled=true;}

    var now=new Date();
    var ds=now.getFullYear()+'-'+_rPad(now.getMonth()+1)+'-'+_rPad(now.getDate());
    var _h=now.getHours(),_ap=_h>=12?'PM':'AM';_h=_h%12||12;
    var ts=_rPad(_h)+':'+_rPad(now.getMinutes())+' '+_ap;

    var sn={local:'محلي',zajal:'زجل',jadal:'جدل',namaa:'نماء',bseel:'بسيل',watar:'وتر'};
    var remoteSrc=['zajal','jadal','namaa','bseel','watar'];

    var blockN=0,contactN=0;
    rpt.grp.forEach(function(g){
        if(g.rec==='cannot_sell')blockN++;
        else if(g.rec==='contact_first')contactN++;
    });
    blockN+=rpt.pw?rpt.pw.length:0;

    var svr='';
    remoteSrc.forEach(function(s){
        var ok=rpt.ok.indexOf(s)>=0;
        svr+='<div style="display:inline-block;padding:5px 12px;border-radius:6px;margin:0 4px 4px 0;font-size:12px;font-weight:700;'
            +'background:'+(ok?'rgba(72,187,120,0.15)':'rgba(245,101,101,0.15)')+';'
            +'color:'+(ok?'#68d391':'#fc8181')+';">'+(ok?'●':'✗')+' '+sn[s]+'</div>';
    });

    var sb=[];
    for(var k in rpt.src)if(rpt.src.hasOwnProperty(k))sb.push((sn[k]||k)+': '+rpt.src[k]);
    var sbText=sb.join('  ·  ');

    var recBg,recBdr,recClr,recTxt;
    switch(rpt.rec){
        case 'can_sell':
            recBg='rgba(72,187,120,0.12)';recBdr='rgba(72,187,120,0.4)';recClr='#68d391';recTxt='● يمكن البيع';break;
        case 'cannot_sell':
            recBg='rgba(245,101,101,0.12)';recBdr='rgba(245,101,101,0.4)';recClr='#fc8181';recTxt='✗ لا يمكن البيع';break;
        case 'contact_first':
            recBg='rgba(236,201,75,0.12)';recBdr='rgba(236,201,75,0.4)';recClr='#f6e05e';recTxt='⚠ يتطلب تحقق';break;
        default:
            recBg='rgba(148,163,184,0.12)';recBdr='rgba(148,163,184,0.3)';recClr='#94a3b8';recTxt='لا توجد نتائج';
    }

    var blk='';
    rpt.grp.forEach(function(g){
        if(g.rec==='cannot_sell')
            blk+='<div style="padding:8px 12px;margin-bottom:6px;background:rgba(245,101,101,0.08);border-radius:8px;border-right:3px solid rgba(245,101,101,0.4);font-size:12px;color:#fed7d7;line-height:1.7;">✗ '+g.msg+'</div>';
        else if(g.rec==='contact_first')
            blk+='<div style="padding:8px 12px;margin-bottom:6px;background:rgba(236,201,75,0.08);border-radius:8px;border-right:3px solid rgba(236,201,75,0.4);font-size:12px;color:#fefcbf;line-height:1.7;">⚠ '+g.msg+'</div>';
    });
    if(rpt.pw&&rpt.pw.length>0){
        rpt.pw.forEach(function(p){
            blk+='<div style="padding:8px 12px;margin-bottom:6px;background:rgba(245,101,101,0.08);border-radius:8px;border-right:3px solid rgba(245,101,101,0.4);font-size:12px;color:#fed7d7;line-height:1.7;">✗ طرف عقد مخالف: '+p+'</div>';
        });
    }

    var inc='';
    if(rpt.fail&&rpt.fail.length>0)
        inc='<div style="padding:8px 12px;margin-top:8px;background:rgba(251,191,36,0.08);border-radius:8px;border-right:3px solid rgba(251,191,36,0.4);font-size:11px;color:#fbd38d;line-height:1.7;">⚠ هذا التقرير لا يشمل بيانات: '+rpt.fail.join('، ')+' بسبب عدم استجابة الخادم</div>';

    var c1='background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:10px;text-align:center;';

    var h='<div style="width:560px;background:linear-gradient(180deg,#1a1a2e 0%,#16213e 40%,#0f3460 100%);font-family:Almarai,Arial,sans-serif;direction:rtl;padding:28px 24px;color:#e0e6ed;">'

    +'<div style="text-align:center;margin-bottom:20px;">'
    +'<div style="font-size:28px;font-weight:800;color:#fff;letter-spacing:1px;">فهرس</div>'
    +'<div style="font-size:11px;color:rgba(255,255,255,0.3);margin-top:2px;">تقرير بحث العملاء</div>'
    +'</div>'

    +'<div style="background:'+recBg+';border:2px solid '+recBdr+';border-radius:14px;padding:22px;text-align:center;margin-bottom:18px;">'
    +'<div style="font-size:11px;color:rgba(255,255,255,0.45);margin-bottom:8px;">التوصية النهائية</div>'
    +'<div style="font-size:24px;font-weight:800;color:'+recClr+';">'+recTxt+'</div>'
    +(rpt.fail&&rpt.fail.length>0?'<div style="font-size:10px;color:rgba(255,255,255,0.3);margin-top:6px;">* بيانات '+rpt.fail.join(' و ')+' غير متوفرة</div>':'')
    +'</div>'

    +'<div style="'+c1+'margin-bottom:14px;">'
    +'<table style="width:100%;border-collapse:collapse;"><tr>'
    +'<td style="vertical-align:top;padding:0;border:none;text-align:right;">'
    +'<div style="font-size:10px;color:rgba(255,255,255,0.35);">كلمة البحث</div>'
    +'<div style="font-size:16px;font-weight:700;color:#63b3ed;margin-top:3px;word-wrap:break-word;">'+rpt.q+'</div>'
    +'</td>'
    +'<td style="vertical-align:top;text-align:left;padding:0;border:none;white-space:nowrap;">'
    +'<div style="font-size:10px;color:rgba(255,255,255,0.35);">التاريخ والوقت</div>'
    +'<div style="font-size:13px;color:rgba(255,255,255,0.6);margin-top:3px;direction:ltr;">'+ds+'</div>'
    +'<div style="font-size:13px;color:rgba(255,255,255,0.6);direction:ltr;">'+ts+'</div>'
    +'</td>'
    +'</tr></table></div>'

    +'<table style="width:100%;border-collapse:collapse;margin-bottom:14px;"><tr>'
    +'<td style="padding:0 4px 0 0;width:33%;vertical-align:top;border:none;">'
    +'<div style="'+c1+'">'
    +'<div style="font-size:24px;font-weight:800;color:#fff;">'+rpt.total+'</div>'
    +'<div style="font-size:10px;color:rgba(255,255,255,0.35);margin-top:2px;">إجمالي النتائج</div>'
    +'</div></td>'
    +'<td style="padding:0 2px;width:33%;vertical-align:top;border:none;">'
    +'<div style="'+c1+'">'
    +'<div style="font-size:24px;font-weight:800;color:#fc8181;">'+blockN+'</div>'
    +'<div style="font-size:10px;color:rgba(255,255,255,0.35);margin-top:2px;">تمنع البيع</div>'
    +'</div></td>'
    +'<td style="padding:0 0 0 4px;width:33%;vertical-align:top;border:none;">'
    +'<div style="'+c1+'">'
    +'<div style="font-size:24px;font-weight:800;color:#f6e05e;">'+contactN+'</div>'
    +'<div style="font-size:10px;color:rgba(255,255,255,0.35);margin-top:2px;">تتطلب تحقق</div>'
    +'</div></td>'
    +'</tr></table>'

    +'<div style="font-size:11px;color:rgba(255,255,255,0.4);text-align:center;margin-bottom:14px;">'+sbText+'</div>';

    if(blk){
        h+='<div style="margin-bottom:14px;">'
        +'<div style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.4);margin-bottom:8px;">تفاصيل المنع والتحقق</div>'
        +blk+'</div>';
    }

    h+='<div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:12px;margin-bottom:14px;">'
    +'<div style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.35);margin-bottom:8px;">حالة الخوادم ('+rpt.ok.length+'/'+remoteSrc.length+' متصل)</div>'
    +'<div>'+svr+'</div>'+inc+'</div>'

    +'<div style="text-align:center;font-size:9px;color:rgba(255,255,255,0.2);margin-top:4px;">'
    +'فهرس &copy; '+now.getFullYear()+' &middot; نظام فهرسة العملاء والكشف عن المخالفات'
    +'</div></div>';

    var wrap=document.createElement('div');
    wrap.style.cssText='position:fixed;left:-9999px;top:0;z-index:-1;';
    wrap.innerHTML=h;
    document.body.appendChild(wrap);

    setTimeout(function(){
        html2canvas(wrap.firstChild,{backgroundColor:null,scale:3,useCORS:true,logging:false}).then(function(canvas){
            wrap.remove();
            if(btn){btn.innerHTML=origHTML;btn.disabled=false;}
            _copyImage(canvas,ds,ts);
        }).catch(function(){wrap.remove();if(btn){btn.innerHTML=origHTML;btn.disabled=false;}});
    },150);
}

function _copyImage(canvas,ds,ts){
    // Try Clipboard API first (only works on HTTPS)
    if(window.isSecureContext&&navigator.clipboard&&typeof ClipboardItem!=='undefined'){
        canvas.toBlob(function(blob){
            navigator.clipboard.write([new ClipboardItem({'image/png':blob})]).then(function(){
                _rToast('تم نسخ التقرير للحافظة');
            }).catch(function(){
                _showReportOverlay(canvas,ds,ts);
            });
        },'image/png');
        return;
    }
    _showReportOverlay(canvas,ds,ts);
}

function _showReportOverlay(canvas,ds,ts){
    var old=document.getElementById('rpt-overlay');if(old)old.remove();
    var blobUrl='';
    canvas.toBlob(function(blob){
        blobUrl=URL.createObjectURL(blob);
        var ov=document.createElement('div');
        ov.id='rpt-overlay';
        ov.style.cssText='position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(8,12,22,0.95);z-index:99998;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:16px;';

        ov.innerHTML='<div style="position:relative;max-width:620px;width:100%;text-align:center;">'
            +'<button onclick="document.getElementById(\'rpt-overlay\').remove();" style="position:absolute;top:-44px;left:0;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);color:#fff;width:34px;height:34px;border-radius:10px;font-size:16px;cursor:pointer;transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.2)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.1)\'">✕</button>'
            +'<img id="rpt-img" src="'+blobUrl+'" style="width:100%;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,0.6);cursor:context-menu;">'
            +'<div style="margin-top:18px;display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;">'
            +'<div style="color:#63b3ed;font-size:13px;font-family:Almarai,sans-serif;background:rgba(99,179,237,0.1);padding:10px 18px;border-radius:10px;border:1px solid rgba(99,179,237,0.2);line-height:1.6;"><i class="fa fa-mouse-pointer"></i> اضغط بزر الفأرة <b>الأيمن</b> على الصورة ← <b>نسخ الصورة</b></div>'
            +'<button id="rpt-dl-btn" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#e0e6ed;padding:10px 18px;border-radius:10px;font-size:13px;font-family:Almarai,sans-serif;cursor:pointer;transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.15)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.08)\'"><i class="fa fa-download"></i> تحميل</button>'
            +'</div></div>';

        document.body.appendChild(ov);

        ov.addEventListener('click',function(e){if(e.target===ov)ov.remove();});
        document.addEventListener('keydown',function esc(e){if(e.key==='Escape'){ov.remove();document.removeEventListener('keydown',esc);}});

        document.getElementById('rpt-dl-btn').onclick=function(){
            var a=document.createElement('a');
            a.download='fahras-report-'+ds+'-'+ts.replace(/:/g,'')+'.png';
            a.href=blobUrl;a.click();
            _rToast('تم تحميل التقرير');
        };
    },'image/png');
}

function _rToast(msg){
    var old=document.getElementById('report-toast');if(old)old.remove();
    var t=document.createElement('div');t.id='report-toast';t.textContent=msg;
    t.style.cssText='position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:rgba(72,187,120,0.95);color:#fff;padding:14px 32px;border-radius:12px;font-size:14px;font-weight:700;font-family:Almarai,sans-serif;z-index:99999;box-shadow:0 8px 30px rgba(0,0,0,0.3);transition:opacity 0.5s;';
    document.body.appendChild(t);
    setTimeout(function(){t.style.opacity='0'},2500);
    setTimeout(function(){t.remove()},3000);
}
</script>
