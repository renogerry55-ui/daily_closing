<?php
// /daily_closing/hq_batch_store.php
require __DIR__ . '/includes/auth_guard.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/upload_helpers.php';

guard_manager();
$managerId = current_manager_id();

$errors = [];

$date  = $_POST['date'] ?? '';
$notes = trim($_POST['notes'] ?? '');
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) { $errors[]='Invalid date.'; }

$includeOutlets = $_POST['include_outlets'] ?? []; // array of outlet_ids (included)
$subsByOutlet   = $_POST['submissions_by_outlet'] ?? []; // outlet_id => [submission_id, ...]

// Flatten selected submission IDs and validate
$submissionIds = [];
foreach ($includeOutlets as $oid) {
    $oid = (int)$oid;
    if (!isset($subsByOutlet[$oid]) || !is_array($subsByOutlet[$oid])) continue;
    foreach ($subsByOutlet[$oid] as $sid) {
        $submissionIds[] = (int)$sid;
    }
}
$submissionIds = array_values(array_unique(array_filter($submissionIds)));

if (!$submissionIds) { $errors[] = 'No outlets selected (nothing to submit).'; }

if ($errors) {
    $_SESSION['flash_error'] = implode("\n", $errors);
    header('Location: /daily_closing/views/report_hq.php?date='.urlencode($date));
    exit;
}

// Validate that all submissions belong to this manager, match the date, and are not already in HQ
$placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
$params = $submissionIds;
array_unshift($params, $managerId, $date);

$sql = "
  SELECT s.id, s.total_income, s.total_expenses, s.balance, s.pass_to_office,
         (SELECT COUNT(*) FROM receipts r WHERE r.submission_id = s.id) AS receipts_count
  FROM submissions s
  WHERE s.manager_id = ? AND s.date = ?
    AND s.id IN ($placeholders)
    AND s.submitted_to_hq_at IS NULL
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (count($rows) !== count($submissionIds)) {
    $_SESSION['flash_error'] = 'Some selected submissions are invalid or already submitted to HQ.';
    header('Location: /daily_closing/views/report_hq.php?date='.urlencode($date));
    exit;
}

// Totals + validation
$overallInc=0.0; $overallExp=0.0; $overallBal=0.0; $overallPass=0.0;
foreach ($rows as $r) {
    if ((int)$r['receipts_count'] === 0) {
        $_SESSION['flash_error'] = 'Cannot submit: some submissions are missing receipts.';
        header('Location: /daily_closing/views/report_hq.php?date='.urlencode($date));
        exit;
    }
    $overallInc += (float)$r['total_income'];
    $overallExp += (float)$r['total_expenses'];
    $overallBal += (float)$r['balance'];
    $overallPass += (float)$r['pass_to_office'];
}

try {
    $pdo->beginTransaction();

    // Create HQ batch
    $stmt = $pdo->prepare("
      INSERT INTO hq_batches (manager_id, report_date, status, overall_total_income, overall_total_expenses, overall_pass_to_office, overall_balance, notes)
      VALUES (?, ?, 'submitted', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$managerId, $date, $overallInc, $overallExp, $overallPass, $overallBal, $notes]);
    $batchId = (int)$pdo->lastInsertId();

    // Link submissions
    $stmtLink = $pdo->prepare("INSERT INTO hq_batch_submissions (hq_batch_id, submission_id) VALUES (?, ?)");
    $stmtMark = $pdo->prepare("UPDATE submissions SET submitted_to_hq_at = NOW() WHERE id=?");
    foreach ($submissionIds as $sid) {
        $stmtLink->execute([$batchId, $sid]);
        $stmtMark->execute([$sid]);
    }

    // Save HQ attachments (bank-in slip etc.)
    $dt = new DateTime($date ?: 'today');
    $saved = [];
    if (!empty($_FILES['hq_files']) && is_array($_FILES['hq_files']['name'])) {
        $filtered = [
            'name' => [],
            'type' => [],
            'tmp_name' => [],
            'error' => [],
            'size' => [],
        ];
        for ($i = 0, $n = count($_FILES['hq_files']['name']); $i < $n; $i++) {
            if (($_FILES['hq_files']['error'][$i] ?? null) === UPLOAD_ERR_NO_FILE) { continue; }
            $filtered['name'][]     = $_FILES['hq_files']['name'][$i];
            $filtered['type'][]     = $_FILES['hq_files']['type'][$i];
            $filtered['tmp_name'][] = $_FILES['hq_files']['tmp_name'][$i];
            $filtered['error'][]    = $_FILES['hq_files']['error'][$i];
            $filtered['size'][]     = $_FILES['hq_files']['size'][$i];
        }
        if (!empty($filtered['name'])) {
            $saved = save_hq_files($filtered, $dt, $errors);
        }
    }
    if ($errors) { throw new RuntimeException(implode('; ', $errors)); }

    if ($saved) {
        // Ensure table exists; if you haven't created it yet, run the DDL I gave earlier.
        $stmtFile = $pdo->prepare("INSERT INTO hq_batch_files (hq_batch_id, file_path, original_name, mime, size_bytes) VALUES (?, ?, ?, ?, ?)");
        foreach ($saved as $f) {
            $stmtFile->execute([$batchId, $f['file_path'], $f['original_name'], $f['mime'], $f['size_bytes']]);
        }
    }

    $pdo->commit();
    $_SESSION['flash_ok'] = 'Submitted to HQ successfully.';
    header('Location: /daily_closing/views/report_hq.php?date='.urlencode($date));
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'HQ submit failed: '.$e->getMessage();
    header('Location: /daily_closing/views/report_hq.php?date='.urlencode($date));
    exit;
}
