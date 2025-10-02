<?php
// /daily_closing/manager_submission_store.php
require __DIR__ . '/includes/auth_guard.php';
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/upload_helpers.php';
require __DIR__ . '/includes/cash_metrics.php';

guard_manager();
$managerId = current_manager_id();

// Duplicate rule flag
const BLOCK_DUPLICATES_PER_DATE = true; // set false to allow multiple per outlet+date

$errors = [];

// ----- Inputs -----
$outletId = isset($_POST['outlet_id']) ? (int)$_POST['outlet_id'] : 0;
$date     = $_POST['date'] ?? '';
$notes    = trim($_POST['notes'] ?? '');

if ($outletId <= 0) { $errors[] = 'Please choose a valid outlet.'; }
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) { $errors[] = 'Invalid date.'; }

// Ensure outlet belongs to this manager
if ($outletId > 0) {
    assert_outlet_belongs_to_manager($pdo, $outletId);
}

$income = $_POST['income']  ?? ['category'=>[], 'amount'=>[], 'description'=>[]];
$expense= $_POST['expense'] ?? ['category'=>[], 'amount'=>[], 'description'=>[]];
$passInput = trim($_POST['pass_to_office'] ?? '');
$passToOffice = null;

if ($passInput === '') {
    $errors[] = 'Pass to Office amount is required.';
} else {
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $passInput)) {
        $errors[] = 'Pass to Office amount must be a number with up to 2 decimals.';
    } else {
        $passToOffice = (float)$passInput;
        if ($passToOffice < 0) {
            $errors[] = 'Pass to Office amount must be zero or more.';
        }
    }
}

$allowedIncome  = ['Deposit','MP','Berhad','Market','Other'];
$allowedExpense = ['Expenses','Staff Salary','Staff Advance','Pass to HQ','Other'];

function rows_from($arr): array {
    $out=[];
    $n = max(count($arr['category']??[]), count($arr['amount']??[]), count($arr['description']??[]));
    for ($i=0;$i<$n;$i++){
        $out[] = [
            'category'    => trim($arr['category'][$i] ?? ''),
            'amount'      => (float)($arr['amount'][$i] ?? 0),
            'description' => trim($arr['description'][$i] ?? ''),
        ];
    }
    return $out;
}

$incomeRows  = rows_from($income);
$expenseRows = rows_from($expense);

// ----- Validate items -----
$hasPositive = false;

// income rows
foreach ($incomeRows as $r) {
    if ($r['category']==='') continue; // blank row
    if (!in_array($r['category'],$allowedIncome,true)) { $errors[]='Invalid income category.'; break; }
    if ($r['amount'] < 0.01) { $errors[]='Income amount must be at least 0.01'; break; }
    if ($r['category']==='Other' && $r['description']==='') { $errors[]='Income description required for Other.'; break; }
    $hasPositive = true;
}
// expense rows
foreach ($expenseRows as $r) {
    if ($r['category']==='') continue;
    if (!in_array($r['category'],$allowedExpense,true)) { $errors[]='Invalid expense category.'; break; }
    if ($r['amount'] < 0.01) { $errors[]='Expense amount must be at least 0.01'; break; }
    if ($r['category']==='Other' && $r['description']==='') { $errors[]='Expense description required for Other.'; break; }
    $hasPositive = true;
}
if (!$hasPositive) { $errors[] = 'Add at least one Income or Expense with amount ≥ 0.01'; }

// Duplicate per outlet+date?
if (BLOCK_DUPLICATES_PER_DATE && $outletId > 0 && $date) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE outlet_id=? AND date=?");
    $stmt->execute([$outletId, $date]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'A submission for this outlet and date already exists.';
    }
}

// Validate receipts quickly (MIME/size) — saving happens after we insert the header
$dt = new DateTime($date ?: 'today');
if (!empty($_FILES['receipts']) && is_array($_FILES['receipts']['name'])) {
    for ($i=0, $n=count($_FILES['receipts']['name']); $i<$n; $i++) {
        if (($_FILES['receipts']['error'][$i] ?? null) === UPLOAD_ERR_NO_FILE) { continue; }
        $tmp = [
            'name'     => $_FILES['receipts']['name'][$i],
            'type'     => $_FILES['receipts']['type'][$i],
            'tmp_name' => $_FILES['receipts']['tmp_name'][$i],
            'error'    => $_FILES['receipts']['error'][$i],
            'size'     => $_FILES['receipts']['size'][$i],
        ];
        validate_file($tmp, $errors); // collect errors if any
    }
}

if ($errors) {
    $_SESSION['flash_error'] = implode("\n", $errors);
    header('Location: /daily_closing/views/manager_submission_create.php');
    exit;
}

// ----- Compute totals -----
$totalIncome = 0.0; foreach ($incomeRows as $r)  { if ($r['category']!=='') $totalIncome  += $r['amount']; }
$totalExpenses = 0.0; foreach ($expenseRows as $r) { if ($r['category']!=='') $totalExpenses+= $r['amount']; }
$balance = $totalIncome - $totalExpenses;

if ($passToOffice === null) {
    $passToOffice = 0.0;
}

$postedCoh = $outletId > 0 ? outlet_posted_cash_on_hand($pdo, $managerId, $outletId) : 0.0;
$maxPass = max(0.0, $postedCoh + $balance);
if ($passToOffice - $maxPass > 0.0001) {
    $errors[] = 'Pass to Office cannot exceed current cash on hand.';
}

if ($errors) {
    $_SESSION['flash_error'] = implode("\n", $errors);
    header('Location: /daily_closing/views/manager_submission_create.php');
    exit;
}

// ----- Store (transaction) -----
try {
    $pdo->beginTransaction();

    // Header
    $stmt = $pdo->prepare("
        INSERT INTO submissions (manager_id, outlet_id, date, status, total_income, total_expenses, balance, pass_to_office, notes)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$managerId, $outletId, $date, $totalIncome, $totalExpenses, $balance, $passToOffice, $notes]);
    $submissionId = (int)$pdo->lastInsertId();

    // Items
    $stmtItem = $pdo->prepare("INSERT INTO submission_items (submission_id, type, category, amount, description) VALUES (?, ?, ?, ?, ?)");
    foreach ($incomeRows as $r) {
        if ($r['category']==='') continue;
        $stmtItem->execute([$submissionId, 'income', $r['category'], $r['amount'], ($r['category']==='Other'?$r['description']:null)]);
    }
    foreach ($expenseRows as $r) {
        if ($r['category']==='') continue;
        $stmtItem->execute([$submissionId, 'expense', $r['category'], $r['amount'], ($r['category']==='Other'?$r['description']:null)]);
    }

    // Receipts (save to disk, then insert)
    $saved = !empty($_FILES['receipts']) ? save_receipts($_FILES['receipts'], $dt, $errors) : [];
    if ($errors) { throw new RuntimeException(implode('; ', $errors)); }

    if ($saved) {
        $stmtRec = $pdo->prepare("INSERT INTO receipts (submission_id, file_path, original_name, mime, size_bytes) VALUES (?, ?, ?, ?, ?)");
        foreach ($saved as $rec) {
            $stmtRec->execute([$submissionId, $rec['file_path'], $rec['original_name'], $rec['mime'], $rec['size_bytes']]);
        }
    }

    $pdo->commit();
    $_SESSION['flash_ok'] = 'Submission created successfully.';
    header('Location: /daily_closing/manager_submissions.php?status=pending');
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Save failed: ' . $e->getMessage();
    header('Location: /daily_closing/views/manager_submission_create.php');
    exit;
}
