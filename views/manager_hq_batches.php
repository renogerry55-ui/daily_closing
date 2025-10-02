<?php
// /daily_closing/views/manager_hq_batches.php
// Variables: $batches, $status
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HQ Submissions — Manager</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/daily_closing/views/manager/dashboard.php">Daily Closing</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/manager_submissions.php">My Submissions</a>
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/views/report_hq.php">Submit to HQ</a>
      <a class="btn btn-outline-danger btn-sm" href="/daily_closing/logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h1 class="h4 mb-0">HQ Submission History</h1>
    <div class="ms-auto d-flex gap-2">
      <?php
        $filters = [
          'all'         => 'All',
          'submitted'   => 'Submitted',
          'processing'  => 'Processing',
          'acknowledged'=> 'Acknowledged',
          'completed'   => 'Completed',
          'rejected'    => 'Rejected',
        ];
        foreach ($filters as $key => $label):
      ?>
        <a class="btn btn-sm <?= $status === $key ? 'btn-dark' : 'btn-outline-dark' ?>"
           href="/daily_closing/manager_hq_batches.php?status=<?= urlencode($key) ?>"><?= $label ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$batches): ?>
    <div class="text-center text-muted py-5">
      No HQ batches yet. <a href="/daily_closing/views/report_hq.php">Submit today's totals</a>.
    </div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:110px;">Batch</th>
              <th style="width:120px;">Business Date</th>
              <th>Outlets</th>
              <th>Submissions</th>
              <th class="text-end" style="width:140px;">Income (RM)</th>
              <th class="text-end" style="width:150px;">Expenses (RM)</th>
              <th class="text-end" style="width:150px;">Pass to Office (RM)</th>
              <th class="text-end" style="width:140px;">Balance (RM)</th>
              <th style="width:150px;">Status</th>
              <th style="width:120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($batches as $batch): ?>
            <?php
              $badge = match ($batch['status']) {
                'submitted'    => 'warning',
                'processing'   => 'info',
                'acknowledged' => 'primary',
                'completed'    => 'success',
                'rejected'     => 'danger',
                default        => 'secondary',
              };
              $submittedAt = $batch['submitted_at'] ?? null;
              $submittedLabel = null;
              if ($submittedAt) {
                $ts = strtotime($submittedAt);
                if ($ts) { $submittedLabel = date('Y-m-d H:i', $ts); }
              }
            ?>
            <tr>
              <td>#<?= (int)$batch['id'] ?></td>
              <td><?= htmlspecialchars($batch['report_date']) ?></td>
              <td><?= (int)$batch['outlet_count'] ?></td>
              <td><?= (int)$batch['submission_count'] ?></td>
              <td class="text-end"><?= number_format((float)$batch['overall_total_income'], 2) ?></td>
              <td class="text-end"><?= number_format((float)$batch['overall_total_expenses'], 2) ?></td>
              <td class="text-end"><?= number_format((float)$batch['overall_pass_to_office'], 2) ?></td>
              <td class="text-end"><?= number_format((float)$batch['overall_balance'], 2) ?></td>
              <td>
                <div class="d-flex flex-column">
                  <span class="badge text-bg-<?= $badge ?> mb-1"><?= htmlspecialchars(ucfirst($batch['status'])) ?></span>
                  <?php if ($submittedLabel): ?>
                    <small class="text-muted">Sent <?= htmlspecialchars($submittedLabel) ?></small>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <a class="btn btn-sm btn-outline-secondary" href="/daily_closing/manager_hq_batch_view.php?id=<?= (int)$batch['id'] ?>">View</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</main>

</body>
</html>
