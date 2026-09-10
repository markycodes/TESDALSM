<?php /** Lesson list — included from course.php when the user can view materials.
 * Progress is automatic: videos count watch time, materials count reading depth/time. */
$mstates = material_user_states($userId, (int) $course['id']);
$progressSet = $course['progress'][$userId] ?? [];
?>
<section class="mt-6" data-tabs>
  <div class="w-fit rounded-xl bg-white p-1.5 shadow-sm ring-1 ring-slate-200">
    <div class="flex gap-2">
      <button data-tab-btn="videos" aria-selected="true" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 aria-selected:bg-indigo-600 aria-selected:text-white">🎬 Videos (<?= count($videos) ?>)</button>
      <button data-tab-btn="docs" aria-selected="false" class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 aria-selected:bg-indigo-600 aria-selected:text-white">📄 Materials (<?= count($docs) ?>)</button>
    </div>
  </div>

  <!-- Videos pane -->
  <div data-tab-pane="videos" class="mt-4 space-y-4">
    <?php if (!$videos): ?>
      <div class="rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
        <p class="text-4xl">🎬</p>
        <p class="mt-3 font-medium">No video lessons yet<?= $isOwner ? ' — click “＋ Add lesson” to upload one.' : '.' ?></p>
      </div>
    <?php else: foreach ($videos as $m):
      $mid = (int) $m['id'];
      $done = in_array($mid, $progressSet, true);
      $ms = $mstates[$mid] ?? [];
      $pct = (int) ($ms['percent'] ?? 0);
      $type = ($m['type'] ?? '');
      $embed = $type === 'youtube' ? ($m['embed'] ?? video_embed_url((string) ($m['url'] ?? ''))) : null;
      $ytId = $type === 'youtube' ? youtube_id((string) ($m['url'] ?? '')) : null;
      $isVimeo = $type === 'youtube' && $embed !== null && $ytId === null;
      $badge = $done ? '✓ Completed' : ($pct > 0 ? '▶ ' . $pct . '% watched' : 'Not started');
    ?>
    <article class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <?php if ($type === 'youtube' && $ytId !== null): ?>
        <div class="relative aspect-video w-full bg-black">
          <div data-yt="<?= e((string) $ytId) ?>" data-done="<?= $done ? '1' : '0' ?>" data-course="<?= e((string) $course['id']) ?>" data-material="<?= e((string) $mid) ?>"
               data-watched="<?= (int) ($ms['watched'] ?? 0) ?>" data-position="<?= (int) ($ms['position'] ?? 0) ?>" class="h-full w-full"></div>
          <div data-overlay-for="<?= e((string) $mid) ?>" class="js-video-done-overlay absolute inset-0 z-10 <?= $done ? 'flex' : 'hidden' ?> items-center justify-center bg-black/70">
            <div class="flex flex-col items-center gap-2.5 rounded-2xl bg-slate-900/90 px-6 py-4 text-center ring-1 ring-emerald-300">
              <span class="text-sm font-semibold text-emerald-400">✓ Completed</span>
              <span class="max-w-[280px] text-center text-xs text-slate-200">You watched the whole video — great job!</span>
              <button type="button" class="js-video-replay rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">↻ Replay video</button>
            </div>
          </div>
        </div>
      <?php elseif ($type === 'youtube' && $embed !== null): ?>
        <iframe class="aspect-video w-full" src="<?= e((string) $embed) ?>" title="<?= e((string) $m['title']) ?>" loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
      <?php else: ?>
        <div class="relative aspect-video w-full bg-black">
          <video controls preload="metadata" class="h-full w-full" data-watch data-done="<?= $done ? '1' : '0' ?>"
                 data-course="<?= e((string) $course['id']) ?>" data-material="<?= e((string) $mid) ?>"
                 data-watched="<?= (int) ($ms['watched'] ?? 0) ?>" data-position="<?= (int) ($ms['position'] ?? 0) ?>"
                 src="download.php?c=<?= e((string) $course['id']) ?>&amp;m=<?= e((string) $mid) ?>&amp;disp=inline"></video>
          <div data-overlay-for="<?= e((string) $mid) ?>" class="js-video-done-overlay absolute inset-0 z-10 <?= $done ? 'flex' : 'hidden' ?> items-center justify-center bg-black/70">
            <div class="flex flex-col items-center gap-2.5 rounded-2xl bg-slate-900/90 px-6 py-4 text-center ring-1 ring-emerald-300">
              <span class="text-sm font-semibold text-emerald-400">✓ Completed</span>
              <span class="max-w-[280px] text-center text-xs text-slate-200">You watched the whole video — great job!</span>
              <button type="button" class="js-video-replay rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">↻ Replay video</button>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <div class="p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <h3 class="font-bold text-slate-900"><?= e((string) $m['title']) ?></h3>
            <?php if (!empty($m['description'])): ?><p class="mt-1 text-sm text-slate-500"><?= e((string) $m['description']) ?></p><?php endif; ?>
            <p class="mt-2 text-xs text-slate-400">
              <?= $type === 'youtube' ? '🔗 Embedded video' : '🎬 Uploaded video' . (!empty($m['size']) ? ' · ' . format_size((int) $m['size']) : '') ?>
              · <?= date('M j, Y', (int) ($m['created_at'] ?? time())) ?>
            </p>
          </div>
          <div class="flex items-center gap-2">
            <span data-lesson-state="<?= e((string) $mid) ?>" class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $done ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>"><?= e($badge) ?></span>
            <?php if ($isOwner): ?>
              <form method="post" action="delete_material.php" data-confirm="Delete this video lesson?">
                <?= csrf_field() ?>
                <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
                <input type="hidden" name="material_id" value="<?= e((string) $mid) ?>">
                <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-600">Delete</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($isVimeo && ($enrolled || $isOwner)): ?>
        <div class="mt-3">
          <button type="button" class="js-vimeo-done rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100"
                  data-course="<?= e((string) $course['id']) ?>" data-material="<?= e((string) $mid) ?>">✓ I finished watching this video</button>
        </div>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; endif; ?>
  </div>

  <!-- Materials pane -->
  <div data-tab-pane="docs" class="mt-4 hidden">
    <?php if (!$docs): ?>
      <div class="rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500">
        <p class="text-4xl">📄</p>
        <p class="mt-3 font-medium">No materials yet<?= $isOwner ? ' — upload PDFs, Word documents, slides or images.' : '.' ?></p>
      </div>
    <?php else: ?>
    <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <?php foreach ($docs as $m):
        $mid = (int) $m['id'];
        $done = in_array($mid, $progressSet, true);
        $ms = $mstates[$mid] ?? [];
        $depth = (int) ($ms['depth'] ?? 0);
        $ext = ext_of((string) ($m['orig_name'] ?? ($m['filename'] ?? '')));
      ?>
      <div class="flex flex-wrap items-center gap-3 p-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-xs font-extrabold uppercase <?= ext_color($ext) ?>"><?= e($ext !== '' ? $ext : 'file') ?></span>
        <div class="min-w-0 flex-1">
          <h3 class="truncate font-semibold text-slate-900"><?= e((string) $m['title']) ?></h3>
          <p class="truncate text-xs text-slate-500">
            <?= e((string) ($m['orig_name'] ?? '')) ?> · <?= format_size((int) ($m['size'] ?? 0)) ?> · <?= date('M j, Y', (int) ($m['created_at'] ?? time())) ?>
            <?php if (!empty($m['description'])): ?> · <?= e((string) $m['description']) ?><?php endif; ?>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <?php if ($done): ?>
            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">✓ Completed</span>
          <?php elseif ($enrolled && $depth > 0): ?>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">📖 <?= $depth ?>% read</span>
          <?php endif; ?>
          <a href="read.php?c=<?= e((string) $course['id']) ?>&amp;m=<?= e((string) $mid) ?>"
             class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">📖 Read</a>
          <?php if ($isOwner): ?>
            <a href="download.php?c=<?= e((string) $course['id']) ?>&amp;m=<?= e((string) $mid) ?>"
               class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-slate-50" title="Download original file">⬇ Original</a>
            <form method="post" action="delete_material.php" data-confirm="Delete this material?">
              <?= csrf_field() ?>
              <input type="hidden" name="course_id" value="<?= e((string) $course['id']) ?>">
              <input type="hidden" name="material_id" value="<?= e((string) $mid) ?>">
              <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-600">Delete</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
