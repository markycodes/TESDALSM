<?php
/**
 * E-mail settings — configurable FROM THE DEPLOYED SITE.
 *
 * Why this page exists: config.php is git-ignored, so a fresh upload to the
 * server carries the database details but usually NOT the e-mail API key.
 * Without a key the app can only try PHP mail(), which free hosting disables —
 * so registration e-mails silently never leave the server. Saving the key here
 * stores it in the database, where it is available on every request, with no
 * FTP access needed.
 *
 * Precedence: a value in config.php always wins over a saved setting, so a
 * locally-configured machine keeps its own settings.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
if (($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('This page is for the main administrator only.');
}
$nav_active = 'settings';

const MAIL_PROVIDERS = [
    'brevo'    => 'Brevo (free 300/day — recommended)',
    'sendgrid' => 'SendGrid (free 100/day)',
    'resend'   => 'Resend (free 100/day)',
];
const MAIL_PROVIDER_URLS = [
    'brevo'    => 'https://api.brevo.com/v3/smtp/email',
    'sendgrid' => 'https://api.sendgrid.com/v3/mail/send',
    'resend'   => 'https://api.resend.com/emails',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'save') {
        $prov = strtolower(trim((string) ($_POST['provider'] ?? 'brevo')));
        if (!isset(MAIL_PROVIDER_URLS[$prov])) $prov = 'brevo';
        setting_set('mail_api_url', MAIL_PROVIDER_URLS[$prov]);
        setting_set('mail_provider_name', $prov);

        $key = trim((string) ($_POST['api_key'] ?? ''));
        if ($key !== '') setting_set('mail_api_key', $key);   /* blank keeps the saved key */

        setting_set('mail_from', trim((string) ($_POST['mail_from'] ?? '')));
        setting_set('mail_app_url', trim((string) ($_POST['mail_app_url'] ?? '')));
        set_flash('success', 'E-mail settings saved. Press “Send test e-mail” to confirm delivery.');
    } elseif ($action === 'clear_key') {
        setting_set('mail_api_key', '');
        set_flash('success', 'Saved API key removed. E-mail is now disabled unless config.php defines one.');
    } elseif ($action === 'save_video') {
        /* live-class video server (see the jitsi_*() block in lib.php) */
        $dom = trim((string) ($_POST['jitsi_domain'] ?? ''));
        $dom = rtrim((string) preg_replace('#^https?://#', '', $dom), '/');
        setting_set('jitsi_domain', $dom);
        $mode = strtolower(trim((string) ($_POST['jitsi_embed'] ?? 'auto')));
        setting_set('jitsi_embed', in_array($mode, ['auto', 'window', 'iframe'], true) ? $mode : 'auto');
        setting_set('jitsi_jaas_app_id', trim((string) ($_POST['jitsi_jaas_app_id'] ?? '')));
        setting_set('jitsi_jaas_kid', trim((string) ($_POST['jitsi_jaas_kid'] ?? '')));
        $jkey = (string) ($_POST['jitsi_jaas_private_key'] ?? '');
        if (trim($jkey) !== '') setting_set('jitsi_jaas_private_key', jitsi_normalise_pem($jkey));   /* blank keeps the saved key */
        set_flash('success', 'Live-class video settings saved.');
    } elseif ($action === 'clear_jaas') {
        setting_set('jitsi_jaas_app_id', '');
        setting_set('jitsi_jaas_kid', '');
        setting_set('jitsi_jaas_private_key', '');
        set_flash('success', 'JaaS keys removed — live classes fall back to the Jitsi server above.');
    }
    header('Location: settings.php');
    exit;
}

