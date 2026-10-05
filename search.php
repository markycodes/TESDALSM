<?php
/**
 * Search across the courses and lessons this person may see. The scope is decided
 * in lh_search() from the signed-in role — a student can only ever match their own
 * enrolments, a teacher only their own courses — so a search hit is never a way
 * to discover something the rest of the app would refuse to show.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = '';
$userId = (int) $user['id'];

$q = trim((string) ($_GET['q'] ?? ''));
$results = $q !== '' ? lh_search($q, $user) : ['courses' => [], 'lessons' => []];
$total = count($results['courses']) + count($results['lessons']);

$page_title = 'Search';
require __DIR__ . '/header.php';
?>
<div class="mx-auto max-w-3xl">
  <div class="reveal">
    <h1 class="text-2xl font-bold text-slate-900">🔎 Search</h1>
    <p class="mt-1 text-sm text-slate-500">
      <?php
      echo ($user['role'] ?? '') === 'student'
          ? 'Your enrolled courses and their lessons.'
          : (($user['role'] ?? '') === 'admin' ? 'Every course on the site.' : 'Your courses and their lessons.');
      ?>
    </p>
    <form method="get" class="mt-4 flex gap-2">
      <input name="q" value="<?= e($q) ?>" maxlength="120" autofocus
        class="flex-1 rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
        placeholder="Search courses and lessons…">
      <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Search</button>
    </form>
  </div>

  <?php if ($q === ''): ?>
    <p class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-sm text-slate-400">Type at least two characters.</p>
  <?php elseif ($total === 0): ?>
    <p class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-sm text-slate-400">Nothing matched “<?= e($q) ?>”.</p>
  <?php else: ?>

    <?php if ($results['courses']): ?>
    <h2 class="mt-6 text-sm font-bold uppercase tracking-wide text-slate-400">Courses (<?= count($results['courses']) ?>)</h2>
    <ul class="mt-2 space-y-2">
      <?php foreach ($results['courses'] as $c): ?>
      <li class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
        <a href="<?= e(lh_url_clean('course.php?id=' . (int) $c['id'])) ?>" class="font-semibold text-slate-800 hover:text-emerald-700"><?= e((string) $c['title']) ?></a>
        <span class="mt-0.5 block text-[11px] text-slate-400">
          <?= e((string) $c['teacher_name']) ?>
          <?php if ((string) ($c['category'] ?? '') !== ''): ?> · <?= e((string) $c['category']) ?><?php endif; ?>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($results['lessons']): ?>
    <h2 class="mt-6 text-sm font-bold uppercase tracking-wide text-slate-400">Lessons (<?= count($results['lessons']) ?>)</h2>
    <ul class="mt-2 space-y-2">
      <?php foreach ($results['lessons'] as $m): ?>
      <li class="rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
        <a href="<?= e(lh_url_clean('course.php?id=' . (int) $m['course_id'] . '#m' . (int) $m['id'])) ?>" class="font-semibold text-slate-800 hover:text-emerald-700"><?= e((string) $m['title']) ?></a>
        <span class="mt-0.5 block text-[11px] text-slate-400"><?= e((string) $m['course_title']) ?> · <?= e(($m['type'] ?? '') === 'file' ? 'document' : 'video') ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php';
