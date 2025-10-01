<?php
require __DIR__ . '/includes/auth.php';
require_role(['account']);
require __DIR__ . '/includes/db.php';

$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
if (!$batchId) {
    // Backwards compatibility with the old query string.
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

$sqlFiles = "SELECT id, file_path, original_name, mime, size_bytes, created_at "
    . "FROM hq_batch_files WHERE hq_batch_id = ? ORDER BY original_name";
$stmtFiles = $pdo->prepare($sqlFiles);
$stmtFiles->execute([$batchId]);
$attachments = $stmtFiles->fetchAll() ?: [];

$totals = [
    'income' => 0.0,
    'expenses' => 0.0,
    'balance' => 0.0,
];
$submittedAt = null;
$outletTotals = [];

foreach ($items as $row) {
    $totals['income'] += (float)($row['total_income'] ?? 0);
    $totals['expenses'] += (float)($row['total_expenses'] ?? 0);
    $totals['balance'] += (float)($row['balance'] ?? 0);

    $ts = !empty($row['submitted_to_hq_at']) ? strtotime($row['submitted_to_hq_at']) : null;
    if ($ts && ($submittedAt === null || $ts > $submittedAt)) {
        $submittedAt = $ts;
    }

    $outlet = $row['outlet_name'] ?? '—';
    if (!isset($outletTotals[$outlet])) {
        $outletTotals[$outlet] = [
            'income' => 0.0,
            'expenses' => 0.0,
            'balance' => 0.0,
        ];
    }
    $outletTotals[$outlet]['income'] += (float)($row['total_income'] ?? 0);
    $outletTotals[$outlet]['expenses'] += (float)($row['total_expenses'] ?? 0);
    $outletTotals[$outlet]['balance'] += (float)($row['balance'] ?? 0);
}

if ($outletTotals) {
    ksort($outletTotals, SORT_NATURAL | SORT_FLAG_CASE);
}

function format_money(?float $amount): string
{
    if ($amount === null) {
        return '-';
    }

    return number_format((float) $amount, 2);
}

$submittedAtStr = $submittedAt ? date('Y-m-d H:i', $submittedAt) : '—';
$status = $batch['status'] ?? 'submitted';
function format_size(?float $bytes): string
{
    if ($bytes === null) {
        return '—';
    }

    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format($bytes, 0) . ' B';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HQ Batch #<?= htmlspecialchars((string) $batchId) ?> — Account</title>
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
      <h1 class="h3 mb-1">HQ Batch #<?= htmlspecialchars((string) $batchId) ?></h1>
      <div class="text-muted small">Status: <?= htmlspecialchars(ucfirst((string) $status)) ?></div>
    </div>
    <div class="ms-auto d-flex flex-wrap gap-2">
      <a class="btn btn-success" href="/daily_closing/account_hq_package_export.php?batch_id=<?= urlencode((string) $batchId) ?>">
        Export to Excel
      </a>
    </div>
  </div>

  <section class="card shadow-sm mb-4">
    <div class="card-header bg-white">
      <strong>Batch Overview</strong>
    </div>
    <div class="card-body row g-3">
      <div class="col-md-3">
        <div class="text-muted small text-uppercase">Business Date</div>
        <div class="fw-semibold"><?= htmlspecialchars($batch['report_date'] ?? '—') ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small text-uppercase">Manager</div>
        <div class="fw-semibold"><?= htmlspecialchars($batch['manager_name'] ?? '—') ?></div>
        <?php if (!empty($batch['manager_email'])): ?>
          <div class="text-muted small"><?= htmlspecialchars($batch['manager_email']) ?></div>
        <?php endif; ?>
      </div>
      <div class="col-md-3">
        <div class="text-muted small text-uppercase">Submitted At</div>
        <div class="fw-semibold"><?= htmlspecialchars($submittedAtStr) ?></div>
      </div>
      <div class="col-md-3">
        <div class="text-muted small text-uppercase">Last Updated</div>
        <div class="fw-semibold"><?= htmlspecialchars($batch['updated_at'] ?? '—') ?></div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small text-uppercase">Total Income (RM)</div>
        <div class="fw-semibold fs-5"><?= format_money($batch['overall_total_income'] ?? $totals['income']) ?></div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small text-uppercase">Total Expenses (RM)</div>
        <div class="fw-semibold fs-5"><?= format_money($batch['overall_total_expenses'] ?? $totals['expenses']) ?></div>
      </div>
      <div class="col-md-4">
        <div class="text-muted small text-uppercase">Total Balance (RM)</div>
        <div class="fw-semibold fs-5"><?= format_money($batch['overall_balance'] ?? $totals['balance']) ?></div>
      </div>
      <?php if (!empty($batch['notes'])): ?>
        <div class="col-12">
          <div class="text-muted small text-uppercase">Notes</div>
          <div><?= nl2br(htmlspecialchars($batch['notes'])) ?></div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card shadow-sm mb-4">
    <div class="card-header bg-white">
      <strong>Per-Outlet Summary</strong>
    </div>
    <div class="card-body p-0">
      <?php if (!$outletTotals): ?>
        <div class="p-4 text-muted text-center">No outlet breakdown available.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-striped table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>Outlet</th>
                <th class="text-end" style="width:140px;">Income (RM)</th>
                <th class="text-end" style="width:150px;">Expenses (RM)</th>
                <th class="text-end" style="width:150px;">Balance (RM)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($outletTotals as $outlet => $values): ?>
                <tr>
                  <td><?= htmlspecialchars($outlet) ?></td>
                  <td class="text-end"><?= format_money($values['income']) ?></td>
                  <td class="text-end"><?= format_money($values['expenses']) ?></td>
                  <td class="text-end"><?= format_money($values['balance']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="card shadow-sm mb-4">
    <div class="card-header bg-white">
      <strong>Included Submissions</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:110px;">Submission</th>
              <th style="width:120px;">Date</th>
              <th>Outlet</th>
              <th>Manager</th>
              <th class="text-end" style="width:140px;">Income (RM)</th>
              <th class="text-end" style="width:150px;">Expenses (RM)</th>
              <th class="text-end" style="width:140px;">Balance (RM)</th>
              <th>Status</th>
              <th style="width:120px;">Submitted At</th>
              <th class="text-end" style="width:110px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$items): ?>
              <tr>
                <td colspan="10" class="text-center text-muted py-4">No submissions were linked to this batch.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($items as $row): ?>
                <?php
                  $badge = match ($row['status']) {
                      'pending'  => 'warning',
                      'approved' => 'success',
                      'rejected' => 'danger',
                      'recorded' => 'secondary',
                      default    => 'light',
                  };
                  $rowSubmitted = !empty($row['submitted_to_hq_at']) ? date('Y-m-d H:i', strtotime($row['submitted_to_hq_at'])) : '—';
                ?>
                <tr>
                  <td>#<?= (int) $row['submission_id'] ?></td>
                  <td><?= htmlspecialchars($row['date'] ?? '') ?></td>
                  <td><?= htmlspecialchars($row['outlet_name'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($row['submission_manager'] ?? '—') ?></td>
                  <td class="text-end"><?= format_money($row['total_income'] ?? null) ?></td>
                  <td class="text-end"><?= format_money($row['total_expenses'] ?? null) ?></td>
                  <td class="text-end"><?= format_money($row['balance'] ?? null) ?></td>
                  <td><span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst((string) $row['status'])) ?></span></td>
                  <td><?= htmlspecialchars($rowSubmitted) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="/daily_closing/manager_submission_view.php?id=<?= urlencode((string) $row['submission_id']) ?>" target="_blank">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="card shadow-sm">
    <div class="card-header bg-white">
      <strong>HQ Attachments</strong>
    </div>
    <div class="card-body">
      <?php if (!$attachments): ?>
        <p class="text-muted mb-0">No attachments uploaded for this batch.</p>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach ($attachments as $file): ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="/daily_closing<?= htmlspecialchars($file['file_path']) ?>" target="_blank">
              <span>
                <?= htmlspecialchars($file['original_name'] ?? basename((string) $file['file_path'])) ?>
                <?php if (!empty($file['created_at'])): ?>
                  <small class="text-muted d-block">Uploaded: <?= htmlspecialchars($file['created_at']) ?></small>
                <?php endif; ?>
              </span>
              <span class="text-muted small ms-3"><?= format_size($file['size_bytes'] ?? null) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>
</body>
</html>
