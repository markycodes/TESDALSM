<?php
/**
 * Material reader — opens course documents directly in the browser.
 * PDFs and images use the browser's native viewer; Office files (DOCX/PPTX/XLSX)
 * are rendered client-side by an in-page viewer; pasted text renders as formatted HTML.
 * No server-side text extraction, and students never need to download anything.
 * Reading progress (scroll depth + active seconds) auto-completes the lesson.
 */
require_once __DIR__ . '/lib.php';

$user = require_login();
$userId = (int) $user['id'];
$courseId = (int) ($_GET['c'] ?? 0);
$materialId = (int) ($_GET['m'] ?? 0);

$course = course_row($courseId);
if (!$course) { http_response_code(404); exit('Course not found.'); }
$isOwner = (int) $course['teacher_id'] === $userId;
if (!$isOwner && !is_enrolled_id($courseId, $userId)) {
    http_response_code(403);
    exit('Enroll in this course to read its materials.');
}

$material = get_material($courseId, $materialId);
if (!$material || ($material['type'] ?? '') !== 'file') {
    http_response_code(404);
    exit('Material not found.');
}

$payload = read_material_payload($material);
$kind = (string) $payload['kind'];                  // pdf | image | text | doc | missing | unsupported
$viewer = (string) ($payload['viewer'] ?? 'none');  // pdf | image | markdown | docx | pptx | xlsx | text | none
$minSeconds = (int) $payload['minSeconds'];
$trackable = !in_array($kind, ['missing', 'unsupported'], true);

$state = material_user_states($userId, $courseId)[$materialId] ?? [];
$depth = (int) ($state['depth'] ?? 0);
$seconds = (int) ($state['seconds'] ?? 0);
$done = material_completed($userId, $materialId);
$ext = strtolower(ext_of((string) ($material['orig_name'] ?? $material['filename'] ?? '')));
$fileSrc = 'download.php?c=' . $courseId . '&m=' . $materialId . '&disp=inline';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title>Reading: <?= e((string) $material['title']) ?> · LearnHub</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif']}}}}</script>
<style>html{scroll-behavior:smooth}</style>
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800">
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur">
  <div class="mx-auto flex h-14 max-w-5xl items-center justify-between gap-3 px-4">
    <a href="course.php?id=<?= (int) $courseId ?>" class="shrink-0 rounded-lg px-2 py-1.5 text-sm font-semibold text-indigo-600 hover:bg-indigo-50">← Back to course</a>
    <div class="min-w-0 flex-1 px-2 text-center">
      <p class="truncate text-sm font-bold text-slate-900"><?= e((string) $material['title']) ?></p>
      <p class="truncate text-xs text-slate-500"><?= e((string) $course['title']) ?> · <?= e((string) ($course['teacher_name'] ?? '')) ?></p>
    </div>
    <span id="read-badge" class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold <?= $done ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-50 text-indigo-700' ?>"><?= $done ? '✓ Completed' : 'Read ' . $depth . '%' ?></span>
  </div>
  <div class="h-1 w-full bg-slate-200"><div id="read-bar" class="h-full bg-indigo-600 transition-all duration-300" style="width: <?= $depth ?>%"></div></div>
</header>
<main class="mx-auto max-w-4xl px-4 py-8">
<?php if ($kind === 'missing'): ?>
  <div class="rounded-2xl bg-white p-8 text-center ring-1 ring-slate-200">
    <p class="text-4xl">🕳️</p>
    <h1 class="mt-3 text-lg font-bold text-slate-900">File is missing on the server</h1>
    <p class="mt-1 text-sm text-slate-500">The material was uploaded, but its file can no longer be found. Please contact your teacher.</p>
  </div>
<?php elseif ($kind === 'unsupported'): ?>
  <div class="rounded-2xl bg-white p-8 text-center ring-1 ring-slate-200">
    <p class="text-4xl">📦</p>
    <h1 class="mt-3 text-lg font-bold text-slate-900">This file can't be previewed in the browser</h1>
    <p class="mt-1 text-sm text-slate-500">In-page formats: PDF · DOCX · PPTX · XLSX · TXT · MD · CSV · images.</p>
<?php if ($isOwner): ?>
    <a href="<?= e($fileSrc) ?>" class="mt-4 inline-block rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">⬇ Download original</a>
