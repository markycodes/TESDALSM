<?php
/**
 * Lesson quiz page — single attempt per student.
 * States:
 *  - Teacher (owner): read-only preview, correct answers highlighted.
 *  - Student already finished: result banner + per-question review, NO retake.
 *  - Student in progress: resumes at the first unanswered question (progress is
 *    saved per answer server-side, so closing the browser loses nothing).
 *    One question at a time; no Previous button; answers lock immediately.
 *  - Student fresh: starts at question 1.
 * Grading happens server-side in quiz_answer.php; correct answers are never
 * sent to the browser before submission.
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/skeleton.php';   /* the loading pane (this page has no app shell) */
$user = require_login();
$userId = (int) $user['id'];
$courseId = (int) ($_GET['c'] ?? 0);
$materialId = (int) ($_GET['m'] ?? 0);

$ctx = require_quiz_access($userId, $courseId, $materialId);
$course = $ctx['course'];
$quiz = $ctx['quiz'];
$isOwner = $ctx['isOwner'];
$material = get_material($courseId, $materialId);

/* Attendance: taking the lesson quiz IS studying. Arriving from the course page
   fired its leave-beacon, so continue the same row — and since this page has no
   app shell, refresh presence + send the leave-beacon itself (script at the
   bottom), exactly like the reader (read.php). */
touch_presence($userId);
$trackVisit = ($user['role'] ?? '') === 'student' && !$isOwner;
if ($trackVisit) {
    resume_attendance($userId, $courseId);
}

$result = $isOwner ? null : quiz_result_for((int) $quiz['id'], $userId);
// Review source: once finished, read the persisted answers from quiz_results
// (progress rows are deleted on completion); while in progress, read quiz_progress.
$answered = $result ? $result['answers']
          : ($isOwner ? [] : (quiz_progress_for((int) $quiz['id'], $userId) ?? []));
$total = count($quiz['questions']);
$answeredCount = count($answered);
// first unanswered question (in sort order)
$current = null;
$currentIdx = 0;
foreach ($quiz['questions'] as $qi => $q) {
    if (!array_key_exists((string) $q['id'], $answered)) { $current = $q; $currentIdx = $qi; break; }
}
if (!$isOwner && !$result && !$current && $answeredCount > 0) {
    // all questions answered but no result yet (edge) — finalize now
    $result = finalize_quiz((int) $quiz['id'], $userId);
}
$pct = $result ? (float) $result['percentage'] : 0.0;
$passed = $result && $result['status'] === 'PASSED';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<?php if ($trackVisit): ?><meta name="attendance-course" content="<?= (int) $courseId ?>"><?php endif; ?>
<title>Quiz: <?= e((string) $quiz['title']) ?> · LearnHub</title>
<link rel="stylesheet" href="assets/tailwind.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>html{scroll-behavior:smooth}</style>
<?php lh_skeleton_css(); ?>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800">
<?php lh_skeleton_body(); ?>
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

  <!-- Quiz title, prominent -->
  <div class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <p class="text-xs font-bold uppercase tracking-widest text-indigo-500">Lesson quiz</p>
    <h1 class="mt-1 text-2xl font-extrabold text-slate-900">🧪 <?= e((string) $quiz['title']) ?></h1>
    <p class="mt-1 text-sm text-slate-500">📖 <?= e((string) ($material['title'] ?? '')) ?> · <?= e((string) ($course['title'] ?? '')) ?></p>
    <p class="mt-2 text-sm text-slate-600">Pass score: <b><?= (int) $quiz['pass_score'] ?>%</b> · <b><?= $total ?></b> question<?= $total === 1 ? '' : 's' ?> · You get <b>one attempt</b> — answers lock as you go and cannot be changed.</p>
  </div>

