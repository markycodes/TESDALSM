<?php
require_once __DIR__ . '/lib.php';

/* Where to continue after logging in: a shared live-class link sends students
   here with ?next=…, and only a local path is ever used (see login_next_path). */
$nextPath = login_next_path('dashboard.php');
if (current_user()) { header('Location: ' . lh_enc_url($nextPath)); exit; }

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $found = find_user_by_email($email);
    if ($found && password_verify($password, (string) ($found['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $found['id'];
        set_flash('success', 'Welcome back, ' . $found['name'] . '!');
        header('Location: ' . lh_enc_url($nextPath));
        exit;
    }
    $error = 'Wrong email or password.';
}
$page_title = 'Log in';
require __DIR__ . '/header.php';
?>

<div class="mx-auto max-w-md">
  <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
    <h1 class="text-2xl font-bold text-slate-900">Welcome back 👋</h1>
    <p class="mt-1 text-sm text-slate-500"><?= $nextPath !== 'dashboard.php'
      ? 'Log in to continue to your live class.'
      : 'Log in to your LearnHub account.' ?></p>
    <?php if ($error): ?>
      <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">⚠️ <?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="mt-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($nextPath) ?>">
      <div>
        <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
        <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="you@example.com"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
        <input id="password" name="password" type="password" required placeholder="••••••••"
               class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
      </div>
      <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Log in</button>
    </form>
    <p class="mt-6 text-center text-sm text-slate-500">No account? <a href="register.php<?= $nextPath !== 'dashboard.php' ? '?next=' . rawurlencode($nextPath) : '' ?>" class="font-semibold text-indigo-600 hover:underline">Create one free</a></p>
    <p class="mt-2 text-center text-sm text-slate-500">Forgot your password? <a href="reset_password.php" class="font-semibold text-indigo-600 hover:underline">Reset password</a></p>
  </div>

</div>

<?php require __DIR__ . '/footer.php'; ?>
