<?php if (!function_exists('db')) { http_response_code(403); exit('Forbidden'); } /* include-only partial: no direct URL access */ ?>
<div id="folder-create-modal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
  <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
    <div class="flex items-start justify-between">
      <div>
        <h3 class="text-lg font-bold text-slate-900">Add a folder</h3>
        <p class="mt-1 text-sm text-slate-500">New folders are created at the course level, alongside the Main folder.</p>
      </div>
      <button data-modal-close aria-label="Close" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">✕</button>
    </div>
      <form method="post" action="folder_save.php" class="mt-5 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
        <label class="block text-sm font-medium text-slate-700">Folder name
          <input name="name" required maxlength="120" autofocus placeholder="e.g. Module 1"
            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </label>
        <div class="flex justify-end gap-2">
          <button type="button" data-modal-close class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
          <button class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create folder</button>
        </div>
      </form>
  </div>
</div>
