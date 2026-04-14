<?php
$page_title = 'الأدوار والصلاحيات';
$token = 'mojeer';

require_once __DIR__ . '/../includes/bootstrap.php';
require_auth();

require_permission('roles', 'view');

$canCreate = user_can('roles', 'create');
$canEdit   = user_can('roles', 'edit');
$canDelete = user_can('roles', 'delete');

// ─── AJAX Handlers (before any HTML output) ──────────────────────────

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if (!csrf_verify() && $action !== 'get_roles' && $action !== 'get_permissions' && $action !== 'get_role') {
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        exit;
    }

    switch ($action) {
        case 'get_roles':
            $stmt = $db->prepare("SELECT r.*, 
                (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) as user_count,
                (SELECT COUNT(*) FROM role_has_permissions rp WHERE rp.role_id = r.id) as perm_count
                FROM roles r WHERE r.name != 'admin' ORDER BY r.id");
            $stmt->execute();
            echo json_encode(['success' => true, 'roles' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;

        case 'get_permissions':
            $stmt = $db->prepare("SELECT * FROM permissions ORDER BY sort_order, id");
            $stmt->execute();
            $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $modules = [];
            foreach ($perms as $p) {
                $modules[$p['module']]['permissions'][] = $p;
            }
            echo json_encode(['success' => true, 'modules' => $modules, 'permissions' => $perms]);
            exit;

        case 'get_role':
            $roleId = (int)($_GET['id'] ?? 0);
            $role = $db->get_row('roles', ['id' => $roleId]);
            $stmt = $db->prepare("SELECT permission_id FROM role_has_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);
            $permIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'role' => $role, 'permission_ids' => $permIds]);
            exit;

        case 'save_role':
            if (!$canCreate && !$canEdit) {
                echo json_encode(['success' => false, 'message' => 'غير مصرح']);
                exit;
            }

            $roleId       = (int)($_POST['role_id'] ?? 0);
            $name         = trim($_POST['name'] ?? '');
            $displayAr    = trim($_POST['display_name_ar'] ?? '');
            $displayEn    = trim($_POST['display_name_en'] ?? '');
            $description  = trim($_POST['description'] ?? '');
            $permissionIds = json_decode($_POST['permission_ids'] ?? '[]', true);

            if (empty($name) || empty($displayAr)) {
                echo json_encode(['success' => false, 'message' => 'الاسم والاسم العربي مطلوبان']);
                exit;
            }

            // Prevent privilege escalation: user can only assign permissions they have
            $myPerms = _load_user_permissions();
            $myRole = user_role_name();
            if ($myRole !== 'super_admin') {
                $stmt = $db->prepare("SELECT CONCAT(module,'.',action) as k FROM permissions WHERE id IN (" . implode(',', array_map('intval', $permissionIds)) . ")");
                $stmt->execute();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (empty($myPerms[$row['k']])) {
                        echo json_encode(['success' => false, 'message' => 'لا يمكنك منح صلاحيات لا تملكها']);
                        exit;
                    }
                }
            }

            if ($roleId > 0) {
                if (!$canEdit) { echo json_encode(['success' => false, 'message' => 'غير مصرح']); exit; }

                $existing = $db->get_row('roles', ['id' => $roleId]);
                if ($existing && $existing['is_system'] == 1) {
                    $db->update('roles', [
                        'display_name_ar' => $displayAr,
                        'display_name_en' => $displayEn,
                        'description'     => $description,
                    ], ['id' => $roleId]);
                } else {
                    $dupCheck = $db->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
                    $dupCheck->execute([$name, $roleId]);
                    if ($dupCheck->fetch()) {
                        echo json_encode(['success' => false, 'message' => 'اسم الدور مستخدم بالفعل']);
                        exit;
                    }
                    $db->update('roles', [
                        'name'            => $name,
                        'display_name_ar' => $displayAr,
                        'display_name_en' => $displayEn,
                        'description'     => $description,
                    ], ['id' => $roleId]);
                }

                $db->exec("DELETE FROM role_has_permissions WHERE role_id = " . $roleId);
                $logAction = 'update_role';
            } else {
                if (!$canCreate) { echo json_encode(['success' => false, 'message' => 'غير مصرح']); exit; }

                $dupCheck = $db->get_count('roles', ['name' => $name]);
                if ($dupCheck > 0) {
                    echo json_encode(['success' => false, 'message' => 'اسم الدور مستخدم بالفعل']);
                    exit;
                }
                $db->insert('roles', [
                    'name'            => $name,
                    'display_name_ar' => $displayAr,
                    'display_name_en' => $displayEn,
                    'description'     => $description,
                    'guard_name'      => 'web',
                    'is_system'       => 0,
                ]);
                $roleId = $db->insert_id;
                $logAction = 'create_role';
            }

            $ins = $db->prepare("INSERT IGNORE INTO role_has_permissions (permission_id, role_id) VALUES (?, ?)");
            foreach ($permissionIds as $pid) {
                $ins->execute([(int)$pid, $roleId]);
            }

            // Invalidate cached permissions for affected users
            log_activity($logAction, 'role', $roleId, json_encode([
                'name' => $name,
                'permissions_count' => count($permissionIds),
            ]));

            echo json_encode(['success' => true, 'role_id' => $roleId]);
            exit;

        case 'delete_role':
            if (!$canDelete) {
                echo json_encode(['success' => false, 'message' => 'غير مصرح']);
                exit;
            }
            $roleId = (int)($_POST['role_id'] ?? 0);
            $role = $db->get_row('roles', ['id' => $roleId]);
            if (!$role) {
                echo json_encode(['success' => false, 'message' => 'الدور غير موجود']);
                exit;
            }
            if ($role['is_system'] == 1) {
                echo json_encode(['success' => false, 'message' => 'لا يمكن حذف دور مدمج في النظام']);
                exit;
            }
            $userCount = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
            $userCount->execute([$roleId]);
            if ($userCount->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'لا يمكن حذف دور مرتبط بمستخدمين. انقل المستخدمين أولاً']);
                exit;
            }
            $db->exec("DELETE FROM role_has_permissions WHERE role_id = " . $roleId);
            $db->prepare("DELETE FROM roles WHERE id = ?")->execute([$roleId]);

            log_activity('delete_role', 'role', $roleId, json_encode(['name' => $role['name']]));
            echo json_encode(['success' => true]);
            exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

