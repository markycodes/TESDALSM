<?php
/**
 * Mail test — sends a test e-mail to the logged-in user's own address and
 * returns the outcome plus the last mail.log lines, so mail problems are
 * visible from inside the deployed site instead of invisible.
 *
 * A provider "accepted" only means queued: it can still be refused at delivery
 * (e.g. Brevo's "sender you used … is not valid"). So this also asks the
 * provider what really happened to the message it just queued.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
verify_csrf();
header('Content-Type: application/json; charset=utf-8');

$stamp   = date('H:i:s');
$subject = 'LearnHub mail test ' . $stamp;   // unique → lets us find THIS mail's event
$to      = (string) $user['email'];
$ok      = send_email(
    $to,
    $subject,
    '<p>Hello ' . e((string) $user['name']) . ',</p><p>If you are reading this, e-mail delivery works from this server.</p>',
    'LearnHub mail test — delivery works.'
);

/* What did the provider actually do with it? (8s max, so the button feels live) */
$state = 'unknown';
$reason = '';
if ($ok) {
    $r = email_delivery_state($stamp, 8);
    $state  = $r['state'];
    $reason = $r['reason'];
}
$senderOk = email_sender_is_verified();
$diag     = mail_diagnostics();

$tail = [];
$log = DATA_DIR . '/mail.log';
if (is_file($log)) {
    $tail = array_slice(file($log, FILE_IGNORE_NEW_LINES), -4);
}

echo json_encode([
    'ok'        => $ok,
    'to'        => $to,
    'subject'   => $subject,
    'state'     => $state,      // delivered | error | queued | unknown
    'reason'    => $reason,     // provider's own words when it failed
    'sender_ok' => $senderOk,   // is the sender a validated sender at the provider?
    'from'      => mail_from(),  // the address actually used (config.php or the Settings page)
    'provider'  => mail_provider(),
    'diag'      => $diag,       // transport / reachable / config notes for THIS server
    'tail'      => $tail,
]);
exit;