<?php
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from the command line.\n");
    exit(1);
}

require __DIR__ . '/../includes/db.php';

$username = $argv[1] ?? null;
$plain    = $argv[2] ?? null;

if ($username === null || $plain === null) {
    $script = basename(__FILE__);
    fwrite(STDERR, "Usage: php {$script} <username> <plain-text-password>\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id, username, role, password_hash FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    fwrite(STDOUT, "USER NOT FOUND\n");
    exit(0);
}

echo "User found: id={$user['id']} username={$user['username']} role={$user['role']}\n";
echo 'Hash length: ' . strlen($user['password_hash']) . "\n";
echo "Hash: {$user['password_hash']}\n";

$ok = password_verify($plain, $user['password_hash']);
echo "password_verify('{$plain}', hash) => " . ($ok ? "OK\n" : "FAIL\n");
