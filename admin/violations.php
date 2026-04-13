<?php
$token = 'mojeer';
$page_title = 'المخالفات';
include 'header.php';

require_once __DIR__ . '/../includes/violation_engine.php';

require_permission('violations', 'view');

// formatTo12h() is now in bootstrap.php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && user_can('violations', 'manage')) {
    $action = $_POST['action'] ?? '';
    $vid    = (int)($_POST['violation_id'] ?? 0);

    if ($vid > 0 && csrf_verify()) {
        $current = $db->get_row('violations', ['id' => $vid]);
        $oldStatus = $current['status'] ?? '';

        if ($action === 'mark_paid') {
            $db->update('violations', ['is_paid' => 1, 'paid_date' => date('Y-m-d')], ['id' => $vid]);
            $db->insert('violation_log', ['violation_id' => $vid, 'action' => 'paid', 'user_id' => $user['id'], 'old_status' => $oldStatus, 'new_status' => $oldStatus]);
        } elseif ($action === 'resolve') {
            $db->update('violations', ['status' => 'resolved'], ['id' => $vid]);
            $db->insert('violation_log', ['violation_id' => $vid, 'action' => 'resolved', 'user_id' => $user['id'], 'old_status' => $oldStatus, 'new_status' => 'resolved']);
        } elseif ($action === 'exempt_150') {
            $db->update('violations', ['status' => 'exempted_below_150'], ['id' => $vid]);
            $db->insert('violation_log', ['violation_id' => $vid, 'action' => 'exempted', 'user_id' => $user['id'], 'old_status' => $oldStatus, 'new_status' => 'exempted_below_150']);
        } elseif ($action === 'exempt_guarantor') {
            $db->update('violations', ['status' => 'exempted_guarantor'], ['id' => $vid]);
            $db->insert('violation_log', ['violation_id' => $vid, 'action' => 'exempted', 'user_id' => $user['id'], 'old_status' => $oldStatus, 'new_status' => 'exempted_guarantor']);
        } elseif ($action === 'dispute') {
            $db->update('violations', ['status' => 'disputed', 'notes' => $_POST['notes'] ?? ''], ['id' => $vid]);
            $db->insert('violation_log', ['violation_id' => $vid, 'action' => 'disputed', 'user_id' => $user['id'], 'old_status' => $oldStatus, 'new_status' => 'disputed', 'notes' => $_POST['notes'] ?? '']);
        }

        log_activity('violation_' . $action, 'violation', $vid);
        header('Location: /admin/violations?' . $_SERVER['QUERY_STRING']);
        exit;
    }
}

$filterMonth   = $_GET['month'] ?? '';
$filterStatus  = $_GET['status'] ?? '';
$filterAccount = $_GET['account'] ?? '';

$where = "1=1";
$params = [];

if (!empty($filterMonth)) {
    $where .= " AND DATE_FORMAT(violation_month, '%Y-%m') = :month";
    $params['month'] = $filterMonth;
}
if (!empty($filterStatus)) {
    $where .= " AND status = :status";
    $params['status'] = $filterStatus;
}
if (!empty($filterAccount)) {
    $where .= " AND (entitled_account = :acc OR violating_account = :acc2)";
    $params['acc'] = $filterAccount;
    $params['acc2'] = $filterAccount;
}

if (has_role(['company_admin'])) {
    $userAccount = $db->get_var('accounts', ['id' => $user['account']], ['name']) ?: '';
    $where .= " AND (entitled_account = :ua OR violating_account = :ua2)";
    $params['ua'] = $userAccount;
    $params['ua2'] = $userAccount;
}

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

try {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM violations WHERE $where");
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
} catch (Throwable $e) {
    $totalRows = 0;
}
$totalPages = max(1, ceil($totalRows / $perPage));

try {
    $stmt = $db->prepare("SELECT * FROM violations WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $violations = $stmt->fetchAll();
} catch (Throwable $e) {
    $violations = [];
}

$accounts = $db->get_all('accounts');
?>

<style>
.violations-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}

