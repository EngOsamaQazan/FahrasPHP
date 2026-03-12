<?php
/**
 * Migration: Remove 'admin' (مدير) role.
 * - Reassigns any users with 'admin' role to 'super_admin'
 * - Deletes admin role permissions and the role itself
 *
 * Safe to run multiple times.
 * Run: php includes/migrations/003_remove_admin_role.php
 */

require_once __DIR__ . '/../smplPDO.php';

$db = new smplPDO("mysql:host=localhost;dbname=fahras_db", "root", "");

echo "=== Remove 'admin' Role Migration ===\n\n";

$adminRole = $db->get_row('roles', ['name' => 'admin']);
if (!$adminRole) {
    echo "Role 'admin' not found — nothing to do.\n";
    exit;
}
$adminRoleId = (int)$adminRole['id'];

$superAdminRole = $db->get_row('roles', ['name' => 'super_admin']);
if (!$superAdminRole) {
    echo "ERROR: 'super_admin' role not found. Cannot proceed.\n";
    exit(1);
}
$superAdminRoleId = (int)$superAdminRole['id'];

echo "[1/3] Reassigning users from 'admin' to 'super_admin'... ";
$stmt = $db->prepare("UPDATE users SET role_id = ? WHERE role_id = ?");
$stmt->execute([$superAdminRoleId, $adminRoleId]);
$affected = $stmt->rowCount();
echo "OK ({$affected} users moved)\n";

echo "[2/3] Removing admin role permissions... ";
$db->prepare("DELETE FROM role_has_permissions WHERE role_id = ?")->execute([$adminRoleId]);
echo "OK\n";

echo "[3/3] Deleting 'admin' role... ";
$db->prepare("DELETE FROM roles WHERE id = ?")->execute([$adminRoleId]);
echo "OK\n";

echo "\n=== Migration complete! ===\n";
