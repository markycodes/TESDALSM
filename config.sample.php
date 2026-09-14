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

/* ---- Edit these four/five lines, then rename this file to config.php ---- */
define('DB_HOST', 'sqlXXX.infinityfree.com');
define('DB_PORT', '3306');
define('DB_NAME', 'epiz_XXXXXXXX_learnhub');
define('DB_USER', 'epiz_XXXXXXXX');
/* ---- E-mail delivery (greeting / new-lesson / quiz-result / messages / daily reminders) ----
 * The app sends e-mail automatically:
 *   - a welcome e-mail when a student registers with an invitation code
 *   - "new lesson" / "new quiz" to every enrolled student (upload.php, quiz_save.php)
 *   - quiz results, new private messages
 *   - a daily catch-up reminder when something unread is waiting (max 1/day)
 * It uses a Resend-style HTTP API when a key is set (free tier ≈ 100/day), and
 * falls back to PHP mail() elsewhere. InfinityFree free hosting disables PHP
 * mail(), so use a key there. Every attempt is logged to data/mail.log.
 *
 * 1. Create a free account at https://resend.com  → "API Keys" → new key.
 * 2. Without your own domain, use onboarding@resend.dev as the sender and keep
 *    EMAIL_FROM exactly "LearnHub LMS <onboarding@resend.dev>".
 * 3. Uncomment and fill in:
 */
// define('EMAIL_FROM',    'LearnHub LMS <onboarding@resend.dev>');
// define('EMAIL_API_URL', 'https://api.resend.com/emails');
// define('EMAIL_API_KEY', 're_xxxxxxxxxxxxxxxxxxxx');
// define('APP_URL',       'https://learninghublms.wuaze.com'); // used for links inside e-mails
