<?php
/** Request an enrollment code — an existing student asks the course teacher for a code. */
require_once __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: courses.php'); exit; }
verify_csrf();
$user = require_login();

$courseId = (int) ($_POST['course_id'] ?? 0);
$note     = trim((string) ($_POST['note'] ?? ''));
$back     = $courseId > 0 ? 'course.php?id=' . $courseId : 'courses.php';

if (($user['role'] ?? '') !== 'student') {
    set_flash('error', 'Only student accounts can request codes.');
    header('Location: ' . $back); exit;
}

[$ok, $msg] = code_request_create((int) $user['id'], $courseId, $note);
set_flash($ok ? 'success' : 'error', $msg);
header('Location: ' . $back);
exit;
