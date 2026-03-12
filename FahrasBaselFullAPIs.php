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
    case 'debug_dirs':
        header('Content-Type: application/json; charset=UTF-8');
        $result = [];

        $debugTables = ['attachment ', 'CUST_DOC'];
        foreach ($debugTables as $tbl) {
            $colSql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? ORDER BY ORDINAL_POSITION";
            $colStmt = sqlsrv_query($conn, $colSql, [trim($tbl)]);
            if (!$colStmt) {
                $colStmt = sqlsrv_query($conn, $colSql, [$tbl]);
            }
            $cols = [];
            if ($colStmt) {
                while ($c = sqlsrv_fetch_array($colStmt, SQLSRV_FETCH_ASSOC)) {
                    $cols[] = $c['COLUMN_NAME'] . ' (' . $c['DATA_TYPE'] . ($c['CHARACTER_MAXIMUM_LENGTH'] ? ':' . $c['CHARACTER_MAXIMUM_LENGTH'] : '') . ')';
                }
                sqlsrv_free_stmt($colStmt);
            }
            $result['table_' . trim($tbl)] = $cols;
        }

        $clientName = trim($_GET['client_name'] ?? '');
        if ($clientName !== '') {
            $words = preg_split('/\s+/u', $clientName);
            $words = array_filter($words, function($w) { return mb_strlen($w) > 0; });
            $conds = [];
            $params = [];
            foreach ($words as $w) {
                $conds[] = "m.name1 LIKE ?";
                $params[] = '%' . $w . '%';
            }
            $findSql = "SELECT TOP 5 m.contract_num, m.name1 FROM main_cust m WHERE " . implode(' AND ', $conds);
            $findStmt = sqlsrv_query($conn, $findSql, $params);
            $result['clients'] = [];
            if ($findStmt) {
                while ($r2 = sqlsrv_fetch_array($findStmt, SQLSRV_FETCH_ASSOC)) {
                    $cid = $r2['contract_num'];
                    $client = ['id' => $cid, 'name' => $r2['name1']];

                    $attSql = "SELECT TOP 10 * FROM [attachment ] WHERE cust_num = ? OR cont_num = ?";
                    $attStmt = sqlsrv_query($conn, $attSql, [$cid, $cid]);
                    $client['attachments'] = [];
                    if ($attStmt) {
                        while ($a = sqlsrv_fetch_array($attStmt, SQLSRV_FETCH_ASSOC)) {
                            $row = [];
                            foreach ($a as $k => $v) {
                                if ($v instanceof DateTime) { $row[$k] = $v->format('Y-m-d h:i A'); }
                                elseif (is_string($v) && strlen($v) > 500) { $row[$k] = '[BLOB:' . strlen($v) . ']'; }
                                else { $row[$k] = $v; }
                            }
                            $client['attachments'][] = $row;
                        }
                        sqlsrv_free_stmt($attStmt);
                    } else {
                        $client['attachment_error'] = sqlsrv_errors();
                    }

                    $docSql = "SELECT TOP 10 * FROM [CUST_DOC] WHERE cust_num = ? OR contract_num = ?";
                    $docStmt = sqlsrv_query($conn, $docSql, [$cid, $cid]);
                    $client['cust_docs'] = [];
                    if ($docStmt) {
                        while ($d = sqlsrv_fetch_array($docStmt, SQLSRV_FETCH_ASSOC)) {
                            $row = [];
                            foreach ($d as $k => $v) {
                                if ($v instanceof DateTime) { $row[$k] = $v->format('Y-m-d h:i A'); }
                                elseif (is_string($v) && strlen($v) > 500) { $row[$k] = '[DATA:' . strlen($v) . ']'; }
                                else { $row[$k] = $v; }
                            }
                            $client['cust_docs'][] = $row;
                        }
                        sqlsrv_free_stmt($docStmt);
                    } else {
                        $client['cust_doc_error'] = sqlsrv_errors();
                    }

                    $result['clients'][] = $client;
                }
                sqlsrv_free_stmt($findStmt);
            }
        }

        $countSql = "SELECT COUNT(*) AS cnt FROM [attachment ]";
        $cStmt = sqlsrv_query($conn, $countSql);
        if ($cStmt) { $cr = sqlsrv_fetch_array($cStmt, SQLSRV_FETCH_ASSOC); $result['attachment_count'] = $cr['cnt']; sqlsrv_free_stmt($cStmt); }

        $sampleSql = "SELECT TOP 5 id, cust_num, cont_num, [file], type_file FROM [attachment ] ORDER BY id DESC";
        $sStmt = sqlsrv_query($conn, $sampleSql);
        $result['attachment_sample'] = [];
        if ($sStmt) { while ($s = sqlsrv_fetch_array($sStmt, SQLSRV_FETCH_ASSOC)) { $result['attachment_sample'][] = $s; } sqlsrv_free_stmt($sStmt); }

        $countDoc = "SELECT COUNT(*) AS cnt FROM [CUST_DOC]";
        $cStmt2 = sqlsrv_query($conn, $countDoc);
        if ($cStmt2) { $cr2 = sqlsrv_fetch_array($cStmt2, SQLSRV_FETCH_ASSOC); $result['cust_doc_count'] = $cr2['cnt']; sqlsrv_free_stmt($cStmt2); }

        $sampleDoc = "SELECT TOP 3 case_num, cust_num, contract_num, doc_type, CAST(LINK_PHOTO AS NVARCHAR(500)) AS photo_xml FROM [CUST_DOC] WHERE LINK_PHOTO IS NOT NULL";
        $sStmt2 = sqlsrv_query($conn, $sampleDoc);
        $result['cust_doc_sample'] = [];
        if ($sStmt2) { while ($s2 = sqlsrv_fetch_array($sStmt2, SQLSRV_FETCH_ASSOC)) { $result['cust_doc_sample'][] = $s2; } sqlsrv_free_stmt($sStmt2); }

        $mcCols = "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='main_cust' ORDER BY ORDINAL_POSITION";
        $mcStmt = sqlsrv_query($conn, $mcCols);
        $result['main_cust_columns'] = [];
        if ($mcStmt) { while ($mc = sqlsrv_fetch_array($mcStmt, SQLSRV_FETCH_ASSOC)) { $result['main_cust_columns'][] = $mc['COLUMN_NAME'] . '(' . $mc['DATA_TYPE'] . ')'; } sqlsrv_free_stmt($mcStmt); }

        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
        return $val->format('Y-m-d h:i A');
    }
    $str = trim((string)$val);
    if ($str === '') return null;
    try {
        $dt = new DateTime($str);
        return $dt->format('Y-m-d h:i A');
    } catch (Exception $e) {
        return $str;
    }
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
        $words = preg_split('/\s+/u', trim($search));
        $words = array_filter($words, function($w) { return mb_strlen($w) > 0; });

        if (count($words) > 1) {
            $nameConditions = [];
            foreach ($words as $w) {
                $nameConditions[] = "m.name1 LIKE ?";
                $params[] = '%' . $w . '%';
            }
            $nameSql = '(' . implode(' AND ', $nameConditions) . ')';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $sql = clientsBaseQuery('TOP 20',
                "WHERE {$nameSql}
                    OR CAST(m.num1 AS NVARCHAR(50)) LIKE ?
                    OR CAST(m.contract_num AS NVARCHAR(50)) LIKE ?"
            );
        } else {
            $pattern = '%' . $search . '%';
            $params  = [$pattern, $pattern, $pattern];
            $sql = clientsBaseQuery('TOP 20',
                "WHERE m.name1 LIKE ?
                    OR CAST(m.num1 AS NVARCHAR(50)) LIKE ?
                    OR CAST(m.contract_num AS NVARCHAR(50)) LIKE ?"
            );
        }
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

    $contractNum = (int)($_GET['client'] ?? 0);
    if ($contractNum === 0) {
        echo '<p style="text-align:center;color:#999;">لا توجد مرفقات</p>';
        return;
    }

    $custStmt = sqlsrv_query($conn,
        "SELECT num_cust, id_image_front, id_image_back, customer_photo FROM main_cust WHERE contract_num = ?",
        [$contractNum]);
    $numCust = null;
    $inlineImages = [];
    if ($custStmt) {
        $custRow = sqlsrv_fetch_array($custStmt, SQLSRV_FETCH_ASSOC);
        if ($custRow) {
            $numCust = $custRow['num_cust'];
            foreach (['id_image_front' => 'صورة الهوية - أمام', 'id_image_back' => 'صورة الهوية - خلف', 'customer_photo' => 'صورة العميل'] as $col => $label) {
                $val = trim($custRow[$col] ?? '');
                if ($val !== '') $inlineImages[] = ['label' => $label, 'path' => $val];
            }
        }
        sqlsrv_free_stmt($custStmt);
    }

    if ($numCust === null) {
        echo '<p style="text-align:center;color:#999;padding:20px;">لا توجد مرفقات لهذا العميل</p>';
        return;
    }

    $found = [];

    $attSql = "SELECT [file], type_file, update_date FROM [attachment ] WHERE cust_num = ? OR cont_num = ?";
    $attStmt = sqlsrv_query($conn, $attSql, [$numCust, $contractNum]);
    if ($attStmt) {
        while ($row = sqlsrv_fetch_array($attStmt, SQLSRV_FETCH_ASSOC)) {
            $fileName = trim($row['file'] ?? '');
            if ($fileName === '') continue;
            $typeFile = trim($row['type_file'] ?? '');
            $date = ($row['update_date'] instanceof DateTime) ? $row['update_date']->format('Y-m-d') : '';
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $found[] = ['name' => $fileName, 'type' => $typeFile, 'date' => $date, 'ext' => $ext, 'source' => 'file'];
        }
        sqlsrv_free_stmt($attStmt);
    }

    $docSql = "SELECT doc_type, CAST(LINK_PHOTO AS NVARCHAR(MAX)) AS photo_link, date_of_satus FROM [CUST_DOC] WHERE cust_num = ? OR contract_num = ?";
    $docStmt = sqlsrv_query($conn, $docSql, [$numCust, $contractNum]);
    if ($docStmt) {
        while ($row = sqlsrv_fetch_array($docStmt, SQLSRV_FETCH_ASSOC)) {
            $link = trim($row['photo_link'] ?? '');
            if ($link === '') continue;
            $docType = trim($row['doc_type'] ?? '');
            $date = ($row['date_of_satus'] instanceof DateTime) ? $row['date_of_satus']->format('Y-m-d') : '';
            $found[] = ['name' => $link, 'type' => $docType, 'date' => $date, 'ext' => 'link', 'source' => 'gdrive'];
        }
        sqlsrv_free_stmt($docStmt);
    }

    if (empty($found) && empty($inlineImages)) {
        echo '<p style="text-align:center;color:#999;padding:20px;">لا توجد مرفقات لهذا العميل</p>';
        return;
    }

    echo '<div style="padding:10px;">';

    $uploadsBase = 'https://bseel.com/admin/uploads/';

    if (!empty($inlineImages)) {
        echo '<h6 style="color:#2c3e50;margin-bottom:8px;">صور العميل</h6>';
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:15px;">';
        foreach ($inlineImages as $img) {
            $label = htmlspecialchars($img['label']);
            $path = $img['path'];
            $isUrl = (strpos($path, 'http') === 0);
            $url = $isUrl ? $path : $uploadsBase . rawurlencode($path);
            $urlSafe = htmlspecialchars($url);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg','jpeg','png','gif','bmp','webp']);
            if ($isImage) {
                echo "<div style='text-align:center'><a href=\"{$urlSafe}\" target=\"_blank\">"
                   . "<img src=\"{$urlSafe}\" style=\"max-width:200px;max-height:150px;border-radius:8px;border:1px solid #ddd;object-fit:cover;\" alt=\"{$label}\" onerror=\"this.parentElement.innerHTML='<span style=padding:20px;display:block;color:#999>{$label}</span>'\" />"
                   . "</a><div style='font-size:12px;color:#666;margin-top:4px'>{$label}</div></div>";
            } else {
                echo "<div style='text-align:center'><a href=\"{$urlSafe}\" target=\"_blank\" style='display:inline-flex;align-items:center;gap:6px;padding:10px 16px;background:#2c3e50;color:#ecf0f1;border-radius:8px;text-decoration:none;'>"
                   . "<i class='fa fa-file'></i> {$label}</a></div>";
            }
        }
        echo '</div>';
    }

    if (!empty($found)) {
        echo '<h6 style="color:#2c3e50;margin-bottom:8px;">المرفقات والمستندات (' . count($found) . ')</h6>';
        echo '<table class="table table-hover table-bordered table-striped" style="font-size:13px;">';
        echo '<thead><tr><th>#</th><th>النوع</th><th>الملف</th><th>التاريخ</th></tr></thead><tbody>';
        $n = 0;
        foreach ($found as $f) {
            $n++;
            $type = htmlspecialchars($f['type']);
            $date = htmlspecialchars($f['date']);
            if ($f['source'] === 'gdrive') {
                $url = htmlspecialchars($f['name']);
                $fileCell = "<a href=\"{$url}\" target=\"_blank\" style=\"color:#1a73e8;\">فتح المستند <i class='fa fa-external-link'></i></a>";
            } else {
                $name = htmlspecialchars($f['name']);
                $fileUrl = htmlspecialchars($uploadsBase . rawurlencode($f['name']));
                $isImg = in_array($f['ext'], ['jpg','jpeg','png','gif','bmp','webp']);
                if ($isImg) {
                    $fileCell = "<a href=\"{$fileUrl}\" target=\"_blank\" style=\"color:#1a73e8;\">{$name} <i class='fa fa-image'></i></a>";
                } else {
                    $fileCell = "<a href=\"{$fileUrl}\" target=\"_blank\" style=\"color:#1a73e8;\">{$name} <i class='fa fa-download'></i></a>";
                }
            }
            echo "<tr><td>{$n}</td><td>{$type}</td><td>{$fileCell}</td><td>{$date}</td></tr>";
        }
        echo '</tbody></table>';
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

    $sql = "
        SELECT TOP 50
            w.[المعرف]   AS id,
            w.job         AS name,
            w.work_phone,
            w.work_phone2,
            w.work_phone3,
            CAST(w.address_work  AS NVARCHAR(MAX)) AS address_work,
            w.[e-mail]    AS email,
            CAST(w.website       AS NVARCHAR(MAX)) AS website,
            CAST(w.location      AS NVARCHAR(MAX)) AS location,
            w.free_days,
            w.time_work,
            CAST(w.notes         AS NVARCHAR(MAX)) AS notes,
            CAST(w.type_of_work  AS NVARCHAR(MAX)) AS type_of_work,
            (SELECT COUNT(*) FROM main_cust mc WHERE mc.job = w.job) AS customers_count
        FROM [work] w
        WHERE w.job LIKE ?
        ORDER BY w.[المعرف]
    ";

    $stmt = sqlsrv_query($conn, $sql, [$pattern]);
    if ($stmt === false) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        return;
    }

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $name = trim($row['name'] ?? '');
        if ($name === '') continue;

        $item = [
            'id'              => (int)$row['id'],
            'name'            => $name,
            'customers_count' => (int)($row['customers_count'] ?? 0),
            'type'            => trim($row['type_of_work'] ?? ''),
            'status'          => 1,
            'address'         => trim($row['address_work'] ?? ''),
            'email'           => trim($row['email'] ?? ''),
            'website'         => trim($row['website'] ?? ''),
            'notes'           => trim($row['notes'] ?? ''),
        ];

        $phones = [];
        if (!empty($row['work_phone']))  $phones[] = ['phone' => (string)$row['work_phone'],  'type' => 'رئيسي'];
        if (!empty($row['work_phone2'])) $phones[] = ['phone' => (string)$row['work_phone2'], 'type' => 'ثانوي'];
        if (!empty($row['work_phone3'])) $phones[] = ['phone' => (string)$row['work_phone3'], 'type' => 'ثالث'];
        $item['phones'] = $phones;

        $loc = trim($row['location'] ?? '');
        if ($loc !== '') $item['map_url'] = $loc;

        if (!empty($row['time_work']))  $item['working_hours_text'] = trim($row['time_work']);
        if (!empty($row['free_days']))  $item['free_days']          = trim($row['free_days']);

        $item['working_hours'] = [];

        $data[] = $item;
    }
    sqlsrv_free_stmt($stmt);

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}
