<?php
/**
 * Class schedule — built by the teacher, read by the students.
 *
 * A slot is one of two things: a weekly class (a weekday, repeating) or a
 * one-off entry on a date (an exam, an activity, a deadline). Every slot belongs
 * to a course, so the page is course-scoped from top to bottom: a teacher — and
 * the main admin — manage the timetable of their own courses, and a student sees
 * exactly the courses they are enrolled in, never anything else.
 *
 * Both sides get the same week view. Saving a slot notifies the students of that
 * course (bell + e-mail), exactly like posting a new lesson, and the same week
 * is echoed on the dashboard (schedule_section.php).
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = 'schedule';

$role = (string) ($user['role'] ?? '');
$isAdmin = ($role === 'admin');
$isTeacher = in_array($role, ['teacher', 'admin'], true);   /* the admin passes every teacher gate */
$me = (int) $user['id'];

/* The courses this user may build a timetable for — or read one from. */
$myCourses = array_values(array_filter(load_courses(), $isTeacher
    ? fn ($c) => $isAdmin || (int) ($c['teacher_id'] ?? 0) === $me
    : fn ($c) => is_enrolled($c, $me)));

if (!$myCourses) {
    $page_title = 'Class schedule';
    require __DIR__ . '/header.php';
    $emptyMsg = $isTeacher
        ? 'Create a course first — a schedule always belongs to a course, and only its students see it.'
        : 'You are not enrolled in a course yet. Ask your teacher for an invitation code, then register with it — your class schedule appears here and on your dashboard automatically.';
    $emptyBtn = $isTeacher ? 'dashboard.php' : 'courses.php';
    $emptyLabel = $isTeacher ? 'Go to dashboard' : 'Browse courses';
    ?>
    <div class="rounded-2xl border-2 border-dashed border-slate-300 p-14 text-center">
      <p class="text-5xl">🗓️</p>
      <h1 class="mt-4 text-xl font-bold text-slate-900">No schedule yet</h1>
      <p class="mx-auto mt-1 max-w-xl text-sm text-slate-500"><?= e($emptyMsg) ?></p>
      <a href="<?= e($emptyBtn) ?>"
        class="mt-5 inline-block rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"><?= e($emptyLabel) ?></a>
    </div>
    <?php require __DIR__ . '/footer.php';
    exit;
}

/* ---- week selection: ?w=YYYY-MM-DD anywhere inside the week (defaults to this one) ---- */
$weekParam = trim((string) ($_GET['w'] ?? ''));
$weekDate = (preg_match('~^\d{4}-\d{2}-\d{2}$~', $weekParam) && strtotime($weekParam) !== false) ? $weekParam : date('Y-m-d');
$weekStart = (int) strtotime('monday this week', (int) strtotime($weekDate . ' 12:00:00'));
$weekEnd = (int) strtotime('+6 day', $weekStart);
$isThisWeek = ($weekStart === (int) strtotime('monday this week'));

/* ---- course filter — it can only ever narrow what this user may see ---- */
$scopeIds = array_map(fn ($c) => (int) $c['id'], $myCourses);
$selCourse = (int) ($_GET['course'] ?? 0);
if ($selCourse > 0 && in_array($selCourse, $scopeIds, true)) {
    $scopeIds = [$selCourse];
} else {
    $selCourse = 0;
}

/* The query string that keeps the chosen week + filter while links and redirects move around. */
$keep = [];
if (!$isThisWeek) $keep['w'] = date('Y-m-d', $weekStart);
if ($selCourse > 0) $keep['course'] = (string) $selCourse;
$qs = $keep ? '?' . http_build_query($keep) : '';

$rows = schedule_rows_for_courses($scopeIds);
$formErrors = [];
$form = [
    'slot_id' => 0,
    'course_id' => $selCourse > 0 ? $selCourse : (int) ($scopeIds[0] ?? 0),
    'title' => '', 'kind' => 'class', 'repeat_mode' => 'weekly', 'weekday' => (int) date('w'),
    'sched_date' => date('Y-m-d'), 'start_time' => '09:00', 'end_time' => '11:00', 'place' => '', 'notes' => '',
];
$editing = false;

