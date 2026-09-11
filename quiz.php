<?php
/**
 * Lesson quiz page (student) — opened from the course page or the reader once
 * the lesson is completed. Access is validated by require_quiz_access():
 * - the owning teacher may preview at any time;
 * - a student must be enrolled AND have completed the lesson, or the request is
 *   rejected with 403 before any quiz content is rendered.
 * Grading happens server-side in quiz_submit.php; correct answers are never
 * sent to the browser.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$userId = (int) $user['id'];
$courseId = (int) ($_GET['c'] ?? 0);
$materialId = (int) ($_GET['m'] ?? 0);

$ctx = require_quiz_access($userId, $courseId, $materialId);
$course = $ctx['course'];
$quiz = $ctx['quiz'];
$isOwner = $ctx['isOwner'];
$material = get_material($courseId, $materialId);
$stats = quiz_attempt_stats((int) $quiz['id'], $userId);
$total = count($quiz['questions']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title>Quiz: <?= e((string) ($material['title'] ?? '')) ?> · LearnHub</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif']}}}}</script>
<style>html{scroll-behavior:smooth}</style>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800">
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
  <div class="mx-auto flex h-14 max-w-3xl items-center justify-between gap-3 px-4">
    <a href="course.php?id=<?= (int) $courseId ?>" class="shrink-0 rounded-lg px-2 py-1.5 text-sm font-semibold text-indigo-600 hover:bg-indigo-50">← Back to course</a>
    <div class="min-w-0 flex-1 px-2 text-center">
      <p class="truncate text-sm font-bold text-slate-900">🧪 <?= e((string) $quiz['title']) ?></p>
      <p class="truncate text-xs text-slate-500"><?= e((string) ($material['title'] ?? '')) ?> · <?= e((string) ($course['title'] ?? '')) ?></p>
    </div>
    <span class="shrink-0 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700"><?= $total ?> questions</span>
  </div>
</header>
<main class="mx-auto max-w-3xl px-4 py-8">
<?php foreach (take_flashes() as $f): ?>
  <div class="mb-5 rounded-xl px-4 py-3 text-sm ring-1 <?= ($f['type'] ?? '') === 'error' ? 'bg-rose-50 text-rose-800 ring-rose-100' : 'bg-emerald-50 text-emerald-800 ring-emerald-100' ?>">
    <?= ($f['type'] ?? '') === 'error' ? '⚠️' : '✅' ?> <?= e((string) ($f['msg'] ?? '')) ?>
  </div>
<?php endforeach; ?>
  <?php if ($isOwner): ?>
  <div class="mb-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-100">👩‍🏫 <b>Teacher preview</b> — you see this quiz because you own the course. Students unlock it only after completing the lesson. Preview submissions are not recorded.</div>
  <?php elseif ($stats['attempts'] > 0): ?>
  <div class="mb-5 rounded-xl px-4 py-3 text-sm ring-1 <?= ($stats['last']['passed'] ?? false) ? 'bg-emerald-50 text-emerald-800 ring-emerald-100' : 'bg-amber-50 text-amber-800 ring-amber-100' ?>">
    <b>Last attempt:</b> <?= (int) ($stats['last']['correct'] ?? 0) ?>/<?= (int) ($stats['last']['total'] ?? $total) ?> (<?= (int) ($stats['last']['score'] ?? 0) ?>%) — <?= ($stats['last']['passed'] ?? false) ? 'Passed ✅' : 'Not passed ⏳' ?>
    · <b>Best:</b> <?= (int) $stats['best'] ?>% · <b>Attempts:</b> <?= (int) $stats['attempts'] ?>. You can retake it below.
  </div>
  <?php endif; ?>

  <form method="post" action="quiz_submit.php" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="course" value="<?= (int) $courseId ?>">
    <input type="hidden" name="material" value="<?= (int) $materialId ?>">
    <?php foreach ($quiz['questions'] as $qi => $q): ?>
    <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <legend class="sr-only">Question <?= $qi + 1 ?></legend>
      <p class="font-semibold text-slate-900"><span class="mr-1.5 text-indigo-500">Q<?= $qi + 1 ?>.</span><?= e((string) $q['prompt']) ?></p>
      <div class="mt-3 space-y-2">
        <?php foreach ($q['options'] as $oi => $opt): ?>
        <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50/50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
          <input type="radio" name="a_<?= (int) $q['id'] ?>" value="<?= $oi ?>" required class="mt-0.5 accent-indigo-600">
          <span><?= e((string) $opt) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <?php endforeach; ?>
    <div class="rounded-2xl bg-white p-5 text-center shadow-sm ring-1 ring-slate-200">
      <p class="text-sm text-slate-500">Pass score: <b class="text-slate-700"><?= (int) $quiz['pass_score'] ?>%</b> — that is at least <b class="text-slate-700"><?= (int) ceil($total * (int) $quiz['pass_score'] / 100) ?></b> of <?= $total ?> correct.</p>
      <button class="mt-3 w-full rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 sm:w-auto">Submit answers →</button>
      <p class="mt-2 text-xs text-slate-400">You can retake the quiz; your best and latest scores are kept.</p>
    </div>
  </form>
</main>
</body>
</html>
