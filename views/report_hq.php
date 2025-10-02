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
         s.total_income, s.total_expenses, s.balance, s.pass_to_office,
         s.status, s.submitted_to_hq_at,
         COUNT(r.id) AS receipts_count
  FROM submissions s
  JOIN outlets o ON o.id = s.outlet_id
  LEFT JOIN receipts r ON r.submission_id = s.id
  WHERE s.manager_id = ? AND s.date = ?
  GROUP BY s.id, s.outlet_id, o.name, s.total_income, s.total_expenses, s.balance, s.pass_to_office, s.status, s.submitted_to_hq_at
  ORDER BY o.name
");
$stmt->execute([$managerId, $reportDate]);
$subs = $stmt->fetchAll();

// index by outlet for quick lookup
$byOutlet = [];
foreach ($subs as $row) { $byOutlet[(int)$row['outlet_id']][] = $row; }

// precompute sums
$overallInc = 0.0; $overallExp = 0.0; $overallBal = 0.0; $overallPass = 0.0; $overallReceipts = 0;
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
      <a class="btn btn-outline-secondary btn-sm" href="/daily_closing/manager_hq_batches.php">HQ History</a>
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

  <?php $blockedOutlets = 0; ?>
  <form class="card card-body" method="post" action="/daily_closing/hq_batch_store.php" enctype="multipart/form-data" id="hqBatchForm">
    <input type="hidden" name="date" value="<?= htmlspecialchars($reportDate) ?>">

    <div class="alert alert-info d-flex flex-wrap align-items-center gap-3 mb-4">
      <div><strong>Total to send:</strong> RM <span id="summaryTotal">0.00</span></div>
      <div><strong>Outlets:</strong> <span id="summaryOutlets">0</span></div>
      <div><strong>Submissions:</strong> <span id="summarySubs">0</span></div>
      <div><strong>Receipts attached:</strong> <span id="summaryReceipts">0</span></div>
    </div>

    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th style="width:60px;">Include</th>
            <th>Outlet</th>
            <th class="text-end" style="width:150px;">Income (RM)</th>
            <th class="text-end" style="width:150px;">Expenses (RM)</th>
            <th class="text-end" style="width:150px;">Balance (RM)</th>
            <th class="text-end" style="width:150px;">Pass to Office (RM)</th>
            <th style="width:140px;">Receipts</th>
            <th style="width:140px;">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($outlets as $o):
          $oid = (int)$o['id'];
          $name = $o['name'];
          $rows = $byOutlet[$oid] ?? [];
          $inc=0.0; $exp=0.0; $bal=0.0; $pass=0.0; $receipts=0; $includeIds=[]; $status='—'; $blocked=false;
          if ($rows) {
            foreach ($rows as $r) {
              $inc += (float)$r['total_income'];
              $exp += (float)$r['total_expenses'];
              $bal += (float)$r['balance'];
              if (empty($r['submitted_to_hq_at'])) {
                $includeIds[] = (int)$r['id'];
                $status = $r['status'];
                $pass += (float)$r['pass_to_office'];
                $receipts += (int)$r['receipts_count'];
                if ((int)$r['receipts_count'] === 0) {
                  $blocked = true;
                }
              }
            }
          }

          $overallPass += $pass;
          $overallReceipts += $receipts;
          if ($includeIds && $blocked) {
            $blockedOutlets++;
          }

          $overallInc += $inc; $overallExp += $exp; $overallBal += $bal;
          $canInclude = !empty($includeIds) && !$blocked;
        ?>
          <tr>
            <td>
              <?php if ($canInclude): ?>
                <?php foreach ($includeIds as $sid): ?>
                  <input type="hidden" name="submissions_by_outlet[<?= $oid ?>][]" value="<?= $sid ?>">
                <?php endforeach; ?>
                <input type="checkbox" class="form-check-input" name="include_outlets[]" value="<?= $oid ?>" checked data-pass="<?= number_format($pass, 2, '.', '') ?>" data-receipts="<?= (int)$receipts ?>" data-submissions="<?= count($includeIds) ?>">
              <?php else: ?>
                <input class="form-check-input" type="checkbox" disabled>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($name) ?></td>
            <td class="text-end"><?= number_format($inc,2) ?></td>
            <td class="text-end"><?= number_format($exp,2) ?></td>
            <td class="text-end"><?= number_format($bal,2) ?></td>
            <td class="text-end"><?= number_format($pass,2) ?></td>
            <td>
              <?php if ($includeIds): ?>
                <span class="badge <?= $blocked ? 'text-bg-warning text-dark' : 'text-bg-light text-dark' ?>">📎 <?= (int)$receipts ?></span>
                <?php if ($blocked): ?><small class="text-danger d-block">Attach receipts to enable</small><?php endif; ?>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php
                $badge = ($status==='pending'?'warning':($status==='approved'?'success':($status==='rejected'?'danger':'secondary')));
              ?>
              <span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
              <?php if (!$rows): ?><small class="text-muted d-block">No submission</small><?php endif; ?>
              <?php if ($includeIds && !$canInclude): ?><small class="text-danger d-block">Receipts required</small><?php endif; ?>
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
            <td class="text-end"><?= number_format($overallPass,2) ?></td>
            <td></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <?php if ($blockedOutlets > 0): ?>
      <div class="alert alert-warning mt-3"><?= $blockedOutlets ?> outlet<?= $blockedOutlets === 1 ? '' : 's' ?> require receipts before they can be included.</div>
    <?php endif; ?>

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

<script>
  (function(){
    const form = document.getElementById('hqBatchForm');
    if (!form) return;
    const totalEl = document.getElementById('summaryTotal');
    const outletsEl = document.getElementById('summaryOutlets');
    const subsEl = document.getElementById('summarySubs');
    const receiptsEl = document.getElementById('summaryReceipts');
    const checkboxes = Array.from(form.querySelectorAll('input[name="include_outlets[]"]'));

    const updateSummary = () => {
      let total = 0;
      let outlets = 0;
      let submissions = 0;
      let receipts = 0;
      checkboxes.forEach(cb => {
        if (cb.disabled) return;
        if (cb.checked) {
          outlets += 1;
          total += parseFloat(cb.dataset.pass || '0');
          submissions += parseInt(cb.dataset.submissions || '0', 10) || 0;
          receipts += parseInt(cb.dataset.receipts || '0', 10) || 0;
        }
      });
      totalEl.textContent = total.toFixed(2);
      outletsEl.textContent = outlets.toString();
      subsEl.textContent = submissions.toString();
      receiptsEl.textContent = receipts.toString();
    };

    checkboxes.forEach(cb => cb.addEventListener('change', updateSummary));
    updateSummary();

    form.addEventListener('submit', function (event) {
      updateSummary();
      const outlets = parseInt(outletsEl.textContent || '0', 10) || 0;
      const total = parseFloat(totalEl.textContent || '0');
      if (outlets === 0) {
        event.preventDefault();
        alert('Select at least one outlet to build the HQ batch.');
        return;
      }
      const message = `You are sending RM ${total.toFixed(2)} across ${outlets} outlet${outlets === 1 ? '' : 's'}. Continue?`;
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  })();
</script>
</body>
</html>
