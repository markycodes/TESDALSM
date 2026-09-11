<?php
/** Save (create or fully replace) the quiz assigned to a lesson. Teacher only. */
require_once __DIR__ . '/lib.php';
$user = require_teacher();
verify_csrf();

$courseId = (int) ($_POST['course_id'] ?? 0);
$materialId = (int) ($_POST['material_id'] ?? 0);
$back = 'course.php?id=' . $courseId;

$ownerId = course_owner_id($courseId);
if ($ownerId === null) {
    set_flash('error', 'Course not found.');
    header('Location: dashboard.php');
    exit;
}
if ($ownerId !== (int) $user['id']) {
    set_flash('error', 'You can only manage quizzes for your own courses.');
    header('Location: dashboard.php');
    exit;
}
if (!course_material_exists($courseId, $materialId)) {
    set_flash('error', 'Lesson not found.');
    header('Location: ' . $back);
    exit;
}

$title = (string) ($_POST['title'] ?? '');
$passScore = (int) ($_POST['pass_score'] ?? 60);

/* parallel arrays: one entry per question row (order matches the DOM) */
$prompts = (array) ($_POST['prompt'] ?? []);
$o1 = (array) ($_POST['o1'] ?? []);
$o2 = (array) ($_POST['o2'] ?? []);
$o3 = (array) ($_POST['o3'] ?? []);
$o4 = (array) ($_POST['o4'] ?? []);
$correct = (array) ($_POST['correct'] ?? []);

$questions = [];
foreach ($prompts as $i => $p) {
    $questions[] = [
        'prompt' => (string) $p,
        'options' => [$o1[$i] ?? '', $o2[$i] ?? '', $o3[$i] ?? '', $o4[$i] ?? ''],
        'correct' => (int) ($correct[$i] ?? 0),
    ];
}

try {
    save_quiz($materialId, $title, $passScore, $questions);
    set_flash('success', '🧪 Quiz saved — ' . count($questions) . ' question(s) assigned to this lesson. It unlocks for students once they complete the lesson.');
} catch (RuntimeException $e) {
    set_flash('error', $e->getMessage());
}
header('Location: ' . $back);
exit;