include 'header.php';

// ─── Module labels for UI ─────────────────────────────────────────────
$moduleLabels = [
    'dashboard'    => ['ar' => 'البحث',          'en' => 'Dashboard',    'icon' => 'fa-search'],
    'clients'      => ['ar' => 'العملاء',        'en' => 'Clients',      'icon' => 'fa-users'],
    'import'       => ['ar' => 'الاستيراد',      'en' => 'Import',       'icon' => 'fa-upload'],
    'jobs'         => ['ar' => 'الوظائف',        'en' => 'Jobs',         'icon' => 'fa-briefcase'],
    'violations'   => ['ar' => 'المخالفات',      'en' => 'Violations',   'icon' => 'fa-exclamation-triangle'],
    'accounts'     => ['ar' => 'الشركات',       'en' => 'Companies',     'icon' => 'fa-building'],
    'users'        => ['ar' => 'المستخدمين',     'en' => 'Users',        'icon' => 'fa-user-cog'],
    'roles'        => ['ar' => 'الأدوار',        'en' => 'Roles',        'icon' => 'fa-shield-alt'],
    'scan'         => ['ar' => 'الجرد',          'en' => 'Scan',         'icon' => 'fa-radar'],
    'reports'      => ['ar' => 'التقارير',       'en' => 'Reports',      'icon' => 'fa-chart-bar'],
    'sales_report' => ['ar' => 'تقرير المبيعات', 'en' => 'Sales Report', 'icon' => 'fa-chart-line'],
    'activity_log' => ['ar' => 'سجل النشاطات',  'en' => 'Activity Log', 'icon' => 'fa-history'],
    'translate'    => ['ar' => 'الترجمة',        'en' => 'Translate',    'icon' => 'fa-language'],
];
$actionLabels = [
    'view'    => ['ar' => 'عرض',    'en' => 'View'],
    'create'  => ['ar' => 'إضافة',  'en' => 'Create'],
    'edit'    => ['ar' => 'تعديل',  'en' => 'Edit'],
    'delete'  => ['ar' => 'حذف',    'en' => 'Delete'],
    'export'  => ['ar' => 'تصدير',  'en' => 'Export'],
    'execute' => ['ar' => 'تنفيذ',  'en' => 'Execute'],
    'manage'           => ['ar' => 'إدارة',           'en' => 'Manage'],
    'view_attachments' => ['ar' => 'مشاهدة المرفقات', 'en' => 'View Attachments'],
];
?>

