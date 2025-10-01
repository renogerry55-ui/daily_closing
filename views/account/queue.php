<?php
require __DIR__ . '/../../includes/auth.php';
require_role(['account']);
require __DIR__ . '/../../includes/db.php';

$sql = "SELECT p.id, p.package_date, p.created_at, p.status, p.total_income, p.total_expenses, p.total_balance,
               creator.name AS created_by_name,
               approver.name AS approved_by_name,
               COUNT(DISTINCT hpi.submission_id) AS submission_count,
               COALESCE(SUM(s.total_income), 0) AS sum_income,
               COALESCE(SUM(s.total_expenses), 0) AS sum_expenses,
               COALESCE(SUM(s.balance), 0) AS sum_balance,
               COALESCE(SUM(hpi.pass_to_hq), 0) AS sum_pass_to_hq,
               MAX(s.date) AS last_submission_date
        FROM hq_packages p
        LEFT JOIN users creator ON creator.id = p.created_by
        LEFT JOIN users approver ON approver.id = p.approved_by
        LEFT JOIN hq_package_items hpi ON hpi.package_id = p.id
        LEFT JOIN submissions s ON s.id = hpi.submission_id
        GROUP BY p.id
        ORDER BY COALESCE(p.package_date, p.created_at) DESC, p.id DESC";

$packages = $pdo->query($sql)->fetchAll();

function format_money($amount): string
{
    if ($amount === null || $amount === '') {
        $amount = 0;
    }
    return 'RM ' . number_format((float) $amount, 2);
}

function format_date(?string $date, string $fallback = '—'): string
{
    if (!$date) {
        return $fallback;
    }
    $ts = strtotime($date);
    if (!$ts) {
        return $fallback;
    }
    return date('Y-m-d', $ts);
}

function format_datetime(?string $date, string $fallback = '—'): string
{
    if (!$date) {
        return $fallback;
    }
    $ts = strtotime($date);
    if (!$ts) {
        return $fallback;
    }
    return date('Y-m-d H:i', $ts);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Account HQ Packages</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom mb-4">
  <div class="container">
    <a class="navbar-brand" href="#">Daily Closing</a>
    <div class="ms-auto">
      <a class="btn btn-outline-danger btn-sm" href="/daily_closing/logout.php">Logout</a>
    </div>
  </div>
</nav>
<main class="container pb-5">
  <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
    <div>
      <h1 class="h3 mb-0">Approval Queue</h1>
      <div class="text-muted small">Review HQ packages and export supporting data.</div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th scope="col">Package</th>
              <th scope="col">Package Date</th>
              <th scope="col">Created By</th>
              <th scope="col">Approved By</th>
              <th scope="col">Submissions</th>
              <th scope="col" class="text-end">Sales</th>
              <th scope="col" class="text-end">Expenses</th>
              <th scope="col" class="text-end">Balance</th>
              <th scope="col" class="text-end">Pass to HQ</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$packages): ?>
              <tr>
                <td colspan="11" class="text-center text-muted py-4">No packages have been submitted yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($packages as $pkg): ?>
                <?php
                  $status = $pkg['status'] ?? 'pending';
                  $badgeClass = match ($status) {
                      'approved' => 'bg-success',
                      'rejected' => 'bg-danger',
                      'processing' => 'bg-info text-dark',
                      default => 'bg-secondary',
                  };
                  $totalIncome = $pkg['total_income'] ?? $pkg['sum_income'];
                  $totalExpenses = $pkg['total_expenses'] ?? $pkg['sum_expenses'];
                  $totalBalance = $pkg['total_balance'] ?? $pkg['sum_balance'];
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold">#<?= htmlspecialchars((string) $pkg['id']) ?></div>
                    <div class="small text-muted">Last submission: <?= htmlspecialchars($pkg['last_submission_date'] ? format_date($pkg['last_submission_date']) : '—') ?></div>
                  </td>
                  <td><?= htmlspecialchars(format_date($pkg['package_date'], format_date($pkg['created_at']))) ?></td>
                  <td><?= htmlspecialchars($pkg['created_by_name'] ?? '—') ?></td>
                  <td>
                    <?php if ($pkg['approved_by_name']): ?>
                      <div><?= htmlspecialchars($pkg['approved_by_name']) ?></div>
                      <div class="small text-muted"><?= htmlspecialchars(format_datetime($pkg['approved_at'] ?? null)) ?></div>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td><?= (int) $pkg['submission_count'] ?></td>
                  <td class="text-end"><?= htmlspecialchars(format_money($totalIncome)) ?></td>
                  <td class="text-end"><?= htmlspecialchars(format_money($totalExpenses)) ?></td>
                  <td class="text-end"><?= htmlspecialchars(format_money($totalBalance)) ?></td>
                  <td class="text-end"><?= htmlspecialchars(format_money($pkg['sum_pass_to_hq'])) ?></td>
                  <td>
                    <span class="badge <?= $badgeClass ?> text-uppercase"><?= htmlspecialchars($status) ?></span>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-primary btn-sm" href="/daily_closing/account_hq_package_show.php?package_id=<?= urlencode((string) $pkg['id']) ?>">Open</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
</body>
</html>
