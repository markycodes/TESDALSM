/* LearnHub LMS — small vanilla JS helpers (no frameworks) */

/* ---------- Toasts ---------- */
function showToast(message, type) {
  const ok = type !== 'error';
  const el = document.createElement('div');
  el.className = 'toast-in fixed right-4 top-20 z-50 flex max-w-sm items-start gap-3 rounded-xl border p-4 shadow-lg ' +
    (ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800');
  el.innerHTML = '<span>' + (ok ? '✅' : '⚠️') + '</span><p class="text-sm font-medium"></p>';
  el.querySelector('p').textContent = message;
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

document.addEventListener('click', (e) => {
  if (e.target.closest('[data-toast-close]')) {
    const t = e.target.closest('[data-toast]');
    if (t) t.remove();
  }
});
document.querySelectorAll('[data-toast]').forEach((t) => setTimeout(() => t.remove(), 4500));

/* ---------- Global scroll reveal (applies the animation across every page) ---------- */
(function () {
  // Auto-tag content without an explicit .reveal class so all pages animate on scroll.
  document.querySelectorAll(
    'body > section:not(.reveal), body > article:not(.reveal), ' +
    'body > main > section:not(.reveal), main .grid > *:not(.reveal), section .grid > *:not(.reveal)'
  ).forEach((el) => el.classList.add('reveal'));
})();

/* ---------- Scroll reveal ---------- */
(function () {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;
  if (!('IntersectionObserver' in window)) {
    els.forEach((el) => el.classList.add('in'));
    return;
  }
  let seq = 0;
  const io = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      el.style.transitionDelay = Math.min((seq % 6) * 70, 420) + 'ms';
      seq += 1;
      el.classList.add('in');
      io.unobserve(el);
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
  els.forEach((el) => io.observe(el));
})();

/* ---------- Modals ---------- */
function openModal(modal) {
  if (!modal) return;
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.body.classList.add('overflow-hidden');
}
function closeModal(el) {
  const backdrop = el && el.classList && el.classList.contains('modal-backdrop') ? el : (el ? el.closest('.modal-backdrop') : null);
  if (backdrop) { backdrop.classList.add('hidden'); backdrop.classList.remove('flex'); }
  document.body.classList.remove('overflow-hidden');
}
document.addEventListener('click', (e) => {
  const opener = e.target.closest('[data-modal-open]');
  if (opener) {
    e.preventDefault();
    openModal(document.getElementById(opener.getAttribute('data-modal-open')));
    return;
  }
  const closer = e.target.closest('[data-modal-close]');
  if (closer) { e.preventDefault(); closeModal(closer); return; }
  if (e.target.matches('.modal-backdrop')) closeModal(e.target);
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-backdrop').forEach((m) => closeModal(m));
});

/* ---------- Tabs (lesson modal, videos/materials) ---------- */
function findTabButton(target) {
  // Fast path: target is an Element
  if (target && typeof target.closest === 'function') {
    const b = target.closest('[data-tab-btn]');
    if (b) return b;
  }
  // Fallback: walk up the DOM tree (handles text-node targets inside buttons)
  let el = target;
  while (el && el !== document && el !== document.body) {
    if (el.nodeType === 1 && el.hasAttribute && el.hasAttribute('data-tab-btn')) return el;
    el = el.parentNode;
  }
  return null;
}
function switchLessonTab(btn) {
  // NOTE: panes are SIBLINGS of the [data-tabs] bar (not children of it),
  // so scope the lookup to the modal/root — never to the [data-tabs] bar itself.
  const root = btn.closest('#lesson-modal') || btn.closest('.modal-backdrop') || document;
  const key = btn.getAttribute('data-tab-btn');
  root.querySelectorAll('[data-tab-btn]').forEach((b) => {
    b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
  });
  root.querySelectorAll('[data-tab-pane]').forEach((p) => {
    p.classList.toggle('hidden', p.getAttribute('data-tab-pane') !== key);
  });
}
document.addEventListener('click', (e) => {
  const btn = findTabButton(e.target);
  if (!btn) return;
  try {
    switchLessonTab(btn);
  } catch (err) {
    // Visible error so it's not a silent failure
    const msg = document.getElementById('tab-error');
    if (msg) { msg.textContent = 'Tab error: ' + err.message; msg.classList.remove('hidden'); }
    console.error('[TAB] error:', err);
  }
});

/* ---------- Multi-file video input: live "N selected" hint ---------- */
const videoFilesInput = document.getElementById('video-files');
if (videoFilesInput) {
  const videoNote = document.getElementById('video-files-note');
  videoFilesInput.addEventListener('change', () => {
    const n = (videoFilesInput.files || []).length;
    if (videoNote && n) videoNote.textContent = n + ' video file(s) selected — each becomes its own lesson.';
  });
}

/* ---------- Confirm dialogs ---------- */
document.addEventListener('submit', (e) => {
  const f = e.target;
  if (f.matches('[data-confirm]') && !window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
});

/* ---------- Course search + category filter ---------- */
let activeCat = 'all';
function applyCourseFilters() {
  const input = document.getElementById('course-search');
  const q = ((input && input.value) || '').toLowerCase().trim();
  let shown = 0;
  document.querySelectorAll('[data-course-card]').forEach((card) => {
    const okQ = !q || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
    const okC = activeCat === 'all' || card.getAttribute('data-cat') === activeCat;
    const show = okQ && okC;
    card.classList.toggle('hidden', !show);
    if (show) shown++;
  });
  const empty = document.getElementById('courses-empty');
  if (empty) empty.classList.toggle('hidden', shown > 0);
}
const searchInput = document.getElementById('course-search');
if (searchInput) searchInput.addEventListener('input', applyCourseFilters);
document.querySelectorAll('[data-cat-btn]').forEach((btn) => {
  btn.addEventListener('click', () => {
    activeCat = btn.getAttribute('data-cat-btn');
    document.querySelectorAll('[data-cat-btn]').forEach((b) => {
      const on = b === btn;
      b.classList.toggle('bg-indigo-600', on);
      b.classList.toggle('text-white', on);
      b.classList.toggle('bg-white', !on);
      b.classList.toggle('text-slate-600', !on);
    });
    applyCourseFilters();
  });
});

/* ---------- automatic progress: watch time (videos) + course bar ---------- */
function csrfToken() {
  const el = document.querySelector('meta[name="csrf"]');
  return el ? el.content : '';
}
function applyCourseProgress(courseId, data) {
  if (!data || typeof data.pct !== 'number') return;
  document.querySelectorAll('[data-progress-for="' + courseId + '"]').forEach((el) => {
    const role = el.getAttribute('data-role');
    if (role === 'bar') el.style.width = data.pct + '%';
    if (role === 'text') el.textContent = data.done + ' of ' + data.total + ' lessons completed';
    if (role === 'pct') el.textContent = data.pct + '%';
  });
}
function setLessonState(materialId, text, done) {
  document.querySelectorAll('[data-lesson-state="' + materialId + '"]').forEach((el) => {
    el.textContent = text;
    el.classList.toggle('bg-emerald-100', !!done);
    el.classList.toggle('text-emerald-700', !!done);
    el.classList.toggle('bg-slate-100', !done);
    el.classList.toggle('text-slate-600', !done);
  });
}
async function sendWatch(courseId, materialId, watched, duration, position) {
  const body = new URLSearchParams({
    csrf: csrfToken(), course: courseId, material: materialId,
    watched: String(watched), duration: String(duration), position: String(position),
  });
  const res = await fetch('watch.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
    body: body.toString(),
  });
  const data = await res.json();
  if (!data.ok) return null;
  applyCourseProgress(courseId, data);
  const pct = data.percent || 0;
  setLessonState(materialId, data.complete ? '✓ Completed' : (pct > 0 ? '▶ ' + pct + '% watched' : 'Not started'), !!data.complete);
  if (data.complete) showToast('Video lesson completed 🎉');
  return data;
}
function trackUploadedVideo(video) {
  const courseId = video.getAttribute('data-course') || '';
  const materialId = video.getAttribute('data-material') || '';
  let watched = parseFloat(video.getAttribute('data-watched') || '0');
  const resume = parseFloat(video.getAttribute('data-position') || '0');
  let last = -1;
  let duration = 0;
  let lastSent = Math.floor(watched);
  const done = video.getAttribute('data-done') === '1';
  if (resume > 3) video.addEventListener('loadedmetadata', () => {
    try { if (resume < (video.duration || Infinity) - 3) video.currentTime = resume; } catch (e) { }
  }, { once: true });
  video.addEventListener('timeupdate', () => {
    const t = video.currentTime || 0;
    if (last >= 0) { const d = t - last; if (d > 0 && d <= 1.5) watched += d; }
    last = t;
    if (video.duration) duration = video.duration;
    if (done) return;
    if (Math.floor(watched) - lastSent >= 5) {
      lastSent = Math.floor(watched);
      sendWatch(courseId, materialId, Math.round(watched), Math.round(duration), Math.round(t));
    }
  });
  ['pause', 'ended'].forEach((ev) => video.addEventListener(ev, () => {
    if (done) return;
    sendWatch(courseId, materialId, Math.round(watched), Math.round(duration), Math.round(video.currentTime || 0));
  }));
  window.addEventListener('pagehide', () => {
    if (done) return;
    navigator.sendBeacon('watch.php', new URLSearchParams({
      csrf: csrfToken(), course: courseId, material: materialId,
      watched: String(Math.round(watched)), duration: String(Math.round(duration)),
      position: String(Math.round(video.currentTime || 0)),
    }));
  });
  /* --- never auto-resume: bfcache restores play AFTER pageshow, so suppress + catch the play event --- */
  let suppressReplay = false;
  window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    suppressReplay = true;
    try { video.pause(); } catch (err) { }
    setTimeout(() => {
      suppressReplay = false;
      try { if (!video.paused) video.pause(); } catch (err) { }
    }, 800);
  });
  video.addEventListener('play', () => {
    if (suppressReplay) { try { video.pause(); } catch (err) { } }
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden' && !video.paused) { try { video.pause(); } catch (err) { } }
    else {
      setTimeout(() => { try { if (!video.paused) video.pause(); } catch (err) { } }, 120);
    }
  });
}
function trackYouTube(el) {
  const courseId = el.getAttribute('data-course') || '';
  const materialId = el.getAttribute('data-material') || '';
  let watched = parseFloat(el.getAttribute('data-watched') || '0');
  const resume = parseFloat(el.getAttribute('data-position') || '0');
  let duration = 0;
  let lastSent = Math.floor(watched);
  let lastTime = -1;
  let player = null;
  function ytReady() {
    if (window.YT && window.YT.Player) return Promise.resolve();
    if (!trackYouTube.p) {
      trackYouTube.p = new Promise((resolve) => {
        const prev = window.onYouTubeIframeAPIReady;
        window.onYouTubeIframeAPIReady = () => { if (prev) prev(); resolve(); };
        const s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(s);
      });
    }
    return trackYouTube.p;
  }
  function tick() {
    if (!player) return;
    let state = -1;
    try { state = player.getPlayerState(); } catch (e) { return; }
    if (state !== 1) return;
    const t = player.getCurrentTime() || 0;
    if (lastTime >= 0) { const d = t - lastTime; if (d > 0 && d <= 2) watched += d; }
    lastTime = t;
    try { duration = player.getDuration() || duration; } catch (e) { }
    if (Math.floor(watched) - lastSent >= 5) {
      lastSent = Math.floor(watched);
      sendWatch(courseId, materialId, Math.round(watched), Math.round(duration), Math.round(t));
    }
  }
  ytReady().then(() => {
    if (!el.isConnected) return;
    el.setAttribute('data-api', '1');
    player = new YT.Player(el, {
      width: '100%', height: '100%',
      videoId: el.getAttribute('data-yt'),
      playerVars: { rel: 0, modestbranding: 1 },
      events: {
        onReady: (e) => {
          try {
            duration = e.target.getDuration() || 0;
            if (resume > 3 && resume < duration - 3) e.target.seekTo(resume, true);
          } catch (err) { }
          if (e.target.getIframe) e.target.getIframe().classList.add('h-full', 'w-full');
          setInterval(tick, 1000);
        },
        onStateChange: (e) => {
          if (e.data === YT.PlayerState.ENDED || e.data === YT.PlayerState.PAUSED) {
            const t = player.getCurrentTime ? player.getCurrentTime() : 0;
            sendWatch(courseId, materialId, Math.round(watched), Math.round(duration), Math.round(t || 0));
          }
        },
      },
    });
  });
  setTimeout(() => {
    if (!el.getAttribute('data-api')) {
      el.innerHTML = '<iframe class="h-full w-full" src="https://www.youtube-nocookie.com/embed/' +
        encodeURIComponent(el.getAttribute('data-yt') || '') +
        '" title="video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
    }
  }, 6000);
  /* --- never auto-resume: pause when tab hidden, restored from cache, or re-plays on its own --- */
  let hiddenSince = 0;
  function pausePlayer() {
    if (!player) return;
    try { if (player.getPlayerState() === 1) player.pauseVideo(); } catch (e) { }
  }
  window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    const t0 = performance.now();
    const iv = setInterval(() => {
      pausePlayer();
      if (performance.now() - t0 > 800) clearInterval(iv);
    }, 200);
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      hiddenSince = performance.now();
      pausePlayer();
      const iv = setInterval(() => {
        pausePlayer();
        if (performance.now() - hiddenSince > 800) clearInterval(iv);
      }, 200);
    } else {
      setTimeout(pausePlayer, 150);
    }
  });
}
document.querySelectorAll('video[data-watch]').forEach(trackUploadedVideo);
document.querySelectorAll('[data-yt]').forEach(trackYouTube);
/* Vimeo: no in-page tracking API — one-click watch confirmation */
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.js-vimeo-done');
  if (!btn) return;
  e.preventDefault();
  btn.disabled = true;
  const data = await sendWatch(btn.getAttribute('data-course') || '', btn.getAttribute('data-material') || '', 1, 1, 0);
  if (data && data.complete) btn.remove();
});
/* ---------- presence heartbeat (keep "online") ---------- */
function heartbeat() {
  fetch('ping.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
    body: 'csrf=' + encodeURIComponent(csrfToken()),
  }).catch(() => { });
}
if (document.body.hasAttribute('data-heartbeat')) {
  setInterval(heartbeat, 55000);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') heartbeat();
  });
}

