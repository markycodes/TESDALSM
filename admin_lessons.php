<?php
/**
 * Lessons by teacher — the admin's read-only inventory of every lesson on the
 * site, grouped teacher → course → lesson.
 *
 * Why this page exists: teachers see their own lessons on course.php and
 * admin.php only counts courses, so there was no single screen answering
 * "what has each teacher actually put up?". The list comes from
 * admin_lessons_by_teacher() (lib.php), which builds on load_courses(), so the
 * lesson order here matches the course page, the offline bundle and bulk.php
 * exactly (the sort_order rule lives in that one function, on purpose).
 *
 * Read-only on purpose: no forms and no delete buttons — every lesson opens
 * where the app already renders it (the reader, the course player, the quiz
 * preview), and each of those gates lets the main admin through, like every
 * other teacher gate in the app.
 */
require_once __DIR__ . '/lib.php';
$user = require_admin();
$nav_active = 'admin_lessons';

$byTeacher = admin_lessons_by_teacher();

/* One query for the whole page: which lessons carry a quiz (and its title). */
$quizByMaterial = [];
foreach (db()->query('SELECT material_id, title FROM quizzes ORDER BY id')->fetchAll() as $q) {
    $quizByMaterial[(int) $q['material_id']] = (string) $q['title'];
}

$nCourses = 0;
$nLessons = 0;
foreach ($byTeacher as $t) {
    $nCourses += count($t['courses']);
    $nLessons += (int) $t['lessons'];
}
/* Hover cards on the teacher names — one query primes them all. */
profile_cards_preload(array_column($byTeacher, 'id'), $user);

$page_title = 'Lessons by teacher';
require __DIR__ . '/header.php';
?>
<style>
  /* Page-scoped, exactly like faq.php's: each course header is a native
     <details> so the whole inventory is open at a glance but any course can
     be folded away — the browser's marker is replaced by the caret below. */
  .lh-al>summary {
    list-style: none;
    cursor: pointer;
  }

  .lh-al>summary::-webkit-details-marker {
    display: none;
  }

  .lh-al-mark {
    display: inline-block;
    transition: transform .18s ease;
  }

  .lh-al[open]>summary .lh-al-mark {
    transform: rotate(90deg);
  }
</style>

<div class="lh-admin-page mx-auto max-w-7xl">
<a href="admin.php" class="lh-admin-back">← Admin control</a>

<div class="lh-admin-hero reveal mt-3">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <div class="max-w-2xl">
      <p class="lh-admin-eyebrow">Content oversight</p>
      <h1 class="lh-admin-title">Lessons by teacher</h1>
      <p class="lh-admin-description">Every course and every lesson each teacher has published, in one list and
        in the same order the course page shows them. Read-only — each lesson opens where the app already renders
        it.</p>
    </div>
    <div class="flex flex-wrap gap-2">
      <?php foreach ([count($byTeacher) . ' teachers', $nCourses . ' courses', $nLessons . ' lessons'] as $metric): ?>
        <span class="rounded-xl border border-indigo-100 bg-white/80 px-3 py-2 text-xs font-bold text-indigo-700"><?= e($metric) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (!$byTeacher): ?>
  <p class="mt-5 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">No
    teachers yet.</p>
<?php else: foreach ($byTeacher as $t): ?>
  <section class="lh-admin-card reveal mt-5 p-6">
    <div class="flex flex-wrap items-center gap-3">
      <?= user_peer_avatar_html($t, 'h-9 w-9', $user) ?>
      <span class="min-w-0 flex-1 leading-tight"<?= profile_hover_attrs($t) ?>>
        <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $t['name']) ?></span>
        <span class="block text-[11px] text-slate-400">
          <?= count($t['courses']) ?> course<?= count($t['courses']) === 1 ? '' : 's' ?> ·
          <?= (int) $t['lessons'] ?> lesson<?= (int) $t['lessons'] === 1 ? '' : 's' ?></span>
      </span>
    </div>

    <?php if (!$t['courses']): ?>
      <p class="mt-4 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">No
        courses yet — this teacher has not created any.</p>
    <?php else: foreach ($t['courses'] as $c):
        $cid = (int) $c['id'];
        $mats = $c['materials'] ?? []; ?>
      <details class="lh-al mt-4 rounded-xl border border-slate-200" open>
        <summary class="flex flex-wrap items-center gap-2 px-4 py-3">
          <span class="lh-al-mark" aria-hidden="true">▸</span>
          <a href="course.php?id=<?= $cid ?>"
            class="text-sm font-bold text-slate-900 hover:text-indigo-600"><?= e((string) $c['title']) ?></a>
          <span
            class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700"><?= e((string) ($c['category'] ?? 'General')) ?></span>
          <span class="text-xs text-slate-400"><?= count($mats) ?> lesson<?= count($mats) === 1 ? '' : 's' ?> · 👥
            <?= count($c['enrolled'] ?? []) ?> enrolled</span>
        </summary>

        <?php if (!$mats): ?>
          <p class="px-4 pb-4 text-sm text-slate-400">No lessons in this course yet.</p>
        <?php else: ?>
          <ol class="divide-y divide-slate-100 border-t border-slate-100 px-4 pb-4">
            <?php $n = 0;
            foreach ($mats as $m):
                $n++;
                $mid = (int) $m['id'];
                $type = (string) ($m['type'] ?? 'file');
                if ($type === 'video') {
                    $icon = '🎬';
                    $kind = 'Video';
                } elseif ($type === 'youtube') {
                    $icon = '🔗';
                    $kind = 'Video link';
                } elseif (is_pasted_material($m)) {
                    $icon = '✍️';
                    $kind = 'Pasted text';
                } else {
                    $icon = '📄';
                    $kind = 'Document';
                }
                /* Files open in the reader; players (video / YouTube / Vimeo)
                   live on the course page, so that is where videos go. */
                $href = $type === 'file'
                    ? 'read.php?c=' . $cid . '&amp;m=' . $mid
                    : 'course.php?id=' . $cid;
                $meta = $kind;
                if ($type !== 'youtube' && !empty($m['size'])) {
                    $meta .= ' · ' . format_size((int) $m['size']);
                }
                if (!empty($m['created_at'])) {
                    $meta .= ' · ' . date('M j, Y', (int) $m['created_at']);
                }
                $desc = trim((string) ($m['description'] ?? ''));
                $quizTitle = $quizByMaterial[$mid] ?? null; ?>
              <li class="px-4 py-2.5">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="w-5 shrink-0 text-right text-xs font-bold text-slate-300"><?= $n ?></span>
                  <span class="text-base" aria-hidden="true"><?= $icon ?></span>
                  <a href="<?= $href ?>"
                    class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800 hover:text-indigo-600"
                    title="<?= e($desc !== '' ? $m['title'] . ' — ' . $desc : (string) $m['title']) ?>"><?= e((string) $m['title']) ?></a>
                  <span class="text-[11px] text-slate-400"><?= e($meta) ?></span>
                  <?php if ($quizTitle !== null): ?>
                    <a href="quiz.php?c=<?= $cid ?>&amp;m=<?= $mid ?>" title="Open the quiz preview: <?= e($quizTitle) ?>"
                      class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">🧪
                      <?= e($quizTitle) ?></a>
                  <?php endif; ?>
                </div>
                <?php if ($desc !== ''): ?>
                  <p class="mt-1 truncate text-[11px] text-slate-400"><?= e($desc) ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </details>
    <?php endforeach; endif; ?>
  </section>
<?php endforeach; endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>
