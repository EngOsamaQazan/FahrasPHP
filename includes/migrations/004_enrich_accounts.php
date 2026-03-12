<?php
/**
 * Migration 004: Enrich accounts table with business fields
 * Safe to run multiple times.
 */
if (!isset($db)) return;

$db->exec("CREATE TABLE IF NOT EXISTS `fahras_migrations` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NOT NULL, `ran_at` DATETIME DEFAULT CURRENT_TIMESTAMP)");

$marker = 'migration_004_enrich_accounts';
$chk = $db->prepare("SELECT COUNT(*) FROM `fahras_migrations` WHERE `name` = ?");
$chk->execute([$marker]);
if ($chk->fetchColumn() > 0) return;

$colStmt = $db->query("SHOW COLUMNS FROM accounts");
$columns = array_column($colStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

$adds = [];
if (!in_array('email', $columns))              $adds[] = "ADD COLUMN `email` VARCHAR(255) DEFAULT NULL AFTER `address`";
if (!in_array('website', $columns))            $adds[] = "ADD COLUMN `website` VARCHAR(255) DEFAULT NULL AFTER `email`";
if (!in_array('tax_number', $columns))         $adds[] = "ADD COLUMN `tax_number` VARCHAR(50) DEFAULT NULL AFTER `website`";
if (!in_array('type', $columns))               $adds[] = "ADD COLUMN `type` ENUM('individual','company','institution') DEFAULT 'company' AFTER `tax_number`";
if (!in_array('status', $columns))             $adds[] = "ADD COLUMN `status` ENUM('active','suspended','expired') DEFAULT 'active' AFTER `type`";
if (!in_array('subscription_start', $columns)) $adds[] = "ADD COLUMN `subscription_start` DATE DEFAULT NULL AFTER `status`";
if (!in_array('subscription_end', $columns))   $adds[] = "ADD COLUMN `subscription_end` DATE DEFAULT NULL AFTER `subscription_start`";
if (!in_array('notes', $columns))              $adds[] = "ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `subscription_end`";
if (!in_array('contact_person', $columns))     $adds[] = "ADD COLUMN `contact_person` VARCHAR(255) DEFAULT NULL AFTER `notes`";
if (!in_array('city', $columns))               $adds[] = "ADD COLUMN `city` VARCHAR(100) DEFAULT NULL AFTER `contact_person`";
if (!in_array('created_at', $columns))         $adds[] = "ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `city`";

if (!empty($adds)) {
    $db->exec("ALTER TABLE `accounts` " . implode(", ", $adds));
}

$ins = $db->prepare("INSERT INTO `fahras_migrations` (`name`) VALUES (?)");
$ins->execute([$marker]);
