<?php
/**
 * Student Records (teacher) — all quiz results for the quizzes this teacher owns.
 * Single data source: quiz_results (same table the student view uses).
 * Role guard: teacher only (require_teacher redirects students to dashboard).
 */
require_once __DIR__ . '/lib.php';
$user = require_teacher();
$teacherId = (int) $user['id'];
$nav_active = 'records';
$page_title = 'Student Quiz Records';

$rows = teacher_quiz_records($teacherId);
$summary = quiz_records_summary($rows);

// group alphabetically by student name
$byStudent = [];
foreach ($rows as $r) {
    $byStudent[(string) $r['student_name']][] = $r;
}
ksort($byStudent, SORT_NATURAL | SORT_FLAG_CASE);
$students = array_keys($byStudent);

require __DIR__ . '/header.php';
?>

<a href="dashboard.php" class="text-sm font-medium text-slate-500 hover:text-indigo-600">← Dashboard</a>

<div class="mt-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-8">
  <p class="text-xs font-bold uppercase tracking-widest text-indigo-500">Teacher tools</p>
  <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900">👤 Student Quiz Records</h1>
  <p class="mt-2 text-sm text-slate-500">Every quiz your students have completed, grouped by student (alphabetical). Each quiz is one attempt — the stored result is final.</p>

  <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
      <p class="text-2xl font-extrabold text-slate-900"><?= (int) $summary['taken'] ?></p>
      <p class="mt-0.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Quizzes completed</p>
    </div>
    <div class="rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
      <p class="text-2xl font-extrabold text-emerald-700"><?= (int) $summary['passed'] ?></p>
      <p class="mt-0.5 text-xs font-semibold uppercase tracking-wide text-emerald-600">Passed</p>
    </div>
    <div class="rounded-xl bg-rose-50 p-4 ring-1 ring-rose-200">
      <p class="text-2xl font-extrabold text-rose-700"><?= (int) $summary['failed'] ?></p>
      <p class="mt-0.5 text-xs font-semibold uppercase tracking-wide text-rose-600">Failed</p>
    </div>
    <div class="rounded-xl bg-indigo-50 p-4 ring-1 ring-indigo-200">
      <p class="text-2xl font-extrabold text-indigo-700"><?= rtrim(rtrim(number_format((float) $summary['avg'], 1), '0'), '.') ?>%</p>
      <p class="mt-0.5 text-xs font-semibold uppercase tracking-wide text-indigo-600">Average</p>
    </div>
  </div>

  <div class="mt-5">
    <input id="student-search" type="search" placeholder="🔍 Search a student by name…"
      class="w-full max-w-sm rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
  </div>
</div>

<?php if (!$byStudent): ?>
<div class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-12 text-center">
  <p class="text-4xl">👤</p>
  <h2 class="mt-3 text-lg font-bold text-slate-900">No quiz records yet</h2>
  <p class="mt-1 text-sm text-slate-500">Assign a quiz to a lesson — records appear here as soon as a student finishes it.</p>
</div>
<?php else: ?>
<div class="mt-6 space-y-5" id="student-cards">
  <?php foreach ($byStudent as $studentName => $studentRows): $s = quiz_records_summary($studentRows); ?>
  <div class="student-card overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200" data-student="<?= e((string) $studentName) ?>">
    <button type="button" data-student-toggle class="flex w-full flex-wrap items-center gap-3 bg-slate-50/80 px-5 py-4 text-left hover:bg-slate-100">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-indigo-600 text-sm font-bold text-white"><?= e(strtoupper(substr($studentName, 0, 1))) ?></span>
      <span class="min-w-0 flex-1">
        <span class="block truncate text-base font-bold text-slate-900">👤 <?= e($studentName) ?></span>
        <span class="block text-xs text-slate-500"><?= count($studentRows) ?> quiz<?= count($studentRows) === 1 ? '' : 'zes' ?> · <?= (int) $s['passed'] ?> passed · <?= (int) $s['failed'] ?> failed · avg <?= rtrim(rtrim(number_format((float) $s['avg'], 1), '0'), '.') ?>%</span>
      </span>
      <span class="student-chevron shrink-0 text-slate-400 transition-transform">▾</span>
    </button>
    <div class="student-rows border-t border-slate-100">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
            <tr>
              <th class="px-5 py-3">📌 Quiz title</th>
              <th class="px-5 py-3">📖 Lesson</th>
              <th class="px-5 py-3">🎯 Score</th>
              <th class="px-5 py-3">📊 %</th>
              <th class="px-5 py-3">🏷️ Status</th>
              <th class="px-5 py-3">📅 Completed</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
          <?php foreach ($studentRows as $r): $ok = $r['status'] === 'PASSED'; ?>
            <tr class="hover:bg-slate-50/60">
              <td class="px-5 py-3 font-semibold text-slate-900"><?= e((string) $r['quiz_title']) ?></td>
              <td class="px-5 py-3 text-slate-600">
                <a href="course.php?id=<?= (int) $r['course_id'] ?>" class="hover:text-indigo-600 hover:underline"><?= e((string) $r['lesson_title']) ?></a>
                <span class="block text-xs text-slate-400"><?= e((string) $r['course_title']) ?></span>
              </td>
              <td class="px-5 py-3 font-bold text-slate-800"><?= (int) $r['correct'] ?>/<?= (int) $r['total'] ?></td>
              <td class="px-5 py-3 font-semibold text-slate-700"><?= rtrim(rtrim(number_format((float) $r['percentage'], 2), '0'), '.') ?>%</td>
              <td class="px-5 py-3">
                <span class="rounded-full px-2.5 py-1 text-xs font-bold <?= $ok ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>"><?= $ok ? 'PASSED' : 'FAILED' ?></span>
              </td>
              <td class="px-5 py-3 text-slate-500"><?= e(date('M j, Y H:i', (int) $r['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
(function () {
  var search = document.getElementById('student-search');
  if (search) search.addEventListener('input', function () {
    var q = (search.value || '').toLowerCase().trim();
    var shown = 0;
    document.querySelectorAll('.student-card').forEach(function (card) {
      var name = (card.getAttribute('data-student') || '').toLowerCase();
      var show = !q || name.indexOf(q) !== -1;
      card.classList.toggle('hidden', !show);
      if (show) shown++;
    });
    var empty = document.getElementById('search-empty');
    if (empty) empty.classList.toggle('hidden', shown > 0);
  });
  document.querySelectorAll('[data-student-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var card = btn.closest('.student-card');
      var rows = card ? card.querySelector('.student-rows') : null;
      var chev = card ? card.querySelector('.student-chevron') : null;
      if (rows) rows.classList.toggle('hidden');
      if (chev) chev.classList.toggle('-rotate-90');
    });
  });
})();
</script>
<div id="search-empty" class="hidden mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
  <p class="font-medium">No student matches your search.</p>
</div>

<?php require __DIR__ . '/footer.php'; ?>
