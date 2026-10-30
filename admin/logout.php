<?php
declare(strict_types=1);

require __DIR__ . '/lib/bootstrap.php';
ab_boot();
if (empty($_COOKIE[session_cookie_name()])) {
    header('Location: login.php');
    exit;
}
require_post_security(false);
audit('logout', ['email' => (string) ($_SESSION['uid'] ?? '')]);
logout_session();
header('Location: login.php');
exit;
