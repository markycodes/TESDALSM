<?php
/**
 * Assignments — a teacher sets work, a student hands it in, the teacher grades
 * it here. One page, three faces:
 *
 *  • Owner teacher  create / edit the brief, then grade the queue
 *  • Student        read the brief, hand in (or replace an ungraded hand-in),
 *                   read the grade and the feedback
 *  • Anyone else    nothing at all — the same gate as the lessons themselves
 *
 * A hand-in is locked the moment it carries a grade, so a returned mark can
 * never be left describing work the student has since changed.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$nav_active = '';
$userId = (int) $user['id'];

$assignId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$assignment = $assignId > 0 ? assignment_row($assignId) : null;
if ($assignId > 0 && !$assignment) {
    set_flash('error', 'That assignment no longer exists.');
    header('Location: courses.php');
    exit;
}
$courseId = $assignment ? (int) $assignment['course_id'] : (int) ($_GET['course'] ?? $_POST['course'] ?? 0);
$course = $courseId > 0 ? course_row($courseId) : null;
if (!$course) {
    set_flash('error', 'Course not found.');
    header('Location: courses.php');
    exit;
}

$isOwner = assignment_can_manage($user, $courseId);
$enrolled = is_enrolled_id($courseId, $userId);
/* Students get the brief and their own hand-in; the owner teacher (or the main
   admin, who passes every teacher gate in the app — see assignment_can_manage())
   also gets the composer and the grading queue. */
if (!$isOwner && !$enrolled) {
    set_flash('error', 'Enroll in this course to see its assignments.');
    header('Location: ' . lh_url_clean('course.php?id=' . $courseId));
    exit;
}

/* ---- actions ---- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save' && $isOwner) {
        $res = assignment_save($user, $assignId, $_POST);
        if ($res['ok']) {
            set_flash('success', $assignId > 0 ? 'Assignment updated.' : 'Assignment published.');
            header('Location: ' . lh_url_clean('assignment.php?id=' . $res['id']));
            exit;
        }
        foreach ($res['errors'] as $m) set_flash('error', $m);
        /* Editing something that already exists? Come back to it, not to a blank
           "new assignment" form, or a typo in the title loses the whole draft. */
        header('Location: ' . lh_url_clean($assignId > 0
            ? 'assignment.php?id=' . $assignId . '&edit=1'
            : 'assignment.php?course=' . $courseId));
        exit;
    }

    if ($action === 'delete' && $isOwner) {
        if (assignment_delete($user, $assignId)) set_flash('success', 'Assignment deleted.');
        header('Location: ' . lh_url_clean('course.php?id=' . $courseId));
        exit;
    }

    if ($action === 'submit' && $enrolled && !$isOwner) {
        $res = assignment_submit($user, $assignment, $_POST);
        if ($res['ok']) {
            set_flash('success', 'Your work has been handed in. You can replace it until it is graded.');
        } else {
            foreach ($res['errors'] as $m) set_flash('error', $m);
        }
        header('Location: ' . lh_url_clean('assignment.php?id=' . $assignId));
        exit;
    }

    if ($action === 'grade' && $isOwner) {
        $raw = trim((string) ($_POST['grade'] ?? ''));
        $res = assignment_grade($user, (int) ($_POST['submission'] ?? 0), $raw === '' ? null : (int) $raw, (string) ($_POST['feedback'] ?? ''));
        if ($res['ok']) set_flash('success', $raw === '' ? 'Grade cleared.' : 'Grade saved and the student notified.');
        else foreach ($res['errors'] as $m) set_flash('error', $m);
        header('Location: ' . lh_url_clean('assignment.php?id=' . $assignId));
        exit;
    }
}

/* ---- read ---- */
/* Both faces get the LIST, not just the teacher. This was owner-only, so a
   student who followed the course page's "📝 N assignments" link arrived at a
   page with an empty list AND no single assignment selected — every branch
   below was skipped and the page rendered blank. An assignment was reachable
   only by guessing its id. The gate above has already established that whoever
   is here is the owner teacher or an enrolled student, so the list is safe. */
