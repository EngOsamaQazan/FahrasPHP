<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  Fahras Verdict API — for Tayseer integration
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Endpoint:
 *    GET  /admin/api/check.php?token=SECRET&id_number=...&name=...&phone=...&client=tayseer
 *    POST /admin/api/check.php  (same params via x-www-form-urlencoded or JSON body)
 *
 *  Response (JSON, UTF-8):
 *    {
 *      "ok":           true,
 *      "verdict":      "can_sell" | "cannot_sell" | "contact_first" | "no_record",
 *      "reason_code":  "DUPLICATE_ACTIVE_CONTRACT" | "NO_RECORD" | ...,
 *      "reason_ar":    "النص العربي للسبب",
 *      "matches":      [ { "account":"...", "name":"...", "national_id":"...",
 *                          "remaining_amount":1500, "status":"نشط",
 *                          "contract_id":"...", "_source":"..." }, ... ],
 *      "checked_at":   "2026-04-19T12:00:00+03:00",
 *      "request_id":   "uuid-v4",
 *      "client":       "tayseer"
 *    }
 *
 *  Failure modes:
 *    HTTP 400  → Missing required parameter
 *    HTTP 403  → Missing/invalid token
 *    HTTP 405  → Method not allowed
 *    HTTP 500  → Internal error  (still returns JSON envelope)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

