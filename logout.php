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
/* The public front page. Clean, without the .php: this is a Location HEADER, which
   the output filter in lib.php cannot reach (headers are not part of the buffered
   body), and .htaccess deliberately does NOT redirect logout.php — so printing
   home.php here would bounce the browser to /logout and then straight back here,
   a redirect loop on the way out. Emit the extensionless address directly. */
header('Location: ' . lh_url_clean(public_url('home')));
exit;
