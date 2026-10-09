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
    if ($action === 'edit') {
        $postId = (int) ($_POST['post'] ?? 0);
        $res = lesson_post_edit($user, $postId, (string) ($_POST['body'] ?? ''));
        if ($res['ok']) set_flash('success', 'Post updated.');
        else foreach ($res['errors'] as $m) set_flash('error', $m);
        header('Location: ' . lh_url_clean('discussion.php?c=' . $courseId . '&m=' . $materialId . '#post-' . $postId));
        exit;
    }
    if ($action === 'delete') {
        if (lesson_post_delete($user, (int) ($_POST['post'] ?? 0))) set_flash('success', 'Post deleted.');
        header('Location: ' . lh_url_clean('discussion.php?c=' . $courseId . '&m=' . $materialId));
        exit;
    }
}

$thread = lesson_posts($materialId, $userId);
$page_title = 'Discussion — ' . (string) ($material['title'] ?? 'Lesson');
require __DIR__ . '/header.php';
$back = lh_url_clean('course.php?id=' . $courseId);
$canModerate = lesson_can_moderate($courseId, $user);

/** Render a post and recursively nest every reply beneath its direct parent. */
function lh_render_post(array $p, int $viewerId, bool $canModerate, int $courseId, int $materialId, int $depth = 0): void
{
    $isMine = (int) $p['user_id'] === $viewerId;
    $isReply = (int) $p['parent_id'] > 0;
    $replyIndent = $isReply ? min($depth * 24, 120) : 0;
    $who = ['id' => (int) $p['user_id'], 'name' => (string) $p['author_name'], 'avatar' => (string) $p['author_avatar']];
    $isTeacher = ($p['author_role'] ?? '') === 'teacher';
    $replies = $p['replies'] ?? [];
    ?>
    <div class="w-full"<?= $isReply ? ' style="margin-left:' . $replyIndent . 'px;width:calc(100% - ' . $replyIndent . 'px)"' : '' ?>>
    <article id="post-<?= (int) $p['id'] ?>" data-discussion-post data-author-id="<?= (int) $p['user_id'] ?>" data-created-at="<?= (int) $p['created_at'] ?>" class="<?= $isReply ? 'rounded-lg py-1' : 'mx-auto w-full max-w-[48rem] rounded-xl border border-slate-200 bg-white p-4' ?>">
      <div class="flex items-center gap-2">
        <?= user_peer_avatar_html($who, 'h-8 w-8') ?>
        <span class="min-w-0 flex-1 leading-tight">
          <span data-post-author class="block text-sm font-semibold text-slate-800"><?= e((string) $p['author_name']) ?></span>
          <span class="block text-[11px] text-slate-400"><?= e(assignment_when((int) $p['created_at'])) ?></span>
        </span>
        <?php if ($replies): ?>
          <button type="button" data-view-all-replies
            class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">View all replies</button>
        <?php endif; ?>
        <?php if ($isTeacher): ?>
          <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">Teacher</span>
        <?php endif; ?>
        <?php if ($isMine || $canModerate): ?>
        <button type="button" data-edit-post="<?= (int) $p['id'] ?>" data-edit-body="<?= e((string) $p['body']) ?>"
          class="shrink-0 rounded-lg px-2 py-1 text-[11px] font-semibold text-indigo-600 hover:bg-indigo-50">Edit</button>
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
      <p class="<?= $isReply ? 'ml-10 mt-1 inline-block max-w-full rounded-2xl bg-slate-100 px-3 py-2' : 'mt-3' ?> whitespace-pre-line text-sm leading-6 text-slate-700"><?= e((string) $p['body']) ?></p>
      <?= content_reaction_html('lesson_post', (int) $p['id'], (array) ($p['reaction_summary'] ?? [])) ?>
      <div class="mt-1">
        <button type="button" data-reply-toggle data-reply-name="<?= e((string) $p['author_name']) ?>"
          data-reply-author-id="<?= (int) $p['user_id'] ?>" data-viewer-id="<?= $viewerId ?>" aria-expanded="false"
          class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-indigo-600">Reply</button>
        <form method="post" data-reply-form hidden class="mt-2 flex flex-wrap items-start gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="reply">
          <input type="hidden" name="parent" value="<?= (int) $p['id'] ?>">
          <input type="hidden" name="material" value="<?= $materialId ?>">
          <input type="hidden" name="course" value="<?= $courseId ?>">
          <textarea name="body" required maxlength="2000" rows="2" data-typing-input placeholder="Write a reply…"
            class="min-w-[12rem] flex-1 resize-y rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"></textarea>
          <div class="flex items-center gap-2">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Reply</button>
            <button type="button" data-reply-cancel class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-100">Cancel</button>
          </div>
        </form>
      </div>
      <?php if ($replies): ?>
        <button type="button" data-view-replies data-reply-count="<?= count($replies) ?>" aria-expanded="true"
          class="ml-2 rounded-lg px-2 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50">
          Hide replies
        </button>
        <div data-replies-list class="lh-thread-preview mt-2 space-y-2">
          <?php foreach ($replies as $r): ?>
            <?php lh_render_post($r, $viewerId, $canModerate, $courseId, $materialId, $depth + 1); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
    </div>
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
    <input name="body" required maxlength="2000" data-typing-input placeholder="Ask a question or share something that helps…"
      class="flex-1 rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
    <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Post</button>
  </form>
  <?php if (!$thread): ?>
    <p class="mt-6 rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-sm text-slate-400">No posts yet — ask the first question.</p>
  <?php else: ?>
  <div class="mx-auto mt-6 w-full max-w-[48rem] space-y-3">
    <?php foreach ($thread as $p): ?>
      <?php lh_render_post($p, $userId, $canModerate, $courseId, $materialId); ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<dialog data-thread-dialog style="width:min(52rem,calc(100vw - 2rem));max-height:85vh;overflow:hidden" class="rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-900/50">
  <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
    <h2 class="text-sm font-bold text-slate-800">Discussion replies</h2>
    <button type="button" data-thread-dialog-close class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-100">Close</button>
  </div>
  <div data-thread-dialog-content style="max-height:calc(85vh - 3.5rem);overflow-y:auto" class="p-4"></div>
</dialog>
<style>
  [data-reply-form][hidden],
  [data-replies-list][hidden] {
    display: none !important;
  }
  .lh-thread-preview {
    max-height: 55vh;
    overflow: hidden;
  }
  [data-thread-dialog-content].lh-thread-expanded .lh-thread-preview {
    max-height: none;
    overflow: visible;
  }
</style>
  <dialog data-edit-dialog class="w-[min(32rem,calc(100vw-2rem))] rounded-2xl border-0 bg-white p-0 shadow-2xl backdrop:bg-slate-900/50">
    <form method="post" class="p-5">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="post" data-edit-post-id value="">
      <input type="hidden" name="material" value="<?= $materialId ?>">
      <input type="hidden" name="course" value="<?= $courseId ?>">
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-slate-900">Edit discussion post</h2>
        <button type="button" data-edit-close aria-label="Close edit dialog" class="rounded-lg px-2 py-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
      </div>
      <textarea name="body" data-edit-post-body required maxlength="2000" rows="6"
        class="mt-4 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm leading-6 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"></textarea>
      <div class="mt-4 flex justify-end gap-2">
        <button type="button" data-edit-close class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button>
      </div>
    </form>
  </dialog>
  <script>
  (function () {
    document.addEventListener('click', function (event) {
      var viewAll = event.target.closest('[data-view-all-replies]');
      if (viewAll) {
        var postCard = viewAll.closest('[data-discussion-post]');
        var threadDialog = document.querySelector('[data-thread-dialog]');
        var dialogContent = threadDialog ? threadDialog.querySelector('[data-thread-dialog-content]') : null;
        if (!postCard || !threadDialog || !dialogContent) return;
        var placeholder = document.createElement('div');
        placeholder.setAttribute('data-thread-card-placeholder', '');
        postCard.before(placeholder);
        postCard.dataset.dialogPlaceholder = 'true';
        postCard._threadModalPlaceholder = placeholder;
        postCard._threadModalReplyLists = [];
        postCard.dataset.dialogOriginalWidth = postCard.style.width;
        postCard.dataset.dialogOriginalMaxWidth = postCard.style.maxWidth;
        postCard.style.width = '100%';
        postCard.style.maxWidth = 'none';
        postCard.style.height = 'auto';
        postCard.style.maxHeight = 'none';
        postCard.style.overflow = 'visible';
        dialogContent.classList.add('lh-thread-expanded');
        postCard.querySelectorAll('[data-replies-list]').forEach(function (list) {
          postCard._threadModalReplyLists.push({
            element: list,
            hidden: list.hidden,
            maxHeight: list.style.maxHeight,
            overflow: list.style.overflow
          });
          list.hidden = false;
          list.style.maxHeight = 'none';
          list.style.overflow = 'visible';
          var repliesToggle = list.parentElement.querySelector(':scope > [data-view-replies]');
          if (repliesToggle) {
            repliesToggle.hidden = false;
            repliesToggle.setAttribute('aria-expanded', 'true');
            repliesToggle.textContent = 'Hide replies';
          }
        });
        dialogContent.appendChild(postCard);
        threadDialog.showModal();
        return;
      }
      var closeThread = event.target.closest('[data-thread-dialog-close]');
      if (closeThread) {
        var activeDialog = closeThread.closest('[data-thread-dialog]');
        if (activeDialog) activeDialog.close();
        return;
      }
      var toggle = event.target.closest('[data-reply-toggle]');
      if (toggle) {
        var post = toggle.closest('[data-discussion-post]');
        var form = post ? post.querySelector('[data-reply-form]') : null;
        if (!form) return;
        var open = form.hidden;
        form.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
          var field = form.querySelector('[data-typing-input]');
          if (field) {
            var authorId = toggle.getAttribute('data-reply-author-id');
            var viewerId = toggle.getAttribute('data-viewer-id');
            var authorName = (toggle.getAttribute('data-reply-name') || '').trim();
            field.value = authorId && authorId !== viewerId && authorName ? '@' + authorName + ' ' : '';
            field.focus();
            field.setSelectionRange(field.value.length, field.value.length);
            field.dispatchEvent(new Event('input', { bubbles: true }));
          }
        }
        return;
      }
      var viewReplies = event.target.closest('[data-view-replies]');
      if (viewReplies) {
        var thread = viewReplies.closest('[data-discussion-post]');
        var repliesList = thread ? thread.querySelector(':scope > [data-replies-list]') : null;
        if (!repliesList) return;
        var showReplies = repliesList.hidden;
        repliesList.hidden = !showReplies;
        viewReplies.setAttribute('aria-expanded', showReplies ? 'true' : 'false');
        var replyCount = Number(viewReplies.getAttribute('data-reply-count')) || 0;
        viewReplies.textContent = showReplies
          ? 'Hide replies'
          : 'View ' + replyCount + ' repl' + (replyCount === 1 ? 'y' : 'ies');
        return;
      }
      var cancel = event.target.closest('[data-reply-cancel]');
      if (cancel) {
        var replyForm = cancel.closest('[data-reply-form]');
        if (!replyForm) return;
        replyForm.hidden = true;
        var replyPost = cancel.closest('[data-discussion-post]');
        var replyToggle = replyPost ? replyPost.querySelector('[data-reply-toggle]') : null;
        if (replyToggle) replyToggle.setAttribute('aria-expanded', 'false');
        var replyField = replyForm.querySelector('[data-typing-input]');
        if (replyField) {
          replyField.value = '';
          replyField.dispatchEvent(new Event('input', { bubbles: true }));
        }
      }
    });

    var threadDialog = document.querySelector('[data-thread-dialog]');
    if (threadDialog) {
      threadDialog.addEventListener('close', function () {
        var postCard = threadDialog.querySelector('[data-dialog-placeholder="true"]');
        if (!postCard) return;
        var placeholder = postCard._threadModalPlaceholder;
        if (placeholder && placeholder.parentNode) {
          placeholder.parentNode.replaceChild(postCard, placeholder);
        }
        (postCard._threadModalReplyLists || []).forEach(function (state) {
          state.element.hidden = state.hidden;
          state.element.style.maxHeight = state.maxHeight;
          state.element.style.overflow = state.overflow;
        });
        delete postCard._threadModalPlaceholder;
        delete postCard._threadModalReplyLists;
        delete postCard.dataset.dialogPlaceholder;
        postCard.style.width = postCard.dataset.dialogOriginalWidth || '';
        postCard.style.maxWidth = postCard.dataset.dialogOriginalMaxWidth || '';
        postCard.style.height = '';
        postCard.style.maxHeight = '';
        postCard.style.overflow = '';
        delete postCard.dataset.dialogOriginalWidth;
        delete postCard.dataset.dialogOriginalMaxWidth;
        threadDialog.querySelector('[data-thread-dialog-content]').classList.remove('lh-thread-expanded');
      });
    }

    var editDialog = document.querySelector('[data-edit-dialog]');
    var editPostId = document.querySelector('[data-edit-post-id]');
    var editPostBody = document.querySelector('[data-edit-post-body]');
    document.querySelectorAll('[data-edit-post]').forEach(function (button) {
      button.addEventListener('click', function () {
        editPostId.value = button.getAttribute('data-edit-post') || '';
        editPostBody.value = button.getAttribute('data-edit-body') || '';
        editDialog.showModal();
        editPostBody.focus();
      });
    });
    document.querySelectorAll('[data-edit-close]').forEach(function (button) {
      button.addEventListener('click', function () { editDialog.close(); });
    });

    var inputs = Array.prototype.slice.call(document.querySelectorAll('[data-typing-input]'));
    if (!inputs.length) return;
    var authorsById = {};
    Array.prototype.slice.call(document.querySelectorAll('[data-discussion-post]')).forEach(function (post) {
      var author = post.querySelector('[data-post-author]');
      var id = post.getAttribute('data-author-id');
      if (!author || !id) return;
      var known = authorsById[id];
      if (!known || Number(post.getAttribute('data-created-at')) >= Number(known.getAttribute('data-created-at'))) {
        authorsById[id] = author;
        author.setAttribute('data-created-at', post.getAttribute('data-created-at') || '0');
      }
    });
    var endpoint = 'discussion_live.php?c=<?= $courseId ?>&m=<?= $materialId ?>';
    var csrf = document.querySelector('meta[name="csrf"]');
    var token = csrf ? csrf.content : '';
    var typing = false;
    var lastTypingSent = 0;
    var stopTimer = 0;
    var warned = false;

    function sendTyping(active) {
      var now = Date.now();
      if (typing === active && (!active || now - lastTypingSent < 2500)) return;
      typing = active;
      lastTypingSent = now;
      var body = new URLSearchParams({ csrf: token, typing: active ? '1' : '0' });
      fetch(endpoint + '&v=beat', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
        body: body.toString(),
        keepalive: !active
      }).then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      }).then(function (data) {
        if (!data.ok) throw new Error(data.error || 'Typing status update failed.');
        warned = false;
      }).catch(function (error) {
        if (!warned) console.warn('Lesson discussion typing status could not be updated.', error);
        warned = true;
      });
    }

    inputs.forEach(function (input) {
      input.addEventListener('input', function () {
        sendTyping(input.value.trim() !== '');
        window.clearTimeout(stopTimer);
        if (input.value.trim() !== '') {
          stopTimer = window.setTimeout(function () { sendTyping(false); }, 4000);
        }
      });
      input.addEventListener('blur', function () {
        window.clearTimeout(stopTimer);
        sendTyping(false);
      });
    });

    function poll() {
      if (document.visibilityState !== 'visible') return;
      fetch(endpoint + '&v=state&t=' + Date.now(), {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' }
      }).then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      }).then(function (data) {
        if (!data.ok) throw new Error(data.error || 'Typing status request failed.');
        var typingIds = {};
        (Array.isArray(data.users) ? data.users : []).forEach(function (typingUser) {
          var author = authorsById[String(typingUser.id)];
          if (!author) return;
          typingIds[String(typingUser.id)] = true;
          var indicator = author.querySelector('[data-typing-indicator]');
          if (!indicator) {
            indicator = document.createElement('span');
            indicator.setAttribute('data-typing-indicator', '');
            indicator.setAttribute('aria-live', 'polite');
            indicator.className = 'ml-2 inline-flex align-middle text-xs font-medium text-indigo-600';
            author.appendChild(indicator);
          }
          indicator.textContent = '· typing…';
        });
        Object.keys(authorsById).forEach(function (id) {
          if (!typingIds[id]) {
            var indicator = authorsById[id].querySelector('[data-typing-indicator]');
            if (indicator) indicator.remove();
          }
        });
        warned = false;
      }).catch(function (error) {
        if (!warned) console.warn('Lesson discussion typing status could not be loaded.', error);
        warned = true;
      });
    }
    poll();
    window.setInterval(poll, 2500);
    window.addEventListener('pagehide', function () { sendTyping(false); });
  })();
  </script>
  <?php require __DIR__ . '/footer.php';
