<?php
/**
 * AJAX endpoint for step-by-step scan & sync operations.
 * Each request handles ONE step and returns JSON progress.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED);
header('Content-Type: application/json; charset=utf-8');

$token = 'mojeer';
$admin = 1;
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/violation_engine.php';

if (!auth_check() || !user_can('scan', 'execute')) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals(csrf_token(), $csrfToken)) {
    echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch']);
    exit;
}

set_time_limit(600);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$sourceLabels = [
    'zajal' => 'زجل',
    'jadal' => 'جدل',
    'namaa' => 'نماء',
    'bseel' => 'بسيل',
    'watar' => 'وتر',
];

$sourceUrls = [
    'zajal' => 'https://zajal.cc/fahras-api.php?token=354afdf5357c&search=',
    'jadal' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=jadal&search=',
    'namaa' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=erp&search=',
    'bseel' => 'https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&search=',
    'watar' => 'https://watar.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=watar&search=',
];

$bulkExportUrls = [
    'jadal' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=jadal&action=bulk_export',
    'namaa' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=erp&action=bulk_export',
    'bseel' => 'https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&action=bulk_export',
    'watar' => 'https://watar.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=watar&action=bulk_export',
];

$bulkSources = ['jadal', 'namaa', 'bseel', 'watar'];

switch ($action) {

    // ─── الخطوة 0: جمع الإحصائيات ───
    case 'stats':
        try {
            $totalClients = $db->get_count('clients') ?: 0;
            $totalRemote  = $db->get_count('remote_clients') ?: 0;
            $totalActive  = $db->get_count('violations', ['status' => 'active']) ?: 0;
            $syncStmt = $db->run("SELECT MAX(synced_at) as last_sync FROM remote_clients");
            $lastSyncRow = ($syncStmt && is_object($syncStmt)) ? $syncStmt->fetch() : [];
            $lastSync = $lastSyncRow['last_sync'] ?? null;

            $searchTerms = getUniqueSearchTerms();

            echo json_encode([
                'ok' => true,
                'clients' => $totalClients,
                'remote' => $totalRemote,
                'violations' => $totalActive,
                'last_sync' => $lastSync,
                'search_terms_count' => count($searchTerms),
            ]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─── مزامنة مصدر واحد ───
    case 'sync_source':
        $source = $_POST['source'] ?? '';
        if (!isset($sourceUrls[$source])) {
            echo json_encode(['ok' => false, 'error' => 'Invalid source']);
            break;
        }

        $baseUrl = $sourceUrls[$source];
        $label   = $sourceLabels[$source];

        try {
            $searchTerms = getUniqueSearchTerms();
            $totalTerms = count($searchTerms);
            $synced = 0;
            $updated = 0;
            $failed = 0;
            $apiErrors = 0;
            $jsonFailStreak = 0;
            $startTime = microtime(true);

            $batches = array_chunk($searchTerms, 30);

            foreach ($batches as $batchIdx => $batch) {
                $urls = [];
                foreach ($batch as $term) {
                    $urls[$term] = $baseUrl . urlencode($term);
                }

                $responses = curl_multi_load($urls, 30);

                $batchJsonFails = 0;
                foreach ($responses as $term => $resp) {
                    if ($resp['error']) {
                        $apiErrors++;
                        continue;
                    }

                    $raw = $resp['body'];
                    if (empty($raw)) continue;

                    $decoded = json_decode($raw, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                        $apiErrors++;
                        $batchJsonFails++;
                        continue;
                    }

                    if (isset($decoded['data']) && is_array($decoded['data'])) {
                        $decoded = $decoded['data'];
                    }

                    foreach ($decoded as $entry) {
                        if (!is_array($entry) || empty($entry['name'])) continue;

                        $contractId = $entry['id'] ?? $entry['cid'] ?? null;
                        $entryName  = $entry['name'];

                        $lookupWhere = ['source' => $source];
                        if (!empty($contractId) && $contractId !== '0') {
                            $lookupWhere['remote_id'] = $contractId;
                        } else {
                            $lookupWhere['name'] = $entryName;
                        }

                        $existingCount = $db->get_count('remote_clients', $lookupWhere);

                        if ($existingCount > 0) {
                            $updateSql = "UPDATE remote_clients SET remaining_amount = ?, status = ?, created_on = ?, sell_date = ?, party_type = ?, synced_at = NOW() WHERE source = ?";
                            $updateParams = [$entry['remaining_amount'] ?? null, $entry['status'] ?? null, $entry['created_on'] ?? null, $entry['sell_date'] ?? null, $entry['party_type'] ?? 'عميل', $source];
                            if (!empty($contractId) && $contractId !== '0') {
                                $updateSql .= " AND remote_id = ?";
                                $updateParams[] = $contractId;
                            } else {
                                $updateSql .= " AND name = ?";
                                $updateParams[] = $entryName;
                            }
                            $db->run($updateSql, $updateParams);
                            $updated++;
                        } else {
                            $db->insert('remote_clients', [
                                'source'           => $source,
                                'remote_id'        => $contractId,
                                'name'             => $entryName,
                                'party_type'       => $entry['party_type'] ?? 'عميل',
                                'national_id'      => $entry['national_id'] ?? null,
                                'sell_date'        => $entry['sell_date'] ?? null,
                                'remaining_amount' => $entry['remaining_amount'] ?? null,
                                'status'           => $entry['status'] ?? null,
                                'phone'            => $entry['phone'] ?? null,
                                'raw_data'         => json_encode($entry, JSON_UNESCAPED_UNICODE),
                                'created_on'       => $entry['created_on'] ?? null,
                            ]);
                            $synced++;
                        }
                    }
                }

                if ($batchJsonFails === count($batch)) {
                    $jsonFailStreak++;
                } else {
                    $jsonFailStreak = 0;
                }
                if ($jsonFailStreak >= 2) {
                    $apiErrors = $totalTerms;
                    break;
                }
            }

            $elapsed = round(microtime(true) - $startTime, 1);
            $statusText = $apiErrors > 0 ? 'partial' : 'ok';

            log_activity('sync_source', null, null, "$source: synced=$synced, updated=$updated, errors=$apiErrors, time={$elapsed}s");

            echo json_encode([
                'ok'       => true,
                'source'   => $source,
                'label'    => $label,
                'status'   => $statusText,
                'synced'   => $synced,
                'updated'  => $updated,
                'errors'   => $apiErrors,
                'total_terms' => $totalTerms,
                'elapsed'  => $elapsed,
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'ok'     => false,
                'source' => $source,
                'label'  => $label,
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }
        break;

    // ─── مزامنة جماعية: طلب واحد لكل مصدر ───
    case 'sync_bulk':
        $source = $_POST['source'] ?? '';
        if (!isset($bulkExportUrls[$source])) {
            echo json_encode(['ok' => false, 'error' => 'Source does not support bulk export']);
            break;
        }

        $url   = $bulkExportUrls[$source];
        $label = $sourceLabels[$source];

        try {
            $startTime = microtime(true);
            $synced = 0;
            $updated = 0;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if (!empty($curlErr) || $httpCode !== 200) {
                throw new Exception("HTTP $httpCode - $curlErr");
            }

            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new Exception("Invalid JSON: " . json_last_error_msg());
            }

            if (isset($decoded['data']) && is_array($decoded['data'])) {
                $decoded = $decoded['data'];
            }

            $db->run("DELETE FROM remote_clients WHERE source = ?", [$source]);

            $insertValues = [];
            $insertParams = [];

            foreach ($decoded as $entry) {
                if (!is_array($entry) || empty($entry['name'])) continue;

                $contractId = $entry['id'] ?? $entry['cid'] ?? null;

                $insertValues[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $insertParams = array_merge($insertParams, [
                    $source,
                    $contractId,
                    $entry['name'],
                    $entry['party_type'] ?? 'عميل',
                    $entry['national_id'] ?? null,
                    $entry['sell_date'] ?? null,
                    $entry['remaining_amount'] ?? null,
                    $entry['status'] ?? null,
                    $entry['phone'] ?? null,
                    $entry['created_on'] ?? null,
                ]);

                $synced++;

                if (count($insertValues) >= 100) {
                    $sql = "INSERT INTO remote_clients (source, remote_id, name, party_type, national_id, sell_date, remaining_amount, status, phone, created_on, synced_at) VALUES " . implode(',', $insertValues);
                    $db->run($sql, $insertParams);
                    $insertValues = [];
                    $insertParams = [];
                }
            }

            if (!empty($insertValues)) {
                $sql = "INSERT INTO remote_clients (source, remote_id, name, party_type, national_id, sell_date, remaining_amount, status, phone, created_on, synced_at) VALUES " . implode(',', $insertValues);
                $db->run($sql, $insertParams);
            }

            $elapsed = round(microtime(true) - $startTime, 1);
            log_activity('sync_bulk', null, null, "$source: synced=$synced, time={$elapsed}s");

            echo json_encode([
                'ok'      => true,
                'source'  => $source,
                'label'   => $label,
                'status'  => 'ok',
                'synced'  => $synced,
                'updated' => 0,
                'errors'  => 0,
                'elapsed' => $elapsed,
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'ok'     => false,
                'source' => $source,
                'label'  => $label,
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }
        break;

    // ─── فحص المخالفات: محلي ← محلي ───
    case 'scan_local':
        try {
            $startTime = microtime(true);
            $newViolations = 0;
            $excludedIds = implode(',', getExcludedAccountIds());

            $stmt = $db->run("
                SELECT c1.id AS first_id, c1.name, c1.national_id, c1.account AS first_account,
                       COALESCE(c1.created_on, c1.sell_date) AS first_date,
                       c1.remaining_amount, c1.status AS first_status,
                       c2.id AS second_id, c2.account AS second_account,
                       COALESCE(c2.created_on, c2.sell_date) AS second_date
                FROM clients c1
                INNER JOIN clients c2
                    ON c1.id != c2.id
                    AND c1.account != c2.account
                    AND (
                        (c1.national_id != '' AND c1.national_id IS NOT NULL AND c1.national_id = c2.national_id)
                        OR (c1.name = c2.name AND (c1.national_id IS NULL OR c1.national_id = ''))
                    )
                WHERE COALESCE(c1.created_on, c1.sell_date) < COALESCE(c2.created_on, c2.sell_date)
                    AND c1.account NOT IN ($excludedIds) AND c2.account NOT IN ($excludedIds)
                ORDER BY COALESCE(c1.created_on, c1.sell_date) ASC
                LIMIT 5000
            ");
            $duplicates = ($stmt && is_object($stmt)) ? $stmt->fetchAll() : [];

            foreach ($duplicates as $dup) {
                $recorded = recordViolationFromDuplicate(
                    $dup['name'], $dup['national_id'],
                    $dup['first_account'], 'local', $dup['first_date'],
                    $dup['remaining_amount'], $dup['first_status'],
                    $dup['second_account'], 'local', $dup['second_date']
                );
                if ($recorded) $newViolations++;
            }

            $elapsed = round(microtime(true) - $startTime, 1);
            log_activity('scan_local', null, null, "Found $newViolations violations in {$elapsed}s, checked " . count($duplicates) . " pairs");

            echo json_encode([
                'ok' => true,
                'violations' => $newViolations,
                'checked' => count($duplicates),
                'elapsed' => $elapsed,
            ]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─── فحص المخالفات: محلي ← خارجي ───
    case 'scan_remote':
        try {
            $startTime = microtime(true);
            $newViolations = 0;
            $checked = 0;
            $sourceMap = ['zajal' => 'زجل', 'jadal' => 'جدل', 'namaa' => 'نماء', 'bseel' => 'بسيل', 'watar' => 'وتر'];

            // العميل المحلي أقدم → الشركة الخارجية مخالفة
            $stmt2 = $db->run("
                SELECT c.name, c.national_id, c.account AS local_account,
                       COALESCE(c.created_on, c.sell_date) AS local_date,
                       c.remaining_amount, c.status AS local_status,
                       r.source AS remote_source,
                       COALESCE(r.created_on, r.sell_date) AS remote_date,
                       r.remaining_amount AS remote_remaining,
                       r.status AS remote_status,
                       r.party_type AS remote_party_type
                FROM clients c
                INNER JOIN remote_clients r ON (
                    (c.national_id != '' AND c.national_id IS NOT NULL AND c.national_id = r.national_id)
                    OR (c.name = r.name AND (c.national_id IS NULL OR c.national_id = ''))
                )
                WHERE COALESCE(c.created_on, c.sell_date) IS NOT NULL
                    AND COALESCE(r.created_on, r.sell_date) IS NOT NULL
                    AND COALESCE(c.created_on, c.sell_date) < COALESCE(r.created_on, r.sell_date)
                LIMIT 5000
            ");
            $localFirst = ($stmt2 && is_object($stmt2)) ? $stmt2->fetchAll() : [];
            $checked += count($localFirst);

            foreach ($localFirst as $dup) {
                $localAccountName = $db->get_var('accounts', ['id' => $dup['local_account']], ['name']) ?: $dup['local_account'];
                $remoteAccountName = $sourceMap[$dup['remote_source']] ?? ucfirst($dup['remote_source']);
                if ($localAccountName === $remoteAccountName) continue;

                $partyType = $dup['remote_party_type'] ?? 'عميل';
                $recorded = recordViolationFromData(
                    $dup['name'], $dup['national_id'],
                    (string)$localAccountName, 'local', $dup['local_date'],
                    $dup['remaining_amount'], $dup['local_status'],
                    (string)$remoteAccountName, $dup['remote_source'], $dup['remote_date'],
                    $partyType
                );
                if ($recorded) $newViolations++;
            }

            // الشركة الخارجية أقدم → العميل المحلي مخالف
            $stmt3 = $db->run("
                SELECT c.name, c.national_id, c.account AS local_account,
                       COALESCE(c.created_on, c.sell_date) AS local_date,
                       r.source AS remote_source,
                       COALESCE(r.created_on, r.sell_date) AS remote_date,
                       r.remaining_amount AS remote_remaining,
                       r.status AS remote_status,
                       r.party_type AS remote_party_type
                FROM clients c
                INNER JOIN remote_clients r ON (
                    (c.national_id != '' AND c.national_id IS NOT NULL AND c.national_id = r.national_id)
                    OR (c.name = r.name AND (c.national_id IS NULL OR c.national_id = ''))
                )
                WHERE COALESCE(c.created_on, c.sell_date) IS NOT NULL
                    AND COALESCE(r.created_on, r.sell_date) IS NOT NULL
                    AND COALESCE(r.created_on, r.sell_date) < COALESCE(c.created_on, c.sell_date)
                LIMIT 5000
            ");
            $remoteFirst = ($stmt3 && is_object($stmt3)) ? $stmt3->fetchAll() : [];
            $checked += count($remoteFirst);

            foreach ($remoteFirst as $dup) {
                $localAccountName = $db->get_var('accounts', ['id' => $dup['local_account']], ['name']) ?: $dup['local_account'];
                $remoteAccountName = $sourceMap[$dup['remote_source']] ?? ucfirst($dup['remote_source']);
                if ($localAccountName === $remoteAccountName) continue;

                $partyType = $dup['remote_party_type'] ?? 'عميل';
                $recorded = recordViolationFromData(
                    $dup['name'], $dup['national_id'],
                    (string)$remoteAccountName, $dup['remote_source'], $dup['remote_date'],
                    $dup['remote_remaining'], $dup['remote_status'],
                    (string)$localAccountName, 'local', $dup['local_date'],
                    $partyType
                );
                if ($recorded) $newViolations++;
            }

            $elapsed = round(microtime(true) - $startTime, 1);
            log_activity('scan_remote', null, null, "Found $newViolations violations in {$elapsed}s, checked $checked pairs");

            echo json_encode([
                'ok' => true,
                'violations' => $newViolations,
                'checked' => $checked,
                'elapsed' => $elapsed,
            ]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─── فحص المخالفات: خارجي ← خارجي ───
    case 'scan_external':
        try {
            $startTime = microtime(true);
            $newViolations = 0;
            $checked = 0;
            $sourceMap = ['zajal' => 'زجل', 'jadal' => 'جدل', 'namaa' => 'نماء', 'bseel' => 'بسيل', 'watar' => 'وتر'];

            $stmt = $db->run("
                SELECT r1.name, r1.national_id,
                       r1.source AS first_source,
                       COALESCE(r1.created_on, r1.sell_date) AS first_date,
                       r1.remaining_amount AS first_remaining,
                       r1.status AS first_status,
                       r1.party_type AS first_party_type,
                       r2.source AS second_source,
                       COALESCE(r2.created_on, r2.sell_date) AS second_date,
                       r2.party_type AS second_party_type
                FROM remote_clients r1
                INNER JOIN remote_clients r2 ON (
                    r1.source != r2.source
                    AND (
                        (r1.national_id != '' AND r1.national_id IS NOT NULL AND r1.national_id = r2.national_id)
                        OR (r1.name = r2.name AND (r1.national_id IS NULL OR r1.national_id = ''))
                    )
                )
                WHERE COALESCE(r1.created_on, r1.sell_date) IS NOT NULL
                    AND COALESCE(r2.created_on, r2.sell_date) IS NOT NULL
                    AND COALESCE(r1.created_on, r1.sell_date) < COALESCE(r2.created_on, r2.sell_date)
                LIMIT 10000
            ");
            $pairs = ($stmt && is_object($stmt)) ? $stmt->fetchAll() : [];
            $checked = count($pairs);

            foreach ($pairs as $dup) {
                $firstLabel  = $sourceMap[$dup['first_source']] ?? ucfirst($dup['first_source']);
                $secondLabel = $sourceMap[$dup['second_source']] ?? ucfirst($dup['second_source']);

                $partyType = $dup['first_party_type'] ?? $dup['second_party_type'] ?? 'عميل';
                $recorded = recordViolationFromData(
                    $dup['name'], $dup['national_id'],
                    (string)$firstLabel, $dup['first_source'], $dup['first_date'],
                    $dup['first_remaining'], $dup['first_status'],
                    (string)$secondLabel, $dup['second_source'], $dup['second_date'],
                    $partyType
                );
                if ($recorded) $newViolations++;
            }

            $elapsed = round(microtime(true) - $startTime, 1);
            log_activity('scan_external', null, null, "Found $newViolations violations in {$elapsed}s, checked $checked pairs");

            echo json_encode([
                'ok' => true,
                'violations' => $newViolations,
                'checked' => $checked,
                'elapsed' => $elapsed,
            ]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ─── جلب آخر المخالفات ───
    case 'recent_violations':
        try {
            $stmt = $db->run("SELECT * FROM violations ORDER BY id DESC LIMIT 20");
            $rows = ($stmt && is_object($stmt)) ? $stmt->fetchAll() : [];
            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}

/**
 * Get unique, deduplicated search terms from local clients.
 * Uses national_id when available (precise), falls back to name.
 * Prioritizes clients with national_id first for faster API matching.
 */
function getUniqueSearchTerms() {
    global $db;

    $terms = [];
    $seen = [];

    $stmtNid = $db->run("SELECT national_id, MIN(id) AS min_id FROM clients WHERE national_id IS NOT NULL AND national_id != '' AND national_id != '0' GROUP BY national_id ORDER BY min_id ASC LIMIT 400");
    $nidRows = ($stmtNid && is_object($stmtNid)) ? $stmtNid->fetchAll() : [];

    foreach ($nidRows as $row) {
        $term = trim($row['national_id']);
        if (empty($term)) continue;
        $key = mb_strtolower($term);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $terms[] = $term;
    }

    $remaining = 500 - count($terms);
    if ($remaining > 0) {
        $stmtName = $db->run("SELECT name, MIN(id) AS min_id FROM clients WHERE name != '' AND (national_id IS NULL OR national_id = '' OR national_id = '0') GROUP BY name ORDER BY min_id ASC LIMIT $remaining");
        $nameRows = ($stmtName && is_object($stmtName)) ? $stmtName->fetchAll() : [];

        foreach ($nameRows as $row) {
            $term = trim($row['name']);
            if (empty($term)) continue;
            $key = mb_strtolower($term);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $terms[] = $term;
        }
    }

    return $terms;
}