$assignments = course_assignments($courseId);
$mine = ($assignment && $enrolled && !$isOwner) ? submission_for($assignId, $userId) : null;
$queue = ($assignment && $isOwner) ? assignment_submissions($assignId) : [];
$dueState = $assignment ? assignment_due_state($assignment) : 'open';
$locked = $mine !== null && $mine['grade'] !== null;
$enrolledCount = count(course_student_ids($courseId));
$outstanding = 0;
if ($isOwner && $assignment) {
    foreach (course_student_ids($courseId) as $sid) {
        if (submission_for($assignId, $sid) === null) $outstanding++;
    }
}

/* The list view is a teacher's "start something new" page, so only they should be
   told it is a new assignment; a student opening the same URL sees their work. */
$page_title = $assignment ? 'Assignment — ' . (string) $assignment['title']
    : ($isOwner ? 'New assignment' : 'Assignments');
/* ?edit=1 opens the brief for editing. Only the owning teacher ever sees the
   form on an existing assignment; a student just reads it. */
$editing = $isOwner && $assignment !== null && !empty($_GET['edit']);
require __DIR__ . '/header.php';
$back = lh_url_clean('course.php?id=' . $courseId);
?>
<div class="mx-auto max-w-4xl">

  <div class="reveal flex flex-wrap items-center justify-between gap-3">
    <div>
      <a href="<?= $back ?>" class="text-xs font-semibold text-slate-400 hover:text-emerald-700">← <?= e((string) $course['title']) ?></a>
      <h1 class="mt-1 text-2xl font-bold text-slate-900"><?= $assignment ? '📝 ' . e((string) $assignment['title']) : ($isOwner ? '➕ New assignment' : '📝 Assignments') ?></h1>
    </div>
<?php if ($isOwner && (!$assignment || $editing)): ?>
  <form method="post" class="reveal mt-5 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $editing ? $assignId : 0 ?>">
    <input type="hidden" name="course_id" value="<?= $courseId ?>">
    <?php if ($editing): ?>
      <div class="flex items-center justify-between gap-3">
        <h2 class="text-base font-bold text-slate-900">✏️ Editing this brief</h2>
        <a href="<?= e(lh_url_clean('assignment.php?id=' . $assignId)) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
      </div>
    <?php endif; ?>
    <label class="block"><span class="text-sm font-semibold text-slate-700">Title</span>
      <input name="title" required maxlength="160" value="<?= e((string) ($editing ? $assignment['title'] : '')) ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="e.g. Build a small PHP contact form"></label>
    <label class="block"><span class="text-sm font-semibold text-slate-700">What to do</span>
      <textarea name="instructions" required rows="5" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Spell out what you expect. Students read exactly this."><?= e((string) ($editing ? $assignment['instructions'] : '')) ?></textarea></label>
    <div class="grid gap-4 sm:grid-cols-3">
      <label class="block"><span class="text-sm font-semibold text-slate-700">Deadline</span>
        <input type="datetime-local" name="due_at" value="<?= e($editing ? lh_dt_local_value($assignment['due_at']) : '') ?>" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500">
        <span class="mt-1 block text-[11px] text-slate-400">Leave empty for no deadline.</span></label>
      <label class="block"><span class="text-sm font-semibold text-slate-700">Points</span>
        <input type="number" name="max_points" value="<?= (int) ($editing ? $assignment['max_points'] : 100) ?>" min="1" max="10000" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500"></label>
      <label class="mt-6 flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" name="allow_late" value="1" <?= (!$editing || (int) $assignment['allow_late'] === 1) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-indigo-600"> Accept late work</label>
    </div>
    <div class="flex flex-wrap gap-2">
      <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"><?= $editing ? 'Save changes' : 'Publish assignment' ?></button>
      <?php if ($editing): ?>
        <a href="<?= e(lh_url_clean('assignment.php?id=' . $assignId)) ?>" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</a>
      <?php endif; ?>
    </div>
    <?php if ($editing): ?>
      <p class="text-[11px] text-slate-400">Hand-ins and grades already given are kept — editing the brief never touches a student's work.</p>
    <?php endif; ?>
  </form>