$diag     = mail_diagnostics();
$savedKey = setting_get('mail_api_key', '');
$provName = setting_get('mail_provider_name', 'brevo');
$masked   = $savedKey === '' ? '' : (strlen($savedKey) > 6 ? '••••••••' . substr($savedKey, -6) : '••••••••');
$lastLog  = setting_get('mail_last', '');
/* which values are pinned by config.php (they override anything saved here)? */
$pinned = [];
if (EMAIL_API_KEY !== '') $pinned[] = 'EMAIL_API_KEY';
if (EMAIL_API_URL !== '') $pinned[] = 'EMAIL_API_URL';
if (EMAIL_FROM !== '')    $pinned[] = 'EMAIL_FROM';
if (APP_URL !== '')       $pinned[] = 'APP_URL';

/* live-class video (Jitsi): what is in effect, and which values config.php pins */
$jstat = jitsi_status();
$jitsiPinned = [];
if (JITSI_DOMAIN !== '')                $jitsiPinned[] = 'JITSI_DOMAIN';
if (JITSI_EMBED !== '')                 $jitsiPinned[] = 'JITSI_EMBED';
if (JITSI_JAAS_APP_ID !== '')           $jitsiPinned[] = 'JITSI_JAAS_APP_ID';
if (JITSI_JAAS_KID !== '')              $jitsiPinned[] = 'JITSI_JAAS_KID';
if (JITSI_JAAS_PRIVATE_KEY !== '')      $jitsiPinned[] = 'JITSI_JAAS_PRIVATE_KEY';
if (JITSI_JAAS_PRIVATE_KEY_FILE !== '') $jitsiPinned[] = 'JITSI_JAAS_PRIVATE_KEY_FILE';
$jitsiModeSaved = strtolower(trim(setting_get('jitsi_embed', 'auto')));
if (!in_array($jitsiModeSaved, ['auto', 'window', 'iframe'], true)) $jitsiModeSaved = 'auto';
$jaasApp = setting_get('jitsi_jaas_app_id', '');
$jaasKid = setting_get('jitsi_jaas_kid', '');
$jaasKey = setting_get('jitsi_jaas_private_key', '');

$page_title = 'Settings';
require __DIR__ . '/header.php';
?>

<div class="mx-auto max-w-3xl space-y-6">
  <div class="reveal">
    <h1 class="text-2xl font-bold text-slate-900">⚙️ E-mail &amp; live-class settings</h1>
    <p class="mt-1 text-sm text-slate-500">Delivery e-mail and the live-class video server for this site. Both are saved in the
      database, so they apply to the deployed site without editing (or re-uploading) any file.</p>
  </div>

  <div class="reveal lh-plain rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h2 class="text-base font-bold text-slate-900">What this server will do right now</h2>
    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Delivery method</dt>
        <dd class="font-semibold text-slate-800"><?= e((string) $diag['transport']) ?></dd></div>
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Outbound HTTP</dt>
        <dd class="font-semibold text-slate-800"><?= e((string) ($diag['http'] ?? 'unknown')) ?></dd></div>
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Sending address</dt>
        <dd class="min-w-0 truncate font-semibold text-slate-800"><?= e((string) ($diag['from'] ?? '')) ?></dd></div>
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Links use</dt>
        <dd class="min-w-0 truncate font-semibold text-slate-800"><?= e((string) ($diag['app_url'] ?? '')) ?></dd></div>
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Provider reachable</dt>
        <dd class="font-semibold <?= ($diag['reachable'] ?? null) === false ? 'text-rose-600' : 'text-emerald-700' ?>">
          <?= ($diag['reachable'] ?? null) === null ? 'not checked' : (($diag['reachable'] ?? null) ? 'yes ✅' : 'no ❌') ?></dd></div>
      <div class="flex justify-between gap-3"><dt class="text-slate-500">Sender validated</dt>
        <dd class="font-semibold <?= ($diag['sender_ok'] ?? null) === false ? 'text-rose-600' : 'text-emerald-700' ?>">
          <?= ($diag['sender_ok'] ?? null) === null ? 'n/a' : (($diag['sender_ok'] ?? null) ? 'yes ✅' : 'no ') ?></dd></div>
    </dl>
    <?php if (!empty($diag['notes'])): ?>
      <ul class="mt-4 space-y-1.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
        <?php foreach ($diag['notes'] as $note): ?><li>⚠️ <?= e((string) $note) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if ($lastLog !== ''): ?>
      <p class="mt-3 overflow-x-auto rounded-xl bg-slate-900 p-3 font-mono text-[11px] text-slate-100">last attempt: <?= e($lastLog) ?></p>
    <?php endif; ?>
    <button type="button" id="lh-settings-test"
      class="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
      <span>✉️</span><span id="lh-settings-test-label">Send test e-mail to me</span>
    </button>
    <p id="lh-settings-test-out"
      class="mt-3 hidden whitespace-pre-wrap rounded-xl border border-slate-200 bg-slate-50 p-3 font-mono text-[11px] text-slate-700"></p>
  </div>