<style>
.roles-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}
.roles-page .container { max-width: 1200px; }
.roles-header {
    text-align: center;
    margin-bottom: 30px;
}
.roles-header h1 { color: #fff; font-size: 26px; font-weight: 800; margin: 0 0 6px; }
.roles-header p { color: rgba(255,255,255,0.4); font-size: 13px; margin: 0; }

/* ─── Role Cards Grid ────────────────────────────────── */
.roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}
.role-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 16px;
    padding: 22px 20px;
    backdrop-filter: blur(12px);
    transition: all .25s ease;
    cursor: pointer;
    position: relative;
}
.role-card:hover {
    background: rgba(255,255,255,0.09);
    border-color: rgba(102,126,234,0.4);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
}
.role-card.active {
    border-color: #667eea;
    background: rgba(102,126,234,0.12);
    box-shadow: 0 0 20px rgba(102,126,234,0.2);
}
.role-card .role-name {
    font-size: 17px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}
.role-card .role-name-en {
    font-size: 12px;
    color: rgba(255,255,255,0.35);
    margin-bottom: 10px;
}
.role-card .role-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 10px;
}
.role-card .role-stat {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: rgba(255,255,255,0.5);
}
.role-card .role-stat i { font-size: 11px; }
.role-card .role-stat .num { color: #667eea; font-weight: 700; }
.role-card .role-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 6px;
    background: rgba(102,126,234,0.15);
    color: #667eea;
    border: 1px solid rgba(102,126,234,0.25);
}
.role-card .role-desc {
    font-size: 12px;
    color: rgba(255,255,255,0.35);
    line-height: 1.5;
}
.role-card .role-actions {
    display: flex;
    gap: 6px;
    margin-top: 12px;
}
.role-card .role-actions button {
    flex: 1;
    padding: 7px 0;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.6);
    font-size: 12px;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
}
.role-card .role-actions button:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}
.role-card .role-actions button.btn-del:hover {
    background: rgba(229,62,62,0.2);
    border-color: rgba(229,62,62,0.4);
    color: #ff6b6b;
}

/* ─── Add Role Button ────────────────────────────────── */
.add-role-card {
    background: rgba(255,255,255,0.03);
    border: 2px dashed rgba(255,255,255,0.12);
    border-radius: 16px;
    padding: 22px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .25s;
    min-height: 150px;
}
.add-role-card:hover {
    border-color: rgba(102,126,234,0.5);
    background: rgba(102,126,234,0.05);
}
.add-role-card i {
    font-size: 28px;
    color: rgba(255,255,255,0.2);
    margin-left: 8px;
}
.add-role-card span {
    font-size: 14px;
    color: rgba(255,255,255,0.3);
    font-weight: 600;
}

