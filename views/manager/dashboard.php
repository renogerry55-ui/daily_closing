<?php
require __DIR__ . '/../../includes/auth_guard.php';
require __DIR__ . '/../../includes/db.php';

guard_manager();

$managerId = current_manager_id();
$outlets = allowed_outlets($pdo);

$sessionKey = 'manager_dashboard_filters_' . $managerId;
$allowedTabs = ['submissions', 'hq', 'remittances'];
$defaultFilters = [
    'submissions'  => ['range' => 'week', 'status' => 'all', 'outlets' => [], 'date_from' => '', 'date_to' => ''],
    'hq'           => ['range' => 'week', 'status' => 'all', 'outlets' => [], 'date_from' => '', 'date_to' => ''],
    'remittances'  => ['range' => 'week', 'status' => 'all', 'outlets' => [], 'date_from' => '', 'date_to' => ''],
];

if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = $defaultFilters + ['tab' => 'submissions'];
}

$tab = $_GET['tab'] ?? ($_SESSION[$sessionKey]['tab'] ?? 'submissions');
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'submissions';
}
$_SESSION[$sessionKey]['tab'] = $tab;

$action = $_GET['action'] ?? '';

$availableOutletIds = array_map(static fn(array $row) => (int)$row['id'], $outlets);
$availableOutletIds = array_values(array_unique($availableOutletIds));

$redirectToTab = static function (string $tab) {
    $query = http_build_query(['tab' => $tab]);
    header('Location: /daily_closing/views/manager/dashboard.php?' . $query);
    exit;
};

$sanitizeOutlets = static function (array $raw, array $allowed): array {
    $selected = [];
    foreach ($raw as $value) {
        $id = (int)$value;
        if (in_array($id, $allowed, true)) {
            $selected[] = $id;
        }
    }
    return array_values(array_unique($selected));
};

$parseDate = static function (?string $value): ?string {
    if (!$value) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $dt ? $dt->format('Y-m-d') : null;
};

$computeRange = static function (string $range, ?string $from, ?string $to): array {
    $today = new DateTimeImmutable('today');
    $end = $today;
    $start = $today;

    switch ($range) {
        case 'today':
            break;
        case 'month':
            $start = $today->modify('first day of this month');
            break;
        case 'custom':
            if ($from && $to) {
                $startDt = DateTimeImmutable::createFromFormat('Y-m-d', $from);
                $endDt = DateTimeImmutable::createFromFormat('Y-m-d', $to);
                if ($startDt && $endDt && $startDt <= $endDt) {
                    $start = $startDt;
                    $end = $endDt;
                    break;
                }
            }
            // fall through to default week if invalid custom
        default:
            $range = 'week';
            $start = $today->modify('monday this week');
            break;
    }

    if ($range === 'week') {
        $start = $today->modify('monday this week');
    }

    return [$start->format('Y-m-d'), $end->format('Y-m-d'), $range];
};

if ($action === 'reset' && isset($_GET['tab']) && in_array($_GET['tab'], $allowedTabs, true)) {
    $_SESSION[$sessionKey][$_GET['tab']] = $defaultFilters[$_GET['tab']];
    $redirectToTab($_GET['tab']);
}

if ($action === 'apply' && isset($_GET['tab']) && in_array($_GET['tab'], $allowedTabs, true)) {
    $range = $_GET['range'] ?? 'week';
    $status = $_GET['status'] ?? 'all';
    $rawOutlets = $_GET['outlets'] ?? [];
    if (!is_array($rawOutlets)) {
        $rawOutlets = [$rawOutlets];
    }
    $selectedOutlets = $sanitizeOutlets($rawOutlets, $availableOutletIds);
    $dateFrom = $parseDate($_GET['date_from'] ?? '');
    $dateTo = $parseDate($_GET['date_to'] ?? '');

    $_SESSION[$sessionKey][$_GET['tab']] = [
        'range' => in_array($range, ['today', 'week', 'month', 'custom'], true) ? $range : 'week',
        'status' => $status ?: 'all',
        'outlets' => $selectedOutlets,
        'date_from' => $dateFrom ?? '',
        'date_to' => $dateTo ?? '',
    ];

    $redirectToTab($_GET['tab']);
}

