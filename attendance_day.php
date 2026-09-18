<?php
/**
 * Daily attendance page — every recorded course visit for the selected day.
 * Teachers: their courses. Students: courses they are enrolled in.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = 'attendance';
$me = (int) $user['id'];
$isTeacher = ($user['role'] ?? '') === 'teacher';

close_stale_attendance();

$courses = load_courses();
$myCourses = array_values(array_filter($courses, $isTeacher
  ? fn($c) => (int) ($c['teacher_id'] ?? 0) === $me
  : fn($c) => is_enrolled($c, $me)));

if (!$myCourses) {
  $page_title = 'Attendance';
  require __DIR__ . '/header.php';
  $emptyMsg = $isTeacher ? 'Create a course to start recording attendance.' : 'Enroll in a course to record your attendance.';
  $emptyBtn = $isTeacher ? 'dashboard.php' : 'courses.php';
  $emptyLabel = $isTeacher ? 'Go to dashboard' : 'Browse courses';
  ?>
  <div class="rounded-2xl border-2 border-dashed border-slate-300 p-14 text-center">
    <p class="text-5xl">📋</p>
    <h1 class="mt-4 text-xl font-bold text-slate-900">Nothing to record yet</h1>
    <p class="mt-1 text-sm text-slate-500"><?= e($emptyMsg) ?></p>
    <a href="<?= e($emptyBtn) ?>"
      class="mt-5 inline-block rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"><?= e($emptyLabel) ?></a>
  </div>
  <?php require __DIR__ . '/footer.php';
  exit;
}

/* ---- date selection (defaults to today) ---- */
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $date) || strtotime($date) === false) {
  $date = date('Y-m-d');
}
$dayStart = (int) strtotime($date . ' 00:00:00');
$dayEnd = $dayStart + 86400;
$isToday = ($date === date('Y-m-d'));

/* ---- course/category filters ---- */
$allCategories = [];
foreach ($myCourses as $c) {
  $cat = trim((string) ($c['category'] ?? ''));
  if ($cat !== '' && !in_array($cat, $allCategories, true))
    $allCategories[] = $cat;
}
sort($allCategories);

$selCourse = (int) ($_GET['course'] ?? 0);
$selCategory = (string) ($_GET['category'] ?? '');

$scopeCourses = [];
if ($selCourse > 0) {
  $hit = array_values(array_filter($myCourses, fn($c) => (int) $c['id'] === $selCourse));
  if ($hit)
    $scopeCourses = $hit;
  else
    $selCourse = 0;
}
if (!$scopeCourses && $selCategory !== '' && in_array($selCategory, $allCategories, true)) {
  $scopeCourses = array_values(array_filter($myCourses, fn($c) => trim((string) ($c['category'] ?? '')) === $selCategory));
}
if (!$scopeCourses)
  $scopeCourses = $myCourses;

$scopeIds = array_values(array_map('intval', array_column($scopeCourses, 'id')));
$courseOptions = $myCourses;
if ($selCategory !== '' && in_array($selCategory, $allCategories, true)) {
  $courseOptions = array_values(array_filter($myCourses, fn($c) => trim((string) ($c['category'] ?? '')) === $selCategory));
}

/* ---- records for the selected day ---- */
$records = [];
if ($scopeIds) {
  $in = implode(',', array_fill(0, count($scopeIds), '?'));
  $sql = "SELECT a.id, a.user_id, a.entered_at, a.left_at, a.ip,
                   u.name,
                   c.title AS course_title, c.category AS course_category
            FROM attendance a
            JOIN users u ON u.id = a.user_id
            JOIN courses c ON c.id = a.course_id
            WHERE a.entered_at >= ? AND a.entered_at < ? AND a.course_id IN ($in)
            ORDER BY a.entered_at DESC";
  $params = array_merge([$dayStart, $dayEnd], $scopeIds);
  $stmt = db()->prepare($sql);
  $stmt->execute($params);
  $records = $stmt->fetchAll();
}

/* ---- stats ---- */
$now = time();
$uniqueStudents = count(array_unique(array_map(fn($r) => (int) $r['user_id'], $records)));
$openCount = 0;
$totalSeconds = 0;
$recordUserIds = [];
foreach ($records as $r) {
  $recordUserIds[] = (int) $r['user_id'];
  $end = !empty($r['left_at']) ? (int) $r['left_at'] : ($isToday ? $now : (int) $r['entered_at']);
  $totalSeconds += max(0, $end - (int) $r['entered_at']);
  if (empty($r['left_at']) && $isToday)
    $openCount++;
}
$fmtDur = fn(int $s): string => sprintf('%dm %02ds', (int) floor($s / 60), $s % 60);
$onlineSet = array_flip(online_user_ids($recordUserIds));

