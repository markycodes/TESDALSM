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
$archiveGroups = [];
foreach ($trainees as $trainee) {
    $groupName = trim((string) ($trainee['archive_group'] ?? ''));
    if ($groupName === '') $groupName = (string) $trainee['saved_name'];
    $groupKey = (int) $trainee['course_id'] . ':' . $groupName;
    if (!isset($archiveGroups[$groupKey])) {
        $archiveGroups[$groupKey] = [
            'name' => $groupName,
            'course_id' => (int) $trainee['course_id'],
            'course_title' => (string) $trainee['course_title'],
            'entries' => [],
        ];
    }
    $archiveGroups[$groupKey]['entries'][] = $trainee;
}

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
    <p id="archive-search-count" class="mt-2 text-xs text-slate-500" aria-live="polite"><?= count($archiveGroups) ?> archive group<?= count($archiveGroups) === 1 ? '' : 's' ?> · <?= count($trainees) ?> trainee<?= count($trainees) === 1 ? '' : 's' ?></p>
  <?php endif; ?>

  <?php if (!$trainees): ?>
    <div class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center">
      <p class="text-4xl">🗂️</p>
      <h2 class="mt-3 font-bold text-slate-800">Archive is empty</h2>
      <p class="mt-1 text-sm text-slate-500">When a student is removed from a course, their saved trainee entry will appear here.</p>
    </div>
  <?php else: ?>
    <div id="archive-groups" class="mt-6 space-y-4">
      <?php foreach ($archiveGroups as $group): ?>
        <?php
          $groupSearch = implode(' ', [$group['name'], $group['course_title']]);
          $groupSearch = function_exists('mb_strtolower') ? mb_strtolower($groupSearch, 'UTF-8') : strtolower($groupSearch);
        ?>
        <section data-archive-group data-search="<?= e($groupSearch) ?>" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3">
            <div>
              <h2 class="font-bold text-slate-900"><?= e($group['name']) ?></h2>
              <a href="trainees.php?course=<?= $group['course_id'] ?>" class="mt-0.5 inline-block text-xs font-medium text-indigo-600 hover:text-indigo-800"><?= e($group['course_title']) ?></a>
            </div>
            <span data-group-count class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700"><?= count($group['entries']) ?> trainee<?= count($group['entries']) === 1 ? '' : 's' ?></span>
          </header>
          <div class="overflow-x-auto">
            <table class="w-full min-w-[620px] text-left text-sm">
              <thead class="text-xs uppercase tracking-wide text-slate-400">
                <tr>
                  <th class="px-4 py-2.5">Student account</th>
                  <th class="px-4 py-2.5">Removed by</th>
                  <th class="px-4 py-2.5">Removed on</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php foreach ($group['entries'] as $trainee): ?>
                  <?php
                    $entrySearch = implode(' ', [
                        (string) $trainee['saved_name'],
                        (string) $trainee['account_name'],
                        (string) $trainee['email'],
                        (string) $trainee['course_title'],
                        (string) $trainee['removed_by_name'],
                        date('M j, Y g:i A', (int) $trainee['removed_at']),
                    ]);
                    $entrySearch = function_exists('mb_strtolower') ? mb_strtolower($entrySearch, 'UTF-8') : strtolower($entrySearch);
                  ?>
                  <tr data-archive-entry data-search="<?= e($entrySearch) ?>">
                    <td class="px-4 py-3 text-slate-700">
                      <span class="font-semibold text-slate-900"><?= e((string) $trainee['saved_name']) ?></span>
                      <?php if ((string) $trainee['saved_name'] !== (string) $trainee['account_name']): ?>
                        <span class="block text-xs text-slate-400">Account: <?= e((string) $trainee['account_name']) ?></span>
                      <?php endif; ?>
                      <span class="block text-xs text-slate-400"><?= e((string) $trainee['email']) ?></span>
                    </td>
                    <td class="px-4 py-3 text-slate-600"><?= e((string) $trainee['removed_by_name']) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-600"><?= date('M j, Y g:i A', (int) $trainee['removed_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
    <p id="archive-search-empty" class="hidden mt-6 rounded-xl border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500">No archived trainees match your search.</p>
  <?php endif; ?>
</main>
<?php if ($trainees): ?>
<script>
(function () {
  var search = document.getElementById('archive-search');
  var groups = Array.prototype.slice.call(document.querySelectorAll('[data-archive-group]'));
  var count = document.getElementById('archive-search-count');
  var empty = document.getElementById('archive-search-empty');
  if (!search) return;
  search.addEventListener('input', function () {
    var query = search.value.trim().toLocaleLowerCase();
    var visible = 0;
    var visibleGroups = 0;
    groups.forEach(function (group) {
      var groupMatches = !query || (group.getAttribute('data-search') || '').indexOf(query) !== -1;
      var entries = Array.prototype.slice.call(group.querySelectorAll('[data-archive-entry]'));
      var groupVisible = 0;
      entries.forEach(function (entry) {
        var matches = groupMatches || !query || (entry.getAttribute('data-search') || '').indexOf(query) !== -1;
        entry.classList.toggle('hidden', !matches);
        if (matches) groupVisible++;
      });
      group.classList.toggle('hidden', groupVisible === 0);
      if (groupVisible > 0) {
        visibleGroups++;
        visible += groupVisible;
        var badge = group.querySelector('[data-group-count]');
        if (badge) badge.textContent = groupVisible + ' trainee' + (groupVisible === 1 ? '' : 's');
      }
    });
    if (count) count.textContent = visibleGroups + ' archive group' + (visibleGroups === 1 ? '' : 's') + ' · ' + visible + ' trainee' + (visible === 1 ? '' : 's');
    if (empty) empty.classList.toggle('hidden', visible !== 0);
  });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