<?php endif; ?>
  </div>
<?php elseif ($kind === 'pdf'): ?>
  <p class="mb-3 rounded-xl bg-indigo-50 px-4 py-3 text-sm text-indigo-800 ring-1 ring-indigo-100">📖 The document opens below — go through it at a normal pace. Progress is tracked automatically.</p>
  <embed src="<?= e($fileSrc) ?>" type="application/pdf" class="h-[85vh] w-full rounded-2xl bg-white ring-1 ring-slate-200">
<?php elseif ($kind === 'image'): ?>
  <p class="mb-3 rounded-xl bg-indigo-50 px-4 py-3 text-sm text-indigo-800 ring-1 ring-indigo-100">🖼 Scroll through the image below — progress is tracked automatically.</p>
  <img src="<?= e($fileSrc) ?>" alt="<?= e((string) $material['title']) ?>" class="mx-auto max-w-full rounded-2xl bg-white ring-1 ring-slate-200">
<?php elseif ($kind === 'text'): ?>
  <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-10">
    <div class="mx-auto max-w-2xl space-y-4 text-[15px] leading-7 text-slate-700"><?= $payload['html'] ?></div>
  </article>
<?php else: ?>
  <p class="mb-3 rounded-xl bg-indigo-50 px-4 py-3 text-sm text-indigo-800 ring-1 ring-indigo-100">📄 The document opens right here on the website — nothing to download. Go through it at a normal pace; progress is tracked automatically.</p>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:p-8">
    <div id="doc-viewer" data-viewer="<?= e($viewer) ?>" data-ext="<?= e($ext) ?>" data-src="<?= e($fileSrc) ?>">
      <p class="py-16 text-center text-sm font-medium text-slate-400">Opening document…</p>
    </div>
  </div>
<?php endif; ?>
<?php if ($isOwner): ?>
  <p class="mt-6 text-center"><a href="<?= e($fileSrc) ?>" class="text-xs font-semibold text-slate-400 hover:text-indigo-600">⬇ Download original file</a></p>
<?php endif; ?>
  <p class="mt-8 text-center text-xs text-slate-400">Reading progress is tracked automatically — reach the end at a normal pace to complete this material.</p>
