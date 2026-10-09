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
$courseId = (int) $course['id'];
$userId = (int) $user['id'];
$isOwner = ($user['role'] ?? '') === 'teacher' && (int) ($course['teacher_id'] ?? 0) === $userId;
$enrolled = is_enrolled($course, $userId);
$isAdmin = ($user['role'] ?? '') === 'admin';
$courseStudents = [];
if ($isOwner) {
  $studentStmt = db()->prepare("SELECT u.id, u.name, u.email
                                FROM enrollments e
                                JOIN users u ON u.id = e.user_id
                                WHERE e.course_id = ? AND u.role = 'student'
                                ORDER BY u.name, u.id");
  $studentStmt->execute([$courseId]);
  $courseStudents = $studentStmt->fetchAll();
}
/* The main admin passes this gate as well — like every other teacher gate in
   the app — but read-only: $isOwner stays false, so no manage controls appear. */
$canView = $isOwner || $enrolled || $isAdmin;
$progress = course_progress($course, $userId);
$lessonW = $progress['total'] > 0 ? (int) round(100 / $progress['total']) : 0;

// attendance recording: log student entries only (with timestamp + IP, page-load side)
$attendance_entered = 0;                       /* unix time this visit started (students only) */
if ($canView && ($user['role'] ?? '') === 'student') {
  $attendance_entered = resume_attendance($userId, (int) $course['id']);
  $attendance_course = (int) $course['id']; // lets the footer send a "leave" beacon
}

/* lesson contents are private: only the owning teacher and enrolled students
   get the real lists (and even the counts) — other teachers see "private" */
$canLessons = can_view_lessons($course, $user);
$mainFolderId = 0;
if ($canLessons) {
  $mainFolderId = ensure_course_main_folder($courseId);
}
$folders = $canLessons ? course_folders($courseId) : [];
$validFolderIds = [];
foreach ($folders as $courseFolder) {
  $validFolderIds[(int) $courseFolder['id']] = true;
}
if ($canLessons && $mainFolderId > 0) {
  foreach ($course['materials'] as &$courseMaterial) {
    $materialFolderId = (int) ($courseMaterial['folder_id'] ?? 0);
    if (!isset($validFolderIds[$materialFolderId])) $courseMaterial['folder_id'] = $mainFolderId;
  }
  unset($courseMaterial);
}
$videos = $canLessons ? array_values(array_filter($course['materials'] ?? [], fn($m) => in_array($m['type'] ?? '', ['video', 'youtube'], true))) : [];
$docs = $canLessons ? array_values(array_filter($course['materials'] ?? [], fn($m) => ($m['type'] ?? '') === 'file')) : [];

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
<div class="mt-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 md:p-8" data-live-scope="course-online"
  data-course-id="<?= (int) $course['id'] ?>">
  <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
    <div class="max-w-2xl">
      <span
        class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"><?= e((string) ($course['category'] ?? 'General')) ?></span>
      <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900"><?= e((string) $course['title']) ?></h1>
      <p class="mt-3 leading-7 text-slate-600"><?= e((string) ($course['description'] ?? '')) ?></p>
      <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
        <?= course_teacher_html($course, 'h-7 w-7') ?>
        <span>👥 <?= count($course['enrolled'] ?? []) ?> enrolled</span>
        <?php if ($canLessons): ?><span>📦 <?= count($course['materials'] ?? []) ?> lessons</span>
        <?php else: ?><span>🔒 Lessons private</span><?php endif; ?>
        <?php if ($canLessons): ?>
          <?php
          $lhAssigns = course_assignments($courseId);
          $lhUndone  = 0;
          if ($enrolled && ($user['role'] ?? '') === 'student' && $lhAssigns) {
              foreach ($lhAssigns as $la) {
                  $ls = submission_for((int) $la['id'], $userId);
                  if ($ls === null) $lhUndone++;
              }
          }
          $lhAnnCount = count(course_announcements($courseId));
          ?>
          <span class="flex flex-wrap items-center gap-2">
            <a href="<?= e(lh_url_clean('assignment.php?course=' . $courseId)) ?>"
               class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">
              📝 <?= count($lhAssigns) ?> assignment<?= count($lhAssigns) === 1 ? '' : 's' ?><?php if ($lhUndone): ?> · <?= $lhUndone ?> to do<?php endif; ?></a>
            <a href="<?= e(lh_url_clean('announcements.php?id=' . $courseId)) ?>"
               class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">
              📣 <?= $lhAnnCount ?> announcement<?= $lhAnnCount === 1 ? '' : 's' ?></a>
            <a href="<?= e(lh_url_clean('gradebook.php?id=' . $courseId)) ?>"
               class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">📊
              Gradebook</a>
            <?php if ($isOwner): ?>
              <a href="<?= e(lh_url_clean('bulk.php?c=' . $courseId)) ?>"
                 class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 hover:bg-slate-200">🗂️
                Organise lessons</a>
            <?php endif; ?>
          </span>
        <?php endif; ?>
        <?php if (!empty($attendance_entered)): ?>
          <span
            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>⏱ Time spent
            <span data-open-seconds="<?= max(0, time() - (int) $attendance_entered) ?>"
              data-mark="me<?= (int) $courseId ?>"
              class="tabular-nums"><?= duration_between((int) $attendance_entered, null) ?></span>
          </span>
        <?php endif; ?>
        <span>📅 <?= date('M j, Y', (int) ($course['created_at'] ?? time())) ?></span>
        <?php if ($enrolled || $isOwner): ?>
          <span id="course-online-chip" class="flex items-center gap-1.5 font-medium text-emerald-700">
            <span class="relative flex h-2.5 w-2.5"><span
                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span
                class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span></span>
            <span id="course-online-count"><?= $onlineNow ?></span> online now
          </span>
        <?php endif; ?>
      </div>
    </div>
    <div class="w-full shrink-0 md:w-64">
      <?php if ($isOwner): ?>
        <div class="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-100">
          <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">You teach this course</p>
          <button data-modal-open="course-edit-modal"
            class="mt-3 w-full rounded-xl border border-indigo-200 bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">✏️
            Edit course details</button>
          <button data-modal-open="folder-create-modal"
            class="mt-2 w-full rounded-xl border border-indigo-200 bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">＋
            Add folder</button>
          <button id="lc-start"
            class="mt-2 w-full rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700">🔴
            Start live class</button>
          <details class="mt-3 rounded-xl border border-amber-200 bg-white">
            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-slate-700">
              👥 Enrolled students (<?= count($courseStudents) ?>)
            </summary>
            <a href="trainees.php?course=<?= $courseId ?>" class="block border-t border-slate-100 px-3 py-2 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">View saved trainees</a>
            <?php if ($courseStudents): ?>
              <form id="course-bulk-remove-form" method="post" action="kick.php"
                    data-confirm="Remove the selected students as one named trainee group? Their progress, grades, quizzes, and attendance stay on record.">
                <?= csrf_field() ?>
                <input type="hidden" name="course_id" value="<?= $courseId ?>">
                <input type="hidden" name="back" value="course">
                <label class="block border-t border-slate-100 px-3 py-2 text-[11px] font-semibold text-slate-600">
                  Archive group name
                  <input type="text" name="archive_group" maxlength="120" required disabled data-course-archive-group
                         placeholder="Name this group of removed trainees"
                         class="mt-1 w-full rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-normal text-slate-800">
                </label>
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-3 py-2">
                  <label class="flex items-center gap-2 text-[11px] font-semibold text-slate-600">
                    <input type="checkbox" data-course-select-all class="rounded border-slate-300 text-indigo-600">
                    Select all
                  </label>
                  <button class="rounded-lg border border-rose-200 px-2.5 py-1 text-[10px] font-semibold text-rose-600 hover:bg-rose-50">Remove selected</button>
                </div>
              </form>
              <ul class="divide-y divide-slate-100 border-t border-slate-100">
                <?php foreach ($courseStudents as $student): ?>
                  <li class="flex items-center justify-between gap-2 px-3 py-2">
                    <span class="min-w-0">
                      <input type="checkbox" name="student_ids[]" value="<?= (int) $student['id'] ?>"
                             form="course-bulk-remove-form" data-course-student-select
                             aria-label="Select <?= e((string) $student['name']) ?> for removal"
                             class="mr-1 rounded border-slate-300 text-indigo-600">
                      <span class="block truncate text-xs font-semibold text-slate-800"><?= e((string) $student['name']) ?></span>
                      <span class="block truncate text-[10px] text-slate-400"><?= e((string) $student['email']) ?></span>
                    </span>
                    <form method="post" action="kick.php"
                          data-confirm="Remove <?= e((string) $student['name']) ?> and save the entered trainee name? Their progress, grades, quizzes, and attendance stay on record.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="course_id" value="<?= $courseId ?>">
                      <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
                      <label for="trainee-name-<?= (int) $student['id'] ?>" class="sr-only">Name to save this trainee as</label>
                      <input id="trainee-name-<?= (int) $student['id'] ?>" type="text" name="trainee_names[<?= (int) $student['id'] ?>]" value="<?= e((string) $student['name']) ?>"
                             maxlength="120" required aria-label="Name to save trainee <?= e((string) $student['name']) ?> as" placeholder="Save trainee as"
                             class="mb-1 w-28 rounded-lg border border-slate-200 px-2 py-1 text-[10px] text-slate-700">
                      <input type="hidden" name="back" value="course">
                      <button class="shrink-0 rounded-lg border border-rose-200 px-2 py-1 text-[10px] font-semibold text-rose-600 hover:bg-rose-50">Remove</button>
                    </form>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="border-t border-slate-100 px-3 py-3 text-xs text-slate-500">No students are enrolled yet.</p>
            <?php endif; ?>
          </details>
          <form method="post" action="course_delete.php" data-confirm="Delete this course and all of its lessons?"
            class="mt-2">
            <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
            <button
              class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">Delete
              course</button>
          </form>
        </div>
      <?php elseif (($user['role'] ?? '') === 'student' && !$enrolled): ?>
        <div class="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-100">
          <p class="text-sm font-semibold text-amber-800">🔑 Invitation only</p>
          <p class="mt-1 text-xs leading-5 text-amber-700">This course is locked. Enter the invitation code from
            <b><?= e((string) ($course['teacher_name'] ?? 'the teacher')) ?></b> to unlock it:</p>
          <form method="post" action="enroll.php" class="mt-2 space-y-2">
            <?= csrf_field() ?>
            <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
            <input name="code" required placeholder="e.g. A70B-59CC"
              class="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs uppercase outline-none focus:border-amber-400">
            <button
              class="w-full rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">🔓
              Unlock this course</button>
          </form>
        </div>
      <?php elseif ($enrolled): ?>
        <div class="rounded-xl bg-indigo-50 p-4 ring-1 ring-indigo-100">
          <div class="flex items-center justify-between text-xs font-semibold text-indigo-700">
            <span data-role="text" data-progress-for="<?= e((string) $course['id']) ?>"><?= $progress['done'] ?> of
              <?= $progress['total'] ?> lessons completed</span>
            <span data-role="pct" data-progress-for="<?= e((string) $course['id']) ?>"><?= $progress['pct'] ?>%</span>
          </div>
          <div class="mt-2 h-2 overflow-hidden rounded-full bg-indigo-200/70">
            <div data-role="bar" data-progress-for="<?= e((string) $course['id']) ?>"
              class="h-full rounded-full bg-indigo-600 transition-all duration-300"
              style="width: <?= $progress['pct'] ?>%"></div>
          </div>
          <p class="mt-2 text-[11px] leading-4 text-slate-500">Progress splits <b>100% equally</b> across the
            <?= $progress['total'] ?> lessons — each lesson counts <b><?= $lessonW ?>%</b> toward this course. Videos
            complete only when watched to the very end; materials complete when read to the bottom at a normal pace.</p>
          <?php if ($progress['total'] > 0 && $progress['pct'] >= 100): ?>
            <a href="certificate.php?course=<?= e((string) $course['id']) ?>"
              class="mt-3 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">🎓
              View your certificate</a>
          <?php endif; ?>
          <form method="post" action="enroll.php" data-confirm="Leave this course? Your progress will be kept."
            class="mt-3">
            <?= csrf_field() ?><input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
            <button class="text-xs font-medium text-slate-400 hover:text-rose-500">Leave course</button>
          </form>
        </div>
      <?php elseif ($isAdmin): ?>
        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">🛡️ You are viewing as the
          administrator — read-only.</div>
      <?php else: ?>
        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 ring-1 ring-slate-200">👩‍🏫 You are viewing as a
          teacher — only student accounts can enroll.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($canView): ?>
  <?php
  /* live class: the one currently running (teacher control + student join banner) */
  $liveNow = live_class_active((int) $course['id']);
  ?>
  <div id="live-class-box"><?php if ($liveNow): ?>
      <div id="live-class-banner"
        class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-rose-50 p-4 ring-1 ring-rose-200">
        <div class="flex items-center gap-3">
          <span class="lh-live-dot" aria-hidden="true"><span></span><span></span></span>
          <p class="text-sm font-bold text-rose-700">🔴 Live class
            running<?= $isOwner ? '' : ' — ' . e((string) ($course['teacher_name'] ?? 'your teacher')) ?> is waiting for
            you!</p>
        </div>
        <?php if ($isOwner): ?>
          <button type="button" data-lh-copy="<?= e(live_class_join_url($courseId)) ?>" data-lh-label="🔗 Copy invite link"
            class="rounded-xl border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">🔗
            Copy invite link</button>
          <button id="lc-end" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">End
            live class</button>
          <a href="class_room.php?course=<?= $courseId ?>"
            class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Re-enter room</a>
        <?php else: ?>
          <a href="class_room.php?course=<?= $courseId ?>"
            class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">🎥
            Join now</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php require __DIR__ . '/lessons_section.php'; ?>
  <?php if ($isOwner) {
    require __DIR__ . '/course_edit_modal.php';
    require __DIR__ . '/folder_create_modal.php';
    require __DIR__ . '/lesson_modal.php';
    require __DIR__ . '/quiz_modal.php';
  } ?>
<?php else: ?>
  <?php if (($user['role'] ?? '') === 'teacher'): ?>
    <div class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-12 text-center">
      <p class="text-5xl">🔒</p>
      <h2 class="mt-4 text-xl font-bold text-slate-900">Lessons are private</h2>
      <p class="mt-1 text-sm text-slate-500">Only the course teacher and their enrolled students can see this course's
        lessons.</p>
    </div>
  <?php else: ?>
    <div class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-12 text-center">
      <p class="text-5xl">🔒</p>
      <h2 class="mt-4 text-xl font-bold text-slate-900"><?= count($course['materials'] ?? []) ?> lessons are locked</h2>
      <p class="mt-1 text-sm text-slate-500">This course is invite-only — register with the invitation code from
        <?= e((string) ($course['teacher_name'] ?? 'the teacher')) ?> to unlock the lessons.</p>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>