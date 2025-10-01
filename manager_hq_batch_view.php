<?php
// /daily_closing/manager_hq_batch_view.php
require __DIR__ . '/includes/auth_guard.php';
require __DIR__ . '/includes/db.php';

guard_manager();
$managerId = current_manager_id();

$batchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($batchId <= 0) {
    http_response_code(404);
    exit('HQ batch not found.');
}

$sql = "
    SELECT b.id, b.report_date, b.status, b.overall_total_income, b.overall_total_expenses, b.overall_balance, b.notes
    FROM hq_batches b
    WHERE b.id = ? AND b.manager_id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$batchId, $managerId]);
$batch = $stmt->fetch();

if (!$batch) {
    http_response_code(404);
    exit('HQ batch not found.');
}

$stmtSub = $pdo->prepare("\
    SELECT s.id, s.date, s.total_income, s.total_expenses, s.balance, s.status, s.submitted_to_hq_at,\
           o.name AS outlet_name\
    FROM hq_batch_submissions hbs\
    JOIN submissions s ON s.id = hbs.submission_id\
    JOIN outlets o ON o.id = s.outlet_id\
    WHERE hbs.hq_batch_id = ?\
    ORDER BY o.name, s.date, s.id\
");
$stmtSub->execute([$batchId]);
$submissions = $stmtSub->fetchAll() ?: [];

$submittedAt = null;
$outletTotals = [];
foreach ($submissions as $row) {
    if (!empty($row['submitted_to_hq_at'])) {
        $ts = strtotime($row['submitted_to_hq_at']);
        if ($ts && ($submittedAt === null || $ts > $submittedAt)) {
            $submittedAt = $ts;
        }
    }
    $outlet = $row['outlet_name'];
    if (!isset($outletTotals[$outlet])) {
        $outletTotals[$outlet] = [
            'income'   => 0.0,
            'expenses' => 0.0,
            'balance'  => 0.0,
            'submissions' => [],
        ];
    }
    $outletTotals[$outlet]['income']   += (float)$row['total_income'];
    $outletTotals[$outlet]['expenses'] += (float)$row['total_expenses'];
    $outletTotals[$outlet]['balance']  += (float)$row['balance'];
    $outletTotals[$outlet]['submissions'][] = $row;
}

if ($outletTotals) {
    ksort($outletTotals, SORT_NATURAL | SORT_FLAG_CASE);
}

$stmtFiles = $pdo->prepare("SELECT file_path, original_name, mime, size_bytes FROM hq_batch_files WHERE hq_batch_id = ? ORDER BY original_name");
$stmtFiles->execute([$batchId]);
$files = $stmtFiles->fetchAll() ?: [];

require __DIR__ . '/views/manager_hq_batch_view.php';
