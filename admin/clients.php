<?php
	$page_title = 'العملاء';
	$token = 'mojeer';
	include 'header.php';
	require_permission('clients', 'view');
	require_once __DIR__ . '/../includes/migrations/006_add_external_accounts.php';

	$ar_labels = [
		'id' => 'رقم',
		'account' => 'الشركة',
		'name' => 'الاسم',
		'contracts' => 'العقود',
		'sell_date' => 'تاريخ البيع',
		'national_id' => 'الرقم الوطني',
		'work' => 'جهة العمل',
		'home_address' => 'عنوان السكن',
		'work_address' => 'عنوان العمل',
		'phone' => 'الهاتف',
		'status' => 'الحالة',
		'court_status' => 'حالة المحكمة',
		'user' => 'المستخدم',
		'created_on' => 'تاريخ الإنشاء',
		'added_by' => 'أضيف بواسطة',
		'image' => 'الصورة',
		'remaining_amount' => 'المبلغ المتبقي',
		'is_guarantor' => 'كفيل',
		'employment_type' => 'نوع التوظيف',
	];

	$en_labels = [
		'id' => '#',
		'account' => 'Company',
		'name' => 'Name',
		'contracts' => 'Contracts',
		'sell_date' => 'Sell Date',
		'national_id' => 'National ID',
		'work' => 'Workplace',
		'home_address' => 'Home Address',
		'work_address' => 'Work Address',
		'phone' => 'Phone',
		'status' => 'Status',
		'court_status' => 'Court Status',
		'user' => 'User',
		'created_on' => 'Created On',
		'added_by' => 'Added By',
		'image' => 'Image',
		'remaining_amount' => 'Remaining amount',
		'is_guarantor' => 'Guarantor',
		'employment_type' => 'Employment type',
	];

	$labels = ($lang == 'ar') ? $ar_labels : $en_labels;
	$searchTerm = trim($_GET['search'] ?? '');
?>

<style>
/* ── Page Shell (مطابق لتقرير المبيعات) ───────────────── */
.clients-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
    font-family: 'Almarai', sans-serif;
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
    overflow-x: clip;
}
.clients-page *,
.clients-page *::before,
.clients-page *::after {
    box-sizing: border-box;
}
.clients-page .container {
    width: 100%;
    max-width: 100%;
}