<?php endif; ?>

  <?php /* This list/detail chain is a SIBLING of the composer branch above, not
        nested inside it. It used to sit inside, which meant a student never
        satisfied the composer's owner-only condition, fell straight through to
        the `elseif ($assignment)` branch below, and got an empty page. */
        if ($assignment && $editing): /* the list would just repeat the row being edited */ ?>
  <?php elseif (!$assignment && $assignments): ?>
     <?php /* (!$assignment) matters: without it a student who opened ONE assignment
            from the list fell into this branch and got the list again instead of
            the brief and the hand-in box, so a hand-in was impossible to reach. */ ?>
  <h2 class="mt-8 text-base font-bold text-slate-900"><?= $isOwner ? 'Already published' : 'Your assignments' ?></h2>
  <ul class="mt-3 space-y-2">
    <?php foreach ($assignments as $a): $st = assignment_due_state($a);
        /* A student is shown THEIR OWN row, never the class's tally: "4/6 graded"
           is the teacher's information, and it would also let one student read
           how far along everyone else is. */
        $lhSub = (!$isOwner && $enrolled) ? submission_for((int) $a['id'], $userId) : null;
    ?>
    <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200">
      <span class="min-w-0">
        <a href="<?= e(lh_url_clean('assignment.php?id=' . $a['id'])) ?>" class="truncate font-semibold text-slate-800 hover:text-emerald-700"><?= e((string) $a['title']) ?></a>
        <?php if ($isOwner): ?>
          <span class="block text-[11px] text-slate-400"><?= e(assignment_due_label($a)) ?> · <?= (int) $a['max_points'] ?> pts · <?= (int) ($a['graded'] ?? 0) ?>/<?= $enrolledCount ?> graded</span>
        <?php else: ?>
          <span class="block text-[11px] text-slate-400"><?= e(assignment_due_label($a)) ?> · <?= (int) $a['max_points'] ?> pts ·
            <?php if ($lhSub === null): ?>not handed in yet
            <?php elseif ($lhSub['grade'] === null): ?>handed in — waiting to be graded
            <?php else: ?>graded <?= (int) $lhSub['grade'] ?>/<?= (int) $a['max_points'] ?><?php endif; ?></span>
        <?php endif; ?>
      </span>
      <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $st === 'closed' ? 'bg-slate-100 text-slate-500' : ($st === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') ?>"><?= $st === 'closed' ? 'Closed' : ($st === 'late' ? 'Late OK' : 'Open') ?></span>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php elseif ($assignment): ?>
  <div class="reveal mt-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-wrap items-center gap-2">
      <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold <?= $dueState === 'closed' ? 'bg-slate-100 text-slate-500' : ($dueState === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') ?>"><?= e(assignment_due_label($assignment)) ?></span>
      <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-700"><?= (int) $assignment['max_points'] ?> points</span>
    </div>
    <div class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700"><?= e((string) $assignment['instructions']) ?></div>
<?php if ($isOwner && !$editing): ?>
    <div class="mt-4 border-t border-slate-100 pt-4">
      <a href="<?= e(lh_url_clean('assignment.php?id=' . $assignId . '&edit=1')) ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100">✏️ Edit brief</a>
    </div>
<?php endif; ?>
  </div>
<?php if ($isOwner): ?>
  <div class="reveal mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-base font-bold text-slate-900">🧾 Hand-ins</h2>
      <span class="text-xs text-slate-400"><?= count($queue) ?>/<?= $enrolledCount ?> in · <?= $outstanding ?> still to do</span>
    </div>
    <?php if (!$queue): ?>
      <p class="mt-4 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">Nobody has handed this in yet.</p>
    <?php else: ?>
      <ul class="mt-4 space-y-4">
        <?php foreach ($queue as $s):
          $who = ['id' => (int) $s['user_id'], 'name' => (string) $s['student_name'], 'avatar' => (string) $s['student_avatar']]; ?>
        <li class="rounded-xl border border-slate-200 p-4">
          <div class="flex items-center gap-3">
            <span class="shrink-0"><?= user_peer_avatar_html($who, 'h-9 w-9') ?></span>
            <span class="min-w-0 flex-1 leading-tight">
              <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $s['student_name']) ?></span>
              <span class="block text-[11px] text-slate-400">handed in <?= e(assignment_when((int) $s['submitted_at'])) ?></span>
            </span>
            <?php if ($s['grade'] !== null): ?>
              <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700"><?= (int) $s['grade'] ?>/<?= (int) $assignment['max_points'] ?></span>
            <?php endif; ?>
          </div>
          <?php if ((string) $s['body'] !== ''): ?>
            <div class="mt-3 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700"><?= e((string) $s['body']) ?></div>
          <?php endif; ?>
          <?php if ((string) $s['filename'] !== ''): ?>
            <a href="<?= e(lh_url_clean('download_submission.php?f=' . rawurlencode((string) $s['filename']))) ?>"
               class="mt-2 inline-block rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-100">📎 <?= e((string) $s['orig_name']) ?> (<?= e(format_size((int) $s['size'])) ?>)</a>
          <?php endif; ?>
          <form method="post" class="mt-3 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="grade">
            <input type="hidden" name="submission" value="<?= (int) $s['id'] ?>">
            <input type="hidden" name="id" value="<?= $assignId ?>">
            <label class="w-28"><span class="text-[11px] font-semibold text-slate-500">Grade</span>
              <input type="number" name="grade" min="0" max="<?= (int) $assignment['max_points'] ?>" value="<?= $s['grade'] === null ? '' : (int) $s['grade'] ?>"
                class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm outline-none focus:border-indigo-500"></label>
            <label class="min-w-[12rem] flex-1"><span class="text-[11px] font-semibold text-slate-500">Feedback</span>
              <input name="feedback" maxlength="2000" value="<?= e((string) $s['feedback']) ?>"
                class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm outline-none focus:border-indigo-500"></label>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Save</button>
          </form>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <form method="post" class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4" data-confirm="Delete this assignment and every hand-in?">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
<?php else: /* the student */ ?>
  <div class="reveal mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <?php if ($mine && $locked): ?>
      <div class="rounded-xl bg-emerald-50 p-4">
        <p class="text-sm font-bold text-emerald-800">✓ Graded — <?= (int) $mine['grade'] ?>/<?= (int) $assignment['max_points'] ?></p>
        <?php if ((string) $mine['feedback'] !== ''): ?>
          <p class="mt-2 whitespace-pre-line text-sm text-emerald-900"><?= e((string) $mine['feedback']) ?></p>
        <?php else: ?>
          <p class="mt-2 text-sm text-emerald-700">No written feedback on this one.</p>
        <?php endif; ?>
        <p class="mt-2 text-[11px] text-emerald-700">Handed in <?= e(assignment_when((int) $mine['submitted_at'])) ?>. Graded work is locked, so it can no longer be changed.</p>
      </div>
    <?php elseif ($dueState === 'closed'): ?>
      <p class="rounded-xl border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">This assignment is closed for submissions.</p>
    <?php else: ?>
      <?php if ($mine): ?>
        <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">You handed this in <?= e(assignment_when((int) $mine['submitted_at'])) ?>. You can replace it until it is graded.</p>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit">
        <input type="hidden" name="id" value="<?= $assignId ?>">
        <label class="block"><span class="text-sm font-semibold text-slate-700">Your answer</span>
          <textarea name="body" rows="7" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200" placeholder="Write your answer, a link to your work, or both."><?= e((string) ($mine['body'] ?? '')) ?></textarea></label>
        <label class="block"><span class="text-sm font-semibold text-slate-700">Attach a file <span class="font-normal text-slate-400">(optional — PDF, image, document or zip, up to 25 MB)</span></span>
          <input type="file" name="file" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700"></label>
        <?php if ($mine && (string) $mine['filename'] !== ''): ?>
          <p class="text-[11px] text-slate-400">Currently attached: <?= e((string) $mine['orig_name']) ?> — uploading again replaces it.</p>
        <?php endif; ?>
        <button class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700"><?= $mine ? 'Replace my work' : 'Hand in' ?></button>
      </form>
    <?php endif; ?>
  </div>
  <?php endif; /* end of: isOwner -> grading queue | student -> hand-in box */ ?>
  <?php endif; /* end of the list / detail chain */ ?>
</div>
<?php require __DIR__ . '/footer.php';
