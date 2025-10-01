<?php
require __DIR__ . '/../../includes/auth_guard.php';
require __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../includes/metrics_manager.php';

guard_manager();

$managerId = current_manager_id();
$range = $_GET['range'] ?? 'month';
$metrics = manager_dashboard_metrics($pdo, $managerId, $range);
$outlets = $metrics['outlets'];
$totals = $metrics['totals'];
$rangeKey = $metrics['range'];

$startLabel = DateTime::createFromFormat('Y-m-d', $metrics['start']);
$endLabel = DateTime::createFromFormat('Y-m-d', $metrics['end']);
$rangeLabel = $rangeKey === 'today' ? 'Today' : 'This Month';
$rangeSubtitle = $rangeKey === 'today'
    ? ($endLabel ? $endLabel->format('j M Y') : $metrics['end'])
    : (($startLabel && $endLabel) ? $startLabel->format('j M Y') . ' – ' . $endLabel->format('j M Y') : $metrics['start'] . ' – ' . $metrics['end']);

function manager_badge(string $label, int $count, float $amount, string $color): string
{
    $formattedAmount = number_format($amount, 2);
    return sprintf(
        '<span class="badge text-bg-%s rounded-pill">%s: %d (RM %s)</span>',
        $color,
        htmlspecialchars($label),
        $count,
        $formattedAmount
    );
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manager Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  .kpi-card { min-height: 120px; }
  .badge + .badge { margin-left: .5rem; }
  .outlet-card { border-radius: 1rem; }
  .chip { display: inline-flex; align-items: center; gap: .25rem; padding: .35rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
  .chip-warning { background-color: #fff3cd; color: #856404; }
  .chip-info { background-color: #e7f1ff; color: #084298; }
</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="#">Daily Closing</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/manager_submissions.php">📄 My Submissions</a>
      <a class="btn btn-outline-danger btn-sm" href="/daily_closing/logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="mb-1">Manager Dashboard</h2>
      <div class="text-muted"><?= htmlspecialchars($rangeLabel) ?> · <?= htmlspecialchars($rangeSubtitle) ?></div>
    </div>
    <div class="btn-group" role="group" aria-label="Period toggle">
      <a class="btn btn-sm <?= $rangeKey === 'month' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?range=month">This Month</a>
      <a class="btn btn-sm <?= $rangeKey === 'today' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?range=today">Today</a>
    </div>
  </div>

  <?php if ($metrics['outletCount'] === 0): ?>
    <div class="alert alert-info">
      <h5 class="alert-heading">No outlets assigned</h5>
      <p class="mb-0">You currently do not have any active outlets assigned. Please contact the administrator to request an assignment.</p>
    </div>
  <?php else: ?>
    <section class="mb-5">
      <h5 class="text-uppercase text-muted mb-3">All Outlets — <?= htmlspecialchars($rangeLabel) ?></h5>
      <div class="row g-3 mb-2">
        <div class="col-md-4">
          <div class="card kpi-card shadow-sm border-0">
            <div class="card-body">
              <div class="text-muted small">Sales (Approved, <?= htmlspecialchars($rangeLabel) ?>)</div>
              <div class="display-6 fs-2">RM <?= number_format($totals['salesApproved'], 2) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card kpi-card shadow-sm border-0">
            <div class="card-body">
              <div class="text-muted small">Expenses (Approved, <?= htmlspecialchars($rangeLabel) ?>)</div>
              <div class="display-6 fs-2">RM <?= number_format($totals['expensesApproved'], 2) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card kpi-card shadow-sm border-0">
            <div class="card-body">
              <div class="text-muted small">Cash on Hand (Approved, <?= htmlspecialchars($rangeLabel) ?>)</div>
              <div class="display-6 fs-2">RM <?= number_format($totals['coh'], 2) ?></div>
              <div class="text-muted small mt-2">Remitted (Approved, <?= htmlspecialchars($rangeLabel) ?>): RM <?= number_format($totals['remittedApproved'], 2) ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <?= manager_badge('Pending submissions', (int)$totals['pendingSubmissionsCount'], (float)$totals['pendingSubmissionsAmount'], 'warning') ?>
        <?= manager_badge('Pending remittances', (int)$totals['pendingRemittancesCount'], (float)$totals['pendingRemittancesAmount'], 'info') ?>
      </div>
    </section>

    <section>
      <h5 class="text-uppercase text-muted mb-3">Per Outlet — <?= htmlspecialchars($rangeLabel) ?></h5>
      <div class="row g-4">
        <?php foreach ($outlets as $outlet):
          $oid = (int)$outlet['id'];
          $data = $metrics['outlets'][$oid];
          $pendingSubLabel = $data['pendingSubmissionsAmount'] >= 0 ? 'RM ' . number_format($data['pendingSubmissionsAmount'], 2) : 'RM ' . number_format($data['pendingSubmissionsAmount'], 2);
          $pendingRemitLabel = 'RM ' . number_format($data['pendingRemittancesAmount'], 2);
        ?>
        <div class="col-lg-4">
          <div class="card outlet-card shadow-sm border-0 h-100">
            <div class="card-body d-flex flex-column">
              <div class="d-flex align-items-start justify-content-between mb-3">
                <h5 class="mb-0"><?= htmlspecialchars($outlet['name']) ?></h5>
              </div>
              <div class="mb-3">
                <div class="small text-muted">Sales (Approved)</div>
                <div class="fw-semibold">RM <?= number_format($data['salesApproved'], 2) ?></div>
                <div class="small text-muted mt-2">Expenses (Approved)</div>
                <div class="fw-semibold">RM <?= number_format($data['expensesApproved'], 2) ?></div>
                <div class="small text-muted mt-2">Approved Remitted</div>
                <div class="fw-semibold">RM <?= number_format($data['remittedApproved'], 2) ?></div>
                <div class="small text-muted mt-2">Cash on Hand (Approved)</div>
                <div class="fw-semibold text-success">RM <?= number_format($data['cashOnHand'], 2) ?></div>
              </div>
              <div class="mb-3 d-flex flex-column gap-2">
                <span class="chip chip-warning">Pending submissions: <?= (int)$data['pendingSubmissionsCount'] ?> (<?= $pendingSubLabel ?>)</span>
                <span class="chip chip-info">Pending remittances: <?= (int)$data['pendingRemittancesCount'] ?> (<?= $pendingRemitLabel ?>)</span>
              </div>

              <div class="mb-4">
                <div class="small text-uppercase text-muted fw-semibold mb-2">Latest Submissions</div>
                <?php if ($data['latestSubmissions']): ?>
                  <ul class="list-unstyled mb-0">
                    <?php foreach ($data['latestSubmissions'] as $submission):
                      $status = strtolower((string)$submission['status']);
                      $badgeMap = [
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'declined' => 'danger',
                        'recorded' => 'info',
                      ];
                      $badgeColor = $badgeMap[$status] ?? 'secondary';
                    ?>
                      <li class="mb-2">
                        <div class="d-flex justify-content-between gap-2">
                          <div>
                            <div class="fw-semibold small"><?= htmlspecialchars($submission['date']) ?></div>
                            <div class="small text-muted">Sales RM <?= number_format((float)$submission['total_income'], 2) ?> · Expenses RM <?= number_format((float)$submission['total_expenses'], 2) ?></div>
                          </div>
                          <span class="badge text-bg-<?= $badgeColor ?> align-self-start"><?= htmlspecialchars(ucfirst($status)) ?></span>
                        </div>
                        <a class="small" href="/daily_closing/manager_submission_view.php?id=<?= (int)$submission['id'] ?>">View details</a>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php else: ?>
                  <p class="text-muted small mb-0">No submissions yet.</p>
                <?php endif; ?>
              </div>

              <div class="mb-4">
                <div class="small text-uppercase text-muted fw-semibold mb-2">Today's Receipts</div>
                <?php if ($data['todayReceipts']): ?>
                  <?php foreach ($data['todayReceipts'] as $sub): ?>
                    <div class="mb-3">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div class="small fw-semibold">Submission <?= htmlspecialchars($sub['date']) ?></div>
                          <div class="small text-muted">Sales RM <?= number_format($sub['income'], 2) ?> · Expenses RM <?= number_format($sub['expenses'], 2) ?></div>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="/daily_closing/manager_submission_view.php?id=<?= (int)$sub['id'] ?>">View details</a>
                      </div>
                      <?php if ($sub['receipts']): ?>
                        <ul class="list-unstyled small mb-0 mt-2">
                          <?php foreach ($sub['receipts'] as $rec): ?>
                            <li class="d-flex align-items-center gap-2">
                              <span class="text-muted">📎</span>
                              <a href="<?= htmlspecialchars($rec['path']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($rec['name']) ?></a>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      <?php else: ?>
                        <p class="text-muted small mb-0 mt-2">No receipts uploaded.</p>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <p class="text-muted small mb-0">No submissions today.</p>
                <?php endif; ?>
              </div>

              <div class="mt-auto pt-3 border-top">
                <div class="d-flex flex-wrap gap-2">
                  <a class="btn btn-sm btn-primary" href="/daily_closing/views/manager_submission_create.php?outlet_id=<?= $oid ?>">➕ Sales / Expenses</a>
                  <a class="btn btn-sm btn-outline-primary" href="/daily_closing/views/report_hq.php">Upload deposit slip</a>
                  <a class="btn btn-sm btn-outline-secondary" href="/daily_closing/manager_hq_batches.php">HQ History</a>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
