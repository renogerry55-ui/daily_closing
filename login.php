<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, username, password_hash, role, outlet_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user); // sets user_id, role

            // Preload allowed outlets into session for convenience (not required, but handy)
            $stmt2 = $pdo->prepare("
                SELECT o.id
                FROM user_outlets uo
                JOIN outlets o ON o.id = uo.outlet_id
                WHERE uo.user_id = ?
            ");
            $stmt2->execute([$user['id']]);
            $_SESSION['allowed_outlets'] = array_map(fn($r)=>(int)$r['id'], $stmt2->fetchAll());

            header('Location: ' . role_redirect($user['role']));
            exit;
        }
        $error = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><title>Login — Daily Closing</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width:420px">
  <div class="card mt-5">
    <div class="card-body">
      <h4 class="mb-3">Sign in</h4>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" action="login.php" autocomplete="off">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input class="form-control" name="username" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input class="form-control" type="password" name="password" required>
        </div>
        <button class="btn btn-primary w-100">Sign in</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
