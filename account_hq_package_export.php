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

$packageId = filter_input(INPUT_GET, 'package_id', FILTER_VALIDATE_INT);
if (!$packageId) {
    http_response_code(400);
    echo 'Invalid package id.';
    exit;
}

$sqlPackage = "SELECT p.*, "
    . "creator.name AS created_by_name, "
    . "approver.name AS approved_by_name "
    . "FROM hq_packages p "
    . "LEFT JOIN users creator ON creator.id = p.created_by "
    . "LEFT JOIN users approver ON approver.id = p.approved_by "
    . "WHERE p.id = ?";
$stmtPkg = $pdo->prepare($sqlPackage);
$stmtPkg->execute([$packageId]);
$package = $stmtPkg->fetch();

if (!$package) {
    http_response_code(404);
    echo 'Package not found.';
    exit;
}

$sqlItems = "SELECT hpi.pass_to_hq, s.id AS submission_id, s.date, s.total_income, s.total_expenses, s.balance, "
    . "o.name AS outlet_name, m.name AS manager_name "
    . "FROM hq_package_items hpi "
    . "INNER JOIN submissions s ON s.id = hpi.submission_id "
    . "INNER JOIN outlets o ON o.id = s.outlet_id "
    . "INNER JOIN users m ON m.id = s.manager_id "
    . "WHERE hpi.package_id = ? "
    . "ORDER BY o.name ASC, s.date ASC";
$stmtItems = $pdo->prepare($sqlItems);
$stmtItems->execute([$packageId]);
$items = $stmtItems->fetchAll();

$sqlReceipts = "SELECT r.submission_id, r.original_name, r.file_path "
    . "FROM receipts r "
    . "INNER JOIN hq_package_items hpi ON hpi.submission_id = r.submission_id "
    . "WHERE hpi.package_id = ? "
    . "ORDER BY r.original_name";
$stmtReceipts = $pdo->prepare($sqlReceipts);
$stmtReceipts->execute([$packageId]);
$receipts = $stmtReceipts->fetchAll();

$totals = [
    'income' => 0.0,
    'expenses' => 0.0,
    'balance' => 0.0,
    'pass_to_hq' => 0.0,
];
foreach ($items as $row) {
    $totals['income'] += (float)($row['total_income'] ?? 0);
    $totals['expenses'] += (float)($row['total_expenses'] ?? 0);
    $totals['balance'] += (float)($row['balance'] ?? 0);
    $totals['pass_to_hq'] += (float)($row['pass_to_hq'] ?? 0);
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$sheet1 = new Worksheet($spreadsheet, 'Package');
$spreadsheet->addSheet($sheet1, 0);
$sheet1->fromArray([
    ['Package ID', $package['id']],
    ['Package Date', $package['package_date'] ?? null],
    ['Created By', $package['created_by_name'] ?? null],
    ['Approved By', $package['approved_by_name'] ?? null],
    ['Approved At', $package['approved_at'] ?? null],
    ['Status', $package['status'] ?? null],
    ['Total Income (RM)', $package['total_income'] ?? $totals['income']],
    ['Total Expenses (RM)', $package['total_expenses'] ?? $totals['expenses']],
    ['Total Balance (RM)', $package['total_balance'] ?? $totals['balance']],
    ['Total Pass to HQ (RM)', $totals['pass_to_hq']],
], null, 'A1');
$sheet1->getColumnDimension('A')->setAutoSize(true);
$sheet1->getColumnDimension('B')->setAutoSize(true);

$sheet2 = new Worksheet($spreadsheet, 'Outlet Breakdown');
$spreadsheet->addSheet($sheet2, 1);
$sheet2->fromArray([
    ['Outlet', 'Manager', 'Date', 'Income (RM)', 'Expenses (RM)', 'Balance (RM)', 'Pass to HQ (RM)'],
], null, 'A1');
$rowNum = 2;
foreach ($items as $row) {
    $sheet2->fromArray([
        [
            $row['outlet_name'] ?? null,
            $row['manager_name'] ?? null,
            $row['date'] ?? null,
            $row['total_income'] ?? null,
            $row['total_expenses'] ?? null,
            $row['balance'] ?? null,
            $row['pass_to_hq'] ?? null,
        ],
    ], null, 'A' . $rowNum);
    $rowNum++;
}
$sheet2->fromArray([
    ['Totals', null, null, $totals['income'], $totals['expenses'], $totals['balance'], $totals['pass_to_hq']],
], null, 'A' . $rowNum);
$sheet2->getStyle('A1:G1')->getFont()->setBold(true);
$sheet2->getStyle('A' . $rowNum . ':G' . $rowNum)->getFont()->setBold(true);
foreach (range('A', 'G') as $col) {
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

$spreadsheet->setActiveSheetIndex(0);

$filename = 'hq-package-' . $packageId . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
