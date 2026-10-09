<?php /** Lesson list — included from course.php when the user can view materials.
 * Progress is automatic: videos count watch time, materials count reading depth/time.
 * Lessons are private: owning teacher + enrolled students only — this guard keeps
 * the list from rendering even if a future caller forgets to check. */
if (!function_exists('db')) { http_response_code(403); exit('Forbidden'); } /* include-only partial: no direct URL access */
if (!can_view_lessons($course, $user)) return;
$progressSet = $course['progress'][$userId] ?? [];
$lessonStates = material_user_states($userId, (int) $course['id']);
$lessonQuizzes = course_quizzes((int) $course['id'], true);
$folderQuizzes = course_folder_quizzes((int) $course['id'], true);
$folderProgressById = ($enrolled || $isAdmin) ? course_folder_progress_map((int) $course['id'], $userId) : [];
?>
<section class="mt-6">
  <div class="mb-5 space-y-3">
    <?php foreach ($folders as $folder):
      $renderFolder = $folder;
    ?>
    <?php require __DIR__ . '/folder_section.php'; ?>
    <?php endforeach; ?>
  </div>
</section>
