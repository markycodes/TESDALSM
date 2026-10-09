<?php
/**
 * Enrollments & attendance page.
 * Shows: all students enrolled together with you (classmates), filterable by
 * category and by course, who is online right now, and recent attendance
 * (every visit to a course with entered/left time, IP and duration).
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = 'enrollments';
$me = (int) $user['id'];
$isTeacher = ($user['role'] ?? '') === 'teacher';
$isStaff = $isTeacher || (($user['role'] ?? '') === 'admin');   /* staff keep removed students on the lists; see the roster query below */

$courses = load_courses();
$myCourses = array_values(array_filter($courses, $isTeacher
    ? fn ($c) => (int) ($c['teacher_id'] ?? 0) === $me
    : fn ($c) => is_enrolled($c, $me)));

close_stale_attendance(); // auto-close sessions whose user went offline

if (!$myCourses) {
    $page_title = 'Enrollments';
    require __DIR__ . '/header.php';
    $emptyMsg = $isTeacher ? 'Create a course to see your enrolled students and their attendance.' : 'Enroll in a course first to see your classmates and attendance.';
    $emptyBtn = $isTeacher ? 'dashboard.php' : 'courses.php';
    $emptyLabel = $isTeacher ? 'Go to dashboard' : 'Browse courses';
    ?>
    <div class="rounded-2xl border-2 border-dashed border-slate-300 p-14 text-center">
      <p class="text-5xl">👥</p>
      <h1 class="mt-4 text-xl font-bold text-slate-900">No classmates yet</h1>
      <p class="mt-1 text-sm text-slate-500"><?= e($emptyMsg) ?></p>
      <a href="<?= e($emptyBtn) ?>" class="mt-5 inline-block rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"><?= e($emptyLabel) ?></a>
    </div>
    <?php require __DIR__ . '/footer.php'; exit;
}

/* categories available to this user */
$allCategories = [];
foreach ($myCourses as $c) {
    $cat = trim((string) ($c['category'] ?? ''));
    if ($cat !== '' && !in_array($cat, $allCategories, true)) $allCategories[] = $cat;
}
sort($allCategories);

/* ---- filters (category + course) ---- */
$selCourse   = (int) ($_GET['course'] ?? 0);
$selCategory = (string) ($_GET['category'] ?? '');

$scopeCourses = [];
if ($selCourse > 0) {
    $hit = array_values(array_filter($myCourses, fn ($c) => (int) $c['id'] === $selCourse));
    if ($hit) $scopeCourses = $hit; else $selCourse = 0;
}
if (!$scopeCourses && $selCategory !== '' && in_array($selCategory, $allCategories, true)) {
    $scopeCourses = array_values(array_filter($myCourses, fn ($c) => trim((string) ($c['category'] ?? '')) === $selCategory));
}
if (!$scopeCourses) $scopeCourses = $myCourses;

$scopeIds = array_values(array_map('intval', array_column($scopeCourses, 'id')));
$courseOptions = $myCourses;
if ($selCategory !== '' && in_array($selCategory, $allCategories, true)) {
    $courseOptions = array_values(array_filter($myCourses, fn ($c) => trim((string) ($c['category'] ?? '')) === $selCategory));
}

/* ---- classmates (students in the filtered courses) ----
   For a teacher (and the admin) the list ALSO keeps students who were removed
   or left but still have records in this selection — their row stays, flagged
   'Removed', so work and visits never vanish from view. Students see their
   current classmates only. realtime.php (v=roster) runs this same query, so
   the live refresh agrees with the page down to the row. */
