<?php
/** Remove the quiz assigned to a lesson. Teacher (owner) only. */
require_once __DIR__ . '/lib.php';
$user = require_teacher();
verify_csrf();

$courseId = (int) ($_POST['course_id'] ?? 0);
$materialId = (int) ($_POST['material_id'] ?? 0);
$back = 'course.php?id=' . $courseId;

$ownerId = course_owner_id($courseId);
if ($ownerId === null) {
    set_flash('error', 'Course not found.');
    header('Location: dashboard.php');
    exit;
}
if ($ownerId !== (int) $user['id']) {
    set_flash('error', 'You can only manage quizzes for your own courses.');
    header('Location: dashboard.php');
    exit;
}

delete_quiz($materialId);
set_flash('success', 'Quiz removed from the lesson.');
header('Location: ' . $back);
exit;
