<?php
/**
 * Quiz answer endpoint — locks in ONE answer per question (single attempt).
 * Guards:
 *  - require_quiz_access(): enrolled + lesson completed (owner = preview only);
 *  - a finished quiz cannot be answered again (result exists -> lockout);
 *  - an already-answered question is never overwritten (re-POST is ignored);
 *  - the answer is persisted immediately, so closing the browser keeps progress.
 * When the last question is answered the quiz is graded and the QuizResult is stored.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
verify_csrf();
$userId = (int) $user['id'];
$courseId = (int) ($_POST['course'] ?? 0);
$materialId = (int) ($_POST['material'] ?? 0);
$questionId = (int) ($_POST['question'] ?? 0);
$choice = (int) ($_POST['option'] ?? -1);
$back = 'quiz.php?c=' . $courseId . '&m=' . $materialId;

$ctx = require_quiz_access($userId, $courseId, $materialId);
$quiz = $ctx['quiz'];
$isOwner = $ctx['isOwner'];

if ($isOwner) {
    set_flash('error', 'Teacher previews are read-only — submissions are not recorded.');
    header('Location: ' . $back);
    exit;
}
if (quiz_result_for((int) $quiz['id'], $userId) !== null) {
    set_flash('error', 'This quiz was already completed — each student gets one attempt.');
    header('Location: ' . $back);
    exit;
}

// the question must belong to this quiz
$valid = false;
foreach ($quiz['questions'] as $q) {
    if ((int) $q['id'] === $questionId) { $valid = $choice >= 0 && $choice < count($q['options']); break; }
}
if (!$valid) {
    set_flash('error', 'Invalid answer submission.');
    header('Location: ' . $back);
    exit;
}

$answers = save_quiz_answer((int) $quiz['id'], $userId, $questionId, $choice);

if (count($answers) >= count($quiz['questions'])) {
    // last question — grade and store the single result
    $result = finalize_quiz((int) $quiz['id'], $userId);
    if ($result) {
        $pctTxt = rtrim(rtrim(number_format((float) $result['percentage'], 2), '0'), '.');
        if ($result['status'] === 'PASSED') {
            set_flash('success', "🧪 Quiz finished — {$result['correct']}/{$result['total']} ({$pctTxt}%) — PASSED. Well done!");
        } else {
            set_flash('error', "🧪 Quiz finished — {$result['correct']}/{$result['total']} ({$pctTxt}%) — FAILED. Pass score was {$quiz['pass_score']}%. Review your answers below.");
        }
    }
}
header('Location: ' . $back);
exit;