/* ---------- attendance: close the visit when leaving the course page ---------- */
const attendanceMeta = document.querySelector('meta[name="attendance-course"]');
if (attendanceMeta) {
  const closeVisit = () => {
    if (!navigator.sendBeacon) return;
    navigator.sendBeacon('attendance.php', new URLSearchParams({
      csrf: csrfToken(), course: attendanceMeta.getAttribute('content') || '',
    }));
  };
  window.addEventListener('pagehide', closeVisit);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') closeVisit();
  });
}

/* ---------- attendance day page: live durations for open sessions ---------- */
document.querySelectorAll('[data-open-seconds]').forEach((el) => {
  let s = parseInt(el.getAttribute('data-open-seconds') || '0', 10);
  const fmt = (v) => {
    const h = Math.floor(v / 3600);
    const m = Math.floor((v % 3600) / 60);
    const ss = v % 60;
    return h > 0 ? h + 'h ' + String(m).padStart(2, '0') + 'm' : m + 'm ' + String(ss).padStart(2, '0') + 's';
  };
  const render = () => { el.textContent = fmt(s); };
  render();
  setInterval(() => { s++; render(); }, 1000);
});

/* ---------- enrollments page: live online status + auto-submitting filters ---------- */
const presenceMeta = document.querySelector('meta[name="presence-ids"]');
if (presenceMeta) {
  const ids = presenceMeta.getAttribute('content').split(',').map((s) => s.trim()).filter(Boolean);
  async function refreshPresence() {
    if (!ids.length) return;
    try {
      const res = await fetch('presence.php?ids=' + ids.join(','), { headers: { 'X-Requested-With': 'fetch' } });
      const data = await res.json();
      const onlineSet = new Set((data.online || []).map(String));
      ids.forEach((id) => {
        const el = document.querySelector('[data-presence="' + id + '"]');
        if (!el) return;
        const on = onlineSet.has(id);
        el.textContent = on ? '● Online' : '◌ Offline';
        el.classList.toggle('bg-emerald-100', on);
        el.classList.toggle('text-emerald-700', on);
        el.classList.toggle('bg-slate-100', !on);
        el.classList.toggle('text-slate-500', !on);
      });
    } catch (e) { /* ignore */ }
  }
  refreshPresence();
  setInterval(refreshPresence, 60000);
}

