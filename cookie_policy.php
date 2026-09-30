<?php
/**
 * Cookie Policy — and the written answer to "do we need a cookie consent
 * banner?". The verdict is in section 1 and backed by the tables below: this
 * app sets exactly ONE cookie (the login session, strictly necessary), and the
 * only non-essential storage is what a visitor chooses to keep plus the
 * third-party players that are loaded for a lesson. Keep this page in step with
 * the code: lib.php session_set_cookie_params() for the cookie attributes,
 * assets/app.js + read.php for the browser-storage keys, and the embeds in
 * lib.php (video_embed_url, jitsi_script_url) / turnstile_field().
 *
 * A material change here must also bump LEGAL_VERSION in lib.php.
 */
require_once __DIR__ . '/lib.php';

$sections = [
    [
        'title' => 'The short answer',
        'body'  => '<p><b>We set one cookie, and it is one you asked for: the login session.</b> It carries no '
            . 'advertising, analytics or tracking of any kind, it is not shared with anyone, and it is what the law '
            . 'calls <i>strictly necessary</i> — the service cannot work without it. Because of that, <b>this site does '
            . 'not need a blocking consent banner</b> for its own cookie, and you will not find one here.</p>'
            . '<p class="mt-3">What you get instead is this page, linked from the footer of every page and from the '
            . 'bottom of every form: a plain list of everything this site stores in your browser, why, and how to get '
            . 'rid of it. Where a <i>non-essential</i> thing does touch your browser — a YouTube or Vimeo player on a '
            . 'lesson, a Jitsi room for a live class — it happens only because you opened that lesson, and that is what '
            . 'consent looks like here: an action you took, described before you take it.</p>'
            . '<p class="mt-3">If you would still like a banner, switch on analytics, or embed social media pixels, '
            . 'the answer changes: those are not strictly necessary, and they would have to be blocked until you '
            . 'agreed. Section 6 lists what would flip the answer.</p>',
    ],
    [
        'title' => 'What a cookie actually is',
        'body'  => '<p>A cookie is a small text file a site asks your browser to keep and send back with the next '
            . 'request to that site. It is how a site recognises that two page views come from the same person — and '
            . 'for a login, that is the whole trick. Cookies can only be read by the site that set them (first-party), '
            . 'or by a third party whose content you loaded and that set its own cookie from its own domain.</p>'
            . '<p class="mt-3">Modern browsers also offer <code>localStorage</code> and <code>sessionStorage</code>: '
            . 'places in your browser that hold data but are never sent automatically with a request. The law treats '
            . 'them the same as cookies when they identify you or your device, so they are listed here too — even the '
            . 'harmless ones, so that this page is complete rather than minimal.</p>',
    ],
    [
        'title' => 'The one cookie we set',
        'body'  => '<div class="overflow-x-auto"><table class="w-full text-left text-sm">'
            . '<thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">'
            . '<tr><th class="px-3 py-2 font-semibold">Name</th><th class="px-3 py-2 font-semibold">Purpose</th>'
            . '<th class="px-3 py-2 font-semibold">Type</th><th class="px-3 py-2 font-semibold">Lifetime</th></tr></thead>'
            . '<tbody class="divide-y divide-slate-100"><tr>'
            . '<td class="px-3 py-2"><code>' . e(session_name()) . '</code></td>'
            . '<td class="px-3 py-2">Keeps you signed in and remembers the page state that makes the app safe to use: '
            . 'your user id, the single-use security token on every form (otherwise a form could be submitted from '
            . 'another site), the "problem" message shown after a redirect, and the answer to the human check while a '
            . 'registration form is open. No name, e-mail address or content is stored in the cookie itself — only an '
            . 'opaque id that matches a record on our server.</td>'
            . '<td class="px-3 py-2"><b>Strictly necessary</b> — exempt from consent (ePrivacy Directive Art. 5(3) / '
            . 'PECR reg. 6; RA 10173 on consent for necessary processing). Block it and you simply cannot log in.</td>'
            . '<td class="px-3 py-2">Until you close the browser (the value on the server behind it expires earlier '
            . 'when the session times out, and logging out destroys it immediately).</td>'
            . '</tr></tbody></table></div>'
            . '<p class="mt-3">The attributes are fixed in code, so this description stays true: <code>HttpOnly</code> '
            . '(scripts cannot read it, so an injected script cannot steal your session), <code>SameSite=Lax</code> '
            . '(it is not sent on a form submitted from another website — the standard defence against cross-site '
            . 'request forgery), <code>Path=/</code> (the whole site), and <code>Secure</code>, which the server adds '
            . 'by itself whenever the site is reached over HTTPS.</p>'
            . '<p class="mt-3">We set <b>no</b> analytics cookie, <b>no</b> advertising or remarketing cookie, '
            . '<b>no</b> social-media pixel, and <b>no</b> cookie that follows you to another website.</p>',
    ],
    [
        'title' => 'What we keep in your browser storage (no cookie, still listed)',
        'body'  => '<div class="overflow-x-auto"><table class="w-full text-left text-sm">'
            . '<thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">'
            . '<tr><th class="px-3 py-2 font-semibold">Key</th><th class="px-3 py-2 font-semibold">Where</th>'
            . '<th class="px-3 py-2 font-semibold">What it is for</th><th class="px-3 py-2 font-semibold">How long</th></tr></thead>'
            . '<tbody class="divide-y divide-slate-100">'
            . '<tr><td class="px-3 py-2"><code>lh-cookie-notice</code></td><td class="px-3 py-2">localStorage</td>'
            . '<td class="px-3 py-2">Remembers that you dismissed the one-line notice about this policy, so it does not '
            . 'reappear on every page. Set only when you press "Got it".</td><td class="px-3 py-2">Until you clear your '
            . 'browser data.</td></tr>'
            . '<tr><td class="px-3 py-2"><code>lh-rail</code></td><td class="px-3 py-2">localStorage</td>'
            . '<td class="px-3 py-2">Whether you collapsed the sidebar to icons or kept the full menu — a display '
            . 'preference, written only when you click the toggle yourself.</td><td class="px-3 py-2">Until you clear '
            . 'your browser data.</td></tr>'
            . '<tr><td class="px-3 py-2"><code>lh-scheme</code></td><td class="px-3 py-2">localStorage</td>'
            . '<td class="px-3 py-2">Whether you switched the page to its dark colours or left it light, from the '
            . 'sun/moon button in the top bar. Again a display preference and nothing else — it is not tied to your '
            . 'account, is not sent to the server, and is read back by the page before it first paints so the page '
            . 'does not arrive light and change afterwards.</td><td class="px-3 py-2">Until you clear '
            . 'your browser data.</td></tr>'
            . '<tr><td class="px-3 py-2"><code>lh-lessons-tab</code></td><td class="px-3 py-2">sessionStorage</td>'
            . '<td class="px-3 py-2">Which tab of the lessons panel (video, notes, upload…) you were looking at, so a '
            . 'reload does not jump back to the first tab.</td><td class="px-3 py-2">Until the tab is closed.</td></tr>'
            . '<tr><td class="px-3 py-2"><code>lh-challenge-reload</code></td><td class="px-3 py-2">sessionStorage</td>'
            . '<td class="px-3 py-2">A timestamp that stops the app reloading itself more than once every 45 seconds '
            . 'when a shared host answers a background request with its own anti-bot page. Pure housekeeping.</td>'
            . '<td class="px-3 py-2">Until the tab is closed.</td></tr>'
            . '</tbody></table></div>'
            . '<p class="mt-3">None of these keys contains personal data, none of them leaves your browser, and none is '
            . 'read by us. We list them because a complete inventory is more useful than a clever one.</p>',
    ],
    [
        'title' => 'Cookies set by services we load',
        'body'  => '<p>These appear only in the situations named, and they come from the other company\'s domain — we '
            . 'never receive them:</p>'
            . '<div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm">'
            . '<thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">'
            . '<tr><th class="px-3 py-2 font-semibold">Service</th><th class="px-3 py-2 font-semibold">When it loads</th>'
            . '<th class="px-3 py-2 font-semibold">What it may store</th></tr></thead>'
            . '<tbody class="divide-y divide-slate-100">'
            . '<tr><td class="px-3 py-2">Cloudflare Turnstile (bot check)</td><td class="px-3 py-2">Only on the log-in, '
            . 'registration and password-reset forms, and only while the administrator has Turnstile switched on. With '
            . 'it off, those forms use the built-in arithmetic question and no third party is contacted.</td>'
            . '<td class="px-3 py-2">Cloudflare\'s own cookies such as <code>cf_clearance</code>, <code>__cf_bm</code> '
            . 'and <code>_cfuvid</code>, used to decide whether the visitor is a human being and to keep that answer '
            . 'briefly. Security, not advertising.</td></tr>'
            . '<tr><td class="px-3 py-2">YouTube (privacy-enhanced mode) / Vimeo</td><td class="px-3 py-2">Only when a '
            . 'lesson contains a video link and you open it. Lesson videos uploaded by your teacher are streamed from '
            . 'this site instead and involve nobody else.</td><td class="px-3 py-2">Whatever the player sets once you '
            . 'press play — typically playback preferences and a device identifier. Embedding them is what makes the '
            . 'video work; if you do not press play, the player stays silent.</td></tr>'
            . '<tr><td class="px-3 py-2">Jitsi / 8x8 (live class)</td><td class="px-3 py-2">Only while you are in a live '
            . 'class room on the server your school configured.</td><td class="px-3 py-2">Session and device data needed '
            . 'to hold the call open, plus your display name inside the room.</td></tr>'
            . '<tr><td class="px-3 py-2">Shared-hosting anti-bot challenge</td><td class="px-3 py-2">Only if the web '
            . 'host chosen for this installation answers a request with its own "enable JavaScript" challenge page.</td>'
            . '<td class="px-3 py-2">That host\'s clearance cookie, which lets the following requests through.</td></tr>'
            . '</tbody></table></div>'
            . '<p class="mt-3">Nothing above is loaded on the home page or on any page you did not ask for. There are no '
            . 'advertising networks, no social plug-ins, no heat-map or session-recording scripts.</p>',
    ],
    [
        'title' => 'So — do we need a cookie consent banner?',
        'body'  => '<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm leading-6 text-emerald-800">'
            . '<b>No — and here is the audit behind that answer.</b> The test is not "is there a cookie?" but "is the '
            . 'cookie needed to deliver the thing the visitor asked for?". Everything we set is.</div>'
            . '<div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm">'
            . '<thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">'
            . '<tr><th class="px-3 py-2 font-semibold">Question</th><th class="px-3 py-2 font-semibold">This site</th>'
            . '<th class="px-3 py-2 font-semibold">Consequence</th></tr></thead>'
            . '<tbody class="divide-y divide-slate-100">'
            . '<tr><td class="px-3 py-2">Does the site set any cookie of its own?</td><td class="px-3 py-2">One — the '
            . 'login session.</td><td class="px-3 py-2">Disclosure is enough; a banner is not required for it.</td></tr>'
            . '<tr><td class="px-3 py-2">Can the site work without it?</td><td class="px-3 py-2">No. Without the session '
            . 'cookie, logging in is impossible (and so is the anti-forgery token on every form).</td>'
            . '<td class="px-3 py-2">It is <i>strictly necessary</i> — the exemption in Art. 5(3) of the ePrivacy '
            . 'Directive and reg. 6 of the UK PECR.</td></tr>'
            . '<tr><td class="px-3 py-2">Does anything track you across websites, or build a profile?</td>'
            . '<td class="px-3 py-2">No. No analytics, no pixels, no advertising or social plug-ins.</td>'
            . '<td class="px-3 py-2">There is nothing to ask consent for.</td></tr>'
            . '<tr><td class="px-3 py-2">Is anything loaded from a third party without you asking?</td>'
            . '<td class="px-3 py-2">Page fonts (Google Fonts), and Cloudflare Turnstile while the human check is on. '
            . 'Both are disclosed above; neither follows you across sites.</td><td class="px-3 py-2">Disclosure and a '
            . 'lawful basis are needed (see the Privacy Policy). Under a stricter reading, self-host the fonts — the '
            . 'page then contacts nobody.</td></tr>'
            . '<tr><td class="px-3 py-2">Are the non-essential players loaded on their own?</td><td class="px-3 py-2">No. '
            . 'A YouTube/Vimeo player appears only inside a lesson that uses such a link, and Jitsi only when a live '
            . 'class is opened.</td><td class="px-3 py-2">Opening the lesson is the visitor\'s own action, and the '
            . 'Privacy Policy explains it beforehand.</td></tr>'
            . '<tr><td class="px-3 py-2">Is data sold or shared for advertising?</td><td class="px-3 py-2">Never.</td>'
            . '<td class="px-3 py-2">No "sale/share" opt-out notice is needed.</td></tr>'
            . '<tr><td class="px-3 py-2">What would change the answer?</td><td class="px-3 py-2">Adding analytics '
            . '(Google Analytics, Meta Pixel, Hotjar…), advertising, a chat widget, or any cookie that survives to '
            . 'recognise you on another site.</td><td class="px-3 py-2">Then those scripts must be blocked until the '
            . 'visitor agrees — a real banner, with an equally easy "reject".</td></tr>'
            . '</tbody></table></div>'
            . '<p class="mt-3">Until such a tool is added, the honest position is: one necessary cookie, disclosed, plus '
            . 'a one-line notice in the footer that links here. No dark patterns, no "accept-all" wall, and nothing to '
            . 'withdraw.</p>',
    ],
    [
        'title' => 'Not cookies, but still worth knowing',
        'body'  => '<ul class="list-disc space-y-2 pl-5">'
            . '<li><b>Google Fonts.</b> The typefaces are fetched from Google\'s CDN, so your browser makes a request '
            . 'to Google and your IP address is visible there. That request sets no cookie. A school that wants zero '
            . 'contact with Google can self-host the two fonts; the pages look identical.</li>'
            . '<li><b>Your e-mail address.</b> When we send you a notification, the address and the message pass '
            . 'through the e-mail service our administrator configured (Brevo, SendGrid or Resend). That is a transfer '
            . 'of personal data to a processor, described in the Privacy Policy — not a cookie.</li>'
            . '<li><b>Server logs.</b> Our own error log records what went wrong, and an attendance record keeps the '
            . 'IP address it was taken from. Neither is a cookie, and neither is used to follow you.</li></ul>',
    ],
    [
        'title' => 'How to see, block or delete what is stored',
        'body'  => '<p>Every mainstream browser lists what a site has stored and lets you delete it, or block cookies '
            . 'per site — look under <i>Settings → Privacy and security → Cookies and site data</i> (Chrome / Edge), '
            . '<i>Settings → Privacy &amp; Security</i> (Firefox), or <i>Settings → Safari → Advanced</i> (Safari). '
            . 'Private or incognito windows keep the session cookie only until the window is closed.</p>'
            . '<p class="mt-3">One practical warning: blocking the session cookie, or clearing it while you are signed '
            . 'in, <b>signs you out</b> and stops forms from submitting (the security token inside the form no longer '
            . 'matches your session). That is the cookie doing exactly the job described above.</p>'
            . '<p class="mt-3">For the third-party players in section 6, deleting the cookies they set, using your '
            . 'browser\'s tracking protection, or choosing <i>never</i> when a video platform asks about storage '
            . 'settings are all effective. You can also simply not press play — the lesson text and your teacher\'s '
            . 'uploaded files never depend on it.</p>',
    ],
    [
        'title' => 'Changes, and how to ask us something',
        'body'  => '<p>The "Last updated" date at the top of this page shows the current version, and a material change '
            . 'is announced here — with fresh consent where the law requires it.</p>'
            . '<p class="mt-3">Questions about cookies or about your data: e-mail <a class="font-semibold '
            . 'text-emerald-700 hover:underline" href="mailto:fttccatbalogan@gmail.com?subject=Cookie%20question">'
            . 'fttccatbalogan@gmail.com</a>, or ask at the school office — Felices Technological Training Center, Inc., '
            . '6th St. Brgy 12 Patag, Catbalogan City, Samar, 6700 · 0917 320 2508.</p>',
    ],
];

