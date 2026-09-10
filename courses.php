<?php
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = 'courses';

$courses = load_courses();
usort($courses, fn ($a, $b) => (int) ($b['created_at'] ?? 0) <=> (int) ($a['created_at'] ?? 0));

$categories = [];
foreach ($courses as $c) {
    $cat = trim((string) ($c['category'] ?? ''));
    if ($cat !== '' && !in_array($cat, $categories, true)) $categories[] = $cat;
}
sort($categories);

$page_title = 'All courses';
require __DIR__ . '/header.php';
?>

<div class="flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">Browse courses</h1>
    <p class="mt-1 text-sm text-slate-500"><span data-live-courses-total><?= count($courses) ?></span> course<?= count($courses) === 1 ? '' : 's' ?> available.</p>
  </div>
  <?php if (($user['role'] ?? '') === 'teacher'): ?>
    <a href="dashboard.php" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">＋ Create course</a>
  <?php endif; ?>
</div>

<div class="mt-6 flex flex-col gap-4">
  <input id="course-search" type="search" placeholder="🔍 Search by title, teacher or category…"
         class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
  <div class="flex flex-wrap gap-2">
    <button data-cat-btn="all" class="rounded-full bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white ring-1 ring-indigo-600">All</button>
    <?php foreach ($categories as $cat): ?>
    <button data-cat-btn="<?= e($cat) ?>" class="rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50"><?= e($cat) ?></button>
    <?php endforeach; ?>
  </div>
</div>

<div data-live-scope="courses">
<div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
  <?php foreach ($courses as $c):
    $owner = ($user['role'] ?? '') === 'teacher' && (int) ($c['teacher_id'] ?? 0) === (int) $user['id'];
    $enrolled = is_enrolled($c, (string) $user['id']);
    $searchText = strtolower(($c['title'] ?? '') . ' ' . ($c['teacher_name'] ?? '') . ' ' . ($c['category'] ?? ''));
  ?>
  <div data-course-card data-cat="<?= e((string) ($c['category'] ?? 'General')) ?>" data-search="<?= e($searchText) ?>"
       class="flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="flex items-center justify-between gap-2">
      <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"><?= e((string) ($c['category'] ?? 'General')) ?></span>
      <?php if ($owner): ?><span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">Your course</span>
      <?php elseif ($enrolled): ?><span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">✓ Enrolled</span><?php endif; ?>
    </div>
    <h3 class="mt-2 text-lg font-bold text-slate-900"><?= e((string) $c['title']) ?></h3>
    <p class="mt-1 line-clamp-2 flex-1 text-sm text-slate-500"><?= e((string) ($c['description'] ?? '')) ?></p>
    <p class="mt-3 text-xs text-slate-500">👩‍🏫 <?= e((string) ($c['teacher_name'] ?? '')) ?> · 📦 <span data-live-c-lessons="<?= (int) $c['id'] ?>"><?= count($c['materials'] ?? []) ?></span> lessons · 👥 <span data-live-c-students="<?= (int) $c['id'] ?>"><?= count($c['enrolled'] ?? []) ?></span></p>
    <a href="course.php?id=<?= e((string) $c['id']) ?>"
       class="mt-4 rounded-xl px-4 py-2 text-center text-sm font-semibold <?= $owner || $enrolled ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'border border-indigo-600 text-indigo-600 hover:bg-indigo-50' ?>">
      <?= $owner ? 'Manage' : ($enrolled ? 'Continue' : ((($user['role'] ?? '') === 'student') ? '🔑 View course' : 'View course')) ?>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div id="courses-empty" class="mt-6 hidden rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
  <p class="text-4xl">🔍</p><p class="mt-3 font-medium">No courses match your search.</p>
</div>
</div><!-- /data-live-scope="courses" -->

<?php require __DIR__ . '/footer.php'; ?>
