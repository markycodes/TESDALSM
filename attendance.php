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

/* Close a session only in a course this account genuinely belongs to — enrolled
   as a student, or the teacher of it. close_attendance() is already keyed on the
   caller's own user id, so a guessed course could never touch another person's
   record; the point of the check is that a wrong request is answered with 'no'
   instead of a quiet 'ok'. */
$course = course_row($courseId);
$isMine = $course && ((int) ($course['teacher_id'] ?? 0) === (int) $user['id']
    || is_enrolled_id($courseId, (int) $user['id']));
if (!$isMine) att_json(['ok' => false, 'error' => 'Not your course']);

close_attendance((int) $user['id'], $courseId);
att_json(['ok' => true, 'left' => time()]);