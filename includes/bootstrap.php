<?php
/**
 * Fahras System - Central Bootstrap
 * All pages should include this file for session, DB, auth, and helpers.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set("Asia/Amman");

require_once __DIR__ . '/smplPDO.php';

$db_host = 'localhost';
$db_name = 'fahras_db';
$db_user = getenv('FAHRAS_DB_USER') ?: 'root';
$db_pass = getenv('FAHRAS_DB_PASS') ?: '';

$db = new smplPDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);

// ─── Authentication ───────────────────────────────────────────────

function auth_login($username, $password) {
    global $db;

    $row = $db->get_row('users', ['username' => $username, 'active' => 1]);
    if (!$row) return false;

    $stored = $row['password'];
    $valid  = false;

    $isModernHash = (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$argon2'));

    if ($isModernHash) {
        $valid = password_verify($password, $stored);
    } else {
        $valid = ($stored === md5($password));
        if ($valid) {
            $db->update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $row['id']]);
        }
    }

    if (!$valid) return false;

    $token = bin2hex(random_bytes(32));
    $db->update('users', [
        'token'      => $token,
        'last_login' => date('Y-m-d H:i:s'),
    ], ['id' => $row['id']]);

    $_SESSION['user_id']  = $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['token']    = $token;

    setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
    setcookie('remember_user', $row['username'], time() + (86400 * 30), "/", "", false, true);

    return $row;
}

function auth_check() {
    global $db;

    if (!empty($_SESSION['user_id']) && !empty($_SESSION['token'])) {
        $count = $db->get_count('users', [
            'id'    => $_SESSION['user_id'],
            'token' => $_SESSION['token'],
            'active' => 1,
        ]);
        if ($count > 0) return true;
    }

    if (!empty($_COOKIE['remember_token']) && !empty($_COOKIE['remember_user'])) {
        $row = $db->get_row('users', [
            'username' => $_COOKIE['remember_user'],
            'token'    => $_COOKIE['remember_token'],
            'active'   => 1,
        ]);
        if ($row) {
            $_SESSION['user_id']  = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['token']    = $row['token'];
            return true;
        }
    }

    return false;
}

function auth_user() {
    global $db;
    if (empty($_SESSION['user_id'])) return [];
    $user = $db->get_row('users', ['id' => $_SESSION['user_id']]);
    return is_array($user) ? $user : [];
}

function auth_logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
    setcookie('remember_token', '', time() - 3600, "/");
    setcookie('remember_user', '', time() - 3600, "/");
    setcookie('username', '', time() - 3600, "/");
    setcookie('token', '', time() - 3600, "/");
    setcookie('language', '', time() - 3600, "/");
}

function require_auth() {
    if (!auth_check()) {
        header('Location: /admin/login');
        exit;
    }
}

// ─── CSRF Protection ──────────────────────────────────────────────

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals(csrf_token(), $token);
}

// ─── RBAC Permissions ─────────────────────────────────────────────

function _load_user_permissions() {
    $user = auth_user();
    $userId = (int)($user['id'] ?? 0);
    $roleId = (int)($user['role_id'] ?? 0);

    $cacheKey = $roleId . ':' . $userId;
    if (isset($_SESSION['_rbac_perms']) && isset($_SESSION['_rbac_cache_key'])
        && $_SESSION['_rbac_cache_key'] === $cacheKey) {
        return $_SESSION['_rbac_perms'];
    }

    global $db;
    $perms = [];

    if ($roleId > 0) {
        $stmt = $db->prepare(
            "SELECT p.module, p.action, p.name, 'role' AS source
             FROM role_has_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?"
        );
        $stmt->execute([$roleId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['module'] . '.' . $row['action'];
            $perms[$key] = true;
            $perms[$row['name']] = true;
        }
    }

    if ($userId > 0) {
        $stmt = $db->prepare(
            "SELECT p.module, p.action, p.name, 'direct' AS source
             FROM user_has_permissions uhp
             JOIN permissions p ON p.id = uhp.permission_id
             WHERE uhp.user_id = ?"
        );
        $stmt->execute([$userId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['module'] . '.' . $row['action'];
            $perms[$key] = true;
            $perms[$row['name']] = true;
        }
    }

    $_SESSION['_rbac_perms']     = $perms;
    $_SESSION['_rbac_cache_key'] = $cacheKey;
    return $perms;
}

function rbac_clear_cache() {
    unset($_SESSION['_rbac_perms'], $_SESSION['_rbac_cache_key']);
}

function user_can($module, $action = 'view') {
    $perms = _load_user_permissions();
    $key = $module . '.' . $action;
    return !empty($perms[$key]);
}

function user_role_name() {
    global $db;
    $user = auth_user();
    if (empty($user['role_id'])) return '';
    $role = $db->get_row('roles', ['id' => $user['role_id']]);
    return $role ? $role['name'] : '';
}

function require_permission($module, $action = 'view') {
    if (!user_can($module, $action)) {
        show_access_denied();
        exit;
    }
}

function show_access_denied() {
    http_response_code(403);
    $lang = $_COOKIE['language'] ?? 'ar';
    $title   = $lang === 'ar' ? 'غير مصرح' : 'Access Denied';
    $message = $lang === 'ar' ? 'ليس لديك صلاحية للوصول إلى هذه الصفحة.' : 'You do not have permission to access this page.';
    $back    = $lang === 'ar' ? 'العودة للرئيسية' : 'Back to Home';
    echo '<!DOCTYPE html><html dir="' . ($lang === 'ar' ? 'rtl' : 'ltr') . '"><head><meta charset="utf-8"><title>' . $title . '</title>';
    echo '<style>*{margin:0;padding:0;box-sizing:border-box}body{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0a1628,#1a2a4a,#0f2035);font-family:Tajawal,sans-serif;color:#fff}.card{text-align:center;padding:60px 40px;background:rgba(255,255,255,.06);border-radius:20px;border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(20px);max-width:460px}.icon{font-size:80px;margin-bottom:20px;opacity:.7}h1{font-size:28px;margin-bottom:12px;color:#ff6b6b}p{font-size:16px;color:rgba(255,255,255,.7);margin-bottom:30px}a{display:inline-block;padding:12px 32px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:10px;font-size:15px;transition:transform .2s}a:hover{transform:translateY(-2px)}</style></head>';
    echo '<body><div class="card"><div class="icon">🔒</div><h1>' . $title . '</h1><p>' . $message . '</p><a href="/admin/">' . $back . '</a></div></body></html>';
}

// ─── Legacy Compatibility Wrappers ────────────────────────────────

function has_role($required_roles) {
    $roleName = user_role_name();
    if (!$roleName) return false;
    if (!is_array($required_roles)) $required_roles = [$required_roles];

    $role_map = [
        'Administrator' => 'admin',
        'User'          => 'user',
    ];
    $normalized = $role_map[$roleName] ?? $roleName;
    return in_array($normalized, $required_roles);
}

function is_admin_role() {
    return user_can('users', 'view') && user_can('accounts', 'view');
}

function can_manage_violations() {
    return user_can('violations', 'manage');
}

function can_view_violations() {
    return user_can('violations', 'view');
}

function can_add_clients() {
    return user_can('clients', 'create');
}

// ─── Activity Logging ─────────────────────────────────────────────

function log_activity($action, $entity_type = null, $entity_id = null, $details = null) {
    global $db;
    try {
        $db->insert('activity_log', [
            'user_id'     => $_SESSION['user_id'] ?? 0,
            'action'      => $action,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'details'     => $details,
            'ip_address'  => getUserIP(),
        ]);
    } catch (Exception $e) {
        // table may not exist yet during migration
    }
}

// ─── Helper Functions ─────────────────────────────────────────────

function getUserIP() {
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    $client  = @$_SERVER['HTTP_CLIENT_IP'];
    $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
    $remote  = $_SERVER['REMOTE_ADDR'];

    if (filter_var($client, FILTER_VALIDATE_IP)) {
        return $client;
    } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
        return $forward;
    }
    return $remote;
}

function _e($text) {
    global $db, $user;
    static $cache = null;
    static $lang = null;

    if ($lang === null) {
        $lang = !empty($user['language']) ? $user['language'] : ($_COOKIE['language'] ?? 'ar');
    }

    if ($cache === null) {
        $cache = [];
        try {
            $rows = $db->prepare("SELECT text, ar, en FROM translate");
            $rows->execute();
            foreach ($rows->fetchAll() as $row) {
                $cache[$row['text']] = $row;
            }
        } catch (Exception $e) {}
    }

    if (isset($cache[$text])) {
        $val = $cache[$text][$lang] ?? '';
        return ($val !== '' && $val !== null) ? $val : $text;
    }

    try {
        $db->insert('translate', ['text' => $text]);
        $cache[$text] = ['text' => $text, 'ar' => '', 'en' => ''];
    } catch (Exception $e) {}

    return $text;
}

function curl_load($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        $result = curl_error($ch);
    }
    curl_close($ch);

    return $result;
}

/**
 * Execute multiple curl requests in parallel using curl_multi.
 * @param array $urls  Associative array [key => url]
 * @param int $batchSize  Max concurrent requests
 * @return array  [key => ['body' => string|null, 'error' => string|null, 'http_code' => int]]
 */
