<?php
/** Presence heartbeat — called every ~60s by app.js to keep the current user "online".
 *  The mirror image is v=offline: the browser's going-offline beacon (fired on the
 *  `offline` event, before the link is fully gone), which backdates last_seen so the
 *  user counts as offline NOW instead of waiting out PRESENCE_TIMEOUT — their PHP
 *  session stays alive, only presence flips. */
require_once __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }
verify_csrf();
$user = require_login();
header('Content-Type: application/json');
if (($_POST['v'] ?? '') === 'offline') {
    offline_user((int) $user['id']);
    echo json_encode(['ok' => true, 'online' => false]);
    exit;
}
touch_presence((int) $user['id']);
maybe_send_digest((int) $user['id']); // daily catch-up e-mail when there is something unread
echo json_encode(['ok' => true]);
exit;