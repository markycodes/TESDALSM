<?php
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = 'dashboard';

$courses = load_courses();
$isTeacher = ($user['role'] ?? '') === 'teacher';

/* The hero heading greets the person by name (falls back to their role if a name was not set). */
$displayName = trim((string) ($user['name'] ?? ''));
if ($displayName === '') {
  $displayName = $isTeacher ? 'Trainer' : 'Student';
}

$ago = function (int $ts): string {
  $d = max(0, time() - $ts);
  if ($d < 60)
    return 'just now';
  if ($d < 3600)
    return floor($d / 60) . 'm ago';
  if ($d < 86400)
    return floor($d / 3600) . 'h ago';
  return floor($d / 86400) . 'd ago';
};

/** Small stat card: emoji tile + value + label (clean — no chart). */
$statCard = function (string $emoji, string $tile, string $label, string $value, string $key = ''): string {
  $live = $key !== '' ? ' data-live-stat="' . e($key) . '"' : '';
  return '<div class="lh-stat-mini reveal flex min-w-[150px] shrink-0 snap-start items-center justify-between gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:min-w-0">'
    . '<div class="min-w-0"><span class="lh-stat-tile grid h-9 w-9 place-items-center rounded-lg text-lg ' . $tile . '">' . $emoji . '</span>'
    . '<p class="mt-2 truncate text-2xl font-extrabold leading-7 text-slate-900 lh-num"' . $live . '>' . e($value) . '</p>'
    . '<p class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-500">' . e($label) . '</p></div>'
    . '</div>';
};

/**
 * Stat card WITH a mini bar chart: headline number + a 5-day day-by-day
 * comparison underneath (teacher dashboard only — Completions / Visits /
 * Lessons). Bars are plain inline-styled blocks so they render without a
 * Tailwind rebuild, colors match the card's emoji tile, and every column
 * carries a native title tooltip ("Sep 19: 3") for the exact value.
 * $vals = the last 5 daily counts (oldest → today), $days = short weekday
 * labels, $tips = "M j" labels for the hover text.
 */
$statBars = function (string $emoji, string $tile, string $label, string $value, string $key,
                      array $vals, array $days, array $tips, string $color, string $caption): string {
  $live = $key !== '' ? ' data-live-stat="' . e($key) . '"' : '';
  $vals = array_map('intval', array_slice(array_values($vals), 0, 5));
  while (count($vals) < 5) $vals[] = 0;             /* never render a ragged chart */
  $max = max($vals);
  $cols = '';
  foreach ($vals as $i => $v) {
    $pct = ($max > 0 && $v > 0) ? max(8, (int) round($v * 100 / $max)) : 0;
    $cols .= '<div title="' . e(($tips[$i] ?? '') . ': ' . $v) . '"'
      . ' style="flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:3px;height:100%">'
      . '<span style="font-size:9px;font-weight:800;line-height:1;color:' . ($v > 0 ? '#475569' : '#cbd5e1') . '">' . $v . '</span>'
      . '<div style="flex:1;width:100%;display:flex;align-items:flex-end">'
      . '<div style="width:100%;height:' . ($v > 0 ? $pct . '%' : '2px') . ';border-radius:5px 5px 2px 2px;background:'
      . ($v > 0 ? $color : '#e2e8f0') . '"></div></div>'
      . '<span style="font-size:9px;font-weight:600;line-height:1;color:#94a3b8;white-space:nowrap">'
      . e($days[$i] ?? '') . '</span>'
      . '</div>';
  }
  return '<div class="lh-stat-bars reveal flex min-w-[150px] shrink-0 snap-start flex-col justify-between rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:col-span-2 sm:min-w-0">'
    . '<div class="flex items-center gap-3">'
    . '<span class="lh-stat-tile grid h-11 w-11 shrink-0 place-items-center rounded-lg text-xl ' . $tile . '">' . $emoji . '</span>'
    . '<div class="min-w-0"><p class="truncate text-2xl font-extrabold leading-7 text-slate-900 lh-num"' . $live . '>' . e($value) . '</p>'
    . '<p class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-500">' . e($label) . '</p></div>'
    . '</div>'
    . '<div class="lh-stat-bars-chart" style="display:flex;align-items:stretch;gap:6px;height:64px;margin-top:10px">' . $cols . '</div>'
    . '<p class="mt-1.5 truncate text-[10px] font-medium text-slate-400">' . e($caption) . '</p>'
    . '</div>';
};