<?php if ($isOwner): ?>
  <div class="mb-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-100">👩‍🏫 <b>Teacher preview</b> — correct answers are highlighted below. Students unlock this quiz only after completing the lesson and get a single attempt.</div>
  <div class="space-y-4">
  <?php foreach ($quiz['questions'] as $qi => $q): ?>
    <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <p class="font-semibold text-slate-900"><span class="mr-1.5 text-indigo-500">Q<?= $qi + 1 ?>.</span><?= e((string) $q['prompt']) ?></p>
      <div class="mt-3 space-y-2">
        <?php foreach ($q['options'] as $oi => $opt): ?>
        <div class="flex items-start gap-2.5 rounded-xl border px-3.5 py-2.5 text-sm <?= $oi === (int) $q['correct'] ? 'border-emerald-300 bg-emerald-50 font-semibold text-emerald-800' : 'border-slate-200 text-slate-600' ?>">
          <span class="mt-0.5 text-xs font-bold"><?= $oi === (int) $q['correct'] ? '✅' : ($oi + 1) . '.' ?></span>
          <span><?= e((string) $opt) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>
  </div>
<?php elseif ($result): ?>
  <!-- Completed: result banner + review, no retake -->
  <div class="mb-6 rounded-2xl p-6 shadow-sm ring-1 <?= $passed ? 'bg-emerald-50 ring-emerald-200' : 'bg-rose-50 ring-rose-200' ?>">
    <div class="flex flex-wrap items-center gap-3">
      <span class="rounded-full px-3 py-1 text-xs font-bold <?= $passed ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' ?>"><?= $passed ? 'PASSED' : 'FAILED' ?></span>
      <span class="text-2xl font-extrabold text-slate-900"><?= (int) $result['correct'] ?>/<?= (int) $result['total'] ?></span>
      <span class="text-lg font-bold text-slate-700"><?= rtrim(rtrim(number_format($pct, 2), '0'), '.') ?>%</span>
    </div>
    <p class="mt-2 text-sm text-slate-600">📅 Completed <?= e(date('M j, Y \a\t H:i', (int) $result['created_at'])) ?> · Pass score was <?= (int) $quiz['pass_score'] ?>%.</p>
    <p class="mt-1 text-sm <?= $passed ? 'text-emerald-700' : 'text-rose-700' ?>"><?= $passed ? 'Well done — this quiz is complete and cannot be retaken.' : 'This quiz is complete and cannot be retaken. Review your answers below.' ?></p>
    <a href="my_records.php" class="mt-3 inline-block rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">📋 View in My Records</a>
  </div>
  <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-400">Your answers</h2>
  <div class="space-y-4">
  <?php foreach ($quiz['questions'] as $qi => $q): ?>
    <?php $chosen = $answered[$q['id']] ?? null; $ok = $chosen !== null && (int) $chosen === (int) $q['correct']; ?>
    <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 <?= $ok ? 'ring-emerald-200' : 'ring-rose-200' ?>">
      <div class="flex items-center justify-between gap-2">
        <p class="font-semibold text-slate-900"><span class="mr-1.5 text-indigo-500">Q<?= $qi + 1 ?>.</span><?= e((string) $q['prompt']) ?></p>
        <span class="shrink-0 text-sm font-bold <?= $ok ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $ok ? '✓ Correct' : '✗ Incorrect' ?></span>
      </div>
      <div class="mt-3 space-y-2">
        <?php foreach ($q['options'] as $oi => $opt): ?>
        <?php
          $isChosen = $chosen !== null && (int) $chosen === $oi;
          $isRight = $oi === (int) $q['correct'];
          $cls = $isRight ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
              : ($isChosen ? 'border-rose-300 bg-rose-50 text-rose-700' : 'border-slate-200 text-slate-500');
        ?>
        <div class="flex items-start gap-2.5 rounded-xl border px-3.5 py-2.5 text-sm <?= $cls ?>">
          <span class="mt-0.5 text-xs font-bold"><?= $isChosen ? '➜' : ($isRight ? '✅' : ($oi + 1) . '.') ?></span>
          <span><?= e((string) $opt) ?></span>
          <?php if ($isChosen): ?><span class="ml-auto shrink-0 text-xs font-semibold <?= $ok ? 'text-emerald-600' : 'text-rose-500' ?>"><?= $ok ? 'your answer ✓' : 'your answer' ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>
  </div>