/* ---- add, edit and remove (teachers and the admin only) ---- */
if ($isTeacher && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $slotId = (int) ($_POST['slot_id'] ?? 0);

    if ((string) ($_POST['action'] ?? '') === 'delete') {
        $ok = schedule_delete($user, $slotId);
        set_flash($ok ? 'success' : 'error', $ok ? 'Slot removed from the schedule.' : 'That slot is not yours to remove.');
        header('Location: schedule.php' . $qs);
        exit;
    }

    $res = schedule_save($user, $slotId, $_POST);
    if ($res['ok']) {
        $courseTitle = (string) (course_row((int) $res['course_id'])['title'] ?? 'your course');
        notify_course_students((int) $res['course_id'], 'schedule',
            ($res['created'] ? '🗓️ New schedule' : '🗓️ Schedule changed') . ' — ' . $courseTitle,
            'Open the class schedule to see the day, time and room.', 'schedule.php');
        set_flash('success', ($res['created'] ? 'Slot added' : 'Slot updated') . ' — the students in ' . $courseTitle . ' were notified.');
        header('Location: schedule.php' . $qs);
        exit;
    }

    /* Rejected: stay on the page, keep exactly what they typed, and say what is wrong. */
    $formErrors = $res['errors'];
    $form = array_merge($form, [
        'slot_id' => $slotId,
        'course_id' => (int) ($_POST['course_id'] ?? 0),
        'title' => trim((string) ($_POST['title'] ?? '')),
        'kind' => (string) ($_POST['kind'] ?? 'class'),
        'repeat_mode' => ((string) ($_POST['repeat_mode'] ?? 'weekly') === 'once') ? 'once' : 'weekly',
        'weekday' => (int) ($_POST['weekday'] ?? -1),
        'sched_date' => trim((string) ($_POST['sched_date'] ?? '')),
        'start_time' => schedule_hm($_POST['start_time'] ?? ''),
        'end_time' => schedule_hm($_POST['end_time'] ?? ''),
        'place' => trim((string) ($_POST['place'] ?? '')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ]);
}

/* ---- editing an existing slot: ?edit=ID fills the form above ---- */
if ($isTeacher && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $editId = (int) ($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $editRow = schedule_row($editId);
        if ($editRow && schedule_can_manage($user, (int) $editRow['course_id'])) {
            $editing = true;
            $form = [
                'slot_id' => (int) $editRow['id'],
                'course_id' => (int) $editRow['course_id'],
                'title' => (string) $editRow['title'],
                'kind' => (string) $editRow['kind'],
                'repeat_mode' => (string) $editRow['repeat_mode'],
                'weekday' => $editRow['weekday'] === null ? (int) date('w') : (int) $editRow['weekday'],
                'sched_date' => (string) ($editRow['sched_date'] ?? date('Y-m-d')),
                'start_time' => (string) $editRow['start_time'],
                'end_time' => (string) $editRow['end_time'],
                'place' => (string) $editRow['place'],
                'notes' => (string) $editRow['notes'],
            ];
        } else {
            set_flash('error', 'That slot is not yours to edit.');
        }
    }
}

$week = schedule_week($rows, $weekStart);
$kinds = schedule_kinds();
$weekdayNames = schedule_weekdays();
$weeklyCount = 0;
$onceCount = 0;
foreach ($rows as $r) {
    if ($r['repeat_mode'] === 'once') $onceCount++; else $weeklyCount++;
}
$dayCount = 0;
foreach ($week as $d) {
    if ($d['items']) $dayCount++;
}
$courseFilter = [];
foreach ($myCourses as $c) $courseFilter[(int) $c['id']] = (string) $c['title'];

$page_title = 'Class schedule';
require __DIR__ . '/header.php';
?>

<div class="mx-auto max-w-5xl">

  <!-- Title -->
  <div class="reveal flex flex-wrap items-end justify-between gap-3">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">🗓️ Class schedule</h1>
      <p class="mt-1 text-sm text-slate-500"><?= $isTeacher
        ? 'Build your week — each slot belongs to one of your courses, and the students in it see it here and on their dashboard the moment you save.'
        : 'Your classes for the week, straight from your teachers. Anything your teacher adds shows up here and on your dashboard.' ?></p>
    </div>
    <?php if ($isTeacher): ?>
      <a href="#slot-form"
        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">＋
        Add a slot</a>
    <?php endif; ?>
  </div>

  <!-- Week navigation + course filter -->
  <div
    class="reveal mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-200">
    <div class="flex items-center gap-1.5">
      <a href="schedule.php?<?= e(http_build_query(array_merge($keep, ['w' => date('Y-m-d', (int) strtotime('-7 day', $weekStart))]))) ?>"
        data-tip="Previous week" aria-label="Previous week"
        class="lh-tip grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100">‹</a>
      <div class="min-w-[10.5rem] text-center">
        <p class="text-sm font-bold text-slate-800"><?= e(date('M j', $weekStart)) ?> – <?= e(date('M j, Y', $weekEnd)) ?></p>
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
          <?= $dayCount ?> class day<?= $dayCount === 1 ? '' : 's' ?><?= $isThisWeek ? ' · this week' : '' ?></p>
      </div>
      <a href="schedule.php?<?= e(http_build_query(array_merge($keep, ['w' => date('Y-m-d', (int) strtotime('+7 day', $weekStart))]))) ?>"
        data-tip="Next week" aria-label="Next week"
        class="lh-tip grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100">›</a>
      <?php if (!$isThisWeek): ?>
        <a href="schedule.php<?= $selCourse > 0 ? '?course=' . $selCourse : '' ?>"
          class="ml-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">Today</a>
      <?php endif; ?>
    </div>
    <?php if (count($courseFilter) > 1): ?>
      <form method="get" action="schedule.php" class="flex items-center gap-2">
        <?php if (!$isThisWeek): ?><input type="hidden" name="w" value="<?= e(date('Y-m-d', $weekStart)) ?>"><?php endif; ?>
        <label for="course" class="text-xs font-semibold uppercase tracking-wide text-slate-400">Course</label>
        <select id="course" name="course" data-auto-submit
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500">
          <option value="">All (<?= count($courseFilter) ?>)</option>
          <?php foreach ($courseFilter as $cid => $ctitle): ?>
            <option value="<?= $cid ?>" <?= $cid === $selCourse ? 'selected' : '' ?>><?= e($ctitle) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button
            class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Show</button></noscript>
      </form>
    <?php endif; ?>
  </div>

  <!-- The week: Monday → Sunday, each day its own card -->
  <div class="mt-4 space-y-3">
    <?php foreach ($week as $day): ?>
      <section
        class="reveal rounded-2xl bg-white p-4 shadow-sm ring-1 <?= $day['is_today'] ? 'ring-2 ring-emerald-300' : 'ring-slate-200' ?>">
        <div class="flex items-start gap-3 sm:gap-4">
          <div class="w-14 shrink-0 text-center sm:w-16">
            <p
              class="text-[11px] font-bold uppercase tracking-wide <?= $day['is_today'] ? 'text-emerald-700' : 'text-slate-400' ?>">
              <?= e($day['short']) ?></p>
            <p class="text-xl font-extrabold leading-6 <?= $day['is_today'] ? 'text-emerald-700' : 'text-slate-800' ?>">
              <?= (int) $day['day'] ?></p>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400"><?= e($day['month']) ?></p>
            <?php if ($day['is_today']): ?><span
                class="mt-1 inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Today</span><?php endif; ?>
          </div>

          <div class="min-w-0 flex-1">
            <?php if (!$day['items']): ?>
              <p class="py-2.5 text-sm text-slate-400">No classes scheduled.</p>
            <?php else: ?>
              <ul class="space-y-2">
                <?php foreach ($day['items'] as $it):
                  $k = $kinds[$it['kind']] ?? $kinds['class'];
                  $live = $day['is_today'] && empty($it['past']); ?>
                  <li
                    class="flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-xl border px-3 py-2 <?= $it['past'] ? 'border-slate-100 bg-white opacity-55' : 'border-slate-100 bg-slate-50/70' ?>">
                    <span
                      class="rounded-lg bg-white px-2 py-1 text-[11px] font-bold text-slate-700 ring-1 ring-slate-200"><?= e(schedule_time_label($it)) ?></span>
                    <span class="text-sm font-semibold text-slate-800"><?= e($it['title'] !== '' ? $it['title'] : $k['label']) ?></span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide <?= e($k['chip']) ?>"><?= $k['icon'] ?>
                      <?= e($k['label']) ?></span>
                    <span class="text-xs font-medium text-slate-500"><?= e((string) $it['course_title']) ?></span>
                    <?php if ($it['place'] !== ''): ?><span class="text-xs text-slate-500">📍 <?= e((string) $it['place']) ?></span><?php endif; ?>
                    <?php if ($it['repeat_mode'] === 'weekly'): ?><span class="text-[11px] text-slate-400">· repeats every week</span><?php endif; ?>

                    <div class="ml-auto flex items-center gap-1.5">
                      <?php if ($live): ?>
                        <a href="live_join.php?course=<?= (int) $it['course_id'] ?>"
                          class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-emerald-700">▶
                          <?= $isTeacher ? 'Class link' : 'Join live class' ?></a>
                      <?php endif; ?>
                      <?php if ($isTeacher): ?>
                        <a href="schedule.php?<?= e(http_build_query(array_merge($keep, ['edit' => (string) $it['id']]))) ?>#slot-form"
                          class="rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-100">Edit</a>
                        <form method="post" action="schedule.php" data-confirm="Remove this slot from the schedule? Students are not told about removals.">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="slot_id" value="<?= (int) $it['id'] ?>">
                          <button
                            class="rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                            aria-label="Remove this slot">🗑</button>
                        </form>
                      <?php endif; ?>
                    </div>
                    <?php if ($it['notes'] !== ''): ?><span class="w-full text-xs text-slate-500"><?= e((string) $it['notes']) ?></span><?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>

  <?php if ($isTeacher): ?>
    <!-- Add / edit a slot -->
    <section id="slot-form" class="reveal mt-6 scroll-mt-24 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-base font-bold text-slate-900"><?= $editing ? '✏️ Edit this slot' : '＋ Add a slot' ?></h2>
          <p class="mt-1 max-w-2xl text-sm text-slate-500">A <b>weekly class</b> repeats every week on the day you pick —
            that is the timetable itself. A <b>one-off</b> entry lands on a single date: an exam, a field trip, a deadline.
            Saving notifies every student enrolled in the course (bell + e-mail).</p>
        </div>
        <?php if ($editing): ?>
          <a href="schedule.php<?= e($qs) ?>"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
        <?php endif; ?>
      </div>

      <?php if ($formErrors): ?>
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">
          <?php foreach ($formErrors as $err): ?><p>⚠️ <?= e($err) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="schedule.php" class="mt-5 grid gap-4 sm:grid-cols-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="slot_id" value="<?= (int) $form['slot_id'] ?>">

        <div>
          <label for="s-course" class="block text-sm font-medium text-slate-700">Course *</label>
          <select id="s-course" name="course_id" required
            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            <?php foreach ($courseFilter as $cid => $ctitle): ?>
              <option value="<?= $cid ?>" <?= $cid === (int) $form['course_id'] ? 'selected' : '' ?>><?= e($ctitle) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label for="s-kind" class="block text-sm font-medium text-slate-700">Type</label>
          <select id="s-kind" name="kind"
            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            <?php foreach ($kinds as $key => $k): ?>
              <option value="<?= e($key) ?>" <?= $key === (string) $form['kind'] ? 'selected' : '' ?>><?= $k['icon'] ?>
                <?= e($k['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label for="s-mode" class="block text-sm font-medium text-slate-700">Repeats</label>
          <select id="s-mode" name="repeat_mode"
            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            <option value="weekly" <?= $form['repeat_mode'] === 'weekly' ? 'selected' : '' ?>>Every week (a weekly class)
            </option>
            <option value="once" <?= $form['repeat_mode'] === 'once' ? 'selected' : '' ?>>Once, on one date</option>
          </select>
        </div>

        <div data-when="weekly">
          <label for="s-weekday" class="block text-sm font-medium text-slate-700">Day of the week *</label>
          <select id="s-weekday" name="weekday"
            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            <?php foreach ($weekdayNames as $wd => $wdName): ?>
              <option value="<?= (int) $wd ?>" <?= (int) $wd === (int) $form['weekday'] ? 'selected' : '' ?>><?= e($wdName) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div data-when="once" class="hidden">
          <label for="s-date" class="block text-sm font-medium text-slate-700">Date *</label>
          <input id="s-date" type="date" name="sched_date" value="<?= e((string) $form['sched_date']) ?>"
            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>

        <div>
          <label for="s-start" class="block text-sm font-medium text-slate-700">Starts *</label>
          <input id="s-start" type="time" name="start_time" required value="<?= e((string) $form['start_time']) ?>"
            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>

        <div>
          <label for="s-end" class="block text-sm font-medium text-slate-700">Ends</label>
          <input id="s-end" type="time" name="end_time" value="<?= e((string) $form['end_time']) ?>"
            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
          <p class="mt-1 text-xs text-slate-400">Leave empty when only the start matters.</p>
        </div>

        <div>
          <label for="s-title" class="block text-sm font-medium text-slate-700">Title</label>
          <input id="s-title" name="title" maxlength="120" value="<?= e((string) $form['title']) ?>"
            placeholder="e.g. HTML &amp; CSS lab"
            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>

        <div>
          <label for="s-place" class="block text-sm font-medium text-slate-700">Room / place</label>
          <input id="s-place" name="place" maxlength="160" value="<?= e((string) $form['place']) ?>"
            placeholder="e.g. Computer Lab 1"
            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>

        <div class="sm:col-span-2">
          <label for="s-notes" class="block text-sm font-medium text-slate-700">Notes <span
              class="font-normal text-slate-400">(optional)</span></label>
          <textarea id="s-notes" name="notes" rows="2" maxlength="500"
            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
            placeholder="What should students bring or prepare?"><?= e((string) $form['notes']) ?></textarea>
        </div>

        <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
          <button
            class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"><?= $editing ? 'Save changes' : 'Add to the schedule' ?></button>
          <span class="text-xs text-slate-400">The students in that course are notified automatically.</span>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($isTeacher): ?>
    <!-- Every slot in one list: the weekly rules and the one-off entries -->
    <section class="reveal mt-8">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-lg font-bold text-slate-900">All slots</h2>
        <span class="text-xs text-slate-400"><?= count($rows) ?> total · <?= $weeklyCount ?> weekly ·
          <?= $onceCount ?> one-off</span>
      </div>
      <?php if (!$rows): ?>
        <p class="mt-4 rounded-2xl border-2 border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
          Nothing on the timetable yet — add your first slot above. It appears in this week view and on the dashboard of
          every student enrolled in that course.
        </p>
      <?php else: ?>
        <div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
          <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3 font-semibold">Slot</th>
                <th class="px-4 py-3 font-semibold">Type</th>
                <th class="hidden px-4 py-3 font-semibold md:table-cell">Course</th>
                <th class="hidden px-4 py-3 font-semibold sm:table-cell">Where</th>
                <th class="px-4 py-3 text-right font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($rows as $r):
                $k = $kinds[$r['kind']] ?? $kinds['class']; ?>
                <tr>
                  <td class="px-4 py-3">
                    <span
                      class="font-semibold text-slate-800"><?= e($r['title'] !== '' ? $r['title'] : $k['label']) ?></span>
                    <span class="block text-xs text-slate-500"><?= e(schedule_when_label($r)) ?></span>
                  </td>
                  <td class="px-4 py-3"><span
                      class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide <?= e($k['chip']) ?>"><?= $k['icon'] ?>
                      <?= e($k['label']) ?></span></td>
                  <td class="hidden px-4 py-3 text-slate-600 md:table-cell"><?= e((string) $r['course_title']) ?></td>
                  <td class="hidden px-4 py-3 text-slate-500 sm:table-cell">
                    <?= $r['place'] !== '' ? e((string) $r['place']) : '—' ?></td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-1.5">
                      <a href="schedule.php?<?= e(http_build_query(array_merge($keep, ['edit' => (string) $r['id']]))) ?>#slot-form"
                        class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100">Edit</a>
                      <form method="post" action="schedule.php"
                        data-confirm="Delete this slot? Students are not told about removals.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slot_id" value="<?= (int) $r['id'] ?>">
                        <button
                          class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-600"
                          aria-label="Delete this slot">🗑</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <p class="reveal mt-6 rounded-2xl bg-white p-4 text-center text-xs text-slate-400 shadow-sm ring-1 ring-slate-200">
      🔒 You are seeing the timetable of the courses you are enrolled in. Ask your teacher if something looks wrong —
      only the teacher of a course can change its schedule.
    </p>
  <?php endif; ?>
</div>

<script>
  /* The two behaviours this page needs, kept here instead of in app.js:
     the course filter submits on change, and the slot form shows either the
     weekday (a weekly class) or the date (a one-off) — never both. */
  (function () {
    document.querySelectorAll('select[data-auto-submit]').forEach(function (sel) {
      sel.addEventListener('change', function () { if (sel.form) sel.form.submit(); });
    });

    var mode = document.getElementById('s-mode');
    if (!mode) return;
    var day = document.getElementById('s-weekday');
    var date = document.getElementById('s-date');
    function sync() {
      var once = mode.value === 'once';
      document.querySelectorAll('[data-when]').forEach(function (el) {
        el.classList.toggle('hidden', (el.getAttribute('data-when') === 'once') !== once);
      });
      if (day) day.required = !once;
      if (date) date.required = once;
    }
    mode.addEventListener('change', sync);
    sync();
  })();
</script>

<?php require __DIR__ . '/footer.php'; ?>





