<?php
$token = 'mojeer';
$page_title = 'التقارير';
include 'header.php';

require_once __DIR__ . '/../includes/violation_engine.php';

require_permission('reports', 'view');

$selectedMonth = $_GET['month'] ?? date('Y-m');
$monthStart = $selectedMonth . '-01';

$stmt = $db->prepare("
    SELECT violating_account,
           COUNT(*) as violation_count,
           SUM(fine_amount) as total_fine,
           SUM(CASE WHEN is_paid = 1 THEN fine_amount ELSE 0 END) as paid_amount,
           SUM(CASE WHEN is_paid = 0 AND status = 'active' THEN fine_amount ELSE 0 END) as unpaid_amount
    FROM violations
    WHERE violation_month = :m AND status IN ('active','disputed')
    GROUP BY violating_account
    ORDER BY violation_count DESC
");
$stmt->execute(['m' => $monthStart]);
$report = $stmt->fetchAll();

$entitledStmt = $db->prepare("
    SELECT entitled_account,
           COUNT(*) as violation_count,
           SUM(fine_amount) as total_earned
    FROM violations
    WHERE violation_month = :m AND status IN ('active','disputed')
    GROUP BY entitled_account
    ORDER BY violation_count DESC
");
$entitledStmt->execute(['m' => $monthStart]);
$entitledReport = $entitledStmt->fetchAll();

$chartStmt = $db->prepare("
    SELECT violation_month, COUNT(*) as cnt
    FROM violations
    WHERE violation_month >= DATE_SUB(:m, INTERVAL 11 MONTH) AND status IN ('active','disputed')
    GROUP BY violation_month
    ORDER BY violation_month ASC
");
$chartStmt->execute(['m' => $monthStart]);
$chartData = $chartStmt->fetchAll();
?>

<style>
.report-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}
.report-header {
    text-align: center;
    margin-bottom: 28px;
}
.report-header h1 { color: #fff; font-size: 24px; font-weight: 800; margin: 0 0 6px; }
.report-header p { color: rgba(255,255,255,0.4); font-size: 12px; margin: 0; }

.report-filter {
    background: rgba(255,255,255,0.06);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 24px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}
.report-filter input {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    color: #e2e8f0;
    padding: 10px 14px;
    font-size: 13px;
    font-family: 'Almarai', sans-serif;
    outline: none;
}
.report-filter input:focus { border-color: rgba(99,179,237,0.5); }
.report-filter .btn-view {
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
.report-filter .btn-view:hover { background: linear-gradient(135deg, #2980b9, #3498db); }

.report-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    max-width: 900px;
    margin: 0 auto 24px;
}

.report-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    overflow: hidden;
}
.report-card-header {
    padding: 14px 18px;
    font-size: 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.report-card-header.danger { background: rgba(229,62,62,0.08); border-bottom: 2px solid rgba(229,62,62,0.2); color: #fc8181; }
.report-card-header.success { background: rgba(72,187,120,0.08); border-bottom: 2px solid rgba(72,187,120,0.2); color: #68d391; }

.report-card table {
    width: 100%;
    border-collapse: collapse;
}
.report-card th {
    background: rgba(99,179,237,0.06);
    color: #93c5fd;
    padding: 10px 14px;
    font-size: 12px;
    font-weight: 700;
    text-align: right;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.report-card td {
    padding: 10px 14px;
    font-size: 13px;
    color: #e0e6ed;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.report-card tr:hover td { background: rgba(255,255,255,0.03); }
.report-card .text-success { color: #68d391 !important; }
.report-card .text-danger { color: #fc8181 !important; }
.report-card .total-row td {
    background: rgba(255,255,255,0.04);
    border-top: 1px solid rgba(255,255,255,0.08);
    font-weight: 700;
}
.report-card .empty-row td {
    text-align: center;
    color: rgba(255,255,255,0.3);
    padding: 20px;
}

.chart-card {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 14px;
    overflow: hidden;
    max-width: 900px;
    margin: 0 auto 24px;
}
.chart-card-header {
    background: rgba(99,179,237,0.06);
    border-bottom: 1px solid rgba(99,179,237,0.1);
    padding: 14px 18px;
    font-size: 14px;
    font-weight: 700;
    color: #93c5fd;
}
.chart-card-body {
    padding: 20px;
}

.report-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.report-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.report-footer a:hover { color: rgba(255,255,255,0.6); }
.report-footer .fa-heart { color: #e53e3e; }

.report-page ~ footer.footer { display: none !important; }

@media (max-width: 768px) {
    .report-page { padding: 16px 10px 60px; margin: -10px -15px -60px; }
    .report-grid { grid-template-columns: 1fr; }
    .report-filter { flex-direction: column; }
}
</style>

<div class="report-page">
    <div class="container">
        <div class="report-header">
            <h1><i class="fa fa-chart-bar"></i> <?= _e('التقرير الشهري') ?></h1>
            <p><?= _e('ملخص مالي للمخالفات حسب الشركة') ?></p>
        </div>

        <form method="get" class="report-filter">
            <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">
            <button type="submit" class="btn-view"><i class="fa fa-search"></i> <?= _e('عرض') ?></button>
        </form>

        <div class="report-grid">
            <div class="report-card">
                <div class="report-card-header danger"><i class="fa fa-arrow-up"></i> <?= _e('الشركات المخالفة') ?> (<?= _e('مدين') ?>)</div>
                <table>
                    <thead>
                        <tr><th><?= _e('الشركة') ?></th><th><?= _e('المخالفات') ?></th><th><?= _e('إجمالي الغرامة') ?></th><th><?= _e('المدفوع') ?></th><th><?= _e('المتبقي') ?></th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $grandTotal = 0;
                    foreach ($report as $r) {
                        $count = $r['violation_count'];
                        $grandTotal += $r['unpaid_amount'];
                    ?>
                    <tr>
                        <td><b><?= htmlspecialchars($r['violating_account']) ?></b></td>
                        <td><?= $count ?></td>
                        <td><?= number_format($r['total_fine'], 2) ?> <?= _e('دينار') ?></td>
                        <td class="text-success"><?= number_format($r['paid_amount'], 2) ?></td>
                        <td class="text-danger"><b><?= number_format($r['unpaid_amount'], 2) ?></b></td>
                    </tr>
                    <?php } ?>
                    <?php if (empty($report)) { ?>
                    <tr class="empty-row"><td colspan="5"><?= _e('لا مخالفات') ?></td></tr>
                    <?php } else { ?>
                    <tr class="total-row"><td colspan="4"><b><?= _e('إجمالي غير المدفوع') ?></b></td><td class="text-danger"><b><?= number_format($grandTotal, 2) ?> <?= _e('دينار') ?></b></td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="report-card">
                <div class="report-card-header success"><i class="fa fa-arrow-down"></i> <?= _e('الشركات المستحقة') ?> (<?= _e('مستحق') ?>)</div>
                <table>
                    <thead>
                        <tr><th><?= _e('الشركة') ?></th><th><?= _e('المخالفات لصالح') ?></th><th><?= _e('إجمالي المستحق') ?></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($entitledReport as $e) { ?>
                    <tr>
                        <td><b><?= htmlspecialchars($e['entitled_account']) ?></b></td>
                        <td><?= $e['violation_count'] ?></td>
                        <td class="text-success"><b><?= number_format($e['total_earned'], 2) ?> <?= _e('دينار') ?></b></td>
                    </tr>
                    <?php } ?>
                    <?php if (empty($entitledReport)) { ?>
                    <tr class="empty-row"><td colspan="3"><?= _e('لا مخالفات') ?></td></tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="chart-card">
            <div class="chart-card-header"><i class="fa fa-chart-line"></i> <?= _e('اتجاه المخالفات (12 شهراً)') ?></div>
            <div class="chart-card-body">
                <canvas id="violationsChart" height="80"></canvas>
            </div>
        </div>

        <div class="report-footer">
            <?php include __DIR__ . '/includes/fahras-footer-credits.php'; ?>
            &nbsp;&middot;&nbsp;
            &copy; <?= _e('فهرس') ?> <?= date('Y') ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
var ctx = document.getElementById('violationsChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($chartData as $cd) { echo '"' . $cd['violation_month'] . '",'; } ?>],
        datasets: [{
            label: '<?= _e('المخالفات') ?>',
            data: [<?php foreach ($chartData as $cd) { echo $cd['cnt'] . ','; } ?>],
            backgroundColor: 'rgba(99, 179, 237, 0.3)',
            borderColor: 'rgba(99, 179, 237, 0.8)',
            borderWidth: 2,
            borderRadius: 6,
            hoverBackgroundColor: 'rgba(99, 179, 237, 0.5)',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                labels: { color: 'rgba(255,255,255,0.6)', font: { family: 'Almarai' } }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1, color: 'rgba(255,255,255,0.4)', font: { family: 'Almarai' } },
                grid: { color: 'rgba(255,255,255,0.04)' }
            },
            x: {
                ticks: { color: 'rgba(255,255,255,0.4)', font: { family: 'Almarai' } },
                grid: { color: 'rgba(255,255,255,0.04)' }
            }
        }
    }
});
</script>
