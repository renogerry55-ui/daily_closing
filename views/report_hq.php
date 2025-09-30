<?php
// /daily_closing/views/report_hq.php
require_once dirname(__DIR__) . '/includes/auth_guard.php';
require_once dirname(__DIR__) . '/includes/db.php';

guard_manager();
$managerId = current_manager_id();
$outlets   = allowed_outlets($pdo);

$reportDate = $_GET['date'] ?? (new DateTime('today'))->format('Y-m-d');

// fetch submissions for that date per outlet (only those not yet sent to HQ)
$stmt = $pdo->prepare("
  SELECT s.id, s.outlet_id, o.name AS outlet_name,
         s.total_income, s.total_expenses, s.balance,
         s.status, s.submitted_to_hq_at
  FROM submissions s
  JOIN outlets o ON o.id = s.outlet_id
  WHERE s.manager_id = ? AND s.date = ?
  ORDER BY o.name
");
$stmt->execute([$managerId, $reportDate]);
$subs = $stmt->fetchAll();

// index by outlet for quick lookup
$byOutlet = [];
foreach ($subs as $row) { $byOutlet[(int)$row['outlet_id']][] = $row; }

// precompute sums
$overallInc = 0.0; $overallExp = 0.0; $overallBal = 0.0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Report to HQ — Finalize</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="/daily_closing/views/manager/dashboard.php">Daily Closing</a>
    <div class="ms-auto d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/manager_submissions.php">My Submissions</a>
      <a class="btn btn-outline-danger btn-sm" href="/daily_closing/logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= nl2br(htmlspecialchars($_SESSION['flash_error'])); unset($_SESSION['flash_error']); ?></div>
  <?php endif; ?>
  <?php if (!empty($_SESSION['flash_ok'])): ?>
    <div class="alert alert-success"><?= nl2br(htmlspecialchars($_SESSION['flash_ok'])); unset($_SESSION['flash_ok']); ?></div>
  <?php endif; ?>

  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <form class="d-flex align-items-end gap-2" method="get">
      <div>
        <label class="form-label mb-0">Business date</label>
        <input type="date" name="date" value="<?= htmlspecialchars($reportDate) ?>" class="form-control">
      </div>
      <button class="btn btn-primary" type="submit">Load</button>
    </form>
  </div>

  <form class="card card-body" method="post" action="/daily_closing/hq_batch_store.php" enctype="multipart/form-data">
    <input type="hidden" name="date" value="<?= htmlspecialchars($reportDate) ?>">

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th style="width:60px;">Include</th>
            <th>Outlet</th>
            <th class="text-end" style="width:160px;">Income (RM)</th>
            <th class="text-end" style="width:170px;">Expenses (RM)</th>
            <th class="text-end" style="width:150px;">Balance (RM)</th>
            <th style="width:140px;">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($outlets as $o): 
          $oid = (int)$o['id'];
          $name = $o['name'];
          $rows = $byOutlet[$oid] ?? [];
          // if multiple submissions per outlet per date are allowed, sum them all
          $inc=0.0; $exp=0.0; $bal=0.0; $includeIds=[]; $allSubmitted=true; $status='—';
          if ($rows) {
            $allSubmitted=true;
            foreach ($rows as $r) {
              $inc += (float)$r['total_income'];
              $exp += (float)$r['total_expenses'];
              $bal += (float)$r['balance'];
              if (empty($r['submitted_to_hq_at'])) { $includeIds[] = (int)$r['id']; $allSubmitted=false; $status=$r['status']; }
            }
          } else {
            $allSubmitted=false; $status='none';
          }
          $overallInc += $inc; $overallExp += $exp; $overallBal += $bal;

          // can include only if there is at least one not-submitted submission
          $canInclude = !empty($includeIds);
        ?>
          <tr>
            <td>
              <?php if ($canInclude): ?>
                <?php foreach ($includeIds as $sid): ?>
                  <input type="hidden" name="submissions_by_outlet[<?= $oid ?>][]" value="<?= $sid ?>">
                <?php endforeach; ?>
                <input type="checkbox" class="form-check-input" name="include_outlets[]" value="<?= $oid ?>" checked>
              <?php else: ?>
                <input class="form-check-input" type="checkbox" disabled>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($name) ?></td>
            <td class="text-end"><?= number_format($inc,2) ?></td>
            <td class="text-end"><?= number_format($exp,2) ?></td>
            <td class="text-end"><?= number_format($bal,2) ?></td>
            <td>
              <?php
                $badge = ($status==='pending'?'warning':($status==='approved'?'success':($status==='rejected'?'danger':'secondary')));
              ?>
              <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
              <?php if (!$rows): ?><small class="text-muted d-block">No submission</small><?php endif; ?>
              <?php if ($rows && empty($includeIds)): ?><small class="text-muted d-block">Already in HQ</small><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-semibold">
            <td></td>
            <td>Total</td>
            <td class="text-end"><?= number_format($overallInc,2) ?></td>
            <td class="text-end"><?= number_format($overallExp,2) ?></td>
            <td class="text-end"><?= number_format($overallBal,2) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="row g-3 mt-2">
      <div class="col-md-8">
        <label class="form-label">Notes (optional)</label>
        <textarea name="notes" class="form-control" rows="3" placeholder="Bank-in information, reference no., remarks…"></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label">HQ attachments (bank-in slip etc.)</label>
        <input type="file" name="hq_files[]" multiple class="form-control" accept=".pdf,.jpg,.jpeg,.png">
        <div class="form-text">PDF/JPG/PNG, up to 20MB each.</div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button class="btn btn-success" type="submit">Submit to HQ</button>
      <a href="/daily_closing/views/manager/dashboard.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</main>
</body>
</html>
