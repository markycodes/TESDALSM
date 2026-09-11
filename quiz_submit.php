<?php
/**
 * Quiz grading endpoint (POST from the lesson quiz form).
 * The SAME gate as quiz.php applies server-side: a student can only submit
 * answers for a lesson they have completed — an early/forced POST is rejected
 * with 403 before grading. Correct answers live only on the server.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
verify_csrf();
$userId = (int) $user['id'];
$courseId = (int) ($_POST['course'] ?? 0);
$materialId = (int) ($_POST['material'] ?? 0);
$backQuiz = 'quiz.php?c=' . $courseId . '&m=' . $materialId;

$ctx = require_quiz_access($userId, $courseId, $materialId);
$quiz = $ctx['quiz'];
$isOwner = $ctx['isOwner'];

$total = count($quiz['questions']);
$correct = 0;
foreach ($quiz['questions'] as $q) {
    if ((int) ($_POST['a_' . $q['id']] ?? -1) === (int) $q['correct']) $correct++;
}
$pct = $total > 0 ? (int) round($correct * 100 / $total) : 0;

if ($isOwner) {
    set_flash('success', "👩‍🏫 Preview graded: {$correct}/{$total} ({$pct}%). Teacher previews are not recorded as attempts.");
} else {
    $res = record_quiz_attempt((int) $quiz['id'], $userId, $correct, $total, (int) $quiz['pass_score']);
    if ($res['passed']) {
        set_flash('success', "🧪 Quiz passed — {$correct}/{$total} ({$pct}%). Well done!");
    } else {
        set_flash('error', "Not passed yet — {$correct}/{$total} ({$pct}%). Review the lesson and retake the quiz (pass score: {$quiz['pass_score']}%).");
    }
}
header('Location: ' . $backQuiz);
exit;
