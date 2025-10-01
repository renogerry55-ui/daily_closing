<?php
require __DIR__ . '/../../includes/auth.php';
require_role(['account']);
require __DIR__ . '/../../includes/db.php';

$sql = "SELECT b.id,
               b.report_date,
               b.status,
               b.overall_total_income,
               b.overall_total_expenses,
               b.overall_balance,
               b.created_at,
               b.updated_at,
               m.name AS manager_name,
               COUNT(DISTINCT hbs.submission_id) AS submission_count,
               COUNT(DISTINCT s.outlet_id)     AS outlet_count,
               MAX(s.submitted_to_hq_at)       AS submitted_at,
               MAX(s.date)                     AS last_submission_date
        FROM hq_batches b
        INNER JOIN users m ON m.id = b.manager_id
        LEFT JOIN hq_batch_submissions hbs ON hbs.hq_batch_id = b.id
        LEFT JOIN submissions s ON s.id = hbs.submission_id
        WHERE b.status IN ('submitted', 'processing')
        GROUP BY b.id
        ORDER BY b.report_date DESC, b.id DESC";

$batches = $pdo->query($sql)->fetchAll();

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
              <th scope="col">Batch</th>
              <th scope="col">Business Date</th>
              <th scope="col">Manager</th>
              <th scope="col" class="text-center">Outlets</th>
              <th scope="col" class="text-center">Submissions</th>
              <th scope="col" class="text-end">Income (RM)</th>
              <th scope="col" class="text-end">Expenses (RM)</th>
              <th scope="col" class="text-end">Balance (RM)</th>
              <th scope="col">Submitted At</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$batches): ?>
              <tr>
                <td colspan="11" class="text-center text-muted py-4">No HQ submissions are waiting for review.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($batches as $batch): ?>
                <?php
                  $status = $batch['status'] ?? 'submitted';
                  $badgeClass = match ($status) {
                      'submitted'  => 'bg-warning text-dark',
                      'processing' => 'bg-info text-dark',
                      'approved'   => 'bg-success',
                      'recorded'   => 'bg-primary',
                      'rejected'   => 'bg-danger',
                      default      => 'bg-secondary',
                  };
                  $submittedAt = $batch['submitted_at'] ? format_datetime($batch['submitted_at']) : '—';
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold">#<?= htmlspecialchars((string) $batch['id']) ?></div>
                    <div class="small text-muted">Last submission: <?= htmlspecialchars($batch['last_submission_date'] ? format_date($batch['last_submission_date']) : '—') ?></div>
                  </td>
                  <td><?= htmlspecialchars(format_date($batch['report_date'], '—')) ?></td>
                  <td><?= htmlspecialchars($batch['manager_name'] ?? '—') ?></td>
                  <td class="text-center"><?= (int) $batch['outlet_count'] ?></td>
                  <td class="text-center"><?= (int) $batch['submission_count'] ?></td>
                  <td class="text-end"><?= htmlspecialchars(format_money($batch['overall_total_income'])) ?></td>
                  <td class="text-end"><?= htmlspecialchars(format_money($batch['overall_total_expenses'])) ?></td>
                  <td class="text-end"><?= htmlspecialchars(format_money($batch['overall_balance'])) ?></td>
                  <td><?= htmlspecialchars($submittedAt) ?></td>
                  <td>
                    <span class="badge <?= $badgeClass ?> text-uppercase"><?= htmlspecialchars($status) ?></span>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-primary btn-sm" href="/daily_closing/account_hq_package_show.php?batch_id=<?= urlencode((string) $batch['id']) ?>">Open</a>
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
