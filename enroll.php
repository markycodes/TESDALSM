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

// Existing student redeeming an invitation code for THIS course.
$code = strtoupper(trim((string) ($_POST['code'] ?? '')));
if ($code !== '') {
    $row = enroll_code_lookup($code);
    if (!$row) {
        set_flash('error', 'That code is invalid — check it and try again.');
    } elseif ((int) $row['course_id'] !== $courseId) {
        set_flash('error', 'That code belongs to a different course ("' . $row['course_title'] . '"). Open that course and unlock it there.');
    } elseif (!empty($row['used_by'])) {
        set_flash('error', 'That code has already been used — ask your teacher for a fresh one.');
    } else {
        $enrolledCourseId = redeem_enroll_code($code, $userId);
        if ($enrolledCourseId === $courseId) {
            set_flash('success', 'Code redeemed — welcome to "' . $course['title'] . '"! Happy learning! 🎉');
            header('Location: course.php?id=' . $courseId); exit;
        }
        set_flash('error', 'That code was just claimed by someone else — ask your teacher for a fresh one.');
    }
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