/* ─── Editor Panel ───────────────────────────────────── */
.editor-panel {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 20px;
    padding: 28px;
    backdrop-filter: blur(14px);
    display: none;
}
.editor-panel.visible { display: block; }
.editor-panel h2 {
    color: #fff;
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.editor-panel h2 .close-editor {
    background: none;
    border: none;
    color: rgba(255,255,255,0.4);
    font-size: 20px;
    cursor: pointer;
    padding: 4px 8px;
}
.editor-panel h2 .close-editor:hover { color: #fff; }

.editor-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin-bottom: 24px;
}
.editor-fields .field-group { display: flex; flex-direction: column; gap: 5px; }
.editor-fields .field-group.full { grid-column: 1 / -1; }
.editor-fields label {
    font-size: 12px;
    color: rgba(255,255,255,0.5);
    font-weight: 600;
}
.editor-fields input,
.editor-fields textarea {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    padding: 10px 14px;
    color: #fff;
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: border-color .2s;
}
.editor-fields input:focus,
.editor-fields textarea:focus {
    border-color: #667eea;
}
.editor-fields textarea { resize: vertical; min-height: 50px; }

/* ─── Permissions Grid ───────────────────────────────── */
.perm-section-title {
    color: rgba(255,255,255,0.5);
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.perm-section-title .perm-count {
    background: rgba(102,126,234,0.15);
    color: #667eea;
    padding: 2px 10px;
    border-radius: 8px;
    font-size: 12px;
}

.perm-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-bottom: 22px;
}
.perm-table thead th {
    background: rgba(255,255,255,0.06);
    padding: 10px 12px;
    font-size: 12px;
    font-weight: 700;
    color: rgba(255,255,255,0.5);
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.perm-table thead th:first-child {
    text-align: right;
    border-radius: 0 10px 0 0;
    padding-right: 16px;
}
.perm-table thead th:last-child { border-radius: 10px 0 0 0; }
.perm-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    text-align: center;
    font-size: 13px;
}
.perm-table tbody td:first-child {
    text-align: right;
    padding-right: 16px;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
}
.perm-table tbody td:first-child i {
    margin-left: 6px;
    color: rgba(255,255,255,0.3);
    font-size: 12px;
    width: 18px;
    display: inline-block;
}
.perm-table tbody tr:hover { background: rgba(255,255,255,0.03); }

/* Custom Checkbox */
.perm-cb { display: none; }
.perm-cb + label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 1.5px solid rgba(255,255,255,0.15);
    border-radius: 7px;
    cursor: pointer;
    transition: all .2s;
    background: rgba(255,255,255,0.03);
}
.perm-cb + label:hover {
    border-color: rgba(102,126,234,0.5);
    background: rgba(102,126,234,0.08);
}
.perm-cb:checked + label {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-color: transparent;
}
.perm-cb:checked + label::after {
    content: '✓';
    color: #fff;
    font-size: 14px;
    font-weight: 700;
}

