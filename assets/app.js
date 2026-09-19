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
  /* guarantee true viewport centering: an ancestor with a transform/rotate/filter
     (paper-card rotate, reveal, …) would otherwise become the containing block
     for this position:fixed backdrop — re-parent to <body> before showing */
  if (modal.parentElement && modal.parentElement !== document.body) document.body.appendChild(modal);
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

/* ---------- Lesson quiz modal: prefill per lesson + add/remove question rows ---------- */
/* Rows are [data-q-row] blocks rendered by quiz_question_row_html(): inputs prompt[], o1[]..o4[],
   correct[] are parallel arrays (DOM order = server order). Blank master: template[data-quiz-blank-row];
   saved questions live in per-lesson template[data-quiz-template="<mid>"]. Server caps at 20 questions. */
const QUIZ_MAX_QUESTIONS = 20;
function quizRowCount() {
  const rows = document.getElementById('quiz-rows');
  return rows ? rows.querySelectorAll('[data-q-row]').length : 0;
}
function quizRenumber() {
  document.querySelectorAll('#quiz-rows [data-q-row]').forEach((row, i) => {
    const num = row.querySelector('[data-q-num]');
    if (num) num.textContent = 'Q' + (i + 1);
  });
  const addBtn = document.getElementById('quiz-add-row');
  if (addBtn) {
    const full = quizRowCount() >= QUIZ_MAX_QUESTIONS;
    addBtn.disabled = full;
    addBtn.classList.toggle('opacity-40', full);
    addBtn.classList.toggle('cursor-not-allowed', full);
    addBtn.classList.toggle('hover:border-indigo-300', !full);
    addBtn.classList.toggle('hover:text-indigo-600', !full);
  }
}
function quizLoadRows(mid) {
  const rows = document.getElementById('quiz-rows');
  if (!rows) return;
  rows.innerHTML = '';
  const tpl = document.querySelector('template[data-quiz-template="' + mid + '"]');
  if (tpl) rows.appendChild(tpl.content.cloneNode(true));
  if (!rows.querySelector('[data-q-row]')) {
    const blank = document.querySelector('template[data-quiz-blank-row]');
    if (blank) rows.appendChild(blank.content.cloneNode(true));
  }
  quizRenumber();
}
document.addEventListener('click', (e) => {
  const editBtn = e.target.closest('[data-modal-open="quiz-modal"][data-quiz-edit]');
  if (editBtn) {
    /* runs after the generic opener (registered earlier) has shown the modal */
    const mid = editBtn.getAttribute('data-quiz-edit') || '';
    const matInput = document.getElementById('quiz-material-id');
    if (matInput) matInput.value = mid;
    const lessonTitle = document.getElementById('quiz-lesson-title');
    if (lessonTitle) lessonTitle.textContent = editBtn.getAttribute('data-title') || '—';
    const tpl = document.querySelector('template[data-quiz-template="' + mid + '"]');
    const has = !!(tpl && tpl.getAttribute('data-has-quiz') === '1');
    const title = document.getElementById('quiz-title');
    if (title) title.value = has ? (tpl.getAttribute('data-quiz-title') || '') : '';
    const pass = document.getElementById('quiz-pass');
    if (pass) pass.value = has ? (tpl.getAttribute('data-pass') || '60') : '60';
    quizLoadRows(mid);
    return;
  }
  if (e.target.closest('#quiz-add-row')) {
    e.preventDefault();
    if (quizRowCount() >= QUIZ_MAX_QUESTIONS) return;
    const blank = document.querySelector('template[data-quiz-blank-row]');
    const rows = document.getElementById('quiz-rows');
    if (blank && rows) {
      rows.appendChild(blank.content.cloneNode(true));
      quizRenumber();
      const firstInput = rows.querySelector('[data-q-row]:last-child input[name="prompt[]"]');
      if (firstInput) firstInput.focus();
    }
    return;
  }
  const removeBtn = e.target.closest('[data-q-remove]');
  if (removeBtn) {
    e.preventDefault();
    const row = removeBtn.closest('[data-q-row]');
    if (!row) return;
    if (quizRowCount() <= 1) {
      /* never leave zero rows — clear the last one instead */
      row.querySelectorAll('input:not([type="hidden"])').forEach((i) => { i.value = ''; });
      const sel = row.querySelector('select');
      if (sel) sel.selectedIndex = 0;
    } else {
      row.remove();
    }
    quizRenumber();
    return;
  }
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
  // For the course lessons tabs, scope to their own section so the lesson-modal
  // panes (doc/paste/vid/link) are never toggled by course tab clicks.
  const root = btn.closest('#lesson-modal') || btn.closest('.modal-backdrop') || btn.closest('section[data-tabs]') || document;
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

/* ---------- lessons tabs: return to the tab that matches the last action ---------- */
/* upload.php redirects with ?tab=docs|videos after an upload; otherwise the last
   tab the teacher was viewing is restored (sessionStorage) — so a materials upload
   no longer reloads onto the Videos tab */
(function () {
  const bar = document.querySelector('section[data-tabs]');
  if (!bar || !bar.querySelector('[data-tab-btn="videos"]')) return;
  const valid = (t) => t === 'videos' || t === 'docs';
  let want = null;
  let fromUrl = false;
  try {
    const sp = new URLSearchParams(window.location.search);
    if (sp.has('tab')) { want = sp.get('tab'); fromUrl = true; }
  } catch (e) { /* very old browser: fall through */ }
  if (!valid(want)) {
    try { want = sessionStorage.getItem('lh-lessons-tab'); } catch (e) { /* private mode */ }
  }
  if (valid(want)) {
    const btn = bar.querySelector('[data-tab-btn="' + want + '"]');
    if (btn && btn.getAttribute('aria-selected') !== 'true') {
      try { switchLessonTab(btn); } catch (e) { /* leave the default tab */ }
    }
  }
  if (fromUrl) {
    /* consume the param so a manual reload follows the tab you clicked last */
    try {
      const u = new URL(window.location.href);
      u.searchParams.delete('tab');
      window.history.replaceState({}, '', u.pathname + (u.searchParams.toString() ? '?' + u.searchParams.toString() : '') + u.hash);
    } catch (e) { /* ignore */ }
  }
  bar.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-tab-btn]');
    if (!btn) return;
    try { sessionStorage.setItem('lh-lessons-tab', btn.getAttribute('data-tab-btn')); } catch (err) { /* ignore */ }
  });
})();

