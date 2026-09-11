<?php
/** Mark every message in a conversation as read for the viewer. JSON response. */
require_once __DIR__ . '/lib.php';
$user = require_login();
verify_csrf();
$conversationId = (int) ($_POST['conversation'] ?? 0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
mark_conversation_read($conversationId, (int) $user['id']);
echo json_encode(['ok' => true, 'unread_total' => unread_message_total((int) $user['id'])]);
exit;