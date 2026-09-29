<?php if (!function_exists('db')) { http_response_code(403); exit('Forbidden'); } /* include-only partial: no direct URL access */
/**
 * Dashboard widget — "Today's schedule".
 *
 * Rendered from the data the dashboard already loaded ($schedRows, $schedToday,
 * $schedNext), so a dashboard makes one query for the card, not one per item, and
 * both sides see exactly the week the Schedule page shows. A teacher gets a way
 * straight into the builder; a student gets the read-only half of it.
 */
$slotName = function (array $r): string {
    if (trim((string) ($r['title'] ?? '')) !== '') return (string) $r['title'];
    $k = schedule_kinds()[(string) ($r['kind'] ?? 'class')] ?? null;
    return $k ? $k['label'] : 'Class';
};
$slotKind = fn (array $r) => schedule_kinds()[(string) ($r['kind'] ?? 'class')] ?? schedule_kinds()['class'];
?>
<section class="reveal mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:p-5">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">🗓️ Today's schedule
      <?php if ($schedToday): ?><span
          class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700"><?= count($schedToday) ?>
          today</span><?php endif; ?>
    </h2>
    <div class="flex items-center gap-3">
      <?php if ($isTeacher): ?><a href="schedule.php"
          class="text-xs font-semibold text-indigo-600 hover:underline">＋ Add a slot</a><?php endif; ?>
      <a href="schedule.php" class="text-xs font-semibold text-emerald-700 hover:underline">Full week →</a>
    </div>
  </div>

  <?php if ($schedToday): ?>
    <ul class="mt-3 space-y-2">
      <?php foreach ($schedToday as $it): $k = $slotKind($it); ?>
        <li
          class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-slate-100 bg-slate-50/70 px-3 py-2 <?= !empty($it['past']) ? 'opacity-55' : '' ?>">
          <span
            class="rounded-lg bg-white px-2 py-1 text-[11px] font-bold text-slate-700 ring-1 ring-slate-200"><?= e(schedule_time_label($it)) ?></span>
          <span class="text-sm font-semibold text-slate-800"><?= e($slotName($it)) ?></span>
          <span
            class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide <?= e($k['chip']) ?>"><?= $k['icon'] ?></span>
          <span class="text-xs font-medium text-slate-500"><?= e((string) $it['course_title']) ?></span>
          <?php if (trim((string) $it['place']) !== ''): ?><span
              class="text-xs text-slate-500">📍 <?= e((string) $it['place']) ?></span><?php endif; ?>
          <?php if (empty($it['past'])): ?>
            <a href="live_join.php?course=<?= (int) $it['course_id'] ?>"
              class="ml-auto rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-emerald-700">▶
              <?= $isTeacher ? 'Class link' : 'Join' ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($schedNext && !empty($schedNext[0]['on_date']) && $schedNext[0]['on_date'] !== date('Y-m-d')): ?>
      <p class="mt-3 text-xs text-slate-400">Next up: <b
          class="font-semibold text-slate-600"><?= e($schedNext[0]['when']) ?></b>
        <?= e(schedule_time_label($schedNext[0])) ?> — <?= e($slotName($schedNext[0])) ?></p>
    <?php endif; ?>
  <?php elseif ($schedNext): ?>
    <p class="mt-3 text-sm text-slate-500">Nothing scheduled today.</p>
    <ul class="mt-2 space-y-2">
      <?php foreach ($schedNext as $it): $k = $slotKind($it); ?>
        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-slate-100 px-3 py-2">
          <span
            class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600"><?= e((string) $it['when']) ?></span>
          <span
            class="rounded-lg bg-white px-2 py-1 text-[11px] font-bold text-slate-700 ring-1 ring-slate-200"><?= e(schedule_time_label($it)) ?></span>
          <span class="text-sm font-medium text-slate-800"><?= e($slotName($it)) ?></span>
          <span
            class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide <?= e($k['chip']) ?>"><?= $k['icon'] ?></span>
          <span class="text-xs text-slate-500"><?= e((string) $it['course_title']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="mt-3 text-sm text-slate-500"><?= $isTeacher
      ? 'Nothing on the timetable yet — add your weekly classes and every enrolled student sees them here.'
      : 'No schedule yet — your teacher has not posted one for your courses.' ?></p>
    <?php if ($isTeacher): ?>
      <a href="schedule.php"
        class="mt-3 inline-block rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">＋
        Set up the schedule</a>
    <?php endif; ?>
  <?php endif; ?>
</section>