<form method="post" class="reveal lh-plain space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <h2 class="text-base font-bold text-slate-900">Mail service</h2>
    <?php if ($pinned): ?>
      <p class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs text-sky-800">
        This server's <b>config.php</b> already defines <?= e(implode(', ', $pinned)) ?> — those values take priority
        over this page. Remove them from config.php if you want to manage e-mail here instead.
      </p>
    <?php endif; ?>

    <div>
      <label class="block text-sm font-medium text-slate-700" for="provider">Provider</label>
      <select id="provider" name="provider"
        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        <?php foreach (MAIL_PROVIDERS as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= $provName === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700" for="api_key">API key</label>
      <input id="api_key" name="api_key" type="password" autocomplete="off"
        placeholder="<?= $savedKey !== '' ? e('Saved: ' . $masked . ' — leave blank to keep it') : 'paste the provider API key' ?>"
        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
      <p class="mt-1 text-xs text-slate-500">Brevo keys start with <code>xkeysib-</code>; SendGrid with <code>SG.</code>.
        Stored in your database and never displayed again in full.</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700" for="mail_from">Sending address (From)</label>
      <input id="mail_from" name="mail_from" type="text" value="<?= e(setting_get('mail_from', '')) ?>"
        placeholder="LearnHub LMS &lt;your-verified-sender@gmail.com&gt;"
        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
      <p class="mt-1 text-xs text-slate-500">Must be an address you validated <b>at the provider</b>
        (Brevo → Senders &amp; IP → Senders); otherwise every message is refused at delivery.</p>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700" for="mail_app_url">Site URL for links inside e-mails</label>
      <input id="mail_app_url" name="mail_app_url" type="text" value="<?= e(setting_get('mail_app_url', '')) ?>"
        placeholder="<?= e(mail_app_url() !== '' ? mail_app_url() : 'https://yoursite') ?>"
        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
      <p class="mt-1 text-xs text-slate-500">Leave blank to auto-detect from the visitor's address.</p>
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-1">
      <button type="submit"
        class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
        Save settings</button>
      <?php if ($savedKey !== ''): ?>
        <button type="submit" name="action" value="clear_key"
          class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">
          Remove saved key</button>
      <?php endif; ?>
    </div>
  </form>

  <form method="post" class="reveal lh-plain rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_video">
    <h2 class="text-base font-bold text-slate-900">🎥 Live-class video (Jitsi)</h2>
    <p class="mt-1 text-sm text-slate-600">
      Live classes run on <b><?= e($jstat['domain']) ?></b><?= $jstat['jaas_ready'] ? ' through Jitsi as a Service' : '' ?>
      in <b><?= $jstat['mode'] === 'window' ? 'own-window' : 'in-page' ?></b> mode.
    </p>

    <div class="mt-3 rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs text-sky-800">
      <b>Why the video opens in a new window.</b> The free <b>meet.jit.si</b> server only allows embedded calls as a demo:
      in an iframe it warns “Embedding meet.jit.si is only meant for demo purposes, so this call will disconnect in 5 minutes”
      and then hangs up. That is an 8x8 rule for that one server and there is no switch for it — so live classes there open in
      the browser's own window, which skips that 5-minute cut but still ends a meeting <b>nobody is signed in to</b> after
      <b>60 minutes</b> (“Meeting time limit reached”); the teacher signing in once (Google / GitHub / Facebook) lifts the cap.
      To run <b>longer classes with no caps</b>, use JaaS below (free) or your own Jitsi server.
    </div>

    <?php foreach ($jstat['warnings'] as $w): ?>
      <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">⚠️ <?= e($w) ?></p>
    <?php endforeach; ?>

    <?php if ($jitsiPinned): ?>
      <p class="mt-3 rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs text-sky-800">
        This server's <b>config.php</b> already defines <?= e(implode(', ', $jitsiPinned)) ?> — those values take priority over this page.
      </p>
    <?php endif; ?>

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
      <div>
        <label class="block text-sm font-medium text-slate-700" for="jitsi_embed">Where the video runs</label>
        <select id="jitsi_embed" name="jitsi_embed"
          class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
          <option value="auto" <?= $jitsiModeSaved === 'auto' ? 'selected' : '' ?>>Automatic — recommended</option>
          <option value="window" <?= $jitsiModeSaved === 'window' ? 'selected' : '' ?>>Own browser window (no 5-minute embed cut)</option>
          <option value="iframe" <?= $jitsiModeSaved === 'iframe' ? 'selected' : '' ?>>Inside this page (embed)</option>
        </select>
        <p class="mt-1 text-xs text-slate-500">Automatic keeps <b>meet.jit.si</b> in its own window and embeds every other server.</p>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700" for="jitsi_domain">Jitsi server</label>
        <input id="jitsi_domain" name="jitsi_domain" type="text" value="<?= e(setting_get('jitsi_domain', '')) ?>"
          placeholder="meet.jit.si"
          class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        <p class="mt-1 text-xs text-slate-500">Blank = the free public server. Point this at your own Jitsi
          (e.g. <code>meet.yourschool.com</code>) for unlimited, private classes.</p>
      </div>
    </div>
    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
      <h3 class="text-sm font-bold text-slate-900">Embed for hours: free Jitsi as a Service (JaaS)</h3>
      <p class="mt-1 text-xs text-slate-600">
        JaaS is the official way to embed Jitsi, and it is <b>free for unlimited minutes on up to 25 users</b> — one class fits.
        <a class="font-semibold text-indigo-600" href="https://jaas.8x8.vc" target="_blank" rel="noopener">Sign up at jaas.8x8.vc</a>, then:
        (1) copy the <b>AppID</b> (<code>vpaas-magic-cookie-…</code>); (2) under <b>API keys</b> generate a key and copy its
        <b>key id (kid)</b>; (3) paste the matching <b>private key</b> (PEM) below. Every member then joins with a token this
        server signs: the teacher is the host, students see no sign-in and no waiting room, and the 5-minute cut is gone.
      </p>

      <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-sm font-medium text-slate-700" for="jitsi_jaas_app_id">JaaS AppID</label>
          <input id="jitsi_jaas_app_id" name="jitsi_jaas_app_id" type="text" value="<?= e($jaasApp) ?>"
            placeholder="vpaas-magic-cookie-xxxxxxxx"
            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700" for="jitsi_jaas_kid">JaaS API key id (kid)</label>
          <input id="jitsi_jaas_kid" name="jitsi_jaas_kid" type="text" value="<?= e($jaasKid) ?>"
            placeholder="vpaas-magic-cookie-xxxxxxxx/abc123"
            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none">
        </div>
      </div>

      <div class="mt-3">
        <label class="block text-sm font-medium text-slate-700" for="jitsi_jaas_private_key">JaaS private key (PEM)</label>
        <textarea id="jitsi_jaas_private_key" name="jitsi_jaas_private_key" rows="5"
          placeholder="<?= $jaasKey !== '' ? 'A key is saved — leave this blank to keep it' : '-----BEGIN PRIVATE KEY----- …' ?>"
          class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs focus:border-emerald-500 focus:outline-none"></textarea>
        <p class="mt-1 text-xs text-slate-500">
          <?= $jstat['jwt_supported'] ? '✅ This server can sign JaaS tokens.' : '⚠️ This server has no OpenSSL, so JaaS tokens cannot be signed — use own-window mode or your own server.' ?>
          <?= $jaasKey !== '' ? ($jstat['key_ok'] ? ' A key is stored and readable by OpenSSL.' : ' A key is stored but OpenSSL cannot read it — paste the complete PEM block again.') : '' ?>
        </p>
      </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
      <button type="submit"
        class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
        Save video settings</button>
      <?php if ($jaasKey !== '' || $jaasApp !== '' || $jaasKid !== ''): ?>
        <button type="submit" name="action" value="clear_jaas"
          class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">
          Remove JaaS keys</button>
      <?php endif; ?>
    </div>
  </form>
  <div class="reveal lh-plain rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
    <h2 class="text-base font-bold text-slate-900">Which e-mails are sent automatically</h2>
    <ul class="mt-2 space-y-1.5 text-sm text-slate-600">
      <li>👋 <b>Welcome e-mail</b> — every successful registration, to the new student's or teacher's own address.</li>
      <li>📚 <b>New lesson</b> / 🧪 <b>new quiz</b> — every enrolled student, when a teacher publishes them.</li>
      <li>✅ <b>Quiz result</b> — the student who took the quiz.</li>
      <li>💬 <b>New private message</b> — the recipient.</li>
      <li>🔔 <b>Daily catch-up reminder</b> — at most once a day, and only when updates, unread messages,
        quizzes still to take or lessons still to finish are waiting.</li>
    </ul>
  </div>
</div>

<script>
/* inline mail test on the settings page (same endpoint as the header button) */
(function () {
  var btn = document.getElementById('lh-settings-test');
  if (!btn) return;
  var lab = document.getElementById('lh-settings-test-label');
  var out = document.getElementById('lh-settings-test-out');
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';
  btn.addEventListener('click', function () {
    btn.disabled = true; lab.textContent = 'Sending…';
    out.classList.remove('hidden'); out.textContent = 'Contacting the mail service…';
    fetch('mailtest.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      body: 'csrf=' + encodeURIComponent(csrf)
    }).then(function (r) { return r.json(); }).then(function (d) {
      btn.disabled = false; lab.textContent = 'Send test e-mail to me';
      var msg;
      if (d.sender_ok === false) {
        msg = '\u274C Your sending address is NOT validated at the provider:\n\n' + d.from +
          '\n\nAdd and verify this exact address at the provider, or correct the From field above.';
      } else if (!d.ok) {
        msg = '\u274C The mail service refused the request.';
      } else if (d.state === 'delivered') {
        msg = '\u2705 Delivered — the provider handed it to the recipient. Check your inbox (and spam): ' + d.to;
      } else if (d.state === 'queued') {
        msg = '\u23F3 Accepted and queued — check your inbox (and spam) in a minute: ' + d.to;
      } else if (d.state === 'error') {
        msg = '\u274C Accepted, then REJECTED at delivery to ' + d.to + '.\n\nThe provider said:\n' + (d.reason || '(no reason given)');
      } else {
        msg = '\u2709\uFE0F Accepted by the provider; delivery not confirmed yet. Check your inbox (and spam): ' + d.to;
      }
      if (d.tail && d.tail.length) msg += '\n\n' + d.tail.join('\n');
      var notes = (d.diag && d.diag.notes) ? d.diag.notes : [];
      if (notes.length && (d.sender_ok === false || !d.ok || d.state === 'error' || d.state === 'unknown')) {
        msg += '\n\nServer check (' + ((d.diag && d.diag.transport) || 'unknown') + '):\n- ' + notes.join('\n- ');
      }
      out.textContent = msg;
    }).catch(function () {
      btn.disabled = false; lab.textContent = 'Send test e-mail to me';
      out.textContent = 'Could not reach the mail test endpoint.';
    });
  });
})();
</script>
<?php require __DIR__ . '/footer.php'; ?>
