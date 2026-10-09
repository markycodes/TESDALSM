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
$folderId = (int) ($_GET['f'] ?? 0);

$ctx = require_quiz_access($userId, $courseId, $materialId, $folderId);
$course = $ctx['course'];
$quiz = $ctx['quiz'];
$isOwner = $ctx['isOwner'];
$material = $materialId > 0 ? get_material($courseId, $materialId) : null;
$quizItemTitle = $material['title'] ?? ($ctx['folder']['name'] ?? '');

/* Attendance: taking the lesson quiz IS studying. Arriving from the course page
   fired its leave-beacon, so continue the same row — and since this page has no
   app shell, refresh presence + send the leave-beacon itself (script at the
   bottom), exactly like the reader (read.php). */
touch_presence($userId);
$trackVisit = ($user['role'] ?? '') === 'student' && !$isOwner;
$attendance_entered = 0;
if ($trackVisit) {
    $attendance_entered = resume_attendance($userId, $courseId);
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
<?php lh_skeleton_body(true, 'quiz'); ?>
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
  <div class="mx-auto flex h-14 max-w-3xl items-center justify-between gap-3 px-4">
    <a href="course.php?id=<?= (int) $courseId ?>" class="shrink-0 rounded-lg px-2 py-1.5 text-sm font-semibold text-indigo-600 hover:bg-indigo-50">← Back to course</a>
    <div class="min-w-0 flex-1 px-2 text-center">
      <p class="truncate text-sm font-bold text-slate-900">🧪 <?= $folderId > 0 ? 'Folder quiz' : 'Lesson quiz' ?> · <?= e((string) $quiz['title']) ?></p>
      <p class="truncate text-xs text-slate-500"><?= e((string) $quizItemTitle) ?> · <?= e((string) ($course['title'] ?? '')) ?></p>
    </div>
    <span class="shrink-0 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700"><?= $total ?> questions</span>
  </div>
  <?php if ($trackVisit): ?>
    <p class="mt-2 text-right text-xs font-semibold text-emerald-700">
      ⏱ Time spent
      <span data-quiz-open-seconds="<?= max(0, time() - $attendance_entered) ?>" class="tabular-nums"><?= duration_between($attendance_entered, null) ?></span>
    </p>
  <?php endif; ?>
</header>
<main class="mx-auto max-w-3xl px-4 py-8">
<?php foreach (take_flashes() as $f): ?>
  <div class="mb-5 rounded-xl px-4 py-3 text-sm ring-1 <?= ($f['type'] ?? '') === 'error' ? 'bg-rose-50 text-rose-800 ring-rose-100' : 'bg-emerald-50 text-emerald-800 ring-emerald-100' ?>">
    <?= ($f['type'] ?? '') === 'error' ? '⚠️' : '✅' ?> <?= e((string) ($f['msg'] ?? '')) ?>
  </div>
<?php endforeach; ?>

<?php if (!$isOwner && !$result && $current): ?>
  <div id="quiz-tab-warning" class="mb-5 hidden rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm"
       role="alert" aria-live="assertive">
    <div class="flex items-start justify-between gap-3">
      <p><b>Quiz focus reminder:</b> <span data-quiz-warning-message>You switched away from the quiz. Your attempt is still in progress. Please return your attention to the quiz before continuing.</span></p>
      <button type="button" data-dismiss-quiz-warning class="shrink-0 rounded-lg px-2 py-1 font-bold text-amber-800 hover:bg-amber-100" aria-label="Dismiss quiz focus reminder">×</button>
    </div>
  </div>
<?php endif; ?>

  <!-- Quiz title, prominent -->
  <div class="mb-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <p class="text-xs font-bold uppercase tracking-widest text-indigo-500"><?= $folderId > 0 ? 'Folder quiz' : 'Lesson quiz' ?></p>
    <h1 class="mt-1 text-2xl font-extrabold text-slate-900">🧪 <?= e((string) $quiz['title']) ?></h1>
    <p class="mt-1 text-sm text-slate-500"><?= $folderId > 0 ? '📁' : '📖' ?> <?= e((string) $quizItemTitle) ?> · <?= e((string) ($course['title'] ?? '')) ?></p>
    <p class="mt-2 text-sm text-slate-600">Pass score: <b><?= (int) $quiz['pass_score'] ?>%</b> · <b><?= $total ?></b> question<?= $total === 1 ? '' : 's' ?> · You get <b>one attempt</b> — answers lock as you go and cannot be changed.</p>
  </div>

<?php if ($isOwner): ?>
  <div class="mb-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-100">👩‍🏫 <b>Teacher preview</b> — correct answers are highlighted below. Students unlock it after <?= $folderId > 0 ? 'completing every lesson in this folder' : 'completing this lesson' ?> and get a single attempt.</div>
  <div class="space-y-4">
  <?php foreach ($quiz['questions'] as $qi => $q): ?>
    <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <p class="font-semibold text-slate-900"><span class="mr-1.5 text-indigo-500">Q<?= $qi + 1 ?>.</span><?= e((string) $q['prompt']) ?></p>
      <div class="mt-3 space-y-2">
        <?php foreach ($q['options'] as $oi => $opt): ?>
        <div class="flex items-start gap-2.5 rounded-xl border px-3.5 py-2.5 text-sm <?= $oi === (int) $q['correct'] ? 'border-emerald-300 bg-emerald-50 font-semibold text-emerald-800' : 'border-slate-200 text-slate-600' ?>">
          <span class="mt-0.5 text-xs font-bold"><?= $oi === (int) $q['correct'] ? '✅' : ($oi + 1) . '.' ?></span>
          <span class="flex-1"><?= e((string) $opt) ?>
            <?php if (trim((string) ($q['option_explanations'][$oi] ?? '')) !== ''): ?>
              <span class="mt-1 block text-xs font-normal leading-5 text-slate-600"><?= e((string) $q['option_explanations'][$oi]) ?></span>
            <?php endif; ?>
          </span>
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
    <fieldset class="rounded-2xl bg-white p-5 shadow-sm ring-1 <?= $ok ?  'ring-emerald-200': 'ring-rose-200' ?>">
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
          <span class="mt-0.5 text-xs text-emerald-600 font-bold"><?= $isChosen ? '➜' : ($isRight ? '✓' : ($oi + 1) . '.') ?></span>
          <span class="flex-1"><?= e((string) $opt) ?>
            <?php if (trim((string) ($q['option_explanations'][$oi] ?? '')) !== ''): ?>
              <span class="mt-1 block text-xs leading-5 text-slate-600"><?= e((string) $q['option_explanations'][$oi]) ?></span>
            <?php endif; ?>
          </span>
          <?php if ($isChosen): ?><span class="ml-auto shrink-0 text-xs font-semibold <?= $ok ? 'text-emerald-600' : 'text-rose-500' ?>"><?= $ok ? 'your answer ✓' : 'your answer' ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>
  </div>
<?php elseif ($current): ?>
  <!-- In progress / fresh: one question at a time, no backtracking -->
  <?php if ($answeredCount === 0): ?>
  <div id="quiz-start-reminder" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4" role="alertdialog" aria-modal="true" aria-labelledby="quiz-start-title" aria-describedby="quiz-start-copy">
    <div class="w-full max-w-lg rounded-2xl border-2 border-amber-300 bg-white p-6 shadow-2xl">
      <p class="text-xs font-bold uppercase tracking-widest text-amber-600">Before you begin</p>
      <h2 id="quiz-start-title" class="mt-2 text-xl font-extrabold text-slate-900">Switching apps submits your quiz</h2>
      <p id="quiz-start-copy" class="mt-3 text-sm leading-6 text-slate-600">If your browser detects that you switch apps or use Alt+Tab, it will automatically submit your current answers. Any unanswered questions will count as incorrect, and you cannot retake the quiz.</p>
      <button type="button" id="quiz-start-confirm" class="mt-5 w-full rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">I understand — start quiz</button>
    </div>
  </div>
  <?php endif; ?>
  <div id="quiz-attempt-content"<?= $answeredCount === 0 ? ' class="hidden"' : '' ?> data-quiz-started="<?= $answeredCount > 0 ? '1' : '0' ?>">
  <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3">
    <p class="text-xs leading-5 text-indigo-900">For a focused quiz, enter fullscreen. Leaving the quiz will submit your attempt if the browser detects it.</p>
    <button type="button" id="quiz-fullscreen-button" class="shrink-0 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700">
      Enter fullscreen
    </button>
  </div>
  <p id="quiz-fullscreen-error" class="mb-4 hidden rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800" role="status"></p>
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
    <input type="hidden" name="folder" value="<?= (int) $folderId ?>">
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
  </div>
<?php else: ?>
  <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200">
    <p class="text-sm text-slate-500">This quiz has no questions assigned.</p>
  </div>
<?php endif; ?>
</main>
<script>
/* No app shell here either: keep presence fresh while the student takes the quiz
   (or reads their review) and preserve the course visit between lesson pages. */
(function () {
  var csrf = document.querySelector('meta[name="csrf"]').content;
  var visitTimer = document.querySelector('[data-quiz-open-seconds]');
  if (visitTimer) {
    var visitSeconds = parseInt(visitTimer.getAttribute('data-quiz-open-seconds') || '0', 10);
    var renderVisitTime = function () {
      var h = Math.floor(visitSeconds / 3600);
      var m = Math.floor((visitSeconds % 3600) / 60);
      var s = visitSeconds % 60;
      visitTimer.textContent = h > 0
        ? h + 'h ' + String(m).padStart(2, '0') + 'm'
        : m + 'm ' + String(s).padStart(2, '0') + 's';
    };
    renderVisitTime();
    setInterval(function () { visitSeconds++; renderVisitTime(); }, 1000);
  }
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
  var quizWarning = document.getElementById('quiz-tab-warning');
  if (quizWarning) {
    var quizWasHidden = false;
    var attemptContent = document.getElementById('quiz-attempt-content');
    var quizStarted = attemptContent && attemptContent.getAttribute('data-quiz-started') === '1';
    var quizSubmitting = false;
    var startReminder = document.getElementById('quiz-start-reminder');
    var startButton = document.getElementById('quiz-start-confirm');
    var quizSubmitUrl = new URL('quiz_auto_submit.php', window.location.href);
    var submitData = new URLSearchParams({
      csrf: csrf,
      course: '<?= (int) $courseId ?>',
      material: '<?= (int) $materialId ?>',
      folder: '<?= (int) $folderId ?>'
    });
    function submitQuizOnLeave() {
      if (!quizStarted || quizSubmitting) return;
      quizSubmitting = true;
      fetch(quizSubmitUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        body: submitData.toString(),
        keepalive: true
      }).then(function (response) {
        if (!response.ok) throw new Error('Automatic quiz submission was rejected.');
        window.location.reload();
      }).catch(function () {
        var beaconQueued = navigator.sendBeacon && navigator.sendBeacon(quizSubmitUrl, new Blob([submitData.toString()], { type: 'application/x-www-form-urlencoded; charset=UTF-8' }));
        if (beaconQueued) {
          if (warningMessage) warningMessage.textContent = 'Your attempt is being submitted. Please wait for your result.';
          quizWarning.classList.remove('hidden');
          if (document.visibilityState === 'visible') window.setTimeout(function () { window.location.reload(); }, 600);
        } else {
          quizSubmitting = false;
          if (warningMessage) warningMessage.textContent = 'Automatic submission could not be confirmed. Stay on this page and check your connection before continuing.';
          quizWarning.classList.remove('hidden');
        }
      });
    }
    if (startButton) {
      startButton.addEventListener('click', function () {
        quizStarted = true;
        if (startReminder) startReminder.classList.add('hidden');
        if (attemptContent) attemptContent.classList.remove('hidden');
      });
    }
    var answerForm = document.querySelector('form[action="quiz_answer.php"]');
    if (answerForm) {
      answerForm.addEventListener('submit', function () {
        quizStarted = false;
      });
    }
    var dismissWarning = quizWarning.querySelector('[data-dismiss-quiz-warning]');
    var warningMessage = quizWarning.querySelector('[data-quiz-warning-message]');
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') {
        quizWasHidden = true;
        submitQuizOnLeave();
      } else if (quizWasHidden) {
        quizWasHidden = false;
        if (quizSubmitting) {
          if (warningMessage) warningMessage.textContent = 'Your attempt is being submitted. Please wait for your result.';
          quizWarning.classList.remove('hidden');
          window.setTimeout(function () { window.location.reload(); }, 600);
          return;
        }
        if (!quizSubmitting && !quizWarning.classList.contains('hidden')) return;
        if (warningMessage) warningMessage.textContent = 'You switched away from the quiz. Your attempt was submitted.';
        quizWarning.classList.remove('hidden');
      }
    });
    window.addEventListener('blur', submitQuizOnLeave);
    var fullscreenButton = document.getElementById('quiz-fullscreen-button');
    var fullscreenError = document.getElementById('quiz-fullscreen-error');
    var fullscreenWasActive = false;
    function quizIsFullscreen() {
      return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }
    function quizFullscreenChanged() {
      var active = quizIsFullscreen();
      if (active) {
        fullscreenWasActive = true;
        if (fullscreenButton) fullscreenButton.textContent = 'Exit fullscreen';
      } else {
        if (fullscreenButton) fullscreenButton.textContent = 'Enter fullscreen';
        if (fullscreenWasActive) {
          fullscreenWasActive = false;
          if (warningMessage) warningMessage.textContent = 'You exited fullscreen. Your quiz attempt is still in progress; you can re-enter fullscreen and continue.';
          quizWarning.classList.remove('hidden');
        }
      }
    }
    document.addEventListener('fullscreenchange', quizFullscreenChanged);
    document.addEventListener('webkitfullscreenchange', quizFullscreenChanged);
    if (fullscreenButton) {
      fullscreenButton.addEventListener('click', function () {
        if (fullscreenError) {
          fullscreenError.textContent = '';
          fullscreenError.classList.add('hidden');
        }
        if (quizIsFullscreen()) {
          var exit = document.exitFullscreen || document.webkitExitFullscreen;
          if (exit) {
            try {
              var exitResult = exit.call(document);
              if (exitResult && typeof exitResult.catch === 'function') {
                exitResult.catch(function () {
                  if (fullscreenError) {
                    fullscreenError.textContent = 'The browser could not exit fullscreen. Use its fullscreen control or Escape key.';
                    fullscreenError.classList.remove('hidden');
                  }
                });
              }
            } catch (error) {
              if (fullscreenError) {
                fullscreenError.textContent = 'The browser could not exit fullscreen. Use its fullscreen control or Escape key.';
                fullscreenError.classList.remove('hidden');
              }
            }
          }
          return;
        }
        var enter = document.documentElement.requestFullscreen || document.documentElement.webkitRequestFullscreen;
        if (!enter) {
          if (fullscreenError) {
            fullscreenError.textContent = 'Fullscreen is not supported by this browser. You can still take the quiz; switching away will show a reminder.';
            fullscreenError.classList.remove('hidden');
          }
          return;
        }
        try {
          var enterResult = enter.call(document.documentElement);
          if (enterResult && typeof enterResult.catch === 'function') {
            enterResult.catch(function () {
              if (fullscreenError) {
                fullscreenError.textContent = 'The browser did not allow fullscreen. Try again, or continue the quiz without fullscreen.';
                fullscreenError.classList.remove('hidden');
              }
            });
          }
        } catch (error) {
          if (fullscreenError) {
            fullscreenError.textContent = 'The browser did not allow fullscreen. Try again, or continue the quiz without fullscreen.';
            fullscreenError.classList.remove('hidden');
          }
        }
      });
    }
    if (dismissWarning) {
      dismissWarning.addEventListener('click', function () {
        quizWarning.classList.add('hidden');
      });
    }
  }
<?php if ($trackVisit): ?>
  try { sessionStorage.setItem('lh-active-attendance-course', '<?= (int) $courseId ?>'); } catch (error) {}
<?php endif; ?>
})();
</script>
</body>
</html>
