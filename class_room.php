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
/* Nobody sits in a room that is not actually live. Students are told so; the
   host is sent back to the course page to start one. (This also means we never
   read properties off a null active-class row, which used to print PHP warnings
   and "Started 56 years ago" if a teacher opened the room link directly.) */
if (!$active) {
    set_flash('error', $isHost
        ? 'No live class is running — start one from the course page.'
        : 'No live class is running right now.');
    header('Location: course.php?id=' . $courseId);
    exit;
}
/* The one link the teacher copies into Messenger / a group chat / SMS.
   It opens live_join.php, which copes with every state a student arrives in
   (logged out, not yet started) and walks them into the room. */
$joinUrl    = live_class_join_url($courseId);
$inviteText = live_class_invite_text($course);

$roomBase = 'lhclass-' . $courseId . '-' . ((int) $active['id']);
$userName = (string) ($user['name'] ?? 'User');
/* Live-class video is a Jitsi meeting — but WHERE it runs, and whether it is
   embedded, decides how long it may last:
     • meet.jit.si (the free public server) must NOT be embedded any more. In an
       iframe it answers "Embedding meet.jit.si is only meant for demo purposes,
       so this call will disconnect in 5 minutes" and hangs up. lib.php therefore
       opens it in its OWN browser window — that dodges the 5-minute embed cut,
      though a meeting nobody signed in to still ends there at 60 minutes (the
      teacher signing in once lifts that cap).
     • a self-hosted Jitsi server or JaaS (8x8.vc) is embedded right here in the
       page; JaaS additionally receives a signed per-person token, so the teacher
       is the host and nobody has to sign in (Settings → Live class video). */
$jitsiDomain = jitsi_domain();                    /* "8x8.vc" once JaaS is set up */
$jitsiRoom   = jitsi_room_name($roomBase);        /* JaaS prefixes the AppID */
$jitsiMode   = jitsi_embed_mode();                /* 'window' | 'iframe' */
$jitsiDemo   = jitsi_is_demo_domain() && !jitsi_jaas_ready();
$jitsiJwt    = jitsi_jaas_ready() ? jitsi_jaas_jwt('lh-u' . $userId, $userName, $isHost) : '';
$jitsiJoin   = jitsi_room_url($jitsiRoom, $userName, $jitsiJwt);
$page_title = 'Live class — ' . (string) $course['title'];
/* Leaving the room must close the visit: header emits the attendance meta, so
   app.js' pagehide beacon ends the row the moment the student exits (before
   this, live-class rows never closed on exit and the teacher's attendance page
   kept showing them "In course" forever). */
$attendance_course = $courseId;
require __DIR__ . '/header.php';
?>
<script src="<?= e(jitsi_api_script()) ?>" onload="window.lhJitsiLoad='ok'" onerror="window.lhJitsiLoad='failed'"></script>

<a href="course.php?id=<?= $courseId ?>" class="text-sm font-medium text-slate-500 hover:text-indigo-600">← <?= e((string) $course['title']) ?></a>