const enrollFilter = document.getElementById('enroll-filter');
if (enrollFilter) {
  const cat = enrollFilter.elements.category;
  const course = enrollFilter.elements.course;
  if (cat) {
    cat.addEventListener('change', () => {
      if (course) course.value = '';
      enrollFilter.submit();
    });
  }
  if (course) course.addEventListener('change', () => enrollFilter.submit());
}

/* ---------- attendance day page: auto-submit filters on change ---------- */
const dayFilter = document.getElementById('day-filter');
if (dayFilter) {
  const fDate = dayFilter.elements.date;
  const fCat = dayFilter.elements.category;
  const fCourse = dayFilter.elements.course;
  if (fDate) fDate.addEventListener('change', () => dayFilter.submit());
  if (fCat) fCat.addEventListener('change', () => dayFilter.submit());
  if (fCourse) fCourse.addEventListener('change', () => dayFilter.submit());
}

/* ---------- Mobile swipe hint for stat rows (swipe right to reveal more stats) ---------- */
(function () {
  document.querySelectorAll('.swipe-hint').forEach((wrap) => {
    const row = wrap.querySelector('.overflow-x-auto');
    if (!row) return;
    const dismiss = () => wrap.classList.add('hint-off');
    const arm = () => {
      row.addEventListener('scroll', dismiss, { once: true, passive: true });
      row.addEventListener('touchstart', dismiss, { once: true, passive: true });
    };
    const build = () => {
      if (window.innerWidth >= 640) return;                 // desktop grid: no hint
      if (row.scrollWidth <= row.clientWidth + 8) return;   // nothing to swipe
      if (wrap.querySelector('.swipe-chevron')) return;
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'swipe-chevron';
      b.setAttribute('aria-label', 'Swipe to see more stats');
      b.textContent = '›';
      b.addEventListener('click', () => row.scrollBy({ left: row.clientWidth * 0.75, behavior: 'smooth' }));
      wrap.appendChild(b);
      arm();
    };
    build();
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 640) { dismiss(); return; }
      wrap.classList.remove('hint-off');
      build();
    });
  });
})();

