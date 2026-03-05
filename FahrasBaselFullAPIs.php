<?php
/*
 * ═══════════════════════════════════════════════════════
 *  Bseel Unified API for Fahras System
 *  Version: 2.0
 * ═══════════════════════════════════════════════════════
 *
 *  Endpoints:
 *    ?token=XXX&search=QUERY                     → Search clients
 *    ?token=XXX&action=bulk_export               → Export all clients
 *    ?token=XXX&action=people&client=ID           → Phone numbers & references
 *    ?token=XXX&action=parties&contract=ID        → Contract parties
 *    ?token=XXX&action=attachments&client=ID      → Client attachments
 *    ?token=XXX&action=jobs&search=QUERY          → Search workplaces
 */

// ═══════════════════════════════════════
// CORS
// ═══════════════════════════════════════
$allowed_origins = [
    'https://fahras.aqssat.co',
    'https://fahras.x10.ltd',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ═══════════════════════════════════════
// Token Authentication
// ═══════════════════════════════════════
define('API_TOKEN', 'bseel_fahras_2024');

$token = $_GET['token'] ?? $_REQUEST['token'] ?? '';
if ($token !== API_TOKEN) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════
// Database Connection (SQL Server)
// ═══════════════════════════════════════
$conn = sqlsrv_connect("SQL5110.site4now.net", [
    "UID"          => "db_a99895_bseel2023_admin",
    "PWD"          => "Am@23704660",
    "Database"     => "db_a99895_bseel2023",
    "CharacterSet" => "UTF-8"
]);

if ($conn === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => 'Connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════
// Router
// ═══════════════════════════════════════
$action = trim($_GET['action'] ?? '');
$search = trim($_GET['search'] ?? '');

switch ($action) {
    case 'people':
        handlePeople($conn);
        break;
    case 'parties':
        handleParties($conn);
        break;
    case 'attachments':
        handleAttachments($conn);
        break;
    case 'jobs':
        handleJobs($conn, $search);
        break;
    case 'bulk_export':
        handleSearch($conn, '', true);
        break;
    default:
        if ($search !== '') {
            handleSearch($conn, $search, false);
        } else {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['data' => []], JSON_UNESCAPED_UNICODE);
        }
        break;
}

sqlsrv_close($conn);
exit;


/* ═══════════════════════════════════════════════════════════════
   HELPER
   ═══════════════════════════════════════════════════════════════ */

function formatDate($val) {
    if ($val === null) return null;
    if ($val instanceof DateTimeInterface) {
        return $val->format('Y-m-d H:i:s');
    }
    $str = trim((string)$val);
    return $str !== '' ? $str : null;
}

function clientsBaseQuery($topClause, $whereClause) {
    return "
        SELECT {$topClause}
            m.contract_num,
            m.name1,
            m.num1,
            m.job,
            m.HOME_ADRESS,
            m.JOB_LOCATION,
            f.created_date,
            f.sell_date,
            f.court,
            f.Finished,
            f.total_original,
            ISNULL(pay.paid_sum, 0) AS paid_sum,
            ph.first_phone
        FROM main_cust m
        LEFT JOIN (
            SELECT
                [المعرف],
                MAX(created_on) AS created_date,
                MAX(date1) AS sell_date,
                MAX(CAST(court AS int)) AS court,
                MAX(CAST(Finished AS int)) AS Finished,
                SUM(ISNULL(many,0)) + SUM(ISNULL(court_tax,0))
                + SUM(ISNULL(tax_court,0)) + SUM(ISNULL(Car_tax,0)) AS total_original
            FROM finance
            GROUP BY [المعرف]
        ) f ON f.[المعرف] = m.contract_num
        LEFT JOIN (
            SELECT num1, SUM(ISNULL(Creditor,0)) AS paid_sum
            FROM many
            GROUP BY num1
        ) pay ON pay.num1 = m.contract_num
        OUTER APPLY (
            SELECT TOP 1 phone1 AS first_phone
            FROM phones
            WHERE conract_num = m.contract_num
        ) ph
        {$whereClause}
    ";
}

function buildClientRow($row) {
    $remaining = ($row['total_original'] ?? 0) - ($row['paid_sum'] ?? 0);
    $createdOn = formatDate($row['created_date'] ?? null);
    $sellDate  = formatDate($row['sell_date'] ?? null);

    return [
        'account'          => 'بسيل',
        'id'               => $row['contract_num'],
        'cid'              => $row['contract_num'],
        'name'             => $row['name1'] ?? '',
        'national_id'      => $row['num1'] ?? '',
        'created_on'       => $createdOn ?? $sellDate,
        'sell_date'        => $sellDate,
        'remaining_amount' => $remaining,
        'status'           => (!isset($row['Finished']) || $row['Finished'] != 1) ? 'نشط' : 'منتهي',
        'phone'            => $row['first_phone'] ?? '',
        'party_type'       => 'عميل',
        'work'             => $row['job'] ?? '',
        'home_address'     => $row['HOME_ADRESS'] ?? '',
        'work_address'     => $row['JOB_LOCATION'] ?? '',
        'court_status'     => (!isset($row['court']) || $row['court'] != 1) ? 'لا يوجد' : 'مشتكى عليه',
        'attachments'      => 1,
    ];
}


/* ═══════════════════════════════════════════════════════════════
   1. SEARCH + BULK EXPORT
   ═══════════════════════════════════════════════════════════════ */

function handleSearch($conn, $search, $isBulk) {
    header('Content-Type: application/json; charset=UTF-8');

    $params = [];

    if ($isBulk) {
        $sql = clientsBaseQuery('', '');
    } else {
        $pattern = '%' . $search . '%';
        $params  = [$pattern, $pattern, $pattern];
        $sql = clientsBaseQuery('TOP 20',
            "WHERE m.name1 LIKE ?
                OR CAST(m.num1 AS NVARCHAR(50)) LIKE ?
                OR CAST(m.contract_num AS NVARCHAR(50)) LIKE ?"
        );
    }

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        echo json_encode(['error' => 'Query failed', 'details' => sqlsrv_errors()], JSON_UNESCAPED_UNICODE);
        return;
    }

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = buildClientRow($row);
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE);
}