/* Column / Row toggle */
.toggle-link {
    font-size: 11px;
    color: rgba(102,126,234,0.7);
    cursor: pointer;
    text-decoration: none;
    transition: color .2s;
}
.toggle-link:hover { color: #667eea; }

/* ─── Save Bar ───────────────────────────────────────── */
.save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 0 0;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.save-bar .btn-save {
    padding: 11px 36px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all .25s;
    font-family: inherit;
}
.save-bar .btn-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 20px rgba(102,126,234,0.4);
}
.save-bar .btn-save:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
.save-bar .btn-cancel {
    padding: 11px 24px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: rgba(255,255,255,0.6);
    font-size: 13px;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
}
.save-bar .btn-cancel:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}
.save-msg {
    font-size: 13px;
    margin: 0 12px;
    opacity: 0;
    transition: opacity .3s;
}
.save-msg.show { opacity: 1; }
.save-msg.success { color: #48bb78; }
.save-msg.error { color: #ff6b6b; }

/* ─── Footer ─────────────────────────────────────────── */
.roles-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0;
    margin-top: 40px;
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.25);
}
.roles-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.roles-footer a:hover { color: rgba(255,255,255,0.6); }
.roles-footer .fa-heart { color: #e53e3e; }
.roles-page ~ footer.footer { display: none !important; }

/* ─── Responsive ─────────────────────────────────────── */
@media (max-width: 768px) {
    .roles-grid { grid-template-columns: 1fr; }
    .editor-fields { grid-template-columns: 1fr; }
    .perm-table { font-size: 12px; }
    .perm-table thead th, .perm-table tbody td { padding: 8px 6px; }
    .perm-cb + label { width: 24px; height: 24px; }
    .perm-cb:checked + label::after { font-size: 12px; }
    .save-bar { flex-wrap: wrap; gap: 10px; }
}
</style>

<div class="roles-page">
    <div class="container">
        <div class="roles-header">
            <h1><i class="fad fa-shield-alt"></i> <?=_e('الأدوار والصلاحيات')?></h1>
            <p><?=_e('إدارة الأدوار والتحكم في صلاحيات الوصول لأقسام النظام')?></p>
        </div>

        <!-- Roles Grid -->
        <div class="roles-grid" id="rolesGrid"></div>

        <!-- Editor Panel -->
        <div class="editor-panel" id="editorPanel">
            <h2>
                <span id="editorTitle"><?=_e('تعديل الدور')?></span>
                <button class="close-editor" onclick="closeEditor()" title="<?=_e('إغلاق')?>"><i class="fa fa-times"></i></button>
            </h2>

            <div class="editor-fields">
                <input type="hidden" id="roleId" value="0">
                <div class="field-group">
                    <label><?=_e('اسم الدور (النظام)')?></label>
                    <input type="text" id="roleName" placeholder="مثال: accountant" dir="ltr">
                </div>
                <div class="field-group">
                    <label><?=_e('الاسم العربي')?></label>
                    <input type="text" id="roleDisplayAr" placeholder="مثال: محاسب">
                </div>
                <div class="field-group">
                    <label><?=_e('الاسم الإنجليزي')?></label>
                    <input type="text" id="roleDisplayEn" placeholder="مثال: Accountant" dir="ltr">
                </div>
                <div class="field-group">
                    <label><?=_e('الوصف')?></label>
                    <input type="text" id="roleDesc" placeholder="<?=_e('وصف مختصر للدور')?>">
                </div>
            </div>

            <div class="perm-section-title">
                <span><i class="fal fa-key"></i> <?=_e('الصلاحيات')?></span>
                <span class="perm-count" id="permCount">0 / 0</span>
            </div>

            <table class="perm-table" id="permTable">
                <thead><tr id="permTableHead"></tr></thead>
                <tbody id="permTableBody"></tbody>
            </table>

            <div class="save-bar">
                <div>
                    <?php if ($canEdit || $canCreate) { ?>
                    <button class="btn-save" id="btnSave" onclick="saveRole()"><?=_e('حفظ')?></button>
                    <?php } ?>
                    <button class="btn-cancel" onclick="closeEditor()"><?=_e('إلغاء')?></button>
                    <span class="save-msg" id="saveMsg"></span>
                </div>
            </div>
        </div>

        <div class="roles-footer">
            <?php include __DIR__ . '/includes/fahras-footer-credits.php'; ?>
            &nbsp;&middot;&nbsp;
            &copy; <?=_e('فهرس')?> <?=date('Y')?>
        </div>
    </div>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';
const LANG = '<?= $lang ?>';
const CAN_CREATE = <?= $canCreate ? 'true' : 'false' ?>;
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;

const MODULE_LABELS = <?= json_encode($moduleLabels, JSON_UNESCAPED_UNICODE) ?>;
const ACTION_LABELS = <?= json_encode($actionLabels, JSON_UNESCAPED_UNICODE) ?>;

let allPerms = [];
let allModules = {};
let currentRoleId = 0;

function ajax(method, params) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    for (const [k, v] of Object.entries(params)) fd.append(k, v);
    const url = method === 'GET'
        ? 'roles?' + new URLSearchParams(params).toString()
        : 'roles';
    return fetch(url, method === 'GET' ? {} : { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json());
}

function loadRoles() {
    fetch('roles?action=get_roles', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const grid = document.getElementById('rolesGrid');
            let html = '';
            data.roles.forEach(role => {
                const isSystem = role.is_system == 1;
                const lbl = LANG === 'ar' ? (role.display_name_ar || role.name) : (role.display_name_en || role.name);
                const subLbl = LANG === 'ar' ? (role.display_name_en || '') : (role.display_name_ar || '');
                html += `<div class="role-card ${currentRoleId == role.id ? 'active' : ''}" data-id="${role.id}" onclick="editRole(${role.id})">
                    ${isSystem ? '<span class="role-badge">' + (LANG === 'ar' ? 'مدمج' : 'System') + '</span>' : ''}
                    <div class="role-name">${lbl}</div>
                    <div class="role-name-en">${subLbl}</div>
                    <div class="role-stats">
                        <div class="role-stat"><i class="fal fa-users"></i> <span class="num">${role.user_count}</span> ${LANG === 'ar' ? 'مستخدم' : 'users'}</div>
                        <div class="role-stat"><i class="fal fa-key"></i> <span class="num">${role.perm_count}</span> ${LANG === 'ar' ? 'صلاحية' : 'perms'}</div>
                    </div>
                    <div class="role-desc">${role.description || ''}</div>
                    <div class="role-actions">
                        ${CAN_EDIT ? '<button onclick="event.stopPropagation();editRole(' + role.id + ')"><i class="fal fa-edit"></i> ' + (LANG === 'ar' ? 'تعديل' : 'Edit') + '</button>' : ''}
                        ${CAN_DELETE && !isSystem ? '<button class="btn-del" onclick="event.stopPropagation();deleteRole(' + role.id + ',\'' + lbl + '\')"><i class="fal fa-trash"></i> ' + (LANG === 'ar' ? 'حذف' : 'Delete') + '</button>' : ''}
                    </div>
                </div>`;
            });

            if (CAN_CREATE) {
                html += `<div class="add-role-card" onclick="createRole()">
                    <i class="fal fa-plus-circle"></i>
                    <span>${LANG === 'ar' ? 'إضافة دور جديد' : 'Add New Role'}</span>
                </div>`;
            }
            grid.innerHTML = html;
        });
}

