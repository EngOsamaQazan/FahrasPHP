<?php
$token = 'mojeer';
$page_title = 'الجرد';
include 'header.php';

require_once __DIR__ . '/../includes/violation_engine.php';

require_permission('scan', 'view');

try {
    $totalClients = $db->get_count('clients') ?: 0;
    $totalRemote  = $db->get_count('remote_clients') ?: 0;
    $totalActive  = $db->get_count('violations', ['status' => 'active']) ?: 0;
    $syncStmt = $db->run("SELECT MAX(synced_at) as last_sync FROM remote_clients");
    $lastSyncRow = is_object($syncStmt) ? $syncStmt->fetch() : [];
    $lastSync = $lastSyncRow['last_sync'] ?? _e('لم يتم بعد');
} catch (Exception $e) {
    $totalClients = $totalRemote = $totalActive = 0;
    $lastSync = _e('لم يتم بعد');
}
?>

<style>
.scan-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}
.scan-header {
    text-align: center;
    margin-bottom: 28px;
}
.scan-header h1 { color: #fff; font-size: 24px; font-weight: 800; margin: 0 0 6px; }
.scan-header p { color: rgba(255,255,255,0.4); font-size: 12px; margin: 0; }

.scan-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 28px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}
.scan-stat-card {
    background: rgba(255,255,255,0.06);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    padding: 18px;
    text-align: center;
    transition: transform 0.2s, background 0.2s;
}
.scan-stat-card:hover { transform: translateY(-2px); background: rgba(255,255,255,0.1); }
.scan-stat-card h2 { font-size: 28px; font-weight: 800; color: #63b3ed; margin: 0 0 4px; }
.scan-stat-card h4 { font-size: 16px; font-weight: 700; color: #63b3ed; margin: 0 0 4px; }
.scan-stat-card p { font-size: 11px; opacity: 0.5; margin: 0; text-transform: uppercase; }
.scan-stat-card.danger h2 { color: #fc8181; }

.scan-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    max-width: 900px;
    margin: 0 auto 24px;
}
.btn-scan-action {
    padding: 16px 24px;
    font-size: 15px;
    font-weight: 700;
    font-family: 'Almarai', sans-serif;
    border-radius: 12px;
    border: none;
    color: #fff;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-scan-action.btn-scan-green { background: linear-gradient(135deg, #27ae60, #2ecc71); }
.btn-scan-action.btn-scan-green:hover { background: linear-gradient(135deg, #2ecc71, #27ae60); transform: translateY(-1px); }
.btn-scan-action.btn-scan-amber { background: linear-gradient(135deg, #f39c12, #e67e22); }
.btn-scan-action.btn-scan-amber:hover { background: linear-gradient(135deg, #e67e22, #f39c12); transform: translateY(-1px); }
.btn-scan-action.btn-scan-blue { background: linear-gradient(135deg, #1f62b9, #2980b9); }
.btn-scan-action.btn-scan-blue:hover { background: linear-gradient(135deg, #2980b9, #3498db); transform: translateY(-1px); }
.btn-scan-action:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }

.scan-console {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.06);
    color: #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    max-height: 500px;
    overflow-y: auto;
    direction: ltr;
    text-align: left;
    display: none;
    margin-bottom: 20px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}
.scan-console .log-line { padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: flex-start; gap: 8px; }
.scan-console .log-time { color: #666; flex-shrink: 0; font-size: 11px; }
.scan-console .log-icon { flex-shrink: 0; width: 18px; text-align: center; }
.scan-console .log-msg { flex: 1; word-break: break-word; }
.scan-console .log-success { color: #4ade80; }
.scan-console .log-error   { color: #f87171; }
.scan-console .log-warn    { color: #fbbf24; }
.scan-console .log-info    { color: #60a5fa; }
.scan-console .log-step    { color: #c084fc; font-weight: bold; }
.scan-console .log-dim     { color: #888; }

.progress-section {
    display: none;
    margin-bottom: 20px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}
.progress-section .progress {
    height: 28px;
    border-radius: 14px;
    background: rgba(255,255,255,0.06);
    margin-bottom: 8px;
    overflow: hidden;
}
.progress-section .progress-bar {
    height: 100%;
    line-height: 28px;
    font-size: 13px;
    font-weight: bold;
    transition: width 0.4s ease;
    border-radius: 14px;
}
.step-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 15px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}
.step-card {
    flex: 1;
    min-width: 120px;
    background: rgba(255,255,255,0.04);
    border: 2px solid rgba(255,255,255,0.08);
    border-radius: 10px;
    padding: 14px;
    text-align: center;
    transition: all 0.3s;
}
.step-card.active  { border-color: rgba(99,179,237,0.4); background: rgba(99,179,237,0.08); }
.step-card.success { border-color: rgba(72,187,120,0.4); background: rgba(72,187,120,0.08); }
.step-card.error   { border-color: rgba(245,101,101,0.4); background: rgba(245,101,101,0.08); }
.step-card .step-icon { font-size: 24px; margin-bottom: 5px; }
.step-card .step-name { font-size: 12px; font-weight: bold; color: rgba(255,255,255,0.7); }
.step-card .step-detail { font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 4px; }

.summary-panel {
    display: none;
    margin-top: 15px;
    max-width: 900px;
    margin-left: auto;
    margin-right: auto;
}
.summary-panel .alert-success {
    background: rgba(72,187,120,0.1) !important;
    border: 1px solid rgba(72,187,120,0.2) !important;
    color: #68d391 !important;
    border-radius: 12px;
}
.summary-panel .alert-success h4 { color: #68d391; margin-top: 0; }
.summary-panel .alert-success hr { border-color: rgba(72,187,120,0.15); }

.scan-page ~ footer.footer { display: none !important; }

.scan-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.scan-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.scan-footer a:hover { color: rgba(255,255,255,0.6); }
.scan-footer .fa-heart { color: #e53e3e; }

@media (max-width: 768px) {
    .scan-page { padding: 16px 10px 60px; margin: -10px -15px -60px; }
    .scan-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .scan-actions { grid-template-columns: 1fr; }
    .step-grid { flex-direction: column; }
}
</style>

<div class="scan-page">
    <div class="container">
        <div class="scan-header">
            <h1><i class="fa fa-radar"></i> <?= _e('الفحص والمزامنة') ?></h1>
            <p><?= _e('مزامنة البيانات واكتشاف المخالفات') ?></p>
        </div>

        <div class="scan-stats-grid" id="stats-row">
            <div class="scan-stat-card">
                <h2 id="stat-clients"><?= number_format($totalClients) ?></h2>
                <p><?= _e('العملاء المحليين') ?></p>
            </div>
            <div class="scan-stat-card">
                <h2 id="stat-remote"><?= number_format($totalRemote) ?></h2>
                <p><?= _e('السجلات الخارجية المخزنة') ?></p>
            </div>
            <div class="scan-stat-card danger">
                <h2 id="stat-violations"><?= $totalActive ?></h2>
                <p><?= _e('المخالفات النشطة') ?></p>
            </div>
            <div class="scan-stat-card">
                <h4 id="stat-sync"><?= htmlspecialchars($lastSync) ?></h4>
                <p><?= _e('آخر مزامنة') ?></p>
            </div>
        </div>

        <div class="scan-actions" id="action-buttons">
            <button class="btn-scan-action btn-scan-green" onclick="runFullScan()">
                <i class="fa fa-check-double"></i> <?= _e('بدء الفحص المجمع') ?>
            </button>
            <button class="btn-scan-action btn-scan-amber" onclick="runSyncOnly()">
                <i class="fa fa-sync"></i> <?= _e('مزامنة APIs فقط') ?>
            </button>
            <button class="btn-scan-action btn-scan-blue" onclick="runScanOnly()">
                <i class="fa fa-search"></i> <?= _e('فحص المخالفات فقط') ?>
            </button>
        </div>

        <div class="progress-section" id="progress-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                <b id="progress-title" style="color:#e0e6ed;"><i class="fa fa-cog fa-spin"></i> <?= _e('جاري المعالجة') ?>...</b>
                <span id="progress-percent" style="font-weight: bold; color: #63b3ed;">0%</span>
            </div>
            <div class="progress">
                <div class="progress-bar progress-bar-striped active" id="progress-bar" style="width: 0%"></div>
            </div>
            <div id="progress-subtitle" style="font-size: 12px; color: rgba(255,255,255,0.4);"></div>
        </div>

        <div class="step-grid" id="step-grid" style="display:none;"></div>

        <div class="scan-console" id="scan-console"></div>

        <div class="summary-panel" id="summary-panel"></div>

        <div id="violations-table" style="display:none;max-width:900px;margin:0 auto;"></div>

        <div class="scan-footer">
            <a href="https://fb.com/mujeer.world" target="_blank"><?= _e('صُنع بـ') ?> <i class="fa fa-heart"></i> <?= _e('بواسطة MÜJEER') ?></a>
            &nbsp;&middot;&nbsp;
            &copy; <?= _e('فهرس') ?> <?= date('Y') ?>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';
const LABELS = {
    zajal: 'زجل', jadal: 'جدل', namaa: 'نماء', bseel: 'بسيل', watar: 'وتر', majd: 'المجد',
    syncing: '<?= _e('جاري المزامنة') ?>',
    scanning: '<?= _e('جاري الفحص') ?>',
    done: '<?= _e('اكتمل') ?>',
    failed: '<?= _e('فشل') ?>',
    newRecords: '<?= _e('new records synced') ?>',
    updated: '<?= _e('updated') ?>',
    errors: '<?= _e('errors') ?>',
    violations: '<?= _e('المخالفات') ?>',
    newViolations: '<?= _e('new violations detected') ?>',
    checked: '<?= _e('pairs checked') ?>',
    seconds: '<?= _e('seconds') ?>',
    scanLocal: '<?= _e('فحص محلي مقابل محلي') ?>',
    scanRemote: '<?= _e('فحص محلي مقابل خارجي') ?>',
    scanExternal: '<?= _e('فحص خارجي مقابل خارجي') ?>',
    scanViolations: '<?= _e('فحص المخالفات') ?>',
    combinedDone: '<?= _e('اكتمل الفحص المجمع') ?>',
    bulkSync: '<?= _e('مزامنة مجمعة') ?>',
    syncDone: '<?= _e('اكتملت المزامنة') ?>',
    scanDone: '<?= _e('اكتمل الفحص') ?>',
    totalSynced: '<?= _e('إجمالي المزامنة') ?>',
    totalViolations: '<?= _e('إجمالي المخالفات المكتشفة') ?>',
    totalTime: '<?= _e('الوقت الإجمالي') ?>',
    connectionFailed: '<?= _e('فشل الاتصال') ?>',
    latestViolations: '<?= _e('آخر المخالفات المكتشفة') ?>',
    client: '<?= _e('العميل') ?>',
    nationalId: '<?= _e('الرقم الوطني') ?>',
    entitledCompany: '<?= _e('الشركة المستحقة') ?>',
    entitledDate: '<?= _e('تاريخ عقد المستحق') ?>',
    violatingCompany: '<?= _e('الشركة المخالفة') ?>',
    violatingDate: '<?= _e('تاريخ المخالفة') ?>',
    fine: '<?= _e('الغرامة') ?>',
    status: '<?= _e('الحالة') ?>',
    active: '<?= _e('نشطة') ?>',
    exempted: '<?= _e('معفاة') ?>',
    resolved: '<?= _e('تم الحل') ?>',
    noViolations: '<?= _e('لم يتم العثور على مخالفات في قاعدة البيانات.') ?>',
};

let isRunning = false;
const consoleEl = document.getElementById('scan-console');

function logTime() {
    return new Date().toLocaleTimeString('en-US', {hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
}

function log(msg, type = 'info') {
    const icons = { info: '●', success: '✓', error: '✗', warn: '⚠', step: '▶', dim: '·' };
    const line = document.createElement('div');
    line.className = 'log-line';
    line.innerHTML = `<span class="log-time">${logTime()}</span><span class="log-icon log-${type}">${icons[type] || '●'}</span><span class="log-msg log-${type}">${msg}</span>`;
    consoleEl.appendChild(line);
    consoleEl.scrollTop = consoleEl.scrollHeight;
}

function setProgress(pct, subtitle) {
    document.getElementById('progress-bar').style.width = pct + '%';
    document.getElementById('progress-percent').textContent = Math.round(pct) + '%';
    if (subtitle) document.getElementById('progress-subtitle').textContent = subtitle;
}

function setProgressColor(color) {
    const bar = document.getElementById('progress-bar');
    bar.className = 'progress-bar progress-bar-striped';
    if (color === 'success') bar.classList.add('progress-bar-success');
    else if (color === 'danger') bar.classList.add('progress-bar-danger');
    else if (color === 'warning') bar.classList.add('progress-bar-warning');
    else bar.classList.add('active');
}

function showUI(show) {
    document.getElementById('progress-section').style.display = show ? 'block' : 'none';
    document.getElementById('step-grid').style.display = show ? 'flex' : 'none';
    consoleEl.style.display = show ? 'block' : 'none';
    if (show) {
        consoleEl.innerHTML = '';
        document.getElementById('summary-panel').style.display = 'none';
        document.getElementById('violations-table').style.display = 'none';
    }
}

function disableButtons(disabled) {
    isRunning = disabled;
    document.querySelectorAll('#action-buttons button').forEach(b => {
        b.disabled = disabled;
        b.style.opacity = disabled ? '0.5' : '1';
    });
}

function buildSteps(steps) {
    const grid = document.getElementById('step-grid');
    grid.innerHTML = '';
    steps.forEach(s => {
        const card = document.createElement('div');
        card.className = 'step-card';
        card.id = 'step-' + s.id;
        card.innerHTML = `<div class="step-icon">${s.icon}</div><div class="step-name">${s.name}</div><div class="step-detail" id="step-detail-${s.id}">—</div>`;
        grid.appendChild(card);
    });
}

function setStep(id, state, detail) {
    const card = document.getElementById('step-' + id);
    if (!card) return;
    card.className = 'step-card ' + state;
    if (state === 'active') card.querySelector('.step-icon').innerHTML = '<i class="fa fa-cog fa-spin" style="color:#3498db"></i>';
    else if (state === 'success') card.querySelector('.step-icon').innerHTML = '<i class="fa fa-check-circle" style="color:#27ae60"></i>';
    else if (state === 'error') card.querySelector('.step-icon').innerHTML = '<i class="fa fa-times-circle" style="color:#e74c3c"></i>';
    if (detail) document.getElementById('step-detail-' + id).textContent = detail;
}

async function ajax(action, data = {}) {
    const body = new URLSearchParams();
    body.append('action', action);
    for (const [k, v] of Object.entries(data)) body.append(k, v);

    const resp = await fetch('/admin/scan_ajax.php', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF },
        body: body,
    });
    return resp.json();
}

async function refreshStats() {
    try {
        const r = await ajax('stats');
        if (r.ok) {
            document.getElementById('stat-clients').textContent = r.clients.toLocaleString();
            document.getElementById('stat-remote').textContent = r.remote.toLocaleString();
            document.getElementById('stat-violations').textContent = r.violations;
            if (r.last_sync) document.getElementById('stat-sync').textContent = r.last_sync;
        }
    } catch(e) {}
}

const BULK_SOURCES = ['jadal', 'namaa', 'watar', 'majd'];

async function syncSource(source, stepIdx, totalSteps) {
    const label = LABELS[source] || source;
    const isBulk = BULK_SOURCES.includes(source);
    const actionType = isBulk ? 'sync_bulk' : 'sync_source';

    setStep(source, 'active');
    log(`${isBulk ? LABELS.bulkSync : LABELS.syncing} ${label}...`, 'step');

    try {
        const r = await ajax(actionType, { source });
        const pct = ((stepIdx + 1) / totalSteps) * 100;

        if (r.ok) {
            const detail = `جديد: ${r.synced} | محدّث: ${r.updated} | ${r.elapsed} ث`;
            setStep(source, r.status === 'ok' ? 'success' : 'error', detail);
            setProgress(pct, `${label}: ${r.synced} ${LABELS.newRecords}`);

            if (r.errors > 0) {
                log(`${label}: ${r.synced} ${LABELS.newRecords}, ${r.updated} ${LABELS.updated}, ${r.errors} ${LABELS.errors} (${r.elapsed}s)`, 'warn');
            } else {
                log(`${label}: ${r.synced} ${LABELS.newRecords}, ${r.updated} ${LABELS.updated} (${r.elapsed}s)`, 'success');
            }
            return { synced: r.synced, updated: r.updated, errors: r.errors || 0, elapsed: r.elapsed, ok: true };
        } else if (isBulk) {
            log(`${label}: فشل التصدير الجماعي، جاري التبديل للمزامنة الفردية...`, 'warn');
            return await syncSourceFallback(source, stepIdx, totalSteps);
        } else {
            setStep(source, 'error', LABELS.failed);
            log(`${label}: ${r.error || LABELS.connectionFailed}`, 'error');
            return { synced: 0, updated: 0, errors: 1, elapsed: 0, ok: false };
        }
    } catch (e) {
        if (isBulk) {
            log(`${label}: فشل التصدير الجماعي (${e.message})، جاري التبديل للمزامنة الفردية...`, 'warn');
            return await syncSourceFallback(source, stepIdx, totalSteps);
        }
        setStep(source, 'error', LABELS.connectionFailed);
        log(`${label}: ${e.message}`, 'error');
        return { synced: 0, updated: 0, errors: 1, elapsed: 0, ok: false };
    }
}

async function syncSourceFallback(source, stepIdx, totalSteps) {
    const label = LABELS[source] || source;
    try {
        const r = await ajax('sync_source', { source });
        const pct = ((stepIdx + 1) / totalSteps) * 100;

        if (r.ok) {
            const detail = `جديد: ${r.synced} | محدّث: ${r.updated} | ${r.elapsed} ث (فردي)`;
            setStep(source, r.status === 'ok' ? 'success' : 'error', detail);
            setProgress(pct, `${label}: ${r.synced} ${LABELS.newRecords}`);
            log(`${label}: ${r.synced} ${LABELS.newRecords}, ${r.updated} ${LABELS.updated} (${r.elapsed}s) [مزامنة فردية]`, 'success');
            return { synced: r.synced, updated: r.updated, errors: r.errors || 0, elapsed: r.elapsed, ok: true };
        } else {
            setStep(source, 'error', LABELS.failed);
            log(`${label}: ${r.error || LABELS.connectionFailed}`, 'error');
            return { synced: 0, updated: 0, errors: 1, elapsed: 0, ok: false };
        }
    } catch (e2) {
        setStep(source, 'error', LABELS.connectionFailed);
        log(`${label}: ${e2.message}`, 'error');
        return { synced: 0, updated: 0, errors: 1, elapsed: 0, ok: false };
    }
}

async function scanLocal(stepIdx, totalSteps) {
    setStep('scan_local', 'active');
    log(`${LABELS.scanning}: ${LABELS.scanLocal}...`, 'step');

    try {
        const r = await ajax('scan_local');
        const pct = ((stepIdx + 1) / totalSteps) * 100;

        if (r.ok) {
            setStep('scan_local', 'success', `مخالفات: ${r.violations} | ${r.elapsed} ث`);
            setProgress(pct, `${LABELS.scanLocal}: ${r.violations} ${LABELS.newViolations}`);
            log(`${LABELS.scanLocal}: ${r.violations} ${LABELS.newViolations}, ${r.checked} ${LABELS.checked} (${r.elapsed}s)`, r.violations > 0 ? 'warn' : 'success');
            return { violations: r.violations, elapsed: r.elapsed };
        } else {
            setStep('scan_local', 'error', LABELS.failed);
            log(`${LABELS.scanLocal}: ${r.error}`, 'error');
            return { violations: 0, elapsed: 0 };
        }
    } catch (e) {
        setStep('scan_local', 'error', LABELS.connectionFailed);
        log(`${LABELS.scanLocal}: ${e.message}`, 'error');
        return { violations: 0, elapsed: 0 };
    }
}

async function scanRemote(stepIdx, totalSteps) {
    setStep('scan_remote', 'active');
    log(`${LABELS.scanning}: ${LABELS.scanRemote}...`, 'step');

    try {
        const r = await ajax('scan_remote');
        const pct = ((stepIdx + 1) / totalSteps) * 100;

        if (r.ok) {
            setStep('scan_remote', 'success', `مخالفات: ${r.violations} | ${r.elapsed} ث`);
            setProgress(pct, `${LABELS.scanRemote}: ${r.violations} ${LABELS.newViolations}`);
            log(`${LABELS.scanRemote}: ${r.violations} ${LABELS.newViolations}, ${r.checked} ${LABELS.checked} (${r.elapsed}s)`, r.violations > 0 ? 'warn' : 'success');
            return { violations: r.violations, elapsed: r.elapsed };
        } else {
            setStep('scan_remote', 'error', LABELS.failed);
            log(`${LABELS.scanRemote}: ${r.error}`, 'error');
            return { violations: 0, elapsed: 0 };
        }
    } catch (e) {
        setStep('scan_remote', 'error', LABELS.connectionFailed);
        log(`${LABELS.scanRemote}: ${e.message}`, 'error');
        return { violations: 0, elapsed: 0 };
    }
}

async function scanExternal(stepIdx, totalSteps) {
    setStep('scan_external', 'active');
    log(`${LABELS.scanning}: ${LABELS.scanExternal}...`, 'step');

    try {
        const r = await ajax('scan_external');
        const pct = ((stepIdx + 1) / totalSteps) * 100;

        if (r.ok) {
            setStep('scan_external', 'success', `مخالفات: ${r.violations} | ${r.elapsed} ث`);
            setProgress(pct, `${LABELS.scanExternal}: ${r.violations} ${LABELS.newViolations}`);
            log(`${LABELS.scanExternal}: ${r.violations} ${LABELS.newViolations}, ${r.checked} ${LABELS.checked} (${r.elapsed}s)`, r.violations > 0 ? 'warn' : 'success');
            return { violations: r.violations, elapsed: r.elapsed };
        } else {
            setStep('scan_external', 'error', LABELS.failed);
            log(`${LABELS.scanExternal}: ${r.error}`, 'error');
            return { violations: 0, elapsed: 0 };
        }
    } catch (e) {
        setStep('scan_external', 'error', LABELS.connectionFailed);
        log(`${LABELS.scanExternal}: ${e.message}`, 'error');
        return { violations: 0, elapsed: 0 };
    }
}

function to12h(dateStr) {
    if (!dateStr) return '';
    try {
        var d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        var y = d.getFullYear(), m = String(d.getMonth()+1).padStart(2,'0'), day = String(d.getDate()).padStart(2,'0');
        var h = d.getHours(), ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        var min = String(d.getMinutes()).padStart(2,'0');
        return y+'-'+m+'-'+day+' '+String(h).padStart(2,'0')+':'+min+' '+ampm;
    } catch(e) { return dateStr; }
}

async function showViolationsTable() {
    try {
        const r = await ajax('recent_violations');
        if (!r.ok || !r.data || r.data.length === 0) {
            document.getElementById('violations-table').innerHTML = `<div class="alert alert-warning"><i class="fa fa-info-circle"></i> ${LABELS.noViolations}</div>`;
        } else {
            let html = `<div class="panel panel-danger"><div class="panel-heading"><b><i class="fa fa-gavel"></i> ${LABELS.latestViolations}</b></div><div class="table-responsive"><table class="table table-hover table-striped" style="margin-bottom:0"><thead><tr><th>#</th><th>${LABELS.client}</th><th>${LABELS.nationalId}</th><th>نوع الطرف</th><th>${LABELS.entitledCompany}</th><th>${LABELS.entitledDate}</th><th>${LABELS.violatingCompany}</th><th>${LABELS.violatingDate}</th><th>${LABELS.fine}</th><th>${LABELS.status}</th></tr></thead><tbody>`;
            r.data.forEach(v => {
                let badge = 'default', sLabel = v.status || '';
                if (sLabel === 'active') { badge = 'danger'; sLabel = LABELS.active; }
                else if (sLabel === 'exempted_below_150') { badge = 'info'; sLabel = LABELS.exempted; }
                else if (sLabel === 'resolved') { badge = 'success'; sLabel = LABELS.resolved; }
                const partyBadge = (v.party_type && v.party_type !== 'عميل') ? `<span class="label label-warning">${v.party_type}</span>` : (v.party_type || 'عميل');
                html += `<tr><td>${v.id}</td><td>${v.client_name||''}</td><td>${v.national_id||''}</td><td>${partyBadge}</td><td>${v.entitled_account||''}</td><td>${to12h(v.entitled_sell_date)}</td><td>${v.violating_account||''}</td><td>${to12h(v.violating_sell_date)}</td><td>${parseFloat(v.fine_amount||0).toFixed(2)}</td><td><span class="label label-${badge}">${sLabel}</span></td></tr>`;
            });
            html += '</tbody></table></div></div>';
            document.getElementById('violations-table').innerHTML = html;
        }
        document.getElementById('violations-table').style.display = 'block';
    } catch(e) {}
}

function showSummary(title, stats) {
    let html = `<div class="alert alert-success"><h4><i class="fa fa-check-circle"></i> ${title}</h4><hr style="margin:8px 0">`;
    stats.forEach(s => { html += `<div>${s}</div>`; });
    html += '</div>';
    document.getElementById('summary-panel').innerHTML = html;
    document.getElementById('summary-panel').style.display = 'block';
}

// ─── فحص المخالفات الموحّد ───
async function scanViolations(stepIdx, totalSteps) {
    setStep('scan_violations', 'active');
    log(`${LABELS.scanning}: ${LABELS.scanViolations}...`, 'step');

    try {
        const r = await ajax('scan_violations');
        const pct = ((stepIdx + 1) / totalSteps) * 100;

        if (r.ok) {
            const detail = `${r.violations} ${LABELS.newViolations} | ${r.checked} ${LABELS.checked} | ${r.elapsed} ث`;
            setStep('scan_violations', 'success', detail);
            setProgress(pct, `${LABELS.scanViolations}: ${r.violations} ${LABELS.newViolations}`);
            log(`${LABELS.scanViolations}: ${r.violations} ${LABELS.newViolations}, ${r.checked} ${LABELS.checked} (${r.elapsed}s)`, r.violations > 0 ? 'warn' : 'success');
            if (r.debug) {
                const d = r.debug;
                const parts = Object.entries(d.breakdown || {}).map(([k,v]) => `${k}: ${v}`).join(' | ');
                log(`   ⤷ جدول موحّد: ${d.temp_total} سجل (${parts}) — منها ${d.with_date} بتاريخ`, 'dim');
            }
            return { violations: r.violations, elapsed: r.elapsed };
        } else {
            setStep('scan_violations', 'error', LABELS.failed);
            log(`${LABELS.scanViolations}: ${r.error}`, 'error');
            return { violations: 0, elapsed: 0 };
        }
    } catch (e) {
        setStep('scan_violations', 'error', LABELS.connectionFailed);
        log(`${LABELS.scanViolations}: ${e.message}`, 'error');
        return { violations: 0, elapsed: 0 };
    }
}

// ─── الجرد المجمّع ───
async function runFullScan() {
    if (isRunning) return;
    disableButtons(true);
    showUI(true);

    const steps = [
        { id: 'jadal', icon: '🔗', name: LABELS.jadal },
        { id: 'namaa', icon: '🔗', name: LABELS.namaa },
        { id: 'zajal', icon: '🔗', name: LABELS.zajal },
        { id: 'bseel', icon: '🔗', name: LABELS.bseel },
        { id: 'watar', icon: '🔗', name: LABELS.watar },
        { id: 'majd', icon: '🔗', name: LABELS.majd },
        { id: 'scan_local', icon: '🔍', name: LABELS.scanLocal },
        { id: 'scan_remote', icon: '🌐', name: LABELS.scanRemote },
        { id: 'scan_external', icon: '🔄', name: LABELS.scanExternal },
    ];
    buildSteps(steps);
    setProgress(0, '');
    setProgressColor('info');

    const startTime = Date.now();
    log('═══ ' + LABELS.combinedDone.replace(LABELS.done, '') + ' ═══', 'step');

    const sources = ['jadal', 'namaa', 'zajal', 'bseel', 'watar', 'majd'];
    let totalSynced = 0, totalUpdated = 0, totalErrors = 0;

    for (let i = 0; i < sources.length; i++) {
        const r = await syncSource(sources[i], i, 9);
        totalSynced += r.synced;
        totalUpdated += r.updated;
        totalErrors += r.errors;
    }

    const localResult = await scanLocal(6, 9);
    const remoteResult = await scanRemote(7, 9);
    const externalResult = await scanExternal(8, 9);

    const totalViolations = localResult.violations + remoteResult.violations + externalResult.violations;
    const totalTime = ((Date.now() - startTime) / 1000).toFixed(1);

    setProgress(100);
    setProgressColor(totalErrors > 0 ? 'warning' : 'success');
    document.getElementById('progress-bar').classList.remove('active');
    document.getElementById('progress-title').innerHTML = `<i class="fa fa-check-circle" style="color:#27ae60"></i> ${LABELS.done}`;

    log('─────────────────────────────', 'dim');
    log(`${LABELS.totalSynced}: ${totalSynced} (+${totalUpdated} ${LABELS.updated})`, 'info');
    log(`${LABELS.totalViolations}: ${totalViolations}`, totalViolations > 0 ? 'warn' : 'success');
    log(`${LABELS.totalTime}: ${totalTime}s`, 'info');

    showSummary(LABELS.combinedDone, [
        `<i class="fa fa-sync"></i> ${LABELS.totalSynced}: <b>${totalSynced}</b> (+${totalUpdated} ${LABELS.updated})`,
        `<i class="fa fa-exclamation-triangle"></i> ${LABELS.totalViolations}: <b>${totalViolations}</b>`,
        totalErrors > 0 ? `<i class="fa fa-times-circle" style="color:red"></i> ${LABELS.errors}: <b>${totalErrors}</b>` : '',
        `<i class="fa fa-clock"></i> ${LABELS.totalTime}: <b>${totalTime}s</b>`,
    ].filter(Boolean));

    await refreshStats();
    await showViolationsTable();
    disableButtons(false);
}

// ─── المزامنة فقط ───
async function runSyncOnly() {
    if (isRunning) return;
    disableButtons(true);
    showUI(true);

    const steps = [
        { id: 'jadal', icon: '🔗', name: LABELS.jadal },
        { id: 'namaa', icon: '🔗', name: LABELS.namaa },
        { id: 'zajal', icon: '🔗', name: LABELS.zajal },
        { id: 'bseel', icon: '🔗', name: LABELS.bseel },
        { id: 'watar', icon: '🔗', name: LABELS.watar },
        { id: 'majd', icon: '🔗', name: LABELS.majd },
    ];
    buildSteps(steps);
    setProgress(0, '');
    setProgressColor('info');

    const startTime = Date.now();
    log('═══ ' + LABELS.syncing + ' ═══', 'step');

    const sources = ['jadal', 'namaa', 'zajal', 'bseel', 'watar', 'majd'];
    let totalSynced = 0, totalErrors = 0;

    for (let i = 0; i < sources.length; i++) {
        const r = await syncSource(sources[i], i, 6);
        totalSynced += r.synced;
        totalErrors += r.errors;
    }

    const totalTime = ((Date.now() - startTime) / 1000).toFixed(1);
    setProgress(100);
    setProgressColor(totalErrors > 0 ? 'warning' : 'success');
    document.getElementById('progress-bar').classList.remove('active');
    document.getElementById('progress-title').innerHTML = `<i class="fa fa-check-circle" style="color:#27ae60"></i> ${LABELS.done}`;

    showSummary(LABELS.syncDone, [
        `<i class="fa fa-sync"></i> ${LABELS.totalSynced}: <b>${totalSynced}</b>`,
        totalErrors > 0 ? `<i class="fa fa-times-circle" style="color:red"></i> ${LABELS.errors}: <b>${totalErrors}</b>` : '',
        `<i class="fa fa-clock"></i> ${LABELS.totalTime}: <b>${totalTime}s</b>`,
    ].filter(Boolean));

    await refreshStats();
    disableButtons(false);
}

// ─── فحص المخالفات فقط ───
async function runScanOnly() {
    if (isRunning) return;
    disableButtons(true);
    showUI(true);

    const steps = [
        { id: 'scan_violations', icon: '🔍', name: LABELS.scanViolations },
    ];
    buildSteps(steps);
    setProgress(0, '');
    setProgressColor('info');

    const startTime = Date.now();
    log('═══ ' + LABELS.scanning + ' ═══', 'step');

    const scanResult = await scanViolations(0, 1);
    const totalViolations = scanResult.violations;
    const totalTime = ((Date.now() - startTime) / 1000).toFixed(1);

    setProgress(100);
    setProgressColor('success');
    document.getElementById('progress-bar').classList.remove('active');
    document.getElementById('progress-title').innerHTML = `<i class="fa fa-check-circle" style="color:#27ae60"></i> ${LABELS.done}`;

    showSummary(LABELS.scanDone, [
        `<i class="fa fa-exclamation-triangle"></i> ${LABELS.totalViolations}: <b>${totalViolations}</b>`,
        `<i class="fa fa-clock"></i> ${LABELS.totalTime}: <b>${totalTime}s</b>`,
    ]);

    await refreshStats();
    await showViolationsTable();
    disableButtons(false);
}
</script>

<?php include 'footer.php'; ?>
