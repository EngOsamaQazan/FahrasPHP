<?php
$token = 'mojeer';
$page_title = 'البحث عن وظائف';
include 'header.php';

require_permission('jobs', 'view');

require_once __DIR__ . '/../includes/violation_engine.php';

function normalizeArabicJob($text) {
    $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
    $text = str_replace('ة', 'ه', $text);
    $text = str_replace('ى', 'ي', $text);
    $text = mb_strtolower($text, 'UTF-8');
    return $text;
}

function fetchJobsApi($url, $label) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'label' => $label, 'data' => [], 'error' => $error];
    }
    if ($httpCode >= 400) {
        return ['ok' => false, 'label' => $label, 'data' => [], 'error' => "HTTP {$httpCode}"];
    }
    if (empty($raw)) {
        return ['ok' => false, 'label' => $label, 'data' => [], 'error' => _e('لا استجابة من الخادم')];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'label' => $label, 'data' => [], 'error' => _e('صيغة استجابة غير صالحة')];
    }
    return ['ok' => true, 'label' => $label, 'data' => $data, 'error' => ''];
}

$searchQuery = trim($_GET['q'] ?? '');
$workplaces = [];
$remoteWorkplaces = [];
$remoteErrors = [];
$remoteStatuses = [];
$selectedWork = trim($_GET['work'] ?? '');
$selectedSource = trim($_GET['src'] ?? '');
$selectedId = trim($_GET['wid'] ?? '');
$workClients = [];
$workStats = [];