$page_title = 'Cookie Policy';
require __DIR__ . '/header.php';
?>
<div class="mx-auto max-w-3xl">
  <p class="lh-kicker reveal">Legal</p>
  <h1 class="reveal mt-3 text-3xl font-bold text-slate-900">Cookie Policy</h1>
  <p class="reveal mt-2 text-sm text-slate-500">
    Last updated <b><?= e(LEGAL_VERSION) ?></b> · one necessary cookie, nothing that tracks you, and the audit that
    shows why no consent banner is needed
  </p>

  <div class="reveal mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(17,33,26,.05),0_18px_40px_-28px_rgba(17,33,26,.25)]">
    <?php foreach ($sections as $i => $s): ?>
      <section class="<?= $i > 0 ? 'border-t border-slate-100 ' : '' ?>px-6 py-6">
        <h2 class="text-base font-bold text-slate-900"><?= $i + 1 ?>. <?= e((string) $s['title']) ?></h2>
        <div class="mt-3 space-y-3 text-sm leading-6 text-slate-600"><?= $s['body'] ?></div>
      </section>
    <?php endforeach; ?>
  </div>

  <p class="mt-4 text-xs leading-5 text-slate-400">
    Read next: <?= legal_links() ?>.
  </p>
</div>

<?php require __DIR__ . '/footer.php'; ?>