<?php elseif ($current): ?>
  <!-- In progress / fresh: one question at a time, no backtracking -->
  <div class="mb-4 flex items-center gap-3">
    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-200">
      <div class="h-full rounded-full bg-indigo-600 transition-all" style="width: <?= $total > 0 ? (int) round($answeredCount * 100 / $total) : 0 ?>%"></div>
    </div>
    <span class="shrink-0 text-xs font-bold text-slate-500">Question <?= $currentIdx + 1 ?> of <?= $total ?></span>
  </div>
  <form method="post" action="quiz_answer.php" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <?= csrf_field() ?>
    <input type="hidden" name="course" value="<?= (int) $courseId ?>">
    <input type="hidden" name="material" value="<?= (int) $materialId ?>">
    <input type="hidden" name="question" value="<?= (int) $current['id'] ?>">
    <p class="text-lg font-bold text-slate-900"><span class="mr-2 text-indigo-500">Q<?= $currentIdx + 1 ?>.</span><?= e((string) $current['prompt']) ?></p>
    <div class="mt-4 space-y-2.5">
      <?php foreach ($current['options'] as $oi => $opt): ?>
      <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 transition hover:border-indigo-300 hover:bg-indigo-50/50 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
        <input type="radio" name="option" value="<?= $oi ?>" required class="mt-0.5 accent-indigo-600">
        <span><?= e((string) $opt) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <button class="mt-5 w-full rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">🔒 Lock answer<?= $answeredCount + 1 < $total ? ' & continue' : ' & finish' ?> →</button>
    <p class="mt-2 text-center text-xs text-slate-400">Your answer is saved and locked immediately — it cannot be changed or revisited.</p>
  </form>

  <?php if ($answeredCount > 0): ?>
  <h2 class="mt-8 mb-3 text-sm font-bold uppercase tracking-wide text-slate-400">Locked answers (<?= $answeredCount ?>)</h2>
  <div class="space-y-3">
    <?php foreach ($quiz['questions'] as $qi => $q): ?>
      <?php if (!array_key_exists((string) $q['id'], $answered)) continue; $chosen = (int) $answered[$q['id']]; ?>
      <div class="rounded-2xl bg-white p-4 opacity-80 shadow-sm ring-1 ring-slate-200">
        <div class="flex items-center justify-between gap-2">
          <p class="text-sm font-semibold text-slate-700"><span class="mr-1.5 text-slate-400">Q<?= $qi + 1 ?>.</span><?= e((string) $q['prompt']) ?></p>
          <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-500">🔒 Locked</span>
        </div>
        <p class="mt-1.5 text-sm text-slate-500">Your answer: <b class="text-slate-700"><?= e((string) ($q['options'][$chosen] ?? '—')) ?></b></p>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
<?php else: ?>
  <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200">
    <p class="text-sm text-slate-500">This quiz has no questions assigned.</p>
  </div>
<?php endif; ?>
</main>
<script>
/* No app shell here either: keep presence fresh while the student takes the quiz
   (or reads their review) and close the attendance visit on real departure. */
(function () {
  var csrf = document.querySelector('meta[name="csrf"]').content;
  var ping = function () {
    fetch('ping.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      body: 'csrf=' + encodeURIComponent(csrf),
    }).catch(function () {});
  };
  ping();
  setInterval(ping, 55000);
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') ping();
  });
<?php if ($trackVisit): ?>
  if (navigator.sendBeacon) {
    window.addEventListener('pagehide', function () {
      navigator.sendBeacon('attendance.php', new URLSearchParams({
        csrf: csrf, course: '<?= (int) $courseId ?>',
      }));
    });
  }
<?php endif; ?>
})();
</script>
</body>
</html>