/* ═══════════════════════════════════════════════════════════════
   2. PEOPLE – أرقام هواتف المعرّفين
   ═══════════════════════════════════════════════════════════════ */

function handlePeople($conn) {
    header('Content-Type: text/html; charset=UTF-8');

    $clientId = (int)($_GET['client'] ?? 0);
    if ($clientId === 0) {
        echo '<p style="text-align:center;color:#999;">لا توجد بيانات</p>';
        return;
    }

    $sql  = "SELECT * FROM phones WHERE conract_num = ?";
    $stmt = sqlsrv_query($conn, $sql, [$clientId]);

    if ($stmt === false) {
        echo '<p style="text-align:center;color:#e74c3c;">خطأ في جلب البيانات</p>';
        return;
    }

    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC)) {
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);

    if (empty($rows)) {
        echo '<p style="text-align:center;color:#999;">لا توجد أرقام هواتف</p>';
        return;
    }

    echo '<table class="table table-hover table-bordered table-striped">';
    echo '<thead><tr><th>الهاتف</th><th>صلة القرابة</th></tr></thead>';
    echo '<tbody>';
    foreach ($rows as $row) {
        $phone    = htmlspecialchars($row[3] ?? '');
        $relation = htmlspecialchars($row[4] ?? '');
        echo "<tr><td>{$phone}</td><td>{$relation}</td></tr>";
    }
    echo '</tbody></table>';
}


/* ═══════════════════════════════════════════════════════════════
   3. PARTIES – أطراف العقد (كفلاء وشهود)
   ═══════════════════════════════════════════════════════════════ */

