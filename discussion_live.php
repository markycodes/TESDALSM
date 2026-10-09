<?php
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function discussion_live_json(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$user = require_login();
$userId = (int) $user['id'];
$courseId = (int) ($_GET['c'] ?? $_POST['c'] ?? 0);
$materialId = (int) ($_GET['m'] ?? $_POST['m'] ?? 0);
$course = $courseId > 0 ? course_row($courseId) : null;
if (!$course || !course_material_exists($courseId, $materialId)) {
    discussion_live_json(['ok' => false, 'error' => 'Lesson not found.'], 404);
}
if (!can_view_lessons($course, $user)
    || (!assignment_can_manage($user, $courseId) && !is_enrolled_id($courseId, $userId))) {
    discussion_live_json(['ok' => false, 'error' => 'You cannot access this lesson discussion.'], 403);
}

$action = (string) ($_GET['v'] ?? '');
if ($action === 'beat') {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        discussion_live_json(['ok' => false, 'error' => 'POST required.'], 405);
    }
    verify_csrf();
    $typing = (string) ($_POST['typing'] ?? '');
    if ($typing === '1') {
        $now = time();
        db()->prepare('INSERT INTO lesson_typing (material_id, user_id, typed_at) VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE typed_at = VALUES(typed_at)')
            ->execute([$materialId, $userId, $now]);
        db()->prepare('DELETE FROM lesson_typing WHERE typed_at < ?')->execute([$now - 30]);
    } elseif ($typing === '0') {
        db()->prepare('DELETE FROM lesson_typing WHERE material_id = ? AND user_id = ?')
            ->execute([$materialId, $userId]);
    } else {
        discussion_live_json(['ok' => false, 'error' => 'Invalid typing state.'], 400);
    }
    discussion_live_json(['ok' => true]);
}

if ($action !== 'state' || (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET')) {
    discussion_live_json(['ok' => false, 'error' => 'Invalid request.'], 400);
}

$st = db()->prepare('SELECT u.id, u.name
                     FROM lesson_typing t JOIN users u ON u.id = t.user_id
                     WHERE t.material_id = ? AND t.user_id <> ? AND t.typed_at >= ?
                     ORDER BY t.typed_at DESC');
$st->execute([$materialId, $userId, time() - 6]);
discussion_live_json(['ok' => true, 'users' => $st->fetchAll()]);
