<?php
/**
 * Reading-progress endpoint (called by the material reader page while scrolling).
 * Body: csrf, course, material, depth (0-100), seconds (active reading time), min (minimum seconds required)
 * Returns JSON: ok, depth, seconds, complete, done, total, pct (course-level progress),
 * plus folder_progress when this lesson completes a folder's progress update.
 */
require_once __DIR__ . '/lib.php';

function json_out(array $data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'POST required']);
}
verify_csrf();
$user = require_login();

$courseId   = (int) ($_POST['course'] ?? 0);
$materialId = (int) ($_POST['material'] ?? 0);
$userId     = (int) $user['id'];
$depth      = (int) ($_POST['depth'] ?? 0);
$seconds    = (int) ($_POST['seconds'] ?? 0);
$minSeconds = (int) ($_POST['min'] ?? 15);

$ownerId = course_owner_id($courseId);
if ($ownerId === null) {
    json_out(['ok' => false, 'error' => 'Course not found']);
}
/* the main admin passes this gate too (writes land in the admin's own rows) */
if ($ownerId !== $userId && ($user['role'] ?? '') !== 'admin' && !is_enrolled_id($courseId, $userId)) {
    json_out(['ok' => false, 'error' => 'Enroll in this course first.']);
}
if (!course_material_exists($courseId, $materialId)) {
    json_out(['ok' => false, 'error' => 'Lesson not found']);
}

$data = record_read_progress($userId, $materialId, $depth, $seconds, $minSeconds);
$total = course_materials_count($courseId);
$done  = user_progress_count($courseId, $userId);
$pct   = $total > 0 ? (int) round($done * 100 / $total) : 0;
$folderProgress = $data['complete'] ? course_folder_progress_map($courseId, $userId) : [];

json_out(['ok' => true] + $data + ['done' => $done, 'total' => $total, 'pct' => $pct, 'folder_progress' => $folderProgress]);