.clients-header {
    text-align: center;
    margin-bottom: 24px;
}
.clients-header h1 {
    color: #fff;
    font-size: 24px;
    font-weight: 800;
    margin: 0 0 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.clients-header p { color: rgba(255,255,255,0.4); font-size: 12px; margin: 0; }

/* ── بحث — نفس أسلوب .sales-filter ───────────────────── */
.clients-filter {
    background: rgba(255,255,255,0.06);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 24px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
    width: 100%;
}
.clients-filter__icon {
    color: rgba(255,255,255,0.45);
    font-size: 16px;
    flex-shrink: 0;
}
/* موبايل: عموديًا لا نستخدم flex-basis بقيمة طول — وإلا يُفسَّر كارتفاع (~200px) */
.clients-filter__input {
    flex: 0 1 auto;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    min-height: 0;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    color: #e2e8f0;
    padding: 9px 14px;
    font-size: 13px;
    line-height: 1.4;
    font-family: 'Almarai', sans-serif;
    outline: none;
    box-shadow: none;
    resize: none;
    appearance: none;
    -webkit-appearance: none;
}
.clients-filter__input::placeholder {
    color: rgba(255,255,255,0.35);
    font-size: 13px;
}
.clients-filter__input:focus {
    border-color: rgba(99,179,237,0.5);
}
@media (min-width: 577px) {
    .clients-filter__input {
        flex: 1 1 200px;
        width: auto;
    }
}
.clients-filter .btn-view {
    background: linear-gradient(135deg, #1f62b9, #2980b9);
    border: none;
    color: #fff;
    padding: 9px 22px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    font-family: 'Almarai', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    flex: 0 0 auto;
    line-height: 1.4;
}
.clients-filter .btn-view:hover {
    background: linear-gradient(135deg, #2980b9, #3498db);
}

.search-active-bar {
    max-width: 900px;
    margin: -8px auto 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 0 0 12px 12px;
    background: rgba(31,98,185,0.15);
    border: 1px solid rgba(31,98,185,0.2);
    border-top: none;
    font-size: 12px;
    color: rgba(255,255,255,0.5);
}
.search-active-bar a { color: #63b3ed; font-weight: 700; }

/* ── Glass Card (XCRUD) — زوايا كتقرير المبيعات ──────── */
.clients-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.25);
    backdrop-filter: blur(12px);
    overflow: hidden;
    max-width: min(1200px, 100%);
    margin-left: auto;
    margin-right: auto;
}

/* ── XCRUD Overrides inside .clients-card ────────────── */
.clients-card .xcrud-container { background: transparent !important; }
.clients-card .xcrud { color: #e0e6ed; }
.clients-card .xcrud-header {
    border-bottom: 1px solid rgba(255,255,255,0.06) !important;
    padding: 0 !important; margin: 0 !important;
}

/* Top actions bar */
.clients-card .xcrud-top-actions {
    padding: 14px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.clients-card .xcrud-top-actions .btn-group { order: 2; }
/* أزرار عليا — مطابقة تقرير المبيعات: btn-view + btn-today */
.clients-card .xcrud-top-actions .btn-success {
    background: linear-gradient(135deg, #1f62b9, #2980b9) !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 9px 22px !important;
    color: #fff !important;
    font-family: 'Almarai' !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    line-height: 1.4 !important;
    transition: all 0.2s !important;
}
.clients-card .xcrud-top-actions .btn-success:hover {
    background: linear-gradient(135deg, #2980b9, #3498db) !important;
}
.clients-card .xcrud-top-actions .btn-default {
    background: rgba(255,255,255,0.08) !important;
    color: #93c5fd !important;
    border: 1px solid rgba(255,255,255,0.12) !important;
    border-radius: 8px !important;
    padding: 9px 16px !important;
    font-family: 'Almarai' !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    line-height: 1.4 !important;
    transition: all 0.2s !important;
}
.clients-card .xcrud-top-actions .btn-default:hover {
    background: rgba(255,255,255,0.14) !important;
    color: #fff !important;
}

/* Table */
.clients-card .xcrud-list-container {
    border-radius: 0; overflow-x: auto; -webkit-overflow-scrolling: touch;
}
.clients-card .xcrud-list.table {
    background: transparent !important; margin-bottom: 0 !important;
    border-collapse: separate !important; border-spacing: 0 !important;
}
.clients-card .xcrud-list.table > thead > tr > th,
.clients-card .xcrud-th th {
    background: rgba(31,98,185,0.12) !important;
    color: #93c5fd !important;
    border-bottom: 2px solid rgba(31,98,185,0.2) !important;
    border-top: none !important;
    padding: 12px 14px !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    white-space: nowrap !important;
    font-family: 'Almarai' !important;
    position: sticky;
    top: 0;
    z-index: 2;
}
.clients-card .xcrud-list > tbody > tr > td {
    border-top: 1px solid rgba(255,255,255,0.04) !important;
    padding: 11px 14px !important;
    font-size: 13px !important;
    color: #e0e6ed !important;
    vertical-align: middle !important;
    font-family: 'Almarai' !important;
}
.clients-card .xcrud-list > tbody > tr:nth-of-type(odd) > td {
    background: rgba(255,255,255,0.02) !important;
}
.clients-card .xcrud-list > tbody > tr:nth-of-type(even) > td {
    background: transparent !important;
}
.clients-card .xcrud-list > tbody > tr:hover > td {
    background: rgba(31,98,185,0.1) !important;
}

/* Row number */
.clients-card .xcrud-num { color: rgba(255,255,255,0.25) !important; font-size: 11px !important; }

/* Action buttons in rows — مواءمة كاملة مع هوية تقرير المبيعات */
.clients-card .xcrud-list td .btn,
.clients-card .xcrud-actions a.btn {
    border-radius: 8px !important;
    padding: 6px 12px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    font-family: 'Almarai', sans-serif !important;
    line-height: 1.4 !important;
    transition: all 0.2s !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
}
.clients-card td.xcrud-actions {
    vertical-align: middle !important;
}
.clients-card .xcrud-actions .btn-group {
    display: inline-flex !important;
    flex-wrap: wrap !important;
    gap: 6px !important;
    align-items: center !important;
}

/* XCRUD الافتراضي: تعديل=warning، عرض=info، حذف=danger — نعيد ربطها بالهوية */
.clients-card .xcrud-list td .btn-primary,
.clients-card .xcrud-list td a.btn-primary,
.clients-card .xcrud-actions a.btn-primary {
    background: linear-gradient(135deg, #1f62b9, #2980b9) !important;
    border: none !important;
    color: #fff !important;
    border-radius: 8px !important;
    padding: 6px 14px !important;
}
.clients-card .xcrud-list td .btn-primary:hover,
.clients-card .xcrud-actions a.btn-primary:hover {
    background: linear-gradient(135deg, #2980b9, #3498db) !important;
    color: #fff !important;
}

.clients-card .xcrud-actions a.btn-warning {
    background: linear-gradient(135deg, #1f62b9, #2980b9) !important;
    border: none !important;
    color: #fff !important;
}
.clients-card .xcrud-actions a.btn-warning:hover {
    background: linear-gradient(135deg, #2980b9, #3498db) !important;
    color: #fff !important;
}

.clients-card .xcrud-actions a.btn-info {
    background: rgba(255,255,255,0.08) !important;
    border: 1px solid rgba(255,255,255,0.12) !important;
    color: #93c5fd !important;
}
.clients-card .xcrud-actions a.btn-info:hover {
    background: rgba(255,255,255,0.14) !important;
    color: #fff !important;
}

.clients-card .xcrud-actions a.btn-default {
    background: rgba(255,255,255,0.08) !important;
    border: 1px solid rgba(255,255,255,0.12) !important;
    color: #93c5fd !important;
}
.clients-card .xcrud-actions a.btn-default:hover {
    background: rgba(255,255,255,0.14) !important;
    color: #fff !important;
}

.clients-card .xcrud-actions a.btn-inverse {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
    color: #cbd5e1 !important;
}
.clients-card .xcrud-actions a.btn-inverse:hover {
    background: rgba(255,255,255,0.12) !important;
    color: #fff !important;
}

.clients-card .xcrud-list td .btn-danger,
.clients-card .xcrud-list td a.btn-danger,
.clients-card .xcrud-actions a.btn-danger {
    background: rgba(229,62,62,0.15) !important;
    border: 1px solid rgba(229,62,62,0.25) !important;
    color: #fc8181 !important;
}
.clients-card .xcrud-list td .btn-danger:hover,
.clients-card .xcrud-actions a.btn-danger:hover {
    background: rgba(229,62,62,0.3) !important;
    color: #fff !important;
}

/* Nav bar (pagination + limit + search) */
.clients-card .xcrud-nav {
    background: rgba(0,0,0,0.15) !important;
    border-top: 1px solid rgba(255,255,255,0.06) !important;
    padding: 14px 20px 10px !important;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}
.clients-card .xcrud-nav .pagination {
    margin: 0 !important;
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}
.clients-card .xcrud-nav .pagination > li > a,
.clients-card .xcrud-nav .pagination > li > span {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(255,255,255,0.08) !important;
    color: #94a3b8 !important;
    border-radius: 8px !important;
    padding: 6px 11px !important;
    font-size: 12px !important;
    font-family: 'Almarai' !important;
    min-width: 32px;
    text-align: center;
    line-height: 1.4 !important;
}
.clients-card .xcrud-nav .pagination > li > a:hover {
    background: rgba(255,255,255,0.12) !important;
    color: #fff !important;
}
.clients-card .xcrud-nav .pagination > .active > a,
.clients-card .xcrud-nav .pagination > .active > span {
    background: linear-gradient(135deg, #1f62b9 0%, #2980b9 100%) !important;
    border-color: #1f62b9 !important;
    color: #fff !important;
}

/* Limit selector */
.clients-card .xcrud-nav .btn-group .btn {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(255,255,255,0.08) !important;
    color: #94a3b8 !important;
    font-size: 11px !important;
    padding: 5px 10px !important;
    font-family: 'Almarai' !important;
    border-radius: 6px !important;
    margin: 0 1px !important;
}
.clients-card .xcrud-nav .btn-group .btn.active,
.clients-card .xcrud-nav .btn-group .btn:hover {
    background: rgba(31,98,185,0.25) !important;
    color: #93c5fd !important;
}

/* XCRUD built-in search */
.clients-card .xcrud-search {
    margin-right: auto;
}
.clients-card .xcrud-search input,
.clients-card .xcrud-search select {
    background: rgba(255,255,255,0.06) !important;
    border: 1px solid rgba(255,255,255,0.1) !important;
    color: #e2e8f0 !important;
    border-radius: 8px !important;
    font-family: 'Almarai' !important;
    font-size: 12px !important;
    padding: 6px 10px !important;
}
.clients-card .xcrud-search input:focus { border-color: rgba(99,179,237,0.5) !important; }

/* Benchmark */
.clients-card .xcrud-benchmark { color: rgba(255,255,255,0.2) !important; font-size: 10px !important; }

/* Overlay */
.clients-card .xcrud-overlay { background-color: rgba(26,26,46,0.6) !important; }

/* Tabs & Forms (edit/add modal) */
.clients-card .xcrud .tab-content {
    border-color: rgba(255,255,255,0.08) !important;
    background: rgba(255,255,255,0.03) !important;
}
.clients-card .xcrud .nav-tabs > li > a {
    color: #94a3b8 !important; border-color: rgba(255,255,255,0.08) !important;
    font-family: 'Almarai' !important;
}
.clients-card .xcrud .nav-tabs > li.active > a {
    background: rgba(255,255,255,0.06) !important;
    border-bottom-color: transparent !important; color: #e2e8f0 !important;
}

.clients-page ~ footer.footer { display: none !important; }

@supports not (overflow: clip) {
    .clients-page { overflow-x: hidden; }
}

/* ── Responsive ──────────────────────────────────────── */
@media (max-width: 991px) {
    .clients-page { padding: 22px 16px 56px; }
}

@media (max-width: 992px) {
    .clients-card .xcrud-list-container {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
    }
    .clients-card .xcrud-list.table {
        min-width: 700px;
    }
}

@media (max-width: 768px) {
    .clients-page { padding: 16px 12px 52px; margin: -10px -15px -60px; }
    .clients-header h1 { font-size: 20px; }
    .clients-filter { padding: 14px 16px; margin-bottom: 20px; }
    .clients-card { border-radius: 12px; }

    /* Card layout on mobile */
    .clients-card .xcrud-list-container { overflow-x: visible !important; }
    .clients-card .xcrud-list.table { min-width: 0; }

    .clients-card .xcrud-list.table > thead { position: absolute !important; left: -9999px !important; }

    .clients-card .xcrud-list.table > tbody > tr {
        display: block !important;
        padding: 14px 16px !important;
        border-bottom: 1px solid rgba(255,255,255,0.06) !important;
        margin: 0 !important;
    }
    .clients-card .xcrud-list > tbody > tr:nth-of-type(odd),
    .clients-card .xcrud-list > tbody > tr:nth-of-type(even) { background: transparent !important; }
    .clients-card .xcrud-list > tbody > tr:nth-of-type(odd) > td,
    .clients-card .xcrud-list > tbody > tr:nth-of-type(even) > td { background: transparent !important; }
    .clients-card .xcrud-list > tbody > tr:hover > td { background: transparent !important; }
    .clients-card .xcrud-list > tbody > tr:hover { background: rgba(31,98,185,0.06) !important; }

    .clients-card .xcrud-list > tbody > tr > td {
        display: flex !important;
        align-items: flex-start !important;
        gap: 10px !important;
        padding: 4px 0 !important;
        border: none !important;
        width: 100% !important;
        font-size: 13px !important;
    }
    .clients-card .xcrud-list > tbody > tr > td:before {
        content: attr(data-title);
        font-weight: 700;
        color: #93c5fd;
        font-size: 11px;
        min-width: 80px;
        flex-shrink: 0;
        padding-top: 2px;
    }
    .clients-card .xcrud-list > tbody > tr > td[data-title=""]:before,
    .clients-card .xcrud-list > tbody > tr > td.xcrud-num:before { display: none; }

    .clients-card .xcrud-list > tbody > tr > td.xcrud-num { display: none !important; }

    .clients-card .xcrud-list > tbody > tr > td.xcrud-actions {
        padding-top: 10px !important;
        justify-content: flex-end !important;
        gap: 6px !important;
    }
    .clients-card .xcrud-list > tbody > tr > td.xcrud-actions:before { display: none !important; }

    .clients-card .xcrud-nav {
        padding: 12px !important;
        justify-content: center;
        flex-direction: column;
        align-items: center;
    }
    .clients-card .xcrud-nav .pagination { justify-content: center; }
}

@media (max-width: 576px) {
    .clients-page {
        margin-left: 0;
        margin-right: 0;
        padding-left: max(10px, env(safe-area-inset-left, 0px));
        padding-right: max(10px, env(safe-area-inset-right, 0px));
    }
    .clients-header h1 { font-size: 17px; line-height: 1.35; }
    .clients-header p { font-size: 11px; line-height: 1.55; }
    .clients-filter {
        flex-direction: column;
        align-items: stretch;
        align-content: flex-start;
        gap: 10px;
    }
    .clients-filter__icon { align-self: flex-start; }
    .clients-filter__input {
        flex: 0 0 auto !important;
        width: 100% !important;
        min-height: 44px;
        max-height: none;
    }
    .clients-filter .btn-view {
        flex: 0 0 auto !important;
        width: 100%;
        min-height: 44px;
    }
    .search-active-bar {
        margin-top: -4px;
        font-size: 11px;
        padding: 8px 12px;
    }
}

@media (max-width: 480px) {
    .clients-filter__input { font-size: 12px !important; }
    .clients-filter .btn-view { padding: 12px 14px; font-size: 0; }
    .clients-filter .btn-view .fa { font-size: 16px; }
    .search-active-bar { font-size: 11px; padding: 6px 12px; }
}

@media (max-width: 380px) {
    .clients-page { padding-left: 8px; padding-right: 8px; }
    .clients-header h1 { font-size: 16px; }
}

/* ── Footer (مطابق لتقرير المبيعات) ─────────────────── */
.clients-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 12px;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 8px;
    row-gap: 6px;
}
.clients-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.clients-footer a:hover { color: rgba(255,255,255,0.6); }
.clients-footer .fa-heart { color: #e53e3e; }
.clients-footer .fahras-credits-line { color: rgba(255,255,255,0.5); }
.clients-footer .fahras-credits-vibes .fahras-credits-icon {
    filter: drop-shadow(0 0 6px rgba(251, 191, 36, 0.25));
}
</style>

<div class="clients-page">
    <div class="container">
        <div class="clients-header">
            <h1><i class="fad fa-users"></i> <?= ($lang == 'ar') ? 'العملاء' : 'Clients' ?></h1>
            <p><?= ($lang == 'ar') ? 'إدارة سجلات العملاء والعقود' : 'Manage client records and contracts' ?></p>
        </div>

        <form action="" method="get" class="clients-filter" id="clients-search-form">
            <span class="clients-filter__icon" aria-hidden="true"><i class="fa fa-search"></i></span>
            <input type="text" id="clients-search-input" name="search" class="clients-filter__input"
                   value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="<?= ($lang == 'ar') ? 'ابحث بالاسم، الرقم الوطني، الهاتف، جهة العمل...' : 'Search by name, national ID, phone, workplace...' ?>"
                   autocomplete="off" />
            <button type="submit" class="btn-view"><i class="fa fa-search"></i> <?= ($lang == 'ar') ? 'بحث' : 'Search' ?></button>
        </form>

        <?php if (!empty($searchTerm)): ?>
        <div class="search-active-bar">
            <span><i class="fa fa-filter"></i> <?= ($lang == 'ar') ? 'نتائج البحث عن' : 'Results for' ?>: <b><?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?></b></span>
            <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>"><i class="fa fa-times"></i> <?= ($lang == 'ar') ? 'مسح' : 'Clear' ?></a>
        </div>
        <?php endif; ?>

        <div class="clients-card">
	<?php
		if ($lang == 'en') {
			Xcrud_config::$is_rtl = 0;
		}
		$xcrud = Xcrud::get_instance();
		$xcrud->table('clients');
		$xcrud->order_by('id','desc');
		$xcrud->language($user['language']);

		if (!empty($user['account'])) {
			$xcrud->where('account =', $user['account']);
		}

		if (!empty($searchTerm)) {
			$safe = $db->quote('%' . $searchTerm . '%');
			$xcrud->where("(clients.name LIKE {$safe} OR clients.national_id LIKE {$safe} OR clients.phone LIKE {$safe} OR clients.work LIKE {$safe} OR clients.contracts LIKE {$safe} OR clients.home_address LIKE {$safe})");
		}

		$xcrud->label($labels);

		$xcrud->columns('account,name,national_id,phone,contracts,status,sell_date');

		$xcrud->column_callback('sell_date', 'fahras_format_sell_date_list', 'functions.php');
		$xcrud->column_callback('employment_type', 'fahras_translate_employment_type', 'functions.php');
		$xcrud->column_callback('is_guarantor', 'fahras_translate_is_guarantor', 'functions.php');

		$xcrud->fields('created_on', true);

		$xcrud->relation('account','accounts','id','name');

		$attachments = $xcrud->nested_table('attachments','id','attachments','client');
		$attachments->order_by('id','desc');
		$attachments->language($user['language']);

		$attachments->columns('client', true);
		$attachments->fields('client', true);

		$attachments->label(array(
								'image' => $labels['image'],
		));

		$attachments->change_type('image','image','',array('width'=>1000));

		$xcrud->unset_view();

		if (!user_can('clients', 'create')) $xcrud->unset_add();
		if (!user_can('clients', 'edit'))   $xcrud->unset_edit();
		if (!user_can('clients', 'delete')) $xcrud->unset_remove();

		echo $xcrud->render();
	?>
        </div>

        <div class="clients-footer">
            <?php include __DIR__ . '/includes/fahras-footer-credits.php'; ?>
            &nbsp;&middot;&nbsp;
            &copy; <?=_e('فهرس')?> <?=date('Y')?>
        </div>
    </div>
</div>

<script>
(function() {
    var input = document.getElementById('clients-search-input');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                if (window.location.search.includes('search=')) {
                    window.location.href = window.location.pathname;
                }
            }
        });
    }

    function addDataTitles() {
        var table = document.querySelector('.clients-card .xcrud-list');
        if (!table) return;
        var ths = table.querySelectorAll('thead th');
        var headers = [];
        ths.forEach(function(th) { headers.push(th.textContent.trim()); });
        table.querySelectorAll('tbody tr').forEach(function(row) {
            var tds = row.querySelectorAll('td');
            tds.forEach(function(td, i) {
                if (i < headers.length) td.setAttribute('data-title', headers[i]);
            });
        });
    }

    addDataTitles();

    var observer = new MutationObserver(function() {
        setTimeout(addDataTitles, 100);
    });
    var card = document.querySelector('.clients-card .xcrud-ajax');
    if (card) observer.observe(card, { childList: true, subtree: true });
})();
</script>

<?php include 'footer.php'; ?>
