<?php
$folder = $renderFolder;
$folderId = (int) $folder['id'];
$folderProgress = $folderProgressById[$folderId] ?? ['total' => 0, 'done' => 0, 'pct' => 0];
$folderQuiz = $folderQuizzes[$folderId] ?? null;
$folderQuizCount = $folderQuiz ? count($folderQuiz['questions']) : 0;
$folderLessons = array_values(array_filter(
  $course['materials'] ?? [],
  fn($material) => (int) ($material['folder_id'] ?? 0) === $folderId
));
$folderVideos = array_values(array_filter(
  $folderLessons,
  fn($material) => in_array($material['type'] ?? '', ['video', 'youtube'], true)
));
$folderMaterials = array_values(array_filter(
  $folderLessons,
  fn($material) => ($material['type'] ?? '') === 'file'
));
$defaultFolderTab = $folderVideos ? 'videos' : 'materials';
?>
<details data-folder-progress data-folder-id="<?= $folderId ?>"<?= $isOwner ? ' data-course-id="' . (int) $courseId . '"' : '' ?>
         class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
  <summary<?= $isOwner ? ' data-folder-drop-target="' . $folderId . '"' : '' ?>
           class="flex cursor-pointer list-none flex-wrap items-center gap-3 rounded-2xl p-4 hover:bg-indigo-50 [&::-webkit-details-marker]:hidden">
    <span class="text-lg" data-folder-chevron>📁</span>
    <span class="min-w-0 flex-1 font-bold text-slate-900"><?= e((string) $folder['name']) ?></span>
    <?php if ($enrolled): ?>
      <span data-folder-progress-text class="text-xs text-slate-500"><?= (int) $folderProgress['done'] ?> / <?= (int) $folderProgress['total'] ?> lessons complete · <?= (int) $folderProgress['pct'] ?>%</span>
    <?php endif; ?>
    <span class="text-xs font-semibold text-indigo-600">Click to open ▾</span>
  </summary>
  <div class="ml-12 mt-2 space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 px-4 pb-4 pt-3 sm:px-6">
    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Lessons</h3>
    <div class="flex flex-wrap items-center gap-2">
      <?php if ($isOwner): ?>
        <button type="button" data-modal-open="lesson-modal" data-folder-select="<?= $folderId ?>" data-lesson-tab="vid"
          class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">＋ Add Videos</button>
        <button type="button" data-modal-open="lesson-modal" data-folder-select="<?= $folderId ?>" data-lesson-tab="doc"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">＋ Add Materials</button>
        <span class="text-xs text-slate-500">Drag a lesson onto another folder to move it.</span>
      <?php endif; ?>
      <span class="text-xs text-slate-500"><?= count($folderVideos) ?> video<?= count($folderVideos) === 1 ? '' : 's' ?> · <?= count($folderMaterials) ?> material<?= count($folderMaterials) === 1 ? '' : 's' ?></span>
    </div>
    <div data-folder-tabset class="space-y-3">
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Folder lesson types">
            <button type="button" role="tab" data-folder-tab-button="videos" aria-controls="folder-<?= $folderId ?>-videos" aria-selected="<?= $defaultFolderTab === 'videos' ? 'true' : 'false' ?>"
                    class="rounded-lg px-4 py-2 text-sm font-semibold <?= $defaultFolderTab === 'videos' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-white' ?>">🎬 Videos (<?= count($folderVideos) ?>)</button>
            <button type="button" role="tab" data-folder-tab-button="materials" aria-controls="folder-<?= $folderId ?>-materials" aria-selected="<?= $defaultFolderTab === 'materials' ? 'true' : 'false' ?>"
                    class="rounded-lg px-4 py-2 text-sm font-semibold <?= $defaultFolderTab === 'materials' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-white' ?>">📄 Materials (<?= count($folderMaterials) ?>)</button>
        </div>
      <section id="folder-<?= $folderId ?>-videos" data-folder-tab-pane="videos" role="tabpanel" class="<?= $defaultFolderTab === 'videos' ? '' : 'hidden' ?> space-y-2" aria-label="Videos in <?= e((string) $folder['name']) ?>">
        <?php if ($folderVideos): ?>
          <div class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <?php foreach ($folderVideos as $folderLesson):
          $folderLessonId = (int) $folderLesson['id'];
          $folderLessonDone = in_array($folderLessonId, $progressSet, true);
          $videoState = $lessonStates[$folderLessonId] ?? [];
          $videoPercent = min(100, max(0, (int) ($videoState['percent'] ?? 0)));
          $lessonQuiz = $lessonQuizzes[$folderLessonId] ?? null;
          $lessonQuizCount = $lessonQuiz ? count($lessonQuiz['questions']) : 0;
        ?>
          <div class="flex flex-wrap items-center gap-2 px-3 py-2.5 hover:bg-indigo-50">
            <a href="video_lesson.php?c=<?= $courseId ?>&amp;m=<?= $folderLessonId ?>"<?= $isOwner ? ' draggable="true" data-movable-lesson="' . $folderLessonId . '" data-course-id="' . (int) $courseId . '"' : '' ?>
               data-folder-lesson="<?= $folderLessonId ?>"
               data-folder-id="<?= $folderId ?>"
               data-folder-done="<?= $folderLessonDone ? '1' : '0' ?>"
               class="flex min-w-0 flex-1 items-center gap-3 text-sm<?= $isOwner ? ' cursor-grab active:cursor-grabbing' : '' ?>">
              <span>🎬</span>
              <span class="min-w-0 flex-1">
                <span class="block truncate font-medium text-slate-700"><?= e((string) $folderLesson['title']) ?></span>
                <span class="mt-1.5 block w-full overflow-hidden rounded-full bg-slate-300" style="height: 8px" role="progressbar" aria-label="Video watched" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $folderLessonDone ? 100 : $videoPercent ?>">
                  <span data-video-progress="<?= $folderLessonId ?>" class="block h-full rounded-full bg-indigo-600 transition-all" style="width: <?= $folderLessonDone ? 100 : $videoPercent ?>%"></span>
                </span>
              </span>
              <span data-lesson-state="<?= $folderLessonId ?>" data-video-progress-label class="shrink-0 text-xs <?= $folderLessonDone ? 'font-semibold text-emerald-700' : 'text-slate-600' ?>"><?= $folderLessonDone ? '✓ Complete' : $videoPercent . '% watched' ?></span>
            </a>
            <?php if ($isOwner): ?>
              <button type="button" data-modal-open="quiz-modal" data-quiz-edit="<?= $folderLessonId ?>"
                      data-title="<?= e((string) $folderLesson['title']) ?>"
                      class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold <?= $lessonQuiz ? 'text-indigo-700 hover:bg-indigo-50' : 'text-slate-500 hover:bg-slate-50' ?>">🧪 <?= $lessonQuiz ? 'Quiz (' . $lessonQuizCount . ')' : 'Add quiz' ?></button>
              <template data-quiz-template="<?= $folderLessonId ?>" data-has-quiz="<?= $lessonQuiz ? '1' : '0' ?>" data-quiz-title="<?= e((string) ($lessonQuiz['title'] ?? '')) ?>" data-pass="<?= $lessonQuiz ? (int) $lessonQuiz['pass_score'] : 60 ?>"><?php
                if ($lessonQuiz) {
                  foreach ($lessonQuiz['questions'] as $qi => $question) {
                    echo quiz_question_row_html(['prompt' => $question['prompt'], 'options' => $question['options'], 'correct' => (int) $question['correct'], 'option_explanations' => (array) ($question['option_explanations'] ?? [])], $qi + 1);
                  }
                }
              ?></template>
              <?php if ($lessonQuiz): ?>
                <form method="post" action="quiz_delete.php" data-confirm="Remove the quiz assigned to this video lesson?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                  <input type="hidden" name="material_id" value="<?= $folderLessonId ?>">
                  <button class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Remove quiz</button>
                </form>
              <?php endif; ?>
              <form method="post" action="delete_material.php"
                    data-confirm="Delete this video and its quiz, discussion, and progress data? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                <input type="hidden" name="material_id" value="<?= $folderLessonId ?>">
                <button class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Delete video</button>
              </form>
            <?php elseif ($lessonQuiz && ($enrolled || $isAdmin)): ?>
              <?php if ($isAdmin || $folderLessonDone): ?>
                <a data-lesson-quiz-link="<?= $folderLessonId ?>" href="quiz.php?c=<?= $courseId ?>&amp;m=<?= $folderLessonId ?>" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">🧪 Lesson quiz (<?= $lessonQuizCount ?>)</a>
              <?php else: ?>
                <a data-lesson-quiz-link="<?= $folderLessonId ?>" href="quiz.php?c=<?= $courseId ?>&amp;m=<?= $folderLessonId ?>" class="hidden rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">🧪 Lesson quiz (<?= $lessonQuizCount ?>)</a>
                <span data-lesson-quiz-lock="<?= $folderLessonId ?>" class="text-xs text-slate-500" title="Complete this lesson to unlock its quiz">🔒 Quiz</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-4 text-center text-sm text-slate-500">No videos in this folder yet.</p>
        <?php endif; ?>
      </section>
      <section id="folder-<?= $folderId ?>-materials" data-folder-tab-pane="materials" role="tabpanel" class="<?= $defaultFolderTab === 'materials' ? '' : 'hidden' ?> space-y-2" aria-label="Materials in <?= e((string) $folder['name']) ?>">
        <?php if ($folderMaterials): ?>
          <div class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
          <?php foreach ($folderMaterials as $folderLesson):
            $folderLessonId = (int) $folderLesson['id'];
            $folderLessonDone = in_array($folderLessonId, $progressSet, true);
            $materialState = $lessonStates[$folderLessonId] ?? [];
            $readPercent = min(100, max(0, (int) ($materialState['depth'] ?? 0)));
            $lessonQuiz = $lessonQuizzes[$folderLessonId] ?? null;
            $lessonQuizCount = $lessonQuiz ? count($lessonQuiz['questions']) : 0;
          ?>
            <div class="flex flex-wrap items-center gap-2 px-3 py-2.5 hover:bg-indigo-50">
              <a href="read.php?c=<?= $courseId ?>&amp;m=<?= $folderLessonId ?>"<?= $isOwner ? ' draggable="true" data-movable-lesson="' . $folderLessonId . '" data-course-id="' . (int) $courseId . '"' : '' ?>
                 data-folder-lesson="<?= $folderLessonId ?>"
                 data-folder-id="<?= $folderId ?>"
                 data-folder-done="<?= $folderLessonDone ? '1' : '0' ?>"
                 class="flex min-w-0 flex-1 items-center gap-3 text-sm<?= $isOwner ? ' cursor-grab active:cursor-grabbing' : '' ?>">
                <span>📄</span>
                <span class="min-w-0 flex-1 truncate font-medium text-slate-700"><?= e((string) $folderLesson['title']) ?></span>
                <span data-lesson-state="<?= $folderLessonId ?>" class="shrink-0 text-xs <?= $folderLessonDone ? 'font-semibold text-emerald-700' : 'text-slate-500' ?>"><?= $folderLessonDone ? '✓ Complete' : ($readPercent > 0 ? $readPercent . '% read' : 'Open material') ?></span>
              </a>
              <?php if ($isOwner): ?>
                <button type="button" data-modal-open="quiz-modal" data-quiz-edit="<?= $folderLessonId ?>"
                        data-title="<?= e((string) $folderLesson['title']) ?>"
                        class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold <?= $lessonQuiz ? 'text-indigo-700 hover:bg-indigo-50' : 'text-slate-500 hover:bg-slate-50' ?>">🧪 <?= $lessonQuiz ? 'Quiz (' . $lessonQuizCount . ')' : 'Add quiz' ?></button>
                <template data-quiz-template="<?= $folderLessonId ?>" data-has-quiz="<?= $lessonQuiz ? '1' : '0' ?>" data-quiz-title="<?= e((string) ($lessonQuiz['title'] ?? '')) ?>" data-pass="<?= $lessonQuiz ? (int) $lessonQuiz['pass_score'] : 60 ?>"><?php
                  if ($lessonQuiz) {
                    foreach ($lessonQuiz['questions'] as $qi => $question) {
                      echo quiz_question_row_html(['prompt' => $question['prompt'], 'options' => $question['options'], 'correct' => (int) $question['correct'], 'option_explanations' => (array) ($question['option_explanations'] ?? [])], $qi + 1);
                    }
                  }
                ?></template>
                <?php if ($lessonQuiz): ?>
                  <form method="post" action="quiz_delete.php" data-confirm="Remove the quiz assigned to this material?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                    <input type="hidden" name="material_id" value="<?= $folderLessonId ?>">
                    <button class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Remove quiz</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="delete_material.php"
                      data-confirm="Delete this material and its quiz, discussion, and progress data? This cannot be undone.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                  <input type="hidden" name="material_id" value="<?= $folderLessonId ?>">
                  <button class="rounded-lg border border-rose-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Delete material</button>
                </form>
              <?php elseif ($lessonQuiz && ($enrolled || $isAdmin)): ?>
                <?php if ($isAdmin || $folderLessonDone): ?>
                  <a data-lesson-quiz-link="<?= $folderLessonId ?>" href="quiz.php?c=<?= $courseId ?>&amp;m=<?= $folderLessonId ?>" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">🧪 Lesson quiz (<?= $lessonQuizCount ?>)</a>
                <?php else: ?>
                  <a data-lesson-quiz-link="<?= $folderLessonId ?>" href="quiz.php?c=<?= $courseId ?>&amp;m=<?= $folderLessonId ?>" class="hidden rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">🧪 Lesson quiz (<?= $lessonQuizCount ?>)</a>
                  <span data-lesson-quiz-lock="<?= $folderLessonId ?>" class="text-xs text-slate-500" title="Complete this lesson to unlock its quiz">🔒 Quiz</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="rounded-xl border border-dashed border-slate-300 bg-white px-3 py-4 text-center text-sm text-slate-500">No materials in this folder yet.</p>
        <?php endif; ?>
      </section>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <?php if ($isOwner): ?>
        <button type="button" data-modal-open="quiz-modal" data-folder-quiz-edit="<?= $folderId ?>"
                data-title="<?= e((string) $folder['name']) ?>"
                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold <?= $folderQuiz ? 'text-indigo-700 hover:bg-indigo-50' : 'text-slate-500 hover:bg-slate-50' ?>">
          🧪 <?= $folderQuiz ? 'Edit folder quiz (' . $folderQuizCount . ')' : 'Assign quiz in this folder' ?>
        </button>
        <form method="post" action="folder_save.php" class="flex items-center gap-1">
          <?= csrf_field() ?>
          <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
          <input type="hidden" name="folder_id" value="<?= $folderId ?>">
          <input name="name" required maxlength="120" value="<?= e((string) $folder['name']) ?>" aria-label="Folder name"
                 class="w-36 rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
          <button class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Rename</button>
        </form>
        <form method="post" action="folder_save.php"
              data-confirm="Delete this folder and permanently remove all its lessons, quizzes, and progress? Uploaded lesson files will also be deleted."
              class="inline-flex">
          <?= csrf_field() ?>
          <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
          <input type="hidden" name="folder_id" value="<?= $folderId ?>">
          <input type="hidden" name="action" value="delete">
          <button class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Delete folder</button>
        </form>
        <?php if ($folderQuiz): ?>
          <form method="post" action="quiz_delete.php" data-confirm="Remove the quiz assigned to this folder?">
            <?= csrf_field() ?>
            <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
            <input type="hidden" name="folder_id" value="<?= $folderId ?>">
            <button class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Remove quiz</button>
          </form>
        <?php endif; ?>
      <?php elseif (($enrolled || $isAdmin) && $folderQuiz): ?>
        <?php if ($isAdmin || ($folderProgress['total'] > 0 && $folderProgress['pct'] >= 100)): ?>
          <a href="quiz.php?c=<?= (int) $course['id'] ?>&amp;f=<?= $folderId ?>"
             data-folder-quiz-link class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">🧪 Folder quiz (<?= $folderQuizCount ?>)</a>
        <?php else: ?>
          <span data-folder-quiz-lock class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500">🔒 Folder quiz unlocks at 100%</span>
          <a href="quiz.php?c=<?= (int) $course['id'] ?>&amp;f=<?= $folderId ?>"
             data-folder-quiz-link class="hidden rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">🧪 Folder quiz (<?= $folderQuizCount ?>)</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <template data-folder-quiz-template="<?= $folderId ?>" data-has-quiz="<?= $folderQuiz ? '1' : '0' ?>"
              data-quiz-title="<?= e((string) ($folderQuiz['title'] ?? '')) ?>"
              data-pass="<?= $folderQuiz ? (int) $folderQuiz['pass_score'] : 60 ?>"><?php
      if ($folderQuiz) {
        foreach ($folderQuiz['questions'] as $qi => $qq) {
          echo quiz_question_row_html(['prompt' => $qq['prompt'], 'options' => $qq['options'], 'correct' => (int) $qq['correct'], 'option_explanations' => (array) ($qq['option_explanations'] ?? [])], $qi + 1);
        }
      }
    ?></template>
  </div>
</details>
