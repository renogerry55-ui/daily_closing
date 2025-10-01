<?php
require __DIR__ . '/../../includes/auth_guard.php';
require __DIR__ . '/../../includes/db.php';
guard_manager();

$uid = current_manager_id();

// today totals across all outlets
$stmt = $pdo->prepare("
  SELECT
    COALESCE(SUM(total_income),0)  AS inc,
    COALESCE(SUM(total_expenses),0) AS exp,
    COALESCE(SUM(balance),0)        AS bal
  FROM submissions
  WHERE manager_id=? AND date=CURDATE()
");
$stmt->execute([$uid]);
$tot = $stmt->fetch() ?: ['inc'=>0,'exp'=>0,'bal'=>0];

// per-outlet cards (today)
$stmt2 = $pdo->prepare("
  SELECT o.name, COALESCE(SUM(s.total_income),0) inc, COALESCE(SUM(s.total_expenses),0) exp, COALESCE(SUM(s.balance),0) bal
  FROM user_outlets uo
  JOIN outlets o ON o.id = uo.outlet_id
  LEFT JOIN submissions s ON s.outlet_id=o.id AND s.manager_id=uo.user_id AND s.date=CURDATE()
  WHERE uo.user_id=?
  GROUP BY o.id
  ORDER BY o.name
");
$stmt2->execute([$uid]);
$perOutlet = $stmt2->fetchAll();
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
        </div></div>
      </div>
    <?php endforeach; ?>
  </div>
</main>
</body>
</html>
