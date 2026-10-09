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

/* ---------- invite links: copy & share ---------- */
/* Used by the live-class "copy the join link" buttons. Clipboard support is a
   mess on phones: the async clipboard API needs a secure context (https or
   localhost), in-app browsers (Messenger, Instagram…) often block it outright,
   and older WebViews only know execCommand. Try them in that order, and when
   every path fails, show the text so it can be copied by hand. */
function lhCopyFallback(text) {
  try {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.top = '-1000px';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    const ok = document.execCommand('copy');
    ta.remove();
    if (ok) return true;
  } catch (e) { /* fall through to the manual route */ }
  window.prompt('Copy this link (long-press to select):', text);
  return false;
}

function lhCopyText(text) {
  return new Promise((resolve) => {
    if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(() => resolve(true), () => resolve(lhCopyFallback(text)));
    } else {
      resolve(lhCopyFallback(text));
    }
  });
}

/* Native share sheet where it exists — on a phone that is where Messenger,
   WhatsApp, SMS and the rest live. Returns false when unsupported. */
function lhShareText(title, text, url) {
  if (!navigator.share) return false;
  navigator.share({ title: title, text: text, url: url }).catch(() => {});
  return true;
}

/* Wire every [data-lh-copy] / [data-lh-share] button inside root (default: the
   whole document). Safe to call repeatedly, and to call again after adding
   buttons dynamically (see class_room.php). */
function lhWireShare(root) {
  const scope = root || document;
  scope.querySelectorAll('[data-lh-copy]').forEach((btn) => {
    if (btn.dataset.lhWired) return;
    btn.dataset.lhWired = '1';
    btn.addEventListener('click', () => {
      const label = btn.dataset.lhLabel || btn.textContent;
      lhCopyText(btn.dataset.lhCopy || '').then((ok) => {
        btn.textContent = ok ? 'copied ✓' : 'select & copy';
        setTimeout(() => { btn.textContent = label; }, 2200);
      });
    });
  });
  scope.querySelectorAll('[data-lh-share]').forEach((btn) => {
    if (btn.dataset.lhWired) return;
    btn.dataset.lhWired = '1';
    if (!navigator.share) { btn.style.display = 'none'; return; }
    btn.addEventListener('click', () => {
      lhShareText(btn.dataset.lhShareTitle || document.title, btn.dataset.lhShareText || '', btn.dataset.lhShare);
    });
  });
}

