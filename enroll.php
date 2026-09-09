<?php
/** Enroll / leave a course (students only). MySQL-backed. */
require_once __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: courses.php'); exit; }
verify_csrf();
$user = require_login();

$courseId = (int) ($_POST['course_id'] ?? 0);
$userId   = (int) $user['id'];
$course   = course_row($courseId);
if (!$course) {
    set_flash('error', 'Course not found.');
    header('Location: courses.php'); exit;
}
$back = 'course.php?id=' . $courseId;

if (($user['role'] ?? '') !== 'student') {
    set_flash('error', 'Only student accounts can enroll in courses.');
    header('Location: ' . $back); exit;
}

$enrolledNow = toggle_enroll($courseId, $userId);
set_flash($enrolledNow
    ? 'You are enrolled in "' . $course['title'] . '". Happy learning! 🎉'
    : 'You left "' . $course['title'] . '". Your progress was kept.');
header('Location: ' . $back);
exit;
