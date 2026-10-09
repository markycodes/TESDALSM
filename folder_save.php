<?php
/** Create a top-level course folder or rename one of its folders. */
require_once __DIR__ . '/lib.php';
$user = require_teacher();
verify_csrf();

$courseId = (int) ($_POST['course_id'] ?? 0);
$ownerId = course_owner_id($courseId);
if ($ownerId === null || $ownerId !== (int) $user['id']) {
    set_flash('error', 'You can only manage folders in your own courses.');
    header('Location: dashboard.php');
    exit;
}

$name = (string) ($_POST['name'] ?? '');
$folderId = (int) ($_POST['folder_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
try {
    if ($action === 'delete') {
        if ($folderId <= 0) throw new RuntimeException('Choose a folder to delete.');
        $files = delete_course_folder($courseId, $folderId);
        if ($files === null) throw new RuntimeException('Folder not found.');
        $failedFiles = 0;
        foreach ($files as $filename) {
            if (!delete_uploaded_file($filename)) $failedFiles++;
        }
        if ($failedFiles > 0) {
            lms_error_log('folder deletion left ' . $failedFiles . ' uploaded file(s) on disk for course ' . $courseId . '.');
            set_flash('error', 'Folder content was deleted, but some uploaded files could not be removed from storage. Please check the server logs.');
        } else {
            set_flash('success', 'Folder, lessons, quizzes, and related progress deleted.');
        }
    } elseif ($folderId > 0) {
        if (!rename_course_folder($courseId, $folderId, $name)) {
            throw new RuntimeException('Folder not found.');
        }
        set_flash('success', 'Folder renamed.');
    } else {
        create_course_folder($courseId, $name);
        set_flash('success', 'Folder created.');
    }
} catch (RuntimeException $e) {
    set_flash('error', $e->getMessage());
} catch (PDOException $e) {
    lms_error_log('course folder save failed: ' . $e->getMessage());
    set_flash('error', 'The folder could not be saved because of a database problem. Please try again.');
}
header('Location: ' . lh_enc_url('course.php?id=' . $courseId));
exit;
