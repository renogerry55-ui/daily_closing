<?php
// /daily_closing/views/manager_submission_create.php
require_once dirname(__DIR__) . '/includes/auth_guard.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/cash_metrics.php';
guard_manager();
$managerId = current_manager_id();

/** fetch manager's outlets for the dropdown */
$outlets = allowed_outlets($pdo);
if (!$outlets) {
  http_response_code(403);
  exit('No outlets assigned to your account. Please contact Admin.');
}

$today = (new DateTime('today'))->format('Y-m-d');

$incomeCats  = ['Deposit','MP','Berhad','Market','Other'];
$expenseCats = ['Expenses','Staff Salary','Staff Advance','Pass to HQ','Other'];
$postedByOutlet = [];
foreach ($outlets as $outlet) {
  $postedByOutlet[(int)$outlet['id']] = outlet_posted_cash_on_hand($pdo, $managerId, (int)$outlet['id']);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Create Submission — Manager</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  .table td, .table th { vertical-align: middle; }
  .is-invalid { border-color:#dc3545!important; }
  .totals-box { position: sticky; top: 12px; }
</style>
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

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title mb-3">New Daily Submission</h5>

          <form id="submissionForm" action="/daily_closing/manager_submission_store.php" method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="manager_id" value="<?= (int)$managerId ?>">

            <div class="row mb-3">
              <div class="col-md-6">
                <label for="outlet_id" class="form-label">Outlet</label>
                <select id="outlet_id" name="outlet_id" class="form-select" required>
                  <option value="" selected disabled>-- Choose outlet --</option>
                  <?php foreach ($outlets as $o): ?>
                    <option value="<?= (int)$o['id'] ?>" data-posted="<?= number_format($postedByOutlet[(int)$o['id']] ?? 0, 2, '.', '') ?>">
                      <?= htmlspecialchars($o['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="date" class="form-label">Date</label>
                <input type="date" id="date" name="date" class="form-control" value="<?= $today ?>" required>
              </div>
            </div>

            <hr class="my-4">

            <h6>Income</h6>
            <table class="table table-sm align-middle" id="incomeTable">
              <thead><tr><th style="width:40%">Category</th><th style="width:35%">Amount (RM)</th><th>Description</th><th></th></tr></thead>
              <tbody></tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addIncome">+ Add income</button>

            <hr class="my-4">

            <h6>Expenses</h6>
            <table class="table table-sm align-middle" id="expenseTable">
              <thead><tr><th style="width:40%">Category</th><th style="width:35%">Amount (RM)</th><th>Description</th><th></th></tr></thead>
              <tbody></tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addExpense">+ Add expense</button>

            <hr class="my-4">

            <h6>Receipts <small class="text-muted">(PDF/JPG/PNG, up to 20MB each)</small></h6>
            <input class="form-control" type="file" name="receipts[]" id="receipts" multiple accept=".pdf,.jpg,.jpeg,.png">

            <div class="row g-3 mt-4">
              <div class="col-md-6">
                <label for="pass_to_office" class="form-label">Pass to Office (RM)</label>
                <input type="number" inputmode="decimal" step="0.01" min="0" class="form-control" id="pass_to_office" name="pass_to_office" required placeholder="0.00">
                <div class="form-text">Enter amount of cash physically sent to HQ today.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Notes (optional)</label>
                <textarea class="form-control" name="notes" rows="3" placeholder="Any remarks..."></textarea>
              </div>
            </div>

            <div class="d-flex gap-2 mt-4">
              <button type="submit" class="btn btn-success">Submit</button>
              <a href="/daily_closing/manager_submissions.php" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card totals-box">
        <div class="card-body">
          <h6 class="mb-3">Summary</h6>
          <div class="d-flex justify-content-between"><span>Income</span><strong>RM <span id="sumIncome">0.00</span></strong></div>
          <div class="d-flex justify-content-between"><span>Expenses</span><strong>RM <span id="sumExpense">0.00</span></strong></div>
          <div class="d-flex justify-content-between"><span>Pass to Office</span><strong>RM <span id="sumPass">0.00</span></strong></div>
          <hr>
          <div class="d-flex justify-content-between"><span>Net (Income − Expenses)</span><strong>RM <span id="sumBalance">0.00</span></strong></div>
          <div class="d-flex justify-content-between"><span>Old Cash on Hand</span><strong>RM <span id="oldCoh">0.00</span></strong></div>
          <div class="d-flex justify-content-between"><span>New Cash on Hand</span><strong>RM <span id="newCoh">0.00</span></strong></div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
const incomeCats  = <?= json_encode($incomeCats) ?>;
const expenseCats = <?= json_encode($expenseCats) ?>;
const postedByOutlet = <?= json_encode($postedByOutlet) ?>;

function money(v){ const n = parseFloat(v); return isNaN(n)?0:n; }
function fmt(n){ return n.toFixed(2); }

function makeRow(kind){
  const cats = kind==='income'?incomeCats:expenseCats;
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select name="${kind}[category][]" class="form-select form-select-sm catSel">
        ${cats.map(c=>`<option value="${c}">${c}</option>`).join('')}
      </select>
    </td>
    <td><input type="number" min="0" step="0.01" class="form-control form-control-sm amt" name="${kind}[amount][]" placeholder="0.00"></td>
    <td><input type="text" class="form-control form-control-sm desc d-none" name="${kind}[description][]" placeholder="Required if Other"></td>
    <td class="text-end"><button type="button" class="btn btn-outline-danger btn-sm rm">Remove</button></td>
  `;
  return tr;
}

function refreshTotals(){
  let inc=0, exp=0;
  document.querySelectorAll('#incomeTable .amt').forEach(i=>inc += money(i.value));
  document.querySelectorAll('#expenseTable .amt').forEach(i=>exp += money(i.value));
  const pass = money(document.getElementById('pass_to_office').value);
  const outlet = document.getElementById('outlet_id').value;
  const oldCoh = outlet && postedByOutlet[outlet] ? parseFloat(postedByOutlet[outlet]) : 0;
  const net = inc - exp;
  const newCoh = oldCoh + net - pass;
  document.getElementById('sumIncome').textContent = fmt(inc);
  document.getElementById('sumExpense').textContent = fmt(exp);
  document.getElementById('sumPass').textContent = fmt(pass);
  document.getElementById('sumBalance').textContent = fmt(net);
  document.getElementById('oldCoh').textContent = fmt(oldCoh);
  document.getElementById('newCoh').textContent = fmt(newCoh);
}

function attachRowBehaviors(scope){
  scope.querySelectorAll('.catSel').forEach(sel=>{
    const tr = sel.closest('tr'); const desc = tr.querySelector('.desc');
    const toggle = ()=> { if (sel.value==='Other'){ desc.classList.remove('d-none'); desc.required = true; } else { desc.classList.add('d-none'); desc.required = false; desc.value=''; } };
    sel.addEventListener('change', toggle); toggle();
  });
  scope.querySelectorAll('.amt').forEach(inp=>{
    inp.addEventListener('input', e=>{
      const v=e.target.value;
      if (v!=='' && (isNaN(parseFloat(v)) || parseFloat(v)<0)) e.target.classList.add('is-invalid'); else e.target.classList.remove('is-invalid');
      refreshTotals();
    });
  });
  scope.querySelectorAll('.rm').forEach(btn=>{
    btn.addEventListener('click', e=>{ e.target.closest('tr').remove(); refreshTotals(); });
  });
}

document.getElementById('addIncome').addEventListener('click', ()=>{
  const tr = makeRow('income'); document.querySelector('#incomeTable tbody').appendChild(tr); attachRowBehaviors(tr);
});
document.getElementById('addExpense').addEventListener('click', ()=>{
  const tr = makeRow('expense'); document.querySelector('#expenseTable tbody').appendChild(tr); attachRowBehaviors(tr);
});

// start with one row each
document.getElementById('addIncome').click();
document.getElementById('addExpense').click();

document.getElementById('outlet_id').addEventListener('change', refreshTotals);
document.getElementById('pass_to_office').addEventListener('input', e=>{
  const value = e.target.value;
  if (value !== '' && !/^\d+(\.\d{0,2})?$/.test(value)) {
    e.target.classList.add('is-invalid');
  } else {
    e.target.classList.remove('is-invalid');
  }
  refreshTotals();
});

// basic submit guard: outlet selected + at least one positive amount
document.getElementById('submissionForm').addEventListener('submit', (e)=>{
  const outletSel = document.getElementById('outlet_id');
  if (!outletSel.value) { e.preventDefault(); alert('Please choose an outlet.'); return; }
  const hasPos = [...document.querySelectorAll('.amt')].some(x => parseFloat(x.value||'0')>0);
  if (!hasPos) { e.preventDefault(); alert('Please enter at least one positive amount in Income or Expenses.'); }
  const passField = document.getElementById('pass_to_office');
  if (passField.value === '' || parseFloat(passField.value) < 0) {
    e.preventDefault();
    alert('Enter a valid Pass to Office amount.');
    return;
  }
});

refreshTotals();
</script>
</body>
</html>
