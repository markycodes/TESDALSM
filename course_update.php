<?php
/** Update a course's public details (owning teacher only). */
require_once __DIR__ . '/lib.php';
$user = require_teacher();
verify_csrf();

$courseId = (int) ($_POST['course_id'] ?? 0);
$ownerId = course_owner_id($courseId);
if ($ownerId === null) {
    set_flash('error', 'Course not found.');
    header('Location: dashboard.php');
    exit;
}
if ($ownerId !== (int) $user['id']) {
    set_flash('error', 'You can only edit your own courses.');
    header('Location: dashboard.php');
    exit;
}

$title = cut(trim((string) ($_POST['title'] ?? '')), 120);
if ($title === '') {
    set_flash('error', 'Please give your course a title.');
    header('Location: ' . lh_enc_url('course.php?id=' . $courseId));
    exit;
}
$category = cut(trim((string) ($_POST['category'] ?? '')), 60);
$description = cut(trim((string) ($_POST['description'] ?? '')), 2000);
try {
    db()->prepare('UPDATE courses SET title = ?, category = ?, description = ? WHERE id = ? AND teacher_id = ?')
        ->execute([$title, $category !== '' ? $category : 'General', $description, $courseId, (int) $user['id']]);
} catch (PDOException $e) {
    lms_error_log('course update failed: ' . $e->getMessage());
    set_flash('error', 'The course details could not be saved because of a database problem. Please try again.');
    header('Location: ' . lh_enc_url('course.php?id=' . $courseId));
    exit;
}
set_flash('success', 'Course details updated.');
header('Location: ' . lh_enc_url('course.php?id=' . $courseId));
exit;
