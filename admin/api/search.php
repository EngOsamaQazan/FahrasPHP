<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  Fahras Search API — for Tayseer integration (by-name lookup)
 * ═══════════════════════════════════════════════════════════════════
 *
 *  Used by Tayseer's wizard "بحث بالاسم في الفهرس" modal: returns the
 *  raw candidate list across local + 6 remote sources (no verdict).
 *
 *  Endpoint:
 *    GET /admin/api/search.php?token=SECRET&q=محمد أحمد&client=tayseer&limit=20
 *
 *  Response:
 *    {
 *      "ok":      true,
 *      "results": [ { name, national_id, phone, account, source,
 *                     remaining_amount, status, contract_id, created_on } ],
 *      "remote_errors": [ {source,label,error} ],
 *      "request_id": "uuid"
 *    }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

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

$TOKENS = [
    'tayseer' => getenv('FAHRAS_TOKEN_TAYSEER') ?: 'tayseer_fahras_2026_change_me',
];

function fs_respond(array $payload, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fs_uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function fs_input(string $key, $default = null)
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

function fs_clean_str($v, int $max = 255): string
{
    $v = (string)$v;
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return mb_substr(trim($v ?? ''), 0, $max, 'UTF-8');
}

$token  = fs_clean_str(fs_input('token', ''));
$client = fs_clean_str(fs_input('client', 'tayseer'), 32);

if ($token === '' || empty($TOKENS[$client] ?? null) || !hash_equals($TOKENS[$client], $token)) {
    fs_respond(['ok' => false, 'error' => 'unauthorized'], 403);
}

$q     = fs_clean_str(fs_input('q', ''), 250);
$limit = max(1, min((int)fs_input('limit', 20), 50));

if ($q === '' || mb_strlen($q, 'UTF-8') < 3) {
    fs_respond([
        'ok'    => false,
        'error' => 'short_query',
        'hint'  => 'Provide q with at least 3 characters.',
    ], 400);
}

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';
    require_once __DIR__ . '/../../includes/violation_engine.php';
} catch (Throwable $e) {
    fs_respond(['ok' => false, 'error' => 'bootstrap_failed', 'detail' => $e->getMessage()], 500);
}

$requestId  = fs_uuid_v4();
$startedAt  = microtime(true);
$results    = [];
$remoteErrs = [];

try {
    global $db;
    $like = '%' . $q . '%';
    $stmt = $db->prepare("SELECT *, 'local' AS _source FROM clients WHERE name LIKE :q OR national_id LIKE :q OR phone LIKE :q LIMIT :lim");
    $stmt->bindValue(':q',   $like);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $results = array_merge($results, $stmt->fetchAll() ?: []);
} catch (Throwable $e) {}

$enc = urlencode($q);
$remoteApis = [
    'zajal' => ['url' => 'https://zajal.cc/fahras-api.php?token=354afdf5357c&search=' . $enc, 'label' => 'زجل'],
    'jadal' => ['url' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=jadal&search=' . $enc, 'label' => 'جدل'],
    'namaa' => ['url' => 'https://jadal.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=erp&search=' . $enc, 'label' => 'نماء'],
    'bseel' => ['url' => 'https://bseel.com/FahrasBaselFullAPIs.php?token=bseel_fahras_2024&search=' . $enc, 'label' => 'بسيل'],
    'watar' => ['url' => 'https://watar.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=watar&search=' . $enc, 'label' => 'وتر'],
    'majd'  => ['url' => 'https://majd.aqssat.co/fahras/api.php?token=b83ba7a49b72&db=majd&search=' . $enc, 'label' => 'عالم المجد'],
];
$urls = [];
foreach ($remoteApis as $k => $v) $urls[$k] = $v['url'];
try { $multi = curl_multi_load($urls); } catch (Throwable $e) { $multi = []; }

foreach ($remoteApis as $k => $api) {
    $body = $multi[$k]['body']  ?? null;
    $err  = $multi[$k]['error'] ?? null;
    if ($err || $body === null || trim($body) === '') {
        $remoteErrs[] = ['source' => $k, 'label' => $api['label'], 'error' => $err ?: 'no_response'];
        continue;
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $remoteErrs[] = ['source' => $k, 'label' => $api['label'], 'error' => 'invalid_json'];
        continue;
    }
    if (isset($decoded['data']) && is_array($decoded['data'])) $decoded = $decoded['data'];
    if (isset($decoded['error'])) {
        $remoteErrs[] = ['source' => $k, 'label' => $api['label'], 'error' => (string)$decoded['error']];
        continue;
    }
    $decoded = array_slice($decoded, 0, $limit);
    foreach ($decoded as &$r) { $r['_source'] = $k; }
    unset($r);
    $results = array_merge($results, $decoded);
}

$out = [];
foreach ($results as $r) {
    $out[] = [
        'account'          => resolveAccountName($r),
        'source'           => $r['_source'] ?? 'local',
        'name'             => $r['name'] ?? '',
        'national_id'      => $r['national_id'] ?? '',
        'phone'            => $r['phone'] ?? '',
        'remaining_amount' => isset($r['remaining_amount']) ? (float)$r['remaining_amount'] : null,
        'status'           => $r['status'] ?? '',
        'contract_id'      => $r['id'] ?? $r['cid'] ?? null,
        'created_on'       => getEffectiveDate($r),
    ];
}

try {
    log_activity('api_search_' . $client, 'tayseer', null, json_encode([
        'request_id' => $requestId,
        'q' => $q,
        'count' => count($out),
        'duration_ms' => (int)((microtime(true) - $startedAt) * 1000),
    ], JSON_UNESCAPED_UNICODE));
} catch (Throwable $e) {}

fs_respond([
    'ok'           => true,
    'results'      => $out,
    'remote_errors'=> $remoteErrs,
    'request_id'   => $requestId,
    'client'       => $client,
    'duration_ms'  => (int)((microtime(true) - $startedAt) * 1000),
]);
