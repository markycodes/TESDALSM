<?php
/** Delete a single lesson (material/video) from a course (owner only). */
require_once __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: courses.php'); exit; }
verify_csrf();
$user = require_login();

$courseId   = (int) ($_POST['course_id'] ?? 0);
$materialId = (int) ($_POST['material_id'] ?? 0);

$course = course_row($courseId);
if (!$course) {
    set_flash('error', 'Course not found.');
    header('Location: courses.php'); exit;
}
if (($user['role'] ?? '') !== 'teacher' || (int) $course['teacher_id'] !== (int) $user['id']) {
    set_flash('error', 'Only the course teacher can delete lessons.');
    header('Location: course.php?id=' . $courseId); exit;
}

$material = delete_material_row($courseId, $materialId); // cascades its progress rows
if (!$material) {
    set_flash('error', 'Lesson not found.');
    header('Location: course.php?id=' . $courseId); exit;
}

if (in_array($material['type'], ['file', 'video'], true) && !empty($material['filename'])) {
    delete_uploaded_file((string) $material['filename']);
}
set_flash('success', 'Lesson "' . $material['title'] . '" deleted.');
header('Location: course.php?id=' . $courseId);
exit;
