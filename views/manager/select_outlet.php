<?php
require __DIR__ . '/../../includes/auth.php';
require_role(['manager']);

$allowed = $_SESSION['allowed_outlets'] ?? [];
if (!$allowed) {
    echo "<p>No outlets assigned to your account. Contact admin.</p>";
    echo '<p><a href="/daily_closing/logout.php">Logout</a></p>';
    exit;
}

// Fetch outlet names for display (optional if you stored names in session)
require __DIR__ . '/../../includes/db.php';
if ($allowed) {
    $in  = implode(',', array_fill(0, count($allowed), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM outlets WHERE id IN ($in) ORDER BY name");
    $stmt->execute($allowed);
    $outlets = $stmt->fetchAll();
}
?>
<!doctype html><meta charset="utf-8"><title>Select Outlet</title>
<style>
  body{font-family:system-ui,Arial,sans-serif} .card{max-width:520px;margin:60px auto;padding:24px;border:1px solid #eee;border-radius:12px}
  button,select{padding:10px;border-radius:8px}
</style>
<div class="card">
  <h2>Select Outlet</h2>
  <form method="post" action="/daily_closing/views/manager/set_current_outlet.php">
    <label>Outlet
      <select name="outlet_id" required>
        <?php foreach ($outlets as $o): ?>
          <option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" style="margin-top:12px">Continue</button>
  </form>
  <p style="margin-top:12px"><a href="/daily_closing/logout.php">Logout</a></p>
</div>
