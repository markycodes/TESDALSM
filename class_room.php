<?php
/**
 * Live-class room — embeds a Jitsi Meet room (video / audio / screen share / chat)
 * keyed to the course, so every course gets its own private meeting.
 * Host: the course teacher. Entry: owning teacher + enrolled students only.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();

$courses = load_courses();
$idx = find_course($courses, (string) ($_GET['course'] ?? ''));
if ($idx === null) {
    set_flash('error', 'Course not found.');
    header('Location: courses.php');
    exit;
}
$course = $courses[$idx];
$courseId = (int) $course['id'];
$userId = (int) $user['id'];
$isHost = ($user['role'] ?? '') === 'teacher' && (int) ($course['teacher_id'] ?? 0) === $userId;
if (!$isHost && !is_enrolled($course, $userId)) {
    set_flash('error', 'Enroll in this course to join its live class.');
    header('Location: course.php?id=' . $courseId);
    exit;
}

$active = live_class_active($courseId);
/* non-hosts can only sit in a room that is actually live */
if (!$active && !$isHost) {
    set_flash('error', 'No live class is running right now.');
    header('Location: course.php?id=' . $courseId);
    exit;
}
$roomId = 'lhclass-' . $courseId . '-' . ((int) $active['id']);
$page_title = 'Live class — ' . (string) $course['title'];
require __DIR__ . '/header.php';
?>
<script src="https://meet.jit.si/external_api.js"></script>

<a href="course.php?id=<?= $courseId ?>" class="text-sm font-medium text-slate-500 hover:text-indigo-600">← <?= e((string) $course['title']) ?></a>

<div class="mt-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <span class="relative flex h-3 w-3">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-rose-400 opacity-75"></span>
        <span class="relative inline-flex h-3 w-3 rounded-full bg-rose-500"></span>
      </span>
      <div>
        <h1 class="text-lg font-bold text-slate-900">🔴 Live class — <?= e((string) $course['title']) ?></h1>
        <p class="text-xs text-slate-500">Started <?= e(time_ago((int) $active['started_at'])) ?> · hosted by <?= e((string) ($course['teacher_name'] ?? 'the teacher')) ?></p>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <span id="lr-count" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">… in room</span>
      <button id="lr-hand" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">✋ Raise hand</button>
      <?php if ($isHost): ?>
      <button id="lr-end" data-confirm="End the live class for everyone?" class="rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">End class</button>
      <?php endif; ?>
    </div>
  </div>
  <div id="lr-meeting" class="mt-4 h-[62vh] min-h-[380px] w-full overflow-hidden rounded-xl bg-slate-900"></div>
  <p class="mt-2 text-[11px] text-slate-400">Video, audio, screen sharing and in-meeting chat run through Jitsi Meet. Your browser will ask for camera/microphone permission — you can join with audio only.</p>
</div>

<div class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
  <h2 class="text-sm font-bold text-slate-900">In the room <span id="lr-hand-note" class="hidden font-normal text-amber-600">— ✋ a hand is raised</span></h2>
  <ul id="lr-people" class="mt-2 grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3"></ul>
</div>

<script>
(function () {
  var cid = <?= $courseId ?>;
  var me = <?= $userId ?>;
  var isHost = <?= $isHost ? 'true' : 'false' ?>;
  var csrf = document.querySelector('meta[name="csrf"]').content;

  function post(data) {
    var body = new URLSearchParams(Object.assign({ csrf: csrf, course: cid }, data));
    return fetch('live_class.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).then(function (r) { return r.json(); });
  }

  /* Jitsi room — the embed carries video / audio / screen share / meeting chat */
  var api = null;
  try {
    api = new JitsiMeetExternalAPI('meet.jit.si', {
      roomName: <?= json_encode($roomId) ?>,
      parentNode: document.querySelector('#lr-meeting'),
      width: '100%',
      height: '100%',
      userInfo: { displayName: <?= json_encode((string) ($user['name'] ?? 'User')) ?> },
    });
  } catch (e) {
    document.querySelector('#lr-meeting').innerHTML =
      '<p style="color:#94a3b8;padding:24px;text-align:center">Could not load the meeting embed — check your connection and reload.</p>';
  }

  /* hand raise */
  var handBtn = document.getElementById('lr-hand');
  var myHand = false;
  handBtn.addEventListener('click', function () {
    myHand = !myHand;
    handBtn.textContent = myHand ? '✋ Hand raised' : '✋ Raise hand';
    handBtn.className = 'rounded-xl px-3 py-1.5 text-xs font-semibold ' + (myHand
      ? 'border border-amber-400 bg-amber-200 text-amber-900'
      : 'border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100');
    post({ v: 'beat', hand: myHand ? '1' : '0' });
  });

  /* teacher: end the class */
  var endBtn = document.getElementById('lr-end');
  if (endBtn) endBtn.addEventListener('click', function () {
    post({ v: 'end' }).then(function () { location.href = 'course.php?id=' + cid; });
  });

  /* roster poll — heartbeat + who is in the room */
  function refresh() {
    fetch('live_class.php?v=state&course=' + cid, { headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok || !d.live) {
          if (isHost) return;
          document.body.innerHTML = '<div style="max-width:480px;margin:80px auto;text-align:center"><h1 style="font-size:20px;font-weight:700">The live class has ended</h1><p style="color:#64748b;margin-top:8px">Your teacher closed the room.</p><a href="course.php?id=' + cid + '" style="color:#4f46e5;font-weight:600">Back to the course</a></div>';
          if (api) try { api.dispose(); } catch (e) { }
          return;
        }
        var ps = d.participants || [];
        document.getElementById('lr-count').textContent = ps.length + ' in room';
        var ul = document.getElementById('lr-people');
        var anyHand = false;
        ul.innerHTML = ps.map(function (p) {
          anyHand = anyHand || (p.hand && p.id !== me);
          return '<li class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-1.5 text-sm">'
            + '<span class="grid h-6 w-6 place-items-center rounded-full bg-indigo-600 text-[10px] font-bold text-white">' + (p.name || '?').charAt(0).toUpperCase() + '</span>'
            + '<span class="font-medium text-slate-700">' + String(p.name || 'User').replace(/[<>&]/g, '') + '</span>'
            + (p.role === 'teacher' ? '<span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700">Host</span>' : '')
            + (p.hand ? '<span class="ml-auto text-amber-500" title="Hand raised">✋</span>' : '');
        }).join('');
        document.getElementById('lr-hand-note').classList.toggle('hidden', !anyHand);
        /* if the server lost my heartbeat (e.g. laptop slept) re-join automatically */
        if (!d.me_in) post({ v: 'beat', hand: myHand ? '1' : '0' });
      }).catch(function () { });
  }
  refresh();
  setInterval(refresh, 5000);

  window.addEventListener('pagehide', function () {
    if (api) try { api.dispose(); } catch (e) { }
  });
})();
</script>
<?php require __DIR__ . '/footer.php'; ?>