<?php
/** Presence heartbeat — called every ~60s by app.js to keep the current user "online". */
require_once __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }
verify_csrf();
$user = require_login();
touch_presence((int) $user['id']);
maybe_send_digest((int) $user['id']); // daily catch-up e-mail when there is something unread
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
exit;