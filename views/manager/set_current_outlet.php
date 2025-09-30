<?php
require __DIR__ . '/../../includes/auth.php';
require_role(['manager']);

$allowed = $_SESSION['allowed_outlets'] ?? [];
$chosen  = isset($_POST['outlet_id']) ? (int)$_POST['outlet_id'] : 0;

if (!$allowed || !$chosen || !in_array($chosen, $allowed, true)) {
    http_response_code(400);
    echo 'Invalid outlet selection.';
    exit;
}

$_SESSION['outlet_id'] = $chosen;
header('Location: /daily_closing/views/manager/dashboard.php');
exit;