</main>
<div id="read-pill" class="fixed bottom-4 right-4 z-40 rounded-full bg-slate-900/90 px-4 py-2 text-xs font-semibold text-white shadow-lg">📖 Read <span id="read-pct"><?= $depth ?></span>% · <span id="read-time"><?= $seconds ?></span>s</div>
<?php if ($kind === 'doc'): ?>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script>
(function () {
  var box = document.getElementById('doc-viewer');
  if (!box) return;
  var viewer = box.getAttribute('data-viewer');
  var src = box.getAttribute('data-src');
  var ext = box.getAttribute('data-ext');
  function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
  function fail(msg) {
    box.innerHTML = '<div class="py-12 text-center"><p class="text-3xl">😕</p><p class="mt-2 text-sm font-semibold text-slate-700">' + msg + '</p><p class="mt-1 text-xs text-slate-400">Ask your teacher to also provide a PDF version for the smoothest in-browser reading.</p></div>';
  }
  function sheetTable(rows) {
    var t = '<table class="mt-2 w-full border-collapse text-xs"><tbody>';
    rows.forEach(function (r, i) {
      t += '<tr' + (i === 0 ? ' class="bg-slate-50 font-semibold"' : '') + '>';
      r.forEach(function (c) { t += '<td class="border border-slate-200 px-2 py-1 align-top">' + (c === '' ? '&nbsp;' : c) + '</td>'; });
      t += '</tr>';
    });
    return t + '</tbody></table>';
  }
  function renderText(raw) {
    if (ext === 'csv') {
      var rows = raw.replace(/\r/g, '').split('\n').filter(function (l) { return l.trim() !== ''; })
        .map(function (l) { return l.split(',').map(esc); });
      box.innerHTML = rows.length ? sheetTable(rows) : '<p class="py-12 text-center text-sm text-slate-400">(empty file)</p>';
    } else {
      box.innerHTML = '<pre class="whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-5 text-[13px] leading-6 text-slate-700">' + esc(raw) + '</pre>';
    }
  }
  function num(n) { var m = String(n).match(/(\d+)\.\w+$/); return m ? parseInt(m[1], 10) : 0; }
  function renderZip(z) {
    if (viewer === 'docx') {
      return z.file('word/document.xml').async('string').then(function (xml) {
        var paras = xml.match(/<w:p(?:>| [^>]*>)[\s\S]*?<\/w:p>/g) || [];
        if (!paras.length) return fail('(no readable text found in this document)');
        var html = '';
        paras.forEach(function (p) {
          var runs = p.match(/<w:t[^>]*>[\s\S]*?<\/w:t>/g) || [];
          var line = runs.map(function (t) { return t.replace(/^<w:t[^>]*>/, '').replace(/<\/w:t>$/, ''); }).join('');
          if (!line.trim()) { html += '<div class="h-3"></div>'; return; }
          var tag = 'p', cls = 'leading-7 text-slate-700';
          if (/<w:pStyle[^>]*w:val="(?:Heading1|Title)"/.test(p)) { tag = 'h2'; cls = 'mt-6 mb-2 text-2xl font-bold text-slate-900'; }
          else if (/<w:pStyle[^>]*w:val="Heading2"/.test(p)) { tag = 'h2'; cls = 'mt-5 mb-2 text-xl font-bold text-slate-900'; }
          else if (/<w:pStyle[^>]*w:val="Heading3"/.test(p)) { tag = 'h3'; cls = 'mt-4 mb-1 text-lg font-semibold text-slate-900'; }
          html += '<' + tag + ' class="' + cls + '">' + line + '</' + tag + '>';
        });
        box.innerHTML = html;
      });
    }

    if (viewer === 'pptx') {
      var slides = Object.keys(z.files).filter(function (n) { return /^ppt\/slides\/slide\d+\.xml$/.test(n); })
        .sort(function (a, b) { return num(a) - num(b); });
      if (!slides.length) return fail('(no slides found in this presentation)');
      return Promise.all(slides.map(function (n) { return z.file(n).async('string'); })).then(function (xmls) {
        var html = '';
        xmls.forEach(function (xml, i) {
          var lines = [];
          (xml.match(/<a:p(?:>| [^>]*>)[\s\S]*?<\/a:p>/g) || []).forEach(function (p) {
            var runs = p.match(/<a:t[^>]*>[\s\S]*?<\/a:t>/g) || [];
            var line = runs.map(function (t) { return t.replace(/^<a:t[^>]*>/, '').replace(/<\/a:t>$/, ''); }).join('');
            if (line.trim()) lines.push('<p class="leading-6 text-slate-700">' + line + '</p>');
          });
          html += '<section class="mb-4 rounded-xl border border-slate-200 p-5 shadow-sm"><p class="mb-2 text-xs font-bold uppercase tracking-wide text-indigo-500">Slide ' + (i + 1) + '</p>' + (lines.join('') || '<p class="text-sm text-slate-400">(blank slide)</p>') + '</section>';
        });
        box.innerHTML = html;
      });
    }
    if (viewer === 'xlsx') {
      var ssf = z.file('xl/sharedStrings.xml');
      var ssP = ssf ? ssf.async('string') : Promise.resolve('<sst></sst>');
      return ssP.then(function (ss) {
        var shared = [];
        (ss.match(/<si>[\s\S]*?<\/si>/g) || []).forEach(function (si) {
          var parts = si.match(/<t[^>]*>[\s\S]*?<\/t>/g) || [];
          shared.push(parts.map(function (t) { return t.replace(/^<t[^>]*>/, '').replace(/<\/t>$/, ''); }).join(''));
        });
        var sheets = Object.keys(z.files).filter(function (n) { return /^xl\/worksheets\/sheet\d+\.xml$/.test(n); })
          .sort(function (a, b) { return num(a) - num(b); });
        if (!sheets.length) return fail('(no worksheets found in this workbook)');
        return Promise.all(sheets.map(function (n) { return z.file(n).async('string'); })).then(function (xmls) {
          var html = '';
          xmls.forEach(function (xml, i) {
            var rows = [];
            (xml.match(/<row(?:>| [^>]*>)[\s\S]*?<\/row>/g) || []).forEach(function (rx) {
              var cells = [];
              (rx.match(/<c(?:>| [^>]*>)[\s\S]*?<\/c>|<c[^>]*\/>/g) || []).forEach(function (cx) {
                var vm = cx.match(/<v>([\s\S]*?)<\/v>/), val = '';
                if (/t="s"/.test(cx) && vm) val = shared[parseInt(vm[1], 10)] || '';
                else if (/t="inlineStr"/.test(cx)) { var im = cx.match(/<t[^>]*>([\s\S]*?)<\/t>/); val = im ? im[1] : ''; }
                else if (vm) val = vm[1];
                cells.push(val);
              });
              rows.push(cells);
            });
            while (rows.length && rows[rows.length - 1].join('') === '') rows.pop();
            html += '<p class="mt-5 mb-1 text-xs font-bold uppercase tracking-wide text-indigo-500">Sheet ' + (i + 1) + '</p>' + sheetTable(rows);
          });
          box.innerHTML = html;
        });
      });
    }
    fail('Unsupported document type.');
  }
  fetch(src, { credentials: 'same-origin' }).then(function (res) {
    if (!res.ok) throw new Error('http ' + res.status);
    if (viewer === 'text') return res.text().then(renderText);
    return res.arrayBuffer().then(function (buf) { return JSZip.loadAsync(buf); }).then(renderZip);
  }).catch(function () { fail('Could not open this document in the browser.'); });
})();
</script>
<?php endif; ?>
<script>
(function () {
  var csrfEl = document.querySelector('meta[name="csrf"]');
  var csrf = csrfEl ? csrfEl.content : '';
  var courseId = '<?= (int) $courseId ?>';
  var materialId = '<?= (int) $materialId ?>';
  var minSeconds = <?= max(5, $minSeconds) ?>;
  var tracking = <?= $trackable ? 'true' : 'false' ?>;
  var isOwner = <?= $isOwner ? 'true' : 'false' ?>;
  var maxDepth = <?= $depth ?>;
  var seconds = <?= $seconds ?>;
  var completed = <?= $done ? 'true' : 'false' ?>;
  var pctEl = document.getElementById('read-pct');
  var timeEl = document.getElementById('read-time');
  var barEl = document.getElementById('read-bar');
  var badgeEl = document.getElementById('read-badge');
  function depthNow() {
    var doc = document.documentElement;
    var scrollable = doc.scrollHeight - window.innerHeight;
    if (scrollable <= 4) return 100;
    return Math.min(100, Math.max(0, Math.round((window.scrollY / scrollable) * 100)));
  }
  function render() {
    if (pctEl) pctEl.textContent = maxDepth;
    if (timeEl) timeEl.textContent = seconds;
    if (barEl) barEl.style.width = maxDepth + '%';
    if (badgeEl && !completed) badgeEl.textContent = 'Read ' + maxDepth + '%';
  }
  function toast(msg) {
    var t = document.createElement('div');
    t.className = 'fixed bottom-16 right-4 z-50 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 3200);
  }
  setInterval(function () {
    if (document.visibilityState !== 'visible') return;
    seconds++;
    maxDepth = Math.max(maxDepth, depthNow());
    render();
  }, 1000);
  window.addEventListener('scroll', function () { maxDepth = Math.max(maxDepth, depthNow()); render(); }, { passive: true });
  function payload() {
    return new URLSearchParams({ csrf: csrf, course: courseId, material: materialId, depth: String(maxDepth), seconds: String(seconds), min: String(minSeconds) });
  }
  async function send() {
    if (!tracking || isOwner || completed) return;
    try {
      var res = await fetch('read_progress.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' }, body: payload().toString() });
      var data = await res.json();
      if (data.ok && data.complete && !completed) {
        completed = true; render();
        if (badgeEl) { badgeEl.textContent = '✓ Completed'; badgeEl.className = 'shrink-0 rounded-full px-3 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700'; }
        toast('✓ Material completed — course progress ' + data.pct + '%');
      }
    } catch (e) {}
  }
  setInterval(send, 5000);
  window.addEventListener('pagehide', function () { if (!tracking || isOwner || completed) return; navigator.sendBeacon('read_progress.php', payload()); });
  render();
})();
</script>
</body>
</html>



