<?php
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');

function folder_move_json(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    folder_move_json(405, ['ok' => false, 'error' => 'POST required.']);
}

$user = require_teacher();
verify_csrf();

$courseId = (int) ($_POST['course'] ?? 0);
$materialId = (int) ($_POST['material'] ?? 0);
$folderId = (int) ($_POST['folder'] ?? 0);
$ownerId = course_owner_id($courseId);

if ($ownerId === null || $ownerId !== (int) $user['id']) {
    folder_move_json(403, ['ok' => false, 'error' => 'You can only move lessons in your own courses.']);
}
if ($materialId <= 0 || !course_material_exists($courseId, $materialId)) {
    folder_move_json(404, ['ok' => false, 'error' => 'Lesson not found in this course.']);
}
$folder = course_folder_row($courseId, $folderId);
if (!$folder) {
    folder_move_json(404, ['ok' => false, 'error' => 'Destination folder not found in this course.']);
}

try {
    $st = db()->prepare('UPDATE materials SET folder_id = ? WHERE course_id = ? AND id = ?');
    $st->execute([$folderId, $courseId, $materialId]);
} catch (PDOException $e) {
    lms_error_log('lesson folder drag-and-drop failed: ' . $e->getMessage());
    folder_move_json(500, ['ok' => false, 'error' => 'The lesson could not be moved. Please try again.']);
}

folder_move_json(200, ['ok' => true, 'folder' => (string) $folder['name']]);
