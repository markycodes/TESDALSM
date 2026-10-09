<?php
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function reaction_json(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$user = require_login();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reaction_json(['ok' => false, 'error' => 'POST required.'], 405);
}
verify_csrf();

$type = (string) ($_POST['type'] ?? '');
$targetId = (int) ($_POST['target'] ?? 0);
$reaction = (string) ($_POST['reaction'] ?? '');
if (!content_reaction_storage($type) || $targetId <= 0) {
    reaction_json(['ok' => false, 'error' => 'Invalid reaction target.'], 400);
}

if ($type === 'lesson_post') {
    $st = db()->prepare('SELECT m.course_id
                         FROM lesson_posts p JOIN materials m ON m.id = p.material_id
                         WHERE p.id = ? LIMIT 1');
    $st->execute([$targetId]);
    $courseId = (int) $st->fetchColumn();
} else {
    $st = db()->prepare('SELECT course_id FROM announcements WHERE id = ? LIMIT 1');
    $st->execute([$targetId]);
    $courseId = (int) $st->fetchColumn();
}

$course = $courseId > 0 ? course_row($courseId) : null;
if (!$course) reaction_json(['ok' => false, 'error' => 'Post not found.'], 404);
$userId = (int) $user['id'];
$canManage = assignment_can_manage($user, $courseId);
$enrolled = is_enrolled_id($courseId, $userId);
if (($type === 'lesson_post' && (!can_view_lessons($course, $user) || (!$canManage && !$enrolled)))
    || ($type === 'announcement' && !$canManage && !$enrolled)) {
    reaction_json(['ok' => false, 'error' => 'You cannot react to this post.'], 403);
}

$result = content_reaction_toggle($type, $targetId, $userId, $reaction);
if (!$result['ok']) reaction_json($result, 400);
reaction_json($result);