if ($isTeacher) {
  $myCourses = array_values(array_filter($courses, fn($c) => (int) ($c['teacher_id'] ?? 0) === (int) $user['id']));
  $tc = teacher_counts((int) $user['id']);
  $ts = teacher_daily_series((int) $user['id']);
  $online = teacher_online_students((int) $user['id']);
  $todayVisits = teacher_today_visits((int) $user['id']);
  $activity = teacher_recent_activity((int) $user['id']);
  $attention = teacher_attention_students((int) $user['id']);
} else {
  $enrolled = array_values(array_filter($courses, fn($c) => is_enrolled($c, (string) $user['id'])));
  $totDone = 0;
  $totLessons = 0;
  foreach ($enrolled as $c) {
    $p = course_progress($c, (string) $user['id']);
    $totDone += $p['done'];
    $totLessons += $p['total'];
  }
  $avg = $totLessons > 0 ? (int) round($totDone * 100 / $totLessons) : 0;
  $ss = student_daily_series((int) $user['id']);
}

$page_title = 'Dashboard';
require __DIR__ . '/header.php';
?>

<!-- Hero banner: paper pinned to the wall -->
<section class="reveal lh-hero overflow-hidden rounded-3xl p-5 text-slate-800 sm:p-8">
  <div class="lh-hero-paper relative px-5 py-6 sm:px-9 sm:py-7">
    <span class="lh-tape lh-tape-l" aria-hidden="true"></span>
    <span class="lh-tape lh-tape-r" aria-hidden="true"></span>
    <span class="lh-tape lh-tape-b" aria-hidden="true"></span>
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="lh-kicker text-emerald-700"><?= date('l, M j') ?></p>
        <h1 class="mt-2 break-words text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
          <?= e($displayName)?> Dashboard</h1>
        <p class="mt-1.5 max-w-2xl text-sm text-slate-600">
          <?= $isTeacher ? 'Here is what is happening in your courses today.' : 'Ready to continue learning?' ?></p>
      </div>
      <?php if ($isTeacher): ?>
        <button data-modal-open="course-modal"
          class="lh-hero-cta rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800">＋
          New course</button>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Stats + analytics: live-updating region (no refresh needed) -->
