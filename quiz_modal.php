<!-- Assign / edit the quiz of a lesson (teacher only, opened from the lesson cards) -->
<div id="quiz-modal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
  <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
    <div class="flex items-start justify-between">
      <div>
        <h3 class="text-lg font-bold text-slate-900">🧪 Lesson quiz</h3>
        <p class="mt-1 text-sm text-slate-500">Lesson: <b id="quiz-lesson-title">—</b></p>
      </div>
      <button data-modal-close class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">✕</button>
    </div>

    <form method="post" action="quiz_save.php" class="mt-4 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
      <input type="hidden" name="material_id" id="quiz-material-id" value="">
      <div class="grid gap-3 sm:grid-cols-3">
        <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Quiz title
          <input id="quiz-title" name="title" required maxlength="120" placeholder="e.g. Lesson check — key ideas"
            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </label>
        <label class="block text-sm font-medium text-slate-700">Pass score
          <select id="quiz-pass" name="pass_score"
            class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
            <option value="50">50%</option>
            <option value="60">60%</option>
            <option value="70" selected>70%</option>
            <option value="80">80%</option>
            <option value="100">100%</option>
          </select>
        </label>
      </div>

      <div id="quiz-rows" class="space-y-3"></div>

      <button type="button" id="quiz-add-row"
        class="w-full rounded-xl border-2 border-dashed border-slate-300 py-2.5 text-sm font-semibold text-slate-500 hover:border-indigo-300 hover:text-indigo-600">＋ Add question</button>
      <p class="text-xs leading-5 text-slate-400">Mark the correct option for every question (options 3–4 are optional). Students see the quiz only after completing the lesson. Saving replaces the existing quiz and clears its past attempts.</p>

      <div class="flex flex-wrap justify-end gap-2 pt-1">
        <button type="button" data-modal-close
          class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
        <button class="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save quiz</button>
      </div>
    </form>

    <form method="post" action="quiz_delete.php" data-confirm="Remove the quiz assigned to this lesson? Students will no longer see it." class="mt-2">
      <?= csrf_field() ?>
      <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
      <input type="hidden" name="material_id" id="quiz-del-material-id" value="">
      <button type="submit" id="quiz-remove"
        class="hidden w-full rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">🗑 Remove this quiz</button>
    </form>

    <template data-quiz-blank-row><?= quiz_question_row_html(null, 1) ?></template>
  </div>
</div>
