<?php
/**
 * Course announcements — one teacher posts, every enrolled student gets a
 * notification row and the bell lights up. Read-only for students; the teacher's
 * half is the composer at the top.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = '';
$userId = (int) $user['id'];

/* One parameter name everywhere: every link, the notification target and both
   forms use ?id= . The composer used to post ?course= , so an announce action
   worked while a hand-typed GET silently did not. */
$courseId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$course = $courseId > 0 ? course_row($courseId) : null;
if (!$course) {
    set_flash('error', 'Course not found.');
    header('Location: courses.php');
    exit;
}
$isOwner = assignment_can_manage($user, $courseId);
$enrolled = is_enrolled_id($courseId, $userId);
if (!$isOwner && !$enrolled) {
    set_flash('error', 'Enroll in this course to see its announcements.');
    header('Location: ' . lh_url_clean('course.php?id=' . $courseId));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'post' && $isOwner) {
        $res = announcement_post($user, $courseId, (string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? ''), !empty($_POST['pinned']));
        if ($res['ok']) set_flash('success', 'Announcement posted and every student notified.');
        else foreach ($res['errors'] as $m) set_flash('error', $m);
        header('Location: ' . lh_url_clean('announcements.php?id=' . $courseId));
        exit;
    }
    if (($action === 'pin' || $action === 'unpin' || $action === 'delete') && $isOwner) {
        $a = announcement_row((int) ($_POST['announcement'] ?? 0));
        if ($a && (int) $a['course_id'] === $courseId) {
            if ($action === 'delete') {
                db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([(int) $a['id']]);
                set_flash('success', 'Announcement deleted.');
            } else {
                db()->prepare('UPDATE announcements SET pinned = ? WHERE id = ?')
                    ->execute([$action === 'pin' ? 1 : 0, (int) $a['id']]);
                set_flash('success', $action === 'pin' ? 'Pinned to the top.' : 'Unpinned.');
            }
        }
        header('Location: ' . lh_url_clean('announcements.php?id=' . $courseId));
        exit;
    }
}

$list = course_announcements($courseId);
$page_title = 'Announcements — ' . (string) $course['title'];
require __DIR__ . '/header.php';
$back = lh_url_clean('course.php?id=' . $courseId);
?>
<div class="mx-auto max-w-3xl">
  <div class="reveal flex flex-wrap items-center justify-between gap-3">
    <div>
      <a href="<?= $back ?>" class="text-xs font-semibold text-slate-400 hover:text-emerald-700">← <?= e((string) $course['title']) ?></a>
      <h1 class="mt-1 text-2xl font-bold text-slate-900">📣 Announcements</h1>
    </div>
    <a href="<?= $back ?>" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Back to course</a>
  </div>

  <?php if ($isOwner): ?>
  <form method="post" class="reveal mt-5 space-y-3 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="post">
    <input type="hidden" name="id" value="<?= $courseId ?>">
    <label class="block"><span class="text-sm font-semibold text-slate-700">Title</span>
      <input name="title" required maxlength="200" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="e.g. Friday's class moves to Room 3"></label>
    <label class="block"><span class="text-sm font-semibold text-slate-700">Message</span>
      <textarea name="body" required rows="4" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Say it once; everyone enrolled is notified."></textarea></label>
    <label class="flex items-center gap-2 text-sm text-slate-600">
      <input type="checkbox" name="pinned" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600"> Pin to the top</label>
    <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Post to the class</button>
  </form>
  <?php endif; ?>

  <?php if (!$list): ?>
    <p class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-sm text-slate-400">No announcements yet<?= $isOwner ? ' — post the first one above.' : '.' ?></p>
  <?php else: ?>
  <ul class="mt-6 space-y-3">
    <?php foreach ($list as $a):
      $who = ['id' => (int) $a['teacher_id'], 'name' => (string) $a['teacher_name'], 'avatar' => (string) $a['teacher_avatar']]; ?>
    <li class="rounded-2xl bg-white p-5 shadow-sm ring-1 <?= (int) $a['pinned'] ? 'ring-2 ring-amber-300' : 'ring-slate-200' ?>">
      <?php if ((int) $a['pinned']): ?>
        <p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-amber-600">📌 Pinned</p>
      <?php endif; ?>
      <h2 class="text-base font-bold text-slate-900"><?= e((string) $a['title']) ?></h2>
      <div class="mt-1.5 flex items-center gap-2">
        <?= user_peer_avatar_html($who, 'h-6 w-6') ?>
        <span class="text-[11px] text-slate-400"><?= e((string) $a['teacher_name']) ?> · <?= e(assignment_when((int) $a['created_at'])) ?></span>
      </div>
      <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700"><?= e((string) $a['body']) ?></p>
      <?php if ($isOwner): ?>
      <form method="post" class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $courseId ?>">
        <input type="hidden" name="announcement" value="<?= (int) $a['id'] ?>">
        <button name="action" value="<?= (int) $a['pinned'] ? 'unpin' : 'pin' ?>"
          class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100"><?= (int) $a['pinned'] ? 'Unpin' : 'Pin to top' ?></button>
        <button name="action" value="delete" data-confirm="Delete this announcement?"
          class="rounded-lg border border-rose-200 px-3 py-1.5 text-[11px] font-semibold text-rose-600 hover:bg-rose-50">Delete</button>
      </form>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php';

