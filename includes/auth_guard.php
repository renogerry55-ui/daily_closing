<?php
// /daily_closing/includes/auth_guard.php
require_once __DIR__ . '/auth.php';

function guard_manager(): void {
    require_role(['manager']);
    // No outlet required at login anymore.
}

function current_manager_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

/** Return array of outlets the manager can use: [ ['id'=>1,'name'=>'...'], ... ] */
function allowed_outlets(PDO $pdo): array {
    $uid = current_manager_id();
    $stmt = $pdo->prepare("
        SELECT o.id, o.name
        FROM user_outlets uo
        JOIN outlets o ON o.id = uo.outlet_id
        WHERE uo.user_id = ?
        ORDER BY o.name
    ");
    $stmt->execute([$uid]);
    return $stmt->fetchAll() ?: [];
}

/** Throw/exit if the outlet_id doesn’t belong to this manager */
function assert_outlet_belongs_to_manager(PDO $pdo, int $outlet_id): void {
    $uid = current_manager_id();
    $stmt = $pdo->prepare("SELECT 1 FROM user_outlets WHERE user_id=? AND outlet_id=?");
    $stmt->execute([$uid, $outlet_id]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        exit('Forbidden: outlet not assigned to you.');
    }
}
