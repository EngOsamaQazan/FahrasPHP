<?php
/**
 * RBAC Migration: Creates RBAC tables, seeds roles/permissions, links users.
 *
 * Safe to run multiple times -- uses IF NOT EXISTS and checks before altering.
 * Run once: php includes/migrations/001_rbac.php
 */

require_once __DIR__ . '/../smplPDO.php';

$db = new smplPDO("mysql:host=localhost;dbname=fahras_db", "root", "");

echo "=== Fahras RBAC Migration ===\n\n";

// ─── 0. Create base tables if they don't exist ─────────────────────

echo "[0/5] Creating RBAC tables if needed... ";

$db->exec("CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(125) NOT NULL UNIQUE,
    `guard_name` VARCHAR(125) NOT NULL DEFAULT 'web',
    `display_name_ar` VARCHAR(100) DEFAULT '',
    `display_name_en` VARCHAR(100) DEFAULT '',
    `description` TEXT DEFAULT NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS `permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(125) NOT NULL UNIQUE,
    `guard_name` VARCHAR(125) NOT NULL DEFAULT 'web',
    `module` VARCHAR(50) DEFAULT '',
    `action` VARCHAR(50) DEFAULT '',
    `display_name_ar` VARCHAR(100) DEFAULT '',
    `display_name_en` VARCHAR(100) DEFAULT '',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("CREATE TABLE IF NOT EXISTS `role_has_permissions` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `role_id`),
    CONSTRAINT `rhp_perm_fk` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    CONSTRAINT `rhp_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "OK\n";

// ─── 1. Ensure roles table has all needed columns ───────────────────

echo "[1/5] Ensuring roles table columns... ";
$cols = [];
$s = $db->prepare("SHOW COLUMNS FROM roles"); $s->execute();
while ($r = $s->fetch(PDO::FETCH_ASSOC)) $cols[] = $r['Field'];

if (!in_array('display_name_ar', $cols))
    $db->exec("ALTER TABLE `roles` ADD COLUMN `display_name_ar` VARCHAR(100) DEFAULT '' AFTER `name`");
if (!in_array('display_name_en', $cols))
    $db->exec("ALTER TABLE `roles` ADD COLUMN `display_name_en` VARCHAR(100) DEFAULT '' AFTER `display_name_ar`");
if (!in_array('description', $cols))
    $db->exec("ALTER TABLE `roles` ADD COLUMN `description` TEXT DEFAULT NULL AFTER `display_name_en`");
if (!in_array('is_system', $cols))
    $db->exec("ALTER TABLE `roles` ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 AFTER `description`");
echo "OK\n";

// ─── 2. Ensure permissions table has all needed columns ──────────────

echo "[2/5] Ensuring permissions table columns... ";
$cols = [];
$s = $db->prepare("SHOW COLUMNS FROM permissions"); $s->execute();
while ($r = $s->fetch(PDO::FETCH_ASSOC)) $cols[] = $r['Field'];

if (!in_array('module', $cols))
    $db->exec("ALTER TABLE `permissions` ADD COLUMN `module` VARCHAR(50) DEFAULT '' AFTER `name`");
if (!in_array('action', $cols))
    $db->exec("ALTER TABLE `permissions` ADD COLUMN `action` VARCHAR(50) DEFAULT '' AFTER `module`");
if (!in_array('display_name_ar', $cols))
    $db->exec("ALTER TABLE `permissions` ADD COLUMN `display_name_ar` VARCHAR(100) DEFAULT '' AFTER `action`");
if (!in_array('display_name_en', $cols))
    $db->exec("ALTER TABLE `permissions` ADD COLUMN `display_name_en` VARCHAR(100) DEFAULT '' AFTER `display_name_ar`");
if (!in_array('sort_order', $cols))
    $db->exec("ALTER TABLE `permissions` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `display_name_en`");
echo "OK\n";

// ─── 3. Update existing data ────────────────────────────────────────

echo "[3/5] Seeding roles and permissions... ";

$rolesMeta = [
    'super_admin'   => ['مدير النظام',  'Super Admin',   'صلاحيات كاملة بما فيها إدارة الأدوار', 1],
    'company_admin' => ['مدير شركة',    'Company Admin', 'إدارة العملاء والمخالفات للشركة',      1],
    'user'          => ['مستخدم',       'User',          'إضافة وتعديل العملاء',                 1],
    'viewer'        => ['مشاهد',        'Viewer',        'عرض البيانات فقط',                     1],
];

$insRole = $db->prepare("INSERT IGNORE INTO roles (name, guard_name, display_name_ar, display_name_en, description, is_system) VALUES (?, 'web', ?, ?, ?, ?)");
$updRole = $db->prepare("UPDATE roles SET display_name_ar=?, display_name_en=?, description=?, is_system=? WHERE name=?");
foreach ($rolesMeta as $name => $meta) {
    $insRole->execute([$name, $meta[0], $meta[1], $meta[2], $meta[3]]);
    $updRole->execute([$meta[0], $meta[1], $meta[2], $meta[3], $name]);
}

$permMeta = [
    'view_clients'          => ['clients',      'view',    'العملاء - عرض',          'Clients - View',        1],
    'create_clients'        => ['clients',      'create',  'العملاء - إضافة',        'Clients - Create',      2],
    'edit_clients'          => ['clients',      'edit',    'العملاء - تعديل',        'Clients - Edit',        3],
    'delete_clients'        => ['clients',      'delete',  'العملاء - حذف',          'Clients - Delete',      4],
    'export_clients'        => ['clients',      'export',  'العملاء - تصدير',        'Clients - Export',      5],
    'import_clients'        => ['import',       'execute', 'الاستيراد - تنفيذ',      'Import - Execute',      6],
    'view_jobs'             => ['jobs',         'view',    'الوظائف - عرض',          'Jobs - View',           7],
    'view_violations'       => ['violations',   'view',    'المخالفات - عرض',        'Violations - View',     8],
    'manage_violations'     => ['violations',   'manage',  'المخالفات - إدارة',      'Violations - Manage',   9],
    'view_accounts'         => ['accounts',     'view',    'الشركات - عرض',         'Companies - View',       10],
    'create_accounts'       => ['accounts',     'create',  'الشركات - إضافة',       'Companies - Create',     11],
    'edit_accounts'         => ['accounts',     'edit',    'الشركات - تعديل',       'Companies - Edit',       12],
    'delete_accounts'       => ['accounts',     'delete',  'الشركات - حذف',         'Companies - Delete',     13],
    'view_users'            => ['users',        'view',    'المستخدمين - عرض',       'Users - View',          14],
    'create_users'          => ['users',        'create',  'المستخدمين - إضافة',     'Users - Create',        15],
    'edit_users'            => ['users',        'edit',    'المستخدمين - تعديل',     'Users - Edit',          16],
    'delete_users'          => ['users',        'delete',  'المستخدمين - حذف',       'Users - Delete',        17],
    'view_roles'            => ['roles',        'view',    'الأدوار - عرض',          'Roles - View',          18],
    'create_roles'          => ['roles',        'create',  'الأدوار - إضافة',        'Roles - Create',        19],
    'edit_roles'            => ['roles',        'edit',    'الأدوار - تعديل',        'Roles - Edit',          20],
    'delete_roles'          => ['roles',        'delete',  'الأدوار - حذف',          'Roles - Delete',        21],
    'view_scan'             => ['scan',         'view',    'الجرد - عرض',            'Scan - View',           22],
    'run_scan'              => ['scan',         'execute', 'الجرد - تنفيذ',          'Scan - Execute',        23],
    'view_reports'          => ['reports',      'view',    'التقارير - عرض',         'Reports - View',        24],
    'view_activity_log'     => ['activity_log', 'view',    'سجل النشاطات - عرض',    'Activity Log - View',   25],
    'manage_translations'   => ['translate',    'edit',    'الترجمة - تعديل',        'Translate - Edit',      26],
    'view_translations'     => ['translate',    'view',    'الترجمة - عرض',          'Translate - View',      27],
    'view_dashboard'        => ['dashboard',    'view',    'البحث - عرض',            'Dashboard - View',      28],
    'view_client_attachments' => ['clients',  'view_attachments', 'العملاء - مشاهدة المرفقات', 'Clients - View Attachments', 29],
    'view_sales_report'      => ['sales_report', 'view', 'تقرير المبيعات - عرض', 'Sales Report - View', 30],
];

$updP = $db->prepare("UPDATE permissions SET module=?, action=?, display_name_ar=?, display_name_en=?, sort_order=? WHERE name=?");
$insP = $db->prepare("INSERT INTO permissions (name, guard_name, module, action, display_name_ar, display_name_en, sort_order, created_at, updated_at) VALUES (?, 'web', ?, ?, ?, ?, ?, NOW(), NOW())");

foreach ($permMeta as $name => $meta) {
    $exists = $db->get_count('permissions', ['name' => $name]);
    if ($exists > 0) {
        $updP->execute([$meta[0], $meta[1], $meta[2], $meta[3], $meta[4], $name]);
    } else {
        $insP->execute([$name, $meta[0], $meta[1], $meta[2], $meta[3], $meta[4]]);
    }
}
echo "OK\n";

// ─── 4. Assign new permissions to roles ──────────────────────────────

echo "[4/5] Assigning permissions to roles... ";

$s = $db->prepare("SELECT id, name FROM permissions"); $s->execute();
$permMap = [];
while ($r = $s->fetch(PDO::FETCH_ASSOC)) $permMap[$r['name']] = (int)$r['id'];

$s = $db->prepare("SELECT id, name FROM roles"); $s->execute();
$roleMap = [];
while ($r = $s->fetch(PDO::FETCH_ASSOC)) $roleMap[$r['name']] = (int)$r['id'];

$allPermNames = array_keys($permMeta);

$roleAssignments = [
    'super_admin'   => $allPermNames,
    'company_admin' => [
        'view_dashboard', 'view_clients', 'create_clients', 'edit_clients', 'delete_clients', 'export_clients',
        'import_clients', 'view_jobs', 'view_violations', 'manage_violations', 'view_client_attachments',
    ],
    'user' => [
        'view_dashboard', 'view_clients', 'create_clients', 'edit_clients', 'export_clients',
        'import_clients', 'view_jobs', 'view_client_attachments',
    ],
    'viewer' => [
        'view_dashboard', 'view_clients', 'view_jobs', 'view_violations',
    ],
];

$ins = $db->prepare("INSERT IGNORE INTO role_has_permissions (permission_id, role_id) VALUES (?, ?)");
foreach ($roleAssignments as $roleName => $permNames) {
    $roleId = $roleMap[$roleName] ?? null;
    if (!$roleId) continue;
    foreach ($permNames as $pn) {
        $pid = $permMap[$pn] ?? null;
        if ($pid) $ins->execute([$pid, $roleId]);
    }
}
echo "OK\n";

// ─── 5. Add role_id to users ─────────────────────────────────────────

echo "[5/5] Adding role_id to users... ";

$colCheck = $db->prepare("SHOW COLUMNS FROM users LIKE 'role_id'"); $colCheck->execute();
if (!$colCheck->fetch()) {
    $db->exec("ALTER TABLE `users` ADD COLUMN `role_id` BIGINT UNSIGNED DEFAULT NULL AFTER `role`");
}

$oldToNew = [
    'Administrator' => $roleMap['super_admin'] ?? null,
    'User'          => $roleMap['user']        ?? null,
    'super_admin'   => $roleMap['super_admin'] ?? null,
    'admin'         => $roleMap['super_admin'] ?? null,
    'company_admin' => $roleMap['company_admin'] ?? null,
    'user'          => $roleMap['user']        ?? null,
    'viewer'        => $roleMap['viewer']      ?? null,
];

$upd = $db->prepare("UPDATE `users` SET `role_id` = ? WHERE `role` = ? AND (`role_id` IS NULL OR `role_id` = 0)");
foreach ($oldToNew as $oldRole => $newRoleId) {
    if ($newRoleId) $upd->execute([$newRoleId, $oldRole]);
}

$defaultRoleId = $roleMap['user'] ?? 4;
$fb = $db->prepare("UPDATE `users` SET `role_id` = ? WHERE `role_id` IS NULL OR `role_id` = 0");
$fb->execute([$defaultRoleId]);

echo "OK\n";

echo "\n=== Migration complete! ===\n";
echo "Roles: " . $db->get_count('roles') . "\n";
echo "Permissions: " . $db->get_count('permissions') . "\n";
$c1 = $db->prepare("SELECT COUNT(*) FROM role_has_permissions"); $c1->execute();
echo "Role-Permission mappings: " . $c1->fetchColumn() . "\n";
$c2 = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id IS NOT NULL"); $c2->execute();
echo "Users migrated: " . $c2->fetchColumn() . "\n";
