<?php
// /daily_closing/manager_hq_batches.php
require __DIR__ . '/includes/auth_guard.php';
require __DIR__ . '/includes/db.php';

guard_manager();
$managerId = current_manager_id();

$status = $_GET['status'] ?? 'all';
$allowedStatus = ['all', 'submitted', 'processing', 'acknowledged', 'rejected', 'completed'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'all';
}

$where = ['b.manager_id = ?'];
$args  = [$managerId];
if ($status !== 'all') {
    $where[] = 'b.status = ?';
    $args[]  = $status;
}
$WHERE = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT
        b.id,
        b.report_date,
        b.status,
        b.overall_total_income,
        b.overall_total_expenses,
        b.overall_pass_to_office,
        b.overall_balance,
        b.notes,
        COUNT(DISTINCT s.outlet_id)   AS outlet_count,
        COUNT(bs.submission_id)       AS submission_count,
        MAX(s.submitted_to_hq_at)     AS submitted_at
    FROM hq_batches b
    LEFT JOIN hq_batch_submissions bs ON bs.hq_batch_id = b.id
    LEFT JOIN submissions s ON s.id = bs.submission_id
    $WHERE
    GROUP BY b.id
    ORDER BY b.report_date DESC, b.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$batches = $stmt->fetchAll() ?: [];

require __DIR__ . '/views/manager_hq_batches.php';