/* ---------- File inputs: live count / size hint ----------
   The per-file ceiling comes from PHP (data-max-bytes) so the browser and the
   server can never disagree. Big files are posted in pieces (see the chunked
   uploader below), so the host's per-request cap no longer limits the size. */
const lhFmtSize = (bytes) => {
  if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
  if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return bytes + ' B';
};

document.querySelectorAll('input[type="file"][data-chunk-url]').forEach((input) => {
  const note = document.getElementById(input.id + '-note');
  const maxBytes = parseInt(input.getAttribute('data-max-bytes') || '0', 10);
  const multiple = input.hasAttribute('multiple');
  input.addEventListener('change', () => {
    if (!note) return;
    const files = Array.from(input.files || []);
    if (!files.length) { note.textContent = ''; return; }
    const total = files.reduce((sum, f) => sum + (f.size || 0), 0);
    const tooBig = maxBytes ? files.filter((f) => f.size > maxBytes) : [];
    let text = files.length + (multiple ? ' file(s) selected · ' + lhFmtSize(total) + ' total — each becomes its own lesson.'
      : ' file selected · ' + lhFmtSize(total) + '.');
    if (tooBig.length) {
      text += ' ⚠ exceeds the ' + lhFmtSize(maxBytes) + ' limit: ' + tooBig.map((f) => f.name).join(', ');
      note.className = 'mt-1 text-xs font-semibold text-red-600';
    } else {
      note.className = 'mt-1 text-xs text-slate-400';
    }
    note.textContent = text;
  });
});

/* ---------- Chunked upload: post large files piece by piece ----------
   Shared hosts cap a SINGLE request (post_max_size / upload_max_filesize — often
   10–20 MB), which is why a big video was refused before it ever reached the
   server. The file is sliced here instead and each piece is POSTed to
   upload_chunk.php, which appends it to a part file on disk and — on the last
   piece — publishes the file into uploads/ and creates the lesson. The database
   only ever stores lesson metadata; the video bytes stay on disk. */
