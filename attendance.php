<?php
/**
 * Attendance endpoint.
 * - POST leave: close the current open session for a user+course (called by pagehide beacon).
 */
require_once __DIR__ . '/lib.php';

function att_json(array $data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    att_json(['ok' => false, 'error' => 'POST required']);
}
verify_csrf();
$user = require_login();

$courseId = (int) ($_POST['course'] ?? 0);
if ($courseId <= 0) att_json(['ok' => false, 'error' => 'Missing course']);

close_attendance((int) $user['id'], $courseId);
att_json(['ok' => true, 'left' => time()]);