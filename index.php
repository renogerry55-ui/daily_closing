<?php
// /daily_closing/index.php
require __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . role_redirect($_SESSION['role']));
    exit;
} else {
    header('Location: /daily_closing/login.php');
    exit;
}
