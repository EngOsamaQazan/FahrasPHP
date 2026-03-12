<?php
	$page_title = 'المستخدمين';
	$token = 'mojeer';

	require_once __DIR__ . '/../includes/bootstrap.php';
	require_auth();
	require_permission('users', 'view');

	$canEditPerms = user_can('users', 'edit');

	// ─── AJAX: User Direct Permissions ──────────────────────────────────
	if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
		header('Content-Type: application/json; charset=utf-8');
		$action = $_POST['action'] ?? $_GET['action'] ?? '';

		if (!csrf_verify() && !in_array($action, ['get_user_perms'])) {
			echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
			exit;
		}

		switch ($action) {
			case 'get_user_perms':
				$uid = (int)($_GET['user_id'] ?? 0);
				$target = $db->get_row('users', ['id' => $uid]);
				if (!$target) { echo json_encode(['success' => false, 'message' => 'User not found']); exit; }

				$roleId = (int)($target['role_id'] ?? 0);
				$roleName = '';
				if ($roleId) {
					$role = $db->get_row('roles', ['id' => $roleId]);
					$roleName = $role ? $role['name'] : '';
				}

				$rolePerms = [];
				if ($roleId) {
					$s = $db->prepare("SELECT permission_id FROM role_has_permissions WHERE role_id = ?");
					$s->execute([$roleId]);
					$rolePerms = $s->fetchAll(PDO::FETCH_COLUMN);
				}

				$directPerms = [];
				$s = $db->prepare("SELECT permission_id FROM user_has_permissions WHERE user_id = ?");
				$s->execute([$uid]);
				$directPerms = $s->fetchAll(PDO::FETCH_COLUMN);

				echo json_encode([
					'success' => true,
					'user_id' => $uid,
					'user_name' => $target['name'],
					'role_name' => $roleName,
					'role_permission_ids' => array_map('intval', $rolePerms),
					'direct_permission_ids' => array_map('intval', $directPerms),
				]);
				exit;

			case 'save_user_perms':
				if (!$canEditPerms) {
					echo json_encode(['success' => false, 'message' => 'غير مصرح']);
					exit;
				}

				$uid = (int)($_POST['user_id'] ?? 0);
				$permIds = json_decode($_POST['permission_ids'] ?? '[]', true);
				if (!is_array($permIds)) $permIds = [];

				$target = $db->get_row('users', ['id' => $uid]);
				if (!$target) { echo json_encode(['success' => false, 'message' => 'User not found']); exit; }

				$myRole = user_role_name();
				if ($myRole !== 'super_admin') {
					$myPerms = _load_user_permissions();
					if (!empty($permIds)) {
						$placeholders = implode(',', array_fill(0, count($permIds), '?'));
						$chk = $db->prepare("SELECT CONCAT(module,'.',action) as k FROM permissions WHERE id IN ($placeholders)");
						$chk->execute(array_map('intval', $permIds));
						while ($row = $chk->fetch(PDO::FETCH_ASSOC)) {
							if (empty($myPerms[$row['k']])) {
								echo json_encode(['success' => false, 'message' => 'لا يمكنك منح صلاحيات لا تملكها']);
								exit;
							}
						}
					}
				}

				$db->prepare("DELETE FROM user_has_permissions WHERE user_id = ?")->execute([$uid]);
				$ins = $db->prepare("INSERT IGNORE INTO user_has_permissions (user_id, permission_id) VALUES (?, ?)");
				foreach ($permIds as $pid) {
					$ins->execute([$uid, (int)$pid]);
				}

				log_activity('update_user_perms', 'user', $uid, json_encode([
					'direct_permissions_count' => count($permIds),
				]));

				echo json_encode(['success' => true, 'count' => count($permIds)]);
				exit;
		}

		echo json_encode(['success' => false, 'message' => 'Unknown action']);
		exit;
	}

	include 'header.php';

	// Build dynamic role options from DB (exclude 'admin' role)
	$roleStmt = $db->prepare("SELECT id, name, display_name_ar, display_name_en FROM roles WHERE name != 'admin' ORDER BY id");
	$roleStmt->execute();
	$roleOptions = [];
	$superAdminRoleId = 0;
	while ($r = $roleStmt->fetch(PDO::FETCH_ASSOC)) {
		$label = ($lang === 'ar') ? ($r['display_name_ar'] ?: $r['name']) : ($r['display_name_en'] ?: $r['name']);
		$roleOptions[$r['id']] = $label;
		if ($r['name'] === 'super_admin') $superAdminRoleId = (int)$r['id'];
	}

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
.xcrud-page {
    background: linear-gradient(180deg, #1a1a2e 0%, #16213e 40%, #0f3460 100%);
    min-height: calc(100vh - 50px);
    margin: -20px -15px -60px;
    padding: 30px 20px 60px;
    color: #e0e6ed;
}
.xcrud-page-header { text-align: center; margin-bottom: 28px; }
.xcrud-page-header h1 { color: #fff; font-size: 24px; font-weight: 800; margin: 0 0 6px; }
.xcrud-page-header p { color: rgba(255,255,255,0.4); font-size: 12px; margin: 0; }
.xcrud-page .container { max-width: 1100px; }
.xcrud-page-footer {
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.06);
    padding: 16px 0; margin-top: 40px; text-align: center;
    font-size: 12px; color: rgba(255,255,255,0.25);
}
.xcrud-page-footer a { color: rgba(255,255,255,0.35); text-decoration: none; }
.xcrud-page-footer a:hover { color: rgba(255,255,255,0.6); }
.xcrud-page-footer .fa-heart { color: #e53e3e; }
.xcrud-page ~ footer.footer { display: none !important; }

/* ─── Permissions Modal ──────────────────────────────── */
.up-overlay {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.65); backdrop-filter: blur(6px);
    align-items: center; justify-content: center; padding: 20px;
}
.up-overlay.open { display: flex; }
.up-modal {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px; width: 100%; max-width: 820px;
    max-height: 90vh; display: flex; flex-direction: column;
    box-shadow: 0 25px 60px rgba(0,0,0,0.5);
}
.up-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 22px 28px; border-bottom: 1px solid rgba(255,255,255,0.06);
}
.up-header h2 { color: #fff; font-size: 18px; font-weight: 700; margin: 0; }
.up-header .up-user-badge {
    background: rgba(102,126,234,0.12); border: 1px solid rgba(102,126,234,0.25);
    padding: 4px 14px; border-radius: 8px; font-size: 12px; color: #667eea;
}
.up-header .up-close {
    background: none; border: none; color: rgba(255,255,255,0.4);
    font-size: 22px; cursor: pointer; padding: 4px 8px; line-height: 1;
}
.up-header .up-close:hover { color: #fff; }
.up-body { overflow-y: auto; padding: 24px 28px; flex: 1; }

.up-legend {
    display: flex; gap: 18px; margin-bottom: 18px; flex-wrap: wrap;
}
.up-legend-item {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: rgba(255,255,255,0.5);
}
.up-legend-dot {
    width: 14px; height: 14px; border-radius: 4px; flex-shrink: 0;
}
.up-legend-dot.role { background: linear-gradient(135deg, #667eea, #764ba2); opacity: 0.5; }
.up-legend-dot.direct { background: linear-gradient(135deg, #48bb78, #38a169); }
.up-legend-dot.none { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); }

.up-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.up-table thead th {
    background: rgba(255,255,255,0.05); padding: 10px 10px;
    font-size: 12px; font-weight: 700; color: rgba(255,255,255,0.45);
    text-align: center; border-bottom: 1px solid rgba(255,255,255,0.06);
}
.up-table thead th:first-child {
    text-align: right; padding-right: 14px; border-radius: 0 10px 0 0;
}
.up-table thead th:last-child { border-radius: 10px 0 0 0; }
.up-table tbody td {
    padding: 9px 10px; text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px;
}
.up-table tbody td:first-child {
    text-align: right; padding-right: 14px;
    color: rgba(255,255,255,0.65); font-weight: 600;
}
.up-table tbody td:first-child i {
    margin-left: 6px; color: rgba(255,255,255,0.25); font-size: 12px; width: 18px; display: inline-block;
}
.up-table tbody tr:hover { background: rgba(255,255,255,0.025); }

.up-cb { display: none; }
.up-cb + label {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 7px;
    cursor: pointer; transition: all .2s;
}
.up-cb + label.from-role {
    background: linear-gradient(135deg, #667eea, #764ba2); opacity: 0.45;
    cursor: default; border: none;
}
.up-cb + label.from-role::after { content: '✓'; color: #fff; font-size: 13px; font-weight: 700; }
.up-cb + label:not(.from-role) {
    border: 1.5px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.03);
}
.up-cb + label:not(.from-role):hover {
    border-color: rgba(72,187,120,0.5); background: rgba(72,187,120,0.08);
}
.up-cb:checked + label:not(.from-role) {
    background: linear-gradient(135deg, #48bb78, #38a169); border-color: transparent;
}
.up-cb:checked + label:not(.from-role)::after { content: '✓'; color: #fff; font-size: 14px; font-weight: 700; }

.up-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 28px; border-top: 1px solid rgba(255,255,255,0.06);
}
.up-counter { font-size: 12px; color: rgba(255,255,255,0.4); }
.up-counter b { color: #48bb78; }
.up-counter .role-count { color: #667eea; }
.up-actions { display: flex; gap: 8px; align-items: center; }
.up-btn-save {
    padding: 10px 30px; border: none; border-radius: 10px;
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;
    font-family: inherit; transition: all .25s;
}
.up-btn-save:hover { transform: translateY(-1px); box-shadow: 0 5px 20px rgba(72,187,120,0.35); }
.up-btn-save:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
.up-btn-cancel {
    padding: 10px 20px; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px;
    background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.6);
    font-size: 13px; cursor: pointer; font-family: inherit; transition: all .2s;
}
.up-btn-cancel:hover { background: rgba(255,255,255,0.1); color: #fff; }
.up-msg { font-size: 13px; opacity: 0; transition: opacity .3s; margin: 0 10px; }
.up-msg.show { opacity: 1; }
.up-msg.success { color: #48bb78; }
.up-msg.error { color: #ff6b6b; }

@media (max-width: 768px) {
    .up-modal { max-width: 100%; border-radius: 14px; }
    .up-header, .up-body, .up-footer { padding-left: 16px; padding-right: 16px; }
    .up-table thead th, .up-table tbody td { padding: 7px 5px; font-size: 11px; }
    .up-cb + label { width: 24px; height: 24px; }
    .up-footer { flex-wrap: wrap; gap: 10px; }
}
</style>

<div class="xcrud-page">
    <div class="container">
        <div class="xcrud-page-header">
            <h1><i class="fad fa-key"></i> <?=_e($page_title)?></h1>
            <p><?=_e('إدارة مستخدمي النظام والصلاحيات')?></p>
        </div>

	<?php
		if ($lang == 'en') {
			Xcrud_config::$is_rtl = 0;
		}
		$xcrud = Xcrud::get_instance();
		$xcrud->table('users');
		$xcrud->order_by('id','desc');
		$xcrud->language($user['language']);

		$xcrud->change_type('password', 'password', 'md5', array('placeholder'=>_e('أدخل كلمة المرور الجديدة')));

		$xcrud->label(array(
			'name' => _e('الاسم الكامل'),
			'role_id' => _e('الصلاحية'),
			'account' => _e('الشركة'),
			'language' => _e('اللغة'),
			'username' => _e('اسم المستخدم'),
			'token' => _e('الرمز'),
			'password' => _e('كلمة المرور'),
			'last_login' => _e('آخر دخول'),
			'created_on' => _e('تاريخ الإنشاء'),
			'active' => _e('نشط؟'),
		));

		$xcrud->change_type('role_id', 'select', '', $roleOptions);

		$xcrud->change_type('language', 'select', '', array(
			'en'=>_e('الإنجليزية'),
			'ar'=>_e('العربية'),
		));

		$xcrud->pass_var('created_on', date('Y-m-d H:i:s'));

		$xcrud->fields('created_on,token,last_login,role', true);
		$xcrud->columns('token,role', true);

		$xcrud->unset_view();

		$xcrud->unset_remove(true, 'id', '=', $user['id']);

		$xcrud->relation('account','accounts','id','name');
		$xcrud->relation('role_id','roles','id', ($lang === 'ar' ? 'display_name_ar' : 'display_name_en'));

		if (!user_can('users', 'create')) $xcrud->unset_add();
		if (!user_can('users', 'edit'))   $xcrud->unset_edit();
		if (!user_can('users', 'delete')) $xcrud->unset_remove();

		$permBtnLabel = _e('صلاحيات');
		$xcrud->button('#', $permBtnLabel, 'fal fa-user-shield', 'xcrud-action btn-perms', [
			'data-uid' => '{id}',
			'onclick' => 'event.preventDefault();upOpen({id})',
			'style' => 'color:#48bb78;',
		]);

		echo $xcrud->render();
	?>

        <div class="xcrud-page-footer">
            <a href="https://fb.com/mujeer.world" target="_blank"><?=_e('صُنع بـ')?> <i class="fa fa-heart"></i> <?=_e('بواسطة MÜJEER')?></a>
            &nbsp;&middot;&nbsp;
            &copy; <?=_e('فهرس')?> <?=date('Y')?>
        </div>
    </div>
</div>

<!-- Permissions Modal -->
<div class="up-overlay" id="upOverlay">
    <div class="up-modal">
        <div class="up-header">
            <h2><i class="fal fa-user-shield"></i> <span id="upTitle"><?=_e('صلاحيات المستخدم')?></span></h2>
            <div>
                <span class="up-user-badge" id="upRoleBadge"></span>
            </div>
            <button class="up-close" onclick="upClose()"><i class="fa fa-times"></i></button>
        </div>
        <div class="up-body">
            <div class="up-legend">
                <div class="up-legend-item"><div class="up-legend-dot role"></div> <?= $lang === 'ar' ? 'من الدور (مقفلة)' : 'From role (locked)' ?></div>
                <div class="up-legend-item"><div class="up-legend-dot direct"></div> <?= $lang === 'ar' ? 'صلاحية مباشرة (إضافية)' : 'Direct permission (extra)' ?></div>
                <div class="up-legend-item"><div class="up-legend-dot none"></div> <?= $lang === 'ar' ? 'غير مفعّلة' : 'Not granted' ?></div>
            </div>
            <table class="up-table">
                <thead><tr id="upHead"></tr></thead>
                <tbody id="upBody"></tbody>
            </table>
        </div>
        <div class="up-footer">
            <div class="up-counter" id="upCounter"></div>
            <div class="up-actions">
                <span class="up-msg" id="upMsg"></span>
                <?php if ($canEditPerms) { ?>
                <button class="up-btn-save" id="upSave" onclick="upSavePerms()"><?= $lang === 'ar' ? 'حفظ' : 'Save' ?></button>
                <?php } ?>
                <button class="up-btn-cancel" onclick="upClose()"><?= $lang === 'ar' ? 'إغلاق' : 'Close' ?></button>
            </div>
        </div>
    </div>
</div>

<script>
const UP_CSRF = '<?= csrf_token() ?>';
const UP_LANG = '<?= $lang ?>';
const UP_CAN_EDIT = <?= $canEditPerms ? 'true' : 'false' ?>;
const UP_MODULES = <?= json_encode($moduleLabels, JSON_UNESCAPED_UNICODE) ?>;
const UP_ACTIONS = <?= json_encode($actionLabels, JSON_UNESCAPED_UNICODE) ?>;

let upUserId = 0;
let upAllPerms = [];
let upRolePermIds = [];

function upInit() {
    loadAllPerms();
}

function loadAllPerms() {
    return fetch('roles?action=get_permissions', { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (data.success) upAllPerms = data.permissions;
        });
}

function upOpen(userId) {
    upUserId = userId;
    document.getElementById('upOverlay').classList.add('open');

    fetch('users?action=get_user_perms&user_id=' + userId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;

            const userName = data.user_name;
            const roleName = data.role_name;
            upRolePermIds = data.role_permission_ids;
            const directPermIds = data.direct_permission_ids;

            document.getElementById('upTitle').textContent =
                (UP_LANG === 'ar' ? 'صلاحيات: ' : 'Permissions: ') + userName;
            document.getElementById('upRoleBadge').textContent =
                (UP_LANG === 'ar' ? 'الدور: ' : 'Role: ') + (roleName || '-');

            buildPermGrid(upRolePermIds, directPermIds);
        });
}

function buildPermGrid(rolePermIds, directPermIds) {
    if (!upAllPerms.length) { loadAllPerms().then(() => buildPermGrid(rolePermIds, directPermIds)); return; }

    const actions = [...new Set(upAllPerms.map(p => p.action))];
    const modules = [...new Set(upAllPerms.map(p => p.module))];

    let headHtml = '<th>' + (UP_LANG === 'ar' ? 'الوحدة' : 'Module') + '</th>';
    actions.forEach(a => {
        const lbl = UP_ACTIONS[a] ? UP_ACTIONS[a][UP_LANG] : a;
        headHtml += '<th>' + lbl + '</th>';
    });
    document.getElementById('upHead').innerHTML = headHtml;

    const roleSet = new Set(rolePermIds);
    const directSet = new Set(directPermIds);

    let bodyHtml = '';
    modules.forEach(mod => {
        const ml = UP_MODULES[mod] || {};
        const mLabel = ml[UP_LANG] || mod;
        const icon = ml.icon || 'fa-circle';
        const modPerms = upAllPerms.filter(p => p.module === mod);

        bodyHtml += '<tr>';
        bodyHtml += '<td><i class="fal ' + icon + '"></i> ' + mLabel + '</td>';
        actions.forEach(a => {
            const perm = modPerms.find(p => p.action === a);
            if (perm) {
                const pid = parseInt(perm.id);
                const fromRole = roleSet.has(pid);
                const isDirect = directSet.has(pid);

                if (fromRole) {
                    bodyHtml += '<td><input type="checkbox" class="up-cb" id="up_' + pid + '" value="' + pid + '" disabled checked><label for="up_' + pid + '" class="from-role" title="' + (UP_LANG === 'ar' ? 'من الدور' : 'From role') + '"></label></td>';
                } else {
                    const chk = isDirect ? ' checked' : '';
                    const dis = UP_CAN_EDIT ? '' : ' disabled';
                    bodyHtml += '<td><input type="checkbox" class="up-cb up-direct" id="up_' + pid + '" value="' + pid + '"' + chk + dis + ' onchange="upUpdateCount()"><label for="up_' + pid + '"></label></td>';
                }
            } else {
                bodyHtml += '<td></td>';
            }
        });
        bodyHtml += '</tr>';
    });
    document.getElementById('upBody').innerHTML = bodyHtml;
    upUpdateCount();
}

function upUpdateCount() {
    const roleCount = document.querySelectorAll('.up-cb:disabled:checked').length;
    const directCount = document.querySelectorAll('.up-direct:checked').length;
    const el = document.getElementById('upCounter');
    if (UP_LANG === 'ar') {
        el.innerHTML = '<span class="role-count">' + roleCount + '</span> من الدور + <b>' + directCount + '</b> مباشرة';
    } else {
        el.innerHTML = '<span class="role-count">' + roleCount + '</span> from role + <b>' + directCount + '</b> direct';
    }
}

function upSavePerms() {
    const btn = document.getElementById('upSave');
    btn.disabled = true;

    const directIds = [...document.querySelectorAll('.up-direct:checked')].map(cb => parseInt(cb.value));

    const fd = new FormData();
    fd.append('csrf_token', UP_CSRF);
    fd.append('action', 'save_user_perms');
    fd.append('user_id', upUserId);
    fd.append('permission_ids', JSON.stringify(directIds));

    fetch('users', { method: 'POST', body: fd, headers: {'X-Requested-With': 'XMLHttpRequest'} })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            const msg = document.getElementById('upMsg');
            if (data.success) {
                msg.textContent = UP_LANG === 'ar' ? 'تم الحفظ بنجاح' : 'Saved';
                msg.className = 'up-msg show success';
            } else {
                msg.textContent = data.message || 'Error';
                msg.className = 'up-msg show error';
            }
            setTimeout(() => msg.className = 'up-msg', 3000);
        })
        .catch(() => { btn.disabled = false; });
}

function upClose() {
    document.getElementById('upOverlay').classList.remove('open');
    upUserId = 0;
}

document.getElementById('upOverlay').addEventListener('click', function(e) {
    if (e.target === this) upClose();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('upOverlay').classList.contains('open')) upClose();
});

upInit();

var SUPER_ADMIN_ROLE_ID = '<?= $superAdminRoleId ?>';
</script>

<?php include 'footer.php'; ?>

<script>
(function(){
    var ROLE_NAME = '<?= str_replace(["=","/","+"], ["-","_",":"], base64_encode("users.role_id")) ?>';
    var ACCT_NAME = '<?= str_replace(["=","/","+"], ["-","_",":"], base64_encode("users.account")) ?>';

    function doToggle() {
        var rs = document.querySelector('select[name="' + ROLE_NAME + '"]');
        var as = document.querySelector('select[name="' + ACCT_NAME + '"]');
        if (!rs || !as) return;
        var grp = jQuery(as).closest('.form-group');
        if (!grp.length) return;
        if (rs.value == SUPER_ADMIN_ROLE_ID) {
            grp.slideUp(200);
        } else {
            grp.slideDown(200);
        }
    }

    jQuery(document).on('xcrudafterrequest', function() {
        setTimeout(function(){
            doToggle();
            var rs = jQuery('select[name="' + ROLE_NAME + '"]');
            rs.off('change.acctToggle select2:select.acctToggle');
            rs.on('change.acctToggle select2:select.acctToggle', doToggle);
        }, 500);
    });
})();
</script>
