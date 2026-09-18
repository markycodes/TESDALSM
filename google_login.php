<?php
/**
 * "Continue with Google" — OAuth 2.0 endpoint.
 *
 *  GET /google_login.php            -> redirects to Google's consent screen
 *  GET /google_login.php?code=…&state=…  -> Google comes back here:
 *      • e-mail belongs to an existing user  -> signed in, on to the dashboard
 *      • brand-new e-mail                    -> the visitor is sent to
 *         register.php?mode=google, where they MUST enter their full name and
 *         a course invitation code (student) or a teacher access code (teacher)
 *         before any account is created.
 */
require_once __DIR__ . '/lib.php';
if (current_user()) { header('Location: dashboard.php'); exit; }

if (!google_configured()) {
    http_response_code(404);
    $page_title = 'Google sign-in';
    require __DIR__ . '/header.php';
    echo '<div class="mx-auto max-w-md"><div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">'
        . '<h1 class="text-2xl font-bold text-slate-900">🔤 Continue with Google</h1>'
        . '<p class="mt-3 text-sm text-slate-500">Google sign-in is not enabled on this site yet. '
        . 'The administrator can switch it on under <b>Settings → Continue with Google</b>.</p>'
        . '<a href="login.php" class="mt-6 inline-block rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Back to login</a>'
        . '</div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

/* Google reports "user cancelled" or an upstream error */
if (isset($_GET['error'])) {
    set_flash('error', 'Google sign-in was cancelled. You can log in with your e-mail instead.');
    header('Location: login.php');
    exit;
}

if (isset($_GET['code'])) {
    $state = (string) ($_GET['state'] ?? '');
    $want  = (string) ($_SESSION['google_state'] ?? '');
    unset($_SESSION['google_state']);
    if ($state === '' || $want === '' || !hash_equals($want, $state)) {
        set_flash('error', 'Your Google sign-in link expired — please press the button again.');
        header('Location: login.php');
        exit;
    }
    $profile = google_exchange_code((string) $_GET['code']);
    if (!$profile) {
        set_flash('error', 'Google sign-in failed — please try again, or register with a password.');
        header('Location: login.php');
        exit;
    }
    if (empty($profile['email_verified'])) {
        set_flash('error', 'Your Google e-mail address is not verified, so the site cannot trust it. Verify it at google.com and try again.');
        header('Location: login.php');
        exit;
    }
    $existing = find_user_by_email($profile['email']);
    if ($existing) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $existing['id'];
        set_flash('success', 'Welcome back, ' . $existing['name'] . '! Signed in with Google.');
        header('Location: dashboard.php');
        exit;
    }
    /* brand-new visitor: remember who Google verified, then require name + code */
    $_SESSION['google_pending'] = [
        'email' => $profile['email'],
        'name'  => $profile['name'],
        'sub'   => $profile['sub'],
    ];
    header('Location: register.php?mode=google');
    exit;
}

/* no code yet -> start the dance */
$state = bin2hex(random_bytes(16));
$_SESSION['google_state'] = $state;
header('Location: ' . google_auth_url($state));
exit;