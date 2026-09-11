<?php
/** Create a new course (teachers only). */
require_once __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
verify_csrf();
$user = require_teacher();

$title = trim((string) ($_POST['title'] ?? ''));
$category = trim((string) ($_POST['category'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));

if ($title === '') {
    set_flash('error', 'Please give your course a title.');
    header('Location: dashboard.php');
    exit;
}

$newCourseId = create_course((int) $user['id'], cut($title, 120), $category !== '' ? cut($category, 60) : 'General', cut($description, 2000));
set_flash('success', 'Course "' . cut($title, 120) . '" created! Now add your first lesson.');
header('Location: course.php?id=' . $newCourseId);
exit;
