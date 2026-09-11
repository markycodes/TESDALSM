<?php
/** Send a private message (student <-> teacher). JSON response for the chat JS. */
require_once __DIR__ . '/lib.php';
$user = require_login();
verify_csrf();
$fromId = (int) $user['id'];
$role = (string) $user['role'];

$body = (string) ($_POST['body'] ?? '');
$withId = (int) ($_POST['with'] ?? 0);      // the other party's user id
$conversationId = (int) ($_POST['conversation'] ?? 0);

$other = $withId > 0 ? find_user_by_id($withId) : null;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!$other || ($other['role'] ?? '') === $role) {
    echo json_encode(['ok' => false, 'error' => 'Invalid recipient.']);
    exit;
}
if (trim($body) === '') {
    echo json_encode(['ok' => false, 'error' => 'Message is empty.']);
    exit;
}

// resolve/create the conversation; only a real student<->teacher pair is allowed
$studentId = $role === 'student' ? $fromId : (int) $other['id'];
$teacherId = $role === 'teacher' ? $fromId : (int) $other['id'];
if ($conversationId > 0 && user_owns_conversation($conversationId, $fromId)) {
    $cid = $conversationId;
} else {
    $cid = get_or_create_conversation($studentId, $teacherId);
}
$toId = $role === 'student' ? $teacherId : $studentId;
$msgId = send_private_message($fromId, $toId, $cid, $body);
echo json_encode(['ok' => true, 'id' => $msgId, 'conversation' => $cid, 'unread_total' => unread_message_total($fromId)]);
exit;