<?php
declare(strict_types=1);

// CLI helper to inspect the manager dashboard data pipeline.
// Usage: php scripts/check_dashboard_data.php <manager_id> [YYYY-MM-DD]

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require __DIR__ . '/../includes/db.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/check_dashboard_data.php <manager_id> [YYYY-MM-DD]\n");
    exit(1);
}

$managerId = (int)$argv[1];
if ($managerId <= 0) {
    fwrite(STDERR, "Manager ID must be a positive integer.\n");
    exit(1);
}

$phpToday = (new DateTimeImmutable('today'))->format('Y-m-d');
$today = null;
if (isset($argv[2])) {
    try {
        $today = (new DateTimeImmutable($argv[2]))->format('Y-m-d');
    } catch (Exception $e) {
        fwrite(STDERR, "Invalid date provided. Use format YYYY-MM-DD.\n");
        exit(1);
    }
} else {
    $stmtToday = $pdo->query("SELECT CURDATE() AS today");
    $today = $stmtToday->fetchColumn() ?: $phpToday;

    if ($today !== $phpToday) {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE manager_id = ? AND date = ?");
        $stmtCheck->execute([$managerId, $today]);
        $countDbDay = (int)$stmtCheck->fetchColumn();

        if ($countDbDay === 0) {
            $stmtCheck->execute([$managerId, $phpToday]);
            if ((int)$stmtCheck->fetchColumn() > 0) {
                $today = $phpToday;
            }
        }
    }
}

printf("Checking dashboard data for manager #%d on %s\n\n", $managerId, $today);

// --- Outlets assigned to the manager ---
$stmtOutlets = $pdo->prepare("
    SELECT o.id, o.name
    FROM user_outlets uo
    JOIN outlets o ON o.id = uo.outlet_id
    WHERE uo.user_id = ?
    ORDER BY o.name
");
$stmtOutlets->execute([$managerId]);
$outlets = $stmtOutlets->fetchAll(PDO::FETCH_ASSOC);

if (!$outlets) {
    echo "- No outlets are assigned to this manager.\n";
} else {
    printf("- %d outlets assigned.\n", count($outlets));
}

// --- Submissions and receipts for the requested day ---
$stmtSubs = $pdo->prepare("
    SELECT
        s.id,
        s.outlet_id,
        s.total_income,
        s.total_expenses,
        s.balance,
        COUNT(r.id) AS receipt_count
    FROM submissions s
    LEFT JOIN receipts r ON r.submission_id = s.id
    WHERE s.manager_id = ? AND s.date = ?
    GROUP BY s.id
    ORDER BY s.outlet_id, s.id
");
$stmtSubs->execute([$managerId, $today]);
$subs = $stmtSubs->fetchAll(PDO::FETCH_ASSOC);

$receiptsTotal = 0;
$subsByOutlet = [];
foreach ($subs as $row) {
    $oid = (int)$row['outlet_id'];
    $subsByOutlet[$oid][] = $row;
    $receiptsTotal += (int)$row['receipt_count'];
}

if ($subs) {
    printf("- %d submissions found for %s (total receipts: %d).\n", count($subs), $today, $receiptsTotal);
} else {
    printf("- No submissions found for %s.\n", $today);
}

// --- Detail per outlet ---
if ($outlets) {
    echo "\nOutlet breakdown:\n";
    foreach ($outlets as $outlet) {
        $oid = (int)$outlet['id'];
        printf("• [%d] %s\n", $oid, $outlet['name']);
        $rows = $subsByOutlet[$oid] ?? [];
        if (!$rows) {
            echo "  · No submissions recorded for this date.\n";
            continue;
        }
        foreach ($rows as $row) {
            printf(
                "  · Submission #%d — Sales RM %.2f / Expenses RM %.2f / Balance RM %.2f / Receipts: %d\n",
                $row['id'],
                (float)$row['total_income'],
                (float)$row['total_expenses'],
                (float)$row['balance'],
                (int)$row['receipt_count']
            );
        }
    }
}

// --- Submissions not linked to the manager's outlets ---
$stmtOrphans = $pdo->prepare("
    SELECT s.id, s.outlet_id
    FROM submissions s
    WHERE s.manager_id = ? AND s.date = ?
      AND NOT EXISTS (
        SELECT 1 FROM user_outlets uo
        WHERE uo.user_id = s.manager_id AND uo.outlet_id = s.outlet_id
      )
    ORDER BY s.id
");
$stmtOrphans->execute([$managerId, $today]);
$orphans = $stmtOrphans->fetchAll(PDO::FETCH_ASSOC);

if ($orphans) {
    echo "\nWarning: submissions exist for outlets not mapped to this manager:\n";
    foreach ($orphans as $row) {
        printf("- Submission #%d (outlet #%d)\n", $row['id'], $row['outlet_id']);
    }
}

// --- Receipts sanity check ---
$stmtBrokenReceipts = $pdo->prepare("
    SELECT COUNT(*)
    FROM receipts r
    JOIN submissions s ON s.id = r.submission_id
    WHERE s.manager_id = ? AND s.date = ?
      AND (r.file_path IS NULL OR r.file_path = '')
");
$stmtBrokenReceipts->execute([$managerId, $today]);
$brokenCount = (int)$stmtBrokenReceipts->fetchColumn();

if ($brokenCount > 0) {
    printf("\nWarning: %d receipt rows have empty file paths.\n", $brokenCount);
}

echo "\nCheck complete.\n";
