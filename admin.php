<?php
/**
 * Main-admin control panel.
 * - Shut the website down / bring it back online (maintenance mode)
 * - Generate one-time access codes that let new teachers register
 * - Link to site settings (mail delivery) — teachers no longer have access to those
 * - Change the admin password
 */
require_once __DIR__ . '/lib.php';
$user = require_admin();
$nav_active = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'maintenance_on') {
        setting_set('maintenance', '1');
        set_flash('success', '🛑 The website is now CLOSED to visitors. Only you (the admin) can browse while it is shut down.');
    } elseif ($action === 'maintenance_off') {
        setting_set('maintenance', '0');
        set_flash('success', '✅ The website is back online for everyone.');
    } elseif ($action === 'gen_teacher_code') {
        $code = generate_teacher_code((int) $user['id']);
        if ($code === null) {
            set_flash('error', 'Too many unused codes are outstanding (50) — revoke one first.');
        } else {
            set_flash('success', 'Teacher access code generated: ' . $code . ' — give it to the teacher (one-time use).');
        }
    } elseif ($action === 'revoke_code') {
        delete_teacher_code((int) ($_POST['code_id'] ?? 0));
        set_flash('success', 'Access code revoked.');
    } elseif ($action === 'change_password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $conf = (string) ($_POST['confirm'] ?? '');
        $pwError = password_strength_error($new);
        if ($pwError !== null) {
            set_flash('error', $pwError);
        } elseif ($new !== $conf) {
            set_flash('error', 'New password and confirmation do not match.');
        } elseif (!password_verify($cur, (string) $user['password'])) {
            set_flash('error', 'Your current password is incorrect.');
        } else {
            db()->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
            set_flash('success', 'Admin password updated.');
        }
    }
    header('Location: admin.php');
    exit;
}

$maint  = maintenance_enabled();
$tcodes = admin_teacher_codes();
$diag   = mail_diagnostics();
$overview = admin_course_overview();
$onlineTeachers = admin_online_users(['teacher']);
$onlineStudents = admin_online_users(['student']);
/* The circles and the hover cards below read through profile_card_data()'s
   per-request cache, so one query primes the whole list here. */
profile_cards_preload(array_column($onlineTeachers, 'id'));
profile_cards_preload(array_column($onlineStudents, 'id'));

/* tiny stats strip */
$stats = ['admins' => 0, 'teachers' => 0, 'students' => 0, 'courses' => 0, 'enrollments' => 0];
try {
    foreach (db()->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetchAll() as $r) {
        $stats[$r['role'] . 's'] = (int) $r['c'];
    }
    $stats['courses'] = (int) db()->query('SELECT COUNT(*) FROM courses')->fetchColumn();
    $stats['enrollments'] = (int) db()->query('SELECT COUNT(*) FROM enrollments')->fetchColumn();

    /* Today's timetable across every course — the admin side is the one place
       that sees all of them at once, so this is where a school-wide view of
       who is teaching whom belongs. Course-scoped like the rest of the app:
       the rows only ever come from schedule_rows_for_courses(). */
    $scheduleKinds = schedule_kinds();
    $adminTodayItems = schedule_today(schedule_rows_for_courses(schedule_teacher_course_ids(0, true)));

    /* Every class live anywhere in the school right now. Read-only on purpose:
       the admin may sit in on any of them (live_class_can_join) but never
       starts or ends one — that stays with the teacher who owns the course. */
    $liveNow = live_classes_now();
} catch (Throwable $e) { /* stats are decorative */ }

function lh_ago_txt(int $ts): string
{
    $d = time() - $ts;
    return $d < 60 ? 'just now' : ($d < 3600 ? floor($d / 60) . 'm ago' : ($d < 86400 ? floor($d / 3600) . 'h ago' : floor($d / 86400) . 'd ago'));
}

$page_title = 'Admin';
require __DIR__ . '/header.php';
?>

