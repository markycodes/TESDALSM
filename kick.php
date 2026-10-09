<?php
/**
 * Remove one student from one course (the owning teacher, or the admin).
 * Deletes the ENROLMENT row only — the account and every record the student
 * made in the course (lessons, hand-ins, grades, visits) stay on file, shown
 * to the teacher flagged as "Removed" until the student re-enrols. Mirrors
 * course_delete.php: POST + CSRF + the course's own teacher gate.
 */
require_once __DIR__ . '/lib.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: courses.php');
    exit;
}
verify_csrf();
$user = require_login();

$courseId = (int) ($_POST['course_id'] ?? 0);
$studentIds = array_values(array_unique(array_filter(
    array_map('intval', (array) ($_POST['student_ids'] ?? [])),
    static fn($id) => $id > 0
)));
if (!$studentIds && isset($_POST['student_id'])) {
    $studentIds[] = (int) $_POST['student_id'];
}
$back = (string) ($_POST['back'] ?? '');
$back = in_array($back, ['course', 'gradebook'], true) ? $back : 'enrollments';

if (!$studentIds) {
    set_flash('error', 'Select at least one student to remove.');
} else {
    $removed = 0;
    $errors = [];
    foreach ($studentIds as $studentId) {
        $res = kick_student_from_course($user, $courseId, $studentId);
        if ($res['ok']) {
            $removed++;
        } else {
            $errors[] = $res['msg'];
        }
    }
    if ($errors) {
        $message = $removed . ' student(s) removed.';
        $message .= ' Could not remove ' . count($errors) . ' selection(s): ' . implode(' ', array_unique($errors));
        set_flash('error', $message);
    } else {
        set_flash('success', $removed . ' student(s) removed from the course. Their records stay on file.');
    }
}
$dest = $back === 'gradebook'
    ? 'gradebook.php?id=' . $courseId
    : ($back === 'course'
        ? 'course.php?id=' . $courseId
        : 'enrollments.php?course=' . $courseId);
header('Location: ' . lh_enc_url($dest));
exit;