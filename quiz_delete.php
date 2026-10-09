<?php
/** Remove the quiz assigned to a lesson. Teacher (owner) only. */
require_once __DIR__ . '/lib.php';
$user = require_teacher();
verify_csrf();

$courseId = (int) ($_POST['course_id'] ?? 0);
$folderId = (int) ($_POST['folder_id'] ?? 0);
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

if (($folderId > 0) === ($materialId > 0)) {
    set_flash('error', 'Choose exactly one folder for this quiz.');
    header('Location: ' . lh_enc_url($back));
    exit;
}
if ($folderId > 0) {
    if (!course_folder_row($courseId, $folderId)) {
        set_flash('error', 'Folder not found.');
        header('Location: ' . lh_enc_url($back));
        exit;
    }
    delete_folder_quiz($folderId);
    set_flash('success', 'Folder quiz removed.');
} else {
    if (!course_material_exists($courseId, $materialId)) {
        set_flash('error', 'Lesson not found.');
        header('Location: ' . lh_enc_url($back));
        exit;
    }
    delete_quiz($materialId);
    set_flash('success', 'Quiz removed from the lesson.');
}
header('Location: ' . lh_enc_url($back));
exit;