<div class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <span class="lh-live-dot" aria-hidden="true"><span></span><span></span></span>
      <div>
        <h1 class="text-lg font-bold text-slate-900">🔴 Live class — <?= e((string) $course['title']) ?></h1>
        <p class="text-xs text-slate-500">Started <?= e(time_ago((int) $active['started_at'])) ?> · hosted by <?= e((string) ($course['teacher_name'] ?? 'the teacher')) ?></p>
      </div>
    </div>
    <div class="lh-roombar flex flex-wrap items-center gap-2">
      <span id="lr-count" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">… in room</span>
      <?php if ($jitsiMode === 'iframe'): /* both are Jitsi-API features */ ?>
      <button id="lr-tiles" title="Gallery view — everyone's video at once" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">▦ Gallery</button>
      <button id="lr-fs" title="Full screen" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">⛶ Full screen</button>
      <?php endif; ?>
      <button id="lr-hand" class="lh-chip-amber rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">✋ Raise hand</button>
      <?php if ($isHost): ?>
      <button id="lr-end" data-confirm="End the live class for everyone?" class="rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">End class</button>
      <?php endif; ?>
      <?php if ($isHost): /* copy/paste the join link into Messenger or any chat */ ?>
      <button type="button" data-lh-copy="<?= e($joinUrl) ?>" data-lh-label="🔗 Copy invite link"
              class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">🔗 Copy invite link</button>
      <button type="button" data-lh-share="<?= e($joinUrl) ?>" data-lh-share-title="Live class: <?= e((string) $course['title']) ?>"
              data-lh-share-text="<?= e($inviteText) ?>"
              class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">📤 Share…</button>
      <?php endif; ?>
    </div>
  </div>
  <div id="lr-meeting" class="<?= $jitsiMode === 'window' ? 'lh-launch' : 'lh-stage' ?> mt-3">
  <?php if ($jitsiMode === 'window'): ?>
    <div class="lh-launch-icon" aria-hidden="true">🎥</div>
    <h2>The class video opens in its own window — on a phone, in a new tab</h2>
    <p>
      The free <b>meet.jit.si</b> server no longer allows being embedded — inside a page it warns
      “Embedding meet.jit.si is only meant for demo purposes, so this call will disconnect in 5 minutes”
      and then hangs up. In a window of its own the 5-minute cut is gone, but meet.jit.si still ends a
      meeting opened <b>while nobody is signed in</b> after <b>60 minutes</b> (“Meeting time limit reached”).
      One sign-in by the teacher lifts that cap — see the notes below.
    </p>
    <a id="lr-open" class="lh-launch-go" href="<?= e($jitsiJoin) ?>" target="_blank" rel="noopener">▶ Open the class meeting</a>
    <p class="lh-launch-alt">
      Leave that window (tab on a phone) open for the lesson — the roster, the ✋ raised hands and “End class” stay on this page.
      <button type="button" id="lr-embed-anyway">Embed the video in this page instead (drops after 5 minutes)</button>
    </p>
  <?php endif; ?>
  </div>
  <div class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-[11px] leading-5 text-amber-800 ring-1 ring-amber-100">
  <?php if ($jitsiMode === 'window'): ?>
    🪟 <b>The video is meant to run in its own window.</b> The free <b>meet.jit.si</b> server refuses to be embedded — it warns
    “only meant for demo purposes … disconnect in 5 minutes” and then hangs up.
    (1) A meeting opened while <b>nobody is signed in</b> is cut at <b>60 minutes</b>: meet.jit.si pops up “Meeting time limit
    reached” and ends the call for everyone. That cap is 8x8's policy for anonymous users on their free server — no setting here changes it.
    (2) The <b>teacher</b> lifts it by signing in <b>once</b> (Google / GitHub / Facebook) inside the meeting window; the room then
    belongs to them with <b>no time limit</b>, and students still join with one click — no account needed.
    (3) Open it <b>before</b> announcing the class: the room belongs to whoever creates it first, so a student who arrives
    first waits (“no moderator yet”) until the teacher joins.
    (4) The teacher can drop that waiting room inside the meeting: <b>Security</b> → <b>Enable lobby</b> off.
    (5) Camera and microphone need <b>https://</b> — on plain http the browser blocks them.
    (6) Want the video <b>inside</b> this page with no caps at all? Free <b>Jitsi as a Service</b> (25 users) or your own
    Jitsi server — ⚙️ <b>Settings → Live class video</b>.
  <?php else: ?>
    🎥 <b>In-page video.</b> The meeting is embedded here, and this server allows it — the 5-minute cut only applies to the free <b>meet.jit.si</b> demo server.
    (1) The bottom control bar is <b>pinned</b>: the <b>mic icon</b> is its leftmost button (camera sits next to it). Move the mouse over the video if it still hides.
    (2) Check that YOU and the others are <b>unmuted</b> — a red slash over the mic means muted.
    (3) If the browser blocked the microphone, allow it via the padlock icon → Microphone → Allow, then reload. Camera and mic need <b>https://</b>.
    <?php if (jitsi_jaas_ready()): ?>(4) <b>JaaS is active:</b> the teacher enters as host and nobody has to sign in.<?php endif; ?>
  <?php endif; ?>
  </div>
