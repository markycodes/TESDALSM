<?php
/**
 * Saved trainee names for a course, recorded when a teacher removes students.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$courseId = (int) ($_GET['course'] ?? 0);
$course = course_row($courseId);
if (!$course) {
    set_flash('error', 'Course not found.');
    header('Location: courses.php');
    exit;
}
if (!schedule_can_manage($user, $courseId)) {
    http_response_code(403);
    exit('You do not have permission to view this course’s saved trainees.');
}

$st = db()->prepare(
    'SELECT ct.saved_name, ct.archive_group, ct.removed_at, u.name AS account_name, u.email, remover.name AS removed_by_name
     FROM course_trainees ct
     JOIN users u ON u.id = ct.user_id
     JOIN users remover ON remover.id = ct.removed_by
     WHERE ct.course_id = ?
     ORDER BY ct.removed_at DESC, ct.id DESC'
);
$st->execute([$courseId]);
$trainees = $st->fetchAll();

$page_title = 'Saved trainees';
$nav_active = 'courses';
require __DIR__ . '/header.php';
?>
<main class="mx-auto max-w-5xl px-4 py-8">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <p class="text-xs font-bold uppercase tracking-widest text-indigo-500">Course archive</p>
      <h1 class="mt-1 text-2xl font-extrabold text-slate-900">Saved trainees</h1>
      <p class="mt-1 text-sm text-slate-500"><?= e((string) $course['title']) ?></p>
    </div>
    <a href="course.php?id=<?= $courseId ?>" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">← Back to course</a>
  </div>

  <?php if (!$trainees): ?>
    <div class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center">
      <p class="text-4xl">🗂️</p>
      <h2 class="mt-3 font-bold text-slate-800">No saved trainees yet</h2>
      <p class="mt-1 text-sm text-slate-500">When you remove a student from this course, their saved name will appear here.</p>
    </div>
  <?php else: ?>
    <div class="mt-6 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">Saved trainee name</th>
            <th class="px-4 py-3">Archive group</th>
            <th class="px-4 py-3">Student account</th>
            <th class="px-4 py-3">Removed by</th>
            <th class="px-4 py-3">Saved on</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($trainees as $trainee): ?>
            <tr>
              <td class="px-4 py-3 font-semibold text-slate-900"><?= e((string) $trainee['saved_name']) ?></td>
              <td class="px-4 py-3 text-slate-700"><?= e((string) ($trainee['archive_group'] ?: $trainee['saved_name'])) ?></td>
              <td class="px-4 py-3 text-slate-600">
                <?= e((string) $trainee['account_name']) ?>
                <span class="block text-xs text-slate-400"><?= e((string) $trainee['email']) ?></span>
              </td>
              <td class="px-4 py-3 text-slate-600"><?= e((string) $trainee['removed_by_name']) ?></td>
              <td class="px-4 py-3 text-slate-600"><?= date('M j, Y g:i A', (int) $trainee['removed_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/footer.php'; ?>