function lhRandomHex32() {
  const a = new Uint8Array(16);
  if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(a);
  else for (let i = 0; i < 16; i++) a[i] = Math.floor(Math.random() * 256);
  return Array.from(a).map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** POST one piece; resolves with the server's JSON reply. */
function lhPostPiece(url, fields, blob, name, onLoaded) {
  return new Promise((resolve, reject) => {
    const fd = new FormData();
    Object.keys(fields).forEach((k) => fd.append(k, fields[k]));
    fd.append('chunk', blob, name);
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.onload = () => {
      let data = null;
      try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
      if (!data) { reject(new Error('The server did not answer properly (HTTP ' + xhr.status + ').')); return; }
      if (!data.ok) { reject(new Error(data.error || 'The upload was refused by the server.')); return; }
      resolve(data);
    };
    xhr.onerror = () => reject(new Error('Network error while uploading — check your connection and try again.'));
    xhr.upload.onprogress = (e) => { if (onLoaded && e.lengthComputable) onLoaded(e.loaded); };
    xhr.send(fd);
  });
}

/** Slice every selected file and post the pieces in order; resolves with the last reply. */
function lhUploadPieces(url, base, files, chunkBytes, onProgress) {
  const ids = files.map(() => lhRandomHex32());
  let chain = Promise.resolve(null);
  const done = { bytes: 0, total: files.reduce((s, f) => s + f.size, 0) };
  files.forEach((file, fi) => {
    chain = chain.then(() => {
      const pieces = Math.max(1, Math.ceil(file.size / chunkBytes));
      let step = Promise.resolve(null);
      for (let i = 0; i < pieces; i++) {
        const start = i * chunkBytes;
        const blob = file.slice(start, Math.min(start + chunkBytes, file.size));
        const fields = Object.assign({}, base, {
          upload_id: ids[fi],
          name: file.name,
          size: String(file.size),
          index: String(i),
          total: String(pieces),
          file_index: String(fi),
        });
        step = step.then(() => lhPostPiece(url, fields, blob, file.name, (loaded) => {
          if (onProgress) onProgress({ file: file.name, fileIndex: fi + 1, fileCount: files.length, sent: start + loaded, fileSize: file.size, allSent: done.bytes + start + loaded, allTotal: done.total });
        }));
      }
      return step.then((res) => { done.bytes += file.size; return res; });
    });
  });
  return chain;
}

/* Intercept submits on forms whose file input is chunk-enabled: upload the pieces,
   then follow the redirect the server hands back. Without JS the form still posts
   normally (and stays subject to the host's per-request cap). */
document.querySelectorAll('input[type="file"][data-chunk-url]').forEach((input) => {
  const form = input.form;
  if (!form) return;
  form.addEventListener('submit', (e) => {
    if (form.dataset.lhUploading === '1') { e.preventDefault(); return; }
    const files = Array.from(input.files || []);
    if (!files.length) return;                                  /* let the browser flag "required" */
    const maxBytes = parseInt(input.getAttribute('data-max-bytes') || '0', 10);
    if (maxBytes && files.some((f) => f.size > maxBytes)) { e.preventDefault(); return; }  /* note already warns */
    if (typeof FormData === 'undefined' || !window.XMLHttpRequest || !File.prototype.slice) return;  /* let the server try */

    e.preventDefault();
    form.dataset.lhUploading = '1';
    const note = document.getElementById(input.id + '-note');
    const buttons = Array.from(form.querySelectorAll('button'));
    buttons.forEach((b) => { b.disabled = true; });
    const label = buttons.map((b) => b.textContent);
    if (note) note.className = 'mt-1 text-xs font-semibold text-indigo-700';

    const chunkBytes = parseInt(input.getAttribute('data-chunk-bytes') || '0', 10) || 4194304;
    const url = input.getAttribute('data-chunk-url');
    const base = {
      csrf: (form.querySelector('[name="csrf"]') || {}).value || '',
      course_id: (form.querySelector('[name="course_id"]') || {}).value || '',
      lesson_type: (form.querySelector('[name="lesson_type"]') || {}).value || 'video',
      title: (form.querySelector('[name="title"]') || {}).value || '',
      description: (form.querySelector('[name="description"]') || {}).value || '',
      chunk_size: String(chunkBytes),
      file_count: String(files.length),
    };

    lhUploadPieces(url, base, files, chunkBytes, (p) => {
      if (!note) return;
      const pct = p.allTotal ? Math.floor((p.allSent / p.allTotal) * 100) : 0;
      note.textContent = '⏳ Uploading ' + p.file + (p.fileCount > 1 ? ' (' + p.fileIndex + '/' + p.fileCount + ')' : '')
        + ' — ' + pct + '% (' + lhFmtSize(p.allSent) + ' of ' + lhFmtSize(p.allTotal) + '). Keep this tab open.';
    }).then((res) => {
      if (note) note.textContent = '✅ Upload complete — opening your course…';
      window.location.href = (res && res.redirect) ? res.redirect : 'dashboard.php';
    }).catch((err) => {
      form.dataset.lhUploading = '';
      buttons.forEach((b, i) => { b.disabled = false; if (label[i]) b.textContent = label[i]; });
      if (note) {
        note.className = 'mt-1 text-xs font-semibold text-red-600';
        note.textContent = '⚠ ' + (err && err.message ? err.message : 'The upload failed — please try again.');
      }
    });
  });
});

/* ---------- Confirm dialogs (styled modal, replaces window.confirm) ---------- */
/* Every form/link marked [data-confirm] opens this modal instead of the native
   dialog. Confirming re-submits the original form; ESC / backdrop / Cancel
   dismiss it. Works with the global modal system (openModal/closeModal). */
let lhConfirmState = null; // { resolve, done }

function lhConfirmModal() {
  let m = document.getElementById('lh-confirm-modal');
  if (m) return m;
  m = document.createElement('div');
  m.id = 'lh-confirm-modal';
  m.className = 'modal-backdrop fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4';
  m.innerHTML =
    '<div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">'
    + '<div class="flex items-start gap-3">'
    + '<span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-rose-100 text-lg">⚠️</span>'
    + '<div class="min-w-0">'
    + '<h3 data-lh-confirm-title class="text-base font-bold text-slate-900">Please confirm</h3>'
    + '<p data-lh-confirm-text class="mt-1 text-sm leading-6 text-slate-600"></p>'
    + '</div></div>'
    + '<div class="mt-5 flex justify-end gap-2">'
    + '<button type="button" data-lh-confirm-cancel class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>'
    + '<button type="button" data-lh-confirm-ok class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Delete</button>'
    + '</div></div>';
  document.body.appendChild(m);
  m.querySelector('[data-lh-confirm-cancel]').addEventListener('click', () => lhSettle(false));
  m.querySelector('[data-lh-confirm-ok]').addEventListener('click', () => lhSettle(true));
  /* closed by ESC / backdrop click (global modal handlers) => treat as cancel */
  new MutationObserver(() => {
    if (m.classList.contains('hidden')) lhSettle(false);
  }).observe(m, { attributes: true, attributeFilter: ['class'] });
  return m;
}

function lhSettle(result) {
  const s = lhConfirmState;
  if (!s || s.done) return;
  s.done = true;
  lhConfirmState = null;
  closeModal(document.getElementById('lh-confirm-modal'));
  s.resolve(result);
}

function lhConfirm(opts) {
  const m = lhConfirmModal();
  const text = String((opts && opts.message) || 'Are you sure?');
  m.querySelector('[data-lh-confirm-text]').textContent = text;
  m.querySelector('[data-lh-confirm-title]').textContent = (opts && opts.title) || 'Please confirm';
  const ok = m.querySelector('[data-lh-confirm-ok]');
  const verb = (/delete|remove|revoke|leave/i.exec(text) || [''])[0];
  ok.textContent = verb ? verb[0].toUpperCase() + verb.slice(1) : 'Yes, continue';
  /* a pending confirm is cancelled if a new one opens */
  if (lhConfirmState && !lhConfirmState.done) {
    const old = lhConfirmState;
    lhConfirmState = null; old.done = true; old.resolve(false);
  }
  return new Promise((resolve) => {
    lhConfirmState = { resolve, done: false };
    openModal(m);
    setTimeout(() => ok.focus(), 30);
  });
}

document.addEventListener('submit', (e) => {
  const f = e.target;
  if (!f.matches('form[data-confirm]')) return;
  if (f.dataset.lhConfirmed === '1') { delete f.dataset.lhConfirmed; return; } /* confirmed pass-through */
  e.preventDefault();
  lhConfirm({ message: f.getAttribute('data-confirm') || '' }).then((ok) => {
    if (!ok) return;
    f.dataset.lhConfirmed = '1';
    if (typeof f.requestSubmit === 'function') f.requestSubmit(); else f.submit();
  });
});

/* ---------- AJAX resilience for shared hosts (InfinityFree, etc.) ----------
   Their anti-bot system answers some requests with an HTML challenge page
   instead of JSON ("This site requires Javascript…"). When a poller gets
   non-JSON back we reload the page ONCE (rate-limited to 1×/45s): a full
   page load runs the challenge JS, receives the clearance cookies, and all
   following fetches return JSON again. Returns parsed JSON, or null on any
   failure so callers can back off instead of hammering the host. ---------- */
function lhSecureJson(url) {
  return fetch(url + (url.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now(), {
    cache: 'no-store',
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'fetch' },
  }).then(function (r) {
    return r.text().then(function (t) {
      var d = null;
      try { d = JSON.parse(t); } catch (e) { d = null; }
      if (d === null) { lhChallengeRecovery(); return null; } /* HTML challenge / garbage */
      return d;
    });
  }, function () { return null; }); /* network error — caller backs off */
}
function lhChallengeRecovery() {
  try {
    var last = parseInt(sessionStorage.getItem('lh-challenge-reload') || '0', 10);
    if (Date.now() - last > 45000) {
      sessionStorage.setItem('lh-challenge-reload', String(Date.now()));
      window.location.reload();
    }
  } catch (e) { /* storage unavailable — just skip the reload */ }
}

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
  let done = video.getAttribute('data-done') === '1';
  const overlay = document.querySelector('[data-overlay-for="' + materialId + '"]');
  const overlayOn = () => !!(overlay && !overlay.classList.contains('hidden'));
  const park = () => { try { if (!video.paused) video.pause(); } catch (e) { } };

  if (done) {
    /* completed lesson: stay parked at 0 under the completed overlay — never auto-resume/auto-play */
    video.addEventListener('loadedmetadata', () => {
      park();
      try { if (video.currentTime > 0.25) video.currentTime = 0; } catch (e) { }
    }, { once: true });
    window.addEventListener('pageshow', park);
    document.addEventListener('visibilitychange', () => { if (overlayOn()) park(); });
  } else if (resume > 3) {
    video.addEventListener('loadedmetadata', () => {
      try { if (resume < (video.duration || Infinity) - 3) video.currentTime = resume; } catch (e) { }
    }, { once: true });
  }
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
    park();
    setTimeout(() => {
      suppressReplay = false;
      park();
    }, 800);
  });
  video.addEventListener('play', () => {
    if (overlayOn()) { park(); return; }
    if (suppressReplay) { park(); }
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') { park(); }
    else {
      setTimeout(() => { if (overlayOn()) park(); }, 120);
    }
  });
  /* --- Replay button: clear the completed overlay, reset tracking, restart from 0 --- */
  const replayBtn = overlay ? overlay.querySelector('.js-video-replay') : null;
  if (replayBtn) replayBtn.addEventListener('click', (e) => {
    e.preventDefault();
    done = false; watched = 0; last = -1; lastSent = 0;
    if (overlay) overlay.classList.add('hidden');
    try { video.currentTime = 0; } catch (err) { }
    const p = video.play();
    if (p && p.catch) p.catch(() => { });
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
  let done = el.getAttribute('data-done') === '1';
  const overlay = document.querySelector('[data-overlay-for="' + materialId + '"]');
  const overlayOn = () => !!(overlay && !overlay.classList.contains('hidden'));
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
            if (done) {
              /* completed lesson: parked at 0 under the overlay — never auto-resume */
              e.target.pauseVideo();
              e.target.seekTo(0, true);
            } else if (resume > 3 && resume < duration - 3) {
              e.target.seekTo(resume, true);
            }
          } catch (err) { }
          if (e.target.getIframe) e.target.getIframe().classList.add('h-full', 'w-full');
          setInterval(tick, 1000);
        },
        onStateChange: (e) => {
          if (e.data === YT.PlayerState.PLAYING && overlayOn()) {
            try { player.pauseVideo(); } catch (err) { }
            return;
          }
          if (e.data === YT.PlayerState.ENDED || e.data === YT.PlayerState.PAUSED) {
            const t = player.getCurrentTime ? player.getCurrentTime() : 0;
            sendWatch(courseId, materialId, Math.round(watched), Math.round(duration), Math.round(t || 0));
          }
        },
      },
    });
  });
  /* Replay button on the completed overlay: reset tracking and restart from 0 */
  const replayBtn = overlay ? overlay.querySelector('.js-video-replay') : null;
  if (replayBtn) replayBtn.addEventListener('click', (e) => {
    e.preventDefault();
    done = false; watched = 0; lastSent = 0; lastTime = -1;
    if (overlay) overlay.classList.add('hidden');
    if (!player) return;
    try { player.seekTo(0, true); player.playVideo(); } catch (err) { }
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

  /* -------- collapsible sidebar (icon rail on desktop) -------- */
  var collapseBtn = document.getElementById('lh-side-collapse');
  var railBtn = document.getElementById('lh-rail-toggle');
  function setRail(on) {
    if (on) body.classList.add('lh-rail'); else body.classList.remove('lh-rail');
    try { localStorage.setItem('lh-rail', on ? '1' : '0'); } catch (e) {}
  }
  if (document.body.classList.contains('lh-app')) {
    try { if (localStorage.getItem('lh-rail') === '1' && window.innerWidth >= 1024) body.classList.add('lh-rail'); } catch (e) {}
  }
  var railToggle = function (e) {
    if (e && e.preventDefault) e.preventDefault();
    setRail(!body.classList.contains('lh-rail'));
  };
  if (collapseBtn) collapseBtn.addEventListener('click', railToggle);
  if (railBtn) railBtn.addEventListener('click', railToggle);
  window.addEventListener('resize', function () {
    if (window.innerWidth < 1024) body.classList.remove('lh-rail');
  });

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
    if (n > 0) {
      b.textContent = n > 99 ? '99+' : String(n);
      b.classList.remove('hidden');
      b.style.display = '';   /* clear the inline hide — let .lh-badge pick grid/block */
    } else {
      /* hide by tweaking the class AND the inline style: the component class
         (.lh-badge{display:grid}) and Tailwind's .hidden have identical
         specificity, so the plain class toggle alone is stylesheet-order
         dependent (a zero badge once stayed visible on the deployed build) */
      b.classList.add('hidden');
      b.style.display = 'none';
    }
  }

  /* -------- notifications: poll + dropdown -------- */
  var lastUnread = 0;   /* newest known unread-notification count (drives the bell badge) */
  var notifBtn = document.getElementById('lh-notif-btn');
  var panel = document.getElementById('lh-notif-panel');
  var list = document.getElementById('lh-notif-list');
  function markAllRead() {
    return fetch('mark_notifications_read.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      body: 'csrf=' + encodeURIComponent(csrf),
    }).then(function (r) { return r.json().catch(function () { return null; }); }).then(function (d) {
      if (d && d.ok && typeof d.unread === 'number') { lastUnread = d.unread; setBadge('lh-notif-badge', d.unread); }
      return d;
    });
  }
  if (notifBtn && panel) {
    notifBtn.addEventListener('click', function (e) {
      e.preventDefault();
      panel.classList.toggle('hidden');
      /* "seen": opening the panel counts as reading everything — the badge
         clears once the fresh list confirms it; a new notification later
         brings the badge back with the new count */
      if (!panel.classList.contains('hidden')) {
        refreshNotifs().then(function () {
          if (lastUnread <= 0) return null;
          return markAllRead().then(function () { return refreshNotifs(); });
        }).catch(function () {});
      }
      e.stopPropagation();
    });
    document.addEventListener('click', function (e) {
      if (!panel.classList.contains('hidden') && !panel.contains(e.target) && e.target !== notifBtn) panel.classList.add('hidden');
    });
  }
  var readAllBtn = document.getElementById('lh-notif-read-all');
  if (readAllBtn) readAllBtn.addEventListener('click', function () {
    markAllRead().then(function () { return refreshNotifs(); }).catch(function () {});
  });
  function refreshNotifs() {
    return lhSecureJson('realtime.php?v=notifications').then(function (d) {
      if (!d || !d.ok) return false;
      /* bell badge = UNREAD notifications only: clears when items are read/seen,
         re-appears with the new count as soon as something new arrives */
      lastUnread = d.unread || 0;
      setBadge('lh-notif-badge', lastUnread);
      setBadge('lh-chat-badge', d.chat_unread || 0);
      setBadge('lh-chat-badge-side', d.chat_unread || 0);
      if (!list) return true;
      if (!(d.items || []).length) {
        list.innerHTML = '<p class="px-3.5 py-6 text-center text-xs text-slate-400">No notifications yet.<br>New lessons, quizzes, results and messages will show up here.</p>';
        return true;
      }
      list.innerHTML = (d.items || []).map(function (n) {
        return '<a href="' + esc(n.link) + '" data-notif-id="' + n.id + '" class="lx-panel-item lh-notif-item ' + (n.is_read ? '' : 'lh-notif-unread') + '">'
          + '<span class="text-[13px] font-semibold text-slate-800">' + esc(n.title) + '</span>'
          + (n.body ? '<span class="text-xs text-slate-500">' + esc(n.body) + '</span>' : '')
          + '<span class="text-[10px] text-slate-400">' + esc(ago(n.created_at)) + '</span></a>';
      }).join('');
      return true;
    });
  }
  /* adaptive interval: 8s normally, backs off (max 60s) while the host
     challenges/fails, resets on the next success — friendly to rate-limited
     shared hosts instead of hammering them every 8s no matter what */
  var notifDelay = 8000;
  function notifLoop() {
    refreshNotifs().then(function (ok) {
      if (ok === false) notifDelay = Math.min(60000, Math.round(notifDelay * 1.6));
      else if (ok === true) notifDelay = 8000;
      setTimeout(notifLoop, notifDelay);
    });
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
    }).then(function (r) { return r.json().catch(function () { return null; }); }).then(function (d) {
      /* the clicked item is read — drop it from the badge immediately */
      if (d && typeof d.unread === 'number') { lastUnread = d.unread; setBadge('lh-notif-badge', d.unread); }
    }).catch(function () {}).finally(function () { window.location.href = link; });
  });
  if (document.body.classList.contains('lh-app')) {
    notifLoop();
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
      + '<p class="mt-1 text-right text-[10px] ' + (mine ? 'text-emerald-100/90' : 'text-slate-400') + '">' + esc(ts)
      + (mine ? '<span class="chat-seen" style="display:none"> · ✓✓ Seen</span>' : '')
      + '</p></div>';
    if (mine && parseInt(m.is_read, 10) === 1) {
      var s0 = wrap.querySelector('.chat-seen');
      if (s0) s0.style.display = '';
    }
    chatBox.appendChild(wrap);
    chatBox.scrollTop = chatBox.scrollHeight;
  }
  if (chatBox) {
    chatBox.querySelectorAll('[data-msg]').forEach(function (el) {
      lastId = Math.max(lastId, parseInt(el.getAttribute('data-msg'), 10) || 0);
    });
    chatBox.scrollTop = chatBox.scrollHeight;
    var convoId = parseInt(chatBox.getAttribute('data-conversation'), 10) || 0;
    var readUpTo = parseInt(chatBox.getAttribute('data-read-up-to'), 10) || 0;
    var chatDelay = 4000;
    /* read receipts: reveal "✓✓ Seen" on MY bubbles up to the newest one the peer opened */
    function updateSeen(upTo) {
      upTo = parseInt(upTo, 10) || 0;
      if (upTo <= readUpTo) return;
      readUpTo = upTo;
      chatBox.querySelectorAll('.chat-msg').forEach(function (el) {
        var id = parseInt(el.getAttribute('data-msg'), 10) || 0;
        var mine = el.classList.contains('justify-end');
        if (mine && id <= readUpTo) {
          var s = el.querySelector('.chat-seen');
          if (s) s.style.display = '';
        }
      });
    }
    function chatLoop() {
      if (document.visibilityState === 'hidden') { setTimeout(chatLoop, chatDelay); return; }
      lhSecureJson('realtime.php?v=chat&c=' + convoId + '&since=' + lastId).then(function (d) {
        if (d && d.ok) {
          chatDelay = 4000;
          var fresh = 0;
          (d.messages || []).forEach(function (m) {
            if (parseInt(m.id, 10) > lastId) { lastId = parseInt(m.id, 10); appendMsg(m); fresh++; }
          });
          updateSeen(d.read_up_to);
          if (fresh) refreshNotifs();
        } else if (d === null) {
          chatDelay = Math.min(60000, Math.round(chatDelay * 1.6)); /* host challenging / network error */
        }
        setTimeout(chatLoop, chatDelay);
      });
    }
    chatLoop();
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
    var running = true, delay = interval, timer = null;
    function schedule() { timer = setTimeout(tick, delay); }
    function tick() {
      if (!running) return;
      if (document.visibilityState === 'hidden') { schedule(); return; }
      lhSecureJson(url).then(function (d) {
        if (!running) return;
        if (d && d.ok) { delay = interval; onData(d); }
        else delay = Math.min(60000, Math.round(delay * 1.6)); /* challenge/network: back off */
        schedule();
      });
    }
    setTimeout(tick, 500);
    return function () { running = false; clearTimeout(timer); };
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

  /* Slide a live stat to its new value: the old number slides up and out while
     the new one slides in from the bottom (odometer-style). Plain swap when the
     value is unchanged or the visitor prefers reduced motion. The CSS classes
     (.lh-rolling/.lh-roll-in/.lh-roll-out) live in header.php's stylesheet. */
  function lhRollText(el, next) {
    next = String(next);
    var rolling = el.querySelector('.lh-roll-in');
    var current = rolling ? rolling.textContent : el.textContent;
    if (current === next) return;   /* unchanged — also keeps a roll already in flight coherent */
    if (el.lhRollTimer) { clearTimeout(el.lhRollTimer); el.lhRollTimer = null; }
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      el.textContent = next;
      return;
    }
    var out = document.createElement('span');
    out.className = 'lh-roll-out';
    out.textContent = current;
    var inn = document.createElement('span');
    inn.className = 'lh-roll-in';
    inn.textContent = next;
    el.classList.add('lh-rolling');
    el.textContent = '';
    el.appendChild(out);
    el.appendChild(inn);
    el.lhRollTimer = setTimeout(function () {
      el.lhRollTimer = null;
      el.classList.remove('lh-rolling');
      el.textContent = next;
    }, 460);
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
            return '<li class="flex items-center gap-3 px-4 py-2.5"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-amber-100 text-sm font-bold text-amber-700">' + lmsEsc(String(s.name).charAt(0).toUpperCase()) + '</span><span class="min-w-0 flex-1"><span class="block truncate font-semibold text-slate-900">' + lmsEsc(s.name) + '</span><span class="block truncate text-xs text-slate-400">' + lmsEsc(s.course) + ' · ' + s.done + '/' + s.total + ' lessons · ' + s.pct + '%</span></span><span class="w-16 shrink-0"><span class="mb-1 block text-right text-xs font-bold text-amber-600">' + s.pct + '%</span><span class="block h-1.5 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-amber-500" style="width:' + s.pct + '%"></span></span></span></li>';
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
        lhRollText(el, k === 'time' ? lmsDur(st.time) : st[k]);
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