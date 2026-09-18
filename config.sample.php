<?php
/**
 * LearnHub LMS — per-environment database settings (SAMPLE).
 *
 * Copy this file to `config.php` in the same folder and fill in the values
 * from your hosting control panel. `config.php` is git-ignored, so your
 * credentials are never committed. XAMPP needs NO config file at all — the
 * defaults in lib.php already match it.
 *
 * ---- InfinityFree (free hosting) ----
 * 1. vPanel -> "MySQL Databases" — it lists the exact values to use:
 *      Host  : sqlXXX.infinityfree.com  (localhost / 127.0.0.1 is NOT reachable)
 *      Name  : epiz_XXXXXXXX_learnhub   (create the DB in the panel first —
 *                                        PHP cannot create databases there)
 *      User  : epiz_XXXXXXXX            (the MySQL username shown in the panel)
 *      Pass  : the MySQL password set in the panel (NOT your account password)
 * 2. Everything else is automatic: the schema and demo accounts
 *    (teacher@demo.com / student@demo.com — demo123) are created on first run.
 *
 * ---- Upload limits on InfinityFree free hosting ----
 * Uploads are capped at ~10 MB and cannot be raised, so large video files
 * won't go through. For video lessons prefer pasting a YouTube / Vimeo link
 * instead of uploading the file, or host big media elsewhere.
 */

/* Never expose this file directly over HTTP */
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Forbidden');
}

/* ---- Edit these values, then rename this file to config.php ----
 * The file may carry BOTH environments — lib.php auto-detects where it runs:
 *   • XAMPP / localhost / CLI → uses the *_LOCAL block
 *   • any real domain         → uses the *_PROD  block
 * so one file works on your PC AND on InfinityFree (no loopback errors). */
define('DB_HOST_LOCAL', '127.0.0.1');   define('DB_HOST_PROD', 'sqlXXX.infinityfree.com');
define('DB_PORT_LOCAL', '3306');        define('DB_PORT_PROD', '3306');
define('DB_NAME_LOCAL', 'learnhub');    define('DB_NAME_PROD', 'if0_XXXXXXXX_learnhub');
define('DB_USER_LOCAL', 'root');        define('DB_USER_PROD', 'if0_XXXXXXXX');
define('DB_PASS_LOCAL', '');            define('DB_PASS_PROD', 'your-mysql-password');
/* ---- E-MAIL delivery (greeting / new-lesson / quiz-result / messages / daily reminders) ----
 * The app sends e-mail automatically:
 *   - a welcome e-mail to the NEWLY REGISTERED STUDENT (every registration)
 *   - "new lesson" / "new quiz" to every enrolled student (upload.php, quiz_save.php)
 *   - quiz results, new private messages
 *   - a daily catch-up reminder when something unread is waiting (max 1/day)
 *
 * ✅ BREVO (recommended — free 300/day, NO domain needed, reaches any address):
 *   1. https://www.brevo.com → sign up → "SMTP & API" → "API Keys" → generate
 *      (key starts with xkeysib-)
 *   2. "Senders & IP" → "Senders" → add your sender e-mail (e.g. your gmail)
 *      → click the confirmation link Brevo e-mails to it (check spam)
 *   3. Uncomment the 4 BREVO lines below, paste the key, save.
 *      Brevo verifies ONE sender, then delivers to every student.
 * ⚠️ RESEND (100/day): the sandbox sender onboarding@resend.dev ONLY delivers
 *    to the e-mail address that OWNS the Resend account — until you verify a
 *    domain. Fine to test with your own address; NOT for real students.
 * ⚙️ NO FTP NEEDED: you can also configure all of this FROM THE SITE ITSELF —
 *    log in as a teacher and open  ⚙️ Settings  (settings.php). The values are
 *    then saved in the database (app_settings table), which survives uploads.
 *    That is the recommended way to fix "registered users get no e-mail" on a
 *    deployed site whose config.php has no e-mail lines.
 *    Precedence: a constant defined here always wins over a saved setting.
 *
 * Without any of the above, PHP mail() is attempted (XAMPP dev only;
 * disabled on InfinityFree). Every attempt is logged to data/mail.log.
 */
// define('EMAIL_FROM',    'LearnHub LMS <your-verified-sender@gmail.com>');
// define('EMAIL_API_URL', 'https://api.brevo.com/v3/smtp/email');
// define('EMAIL_API_KEY', 'xkeysib-xxxxx');
// define('APP_URL',       'https://lmshub.wuaze.com'); // used for links inside e-mails

/* ---- Continue with Google (optional) ---------------------------------------
 * Easiest: leave these empty and set the Client ID + Secret in the app's
 * Settings page (admin only) — no file upload needed there. Values here would
 * override the Settings page. Get them at console.cloud.google.com →
 * APIs & Services → Credentials → OAuth client ID (Web application), and add
 * https://YOUR-DOMAIN/google_login.php as an Authorized redirect URI. */
// define('GOOGLE_CLIENT_ID',     '');
// define('GOOGLE_CLIENT_SECRET', '');
