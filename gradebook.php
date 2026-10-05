<?php
/**
 * Gradebook — where a student stands in a course, and the teacher's view of the
 * whole class. Two faces on one page:
 *
 *  • Teacher (and the admin)  every enrolled student, best first
 *  • Student                 only their own row
 *
 * The number is deliberately simple and stated on the page: quizzes and graded
 * assignments each count half, a pass is 75%, and a half with nothing in it is
 * left out of the average rather than counted as zero — so a student is never
 * punished for a course that has no assignments on it.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = '';
$userId = (int) $user['id'];

$courseId = (int) ($_GET['id'] ?? $_GET['course'] ?? 0);
$course = $courseId > 0 ? course_row($courseId) : null;
if (!$course) {
    set_flash('error', 'Course not found.');
    header('Location: courses.php');
    exit;
}
$isOwner = assignment_can_manage($user, $courseId);
$enrolled = is_enrolled_id($courseId, $userId);
if (!$isOwner && !$enrolled) {
    set_flash('error', 'Enroll in this course to see the gradebook.');
    header('Location: ' . lh_url_clean('course.php?id=' . $courseId));
    exit;
}

/* the student sees exactly one row; the teacher sees the class */
$all = course_gradebook($courseId);
$rows = $isOwner ? $all : array_values(array_filter($all, fn ($r) => (int) $r['student_id'] === $userId));
$assignments = $isOwner ? course_assignments($courseId) : [];

$sum = 0.0; $gradedCount = 0; $passed = 0;
foreach ($all as $r) {
    if ($r['overall'] !== null) { $sum += (float) $r['overall']; $gradedCount++; $passed += $r['passed'] ? 1 : 0; }
}
$classAverage = $gradedCount > 0 ? round($sum / $gradedCount, 1) : null;

$page_title = 'Gradebook — ' . (string) $course['title'];
require __DIR__ . '/header.php';
$back = lh_url_clean('course.php?id=' . $courseId);

function gradebook_badge(?float $v): string
{
    if ($v === null) return '<span class="text-slate-300">—</span>';
    $cls = $v >= 75 ? 'bg-emerald-50 text-emerald-700' : ($v >= 50 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
    return '<span class="rounded-full px-2.5 py-1 text-xs font-bold ' . $cls . '">' . (0 + $v) . '%</span>';
}
?>
<div class="mx-auto max-w-4xl">
  <div class="reveal flex flex-wrap items-center justify-between gap-3">
    <div>
      <a href="<?= $back ?>" class="text-xs font-semibold text-slate-400 hover:text-emerald-700">← <?= e((string) $course['title']) ?></a>
      <h1 class="mt-1 text-2xl font-bold text-slate-900">📊 Gradebook</h1>
    </div>
    <a href="<?= $back ?>" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Back to course</a>
  </div>

  <?php if ($isOwner): ?>
  <div class="reveal mt-5 grid gap-3 sm:grid-cols-3">
    <div class="rounded-2xl bg-white p-4 text-center ring-1 ring-slate-200">
      <div class="text-xl font-extrabold text-slate-900"><?= count($rows) ?></div>
      <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Students</div>
    </div>
    <div class="rounded-2xl bg-white p-4 text-center ring-1 ring-slate-200">
      <div class="text-xl font-extrabold text-slate-900"><?= $classAverage === null ? '—' : e((string) $classAverage) . '%' ?></div>
      <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Class average</div>
    </div>
    <div class="rounded-2xl bg-white p-4 text-center ring-1 ring-slate-200">
      <div class="text-xl font-extrabold text-emerald-700"><?= $passed ?></div>
      <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Passing</div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <p class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-sm text-slate-400">Nobody is enrolled yet.</p>
  <?php else: ?>
  <div class="reveal mt-6 overflow-x-auto rounded-2xl bg-white ring-1 ring-slate-200">
    <table class="w-full min-w-[540px] text-left text-sm">
      <thead>
        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
          <th class="px-4 py-3">Student</th>
          <th class="px-4 py-3">Lessons</th>
          <th class="px-4 py-3">Quizzes</th>
          <th class="px-4 py-3">Work</th>
          <th class="px-4 py-3 text-right">Overall</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          $u = find_user_by_id((int) $r['student_id']);
          $who = ['id' => (int) $r['student_id'], 'name' => (string) ($u['name'] ?? 'Student'), 'avatar' => (string) ($u['avatar'] ?? '')]; ?>
        <tr class="border-b border-slate-100">
          <td class="px-4 py-3">
            <span class="flex items-center gap-2">
              <?= user_peer_avatar_html($who, 'h-7 w-7') ?>
              <span class="font-semibold text-slate-800"><?= e((string) $who['name']) ?></span>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-600">
            <?= (int) $r['lessons_done'] ?>/<?= (int) $r['lessons_total'] ?>
            <span class="block text-[11px] text-slate-400"><?= e((string) $r['lessons_pct']) ?>%</span>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= (int) $r['quizzes_taken'] ?> taken
            <?php if ($r['quiz_avg'] !== null): ?><span class="block text-[11px] text-slate-400">avg <?= e((string) $r['quiz_avg']) ?>%</span><?php endif; ?>
          </td>
          <td class="px-4 py-3 text-slate-600"><?= (int) $r['graded'] ?> graded
            <?php if ($r['assign_avg'] !== null): ?><span class="block text-[11px] text-slate-400">avg <?= e((string) $r['assign_avg']) ?>%</span><?php endif; ?>
          </td>
          <td class="px-4 py-3 text-right"><?= gradebook_badge($r['overall'] === null ? null : (float) $r['overall']) ?>
            <?php if ($r['overall'] !== null): ?>
              <span class="mt-1 block text-[11px] font-semibold <?= $r['passed'] ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $r['passed'] ? 'Pass' : 'Below 75%' ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <p class="mt-4 text-[11px] leading-5 text-slate-400">
    How this is worked out: your lesson quizzes average 50% of the mark and your graded assignments the other 50%.
    A half with nothing in it is left out rather than counted as zero, so a course with no assignments is not punished.
    75% or more is a pass. Nothing here is graded automatically — the numbers come from what you actually did.
  </p>

  <?php if ($isOwner && $assignments): ?>
  <h2 class="mt-8 text-base font-bold text-slate-900">Assignments</h2>
  <ul class="mt-3 space-y-2">
    <?php foreach ($assignments as $a): ?>
    <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
      <a href="<?= e(lh_url_clean('assignment.php?id=' . (int) $a['id'])) ?>" class="font-semibold text-slate-800 hover:text-emerald-700"><?= e((string) $a['title']) ?></a>
      <span class="text-[11px] text-slate-400"><?= (int) ($a['graded'] ?? 0) ?>/<?= (int) ($a['submitted'] ?? 0) ?> graded · <?= e(assignment_due_label($a)) ?></span>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php';

