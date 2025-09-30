<?php
// /htdocs/daily_closing/logout.php
require __DIR__ . '/includes/auth.php';
logout_user();
header('Location: /daily_closing/login.php?logged_out=1');
exit;
