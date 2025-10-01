<?php
// /daily_closing/views/manager_hq_batch_view.php
// Variables: $batch, $submissions, $outletTotals, $files, $submittedAt
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HQ Batch #<?= (int)$batch['id'] ?> — Manager</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; }
    .receipt-card { max-width: 960px; margin: 0 auto; }
    .section + .section { border-top: 1px dashed #dee2e6; }
    .section { padding: 1.5rem 0; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/daily_closing/views/manager/dashboard.php">Daily Closing</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/manager_hq_batches.php">Back to HQ History</a>
      <a class="btn btn-outline-danger btn-sm" href="/daily_closing/logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="card shadow-sm receipt-card">
    <div class="card-body">
      <header class="pb-3 mb-3 border-bottom border-2">
        <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
          <div>
            <h1 class="h4 mb-1">HQ Submission Receipt</h1>
            <div class="text-muted small">Batch ID: <?= (int)$batch['id'] ?></div>
          </div>
          <div class="text-end">
            <?php
              $badge = match ($batch['status']) {
                'submitted'    => 'warning',
                'processing'   => 'info',
                'acknowledged' => 'primary',
                'completed'    => 'success',
                'rejected'     => 'danger',
                default        => 'secondary',
              };
            ?>
            <span class="badge text-bg-<?= $badge ?>">Status: <?= htmlspecialchars(ucfirst($batch['status'])) ?></span>
            <?php if ($submittedAt): ?>
              <div class="small text-muted mt-1">Sent: <?= htmlspecialchars(date('Y-m-d H:i', $submittedAt)) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="row mt-3 g-3">
          <div class="col-sm-4">
            <div class="fw-semibold text-muted text-uppercase small">Business date</div>
            <div class="fs-5"><?= htmlspecialchars($batch['report_date']) ?></div>
          </div>
          <div class="col-sm-4">
            <div class="fw-semibold text-muted text-uppercase small">Overall Balance</div>
            <div class="fs-5">RM <?= number_format((float)$batch['overall_balance'], 2) ?></div>
          </div>
          <div class="col-sm-4">
            <div class="fw-semibold text-muted text-uppercase small">Overall Income / Expenses</div>
            <div>RM <?= number_format((float)$batch['overall_total_income'], 2) ?> / RM <?= number_format((float)$batch['overall_total_expenses'], 2) ?></div>
          </div>
        </div>
        <?php if (!empty($batch['notes'])): ?>
          <div class="mt-3">
            <div class="fw-semibold text-muted text-uppercase small">Notes</div>
            <div><?= nl2br(htmlspecialchars($batch['notes'])) ?></div>
          </div>
        <?php endif; ?>
      </header>

      <section class="section">
        <h2 class="h5">Per-Outlet Summary</h2>
        <?php if (!$outletTotals): ?>
          <p class="text-muted mb-0">No outlet breakdown available.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-borderless mb-0">
              <thead class="text-muted text-uppercase small">
                <tr>
                  <th>Outlet</th>
                  <th class="text-end" style="width:160px;">Income (RM)</th>
                  <th class="text-end" style="width:170px;">Expenses (RM)</th>
                  <th class="text-end" style="width:150px;">Balance (RM)</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($outletTotals as $outlet => $totals): ?>
                <tr>
                  <td><?= htmlspecialchars($outlet) ?></td>
                  <td class="text-end"><?= number_format($totals['income'], 2) ?></td>
                  <td class="text-end"><?= number_format($totals['expenses'], 2) ?></td>
                  <td class="text-end"><?= number_format($totals['balance'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="section">
        <h2 class="h5">Included Submissions</h2>
        <div class="table-responsive">
          <table class="table table-striped table-sm align-middle mb-0">
            <thead>
              <tr>
                <th style="width:110px;">Submission</th>
                <th style="width:130px;">Date</th>
                <th>Outlet</th>
                <th class="text-end" style="width:140px;">Income (RM)</th>
                <th class="text-end" style="width:150px;">Expenses (RM)</th>
                <th class="text-end" style="width:140px;">Balance (RM)</th>
                <th style="width:140px;">Status</th>
                <th style="width:110px;">Link</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$submissions): ?>
              <tr>
                <td colspan="8" class="text-center text-muted">No submissions were linked to this batch.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($submissions as $row): ?>
                <?php
                  $badge = match ($row['status']) {
                    'pending'  => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'recorded' => 'secondary',
                    default    => 'light',
                  };
                ?>
                <tr>
                  <td>#<?= (int)$row['id'] ?></td>
                  <td><?= htmlspecialchars($row['date']) ?></td>
                  <td><?= htmlspecialchars($row['outlet_name']) ?></td>
                  <td class="text-end"><?= number_format((float)$row['total_income'], 2) ?></td>
                  <td class="text-end"><?= number_format((float)$row['total_expenses'], 2) ?></td>
                  <td class="text-end"><?= number_format((float)$row['balance'], 2) ?></td>
                  <td>
                    <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span>
                  </td>
                  <td>
                    <a class="btn btn-sm btn-outline-secondary" href="/daily_closing/manager_submission_view.php?id=<?= (int)$row['id'] ?>">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="section">
        <h2 class="h5">HQ Attachments</h2>
        <?php if (!$files): ?>
          <p class="text-muted mb-0">No attachments uploaded for this batch.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($files as $file): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <a href="/daily_closing<?= htmlspecialchars($file['file_path']) ?>" target="_blank">
                  <?= htmlspecialchars($file['original_name']) ?>
                </a>
                <?php if (!empty($file['size_bytes'])): ?>
                  <small class="text-muted">
                    <?php
                      $size = (float)$file['size_bytes'];
                      if ($size >= 1048576) {
                        echo number_format($size / 1048576, 2) . ' MB';
                      } elseif ($size >= 1024) {
                        echo number_format($size / 1024, 1) . ' KB';
                      } else {
                        echo (int)$size . ' B';
                      }
                    ?>
                  </small>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>
  </div>
</main>

</body>
</html>