function curl_multi_load(array $urls, int $batchSize = 10): array {
    $results = [];
    $chunks = array_chunk($urls, $batchSize, true);

    foreach ($chunks as $chunk) {
        $mh = curl_multi_init();
        curl_multi_setopt($mh, CURLMOPT_MAXCONNECTS, $batchSize);
        $handles = [];

        foreach ($chunk as $key => $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.2);
        } while ($running > 0);

        foreach ($handles as $key => $ch) {
            $error = null;
            $body  = curl_multi_getcontent($ch);
            $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                $body  = null;
            }

            $results[$key] = [
                'body'      => $body,
                'error'     => $error,
                'http_code' => $code,
            ];

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
    }

    return $results;
}

function GUID() {
    if (function_exists('com_create_guid')) {
        return trim(com_create_guid(), '{}');
    }
    return sprintf(
        '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
        mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535),
        mt_rand(16384, 20479), mt_rand(32768, 49151),
        mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)
    );
}

// ─── Date Helpers ─────────────────────────────────────────────────

function formatTo12h($dateStr) {
    if (empty($dateStr)) return '';
    if ($dateStr instanceof DateTimeInterface) {
        return $dateStr->format('Y-m-d h:i A');
    }
    $dateStr = trim((string)$dateStr);
    if ($dateStr === '') return '';
    try {
        $dt = new DateTime($dateStr);
        return $dt->format('Y-m-d h:i A');
    } catch (Exception $e) {
        return $dateStr;
    }
}

// ─── Load Current User ────────────────────────────────────────────

$user = auth_user();

$current_page = basename($_SERVER['REQUEST_URI'], '?' . ($_SERVER['QUERY_STRING'] ?? ''));
if ($current_page == '?lang=ar' || $current_page == '?lang=en') {
    $current_page = '';
}

if (in_array(($_GET['lang'] ?? ''), ['ar', 'en']) && !empty($user['id'])) {
    $db->update('users', ['language' => $_GET['lang']], ['id' => $user['id']]);
    header('Location: /admin/' . $current_page);
    exit;
}
