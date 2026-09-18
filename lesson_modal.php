<!-- Add-lesson modal (teacher only) -->
<div id="lesson-modal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
  <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
    <div class="flex items-start justify-between">
      <div>
        <h3 class="text-lg font-bold text-slate-900">Add a lesson</h3>
        <p class="mt-1 text-sm text-slate-500">Upload a document or videos, or paste text — students read &amp; watch it right in the browser.</p>
      </div>
      <button data-modal-close class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">✕</button>
    </div>

    <div class="mt-4 flex gap-1 rounded-xl bg-slate-100 p-1" data-tabs>
      <button type="button" data-tab-btn="doc" aria-selected="true" class="flex-1 rounded-lg px-2 py-2 text-xs font-semibold text-slate-600 aria-selected:bg-white aria-selected:text-indigo-700">📄 Document</button>
      <button type="button" data-tab-btn="paste" aria-selected="false" class="flex-1 rounded-lg px-2 py-2 text-xs font-semibold text-slate-600 aria-selected:bg-white aria-selected:text-indigo-700">✍️ Paste text</button>
      <button type="button" data-tab-btn="vid" aria-selected="false" class="flex-1 rounded-lg px-2 py-2 text-xs font-semibold text-slate-600 aria-selected:bg-white aria-selected:text-indigo-700">🎬 Videos</button>
      <button type="button" data-tab-btn="link" aria-selected="false" class="flex-1 rounded-lg px-2 py-2 text-xs font-semibold text-slate-600 aria-selected:bg-white aria-selected:text-indigo-700">🔗 Links</button>
    </div>

    <?php $inp = 'mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200'; ?>

    <!-- → 1 · Document upload (opens directly in the browser, no extraction) -->
    <form data-tab-pane="doc" method="post" action="upload.php" enctype="multipart/form-data" class="mt-5 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
      <input type="hidden" name="lesson_type" value="document">
      <p class="rounded-xl bg-indigo-50 px-3 py-2 text-xs leading-5 text-indigo-800 ring-1 ring-indigo-100">📄 <b>Upload a document here.</b> Students open it directly on the website — no download, no text extraction.</p>
      <div>
        <label class="block text-sm font-medium text-slate-700">Lesson title *</label>
        <input name="title" required maxlength="120" placeholder="e.g. Week 1 slides" class="<?= $inp ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Short description</label>
        <input name="description" maxlength="200" placeholder="What is this material about?" class="<?= $inp ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Document file * (PDF · DOCX · PPTX · XLSX · TXT · MD · images — up to <?= e(max_upload_label()) ?>)</label>
        <input type="file" name="file" required accept="<?= e('.' . implode(',.', DOC_EXTS)) ?>"
               class="mt-1 w-full rounded-xl border border-slate-300 p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Upload material</button>
    </form>

    <!-- → 2 · Pasted text (no file needed) -->
    <form data-tab-pane="paste" method="post" action="upload.php" class="mt-5 hidden space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
      <input type="hidden" name="lesson_type" value="text">
      <p class="rounded-xl bg-indigo-50 px-3 py-2 text-xs leading-5 text-indigo-800 ring-1 ring-indigo-100">✍️ <b>Paste the whole material here.</b> No file needed — text and Markdown formatting are kept.</p>
      <div>
        <label class="block text-sm font-medium text-slate-700">Lesson title *</label>
        <input name="title" required maxlength="120" placeholder="e.g. Chapter 2 — How variables work" class="<?= $inp ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Short description</label>
        <input name="description" maxlength="200" placeholder="Optional" class="<?= $inp ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Material content * (Markdown supported: # headings, **bold**, lists…)</label>
        <textarea name="content" required rows="10" placeholder="# Lesson notes&#10;&#10;Type or paste your whole material here…"
                  class="mt-1 min-h-48 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"></textarea>
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Add pasted material</button>
    </form>

    <!-- → 3 · Video file upload (several files in one go) -->
    <form data-tab-pane="vid" method="post" action="upload.php" enctype="multipart/form-data" class="mt-5 hidden space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
      <input type="hidden" name="lesson_type" value="video">
      <p class="rounded-xl bg-indigo-50 px-3 py-2 text-xs leading-5 text-indigo-800 ring-1 ring-indigo-100">🎬 <b>Upload your videos here.</b> You can select several files at once — each one becomes its own lesson.</p>
      <div>
        <label class="block text-sm font-medium text-slate-700">Lesson title *</label>
        <input name="title" required maxlength="120" placeholder="e.g. Lesson 3 — Variables" class="<?= $inp ?>">
        <p class="mt-1 text-xs text-slate-400">Extra files are numbered automatically: “Lesson 3 — Variables (2)”, “(3)”…</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Short description</label>
        <input name="description" maxlength="200" placeholder="What do these videos cover?" class="<?= $inp ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Video files * (MP4 · WebM · MOV — hold Ctrl/Cmd to pick several)</label>
        <input id="video-files" type="file" name="file[]" multiple required accept="<?= e('.' . implode(',.', VIDEO_EXTS)) ?>,video/*"
               data-max-bytes="<?= (int) max_upload_bytes() ?>"
               class="mt-1 w-full rounded-xl border border-slate-300 p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
        <p class="mt-1 text-xs text-slate-400">Tip: MP4 (H.264) plays in every browser. Up to <b><?= e(max_upload_label()) ?></b> per file — room for a full 1080p lesson.</p>
        <!-- JS writes into this line only (see app.js) — keep the tip above it separate. -->
        <p id="video-files-note" class="mt-1 text-xs text-slate-400"></p>
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Upload video(s)</button>
    </form>

    <!-- → 4 · Video links (one per line) -->
    <form data-tab-pane="link" method="post" action="upload.php" class="mt-5 hidden space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
      <input type="hidden" name="lesson_type" value="link">
      <p class="rounded-xl bg-indigo-50 px-3 py-2 text-xs leading-5 text-indigo-800 ring-1 ring-indigo-100">🔗 <b>Add video links here.</b> Paste one YouTube / Vimeo link per line — several lessons in one go.</p>
      <div>
        <label class="block text-sm font-medium text-slate-700">Lesson title *</label>
        <input name="title" required maxlength="120" placeholder="e.g. Lesson 4 — Loops (YouTube)" class="<?= $inp ?>">
        <p class="mt-1 text-xs text-slate-400">Extra links are numbered automatically.</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Short description</label>
        <input name="description" maxlength="200" placeholder="Optional" class="<?= $inp ?>">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">YouTube or Vimeo URLs * (one per line)</label>
        <textarea name="urls" required rows="5" placeholder="https://www.youtube.com/watch?v=…&#10;https://vimeo.com/…"
                  class="mt-1 min-h-32 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"></textarea>
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Add video link(s)</button>
    </form>

    <p id="tab-error" class="mt-2 text-xs text-red-600 hidden"></p>
  </div>
</div>