$students = [];
$shareCourses = [];
if ($scopeIds) {
    $in = implode(',', array_fill(0, count($scopeIds), '?'));
    $sql = "SELECT u.id, u.name, u.email, u.avatar,
                   MAX(CASE WHEN e.user_id IS NULL THEN 1 ELSE 0 END) AS removed
            FROM users u
            LEFT JOIN enrollments e ON e.user_id = u.id AND e.course_id IN ($in)
            WHERE u.role = 'student' AND (e.user_id IS NOT NULL";
    $scopeParams = array_values($scopeIds);
    $params = $scopeParams;   /* one batch of scope ids for the LEFT JOIN above */
    if ($isStaff) {
        $sql .= " OR EXISTS (SELECT 1 FROM attendance a WHERE a.user_id = u.id AND a.course_id IN ($in))"
              . " OR EXISTS (SELECT 1 FROM progress p JOIN materials m ON m.id = p.material_id WHERE p.user_id = u.id AND m.course_id IN ($in))"
              . " OR EXISTS (SELECT 1 FROM quiz_results qr JOIN quizzes q ON q.id = qr.quiz_id LEFT JOIN materials m2 ON m2.id = q.material_id LEFT JOIN course_folders qf ON qf.id = q.folder_id WHERE qr.user_id = u.id AND COALESCE(m2.course_id, qf.course_id) IN ($in))"
              . " OR EXISTS (SELECT 1 FROM submissions s JOIN assignments a2 ON a2.id = s.assignment_id WHERE s.user_id = u.id AND a2.course_id IN ($in))";
        $params = array_merge($params, $scopeParams, $scopeParams, $scopeParams, $scopeParams);
    }
    $sql .= ')';
    if (!$isTeacher) { $sql .= ' AND u.id <> ?'; $params[] = $me; }
    $sql .= ' GROUP BY u.id ORDER BY u.name';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    /* the hover cards on this list ride along with it: one query for the whole
       page instead of one per name — and for a student, who may see none of these
       classmates, the gate drops every id and the query never runs */
    profile_cards_preload(array_column($students, 'id'));

    $stmt2 = db()->prepare("SELECT e.user_id, e.course_id, c.title FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.course_id IN ($in)");
    $stmt2->execute(array_values($scopeIds));
    foreach ($students as &$st) $shareCourses[(int) $st['id']] = [];
    unset($st);
    foreach ($stmt2->fetchAll() as $row2) {
        $shareCourses[(int) $row2['user_id']][] = ['id' => (int) $row2['course_id'], 'title' => (string) $row2['title']];
    }
}

/* ---- whose attendance may be read on this page ----
   A teacher (and the main admin, who owns the site) read every record of the
   courses they teach. A student reads their OWN records only: the classmate list
   above stays, because knowing who is in your class is what a class is, but how
   often a classmate turned up, when they last came, and the machine they came
   from are theirs, not yours. realtime.php (v=roster) applies the same rule to
   the live refresh, so a figure never appears and then disappears. */
$mayReadAttendance = $isStaff;

/* ---- attendance summaries across the scope ---- */
$attSummary = [];
$totalVisits = 0;
$studentIds = array_map(fn ($u) => (int) $u['id'], $students);
if ($scopeIds) {
    $in = implode(',', array_fill(0, count($scopeIds), '?'));
    $sql = "SELECT user_id, COUNT(*) AS visits, MAX(entered_at) AS last_at
            FROM attendance WHERE course_id IN ($in)"
        . ($mayReadAttendance ? '' : ' AND user_id = ?') .
        ' GROUP BY user_id';
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values(array_merge($scopeIds, $mayReadAttendance ? [] : [$me])));
    foreach ($stmt->fetchAll() as $a) {
        $attSummary[(int) $a['user_id']] = ['visits' => (int) $a['visits'], 'last_at' => (int) $a['last_at']];
        $totalVisits += (int) $a['visits'];
    }
}
$online = array_flip(online_user_ids($studentIds));
$onlineNow = count(array_filter($studentIds, fn ($id) => isset($online[$id])));

/* ---- full per-course attendance log (when a course is selected) ---- */
$attLog = [];
$logShow = '';
if ($selCourse > 0) {
    /* Teachers see each student's latest course visit. Students still see their
       own full history, and removed students remain visible to the teacher. */
    $stmt = db()->prepare('SELECT a.id, a.user_id, a.entered_at, a.left_at, a.ip, u.name,
                                   CASE WHEN u.role = \'student\' AND e.user_id IS NULL THEN 1 ELSE 0 END AS removed
                            FROM attendance a JOIN users u ON u.id = a.user_id
                            LEFT JOIN enrollments e ON e.course_id = a.course_id AND e.user_id = a.user_id
                           WHERE a.course_id = ?'
        . ($mayReadAttendance
            ? ' AND a.id = (SELECT MAX(a2.id) FROM attendance a2 WHERE a2.course_id = a.course_id AND a2.user_id = a.user_id)'
            : ' AND a.user_id = ?') .
        ' ORDER BY a.id DESC' . ($mayReadAttendance ? '' : ' LIMIT 200'));
    $stmt->execute($mayReadAttendance ? [$selCourse] : [$selCourse, $me]);
    $attLog = $stmt->fetchAll();
    $logShow = (string) ($scopeCourses[0]['title'] ?? 'this course');
}

/* The Remove action needs one unambiguous course: shown only when a single
   course is selected and this viewer manages it — the same gate kick.php
   re-checks server-side. */
$kickCourse = ($mayReadAttendance && $selCourse > 0 && schedule_can_manage($user, $selCourse)) ? $selCourse : 0;