<div data-live-scope="<?= $isTeacher ? 'teacher-dash' : 'student-dash' ?>">

  <!-- Stats: 1/3 hero (Students + zigzag) + 2/3 column on desktop; compact swipeable row on mobile (swipe to reveal more) -->
  <div class="relative swipe-hint">
    <div
      class="lh-stat-grid mt-6 flex gap-3 overflow-x-auto pb-1 no-scrollbar snap-x sm:grid sm:grid-cols-3 sm:gap-4 sm:overflow-visible sm:pb-0">
      <?php if ($isTeacher): ?>
        <!-- Left 1/3 — Students hero card, the only stat card with a graph -->
        <div
          class="lh-stat-hero reveal flex min-w-[190px] shrink-0 snap-start flex-col justify-between rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:row-span-3 sm:min-w-0 sm:p-5">
          <div class="flex items-center justify-between gap-3">
            <span class="lh-stat-tile grid h-10 w-10 place-items-center rounded-lg bg-emerald-50 text-xl">👥</span>
            <span
              class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Total</span>
          </div>
          <div class="mt-3">
            <p class="lh-num text-4xl font-semibold leading-10 text-slate-900" data-live-stat="students">
              <?= (int) $tc['students'] ?></p>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Students enrolled</p>
          </div>
          <div class="lh-stat-hero-chart mt-4 h-16 sm:h-24" data-live-svg="students">
            <?= zigzag_svg($ts['enrollments'], '#047857', 'rgba(4,120,87,0.14)', $ts['labels'], 'New enrollments') ?></div>
          <p class="mt-2 text-[10px] font-medium text-slate-400">New enrollments · last 14 days</p>
        </div>
        <!-- Right 2/3 — three stat cards, each with a 5-day bar comparison (series from teacher_daily_series — same source as the sparkline) -->
        <?= $statBars('✅', 'bg-emerald-50', 'Completions', (string) $tc['completions'], 'completions', array_slice($ts['completions'], -5), array_map(fn ($i) => date('D', strtotime('-' . $i . ' days')), [4, 3, 2, 1, 0]), array_slice($ts['labels'], -5), '#047857', 'Completions · last 5 days') ?>
        <?= $statBars('📍', 'bg-amber-50', 'Visits today', (string) $tc['visits_today'], 'visits_today', array_slice($ts['visits'], -5), array_map(fn ($i) => date('D', strtotime('-' . $i . ' days')), [4, 3, 2, 1, 0]), array_slice($ts['labels'], -5), '#f59e0b', 'Course visits · last 5 days') ?>
        <?= $statBars('📦', 'bg-sky-50', 'Lessons', (string) $tc['lessons'], 'lessons', array_slice($ts['uploads'], -5), array_map(fn ($i) => date('D', strtotime('-' . $i . ' days')), [4, 3, 2, 1, 0]), array_slice($ts['labels'], -5), '#0ea5e9', 'Lessons added · last 5 days') ?>
      <?php else: ?>
        <?= $statCard('🎓', 'bg-indigo-50', 'Enrolled courses', (string) count($enrolled), 'enrolled') ?>
        <?= $statCard('✅', 'bg-emerald-50', 'Lessons completed', (string) $totDone, 'done') ?>
        <?= $statCard('📈', 'bg-sky-50', 'Overall progress', $avg . '%', 'avg') ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isTeacher): ?>
    <!-- Teacher analytics: 14-day chart + live now -->
    <section class="reveal mt-6 grid gap-4 lg:grid-cols-3">
      <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-bold text-slate-900">Last 14 days</h2>
          <div class="flex items-center gap-4 text-xs font-semibold text-slate-500">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span> Course
              visits</span>
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
              Completions</span>
          </div>
        </div>
        <div class="mt-3" data-live-svg="activity"><?= activity_chart_svg($ts['visits'], $ts['completions'], $ts['labels']) ?></div>
        <div class="mt-1 flex justify-between text-[10px] font-medium text-slate-400">
          <span><?= e((string) $ts['labels'][0]) ?></span>
          <span><?= e((string) $ts['labels'][intdiv(count($ts['labels']), 2)]) ?></span>
          <span>Today</span>
        </div>
      </div>
      <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">🟢 Live now
          <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700"
            data-live-online-count><?= count($online) ?> online</span>
        </h2>
        <ul class="mt-3 space-y-2.5" data-live-list="online">
          <?php foreach ($online as $o): ?>
            <li class="flex items-center gap-2.5">
              <span
                class="relative grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700"><?= e(mb_substr((string) $o['name'], 0, 1)) ?><span
                  class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white"></span></span>
              <span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-700"><?= e((string) $o['name']) ?></span>
              <span class="shrink-0 text-[11px] text-slate-400"><?= $ago((int) $o['last_seen']) ?></span>
            </li>
          <?php endforeach; ?>
          <?php if (!$online): ?>
            <li class="px-4 py-4 text-center text-sm text-slate-400">No students online right now.</li><?php endif; ?>
        </ul>
      </div>
    </section>

    <!-- Attendance today · needs attention · recent activity -->
    <!-- Responsive: 1 column on phones, 2 on small tablets (activity spans both), 3 on desktop -->
    <section class="reveal mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div class="min-w-0 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:p-5">
        <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">📍 Attendance today
          <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700"
            data-live-visits-count><?= count($todayVisits) ?></span>
        </h2>
        <ul class="mt-3 space-y-2 lg:max-h-72 lg:overflow-y-auto lg:pr-1" data-live-list="visits">
          <?php foreach ($todayVisits as $v): ?>
            <li class="flex items-center gap-2 text-sm">
              <span class="min-w-0 flex-1 truncate font-medium text-slate-700"><?= e((string) $v['student_name']) ?></span>
              <span
                class="hidden min-w-0 max-w-[7rem] flex-1 truncate text-xs text-slate-400 sm:block"><?= e((string) $v['course_title']) ?></span>
              <span
                class="shrink-0 rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600"><?= date('H:i', (int) $v['entered_at']) ?><?= (int) $v['left_at'] > 0 ? ' – ' . date('H:i', (int) $v['left_at']) : ' …' ?></span>
            </li>
          <?php endforeach; ?>
          <?php if (!$todayVisits): ?>
            <li class="px-4 py-4 text-center text-sm text-slate-400">No visits recorded yet today.</li><?php endif; ?>
        </ul>
        <a href="attendance_day.php" class="mt-3 inline-block text-xs font-semibold text-emerald-700 hover:underline">Full
          attendance →</a>
      </div>
      <div class="min-w-0 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:p-5">
        <h2 class="text-base font-bold text-slate-900">🎯 Needs attention</h2>
        <ul class="mt-3 space-y-3 lg:max-h-72 lg:overflow-y-auto lg:pr-1" data-live-list="attention">
          <?php foreach ($attention as $a): ?>
            <li>
              <div class="flex items-center gap-2 text-sm">
                <span
                  class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-rose-100 text-xs font-bold text-rose-600"><?= e(mb_substr((string) $a['name'], 0, 1)) ?></span>
                <span class="min-w-0 flex-1 truncate font-medium text-slate-700"><?= e((string) $a['name']) ?></span>
                <span class="shrink-0 text-[11px] font-bold text-slate-500"><?= $a['pct'] ?>%</span>
              </div>
              <div class="mt-1 flex items-center gap-2 pl-9">
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                  <div class="h-full rounded-full bg-rose-400" style="width: <?= $a['pct'] ?>%"></div>
                </div>
                <span class="min-w-0 truncate text-[10px] text-slate-400"><?= $a['done'] ?>/<?= $a['total'] ?> ·
                  <?= e((string) $a['course']) ?></span>
              </div>
            </li>
          <?php endforeach; ?>
          <?php if (!$attention): ?>
            <li class="px-4 py-4 text-center text-sm text-slate-400">🎉 Everyone is on track!</li><?php endif; ?>
        </ul>
      </div>
      <div class="min-w-0 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:col-span-2 sm:p-5 lg:col-span-1">
        <h2 class="text-base font-bold text-slate-900">🕒 Recent activity</h2>
        <ul class="mt-3 space-y-2.5 lg:max-h-72 lg:overflow-y-auto lg:pr-1" data-live-list="activity">
          <?php foreach ($activity as $act): ?>
            <li class="flex items-start gap-2.5 text-sm">
              <span
                class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs <?= $act['kind'] === 'enrolled' ? 'bg-emerald-100 text-emerald-700' : 'bg-emerald-100 text-emerald-600' ?>"><?= $act['kind'] === 'enrolled' ? '👥' : '✅' ?></span>
              <span class="min-w-0 flex-1">
                <span class="block truncate text-slate-700"><b class="font-semibold"><?= e((string) $act['who']) ?></b>
                  <?= $act['kind'] === 'enrolled' ? 'enrolled in' : 'completed' ?>
                  <?= $act['kind'] === 'completed' ? '<b class="font-semibold">' . e((string) $act['lesson']) . '</b> · ' : '' ?><span
                    class="text-slate-500"><?= e((string) $act['course']) ?></span></span>
                <span class="text-[11px] text-slate-400"><?= $ago((int) $act['ts']) ?></span>
              </span>
            </li>
          <?php endforeach; ?>
          <?php if (!$activity): ?>
            <li class="px-4 py-4 text-center text-sm text-slate-400">Nothing yet — activity will appear here as students
              engage.</li><?php endif; ?>
        </ul>
      </div>
    </section>

    <section class="reveal mt-10">
      <h2 class="text-lg font-bold text-slate-900">My courses</h2>
      <?php if (!$myCourses): ?>
        <div class="mt-4 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
          <p class="text-4xl">🚀</p>
          <p class="mt-3 font-medium">No courses yet — create your first one and start uploading lessons.</p>
          <button data-modal-open="course-modal"
            class="mt-4 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">＋ Create
            your first course</button>
        </div>
      <?php else: ?>
        <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <?php foreach ($myCourses as $c): ?>
            <div
              class="reveal flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
              <span
                class="w-fit rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"><?= e((string) ($c['category'] ?? 'General')) ?></span>
              <h3 class="mt-2 font-bold text-slate-900"><?= e((string) $c['title']) ?></h3>
              <p class="mt-1 line-clamp-2 text-sm text-slate-500"><?= e((string) ($c['description'] ?? '')) ?></p>
              <p class="mt-3 text-xs text-slate-500">📦 <?= count($c['materials'] ?? []) ?> lessons · 👥
                <?= count($c['enrolled'] ?? []) ?> students</p>
              <div class="mt-4 flex items-center gap-2 pt-2">
                <a href="course.php?id=<?= e((string) $c['id']) ?>"
                  class="flex-1 rounded-xl bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-700">Manage</a>
                <form method="post" action="course_delete.php" data-confirm="Delete this course and all of its lessons?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="course_id" value="<?= e((string) $c['id']) ?>">
                  <button
                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                    title="Delete course">🗑</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php require __DIR__ . '/course_modal.php'; ?>

  <?php else: ?>
    <section class="reveal mt-6 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-bold text-slate-900">Your last 14 days</h2>
        <div class="flex items-center gap-4 text-xs font-semibold text-slate-500">
          <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span> Course
            visits</span>
          <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Lessons
            done</span>
        </div>
      </div>
      <div class="mt-3" data-live-svg="activity"><?= activity_chart_svg($ss['visits'], $ss['completions'], $ss['labels']) ?></div>
      <div class="mt-1 flex justify-between text-[10px] font-medium text-slate-400">
        <span><?= e((string) $ss['labels'][0]) ?></span>
        <span><?= e((string) $ss['labels'][intdiv(count($ss['labels']), 2)]) ?></span>
        <span>Today</span>
      </div>
    </section>
    <section class="reveal mt-10">
      <h2 class="text-lg font-bold text-slate-900">Continue learning</h2>
      <?php if (!$enrolled): ?>
        <div class="mt-4 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
          <p class="text-4xl">🎒</p>
          <p class="mt-3 font-medium">You don't have any courses yet.</p>
          <p class="mt-1 text-sm leading-6 text-slate-500">Ask your teacher for an <b>invitation code</b>, then register
            with it — your course will appear here automatically.</p>
        </div>
      <?php else: ?>
        <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <?php foreach ($enrolled as $c):
            $p = course_progress($c, (string) $user['id']); ?>
            <div
              class="reveal flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
              <span
                class="w-fit rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"><?= e((string) ($c['category'] ?? 'General')) ?></span>
              <h3 class="mt-2 font-bold text-slate-900"><?= e((string) $c['title']) ?></h3>
              <p class="mt-1 text-xs text-slate-500">by <?= e((string) ($c['teacher_name'] ?? '')) ?> · 📦 <?= $p['total'] ?>
                lessons</p>
              <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-indigo-600" style="width: <?= $p['pct'] ?>%"></div>
              </div>
              <p class="mt-1 text-xs text-slate-500"><?= $p['done'] ?> of <?= $p['total'] ?> lessons (<?= $p['pct'] ?>%)</p>
              <a href="course.php?id=<?= e((string) $c['id']) ?>"
                class="mt-4 rounded-xl bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-700"><?= $p['pct'] > 0 ? 'Resume' : 'Start' ?></a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php
    // Students see EXACTLY the course they registered with — enrollment is invite-only via teacher codes,
// so there is no self-serve course browsing on the student dashboard.
    ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>