<div class="lh-admin-page mx-auto max-w-7xl">
  <div class="lh-admin-hero reveal">
    <div class="flex flex-wrap items-start justify-between gap-5">
      <div>
        <p class="lh-admin-eyebrow">LearnHub · Administration</p>
        <h1 class="lh-admin-title">Admin control center</h1>
        <p class="lh-admin-description">A school-wide overview of courses, people, schedules, and system health.</p>
      </div>
      <span class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-white/80 px-3.5 py-2 text-xs font-bold text-indigo-700 shadow-sm">
        <span class="h-2 w-2 rounded-full <?= $maint ? 'bg-rose-500' : 'bg-emerald-500' ?>"></span>
        <?= $maint ? 'Maintenance mode' : 'System online' ?>
      </span>
    </div>
    <div class="mt-5 flex flex-wrap gap-2">
      <a href="admin_lessons.php" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700">Browse lessons <span aria-hidden="true">→</span></a>
      <a href="admin_progress.php" class="rounded-xl border border-indigo-200 bg-white/80 px-4 py-2.5 text-sm font-bold text-indigo-700 transition hover:bg-white">Track student progress</a>
      <a href="codes.php" class="rounded-xl border border-slate-200 bg-white/80 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-white">Manage invite codes</a>
    </div>
  </div>

  <!-- stats strip -->
  <div class="reveal mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
    <?php foreach ([['👨‍🎓', 'Students', $stats['students']], ['👩‍🏫', 'Teachers', $stats['teachers']], ['🔗', 'Enrollments', $stats['enrollments']], ['📚', 'Courses', $stats['courses']], ['🛡️', 'Admins', $stats['admins']]] as $s): ?>
      <div class="lh-admin-stat reveal">
        <div class="relative z-10 mb-2 text-lg" aria-hidden="true"><?= $s[0] ?></div>
        <div class="lh-admin-stat-value"><?= (int) $s[2] ?></div>
        <div class="lh-admin-stat-label"><?= $s[1] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- courses & enrollment overview -->
  <div class="reveal mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lh-admin-card lg:col-span-2 p-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-bold text-slate-900">📚 Courses &amp; enrollment</h2>
        <span class="flex flex-wrap items-center gap-2 text-xs text-slate-400"><?= count($overview) ?> courses · <?= (int) $stats['enrollments'] ?> total enrollments
          <a href="admin_lessons.php"
            class="rounded-full bg-indigo-50 px-2.5 py-0.5 font-semibold text-indigo-700 hover:bg-indigo-100">📚 Lessons by teacher →</a></span>
        <a href="admin_progress.php"
          class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">✅ Student completion →</a>
      </div>
      <?php if (!$overview): ?>
        <p class="mt-4 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">No courses yet.</p>
      <?php else: ?>
      <div class="lh-admin-table-wrap mt-4">
        <table class="lh-admin-table w-full min-w-[520px] text-left text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
              <th class="px-2 py-2">Course</th>
              <th class="px-2 py-2">Teacher</th>
              <th class="px-2 py-2">Category</th>
              <th class="px-2 py-2 text-right">👥 Students</th>
            </tr>
          </thead>
          <tbody>
            <?php $sum = 0; foreach ($overview as $o): $sum += (int) $o['enrolled']; ?>
            <tr class="border-b border-slate-100">
              <td class="px-2 py-3 font-semibold text-slate-800"><a class="hover:text-emerald-700" href="course.php?id=<?= (int) $o['id'] ?>"><?= e((string) $o['title']) ?></a></td>
              <td class="px-2 py-3 text-slate-600"><?= e((string) $o['teacher_name']) ?></td>
              <td class="px-2 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500"><?= e((string) $o['category']) ?></span></td>
              <td class="px-2 py-3 text-right font-bold <?= $o['enrolled'] > 0 ? 'text-emerald-700' : 'text-slate-300' ?>"><?= (int) $o['enrolled'] ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="text-sm font-bold text-slate-700">
              <td class="px-2 py-3" colspan="3">Total enrolled</td>
              <td class="px-2 py-3 text-right text-emerald-700"><?= $sum ?></td>
            </tr>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="lh-admin-card p-6">
      <h2 class="text-base font-bold text-slate-900">🟢 Teachers online now</h2>
      <?php if (!$onlineTeachers): ?>
        <p class="mt-4 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">No teachers online right now.</p>
      <?php else: ?>
        <ul class="mt-4 space-y-3">
          <?php foreach ($onlineTeachers as $t): ?>
          <li class="flex items-center gap-3">
            <span class="relative shrink-0"><?= user_peer_avatar_html($t, 'h-9 w-9') ?><span
                class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"></span></span>
            <span class="min-w-0 flex-1 leading-tight"<?= profile_hover_attrs($t) ?>>
              <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $t['name']) ?></span>
              <span class="block text-[11px] text-slate-400">online · <?= e(lh_ago_txt((int) $t['last_seen'])) ?></span>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
        <p class="mt-4 text-[11px] text-slate-400">Online = active in the last 3 minutes.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- students online -->
  <div class="reveal mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lh-admin-card lg:col-span-2 p-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-bold text-slate-900">🟣 Students online now</h2>
        <span class="text-xs text-slate-400"><?= count($onlineStudents) ?> online · <?= (int) $stats['students'] ?> total</span>
      </div>
      <?php if (!$onlineStudents): ?>
        <p class="mt-4 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">No students online right now.</p>
      <?php else: ?>
        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
          <?php foreach ($onlineStudents as $s): ?>
          <li class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2">
            <span class="relative shrink-0"><?= user_peer_avatar_html($s, 'h-9 w-9') ?><span
                class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500"></span></span>
            <span class="min-w-0 flex-1 leading-tight"<?= profile_hover_attrs($s) ?>>
              <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) $s['name']) ?></span>
              <span class="block text-[11px] text-slate-400">online · <?= e(lh_ago_txt((int) $s['last_seen'])) ?></span>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
        <p class="mt-4 text-[11px] text-slate-400">Online = active in the last 3 minutes.</p>
      <?php endif; ?>
    </div>

    <div class="lh-admin-card p-6">
      <h2 class="text-base font-bold text-slate-900">🗓️ Timetable today</h2>
      <?php if (!$adminTodayItems): ?>
        <p class="mt-4 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">No teacher has anything scheduled today.</p>
      <?php else: ?>
        <ul class="mt-4 space-y-3">
          <?php foreach (array_slice($adminTodayItems, 0, 6) as $it): ?>
          <li class="flex items-center gap-3">
            <span class="shrink-0"><?= schedule_teacher_face_html($it, 'h-7 w-7') ?></span>
            <span class="min-w-0 flex-1 leading-tight">
              <span class="block truncate text-sm font-semibold text-slate-800"><?= e($it['title'] !== '' ? $it['title'] : ($scheduleKinds[$it['kind']]['label'] ?? 'Class')) ?></span>
              <span class="block truncate text-[11px] text-slate-400"><?= e((string) ($it['teacher_name'] ?? '')) ?> · <?= e(schedule_clock((string) $it['start_time'])) ?></span>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php if (count($adminTodayItems) > 6): ?>
          <p class="mt-3 text-[11px] text-slate-400">+<?= count($adminTodayItems) - 6 ?> more today</p>
        <?php endif; ?>
      <?php endif; ?>
      <a href="schedule.php"
        class="mt-4 inline-block rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">Open the full timetable →</a>
    </div>
  </div>

  <!-- live classes happening anywhere right now -->
  <?php if ($liveNow): ?>
  <div class="lh-admin-card reveal mt-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h2 class="text-base font-bold text-slate-900">🔴 Live classes now</h2>
      <span class="text-xs text-slate-400"><?= count($liveNow) ?> running</span>
    </div>
    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
      <?php foreach ($liveNow as $lc):
        $host = (array) find_user_by_id((int) $lc['host_id']); ?>
      <li class="flex items-center gap-3 rounded-xl border border-rose-100 bg-rose-50/40 px-3 py-2">
        <span class="relative shrink-0"><?= user_peer_avatar_html($host, 'h-9 w-9') ?><span
            class="absolute -bottom-0.5 -right-0.5 h-3 w-3 animate-pulse rounded-full border-2 border-white bg-rose-500"></span></span>
        <span class="min-w-0 flex-1 leading-tight">
          <span class="block truncate text-sm font-semibold text-slate-800"><?= e((string) ($lc['course_title'] ?? 'Live class')) ?></span>
          <span class="block truncate text-[11px] text-slate-500"><?= e((string) ($lc['host_name'] ?? '')) ?><?= ($lc['title'] ?? '') !== '' ? ' · ' . e((string) $lc['title']) : '' ?></span>
          <span class="block text-[11px] text-slate-400">started <?= e(lh_ago_txt((int) $lc['started_at'])) ?></span>
        </span>
        <a href="<?= e(live_class_join_link((int) $lc['course_id'])) ?>"
          class="shrink-0 rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">Join</a>
      </li>
      <?php endforeach; ?>
    </ul>
    <p class="mt-4 text-[11px] text-slate-400">You can observe any live class. Starting or ending one stays with the teacher who owns the course.</p>
  </div>
  <?php endif; ?>

  <!-- site control -->
  <div class="lh-admin-card reveal mt-6 p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-base font-bold text-slate-900">Website status</h2>
        <p class="mt-1 text-sm text-slate-500">Shutting down hides the site: every visitor gets a plain &quot;Website has an error&quot; page (HTTP 503) that is redrawn differently on each reload. Only you can browse while it is closed.</p>
      </div>
      <?php if ($maint): ?>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-600">● SHUT DOWN</span>
      <?php else: ?>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">● ONLINE</span>
      <?php endif; ?>
    </div>
    <form method="post" class="mt-4">
      <?= csrf_field() ?>
      <?php if ($maint): ?>
        <button name="action" value="maintenance_off"
          class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">✅ Bring the website back online</button>
      <?php else: ?>
        <button name="action" value="maintenance_on" data-confirm="Shut down the website? Every student and teacher will see a plain 'Website has an error' page until you bring it back online."
          class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-700">🛑 Shut down the website</button>
      <?php endif; ?>
    </form>
  </div>
  <!-- teacher access codes -->
  <div class="lh-admin-card reveal mt-6 p-6">
    <h2 class="text-base font-bold text-slate-900">👩‍🏫 Teacher access codes</h2>
    <p class="mt-1 text-sm text-slate-500">A person can only register as a <b>teacher</b> with one of these one-time codes (choose “Teacher” on the
      <a href="register.php" class="font-semibold text-emerald-700 hover:underline">registration page</a> and enter it). Students still get their codes from their teachers.</p>
    <form method="post" class="mt-4">
      <?= csrf_field() ?>
      <button name="action" value="gen_teacher_code"
        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">🔑 Generate a teacher code</button>
    </form>

    <div class="lh-admin-card reveal lh-plain mt-5">
      <?php if (!$tcodes): ?>
        <p class="p-5 text-center text-sm text-slate-400">No teacher codes yet — generate the first one above.</p>
      <?php else: ?>
      <div class="lh-admin-table-wrap mt-4">
        <table class="lh-admin-table w-full min-w-[520px] text-left text-sm">
          <thead>
            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
              <th class="px-2 py-2">Code</th>
              <th class="px-2 py-2">Created</th>
              <th class="px-2 py-2">Status</th>
              <th class="px-2 py-2 text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tcodes as $c): $used = !empty($c['used_by']); ?>
            <tr class="border-b border-slate-100">
              <td class="px-2 py-3">
                <span class="font-mono text-sm font-bold text-slate-800"><?= e((string) $c['code']) ?></span>
                <?php if (!$used): ?>
                  <button type="button" data-lh-copy="<?= e((string) $c['code']) ?>" data-lh-label="copy" title="Copy code"
                    class="ml-2 rounded-lg border border-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-500 hover:bg-slate-50">copy</button>
                <?php endif; ?>
              </td>
              <td class="px-2 py-3 text-slate-400"><?= date('M j, g:i a', (int) $c['created_at']) ?></td>
              <td class="px-2 py-3">
                <?php if ($used): ?>
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Used by <?= e((string) ($c['used_by_name'] ?? 'someone')) ?></span>
                <?php else: ?>
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">● Unused</span>
                <?php endif; ?>
              </td>
              <td class="px-2 py-3 text-right">
                <?php if ($used): ?>
                  <span class="text-xs text-slate-300">—</span>
                <?php else: ?>
                  <form method="post" data-confirm="Revoke this access code? It can no longer be used.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="revoke_code">
                    <input type="hidden" name="code_id" value="<?= (int) $c['id'] ?>">
                    <button class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-500 hover:bg-rose-50 hover:text-rose-600">Revoke</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- settings + account -->
  <div class="reveal mt-6 grid gap-6 lg:grid-cols-2">
    <div class="lh-admin-card p-6">
      <h2 class="text-base font-bold text-slate-900">⚙️ Site settings</h2>
      <p class="mt-1 text-sm text-slate-500">
        <?php $lhT = (string) ($diag['transport'] ?? 'none');
              $lhTLabel = ['brevo-api' => 'Brevo API', 'sendgrid-api' => 'SendGrid API', 'resend-api' => 'Resend API', 'php-mail' => 'PHP mail()', 'none' => 'not configured'][$lhT] ?? $lhT; ?>
        E-mail delivery: <b class="<?= $lhT !== 'none' && $lhT !== 'php-mail' ? 'text-emerald-700' : 'text-rose-600' ?>"><?= e($lhTLabel) ?></b>
        <?php if (array_key_exists('sender_ok', $diag) && $diag['sender_ok'] !== null): ?>
          · sender <?= $diag['sender_ok'] ? '✅ validated' : '❌ not validated' ?>
        <?php endif; ?>
      </p>
      <p class="mt-1 text-xs text-slate-400">Moved here from the teacher account — the admin controls it.</p>
      <a href="settings.php"
        class="mt-4 inline-block rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Open settings</a>
    </div>

    <div class="lh-admin-card p-6">
      <h2 class="text-base font-bold text-slate-900">🔐 Admin password</h2>
      <form method="post" class="mt-3 space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <input name="current" type="password" required placeholder="Current password"
          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <input name="new" type="password" required minlength="8" placeholder="New password (min 8 — UPPER + lower + number)"
          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <input name="confirm" type="password" required minlength="8" placeholder="Repeat new password"
          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <button class="w-full rounded-xl bg-indigo-600 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Update password</button>
      </form>
    </div>
  </div>

</div>
<?php require __DIR__ . '/footer.php'; ?>
