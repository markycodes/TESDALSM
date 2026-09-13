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
define('DB_PASS', 'your-mysql-password');