/* ================================================================
   Redesign shell: sidebar toggle + notifications + live chat
   ================================================================ */
(function () {
  var body = document.body;
  var toggle = document.getElementById('lh-side-toggle');
  var sidebar = document.getElementById('lh-sidebar');
  var backdrop = document.getElementById('lh-side-backdrop');
  function closeSide() { if (sidebar) sidebar.classList.remove('open'); body.classList.remove('lh-side-open'); }
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      body.classList.toggle('lh-side-open');
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeSide);
  window.addEventListener('resize', function () { if (window.innerWidth >= 1024) closeSide(); });

  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  function esc(s) { return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ago(ts) {
    var s = Math.max(0, Math.floor(Date.now() / 1000) - (ts || 0));
    if (s < 60) return 'just now';
    if (s < 3600) return Math.floor(s / 60) + 'm ago';
    if (s < 86400) return Math.floor(s / 3600) + 'h ago';
    return Math.floor(s / 86400) + 'd ago';
  }
  function setBadge(id, n) {
    var b = document.getElementById(id);
    if (!b) return;
    if (n > 0) { b.textContent = n > 99 ? '99+' : String(n); b.classList.remove('hidden'); }
    else b.classList.add('hidden');
  }

  /* -------- notifications: poll + dropdown -------- */
  var notifBtn = document.getElementById('lh-notif-btn');
  var panel = document.getElementById('lh-notif-panel');
  var list = document.getElementById('lh-notif-list');
  if (notifBtn && panel) {
    notifBtn.addEventListener('click', function (e) {
      e.preventDefault();
      panel.classList.toggle('hidden');
      e.stopPropagation();
    });
    document.addEventListener('click', function (e) {
      if (!panel.classList.contains('hidden') && !panel.contains(e.target) && e.target !== notifBtn) panel.classList.add('hidden');
    });
  }
  var readAllBtn = document.getElementById('lh-notif-read-all');
  if (readAllBtn) readAllBtn.addEventListener('click', function () {
    fetch('mark_notifications_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      body: 'csrf=' + encodeURIComponent(csrf),
    }).then(function (r) { return r.json(); }).then(function () { refreshNotifs(); }).catch(function () {});
  });
  function refreshNotifs() {
    fetch('realtime.php?v=notifications&t=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) return;
        setBadge('lh-notif-badge', d.unread || 0);
        setBadge('lh-chat-badge', d.chat_unread || 0);
        setBadge('lh-chat-badge-side', d.chat_unread || 0);
        if (!list) return;
        if (!(d.items || []).length) {
          list.innerHTML = '<p class="px-3.5 py-6 text-center text-xs text-slate-400">No notifications yet.<br>New lessons, quizzes, results and messages will show up here.</p>';
          return;
        }
        list.innerHTML = (d.items || []).map(function (n) {
          return '<a href="' + esc(n.link) + '" data-notif-id="' + n.id + '" class="lx-panel-item lh-notif-item ' + (n.is_read ? '' : 'lh-notif-unread') + '">'
            + '<span class="text-[13px] font-semibold text-slate-800">' + esc(n.title) + '</span>'
            + (n.body ? '<span class="text-xs text-slate-500">' + esc(n.body) + '</span>' : '')
            + '<span class="text-[10px] text-slate-400">' + esc(ago(n.created_at)) + '</span></a>';
        }).join('');
      }).catch(function () {});
  }
  document.addEventListener('click', function (e) {
    var item = e.target.closest('[data-notif-id]');
    if (!item) return;
    e.preventDefault();
    var link = item.getAttribute('data-notif-link') || item.getAttribute('href') || 'dashboard.php';
    fetch('mark_notifications_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      body: 'csrf=' + encodeURIComponent(csrf) + '&id=' + encodeURIComponent(item.getAttribute('data-notif-id')),
    }).catch(function () {}).finally(function () { window.location.href = link; });
  });
  if (document.body.classList.contains('lh-app')) {
    refreshNotifs();
    setInterval(refreshNotifs, 8000);
  }
  /* -------- private chat: live append + AJAX send -------- */
  var chatBox = document.getElementById('chat-box');
  var chatForm = document.getElementById('chat-form');
  var chatInput = document.getElementById('chat-input');
  var lastId = 0;
  function appendMsg(m) {
    var mine = String(m.sender_id) === String((chatBox && chatBox.getAttribute('data-me')) || '');
    var wrap = document.createElement('div');
    wrap.className = 'chat-msg flex ' + (mine ? 'justify-end' : 'justify-start');
    wrap.setAttribute('data-msg', m.id);
    var d = new Date(m.created_at * 1000);
    var ts = d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ', ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    wrap.innerHTML = '<div class="max-w-[78%] rounded-2xl px-3.5 py-2 text-sm leading-6 shadow-sm '
      + (mine ? 'rounded-br-md bg-emerald-600 text-white' : 'rounded-bl-md bg-slate-100 text-slate-700') + '">'
      + '<p class="whitespace-pre-wrap break-words">' + esc(m.body) + '</p>'
      + '<p class="mt-1 text-right text-[10px] ' + (mine ? 'text-emerald-100/90' : 'text-slate-400') + '">' + esc(ts) + '</p></div>';
    chatBox.appendChild(wrap);
    chatBox.scrollTop = chatBox.scrollHeight;
  }
  if (chatBox) {
    chatBox.querySelectorAll('[data-msg]').forEach(function (el) {
      lastId = Math.max(lastId, parseInt(el.getAttribute('data-msg'), 10) || 0);
    });
    chatBox.scrollTop = chatBox.scrollHeight;
    var convoId = parseInt(chatBox.getAttribute('data-conversation'), 10) || 0;
    setInterval(function () {
      if (document.visibilityState === 'hidden') return;
      fetch('realtime.php?v=chat&c=' + convoId + '&since=' + lastId + '&t=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok) return;
          var fresh = 0;
          (d.messages || []).forEach(function (m) {
            if (parseInt(m.id, 10) > lastId) { lastId = parseInt(m.id, 10); appendMsg(m); fresh++; }
          });
          if (fresh) refreshNotifs();
        }).catch(function () {});
    }, 4000);
  }
  if (chatForm && chatBox) {
    chatForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var body = (chatInput.value || '').trim();
      if (!body) return;
      chatInput.value = '';
      chatInput.disabled = true;
      var fd = new URLSearchParams({
        csrf: csrf,
        with: (chatForm.querySelector('[name="with"]') || {}).value || '',
        conversation: (chatForm.querySelector('[name="conversation"]') || {}).value || '',
        body: body,
      });
      fetch('send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
        body: fd.toString(),
      }).then(function (r) { return r.json(); }).then(function (d) {
        chatInput.disabled = false;
        chatInput.focus();
        if (d && d.ok) {
          refreshNotifs();
        } else {
          chatInput.value = body;
        }
      }).catch(function () { chatInput.disabled = false; chatInput.value = body; });
    });
  }
})();

