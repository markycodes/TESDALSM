<?php
require_once __DIR__ . '/lib.php';

$user = require_login();
$userId = (int) $user['id'];
$courseId = (int) ($_GET['c'] ?? 0);
$materialId = (int) ($_GET['m'] ?? 0);
$course = course_row($courseId);
if (!$course) {
    http_response_code(404);
    exit('Course not found.');
}

$isOwner = (int) $course['teacher_id'] === $userId;
$isAdmin = ($user['role'] ?? '') === 'admin';
if (!$isOwner && !$isAdmin && !is_enrolled_id($courseId, $userId)) {
    http_response_code(403);
    exit('Enroll in this course to watch its lessons.');
}

$material = get_material($courseId, $materialId);
if (!$material || !in_array($material['type'] ?? '', ['video', 'youtube'], true)) {
    http_response_code(404);
    exit('Video lesson not found.');
}

touch_presence($userId);
$trackVisit = ($user['role'] ?? '') === 'student' && !$isOwner;
$attendance_entered = 0;
if ($trackVisit) {
    $attendance_entered = resume_attendance($userId, $courseId);
    $attendance_course = $courseId;
}

$states = material_user_states($userId, $courseId);
$state = $states[$materialId] ?? [];
$done = material_completed($userId, $materialId);
$lessonQuiz = lesson_quiz($materialId);
$lessonQuizCount = $lessonQuiz ? count($lessonQuiz['questions']) : 0;
$type = (string) $material['type'];
$embed = $type === 'youtube'
    ? ($material['embed'] ?? video_embed_url((string) ($material['url'] ?? '')))
    : null;
$youtubeId = $type === 'youtube' ? youtube_id((string) ($material['url'] ?? '')) : null;
$isVimeo = $type === 'youtube' && $embed !== null && $youtubeId === null;
$videoPath = UPLOAD_DIR . '/' . basename((string) ($material['filename'] ?? ''));
$videoExists = $type !== 'video' || is_file($videoPath);
$page_title = (string) $material['title'];
require __DIR__ . '/header.php';
?>

<main class="mx-auto mt-6 max-w-5xl px-4">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <a href="course.php?id=<?= $courseId ?>" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">← Back to course folders</a>
    <?php if ($trackVisit): ?>
      <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1.5 text-xs font-semibold text-emerald-700">
        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>⏱ Time spent
        <span data-open-seconds="<?= max(0, time() - $attendance_entered) ?>" data-start-at="<?= $attendance_entered ?>" data-mark="video<?= $materialId ?>" class="tabular-nums"><?= duration_between($attendance_entered, null) ?></span>
      </span>
    <?php endif; ?>
  </div>
  <article class="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="relative aspect-video w-full bg-black">
      <?php if ($type === 'video' && $videoExists): ?>
        <video controls preload="metadata" class="h-full w-full" data-watch data-done="<?= $done ? '1' : '0' ?>"
               data-course="<?= $courseId ?>" data-material="<?= $materialId ?>"
               data-watched="<?= (int) ($state['watched'] ?? 0) ?>" data-position="<?= (int) ($state['position'] ?? 0) ?>"
               src="download.php?c=<?= $courseId ?>&amp;m=<?= $materialId ?>&amp;disp=inline"></video>
        <div data-overlay-for="<?= $materialId ?>" class="js-video-done-overlay absolute inset-0 z-10 <?= $done ? 'flex' : 'hidden' ?> items-center justify-center bg-black/70">
          <div class="flex flex-col items-center gap-2.5 rounded-2xl bg-slate-900/90 px-6 py-4 text-center ring-1 ring-emerald-300">
            <span class="text-sm font-semibold text-emerald-400">✓ Completed</span>
            <span class="max-w-[280px] text-center text-xs text-slate-200">You watched the whole video — great job!</span>
            <button type="button" class="js-video-replay rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">↻ Replay video</button>
          </div>
        </div>
      <?php elseif ($type === 'youtube' && $youtubeId !== null): ?>
        <div data-yt="<?= e($youtubeId) ?>" data-done="<?= $done ? '1' : '0' ?>"
             data-course="<?= $courseId ?>" data-material="<?= $materialId ?>"
             data-watched="<?= (int) ($state['watched'] ?? 0) ?>" data-position="<?= (int) ($state['position'] ?? 0) ?>"
             class="h-full w-full"></div>
        <div data-overlay-for="<?= $materialId ?>" class="js-video-done-overlay absolute inset-0 z-10 <?= $done ? 'flex' : 'hidden' ?> items-center justify-center bg-black/70">
          <div class="flex flex-col items-center gap-2.5 rounded-2xl bg-slate-900/90 px-6 py-4 text-center ring-1 ring-emerald-300">
            <span class="text-sm font-semibold text-emerald-400">✓ Completed</span>
            <span class="max-w-[280px] text-center text-xs text-slate-200">You watched the whole video — great job!</span>
            <button type="button" class="js-video-replay rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">↻ Replay video</button>
          </div>
        </div>
      <?php elseif ($type === 'youtube' && $isVimeo): ?>
        <iframe class="h-full w-full" src="<?= e((string) $embed) ?>" title="<?= e((string) $material['title']) ?>"
                allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
      <?php elseif ($type === 'youtube' && $embed !== null): ?>
        <iframe class="h-full w-full" src="<?= e((string) $embed) ?>" title="<?= e((string) $material['title']) ?>"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
      <?php elseif ($type === 'video' && !$videoExists): ?>
        <div class="flex h-full items-center justify-center px-6 text-center text-white">This video file is missing from the server. Please contact your teacher.</div>
      <?php else: ?>
        <div class="flex h-full items-center justify-center px-6 text-center text-white">This video link could not be opened. Please contact your teacher.</div>
      <?php endif; ?>
    </div>
    <div class="p-5">
      <h1 class="text-xl font-bold text-slate-900"><?= e((string) $material['title']) ?></h1>
      <?php if (!empty($material['description'])): ?>
        <p class="mt-2 text-sm leading-6 text-slate-600"><?= e((string) $material['description']) ?></p>
      <?php endif; ?>
      <p class="mt-2 text-xs text-slate-500"><?= $type === 'youtube' ? '🔗 Embedded video' : '🎬 Uploaded video' ?></p>
      <?php if ($isVimeo && !$done): ?>
        <button type="button" class="js-vimeo-done mt-4 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                data-course="<?= $courseId ?>" data-material="<?= $materialId ?>">Mark video complete</button>
      <?php endif; ?>
      <?php if ($lessonQuiz && ($isOwner || $isAdmin)): ?>
        <a href="quiz.php?c=<?= $courseId ?>&amp;m=<?= $materialId ?>" class="mt-4 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Preview lesson quiz (<?= $lessonQuizCount ?>)</a>
      <?php elseif ($lessonQuiz && $enrolled): ?>
        <div class="mt-4">
          <a data-lesson-quiz-link="<?= $materialId ?>" href="quiz.php?c=<?= $courseId ?>&amp;m=<?= $materialId ?>" class="<?= $done ? '' : 'hidden' ?> rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">🧪 Take this lesson’s quiz (<?= $lessonQuizCount ?>)</a>
          <span data-lesson-quiz-lock="<?= $materialId ?>" class="<?= $done ? 'hidden' : '' ?> text-sm font-semibold text-slate-500">🔒 Complete this video to unlock its quiz.</span>
        </div>
      <?php endif; ?>
    </div>
  </article>
</main>

<?php require __DIR__ . '/footer.php'; ?>
