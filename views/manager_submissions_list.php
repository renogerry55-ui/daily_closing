<?php
// /daily_closing/views/manager_submissions_list.php
// Variables available:
// $rows, $status, $outlets, $outletId, $dateFrom, $dateTo, $page, $pages, $total, $queryParams
$queryParams = $queryParams ?? [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Submissions — Manager</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/daily_closing/views/manager/dashboard.php">Daily Closing</a>
    <div class="ms-auto">
      <a class="btn btn-primary btn-sm" href="/daily_closing/views/manager_submission_create.php">New Submission</a>
      <a class="btn btn-outline-danger btn-sm" href="/daily_closing/logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container py-4">

  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <?php
      $tabs = ['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','recorded'=>'Recorded'];
      foreach ($tabs as $k=>$label):
    ?>
      <a class="btn btn-sm <?= $k===$status?'btn-dark':'btn-outline-dark' ?>"
         href="/daily_closing/manager_submissions.php?status=<?= urlencode($k) ?>"> <?= $label ?> </a>
    <?php endforeach; ?>
    <span class="ms-auto text-muted small">Total: <?= (int)$total ?></span>
  </div>

  <!-- Filter form -->
  <form class="card card-body mb-3" method="get" action="/daily_closing/manager_submissions.php">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Outlet</label>
        <select name="outlet_id" class="form-select">
          <option value="" <?= $outletId === null ? 'selected' : '' ?>>All outlets</option>
          <?php foreach ($outlets as $o): ?>
            <?php $selected = ($outletId !== null && (int)$o['id'] === (int)$outletId) ? 'selected' : ''; ?>
            <option value="<?= (int)$o['id'] ?>" <?= $selected ?>>
              <?= htmlspecialchars($o['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Date from</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date to</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control">
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">Filter</button>
      </div>
    </div>
  </form>

  <?php if (!$rows): ?>
    <div class="text-center text-muted py-5">
      No submissions found. <a href="/daily_closing/views/manager_submission_create.php">Create one</a>.
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th style="width:110px;">Date</th>
            <th>Outlet</th>
            <th class="text-end" style="width:140px;">Income (RM)</th>
            <th class="text-end" style="width:150px;">Expenses (RM)</th>
            <th class="text-end" style="width:140px;">Balance (RM)</th>
            <th style="width:120px;">Status</th>
            <th style="width:110px;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['date']) ?></td>
            <td><?= htmlspecialchars($r['outlet']) ?></td>
            <td class="text-end"><?= number_format((float)$r['total_income'],2) ?></td>
            <td class="text-end"><?= number_format((float)$r['total_expenses'],2) ?></td>
            <td class="text-end"><?= number_format((float)$r['balance'],2) ?></td>
            <td>
              <?php
                $badge = match ($r['status']) {
                  'pending'  => 'warning',
                  'approved' => 'success',
                  'rejected' => 'danger',
                  'recorded' => 'secondary',
                  default    => 'light'
                };
              ?>
              <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
            </td>
            <td>
              <a class="btn btn-sm btn-outline-secondary" href="/daily_closing/manager_submission_view.php?id=<?= (int)$r['id'] ?>">
                View
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <nav aria-label="pagination">
        <ul class="pagination">
          <?php
            $baseQuery = $queryParams ?? [];
            for ($p = 1; $p <= $pages; $p++):
              $pageQuery = $baseQuery;
              $pageQuery['page'] = $p;
              $pageUrl = '/daily_closing/manager_submissions.php?' . http_build_query($pageQuery);
          ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
              <a class="page-link" href="<?= htmlspecialchars($pageUrl) ?>">
                 <?= $p ?>
              </a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>

</main>
</body>
</html>
