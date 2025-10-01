<?php
require __DIR__ . '/includes/auth.php';
require_role(['account']);
require __DIR__ . '/includes/db.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Spreadsheet library not installed.';
    exit;
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
if (!$batchId) {
    // Backwards compatibility with previous query string.
    $batchId = filter_input(INPUT_GET, 'package_id', FILTER_VALIDATE_INT);
}

if (!$batchId) {
    http_response_code(400);
    echo 'Invalid batch id.';
    exit;
}

$sqlBatch = "SELECT b.*, m.name AS manager_name, m.email AS manager_email "
    . "FROM hq_batches b "
    . "INNER JOIN users m ON m.id = b.manager_id "
    . "WHERE b.id = ?";
$stmtBatch = $pdo->prepare($sqlBatch);
$stmtBatch->execute([$batchId]);
$batch = $stmtBatch->fetch();

if (!$batch) {
    http_response_code(404);
    echo 'HQ batch not found.';
    exit;
}

$sqlItems = "SELECT s.id AS submission_id, s.date, s.total_income, s.total_expenses, s.balance, s.status, s.submitted_to_hq_at, "
    . "o.name AS outlet_name, mgr.name AS submission_manager "
    . "FROM hq_batch_submissions hbs "
    . "INNER JOIN submissions s ON s.id = hbs.submission_id "
    . "INNER JOIN outlets o ON o.id = s.outlet_id "
    . "INNER JOIN users mgr ON mgr.id = s.manager_id "
    . "WHERE hbs.hq_batch_id = ? "
    . "ORDER BY o.name ASC, s.date ASC, s.id ASC";
$stmtItems = $pdo->prepare($sqlItems);
$stmtItems->execute([$batchId]);
$items = $stmtItems->fetchAll() ?: [];

$sqlReceipts = "SELECT r.submission_id, r.original_name, r.file_path "
    . "FROM receipts r "
    . "INNER JOIN hq_batch_submissions hbs ON hbs.submission_id = r.submission_id "
    . "WHERE hbs.hq_batch_id = ? "
    . "ORDER BY r.original_name";
$stmtReceipts = $pdo->prepare($sqlReceipts);
$stmtReceipts->execute([$batchId]);
$receipts = $stmtReceipts->fetchAll() ?: [];

$sqlAttachments = "SELECT original_name, file_path, size_bytes, created_at "
    . "FROM hq_batch_files WHERE hq_batch_id = ? ORDER BY original_name";
$stmtAttachments = $pdo->prepare($sqlAttachments);
$stmtAttachments->execute([$batchId]);
$attachments = $stmtAttachments->fetchAll() ?: [];

$totals = [
    'income' => 0.0,
    'expenses' => 0.0,
    'balance' => 0.0,
];
foreach ($items as $row) {
    $totals['income'] += (float)($row['total_income'] ?? 0);
    $totals['expenses'] += (float)($row['total_expenses'] ?? 0);
    $totals['balance'] += (float)($row['balance'] ?? 0);
}

$submittedAt = null;
foreach ($items as $row) {
    $ts = !empty($row['submitted_to_hq_at']) ? strtotime($row['submitted_to_hq_at']) : null;
    if ($ts && ($submittedAt === null || $ts > $submittedAt)) {
        $submittedAt = $ts;
    }
}
$submittedAtStr = $submittedAt ? date('Y-m-d H:i', $submittedAt) : null;

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$sheet1 = new Worksheet($spreadsheet, 'Batch');
$spreadsheet->addSheet($sheet1, 0);
$sheet1->fromArray([
    ['Batch ID', $batch['id']],
    ['Business Date', $batch['report_date'] ?? null],
    ['Manager', $batch['manager_name'] ?? null],
    ['Manager Email', $batch['manager_email'] ?? null],
    ['Status', $batch['status'] ?? null],
    ['Submitted At', $submittedAtStr],
    ['Created At', $batch['created_at'] ?? null],
    ['Updated At', $batch['updated_at'] ?? null],
    ['Notes', $batch['notes'] ?? null],
    ['Total Income (RM)', $batch['overall_total_income'] ?? $totals['income']],
    ['Total Expenses (RM)', $batch['overall_total_expenses'] ?? $totals['expenses']],
    ['Total Balance (RM)', $batch['overall_balance'] ?? $totals['balance']],
], null, 'A1');
$sheet1->getColumnDimension('A')->setAutoSize(true);
$sheet1->getColumnDimension('B')->setAutoSize(true);

$sheet2 = new Worksheet($spreadsheet, 'Submissions');
$spreadsheet->addSheet($sheet2, 1);
$sheet2->fromArray([
    ['Submission ID', 'Date', 'Outlet', 'Manager', 'Income (RM)', 'Expenses (RM)', 'Balance (RM)', 'Status', 'Submitted At'],
], null, 'A1');
$rowNum = 2;
foreach ($items as $row) {
    $sheet2->fromArray([
        [
            $row['submission_id'] ?? null,
            $row['date'] ?? null,
            $row['outlet_name'] ?? null,
            $row['submission_manager'] ?? null,
            $row['total_income'] ?? null,
            $row['total_expenses'] ?? null,
            $row['balance'] ?? null,
            $row['status'] ?? null,
            !empty($row['submitted_to_hq_at']) ? date('Y-m-d H:i', strtotime($row['submitted_to_hq_at'])) : null,
        ],
    ], null, 'A' . $rowNum);
    $rowNum++;
}
$sheet2->fromArray([
    ['Totals', null, null, null, $totals['income'], $totals['expenses'], $totals['balance'], null, null],
], null, 'A' . $rowNum);
$sheet2->getStyle('A1:I1')->getFont()->setBold(true);
$sheet2->getStyle('A' . $rowNum . ':I' . $rowNum)->getFont()->setBold(true);
foreach (range('A', 'I') as $col) {
    $sheet2->getColumnDimension($col)->setAutoSize(true);
}

if ($receipts) {
    $sheet3 = new Worksheet($spreadsheet, 'Receipts');
    $spreadsheet->addSheet($sheet3, 2);
    $sheet3->fromArray([
        ['Submission ID', 'Original Name', 'File Path'],
    ], null, 'A1');
    $rowNum = 2;
    foreach ($receipts as $rec) {
        $sheet3->fromArray([
            [
                $rec['submission_id'] ?? null,
                $rec['original_name'] ?? null,
                $rec['file_path'] ?? null,
            ],
        ], null, 'A' . $rowNum);
        $rowNum++;
    }
    $sheet3->getStyle('A1:C1')->getFont()->setBold(true);
    foreach (range('A', 'C') as $col) {
        $sheet3->getColumnDimension($col)->setAutoSize(true);
    }
}

if ($attachments) {
    $sheet = new Worksheet($spreadsheet, 'HQ Attachments');
    $spreadsheet->addSheet($sheet, $spreadsheet->getSheetCount());
    $sheet->fromArray([
        ['Original Name', 'File Path', 'Size (bytes)', 'Uploaded At'],
    ], null, 'A1');
    $rowNum = 2;
    foreach ($attachments as $file) {
        $sheet->fromArray([
            [
                $file['original_name'] ?? null,
                $file['file_path'] ?? null,
                $file['size_bytes'] ?? null,
                $file['created_at'] ?? null,
            ],
        ], null, 'A' . $rowNum);
        $rowNum++;
    }
    $sheet->getStyle('A1:D1')->getFont()->setBold(true);
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

$spreadsheet->setActiveSheetIndex(0);

$filename = 'hq-batch-' . $batchId . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
