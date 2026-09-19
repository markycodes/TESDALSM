<?php
/**
 * Password reset via e-mail. Step 1: request a link. Step 2: choose a new
 * password from the e-mailed token (?t=...). Tokens are single-use and live
 * 30 minutes; unknown/expired tokens show the request form with a friendly
 * message. Guests only — logged-in users change their password in Settings.
 */
require_once __DIR__ . '/lib.php';
if (current_user()) { header('Location: dashboard.php'); exit; }

$token = (string) ($_GET['t'] ?? ($_POST['t'] ?? ''));
$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'request') {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid e-mail address.';
        } else {
            password_reset_request($email);   /* false = unknown address; same message either way */
            $done = true;
        }
    } elseif ($action === 'apply') {
        $new = (string) ($_POST['password'] ?? '');
        $new2 = (string) ($_POST['password2'] ?? '');
        if (strlen($new) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif ($new2 !== $new) {
            $errors[] = 'The passwords do not match — please retype them.';
        } elseif (!password_reset_apply($token, $new)) {
            $errors[] = 'This reset link is invalid or has expired — request a fresh one below.';
            $token = '';   /* the token is dead; drop back to the request form */
        } else {
            set_flash('success', 'Password updated — log in with your new password.');
            header('Location: login.php');
            exit;
        }
    }
}

$validToken = $token !== '' && password_reset_user($token) !== null;
$page_title = $validToken ? 'Choose a new password' : 'Reset your password';
require __DIR__ . '/header.php';
?>
<div class="mx-auto max-w-md">
  <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
    <?php if ($validToken): ?>
      <h1 class="text-2xl font-bold text-slate-900">Choose a new password</h1>
      <p class="mt-1 text-sm text-slate-500">Pick something you don't use anywhere else.</p>
      <?php if ($errors): ?>
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">⚠️ <?= e(implode(' ', $errors)) ?></div>
      <?php endif; ?>
      <form method="post" class="mt-6 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="apply">
        <input type="hidden" name="t" value="<?= e($token) ?>">
        <div>
          <label class="block text-sm font-medium text-slate-700" for="password">New password</label>
          <input id="password" name="password" type="password" required minlength="6" placeholder="••••••••"
            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700" for="password2">Confirm new password</label>
          <input id="password2" name="password2" type="password" required minlength="6" placeholder="••••••••"
            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>
        <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Save new password</button>
      </form>
    <?php else: ?>
      <h1 class="text-2xl font-bold text-slate-900">Reset your password</h1>
      <p class="mt-1 text-sm text-slate-500">Enter your account e-mail and we'll send you a reset link.</p>
      <?php if ($errors): ?>
        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm font-medium text-rose-700">⚠️ <?= e(implode(' ', $errors)) ?></div>
      <?php endif; ?>
      <?php if ($done): ?>
        <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">📧 If that e-mail belongs to an account, a reset link is on its way — check your inbox (and spam folder). The link works once and expires in 30 minutes.</div>
      <?php endif; ?>
      <form method="post" class="mt-6 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="request">
        <div>
          <label class="block text-sm font-medium text-slate-700" for="email">E-mail</label>
          <input id="email" name="email" type="email" required value="<?= e((string) ($_POST['email'] ?? '')) ?>" placeholder="you@example.com"
            class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200">
        </div>
        <button class="w-full rounded-xl bg-indigo-600 py-2.5 font-semibold text-white shadow-sm hover:bg-indigo-700">Send reset link</button>
      </form>
    <?php endif; ?>
    <p class="mt-6 text-center text-sm text-slate-500">Remembered it? <a href="login.php" class="font-semibold text-indigo-600 hover:underline">Back to log in</a></p>
  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>