.violations-header {
    text-align: center;
    margin-bottom: 28px;
}
.violations-header h1 {
    color: #fff;
    font-size: 24px;
    font-weight: 800;
    margin: 0 0 6px;
}
.violations-header p {
    color: rgba(255,255,255,0.4);
    font-size: 12px;
    margin: 0;
}

.filter-card {
    background: rgba(255,255,255,0.06);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 24px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}
.filter-card .filter-row {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}
.filter-card input,
.filter-card select {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    color: #e2e8f0;
    padding: 10px 14px;
    font-size: 13px;
    font-family: 'Almarai', sans-serif;
    outline: none;
    transition: border-color 0.2s;
    min-width: 150px;
}
.filter-card input:focus,
.filter-card select:focus {
    border-color: rgba(99,179,237,0.5);
}
.filter-card select option {
    background: #1a1a2e;
    color: #e2e8f0;
}
.filter-card .v-month-field {
    min-width: 170px;
    cursor: pointer;
}

/* تقويم الشهر (Vanillajs Datepicker) */
.datepicker-dropdown {
    z-index: 10050 !important;
    font-family: 'Almarai', sans-serif !important;
    direction: rtl;
}
.dark-theme .datepicker-picker {
    background: #1e293b !important;
    color: #e2e8f0 !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
}
.dark-theme .datepicker-cell:not(.disabled):hover {
    background: rgba(99, 179, 237, 0.15) !important;
}
.dark-theme .datepicker-cell.selected,
.dark-theme .datepicker-cell.selected:hover {
    background: #2563eb !important;
    color: #fff !important;
}
.filter-card .btn-filter {
    background: linear-gradient(135deg, #1f62b9, #2980b9);
    border: none;
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    font-family: 'Almarai', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
}
.filter-card .btn-filter:hover {
    background: linear-gradient(135deg, #2980b9, #3498db);
}

.summary-card {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 24px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}
.summary-card-header {
    background: rgba(99,179,237,0.08);
    border-bottom: 1px solid rgba(99,179,237,0.1);
    padding: 14px 18px;
    font-size: 14px;
    font-weight: 700;
    color: #93c5fd;
}
.summary-card table {
    width: 100%;
    border-collapse: collapse;
}
.summary-card th {
    background: rgba(99,179,237,0.06);
    color: #93c5fd;
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 700;
    text-align: right;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.summary-card td {
    padding: 10px 14px;
    font-size: 13px;
    color: #e0e6ed;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.summary-card tr:hover td {
    background: rgba(255,255,255,0.03);
}

.violation-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 16px 18px;
    margin-bottom: 12px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
    transition: all 0.2s;
}
.violation-card:hover {
    background: rgba(255,255,255,0.08);
    border-color: rgba(255,255,255,0.15);
}

.v-card-top {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}
.v-card-num {
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
}
.v-card-name {
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    flex: 1;
}
.v-card-nid {
    font-size: 12px;
    color: #63b3ed;
    direction: ltr;
}

.v-card-details {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 8px 16px;
    margin-bottom: 10px;
}
.v-card-detail {
    font-size: 12px;
    color: rgba(255,255,255,0.45);
}
.v-card-detail span {
    color: rgba(255,255,255,0.8);
    font-weight: 600;
    margin-right: 4px;
}

.v-card-bottom {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.v-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.v-badge-danger { background: rgba(229,62,62,0.15); color: #fc8181; }
.v-badge-success { background: rgba(72,187,120,0.15); color: #68d391; }
.v-badge-warning { background: rgba(236,201,75,0.15); color: #f6e05e; }
.v-badge-info { background: rgba(99,179,237,0.15); color: #93c5fd; }
.v-badge-default { background: rgba(148,163,184,0.12); color: #94a3b8; }
.v-badge-entitled { background: rgba(72,187,120,0.15); color: #68d391; }
.v-badge-violating { background: rgba(229,62,62,0.15); color: #fc8181; }

.v-card-actions {
    display: flex;
    gap: 6px;
    margin-right: auto;
}
.v-card-actions .btn-action {
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
.v-card-actions .btn-action:hover {
    background: rgba(255,255,255,0.12);
    color: #fff;
    border-color: rgba(255,255,255,0.2);
}
.v-card-actions .btn-action.btn-pay:hover { background: rgba(72,187,120,0.2); color: #68d391; border-color: rgba(72,187,120,0.3); }
.v-card-actions .btn-action.btn-exempt:hover { background: rgba(99,179,237,0.2); color: #93c5fd; border-color: rgba(99,179,237,0.3); }
.v-card-actions .btn-action.btn-resolve:hover { background: rgba(236,201,75,0.2); color: #f6e05e; border-color: rgba(236,201,75,0.3); }

.v-pagination {
    text-align: center;
    margin: 24px 0;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}
.v-pagination .pagination {
    margin: 0;
    display: inline-flex;
    gap: 4px;
}
.v-pagination .pagination li a,
.v-pagination .pagination li span {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.6);
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.2s;
}
.v-pagination .pagination li a:hover {
    background: rgba(255,255,255,0.12);
    color: #fff;
}
.v-pagination .pagination li.active span {
    background: linear-gradient(135deg, #1f62b9, #2980b9);
    border-color: transparent;
    color: #fff;
}
.v-pagination .pagination li.disabled span {
    opacity: 0.3;
}
.v-pagination .page-info {
    font-size: 12px;
    color: rgba(255,255,255,0.3);
    margin-top: 8px;
}

.results-bar-v {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    margin-bottom: 18px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}
.results-bar-v h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #e2e8f0;
}
.results-bar-v .badge-count {
    background: #1f62b9;
    color: #fff;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.no-results-v {
    text-align: center;
    padding: 50px 20px;
    color: rgba(255,255,255,0.3);
    max-width: 900px;
    margin: 0 auto;
}
.no-results-v i { font-size: 48px; margin-bottom: 14px; display: block; }

.violations-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.violations-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.violations-footer a:hover { color: rgba(255,255,255,0.6); }
.violations-footer .fa-heart { color: #e53e3e; }

.v-amount {
    color: #fc8181;
    font-weight: 700;
}

.violations-page ~ footer.footer { display: none !important; }

@media (max-width: 768px) {
    .violations-page { padding: 16px 10px 60px; margin: -10px -15px -60px; }
    .filter-card .filter-row { flex-direction: column; }
    .filter-card input, .filter-card select { width: 100%; min-width: 0; }
    .v-card-details { grid-template-columns: 1fr 1fr; }
    .v-card-top { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="violations-page">
    <div class="container">
        <div class="violations-header">
            <h1><i class="fa fa-exclamation-triangle"></i> <?= _e('المخالفات') ?></h1>
            <p><?= _e('تتبع وإدارة مخالفات العقود') ?></p>
        </div>

        <div class="filter-card">
            <form method="get">
                <div class="filter-row">
                    <input type="text"
                           name="month"
                           class="v-month-field"
                           value="<?= htmlspecialchars($filterMonth) ?>"
                           placeholder="<?= _e('الشهر') ?>"
                           autocomplete="off"
                           inputmode="none">
                    <select name="status">
                        <option value=""><?= _e('جميع الحالات') ?></option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>><?= _e('نشطة') ?></option>
                        <option value="disputed" <?= $filterStatus === 'disputed' ? 'selected' : '' ?>><?= _e('متنازع عليها') ?></option>
                        <option value="resolved" <?= $filterStatus === 'resolved' ? 'selected' : '' ?>><?= _e('تم الحل') ?></option>
                        <option value="exempted_below_150" <?= $filterStatus === 'exempted_below_150' ? 'selected' : '' ?>><?= _e('معفاة (أقل من 150)') ?></option>
                        <option value="exempted_guarantor" <?= $filterStatus === 'exempted_guarantor' ? 'selected' : '' ?>><?= _e('معفاة (كفيل)') ?></option>
                    </select>
                    <select name="account">
                        <option value=""><?= _e('جميع الشركات') ?></option>
                        <?php foreach ($accounts as $acc) { ?>
                        <option value="<?= htmlspecialchars($acc['name']) ?>" <?= $filterAccount === $acc['name'] ? 'selected' : '' ?>><?= htmlspecialchars($acc['name']) ?></option>
                        <?php } ?>
                    </select>
                    <button type="submit" class="btn-filter"><i class="fa fa-filter"></i> <?= _e('تصفية') ?></button>
                </div>
            </form>
        </div>

    <?php
    try {
        $summaryStmt = $db->prepare("
            SELECT violating_account, COUNT(*) as total,
                   SUM(CASE WHEN is_paid = 0 AND status = 'active' THEN fine_amount ELSE 0 END) as unpaid_total
            FROM violations WHERE status = 'active' AND violation_month = :m
            GROUP BY violating_account ORDER BY total DESC
        ");
        $summaryStmt->execute(['m' => date('Y-m-01')]);
        $summary = $summaryStmt->fetchAll();
    } catch (Throwable $e) {
        $summary = [];
    }

    if (!empty($summary) && user_can('violations', 'manage')) {
    ?>
        <div class="summary-card">
            <div class="summary-card-header"><i class="fa fa-chart-bar"></i> <?= _e('ملخص هذا الشهر') ?></div>
            <table>
                <thead>
                    <tr><th><?= _e('الشركة المخالفة') ?></th><th><?= _e('المخالفات') ?></th><th><?= _e('المبلغ غير المدفوع') ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $s) { ?>
                    <tr>
                        <td><b><?= htmlspecialchars($s['violating_account']) ?></b></td>
                        <td><?= $s['total'] ?></td>
                        <td><span class="v-amount"><?= number_format($s['unpaid_total'], 2) ?></span> <?= _e('دينار') ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>

        <div class="results-bar-v">
            <h4><i class="fa fa-gavel"></i> <?= _e('المخالفات') ?></h4>
            <span class="badge-count"><?= $totalRows ?> <?= _e('نتيجة') ?></span>
        </div>

        <?php if (empty($violations)) { ?>
            <div class="no-results-v">
                <i class="fa fa-check-circle"></i>
                <p><?= _e('لم يتم العثور على مخالفات') ?></p>
            </div>
        <?php } ?>

        <?php $i = $offset; foreach ($violations as $v) { $i++; ?>
        <div class="violation-card">
            <div class="v-card-top">
                <div class="v-card-num"><?= $i ?></div>
                <div class="v-card-name"><?= htmlspecialchars($v['client_name']) ?></div>
                <?php if (!empty($v['national_id'])) { ?>
                <div class="v-card-nid"><?= htmlspecialchars($v['national_id']) ?></div>
                <?php } ?>
            </div>
            <div class="v-card-details">
                <div class="v-card-detail"><span><?= _e('الشركة المستحقة') ?>:</span> <?= htmlspecialchars($v['entitled_account']) ?></div>
                <div class="v-card-detail"><span><?= _e('تاريخ عقد المستحق') ?>:</span> <?= htmlspecialchars(formatTo12h($v['entitled_sell_date'] ?? '')) ?></div>
                <div class="v-card-detail"><span><?= _e('الشركة المخالفة') ?>:</span> <?= htmlspecialchars($v['violating_account']) ?></div>
                <div class="v-card-detail"><span><?= _e('تاريخ المخالفة') ?>:</span> <?= htmlspecialchars(formatTo12h($v['violating_sell_date'] ?? '')) ?></div>
                <div class="v-card-detail"><span><?= _e('الغرامة') ?>:</span> <span class="v-amount"><?= number_format($v['fine_amount'], 2) ?></span> <?= _e('دينار') ?></div>
            </div>
            <div class="v-card-bottom">
                <?php
                $statusMap = [
                    'active' => ['v-badge-danger', _e('نشطة')],
                    'disputed' => ['v-badge-warning', _e('متنازع عليها')],
                    'resolved' => ['v-badge-success', _e('تم الحل')],
                    'exempted_below_150' => ['v-badge-info', _e('معفاة')],
                    'exempted_guarantor' => ['v-badge-info', _e('معفاة')],
                ];
                $sl = $statusMap[$v['status']] ?? ['v-badge-default', $v['status']];
                ?>
                <span class="v-badge <?= $sl[0] ?>"><?= $sl[1] ?></span>
                <?php if ($v['is_paid']) { ?>
                    <span class="v-badge v-badge-success"><i class="fa fa-check"></i> <?= _e('مدفوعة') ?></span>
                <?php } else { ?>
                    <span class="v-badge v-badge-default"><?= _e('غير مدفوعة') ?></span>
                <?php } ?>

                <?php if (user_can('violations', 'manage') && $v['status'] === 'active') { ?>
                <div class="v-card-actions">
                    <form method="post" style="display:flex;gap:6px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="violation_id" value="<?= $v['id'] ?>">
                        <?php if (!$v['is_paid']) { ?>
                        <button name="action" value="mark_paid" class="btn-action btn-pay" title="<?= _e('تحديد كمدفوعة') ?>"><i class="fa fa-check"></i></button>
                        <?php } ?>
                        <button name="action" value="exempt_150" class="btn-action btn-exempt" title="<?= _e('إعفاء (أقل من 150)') ?>"><i class="fa fa-minus-circle"></i></button>
                        <button name="action" value="exempt_guarantor" class="btn-action btn-exempt" title="<?= _e('إعفاء (كفيل)') ?>"><i class="fa fa-user-shield"></i></button>
                        <button name="action" value="resolve" class="btn-action btn-resolve" title="<?= _e('حل') ?>"><i class="fa fa-handshake"></i></button>
                    </form>
                </div>
                <?php } ?>
            </div>
        </div>
        <?php } ?>

        <?php if ($totalPages > 1) { ?>
        <div class="v-pagination">
            <nav>
                <ul class="pagination">
                    <?php
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                    $qs = http_build_query($queryParams);

                    if ($page > 1) {
                        echo '<li><a href="?page=' . ($page - 1) . ($qs ? '&' . $qs : '') . '">&laquo;</a></li>';
                    } else {
                        echo '<li class="disabled"><span>&laquo;</span></li>';
                    }

                    $start = max(1, $page - 3);
                    $end = min($totalPages, $page + 3);

                    if ($start > 1) {
                        echo '<li><a href="?page=1' . ($qs ? '&' . $qs : '') . '">1</a></li>';
                        if ($start > 2) echo '<li class="disabled"><span>...</span></li>';
                    }

                    for ($pi = $start; $pi <= $end; $pi++) {
                        if ($pi === $page) {
                            echo '<li class="active"><span>' . $pi . '</span></li>';
                        } else {
                            echo '<li><a href="?page=' . $pi . ($qs ? '&' . $qs : '') . '">' . $pi . '</a></li>';
                        }
                    }

                    if ($end < $totalPages) {
                        if ($end < $totalPages - 1) echo '<li class="disabled"><span>...</span></li>';
                        echo '<li><a href="?page=' . $totalPages . ($qs ? '&' . $qs : '') . '">' . $totalPages . '</a></li>';
                    }

                    if ($page < $totalPages) {
                        echo '<li><a href="?page=' . ($page + 1) . ($qs ? '&' . $qs : '') . '">&raquo;</a></li>';
                    } else {
                        echo '<li class="disabled"><span>&raquo;</span></li>';
                    }
                    ?>
                </ul>
            </nav>
            <div class="page-info"><?= _e('صفحة') ?> <?= $page ?> / <?= $totalPages ?> (<?= $totalRows ?> <?= _e('نتيجة') ?>)</div>
        </div>
        <?php } ?>

        <div class="violations-footer">
            <a href="https://fb.com/mujeer.world" target="_blank"><?= _e('صُنع بـ') ?> <i class="fa fa-heart"></i> <?= _e('بواسطة MÜJEER') ?></a>
            &nbsp;&middot;&nbsp;
            &copy; <?= _e('فهرس') ?> <?= date('Y') ?>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/dist/css/datepicker.min.css">
<script src="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/js/datepicker-full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanillajs-datepicker@1.3.4/js/locales/ar.js"></script>
<script>
(function () {
    var el = document.querySelector('.violations-page .v-month-field');
    if (!el || typeof Datepicker === 'undefined') return;
    try {
        new Datepicker(el, {
            format: 'yyyy-mm',
            pickLevel: 1,
            autohide: true,
            language: 'ar',
            orientation: 'right auto'
        });
    } catch (e) {}
})();
</script>

<?php include 'footer.php'; ?>
