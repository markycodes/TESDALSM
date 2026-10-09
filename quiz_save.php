<?php
/** Save (create or fully replace) the quiz assigned to a lesson. Teacher only. */
require_once __DIR__ . '/lib.php';
$user = require_teacher();
verify_csrf();

$courseId = (int) ($_POST['course_id'] ?? 0);
$folderId = (int) ($_POST['folder_id'] ?? 0);
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
if (($folderId > 0) === ($materialId > 0)) {
    set_flash('error', 'Choose exactly one folder for this quiz.');
    header('Location: ' . lh_enc_url($back));
    exit;
}
if ($folderId > 0 && !course_folder_row($courseId, $folderId)) {
    set_flash('error', 'Folder not found.');
    header('Location: ' . lh_enc_url($back));
    exit;
}
if ($materialId > 0 && !course_material_exists($courseId, $materialId)) {
    set_flash('error', 'Lesson not found.');
    header('Location: ' . lh_enc_url($back));
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
        'option_explanations' => [
            $_POST['option_explanation_1'][$i] ?? '',
            $_POST['option_explanation_2'][$i] ?? '',
            $_POST['option_explanation_3'][$i] ?? '',
            $_POST['option_explanation_4'][$i] ?? '',
        ],
    ];
}

try {
    if ($folderId > 0) {
        save_folder_quiz($folderId, $title, $passScore, $questions);
    } else {
        save_quiz($materialId, $title, $passScore, $questions);
    }
    notify_course_students($courseId, 'quiz',
        '🧪 New quiz: "' . cut($title, 70) . '"',
        $folderId > 0 ? 'A new quiz was added to a course folder.' : 'A new quiz was assigned to this lesson.',
        'course.php?id=' . $courseId);
    set_flash('success', $folderId > 0
        ? '🧪 Folder quiz saved — students can open it when the whole folder reaches 100%.'
        : '🧪 Lesson quiz saved — it unlocks for students once they complete the lesson.');
} catch (PDOException $e) {
    /* never print a database message to the browser: it names tables, columns
       and sometimes the whole failing statement. Log it, apologise to the user. */
    lms_error_log('quiz save failed: ' . $e->getMessage());
    set_flash('error', 'The quiz could not be saved because of a database problem. Please try again.');
} catch (RuntimeException $e) {
    set_flash('error', $e->getMessage());
}
header('Location: ' . lh_enc_url($back));
exit;
