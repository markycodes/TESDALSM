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
        if (strlen($new) < 8) {
            set_flash('error', 'New password must be at least 8 characters.');
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

/* tiny stats strip */
$stats = ['admins' => 0, 'teachers' => 0, 'students' => 0, 'courses' => 0];
try {
    foreach (db()->query("SELECT role, COUNT(*) c FROM users GROUP BY role")->fetchAll() as $r) {
        $stats[$r['role'] . 's'] = (int) $r['c'];
    }
    $stats['courses'] = (int) db()->query('SELECT COUNT(*) FROM courses')->fetchColumn();
} catch (Throwable $e) { /* stats are decorative */ }

$page_title = 'Admin';
require __DIR__ . '/header.php';
?>

<div class="mx-auto max-w-4xl">
  <div class="reveal">
    <h1 class="text-2xl font-bold text-slate-900">🛡️ Admin control</h1>
    <p class="mt-1 text-sm text-slate-500">Everything that concerns the whole website lives here — only the main administrator can open this page.</p>
  </div>

  <!-- stats strip -->
  <div class="reveal mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <?php foreach ([['👨‍🎓 Students', $stats['students']], ['👩‍🏫 Teachers', $stats['teachers']], ['🛡️ Admins', $stats['admins']], ['📚 Courses', $stats['courses']]] as $s): ?>
      <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-200">
        <div class="text-xl font-extrabold text-slate-900"><?= (int) $s[1] ?></div>
        <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-400"><?= $s[0] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- site control -->
  <div class="reveal mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-base font-bold text-slate-900">Website status</h2>
        <p class="mt-1 text-sm text-slate-500">Shutting down shows every visitor a "temporarily closed" notice. Only you can browse while it is closed.</p>
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
        <button name="action" value="maintenance_on" data-confirm="Shut down the website? Every student and teacher will see a closed notice until you bring it back online."
          class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-700">🛑 Shut down the website</button>
      <?php endif; ?>
    </form>
  </div>
  <!-- teacher access codes -->
  <div class="reveal mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h2 class="text-base font-bold text-slate-900">👩‍🏫 Teacher access codes</h2>
    <p class="mt-1 text-sm text-slate-500">A person can only register as a <b>teacher</b> with one of these one-time codes (choose “Teacher” on the
      <a href="register.php" class="font-semibold text-emerald-700 hover:underline">registration page</a> and enter it). Students still get their codes from their teachers.</p>
    <form method="post" class="mt-4">
      <?= csrf_field() ?>
      <button name="action" value="gen_teacher_code"
        class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">🔑 Generate a teacher code</button>
    </form>

    <div class="reveal lh-plain mt-5 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
      <?php if (!$tcodes): ?>
        <p class="p-5 text-center text-sm text-slate-400">No teacher codes yet — generate the first one above.</p>
      <?php else: ?>
      <div class="overflow-x-auto p-4">
        <table class="w-full min-w-[520px] text-left text-sm">
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
                  <button type="button" data-copy="<?= e((string) $c['code']) ?>"
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
    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
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

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <h2 class="text-base font-bold text-slate-900">🔐 Admin password</h2>
      <form method="post" class="mt-3 space-y-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <input name="current" type="password" required placeholder="Current password"
          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <input name="new" type="password" required minlength="8" placeholder="New password (min 8 characters)"
          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <input name="confirm" type="password" required minlength="8" placeholder="Repeat new password"
          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        <button class="w-full rounded-xl bg-indigo-600 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">Update password</button>
      </form>
    </div>
  </div>

</div>
<?php require __DIR__ . '/footer.php'; ?>
