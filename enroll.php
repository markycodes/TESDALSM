<?php
/** Enroll / leave a course. Enrollment is invite-only (via teacher codes), so students may only LEAVE. */
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
    set_flash('error', 'Only student accounts can manage enrollment.');
    header('Location: ' . $back); exit;
}

// Students can only LEAVE a course they are already in.
if (!is_enrolled_id($courseId, $userId)) {
    set_flash('error', 'Enrollment is by invitation only — register with the invitation code you got from the teacher to join this course.');
    header('Location: ' . $back); exit;
}

db()->prepare('DELETE FROM enrollments WHERE course_id = ? AND user_id = ?')->execute([$courseId, $userId]);
set_flash('success', 'You left "' . $course['title'] . '". Your progress was kept.');
header('Location: ' . $back);
exit;
