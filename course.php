<?php
require_once __DIR__ . '/lib.php';
$user = require_login();

$courses = load_courses();
$idx = find_course($courses, (string) ($_GET['id'] ?? ''));
if ($idx === null) {
    set_flash('error', 'Course not found.');
    header('Location: courses.php');
    exit;
}
$course = $courses[$idx];
$userId = (int) $user['id'];
$isOwner = ($user['role'] ?? '') === 'teacher' && (int) ($course['teacher_id'] ?? 0) === $userId;
$enrolled = is_enrolled($course, $userId);
$canView = $isOwner || $enrolled;
$progress = course_progress($course, $userId);
$lessonW  = $progress['total'] > 0 ? (int) round(100 / $progress['total']) : 0;

// attendance recording: log student entries only (with timestamp + IP, page-load side)
if ($canView && ($user['role'] ?? '') === 'student') {
    record_attendance($userId, (int) $course['id']);
    $attendance_course = (int) $course['id']; // lets the footer send a "leave" beacon
}

$videos = array_values(array_filter($course['materials'] ?? [], fn ($m) => in_array($m['type'] ?? '', ['video', 'youtube'], true)));
$docs   = array_values(array_filter($course['materials'] ?? [], fn ($m) => ($m['type'] ?? '') === 'file'));

/* realtime "online now" chip — initial value (kept fresh by app.js polling) */
$onlineNow = 0;
if (($user['role'] ?? '') === 'student' ? $enrolled : $isOwner) {
    $st = db()->prepare("SELECT COUNT(*) FROM enrollments e
                         JOIN users u ON u.id = e.user_id
                         JOIN presence p ON p.user_id = u.id
                         WHERE e.course_id = ? AND p.last_seen >= ? AND u.role = 'student' AND u.id <> ?");
    $st->execute([(int) $course['id'], time() - PRESENCE_TIMEOUT, (int) $user['id']]);
    $onlineNow = (int) $st->fetchColumn();
}

$page_title = (string) $course['title'];
require __DIR__ . '/header.php';
?>

<a href="courses.php" class="text-sm font-medium text-slate-500 hover:text-indigo-600">← All courses</a>

<!-- Course header -->
<div class="mt-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-8" data-live-scope="course-online" data-course-id="<?= (int) $course['id'] ?>">
  <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
    <div class="max-w-2xl">
      <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"><?= e((string) ($course['category'] ?? 'General')) ?></span>
      <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900"><?= e((string) $course['title']) ?></h1>
      <p class="mt-3 leading-7 text-slate-600"><?= e((string) ($course['description'] ?? '')) ?></p>
      <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
        <span class="flex items-center gap-2">
          <span class="grid h-7 w-7 place-items-center rounded-full bg-indigo-600 text-xs font-bold text-white"><?= e(strtoupper(substr((string) ($course['teacher_name'] ?? '?'), 0, 1))) ?></span>
          <?= e((string) ($course['teacher_name'] ?? '')) ?>
        </span>
        <span>👥 <?= count($course['enrolled'] ?? []) ?> enrolled</span>
        <span>📦 <?= count($course['materials'] ?? []) ?> lessons</span>
        <span>📅 <?= date('M j, Y', (int) ($course['created_at'] ?? time())) ?></span>
        <?php if ($enrolled || $isOwner): ?>
        <span id="course-online-chip" class="flex items-center gap-1.5 font-medium text-emerald-700">
          <span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span></span>
          <span id="course-online-count"><?= $onlineNow ?></span> online now
        </span>
        <?php endif; ?>
      </div>
    </div>
    <div class="w-full shrink-0 md:w-64">
      <?php if ($isOwner): ?>
        <div class="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-100">
          <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">You teach this course</p>
          <button data-modal-open="lesson-modal" class="mt-3 w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">＋ Add lesson</button>
          <form method="post" action="course_delete.php" data-confirm="Delete this course and all of its lessons?" class="mt-2">
            <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
            <button class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">Delete course</button>
          </form>
        </div>
      <?php elseif (($user['role'] ?? '') === 'student' && !$enrolled): ?>
        <div class="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-100">
          <p class="text-sm font-semibold text-amber-800">🔑 Invitation only</p>
          <p class="mt-1 text-xs leading-5 text-amber-700">Ask <b><?= e((string) ($course['teacher_name'] ?? 'the teacher')) ?></b> for an invitation code, then create your account with it to unlock this course.</p>
        </div>
      <?php elseif ($enrolled): ?>
        <div class="rounded-xl bg-indigo-50 p-4 ring-1 ring-indigo-100">
          <div class="flex items-center justify-between text-xs font-semibold text-indigo-700">
            <span data-role="text" data-progress-for="<?= e((string) $course['id']) ?>"><?= $progress['done'] ?> of <?= $progress['total'] ?> lessons completed</span>
            <span data-role="pct" data-progress-for="<?= e((string) $course['id']) ?>"><?= $progress['pct'] ?>%</span>
          </div>
          <div class="mt-2 h-2 overflow-hidden rounded-full bg-indigo-200/70">
            <div data-role="bar" data-progress-for="<?= e((string) $course['id']) ?>" class="h-full rounded-full bg-indigo-600 transition-all duration-300" style="width: <?= $progress['pct'] ?>%"></div>
          </div>
          <p class="mt-2 text-[11px] leading-4 text-slate-500">Progress splits <b>100% equally</b> across the <?= $progress['total'] ?> lessons — each lesson counts <b><?= $lessonW ?>%</b> toward this course. Videos complete only when watched to the very end; materials complete when read to the bottom at a normal pace.</p>
          <form method="post" action="enroll.php" data-confirm="Leave this course? Your progress will be kept." class="mt-3">
            <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
            <button class="text-xs font-medium text-slate-400 hover:text-rose-500">Leave course</button>
          </form>
        </div>
      <?php else: ?>
        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">👩‍🏫 You are viewing as a teacher — only student accounts can enroll.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($canView): ?>
<?php require __DIR__ . '/lessons_section.php'; ?>
<?php if ($isOwner) { require __DIR__ . '/lesson_modal.php'; } ?>
<?php else: ?>
<div class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-12 text-center">
  <p class="text-5xl">🔒</p>
  <h2 class="mt-4 text-xl font-bold text-slate-900"><?= count($course['materials'] ?? []) ?> lessons are locked</h2>
  <p class="mt-1 text-sm text-slate-500">This course is invite-only — register with the invitation code from <?= e((string) ($course['teacher_name'] ?? 'the teacher')) ?> to unlock the lessons.</p>
</div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>
