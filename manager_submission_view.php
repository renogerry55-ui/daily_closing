<?php
// /daily_closing/manager_submission_view.php
require __DIR__ . '/includes/auth_guard.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/cash_metrics.php';

guard_manager();
$managerId = current_manager_id();

$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($submissionId <= 0) {
    http_response_code(404);
    exit('Submission not found.');
}

// Load submission header ensuring it belongs to current manager
$sql = "SELECT s.id, s.date, s.status, s.total_income, s.total_expenses, s.balance, s.pass_to_office, s.notes,
               s.outlet_id, o.name AS outlet_name
        FROM submissions s
        JOIN outlets o ON o.id = s.outlet_id
        WHERE s.id = ? AND s.manager_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$submissionId, $managerId]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    exit('Submission not found.');
}

// Load line items
$stmtItems = $pdo->prepare("SELECT type, category, amount, description FROM submission_items WHERE submission_id = ? ORDER BY type, category, amount");
$stmtItems->execute([$submissionId]);
$items = $stmtItems->fetchAll() ?: [];

$incomeItems = [];
$expenseItems = [];
foreach ($items as $item) {
    if ($item['type'] === 'income') {
        $incomeItems[] = $item;
    } elseif ($item['type'] === 'expense') {
        $expenseItems[] = $item;
    }
}

// Load receipts
$stmtRec = $pdo->prepare("SELECT file_path, original_name, size_bytes FROM receipts WHERE submission_id = ? ORDER BY original_name");
$stmtRec->execute([$submissionId]);
$receipts = $stmtRec->fetchAll() ?: [];

$cohBefore = outlet_posted_cash_on_hand($pdo, $managerId, (int)$submission['outlet_id']);
$netChange = (float)$submission['total_income'] - (float)$submission['total_expenses'] - (float)$submission['pass_to_office'];
if (in_array((string)$submission['status'], ['approved', 'recorded'], true)) {
    $cohBefore -= $netChange;
}
$cohAfter = $cohBefore + $netChange;

$submission['coh_before'] = $cohBefore;
$submission['coh_after'] = $cohAfter;
$submission['net_change'] = $netChange;
$submission['receipts_count'] = count($receipts);

require __DIR__ . '/views/manager_submission_view.php';
