<?php
$token = 'mojeer';
$page_title = 'سجل النشاطات';
include 'header.php';

require_permission('activity_log', 'view');

$filterUser   = $_GET['user_id'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDate   = $_GET['date'] ?? '';

$where  = "1=1";
$params = [];

if (!empty($filterUser)) {
    $where .= " AND a.user_id = :uid";
    $params['uid'] = (int)$filterUser;
}
if (!empty($filterAction)) {
    $where .= " AND a.action LIKE :act";
    $params['act'] = '%' . $filterAction . '%';
}
if (!empty($filterDate)) {
    $where .= " AND DATE(a.created_at) = :dt";
    $params['dt'] = $filterDate;
}

$stmt = $db->prepare("
    SELECT a.*, u.name AS user_name, u.username
    FROM activity_log a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE $where
    ORDER BY a.created_at DESC
    LIMIT 500
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$allUsers = $db->get_all('users');

$actionLabels = [
    'login'             => ['fa-sign-in', 'al-badge-success', _e('تسجيل الدخول')],
    'logout'            => ['fa-sign-out', 'al-badge-default', _e('تسجيل الخروج')],
    'search'            => ['fa-search', 'al-badge-info', _e('بحث')],
    'batch_scan'        => ['fa-radar', 'al-badge-warning', _e('مسح جماعي')],
    'sync_apis'         => ['fa-sync', 'al-badge-info', _e('مزامنة API')],
    'violation_mark_paid' => ['fa-check', 'al-badge-success', _e('مخالفة مدفوعة')],
    'violation_resolve' => ['fa-handshake', 'al-badge-warning', _e('مخالفة محلولة')],
    'violation_exempt_150' => ['fa-minus-circle', 'al-badge-info', _e('مخالفة معفاة')],
    'violation_exempt_guarantor' => ['fa-user-shield', 'al-badge-info', _e('مخالفة معفاة')],
    'violation_dispute'  => ['fa-exclamation-triangle', 'al-badge-danger', _e('مخالفة متنازع عليها')],
    'client_import'      => ['fa-upload', 'al-badge-info', _e('استيراد العملاء')],
];
?>

<style>
.activity-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}
.activity-header {
    text-align: center;
    margin-bottom: 28px;
}
.activity-header h1 { color: #fff; font-size: 24px; font-weight: 800; margin: 0 0 6px; }
.activity-header p { color: rgba(255,255,255,0.4); font-size: 12px; margin: 0; }

.al-filter-card {
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
.al-filter-row {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}
.al-filter-card input,
.al-filter-card select {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    color: #e2e8f0;
    padding: 10px 14px;
    font-size: 13px;
    font-family: 'Almarai', sans-serif;
    outline: none;
    transition: border-color 0.2s;
    min-width: 140px;
}
.al-filter-card input:focus,
.al-filter-card select:focus { border-color: rgba(99,179,237,0.5); }
.al-filter-card select option { background: #1a1a2e; color: #e2e8f0; }
.al-btn-filter {
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
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.al-btn-filter:hover { background: linear-gradient(135deg, #2980b9, #3498db); color: #fff; text-decoration: none; }
.al-btn-reset {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    color: rgba(255,255,255,0.6);
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-family: 'Almarai', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.al-btn-reset:hover { background: rgba(255,255,255,0.12); color: #fff; text-decoration: none; }

.al-results-bar {
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
.al-results-bar h4 { margin: 0; font-size: 15px; font-weight: 600; color: #e2e8f0; }
.al-results-bar .badge-count { background: #1f62b9; color: #fff; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; }

.al-log-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 8px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: background 0.15s;
}
.al-log-card:hover { background: rgba(255,255,255,0.08); }

.al-log-num {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: rgba(255,255,255,0.4);
    flex-shrink: 0;
}
.al-log-body { flex: 1; min-width: 0; }
.al-log-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 12px;
    color: rgba(255,255,255,0.4);
    margin-top: 4px;
}
.al-log-meta b { color: rgba(255,255,255,0.7); margin-right: 2px; }

.al-log-user {
    font-size: 13px;
    font-weight: 700;
    color: #fff;
}

.al-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}
.al-badge-success { background: rgba(72,187,120,0.15); color: #68d391; }
.al-badge-danger { background: rgba(229,62,62,0.15); color: #fc8181; }
.al-badge-warning { background: rgba(236,201,75,0.15); color: #f6e05e; }
.al-badge-info { background: rgba(99,179,237,0.15); color: #93c5fd; }
.al-badge-default { background: rgba(148,163,184,0.12); color: #94a3b8; }

.al-log-time {
    font-size: 11px;
    color: rgba(255,255,255,0.3);
    flex-shrink: 0;
    direction: ltr;
}
.al-log-ip {
    font-size: 11px;
    color: rgba(255,255,255,0.25);
    font-family: 'Courier New', monospace;
    direction: ltr;
}

.al-empty {
    text-align: center;
    padding: 50px 20px;
    color: rgba(255,255,255,0.3);
    max-width: 900px;
    margin: 0 auto;
}
.al-empty i { font-size: 48px; margin-bottom: 14px; display: block; }

.al-footer-note {
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
    margin-top: 16px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.activity-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.activity-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.activity-footer a:hover { color: rgba(255,255,255,0.6); }
.activity-footer .fa-heart { color: #e53e3e; }

.activity-page ~ footer.footer { display: none !important; }

@media (max-width: 768px) {
    .activity-page { padding: 16px 10px 60px; margin: -10px -15px -60px; }
    .al-filter-row { flex-direction: column; }
    .al-filter-card input, .al-filter-card select { width: 100%; min-width: 0; }
    .al-log-card { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="activity-page">
    <div class="container">
        <div class="activity-header">
            <h1><i class="fa fa-history"></i> <?= _e('سجل النشاطات') ?></h1>
            <p><?= _e('تتبع جميع إجراءات المستخدمين وأحداث النظام') ?></p>
        </div>

        <div class="al-filter-card">
            <form method="get">
                <div class="al-filter-row">
                    <select name="user_id">
                        <option value=""><?= _e('جميع المستخدمين') ?></option>
                        <?php foreach ($allUsers as $u) { ?>
                        <option value="<?= $u['id'] ?>" <?= $filterUser == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name'] ?? $u['username']) ?></option>
                        <?php } ?>
                    </select>
                    <input type="text" name="action" placeholder="<?= _e('إجراء') ?>" value="<?= htmlspecialchars($filterAction) ?>">
                    <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
                    <button type="submit" class="al-btn-filter"><i class="fa fa-filter"></i> <?= _e('تصفية') ?></button>
                    <a href="activity-log" class="al-btn-reset"><i class="fa fa-refresh"></i> <?= _e('إعادة تعيين') ?></a>
                </div>
            </form>
        </div>

        <div class="al-results-bar">
            <h4><i class="fa fa-list-ul"></i> <?= _e('سجل النشاطات') ?></h4>
            <span class="badge-count"><?= count($logs) ?> <?= _e('نتيجة') ?></span>
        </div>

        <?php if (empty($logs)) { ?>
            <div class="al-empty">
                <i class="fa fa-inbox"></i>
                <p><?= _e('لم يتم العثور على سجلات نشاط') ?></p>
            </div>
        <?php } ?>

        <?php $i = 0; foreach ($logs as $log) { $i++;
            $al = $actionLabels[$log['action']] ?? ['fa-circle', 'al-badge-default', $log['action']];
        ?>
        <div class="al-log-card">
            <div class="al-log-num"><?= $i ?></div>
            <div class="al-log-body">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="al-badge <?= $al[1] ?>"><i class="fa <?= $al[0] ?>"></i> <?= $al[2] ?></span>
                    <span class="al-log-user"><?= htmlspecialchars($log['user_name'] ?? $log['username'] ?? '-') ?></span>
                </div>
                <div class="al-log-meta">
                    <?php if (!empty($log['details'])) { ?>
                    <span><?= htmlspecialchars($log['details']) ?></span>
                    <?php } ?>
                </div>
            </div>
            <div style="text-align:left;flex-shrink:0;">
                <div class="al-log-time"><?= $log['created_at'] ?></div>
                <div class="al-log-ip"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></div>
            </div>
        </div>
        <?php } ?>

        <div class="al-footer-note"><i class="fa fa-info-circle"></i> <?= _e('عرض آخر 500 سجل') ?></div>

        <div class="activity-footer">
            <a href="https://fb.com/mujeer.world" target="_blank"><?= _e('صُنع بـ') ?> <i class="fa fa-heart"></i> <?= _e('بواسطة MÜJEER') ?></a>
            &nbsp;&middot;&nbsp;
            &copy; <?= _e('فهرس') ?> <?= date('Y') ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