$currentFilters = $_SESSION[$sessionKey][$tab] ?? $defaultFilters[$tab];
[$dateStart, $dateEnd, $normalizedRange] = $computeRange(
    $currentFilters['range'] ?? 'week',
    $currentFilters['date_from'] ?: null,
    $currentFilters['date_to'] ?: null
);
$currentFilters['range'] = $normalizedRange;
$dateStartParam = $dateStart;
$dateEndParam = $dateEnd;

$selectedOutletIds = $currentFilters['outlets'];
if (!$selectedOutletIds) {
    $selectedOutletIds = $availableOutletIds;
}

$outletLookup = [];
foreach ($outlets as $outlet) {
    $outletLookup[(int)$outlet['id']] = $outlet['name'];
}

$selectedOutletNames = [];
foreach ($selectedOutletIds as $outletId) {
    if (isset($outletLookup[$outletId])) {
        $selectedOutletNames[] = $outletLookup[$outletId];
    }
}

$selectedOutletSummary = 'All outlets';
if ($selectedOutletNames && count($selectedOutletIds) !== count($availableOutletIds)) {
    if (count($selectedOutletNames) <= 2) {
        $selectedOutletSummary = implode(', ', $selectedOutletNames);
    } else {
        $selectedOutletSummary = count($selectedOutletNames) . ' outlets selected';
    }
}

$buildPlaceholders = static function (array $ids): string {
    return implode(',', array_fill(0, count($ids), '?'));
};

$metrics = [
    'today_submissions' => 0,
    'pending_hq' => 0,
    'cash_on_hand' => 0.0,
    'last_remittance' => null,
];

