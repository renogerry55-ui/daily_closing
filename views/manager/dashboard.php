<?php
require __DIR__ . '/../../includes/auth_guard.php';
require __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../../includes/cash_metrics.php';

guard_manager();

$managerId = current_manager_id();
$outlets = allowed_outlets($pdo);
$outletIds = array_map(static fn(array $row) => (int)$row['id'], $outlets);
$outletLookup = [];
foreach ($outlets as $row) {
    $outletLookup[(int)$row['id']] = $row['name'];
}

$parseDate = static function (?string $value): ?string {
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $dt ? $dt->format('Y-m-d') : null;
};

$today = new DateTimeImmutable('today');
$defaultFrom = $today->modify('-6 days')->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');

$selectedOutletIds = [];
if (!empty($_GET['outlets']) && is_array($_GET['outlets'])) {
    foreach ($_GET['outlets'] as $value) {
        $id = (int)$value;
        if (in_array($id, $outletIds, true)) {
            $selectedOutletIds[] = $id;
        }
    }
    $selectedOutletIds = array_values(array_unique($selectedOutletIds));
}
if (!$selectedOutletIds && $outletIds) {
    $selectedOutletIds = $outletIds;
}

$dateFrom = $parseDate($_GET['date_from'] ?? '') ?? $defaultFrom;
$dateTo = $parseDate($_GET['date_to'] ?? '') ?? $defaultTo;
if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$statusOptions = [
    'all' => ['label' => 'All statuses', 'statuses' => []],
    'draft' => ['label' => 'Draft', 'statuses' => ['draft']],
    'submitted' => ['label' => 'Submitted', 'statuses' => ['pending']],
    'approved' => ['label' => 'Approved', 'statuses' => ['approved']],
    'rejected' => ['label' => 'Rejected', 'statuses' => ['rejected']],
    'posted' => ['label' => 'Posted', 'statuses' => ['recorded']],
];
$selectedStatus = $_GET['status'] ?? 'all';
if (!isset($statusOptions[$selectedStatus])) {
    $selectedStatus = 'all';
}
$statusFilter = $statusOptions[$selectedStatus]['statuses'];

$selectedOutletSummary = 'All outlets';
if ($selectedOutletIds && count($selectedOutletIds) !== count($outletIds)) {
    $names = [];
    foreach ($selectedOutletIds as $id) {
        if (isset($outletLookup[$id])) {
            $names[] = $outletLookup[$id];
        }
    }
    if ($names) {
        $selectedOutletSummary = count($names) <= 2 ? implode(', ', $names) : (count($names) . ' outlets selected');
    }
}

$where = ['s.manager_id = ?'];
$params = [$managerId];

$where[] = 's.date BETWEEN ? AND ?';
$params[] = $dateFrom;
$params[] = $dateTo;

if ($selectedOutletIds && count($selectedOutletIds) !== count($outletIds)) {
    $placeholders = implode(',', array_fill(0, count($selectedOutletIds), '?'));
    $where[] = "s.outlet_id IN ($placeholders)";
    $params = array_merge($params, $selectedOutletIds);
}

if ($statusFilter) {
    $placeholders = implode(',', array_fill(0, count($statusFilter), '?'));
    $where[] = "s.status IN ($placeholders)";
    $params = array_merge($params, $statusFilter);
}

$whereClause = implode(' AND ', $where);

$activitySql = "
    SELECT
        s.id,
        s.date,
        s.status,
        s.total_income,
        s.total_expenses,
        s.pass_to_office,
        s.balance,
        s.updated_at,
        s.submitted_to_hq_at,
        o.name AS outlet_name,
        COUNT(DISTINCT r.id) AS receipts_count
    FROM submissions s
    JOIN outlets o ON o.id = s.outlet_id
    LEFT JOIN receipts r ON r.submission_id = s.id
    WHERE $whereClause
    GROUP BY s.id, s.date, s.status, s.total_income, s.total_expenses, s.pass_to_office, s.balance, s.updated_at, s.submitted_to_hq_at, o.name
    ORDER BY s.date DESC, s.id DESC
    LIMIT 25