// ─── CORS (Tayseer environments) ─────────────────────────────────
$allowedOrigins = [
    'https://app.tayseer.co',
    'https://tayseer.aqssat.co',
    'https://staging.tayseer.co',
    'http://localhost',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Tokens (separate per-client tokens) ─────────────────────────
// Override via env vars in production; defaults are *example* values.
$TOKENS = [
    'tayseer' => getenv('FAHRAS_TOKEN_TAYSEER') ?: 'tayseer_fahras_2026_change_me',
];

// ─── Helpers ─────────────────────────────────────────────────────
function fc_respond(array $payload, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fc_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function fc_input(string $key, $default = null)
{
    if (isset($_GET[$key]))  return $_GET[$key];
    if (isset($_POST[$key])) return $_POST[$key];
    static $jsonBody = null;
    if ($jsonBody === null) {
        $raw = file_get_contents('php://input') ?: '';
        $jsonBody = json_decode($raw, true) ?: [];
    }
    return $jsonBody[$key] ?? $default;
}

function fc_clean_str($v, int $max = 255): string
{
    $v = (string)$v;
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    $v = trim($v ?? '');
    return mb_substr($v, 0, $max, 'UTF-8');
}

// ─── Method gate ─────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    fc_respond(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

// ─── Token gate ──────────────────────────────────────────────────
$token  = fc_clean_str(fc_input('token', ''));
$client = fc_clean_str(fc_input('client', 'tayseer'), 32);

if ($token === '' || empty($TOKENS[$client] ?? null) || !hash_equals($TOKENS[$client], $token)) {
    fc_respond(['ok' => false, 'error' => 'unauthorized'], 403);
}

// ─── Inputs ──────────────────────────────────────────────────────
$idNumber = fc_clean_str(fc_input('id_number', ''), 20);
$name     = fc_clean_str(fc_input('name', ''), 250);
$phone    = fc_clean_str(fc_input('phone', ''), 30);

if ($idNumber === '' && $name === '') {
    fc_respond([
        'ok'    => false,
        'error' => 'missing_parameter',
        'hint'  => 'Either id_number or name is required.',
    ], 400);
}

if ($idNumber !== '' && !preg_match('/^\d{5,20}$/', $idNumber)) {
    fc_respond([
        'ok'    => false,
        'error' => 'invalid_id_number',
        'hint'  => 'id_number must be 5-20 digits.',
    ], 400);
}

// ─── Bootstrap Fahras core ──────────────────────────────────────
try {
    require_once __DIR__ . '/../../includes/bootstrap.php';
    require_once __DIR__ . '/../../includes/violation_engine.php';
} catch (Throwable $e) {
    fc_respond([
        'ok'    => false,
        'error' => 'bootstrap_failed',
        'detail'=> $e->getMessage(),
    ], 500);
}

$requestId = fc_uuid_v4();
$startedAt = microtime(true);

/* ═══════════════════════════════════════════════════════════════
   1. Local search (clients table) + remote_clients cache
   ═══════════════════════════════════════════════════════════════ */
$allResults = [];

try {
    global $db;

    $localRows = [];
    if ($idNumber !== '') {
        $stmt = $db->prepare("SELECT a.*,
            (SELECT COUNT(*) FROM attachments WHERE client = a.id) AS attachments,
            'local' AS _source
            FROM clients a
            WHERE a.national_id = :nid
            LIMIT 25");
        $stmt->execute(['nid' => $idNumber]);
        $localRows = $stmt->fetchAll();
    } elseif ($name !== '') {
        $stmt = $db->prepare("SELECT a.*,
            (SELECT COUNT(*) FROM attachments WHERE client = a.id) AS attachments,
            'local' AS _source
            FROM clients a
            WHERE a.name = :n
            LIMIT 25");
        $stmt->execute(['n' => $name]);
        $localRows = $stmt->fetchAll();
    }
    $allResults = array_merge($allResults, $localRows ?: []);

    if ($idNumber !== '') {
        $stmt2 = $db->prepare("SELECT *, source AS _source FROM remote_clients WHERE national_id = :nid LIMIT 50");
        $stmt2->execute(['nid' => $idNumber]);
        $allResults = array_merge($allResults, $stmt2->fetchAll() ?: []);
    } elseif ($name !== '') {
        $stmt2 = $db->prepare("SELECT *, source AS _source FROM remote_clients WHERE name = :n LIMIT 50");
        $stmt2->execute(['n' => $name]);
        $allResults = array_merge($allResults, $stmt2->fetchAll() ?: []);
    }
} catch (Throwable $e) {
    // Local DB error → fail-closed for the caller (Tayseer is fail-closed).
    fc_respond([
        'ok'         => false,
        'error'      => 'db_error',
        'detail'     => $e->getMessage(),
        'request_id' => $requestId,
    ], 500);
}

/* ═══════════════════════════════════════════════════════════════
   2. Live remote APIs (parallel) — search by id_number first,
      else by name. We hit the same remote APIs that the dashboard
      uses, so the verdict reflects up-to-date data.
   ═══════════════════════════════════════════════════════════════ */
$remoteErrors = [];
$searchTerm   = $idNumber !== '' ? $idNumber : $name;

if ($searchTerm !== '') {
    $searchEnc = urlencode($searchTerm);
    $remoteApis = [
        'zajal' => ['url' => 'https://zajal.cc/fahras-api.php?token=354afdf5357c&search=' . $searchEnc, 'label' => 'زجل'],
        'jadal' => ['url' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=jadal&search=' . $searchEnc, 'label' => 'جدل'],
        'namaa' => ['url' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=erp&search=' . $searchEnc, 'label' => 'نماء'],
        'bseel' => ['url' => 'https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&search=' . $searchEnc, 'label' => 'بسيل'],
        'watar' => ['url' => 'https://watar.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=watar&search=' . $searchEnc, 'label' => 'وتر'],
        'majd'  => ['url' => 'https://majd.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=majd&search=' . $searchEnc, 'label' => 'عالم المجد'],
    ];

    $remoteUrls = [];
    foreach ($remoteApis as $k => $v) $remoteUrls[$k] = $v['url'];

    try {
        $multi = curl_multi_load($remoteUrls);
    } catch (Throwable $e) {
        $multi = [];
    }

    foreach ($remoteApis as $srcKey => $api) {
        $raw   = $multi[$srcKey]['body']  ?? null;
        $err   = $multi[$srcKey]['error'] ?? null;
        if ($err || $raw === null || trim($raw) === '') {
            $remoteErrors[] = ['source' => $srcKey, 'label' => $api['label'], 'error' => $err ?: 'no_response'];
            continue;
        }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $remoteErrors[] = ['source' => $srcKey, 'label' => $api['label'], 'error' => 'invalid_json'];
            continue;
        }
        if (isset($decoded['error'])) {
            $remoteErrors[] = ['source' => $srcKey, 'label' => $api['label'], 'error' => (string)$decoded['error']];
            continue;
        }
        if (isset($decoded['data']) && is_array($decoded['data'])) $decoded = $decoded['data'];

        $decoded = array_slice($decoded, 0, 25);

        // When searching by id_number, drop rows that don't match exactly
        // (some sources do partial matching). The verdict must be precise.
        if ($idNumber !== '') {
            $decoded = array_values(array_filter($decoded, function ($r) use ($idNumber) {
                $nid = trim((string)($r['national_id'] ?? ''));
                return $nid !== '' && $nid === $idNumber;
            }));
        }

        foreach ($decoded as &$r) { $r['_source'] = $srcKey; }
        unset($r);
        $allResults = array_merge($allResults, $decoded);
    }

    // Best-effort cache (don't fail if cache write errors)
    try { cacheRemoteResults($allResults); } catch (Throwable $e) {}
}

/* ═══════════════════════════════════════════════════════════════
   3. Run violation engine
   ═══════════════════════════════════════════════════════════════ */
$verdict     = 'no_record';
$reasonCode  = 'NO_RECORD';
$reasonAr    = 'لا يوجد سجل لهذا العميل في الفهرس — يمكن إضافته.';
$matchesOut  = [];

try {
    $analyzed = analyzeSearchResults($allResults);

    if (!empty($analyzed)) {
        // Reduce all groups into a single overall verdict, with the strictest
        // recommendation winning (cannot_sell ≻ contact_first ≻ can_sell).
        $priority = ['cannot_sell' => 3, 'contact_first' => 2, 'can_sell' => 1];
        $best = null;
        foreach ($analyzed as $a) {
            $rec = $a['recommendation'] ?? 'can_sell';
            if ($best === null || ($priority[$rec] ?? 0) > ($priority[$best['recommendation']] ?? 0)) {
                $best = $a;
            }
        }
        if ($best) {
            $verdict   = $best['recommendation'];
            $reasonAr  = (string)($best['message'] ?? '');
            $reasonCode = strtoupper(str_replace(' ', '_', $verdict));
            switch ($verdict) {
                case 'cannot_sell':
                    $reasonCode = 'BLOCKED_BY_VIOLATION_RULES';
                    break;
                case 'contact_first':
                    $reasonCode = 'CONTACT_OWNER_COMPANY';
                    break;
                case 'can_sell':
                    $reasonCode = 'ALLOWED';
                    break;
            }
            // Deduplicate matches across local+remote sources by
            // (canonical_account, contract_id || national_id+sell_date).
            $seenMatch = [];
            foreach ($best['results'] ?? [] as $r) {
                $accountName = resolveAccountName($r);
                $contractId  = $r['id'] ?? $r['cid'] ?? '';
                $effDate     = getEffectiveDate($r) ?? '';
                $dedupeKey   = $accountName . '|' . ($contractId !== '' ? $contractId : ($r['national_id'] ?? '') . '@' . $effDate);
                if (isset($seenMatch[$dedupeKey])) continue;
                $seenMatch[$dedupeKey] = true;

                $matchesOut[] = [
                    'account'          => $accountName,
                    'source'           => $r['_source'] ?? 'local',
                    'name'             => $r['name'] ?? '',
                    'national_id'      => $r['national_id'] ?? '',
                    'phone'            => $r['phone'] ?? '',
                    'remaining_amount' => isset($r['remaining_amount']) ? (float)$r['remaining_amount'] : null,
                    'status'           => $r['status'] ?? '',
                    'contract_id'      => $contractId !== '' ? $contractId : null,
                    'created_on'       => $effDate ?: null,
                    'first_account'    => $best['first_account']  ?? null,
                    'first_phone'      => $best['first_phone']    ?? null,
                ];
            }
        }
    }
} catch (Throwable $e) {
    fc_respond([
        'ok'         => false,
        'error'      => 'analysis_error',
        'detail'     => $e->getMessage(),
        'request_id' => $requestId,
    ], 500);
}

/* ═══════════════════════════════════════════════════════════════
   4. Audit log (best-effort)
   ═══════════════════════════════════════════════════════════════ */
try {
    log_activity(
        'api_check_' . $client,
        'verdict',
        null,
        json_encode([
            'request_id'  => $requestId,
            'id_number'   => $idNumber,
            'name'        => $name,
            'verdict'     => $verdict,
            'matches'     => count($matchesOut),
            'remote_errs' => count($remoteErrors),
            'duration_ms' => (int)((microtime(true) - $startedAt) * 1000),
        ], JSON_UNESCAPED_UNICODE)
    );
} catch (Throwable $e) {}

/* ═══════════════════════════════════════════════════════════════
   5. Final response
   ═══════════════════════════════════════════════════════════════ */
fc_respond([
    'ok'             => true,
    'verdict'        => $verdict,
    'reason_code'    => $reasonCode,
    'reason_ar'      => $reasonAr,
    'matches'        => $matchesOut,
    'remote_errors'  => $remoteErrors,
    'checked_at'     => date('c'),
    'request_id'     => $requestId,
    'client'         => $client,
    'duration_ms'    => (int)((microtime(true) - $startedAt) * 1000),
]);