function handleParties($conn) {
    header('Content-Type: text/html; charset=UTF-8');

    $contractId = (int)($_GET['contract'] ?? 0);
    if ($contractId === 0) {
        http_response_code(400);
        echo 'Missing required parameter: contract';
        return;
    }

    $sql = "
        SELECT m.*, p.phones_list
        FROM main_cust m
        OUTER APPLY (
            SELECT STRING_AGG(phone1, '||') AS phones_list
            FROM phones
            WHERE conract_num = m.contract_num
        ) p
        WHERE m.contract_num = ?
    ";
    $stmt = sqlsrv_query($conn, $sql, [$contractId]);

    $rows = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        sqlsrv_free_stmt($stmt);
    }

    if (empty($rows)) {
        $sqlFallback = "SELECT * FROM main_cust WHERE contract_num = ?";
        $stmtFb = sqlsrv_query($conn, $sqlFallback, [$contractId]);
        if ($stmtFb !== false) {
            while ($row = sqlsrv_fetch_array($stmtFb, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
            sqlsrv_free_stmt($stmtFb);
        }
    }

    if (empty($rows)) {
        echo '<p style="text-align:center;color:#999;">لا توجد بيانات أطراف لهذا العقد</p>';
        return;
    }

    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered table-striped align-middle mb-0">';
    echo '<tbody>';

    $n = 0;
    foreach ($rows as $row) {
        $n++;
        $work   = $row['job'] ?? '';
        $phones = isset($row['phones_list']) ? explode('||', $row['phones_list']) : [];

        echo '<tr class="table-dark"><th colspan="2" class="text-nowrap">طرف رقم ' . $n . '</th></tr>';

        $fields = [
            'اسم العميل'    => $row['name1'] ?? '',
            'الرقم الوطني'   => $row['num1'] ?? '',
            'رقم الهاتف 1'  => $phones[0] ?? '',
            'رقم الهاتف 2'  => $phones[1] ?? '',
            'الوظيفة'        => $work,
            'العنوان'        => $row['HOME_ADRESS'] ?? '',
            'مكان العمل'     => $row['JOB_LOCATION'] ?? '',
        ];

        foreach ($fields as $label => $value) {
            $val = htmlspecialchars(trim((string)$value));
            if ($val === '') $val = '<span class="text-muted">-</span>';
            echo "<tr><th class=\"text-nowrap\">{$label}</th><td>{$val}</td></tr>";
        }
    }

    echo '</tbody></table></div>';
}


/* ═══════════════════════════════════════════════════════════════
   4. ATTACHMENTS – مرفقات وصور العميل
   ═══════════════════════════════════════════════════════════════ */

function handleAttachments($conn) {
    header('Content-Type: text/html; charset=UTF-8');

    $clientId = (int)($_GET['client'] ?? 0);
    if ($clientId === 0) {
        echo '<p style="text-align:center;color:#999;">لا توجد مرفقات</p>';
        return;
    }

    $exts     = ['jpg','jpeg','png','gif','bmp','webp','pdf'];
    $found    = [];
    $baseHost = 'https://bseel.com';

    $searchDirs = [
        __DIR__ . '/uploads/' . $clientId          => $baseHost . '/uploads/' . $clientId,
        __DIR__ . '/uploads/contracts/' . $clientId => $baseHost . '/uploads/contracts/' . $clientId,
        __DIR__ . '/uploads/clients/' . $clientId   => $baseHost . '/uploads/clients/' . $clientId,
    ];

    foreach ($searchDirs as $dir => $urlBase) {
        if (!is_dir($dir)) continue;
        $iterator = new DirectoryIterator($dir);
        foreach ($iterator as $file) {
            if ($file->isDot() || !$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (in_array($ext, $exts, true)) {
                $found[] = [
                    'name' => $file->getFilename(),
                    'ext'  => $ext,
                    'url'  => $urlBase . '/' . rawurlencode($file->getFilename()),
                ];
            }
        }
    }

    if (empty($found)) {
        echo '<p style="text-align:center;color:#999;padding:20px;">لا توجد مرفقات لهذا العميل</p>';
        return;
    }

    echo '<div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;padding:10px;">';
    foreach ($found as $f) {
        $url  = htmlspecialchars($f['url']);
        $name = htmlspecialchars($f['name']);
        if ($f['ext'] === 'pdf') {
            echo "<a href=\"{$url}\" target=\"_blank\" "
               . "style=\"display:inline-flex;align-items:center;gap:6px;padding:10px 16px;"
               . "background:#2c3e50;color:#ecf0f1;border-radius:8px;text-decoration:none;\">"
               . "<i class=\"fa fa-file-pdf\"></i> {$name}</a>";
        } else {
            echo "<a href=\"{$url}\" target=\"_blank\">"
               . "<img src=\"{$url}\" "
               . "style=\"max-width:280px;max-height:200px;border-radius:8px;"
               . "border:1px solid #ddd;object-fit:cover;\" alt=\"{$name}\" />"
               . "</a>";
        }
    }
    echo '</div>';
}


/* ═══════════════════════════════════════════════════════════════
   5. JOBS – بحث في جهات عمل العملاء
   ═══════════════════════════════════════════════════════════════ */

function handleJobs($conn, $search) {
    header('Content-Type: application/json; charset=UTF-8');

    if ($search === '') {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        return;
    }

    $pattern = '%' . $search . '%';
    $sql = clientsBaseQuery('TOP 50',
        "WHERE m.job LIKE ?"
    );

    $stmt = sqlsrv_query($conn, $sql, [$pattern]);
    if ($stmt === false) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        return;
    }

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $r = buildClientRow($row);
        $data[] = [
            'id'               => $r['id'],
            'name'             => $r['name'],
            'national_id'      => $r['national_id'],
            'work'             => $r['work'],
            'work_address'     => $r['work_address'],
            'phone'            => $r['phone'],
            'created_on'       => $r['created_on'],
            'sell_date'        => $r['sell_date'],
            'remaining_amount' => $r['remaining_amount'],
            'status'           => $r['status'],
        ];
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
