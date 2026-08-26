<?php
/**
 * Migration: user roles for eDoc staff (2026-08-22)
 *
 * 1. Create edoc_user_role (our own edoc_* table) — maps ap_users.id -> role.
 * 2. Create the three TEST accounts the team defined (only if missing):
 *      admin@edoc.com    -> system_admin (plus admin override in login)
 *      officer1@edoc.com -> officer
 *      aprover1@edoc.com -> approver
 *    These are ADDITIVE rows in ap_users (new users only; no existing rows touched).
 * 3. Seed edoc_user_role rows for those accounts.
 *
 * Password: test accounts get a placeholder hash matching 'Test@1234'.
 * Team must change passwords after first login (documented).
 */

require_once __DIR__ . '/../connect.php';

$pdo = $conn;
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function run($pdo, $label, $sql, $params = []) {
    echo "$label ... ";
    $pdo->prepare($sql)->execute($params);
    echo "OK\n";
}

echo "=== 1. Create edoc_user_role table ===\n";
run($pdo, "create table", "CREATE TABLE IF NOT EXISTS edoc_user_role (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL COMMENT 'ap_users.id (read-only ref)',
  role         VARCHAR(20) NOT NULL COMMENT 'officer | approver | system_admin',
  off_id       VARCHAR(10) DEFAULT NULL COMMENT 'NULL = all offices of the user',
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_edoc_user_role (user_id),
  KEY idx_edoc_user_role_off (off_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='eDoc role assignment per ap_users user'");

echo "=== 2. Create the three test accounts (additive, only if missing) ===\n";
$hash = password_hash('Test@1234', PASSWORD_DEFAULT);
$accounts = [
    ['admin@edoc.com',    'System Admin (eDoc)', 'system_admin'],
    ['officer1@edoc.com', 'Officer 1 (Tasseng)', 'officer'],
    ['aprover1@edoc.com', 'Approver 1 (eDoc)',   'approver'],
];
foreach ($accounts as [$username, $fullname, $role]) {
    $st = $pdo->prepare("SELECT id FROM ap_users WHERE username = ?");
    $st->execute([$username]);
    $uid = $st->fetchColumn();
    if (!$uid) {
        $pdo->prepare("INSERT INTO ap_users (username, password_hash, full_name, email, role_id, is_active, write_bit, edit_bit, delete_bit)
                       VALUES (?, ?, ?, ?, 3, 1, 0, 0, 0)")->execute([$username, $hash, $fullname, $username]);
        $uid = $pdo->lastInsertId();
        echo "  created ap_users account [$username] id=$uid\n";
    } else {
        echo "  ap_users account [$username] already exists id=$uid (kept)\n";
    }
    // idempotent role row
    $pdo->prepare("INSERT INTO edoc_user_role (user_id, role, off_id, is_active)
                   VALUES (?, ?, NULL, 1)
                   ON DUPLICATE KEY UPDATE role = VALUES(role)")->execute([$uid, $role]);
    echo "  role row for [$username] -> $role (user_id=$uid)\n";
}

echo "\n=== done ===\n";
echo "Test passwords: Test@1234 (all three) — CHANGE AFTER FIRST LOGIN.\n";