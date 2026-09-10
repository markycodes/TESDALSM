<?php
/**
 * Video watch-progress endpoint (called by the course page while a video plays).
 * Body: csrf, course, material, watched (seconds), duration (seconds), position (seconds)
 * Returns JSON: ok, percent, complete, done, total, pct (course-level progress).
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
$watched    = (int) ($_POST['watched'] ?? 0);
$duration   = (int) ($_POST['duration'] ?? 0);
$position   = (int) ($_POST['position'] ?? 0);
$ended      = ($_POST['ended'] ?? '') === '1' || ($_POST['ended'] ?? '') === 'true';

$ownerId = course_owner_id($courseId);
if ($ownerId === null) {
    json_out(['ok' => false, 'error' => 'Course not found']);
}
if ($ownerId !== $userId && !is_enrolled_id($courseId, $userId)) {
    json_out(['ok' => false, 'error' => 'Enroll in this course first.']);
}
if (!course_material_exists($courseId, $materialId)) {
    json_out(['ok' => false, 'error' => 'Lesson not found']);
}

$data = record_video_progress($userId, $materialId, $watched, $duration, $position, $ended);
$total = course_materials_count($courseId);
$done  = user_progress_count($courseId, $userId);
$pct   = $total > 0 ? (int) round($done * 100 / $total) : 0;

json_out(['ok' => true] + $data + ['done' => $done, 'total' => $total, 'pct' => $pct]);
