<?php
require_once __DIR__ . '/lib.php';

// close this user's presence heartbeat + open attendance sessions before the session dies
$leaving = current_user();
if ($leaving) {
    close_all_attendance((int) $leaving['id']);
    clear_presence((int) $leaving['id']);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
}
session_destroy();
header('Location: index.php');
exit;
