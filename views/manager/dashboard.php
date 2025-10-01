<?php
require __DIR__ . '/../../includes/auth_guard.php';
require __DIR__ . '/../../includes/db.php';
guard_manager();

$uid = current_manager_id();
$phpToday = (new DateTimeImmutable('today'))->format('Y-m-d');
$stmtToday = $pdo->query("SELECT CURDATE() AS today");
$today = $stmtToday->fetchColumn() ?: $phpToday;

if ($today !== $phpToday) {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE manager_id = ? AND date = ?");
    $stmtCheck->execute([$uid, $today]);
    $countDbDay = (int)$stmtCheck->fetchColumn();

    if ($countDbDay === 0) {
        $stmtCheck->execute([$uid, $phpToday]);
        if ((int)$stmtCheck->fetchColumn() > 0) {
            $today = $phpToday;
        }
    }
}

// today totals across all outlets
$stmt = $pdo->prepare("
  SELECT
    COALESCE(SUM(total_income),0)  AS inc,
    COALESCE(SUM(total_expenses),0) AS exp,
    COALESCE(SUM(balance),0)        AS bal
  FROM submissions
  WHERE manager_id=? AND date=?
");
$stmt->execute([$uid, $today]);
$tot = $stmt->fetch() ?: ['inc'=>0,'exp'=>0,'bal'=>0];

// per-outlet cards (today)
$stmt2 = $pdo->prepare("
  SELECT o.id, o.name, COALESCE(SUM(s.total_income),0) inc, COALESCE(SUM(s.total_expenses),0) exp, COALESCE(SUM(s.balance),0) bal
  FROM user_outlets uo
  JOIN outlets o ON o.id = uo.outlet_id
  LEFT JOIN submissions s ON s.outlet_id=o.id AND s.manager_id=uo.user_id AND s.date=?
  WHERE uo.user_id=?
  GROUP BY o.id
  ORDER BY o.name
");
$stmt2->execute([$today, $uid]);
$perOutlet = $stmt2->fetchAll();

// load today's submissions + receipts keyed by outlet
$stmt3 = $pdo->prepare("
  SELECT
    s.id,
    s.outlet_id,
    s.date,
    s.total_income,
    s.total_expenses,
    s.balance,
    r.file_path,
    r.original_name
  FROM submissions s
  LEFT JOIN receipts r ON r.submission_id = s.id
  WHERE s.manager_id = ? AND s.date = ?
  ORDER BY s.outlet_id, s.id, r.original_name
");
$stmt3->execute([$uid, $today]);
$todaySubs = [];
while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    $oid = (int)$row['outlet_id'];
    $sid = (int)$row['id'];

    if (!isset($todaySubs[$oid][$sid])) {
        $todaySubs[$oid][$sid] = [
            'id'       => $sid,
            'date'     => $row['date'],
            'income'   => (float)$row['total_income'],
            'expenses' => (float)$row['total_expenses'],
            'balance'  => (float)$row['balance'],
            'receipts' => [],
        ];
    }

    if (!empty($row['file_path'])) {
        $path = $row['file_path'];
        if (strpos($path, '/daily_closing/') !== 0) {
            $path = '/daily_closing' . $path;
        }
        $todaySubs[$oid][$sid]['receipts'][] = [
            'path' => $path,
            'name' => $row['original_name'],
        ];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Manager Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg bg-white border-bottom">
  <div class="container">
    <a class="navbar-brand" href="#">Daily Closing</a>
    <div class="ms-auto">
      <a class="btn btn-outline-danger btn-sm" href="/daily_closing/logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="container py-4">
  <h4 class="mb-3">Today Overview</h4>
  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Sales</div><div class="h4">RM <?= number_format($tot['inc'],2) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Expenses</div><div class="h4">RM <?= number_format($tot['exp'],2) ?></div></div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body"><div class="text-muted">Balance</div><div class="h4">RM <?= number_format($tot['bal'],2) ?></div></div></div></div>
  </div>

  <div class="d-flex gap-2 mb-4">
    <a class="btn btn-primary" href="/daily_closing/views/manager_submission_create.php">➕ Sales / Expenses</a>
    <a class="btn btn-outline-primary" href="/daily_closing/manager_submissions.php">📄 My Submissions</a>
    <a class="btn btn-dark" href="/daily_closing/views/report_hq.php">🏦 Report to HQ</a>
    <a class="btn btn-outline-secondary" href="/daily_closing/manager_hq_batches.php">📚 HQ History</a>
  </div>

  <h5 class="mb-2">Per-Outlet (Today)</h5>
  <div class="row g-3">
    <?php foreach ($perOutlet as $r): ?>
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
          <div class="fw-semibold mb-2"><?= htmlspecialchars($r['name']) ?></div>
          <div class="small text-muted">Sales</div><div>RM <?= number_format($r['inc'],2) ?></div>
          <div class="small text-muted mt-2">Expenses</div><div>RM <?= number_format($r['exp'],2) ?></div>
          <div class="small text-muted mt-2">Balance</div><div>RM <?= number_format($r['bal'],2) ?></div>
          <?php $outletSubs = $todaySubs[(int)$r['id']] ?? []; ?>
          <div class="mt-3 pt-3 border-top">
            <div class="small text-uppercase text-muted fw-semibold mb-2">Today's Receipts</div>
            <?php if ($outletSubs): ?>
              <?php foreach ($outletSubs as $sub): ?>
                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                      <div class="small fw-semibold">Submission <?= htmlspecialchars($sub['date']) ?></div>
                      <div class="small text-muted">Sales RM <?= number_format($sub['income'],2) ?> · Expenses RM <?= number_format($sub['expenses'],2) ?></div>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="/daily_closing/manager_submission_view.php?id=<?= (int)$sub['id'] ?>">View details</a>
                  </div>
                  <?php if ($sub['receipts']): ?>
                    <ul class="list-unstyled small mb-0 mt-2">
                      <?php foreach ($sub['receipts'] as $rec): ?>
                        <li class="d-flex align-items-center gap-2">
                          <span class="text-muted">📎</span>
                          <a href="<?= htmlspecialchars($rec['path']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($rec['name']) ?></a>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <p class="text-muted small mb-0 mt-2">No receipts uploaded.</p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-muted small mb-0">No submissions yet today.</p>
            <?php endif; ?>
          </div>
        </div></div>
      </div>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