";
$stmt = $pdo->prepare($activitySql);
$stmt->execute($params);
$activityRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$countSql = "SELECT s.status, COUNT(*) AS total FROM submissions s WHERE $whereClause GROUP BY s.status";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$statusCounts = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $statusCounts[$row['status']] = (int)$row['total'];
}

$missingReceipts = 0;
foreach ($activityRows as $row) {
    if ((int)$row['receipts_count'] === 0 && in_array($row['status'], ['pending', 'approved'], true)) {
        $missingReceipts++;
    }
}

$cohOverall = manager_cash_on_hand($pdo, $managerId, $selectedOutletIds);
$rangeMetrics = manager_cash_on_hand($pdo, $managerId, $selectedOutletIds, [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);
$postedRange = manager_cash_on_hand($pdo, $managerId, $selectedOutletIds, [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'statuses' => ['approved', 'recorded'],
]);
$todayMetrics = manager_cash_on_hand($pdo, $managerId, $selectedOutletIds, [
    'date_from' => $today->format('Y-m-d'),
    'date_to' => $today->format('Y-m-d'),
]);
$yesterdayMetrics = manager_cash_on_hand($pdo, $managerId, $selectedOutletIds, [
    'date_from' => $today->modify('-1 day')->format('Y-m-d'),
    'date_to' => $today->modify('-1 day')->format('Y-m-d'),
]);

function format_money(float $value): string
{
    return number_format($value, 2);
}

function status_chip_class(string $status): string
{
    return match ($status) {
        'draft' => 'secondary',
        'pending' => 'info',
        'approved' => 'success',
        'recorded' => 'primary',
        'rejected' => 'danger',
        default => 'secondary',
    };
}

function status_display_label(string $status): string
{
    return match ($status) {
        'pending' => 'Submitted',
        'recorded' => 'Posted',
        default => ucfirst($status),
    };
}

$flashOk = $_SESSION['flash_ok'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

$appliedFilters = [];
if ($selectedOutletIds && count($selectedOutletIds) !== count($outletIds)) {
    $appliedFilters[] = $selectedOutletSummary;
}
if ($dateFrom !== $defaultFrom || $dateTo !== $defaultTo) {
    $appliedFilters[] = $dateFrom . ' → ' . $dateTo;
}
if ($selectedStatus !== 'all') {
    $appliedFilters[] = $statusOptions[$selectedStatus]['label'];
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
    body { background-color: #f5f6f8; }
    .summary-card { border-radius: 1rem; border: none; box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08); }
    .summary-card h6 { font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: #64748b; margin-bottom: .4rem; }
    .summary-card .value { font-size: 1.75rem; font-weight: 700; }
    .status-chip { display: inline-flex; align-items: center; gap: .35rem; padding: .25rem .75rem; border-radius: 999px; font-size: .75rem; font-weight: 600; text-transform: uppercase; }
    .table-smaller td, .table-smaller th { vertical-align: middle; }
    .filter-chip { display: inline-flex; align-items: center; background: #e2e8f0; border-radius: 999px; padding: .25rem .75rem; font-size: .75rem; margin-right: .5rem; }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="#">Daily Closing</a>
    <div class="ms-auto d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/manager_submissions.php">My Submissions</a>
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/manager_hq_batches.php">HQ History</a>
      <a class="btn btn-outline-danger btn-sm" href="/daily_closing/logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
      <h1 class="h3 mb-1">Manager Dashboard</h1>
      <p class="text-muted mb-0">Monitor daily submissions, Pass to Office, and cash on hand in one glance.</p>
    </div>
    <div class="text-lg-end">
      <div class="small text-muted">Showing <?= htmlspecialchars($dateFrom) ?> → <?= htmlspecialchars($dateTo) ?> · <?= htmlspecialchars($selectedOutletSummary) ?></div>
    </div>
  </div>

  <?php if ($flashOk || $flashError): ?>
    <div aria-live="polite" aria-atomic="true" class="position-relative">
      <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100;">
        <?php if ($flashOk): ?>
          <div class="toast align-items-center text-bg-success border-0" role="alert" data-bs-delay="6000" data-bs-autohide="true" data-bs-animation="true">
            <div class="d-flex">
              <div class="toast-body"><?= nl2br(htmlspecialchars($flashOk)) ?></div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($flashError): ?>
          <div class="toast align-items-center text-bg-danger border-0" role="alert" data-bs-delay="8000" data-bs-autohide="true" data-bs-animation="true">
            <div class="d-flex">
              <div class="toast-body"><?= nl2br(htmlspecialchars($flashError)) ?></div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($missingReceipts > 0): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
      <span class="badge text-bg-warning text-dark">Heads up</span>
      <span><?= $missingReceipts ?> submission<?= $missingReceipts === 1 ? '' : 's' ?> missing receipts. <a class="alert-link" href="/daily_closing/manager_submissions.php?status=pending">Review now</a>.</span>
    </div>
  <?php endif; ?>

  <form class="card border-0 shadow-sm mb-4" method="get">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label class="form-label text-uppercase small text-muted">Outlets</label>
          <select name="outlets[]" id="filterOutlets" class="form-select" multiple size="5">
            <?php foreach ($outlets as $outlet): ?>
              <option value="<?= (int)$outlet['id'] ?>" <?= in_array((int)$outlet['id'], $selectedOutletIds, true) ? 'selected' : '' ?>>
                <?= htmlspecialchars($outlet['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Hold Ctrl (Cmd on Mac) to select multiple.</div>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label text-uppercase small text-muted">Date from</label>
          <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label text-uppercase small text-muted">Date to</label>
          <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control">
        </div>
        <div class="col-lg-2">
          <label class="form-label text-uppercase small text-muted">Status</label>
          <select name="status" class="form-select">
            <?php foreach ($statusOptions as $key => $option): ?>
              <option value="<?= htmlspecialchars($key) ?>" <?= $key === $selectedStatus ? 'selected' : '' ?>><?= htmlspecialchars($option['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2 d-grid gap-2">
          <button class="btn btn-primary" type="submit">Apply filters</button>
          <a class="btn btn-outline-secondary" href="/daily_closing/views/manager/dashboard.php">Clear</a>
        </div>
      </div>
      <?php if ($appliedFilters): ?>
        <div class="mt-3">
          <?php foreach ($appliedFilters as $chip): ?>
            <span class="filter-chip"><?= htmlspecialchars($chip) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </form>

  <div class="row g-3 mb-4">
    <div class="col-sm-6">
      <div class="card summary-card">
        <div class="card-body">
          <h6>Pending Cash on Hand</h6>
          <div class="value">RM <?= format_money($cohOverall['pending']) ?></div>
          <p class="text-muted small mb-0">Awaiting Accounts approval.</p>
        </div>
      </div>
    </div>
    <div class="col-sm-6">
      <div class="card summary-card">
        <div class="card-body">
          <h6>Posted Cash on Hand</h6>
          <div class="value">RM <?= format_money($cohOverall['posted']) ?></div>
          <p class="text-muted small mb-0">Approved &amp; posted by Accounts.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-5">
    <div class="col-sm-6 col-lg-3">
      <div class="card summary-card h-100">
        <div class="card-body">
          <h6>Today&apos;s Sales</h6>
          <div class="value">RM <?= format_money($todayMetrics['income']) ?></div>
          <div class="text-muted small">vs yesterday <?= $yesterdayMetrics['income'] === 0.0 ? '—' : 'RM ' . format_money($todayMetrics['income'] - $yesterdayMetrics['income']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card summary-card h-100">
        <div class="card-body">
          <h6>Today&apos;s Expenses</h6>
          <div class="value">RM <?= format_money($todayMetrics['expenses']) ?></div>
          <div class="text-muted small">vs yesterday <?= $yesterdayMetrics['expenses'] === 0.0 ? '—' : 'RM ' . format_money($todayMetrics['expenses'] - $yesterdayMetrics['expenses']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card summary-card h-100">
        <div class="card-body">
          <h6>Pass to Office Today</h6>
          <div class="value">RM <?= format_money($todayMetrics['pass_to_office']) ?></div>
          <div class="text-muted small">Across <?= count($selectedOutletIds) ?> outlet<?= count($selectedOutletIds) === 1 ? '' : 's' ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card summary-card h-100">
        <div class="card-body">
          <h6>Pending COH (Filtered)</h6>
          <div class="value">RM <?= format_money($rangeMetrics['pending']) ?></div>
          <div class="text-muted small">For selected filters</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
      <div class="card summary-card h-100">
        <div class="card-body">
          <h6>Approved Sales (Filtered)</h6>
          <div class="value">RM <?= format_money($postedRange['income']) ?></div>
          <div class="text-muted small">Approved within range</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card summary-card h-100">
        <div class="card-body">
          <h6>Approved Expenses</h6>
          <div class="value">RM <?= format_money($postedRange['expenses']) ?></div>
          <div class="text-muted small">Approved within range</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card summary-card h-100">
        <div class="card-body">
          <h6>Pass to Office Posted</h6>
          <div class="value">RM <?= format_money($postedRange['pass_to_office']) ?></div>
          <div class="text-muted small">Included in approved submissions</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card summary-card h-100">
        <div class="card-body">
          <h6>Posted COH (Filtered)</h6>
          <div class="value">RM <?= format_money($postedRange['posted']) ?></div>
          <div class="text-muted small">After Accounts approval</div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="/daily_closing/views/manager_submission_create.php">New Submission</a>
      <div class="d-inline-flex align-items-center gap-2 small text-muted">
        <span class="badge text-bg-secondary">Drafts <?= $statusCounts['draft'] ?? 0 ?></span>
        <span class="badge text-bg-info text-dark">Submitted <?= ($statusCounts['pending'] ?? 0) ?></span>
        <span class="badge text-bg-success">Approved <?= ($statusCounts['approved'] ?? 0) ?></span>
      </div>
    </div>
    <div>
      <a class="btn btn-outline-primary" href="/daily_closing/views/report_hq.php">Build HQ Batch</a>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-5">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h2 class="h5 mb-0">Recent activity</h2>
          <p class="text-muted small mb-0">Latest 25 submissions based on filters.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="/daily_closing/manager_submissions.php">Open full list</a>
      </div>
      <?php if (!$activityRows): ?>
        <div class="text-center text-muted py-5">
          <div class="fs-5 mb-2">No submissions yet.</div>
          <p class="mb-3">Create your first daily submission to see activity here.</p>
          <a class="btn btn-primary" href="/daily_closing/views/manager_submission_create.php">Create submission</a>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover table-smaller align-middle mb-0">
            <thead class="text-uppercase text-muted small">
              <tr>
                <th>Date</th>
                <th>Outlet</th>
                <th class="text-end">Sales (RM)</th>
                <th class="text-end">Expenses (RM)</th>
                <th class="text-end">Pass to Office (RM)</th>
                <th class="text-end">COH Δ (RM)</th>
                <th>Receipts</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activityRows as $row): ?>
                <?php $delta = (float)$row['total_income'] - (float)$row['total_expenses'] - (float)$row['pass_to_office']; ?>
                <tr>
                  <td><?= htmlspecialchars($row['date']) ?></td>
                  <td><?= htmlspecialchars($row['outlet_name']) ?></td>
                  <td class="text-end"><?= format_money((float)$row['total_income']) ?></td>
                  <td class="text-end"><?= format_money((float)$row['total_expenses']) ?></td>
                  <td class="text-end"><?= format_money((float)$row['pass_to_office']) ?></td>
                  <td class="text-end">
                    <span class="<?= $delta >= 0 ? 'text-success' : 'text-danger' ?>"><?= format_money($delta) ?></span>
                  </td>
                  <td>
                    <span class="badge <?= (int)$row['receipts_count'] === 0 ? 'text-bg-warning text-dark' : 'text-bg-light text-dark' ?>">
                      📎 <?= (int)$row['receipts_count'] ?>
                    </span>
                  </td>
                  <td>
                    <span class="status-chip text-bg-<?= status_chip_class($row['status']) ?>" data-bs-toggle="tooltip" data-bs-placement="top" title="Last updated <?= htmlspecialchars($row['updated_at'] ?? '—') ?>">
                      <?= htmlspecialchars(status_display_label($row['status'])) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="/daily_closing/manager_submission_view.php?id=<?= (int)$row['id'] ?>">View details</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.querySelectorAll('.toast').forEach(toastEl => {
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
  });
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
</body>
</html>
