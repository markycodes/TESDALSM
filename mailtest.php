<?php
/**
 * Mail test — sends a test e-mail to the logged-in user's own address and
 * returns the outcome plus the last mail.log lines, so mail problems are
 * visible from inside the deployed site instead of invisible.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
verify_csrf();
header('Content-Type: application/json; charset=utf-8');
$ok = send_email(
    (string) $user['email'],
    'LearnHub mail test',
    '<p>Hello ' . e((string) $user['name']) . ',</p><p>If you are reading this, e-mail delivery works from this server.</p>',
    'LearnHub mail test — delivery works.'
);
$tail = [];
$log = DATA_DIR . '/mail.log';
if (is_file($log)) {
    $tail = array_slice(file($log, FILE_IGNORE_NEW_LINES), -4);
}
echo json_encode(['ok' => $ok, 'to' => $user['email'], 'tail' => $tail]);
exit;