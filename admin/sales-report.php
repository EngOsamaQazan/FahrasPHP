<?php
$token = 'mojeer';
$page_title = 'تقرير المبيعات';
include 'header.php';

require_permission('sales_report', 'view');

$today = date('Y-m-d');
$dateFrom = $_GET['from'] ?? $today;
$dateTo   = $_GET['to']   ?? $today;

$sourceLabels = [
    'zajal' => 'زجل',
    'jadal' => 'جدل',
    'namaa' => 'نماء',
    'bseel' => 'بسيل',
    'watar' => 'وتر',
    'majd'  => 'عالم المجد',
];

$localStmt = $db->prepare("
    SELECT acc.name AS company_name,
           COUNT(c.id) AS total_clients,
           SUM(CASE WHEN c.remaining_amount IS NOT NULL THEN c.remaining_amount ELSE 0 END) AS total_amount,
           SUM(CASE WHEN c.status IN ('نشط','فعال','active','جديد') THEN 1 ELSE 0 END) AS active_count,
           SUM(CASE WHEN c.status IN ('منتهي','finished','completed','closed') THEN 1 ELSE 0 END) AS finished_count,
           SUM(CASE WHEN c.status IN ('ملغي','canceled','cancelled') THEN 1 ELSE 0 END) AS canceled_count
    FROM clients c
    INNER JOIN accounts acc ON acc.id = c.account
    WHERE DATE(COALESCE(c.created_on, c.sell_date)) BETWEEN :df AND :dt
    GROUP BY acc.id, acc.name
    ORDER BY total_clients DESC
");
$localStmt->execute(['df' => $dateFrom, 'dt' => $dateTo]);
$localData = $localStmt->fetchAll();

$remoteStmt = $db->prepare("
    SELECT r.source AS company_key,
           COUNT(r.id) AS total_clients,
           SUM(CASE WHEN r.remaining_amount IS NOT NULL THEN r.remaining_amount ELSE 0 END) AS total_amount,
           SUM(CASE WHEN r.status IN ('نشط','فعال','active','جديد') THEN 1 ELSE 0 END) AS active_count,
           SUM(CASE WHEN r.status IN ('منتهي','finished','completed','closed') THEN 1 ELSE 0 END) AS finished_count,
           SUM(CASE WHEN r.status IN ('ملغي','canceled','cancelled') THEN 1 ELSE 0 END) AS canceled_count
    FROM remote_clients r
    WHERE DATE(COALESCE(r.created_on, r.sell_date)) BETWEEN :df AND :dt
    GROUP BY r.source
    ORDER BY total_clients DESC
");
$remoteStmt->execute(['df' => $dateFrom, 'dt' => $dateTo]);
$remoteData = $remoteStmt->fetchAll();

$localDetailStmt = $db->prepare("
    SELECT c.name, c.national_id, c.phone, c.status, c.remaining_amount,
           COALESCE(c.created_on, c.sell_date) AS effective_date,
           acc.name AS company_name
    FROM clients c
    INNER JOIN accounts acc ON acc.id = c.account
    WHERE DATE(COALESCE(c.created_on, c.sell_date)) BETWEEN :df AND :dt
    ORDER BY COALESCE(c.created_on, c.sell_date) DESC
    LIMIT 200
");
$localDetailStmt->execute(['df' => $dateFrom, 'dt' => $dateTo]);
$localDetails = $localDetailStmt->fetchAll();

$remoteDetailStmt = $db->prepare("
    SELECT r.name, r.national_id, r.phone, r.status, r.remaining_amount,
           COALESCE(r.created_on, r.sell_date) AS effective_date,
           r.source AS company_key
    FROM remote_clients r
    WHERE DATE(COALESCE(r.created_on, r.sell_date)) BETWEEN :df AND :dt
    ORDER BY COALESCE(r.created_on, r.sell_date) DESC
    LIMIT 200
");
$remoteDetailStmt->execute(['df' => $dateFrom, 'dt' => $dateTo]);
$remoteDetails = $remoteDetailStmt->fetchAll();

$grandTotalClients = 0;
$grandTotalAmount = 0;
foreach ($localData as $row) {
    $grandTotalClients += $row['total_clients'];
    $grandTotalAmount += $row['total_amount'];
}
foreach ($remoteData as $row) {
    $grandTotalClients += $row['total_clients'];
    $grandTotalAmount += $row['total_amount'];
}

$companyCount = count($localData) + count($remoteData);

$isToday = ($dateFrom === $today && $dateTo === $today);
$periodLabel = $isToday
    ? _e('اليوم') . ' (' . $today . ')'
    : $dateFrom . ' — ' . $dateTo;
?>

<style>
.sales-page {
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
.sales-page *,
.sales-page *::before,
.sales-page *::after {
    box-sizing: border-box;
}
.sales-page .container {
    width: 100%;
    max-width: 100%;
}
.sales-header {
    text-align: center;
    margin-bottom: 24px;
}
.sales-header h1 { color: #fff; font-size: 24px; font-weight: 800; margin: 0 0 6px; }
.sales-header p { color: rgba(255,255,255,0.4); font-size: 12px; margin: 0; }

.sales-filter {
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
.sales-filter input[type="date"] {
    flex: 0 1 auto;
    width: 100%;
    max-width: 100%;
    min-width: 0;
}
.sales-filter .btn-view,
.sales-filter .btn-today {
    flex: 0 0 auto;
    min-width: 0;
}
@media (min-width: 577px) {
    .sales-filter input[type="date"] {
        flex: 0 1 168px;
        width: auto;
        max-width: 100%;
    }
}
.sales-filter label {
    color: rgba(255,255,255,0.5);
    font-size: 12px;
    font-weight: 700;
    margin: 0;
}
.sales-filter input[type="date"] {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    color: #e2e8f0;
    padding: 9px 14px;
    font-size: 13px;
    font-family: 'Almarai', sans-serif;
    outline: none;
    direction: ltr;
}
.sales-filter input[type="date"]:focus { border-color: rgba(99,179,237,0.5); }
.sales-filter .btn-view {
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
}
.sales-filter .btn-view:hover { background: linear-gradient(135deg, #2980b9, #3498db); }
.sales-filter .btn-today {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    color: #93c5fd;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    font-family: 'Almarai', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}
.sales-filter .btn-today:hover { background: rgba(255,255,255,0.14); color: #fff; }

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr));
    gap: 14px;
    max-width: 900px;
    margin: 0 auto 24px;
    width: 100%;
}
.stat-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 18px 20px;
    text-align: center;
}
.stat-card .stat-value {
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
}
.stat-card .stat-label {
    font-size: 12px;
    color: rgba(255,255,255,0.4);
    margin-top: 4px;
}
.stat-card.primary .stat-value { color: #63b3ed; }
.stat-card.success .stat-value { color: #68d391; }
.stat-card.warning .stat-value { color: #fbd38d; }

.companies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 240px), 1fr));
    gap: 14px;
    max-width: 900px;
    margin: 0 auto 24px;
    width: 100%;
}
.company-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    padding: 18px 20px;
    transition: all 0.2s;
    cursor: pointer;
}
.company-card:hover { background: rgba(255,255,255,0.08); border-color: rgba(99,179,237,0.3); }
.company-card.active { border-color: rgba(99,179,237,0.5); background: rgba(99,179,237,0.08); }
.company-card .cc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}
.company-card .cc-name {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
}
.company-card .cc-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.cc-badge.local  { background: rgba(148,163,184,0.15); color: #94a3b8; }
.cc-badge.zajal  { background: rgba(59,130,246,0.2);  color: #93c5fd; }
.cc-badge.jadal  { background: rgba(34,197,94,0.2);   color: #86efac; }
.cc-badge.namaa  { background: rgba(234,179,8,0.2);   color: #fde68a; }
.cc-badge.bseel  { background: rgba(236,72,153,0.2);  color: #f9a8d4; }
.cc-badge.watar  { background: rgba(139,92,246,0.2);  color: #c4b5fd; }
.cc-badge.majd  { background: rgba(249,115,22,0.2);  color: #fdba74; }

.company-card .cc-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.company-card .cc-stat {
    background: rgba(255,255,255,0.04);
    border-radius: 8px;
    padding: 8px 10px;
    text-align: center;
}
.company-card .cc-stat-val {
    font-size: 18px;
    font-weight: 800;
    color: #fff;
}
.company-card .cc-stat-lbl {
    font-size: 10px;
    color: rgba(255,255,255,0.35);
    margin-top: 2px;
}

.detail-section {
    max-width: 900px;
    margin: 0 auto 24px;
    width: 100%;
}
.detail-section h3 {
    color: #93c5fd;
    font-size: 15px;
    font-weight: 700;
    margin: 0 0 14px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.detail-table-wrap {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
    max-width: 100%;
}
.detail-table {
    width: 100%;
    min-width: 640px;
    border-collapse: collapse;
}
.detail-table th {
    background: rgba(99,179,237,0.06);
    color: #93c5fd;
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 700;
    text-align: right;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    white-space: nowrap;
}
.detail-table td {
    padding: 10px 14px;
    font-size: 13px;
    color: #e0e6ed;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.detail-table td:nth-child(2) {
    white-space: normal;
    word-break: break-word;
    max-width: 200px;
}
.detail-table tr:hover td { background: rgba(255,255,255,0.03); }
.detail-table .empty-row td {
    text-align: center;
    color: rgba(255,255,255,0.3);
    padding: 30px;
}
.detail-table .status-active { color: #68d391; font-weight: 700; }
.detail-table .status-finished { color: #a0aec0; }
.detail-table .status-canceled { color: #fc8181; }

.sales-footer {
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
.sales-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.sales-footer a:hover { color: rgba(255,255,255,0.6); }
.sales-footer .fa-heart { color: #e53e3e; }
.sales-page ~ footer.footer { display: none !important; }

@supports not (overflow: clip) {
    .sales-page { overflow-x: hidden; }
}

@media (max-width: 991px) {
    .sales-page { padding: 22px 16px 56px; }
    .stats-row { grid-template-columns: repeat(auto-fit, minmax(min(100%, 140px), 1fr)); }
}

@media (max-width: 768px) {
    .sales-page { padding: 16px 12px 52px; margin: -10px -15px -60px; }
    .sales-header h1 { font-size: 20px; }
    .companies-grid { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: 1fr 1fr; gap: 10px; }
    .sales-filter { padding: 14px 16px; }
    .detail-table th,
    .detail-table td { padding: 8px 10px; font-size: 12px; }
}

@media (max-width: 576px) {
    .sales-page {
        margin-left: 0;
        margin-right: 0;
        padding-left: max(10px, env(safe-area-inset-left, 0px));
        padding-right: max(10px, env(safe-area-inset-right, 0px));
    }
    .sales-header h1 { font-size: 17px; line-height: 1.35; }
    .sales-header p { font-size: 11px; line-height: 1.55; }
    .stats-row { grid-template-columns: 1fr; }
    .stat-card { padding: 14px 16px; }
    .stat-card .stat-value { font-size: 22px; }
    .sales-filter {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    .sales-filter label { width: 100%; }
    .sales-filter input[type="date"],
    .sales-filter .btn-view,
    .sales-filter .btn-today {
        flex: 0 0 auto !important;
        width: 100%;
        min-height: 44px;
    }
    .company-card { padding: 14px 16px; }
    .detail-section h3 { font-size: 14px; }
    .detail-table { min-width: 580px; }
}

/* أجهزة الطي والشاشات الضيقة جداً */
@media (max-width: 380px) {
    .sales-page { padding-left: 8px; padding-right: 8px; }
    .sales-header h1 { font-size: 16px; }
    .stat-card .stat-value { font-size: 20px; }
    .companies-grid { grid-template-columns: minmax(0, 1fr); }
    .detail-table { min-width: 520px; }
    .detail-table th,
    .detail-table td { padding: 7px 8px; font-size: 11px; }
}
</style>

<div class="sales-page">
    <div class="container">
        <div class="sales-header">
            <h1><i class="fa fa-chart-line"></i> <?= _e('تقرير المبيعات') ?></h1>
            <p><?= _e('نظرة عامة على المبيعات حسب الشركة لفترة محددة') ?> — <?= $periodLabel ?></p>
        </div>

        <form method="get" class="sales-filter">
            <label><?= _e('من') ?></label>
            <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
            <label><?= _e('إلى') ?></label>
            <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>">
            <button type="submit" class="btn-view"><i class="fa fa-search"></i> <?= _e('عرض') ?></button>
            <a href="sales-report" class="btn-today"><i class="fa fa-calendar-day"></i> <?= _e('اليوم') ?></a>
        </form>

        <div class="stats-row">
            <div class="stat-card primary">
                <div class="stat-value"><?= $grandTotalClients ?></div>
                <div class="stat-label"><?= _e('إجمالي العملاء المباعين') ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-value"><?= number_format($grandTotalAmount, 0) ?></div>
                <div class="stat-label"><?= _e('المبلغ الإجمالي') ?> (<?= _e('دينار') ?>)</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value"><?= $companyCount ?></div>
                <div class="stat-label"><?= _e('الشركات النشطة') ?></div>
            </div>
        </div>

        <?php if (!empty($localData) || !empty($remoteData)) { ?>
        <div class="companies-grid">
            <?php foreach ($localData as $row) { ?>
            <div class="company-card" onclick="filterTable('local', '<?= htmlspecialchars($row['company_name'], ENT_QUOTES) ?>', this)">
                <div class="cc-header">
                    <span class="cc-name"><?= htmlspecialchars($row['company_name']) ?></span>
                    <span class="cc-badge local"><?= _e('محلي') ?></span>
                </div>
                <div class="cc-stats">
                    <div class="cc-stat">
                        <div class="cc-stat-val"><?= $row['total_clients'] ?></div>
                        <div class="cc-stat-lbl"><?= _e('العملاء') ?></div>
                    </div>
                    <div class="cc-stat">
                        <div class="cc-stat-val"><?= number_format($row['total_amount'], 0) ?></div>
                        <div class="cc-stat-lbl"><?= _e('المبلغ') ?></div>
                    </div>
                </div>
            </div>
            <?php } ?>
            <?php foreach ($remoteData as $row) {
                $srcKey = $row['company_key'];
                $srcLabel = $sourceLabels[$srcKey] ?? $srcKey;
            ?>
            <div class="company-card" onclick="filterTable('remote', '<?= htmlspecialchars($srcKey, ENT_QUOTES) ?>', this)">
                <div class="cc-header">
                    <span class="cc-name"><?= htmlspecialchars($srcLabel) ?></span>
                    <span class="cc-badge <?= htmlspecialchars($srcKey) ?>"><?= htmlspecialchars($srcLabel) ?></span>
                </div>
                <div class="cc-stats">
                    <div class="cc-stat">
                        <div class="cc-stat-val"><?= $row['total_clients'] ?></div>
                        <div class="cc-stat-lbl"><?= _e('العملاء') ?></div>
                    </div>
                    <div class="cc-stat">
                        <div class="cc-stat-val"><?= number_format($row['total_amount'], 0) ?></div>
                        <div class="cc-stat-lbl"><?= _e('المبلغ') ?></div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php } ?>

        <div class="detail-section">
            <h3><i class="fa fa-list"></i> <?= _e('تفاصيل المبيعات') ?> <span id="filter-label" style="font-size:12px;color:rgba(255,255,255,0.3);font-weight:400;"></span></h3>
            <div class="detail-table-wrap">
                <table class="detail-table" id="sales-detail-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= _e('اسم العميل') ?></th>
                            <th><?= _e('الرقم الوطني') ?></th>
                            <th><?= _e('الهاتف') ?></th>
                            <th><?= _e('الشركة') ?></th>
                            <th><?= _e('الحالة') ?></th>
                            <th><?= _e('المبلغ') ?></th>
                            <th><?= _e('التاريخ') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $allDetails = [];
                    foreach ($localDetails as $d) {
                        $allDetails[] = [
                            'name' => $d['name'],
                            'national_id' => $d['national_id'],
                            'phone' => $d['phone'],
                            'company' => $d['company_name'],
                            'company_key' => $d['company_name'],
                            'type' => 'local',
                            'status' => $d['status'],
                            'amount' => $d['remaining_amount'],
                            'date' => $d['effective_date'],
                        ];
                    }
                    foreach ($remoteDetails as $d) {
                        $srcLabel = $sourceLabels[$d['company_key']] ?? $d['company_key'];
                        $allDetails[] = [
                            'name' => $d['name'],
                            'national_id' => $d['national_id'],
                            'phone' => $d['phone'],
                            'company' => $srcLabel,
                            'company_key' => $d['company_key'],
                            'type' => 'remote',
                            'status' => $d['status'],
                            'amount' => $d['remaining_amount'],
                            'date' => $d['effective_date'],
                        ];
                    }

                    usort($allDetails, function($a, $b) {
                        return strtotime($b['date'] ?: '2000-01-01') - strtotime($a['date'] ?: '2000-01-01');
                    });

                    if (empty($allDetails)) {
                        echo '<tr class="empty-row"><td colspan="8">' . _e('لم يتم العثور على مبيعات لهذه الفترة') . '</td></tr>';
                    }

                    $idx = 0;
                    foreach ($allDetails as $d) {
                        $idx++;
                        $statusClass = '';
                        $st = mb_strtolower(trim($d['status'] ?? ''));
                        if (in_array($st, ['نشط','فعال','active','جديد'])) $statusClass = 'status-active';
                        elseif (in_array($st, ['منتهي','finished','completed','closed'])) $statusClass = 'status-finished';
                        elseif (in_array($st, ['ملغي','canceled','cancelled'])) $statusClass = 'status-canceled';

                        $badgeClass = $d['type'] === 'local' ? 'local' : htmlspecialchars($d['company_key']);
                    ?>
                    <tr data-type="<?= $d['type'] ?>" data-company="<?= htmlspecialchars($d['company_key'], ENT_QUOTES) ?>">
                        <td><?= $idx ?></td>
                        <td><b><?= htmlspecialchars($d['name']) ?></b></td>
                        <td style="direction:ltr;"><?= htmlspecialchars($d['national_id']) ?></td>
                        <td style="direction:ltr;"><?= htmlspecialchars($d['phone']) ?></td>
                        <td><span class="cc-badge <?= $badgeClass ?>"><?= htmlspecialchars($d['company']) ?></span></td>
                        <td class="<?= $statusClass ?>"><?= htmlspecialchars($d['status']) ?></td>
                        <td><?= $d['amount'] !== null ? number_format((float)$d['amount'], 2) : '—' ?></td>
                        <td style="direction:ltr;white-space:nowrap;"><?= htmlspecialchars($d['date']) ?></td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sales-footer">
            <?php include __DIR__ . '/includes/fahras-footer-credits.php'; ?>
            &nbsp;&middot;&nbsp;
            &copy; <?= _e('فهرس') ?> <?= date('Y') ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
var currentFilter = null;

function filterTable(type, companyKey, el) {
    var rows = document.querySelectorAll('#sales-detail-table tbody tr[data-type]');
    var label = document.getElementById('filter-label');
    var cards = document.querySelectorAll('.company-card');

    if (currentFilter === type + '|' + companyKey) {
        currentFilter = null;
        rows.forEach(function(r) { r.style.display = ''; });
        label.textContent = '';
        cards.forEach(function(c) { c.classList.remove('active'); });
        reNumber();
        return;
    }

    currentFilter = type + '|' + companyKey;
    var shown = 0;
    rows.forEach(function(r) {
        if (r.getAttribute('data-type') === type && r.getAttribute('data-company') === companyKey) {
            r.style.display = '';
            shown++;
        } else {
            r.style.display = 'none';
        }
    });
    label.textContent = '(' + shown + ' <?= _e('نتائج') ?>)';

    cards.forEach(function(c) { c.classList.remove('active'); });
    if (el) el.classList.add('active');
    reNumber();
}

function reNumber() {
    var rows = document.querySelectorAll('#sales-detail-table tbody tr[data-type]');
    var n = 0;
    rows.forEach(function(r) {
        if (r.style.display !== 'none') {
            n++;
            r.cells[0].textContent = n;
        }
    });
}
</script>