$page_title = 'Attendance';
require __DIR__ . '/header.php';
?>
<div class="flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">📋 Attendance</h1>
    <p class="mt-1 text-sm text-slate-500">
      Recorded course visits for <span class="font-semibold text-slate-700"><?= date('l, M j, Y', $dayStart) ?></span>
      <?php if ($isToday): ?><span
          class="ml-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700"><span
            class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>live</span><?php endif; ?>
    </p>
  </div>
  <a href="enrollments.php"
    class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">👥
    Enrollments</a>
</div>

<!-- Filters -->
<form id="day-filter" method="get" action="attendance_day.php" class="mt-5 grid gap-3 sm:grid-cols-4">
  <div>
    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="f-date">Date</label>
    <input type="date" id="f-date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>"
      class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
  </div>
  <div>
    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="f-cat">Category</label>
    <select id="f-cat" name="category"
      class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      <option value="">All categories</option>
      <?php foreach ($allCategories as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $selCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="f-course">Course</label>
    <select id="f-course" name="course"
      class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      <option value="">All courses</option>
      <?php foreach ($courseOptions as $c2): ?>
        <option value="<?= (int) $c2['id'] ?>" <?= $selCourse === (int) $c2['id'] ? 'selected' : '' ?>>
          <?= e((string) $c2['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="flex items-end">
    <button type="submit"
      class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Apply
      filters</button>
  </div>
</form>

<!-- Stats -->
<div class="mt-6 grid gap-4 sm:grid-cols-4">
  <?php $stats = [
    ['📥', 'Visits', (string) count($records)],
    ['🧑‍🎓', 'Students', (string) $uniqueStudents],
    ['🟢', 'In course now', (string) ($isToday ? $openCount : 0)],
    ['⏱', 'Total time', $fmtDur($totalSeconds)],
  ];
  foreach ($stats as $s): ?>
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <p class="text-2xl"><?= $s[0] ?></p>
      <p class="mt-2 text-3xl font-extrabold text-slate-900"><?= e((string) $s[2]) ?></p>
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e((string) $s[1]) ?></p>
    </div>
  <?php endforeach; ?>
</div>
<!-- Records -->
<section class="mt-8">
  <div class="flex items-center justify-between">
    <h2 class="text-lg font-bold text-slate-900">Recorded visits (<?= count($records) ?>)</h2>
    <?php if ($isToday): ?><span class="text-xs text-slate-400">Durations update live for open
        sessions</span><?php endif; ?>
  </div>
  <?php if (!$records): ?>
    <div class="mt-4 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
      <p class="text-4xl">🗓</p>
      <p class="mt-3 font-medium">No attendance recorded for this day.</p>
    </div>
  <?php else: ?>
    <div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-4 py-3 font-semibold">Entered</th>
            <th class="px-4 py-3 font-semibold">Student</th>
            <th class="px-4 py-3 font-semibold">Course</th>
            <th class="px-4 py-3 font-semibold">Status</th>
            <th class="px-4 py-3 font-semibold">Time spent</th>
            <th class="hidden px-4 py-3 font-semibold md:table-cell">IP address</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php foreach ($records as $r):
            $open = empty($r['left_at']) && $isToday;
            $endTs = !empty($r['left_at']) ? (int) $r['left_at'] : ($isToday ? $now : (int) $r['entered_at']);
            $dur = max(0, $endTs - (int) $r['entered_at']);
            $isOnline = isset($onlineSet[(int) $r['user_id']]);
            ?>
            <tr>
              <td class="px-4 py-3 font-semibold text-slate-900"><?= date('g:i:s A', (int) $r['entered_at']) ?></td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-indigo-600 text-xs font-bold text-white"><?= e(strtoupper(substr((string) $r['name'], 0, 1))) ?></span>
                  <div class="min-w-0">
                    <p class="truncate font-semibold text-slate-900"><?= e((string) $r['name']) ?></p>
                    <p class="text-xs text-slate-400"><?= $isOnline ? '🟢 online' : 'offline' ?></p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700"><?= e((string) $r['course_title']) ?></span>
                <span class="block text-xs text-slate-400"><?= e((string) $r['course_category']) ?></span>
              </td>
              <td class="px-4 py-3">
                <?php if ($open): ?>
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">🟢
                    In course</span>
                <?php else: ?>
                  <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">⏹
                    Left <?= date('g:i A', $endTs) ?></span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-slate-600">
                <?php if ($open): ?>
                  <span data-open-seconds="<?= max(0, $now - (int) $r['entered_at']) ?>"
                    class="font-semibold text-emerald-700"></span>
                <?php else: ?>
                  <?= $fmtDur($dur) ?>
                <?php endif; ?>
              </td>
              <td class="hidden px-4 py-3 text-slate-400 md:table-cell"><?= e((string) ($r['ip'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/footer.php'; ?>