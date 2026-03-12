<?php
/**
 * Migration 005: Seed accounts data from local database
 * Inserts the 7 companies that exist locally but were deleted on production.
 * Safe to run multiple times.
 */
if (!isset($db)) return;

$db->exec("CREATE TABLE IF NOT EXISTS `fahras_migrations` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `ran_at` DATETIME DEFAULT CURRENT_TIMESTAMP)");

$marker = 'migration_005_seed_accounts';
$chk = $db->prepare("SELECT COUNT(*) FROM `fahras_migrations` WHERE `name` = ?");
$chk->execute([$marker]);
if ($chk->fetchColumn() > 0) return;

$count = $db->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
if ($count > 0) {
    $ins = $db->prepare("INSERT INTO `fahras_migrations` (`name`) VALUES (?)");
    $ins->execute([$marker]);
    return;
}

$accounts = [
    ['id' => 1, 'name' => 'قصائد',       'phone' => '0798965240', 'mobile' => null,          'address' => 'عمان'],
    ['id' => 2, 'name' => 'نايف قازان',   'phone' => '0788860065', 'mobile' => null,          'address' => 'المفرق'],
    ['id' => 3, 'name' => 'المرجع',       'phone' => '0777721181', 'mobile' => null,          'address' => 'المفرق'],
    ['id' => 4, 'name' => 'الناظرين',     'phone' => '0787992887', 'mobile' => '0777761078',  'address' => 'اربد'],
    ['id' => 5, 'name' => 'عالم المجد',   'phone' => '0777494444', 'mobile' => '0787494444',  'address' => 'المفرق'],
    ['id' => 6, 'name' => 'زيد الطوالع',  'phone' => '96277946370','mobile' => '96277946370', 'address' => 'اربد'],
    ['id' => 7, 'name' => 'عاصم قازان',   'phone' => "\u{202D}+962 7 9924 44", 'mobile' => null, 'address' => 'الزرقاء'],
];

$stmt = $db->prepare("INSERT INTO accounts (id, name, phone, mobile, address) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
foreach ($accounts as $a) {
    $stmt->execute([$a['id'], $a['name'], $a['phone'], $a['mobile'], $a['address']]);
}

$db->exec("ALTER TABLE accounts AUTO_INCREMENT = 8");

$ins = $db->prepare("INSERT INTO `fahras_migrations` (`name`) VALUES (?)");
$ins->execute([$marker]);
