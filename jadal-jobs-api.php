<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * Jadal/Namaa – Jobs API for Fahras
 * ═══════════════════════════════════════════════════════════════
 *
 * يُرفع على سيرفر جدل ويحل محل الملف الحالي:
 *   jadal.aqssat.co/fahras/jobs.php
 *
 * ⚠️ قبل الرفع:
 *   1. افتح api.php على سيرفر جدل وانسخ بيانات الاتصال (host, user, pass)
 *   2. عدّل $DB_CREDENTIALS أدناه بنفس القيم
 *   3. تحقق من اسم الجدول ($TABLE) واسم عمود الوظيفة ($WORK_COL)
 *
 * الاستخدام:
 *   jobs.php?token=b83ba7a49b72&db=jadal&search=جيش
 *   jobs.php?token=b83ba7a49b72&db=erp&search=وزارة
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// ─── CORS ───
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://fahras.aqssat.co', 'https://fahras.x10.ltd'];
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ─── Authentication ───
$TOKEN = 'b83ba7a49b72';
if (($_GET['token'] ?? '') !== $TOKEN) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE));
}

// ══════════════════════════════════════════════════════════════════
// ⚠️  بيانات الاتصال – انسخها من api.php على نفس السيرفر
// ══════════════════════════════════════════════════════════════════
$DB_CREDENTIALS = [
    'jadal' => [
        'host' => 'localhost',
        'name' => 'jadal',       // اسم قاعدة البيانات
        'user' => 'root',        // ⬅️ عدّل هذا
        'pass' => '',            // ⬅️ عدّل هذا
    ],
    'erp' => [
        'host' => 'localhost',
        'name' => 'erp',         // اسم قاعدة البيانات
        'user' => 'root',        // ⬅️ عدّل هذا
        'pass' => '',            // ⬅️ عدّل هذا
    ],
];

// ══════════════════════════════════════════════════════════════════
// ⚠️  اسم الجدول وعمود الوظيفة – تحقق من api.php
// ══════════════════════════════════════════════════════════════════
$TABLE    = 'clients';  // اسم الجدول الرئيسي (clients / contracts / cases)
$WORK_COL = 'work';     // عمود جهة العمل
$NAME_COL = 'name';     // عمود اسم العميل

// ─── Account label per database ───
$DB_LABELS = ['jadal' => 'جدل', 'erp' => 'نماء'];

// ─── Parameters ───
$dbKey  = $_GET['db'] ?? 'jadal';
$search = trim($_GET['search'] ?? '');

if (!isset($DB_CREDENTIALS[$dbKey])) {
    die(json_encode(['error' => 'invalid db'], JSON_UNESCAPED_UNICODE));
}

$cred = $DB_CREDENTIALS[$dbKey];

// ─── Database connection ───
try {
    $pdo = new PDO(
        "mysql:host={$cred['host']};dbname={$cred['name']};charset=utf8mb4",
        $cred['user'],
        $cred['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'database connection failed'], JSON_UNESCAPED_UNICODE));
}

if ($search === '') {
    echo '[]';
    exit;
}

// ─── Arabic normalization in SQL ───
$normSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$WORK_COL}, 'ة', 'ه'), 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), 'ى', 'ي')";

// ─── Arabic normalization in PHP ───
$searchNorm = str_replace(['أ','إ','آ'], 'ا', $search);
$searchNorm = str_replace('ة', 'ه', $searchNorm);
$searchNorm = str_replace('ى', 'ي', $searchNorm);
$searchNorm = mb_strtolower($searchNorm, 'UTF-8');

$label = $DB_LABELS[$dbKey] ?? $dbKey;

try {
    $sql = "
        SELECT
            {$WORK_COL}  AS workplace,
            COUNT(*)     AS cnt
        FROM {$TABLE}
        WHERE {$WORK_COL} IS NOT NULL
          AND {$WORK_COL} != ''
          AND LOWER({$normSql}) LIKE :q
        GROUP BY {$WORK_COL}
        ORDER BY cnt DESC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':q' => "%{$searchNorm}%"]);
    $rows = $stmt->fetchAll();

    $data = [];
    foreach ($rows as $i => $row) {
        $data[] = [
            'id'              => $i + 1,
            'name'            => $row['workplace'],
            'customers_count' => (int) $row['cnt'],
            'companies'       => $label,
            'type'            => '',
            'status'          => 1,
        ];
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
