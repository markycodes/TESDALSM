<?php
/**
 * Lesson discussion — one thread per lesson, open to the teacher and the
 * students of that course. Same shape as the private messages, but keyed to a
 * lesson instead of a pair of people, so a class can work through a question
 * together instead of thirty separate DMs.
 *
 * Access is the lesson gate (can_view_lessons), so a thread can never be read by
 * someone who could not open the lesson itself.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = '';
$userId = (int) $user['id'];

$materialId = (int) ($_GET['m'] ?? $_POST['material'] ?? 0);
$courseId = (int) ($_GET['c'] ?? $_POST['course'] ?? 0);
$course = $courseId > 0 ? course_row($courseId) : null;
if (!$course || !course_material_exists($courseId, $materialId)) {
    set_flash('error', 'Lesson not found.');
    header('Location: courses.php');
    exit;
}
if (!can_view_lessons($course, $user)
    || (!assignment_can_manage($user, $courseId) && !is_enrolled_id($courseId, $userId))) {
    /* can_view_lessons() alone is NOT enough here. It only narrows TEACHERS to
       the course they own — any non-teacher passes it, and this page is reached
       by guessing ?c=<id>, so an account that never enrolled could read a class's
       questions just by knowing the number. Hence the enrolment check as well,
       which is the same gate assignment.php uses. */
    set_flash('error', 'Enroll in this course to join its lesson discussions.');
    header('Location: ' . lh_url_clean('course.php?id=' . $courseId));
    exit;
}
$material = get_material($courseId, $materialId);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'post' || $action === 'reply') {
        $parent = $action === 'reply' ? (int) ($_POST['parent'] ?? 0) : 0;
        $res = lesson_post_add($user, $materialId, (string) ($_POST['body'] ?? ''), $parent);
        if (!$res['ok']) foreach ($res['errors'] as $m) set_flash('error', $m);
        header('Location: ' . lh_url_clean('discussion.php?c=' . $courseId . '&m=' . $materialId . ($parent ? '#post-' . $parent : '')));
        exit;
    }
    if ($action === 'delete') {
        if (lesson_post_delete($user, (int) ($_POST['post'] ?? 0))) set_flash('success', 'Post deleted.');
        header('Location: ' . lh_url_clean('discussion.php?c=' . $courseId . '&m=' . $materialId));
        exit;
    }
}

$thread = lesson_posts($materialId);
$page_title = 'Discussion — ' . (string) ($material['title'] ?? 'Lesson');
require __DIR__ . '/header.php';
$back = lh_url_clean('course.php?id=' . $courseId);
$canModerate = lesson_can_moderate($courseId, $user);

/** One post and its replies, nested one level. Rendered by recursion so a reply
 *  to a reply reads as part of the same conversation. A reply has no replies of
 *  its own, hence the ?? [] — see lesson_posts(). */
function lh_render_post(array $p, int $viewerId, bool $canModerate, int $courseId, int $materialId): void
{
    $isMine = (int) $p['user_id'] === $viewerId;
    $who = ['id' => (int) $p['user_id'], 'name' => (string) $p['author_name'], 'avatar' => (string) $p['author_avatar']];
    $isTeacher = ($p['author_role'] ?? '') === 'teacher';
    $replies = $p['replies'] ?? [];
    ?>
    <article id="post-<?= (int) $p['id'] ?>" class="rounded-xl border border-slate-200 bg-white p-4">
      <div class="flex items-center gap-2">
        <?= user_peer_avatar_html($who, 'h-8 w-8') ?>
        <span class="min-w-0 flex-1 leading-tight">
          <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $p['author_name']) ?></span>
          <span class="block text-[11px] text-slate-400"><?= e(assignment_when((int) $p['created_at'])) ?></span>
        </span>
        <?php if ($isTeacher): ?>
          <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">Teacher</span>
        <?php endif; ?>
        <?php if ($isMine || $canModerate): ?>
        <form method="post" data-confirm="Delete this post<?= count($replies) ? ' and its replies' : '' ?>?" class="shrink-0">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="post" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="material" value="<?= $materialId ?>">
          <input type="hidden" name="course" value="<?= $courseId ?>">
          <button class="rounded-lg px-2 py-1 text-[11px] font-semibold text-slate-400 hover:bg-rose-50 hover:text-rose-600">Delete</button>
        </form>
        <?php endif; ?>
      </div>
      <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700"><?= e((string) $p['body']) ?></p>
      <?php if (!$isMine): ?>
      <details class="mt-2">
        <summary class="cursor-pointer text-[11px] font-semibold text-slate-400 hover:text-indigo-600">Reply</summary>
        <form method="post" class="mt-2 flex gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reply">
          <input type="hidden" name="parent" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="material" value="<?= $materialId ?>">
          <input type="hidden" name="course" value="<?= $courseId ?>">
          <input name="body" required maxlength="2000" placeholder="Write a reply…"
            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500">
          <button class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Send</button>
        </form>
      </details>
      <?php endif; ?>
      <?php foreach ($replies as $r): ?>
        <?php lh_render_post($r, $viewerId, $canModerate, $courseId, $materialId); ?>
      <?php endforeach; ?>
    </article>
    <?php
}
?>
<div class="mx-auto max-w-3xl">
  <div class="reveal flex flex-wrap items-center justify-between gap-3">
    <div>
      <a href="<?= $back ?>" class="text-xs font-semibold text-slate-400 hover:text-emerald-700">← <?= e((string) $course['title']) ?></a>
      <h1 class="mt-1 text-2xl font-bold text-slate-900">💬 <?= e((string) ($material['title'] ?? 'Lesson')) ?></h1>
    </div>
    <a href="<?= $back ?>" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Back to course</a>
  </div>

  <form method="post" class="reveal mt-5 flex gap-2 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="post">
    <input type="hidden" name="material" value="<?= $materialId ?>">
    <input type="hidden" name="course" value="<?= $courseId ?>">
    <input name="body" required maxlength="2000" placeholder="Ask a question or share something that helps…"
      class="flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
    <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Post</button>
  </form>

  <?php if (!$thread): ?>
    <p class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-sm text-slate-400">No posts yet — ask the first question.</p>
  <?php else: ?>
  <div class="mt-6 space-y-3">
    <?php foreach ($thread as $p): ?>
      <?php lh_render_post($p, $userId, $canModerate, $courseId, $materialId); ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php';