if (!empty($searchQuery) || !empty($selectedWork)) {
    $nameNorm = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(c.work, 'ة', 'ه'), 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي')";

    if (!empty($searchQuery)) {
        $qNorm = normalizeArabicJob($searchQuery);
        try {
            $stmt = $db->prepare("
                SELECT c.work AS workplace,
                       COUNT(*) AS client_count,
                       GROUP_CONCAT(DISTINCT a.name SEPARATOR '، ') AS companies
                FROM clients c
                LEFT JOIN accounts a ON a.id = c.account
                WHERE c.work IS NOT NULL AND c.work != ''
                  AND $nameNorm LIKE :q
                GROUP BY c.work
                ORDER BY client_count DESC
                LIMIT 50
            ");
            $stmt->execute(['q' => "%{$qNorm}%"]);
            $workplaces = $stmt->fetchAll();
        } catch (Throwable $e) {
            $workplaces = [];
        }

        $localCount = count($workplaces);
        $remoteStatuses[] = ['label' => _e('محلي'), 'src' => 'local', 'status' => $localCount > 0 ? 'ok' : 'empty', 'count' => $localCount];

        $enc = urlencode($searchQuery);
        $apis = [
            ['url' => "https://jadal.aqssat.co/fahras/jobs.php?token=b83ba7a49b72&db=jadal&search={$enc}", 'label' => 'جدل', 'src' => 'jadal'],
            ['url' => "https://jadal.aqssat.co/fahras/jobs.php?token=b83ba7a49b72&db=erp&search={$enc}", 'label' => 'نماء', 'src' => 'namaa'],
            ['url' => "https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&action=jobs&search={$enc}", 'label' => 'بسيل', 'src' => 'bseel'],
            ['url' => "https://watar.aqssat.co/fahras/jobs.php?token=b83ba7a49b72&db=watar&search={$enc}", 'label' => 'وتر', 'src' => 'watar'],
            ['url' => "https://majd.aqssat.co/fahras/jobs.php?token=b83ba7a49b72&db=majd&search={$enc}", 'label' => 'المجد', 'src' => 'majd'],
        ];
        foreach ($apis as $api) {
            $res = fetchJobsApi($api['url'], $api['label']);
            if ($res['ok'] && !empty($res['data'])) {
                foreach ($res['data'] as $item) {
                    $item['_source'] = $api['src'];
                    $item['_label'] = $api['label'];
                    $remoteWorkplaces[] = $item;
                }
                $remoteStatuses[] = ['label' => $api['label'], 'src' => $api['src'], 'status' => 'ok', 'count' => count($res['data'])];
            } elseif ($res['ok'] && empty($res['data'])) {
                $remoteStatuses[] = ['label' => $api['label'], 'src' => $api['src'], 'status' => 'empty', 'count' => 0];
            } else {
                $remoteErrors[] = ['label' => $api['label'], 'error' => $res['error'] ?? ''];
                $remoteStatuses[] = ['label' => $api['label'], 'src' => $api['src'], 'status' => 'error', 'error' => $res['error'] ?? ''];
            }
        }
    }

    if (!empty($selectedWork) && empty($selectedSource)) {
        try {
            $stmt = $db->prepare("
                SELECT c.*, a.name AS account_name, 'local' AS _source
                FROM clients c
                LEFT JOIN accounts a ON a.id = c.account
                WHERE c.work = :w
                ORDER BY c.created_on DESC
                LIMIT 100
            ");
            $stmt->execute(['w' => $selectedWork]);
            $workClients = $stmt->fetchAll();

            $statsStmt = $db->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN c.status IN ('نشط','active','فعال') THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN c.status IN ('منتهي','finished','completed','closed') THEN 1 ELSE 0 END) AS finished_count,
                    GROUP_CONCAT(DISTINCT a.name SEPARATOR '، ') AS companies,
                    COUNT(DISTINCT c.account) AS company_count
                FROM clients c
                LEFT JOIN accounts a ON a.id = c.account
                WHERE c.work = :w
            ");
            $statsStmt->execute(['w' => $selectedWork]);
            $workStats = $statsStmt->fetch();
        } catch (Throwable $e) {
            $workClients = [];
        }
    }
}

$remoteDetail = null;
if (!empty($selectedSource) && !empty($selectedId)) {
    $dbMap = ['jadal' => 'jadal', 'namaa' => 'erp', 'watar' => 'watar', 'majd' => 'majd'];
    $labelMap = ['jadal' => 'جدل', 'namaa' => 'نماء', 'bseel' => 'بسيل', 'watar' => 'وتر', 'majd' => 'المجد'];

    if ($selectedSource === 'bseel') {
        $searchTerm = $selectedWork ?: '';
        $words = preg_split('/\s+/u', trim($searchTerm));
        $shortTerm = count($words) > 2 ? implode(' ', array_slice($words, 0, 2)) : $searchTerm;
        $detailUrl = "https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&action=jobs&search=" . urlencode($shortTerm ?: ' ');
        $res = fetchJobsApi($detailUrl, 'بسيل');
        if ($res['ok']) {
            foreach ($res['data'] as $item) {
                if ((string)($item['id'] ?? '') === $selectedId) {
                    $remoteDetail = $item;
                    $remoteDetail['_source'] = 'bseel';
                    $remoteDetail['_label'] = 'بسيل';
                    break;
                }
            }
        }
    } else {
        $dbName = $dbMap[$selectedSource] ?? '';
        $hostMap = ['jadal' => 'jadal.aqssat.co', 'namaa' => 'jadal.aqssat.co', 'watar' => 'watar.aqssat.co', 'majd' => 'majd.aqssat.co'];
        $detailHost = $hostMap[$selectedSource] ?? 'jadal.aqssat.co';
        if ($dbName) {
            $searchTerm = $selectedWork ?: '';
            $words = preg_split('/\s+/u', trim($searchTerm));
            $shortTerm = count($words) > 2 ? implode(' ', array_slice($words, 0, 2)) : $searchTerm;
            $detailUrl = "https://{$detailHost}/fahras/jobs.php?token=b83ba7a49b72&db={$dbName}&search=" . urlencode($shortTerm ?: ' ');
            $res = fetchJobsApi($detailUrl, $labelMap[$selectedSource] ?? $selectedSource);
            if ($res['ok']) {
                foreach ($res['data'] as $item) {
                    if ((string)($item['id'] ?? '') === $selectedId) {
                        $remoteDetail = $item;
                        $remoteDetail['_source'] = $selectedSource;
                        $remoteDetail['_label'] = $labelMap[$selectedSource] ?? $selectedSource;
                        break;
                    }
                }
            }
        }
    }
}

$topWorkplaces = [];
if (empty($searchQuery) && empty($selectedWork)) {
    try {
        $stmt = $db->prepare("
            SELECT c.work AS workplace,
                   COUNT(*) AS client_count,
                   GROUP_CONCAT(DISTINCT a.name SEPARATOR '، ') AS companies
            FROM clients c
            LEFT JOIN accounts a ON a.id = c.account
            WHERE c.work IS NOT NULL AND c.work != '' AND LENGTH(c.work) > 3
            GROUP BY c.work
            ORDER BY client_count DESC
            LIMIT 20
        ");
        $stmt->execute();
        $topWorkplaces = $stmt->fetchAll();
    } catch (Throwable $e) {}
}
?>

<style>
.jobs-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}

.jobs-header {
    text-align: center;
    margin-bottom: 28px;
}
.jobs-header h1 {
    color: #fff;
    font-size: 24px;
    font-weight: 800;
    margin: 0 0 6px;
}
.jobs-header p {
    color: rgba(255,255,255,0.4);
    font-size: 12px;
    margin: 0;
}

.jobs-search {
    max-width: 600px;
    margin: 0 auto 30px;
}
.jobs-search-box {
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    overflow: hidden;
    backdrop-filter: blur(12px);
    transition: all 0.3s;
}
.jobs-search-box:focus-within {
    border-color: rgba(99,179,237,0.5);
    box-shadow: 0 8px 40px rgba(31,98,185,0.35);
}
.jobs-search-box input {
    flex: 1;
    border: none;
    padding: 14px 18px;
    font-size: 14px;
    font-family: 'Almarai', sans-serif;
    background: transparent;
    color: #e2e8f0;
    outline: none;
}
.jobs-search-box input::placeholder { color: rgba(255,255,255,0.3); }
.jobs-search-box button {
    background: linear-gradient(135deg, #1f62b9, #2980b9);
    border: none;
    color: #fff;
    padding: 14px 24px;
    font-size: 14px;
    font-family: 'Almarai', sans-serif;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}
.jobs-search-box button:hover { background: linear-gradient(135deg, #2980b9, #3498db); }

.work-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    max-width: 900px;
    margin: 0 auto;
}

.work-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 18px;
    transition: all 0.2s;
    cursor: pointer;
    text-decoration: none;
    display: block;
    color: inherit;
}
.work-card:hover {
    background: rgba(255,255,255,0.1);
    border-color: rgba(99,179,237,0.3);
    transform: translateY(-2px);
    text-decoration: none;
    color: inherit;
}
.work-card-name {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.work-card-name i { color: rgba(99,179,237,0.6); font-size: 16px; }
.work-card-meta {
    display: flex;
    gap: 16px;
    font-size: 12px;
    color: rgba(255,255,255,0.4);
}
.work-card-meta span { display: flex; align-items: center; gap: 4px; }
.work-card-meta b { color: rgba(255,255,255,0.7); }
.work-card-companies {
    margin-top: 8px;
    font-size: 11px;
    color: rgba(255,255,255,0.3);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.work-card-source {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    margin-top: 6px;
}
.work-card-source.jadal { background: rgba(34,197,94,0.15); color: #86efac; }
.work-card-source.namaa { background: rgba(234,179,8,0.15); color: #fde68a; }
.work-card-source.zajal { background: rgba(59,130,246,0.15); color: #93c5fd; }
.work-card-source.bseel { background: rgba(236,72,153,0.15); color: #f9a8d4; }
.work-card-source.watar { background: rgba(139,92,246,0.15); color: #c4b5fd; }
.work-card-source.majd { background: rgba(249,115,22,0.15); color: #fdba74; }
.work-card-source.local { background: rgba(148,163,184,0.1); color: #94a3b8; }
.work-card-type {
    font-size: 11px;
    color: rgba(255,255,255,0.35);
    margin-top: 4px;
}

.detail-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-top: 16px;
    text-align: right;
}
.detail-info-item {
    font-size: 12px;
    color: rgba(255,255,255,0.4);
    padding: 8px 12px;
    background: rgba(255,255,255,0.04);
    border-radius: 8px;
}
.detail-info-item b { color: rgba(255,255,255,0.7); display: block; margin-bottom: 2px; }
.detail-info-item a { color: #63b3ed; text-decoration: none; }
.detail-info-item a:hover { text-decoration: underline; }

.section-divider {
    border: none;
    border-top: 1px solid rgba(255,255,255,0.06);
    margin: 24px 0 16px;
}

.detail-section-title {
    font-size: 13px;
    font-weight: 700;
    color: rgba(255,255,255,0.5);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.detail-section-title i { color: #63b3ed; font-size: 14px; }

.phones-table, .hours-table {
    margin-top: 10px;
    overflow: hidden;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.06);
}
.phones-table table, .hours-table table {
    width: 100%;
    border-collapse: collapse;
}
.phones-table th, .hours-table th {
    background: rgba(255,255,255,0.06);
    color: rgba(255,255,255,0.5);
    font-size: 11px;
    font-weight: 700;
    padding: 8px 12px;
    text-align: right;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.phones-table td, .hours-table td {
    padding: 8px 12px;
    font-size: 12px;
    color: rgba(255,255,255,0.65);
    border-bottom: 1px solid rgba(255,255,255,0.03);
}
.phones-table tr:last-child td, .hours-table tr:last-child td { border-bottom: none; }
.phones-table tr:hover td, .hours-table tr:hover td { background: rgba(255,255,255,0.03); }

.remote-warning-jobs {
    background: rgba(251,191,36,0.08);
    border: 1px solid rgba(251,191,36,0.2);
    border-right: 4px solid rgba(251,191,36,0.5);
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 14px;
    font-size: 12px;
    color: #fbd38d;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}

.source-status-bar {
    max-width: 900px;
    margin: 0 auto 18px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}
.source-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid;
}
.source-status-chip.ok {
    background: rgba(34,197,94,0.08);
    border-color: rgba(34,197,94,0.2);
    color: #86efac;
}
.source-status-chip.ok i { color: #22c55e; }
.source-status-chip.empty {
    background: rgba(148,163,184,0.06);
    border-color: rgba(148,163,184,0.15);
    color: #94a3b8;
}
.source-status-chip.empty i { color: #64748b; }
.source-status-chip.error {
    background: rgba(239,68,68,0.08);
    border-color: rgba(239,68,68,0.25);
    color: #fca5a5;
}
.source-status-chip.error i { color: #ef4444; }
.source-status-chip .chip-count {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 4px;
    background: rgba(255,255,255,0.08);
}
.source-status-chip.error .chip-error {
    font-size: 10px;
    font-weight: 400;
    opacity: 0.7;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.detail-panel {
    max-width: 900px;
    margin: 0 auto;
}
.detail-header {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 20px;
    text-align: center;
}
.detail-header h2 {
    color: #fff;
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 14px;
}
.detail-header h2 i { color: #63b3ed; margin-left: 8px; }
.detail-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
}
.detail-stat {
    text-align: center;
}
.detail-stat .val {
    font-size: 22px;
    font-weight: 800;
    color: #63b3ed;
}
.detail-stat .lbl {
    font-size: 11px;
    color: rgba(255,255,255,0.4);
    margin-top: 2px;
}
.detail-stat.active-stat .val { color: #68d391; }
.detail-stat.finished-stat .val { color: #fc8181; }

.detail-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    margin-bottom: 16px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    color: rgba(255,255,255,0.5);
    font-size: 12px;
    text-decoration: none;
    transition: all 0.2s;
}
.detail-back:hover { background: rgba(255,255,255,0.12); color: #fff; text-decoration: none; }

.client-row {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: background 0.15s;
}
.client-row:hover { background: rgba(255,255,255,0.08); }
.client-row-num {
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
.client-row-body { flex: 1; min-width: 0; }
.client-row-name {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}
.client-row-info {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    font-size: 12px;
    color: rgba(255,255,255,0.4);
}
.client-row-info span b { color: rgba(255,255,255,0.7); margin-right: 2px; }
.client-row-company {
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(99,179,237,0.15);
    color: #93c5fd;
    flex-shrink: 0;
}

.section-title {
    font-size: 14px;
    font-weight: 700;
    color: rgba(255,255,255,0.5);
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

.jobs-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.jobs-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.jobs-footer a:hover { color: rgba(255,255,255,0.6); }
.jobs-footer .fa-heart { color: #e53e3e; }

.no-results-jobs {
    text-align: center;
    padding: 60px 20px;
    color: rgba(255,255,255,0.3);
}
.no-results-jobs i { font-size: 48px; margin-bottom: 14px; display: block; }

.jobs-page ~ footer.footer { display: none !important; }

@media (max-width: 768px) {
    .jobs-page { padding: 16px 10px 60px; margin: -10px -15px -60px; }
    .work-grid { grid-template-columns: 1fr; }
    .detail-stats { gap: 16px; }
    .client-row { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="jobs-page">
    <div class="container">
        <div class="jobs-header">
            <h1><i class="fa fa-briefcase"></i> <?= _e('البحث عن وظائف') ?></h1>
            <p><?= _e('البحث عن جهات العمل والشركات والمؤسسات...') ?></p>
        </div>

        <form action="" method="get" class="jobs-search">
            <div class="jobs-search-box">
                <input type="text" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= _e('البحث عن جهات العمل والشركات والمؤسسات...') ?>"
                       autocomplete="off" autofocus />
                <button type="submit"><i class="fa fa-search"></i> <?= _e('بحث') ?></button>
            </div>
        </form>

<?php if ($remoteDetail): ?>
        <div class="detail-panel">
            <a href="javascript:history.back()" class="detail-back"><i class="fa fa-arrow-right"></i> <?= _e('رجوع') ?></a>

            <div class="detail-header" style="text-align:right;">
                <h2 style="text-align:center;"><i class="fa fa-building"></i> <?= htmlspecialchars($remoteDetail['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                <div style="text-align:center;margin-bottom:16px;">
                    <span class="work-card-source <?= $remoteDetail['_source'] ?>"><?= htmlspecialchars($remoteDetail['_label']) ?></span>
                    <?php if (!empty($remoteDetail['type'])): ?>
                        <span style="display:inline-block;padding:2px 10px;border-radius:4px;font-size:11px;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.5);margin-right:6px;"><?= htmlspecialchars($remoteDetail['type']) ?></span>
                    <?php endif; ?>
                    <?php $statusLabel = ((int)($remoteDetail['status'] ?? 0) === 1) ? _e('نشط') : _e('غير نشط'); ?>
                    <span style="display:inline-block;padding:2px 10px;border-radius:4px;font-size:11px;background:<?= ((int)($remoteDetail['status'] ?? 0) === 1) ? 'rgba(34,197,94,0.12)' : 'rgba(239,68,68,0.12)' ?>;color:<?= ((int)($remoteDetail['status'] ?? 0) === 1) ? '#86efac' : '#fca5a5' ?>;margin-right:6px;"><?= $statusLabel ?></span>
                </div>

                <div class="detail-stats" style="justify-content:center;margin-bottom:18px;">
                    <div class="detail-stat">
                        <div class="val"><?= (int)($remoteDetail['customers_count'] ?? 0) ?></div>
                        <div class="lbl"><?= _e('عدد العملاء') ?></div>
                    </div>
                </div>

                <hr class="section-divider">

                <?php
                $addr = trim($remoteDetail['address'] ?? '');
                $addrCity = trim($remoteDetail['address_city'] ?? '');
                $addrArea = trim($remoteDetail['address_area'] ?? '');
                $addrStreet = trim($remoteDetail['address_street'] ?? '');
                $addrBuilding = trim($remoteDetail['address_building'] ?? '');
                $postalCode = trim($remoteDetail['postal_code'] ?? '');
                $plusCode = trim($remoteDetail['plus_code'] ?? '');
                $email = trim($remoteDetail['email'] ?? '');
                $website = trim($remoteDetail['website'] ?? '');
                $notes = trim($remoteDetail['notes'] ?? '');
                $mapUrl = trim($remoteDetail['map_url'] ?? '');
                $lat = $remoteDetail['latitude'] ?? null;
                $lng = $remoteDetail['longitude'] ?? null;
                $phones = $remoteDetail['phones'] ?? [];
                $workingHours = $remoteDetail['working_hours'] ?? [];
                $hasAddress = ($addr || $addrCity || $addrArea || $addrStreet || $addrBuilding);
                $hasContact = ($email || $website || !empty($phones));
                ?>

                <?php if ($hasAddress || $postalCode || $plusCode): ?>
                <div class="detail-section-title"><i class="fa fa-map-marker-alt"></i> <?= _e('العنوان') ?></div>
                <div class="detail-info-grid">
                    <?php if ($addrCity): ?>
                        <div class="detail-info-item"><b><?= _e('المدينة') ?></b> <?= htmlspecialchars($addrCity) ?></div>
                    <?php endif; ?>
                    <?php if ($addrArea): ?>
                        <div class="detail-info-item"><b><?= _e('المنطقة') ?></b> <?= htmlspecialchars($addrArea) ?></div>
                    <?php endif; ?>
                    <?php if ($addrStreet): ?>
                        <div class="detail-info-item"><b><?= _e('الشارع') ?></b> <?= htmlspecialchars($addrStreet) ?></div>
                    <?php endif; ?>
                    <?php if ($addrBuilding): ?>
                        <div class="detail-info-item"><b><?= _e('المبنى') ?></b> <?= htmlspecialchars($addrBuilding) ?></div>
                    <?php endif; ?>
                    <?php if ($addr && !$addrCity && !$addrArea): ?>
                        <div class="detail-info-item" style="grid-column:1/-1"><b><?= _e('العنوان الكامل') ?></b> <?= htmlspecialchars($addr) ?></div>
                    <?php endif; ?>
                    <?php if ($postalCode): ?>
                        <div class="detail-info-item"><b><?= _e('الرمز البريدي') ?></b> <?= htmlspecialchars($postalCode) ?></div>
                    <?php endif; ?>
                    <?php if ($plusCode): ?>
                        <div class="detail-info-item"><b><?= _e('رمز بلس') ?></b> <?= htmlspecialchars($plusCode) ?></div>
                    <?php endif; ?>
                    <?php if ($mapUrl): ?>
                        <div class="detail-info-item"><b><?= _e('الموقع') ?></b> <a href="<?= htmlspecialchars($mapUrl) ?>" target="_blank"><i class="fa fa-map-marker-alt"></i> <?= _e('عرض على الخريطة') ?></a></div>
                    <?php elseif ($lat && $lng): ?>
                        <div class="detail-info-item"><b><?= _e('الموقع') ?></b> <a href="https://www.google.com/maps?q=<?= $lat ?>,<?= $lng ?>" target="_blank"><i class="fa fa-map-marker-alt"></i> <?= _e('عرض على الخريطة') ?></a></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($hasContact): ?>
                <div class="detail-section-title" style="margin-top:18px;"><i class="fa fa-address-book"></i> <?= _e('معلومات الاتصال') ?></div>
                <div class="detail-info-grid">
                    <?php if ($email): ?>
                        <div class="detail-info-item"><b><?= _e('البريد الإلكتروني') ?></b> <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a></div>
                    <?php endif; ?>
                    <?php if ($website): ?>
                        <div class="detail-info-item"><b><?= _e('الموقع الإلكتروني') ?></b> <a href="<?= htmlspecialchars($website) ?>" target="_blank"><?= htmlspecialchars($website) ?></a></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($phones) && is_array($phones)): ?>
                <div class="phones-table">
                    <table>
                        <thead>
                            <tr>
                                <th><?= _e('الهاتف') ?></th>
                                <th><?= _e('النوع') ?></th>
                                <th><?= _e('الموظف') ?></th>
                                <th><?= _e('المنصب') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($phones as $ph):
                            $phNum = is_array($ph) ? ($ph['phone'] ?? $ph['number'] ?? '') : $ph;
                            $phType = is_array($ph) ? ($ph['type'] ?? '') : '';
                            $phName = is_array($ph) ? ($ph['name'] ?? '') : '';
                            $phPos  = is_array($ph) ? ($ph['position'] ?? '') : '';
                        ?>
                            <tr>
                                <td dir="ltr" style="text-align:right;"><?= htmlspecialchars($phNum) ?></td>
                                <td><?= htmlspecialchars($phType) ?></td>
                                <td><?= htmlspecialchars($phName) ?></td>
                                <td><?= htmlspecialchars($phPos) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($workingHours) && is_array($workingHours)): ?>
                <div class="detail-section-title" style="margin-top:18px;"><i class="fa fa-clock"></i> <?= _e('ساعات العمل') ?></div>
                <div class="hours-table">
                    <table>
                        <thead>
                            <tr>
                                <th><?= _e('اليوم') ?></th>
                                <th><?= _e('الفتح') ?></th>
                                <th><?= _e('الإغلاق') ?></th>
                                <th><?= _e('الحالة') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($workingHours as $wh): ?>
                            <tr<?= ((int)($wh['closed'] ?? 0) === 1) ? ' style="opacity:0.4;"' : '' ?>>
                                <td><?= htmlspecialchars($wh['day'] ?? '') ?></td>
                                <td dir="ltr" style="text-align:right;"><?= htmlspecialchars($wh['open'] ?? '') ?></td>
                                <td dir="ltr" style="text-align:right;"><?= htmlspecialchars($wh['close'] ?? '') ?></td>
                                <td><?= ((int)($wh['closed'] ?? 0) === 1) ? '<span style="color:#fca5a5;">' . _e('مغلق') . '</span>' : '<span style="color:#86efac;">' . _e('مفتوح') . '</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <?php
                    $whText = trim($remoteDetail['working_hours_text'] ?? '');
                    $freeDays = trim($remoteDetail['free_days'] ?? '');
                ?>
                <?php if ($whText || $freeDays): ?>
                <div class="detail-section-title" style="margin-top:18px;"><i class="fa fa-clock"></i> <?= _e('أوقات العمل') ?></div>
                <div class="detail-info-grid">
                    <?php if ($whText): ?>
                        <div class="detail-info-item"><b><?= _e('ساعات العمل') ?></b> <?= htmlspecialchars($whText) ?></div>
                    <?php endif; ?>
                    <?php if ($freeDays): ?>
                        <div class="detail-info-item"><b><?= _e('أيام العطلة') ?></b> <?= htmlspecialchars($freeDays) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($notes): ?>
                <div class="detail-section-title" style="margin-top:18px;"><i class="fa fa-sticky-note"></i> <?= _e('ملاحظات') ?></div>
                <div class="detail-info-item" style="text-align:right;"><?= nl2br(htmlspecialchars($notes)) ?></div>
                <?php endif; ?>
            </div>
        </div>

<?php elseif (!empty($selectedWork)): ?>
        <div class="detail-panel">
            <a href="jobs" class="detail-back"><i class="fa fa-arrow-right"></i> <?= _e('رجوع') ?></a>

            <div class="detail-header">
                <h2><i class="fa fa-building"></i> <?= htmlspecialchars($selectedWork, ENT_QUOTES, 'UTF-8') ?></h2>
                <span class="work-card-source local"><?= _e('محلي') ?></span>
                <?php if ($workStats): ?>
                <div class="detail-stats" style="margin-top:16px;">
                    <div class="detail-stat">
                        <div class="val"><?= (int)($workStats['total'] ?? 0) ?></div>
                        <div class="lbl"><?= _e('إجمالي العقود') ?></div>
                    </div>
                    <div class="detail-stat active-stat">
                        <div class="val"><?= (int)($workStats['active_count'] ?? 0) ?></div>
                        <div class="lbl"><?= _e('نشط') ?></div>
                    </div>
                    <div class="detail-stat finished-stat">
                        <div class="val"><?= (int)($workStats['finished_count'] ?? 0) ?></div>
                        <div class="lbl"><?= _e('منتهي') ?></div>
                    </div>
                    <div class="detail-stat">
                        <div class="val"><?= (int)($workStats['company_count'] ?? 0) ?></div>
                        <div class="lbl"><?= _e('الشركات') ?></div>
                    </div>
                </div>
                <?php if (!empty($workStats['companies'])): ?>
                    <div style="margin-top:12px;font-size:12px;color:rgba(255,255,255,0.4);">
                        <i class="fa fa-building"></i> <?= htmlspecialchars($workStats['companies'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="section-title"><i class="fa fa-users"></i> <?= _e('العملاء') ?> (<?= count($workClients) ?>)</div>

            <?php $n = 0; foreach ($workClients as $cl): $n++; ?>
            <div class="client-row">
                <div class="client-row-num"><?= $n ?></div>
                <div class="client-row-body">
                    <div class="client-row-name">
                        <a href="/admin/index.php?search=<?= urlencode($cl['name']) ?>" style="color:#fff;text-decoration:none;">
                            <?= htmlspecialchars($cl['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </div>
                    <div class="client-row-info">
                        <?php if (!empty($cl['national_id'])): ?>
                            <span><b><?= _e('الرقم الوطني') ?>:</b> <?= htmlspecialchars($cl['national_id']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($cl['phone'])): ?>
                            <span dir="ltr"><b><?= _e('الهاتف') ?>:</b> <?= htmlspecialchars($cl['phone']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($cl['status'])): ?>
                            <span><b><?= _e('الحالة') ?>:</b> <?= htmlspecialchars($cl['status']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($cl['account_name'])): ?>
                    <span class="client-row-company"><?= htmlspecialchars($cl['account_name']) ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($workClients)): ?>
                <div class="no-results-jobs">
                    <i class="fa fa-user-slash"></i>
                    <p><?= _e('لم يتم العثور على نتائج') ?></p>
                </div>
            <?php endif; ?>
        </div>

<?php elseif (!empty($workplaces) || !empty($remoteWorkplaces)): ?>
        <?php if (!empty($remoteStatuses)): ?>
            <div class="source-status-bar">
                <?php foreach ($remoteStatuses as $rs): ?>
                    <div class="source-status-chip <?= $rs['status'] ?>" title="<?= $rs['status'] === 'error' ? htmlspecialchars($rs['error'] ?? '') : '' ?>">
                        <?php if ($rs['status'] === 'ok'): ?>
                            <i class="fa fa-check-circle"></i>
                        <?php elseif ($rs['status'] === 'empty'): ?>
                            <i class="fa fa-minus-circle"></i>
                        <?php else: ?>
                            <i class="fa fa-times-circle"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($rs['label']) ?></span>
                        <?php if ($rs['status'] === 'ok'): ?>
                            <span class="chip-count"><?= $rs['count'] ?></span>
                        <?php elseif ($rs['status'] === 'empty'): ?>
                            <span class="chip-count"><?= _e('لا نتائج') ?></span>
                        <?php else: ?>
                            <span class="chip-error"><?= htmlspecialchars($rs['error'] ?? _e('تعذر الاتصال')) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="section-title" style="max-width:900px;margin:0 auto 14px;">
            <i class="fa fa-list-ul"></i> <?= _e('نتائج البحث عن') ?>: <b><?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?></b>
            (<?= count($workplaces) + count($remoteWorkplaces) ?>)
        </div>
        <div class="work-grid">
            <?php foreach ($workplaces as $wp): ?>
            <a href="?work=<?= urlencode($wp['workplace']) ?>" class="work-card">
                <div class="work-card-name">
                    <i class="fa fa-building"></i>
                    <?= htmlspecialchars($wp['workplace'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="work-card-meta">
                    <span><i class="fa fa-users"></i> <b><?= (int)$wp['client_count'] ?></b> <?= _e('العملاء') ?></span>
                </div>
                <?php if (!empty($wp['companies'])): ?>
                <div class="work-card-companies">
                    <i class="fa fa-briefcase"></i> <?= htmlspecialchars($wp['companies'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>
                <span class="work-card-source local"><?= _e('محلي') ?></span>
            </a>
            <?php endforeach; ?>

            <?php foreach ($remoteWorkplaces as $rw): ?>
            <a href="?work=<?= urlencode($rw['name']) ?>&src=<?= urlencode($rw['_source']) ?>&wid=<?= urlencode($rw['id']) ?>" class="work-card">
                <div class="work-card-name">
                    <i class="fa fa-building"></i>
                    <?= htmlspecialchars($rw['name'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="work-card-meta">
                    <span><i class="fa fa-users"></i> <b><?= (int)($rw['customers_count'] ?? 0) ?></b> <?= _e('العملاء') ?></span>
                </div>
                <?php if (!empty($rw['type'])): ?>
                <div class="work-card-type"><i class="fa fa-tag"></i> <?= htmlspecialchars($rw['type']) ?></div>
                <?php endif; ?>
                <span class="work-card-source <?= $rw['_source'] ?>"><?= htmlspecialchars($rw['_label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

<?php elseif (!empty($searchQuery)): ?>
        <?php if (!empty($remoteStatuses)): ?>
            <div class="source-status-bar">
                <?php foreach ($remoteStatuses as $rs): ?>
                    <div class="source-status-chip <?= $rs['status'] ?>" title="<?= $rs['status'] === 'error' ? htmlspecialchars($rs['error'] ?? '') : '' ?>">
                        <?php if ($rs['status'] === 'ok'): ?>
                            <i class="fa fa-check-circle"></i>
                        <?php elseif ($rs['status'] === 'empty'): ?>
                            <i class="fa fa-minus-circle"></i>
                        <?php else: ?>
                            <i class="fa fa-times-circle"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($rs['label']) ?></span>
                        <?php if ($rs['status'] === 'ok'): ?>
                            <span class="chip-count"><?= $rs['count'] ?></span>
                        <?php elseif ($rs['status'] === 'empty'): ?>
                            <span class="chip-count"><?= _e('لا نتائج') ?></span>
                        <?php else: ?>
                            <span class="chip-error"><?= htmlspecialchars($rs['error'] ?? _e('تعذر الاتصال')) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="no-results-jobs">
            <i class="fa fa-search"></i>
            <p><?= _e('لم يتم العثور على جهات عمل') ?></p>
        </div>

<?php elseif (!empty($topWorkplaces)): ?>
        <div class="section-title" style="max-width:900px;margin:0 auto 14px;">
            <i class="fa fa-chart-bar"></i> <?= _e('أكثر جهات العمل') ?>
        </div>
        <div class="work-grid">
            <?php foreach ($topWorkplaces as $wp): ?>
            <a href="?work=<?= urlencode($wp['workplace']) ?>" class="work-card">
                <div class="work-card-name">
                    <i class="fa fa-building"></i>
                    <?= htmlspecialchars($wp['workplace'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="work-card-meta">
                    <span><i class="fa fa-users"></i> <b><?= (int)$wp['client_count'] ?></b> <?= _e('العملاء') ?></span>
                </div>
                <?php if (!empty($wp['companies'])): ?>
                <div class="work-card-companies">
                    <i class="fa fa-briefcase"></i> <?= htmlspecialchars($wp['companies'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
<?php endif; ?>

        <div class="jobs-footer">
            <a href="https://fb.com/mujeer.world" target="_blank"><?= _e('صُنع بـ') ?> <i class="fa fa-heart"></i> <?= _e('بواسطة MÜJEER') ?></a>
            &nbsp;&middot;&nbsp;
            &copy; <?= _e('فهرس') ?> <?= date('Y') ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
