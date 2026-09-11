<?php
/** Delete a course and its uploaded files (owner only). */
require_once __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
verify_csrf();
$user = require_teacher();

$courseId = (int) ($_POST['course_id'] ?? 0);
$course = course_row($courseId);
if (!$course) {
    set_flash('error', 'Course not found.');
    header('Location: dashboard.php');
    exit;
}
if ((int) $course['teacher_id'] !== (int) $user['id']) {
    set_flash('error', 'You can only delete your own courses.');
    header('Location: dashboard.php');
    exit;
}

$files = delete_course_row($courseId); // cascades materials, enrollments and progress rows
foreach ($files as $stored) {
    delete_uploaded_file((string) $stored);
}
set_flash('success', 'Course "' . $course['title'] . '" deleted.');
header('Location: dashboard.php');
exit;