function loadPermissions() {
    return fetch('roles?action=get_permissions', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            allPerms = data.permissions;
            allModules = data.modules;
            buildPermTable();
        });
}

function buildPermTable() {
    const actions = [...new Set(allPerms.map(p => p.action))];
    const modules = [...new Set(allPerms.map(p => p.module))];

    let headHtml = '<th>' + (LANG === 'ar' ? 'الوحدة' : 'Module') + '</th>';
    actions.forEach(a => {
        const lbl = ACTION_LABELS[a] ? ACTION_LABELS[a][LANG] : a;
        headHtml += `<th><span class="toggle-link" onclick="toggleCol('${a}')">${lbl}</span></th>`;
    });
    document.getElementById('permTableHead').innerHTML = headHtml;

    let bodyHtml = '';
    modules.forEach(mod => {
        const ml = MODULE_LABELS[mod] || {};
        const mLabel = ml[LANG] || mod;
        const icon = ml.icon || 'fa-circle';
        const modPerms = allPerms.filter(p => p.module === mod);

        bodyHtml += '<tr>';
        bodyHtml += `<td><i class="fal ${icon}"></i> ${mLabel} <span class="toggle-link" onclick="toggleRow('${mod}')">(${LANG === 'ar' ? 'الكل' : 'all'})</span></td>`;
        actions.forEach(a => {
            const perm = modPerms.find(p => p.action === a);
            if (perm) {
                bodyHtml += `<td><input type="checkbox" class="perm-cb" id="perm_${perm.id}" data-module="${mod}" data-action="${a}" value="${perm.id}" onchange="updateCount()"><label for="perm_${perm.id}"></label></td>`;
            } else {
                bodyHtml += '<td></td>';
            }
        });
        bodyHtml += '</tr>';
    });
    document.getElementById('permTableBody').innerHTML = bodyHtml;
}

