<?php
// /daily_closing/views/manager_submission_view.php
// Variables: $submission, $incomeItems, $expenseItems, $receipts
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Submission #<?= (int)$submission['id'] ?> — Manager</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f5f5f5; }
    .receipt-card { max-width: 900px; margin: 0 auto; }
    .receipt-header { border-bottom: 2px dashed #dee2e6; }
    .receipt-section + .receipt-section { border-top: 1px dashed #e9ecef; }
    .receipt-section { padding: 1.25rem 0; }
    .table-receipt th, .table-receipt td { padding: .5rem; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/daily_closing/views/manager/dashboard.php">Daily Closing</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/manager_hq_batches.php">HQ History</a>
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/manager_submissions.php">Back to submissions</a>
      <button class="btn btn-dark btn-sm" onclick="window.print()">Print</button>
    </div>
  </div>
</nav>

<main class="container py-4">
  <div class="card shadow-sm receipt-card">
    <div class="card-body">
      <div class="receipt-header pb-3 mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <h2 class="h4 mb-1">Daily Closing Receipt</h2>
            <div class="text-muted small">Submission ID: <?= (int)$submission['id'] ?></div>
          </div>
          <div class="text-end">
            <?php
              $badge = match ($submission['status']) {
                'pending'  => 'warning',
                'approved' => 'success',
                'rejected' => 'danger',
                'recorded' => 'secondary',
                default    => 'light'
              };
            ?>
            <span class="badge text-bg-<?= $badge ?>">Status: <?= htmlspecialchars(ucfirst($submission['status'])) ?></span>
          </div>
        </div>
        <div class="row mt-3 g-3">
          <div class="col-sm-6">
            <div class="fw-semibold text-muted text-uppercase small">Outlet</div>
            <div class="fs-5"><?= htmlspecialchars($submission['outlet_name']) ?></div>
          </div>
          <div class="col-sm-3">
            <div class="fw-semibold text-muted text-uppercase small">Date</div>
            <div class="fs-5"><?= htmlspecialchars($submission['date']) ?></div>
          </div>
          <div class="col-sm-3">
            <div class="fw-semibold text-muted text-uppercase small">Balance</div>
            <div class="fs-5">RM <?= number_format((float)$submission['balance'], 2) ?></div>
          </div>
        </div>
      </div>

      <div class="receipt-section">
        <h3 class="h5">Income</h3>
        <div class="table-responsive">
          <table class="table table-sm table-borderless table-receipt">
            <thead class="text-uppercase text-muted small">
              <tr>
                <th style="width:25%">Category</th>
                <th>Description</th>
                <th class="text-end" style="width:20%">Amount (RM)</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$incomeItems): ?>
              <tr>
                <td colspan="3" class="text-center text-muted">No income items.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($incomeItems as $item): ?>
                <tr>
                  <td><?= htmlspecialchars($item['category']) ?></td>
                  <td><?= htmlspecialchars($item['description'] ?: '-') ?></td>
                  <td class="text-end"><?= number_format((float)$item['amount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <tfoot class="border-top">
              <tr>
                <th colspan="2" class="text-end">Total Income</th>
                <th class="text-end">RM <?= number_format((float)$submission['total_income'], 2) ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="receipt-section">
        <h3 class="h5">Expenses</h3>
        <div class="table-responsive">
          <table class="table table-sm table-borderless table-receipt">
            <thead class="text-uppercase text-muted small">
              <tr>
                <th style="width:25%">Category</th>
                <th>Description</th>
                <th class="text-end" style="width:20%">Amount (RM)</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!$expenseItems): ?>
              <tr>
                <td colspan="3" class="text-center text-muted">No expense items.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($expenseItems as $item): ?>
                <tr>
                  <td><?= htmlspecialchars($item['category']) ?></td>
                  <td><?= htmlspecialchars($item['description'] ?: '-') ?></td>
                  <td class="text-end"><?= number_format((float)$item['amount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <tfoot class="border-top">
              <tr>
                <th colspan="2" class="text-end">Total Expenses</th>
                <th class="text-end">RM <?= number_format((float)$submission['total_expenses'], 2) ?></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="receipt-section">
        <div class="row g-3 align-items-center">
          <div class="col-sm-6">
            <div class="fw-semibold text-muted text-uppercase small">Net Balance</div>
            <div class="fs-4">RM <?= number_format((float)$submission['balance'], 2) ?></div>
          </div>
          <?php if (!empty($submission['notes'])): ?>
            <div class="col-sm-6">
              <div class="fw-semibold text-muted text-uppercase small">Notes</div>
              <div><?= nl2br(htmlspecialchars($submission['notes'])) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="receipt-section">
        <h3 class="h5">Receipts &amp; Attachments</h3>
        <?php if (!$receipts): ?>
          <p class="text-muted mb-0">No receipts uploaded.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($receipts as $rec): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <a href="/daily_closing<?= htmlspecialchars($rec['file_path']) ?>" target="_blank">
                  <?= htmlspecialchars($rec['original_name']) ?>
                </a>
                <?php if (!empty($rec['size_bytes'])): ?>
                  <span class="text-muted small">
                    <?php
                      $size = (float)$rec['size_bytes'];
                      if ($size >= 1048576) {
                        echo number_format($size / 1048576, 2) . ' MB';
                      } elseif ($size >= 1024) {
                        echo number_format($size / 1024, 1) . ' KB';
                      } else {
                        echo (int)$size . ' B';
                      }
                    ?>
                  </span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

</body>
</html>
