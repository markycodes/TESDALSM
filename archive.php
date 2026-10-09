<?php
/**
 * Staff archive of students removed from courses.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$role = (string) ($user['role'] ?? '');
if (!in_array($role, ['teacher', 'admin'], true)) {
    http_response_code(403);
    exit('Only teachers and administrators can view the trainee archive.');
}

if ($role === 'admin') {
    $st = db()->query(
        'SELECT ct.saved_name, ct.archive_group, ct.removed_at, u.name AS account_name, u.email,
                remover.name AS removed_by_name, c.id AS course_id, c.title AS course_title
         FROM course_trainees ct
         JOIN courses c ON c.id = ct.course_id
         JOIN users u ON u.id = ct.user_id
         JOIN users remover ON remover.id = ct.removed_by
         ORDER BY ct.removed_at DESC, ct.id DESC'
    );
} else {
    $st = db()->prepare(
        'SELECT ct.saved_name, ct.archive_group, ct.removed_at, u.name AS account_name, u.email,
                remover.name AS removed_by_name, c.id AS course_id, c.title AS course_title
         FROM course_trainees ct
         JOIN courses c ON c.id = ct.course_id
         JOIN users u ON u.id = ct.user_id
         JOIN users remover ON remover.id = ct.removed_by
         WHERE c.teacher_id = ?
         ORDER BY ct.removed_at DESC, ct.id DESC'
    );
    $st->execute([(int) $user['id']]);
}
$trainees = $st->fetchAll();

$page_title = 'Trainee archive';
$nav_active = 'archive';
require __DIR__ . '/header.php';
?>
<main class="mx-auto max-w-6xl px-4 py-8">
  <div>
    <p class="text-xs font-bold uppercase tracking-widest text-indigo-500">LMS archive</p>
    <h1 class="mt-1 text-2xl font-extrabold text-slate-900">Removed students</h1>
    <p class="mt-1 text-sm text-slate-500">Saved trainee names and removal details from courses you can manage.</p>
  </div>

  <?php if ($trainees): ?>
    <label for="archive-search" class="sr-only">Search archived trainees</label>
    <input id="archive-search" type="search" placeholder="Search by trainee, account, group, course, or staff name…"
           class="mt-5 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
    <p id="archive-search-count" class="mt-2 text-xs text-slate-500" aria-live="polite"><?= count($trainees) ?> archived entr<?= count($trainees) === 1 ? 'y' : 'ies' ?></p>
  <?php endif; ?>

  <?php if (!$trainees): ?>
    <div class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center">
      <p class="text-4xl">🗂️</p>
      <h2 class="mt-3 font-bold text-slate-800">Archive is empty</h2>
      <p class="mt-1 text-sm text-slate-500">When a student is removed from a course, their saved trainee entry will appear here.</p>
    </div>
  <?php else: ?>
    <div class="mt-6 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3">Saved trainee name</th>
            <th class="px-4 py-3">Archive group</th>
            <th class="px-4 py-3">Student account</th>
            <th class="px-4 py-3">Course</th>
            <th class="px-4 py-3">Removed by</th>
            <th class="px-4 py-3">Removed on</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($trainees as $trainee): ?>
            <?php
              $archiveSearch = implode(' ', [
                  (string) $trainee['saved_name'],
                  (string) ($trainee['archive_group'] ?: $trainee['saved_name']),
                  (string) $trainee['account_name'],
                  (string) $trainee['email'],
                  (string) $trainee['course_title'],
                  (string) $trainee['removed_by_name'],
                  date('M j, Y g:i A', (int) $trainee['removed_at']),
              ]);
            ?>
            <tr data-archive-entry data-search="<?= e(function_exists('mb_strtolower') ? mb_strtolower($archiveSearch, 'UTF-8') : strtolower($archiveSearch)) ?>">
              <td class="px-4 py-3 font-semibold text-slate-900"><?= e((string) $trainee['saved_name']) ?></td>
              <td class="px-4 py-3 text-slate-700"><?= e((string) ($trainee['archive_group'] ?: $trainee['saved_name'])) ?></td>
              <td class="px-4 py-3 text-slate-600">
                <?= e((string) $trainee['account_name']) ?>
                <span class="block text-xs text-slate-400"><?= e((string) $trainee['email']) ?></span>
              </td>
              <td class="px-4 py-3">
                <a href="trainees.php?course=<?= (int) $trainee['course_id'] ?>" class="font-medium text-indigo-600 hover:text-indigo-800"><?= e((string) $trainee['course_title']) ?></a>
              </td>
              <td class="px-4 py-3 text-slate-600"><?= e((string) $trainee['removed_by_name']) ?></td>
              <td class="px-4 py-3 whitespace-nowrap text-slate-600"><?= date('M j, Y g:i A', (int) $trainee['removed_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p id="archive-search-empty" class="hidden border-t border-slate-100 px-4 py-8 text-center text-sm text-slate-500">No archived trainees match your search.</p>
    </div>
  <?php endif; ?>
</main>
<?php if ($trainees): ?>
<script>
(function () {
  var search = document.getElementById('archive-search');
  var rows = Array.prototype.slice.call(document.querySelectorAll('[data-archive-entry]'));
  var count = document.getElementById('archive-search-count');
  var empty = document.getElementById('archive-search-empty');
  if (!search) return;
  search.addEventListener('input', function () {
    var query = search.value.trim().toLocaleLowerCase();
    var visible = 0;
    rows.forEach(function (row) {
      var matches = !query || (row.getAttribute('data-search') || '').indexOf(query) !== -1;
      row.classList.toggle('hidden', !matches);
      if (matches) visible++;
    });
    if (count) count.textContent = visible + ' archived entr' + (visible === 1 ? 'y' : 'ies');
    if (empty) empty.classList.toggle('hidden', visible !== 0);
  });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