</div>

<div class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
  <h2 class="text-sm font-bold text-slate-900">In the room <span id="lr-hand-note" class="lh-soft hidden text-amber-600">— ✋ a hand is raised</span></h2>
  <ul id="lr-people" class="mt-2 grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3"></ul>
</div>

<script>
(function () {
  var cid = <?= $courseId ?>;
  var me = <?= $userId ?>;
  var isHost = <?= $isHost ? 'true' : 'false' ?>;
  var csrf = document.querySelector('meta[name="csrf"]').content;

  /* Browsers refuse camera/microphone on plain http:// (localhost excepted).
     A room that silently has no audio/video is the worst case, so say it. */
  if (!window.isSecureContext) {
    var warn = document.createElement('div');
    warn.style.cssText = 'margin-top:12px;padding:10px 14px;border-radius:12px;background:#fff1f2;color:#be123c;'
      + 'font-size:12px;font-weight:600;line-height:1.6;box-shadow:0 0 0 1px #fecdd3';
    warn.innerHTML = '⛔ This page is not on HTTPS, so the browser <b>blocks the camera and the microphone</b>. '
      + 'Open the site with <b>https://</b> (localhost is fine) to be seen and heard.';
    var stageEl = document.getElementById('lr-meeting');
    stageEl.parentNode.insertBefore(warn, stageEl);
  }

  function post(data) {
    var body = new URLSearchParams(Object.assign({ csrf: csrf, course: cid }, data));
    return fetch('live_class.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).then(function (r) { return r.json(); });
  }

  /* ---- the video: two modes ------------------------------------------------
     'iframe' — Jitsi is embedded in this page (self-hosted server, or JaaS).
     'window' — the meeting opens in its OWN browser window, because the free
                public meet.jit.si server refuses to be embedded: it warns
                "Embedding meet.jit.si is only meant for demo purposes, so this
                call will disconnect in 5 minutes" and then hangs the call up.
                In its own window there is no such limit, so a class can run for
                hours. Every other feature on this page (roster, raise hand,
                end class) talks to our own database and works either way. */
  var MODE   = <?= json_encode($jitsiMode) ?>;
  var DEMO   = <?= $jitsiDemo ? 'true' : 'false' ?>;
  var DOMAIN = <?= json_encode($jitsiDomain) ?>;
  var ROOM   = <?= json_encode($jitsiRoom) ?>;
  var JWT    = <?= json_encode($jitsiJwt) ?>;
  var JOIN   = <?= json_encode($jitsiJoin) ?>;
  var API_SRC = <?= json_encode(jitsi_api_script()) ?>;
  var MYNAME = <?= json_encode($userName) ?>;
  var api = null, leftShown = false, embedTried = false, callWin = null, apiBusy = false;

  /* ---- device flags ----------------------------------------------------------
     Phones and tablets get a slightly different flow: a fixed-size pop-up is a
     desktop idea (mobile browsers open a plain new tab), some buttons do not
     exist there (iOS Safari has no element-fullscreen), and a page opened from
     inside Messenger/Facebook/Instagram runs in a WebView where the OS blocks
     the camera and microphone completely. SMALL = narrow screen, TOUCH = the
     primary input is a finger (tablets/phones, not touch-screen laptops). */
  var SMALL = !!(window.matchMedia && matchMedia('(max-width: 640px)').matches);
  var TOUCH = !!(window.matchMedia && matchMedia('(pointer: coarse)').matches);
  var CAN_FS = !!(document.documentElement.requestFullscreen || document.documentElement.webkitRequestFullscreen);
  var IN_APP = (function () {
    var ua = navigator.userAgent || '';
    if (/FBAV|FBIOS|FBAN|Instagram|Line\/|Snapchat|TikTok|musical_ly|Bytedance|Twitter app/i.test(ua)) return true;
    if (/iPhone|iPad|iPod/.test(ua) && !/Safari\//.test(ua)) return true;   /* iOS WKWebView */
    if (/Android/.test(ua) && /; wv\)/.test(ua)) return true;              /* Android WebView */
    return false;
  })();

  /* Opened from inside another app (Messenger, Facebook, a QR scanner…)? Then
     this is a WebView and the OS will not give it the camera or mic — the call
     would start with no video and no sound. Say so up front and offer the way
     out: open the class link in the real browser (copy it if tapping out is
     not possible from the app's view). */
  if (IN_APP) {
    var inapp = document.createElement('div');
    inapp.className = 'lh-inapp';
    inapp.innerHTML = '📱 <b>This page is open inside another app</b> — apps like that block the camera and microphone, so the video class cannot work here. '
      + 'Open the link in Chrome / Safari instead: '
      + '<a href="' + JOIN + '" target="_blank" rel="noopener">open the meeting</a> · '
      + '<button type="button" id="lr-copy">copy the class link</button>';
    var stage0 = document.getElementById('lr-meeting');
    if (stage0 && stage0.parentNode) stage0.parentNode.insertBefore(inapp, stage0);
    var cp = document.getElementById('lr-copy');
    if (cp) cp.addEventListener('click', function () {
      var fin = function () { cp.textContent = 'copied ✓'; setTimeout(function () { cp.textContent = 'copy the class link'; }, 2500); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(JOIN).then(fin, function () { window.prompt('Copy the class link:', JOIN); });
      else window.prompt('Copy the class link:', JOIN);
    });
  }

  /* Joins with mic + camera ON and switches to gallery (tile) view. */
  function apiOptions(host) {
    var o = {
      roomName: ROOM,
      parentNode: host,
      width: '100%',
      height: '100%',
      configOverwrite: {
        startWithAudioMuted: false,
        startWithVideoMuted: false,
        startScreenSharing: false,
        disableBeforeUnloadHandlers: true,
        /* PIN THE CONTROL BAR. Jitsi hides its bottom toolbar a few seconds
           after the mouse stops moving — that is why the mic/camera icons
           seemed to be missing. Current builds read
           config.toolbarConfig.alwaysVisible; older ones read
           interfaceConfig.TOOLBAR_ALWAYS_VISIBLE (set below). */
        toolbarConfig: { alwaysVisible: true },
        /* keep the "Join meeting" pre-join screen on purpose: it shows the
           mic / camera state and a device picker BEFORE entering, so nobody
           lands in class muted without knowing why. Set enabled:false to skip. */
        prejoinPageEnabled: true,
        prejoinConfig: { enabled: true },
        /* don't bounce phones out to the Jitsi app / store page */
        disableDeepLinking: true,
      },
      interfaceConfigOverwrite: {
        /* documented range for this option is 1..5 (12 was simply ignored) */
        TILE_VIEW_MAX_COLUMNS: 5,
        TOOLBAR_ALWAYS_VISIBLE: true,
        FILM_STRIP_MAX_HEIGHT: 140,
        SHOW_JITSI_WATERMARK: false,
        SHOW_POWERED_BY: false,
        SHOW_WATERMARK_FOR_GUESTS: false,
      },
    };
    /* JaaS already carries the identity (and the moderator flag) inside the
       signed token; the public/self-hosted servers take it from userInfo. */
    if (JWT) o.jwt = JWT; else o.userInfo = { displayName: MYNAME };
    return o;
  }

  /* The call dropped — the 5-minute demo cut, a network glitch, or the visitor
     pressed Jitsi's own hang-up button. Offer a way straight back in. */
  function onLeft() {
    if (leftShown) return;
    leftShown = true;
    setTimeout(showDropped, 1200);   /* the "class ended" redirect wins if it did */
  }

  /* The external_api.js may not be ready when the embed is wanted (the static
     tag was blocked by an extension, the network blipped, or the tag simply
     has not finished downloading). ensureApi() returns straight away when the
     constructor exists, otherwise it injects the script and RETRIES up to
     three times with a pause between tries — a momentary DNS blip from the
     Wi-Fi router (net::ERR_NAME_NOT_RESOLVED) usually recovers in seconds,
     and the embed then comes up on its own instead of showing an error. */
  function ensureApi(done) {
    if (typeof JitsiMeetExternalAPI !== 'undefined') { done(true); return; }
    if (document.getElementById('lh-jitsi-api')) { pendingApi.push(done); return; }
    pendingApi.push(done);
    tryLoadApi(1);
  }
  var pendingApi = [];
  function tryLoadApi(attempt) {
    var s = document.createElement('script');
    s.id = 'lh-jitsi-api';
    /* the retry suffix also busts any cached failed response */
    s.src = API_SRC + (attempt > 1 ? '?lhretry=' + attempt : '');
    var settled = false;
    var timer = setTimeout(function () { if (!settled) { settled = true; loadFailed(attempt); } }, 10000);
    s.onload = function () { if (settled) return; settled = true; clearTimeout(timer); finishApi(true); };
    s.onerror = function () { if (settled) return; settled = true; clearTimeout(timer); loadFailed(attempt); };
    document.head.appendChild(s);
  }
  function loadFailed(attempt) {
    /* a failed fetch must not stay in the DOM, or a retry would only wait */
    var dead = document.getElementById('lh-jitsi-api');
    if (dead && dead.parentNode) dead.parentNode.removeChild(dead);
    if (attempt < 3) {
      embedStatus('The meeting server didn\'t answer for a moment — retrying (' + attempt + ' of 3)…');
      setTimeout(function () { tryLoadApi(attempt + 1); }, 1500);
      return;
    }
    finishApi(false);
  }
  function finishApi(ok) {
    if (ok) window.lhJitsiLoad = 'ok'; else window.lhJitsiLoad = 'failed';
    var waiters = pendingApi; pendingApi = [];
    waiters.forEach(function (cb) { try { cb(ok); } catch (e) { } });
  }
  /* progress text inside the embed area (harmless no-op when it is gone) */
  function embedStatus(msg) {
    var f = document.getElementById('lr-frame');
    if (f) f.innerHTML = '<p style="color:#94a3b8;padding:24px;text-align:center">' + msg + '</p>';
  }

  function mountEmbed() {
    embedTried = true;
    apiBusy = true;
    var box = document.getElementById('lr-meeting');
    if (!box) return;
    box.className = 'lh-stage mt-3';
    box.innerHTML = '<div id="lr-frame" style="width:100%;height:100%"></div>';
    var host = document.getElementById('lr-frame');
    /* Ask for the API first — with internet it is usually already there. */
    embedStatus('Connecting to the meeting…');
    ensureApi(function (ready) {
      if (!document.getElementById('lr-frame')) { apiBusy = false; return; }   /* view changed meanwhile */
      if (!ready) { apiBusy = false; showEmbedError(); return; }
      try {
        api = new JitsiMeetExternalAPI(DOMAIN, apiOptions(document.getElementById('lr-frame')));
      } catch (e) {
        apiBusy = false;
        showEmbedError();
        return;
      }
      api.addEventListener('videoConferenceJoined', goGallery);
      api.addEventListener('participantJoined', goGallery);
      api.addEventListener('videoConferenceLeft', onLeft);
      api.addEventListener('readyToClose', onLeft);
      apiBusy = false;
    });
  }

  function showEmbedError() {
    var host = document.getElementById('lr-frame');
    if (!host) return;
    /* Two very different causes, so say which one it is: the video server's
       script never arrived (connection dropped for a moment, or an ad-blocker
       / privacy extension is blocking the domain) — vs. the script is there
       but the embed could not be created this once. Either way the class can
       run in its own window, so put that link up front and offer a retry. */
    var blocked = window.lhJitsiLoad === 'failed' || typeof JitsiMeetExternalAPI === 'undefined';
    var why = blocked
      ? '<b>The meeting script didn\'t load.</b> The connection to ' + DOMAIN + ' dropped for a moment — or a browser extension (ad-blocker) is blocking it. '
      : '<b>The meeting embed could not start.</b> ';
    host.innerHTML = '<p style="color:#94a3b8;padding:24px;text-align:center;line-height:1.7">' + why
      + 'The class itself keeps working — <a href="' + JOIN + '" target="_blank" rel="noopener">open the meeting in its own window</a> (always available), or '
      + '<a href="#" id="lr-embed-retry">try the embed again</a> / reload the page.</p>';
    var retryBtn = document.getElementById('lr-embed-retry');
    if (retryBtn) retryBtn.addEventListener('click', function (ev3) {
      ev3.preventDefault();
      if (api) { try { api.dispose(); } catch (err) { } api = null; }
      mountEmbed();
    });
  }

  /* gallery view: switch on join and keep it for every newcomer */
  function goGallery() { try { api.executeCommand('setTileView', true); } catch (e) { } }

  if (MODE === 'iframe') mountEmbed();

  /* ---- "own window" mode -------------------------------------------------- */
  function openMeetingWindow(ev) {
    if (callWin && !callWin.closed) {
      /* already running: just bring it forward — never reload it (that would
         drop everyone out of the call) */
      callWin.focus();
      if (ev) ev.preventDefault();
      return;
    }
    /* phones/tablets: fixed-size pop-ups are a desktop idea — their browsers
       just open a new tab, so let the plain link do that natively */
    if (SMALL || TOUCH) return;
    var features = 'width=1440,height=920,menubar=no,toolbar=no,location=yes,status=no,resizable=yes,scrollbars=yes';
    var w = window.open(JOIN, 'lhclass_' + cid, features);
    if (w) { callWin = w; if (ev) ev.preventDefault(); }
    /* pop-up blocked (w === null)? the plain link opens a normal tab by itself */
  }

  var openLink = document.getElementById('lr-open');
  if (openLink) openLink.addEventListener('click', openMeetingWindow);

  /* The classroom page cannot look inside the meeting window (it is another
     origin), but it CAN see when the window goes away — which is exactly what
     happens when meet.jit.si cuts an unsigned meeting at 60 minutes and the
     user closes the ended tab. Put the way back in up front, so a long class
     is one click ("reopen — fresh hour") instead of a dead page. */
  var callWinClosed = false;
  setInterval(function () {
    if (!callWin || callWinClosed === !!callWin.closed) return;
    callWinClosed = !!callWin.closed;
    if (!callWinClosed) return;
    var box = document.getElementById('lr-meeting');
    if (!box || document.getElementById('lr-reopen-banner')) return;
    var b = document.createElement('div');
    b.id = 'lr-reopen-banner';
    b.className = 'lh-drop';
    b.innerHTML = '<b>The meeting window closed.</b> '
      + (DEMO ? 'If meet.jit.si cut it at the 60-minute limit, reopening starts a <b>fresh hour</b> — and the teacher signing in once (Google / GitHub / Facebook) removes the cap entirely. ' : '')
      + '<a href="#" id="lr-reopen">reopen the meeting</a>';
    box.parentNode.insertBefore(b, box.nextSibling);
    document.getElementById('lr-reopen').addEventListener('click', function (e) {
      e.preventDefault();
      var rb = document.getElementById('lr-reopen-banner');
      if (rb && rb.parentNode) rb.parentNode.removeChild(rb);
      callWinClosed = false;
      openMeetingWindow(e);
    });
  }, 1500);

  var embedAnyway = document.getElementById('lr-embed-anyway');
  if (embedAnyway) embedAnyway.addEventListener('click', function () {
    if (!api && !apiBusy) mountEmbed();   /* a re-click after a failure retries */
  });

  /* Buttons that cannot work on this device are not shown at all … */
  if (!CAN_FS) { var fsBtn = document.getElementById('lr-fs'); if (fsBtn) fsBtn.style.display = 'none'; }
  if (SMALL) { var eaBtn = document.getElementById('lr-embed-anyway'); if (eaBtn) eaBtn.style.display = 'none'; }
  /* … and on a phone the in-page embed is cramped and shaky (iOS Safari cannot
     hand a cross-origin iframe the camera), so offer the full page instead. */
  if (SMALL && MODE === 'iframe') {
    var esc = document.createElement('p');
    esc.className = 'lh-inapp';
    esc.innerHTML = '📱 <b>On a phone the full page works best:</b> <a href="' + JOIN + '" target="_blank" rel="noopener">open the meeting full-page</a>';
    var st = document.getElementById('lr-meeting');
    if (st && st.parentNode) st.parentNode.insertBefore(esc, st);
  }

  /* Shown when an embedded call drops. The 5-minute cut is a property of the
     free meet.jit.si server, so say exactly that instead of "something broke". */
  function showDropped() {
    var box = document.getElementById('lr-meeting');
    if (!box || document.getElementById('lr-dropped')) return;
    var d = document.createElement('div');
    d.id = 'lr-dropped';
    d.className = 'lh-drop';
    d.innerHTML = (DEMO
        ? '<b>The call was cut off after 5 minutes.</b> The free meet.jit.si server only tolerates embedded calls as a demo and disconnects them after five minutes. '
          + 'The classroom itself keeps working — open the meeting in <a href="' + JOIN + '" target="_blank" rel="noopener">its own window</a> (a fresh hour there; the teacher signing in lifts the cap) or <a href="#" id="lr-dropped-rejoin">rejoin here</a>'
        : '<b>The video call ended in this page.</b> '
          + 'The classroom itself keeps working — move the video to <a href="' + JOIN + '" target="_blank" rel="noopener">its own window</a> (no time limit there) or <a href="#" id="lr-dropped-rejoin">rejoin here</a>');
    box.parentNode.insertBefore(d, box.nextSibling);
    var rejoin = document.getElementById('lr-dropped-rejoin');
    if (rejoin) rejoin.addEventListener('click', function (ev2) {
      ev2.preventDefault();
      var dd = document.getElementById('lr-dropped');
      if (dd && dd.parentNode) dd.parentNode.removeChild(dd);
      if (api) { try { api.dispose(); } catch (e) { } api = null; }
      leftShown = false;
      mountEmbed();
    });
  }

  /* big screen: gallery button + full screen */
  var tilesBtn = document.getElementById('lr-tiles');
  if (tilesBtn) tilesBtn.addEventListener('click', goGallery);
  var fsBtn = document.getElementById('lr-fs');
  if (fsBtn) fsBtn.addEventListener('click', function () {
    try { api.executeCommand('toggleFullScreen'); } catch (e) { }
  });

  /* hand raise */
  var handBtn = document.getElementById('lr-hand');
  var myHand = false;
  handBtn.addEventListener('click', function () {
    myHand = !myHand;
    handBtn.textContent = myHand ? '✋ Hand raised' : '✋ Raise hand';
    handBtn.className = 'lh-chip-amber rounded-xl px-3 py-1.5 text-xs font-semibold ' + (myHand
      ? 'border border-amber-400 bg-amber-200 text-amber-900'
      : 'border border-amber-200 bg-amber-50 text-amber-700');
    post({ v: 'beat', hand: myHand ? '1' : '0' });
  });

  /* teacher: end the class */
  var endBtn = document.getElementById('lr-end');
  if (endBtn) endBtn.addEventListener('click', function () {
    /* close the meeting window we opened, so nobody is left talking to an empty
       room once the class is over */
    if (callWin && !callWin.closed) { try { callWin.close(); } catch (e) { } }
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
            + '<span class="lh-avatar">' + (p.name || '?').charAt(0).toUpperCase() + '</span>'
            + '<span class="font-medium text-slate-700">' + String(p.name || 'User').replace(/[<>&]/g, '') + '</span>'
            + (p.role === 'teacher' ? '<span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700">Host</span>' : '')
            + (p.hand ? '<span class="lh-hand" title="Hand raised">✋</span>' : '');
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