window.lhCopyText = lhCopyText;
window.lhShareText = lhShareText;
window.lhWireShare = lhWireShare;
lhWireShare();

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
  /* threshold: 0 — with the 0.12 this shipped with, the legal pages broke on
     phones: their whole body sits in ONE .reveal card (thousands of px tall),
     and a viewport can never show 12% of an element ~8x its own height, so
     the card never earned `.in` and stayed at opacity:0 below its short,
     already-revealed title. Any pixel crossing the 6%-inset viewport edge
     reveals it instead; the rootMargin still holds ordinary cards back until
     they are nearly in view. */
  }, { threshold: 0, rootMargin: '0px 0px -6% 0px' });
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
    const modal = document.getElementById(opener.getAttribute('data-modal-open'));
    if (modal && modal.id === 'lesson-modal' && opener.hasAttribute('data-folder-select')) {
      const folderId = opener.getAttribute('data-folder-select');
      modal.querySelectorAll('select[name="folder_id"]').forEach((select) => {
        select.value = folderId;
      });
      const tab = opener.getAttribute('data-lesson-tab');
      if (tab) {
        const tabButton = modal.querySelector('[data-tab-btn="' + tab + '"]');
        if (tabButton) switchLessonTab(tabButton);
      }
    }
    openModal(modal);
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
function quizLoadRows(mid, isFolder) {
  const rows = document.getElementById('quiz-rows');
  if (!rows) return;
  rows.innerHTML = '';
  const tpl = document.querySelector('template[' + (isFolder ? 'data-folder-quiz-template' : 'data-quiz-template') + '="' + mid + '"]');
  if (tpl) rows.appendChild(tpl.content.cloneNode(true));
  if (!rows.querySelector('[data-q-row]')) {
    const blank = document.querySelector('template[data-quiz-blank-row]');
    if (blank) rows.appendChild(blank.content.cloneNode(true));
  }
  quizRenumber();
}
document.addEventListener('click', (e) => {
  const explanationBtn = e.target.closest('[data-explanation-toggle]');
  if (explanationBtn) {
    e.preventDefault();
    const input = explanationBtn.parentElement.querySelector('[data-explanation-input]');
    if (!input) return;
    const show = input.classList.contains('hidden');
    input.classList.toggle('hidden', !show);
    explanationBtn.setAttribute('aria-expanded', show ? 'true' : 'false');
    explanationBtn.textContent = show ? '− Hide explanation' : '+ Add explanation';
    return;
  }
  const folderBtn = e.target.closest('[data-modal-open="quiz-modal"][data-folder-quiz-edit]');
  const editBtn = e.target.closest('[data-modal-open="quiz-modal"][data-quiz-edit]');
  const quizBtn = folderBtn || editBtn;
  if (quizBtn) {
    /* runs after the generic opener (registered earlier) has shown the modal */
    const isFolder = !!folderBtn;
    const mid = quizBtn.getAttribute(isFolder ? 'data-folder-quiz-edit' : 'data-quiz-edit') || '';
    const folderInput = document.getElementById('quiz-folder-id');
    const materialInput = document.getElementById('quiz-material-id');
    const deleteFolderInput = document.getElementById('quiz-del-folder-id');
    const deleteMaterialInput = document.getElementById('quiz-del-material-id');
    if (folderInput) folderInput.value = isFolder ? mid : '';
    if (deleteFolderInput) deleteFolderInput.value = isFolder ? mid : '';
    if (materialInput) materialInput.value = isFolder ? '' : mid;
    if (deleteMaterialInput) deleteMaterialInput.value = isFolder ? '' : mid;
    const lessonTitle = document.getElementById('quiz-lesson-title');
    if (lessonTitle) lessonTitle.textContent = quizBtn.getAttribute('data-title') || '—';
    const kindLabel = document.getElementById('quiz-kind-label');
    const targetLabel = document.getElementById('quiz-target-label');
    const gateCopy = document.getElementById('quiz-gate-copy');
    if (kindLabel) kindLabel.textContent = isFolder ? 'Folder quiz' : 'Lesson quiz';
    if (targetLabel) targetLabel.textContent = isFolder ? 'Folder' : 'Lesson';
    if (gateCopy) gateCopy.textContent = isFolder
      ? 'Mark the correct option for every question (options 3–4 are optional). Students unlock this folder quiz when every lesson in this folder reaches 100%. Each folder has independent progress.'
      : 'Mark the correct option for every question (options 3–4 are optional). This existing lesson quiz is available only after every lesson in its folder is complete.';
    const tpl = document.querySelector('template[' + (isFolder ? 'data-folder-quiz-template' : 'data-quiz-template') + '="' + mid + '"]');
    const has = !!(tpl && tpl.getAttribute('data-has-quiz') === '1');
    const title = document.getElementById('quiz-title');
    if (title) title.value = has ? (tpl.getAttribute('data-quiz-title') || '') : '';
    const pass = document.getElementById('quiz-pass');
    if (pass) pass.value = has ? (tpl.getAttribute('data-pass') || '60') : '60';
    quizLoadRows(mid, isFolder);
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
      row.querySelectorAll('textarea').forEach((textarea) => { textarea.value = ''; });
      row.querySelectorAll('[data-explanation-toggle]').forEach((button) => {
        const input = button.parentElement.querySelector('[data-explanation-input]');
        if (input) input.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
        button.textContent = '+ Add explanation';
      });
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
  const folderTab = e.target.closest('[data-folder-tab-button]');
  if (folderTab) {
    const tabset = folderTab.closest('[data-folder-tabset]');
    if (!tabset) return;
    const key = folderTab.getAttribute('data-folder-tab-button');
    tabset.querySelectorAll('[data-folder-tab-button]').forEach((button) => {
      const active = button === folderTab;
      button.setAttribute('aria-selected', active ? 'true' : 'false');
      button.classList.toggle('bg-indigo-600', active);
      button.classList.toggle('text-white', active);
      button.classList.toggle('text-slate-600', !active);
    });
    tabset.querySelectorAll('[data-folder-tab-pane]').forEach((pane) => {
      pane.classList.toggle('hidden', pane.getAttribute('data-folder-tab-pane') !== key);
    });
    return;
  }
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

/* ---------- move lessons between course folders ---------- */
let draggedLesson = null;
document.addEventListener('dragstart', (e) => {
  const lesson = e.target.closest('[data-movable-lesson]');
  if (!lesson) return;
  draggedLesson = lesson;
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', lesson.getAttribute('data-movable-lesson') || '');
  lesson.classList.add('opacity-50');
});
document.addEventListener('dragend', (e) => {
  const lesson = e.target.closest('[data-movable-lesson]');
  if (lesson) lesson.classList.remove('opacity-50');
  document.querySelectorAll('[data-folder-drop-target].ring-2').forEach((folderHeader) => {
    folderHeader.classList.remove('ring-2', 'ring-indigo-400', 'bg-indigo-50');
  });
  draggedLesson = null;
});
document.addEventListener('dragover', (e) => {
  const folderHeader = e.target.closest('[data-folder-drop-target]');
  if (!folderHeader || !draggedLesson) return;
  if (folderHeader.closest('[data-folder-progress]').getAttribute('data-course-id') !== draggedLesson.getAttribute('data-course-id')) return;
  e.preventDefault();
  e.dataTransfer.dropEffect = 'move';
  folderHeader.classList.add('ring-2', 'ring-indigo-400', 'bg-indigo-50');
});
document.addEventListener('dragleave', (e) => {
  const folderHeader = e.target.closest('[data-folder-drop-target]');
  if (!folderHeader || folderHeader.contains(e.relatedTarget)) return;
  folderHeader.classList.remove('ring-2', 'ring-indigo-400', 'bg-indigo-50');
});
document.addEventListener('drop', async (e) => {
  const folderHeader = e.target.closest('[data-folder-drop-target]');
  if (!folderHeader || !draggedLesson) return;
  e.preventDefault();
  folderHeader.classList.remove('ring-2', 'ring-indigo-400', 'bg-indigo-50');
  const courseId = folderHeader.closest('[data-folder-progress]').getAttribute('data-course-id') || '';
  const materialId = draggedLesson.getAttribute('data-movable-lesson') || e.dataTransfer.getData('text/plain');
  const folderId = folderHeader.getAttribute('data-folder-drop-target') || '';
  if (courseId !== draggedLesson.getAttribute('data-course-id')) return;
  if (folderId === draggedLesson.getAttribute('data-folder-id')) {
    showToast('This lesson is already in that folder.');
    return;
  }
  try {
    const response = await fetch('folder_move.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      body: new URLSearchParams({
        csrf: csrfToken(), course: courseId, material: materialId, folder: folderId,
      }).toString(),
    });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.error || 'The lesson could not be moved.');
    showToast('Lesson moved to ' + result.folder + '.');
    setTimeout(() => window.location.reload(), 500);
  } catch (error) {
    showToast(error.message || 'The lesson could not be moved. Please try again.', 'error');
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
      folder_id: (form.querySelector('[name="folder_id"]') || {}).value || '',
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

document.addEventListener('change', (event) => {
  const syncArchiveGroup = () => {
    const groupName = document.querySelector('[data-course-archive-group]');
    if (groupName) groupName.disabled = !Array.from(document.querySelectorAll('[data-course-student-select]')).some((checkbox) => checkbox.checked);
  };
  const master = event.target.closest('[data-course-select-all]');
  if (master) {
    document.querySelectorAll('[data-course-student-select]').forEach((checkbox) => {
      checkbox.checked = master.checked;
    });
    syncArchiveGroup();
    return;
  }
  const studentCheckbox = event.target.closest('[data-course-student-select]');
  if (!studentCheckbox) return;
  syncArchiveGroup();
  const masterCheckbox = document.querySelector('[data-course-select-all]');
  if (!masterCheckbox) return;
  const all = Array.from(document.querySelectorAll('[data-course-student-select]'));
  const selected = all.filter((checkbox) => checkbox.checked).length;
  masterCheckbox.checked = all.length > 0 && selected === all.length;
  masterCheckbox.indeterminate = selected > 0 && selected < all.length;
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
function setLessonState(materialId, text, done, percent = null) {
  document.querySelectorAll('[data-lesson-state="' + materialId + '"]').forEach((el) => {
    el.textContent = text;
    el.setAttribute('data-folder-done', done ? '1' : '0');
    el.classList.toggle('bg-emerald-100', !!done);
    el.classList.toggle('text-emerald-700', !!done);
    el.classList.toggle('bg-slate-100', !done);
    el.classList.toggle('text-slate-600', !done);
  });
  const videoProgress = document.querySelector('[data-video-progress="' + materialId + '"]');
  if (videoProgress && percent !== null) {
    const progress = done ? 100 : Math.max(0, Math.min(100, percent));
    videoProgress.style.width = progress + '%';
    const progressBar = videoProgress.parentElement;
    if (progressBar && progressBar.getAttribute('role') === 'progressbar') {
      progressBar.setAttribute('aria-valuenow', String(progress));
    }
  }
  document.querySelectorAll('[data-folder-lesson="' + materialId + '"]').forEach((el) => {
    el.setAttribute('data-folder-done', done ? '1' : '0');
  });
  document.querySelectorAll('[data-lesson-quiz-link="' + materialId + '"]').forEach((el) => {
    el.classList.toggle('hidden', !done);
  });
  document.querySelectorAll('[data-lesson-quiz-lock="' + materialId + '"]').forEach((el) => {
    el.classList.toggle('hidden', done);
  });
  updateFolderQuizProgress();
}
function updateFolderQuizProgress() {
  document.querySelectorAll('[data-folder-progress]').forEach((folder) => {
    const folderId = folder.getAttribute('data-folder-id');
    const lessons = Array.from(document.querySelectorAll('[data-folder-lesson]')).filter((lesson) =>
      lesson.getAttribute('data-folder-id') === folderId
    );
    const total = lessons.length;
    const done = lessons.filter((lesson) => lesson.getAttribute('data-folder-done') === '1').length;
    const pct = total ? (done === total ? 100 : Math.min(99, Math.round(done * 100 / total))) : 0;
    const progressText = folder.querySelector('[data-folder-progress-text]');
    if (progressText) progressText.textContent = done + ' / ' + total + ' lessons complete · ' + pct + '%';
    const quizLink = folder.querySelector('[data-folder-quiz-link]');
    const quizLock = folder.querySelector('[data-folder-quiz-lock]');
    if (quizLink && quizLock) {
      const unlocked = total > 0 && pct >= 100;
      quizLink.classList.toggle('hidden', !unlocked);
      quizLock.classList.toggle('hidden', unlocked);
    }
    const legacyLinks = document.querySelectorAll('[data-folder-legacy-link="' + folderId + '"]');
    const legacyLocks = document.querySelectorAll('[data-folder-legacy-lock="' + folderId + '"]');
    const folderComplete = total > 0 && pct >= 100;
    legacyLinks.forEach((el) => el.classList.toggle('hidden', !folderComplete));
    legacyLocks.forEach((el) => el.classList.toggle('hidden', folderComplete));
  });
}
/* Lessons we already celebrated — the completion toast must pop exactly once.
   The server answers complete:true on EVERY progress update for a finished
   lesson, so without this the "completed" popup would repeat forever. */
const completedToasts = new Set();
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
  setLessonState(materialId, data.complete ? '✓ Complete' : (pct > 0 ? pct + '% watched' : 'Not started'), !!data.complete, pct);
  if (data.complete && !completedToasts.has(String(materialId))) {
    completedToasts.add(String(materialId));
    showToast('Video lesson completed 🎉');
  }
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
  if (done) completedToasts.add(materialId); /* already finished: never toast again */
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
      /* once the server confirms completion, stop reporting — otherwise every
         further heartbeat/pause re-"completes" the lesson and re-pops the toast */
      sendWatch(courseId, materialId, Math.round(watched), Math.round(duration), Math.round(t))
        .then((d) => { if (d && d.complete) done = true; });
    }
  });
  ['pause', 'ended'].forEach((ev) => video.addEventListener(ev, () => {
    if (done) return;
    sendWatch(courseId, materialId, Math.round(watched), Math.round(duration), Math.round(video.currentTime || 0))
      .then((d) => { if (d && d.complete) done = true; });
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
  if (done) completedToasts.add(materialId); /* already finished: never toast again */
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
      sendWatch(courseId, materialId, Math.round(watched), Math.round(duration), Math.round(t))
        .then((d) => { if (d && d.complete) done = true; });
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
            if (done) return; /* finished lesson: the overlay auto-pause must not re-report + re-toast */
            const t = player.getCurrentTime ? player.getCurrentTime() : 0;
            sendWatch(courseId, materialId, Math.round(watched), Math.round(duration), Math.round(t || 0))
              .then((d) => { if (d && d.complete) done = true; });
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
/* ---------- connectivity: a lost connection means OFFLINE, and OUT ----------
   Presence is built from heartbeats: while ping.php keeps landing, last_seen
   stays fresh and everyone sees 🟢. But the instant a browser LOSES its internet
   no request can reach us to report it, so without help the user stays green for
   a whole PRESENCE_TIMEOUT (3 minutes) after the wire went dead — and their own
   screen gives no hint either. The browser itself is the alarm: the `offline`
   event (and a heartbeat whose fetch fails while navigator.onLine is false) flips
   THIS page offline at once — a sticky pill says you show as offline to others,
   and a best-effort sendBeacon posts ping.php v=offline, whose offline_user()
   backdates presence.last_seen past the cutoff, so everyone else's next refresh
   shows the user Offline immediately and close_stale_attendance() finishes the
   open visit at that same moment. On a full outage the beacon cannot be
   delivered — nothing is lost: an untouched last_seen simply ages out of
   PRESENCE_TIMEOUT by itself, which remains the fallback.

   And losing the connection is also the end of the session: this is a logout,
   not merely a status change. The session cookie is HttpOnly, so JavaScript
   cannot end the session alone — a signed-in page asks logout.php (which closes
   attendance, clears presence and destroys the session) the moment the server
   can hear it. When that request lands, the tab goes straight to the login page
   with the flag cleared. When it cannot (a full outage — nothing reaches the
   server), the intent is parked in localStorage and a signed-out screen covers
   the page; the logout then finishes at the first of: the `online` event, the
   next heartbeat (for browsers that miss the event), or the next load of any
   signed-in tab. The flag never outlives its logout — only a signed-out page
   (one the server rendered with no session left) clears it, so yesterday's
   outage cannot sign out tomorrow's login — and while it stands no heartbeat
   leaves the page. The older guard stays beside it: while navigator.onLine
   still reports NO internet the heartbeat holds its own presence POST back, a
   local server answers fine with the Wi-Fi off (loopback owes the missing
   connection nothing) and its touch would flip the user green again seconds
   after the offline beacon; both guards let go the moment the connection is
   back, which is also the reconnect path for browsers that miss the `online`
   event. Coming back is symmetric for anything still standing: the `online`
   event drops the pill and heartbeats at once, then again 2.5s later, so a
   straggling offline beacon queued during the outage cannot pin a reconnected
   user back to offline. Guests have no session to end — they get the pill, the
   beacon stays home, and never the logout. */
let lhOffline = false;
let lhOfflinePill = null;
let lhSignedOutScreen = null;
const LH_PENDING_LOGOUT = 'lh-pending-logout';

function lhIsOffline() { return lhOffline; }

/* The logout parked mid-outage: flagged the moment a signed-in page drops,
   cleared only once logout.php has actually run (a signed-out page proves it). */
function lhPendingLogout() {
  try { return window.localStorage.getItem(LH_PENDING_LOGOUT) === '1'; } catch (e) { return false; }
}
function lhMarkPendingLogout() {
  try { window.localStorage.setItem(LH_PENDING_LOGOUT, '1'); } catch (e) { /* storage blocked — the request below still carries the logout out if the server can hear it */ }
}
function lhClearPendingLogout() {
  try { window.localStorage.removeItem(LH_PENDING_LOGOUT); } catch (e) { /* nothing to clear */ }
}

/* The signed-out screen: the page behind it can no longer be trusted — its data
   belongs to a session that is over (or about to be). Inline styles only: this
   has to work even if no stylesheet ever loaded. */
function lhShowSignedOutScreen() {
  if (lhSignedOutScreen) return;
  const screen = document.createElement('div');
  screen.id = 'lh-signed-out';
  screen.setAttribute('role', 'alertdialog');
  screen.setAttribute('aria-modal', 'true');
  screen.style.cssText = 'position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.75);padding:1rem;font-family:inherit;';
  const card = document.createElement('div');
  card.style.cssText = 'width:100%;max-width:26rem;background:#fff;border-radius:1rem;padding:1.5rem;text-align:center;color:#0f172a;box-shadow:0 25px 50px -12px rgba(0,0,0,.4);';
  const head = document.createElement('h2');
  head.textContent = "📵 You've been signed out";
  const why = document.createElement('p');
  why.textContent = "You're offline, so this session is over. Your open visits are closed and others see you as offline.";
  const hint = document.createElement('p');
  hint.textContent = 'Waiting for the connection — you will land on the login page the moment it is back.';
  card.appendChild(head);
  card.appendChild(why);
  card.appendChild(hint);
  screen.appendChild(card);
  document.body.appendChild(screen);
  lhSignedOutScreen = screen;
}

function lhShowOfflinePill(show) {
  if (!show) {
    if (lhOfflinePill) { lhOfflinePill.remove(); lhOfflinePill = null; }
    return;
  }
  if (lhOfflinePill) return;
  const el = document.createElement('div');
  el.id = 'lh-offline-pill';
  el.setAttribute('role', 'status');
  el.className = 'fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-full bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg';
  const msg = document.createElement('span');
  msg.textContent = "📵 You're offline — you show as offline to others until the connection is back.";
  el.appendChild(msg);
  document.body.appendChild(el);
  lhOfflinePill = el;
}

function lhSetOffline(off) {
  if (off === lhOffline) return; /* idempotent: one pill and one beacon per drop */
  lhOffline = off;
  lhShowOfflinePill(off);
  if (off) {
    /* Best effort: say it NOW, while the link may still deliver (Wi-Fi switches,
       captive portals). Signed-in pages only — ping.php wants the CSRF token and
       a session. A hard outage drops the beacon on the floor and PRESENCE_TIMEOUT
       answers for it. */
    if (navigator.sendBeacon && document.body.hasAttribute('data-heartbeat')) {
      navigator.sendBeacon('ping.php', new URLSearchParams({ csrf: csrfToken(), v: 'offline' }));
    }
    /* Instant logout, same audience as the beacon: a signed-in page ends the
       session NOW if the server can hear us — a local server can, even with the
       internet off — and parks the intent in localStorage when it cannot, so the
       logout finishes at the first `online`, heartbeat or signed-in page load.
       The session cookie is HttpOnly: this POST to logout.php is the only way
       JavaScript can end a session at all. */
    if (document.body.hasAttribute('data-heartbeat')) {
      lhMarkPendingLogout();
      fetch('logout.php', { method: 'POST', credentials: 'same-origin', keepalive: true })
        .then(() => {
          lhClearPendingLogout(); /* the session is gone — nothing left to finish */
          window.location.replace('login.php?next=' +
            encodeURIComponent(window.location.pathname + window.location.search));
        })
        .catch(() => lhShowSignedOutScreen()); /* the wire ate it: the flag will finish it */
    }
  } else {
    heartbeat();                   /* the wire is back: reappear immediately... */
    setTimeout(heartbeat, 2500);   /* ...and once more, so an offline beacon that
                                      arrives AFTER the reconnect cannot flip a
                                      back-online user straight back to offline */
  }
}
window.addEventListener('offline', () => lhSetOffline(true));
window.addEventListener('online', () => {
  /* A logout still parked from the outage owns this page — finish it first:
     there is no session left to reappear as. */
  if (lhPendingLogout()) { window.location.replace('logout.php'); return; }
  lhSetOffline(false);
});
if (navigator.onLine === false) lhSetOffline(true); /* opened with no connection */
/* The parked logout also finishes when a signed-in tab merely LOADS — the
   outage may have ended with that tab closed. The other half proves it landed:
   a page rendered with no session clears the flag, so the next sign-in starts
   clean. */
if (document.body.hasAttribute('data-heartbeat')) {
  if (lhPendingLogout()) window.location.replace('logout.php');
} else {
  lhClearPendingLogout();
}

/* ---------- presence heartbeat (keep "online") ---------- */
function heartbeat() {
  /* A parked logout owns this page: no presence POST leaves it. If the link is
     already back but the `online` event never fired, this same beat finishes
     the logout instead. */
  if (lhPendingLogout()) {
    if (navigator.onLine !== false) window.location.replace('logout.php');
    return;
  }
  /* And the presence POST itself waits while the browser still says there is no
     internet: a local server would answer anyway and re-mark a user green that
     the offline beacon just dropped. The moment navigator.onLine flips true the
     guard releases — that is also the reconnect path for browsers which never
     fire the `online` event. */
  if (lhOffline && navigator.onLine === false) return;
  fetch('ping.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
    body: 'csrf=' + encodeURIComponent(csrfToken()),
  })
    .then(() => lhSetOffline(navigator.onLine === false)) /* landed: online — unless the browser still insists there is no internet */
    .catch(() => { if (navigator.onLine === false) lhSetOffline(true); });
}
if (document.body.hasAttribute('data-heartbeat')) {
  setInterval(heartbeat, 55000);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') heartbeat();
  });
}

/* Keep one attendance visit open while navigating course content. Close it on
   the next app page outside a course; browser exits are closed by the stale
   presence handler, which records the last heartbeat as the leave time. */
const attendanceMeta = document.querySelector('meta[name="attendance-course"]');
const attendanceCourseKey = 'lh-active-attendance-course';
let previousAttendanceCourse = '';
try {
  previousAttendanceCourse = sessionStorage.getItem(attendanceCourseKey) || '';
} catch (error) {
  /* The stale-presence closer remains the fallback when session storage is unavailable. */
}

if (attendanceMeta) {
  const currentCourseId = attendanceMeta.getAttribute('content') || '';
  if (previousAttendanceCourse && previousAttendanceCourse !== currentCourseId && navigator.sendBeacon) {
    navigator.sendBeacon('attendance.php', new URLSearchParams({
      csrf: csrfToken(), course: previousAttendanceCourse,
    }));
  }
  try {
    sessionStorage.setItem(attendanceCourseKey, currentCourseId);
  } catch (error) {
    /* The stale-presence closer remains the fallback when session storage is unavailable. */
  }
} else if (previousAttendanceCourse && navigator.sendBeacon) {
  navigator.sendBeacon('attendance.php', new URLSearchParams({
    csrf: csrfToken(), course: previousAttendanceCourse,
  }));
  try {
    sessionStorage.removeItem(attendanceCourseKey);
  } catch (error) {
    /* A later page load may safely retry closing this visit. */
  }
}

/* ---------- live durations for open attendance sessions ---------- */
function lhRenderOpenDuration(el) {
  const startedAt = parseInt(el.getAttribute('data-start-at') || '0', 10);
  let seconds = parseInt(el.getAttribute('data-open-seconds') || '0', 10);
  seconds = startedAt > 0
    ? Math.max(0, Math.floor(Date.now() / 1000) - startedAt)
    : seconds + 1;
  el.setAttribute('data-open-seconds', String(seconds));
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const ss = seconds % 60;
  el.textContent = h > 0
    ? h + 'h ' + String(m).padStart(2, '0') + 'm'
    : m + 'm ' + String(ss).padStart(2, '0') + 's';
}

function lhUpdateOpenDurations() {
  document.querySelectorAll('[data-open-seconds]').forEach(lhRenderOpenDuration);
}

lhUpdateOpenDurations();
setInterval(lhUpdateOpenDurations, 1000);

/* ---------- enrollments page: live online status + auto-submitting filters ---------- */
const presenceMeta = document.querySelector('meta[name="presence-ids"]');
if (presenceMeta) {
  const ids = presenceMeta.getAttribute('content').split(',').map((s) => s.trim()).filter(Boolean);
  async function refreshPresence() {
    if (!ids.length) return; /* no offline guard: a local server still answers with the internet down, and a dead link just fails fast */
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
  /* hover chip: the hamburger is icon-only — keep its data-tip matching the real state */
  function setToggleLabel(open) {
    if (!toggle) return;
    var txt = open ? 'Close menu' : 'Open menu';
    toggle.setAttribute('data-tip', txt);
    toggle.setAttribute('aria-label', txt);
  }
  function closeSide() {
    if (sidebar) sidebar.classList.remove('open');
    body.classList.remove('lh-side-open');
    setToggleLabel(false);
  }
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      body.classList.toggle('lh-side-open');
      setToggleLabel(sidebar.classList.contains('open'));
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
    railLabels();
  }
  /* hover chip: both rail buttons are icon-only — the tip must name the
     state they will move TO ("Open sidebar" while folded, "Close sidebar"
     while open) */
  function railLabels() {
    var txt = body.classList.contains('lh-rail') ? 'Open sidebar' : 'Close sidebar';
    [collapseBtn, railBtn].forEach(function (b) {
      if (b) { b.setAttribute('data-tip', txt); b.setAttribute('aria-label', txt); }
    });
  }
  if (document.body.classList.contains('lh-app')) {
    try { if (localStorage.getItem('lh-rail') === '1' && window.innerWidth >= 1024) body.classList.add('lh-rail'); } catch (e) {}
    railLabels();
  }
  var railToggle = function (e) {
    if (e && e.preventDefault) e.preventDefault();
    setRail(!body.classList.contains('lh-rail'));
  };
  if (collapseBtn) collapseBtn.addEventListener('click', railToggle);
  if (railBtn) railBtn.addEventListener('click', railToggle);
  window.addEventListener('resize', function () {
    if (window.innerWidth < 1024) { body.classList.remove('lh-rail'); railLabels(); }
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
  function runOpenTickers(container) {
    (container || document).querySelectorAll('[data-open-seconds]').forEach(function (el) {
      lhRenderOpenDuration(el);
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

  /* ---- Tala's cloud: one thought at a time, typed out ----
     owl_news() in lib.php owns the WORDS and hands over a LIST of one-line
     thoughts; this owns the DELIVERY. Each line of her cloud is a channel that
     carries a single sentence: what she was saying is wiped away, a breath
     passes, then the new sentence is typed out behind a blinking caret. So two
     updates that land close together are never glued into one run-on line — the
     second waits its turn, and while she is busy with the same line the waiting
     text is simply replaced by the newest one.
     The news line also ROTATES: every OWL_ROTATE_MS she moves on to the next
     thought of the list, so the cloud keeps saying something true about what is
     happening — a message unread, a class coming up, who is online, what is left
     to do — instead of sitting there with one frozen tally. Two owners, one
     rhythm: the 10-second poll refills the LIST, this timer alone decides WHEN
     she moves on, so a poll never cuts into a thought you are halfway through
     reading, and the next tick simply picks up the newer words.
     Two details keep the owl from dancing around the banner while she talks: the
     cloud reserves the size of the FINISHED sentence — and keeps the widest it
     has ever reserved, so a shorter thought cannot pull her sideways — and the
     text is held left-aligned while typing so letters land where they will stay
     instead of being shoved back to the middle on every keystroke.
     Under prefers-reduced-motion nothing is typed, nothing is held back and
     nothing rotates: a line silently rewriting itself twice a minute is exactly
     the movement that setting asks for, and assistive tech would announce it.
     Between two thoughts she also CLOSES the cloud: at the OWL_ROTATE_MS tick
     the puff fades away, opens again OWL_AWAY_MS later, and only once it is
     back does the next line start typing — nothing is ever typed into a cloud
     you cannot see, so a poll that lands mid-breath waits its turn. */
  var OWL_ROTATE_MS = 20000;
  var OWL_AWAY_MS = 900;      /* one breath of empty banner between two thoughts */

  function lhOwlCloud() {
    var lines = {
      say: document.querySelector('[data-live-owl-say]'),
      news: document.querySelector('[data-live-owl-news]')
    };
    if (!lines.say && !lines.news) return null;
    var cloud = (lines.news || lines.say).closest('.lh-hero-hi');
    /* What PHP printed, read NOW — before anything has typed over it. The poll's
       first tick lands about 500 ms after load and speak() runs at 700 ms, so
       whoever goes second would otherwise read the other's half-written line and
       push that fragment as a message in its own right: the greeting was once left
       sitting at "Good morn" for the rest of the visit. These two strings — not
       the DOM — are what speak() says. */
    var printed = { say: lines.say ? lines.say.textContent : '', news: lines.news ? lines.news.textContent : '' };
    var aria = document.querySelector('[data-live-owl-aria]');
    var claimed = {}, queue = [], busy = false, active = null;
    /* her queue of thoughts, which one is on screen, and the widest the cloud has
       ever had to be — see reserve() and rotate() — and whether she has taken
       the cloud away for a breath between two thoughts */
    var thoughts = [], thoughtAt = 0, rotating = false, cloudMax = 0, away = false;

    /* PHP printed both lines for visitors without JS. Rather than let them sit
       there and then be backspaced in front of you, the words are held out of
       sight until it is that line's turn to be said. */
    if (!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
      if (lines.say) lines.say.classList.add('lh-owl-hush');
      if (lines.news) lines.news.classList.add('lh-owl-hush');
    }

    function quiet() {
      return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    /* the bubble keeps the finished sentence's size while it is being typed — and
       never lets that size shrink again: her thoughts are different lengths, and a
       shorter one must not slide the owl sideways mid-conversation */
    function reserve(el, text) {
      var was = el.textContent;
      el.style.minHeight = '';
      if (cloud) cloud.style.minWidth = '';
      el.textContent = text;
      var h = el.offsetHeight, w = cloud ? cloud.offsetWidth : 0;
      if (h > 0) el.style.minHeight = h + 'px';
      if (cloud) {
        if (w > cloudMax) cloudMax = w;
        cloud.style.minWidth = cloudMax + 'px';
      }
      el.textContent = was;
    }

    function push(key, text) {
      var el = lines[key];
      text = String(text === null || text === undefined ? '' : text).replace(/^\s+|\s+$/g, '');
      if (!el || !text || claimed[key] === text) return;   /* the same news twice is not news */
      /* A FRAGMENT is not news either. erase() and type() both write prefixes of
         the line they are working on, so anything shorter that matches the front
         of what this line has already claimed is the DOM caught mid-word — the
         snapshot trap described above, closed for good. */
      if (claimed[key] && text.length < claimed[key].length && claimed[key].indexOf(text) === 0) return;
      claimed[key] = text;
      for (var i = 0; i < queue.length; i++) {
        if (queue[i].key === key) { queue[i].text = text; return; }   /* newest wins */
      }
      queue.push({ key: key, text: text });
      pump();
    }

    function pump() {
      if (busy || away) return;              /* a line waits out the breath, never types through it */
      var job = queue.shift();
      if (!job) return;
      var el = lines[job.key];
      busy = true;                                /* she is saying this one now */
      var mine = { el: el, text: job.text, done: false };
      active = mine;
      mine.finish = function () {
        if (mine.done) return;                    /* one ending per message */
        mine.done = true;
        active = null;
        el.classList.remove('lh-owl-typing');
        busy = false;
        pump();
      };
      /* assistive tech is handed each whole sentence in one go; the letters
         themselves go aria-hidden so a screen reader does not read 40 updates */
      if (aria) aria.textContent = job.text;
      if (quiet()) {
        el.removeAttribute('aria-hidden');
        el.textContent = job.text;
        mine.finish();
        return;
      }
      el.setAttribute('aria-hidden', 'true');
      el.classList.remove('lh-owl-hush');
      el.classList.remove('lh-owl-beat');
      void el.offsetWidth;                /* restarting an animation: off, a frame, on */
      el.classList.add('lh-owl-beat');    /* one small nod marks the start of a message */
      reserve(el, job.text);
      el.classList.add('lh-owl-typing');
      erase(mine, function () { type(mine, job.text, mine.finish); });
    }

    /* backspacing the line she just said, then a breath before the next one */
    function erase(mine, then) {
      var text = mine.el.textContent, n = text.length;
      if (!n) { setTimeout(then, 150); return; }
      (function step() {
        if (mine.done) return;              /* ended elsewhere — stop writing */
        n = Math.max(0, n - (n > 16 ? 2 : 1));
        mine.el.textContent = text.slice(0, n);
        if (n > 0) setTimeout(step, 16);
        else setTimeout(then, 190);
      })();
    }

    /* the typing itself: quick enough to read at a glance, slow enough to notice,
       with a small rest after punctuation so a two-part update reads as two */
    function type(mine, text, then) {
      if (mine.done) return;
      var speed = Math.max(13, Math.min(38, Math.round(1400 / Math.max(1, text.length))));
      var i = 0;
      (function step() {
        if (mine.done) return;              /* ended elsewhere — stop writing */
        i++;
        mine.el.textContent = text.slice(0, i);
        if (i >= text.length) { setTimeout(then, 260); return; }
        var ch = text.charAt(i - 1);
        setTimeout(step, speed + (',.·!?;:'.indexOf(ch) > -1 ? 120 : (ch === ' ' ? 9 : 0)));
      })();
    }

    /* a background tab slows timers to a crawl, which would leave her stranded
       mid-word; coming back finishes the sentence in one go rather than typing on */
    document.addEventListener('visibilitychange', function () {
      if (!active || document.visibilityState !== 'visible') return;
      active.el.textContent = active.text;
      active.finish();
    });

    /** Refill the queue of one-line thoughts — owl_news()['thoughts'], from the
     *  banner's JSON or from the poll. Only the LIST changes here: the timer below
     *  alone decides when she moves on to the next one, so a refresh can never
     *  rewrite the thought you are halfway through reading. */
    function think(list) {
      if (!list || !list.length) return;
      var clean = [];
      for (var i = 0; i < list.length; i++) {
        var t = String(list[i] === null || list[i] === undefined ? '' : list[i]).replace(/^\s+|\s+$/g, '');
        if (t) clean.push(t);
      }
      if (!clean.length) return;
      thoughts = clean;
      if (thoughtAt >= thoughts.length) thoughtAt = 0;
      start();
    }

    /* the rhythm: this thought, then the next, wrapped round so she keeps company
       rather than running dry. A hidden tab is not an audience — it spends none.
       And between the two she CLOSES the cloud (lh-owl-away in assets/hero.css):
       it fades away at the tick, opens again OWL_AWAY_MS later, and only once it
       is back does the next thought start typing — nothing is ever written into
       a cloud you cannot see, and a poll that lands mid-breath waits its turn in
       the queue until she reopens. */
    function rotate() {
      if (quiet()) return;
      if (document.visibilityState === 'hidden') return;
      if (thoughts.length < 2) return;
      if (!cloud) {                                /* no cloud to close: as you were */
        thoughtAt = (thoughtAt + 1) % thoughts.length;
        push('news', thoughts[thoughtAt]);
        return;
      }
      away = true;
      cloud.classList.add('lh-owl-away');
      setTimeout(function () {
        away = false;
        cloud.classList.remove('lh-owl-away');
        pump();                                    /* whatever waited out the breath */
        if (document.visibilityState === 'hidden') return;   /* a hidden tab spends none */
        thoughtAt = (thoughtAt + 1) % thoughts.length;
        push('news', thoughts[thoughtAt]);
      }, OWL_AWAY_MS);
    }

    function start() {
      if (rotating || quiet()) return;
      rotating = true;
      setInterval(rotate, OWL_ROTATE_MS);
    }

    /* PHP printed the whole queue for visitors without JS; read it back so the
       rotation works from page load and not only once the first poll comes in */
    var seed = document.querySelector('[data-live-owl-thoughts]');
    if (seed) {
      try { think(JSON.parse(seed.textContent || '[]')); } catch (e) { /* no rotation, no harm */ }
    }

    /* the sizes reserved above were measured in the viewport they belonged to */
    window.addEventListener('resize', function () {
      cloudMax = 0;
      if (cloud) cloud.style.minWidth = '';
      for (var k in lines) { if (lines[k]) lines[k].style.minHeight = ''; }
    });

    return {
      push: push,
      think: think,
      /* PHP already printed both lines for visitors without JS; say those words
         once — greeting first, then the first of her thoughts — and keep the list
         turning from here on. The words come from `printed`, captured at load, and
         never from the live DOM: by now the poll may already be typing, and a
         half-written line is not a thing to say. */
      speak: function () {
        push('say', printed.say);
        push('news', printed.news);
        start();
      }
    };
  }

  /* ---------------- dashboard graph hover tooltips ---------------- */
  /* The graphs are plain inline SVGs whose per-day invisible strips carry the
     day label and values as data attributes (embedded by lib.php). Listeners
     are delegated on document, so they keep working when the live poll replaces
     the SVG markup every few seconds. */
  var chartTip = null;
  function chartTipShow(col, px, py) {
    if (!chartTip) {
      chartTip = document.createElement('div');
      chartTip.className = 'lh-chart-tip';
      document.body.appendChild(chartTip);
    }
    var html = '<p class="lh-chart-tip-day">' + lmsEsc(col.getAttribute('data-day')) + '</p>';
    for (var k = 1; k <= 2; k++) {
      var val = col.getAttribute(k === 1 ? 'data-a' : 'data-b');
      if (val === null) break;
      html += '<p class="lh-chart-tip-row"><i style="background:' + col.getAttribute('data-c' + k) + '"></i>'
        + '<span>' + lmsEsc(col.getAttribute('data-n' + k)) + '</span><b>' + lmsEsc(val) + '</b></p>';
    }
    chartTip.innerHTML = html;
    chartTip.classList.add('lh-chart-tip-on');
    var r = chartTip.getBoundingClientRect();
    var x = px + 14, y = py + 14;
    if (x + r.width > window.innerWidth - 8) x = px - r.width - 14;
    if (y + r.height > window.innerHeight - 8) y = py - r.height - 14;
    chartTip.style.left = x + 'px';
    chartTip.style.top = y + 'px';
  }
  function chartTipHide() {
    if (chartTip) chartTip.classList.remove('lh-chart-tip-on');
    document.querySelectorAll('svg.lh-chart.lh-chart-on').forEach(function (s) { s.classList.remove('lh-chart-on'); });
  }
  document.addEventListener('mousemove', function (e) {
    var t = e.target;
    var col = t && t.closest ? t.closest('.js-chart-col') : null;
    if (!col) { chartTipHide(); return; }
    var svg = col.ownerSVGElement;
    var cx = parseFloat(col.getAttribute('x')) + parseFloat(col.getAttribute('width')) / 2;
    var guide = svg.querySelector('.js-chart-guide');
    if (guide) { guide.setAttribute('x1', cx); guide.setAttribute('x2', cx); }
    svg.querySelectorAll('.js-chart-dot').forEach(function (dot) {
      dot.setAttribute('cx', cx);
      dot.setAttribute('cy', dot.getAttribute('data-k') === '1' ? col.getAttribute('data-ay') : col.getAttribute('data-by'));
    });
    document.querySelectorAll('svg.lh-chart-on').forEach(function (s) { if (s !== svg) s.classList.remove('lh-chart-on'); });
    svg.classList.add('lh-chart-on');
    chartTipShow(col, e.clientX, e.clientY);
  });

  /* ---------------- dashboard (teacher + student) ---------------- */
  if (scope === 'teacher-dash' || scope === 'student-dash') {
    var dashUrl = scope === 'teacher-dash' ? 'realtime.php?v=dash' : 'realtime.php?v=dash-student';
    /* Tala arrives, then speaks: the delay lets the cloud's entrance finish
       before the first sentence is typed out. */
    var owl = lhOwlCloud();
    if (owl) setTimeout(function () { owl.speak(); }, 700);
    /* One row's circle — the photo when there is one, the coloured initial when
       there is not — built exactly like user_avatar_html() prints it server-side,
       so the first live refresh paints the same face the first paint did. */
    function dashPeerCircle(r, size) {
      var name = String(r.name || '');
      if (r.avatar) {
        return '<img src="' + lmsEsc(r.avatar) + '" alt="' + lmsEsc(name ? 'Profile picture of ' + name : 'Profile picture')
          + '" loading="lazy" decoding="async" class="lh-avatar-img ' + size + ' rounded-full">';
      }
      return '<span class="lh-avatar-initial grid ' + size + ' place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white" aria-hidden="true">'
        + lmsEsc((name.charAt(0) || '?').toUpperCase()) + '</span>';
    }
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
      /* ---- Tala's cloud ----
         owl_news() owns the words, lhOwlCloud owns the delivery, so the cloud and
         the tiles underneath it can never disagree. A refresh REPLENISHES her
         queue of one-line thoughts; it never picks which one is on screen — that
         is the 20-second rotation's job, so the poll cannot rewrite a sentence
         you are halfway through. (A payload with only 'news' still works.) */
      if (d.owl && owl) {
        if (d.owl.say) owl.push('say', d.owl.say);
        owl.think(d.owl.thoughts && d.owl.thoughts.length ? d.owl.thoughts : (d.owl.news ? [d.owl.news] : []));
      }
      if (scope !== 'teacher-dash') return;

      /* live students */
      var onl = document.querySelector('[data-live-list="online"]');
      if (onl) {
        onl.innerHTML = d.online.length
          ? d.online.map(function (u) {
            /* mirrors dashboard.php's first paint: photo circle + presence dot,
               the name carries the profile-card trigger, then the ago text */
            return '<li class="flex items-center gap-2.5">'
              + '<span class="relative shrink-0">' + dashPeerCircle(u, 'h-8 w-8')
              + '<span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white"></span></span>'
              + '<span class="min-w-0 flex-1 truncate text-sm font-medium text-slate-700"' + (u.hover || '') + '>' + lmsEsc(u.name) + '</span>'
              + '<span class="shrink-0 text-[11px] text-slate-400">' + lmsAgo(u.last_seen) + '</span></li>';
          }).join('')
          : '<li class="px-4 py-4 text-center text-sm text-slate-400">No students online right now.</li>';
      }
      /* today's visits */
      var vis = document.querySelector('[data-live-list="visits"]');
      if (vis) {
        vis.innerHTML = d.visits.length
          ? d.visits.map(function (r) {
            return '<li class="flex items-center gap-2 text-sm">'
              + dashPeerCircle(r, 'h-8 w-8')
              + '<span class="min-w-0 flex-1 truncate font-medium text-slate-700"' + (r.hover || '') + '>' + lmsEsc(r.name) + '</span>'
              + '<span class="hidden min-w-0 max-w-[7rem] flex-1 truncate text-xs text-slate-400 sm:block">' + lmsEsc(r.course) + '</span>'
              + '<span class="shrink-0 rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">' + lmsClock(r.entered_at) + (r.left_at ? ' – ' + lmsClock(r.left_at) : ' …') + '</span></li>';
          }).join('')
          : '<li class="px-4 py-4 text-center text-sm text-slate-400">No visits recorded today yet.</li>';
      }
      /* recent activity */
      var act = document.querySelector('[data-live-list="activity"]');
      if (act) {
        act.innerHTML = d.activity.length
          ? d.activity.map(function (r) {
            return '<li class="flex items-start gap-2.5 text-sm">'
              + dashPeerCircle({ name: r.who, avatar: r.avatar }, 'h-7 w-7')
              + '<span class="min-w-0 flex-1"><span class="block truncate text-slate-700"' + (r.hover || '') + '><b class="font-semibold">' + lmsEsc(r.who) + '</b> '
              + (r.kind === 'enrolled' ? 'enrolled in' : 'completed') + ' '
              + (r.lesson ? '<b class="font-semibold">' + lmsEsc(r.lesson) + '</b> · ' : '')
              + '<span class="text-slate-500">' + lmsEsc(r.course) + '</span></span>'
              + '<span class="text-[11px] text-slate-400">' + lmsAgo(r.ts) + '</span></span></li>';
          }).join('')
          : '<li class="px-4 py-4 text-center text-sm text-slate-400">No recent activity.</li>';
      }
      /* needs attention */
      var att = document.querySelector('[data-live-list="attention"]');
      if (att) {
        att.innerHTML = d.attention.length
          ? d.attention.map(function (s) {
            /* two stacked rows like dashboard.php prints: name row, then the
               progress row indented to the name (pl-9 clears the h-7 circle) */
            return '<li><div class="flex items-center gap-2 text-sm">'
              + dashPeerCircle(s, 'h-7 w-7')
              + '<span class="min-w-0 flex-1 truncate font-medium text-slate-700"' + (s.hover || '') + '>' + lmsEsc(s.name) + '</span>'
              + '<span class="shrink-0 text-[11px] font-bold text-slate-500">' + s.pct + '%</span></div>'
              + '<div class="mt-1 flex items-center gap-2 pl-9"><div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">'
              + '<div class="h-full rounded-full bg-rose-400" style="width:' + s.pct + '%"></div></div>'
              + '<span class="min-w-0 truncate text-[10px] text-slate-400">' + s.done + '/' + s.total + ' · ' + lmsEsc(s.course) + '</span></div></li>';
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
        /* The attendance column: the roster scope in realtime.php simply omits the
           figures for anyone the viewer may not read them for (a student's
           classmates), so there is nothing here to write and nothing invented —
           the cell PHP printed for that row stays as it is. */
        var visits = tr.querySelector('[data-visits]');
        if (visits && typeof u.visits === 'number') {
          var html = '<span class="font-semibold text-slate-800">' + u.visits + '</span> visits';
          if (u.last_at) html += '<span class="block text-xs text-slate-400">last ' + lmsAgo(u.last_at) + '</span>';
          visits.innerHTML = html;
        }
        var more = tr.querySelector('[data-more]');
        if (more) more.textContent = u.more_courses > 0 ? '+' + u.more_courses + ' more' : '';
      });
      var logBody = document.getElementById('att-log-body');
      if (logBody && d.attCourse) {
        /* attendance log — ownOnly comes back set when this viewer is a student
           looking at their own record, and then the table has no IP column at
           all, because the addresses were never sent (see realtime.php) */
        var showIp = !d.ownOnly;
        var cnt = document.getElementById('att-log-count');
        if (cnt) cnt.textContent = d.attLog.length;
        logBody.innerHTML = d.attLog.length
          ? d.attLog.map(function (a) {
            var left = a.left_at ? lmsClock(a.left_at) : (a.online ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">🟢 Online</span>' : '<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">⏳ In course</span>');
            return '<tr><td class="px-4 py-3 font-semibold text-slate-900">' + lmsEsc(a.name) + (a.removed ? ' <span class="inline-block rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-600">Removed</span>' : '') + '</td><td class="px-4 py-3 text-slate-600">' + new Date(a.entered_at * 1000).toLocaleString([], { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) + '</td><td class="px-4 py-3 text-slate-600">' + left + '</td><td class="px-4 py-3 text-slate-600">' + (a.left_at ? lmsDur(a.left_at - a.entered_at) : '<span data-open-seconds="' + Math.max(0, Math.floor(Date.now() / 1000) - a.entered_at) + '" data-start-at="' + a.entered_at + '" data-mark="d' + a.id + '" class="font-semibold text-emerald-700"></span>') + '</td>' + (showIp ? '<td class="hidden px-4 py-3 text-slate-400 md:table-cell">' + lmsEsc(a.ip) + '</td>' : '') + '</tr>';
          }).join('')
          : '<tr><td class="px-4 py-6 text-center text-sm text-slate-400" colspan="' + (showIp ? 5 : 4) + '">No attendance recorded for this course yet.</td></tr>';
        runOpenTickers(logBody);   /* freshly-rendered open rows need their per-second ticker restarted */
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
      /* The IP column of the redrawn table exists only where PHP drew it in the
         first place (data-show-ip on the section) and only while the payload
         carries addresses at all — for a student, neither is true, and a column
         of blanks would be worse than no column. */
      var showIp = !d.ownOnly && liveBox.getAttribute('data-show-ip') !== '0';
      var rows = d.records.map(function (r) {
        /* the circle and the card the first paint printed, redrawn: the payload
           carries the picture this viewer is entitled to (blank when they are not,
           or when no file stands behind the row — never link a picture that would
           404) and the hover-card attributes lib.php itself would have printed
           ('' for the viewer's own row). user_avatar_html()'s two shapes exactly:
           the photo circle, or the coloured initial when there is none. */
        var circle = r.avatar
          ? '<img src="' + lmsEsc(r.avatar) + '" alt="' + lmsEsc(r.name ? 'Profile picture of ' + r.name : 'Profile picture') + '" loading="lazy" decoding="async" class="lh-avatar-img h-8 w-8 rounded-full">'
          : '<span class="lh-avatar-initial grid h-8 w-8 place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white" aria-hidden="true">' + lmsEsc((String(r.name || '').charAt(0) || '?').toUpperCase()) + '</span>';
        var student = '<div class="flex items-center gap-2">' + circle + '<div class="min-w-0"><p class="truncate font-semibold text-slate-900">' + lmsEsc(r.name) + (r.removed ? ' <span class="inline-block rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-600">Removed</span>' : '') + '</p><p class="text-xs text-slate-400">' + (r.online ? '🟢 online' : 'offline') + '</p></div></div>';
        /* the shared hover card listens on the document, so the rebuilt cell only
           needs the same wrapper attrs the first paint carried to keep its card */
        if (r.hover) student = '<div class="lh-hcard lh-hcard-block"' + r.hover + '>' + student + '</div>';
        return '<tr>' +
          '<td class="px-4 py-3 font-semibold text-slate-900">' + lmsClock(r.entered_at) + '</td>' +
          '<td class="px-4 py-3">' + student + '</td>' +
          '<td class="px-4 py-3"><span class="inline-block rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">' + lmsEsc(r.course_title) + '</span><span class="block text-xs text-slate-400">' + lmsEsc(r.course_category) + '</span></td>' +
          '<td class="px-4 py-3">' + (r.open ? (r.online ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span></span> Online</span>' : '<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">⏳ In course</span>') : '<span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">⏹ Left ' + lmsClock(r.left_at) + '</span>') + '</td>' +
          '<td class="px-4 py-3 text-slate-600">' + (r.open ? '<span data-open-seconds="' + Math.max(0, Math.floor(Date.now() / 1000) - r.entered_at) + '" data-start-at="' + r.entered_at + '" data-mark="d' + r.id + '" class="font-semibold text-emerald-700"></span>' : lmsDur(r.duration)) + '</td>' +
          (showIp ? '<td class="hidden px-4 py-3 text-slate-400 md:table-cell">' + lmsEsc(r.ip) + '</td>' : '') + '</tr>';
      }).join('');
      sec.innerHTML = '<div class="mt-4 overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"><table class="w-full text-left text-sm"><thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3 font-semibold">Entered</th><th class="px-4 py-3 font-semibold">Student</th><th class="px-4 py-3 font-semibold">Course</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 font-semibold">Time spent</th>' + (showIp ? '<th class="hidden px-4 py-3 font-semibold md:table-cell">IP address</th>' : '') + '</tr></thead><tbody class="divide-y divide-slate-100">' + rows + '</tbody></table></div>';
      runOpenTickers(sec);
    });
    return;
  }

  /* ---------------- course page: students online now + live class ---------------- */
  if (scope === 'course-online') {
    var cid = liveBox.getAttribute('data-course-id') || '0';
    var chip = document.getElementById('course-online-chip');
    livePoll('realtime.php?v=course&id=' + encodeURIComponent(cid), 15000, function (d) {
      if (!chip) return;
      chip.innerHTML = '<span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span></span> <span id="course-online-count">' + d.count + '</span> online now' + (d.students && d.students.length ? ': ' + d.students.slice(0, 3).map(lmsEsc).join(', ') + (d.students.length > 3 ? '…' : '') : '');
    });

    /* live class: poll state so the banner appears/disappears without a reload */
    var lcCsrf = document.querySelector('meta[name="csrf"]').content;
    function lcPost(data) {
      var body = new URLSearchParams(Object.assign({ csrf: lcCsrf, course: cid }, data));
      return fetch('live_class.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      }).then(function (r) { return r.json(); });
    }
    var lcBox = document.getElementById('live-class-box');
    var banner = document.getElementById('live-class-banner');
    var liveSeen = !!banner; /* avoid a redundant immediate poll */
    function lcPoll() {
      fetch('live_class.php?v=state&course=' + encodeURIComponent(cid), { headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) return;
          if (d.live && !banner) location.reload(); /* class started while we were here */
          if (!d.live && banner) location.reload(); /* class ended while we were here */
        }).catch(function () { });
    }
    if (lcBox) setInterval(lcPoll, 8000);
    var startBtn = document.getElementById('lc-start');
    if (startBtn) startBtn.addEventListener('click', function () {
      startBtn.disabled = true;
      startBtn.textContent = 'Starting…';
      lcPost({ v: 'start', title: 'Live class' }).then(function (d) {
        if (d.ok) location.href = 'class_room.php?course=' + encodeURIComponent(cid);
        else { startBtn.disabled = false; startBtn.textContent = '🔴 Start live class'; showToast(d.error || 'Could not start the class', 'error'); }
      }).catch(function () { startBtn.disabled = false; startBtn.textContent = '🔴 Start live class'; });
    });
    var endBtn2 = document.getElementById('lc-end');
    if (endBtn2) endBtn2.addEventListener('click', function () {
      if (!window.confirm('End the live class for everyone?')) return;
      lcPost({ v: 'end' }).then(function () { location.reload(); });
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

/* ================================================================
   Mobile "peek" lists — the dashboard panels (Live now, Attendance
   today, Needs attention, Recent activity) arrive with every row the
   server sent, and on a phone that is a wall of text. Phones keep the
   newest five rows and put the rest behind an open/close control:
   opening a panel caps it at five rows and scrolling inside streams
   the remaining rows in, a batch at a time.

   The realtime poll rewrites each <ul>'s markup every ~10s, so a
   MutationObserver re-applies the limit to the rows that just landed
   (the newest five stay on screen). Retune a list with data-peek="<n>"
   / data-peek-step="<n>", or opt out with data-no-peek. Desktop
   (>=1024px, where the panels keep their own max-h cap) is untouched.
   ================================================================ */
(function () {
  var MQ = '(max-width: 1023.98px)';   /* the edge the themes' `lg:` caps use */
  var PEEK = 5, STEP = 5, REVEAL_AT = 36;

  var lists = [].slice.call(document.querySelectorAll('main [data-live-list]'));
  if (!lists.length) return;
  var mq = window.matchMedia ? window.matchMedia(MQ) : null;

  function positive(el, attr, fallback) {
    var v = parseInt(el.getAttribute(attr), 10);
    return isFinite(v) && v > 0 ? v : fallback;
  }

  lists.forEach(function (ul) {
    if (ul.hasAttribute('data-no-peek')) return;
    var peek = positive(ul, 'data-peek', PEEK);
    var step = positive(ul, 'data-peek-step', STEP);
    var show = peek;    /* rows the reader may see right now */
    var open = false;   /* has the panel been opened into its scroll box? */

    /* The control rides with the list but lives OUTSIDE it: the poll
       replaces the <ul>'s markup, and a button in there would be wiped. */
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'lh-peek-btn mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50';
    btn.hidden = true;
    btn.setAttribute('aria-expanded', 'false');
    var label = document.createElement('span');
    var caret = document.createElement('span');
    caret.className = 'lh-peek-caret';
    caret.setAttribute('aria-hidden', 'true');
    caret.textContent = '▾';
    btn.appendChild(label);
    btn.appendChild(caret);
    ul.insertAdjacentElement('afterend', btn);

    function rows() { return [].slice.call(ul.children); }

    /* Height of a full peek: the first `peek` rows plus the gaps between
       them. Measured, not guessed, so a theme or density that reshapes
       rows still opens to exactly `peek` visible rows. */
    function peekHeight(lis) {
      var h = 0, prevBottom = null, n = Math.min(peek, lis.length);
      for (var i = 0; i < n; i++) {
        var r = lis[i].getBoundingClientRect();
        if (r.height <= 0) return 0;                       /* hidden ancestor: keep the CSS default */
        if (prevBottom !== null) h += r.top - prevBottom;   /* the space-y-* gap */
        h += r.height;
        prevBottom = r.bottom;
      }
      return Math.ceil(h) + 2;                             /* clear of the clip edge */
    }

    function apply() {
      var lis = rows(), total = lis.length;
      var limited = total > peek && (!mq || mq.matches);
      var visible = open ? show : peek;

      if (!limited) {                     /* desktop, short list, or opted out */
        lis.forEach(function (li) { li.classList.remove('lh-peek-off', 'lh-peek-in'); });
        ul.classList.remove('lh-peek-box');
        ul.style.removeProperty('--lh-peek-h');
        if (ul.scrollTop) ul.scrollTop = 0;
        btn.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
        return;
      }

      if (open) {
        ul.classList.add('lh-peek-box');
        var h = peekHeight(lis);
        if (h) ul.style.setProperty('--lh-peek-h', h + 'px');
      } else {
        ul.classList.remove('lh-peek-box');
        ul.style.removeProperty('--lh-peek-h');
      }

      lis.forEach(function (li, i) {
        var was = li.classList.contains('lh-peek-off');
        var off = i >= visible;
        li.classList.toggle('lh-peek-off', off);
        if (!off && was && open) {        /* streamed in: rise, then settle */
          li.classList.add('lh-peek-in');
          setTimeout(function () { li.classList.remove('lh-peek-in'); }, 400);
        }
      });

      btn.hidden = false;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      label.textContent = open ? 'Show less' : 'Show all ' + total;
    }

    btn.addEventListener('click', function () {
      open = !open;
      /* opening reveals the next batch; anything still hiding streams in
         as the panel is scrolled */
      show = open ? Math.min(ul.children.length, peek + step) : peek;
      apply();
    });

    ul.addEventListener('scroll', function () {
      if (!open || show >= ul.children.length) return;
      if (ul.scrollTop + ul.clientHeight < ul.scrollHeight - REVEAL_AT) return;
      show = Math.min(ul.children.length, show + step);
      apply();
    }, { passive: true });

    /* the poll swaps the rows out from under us — re-apply to the new ones */
    if (window.MutationObserver) new MutationObserver(apply).observe(ul, { childList: true });

    /* row heights follow the width: re-measure after a rotate/resize */
    var reflow = null;
    window.addEventListener('resize', function () {
      if (reflow) clearTimeout(reflow);
      reflow = setTimeout(function () { reflow = null; apply(); }, 150);
    });

    var onMQ = function () {
      if (mq && !mq.matches) { open = false; show = peek; }   /* back to the wide layout: reset */
      apply();
    };
    if (mq) {
      if (mq.addEventListener) mq.addEventListener('change', onMQ);
      else if (mq.addListener) mq.addListener(onMQ);
    }

    apply();
  });
})();

/* ---------- profile hover cards ----------
   lib.php marks up the accounts a viewer is allowed to see with data-hcard and
   the words to show; this builds ONE card, fills it from those attributes and
   puts it beside the name. One card because a roster of two hundred students
   must not mean two hundred hidden boxes, and because a card inside a table cell
   or a scrolling list gets clipped (a transformed ancestor also turns a fixed
   element into a positioned one, which is why it is re-parented to <body> first,
   as modals are).

   Everything is built with textContent, never innerHTML: the about line is words
   a person typed about themselves, and words must stay words.

   Nothing here fetches anything — if the server was willing to name this person
   to you, the words were already in the page. */
(function () {
  var pop = null, openBy = null, pending = null, openTimer = null, hideTimer = null;

  function card() {
    if (pop) return pop;
    pop = document.createElement('div');
    pop.id = 'lh-hcard-pop';
    pop.className = 'lh-hcard-pop';
    pop.setAttribute('role', 'tooltip');
    pop.hidden = true;
    document.body.appendChild(pop);
    /* staying open while the pointer travels from the name into the card is what
       makes an about line readable instead of a flash */
    pop.addEventListener('mouseenter', cancelHide);
    pop.addEventListener('mouseleave', function () { hide(false); });
    return pop;
  }

  function cancelHide() { if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; } }
  function cancelOpen() { if (openTimer) { clearTimeout(openTimer); openTimer = null; } }

  function place(el) {
    var r = el.getBoundingClientRect(), pad = 10;
    var w = pop.offsetWidth, h = pop.offsetHeight;
    var left = Math.max(pad, Math.min(r.left - w - 10, window.innerWidth - w - pad));
    /* prefer the left of the name (a list reads better that way); when the name
       sits too near the left edge, drop the card under it instead */
    if (left < pad) left = Math.max(pad, Math.min(r.left, window.innerWidth - w - pad));
    var top = r.top + r.height / 2 - h / 2;
    top = Math.max(pad, Math.min(top, window.innerHeight - h - pad));
    pop.style.left = Math.round(left) + 'px';
    pop.style.top = Math.round(top) + 'px';
  }

  function initial(name) {
    var s = document.createElement('span');
    s.className = 'lh-hcard-init';
    s.setAttribute('aria-hidden', 'true');
    s.textContent = (name || '?').trim().charAt(0).toUpperCase() || '?';
    return s;
  }

  function show(el) {
    var d = el.dataset, name = d.hcName || 'Account';
    cancelHide(); cancelOpen();
    if (openBy && openBy !== el) openBy.classList.remove('is-open');
    openBy = el;
    el.classList.add('is-open');
    var p = card();
    if (p.parentElement !== document.body) document.body.appendChild(p);
    p.textContent = '';

    var pic = d.hcPic || '';
    if (pic) {
      var img = document.createElement('img');
      img.className = 'lh-hcard-pic';
      img.src = pic;
      img.alt = 'Profile picture of ' + name;
      img.decoding = 'async';
      /* a picture deleted between the page being built and this hover should
         still leave a circle, not a torn image icon */
      img.onerror = function () { img.replaceWith(initial(name)); };
      p.appendChild(img);
    } else {
      p.appendChild(initial(name));
    }

    var box = document.createElement('div');
    box.className = 'lh-hcard-txt';
    var nm = document.createElement('span');
    nm.className = 'lh-hcard-name';
    nm.textContent = name;
    box.appendChild(nm);
    if (d.hcRole) {
      var role = document.createElement('span');
      role.className = 'lh-hcard-role';
      role.textContent = d.hcRole;
      box.appendChild(role);
    }
    var bio = document.createElement('p');
    bio.className = 'lh-hcard-bio';
    bio.textContent = d.hcBio || 'No about line yet.';
    box.appendChild(bio);
    if (d.hcJoined) {
      var jn = document.createElement('p');
      jn.className = 'lh-hcard-join';
      jn.textContent = d.hcJoined;
      box.appendChild(jn);
    }
    p.appendChild(box);

    p.hidden = false;
    place(el);                                  /* measured once visible, then nudged into view */
    p.classList.add('is-shown');
    el.setAttribute('aria-describedby', p.id);
  }

  function hide(now) {
    cancelOpen();
    if (!openBy) return;
    if (hideTimer) clearTimeout(hideTimer);
    var run = function () {
      if (openBy) {
        openBy.classList.remove('is-open');
        openBy.removeAttribute('aria-describedby');
      }
      openBy = null;
      if (!pop) return;
      pop.classList.remove('is-shown');
      hideTimer = setTimeout(function () { if (!openBy) { pop.hidden = true; hideTimer = null; } }, 160);
    };
    /* the short delay on leaving a name lets the pointer cross into the card */
    if (now === true) run(); else hideTimer = setTimeout(run, 110);
  }

  function hoverable(node) {
    return (node && node.closest) ? node.closest('[data-hcard]') : null;
  }

  document.addEventListener('pointerover', function (e) {
    var el = hoverable(e.target);
    if (!el || el === openBy) return;
    pending = el;
    cancelHide();
    if (openTimer) return;                        /* one name into the next: a timer already runs */
    /* a sweep of the mouse down a list must not flash a card for every row */
    openTimer = setTimeout(function () { openTimer = null; if (pending) show(pending); }, 140);
  });

  document.addEventListener('pointerout', function (e) {
    var el = hoverable(e.target);
    if (!el) return;
    if (hoverable(e.relatedTarget) === el) return;    /* moved to a child of the same name */
    if (pop && e.relatedTarget && pop.contains(e.relatedTarget)) return;   /* into the card itself */
    cancelOpen();
    pending = null;
    hide(false);
  });

  /* the keyboard gets the same card, immediately — a delay there only feels laggy */
  document.addEventListener('focusin', function (e) {
    var el = hoverable(e.target);
    if (!el) { if (openBy) hide(true); return; }
    cancelOpen();
    show(el);
  });
  document.addEventListener('focusout', function (e) {
    if (hoverable(e.target)) hide(true);
  });

  document.addEventListener('click', function (e) {
    if (!openBy) return;
    if (hoverable(e.target) || (pop && pop.contains(e.target))) return;
    hide(true);                                       /* a tap anywhere else closes it */
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(true); });
  window.addEventListener('resize', function () { if (openBy) place(openBy); });
  /* capture, so a card beside a name inside a scrolling list box follows it */
  document.addEventListener('scroll', function () { if (openBy) place(openBy); }, { passive: true, capture: true });
})();
