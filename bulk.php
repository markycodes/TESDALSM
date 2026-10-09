<?php
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = '';
$userId = (int) $user['id'];

$courseId = (int) ($_GET['c'] ?? $_POST['course'] ?? 0);
$course = $courseId > 0 ? course_row($courseId) : null;
if (!$course) {
    set_flash('error', 'Course not found.');
    header('Location: courses.php');
    exit;
}
if (!assignment_can_manage($user, $courseId)) {
    set_flash('error', 'You can only organise your own courses.');
    header('Location: ' . lh_url_clean('course.php?id=' . $courseId));
    exit;
}

ensure_course_main_folder($courseId);
$folders = course_folders($courseId);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    /* Only ids that really belong to THIS course are ever used, so a crafted id
       list cannot reach another course's lessons. */
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])),
        function ($id) use ($courseId) { return $id > 0 && course_material_exists($courseId, $id); }));

    if ($action === 'move_folder' && $ids) {
        $targetFolderId = (int) ($_POST['target_folder_id'] ?? 0);
        $targetFolder = course_folder_row($courseId, $targetFolderId);
        if (!$targetFolder) {
            set_flash('error', 'Choose a folder in this course.');
        } else {
            $move = db()->prepare('UPDATE materials SET folder_id = ? WHERE course_id = ? AND id = ?');
            foreach ($ids as $id) $move->execute([$targetFolderId, $courseId, $id]);
            set_flash('success', count($ids) . ' lesson(s) moved to ' . (string) $targetFolder['name'] . '.');
        }
    } elseif (($action === 'move_end' || $action === 'move_start' || $action === 'delete') && $ids) {
        if ($action === 'delete') {
            $n = 0;
            foreach ($ids as $id) {
                $m = delete_material_row($courseId, $id);   /* cascades quizzes + discussions */
                if (!$m) continue;
                if (in_array($m['type'], ['file', 'video'], true) && !empty($m['filename'])) {
                    delete_uploaded_file((string) $m['filename']);
                }
                $n++;
            }
            set_flash('success', $n . ' lesson(s) deleted.');
        } else {
            /* Reordering renumbers the WHOLE course 0,1,2… in its new order
               rather than poking one row. That keeps the order total and
               explicit: there is never a lesson sitting at "unsorted" next to
               numbered ones, which is what made a naive single-row update sort
               the moved lesson to the front instead of the back. */
            $current = [];
            foreach (load_courses() as $lc) {
                if ((int) $lc['id'] === $courseId) { $current = $lc['materials'] ?? []; break; }
            }
            $sel = array_flip($ids);
            $picked = []; $rest = [];
            foreach ($current as $m) {
                if (isset($sel[(int) $m['id']])) $picked[] = $m; else $rest[] = $m;
            }
            $new = $action === 'move_start' ? array_merge($picked, $rest) : array_merge($rest, $picked);
            $upd = db()->prepare('UPDATE materials SET sort_order = ? WHERE id = ? AND course_id = ?');
            foreach ($new as $n => $m) $upd->execute([$n + 1, (int) $m['id'], $courseId]);
            set_flash('success', count($ids) . ' lesson(s) moved to the ' . ($action === 'move_start' ? 'top' : 'end') . '.');
        }
    }

    header('Location: ' . lh_url_clean('bulk.php?c=' . $courseId));
    exit;
}

/* the lessons, in the order the course page prints them */
/* The quizzes table keys on material_id, like every other table that hangs off a
   lesson. It said lesson_id here, which is not a column — the page died with
   "Unknown column" every time it was opened. */
$st = db()->prepare('SELECT m.*, f.name AS folder_name,
                            (SELECT COUNT(*) FROM quizzes q WHERE q.material_id = m.id) AS has_quiz
                     FROM materials m
                     LEFT JOIN course_folders f ON f.id = m.folder_id AND f.course_id = m.course_id
                     WHERE m.course_id = ? ORDER BY m.sort_order ASC, m.id ASC');
$st->execute([$courseId]);
$lessons = $st->fetchAll();

$page_title = 'Organise lessons — ' . (string) $course['title'];
require __DIR__ . '/header.php';
$back = lh_url_clean('course.php?id=' . $courseId);
$typeIcon = ['file' => '📄', 'video' => '🎬', 'youtube' => '▶️', 'text' => '📝'];
?>
<div class="mx-auto max-w-3xl">
  <div class="reveal flex flex-wrap items-center justify-between gap-3">
    <div>
      <a href="<?= $back ?>" class="text-xs font-semibold text-slate-400 hover:text-emerald-700">← <?= e((string) $course['title']) ?></a>
      <h1 class="mt-1 text-2xl font-bold text-slate-900">🗂️ Organise lessons</h1>
    </div>
    <a href="<?= $back ?>" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Back to course</a>
  </div>

  <?php if (!$lessons): ?>
    <p class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-sm text-slate-400">This course has no lessons yet.</p>
  <?php else: ?>
  <form method="post" class="reveal mt-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <?= csrf_field() ?>
    <input type="hidden" name="course" value="<?= $courseId ?>">
    <p class="text-xs text-slate-500">Tick the lessons you want to act on, then choose what to do. You can move selected lessons into any folder in this course. Deleting a lesson also removes its quiz and its discussion.</p>

    <ul class="mt-4 space-y-2">
      <?php foreach ($lessons as $l): ?>
      <li class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5">
        <input type="checkbox" name="ids[]" value="<?= (int) $l['id'] ?>" class="h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600">
        <span class="shrink-0 text-lg"><?= $typeIcon[$l['type']] ?? '📘' ?></span>
        <span class="min-w-0 flex-1 leading-tight">
          <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $l['title']) ?></span>
          <span class="block text-[11px] text-slate-400">
            <?= e((string) ($l['folder_name'] ?? 'Main folder')) ?> ·
            <?php if ((int) ($l['has_quiz'] ?? 0) > 0): ?>has a quiz · <?php endif; ?>
            <?= lesson_post_count((int) $l['id']) ?> discussion post(s)
          </span>
        </span>
      </li>
      <?php endforeach; ?>
    </ul>

    <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
      <label class="sr-only" for="target-folder-id">Move selected lessons to folder</label>
      <select id="target-folder-id" name="target_folder_id" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">
        <?php foreach ($folders as $folder): ?>
          <option value="<?= (int) $folder['id'] ?>">📁 <?= e((string) $folder['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button name="action" value="move_folder" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Move to folder</button>
      <button name="action" value="move_start" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Move to top</button>
      <button name="action" value="move_end" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Move to end</button>
      <button name="action" value="delete" data-confirm="Delete the selected lessons, their quizzes and their discussions? This cannot be undone."
        class="rounded-xl border border-rose-200 px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Delete selected</button>
    </div>
  </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php';
