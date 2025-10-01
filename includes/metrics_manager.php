<?php
/**
 * Manager dashboard metrics helper functions.
 */

declare(strict_types=1);

/**
 * Fetch active outlets assigned to the manager.
 */
function manager_assigned_outlets(PDO $pdo, int $managerId): array
{
    $stmt = $pdo->prepare(
        "SELECT o.id, o.name
           FROM user_outlets uo
           JOIN outlets o ON o.id = uo.outlet_id
          WHERE uo.user_id = :uid AND o.status = 'active'
          ORDER BY o.name"
    );
    $stmt->execute(['uid' => $managerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Check if a table exists in the current database.
 */
function manager_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    $exists = (bool)$stmt->fetchColumn();
    $cache[$table] = $exists;
    return $exists;
}

/**
 * Build a placeholder string for an IN clause and merge params.
 */
function manager_build_in_clause(array $ids): array
{
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return [$placeholders, array_map('intval', $ids)];
}

/**
 * Main aggregator for dashboard metrics.
 */
function manager_dashboard_metrics(PDO $pdo, int $managerId, string $range = 'month'): array
{
    $range = $range === 'today' ? 'today' : 'month';
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $start = $range === 'today' ? $today : (new DateTimeImmutable($today))->format('Y-m-01');

    $outlets = manager_assigned_outlets($pdo, $managerId);
    $metrics = [
        'range' => $range,
        'start' => $start,
        'end' => $today,
        'today' => $today,
        'outlets' => [],
        'totals' => [
            'salesApproved' => 0.0,
            'expensesApproved' => 0.0,
            'remittedApproved' => 0.0,
            'coh' => 0.0,
            'pendingSubmissionsCount' => 0,
            'pendingSubmissionsAmount' => 0.0,
            'pendingRemittancesCount' => 0,
            'pendingRemittancesAmount' => 0.0,
        ],
        'outletCount' => count($outlets),
    ];

    if (!$outlets) {
        return $metrics;
    }

    $outletIds = array_map(static fn(array $row) => (int)$row['id'], $outlets);

    foreach ($outlets as $row) {
        $oid = (int)$row['id'];
        $metrics['outlets'][$oid] = [
            'id' => $oid,
            'name' => $row['name'],
            'salesApproved' => 0.0,
            'expensesApproved' => 0.0,
            'remittedApproved' => 0.0,
            'pendingSubmissionsCount' => 0,
            'pendingSubmissionsAmount' => 0.0,
            'pendingRemittancesCount' => 0,
            'pendingRemittancesAmount' => 0.0,
            'latestSubmissions' => [],
            'todayReceipts' => [],
            'cashOnHand' => 0.0,
        ];
    }

    [$inClause, $idParams] = manager_build_in_clause($outletIds);

    // Aggregate submissions (approved totals + pending badges)
    $submissionSql = "
        SELECT
            s.outlet_id,
            SUM(CASE WHEN s.status = 'approved' THEN s.total_income ELSE 0 END) AS approved_income,
            SUM(CASE WHEN s.status = 'approved' THEN s.total_expenses ELSE 0 END) AS approved_expenses,
            SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN s.status = 'pending' THEN (s.total_income - s.total_expenses) ELSE 0 END) AS pending_net
        FROM submissions s
        WHERE s.manager_id = ?
          AND s.outlet_id IN ($inClause)
          AND s.date BETWEEN ? AND ?
        GROUP BY s.outlet_id
    ";
    $submissionParams = array_merge([$managerId], $idParams, [$start, $today]);
    $stmt = $pdo->prepare($submissionSql);
    $stmt->execute($submissionParams);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $oid = (int)$row['outlet_id'];
        if (!isset($metrics['outlets'][$oid])) {
            continue;
        }
        $sales = (float)$row['approved_income'];
        $expenses = (float)$row['approved_expenses'];
        $pendingCount = (int)$row['pending_count'];
        $pendingNet = (float)$row['pending_net'];

        $metrics['outlets'][$oid]['salesApproved'] = $sales;
        $metrics['outlets'][$oid]['expensesApproved'] = $expenses;
        $metrics['outlets'][$oid]['pendingSubmissionsCount'] = $pendingCount;
        $metrics['outlets'][$oid]['pendingSubmissionsAmount'] = $pendingNet;

        $metrics['totals']['salesApproved'] += $sales;
        $metrics['totals']['expensesApproved'] += $expenses;
        $metrics['totals']['pendingSubmissionsCount'] += $pendingCount;
        $metrics['totals']['pendingSubmissionsAmount'] += $pendingNet;
    }

    // Aggregate remittances (approved totals + pending badges)
    if (manager_table_exists($pdo, 'hq_remittances')) {
        $remitSql = "
            SELECT
                outlet_id,
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) AS approved_amount,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) AS pending_amount,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count
            FROM hq_remittances
            WHERE outlet_id IN ($inClause)
              AND received_at BETWEEN ? AND ?
            GROUP BY outlet_id
        ";
        $remitParams = array_merge($idParams, [$start, $today]);
        $stmt = $pdo->prepare($remitSql);
        $stmt->execute($remitParams);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $oid = (int)$row['outlet_id'];
            if (!isset($metrics['outlets'][$oid])) {
                continue;
            }
            $approved = (float)$row['approved_amount'];
            $pendingAmount = (float)$row['pending_amount'];
            $pendingCount = (int)$row['pending_count'];

            $metrics['outlets'][$oid]['remittedApproved'] = $approved;
            $metrics['outlets'][$oid]['pendingRemittancesAmount'] = $pendingAmount;
            $metrics['outlets'][$oid]['pendingRemittancesCount'] = $pendingCount;

            $metrics['totals']['remittedApproved'] += $approved;
            $metrics['totals']['pendingRemittancesCount'] += $pendingCount;
            $metrics['totals']['pendingRemittancesAmount'] += $pendingAmount;
        }
    }

    // Today's receipts for display
    $receiptsSql = "
        SELECT
            s.outlet_id,
            s.id AS submission_id,
            s.date,
            s.total_income,
            s.total_expenses,
            s.balance,
            r.file_path,
            r.original_name
        FROM submissions s
        LEFT JOIN receipts r ON r.submission_id = s.id
        WHERE s.manager_id = ?
          AND s.outlet_id IN ($inClause)
          AND s.date = ?
        ORDER BY s.outlet_id, s.id, r.original_name
    ";
    $receiptParams = array_merge([$managerId], $idParams, [$today]);
    $stmt = $pdo->prepare($receiptsSql);
    $stmt->execute($receiptParams);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $oid = (int)$row['outlet_id'];
        if (!isset($metrics['outlets'][$oid])) {
            continue;
        }
        $sid = (int)$row['submission_id'];
        if (!isset($metrics['outlets'][$oid]['todayReceipts'][$sid])) {
            $metrics['outlets'][$oid]['todayReceipts'][$sid] = [
                'id' => $sid,
                'date' => $row['date'],
                'income' => (float)$row['total_income'],
                'expenses' => (float)$row['total_expenses'],
                'balance' => (float)$row['balance'],
                'receipts' => [],
            ];
        }
        if (!empty($row['file_path'])) {
            $path = (string)$row['file_path'];
            if (strpos($path, '/daily_closing/') !== 0) {
                $path = '/daily_closing' . $path;
            }
            $metrics['outlets'][$oid]['todayReceipts'][$sid]['receipts'][] = [
                'path' => $path,
                'name' => $row['original_name'],
            ];
        }
    }

    // Latest submissions (limit 5 per outlet)
    $latestStmt = $pdo->prepare(
        "SELECT id, outlet_id, date, status, total_income, total_expenses, balance
           FROM submissions
          WHERE manager_id = ? AND outlet_id = ?
          ORDER BY date DESC, id DESC
          LIMIT 5"
    );
    foreach ($outletIds as $oid) {
        $latestStmt->execute([$managerId, $oid]);
        $metrics['outlets'][$oid]['latestSubmissions'] = $latestStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Compute cash-on-hand per outlet and totals.
    foreach ($metrics['outlets'] as $oid => $data) {
        $coh = $data['salesApproved'] - $data['expensesApproved'] - $data['remittedApproved'];
        if ($coh < 0) {
            $coh = 0.0;
        }
        $metrics['outlets'][$oid]['cashOnHand'] = $coh;
        $metrics['totals']['coh'] += $coh;
    }

    return $metrics;
}
