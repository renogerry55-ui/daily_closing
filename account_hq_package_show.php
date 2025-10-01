<?php
require __DIR__ . '/includes/auth.php';
require_role(['account']);
require __DIR__ . '/includes/db.php';

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

$sqlItems = "SELECT hpi.id, hpi.pass_to_hq, "
    . "s.date, s.total_income, s.total_expenses, s.balance, "
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

function format_money(?float $amount): string {
    if ($amount === null) {
        return '-';
    }
    return number_format((float)$amount, 2);
}

$packageDate = $package['package_date'] ?? $package['created_at'] ?? null;
$packageDate = $packageDate ? date('Y-m-d', strtotime($packageDate)) : '—';
$approvedAt = $package['approved_at'] ?? null;
$approvedAt = $approvedAt ? date('Y-m-d H:i', strtotime($approvedAt)) : '—';
$status = $package['status'] ?? 'pending';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HQ Package #<?= htmlspecialchars((string)$packageId) ?> — Account</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom mb-4">
  <div class="container">
    <a class="navbar-brand" href="/daily_closing/views/account/queue.php">Daily Closing</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/views/account/queue.php">Back to Queue</a>
      <a class="btn btn-outline-danger btn-sm" href="/daily_closing/logout.php">Logout</a>
    </div>
  </div>
</nav>
<main class="container pb-5">
  <div class="d-flex flex-wrap align-items-start gap-3 mb-4">
    <div>
      <h1 class="h3 mb-1">HQ Package #<?= htmlspecialchars((string)$packageId) ?></h1>
      <div class="text-muted small">Status: <?= htmlspecialchars(ucfirst((string)$status)) ?></div>
    </div>
    <div class="ms-auto d-flex flex-wrap gap-2">
      <a class="btn btn-success" href="/daily_closing/account_hq_package_export.php?package_id=<?= urlencode((string)$packageId) ?>">
        Export to Excel
      </a>
    </div>
  </div>

  <section class="card shadow-sm mb-4">
    <div class="card-header bg-white">
      <strong>Package Details</strong>
    </div>
    <div class="card-body row g-3">
      <div class="col-md-3">
        <div class="text-muted small">Package Date</div>
        <div class="fw-semibold"><?= htmlspecialchars($packageDate) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Created By</div>
        <div class="fw-semibold"><?= htmlspecialchars($package['created_by_name'] ?? '—') ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Approved By</div>
        <div class="fw-semibold"><?= htmlspecialchars($package['approved_by_name'] ?? '—') ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Approved At</div>
        <div class="fw-semibold"><?= htmlspecialchars($approvedAt) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Total Income (RM)</div>
        <div class="fw-semibold"><?= format_money($package['total_income'] ?? $totals['income']) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Total Expenses (RM)</div>
        <div class="fw-semibold"><?= format_money($package['total_expenses'] ?? $totals['expenses']) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Total Balance (RM)</div>
        <div class="fw-semibold"><?= format_money($package['total_balance'] ?? $totals['balance']) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small">Total Pass to HQ (RM)</div>
        <div class="fw-semibold"><?= format_money($totals['pass_to_hq']) ?></div>
      </div>
    </div>
  </section>

  <section class="card shadow-sm">
    <div class="card-header bg-white">
      <strong>Outlet Breakdown</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Outlet</th>
              <th>Manager</th>
              <th style="width:120px;">Date</th>
              <th class="text-end" style="width:140px;">Income (RM)</th>
              <th class="text-end" style="width:140px;">Expenses (RM)</th>
              <th class="text-end" style="width:140px;">Balance (RM)</th>
              <th class="text-end" style="width:150px;">Pass to HQ (RM)</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$items): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">No submissions linked to this package.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($items as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['outlet_name'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['manager_name'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
                  <td class="text-end"><?= format_money($row['total_income'] ?? null) ?></td>
                  <td class="text-end"><?= format_money($row['total_expenses'] ?? null) ?></td>
                  <td class="text-end"><?= format_money($row['balance'] ?? null) ?></td>
                  <td class="text-end"><?= format_money($row['pass_to_hq'] ?? null) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if ($items): ?>
          <tfoot class="table-light">
            <tr>
              <th colspan="3" class="text-end">Totals</th>
              <th class="text-end"><?= format_money($totals['income']) ?></th>
              <th class="text-end"><?= format_money($totals['expenses']) ?></th>
              <th class="text-end"><?= format_money($totals['balance']) ?></th>
              <th class="text-end"><?= format_money($totals['pass_to_hq']) ?></th>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    </div>
  </section>
</main>
</body>
</html>
