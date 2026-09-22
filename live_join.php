<?php
/**
 * Live-class join link — the page a shared invitation opens.
 *
 * A teacher copies this link (class_room.php and the course page have the copy
 * button, and the "class is live" notification points here too) and pastes it
 * into Messenger, a group chat, an SMS or an e-mail. This page copes with
 * whatever state the visitor arrives in:
 *
 *   • not logged in       -> log in, then come straight back to this link
 *   • not in the course   -> a clear page: ask your teacher for an invite code
 *   • class not started   -> a waiting page that walks them in automatically the
 *                            moment the teacher opens the room
 *   • class is live       -> straight into class_room.php
 *
 * Access mirrors class_room.php exactly: the owning teacher, or an enrolled
 * student. Nobody else ever reaches the room.
 */
require_once __DIR__ . '/lib.php';

$courses = load_courses();
$idx = find_course($courses, (string) ($_GET['course'] ?? ''));
if ($idx === null) {
    set_flash('error', 'That class link no longer points to a course — please ask your teacher for a new one.');
    header('Location: courses.php');
    exit;
}
$course   = $courses[$idx];
$courseId = (int) $course['id'];
$courseTitle = (string) ($course['title'] ?? 'this course');

/* 1) Not signed in yet: log in first, then come back to this very link. */
$user = current_user();
if (!$user) {
    $back = login_next_url();
    set_flash('success', 'Log in to join the live class — we will take you straight in.');
    header('Location: login.php' . ($back !== '' ? '?next=' . rawurlencode($back) : ''));
    exit;
}
$userId  = (int) $user['id'];
$isHost  = ($user['role'] ?? '') === 'teacher' && (int) ($course['teacher_id'] ?? 0) === $userId;
$allowed = $isHost || is_enrolled($course, $userId);

/* 2) Signed in, but this course is not theirs: say so plainly. */
if (!$allowed) {
    $page_title = 'Live class';
    require __DIR__ . '/header.php';
    ?>
<div class="mx-auto max-w-lg">
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
    <p class="text-5xl">🔒</p>
    <h1 class="mt-4 text-xl font-bold text-slate-900">This class is not open to your account</h1>
    <p class="mt-2 text-sm text-slate-600">
      The link is for students of <b><?= e($courseTitle) ?></b>.
      <?php if (($user['role'] ?? '') === 'student'): ?>
        If your teacher gave you an invitation code for it, enter that code on the course page to join the class.
      <?php else: ?>
        Ask that course's teacher to enroll you, then open this link again.
      <?php endif; ?>
    </p>
    <div class="mt-5 flex flex-wrap justify-center gap-3">
      <?php if (($user['role'] ?? '') === 'student'): ?>
      <a href="course.php?id=<?= $courseId ?>" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Open the course page</a>
      <?php endif; ?>
      <a href="courses.php" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">My courses</a>
    </div>
  </div>
</div>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

/* 3) In the class and the room is open: straight in. */
if (live_class_active($courseId)) {
    header('Location: ' . lh_enc_url('class_room.php?course=' . $courseId));
    exit;
}
$roomUrl = lh_enc_url('class_room.php?course=' . $courseId);


/* 4) In the class, but no live room yet: wait here and walk them in. */
$page_title = 'Live class';
require __DIR__ . '/header.php';
?>
<div class="mx-auto max-w-lg">
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
    <?php if ($isHost): ?>
      <p class="text-5xl">🎬</p>
      <h1 class="mt-4 text-xl font-bold text-slate-900">You have not started the class yet</h1>
      <p class="mt-2 text-sm text-slate-600">
        This is the link your students use — it is already safe to share. Start the class from
        <b><?= e($courseTitle) ?></b> and everyone holding this link lands in your room.
      </p>
      <div class="mt-5 flex flex-wrap justify-center gap-3">
        <a href="course.php?id=<?= $courseId ?>" class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-700">Go to the course page</a>
      </div>
    <?php else: ?>
      <span class="lh-live-dot mx-auto" aria-hidden="true"><span></span><span></span></span>
      <h1 class="mt-4 text-xl font-bold text-slate-900">The class has not started yet</h1>
      <p class="mt-2 text-sm text-slate-600">
        You are enrolled in <b><?= e($courseTitle) ?></b>. Keep this page open — the moment your teacher
        starts the live class you will be taken in automatically.
      </p>
      <p id="lj-note" class="mt-3 text-xs text-slate-400">Watching for the class…</p>
      <div class="mt-5 flex flex-wrap justify-center gap-3">
        <button id="lj-check" class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Check now</button>
        <a href="course.php?id=<?= $courseId ?>" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Open the course page</a>
      </div>
    <?php endif; ?>
  </div>
  <p class="mt-4 text-center text-xs text-slate-400">
    Nothing to install — the class runs in your browser. On a phone, open the link in Chrome or Safari:
    apps like Messenger block the camera and microphone.
  </p>
</div>

<script>
/* Poll the class state and walk the student in as soon as the teacher is live.
   Stops itself after ~30 minutes, so a forgotten tab does not poll all evening. */
(function () {
  var cid = <?= $courseId ?>;
  var ROOM = <?= json_encode($roomUrl) ?>;
  var note = document.getElementById('lj-note');
  var btn = document.getElementById('lj-check');
  var tries = 0, MAX = 225, timer = null;      /* 225 x 8s = 30 minutes */
  function check() {
    tries++;
    fetch('live_class.php?v=state&course=' + cid, { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.live) { location.replace(ROOM); return; }
        if (tries >= MAX) {
          if (timer) clearInterval(timer);
          if (note) note.textContent = 'Still nothing after 30 minutes — open the course page and check there.';
          return;
        }
        if (note) note.textContent = 'No class yet — watching for it… (' + tries + ' checks)';
      })
      .catch(function () { if (note) note.textContent = 'Waiting… (connection hiccup — still trying)'; });
  }
  <?php if (!$isHost): ?>
  timer = setInterval(check, 8000);
  if (btn) btn.addEventListener('click', check);
  check();
  <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