/* ================================================================
   Realtime live updates — pages refresh themselves without reloads.
   Polls realtime.php (no-store) and patches the DOM in place.
   ================================================================ */
(function () {
  var liveBox = document.querySelector('[data-live-scope]');
  if (!liveBox) return;
  var scope = liveBox.getAttribute('data-live-scope');

  function lmsEsc(s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function lmsClock(ts) {
    return new Date(ts * 1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
  }
  function lmsAgo(ts) {
    var sec = Math.max(0, Math.floor(Date.now() / 1000) - (ts || 0));
    if (sec < 60) return 'just now';
    if (sec < 3600) return Math.floor(sec / 60) + 'm ago';
    if (sec < 86400) return Math.floor(sec / 3600) + 'h ago';
    return Math.floor(sec / 86400) + 'd ago';
  }
  function lmsDur(sec) {
    sec = Math.max(0, sec | 0);
    var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60, out = [];
    if (h) out.push(h + 'h');
    if (m || h) out.push(m + 'm');
    if (!h) out.push(s + 's');
    return out.length ? out.join(' ') : '0s';
  }
  function livePoll(url, interval, onData) {
    var running = true;
    function tick() {
      if (!running || document.visibilityState === 'hidden') return;
      fetch(url + (url.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw 0; return r.json(); })
        .then(function (d) { if (d && d.ok) onData(d); })
        .catch(function () { });
    }
    setTimeout(tick, 500);
    var timer = setInterval(tick, interval);
    return function () { running = false; clearInterval(timer); };
  }
  var tickersOn = {};
  function runOpenTickers(container) {
    (container || document).querySelectorAll('[data-open-seconds]').forEach(function (el) {
      var key = el.getAttribute('data-open-seconds') + '|' + ((el.dataset && el.dataset.mark) || '');
      if (tickersOn[key]) return;
      tickersOn[key] = true;
      var s = parseInt(el.getAttribute('data-open-seconds'), 10) || 0;
      var fmt = function () {
        var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60, out = [];
        if (h) out.push(h + 'h');
        if (m || h) out.push(m + 'm');
        if (!h) out.push(sec + 's');
        el.textContent = out.length ? out.join(' ') : '0s';
      };
      fmt();
      setInterval(function () { s++; fmt(); }, 1000);
    });
  }

  /* ---------------- dashboard (teacher + student) ---------------- */
  if (scope === 'teacher-dash' || scope === 'student-dash') {
    var dashUrl = scope === 'teacher-dash' ? 'realtime.php?v=dash' : 'realtime.php?v=dash-student';
    livePoll(dashUrl, 10000, function (d) {
      var counts = d.counts || {};
      document.querySelectorAll('[data-live-stat]').forEach(function (el) {
        var k = el.getAttribute('data-live-stat');
        if (k in counts) el.textContent = counts[k];
      });
      if (d.online) { var ob = document.querySelector('[data-live-online-count]'); if (ob) ob.textContent = d.online.length + ' online'; }
      if (d.visits) { var vb = document.querySelector('[data-live-visits-count]'); if (vb) vb.textContent = d.visits.length; }
      if (d.activity_svg) { var ac = document.querySelector('[data-live-svg="activity"]'); if (ac) ac.innerHTML = d.activity_svg; }
      if (d.students_svg) { var sg = document.querySelector('[data-live-svg="students"]'); if (sg) sg.innerHTML = d.students_svg; }
      if (scope !== 'teacher-dash') return;

      /* live students */
      var onl = document.querySelector('[data-live-list="online"]');
      if (onl) {
        onl.innerHTML = d.online.length
          ? d.online.map(function (u) {
            return '<li class="flex items-center gap-3 px-4 py-2.5"><span class="relative grid h-9 w-9 shrink-0 place-items-center rounded-full bg-indigo-600 text-sm font-bold text-white">' + lmsEsc(String(u.name).charAt(0).toUpperCase()) + '<span class="absolute -right-0.5 -bottom-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"></span></span><span class="min-w-0"><span class="block truncate font-semibold text-slate-900">' + lmsEsc(u.name) + '</span><span class="block text-xs text-slate-400">online · ' + lmsAgo(u.last_seen) + '</span></span></li>';
          }).join('')
          : '<li class="px-4 py-4 text-center text-sm text-slate-400">No students online right now.</li>';
      }
      /* today's visits */
      var vis = document.querySelector('[data-live-list="visits"]');
      if (vis) {
        vis.innerHTML = d.visits.length
          ? d.visits.map(function (r) {
            var openNow = !r.left_at;
            return '<li class="flex items-center gap-3 px-4 py-2.5"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700">' + lmsEsc(String(r.name).charAt(0).toUpperCase()) + '</span><span class="min-w-0 flex-1"><span class="block truncate font-semibold text-slate-900">' + lmsEsc(r.name) + '</span><span class="block text-xs text-slate-400">' + lmsEsc(r.course) + '</span></span><span class="text-right text-xs"><span class="block font-semibold ' + (openNow ? 'text-emerald-600' : 'text-slate-600') + '">' + lmsClock(r.entered_at) + '</span><span class="block text-slate-400">' + (openNow ? 'now' : 'left') + '</span></span></li>';
          }).join('')
          : '<li class="px-4 py-4 text-center text-sm text-slate-400">No visits recorded today yet.</li>';
      }
      /* recent activity */
      var act = document.querySelector('[data-live-list="activity"]');
      if (act) {
        act.innerHTML = d.activity.length
          ? d.activity.map(function (r) {
            var icon = r.kind === 'enrolled' ? '🎒' : '✅';
            return '<li class="flex items-start gap-3 px-4 py-2.5"><span class="mt-0.5 text-base">' + icon + '</span><span class="min-w-0 flex-1"><span class="block text-sm text-slate-700"><span class="font-semibold text-slate-900">' + lmsEsc(r.who) + '</span> ' + (r.kind === 'enrolled' ? 'enrolled in' : 'completed') + ' <span class="font-semibold text-indigo-700">' + lmsEsc(r.course) + '</span>' + (r.lesson ? ' · ' + lmsEsc(r.lesson) : '') + '</span></span><span class="shrink-0 text-xs text-slate-400">' + lmsAgo(r.ts) + '</span></li>';
          }).join('')
          : '<li class="px-4 py-4 text-center text-sm text-slate-400">No recent activity.</li>';
      }
      /* needs attention */
      var att = document.querySelector('[data-live-list="attention"]');
      if (att) {
        att.innerHTML = d.attention.length
          ? d.attention.map(function (s) {
            return '<li class="flex items-center gap-3 px-4 py-2.5"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-amber-100 text-sm font-bold text-amber-700">' + lmsEsc(String(s.name).charAt(0).toUpperCase()) + '</span><span class="min-w-0 flex-1"><span class="block truncate font-semibold text-slate-900">' + lmsEsc(s.name) + '</span><span class="block text-xs text-slate-400">' + lmsEsc(s.course) + ' · ' + s.done + '/' + s.total + ' lessons · ' + s.pct + '%</span></span><span class="w-16 shrink-0"><span class="mb-1 block text-right text-xs font-bold text-amber-600">' + s.pct + '%</span><span class="block h-1.5 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-amber-500" style="width:' + s.pct + '%"></span></span></span></li>';
          }).join('')
          : '<li class="px-4 py-4 text-center text-sm text-slate-400">Everyone is keeping up 🎉</li>';
      }
    });
    return;
  }

  /* ---------------- enrollments roster ---------------- */
  if (scope === 'roster') {
    livePoll('realtime.php?v=roster&' + new URLSearchParams(location.search).toString(), 10000, function (d) {
      var sm = d.summary || {};
      document.querySelectorAll('[data-live-summary]').forEach(function (el) {
        var k = el.getAttribute('data-live-summary');
        if (k && k in sm) el.textContent = sm[k];
      });
      var rows = {};
      document.querySelectorAll('tr[data-student]').forEach(function (tr) { rows[tr.getAttribute('data-student')] = tr; });
      (d.students || []).forEach(function (u) {
        var tr = rows[String(u.id)];
        if (!tr) return;
        var badge = tr.querySelector('[data-presence="' + u.id + '"]');
        if (badge) {
          badge.textContent = u.online ? '● Online' : '◌ Offline';
          badge.className = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ' + (u.online ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500');
        }
        var visits = tr.querySelector('[data-visits]');
        if (visits) {
          var html = '<span class="font-semibold text-slate-800">' + u.visits + '</span> visits';
          if (u.last_at) html += '<span class="block text-xs text-slate-400">last ' + lmsAgo(u.last_at) + '</span>';
          visits.innerHTML = html;
        }
        var more = tr.querySelector('[data-more]');
        if (more) more.textContent = u.more_courses > 0 ? '+' + u.more_courses + ' more' : '';
      });
      var logBody = document.getElementById('att-log-body');
      if (logBody && d.attCourse) {
        /* attendance log */
        var cnt = document.getElementById('att-log-count');
        if (cnt) cnt.textContent = d.attLog.length;
        logBody.innerHTML = d.attLog.length
          ? d.attLog.map(function (a) {
            var left = a.left_at ? lmsClock(a.left_at) : (a.online ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">🟢 Online</span>' : '<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">⏳ In course</span>');
            return '<tr><td class="px-4 py-3 font-semibold text-slate-900">' + lmsEsc(a.name) + '</td><td class="px-4 py-3 text-slate-600">' + new Date(a.entered_at * 1000).toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) + '</td><td class="px-4 py-3 text-slate-600">' + left + '</td><td class="px-4 py-3 text-slate-600">' + lmsDur(a.left_at ? a.left_at - a.entered_at : Math.floor(Date.now() / 1000) - a.entered_at) + '</td><td class="hidden px-4 py-3 text-slate-400 md:table-cell">' + lmsEsc(a.ip) + '</td></tr>';
          }).join('')
          : '<tr><td class="px-4 py-6 text-center text-sm text-slate-400" colspan="5">No attendance recorded for this course yet.</td></tr>';
      }
    });
    return;
  }

  /* ---------------- attendance-day page ---------------- */
  if (scope === 'day') {
    livePoll('realtime.php?v=day&' + new URLSearchParams(location.search).toString(), 12000, function (d) {
      var st = d.stats || {};
      document.querySelectorAll('[data-day-stat]').forEach(function (el) {
        var k = el.getAttribute('data-day-stat');
        if (!(k in st)) return;
        if (k === 'time') { el.textContent = lmsDur(st.time); return; }
        el.textContent = st[k];
      });
      var sec = document.getElementById('day-records');
      if (!sec) return;
      if (!d.records.length) {
        sec.innerHTML = '<div class="mt-4 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500"><p class="text-4xl">🗓</p><p class="mt-3 font-medium">No attendance recorded for this day.</p></div>';
        return;
      }
      var rows = d.records.map(function (r) {
        return '<tr>' +
          '<td class="px-4 py-3 font-semibold text-slate-900">' + lmsClock(r.entered_at) + '</td>' +
          '<td class="px-4 py-3"><div class="flex items-center gap-2"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-indigo-600 text-xs font-bold text-white">' + lmsEsc(String(r.name).charAt(0).toUpperCase()) + '</span><div class="min-w-0"><p class="truncate font-semibold text-slate-900">' + lmsEsc(r.name) + '</p><p class="text-xs text-slate-400">' + (r.online ? '🟢 online' : 'offline') + '</p></div></div></td>' +
          '<td class="px-4 py-3"><span class="inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">' + lmsEsc(r.course_title) + '</span><span class="block text-xs text-slate-400">' + lmsEsc(r.course_category) + '</span></td>' +
          '<td class="px-4 py-3">' + (r.open ? (r.online ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span></span> Online</span>' : '<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">⏳ In course</span>') : '<span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">⏹ Left ' + lmsClock(r.left_at) + '</span>') + '</td>' +
          '<td class="px-4 py-3 text-slate-600">' + (r.open ? '<span data-open-seconds="' + Math.max(0, Math.floor(Date.now() / 1000) - r.entered_at) + '" data-mark="d' + r.id + '" class="font-semibold text-emerald-700"></span>' : lmsDur(r.duration)) + '</td>' +
          '<td class="hidden px-4 py-3 text-slate-400 md:table-cell">' + lmsEsc(r.ip) + '</td></tr>';
      }).join('');
      sec.innerHTML = '<div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"><table class="w-full text-left text-sm"><thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 font-semibold">Entered</th><th class="px-4 py-3 font-semibold">Student</th><th class="px-4 py-3 font-semibold">Course</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 font-semibold">Time spent</th><th class="hidden px-4 py-3 font-semibold md:table-cell">IP address</th></tr></thead><tbody class="divide-y divide-slate-100">' + rows + '</tbody></table></div>';
      runOpenTickers(sec);
    });
    return;
  }

  /* ---------------- course page: students online now ---------------- */
  if (scope === 'course-online') {
    var cid = liveBox.getAttribute('data-course-id') || '0';
    var chip = document.getElementById('course-online-chip');
    livePoll('realtime.php?v=course&id=' + encodeURIComponent(cid), 15000, function (d) {
      if (!chip) return;
      chip.innerHTML = '<span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span></span> <span id="course-online-count">' + d.count + '</span> online now' + (d.students && d.students.length ? ': ' + d.students.slice(0, 3).map(lmsEsc).join(', ') + (d.students.length > 3 ? '…' : '') : '');
    });
    return;
  }

  /* ---------------- courses browse page: live lesson/student counts ---------------- */
  if (scope === 'courses') {
    livePoll('realtime.php?v=courses', 12000, function (d) {
      var tot = document.querySelector('[data-live-courses-total]');
      if (tot && d.courses) tot.textContent = d.courses.length;
      (d.courses || []).forEach(function (c) {
        var l = document.querySelector('[data-live-c-lessons="' + c.id + '"]');
        if (l) l.textContent = c.lessons;
        var s = document.querySelector('[data-live-c-students="' + c.id + '"]');
        if (s) s.textContent = c.students;
      });
    });
    return;
  }

  /* ---------------- landing page: live platform stats (guests allowed) ---------------- */
  if (scope === 'index') {
    livePoll('realtime.php?v=index', 15000, function (d) {
      var s = d.stats || {};
      document.querySelectorAll('[data-live-index]').forEach(function (el) {
        var k = el.getAttribute('data-live-index');
        if (k in s) el.textContent = s[k];
      });
    });
  }
})();