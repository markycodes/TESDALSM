<?php
/**
 * Finalize a student's quiz when the active quiz page detects that it is being
 * left. Unanswered questions remain blank and are graded as incorrect.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
verify_csrf();

$userId = (int) $user['id'];
$courseId = (int) ($_POST['course'] ?? 0);
$materialId = (int) ($_POST['material'] ?? 0);
$folderId = (int) ($_POST['folder'] ?? 0);
$ctx = require_quiz_access($userId, $courseId, $materialId, $folderId);
$quiz = $ctx['quiz'];

if (!$ctx['isOwner'] && quiz_result_for((int) $quiz['id'], $userId) === null) {
    db()->prepare('INSERT INTO quiz_progress (quiz_id, user_id, answers, updated_at) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)')
        ->execute([(int) $quiz['id'], $userId, '{}', time()]);
    finalize_quiz((int) $quiz['id'], $userId);
}

http_response_code(204);
exit;
