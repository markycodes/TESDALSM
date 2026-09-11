<?php
/** Mark notifications read (id set = one, otherwise all). JSON response. */
require_once __DIR__ . '/lib.php';
$user = require_login();
verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    mark_one_notification_read($id, (int) $user['id']);
} else {
    mark_all_notifications_read((int) $user['id']);
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['ok' => true, 'unread' => unread_notification_count((int) $user['id'])]);
exit;