<!-- Create-course modal (teacher only) -->
<div id="course-modal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
  <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
    <div class="flex items-start justify-between">
      <div>
        <h3 class="text-lg font-bold text-slate-900">Create a new course</h3>
        <p class="mt-1 text-sm text-slate-500">You can upload lessons right after creating it.</p>
      </div>
      <button data-modal-close class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">✕</button>
    </div>
    <form method="post" action="course_create.php" class="mt-5 space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="c-title">Course title *</label>
        <input id="c-title" name="title" required maxlength="120" placeholder="e.g. PHP for Beginners"
          class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="c-category">Category</label>
        <input id="c-category" name="category" list="category-list" maxlength="60" placeholder="e.g. Programming"
          class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <datalist id="category-list">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>"><?php endforeach; ?>
        </datalist>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="c-desc">Description</label>
        <textarea id="c-desc" name="description" rows="3" maxlength="2000" placeholder="What will students learn?"
          class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" data-modal-close
          class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
        <button class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Create
          course</button>
      </div>
    </form>
  </div>
</div>