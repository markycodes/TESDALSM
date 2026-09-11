<?php
/**
 * My Records — the student's personal quiz history (single data source: quiz_results).
 * Only the signed-in student's own rows are ever queried (user_id from session),
 * so records of other students cannot be reached by manipulating URLs.
 * Role guard: students only (teachers are redirected to their own records view).
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$userId = (int) $user['id'];
if (($user['role'] ?? '') === 'teacher') {
    header('Location: quiz_records.php');
    exit;
}
$nav_active = 'records';
$page_title = 'My Quiz Records';

$records = student_quiz_records($userId);
$summary = quiz_records_summary($records);
$filter = (string) ($_GET['status'] ?? 'all');
if ($filter === 'passed' || $filter === 'failed') {
    $rows = array_values(array_filter($records, fn ($r) => strtolower($r['status']) === $filter));
} else {
    $filter = 'all';
    $rows = $records;
}

require __DIR__ . '/header.php';
?>

<a href="dashboard.php" class="text-sm font-medium text-slate-500 hover:text-indigo-600">← Dashboard</a>

<div class="mt-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-8">
  <p class="text-xs font-bold uppercase tracking-widest text-indigo-500">Quiz history</p>
  <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900">📋 My Quiz Records — <?= e((string) $user['name']) ?></h1>
  <p class="mt-2 text-sm text-slate-500">Every quiz you have completed, newest first. Each quiz can be taken once; your result is permanent.</p>

  <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
      <p class="text-2xl font-extrabold text-slate-900"><?= (int) $summary['taken'] ?></p>
      <p class="mt-0.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Quizzes taken</p>
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

  <div class="mt-5 flex flex-wrap gap-2">
    <a href="my_records.php" class="rounded-lg px-3.5 py-2 text-sm font-semibold <?= $filter === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">All (<?= (int) $summary['taken'] ?>)</a>
    <a href="my_records.php?status=passed" class="rounded-lg px-3.5 py-2 text-sm font-semibold <?= $filter === 'passed' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">✅ Passed (<?= (int) $summary['passed'] ?>)</a>
    <a href="my_records.php?status=failed" class="rounded-lg px-3.5 py-2 text-sm font-semibold <?= $filter === 'failed' ? 'bg-rose-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">✗ Failed (<?= (int) $summary['failed'] ?>)</a>
  </div>
</div>

<div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
        <tr>
          <th class="px-4 py-3">📌 Quiz title</th>
          <th class="px-4 py-3">📖 Lesson</th>
          <th class="px-4 py-3">🎯 Score</th>
          <th class="px-4 py-3">📊 %</th>
          <th class="px-4 py-3">🏷️ Status</th>
          <th class="px-4 py-3">📅 Completed</th>
          <th class="px-4 py-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400">
          <p class="text-3xl">📋</p>
          <p class="mt-2 font-medium">No quiz records<?= $filter !== 'all' ? ' with this status' : ' yet' ?>.</p>
          <p class="mt-1 text-xs">Complete a lesson, then take its quiz — results appear here automatically.</p>
        </td></tr>
      <?php else: foreach ($rows as $r): $ok = $r['status'] === 'PASSED'; ?>
        <tr class="hover:bg-slate-50/60">
          <td class="px-4 py-3 font-semibold text-slate-900"><?= e((string) $r['quiz_title']) ?></td>
          <td class="px-4 py-3 text-slate-600">
            <a href="course.php?id=<?= (int) $r['course_id'] ?>" class="hover:text-indigo-600 hover:underline"><?= e((string) $r['lesson_title']) ?></a>
            <span class="block text-xs text-slate-400"><?= e((string) $r['course_title']) ?></span>
          </td>
          <td class="px-4 py-3 font-bold text-slate-800"><?= (int) $r['correct'] ?>/<?= (int) $r['total'] ?></td>
          <td class="px-4 py-3 font-semibold text-slate-700"><?= rtrim(rtrim(number_format((float) $r['percentage'], 2), '0'), '.') ?>%</td>
          <td class="px-4 py-3">
            <span class="rounded-full px-2.5 py-1 text-xs font-bold <?= $ok ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>"><?= $ok ? 'PASSED' : 'FAILED' ?></span>
          </td>
          <td class="px-4 py-3 text-slate-500"><?= e(date('M j, Y H:i', (int) $r['created_at'])) ?></td>
          <td class="px-4 py-3 text-right">
            <details class="inline-block text-left">
              <summary class="cursor-pointer list-none rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">👁 View details</summary>
              <div class="mt-2 w-64 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs shadow-lg">
                <p class="font-bold uppercase tracking-wide text-slate-400">Answer review</p>
                <?php
                  $qmap = [];
                  $rowQuiz = lesson_quiz((int) $r['lesson_id']);
                  foreach (($rowQuiz['questions'] ?? []) as $qi => $qq) { $qmap[(int) $qq['id']] = $qi + 1; }
                ?>
                <?php if ($r['answers']): foreach ($r['answers'] as $qid => $choice): ?>
                  <p class="mt-1.5 text-slate-600">Question #<?= (int) ($qmap[(int) $qid] ?? $qid) ?> — your choice: option <b><?= (int) $choice + 1 ?></b></p>
                <?php endforeach; else: ?>
                  <p class="mt-1.5 text-slate-400">Detailed answers were not stored for this record.</p>
                <?php endif; ?>
                <a href="quiz.php?c=<?= (int) $r['course_id'] ?>&m=<?= (int) $r['lesson_id'] ?>" class="mt-2 inline-block font-semibold text-indigo-600 hover:underline">Open the quiz review →</a>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
