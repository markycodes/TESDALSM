<?php
/**
 * Enrollment codes — teachers pick a course and generate a unique invitation code.
 * Students use the code when registering; it redeems into exactly that course
 * (no self-enrollment anywhere else in the app).
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
if (!in_array(($user['role'] ?? ''), ['teacher', 'admin'], true)) {
    http_response_code(403);
    exit('This page is for teachers only.');
}
$nav_active = 'codes';
$teacherId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'generate') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $course = course_row($courseId);
        if (!$course || (int) $course['teacher_id'] !== $teacherId) {
            set_flash('error', 'Choose one of your own courses to generate a code for.');
        } else {
            $code = generate_enroll_code($teacherId, $courseId);
            if ($code === null) {
                set_flash('error', 'This course already has 25 unused codes — revoke one or reuse an existing code.');
            } else {
                set_flash('success', 'Invitation code generated: ' . $code . ' — send it to your student (one-time use).');
            }
        }
    } elseif ($action === 'revoke') {
        delete_enroll_code($teacherId, (int) ($_POST['code_id'] ?? 0));
        set_flash('success', 'Invitation code revoked.');
    }
    header('Location: codes.php');
    exit;
}

$st = db()->prepare('SELECT id, title, category FROM courses WHERE teacher_id = ? ORDER BY id');
$st->execute([$teacherId]);
$myCourses = $st->fetchAll();
$codes = teacher_enroll_codes($teacherId);

$page_title = 'Invitation codes';
require __DIR__ . '/header.php';
?>

<div class="mx-auto max-w-4xl">
  <div class="reveal">
    <h1 class="text-2xl font-bold text-slate-900">🔑 Invitation codes</h1>
    <p class="mt-1 text-sm text-slate-500">Generate a one-time code for the course a student should join. When they register with it, they are enrolled in that exact course.</p>
  </div>

  <!-- Generate -->
  <div class="reveal mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h2 class="text-base font-bold text-slate-900">Generate a code</h2>
<!-- List -->
  <div class="reveal lh-plain mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <div class="flex items-center justify-between">
      <h2 class="text-base font-bold text-slate-900">Your codes</h2>
      <span class="text-xs text-slate-400"><?= count($codes) ?> total</span>
    </div>

    <?php if (!$codes): ?>
      <p class="mt-4 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">No codes yet — generate your first one above.</p>
    <?php else: ?>
    <div class="mt-4 overflow-x-auto">
      <table class="w-full min-w-[560px] text-left text-sm">
        <thead>
          <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
            <th class="px-2 py-2">Code</th>
            <th class="px-2 py-2">Course</th>
            <th class="px-2 py-2">Created</th>
            <th class="px-2 py-2">Status</th>
            <th class="px-2 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($codes as $c): $used = !empty($c['used_by']); ?>
          <tr class="border-b border-slate-100">
            <td class="px-2 py-3">
              <span class="font-mono text-sm font-bold text-indigo-700"><?= e((string) $c['code']) ?></span>
              <button type="button" data-copy="<?= e((string) $c['code']) ?>" class="ml-2 rounded-lg border border-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-500 hover:bg-slate-50" title="Copy code">copy</button>
            </td>
            <td class="px-2 py-3 text-slate-700"><?= e((string) $c['course_title']) ?></td>
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
                <form method="post" action="codes.php" data-confirm="Revoke this code? It can no longer be used.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="revoke">
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

<script>
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var t = b.getAttribute('data-copy');
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(t).then(function () {
          b.textContent = 'copied!';
          setTimeout(function () { b.textContent = 'copy'; }, 1400);
        });
      } else {
        var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); ta.remove(); b.textContent = 'copied!';
        setTimeout(function () { b.textContent = 'copy'; }, 1400);
      }
    });
  });
</script>

    <p class="mt-1 text-sm text-slate-500">Choose the course — the code enrolls the student into that course only.</p>
    <form method="post" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="generate">
      <div class="flex-1">
        <label for="course_id" class="block text-sm font-medium text-slate-700">Course</label>
        <select id="course_id" name="course_id" required
                class="mt-1 w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
          <option value="">— choose a course —</option>
          <?php foreach ($myCourses as $mc): ?>
          <option value="<?= (int) $mc['id'] ?>"><?= e((string) $mc['title']) ?> (<?= e((string) ($mc['category'] ?? 'General')) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">＋ Generate</button>
    </form>
    <?php if (!$myCourses): ?>
      <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-700">You need a course first — create one from your dashboard before generating codes.</p>
    <?php endif; ?>
  </div>
<?php require __DIR__ . '/footer.php'; ?>
