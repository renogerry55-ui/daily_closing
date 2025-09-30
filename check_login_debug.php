<?php
require __DIR__ . '/includes/db.php';

$username = 'manager1';
$plain    = 'Manager@123';



$stmt = $pdo->prepare("SELECT id, username, role, password_hash FROM users WHERE username=?");
$stmt->execute([$username]);
$user = $stmt->fetch();

header('Content-Type: text/plain');

if (!$user) {
  echo "USER NOT FOUND\n";
  exit;
}

echo "User found: id={$user['id']} username={$user['username']} role={$user['role']}\n";
echo "Hash length: " . strlen($user['password_hash']) . "\n";
echo "Hash: {$user['password_hash']}\n";

$ok = password_verify($plain, $user['password_hash']);
echo "password_verify('{$plain}', hash) => " . ($ok ? "OK\n" : "FAIL\n");