$page_title = 'Enrollments & attendance';
require __DIR__ . '/header.php';
?>
<div class="flex flex-wrap items-end justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-slate-900">👥 Enrollments &amp; attendance</h1>
    <p class="mt-1 text-sm text-slate-500">Everyone learning with you — who's online, and the latest attendance record per student in the selected course.</p>
  </div>
  <a href="enrollments.php" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Reset filters</a>
</div>

<!-- Filters -->
<form id="enroll-filter" method="get" action="enrollments.php" class="mt-5 grid gap-3 sm:grid-cols-2">
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
        <option value="<?= (int) $c2['id'] ?>" <?= $selCourse === (int) $c2['id'] ? 'selected' : '' ?>><?= e((string) $c2['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<!-- Stats -->
<div class="mt-6 grid gap-4 sm:grid-cols-3">
  <?php $stats = [
      ['🧑‍🎓', 'Classmates', (string) count($students), 'classmates'],
      ['🟢', 'Online now', (string) $onlineNow, 'online'],
      /* the third figure is the class's total for a teacher and the viewer's own
         for a student — the query above returns only their rows, so the label has
         to say whose visits these are */
      ['📅', $mayReadAttendance ? 'Course visits' : 'Your visits', (string) $totalVisits, 'visits'],
  ]; foreach ($stats as $s): ?>
  <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <p class="text-2xl"><?= $s[0] ?></p>
    <p class="mt-2 text-3xl font-extrabold text-slate-900" data-live-summary="<?= e((string) $s[3]) ?>"><?= e($s[2]) ?></p>
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e($s[1]) ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Live roster region (no refresh needed) -->
<div data-live-scope="roster">

<!-- Classmates -->
<meta name="presence-ids" content="<?= e(implode(',', $studentIds)) ?>">
<section class="mt-8">
  <div class="flex items-center justify-between">
    <h2 class="text-lg font-bold text-slate-900" id="roster-count">Students in this view (<?= count($students) ?>)</h2>
    <span class="text-xs text-slate-400">Online status refreshes automatically (3-min window)</span>
  </div>
  <?php if (!$students): ?>
    <div class="mt-4 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
      <p class="text-4xl">👤</p><p class="mt-3 font-medium">No students to show for this selection.</p>
    </div>
  <?php else: ?>
  <div class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
      <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
        <tr>
          <th class="px-4 py-3 font-semibold">Name</th>
          <th class="hidden px-4 py-3 font-semibold md:table-cell">Email</th>
          <th class="hidden px-4 py-3 font-semibold lg:table-cell">Course(s)</th>
          <th class="px-4 py-3 font-semibold">Status</th>
          <th class="px-4 py-3 font-semibold"><?= $mayReadAttendance ? 'Attendance' : 'Your attendance' ?></th>
          <?php if ($kickCourse): ?><th class="px-4 py-3 font-semibold">Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php foreach ($students as $u):
        $sid = (int) $u['id'];
        $isOnline = isset($online[$sid]);
        $sum = $attSummary[$sid] ?? ['visits' => 0, 'last_at' => 0];
        $coursesFor = array_slice($shareCourses[$sid] ?? [], 0, 3); ?>
        <tr data-student="<?= $sid ?>">
          <td class="px-4 py-3">
            <?php /* the circle carries their picture, the name carries the card, and
                     both are decided by who is looking rather than by this list:
                     user_peer_avatar_html() and profile_hover_html() in lib.php */
                $who = '<div class="flex items-center gap-3">' . user_peer_avatar_html($u, 'h-9 w-9')
                     . '<span class="font-semibold text-slate-900">' . e((string) $u['name'])
                     . ((int) ($u['removed'] ?? 0) ? ' <span class="inline-block rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-600">Removed</span>' : '')
                     . '</span></div>'; ?>
            <?= profile_hover_html($who, $u, ['block' => true, 'self' => false]) ?>
          </td>
          <td class="hidden px-4 py-3 text-slate-500 md:table-cell" title="Partially hidden for privacy"><?= e(mask_email((string) $u['email'])) ?></td>
          <td class="hidden px-4 py-3 lg:table-cell">
            <?php foreach ($coursesFor as $cn): ?>
              <span class="mr-1 mb-1 inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700"><?= e($cn['title']) ?></span>
            <?php endforeach; if (!$coursesFor): ?><span class="text-xs text-slate-400">—</span><?php endif; if (count($shareCourses[$sid] ?? []) > 3): ?>
              <span data-more class="text-xs text-slate-400">+<?= count($shareCourses[$sid]) - 3 ?> more</span>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3">
            <span data-presence="<?= $sid ?>" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= $isOnline ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
              <?= $isOnline ? '● Online' : '◌ Offline' ?>
            </span>
          </td>
          <td class="px-4 py-3 text-slate-600" data-visits>
            <?php if ($mayReadAttendance || $sid === $me): ?>
              <span class="font-semibold text-slate-800"><?= (int) $sum['visits'] ?></span> visits
              <?php if ((int) $sum['last_at'] > 0): ?>
                <span class="block text-xs text-slate-400">last <?= time_ago((int) $sum['last_at']) ?></span>
              <?php endif; ?>
            <?php else: ?>
              <!-- nothing to print: this row belongs to somebody else, and the
                   figure was never queried. 'private' rather than a blank, so a
                   dash is not read as 'they never showed up' -->
              <span class="text-xs text-slate-400">private</span>
            <?php endif; ?>
          </td>
          <?php if ($kickCourse): ?>
          <td class="px-4 py-3">
            <?php if (!(int) ($u['removed'] ?? 0)): ?>
            <form method="post" action="kick.php"
                  data-confirm="Remove <?= e((string) $u['name']) ?> from this course? Their grades and attendance stay on record — they can rejoin later with a new invitation code.">
              <?= csrf_field() ?>
              <input type="hidden" name="course_id" value="<?= (int) $kickCourse ?>">
              <input type="hidden" name="student_id" value="<?= $sid ?>">
              <input type="hidden" name="back" value="enrollments">
              <button class="rounded-lg border border-rose-200 px-3 py-1.5 text-[11px] font-semibold text-rose-600 hover:bg-rose-50">Remove</button>
            </form>
            <?php else: ?>
            <span class="text-xs text-slate-400">—</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php if ($selCourse > 0): ?>
<section class="mt-10">
  <h2 class="flex flex-wrap items-center gap-2 text-lg font-bold text-slate-900">📋
    <?= $mayReadAttendance ? 'Attendance' : 'Your attendance' ?> — <?= e($logShow) ?>
    <?= course_teacher_chip($selCourse, 'h-7 w-7') ?></h2>
  <p class="mt-1 text-sm text-slate-500"><?= $mayReadAttendance ? 'Latest recorded entry per student in this course' : 'Your own entries into the course — when a classmate came is theirs to know' ?> (<span id="att-log-count"><?= count($attLog) ?></span> shown).</p>
  <?php if (!$attLog): ?>
    <p class="mt-4 rounded-2xl border-2 border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No attendance recorded for this course yet.</p>
  <?php else: ?>
  <div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
      <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
        <tr>
          <th class="px-4 py-3 font-semibold">Student</th>
          <th class="px-4 py-3 font-semibold">Entered</th>
          <th class="px-4 py-3 font-semibold">Status</th>
          <th class="px-4 py-3 font-semibold">Time spent</th>
          <?php if ($mayReadAttendance): ?>
            <th class="hidden px-4 py-3 font-semibold md:table-cell">IP address</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100" id="att-log-body">
      <?php foreach ($attLog as $a):
        $left = $a['left_at'] ? (int) $a['left_at'] : null; ?>
        <tr>
          <td class="px-4 py-3 font-semibold text-slate-900"><?= profile_hover_html(e((string) $a['name']), (int) $a['user_id'], ['self' => false]) ?><?= (int) ($a['removed'] ?? 0) ? ' <span class="inline-block rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-600">Removed</span>' : '' ?></td>
          <td class="px-4 py-3 text-slate-600"><?= date('M j, Y g:i A', (int) $a['entered_at']) ?></td>
          <td class="px-4 py-3 text-slate-600"><?php if ($left): ?><?= date('g:i A', $left) ?><?php elseif (isset($online[(int) $a['user_id']])): ?><span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">🟢 Online</span><?php else: ?><span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">⏳ In course</span><?php endif; ?></td>
          <td class="px-4 py-3 text-slate-600"><?php if ($left): ?><?= duration_between((int) $a['entered_at'], $left) ?><?php else: ?><span data-open-seconds="<?= max(0, time() - (int) $a['entered_at']) ?>" data-start-at="<?= (int) $a['entered_at'] ?>" data-mark="d<?= (int) $a['id'] ?>" class="font-semibold text-emerald-700"><?= duration_between((int) $a['entered_at'], null) ?></span><?php endif; ?></td>
          <?php if ($mayReadAttendance): ?>
            <td class="hidden px-4 py-3 text-slate-400 md:table-cell"><?= e((string) ($a['ip'] ?? '')) ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>