if ($availableOutletIds) {
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM submissions WHERE manager_id = ? AND date = ?'
    );
    $stmt->execute([$managerId, $today]);
    $metrics['today_submissions'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM submissions WHERE manager_id = ? AND status = 'submitted'"
    );
    $stmt->execute([$managerId]);
    $metrics['pending_hq'] = (int)$stmt->fetchColumn();

    $placeholders = $buildPlaceholders($availableOutletIds);

    $sqlNet = "SELECT IFNULL(SUM(total_income - total_expenses),0) FROM submissions WHERE status = 'approved' AND manager_id = ? AND outlet_id IN ($placeholders)";
    $stmt = $pdo->prepare($sqlNet);
    $stmt->execute(array_merge([$managerId], $availableOutletIds));
    $approvedNet = (float)$stmt->fetchColumn();

    $sqlRemit = "SELECT IFNULL(SUM(amount),0) FROM hq_remittances WHERE status = 'approved' AND outlet_id IN ($placeholders)";
    $stmt = $pdo->prepare($sqlRemit);
    $stmt->execute($availableOutletIds);
    $approvedRemit = (float)$stmt->fetchColumn();

    $metrics['cash_on_hand'] = max(0, $approvedNet - $approvedRemit);

    $sqlLast = "SELECT received_at, amount, status FROM hq_remittances WHERE outlet_id IN ($placeholders) ORDER BY received_at DESC, id DESC LIMIT 1";
    $stmt = $pdo->prepare($sqlLast);
    $stmt->execute($availableOutletIds);
    $metrics['last_remittance'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$submissions = [];
$hqBatches = [];
$remittances = [];

if ($availableOutletIds) {
    $placeholdersSelected = $buildPlaceholders($selectedOutletIds);

    if ($tab === 'submissions') {
        $params = [$managerId, $dateStartParam, $dateEndParam];
        $where = "WHERE s.manager_id = ? AND s.date BETWEEN ? AND ?";
        if ($selectedOutletIds && count($selectedOutletIds) !== count($availableOutletIds)) {
            $where .= " AND s.outlet_id IN ($placeholdersSelected)";
            $params = array_merge($params, $selectedOutletIds);
        }
        $status = $currentFilters['status'] ?? 'all';
        if ($status !== 'all') {
            $where .= ' AND s.status = ?';
            $params[] = $status;
        }

        $sql = "
            SELECT
                s.id,
                s.date,
                s.status,
                s.total_income,
                s.total_expenses,
                s.total_income - s.total_expenses AS balance,
                o.name AS outlet_name,
                COUNT(i.id) AS item_count
            FROM submissions s
            JOIN outlets o ON o.id = s.outlet_id
            LEFT JOIN submission_items i ON i.submission_id = s.id
            $where
            GROUP BY s.id, s.date, s.status, s.total_income, s.total_expenses, o.name
            ORDER BY s.date DESC, s.id DESC
            LIMIT 20
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($tab === 'hq') {
        $params = [$managerId, $dateStartParam, $dateEndParam];
        $where = "WHERE b.manager_id = ? AND b.report_date BETWEEN ? AND ?";
        $extraParams = [];
        if ($selectedOutletIds && count($selectedOutletIds) !== count($availableOutletIds)) {
            $place = $buildPlaceholders($selectedOutletIds);
            $where .= " AND EXISTS (
                SELECT 1 FROM hq_batch_submissions bs
                JOIN submissions s2 ON s2.id = bs.submission_id
                WHERE bs.hq_batch_id = b.id AND s2.outlet_id IN ($place)
            )";
            $extraParams = array_merge($extraParams, $selectedOutletIds);
        }
        $status = $currentFilters['status'] ?? 'all';
        if ($status !== 'all') {
            $where .= ' AND b.status = ?';
            $extraParams[] = $status;
        }

        $sql = "
            SELECT
                b.id,
                b.report_date,
                b.status,
                b.overall_total_income,
                b.overall_total_expenses,
                b.overall_balance,
                COALESCE(COUNT(DISTINCT s.outlet_id), 0) AS outlet_count,
                COALESCE(COUNT(bs.submission_id), 0) AS submission_count
            FROM hq_batches b
            LEFT JOIN hq_batch_submissions bs ON bs.hq_batch_id = b.id
            LEFT JOIN submissions s ON s.id = bs.submission_id
            $where
            GROUP BY b.id, b.report_date, b.status, b.overall_total_income, b.overall_total_expenses, b.overall_balance
            ORDER BY b.report_date DESC, b.id DESC
            LIMIT 20
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($params, $extraParams));
        $hqBatches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($tab === 'remittances') {
        $where = [];
        $params = [];
        $placeAll = $buildPlaceholders($availableOutletIds);
        $where[] = "r.outlet_id IN ($placeAll)";
        $params = array_merge($params, $availableOutletIds);
        $where[] = 'r.received_at BETWEEN ? AND ?';
        $params[] = $dateStartParam;
        $params[] = $dateEndParam;
        if ($selectedOutletIds && count($selectedOutletIds) !== count($availableOutletIds)) {
            $where[] = "r.outlet_id IN ($placeholdersSelected)";
            $params = array_merge($params, $selectedOutletIds);
        }
        $status = $currentFilters['status'] ?? 'all';
        if ($status !== 'all') {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }

        $sql = "
            SELECT
                r.id,
                r.outlet_id,
                r.submission_id,
                r.amount,
                r.received_at,
                r.status,
                r.bank_ref,
                o.name AS outlet_name
            FROM hq_remittances r
            JOIN outlets o ON o.id = r.outlet_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.received_at DESC, r.id DESC
            LIMIT 20
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $remittances = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

$statusOptions = [
    'submissions' => [
        'all' => 'All statuses',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'recorded' => 'Recorded',
    ],
    'hq' => [
        'all' => 'All statuses',
        'submitted' => 'Submitted',
        'processing' => 'Processing',
        'acknowledged' => 'Acknowledged',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
    ],
    'remittances' => [
        'all' => 'All statuses',
        'pending' => 'Pending',
        'approved' => 'Approved',
        'declined' => 'Declined',
    ],
];

$rangeOptions = [
    'today' => 'Today',
    'week' => 'This week',
    'month' => 'This month',
    'custom' => 'Custom range',
];

function format_money(float $value): string
{
    return number_format($value, 2);
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'submitted', 'pending' => 'warning',
        'approved', 'completed', 'acknowledged' => 'success',
        'rejected', 'declined' => 'danger',
        'processing' => 'info',
        'recorded' => 'secondary',
        default => 'secondary',
    };
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
  body { background: #f5f6f8; }
  .dashboard-header { position: sticky; top: 0; z-index: 1020; background: #f5f6f8; padding-top: 1rem; }
  .filters-bar { position: sticky; top: 112px; z-index: 1010; background: #ffffff; }
  @media (max-width: 991.98px) {
    .filters-bar { top: 164px; }
  }
  .card-metric { border: none; border-radius: 1rem; box-shadow: 0 6px 24px rgba(15, 23, 42, .08); }
  .card-metric h6 { text-transform: uppercase; font-size: .75rem; letter-spacing: .08em; margin-bottom: .5rem; color: #64748b; }
  .tab-content .table { font-size: .875rem; }
  .status-pill { display: inline-flex; align-items: center; justify-content: center; padding: .35rem .75rem; border-radius: 999px; font-size: .75rem; font-weight: 600; }
  .legend-dot { width: .75rem; height: .75rem; border-radius: 50%; display: inline-block; margin-right: .35rem; }
  .legend-item { font-size: .75rem; color: #64748b; }
  .table thead th { text-transform: uppercase; font-size: .7rem; letter-spacing: .05em; color: #94a3b8; border-bottom: 2px solid #e2e8f0; }
  .table tbody td { vertical-align: middle; }
  .filters-card { border-radius: 1rem; box-shadow: 0 4px 16px rgba(15, 23, 42, .06); }
  .filters-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .75rem 1rem; }
  .filters-toolbar .filter-field { min-width: 140px; flex: 1 1 160px; }
  .filters-toolbar .filter-actions { margin-left: auto; display: flex; gap: .5rem; flex-wrap: wrap; }
  @media (max-width: 575.98px) {
    .filters-toolbar .filter-actions { width: 100%; justify-content: flex-end; }
  }
  .outlet-selector-button { padding: .55rem .75rem; border-radius: .75rem; }
  .outlet-selector-button span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .dropdown-menu.outlet-selector-menu { min-width: 16rem; max-height: 16rem; overflow: auto; }
  .dropdown-menu.outlet-selector-menu .form-check { padding-left: 1.75rem; }
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
  <div class="dashboard-header">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
      <div>
        <h1 class="h3 mb-2">Manager Dashboard</h1>
        <p class="text-muted mb-0">One place to track submissions, batches, and remittances.</p>
      </div>
      <div class="nav nav-pills gap-2" role="tablist">
        <a class="btn btn-sm <?= $tab === 'submissions' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?tab=submissions">Submissions</a>
        <a class="btn btn-sm <?= $tab === 'hq' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?tab=hq">HQ Batches</a>
        <a class="btn btn-sm <?= $tab === 'remittances' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?tab=remittances">Cash &amp; Remittances</a>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-lg-3">
        <div class="card card-metric h-100">
          <div class="card-body">
            <h6>Today Submitted</h6>
            <div class="display-6 fw-semibold"><?= number_format($metrics['today_submissions']) ?></div>
            <small class="text-muted">Submissions logged today</small>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card card-metric h-100">
          <div class="card-body">
            <h6>Pending HQ</h6>
            <div class="display-6 fw-semibold"><?= number_format($metrics['pending_hq']) ?></div>
            <small class="text-muted">Waiting for HQ review</small>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card card-metric h-100">
          <div class="card-body">
            <h6>Cash on Hand</h6>
            <div class="display-6 fw-semibold">RM <?= format_money($metrics['cash_on_hand']) ?></div>
            <small class="text-muted">Approved net - approved remittances</small>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-3">
        <div class="card card-metric h-100">
          <div class="card-body">
            <h6>Last Remittance</h6>
            <?php if ($metrics['last_remittance']): ?>
              <div class="fw-semibold mb-1">RM <?= format_money((float)$metrics['last_remittance']['amount']) ?></div>
              <div class="text-muted small"><?= htmlspecialchars($metrics['last_remittance']['received_at']) ?> ·
                <span class="badge text-bg-<?= status_badge_class((string)$metrics['last_remittance']['status']) ?>"><?= htmlspecialchars(ucfirst($metrics['last_remittance']['status'])) ?></span>
              </div>
            <?php else: ?>
              <div class="fw-semibold mb-1">No remittances</div>
              <div class="text-muted small">Record your first HQ deposit</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <section class="filters-bar mb-4 border rounded-4 p-3 bg-white">
    <form class="filters-toolbar w-100" method="get">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <input type="hidden" name="action" value="apply">

      <div class="filter-field flex-grow-1 flex-lg-grow-0">
        <label class="form-label small text-uppercase text-muted">Date range</label>
        <select class="form-select form-select-sm" name="range">
          <?php foreach ($rangeOptions as $value => $label): ?>
            <option value="<?= $value ?>" <?= ($currentFilters['range'] ?? 'week') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-field flex-grow-1 flex-lg-grow-0">
        <label class="form-label small text-uppercase text-muted">Outlets</label>
        <div class="dropdown w-100" data-bs-auto-close="outside" data-filter-outlets data-outlet-count="<?= count($availableOutletIds) ?>">
          <button class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between outlet-selector-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="outletSelectorLabel" class="me-2 flex-grow-1 text-start text-truncate small fw-semibold"><?= htmlspecialchars($selectedOutletSummary) ?></span>
            <span class="text-muted small">▾</span>
          </button>
          <div class="dropdown-menu outlet-selector-menu w-100 shadow-sm p-3">
            <?php if ($outlets): ?>
              <?php foreach ($outlets as $outlet): ?>
                <?php $outletId = (int)$outlet['id']; ?>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="outlets[]" value="<?= $outletId ?>" id="filter-outlet-<?= $outletId ?>" data-label="<?= htmlspecialchars($outlet['name']) ?>" <?= in_array($outletId, $selectedOutletIds, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter-outlet-<?= $outletId ?>"><?= htmlspecialchars($outlet['name']) ?></label>
                </div>
              <?php endforeach; ?>
              <div class="d-flex align-items-center gap-2 pt-1 border-top mt-2 pt-2">
                <button type="button" class="btn btn-link btn-sm px-0" data-action="select-all">Select all</button>
                <span class="text-muted">·</span>
                <button type="button" class="btn btn-link btn-sm px-0" data-action="clear">Clear</button>
              </div>
            <?php else: ?>
              <span class="text-muted small">No outlets assigned yet.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="filter-field flex-grow-1 flex-lg-grow-0">
        <label class="form-label small text-uppercase text-muted">Status</label>
        <select class="form-select form-select-sm" name="status">
          <?php foreach ($statusOptions[$tab] as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= ($currentFilters['status'] ?? 'all') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-field flex-grow-1 flex-lg-grow-0">
        <label class="form-label small text-uppercase text-muted">From</label>
        <input type="date" class="form-control form-control-sm" name="date_from" value="<?= htmlspecialchars($currentFilters['date_from'] ?? '') ?>">
      </div>

      <div class="filter-field flex-grow-1 flex-lg-grow-0">
        <label class="form-label small text-uppercase text-muted">To</label>
        <input type="date" class="form-control form-control-sm" name="date_to" value="<?= htmlspecialchars($currentFilters['date_to'] ?? '') ?>">
      </div>

      <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-sm px-3">Apply</button>
        <a class="btn btn-outline-secondary btn-sm px-3" href="?tab=<?= htmlspecialchars($tab) ?>&amp;action=reset">Reset</a>
      </div>
    </form>
  </section>

  <div class="tab-content">
    <div class="tab-pane fade <?= $tab === 'submissions' ? 'show active' : '' ?>" id="tab-submissions" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Recent Submissions</h2>
        <a class="btn btn-primary" href="/daily_closing/views/manager_submission_create.php">New Submission</a>
      </div>
      <?php if (!$availableOutletIds): ?>
        <div class="alert alert-info">No outlets assigned yet. Contact HQ to get started.</div>
      <?php elseif (!$submissions): ?>
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center py-5">
            <h5 class="mb-2">No submissions found</h5>
            <p class="text-muted">Try a wider date range or create a new submission.</p>
            <a class="btn btn-primary" href="/daily_closing/views/manager_submission_create.php">Create Submission</a>
          </div>
        </div>
      <?php else: ?>
        <div class="table-responsive rounded-4 shadow-sm">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Outlet</th>
                <th>Items</th>
                <th class="text-end">Net (RM)</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($submissions as $submission): ?>
                <tr>
                  <td>#<?= (int)$submission['id'] ?></td>
                  <td><?= htmlspecialchars($submission['date']) ?></td>
                  <td><?= htmlspecialchars($submission['outlet_name']) ?></td>
                  <td><?= (int)$submission['item_count'] ?></td>
                  <td class="text-end"><?= format_money((float)$submission['balance']) ?></td>
                  <td>
                    <?php $status = (string)$submission['status']; ?>
                    <span class="badge text-bg-<?= status_badge_class($status) ?> status-pill"><?= htmlspecialchars(ucfirst($status)) ?></span>
                  </td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a class="btn btn-outline-secondary" href="/daily_closing/manager_submission_view.php?id=<?= (int)$submission['id'] ?>">View</a>
                      <a class="btn btn-outline-secondary" href="/daily_closing/views/report_hq.php?submission_id=<?= (int)$submission['id'] ?>">Submit</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex flex-wrap gap-3 mt-3">
          <span class="legend-item"><span class="legend-dot bg-warning"></span> Submitted / Pending</span>
          <span class="legend-item"><span class="legend-dot bg-success"></span> Approved / Completed</span>
          <span class="legend-item"><span class="legend-dot bg-danger"></span> Rejected / Declined</span>
          <span class="legend-item"><span class="legend-dot bg-info"></span> Processing</span>
        </div>
      <?php endif; ?>
    </div>

    <div class="tab-pane fade <?= $tab === 'hq' ? 'show active' : '' ?>" id="tab-hq" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">HQ Batches</h2>
        <a class="btn btn-primary" href="/daily_closing/views/report_hq.php">New HQ Batch</a>
      </div>
      <?php if (!$availableOutletIds): ?>
        <div class="alert alert-info">No outlets assigned yet. Contact HQ to get started.</div>
      <?php elseif (!$hqBatches): ?>
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center py-5">
            <h5 class="mb-2">No batches yet</h5>
            <p class="text-muted">Submit a package to HQ to see it listed here.</p>
            <a class="btn btn-primary" href="/daily_closing/views/report_hq.php">Create HQ Batch</a>
          </div>
        </div>
      <?php else: ?>
        <div class="table-responsive rounded-4 shadow-sm">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Batch</th>
                <th>Date</th>
                <th>Status</th>
                <th>Outlets</th>
                <th>Submissions</th>
                <th class="text-end">Balance (RM)</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($hqBatches as $batch): ?>
                <tr>
                  <td>#<?= (int)$batch['id'] ?></td>
                  <td><?= htmlspecialchars($batch['report_date']) ?></td>
                  <td><span class="badge text-bg-<?= status_badge_class((string)$batch['status']) ?> status-pill"><?= htmlspecialchars(ucfirst((string)$batch['status'])) ?></span></td>
                  <td><?= (int)$batch['outlet_count'] ?></td>
                  <td><?= (int)$batch['submission_count'] ?></td>
                  <td class="text-end"><?= format_money((float)$batch['overall_balance']) ?></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a class="btn btn-outline-secondary" href="/daily_closing/manager_hq_batch_view.php?id=<?= (int)$batch['id'] ?>">View</a>
                      <a class="btn btn-outline-secondary" href="/daily_closing/account_hq_package_export.php?id=<?= (int)$batch['id'] ?>">Export</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="tab-pane fade <?= $tab === 'remittances' ? 'show active' : '' ?>" id="tab-remittances" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">Cash &amp; Remittances</h2>
        <a class="btn btn-primary" href="/daily_closing/views/report_hq.php">Record Pass to Office</a>
      </div>
      <?php if (!$availableOutletIds): ?>
        <div class="alert alert-info">No outlets assigned yet. Contact HQ to get started.</div>
      <?php elseif (!$remittances): ?>
        <div class="card border-0 shadow-sm">
          <div class="card-body text-center py-5">
            <h5 class="mb-2">No remittances recorded</h5>
            <p class="text-muted">Submit a cash pass or bank-in slip to view it here.</p>
            <a class="btn btn-primary" href="/daily_closing/views/report_hq.php">Record Remittance</a>
          </div>
        </div>
      <?php else: ?>
        <div class="table-responsive rounded-4 shadow-sm">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Outlet</th>
                <th>Type</th>
                <th class="text-end">Amount (RM)</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($remittances as $remit): ?>
                <?php
                  $type = !empty($remit['bank_ref']) ? 'Bank In' : 'Pass to Office';
                  $status = (string)$remit['status'];
                ?>
                <tr>
                  <td>#<?= (int)$remit['id'] ?></td>
                  <td><?= htmlspecialchars($remit['received_at']) ?></td>
                  <td><?= htmlspecialchars($remit['outlet_name']) ?></td>
                  <td><?= htmlspecialchars($type) ?></td>
                  <td class="text-end"><?= format_money((float)$remit['amount']) ?></td>
                  <td><span class="badge text-bg-<?= status_badge_class($status) ?> status-pill"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <?php if (!empty($remit['submission_id'])): ?>
                        <a class="btn btn-outline-secondary" href="/daily_closing/manager_submission_view.php?id=<?= (int)$remit['submission_id'] ?>">Submission</a>
                      <?php endif; ?>
                      <a class="btn btn-outline-secondary" href="/daily_closing/views/report_hq.php">Details</a>
                    </div>
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
  document.addEventListener('DOMContentLoaded', function () {
    const outletDropdown = document.querySelector('[data-filter-outlets]');
    if (!outletDropdown) {
      return;
    }

    const outletCount = parseInt(outletDropdown.getAttribute('data-outlet-count'), 10) || 0;
    const summaryEl = outletDropdown.querySelector('#outletSelectorLabel');
    const checkboxes = Array.from(outletDropdown.querySelectorAll('input[type="checkbox"][name="outlets[]"]'));

    const updateSummary = () => {
      const selected = checkboxes.filter(cb => cb.checked);
      let label = 'All outlets';
      if (selected.length && selected.length !== outletCount) {
        const names = selected.map(cb => cb.getAttribute('data-label'));
        if (names.length <= 2) {
          label = names.join(', ');
        } else {
          label = `${names.length} outlets selected`;
        }
      }
      summaryEl.textContent = label || 'All outlets';
    };

    checkboxes.forEach(cb => cb.addEventListener('change', updateSummary));

    outletDropdown.querySelectorAll('[data-action="select-all"]').forEach(button => {
      button.addEventListener('click', function () {
        checkboxes.forEach(cb => { cb.checked = true; });
        updateSummary();
      });
    });

    outletDropdown.querySelectorAll('[data-action="clear"]').forEach(button => {
      button.addEventListener('click', function () {
        checkboxes.forEach(cb => { cb.checked = false; });
        updateSummary();
      });
    });

    updateSummary();
  });
</script>
</body>
</html>