function toggleRow(mod) {
    const cbs = document.querySelectorAll(`.perm-cb[data-module="${mod}"]`);
    const allChecked = [...cbs].every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
    updateCount();
}

function toggleCol(action) {
    const cbs = document.querySelectorAll(`.perm-cb[data-action="${action}"]`);
    const allChecked = [...cbs].every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
    updateCount();
}

function updateCount() {
    const total = document.querySelectorAll('.perm-cb').length;
    const checked = document.querySelectorAll('.perm-cb:checked').length;
    document.getElementById('permCount').textContent = checked + ' / ' + total;
}

function openEditor(title) {
    document.getElementById('editorTitle').textContent = title;
    document.getElementById('editorPanel').classList.add('visible');
    document.getElementById('editorPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function closeEditor() {
    document.getElementById('editorPanel').classList.remove('visible');
    currentRoleId = 0;
    loadRoles();
}

function createRole() {
    currentRoleId = 0;
    document.getElementById('roleId').value = 0;
    document.getElementById('roleName').value = '';
    document.getElementById('roleName').disabled = false;
    document.getElementById('roleDisplayAr').value = '';
    document.getElementById('roleDisplayEn').value = '';
    document.getElementById('roleDesc').value = '';
    document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = false);
    updateCount();
    openEditor(LANG === 'ar' ? 'إضافة دور جديد' : 'Create New Role');
    loadRoles();
}

function editRole(id) {
    currentRoleId = id;
    fetch('roles?action=get_role&id=' + id, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const role = data.role;
            document.getElementById('roleId').value = role.id;
            document.getElementById('roleName').value = role.name;
            document.getElementById('roleName').disabled = (role.is_system == 1);
            document.getElementById('roleDisplayAr').value = role.display_name_ar || '';
            document.getElementById('roleDisplayEn').value = role.display_name_en || '';
            document.getElementById('roleDesc').value = role.description || '';

            document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = false);
            data.permission_ids.forEach(pid => {
                const cb = document.getElementById('perm_' + pid);
                if (cb) cb.checked = true;
            });
            updateCount();
            openEditor(LANG === 'ar' ? 'تعديل الدور: ' + (role.display_name_ar || role.name) : 'Edit Role: ' + (role.display_name_en || role.name));
            loadRoles();
        });
}

function saveRole() {
    const btn = document.getElementById('btnSave');
    btn.disabled = true;

    const permIds = [...document.querySelectorAll('.perm-cb:checked')].map(cb => parseInt(cb.value));

    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('action', 'save_role');
    fd.append('role_id', document.getElementById('roleId').value);
    fd.append('name', document.getElementById('roleName').value);
    fd.append('display_name_ar', document.getElementById('roleDisplayAr').value);
    fd.append('display_name_en', document.getElementById('roleDisplayEn').value);
    fd.append('description', document.getElementById('roleDesc').value);
    fd.append('permission_ids', JSON.stringify(permIds));

    fetch('roles', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            const msg = document.getElementById('saveMsg');
            if (data.success) {
                msg.textContent = LANG === 'ar' ? 'تم الحفظ بنجاح' : 'Saved successfully';
                msg.className = 'save-msg show success';
                currentRoleId = data.role_id;
                loadRoles();
            } else {
                msg.textContent = data.message || 'Error';
                msg.className = 'save-msg show error';
            }
            setTimeout(() => msg.className = 'save-msg', 3000);
        })
        .catch(() => {
            btn.disabled = false;
        });
}

function deleteRole(id, name) {
    if (!confirm((LANG === 'ar' ? 'هل تريد حذف الدور: ' : 'Delete role: ') + name + '?')) return;

    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('action', 'delete_role');
    fd.append('role_id', id);

    fetch('roles', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (currentRoleId == id) closeEditor();
                loadRoles();
            } else {
                alert(data.message || 'Error');
            }
        });
}

// Init
loadPermissions().then(() => loadRoles());
</script>

<?php include 'footer.php'; ?>
