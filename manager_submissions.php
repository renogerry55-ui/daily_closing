<?php
// /daily_closing/manager_submissions.php
require __DIR__ . '/includes/auth_guard.php';
require __DIR__ . '/includes/db.php';

guard_manager();
$managerId = current_manager_id();

/** outlets for filter dropdown */
$outlets = allowed_outlets($pdo); // [{id,name},...]

// --- filters ---
$status    = $_GET['status']    ?? 'all';
$outletF   = isset($_GET['outlet_id']) && $_GET['outlet_id'] !== '' ? (int)$_GET['outlet_id'] : null;
$dateFrom  = $_GET['date_from']  ?? '';
$dateTo    = $_GET['date_to']    ?? '';

// expose selected outlet (null when not filtered) for the view
$outletId = $outletF ?? null;

$allowedStatus = ['all','pending','approved','rejected','recorded'];
if (!in_array($status, $allowedStatus, true)) $status = 'all';

// if outlet filter is set, ensure it belongs to manager
if ($outletF) {
    $ok = false;
    foreach ($outlets as $o) if ((int)$o['id'] === $outletF) { $ok = true; break; }
    if (!$ok) { http_response_code(403); exit('Forbidden: outlet not assigned to you.'); }
}

// --- pagination ---
$pp   = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$off  = ($page-1)*$pp;

// --- build WHERE ---
$where = ['s.manager_id = ?'];
$args  = [$managerId];

if ($status !== 'all') { $where[] = 's.status = ?';  $args[] = $status; }
if ($outletF)          { $where[] = 's.outlet_id = ?'; $args[] = $outletF; }

$WHERE = 'WHERE ' . implode(' AND ', $where);

// --- count ---
$sqlCount = "SELECT COUNT(*) FROM submissions s $WHERE";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($args);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $pp));

// --- list ---
$sql = "
  SELECT s.id, s.date, s.status, s.total_income, s.total_expenses, s.balance, s.pass_to_office,
         (s.total_income - s.total_expenses - s.pass_to_office) AS net_cash,
         o.name AS outlet
  FROM submissions s
  JOIN outlets o ON o.id = s.outlet_id
  $WHERE
  ORDER BY s.date DESC, s.id DESC
  LIMIT $pp OFFSET $off
";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

// shared query parameters for pagination links
$queryParams = ['status' => $status];
if ($outletId !== null) $queryParams['outlet_id'] = $outletId;
if ($dateFrom !== '')  $queryParams['date_from']  = $dateFrom;
if ($dateTo !== '')    $queryParams['date_to']    = $dateTo;

// hand to view
require __DIR__ . '/views/manager_submissions_list.php';
