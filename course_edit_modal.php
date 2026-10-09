<?php if (!function_exists('db')) { http_response_code(403); exit('Forbidden'); } /* include-only partial: no direct URL access */ ?>
<div id="course-edit-modal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
  <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
    <div class="flex items-start justify-between">
      <div>
        <h3 class="text-lg font-bold text-slate-900">Edit course details</h3>
        <p class="mt-1 text-sm text-slate-500">Update the title, category and description students see.</p>
      </div>
      <button data-modal-close aria-label="Close" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">✕</button>
    </div>
    <form method="post" action="course_update.php" class="mt-5 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
      <label class="block text-sm font-medium text-slate-700">Course title
        <input name="title" required maxlength="120" value="<?= e((string) $course['title']) ?>"
          class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </label>
      <label class="block text-sm font-medium text-slate-700">Category
        <input name="category" maxlength="60" value="<?= e((string) ($course['category'] ?? 'General')) ?>"
          class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </label>
      <label class="block text-sm font-medium text-slate-700">Description
        <textarea name="description" maxlength="2000" rows="5"
          class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"><?= e((string) ($course['description'] ?? '')) ?></textarea>
      </label>
      <div class="flex justify-end gap-2">
        <button type="button" data-modal-close class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
        <button class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
      </div>
    </form>
  </div>
</div>
