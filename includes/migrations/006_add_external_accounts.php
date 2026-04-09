<?php
/**
 * Migration 006: Add external/remote companies to accounts table.
 * These are the companies accessed via remote APIs (Tayseer, Zajal, Bseel).
 * Safe to run multiple times.
 */
if (!isset($db)) return;

$db->exec("CREATE TABLE IF NOT EXISTS `fahras_migrations` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `ran_at` DATETIME DEFAULT CURRENT_TIMESTAMP)");

$marker = 'migration_006_external_accounts';
$chk = $db->prepare("SELECT COUNT(*) FROM `fahras_migrations` WHERE `name` = ?");
$chk->execute([$marker]);
if ($chk->fetchColumn() > 0) return;

$externals = [
    ['name' => 'جدل',  'type' => 'company', 'status' => 'active'],
    ['name' => 'نماء',  'type' => 'company', 'status' => 'active'],
    ['name' => 'بسيل', 'type' => 'company', 'status' => 'active'],
    ['name' => 'وتر',  'type' => 'company', 'status' => 'active'],
    ['name' => 'زجل',  'type' => 'company', 'status' => 'active'],
];

$stmt = $db->prepare("INSERT INTO accounts (name, type, status) SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE name = ?)");
foreach ($externals as $ext) {
    $stmt->execute([$ext['name'], $ext['type'], $ext['status'], $ext['name']]);
}

$ins = $db->prepare("INSERT INTO `fahras_migrations` (`name`) VALUES (?)");
$ins->execute([$marker]);
