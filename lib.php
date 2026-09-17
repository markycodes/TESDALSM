<?php
/**
 * LearnHub LMS — core library.
 * PHP + MySQL (PDO). The database, schema and demo data are created automatically on first run.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('LMS_ROOT', __DIR__);
define('DATA_DIR', LMS_ROOT . '/data');        // legacy JSON storage location (auto-imported once)
define('UPLOAD_DIR', LMS_ROOT . '/uploads');
define('MAX_UPLOAD_BYTES', 512 * 1024 * 1024); // 512 MB (php.ini may cap lower)

/* ---- MySQL connection settings --------------------------------------------
 * Defaults are the XAMPP ones. For other hosts (InfinityFree, etc.) create a
 * config.php next to this file — copy config.sample.php and fill in the
 * values from your hosting control panel. config.php is git-ignored, so
 * credentials are never committed.
 * ------------------------------------------------------------------------- */
if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

/* ---- Environment-aware DB selection ----------------------------------------
 * config.php may carry BOTH a LOCAL (XAMPP) and a PROD (InfinityFree) block.
 * Decide by where the request came from: a hostname without a dot (localhost,
 * 127.0.0.1), a CLI run, or a missing host header = local machine; anything
 * else (a real domain like lmshub.wuaze.com) = production. */
if (!defined('DB_HOST')) {
    $__host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $__host = preg_replace('/:\d+$/', '', $__host);   /* strip the port: 127.0.0.1:8099 -> 127.0.0.1 */
    $__isLocal = PHP_SAPI === 'cli'
        || $__host === '' || $__host === 'localhost' || $__host === '127.0.0.1' || $__host === '::1'
        || strpos($__host, '.local') !== false && strpos($__host, '.') === strrpos($__host, '.local');
    if ($__isLocal && defined('DB_HOST_LOCAL')) {
        define('DB_HOST', DB_HOST_LOCAL); define('DB_PORT', DB_PORT_LOCAL);
        define('DB_NAME', DB_NAME_LOCAL); define('DB_USER', DB_USER_LOCAL); define('DB_PASS', DB_PASS_LOCAL);
    } elseif (defined('DB_HOST_PROD')) {
        define('DB_HOST', DB_HOST_PROD); define('DB_PORT', DB_PORT_PROD);
        define('DB_NAME', DB_NAME_PROD); define('DB_USER', DB_USER_PROD); define('DB_PASS', DB_PASS_PROD);
    }
}

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'learnhub');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

/* ---- e-mail (greeting / updates / reminders) -------------------------------
 * Set EMAIL_API_URL + EMAIL_API_KEY in config.php to deliver through a
 * provider HTTP API — e.g. Brevo (free 300/day), SendGrid or Resend.
 * Without a key, PHP mail() is used on dev machines (XAMPP). InfinityFree free
 * hosting disables PHP mail(), so an API key is required there. All attempts
 * are logged to data/mail.log. APP_URL (https://yoursite) builds clickable
 * links inside e-mails. The app never breaks when e-mail is not configured.
 * -------------------------------------------------------------------------- */
if (!defined('EMAIL_FROM'))    define('EMAIL_FROM', '');   /* empty = use the saved setting, else the built-in sender */
if (!defined('EMAIL_API_URL')) define('EMAIL_API_URL', ''); /* empty = use the saved setting / the provider implied by the key */
if (!defined('EMAIL_API_KEY')) define('EMAIL_API_KEY', ''); /* real key lives in config.php or in-app Settings (never committed) */

/* ---- saved settings store ---------------------------------------------------
 * E-mail can be configured FROM THE DEPLOYED SITE (Settings page) because
 * config.php is git-ignored and is therefore usually missing after a fresh
 * upload. Resolution order for every mail value:
 *     config.php constant  ->  saved setting  ->  built-in last resort. */
function settings_ensure(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec('CREATE TABLE IF NOT EXISTS app_settings (k VARCHAR(48) NOT NULL, v TEXT NOT NULL, PRIMARY KEY (k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } catch (Throwable $e) { /* read-only DB: constants still work */ }
}

function setting_get(string $k, string $default = ''): string
{
    settings_ensure();
    try {
        $st = db()->prepare('SELECT v FROM app_settings WHERE k = ?');
        $st->execute([$k]);
        $v = $st->fetchColumn();
        return $v === false ? $default : (string) $v;
    } catch (Throwable $e) {
        return $default;
    }
}

function setting_set(string $k, string $v): void
{
    settings_ensure();
    try {
        db()->prepare('INSERT INTO app_settings (k, v) VALUES (?,?) ON DUPLICATE KEY UPDATE v = ?')->execute([$k, $v, $v]);
    } catch (Throwable $e) { /* ignore */ }
}

/** Default sender when nothing is set: the first (admin) teacher account. Keeps
 *  the address out of the source code while still giving a fresh deploy with no
 *  config.php a sensible sender. */
function mail_default_from(): string
{
    try {
        $st = db()->query("SELECT email FROM users WHERE role = 'teacher' ORDER BY id LIMIT 1");
        $mail = (string) ($st->fetchColumn() ?: '');
        if (filter_var($mail, FILTER_VALIDATE_EMAIL)) return 'LearnHub LMS <' . $mail . '>';
    } catch (Throwable $e) { /* users table not ready yet */ }
    return '';
}

function mail_from(): string
{
    if (EMAIL_FROM !== '') return EMAIL_FROM;                  /* fixed by config.php */
    $saved = setting_get('mail_from', '');
    return $saved !== '' ? $saved : mail_default_from();
}

function mail_api_key(): string
{
    if (EMAIL_API_KEY !== '') return EMAIL_API_KEY;            /* fixed by config.php */
    return setting_get('mail_api_key', '');
}

function mail_api_url(): string
{
    if (EMAIL_API_URL !== '') return EMAIL_API_URL;            /* fixed by config.php */
    $saved = setting_get('mail_api_url', '');
    if ($saved !== '') return $saved;
    return mail_api_key() !== '' ? 'https://api.brevo.com/v3/smtp/email' : '';
}

/** This site's own base URL, derived from the current request — so links inside
 *  e-mails still work on a fresh deploy where config.php (and APP_URL) are absent. */
function request_base_url(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '' || strpos($host, '.') === false) return '';
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $dir = rtrim($dir === '/' ? '' : $dir, '/');
    return ($https ? 'https://' : 'http://') . $host . $dir;
}

function mail_app_url(): string
{
    if (APP_URL !== '') return APP_URL;
    $saved = setting_get('mail_app_url', '');
    return $saved !== '' ? $saved : request_base_url();
}

/** brevo | sendgrid | resend | php-mail | none — what this server will actually use. */
function mail_provider(): string
{
    if (mail_api_url() !== '' && mail_api_key() !== '') {
        $u = strtolower(mail_api_url());
        if (strpos($u, 'brevo') !== false) return 'brevo';
        if (strpos($u, 'sendgrid') !== false) return 'sendgrid';
        return 'resend';
    }
    return function_exists('mail') ? 'php-mail' : 'none';
}

/** Which outbound HTTP transport does this server have? cURL first — it keeps
 *  working when the host disables allow_url_fopen, which is the #1 reason mail
 *  works on a dev machine but not on the live host. */
function lh_http_transport(): string
{
    if (function_exists('curl_init')) return 'curl';
    if (ini_get('allow_url_fopen')) return 'fopen';
    return 'none';
}

/** Minimal HTTP client that works on cURL-only and fopen-only hosts.
 *  Returns ['code' => int, 'body' => string, 'err' => string]; code 0 = never reached. */
function lh_http(string $url, string $method = 'GET', array $headers = [], string $payload = '', int $timeout = 10): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(6, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
        ]);
        if ($payload !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $body = curl_exec($ch);
        $err  = (string) curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (PHP_VERSION_ID < 80500) curl_close($ch);  /* a no-op since PHP 8.0, deprecated in 8.5 */
        if ($body === false) return ['code' => 0, 'body' => '', 'err' => 'curl: ' . ($err !== '' ? $err : 'connection failed')];
        return ['code' => $code, 'body' => (string) $body, 'err' => ''];
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $payload,
            'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 2, 'timeout' => $timeout,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $h) { if (preg_match('#HTTP/\S+\s+(\d+)#', $h, $m)) $code = (int) $m[1]; }
        if ($body === false) return ['code' => 0, 'body' => '', 'err' => 'request failed (allow_url_fopen)'];
        return ['code' => $code, 'body' => (string) $body, 'err' => ''];
    }
    return ['code' => 0, 'body' => '', 'err' => 'no HTTP transport: this host has cURL disabled AND allow_url_fopen Off'];
}
if (!defined('APP_URL'))       define('APP_URL', '');       /* e.g. https://yoursite — builds links inside e-mails */

const DOC_EXTS    = ['pdf','docx','pptx','xlsx','txt','md','csv','png','jpg','jpeg','gif','webp'];
const VIDEO_EXTS  = ['mp4','webm','ogg','ogv','mov','m4v'];
const INLINE_EXTS = ['pdf','png','jpg','jpeg','gif','webp','txt','mp4','webm','ogg','ogv','mov','m4v','mp3','wav'];

/* ---------------- database connection ---------------- */

function db_error_page(string $message): void
{
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Database error · LearnHub</title></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f8fafc;font-family:Segoe UI,Arial,sans-serif">'
        . '<div style="max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;box-shadow:0 10px 30px rgba(0,0,0,.06)">'
        . '<h1 style="margin:0 0 10px;font-size:20px;color:#0f172a">🗄️ Database not reachable</h1>'
        . '<p style="margin:0 0 10px;color:#334155;font-size:14px;line-height:1.6">' . e($message) . '</p>'
        . '<p style="margin:0;color:#64748b;font-size:14px;line-height:1.6">'
        . '📍 Trying <code>' . e(DB_HOST) . '</code> — '
        . (DB_HOST === '127.0.0.1' || DB_HOST === 'localhost'
            ? 'Start <b>MySQL</b> in the XAMPP Control Panel, then reload this page. Connection settings live in <code>config.php</code> (LOCAL block) / top of <code>lib.php</code>.'
            : 'Re-upload the newest <code>config.php</code> and <code>lib.php</code> to the site, and confirm the PROD block matches your hosting control panel\'s "MySQL Databases" values.')
        . '</p>'
        . '</div></body></html>';
    exit;
}

/** Shared lazy PDO connection; creates the database, schema and demo data on first use. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (!extension_loaded('pdo_mysql')) {
        db_error_page('The PHP extension <b>pdo_mysql</b> is not enabled. Enable <code>extension=pdo_mysql</code> in php.ini and restart Apache.');
    }
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
    } catch (PDOException $e) {
        db_error_page('Could not connect to MySQL at ' . DB_HOST . ':' . DB_PORT . '. (' . $e->getMessage() . ')');
    }
    try {
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    } catch (PDOException $e) {
        /* Shared hosts (InfinityFree etc.) forbid CREATE DATABASE from PHP and
           the database must be created in their control panel first. That's
           fine — the database already exists there, so just select it below. */
    }
    try {
        $pdo->exec('USE `' . DB_NAME . '`');
    } catch (PDOException $e) {
        db_error_page('Could not open the `' . DB_NAME . '` database. (' . $e->getMessage() . ') '
            . 'On shared hosting, create the database in the control panel and set DB_NAME / DB_USER / DB_PASS / DB_HOST in config.php (see config.sample.php).');
    }
    db_ensure_schema($pdo);
    db_migrate_legacy_json($pdo);
    db_seed_if_empty($pdo);
    return $pdo;
}
function db_ensure_schema(PDO $pdo): void
{
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('teacher','student','admin') NOT NULL DEFAULT 'student',
            created_at INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS courses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'General',
            description VARCHAR(2000) NOT NULL DEFAULT '',
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_courses_teacher (teacher_id),
            CONSTRAINT fk_courses_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS materials (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            type ENUM('file','video','youtube') NOT NULL DEFAULT 'file',
            title VARCHAR(120) NOT NULL,
            description VARCHAR(200) NOT NULL DEFAULT '',
            filename VARCHAR(255) NULL,
            orig_name VARCHAR(255) NULL,
            mime VARCHAR(100) NULL,
            size INT UNSIGNED NULL,
            url VARCHAR(500) NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_materials_course (course_id),
            CONSTRAINT fk_materials_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS enrollments (
            course_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (course_id, user_id),
            CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
            CONSTRAINT fk_enrollments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS progress (
            user_id INT UNSIGNED NOT NULL,
            material_id INT UNSIGNED NOT NULL,
            completed_at INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id, material_id),
            CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_progress_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS video_progress (
            user_id INT UNSIGNED NOT NULL,
            material_id INT UNSIGNED NOT NULL,
            watched_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            position_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id, material_id),
            CONSTRAINT fk_vp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_vp_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS read_progress (
            user_id INT UNSIGNED NOT NULL,
            material_id INT UNSIGNED NOT NULL,
            depth TINYINT UNSIGNED NOT NULL DEFAULT 0,
            seconds INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id, material_id),
            CONSTRAINT fk_rp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_rp_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS presence (
            user_id INT UNSIGNED NOT NULL,
            last_seen INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (user_id),
            CONSTRAINT fk_presence_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS attendance (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            entered_at INT UNSIGNED NOT NULL DEFAULT 0,
            left_at INT UNSIGNED NULL,
            ip VARCHAR(45) NULL,
            INDEX idx_attendance_course (course_id),
            INDEX idx_attendance_user (user_id),
            CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_att_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS enroll_codes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NOT NULL,
            used_by INT UNSIGNED NULL,
            used_at INT UNSIGNED NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_enroll_codes_code (code),
            INDEX idx_enroll_codes_course (course_id),
            INDEX idx_enroll_codes_teacher (teacher_id),
            CONSTRAINT fk_ec_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
            CONSTRAINT fk_ec_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_ec_user FOREIGN KEY (used_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS teacher_codes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL,
            created_by INT UNSIGNED NOT NULL,
            used_by INT UNSIGNED NULL,
            used_at INT UNSIGNED NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_teacher_codes_code (code),
            INDEX idx_teacher_codes_by (created_by),
            CONSTRAINT fk_tcc_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_tcc_user FOREIGN KEY (used_by) REFERENCES users (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS quizzes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            material_id INT UNSIGNED NOT NULL UNIQUE,
            title VARCHAR(120) NOT NULL,
            pass_score TINYINT UNSIGNED NOT NULL DEFAULT 60,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            CONSTRAINT fk_quiz_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS quiz_questions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT UNSIGNED NOT NULL,
            prompt VARCHAR(500) NOT NULL,
            options TEXT NOT NULL,
            correct TINYINT UNSIGNED NOT NULL DEFAULT 0,
            sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_quiz_questions_quiz (quiz_id),
            CONSTRAINT fk_qq_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS quiz_results (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            lesson_id INT UNSIGNED NULL,
            quiz_title VARCHAR(120) NOT NULL DEFAULT '',
            lesson_title VARCHAR(120) NOT NULL DEFAULT '',
            correct SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            total SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
            status ENUM('PASSED','FAILED') NOT NULL DEFAULT 'FAILED',
            answers TEXT NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_quiz_result_once (quiz_id, user_id),
            INDEX idx_qr_user (user_id),
            CONSTRAINT fk_qr_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE,
            CONSTRAINT fk_qr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS quiz_progress (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            answers TEXT NOT NULL,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_quiz_progress_once (quiz_id, user_id),
            CONSTRAINT fk_qp_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE,
            CONSTRAINT fk_qp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS conversations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NOT NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_conversation_pair (student_id, teacher_id),
            INDEX idx_convo_student (student_id),
            INDEX idx_convo_teacher (teacher_id),
            CONSTRAINT fk_convo_student FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_convo_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL,
            body VARCHAR(2000) NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_msg_convo (conversation_id),
            CONSTRAINT fk_msg_convo FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
            CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'info',
            title VARCHAR(200) NOT NULL,
            body VARCHAR(500) NOT NULL DEFAULT '',
            link VARCHAR(500) NOT NULL DEFAULT '',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_notif_user (user_id, is_read),
            CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS certificates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            course_id INT UNSIGNED NOT NULL,
            code VARCHAR(24) NOT NULL,
            issued_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_cert_once (user_id, course_id),
            UNIQUE KEY uq_cert_code (code),
            INDEX idx_cert_course (course_id),
            CONSTRAINT fk_cert_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_cert_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    db_schema_post_migrate($pdo);
    db_migrate_quiz_results($pdo);
}

/** One-time-per-request post-schema work: widen the role ENUM on old databases, then guarantee a main admin exists. */
function db_schema_post_migrate(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    /* older installs were created with role ENUM('teacher','student') — add 'admin' */
    try {
        $colType = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'")
            ->fetchColumn();
        if ($colType && stripos((string) $colType, 'admin') === false) {
            $pdo->exec("ALTER TABLE users MODIFY role ENUM('teacher','student','admin') NOT NULL DEFAULT 'student'");
        }
    } catch (Throwable $e) { /* best-effort; fresh installs already have the new ENUM */ }
    admin_ensure($pdo);
}

/** Guarantee a main-admin account exists. Credentials are written to data/admin-credentials.txt (web-blocked). */
function admin_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        if ((int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0) return;
        $pass = 'lh-' . bin2hex(random_bytes(5));
        $pdo->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?,?,?,?,?)')
            ->execute(['Main Admin', 'admin@learnhub.local', password_hash($pass, PASSWORD_DEFAULT), 'admin', time()]);
        @file_put_contents(DATA_DIR . '/admin-credentials.txt',
            "LearnHub main administrator (auto-created " . date('Y-m-d H:i:s') . ")\r\n" .
            "E-mail:   admin@learnhub.local\r\n" .
            "Password: {$pass}\r\n" .
            "Log in and change it on the Admin page. This folder is blocked from the web and git.\r\n");
    } catch (Throwable $e) { /* never block the site over this */ }
}

/** One-time migration: legacy multi-attempt rows -> one QuizResult per student (best attempt), then drop the old table. */
function db_migrate_quiz_results(PDO $pdo): void
{
    try {
        if (!$pdo->query("SHOW TABLES LIKE 'quiz_attempts'")->fetchColumn()) return;
        if ((int) $pdo->query('SELECT COUNT(*) FROM quiz_results')->fetchColumn() > 0) return;
        $rows = $pdo->query('SELECT qa.*, q.material_id, q.title AS qtitle, m.title AS mtitle
                             FROM quiz_attempts qa
                             JOIN quizzes q ON q.id = qa.quiz_id
                             JOIN materials m ON m.id = q.material_id')->fetchAll();
        $best = [];
        foreach ($rows as $r) {
            $key = (int) $r['quiz_id'] . ':' . (int) $r['user_id'];
            $cur = $best[$key] ?? null;
            if ($cur === null || (int) $r['score'] > (int) $cur['score'] || ((int) $r['score'] === (int) $cur['score'] && (int) $r['id'] > (int) $cur['id'])) {
                $best[$key] = $r;
            }
        }
        $ins = $pdo->prepare('INSERT INTO quiz_results (quiz_id, user_id, lesson_id, quiz_title, lesson_title, correct, total, percentage, status, answers, created_at)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($best as $r) {
            $ins->execute([
                (int) $r['quiz_id'], (int) $r['user_id'], (int) $r['material_id'],
                (string) $r['qtitle'], (string) $r['mtitle'],
                (int) $r['correct_count'], (int) $r['total'], (float) $r['score'],
                ((int) $r['passed'] === 1 ? 'PASSED' : 'FAILED'), '{}', (int) $r['created_at'],
            ]);
        }
        $pdo->exec('DROP TABLE quiz_attempts');
    } catch (Throwable $e) {
        // migration is best-effort; never block page loads
    }
}
/** Import the old JSON storage into MySQL (runs once, only when the DB is empty). */
function db_migrate_legacy_json(PDO $pdo): void
{
    $usersFile = DATA_DIR . '/users.json';
    $coursesFile = DATA_DIR . '/courses.json';
    if (!is_file($usersFile) || !is_file($coursesFile)) return;
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) return;

    $users = json_decode((string) file_get_contents($usersFile), true) ?: [];
    $courses = json_decode((string) file_get_contents($coursesFile), true) ?: [];
    if (!$users) return;

    $userMap = []; $courseMap = []; $materialMap = [];
    $insUser = $pdo->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?,?,?,?,?)');
    foreach ($users as $u) {
        $insUser->execute([
            (string) ($u['name'] ?? 'User'), (string) ($u['email'] ?? ''), (string) ($u['password'] ?? ''),
            ($u['role'] ?? '') === 'teacher' ? 'teacher' : 'student', (int) ($u['created_at'] ?? time()),
        ]);
        $userMap[(string) ($u['id'] ?? '')] = (int) $pdo->lastInsertId();
    }
    $insCourse = $pdo->prepare('INSERT INTO courses (teacher_id, title, category, description, created_at) VALUES (?,?,?,?,?)');
    foreach ($courses as $c) {
        $insCourse->execute([
            $userMap[(string) ($c['teacher_id'] ?? '')] ?? 0, (string) ($c['title'] ?? 'Untitled'),
            (string) ($c['category'] ?? 'General'), (string) ($c['description'] ?? ''), (int) ($c['created_at'] ?? time()),
        ]);
        $courseMap[(string) ($c['id'] ?? '')] = (int) $pdo->lastInsertId();
    }
    $insMaterial = $pdo->prepare('INSERT INTO materials (course_id, type, title, description, filename, orig_name, mime, size, url, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ($courses as $c) {
        $newCourseId = $courseMap[(string) ($c['id'] ?? '')] ?? 0;
        foreach (($c['materials'] ?? []) as $m) {
            $type = in_array($m['type'] ?? '', ['file', 'video', 'youtube'], true) ? $m['type'] : 'file';
            $insMaterial->execute([
                $newCourseId, $type, (string) ($m['title'] ?? 'Untitled'), (string) ($m['description'] ?? ''),
                $m['filename'] ?? null, $m['orig_name'] ?? null, $m['mime'] ?? null,
                isset($m['size']) ? (int) $m['size'] : null, $m['url'] ?? null, (int) ($m['created_at'] ?? time()),
            ]);
            if (!empty($m['id'])) $materialMap[(string) $m['id']] = (int) $pdo->lastInsertId();
        }
        foreach (($c['enrolled'] ?? []) as $oldUserId) {
            if (isset($userMap[(string) $oldUserId])) {
                $pdo->prepare('INSERT IGNORE INTO enrollments (course_id, user_id, created_at) VALUES (?,?,?)')
                    ->execute([$newCourseId, $userMap[(string) $oldUserId], time()]);
            }
        }
        foreach (($c['progress'] ?? []) as $oldUserId => $oldMaterialIds) {
            if (!isset($userMap[(string) $oldUserId])) continue;
            foreach ((array) $oldMaterialIds as $oldMaterialId) {
                if (isset($materialMap[(string) $oldMaterialId])) {
                    $pdo->prepare('INSERT IGNORE INTO progress (user_id, material_id, completed_at) VALUES (?,?,?)')
                        ->execute([$userMap[(string) $oldUserId], $materialMap[(string) $oldMaterialId], time()]);
                }
            }
        }
    }
}
function db_seed_if_empty(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) return;

    $now = time();
    $addUser = $pdo->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?,?,?,?,?)');
    $addUser->execute(['Sara Ahmed', 'teacher@demo.com', password_hash('demo123', PASSWORD_DEFAULT), 'teacher', $now]);
    $teacherId = (int) $pdo->lastInsertId();
    $addUser->execute(['Ali Khan', 'student@demo.com', password_hash('demo123', PASSWORD_DEFAULT), 'student', $now]);
    $studentId = (int) $pdo->lastInsertId();

    $notes = 'sample_notes_' . bin2hex(random_bytes(4)) . '.txt';
    @file_put_contents(UPLOAD_DIR . '/' . $notes,
        "WEB DEVELOPMENT BOOTCAMP - LESSON 1 NOTES\n\n" .
        "1. HTML gives a page its structure (headings, paragraphs, links).\n" .
        "2. CSS controls how it looks (colors, spacing, layout).\n" .
        "3. JavaScript makes it interactive (buttons, forms, animation).\n\n" .
        "Watch the video lesson, then try building a small page yourself!");

    $addCourse = $pdo->prepare('INSERT INTO courses (teacher_id, title, category, description, created_at) VALUES (?,?,?,?,?)');
    $addCourse->execute([$teacherId, 'Web Development Bootcamp', 'Programming',
        'Learn HTML, CSS and JavaScript step by step. Watch the video lessons, download the class notes, and track your progress as you go.', $now]);
    $course1 = (int) $pdo->lastInsertId();
    $addCourse->execute([$teacherId, 'Intro to Graphic Design', 'Design',
        'Colors, typography and layout fundamentals for absolute beginners.', $now]);
    $course2 = (int) $pdo->lastInsertId();

    $addMaterial = $pdo->prepare('INSERT INTO materials (course_id, type, title, description, filename, orig_name, mime, size, url, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $addMaterial->execute([$course1, 'youtube', 'Lesson 1 - JavaScript Full Course',
        'A complete beginner-friendly JavaScript tutorial to get you started.', null, null, null, null,
        'https://www.youtube.com/watch?v=PkZNo7MFNFg', $now]);
    $lesson1 = (int) $pdo->lastInsertId();
    $addMaterial->execute([$course1, 'file', 'Lesson 1 - Class notes', 'Read along while watching the video.',
        $notes, 'lesson-1-notes.txt', 'text/plain', (int) (@filesize(UPLOAD_DIR . '/' . $notes) ?: 0), null, $now]);
    $addMaterial->execute([$course1, 'youtube', 'Lesson 2 - Sample embedded video',
        'Demo of an embedded video lesson (Big Buck Bunny - open movie).', null, null, null, null,
        'https://www.youtube.com/watch?v=YE7VzlLtp-4', $now]);

    $pdo->prepare('INSERT INTO enrollments (course_id, user_id, created_at) VALUES (?,?,?)')
        ->execute([$course1, $studentId, $now]);
    $pdo->prepare('INSERT INTO progress (user_id, material_id, completed_at) VALUES (?,?,?)')
        ->execute([$studentId, $lesson1, $now]);

    // Demo quiz on Lesson 1 (already completed by the demo student, so it is unlocked out of the box).
    $pdo->prepare('INSERT INTO quizzes (material_id, title, pass_score, created_at) VALUES (?,?,?,?)')
        ->execute([$lesson1, 'Lesson 1 check — HTML, CSS & JS basics', 60, $now]);
    $demoQuizId = (int) $pdo->lastInsertId();
    $addQuestion = $pdo->prepare('INSERT INTO quiz_questions (quiz_id, prompt, options, correct, sort_order) VALUES (?,?,?,?,?)');
    $addQuestion->execute([$demoQuizId, 'What does CSS control in a web page?',
        json_encode(['How it looks — colors, spacing, layout', 'The page structure and headings', 'Interactivity like buttons and forms', 'The database on the server'], JSON_UNESCAPED_UNICODE), 0, 0]);
    $addQuestion->execute([$demoQuizId, 'Which technology makes a web page interactive?',
        json_encode(['HTML', 'CSS', 'JavaScript', 'SQL'], JSON_UNESCAPED_UNICODE), 2, 1]);

    // Demo private message + notification so Messaging / Notifications have content out of the box.
    $pdo->prepare('INSERT INTO conversations (student_id, teacher_id, created_at) VALUES (?,?,?)')
        ->execute([$studentId, $teacherId, $now]);
    $demoConvo = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, body, is_read, created_at) VALUES (?,?,?,0,?)')
        ->execute([$demoConvo, $teacherId, 'Welcome to the Web Development Bootcamp! Reply any time — ask me anything about the lessons. 🙌', $now]);
    $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link, is_read, created_at) VALUES (?,?,?,?,?,0,?)')
        ->execute([$studentId, 'message', '💬 New message from Sara Ahmed', 'Welcome to the Web Development Bootcamp! Reply any time.', 'messages.php?with=' . $teacherId, $now]);

    // Demo invitation codes so students can register into a course out of the box.
    $addCode = $pdo->prepare('INSERT INTO enroll_codes (code, course_id, teacher_id, created_at) VALUES (?,?,?,?)');
    $addCode->execute(['DEMO-7K3P', $course1, $teacherId, $now]);
    $addCode->execute(['DEMO-9X4Q', $course2, $teacherId, $now]);
}
/* ---------------- users ---------------- */

function load_users(): array
{
    return db()->query('SELECT * FROM users ORDER BY id')->fetchAll();
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([trim($email)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function email_exists(string $email): bool
{
    return find_user_by_email($email) !== null;
}

function find_user_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_user(string $name, string $email, string $passwordHash, string $role): int
{
    db()->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?,?,?,?,?)')
        ->execute([$name, strtolower($email), $passwordHash, $role === 'teacher' ? 'teacher' : 'student', time()]);
    return (int) db()->lastInsertId();
}

/* ---------------- courses (read) ---------------- */

/** All courses with nested materials / enrolled ids / progress map (same shape the pages already use). */
function load_courses(): array
{
    $courses = db()->query('SELECT c.*, u.name AS teacher_name FROM courses c JOIN users u ON u.id = c.teacher_id ORDER BY c.id')->fetchAll();
    if (!$courses) return [];

    $byId = [];
    foreach ($courses as &$c) {
        $c['materials'] = [];
        $c['enrolled'] = [];
        $c['progress'] = [];
        $byId[(int) $c['id']] = &$c;
    }
    unset($c);

    foreach (db()->query('SELECT * FROM materials ORDER BY id')->fetchAll() as $m) {
        if (isset($byId[(int) $m['course_id']])) $byId[(int) $m['course_id']]['materials'][] = $m;
    }
    foreach (db()->query('SELECT course_id, user_id FROM enrollments')->fetchAll() as $e) {
        if (isset($byId[(int) $e['course_id']])) $byId[(int) $e['course_id']]['enrolled'][] = (int) $e['user_id'];
    }
    $progress = db()->query('SELECT mt.course_id AS course_id, p.user_id AS user_id, p.material_id AS material_id FROM progress p JOIN materials mt ON mt.id = p.material_id')->fetchAll();
    foreach ($progress as $p) {
        $cid = (int) $p['course_id'];
        if (isset($byId[$cid])) $byId[$cid]['progress'][(int) $p['user_id']][] = (int) $p['material_id'];
    }
    return $courses;
}

function find_course(array $courses, string|int $id): ?int
{
    foreach ($courses as $i => $c) {
        if ((string) ($c['id'] ?? '') === (string) $id) return (int) $i;
    }
    return null;
}

function course_row(int $courseId): ?array
{
    $stmt = db()->prepare('SELECT c.*, u.name AS teacher_name FROM courses c JOIN users u ON u.id = c.teacher_id WHERE c.id = ? LIMIT 1');
    $stmt->execute([$courseId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function course_owner_id(int $courseId): ?int
{
    $c = course_row($courseId);
    return $c ? (int) $c['teacher_id'] : null;
}

/* ---------------- auth & session ---------------- */

function current_user(): ?array
{
    static $cache = null, $loaded = false;
    if ($loaded) return $cache;
    $loaded = true;
    if (!empty($_SESSION['user_id'])) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $row = $stmt->fetch();
        $cache = $row ?: null;
    }
    return $cache;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) { header('Location: login.php'); exit; }
    return $u;
}

function require_teacher(): array
{
    $u = require_login();
    if (!in_array(($u['role'] ?? ''), ['teacher', 'admin'], true)) { header('Location: dashboard.php'); exit; }
    return $u;
}

/** Main-admin-only pages (Admin control panel, site settings). */
function require_admin(): array
{
    $u = require_login();
    if (($u['role'] ?? '') !== 'admin') { header('Location: dashboard.php'); exit; }
    return $u;
}
/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || $t === '' || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Invalid form token — please go back and try again.');
    }
}

/* ---------------- flash messages ---------------- */

function set_flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------------- small helpers ---------------- */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function cut(string $s, int $n): string
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
}

function format_size(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function ext_of(string $name): string
{
    return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

/** Convert a YouTube/Vimeo URL to an embeddable player URL (null if unsupported). */
function video_embed_url(string $url): ?string
{
    if (preg_match('~(?:youtube\.com/(?:watch\?[^#\s]*v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~i', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d{6,12})~i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return null;
}

/** Extract the YouTube video id from a URL (null when the link is Vimeo or invalid). */
function youtube_id(string $url): ?string
{
    if (preg_match('~(?:youtube\.com/(?:watch\?[^#\s]*v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~i', $url, $m)) {
        return $m[1];
    }
    return null;
}

function is_enrolled(array $course, int|string $userId): bool
{
    return in_array((int) $userId, $course['enrolled'] ?? [], true);
}

function course_progress(array $course, int|string $userId): array
{
    $total = count($course['materials'] ?? []);
    $done  = count($course['progress'][(int) $userId] ?? []);
    return ['total' => $total, 'done' => $done, 'pct' => $total > 0 ? (int) round($done * 100 / $total) : 0];
}

/* ---------------- e-certificates ---------------- */

/** Percent (0-100) of a course's lessons the user completed; -1 when the course has no lessons. */
function course_progress_pct(int $userId, int $courseId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM materials WHERE course_id = ?');
    $st->execute([$courseId]);
    $total = (int) $st->fetchColumn();
    if ($total === 0) return -1;
    $st = db()->prepare('SELECT COUNT(*) FROM progress p JOIN materials m ON m.id = p.material_id
                         WHERE m.course_id = ? AND p.user_id = ?');
    $st->execute([$courseId, $userId]);
    return (int) round(100 * (int) $st->fetchColumn() / $total);
}

/** The user's certificate row for a course — issued automatically on the first
 *  visit after every lesson is completed. Returns null while not yet earned. */
function certificate_ensure(int $userId, int $courseId): ?array
{
    $st = db()->prepare('SELECT * FROM certificates WHERE user_id = ? AND course_id = ? LIMIT 1');
    $st->execute([$userId, $courseId]);
    $row = $st->fetch();
    if ($row) return $row;
    if (course_progress_pct($userId, $courseId) < 100) return null;
    for ($try = 0; $try < 5; $try++) {
        $code = 'LH-' . strtoupper(bin2hex(random_bytes(3))) . '-' . strtoupper(bin2hex(random_bytes(3)));
        try {
            db()->prepare('INSERT INTO certificates (user_id, course_id, code, issued_at) VALUES (?,?,?,?)')
                ->execute([$userId, $courseId, $code, time()]);
            $st = db()->prepare('SELECT * FROM certificates WHERE user_id = ? AND course_id = ? LIMIT 1');
            $st->execute([$userId, $courseId]);
            return $st->fetch() ?: null;
        } catch (Throwable $e) { /* rare code collision / race — retry with a fresh code */ }
    }
    return null;
}

/** Public verification: resolve a certificate code to holder + course details. */
function certificate_by_code(string $code): ?array
{
    $st = db()->prepare('SELECT ct.code, ct.issued_at, u.name AS student_name,
                                c.title AS course_title, c.category AS course_category, tu.name AS teacher_name
                         FROM certificates ct
                         JOIN users u   ON u.id  = ct.user_id
                         JOIN courses c ON c.id = ct.course_id
                         JOIN users tu  ON tu.id = c.teacher_id
                         WHERE ct.code = ? LIMIT 1');
    $st->execute([strtoupper(trim($code))]);
    $row = $st->fetch();
    return $row ?: null;
}

/** All certificates earned by a user, newest first. */
function certificates_for_user(int $userId): array
{
    $st = db()->prepare('SELECT ct.code, ct.issued_at, c.id AS course_id, c.title AS course_title, tu.name AS teacher_name
                         FROM certificates ct
                         JOIN courses c ON c.id = ct.course_id
                         JOIN users tu  ON tu.id = c.teacher_id
                         WHERE ct.user_id = ? ORDER BY ct.issued_at DESC');
    $st->execute([$userId]);
    return $st->fetchAll();
}

function ext_color(string $ext): string
{
    return match (true) {
        in_array($ext, ['pdf'], true)                               => 'bg-rose-100 text-rose-700',
        in_array($ext, ['doc', 'docx', 'md', 'txt'], true)          => 'bg-sky-100 text-sky-700',
        in_array($ext, ['ppt', 'pptx'], true)                       => 'bg-orange-100 text-orange-700',
        in_array($ext, ['xls', 'xlsx', 'csv'], true)                => 'bg-emerald-100 text-emerald-700',
        in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) => 'bg-violet-100 text-violet-700',
        in_array($ext, ['zip', 'rar', '7z'], true)                  => 'bg-amber-100 text-amber-700',
        in_array($ext, ['mp3', 'wav'], true)                        => 'bg-cyan-100 text-cyan-700',
        default                                                     => 'bg-slate-100 text-slate-700',
    };
}
/* ---------------- uploads ---------------- */

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE  => 'File exceeds the server upload limit (upload_max_filesize). Raise it in php.ini — see README.md.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds the form size limit.',
        UPLOAD_ERR_PARTIAL   => 'The upload was interrupted — please try again.',
        UPLOAD_ERR_NO_FILE   => 'No file was selected.',
        default              => 'The server could not store that file.',
    };
}

/**
 * Move an uploaded file into uploads/ under a safe random name.
 * @return array{orig:string, stored:string, size:int, ext:string}
 */
function handle_upload(string $field, array $allowedExts): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please choose a file to upload.');
    }
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException(upload_error_message($err));
    if ((int) $f['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('File is larger than 512 MB.');
    $ext = ext_of((string) $f['name']);
    if ($ext === '' || !in_array($ext, $allowedExts, true)) {
        throw new RuntimeException('File type ".' . $ext . '" is not allowed. Allowed: ' . implode(', ', $allowedExts));
    }
    $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file((string) $f['tmp_name'], UPLOAD_DIR . '/' . $stored)) {
        throw new RuntimeException('Could not save the uploaded file (check folder permissions).');
    }
    return ['orig' => (string) $f['name'], 'stored' => $stored, 'size' => (int) $f['size'], 'ext' => $ext];
}

/**
 * Handle one or several files from a `multiple` file input.
 * Each selected file becomes one entry (same shape as handle_upload()).
 */
function handle_uploads(string $field, array $allowedExts): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f)) throw new RuntimeException('Please choose at least one file to upload.');
    $names = (array) ($f['name'] ?? []);
    $rows = [];
    foreach ($names as $i => $nm) {
        $orig = trim((string) $nm);
        if ($orig === '') continue;
        $err = is_array($f['error'] ?? null) ? (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) : (int) ($f['error'] ?? UPLOAD_ERR_OK);
        if ($err !== UPLOAD_ERR_OK) throw new RuntimeException(upload_error_message($err));
        $size = is_array($f['size'] ?? null) ? (int) ($f['size'][$i] ?? 0) : (int) ($f['size'] ?? 0);
        if ($size > MAX_UPLOAD_BYTES) throw new RuntimeException('A file is larger than 512 MB.');
        $ext = ext_of($orig);
        if ($ext === '' || !in_array($ext, $allowedExts, true)) {
            throw new RuntimeException('File type ".' . $ext . '" is not allowed. Allowed: ' . implode(', ', $allowedExts));
        }
        $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $tmp = is_array($f['tmp_name'] ?? null) ? (string) ($f['tmp_name'][$i] ?? '') : (string) ($f['tmp_name'] ?? '');
        if ($tmp === '' || !move_uploaded_file($tmp, UPLOAD_DIR . '/' . $stored)) {
            foreach ($rows as $r) @unlink(UPLOAD_DIR . '/' . $r['stored']); // roll back already-saved files
            throw new RuntimeException('Could not save an uploaded file (check folder permissions).');
        }
        $rows[] = ['orig' => $orig, 'stored' => $stored, 'size' => $size, 'ext' => $ext];
    }
    if (!$rows) throw new RuntimeException('Please choose at least one file to upload.');
    return $rows;
}

function mime_for_ext(string $ext): string
{
    /* authoritative type by the file's real extension — mime_content_type() mislabels
       zip-based Office files (docx/xlsx/pptx) and several others on Windows/XAMPP,
       which would break inline viewing with X-Content-Type-Options: nosniff */
    return match (strtolower($ext)) {
        'pdf'                  => 'application/pdf',
        'doc'                  => 'application/msword',
        'docx'                 => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt'                  => 'application/vnd.ms-powerpoint',
        'pptx'                 => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls'                  => 'application/vnd.ms-excel',
        'xlsx'                 => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv'                  => 'text/csv',
        'txt'                  => 'text/plain',
        'md'                   => 'text/markdown',
        'png'                  => 'image/png',
        'jpg', 'jpeg'          => 'image/jpeg',
        'gif'                  => 'image/gif',
        'webp'                 => 'image/webp',
        'mp4', 'm4v'           => 'video/mp4',
        'webm'                 => 'video/webm',
        'ogg'                  => 'audio/ogg',
        'ogv'                  => 'video/ogg',
        'mov'                  => 'video/quicktime',
        'mp3'                  => 'audio/mpeg',
        'wav'                  => 'audio/wav',
        default                => '',
    };
}

function guess_mime(string $stored): string
{
    /* the stored file keeps its original extension, so the extension map is the
       truth — magic sniffing is only a fallback for unknown types */
    $m = mime_for_ext(ext_of($stored));
    if ($m !== '') return $m;
    $path = UPLOAD_DIR . '/' . basename($stored);
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if (is_string($m) && $m !== '') return $m;
    }
    return 'application/octet-stream';
}

/** Stream a file with HTTP Range support so uploaded videos can be seeked. */
function serve_file_with_range(string $path, string $mime, string $name, string $disposition): void
{
    $size  = (int) filesize($path);
    $start = 0;
    $end   = $size - 1;
    $range = $_SERVER['HTTP_RANGE'] ?? '';
    $partial = false;
    if ($range !== '' && preg_match('~bytes=(\d*)-(\d*)~', $range, $m)) {
        if ($m[1] !== '' && $m[2] !== '') { $start = (int) $m[1]; $end = min((int) $m[2], $size - 1); }
        elseif ($m[1] !== '')             { $start = (int) $m[1]; }
        elseif ($m[2] !== '')             { $start = max(0, $size - (int) $m[2]); }
        if ($start > $end || $start >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $partial = true;
        header('HTTP/1.1 206 Partial Content');
    } else {
        header('HTTP/1.1 200 OK');
    }
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . ($end - $start + 1));
    if ($partial) header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    /* keep the file exactly as uploaded: original name (RFC 5987 for
       non-ASCII names + a printable fallback) so saving keeps the true
       filename and extension instead of a sanitized or mangled one */
    $fallback = preg_replace('~[^\x20-\x7E]~', '_', str_replace(['"', "\\", "\r", "\n"], ['\'', '_', '', ''], $name));
    header('Content-Disposition: ' . $disposition . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0');
    while (ob_get_level() > 0) { ob_end_flush(); }
    $fp = fopen($path, 'rb');
    fseek($fp, $start);
    $remaining = $end - $start + 1;
    while (!feof($fp) && $remaining > 0) {
        $buffer = fread($fp, (int) min(8192, $remaining));
        if ($buffer === false) break;
        print $buffer;
        $remaining -= strlen($buffer);
        flush();
    }
    fclose($fp);
    exit;
}

function ensure_storage(): void
{
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);
    if (!is_file(UPLOAD_DIR . '/.htaccess'))  @file_put_contents(UPLOAD_DIR . '/.htaccess', "Require all denied\n");
    if (!is_file(UPLOAD_DIR . '/index.html')) @file_put_contents(UPLOAD_DIR . '/index.html', '');
}
/* ---------------- courses & lessons (write) ---------------- */

function create_course(int $teacherId, string $title, string $category, string $description): int
{
    db()->prepare('INSERT INTO courses (teacher_id, title, category, description, created_at) VALUES (?,?,?,?,?)')
        ->execute([$teacherId, $title, $category !== '' ? $category : 'General', $description, time()]);
    return (int) db()->lastInsertId();
}

/** Delete a course (cascades to materials, enrollments, progress) and return its stored file names for cleanup. */
function delete_course_row(int $courseId): array
{
    $stmt = db()->prepare("SELECT filename FROM materials WHERE course_id = ? AND type IN ('file','video') AND filename IS NOT NULL");
    $stmt->execute([$courseId]);
    $files = $stmt->fetchAll(PDO::FETCH_COLUMN);
    db()->prepare('DELETE FROM courses WHERE id = ?')->execute([$courseId]);
    return $files;
}

/**
 * Remove an uploaded file, retrying a few times.
 * On Windows a freshly-written file may be briefly locked (antivirus/Defender),
 * which would make a single silent unlink fail.
 */
function delete_uploaded_file(string $filename): bool
{
    $path = UPLOAD_DIR . '/' . basename($filename);
    for ($i = 0; $i < 5; $i++) {
        if (!is_file($path)) return true;
        if (unlink($path)) return true;
        usleep(300000); // 300 ms
    }
    return !is_file($path);
}

function add_material(int $courseId, string $type, string $title, string $description, ?array $file = null, ?string $url = null): int
{
    db()->prepare('INSERT INTO materials (course_id, type, title, description, filename, orig_name, mime, size, url, created_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $courseId, $type, $title, $description,
            $file['stored'] ?? null, $file['orig'] ?? null, $file['mime'] ?? null, $file['size'] ?? null,
            $url, time(),
        ]);
    return (int) db()->lastInsertId();
}

function get_material(int $courseId, int $materialId): ?array
{
    $stmt = db()->prepare('SELECT * FROM materials WHERE id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$materialId, $courseId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Delete a material row (cascades its progress rows) and return it so the caller can unlink the file. */
function delete_material_row(int $courseId, int $materialId): ?array
{
    $material = get_material($courseId, $materialId);
    if (!$material) return null;
    db()->prepare('DELETE FROM materials WHERE id = ? AND course_id = ?')->execute([$materialId, $courseId]);
    return $material;
}

function is_enrolled_id(int $courseId, int $userId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND user_id = ?');
    $stmt->execute([$courseId, $userId]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Toggle enrollment; returns true when the user is enrolled afterwards. */
function toggle_enroll(int $courseId, int $userId): bool
{
    if (is_enrolled_id($courseId, $userId)) {
        db()->prepare('DELETE FROM enrollments WHERE course_id = ? AND user_id = ?')->execute([$courseId, $userId]);
        return false;
    }
    db()->prepare('INSERT INTO enrollments (course_id, user_id, created_at) VALUES (?,?,?)')->execute([$courseId, $userId, time()]);
    return true;
}

/* ---------------- enrollment codes (invite-only) ---------------- */

/** Look up an invitation code (joined with course + teacher names). */
function enroll_code_lookup(string $code): ?array
{
    $st = db()->prepare('SELECT ec.*, c.title AS course_title, u.name AS teacher_name
                         FROM enroll_codes ec
                         JOIN courses c ON c.id = ec.course_id
                         JOIN users u ON u.id = ec.teacher_id
                         WHERE ec.code = ? LIMIT 1');
    $st->execute([strtoupper(trim($code))]);
    return $st->fetch() ?: null;
}

/** Generate a fresh, unused invitation code for one of the teacher's own courses. */
function generate_enroll_code(int $teacherId, int $courseId): ?string
{
    $c = course_row($courseId);
    if (!$c || (int) $c['teacher_id'] !== $teacherId) return null;

    $st = db()->prepare('SELECT COUNT(*) FROM enroll_codes WHERE course_id = ? AND teacher_id = ? AND used_by IS NULL');
    $st->execute([$courseId, $teacherId]);
    if ((int) $st->fetchColumn() >= 25) return null; // cap outstanding codes per course

    for ($i = 0; $i < 8; $i++) {
        $code = strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        $st = db()->prepare('SELECT COUNT(*) FROM enroll_codes WHERE code = ?');
        $st->execute([$code]);
        if ((int) $st->fetchColumn() === 0) {
            db()->prepare('INSERT INTO enroll_codes (code, course_id, teacher_id, created_at) VALUES (?,?,?,?)')
                ->execute([$code, $courseId, $teacherId, time()]);
            return $code;
        }
    }
    return null;
}

/** Atomically redeem a code: marks it used and enrolls the student. Returns the course id or null. */
function redeem_enroll_code(string $code, int $userId): ?int
{
    $code = strtoupper(trim($code));
    $db = db();
    $st = $db->prepare('SELECT id, course_id FROM enroll_codes WHERE code = ? AND used_by IS NULL LIMIT 1');
    $st->execute([$code]);
    $row = $st->fetch();
    if (!$row) return null;

    // Guarded UPDATE: only one concurrent redemption can win (rowCount 1); losers get 0 and return null.
    $st = $db->prepare('UPDATE enroll_codes SET used_by = ?, used_at = ? WHERE id = ? AND used_by IS NULL');
    $st->execute([$userId, time(), (int) $row['id']]);
    if ($st->rowCount() === 0) return null;

    $db->prepare('INSERT IGNORE INTO enrollments (course_id, user_id, created_at) VALUES (?,?,?)')
        ->execute([(int) $row['course_id'], $userId, time()]);
    return (int) $row['course_id'];
}

/** All codes belonging to one teacher (newest first). */
function teacher_enroll_codes(int $teacherId): array
{
    $st = db()->prepare('SELECT ec.id, ec.code, ec.course_id, ec.created_at, ec.used_at, ec.used_by,
                         c.title AS course_title, u.name AS used_by_name
                         FROM enroll_codes ec
                         JOIN courses c ON c.id = ec.course_id
                         LEFT JOIN users u ON u.id = ec.used_by
                         WHERE ec.teacher_id = ?
                         ORDER BY ec.id DESC LIMIT 100');
    $st->execute([$teacherId]);
    return $st->fetchAll();
}

/** Revoke an unused code (teachers can only revoke their own). */
function delete_enroll_code(int $teacherId, int $codeId): bool
{
    $st = db()->prepare('DELETE FROM enroll_codes WHERE id = ? AND teacher_id = ? AND used_by IS NULL');
    $st->execute([$codeId, $teacherId]);
    return $st->rowCount() > 0;
}

/* ---------------- teacher access codes (main admin) ---------------- */

function teacher_code_lookup(string $code): ?array
{
    $st = db()->prepare('SELECT tc.*, u.name AS created_by_name FROM teacher_codes tc
                         LEFT JOIN users u ON u.id = tc.created_by WHERE tc.code = ? LIMIT 1');
    $st->execute([strtoupper(trim($code))]);
    return $st->fetch() ?: null;
}

/** Generate a one-time code that lets someone register as a teacher. */
function generate_teacher_code(int $adminId): ?string
{
    $st = db()->prepare('SELECT COUNT(*) FROM teacher_codes WHERE used_by IS NULL');
    $st->execute();
    if ((int) $st->fetchColumn() >= 50) return null; // cap outstanding codes

    for ($i = 0; $i < 8; $i++) {
        $code = 'T-' . strtoupper(bin2hex(random_bytes(3)));
        $chk = db()->prepare('SELECT COUNT(*) FROM teacher_codes WHERE code = ?');
        $chk->execute([$code]);
        if ((int) $chk->fetchColumn() === 0) {
            db()->prepare('INSERT INTO teacher_codes (code, created_by, created_at) VALUES (?,?,?)')
                ->execute([$code, $adminId, time()]);
            return $code;
        }
    }
    return null;
}

/** Atomically redeem a teacher access code. */
function redeem_teacher_code(string $code, int $userId): bool
{
    $st = db()->prepare('SELECT id FROM teacher_codes WHERE code = ? AND used_by IS NULL LIMIT 1');
    $st->execute([strtoupper(trim($code))]);
    $row = $st->fetch();
    if (!$row) return false;
    $st = db()->prepare('UPDATE teacher_codes SET used_by = ?, used_at = ? WHERE id = ? AND used_by IS NULL');
    $st->execute([$userId, time(), (int) $row['id']]);
    return $st->rowCount() > 0;
}

function admin_teacher_codes(): array
{
    return db()->query('SELECT tc.*, u.name AS created_by_name, u2.name AS used_by_name
                        FROM teacher_codes tc
                        LEFT JOIN users u ON u.id = tc.created_by
                        LEFT JOIN users u2 ON u2.id = tc.used_by
                        ORDER BY tc.id DESC LIMIT 100')->fetchAll();
}

function delete_teacher_code(int $codeId): bool
{
    $st = db()->prepare('DELETE FROM teacher_codes WHERE id = ? AND used_by IS NULL');
    $st->execute([$codeId]);
    return $st->rowCount() > 0;
}

/* ---------------- maintenance mode (main admin) ---------------- */

function maintenance_enabled(): bool
{
    return setting_get('maintenance', '') === '1';
}

/* ---- boot-time shutdown gate ------------------------------------------------
 * Lives in lib.php (included by EVERY page) and runs before any auth redirect,
 * so a shut-down site really is closed: every visitor gets HTTP 503 + the
 * "temporarily closed" paper notice — even when the page would have redirected
 * to login.php first (the header.php gate alone is bypassed by that redirect).
 * Still reachable while closed: admin.php (control panel), login.php/logout.php
 * (so the admin can sign in/out), ping.php (heartbeat), and any logged-in ADMIN
 * browsing the site. CLI scripts skip the gate entirely. */
if (PHP_SAPI !== 'cli') {
    $lh_self = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    if ($lh_self === '' || $lh_self === '/' ) $lh_self = 'index.php';
    if (!in_array($lh_self, ['admin.php', 'login.php', 'logout.php', 'ping.php'], true)) {
        $lh_me = null;
        try { $lh_me = current_user(); } catch (Throwable $e) { /* DB not ready */ }
        if (($lh_me['role'] ?? '') !== 'admin' && maintenance_enabled()) {
            http_response_code(503);
            header('Retry-After: 3600');
            ?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>LearnHub — temporarily closed</title>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef1ee radial-gradient(circle at 20% 0%,rgba(16,185,129,.12),transparent 55%);font-family:ui-sans-serif,system-ui,'Segoe UI',Roboto,Arial,sans-serif;color:#1f2937}
  .paper{position:relative;width:min(92vw,460px);background:repeating-linear-gradient(#fffdf6,#fffdf6 30px,#f4f0e4 31px);border-radius:6px;padding:44px 34px 38px;box-shadow:0 18px 40px rgba(15,23,42,.22);transform:rotate(-1.4deg)}
  .tape{position:absolute;top:-13px;left:50%;margin-left:-56px;width:112px;height:24px;background:rgba(16,185,129,.45);box-shadow:0 1px 3px rgba(0,0,0,.15)}
  h1{margin:0;font-size:22px}p{margin:10px 0 0;font-size:14px;line-height:1.6;color:#4b5563}
  .sig{margin-top:18px;font-size:12px;color:#9ca3af}
</style></head>
<body><div class="paper"><div class="tape"></div>
  <h1>🛠️ LearnHub is temporarily closed</h1>
  <p>The administrator has paused the website for maintenance. Please check back soon —
     lessons, quizzes and your progress are safe and will be right here when we reopen.</p>
  <p class="sig">— LearnHub LMS</p>
</div></body></html><?php
            exit;
        }
    }
    unset($lh_self, $lh_me);
}

/* ---------------- main-admin overview (admin.php) ---------------- */

/** Every course with its teacher and enrolled-student count (biggest first). */
function admin_course_overview(): array
{
    return db()->query('SELECT c.id, c.title, c.category, u.name AS teacher_name,
                        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrolled
                        FROM courses c
                        JOIN users u ON u.id = c.teacher_id
                        ORDER BY enrolled DESC, c.title ASC
                        LIMIT 200')->fetchAll();
}

/** Users currently online (heartbeat within PRESENCE_TIMEOUT), newest heartbeat first. */
function admin_online_users(array $roles = []): array
{
    $roles = array_values(array_intersect($roles, ['teacher', 'student', 'admin']));
    $sql = 'SELECT u.id, u.name, u.role, p.last_seen FROM presence p
            JOIN users u ON u.id = p.user_id
            WHERE p.last_seen >= ?';
    $args = [time() - PRESENCE_TIMEOUT];
    if ($roles) {
        $sql .= ' AND u.role IN (' . implode(',', array_fill(0, count($roles), '?')) . ')';
        $args = array_merge($args, $roles);
    }
    $sql .= ' ORDER BY p.last_seen DESC LIMIT 100';
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

function course_material_exists(int $courseId, int $materialId): bool
{
    return get_material($courseId, $materialId) !== null;
}

function course_materials_count(int $courseId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM materials WHERE course_id = ?');
    $stmt->execute([$courseId]);
    return (int) $stmt->fetchColumn();
}

function user_progress_count(int $courseId, int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM progress p JOIN materials m ON m.id = p.material_id WHERE m.course_id = ? AND p.user_id = ?');
    $stmt->execute([$courseId, $userId]);
    return (int) $stmt->fetchColumn();
}

/** Toggle the completed flag for one lesson; returns true when it is completed afterwards. */
function toggle_progress(int $userId, int $materialId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM progress WHERE user_id = ? AND material_id = ?');
    $stmt->execute([$userId, $materialId]);
    if ((int) $stmt->fetchColumn() > 0) {
        db()->prepare('DELETE FROM progress WHERE user_id = ? AND material_id = ?')->execute([$userId, $materialId]);
        return false;
    }
    db()->prepare('INSERT INTO progress (user_id, material_id, completed_at) VALUES (?,?,?)')->execute([$userId, $materialId, time()]);
    return true;
}

/* ---------------- lesson quizzes (assigned by the teacher) ---------------- */

const QUIZ_MAX_QUESTIONS = 20;

/** Load the quiz assigned to a lesson. Correct answers are only included when $withAnswers is true. */
function lesson_quiz(int $materialId, bool $withAnswers = false): ?array
{
    $st = db()->prepare('SELECT * FROM quizzes WHERE material_id = ? LIMIT 1');
    $st->execute([$materialId]);
    $quiz = $st->fetch();
    if (!$quiz) return null;
    $q = db()->prepare('SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id');
    $q->execute([(int) $quiz['id']]);
    $questions = [];
    foreach ($q->fetchAll() as $row) {
        $item = ['id' => (int) $row['id'], 'prompt' => (string) $row['prompt'], 'options' => json_decode((string) $row['options'], true) ?: []];
        if ($withAnswers) $item['correct'] = (int) $row['correct'];
        $questions[] = $item;
    }
    $quiz['id'] = (int) $quiz['id'];
    $quiz['material_id'] = (int) $quiz['material_id'];
    $quiz['pass_score'] = (int) $quiz['pass_score'];
    $quiz['questions'] = $questions;
    return $quiz;
}

/** All quizzes of a course keyed by material id (batch loader for the course page). */
function course_quizzes(int $courseId, bool $withAnswers = false): array
{
    $st = db()->prepare('SELECT q.* FROM quizzes q JOIN materials m ON m.id = q.material_id WHERE m.course_id = ? ORDER BY q.id');
    $st->execute([$courseId]);
    $quizzes = [];
    foreach ($st->fetchAll() as $quiz) $quizzes[(int) $quiz['id']] = $quiz;
    if (!$quizzes) return [];
    $in = implode(',', array_fill(0, count($quizzes), '?'));
    $q = db()->prepare("SELECT * FROM quiz_questions WHERE quiz_id IN ($in) ORDER BY sort_order, id");
    $q->execute(array_keys($quizzes));
    foreach ($q->fetchAll() as $row) {
        $item = ['id' => (int) $row['id'], 'prompt' => (string) $row['prompt'], 'options' => json_decode((string) $row['options'], true) ?: []];
        if ($withAnswers) $item['correct'] = (int) $row['correct'];
        $quizzes[(int) $row['quiz_id']]['questions'][] = $item;
    }
    $out = [];
    foreach ($quizzes as $quiz) {
        $quiz['id'] = (int) $quiz['id'];
        $quiz['material_id'] = (int) $quiz['material_id'];
        $quiz['pass_score'] = (int) $quiz['pass_score'];
        $quiz['questions'] = $quiz['questions'] ?? [];
        $out[(int) $quiz['material_id']] = $quiz;
    }
    return $out;
}

/** Create or fully replace the quiz of a lesson. $questions: [['prompt','options'=>[...],'correct'=>int],...] */
function save_quiz(int $materialId, string $title, int $passScore, array $questions): int
{
    $title = cut(trim($title), 120);
    if ($title === '') throw new RuntimeException('Give the quiz a title.');
    $passScore = max(10, min(100, $passScore));
    if (count($questions) < 1) throw new RuntimeException('Add at least one question.');
    if (count($questions) > QUIZ_MAX_QUESTIONS) throw new RuntimeException('A quiz can have at most ' . QUIZ_MAX_QUESTIONS . ' questions.');
    $clean = [];
    foreach (array_values($questions) as $i => $q) {
        $prompt = cut(trim((string) ($q['prompt'] ?? '')), 500);
        if ($prompt === '') throw new RuntimeException('Question #' . ($i + 1) . ' has no text.');
        $opts = [];
        foreach ((array) ($q['options'] ?? []) as $opt) {
            $opt = cut(trim((string) $opt), 200);
            if ($opt !== '') $opts[] = $opt;
        }
        if (count($opts) < 2) throw new RuntimeException('Question #' . ($i + 1) . ' needs at least two answer options.');
        if (count($opts) > 4) $opts = array_slice($opts, 0, 4);
        $correct = (int) ($q['correct'] ?? 0);
        if ($correct < 0 || $correct >= count($opts)) throw new RuntimeException('Question #' . ($i + 1) . ': mark which option is the correct one.');
        $clean[] = ['prompt' => $prompt, 'options' => $opts, 'correct' => $correct];
    }
    $db = db();
    $db->beginTransaction();
    try {
        // fresh quiz = fresh single attempt: wipe any abandoned in-progress answers
        $q = $db->prepare('SELECT id FROM quizzes WHERE material_id = ? LIMIT 1');
        $q->execute([$materialId]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $oldQuizId) {
            $db->prepare('DELETE FROM quiz_progress WHERE quiz_id = ?')->execute([(int) $oldQuizId]);
        }
        delete_quiz($materialId);
        $db->prepare('INSERT INTO quizzes (material_id, title, pass_score, created_at) VALUES (?,?,?,?)')
            ->execute([$materialId, $title, $passScore, time()]);
        $quizId = (int) $db->lastInsertId();
        $ins = $db->prepare('INSERT INTO quiz_questions (quiz_id, prompt, options, correct, sort_order) VALUES (?,?,?,?,?)');
        foreach ($clean as $i => $q) {
            $ins->execute([$quizId, $q['prompt'], json_encode($q['options'], JSON_UNESCAPED_UNICODE), $q['correct'], $i]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return $quizId;
}

/** Remove the quiz assigned to a lesson (its questions and attempts cascade). */
function delete_quiz(int $materialId): bool
{
    $st = db()->prepare('DELETE FROM quizzes WHERE material_id = ?');
    $st->execute([$materialId]);
    return $st->rowCount() > 0;
}

/** The passing threshold used when a quiz does not carry its own (adjust here). */
const QUIZ_DEFAULT_PASS_SCORE = 70;

/** The stored QuizResult for one student + quiz (null if not taken yet). */
function quiz_result_for(int $quizId, int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM quiz_results WHERE quiz_id = ? AND user_id = ? LIMIT 1');
    $st->execute([$quizId, $userId]);
    if ($r = $st->fetch()) {
        $r['id'] = (int) $r['id'];
        $r['quiz_id'] = (int) $r['quiz_id'];
        $r['user_id'] = (int) $r['user_id'];
        $r['lesson_id'] = (int) $r['lesson_id'];
        $r['correct'] = (int) $r['correct'];
        $r['total'] = (int) $r['total'];
        $r['percentage'] = (float) $r['percentage'];
        $r['answers'] = json_decode((string) ($r['answers'] ?? '{}'), true) ?: [];
    }
    return $r ?: null;
}

/** Mid-quiz saved state (answers locked so far) for one student + quiz. */
function quiz_progress_for(int $quizId, int $userId): ?array
{
    $st = db()->prepare('SELECT answers FROM quiz_progress WHERE quiz_id = ? AND user_id = ? LIMIT 1');
    $st->execute([$quizId, $userId]);
    if ($r = $st->fetch()) {
        return json_decode((string) $r['answers'], true) ?: [];
    }
    return null;
}

/**
 * Lock in ONE answer for an in-progress attempt.
 * - silently ignores re-answers (a question may only be answered once);
 * - returns the current answers map. Saving is guarded so a double POST cannot corrupt state.
 */
function save_quiz_answer(int $quizId, int $userId, int $questionId, int $choice): array
{
    $answers = quiz_progress_for($quizId, $userId) ?? [];
    if (array_key_exists((string) $questionId, $answers)) {
        return $answers; // already locked — never overwrite
    }
    $answers[(string) $questionId] = max(0, $choice);
    db()->prepare('INSERT INTO quiz_progress (quiz_id, user_id, answers, updated_at) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE answers = VALUES(answers), updated_at = VALUES(updated_at)')
        ->execute([$quizId, $userId, json_encode($answers, JSON_UNESCAPED_UNICODE), time()]);
    return $answers;
}

/** Delete an abandoned in-progress attempt (used when a quiz is re-assigned by the teacher). */
function clear_quiz_progress(int $quizId, int $userId): void
{
    db()->prepare('DELETE FROM quiz_progress WHERE quiz_id = ? AND user_id = ?')->execute([$quizId, $userId]);
}

/** Grade the saved answers and store the single QuizResult. Returns the result row. */
function finalize_quiz(int $quizId, int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM quizzes WHERE id = ? LIMIT 1');
    $st->execute([$quizId]);
    $quiz = $st->fetch();
    $answers = quiz_progress_for($quizId, $userId);
    if (!$quiz || $answers === null) return null;

    $q = db()->prepare('SELECT id, correct FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order, id');
    $q->execute([$quizId]);
    $questions = $q->fetchAll();
    $total = count($questions);
    $correct = 0;
    foreach ($questions as $qq) {
        if ((int) ($answers[(string) $qq['id']] ?? -1) === (int) $qq['correct']) $correct++;
    }
    $percentage = $total > 0 ? round($correct * 100 / $total, 2) : 0.0;
    $passScore = max(1, min(100, (int) $quiz['pass_score']));
    $status = ($total > 0 && $percentage >= $passScore) ? 'PASSED' : 'FAILED';

    // lock titles at completion time; UNIQUE(quiz_id,user_id) makes double-finalization harmless
    db()->prepare('INSERT INTO quiz_results (quiz_id, user_id, lesson_id, quiz_title, lesson_title, correct, total, percentage, status, answers, created_at)
                   SELECT q.id, ?, q.material_id, q.title, m.title, ?, ?, ?, ?, ?, ?
                   FROM quizzes q JOIN materials m ON m.id = q.material_id WHERE q.id = ?
                   ON DUPLICATE KEY UPDATE correct = VALUES(correct), total = VALUES(total), percentage = VALUES(percentage), status = VALUES(status), answers = VALUES(answers), created_at = VALUES(created_at)')
        ->execute([$userId, $correct, $total, $percentage, $status, json_encode($answers, JSON_UNESCAPED_UNICODE), time(), $quizId]);
    clear_quiz_progress($quizId, $userId);

    // real event -> notification: the student just got their result
    $pctTxt = rtrim(rtrim(number_format($percentage, 2), '0'), '.');
    add_notification($userId, 'result',
        $status === 'PASSED' ? '🏆 Quiz passed: ' . (string) $quiz['title'] : '📝 Quiz result: ' . (string) $quiz['title'],
        "You scored {$correct}/{$total} ({$pctTxt}%) — " . ($status === 'PASSED' ? 'passed!' : 'not passed yet.'),
        'my_records.php');
    send_quiz_result_email($userId, (string) ($quiz['title'] ?? 'the quiz'), $result); // e-mail the student their result
    return quiz_result_for($quizId, $userId);
}

/** All quiz results of one student, newest first (course + lesson titles joined). */
function student_quiz_records(int $userId): array
{
    $st = db()->prepare('SELECT qr.*, c.title AS course_title, c.id AS course_id
                         FROM quiz_results qr
                         JOIN quizzes q ON q.id = qr.quiz_id
                         JOIN materials m ON m.id = q.material_id
                         JOIN courses c ON c.id = m.course_id
                         WHERE qr.user_id = ?
                         ORDER BY qr.created_at DESC, qr.id DESC');
    $st->execute([$userId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $r['id'] = (int) $r['id'];
        $r['quiz_id'] = (int) $r['quiz_id'];
        $r['lesson_id'] = (int) $r['lesson_id'];
        $r['course_id'] = (int) $r['course_id'];
        $r['correct'] = (int) $r['correct'];
        $r['total'] = (int) $r['total'];
        $r['percentage'] = (float) $r['percentage'];
        $r['created_at'] = (int) $r['created_at'];
        $r['answers'] = json_decode((string) ($r['answers'] ?? '{}'), true) ?: [];
        $out[] = $r;
    }
    return $out;
}

/** All quiz results for the quizzes a teacher owns, newest first (student names joined). */
function teacher_quiz_records(int $teacherId): array
{
    $st = db()->prepare('SELECT qr.*, u.name AS student_name, c.title AS course_title, c.id AS course_id, q.material_id AS material_id
                         FROM quiz_results qr
                         JOIN quizzes q ON q.id = qr.quiz_id
                         JOIN materials m ON m.id = q.material_id
                         JOIN courses c ON c.id = m.course_id
                         JOIN users u ON u.id = qr.user_id
                         WHERE c.teacher_id = ?
                         ORDER BY qr.created_at DESC, qr.id DESC');
    $st->execute([$teacherId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $r['id'] = (int) $r['id'];
        $r['quiz_id'] = (int) $r['quiz_id'];
        $r['lesson_id'] = (int) $r['lesson_id'];
        $r['course_id'] = (int) $r['course_id'];
        $r['material_id'] = (int) $r['material_id'];
        $r['correct'] = (int) $r['correct'];
        $r['total'] = (int) $r['total'];
        $r['percentage'] = (float) $r['percentage'];
        $r['created_at'] = (int) $r['created_at'];
        $r['answers'] = json_decode((string) ($r['answers'] ?? '{}'), true) ?: [];
        $out[] = $r;
    }
    return $out;
}

/** Summary over result rows: taken / passed / failed / average percentage. */
function quiz_records_summary(array $rows): array
{
    $taken = count($rows);
    $passed = 0;
    $failed = 0;
    $sum = 0.0;
    foreach ($rows as $r) {
        if (($r['status'] ?? '') === 'PASSED') $passed++; else $failed++;
        $sum += (float) ($r['percentage'] ?? 0);
    }
    return ['taken' => $taken, 'passed' => $passed, 'failed' => $failed, 'avg' => $taken > 0 ? round($sum / $taken, 1) : 0.0];
}

/**
 * THE quiz gate — one rule for every quiz endpoint:
 * the owning teacher may always open a quiz (preview); everyone else must be
 * enrolled AND have completed the lesson before the quiz is accessible.
 * Returns [course, quiz (with answers), isOwner]; exits with a friendly page otherwise.
 */
function require_quiz_access(int $userId, int $courseId, int $materialId): array
{
    $course = course_row($courseId);
    if (!$course) {
        quiz_gate_page(404, '📘', 'Course not found', 'This course does not exist (or was deleted).', 'courses.php', 'Back to courses');
    }
    $isOwner = (int) $course['teacher_id'] === $userId;
    if (!$isOwner && !is_enrolled_id($courseId, $userId)) {
        quiz_gate_page(403, '🔒', 'Not enrolled', 'Enroll in this course to open its lesson quizzes.', 'course.php?id=' . $courseId, 'Back to the course');
    }
    if (!course_material_exists($courseId, $materialId)) {
        quiz_gate_page(404, '🧪', 'Lesson not found', 'This lesson does not exist (or was deleted).', 'course.php?id=' . $courseId, 'Back to the course');
    }
    $quiz = lesson_quiz($materialId, true);
    if (!$quiz || !$quiz['questions']) {
        quiz_gate_page(404, '🧪', 'No quiz assigned', 'The teacher has not assigned a quiz to this lesson yet.', 'course.php?id=' . $courseId, 'Back to the course');
    }
    if (!$isOwner && !material_completed($userId, $materialId)) {
        quiz_gate_page(403, '🔒', 'Quiz locked', 'Finish the lesson first — the quiz unlocks as soon as the lesson is marked completed.', 'course.php?id=' . $courseId, 'Back to the course');
    }
    return ['course' => $course, 'quiz' => $quiz, 'isOwner' => $isOwner];
}

/** Friendly blocked/missing page used by the quiz gate. */
function quiz_gate_page(int $code, string $icon, string $title, string $msg, string $backHref, string $backLabel): void
{
    http_response_code($code);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' · LearnHub</title><script src="https://cdn.tailwindcss.com"></script>'
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"></head>'
        . '<body class="min-h-screen bg-slate-100 font-sans text-slate-800">'
        . '<div class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-4 text-center">'
        . '<p class="text-5xl">' . $icon . '</p>'
        . '<h1 class="mt-4 text-xl font-bold text-slate-900">' . e($title) . '</h1>'
        . '<p class="mt-2 text-sm leading-6 text-slate-500">' . e($msg) . '</p>'
        . '<a href="' . e($backHref) . '" class="mt-6 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">' . e($backLabel) . '</a>'
        . '</div></body></html>';
    exit;
}

/** Shared markup for one quiz-question editing row (teacher templates + blank master). */
function quiz_question_row_html(?array $q, int $num): string
{
    $opts = (array) ($q['options'] ?? []);
    $correct = (int) ($q['correct'] ?? 0);
    $inp = 'mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200';
    $h = '<div data-q-row class="rounded-xl border border-slate-200 bg-slate-50/60 p-3">';
    $h .= '<div class="flex items-center justify-between gap-2"><span data-q-num class="text-xs font-bold uppercase tracking-wide text-slate-400">Q' . $num . '</span>';
    $h .= '<button type="button" data-q-remove class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-400 hover:bg-rose-50 hover:text-rose-600">✕ Remove</button></div>';
    $h .= '<input name="prompt[]" required maxlength="500" placeholder="Question text (e.g. What does HTML stand for?)" value="' . e((string) ($q['prompt'] ?? '')) . '" class="' . $inp . '">';
    $h .= '<div class="mt-2 grid gap-2 sm:grid-cols-2">';
    for ($i = 0; $i < 4; $i++) {
        $req = $i < 2 ? ' required' : '';
        $ph = $i < 2 ? 'Option ' . ($i + 1) : 'Option ' . ($i + 1) . ' (optional)';
        $h .= '<div class="flex items-center gap-1.5"><span class="w-4 shrink-0 text-center text-[11px] font-bold text-slate-400">' . ($i + 1) . '</span>'
            . '<input name="o' . ($i + 1) . '[]" maxlength="200" placeholder="' . $ph . '" value="' . e((string) ($opts[$i] ?? '')) . '"' . $req . ' class="' . $inp . '"></div>';
    }
    $h .= '</div>';
    $h .= '<label class="mt-2 flex items-center gap-2 text-xs font-medium text-slate-500">✔ Correct answer '
        . '<select name="correct[]" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs outline-none focus:border-indigo-500">';
    for ($i = 0; $i < 4; $i++) $h .= '<option value="' . $i . '"' . ($correct === $i ? ' selected' : '') . '>Option ' . ($i + 1) . '</option>';
    $h .= '</select></label></div>';
    return $h;
}

/* ---------------- material reader (in-browser viewing) ---------------- */

function md_inline(string $s): string
{
    $s = e($s);
    $s = preg_replace('~`([^`]+)`~', '<code class="rounded bg-slate-100 px-1 py-0.5 text-[13px]">$1</code>', $s);
    $s = preg_replace('~\*\*([^*]+)\*\*~', '<strong>$1</strong>', $s);
    $s = preg_replace('~\*([^*]+)\*~', '<em>$1</em>', $s);
    $s = preg_replace('~\[([^\]]+)\]\((https?://[^\s)]+)\)~', '<a href="$2" target="_blank" rel="noopener" class="text-indigo-600 hover:underline">$1</a>', $s);
    return $s;
}

function md_to_html(string $md): string
{
    $lines = preg_split("~\r\n|\r|\n~", $md) ?: [];
    $html = '';
    $inCode = false;
    $inList = false;
    foreach ($lines as $line) {
        if (str_starts_with(ltrim($line), '```')) {
            if ($inCode) { $html .= '</code></pre>'; $inCode = false; }
            else {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 text-[13px] leading-6 text-slate-100"><code>';
                $inCode = true;
            }
            continue;
        }
        if ($inCode) { $html .= e($line) . "\n"; continue; }
        $t = trim($line);
        if ($t === '') { if ($inList) { $html .= '</ul>'; $inList = false; } continue; }
        if (preg_match('~^(#{1,4})\s+(.*)$~', $t, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $lvl = strlen($m[1]);
            $cls = [1 => 'mt-6 text-2xl font-extrabold text-slate-900', 2 => 'mt-5 text-xl font-bold text-slate-900', 3 => 'mt-4 text-lg font-semibold text-slate-900', 4 => 'mt-3 font-semibold text-slate-800'][$lvl];
            $html .= '<h' . $lvl . ' class="' . $cls . '">' . md_inline($m[2]) . '</h' . $lvl . '>';
            continue;
        }
        if (preg_match('~^[-*]\s+(.*)$~', $t, $m)) {
            if (!$inList) { $html .= '<ul class="my-3 list-disc space-y-1 pl-6">'; $inList = true; }
            $html .= '<li>' . md_inline($m[1]) . '</li>';
            continue;
        }
        if ($inList) { $html .= '</ul>'; $inList = false; }
        $html .= '<p>' . md_inline($t) . '</p>';
    }
    if ($inCode) $html .= '</code></pre>';
    if ($inList) $html .= '</ul>';
    return $html;
}

function text_paragraphs_html(string $text): string
{
    $parts = preg_split("~\r\n|\r|\n~", trim($text)) ?: [];
    $html = '';
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $html .= '<p>' . nl2br(e($p)) . '</p>';
    }
    return $html !== '' ? $html : '<p>(empty document)</p>';
}

/* ---------------- material reader payload ---------------- */

/** Build the in-page reader content for a file material.
 *  Documents are shown directly in the browser (native embed for PDF/images,
 *  formatted render for pasted Markdown, client-side renderers for Office files).
 *  No server-side text extraction is used for uploaded documents.
 */
function read_material_payload(array $material): array
{
    $path = UPLOAD_DIR . '/' . basename((string) ($material['filename'] ?? ''));
    $orig = (string) ($material['orig_name'] ?? ($material['filename'] ?? ''));
    $stored = (string) ($material['filename'] ?? '');
    $ext = ext_of($orig);
    if (!is_file($path)) {
        return ['kind' => 'missing', 'viewer' => 'none', 'html' => '<p class="text-slate-500">The file is missing on the server.</p>', 'minSeconds' => 5, 'chars' => 0];
    }
    $size = (int) filesize($path);
    // Minimum reading time scales with file size (~2 KB per second, 10 s – 15 min).
    $minSeconds = max(10, min(900, (int) ceil(max($size, 1024) / 2000)));
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
        return ['kind' => 'image', 'viewer' => 'image', 'html' => '', 'minSeconds' => 8, 'chars' => 0];
    }
    if ($ext === 'pdf') {
        return ['kind' => 'pdf', 'viewer' => 'pdf', 'html' => '', 'minSeconds' => 20, 'chars' => 0];
    }
    // Pasted material (saved by the "Paste text" tab) still renders as formatted reading pages.
    if (is_pasted_material($material) || $ext === 'md') {
        $text = (string) file_get_contents($path);
        if (trim($text) === '') {
            return ['kind' => 'unsupported', 'viewer' => 'none', 'html' => '', 'minSeconds' => 5, 'chars' => 0];
        }
        return ['kind' => 'text', 'viewer' => 'markdown', 'html' => md_to_html($text), 'minSeconds' => max(10, min(900, (int) ceil(strlen($text) / 17))), 'chars' => strlen($text)];
    }
    // Uploaded documents open directly in the browser via client-side viewers (see read.php).
    $viewers = [
        'txt' => 'text', 'csv' => 'text', 'log' => 'text',
        'docx' => 'docx',
        'xlsx' => 'xlsx',
        'pptx' => 'pptx',
    ];
    if (isset($viewers[$ext])) {
        return ['kind' => 'doc', 'viewer' => $viewers[$ext], 'html' => '', 'minSeconds' => $minSeconds, 'chars' => $size];
    }
    return ['kind' => 'unsupported', 'viewer' => 'none', 'html' => '', 'minSeconds' => 5, 'chars' => 0];
}

/** Check whether a filename belongs to a pasted-text lesson. */
function is_pasted_material(array $material): bool
{
    $orig = (string) ($material['orig_name'] ?? '');
    $stored = (string) ($material['filename'] ?? '');
    return $orig === 'pasted-material.md' || str_starts_with(basename($stored), 'pasted_');
}

/* ---------------- automatic progress tracking ---------------- */

/** Mark a lesson complete for a user (idempotent). */
function mark_material_complete(int $userId, int $materialId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM progress WHERE user_id = ? AND material_id = ?');
    $stmt->execute([$userId, $materialId]);
    if ((int) $stmt->fetchColumn() > 0) return true;
    db()->prepare('INSERT INTO progress (user_id, material_id, completed_at) VALUES (?,?,?)')
        ->execute([$userId, $materialId, time()]);
    return true;
}

function material_completed(int $userId, int $materialId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM progress WHERE user_id = ? AND material_id = ?');
    $stmt->execute([$userId, $materialId]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Store watch time for a video lesson. The percent is the real watched time divided by the duration,
 *  and the lesson only completes once the video actually reaches the very end of its duration.
 *  (Reported by the player with $ended = true, by a position sitting at the end, or when full time is watched.) */
function record_video_progress(int $userId, int $materialId, int $watched, int $duration, int $position, bool $ended = false): array
{
    $stmt = db()->prepare('SELECT watched_seconds, duration_seconds FROM video_progress WHERE user_id = ? AND material_id = ?');
    $stmt->execute([$userId, $materialId]);
    $prev = $stmt->fetch();
    $watched  = max(0, max($watched, (int) ($prev['watched_seconds'] ?? 0)));
    $duration = max(0, max($duration, (int) ($prev['duration_seconds'] ?? 0)));
    if ($ended && $duration > 0) $watched = max($watched, $duration);
    $position = max(0, $position);
    $percent  = $duration > 0 ? (int) min(100, round($watched / $duration * 100)) : 0;
    db()->prepare('INSERT INTO video_progress (user_id, material_id, watched_seconds, duration_seconds, position_seconds, percent, updated_at) VALUES (?,?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE watched_seconds = VALUES(watched_seconds), duration_seconds = VALUES(duration_seconds), position_seconds = VALUES(position_seconds), percent = VALUES(percent), updated_at = VALUES(updated_at)')
        ->execute([$userId, $materialId, $watched, $duration, $position, $percent, time()]);
    $complete = false;
    $reachedEnd = $ended || ($duration > 0 && $position >= $duration - 1) || $percent >= 100;
    if ($reachedEnd) {
        mark_material_complete($userId, $materialId);
        $complete = true;
    }
    return ['percent' => $percent, 'watched' => $watched, 'duration' => $duration, 'position' => $position, 'complete' => $complete];
}

/** Store reading depth/time for a material. Progress equals how far the student actually scrolled
 *  through the lesson (0-100%), and the lesson completes only after reaching the very bottom
 *  (100% scroll depth) and staying the minimum reading time. */
function record_read_progress(int $userId, int $materialId, int $depth, int $seconds, int $minSeconds): array
{
    $stmt = db()->prepare('SELECT depth, seconds FROM read_progress WHERE user_id = ? AND material_id = ?');
    $stmt->execute([$userId, $materialId]);
    $prev = $stmt->fetch();
    $depth   = max(0, min(100, max($depth, (int) ($prev['depth'] ?? 0))));
    $seconds = max(0, max($seconds, (int) ($prev['seconds'] ?? 0)));
    db()->prepare('INSERT INTO read_progress (user_id, material_id, depth, seconds, updated_at) VALUES (?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE depth = VALUES(depth), seconds = VALUES(seconds), updated_at = VALUES(updated_at)')
        ->execute([$userId, $materialId, $depth, $seconds, time()]);
    $complete = $depth >= 100 && $seconds >= max(5, $minSeconds);
    if ($complete) mark_material_complete($userId, $materialId);
    return ['depth' => $depth, 'seconds' => $seconds, 'complete' => $complete];
}

/** Per-lesson watch/read state for one user across a whole course (keyed by material id). */
function material_user_states(int $userId, int $courseId): array
{
    $out = [];
    $v = db()->prepare('SELECT vp.material_id, vp.percent, vp.watched_seconds, vp.position_seconds FROM video_progress vp JOIN materials m ON m.id = vp.material_id WHERE vp.user_id = ? AND m.course_id = ?');
    $v->execute([$userId, $courseId]);
    foreach ($v->fetchAll() as $r) {
        $out[(int) $r['material_id']] = ['percent' => (int) $r['percent'], 'watched' => (int) $r['watched_seconds'], 'position' => (int) $r['position_seconds'], 'depth' => 0, 'seconds' => 0];
    }
    $r = db()->prepare('SELECT rp.material_id, rp.depth, rp.seconds FROM read_progress rp JOIN materials m ON m.id = rp.material_id WHERE rp.user_id = ? AND m.course_id = ?');
    $r->execute([$userId, $courseId]);
    foreach ($r->fetchAll() as $row) {
        $mid = (int) $row['material_id'];
        $out[$mid] = ['percent' => 0, 'watched' => 0, 'position' => 0, 'depth' => (int) $row['depth'], 'seconds' => (int) $row['seconds']];
    }
    return $out;
}
/** A user is considered online if their last heartbeat was within this many seconds. */
const PRESENCE_TIMEOUT = 180;

/** Record the heartbeat for a signed-in user (page views + ping.php). */
function touch_presence(int $userId): void
{
    db()->prepare('INSERT INTO presence (user_id, last_seen) VALUES (?,?)
                   ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)')
        ->execute([$userId, time()]);
}

/** Given a list of user ids, return the subset that is currently online. */
function online_user_ids(array $userIds): array
{
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if (!$userIds) return [];
    $in = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = db()->prepare("SELECT user_id FROM presence WHERE last_seen >= ? AND user_id IN ($in)");
    $stmt->execute(array_merge([time() - PRESENCE_TIMEOUT], $userIds));
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Map user_id => online(bool) for a set of possible user ids. */
function presence_map(array $userIds): array
{
    $online = array_flip(online_user_ids($userIds));
    $map = [];
    foreach ($userIds as $id) $map[(int) $id] = isset($online[$id]);
    return $map;
}

/** Log that a user entered a course (one row per visit). */
function record_attendance(int $userId, int $courseId): void
{
    // close the previous still-open session so each page visit becomes its own entry
    db()->prepare('UPDATE attendance SET left_at = ? WHERE user_id = ? AND course_id = ? AND left_at IS NULL')
        ->execute([time() - 5, $userId, $courseId]);
    db()->prepare('INSERT INTO attendance (user_id, course_id, entered_at, left_at, ip) VALUES (?,?,?,NULL,?)')
        ->execute([$userId, $courseId, time(), $_SERVER['REMOTE_ADDR'] ?? '']);
}

/** Close the latest open attendance row for a user/course (called via pagehide beacon). */
function close_attendance(int $userId, int $courseId): void
{
    db()->prepare('UPDATE attendance SET left_at = ? WHERE user_id = ? AND course_id = ? AND left_at IS NULL')
        ->execute([time(), $userId, $courseId]);
}

/** Close every open attendance session for a user (used on logout). */
function close_all_attendance(int $userId): void
{
    db()->prepare('UPDATE attendance SET left_at = ? WHERE user_id = ? AND left_at IS NULL')
        ->execute([time(), $userId]);
}

/**
 * Auto-close attendance sessions that are no longer real:
 * - user's heartbeat went stale (closed the browser without a beacon) -> left at last_seen
 * - no presence row at all -> closed at entry time
 * - sessions that started before $closeBefore (a previous day) -> closed at that boundary
 */
function close_stale_attendance(int $closeBefore = 0): void
{
    $cutoff = time() - PRESENCE_TIMEOUT;
    db()->prepare('UPDATE attendance a JOIN presence p ON p.user_id = a.user_id
                   SET a.left_at = GREATEST(a.entered_at, p.last_seen)
                   WHERE a.left_at IS NULL AND p.last_seen < ?')
        ->execute([$cutoff]);
    db()->prepare('UPDATE attendance a LEFT JOIN presence p ON p.user_id = a.user_id
                   SET a.left_at = a.entered_at
                   WHERE a.left_at IS NULL AND p.user_id IS NULL')
        ->execute([]);
    if ($closeBefore > 0) {
        db()->prepare('UPDATE attendance SET left_at = ? WHERE left_at IS NULL AND entered_at < ?')
            ->execute([$closeBefore, $closeBefore]);
    }
}

/** Remove the presence heartbeat so the user immediately shows offline (used on logout). */
function clear_presence(int $userId): void
{
    db()->prepare('DELETE FROM presence WHERE user_id = ?')->execute([$userId]);
}

/** Small human helper: "2 min", "3 h", "just now"... */
function time_ago(int $ts): string
{
    if ($ts <= 0) return '—';
    $d = time() - $ts;
    if ($d < 0) $d = 0;
    if ($d < 60) return 'just now';
    if ($d < 3600) return (int) floor($d / 60) . ' min ago';
    if ($d < 86400) return (int) floor($d / 3600) . ' h ago';
    return date('M j', $ts);
}

/** MM:SS (or h:m:s) duration from two unix timestamps (falls back to now when left_at is missing). */
function duration_between(?int $start, ?int $end): string
{
    if (!$start || $start <= 0) return '—';
    $from = $start > 0 ? $start : time();
    $to = $end && $end > 0 ? $end : time();
    $s = max(0, $to - $from);
    $h = (int) floor($s / 3600); $m = (int) floor(($s % 3600) / 60); $sec = $s % 60;
    if ($h > 0) return sprintf('%dh %02dm', $h, $m);
    return sprintf('%dm %02ds', $m, $sec);
}

/* ---------------- dashboard analytics (teacher) ---------------- */

/** Inline SVG sparkline for a stat card (normalized polyline + soft area fill). */
function sparkline_svg(array $vals, string $stroke = '#4f46e5', string $fill = 'rgba(79,70,229,0.14)'): string
{
    $vals = array_values(array_map('intval', $vals));
    if (!$vals) $vals = [0, 0];
    if (count($vals) === 1) $vals[] = $vals[0];
    $w = 100; $h = 30; $pad = 3;
    $max = max($vals); $min = min($vals);
    if ($max <= 0) $max = 1;
    $n = count($vals);
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $n > 1 ? $pad + $i * ($w - 2 * $pad) / ($n - 1) : $w / 2;
        $y = $max == $min ? $h / 2 : $h - $pad - ($v - $min) / ($max - $min) * ($h - 2 * $pad);
        $pts[] = [round($x, 1), round($y, 1)];
    }
    $line = implode(' ', array_map(fn ($p) => $p[0] . ',' . $p[1], $pts));
    $last = $pts[$n - 1];
    $svg  = '<svg viewBox="0 0 100 30" preserveAspectRatio="none" class="h-full w-full" aria-hidden="true">';
    $svg .= '<polygon points="' . $line . ' ' . $w . ',' . $h . ' 0,' . $h . '" fill="' . $fill . '"></polygon>';
    $svg .= '<polyline points="' . $line . '" fill="none" stroke="' . $stroke . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>';
    $svg .= '<circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="2.4" fill="' . $stroke . '"></circle>';
    $svg .= '</svg>';
    return $svg;
}

/** Zigzag sparkline — sharp mountain-peak style used on the student stat cards. */
function zigzag_svg(array $vals, string $stroke = '#4f46e5', string $fill = 'rgba(79,70,229,0.16)'): string
{
    $vals = array_values(array_map('intval', $vals));
    if (!$vals) $vals = [0, 0];
    if (count($vals) === 1) $vals[] = $vals[0];
    $w = 100; $h = 30; $top = 4; $base = $h - 2;
    $max = max($vals);
    if ($max <= 0) $max = 1;
    $n = count($vals);
    $step = $n > 1 ? ($w - 4) / ($n - 1) : 0;
    $peaks = [];
    foreach ($vals as $i => $v) {
        $x = 2 + $i * $step;
        $y = $base - ($v / $max) * ($base - $top);
        $peaks[] = [round($x, 1), round(max($top, $y), 1)];
    }
    $line = '';
    for ($i = 0; $i < $n; $i++) {
        $line .= $peaks[$i][0] . ',' . $peaks[$i][1] . ' ';
        if ($i < $n - 1) {
            $midX = round(($peaks[$i][0] + $peaks[$i + 1][0]) / 2, 1);
            $line .= $midX . ',' . $base . ' ';
        }
    }
    $line = trim($line);
    $last = $peaks[$n - 1];
    $svg  = '<svg viewBox="0 0 100 30" preserveAspectRatio="none" class="h-full w-full" aria-hidden="true">';
    $svg .= '<polygon points="2,' . $base . ' ' . $line . ' ' . $w . ',' . $base . '" fill="' . $fill . '"></polygon>';
    $svg .= '<line x1="0" y1="' . $base . '" x2="' . $w . '" y2="' . $base . '" stroke="#cbd5e1" stroke-width="1" vector-effect="non-scaling-stroke"></line>';
    $svg .= '<polyline points="' . $line . '" fill="none" stroke="' . $stroke . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>';
    $svg .= '<circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="2.6" fill="' . $stroke . '"></circle>';
    $svg .= '</svg>';
    return $svg;
}

/** Dual-series area/line chart (14-day activity) as inline SVG. */
function activity_chart_svg(array $visits, array $completions): string
{
    $visits = array_values(array_map('intval', $visits));
    $completions = array_values(array_map('intval', $completions));
    $n = max(count($visits), count($completions), 2);
    $visits = array_pad($visits, $n, 0);
    $completions = array_pad($completions, $n, 0);
    $w = 300; $h = 90; $padX = 4; $padT = 6; $padB = 8;
    $max = max(max($visits), max($completions), 1);
    $plot = function (array $vals) use ($n, $w, $h, $padX, $padT, $padB, $max): array {
        $pts = [];
        foreach ($vals as $i => $v) {
            $x = $padX + $i * ($w - 2 * $padX) / ($n - 1);
            $y = $h - $padB - ($v / $max) * ($h - $padT - $padB);
            $pts[] = [round($x, 1), round(max($padT, $y), 1)];
        }
        return $pts;
    };
    $toLine = fn (array $pts): string => implode(' ', array_map(fn ($p) => $p[0] . ',' . $p[1], $pts));
    $vPts = $plot($visits);
    $cPts = $plot($completions);
    $grid = '';
    foreach ([0.25, 0.5, 0.75] as $g) {
        $y = round($padT + $g * ($h - $padT - $padB), 1);
        $grid .= '<line x1="0" y1="' . $y . '" x2="' . $w . '" y2="' . $y . '" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="3 4" vector-effect="non-scaling-stroke"></line>';
    }
    $svg = '<svg viewBox="0 0 300 90" preserveAspectRatio="none" class="h-40 w-full sm:h-44" aria-hidden="true">' . $grid;
    $svg .= '<polygon points="' . $toLine($vPts) . ' ' . $w . ',' . $h . ' 0,' . $h . '" fill="rgba(79,70,229,0.10)"></polygon>';
    $svg .= '<polyline points="' . $toLine($vPts) . '" fill="none" stroke="#4f46e5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>';
    $svg .= '<polyline points="' . $toLine($cPts) . '" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>';
    $lv = $vPts[$n - 1]; $lc = $cPts[$n - 1];
    $svg .= '<circle cx="' . $lv[0] . '" cy="' . $lv[1] . '" r="3" fill="#4f46e5"></circle>';
    $svg .= '<circle cx="' . $lc[0] . '" cy="' . $lc[1] . '" r="3" fill="#10b981"></circle>';
    $svg .= '</svg>';
    return $svg;
}

/** Per-day counts over the last N days for a teacher's courses (enrollments, completions, visits, uploads). */
function teacher_daily_series(int $teacherId, int $days = 14): array
{
    $today = (int) strtotime('today');
    $start = $today - ($days - 1) * 86400;
    $dayTs = [];
    $labels = [];
    for ($t = $start; $t <= $today; $t += 86400) {
        $dayTs[] = $t;
        $labels[] = date('M j', $t);
    }
    $bucket = function (array $timestamps) use ($dayTs): array {
        $out = array_fill(0, count($dayTs), 0);
        foreach ($timestamps as $ts) {
            $ts = (int) $ts;
            for ($i = count($dayTs) - 1; $i >= 0; $i--) {
                if ($ts >= $dayTs[$i]) { $out[$i]++; break; }
            }
        }
        return $out;
    };
    $seriesOf = function (string $sql) use ($teacherId, $start, $bucket): array {
        $st = db()->prepare($sql);
        $st->execute([$teacherId, $start]);
        return $bucket($st->fetchAll(PDO::FETCH_COLUMN));
    };
    return [
        'labels' => $labels,
        'enrollments' => $seriesOf('SELECT e.created_at FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.teacher_id = ? AND e.created_at >= ?'),
        'completions' => $seriesOf('SELECT p.completed_at FROM progress p JOIN materials m ON m.id = p.material_id JOIN courses c ON c.id = m.course_id WHERE c.teacher_id = ? AND p.completed_at >= ?'),
        'visits' => $seriesOf('SELECT a.entered_at FROM attendance a JOIN courses c ON c.id = a.course_id WHERE c.teacher_id = ? AND a.entered_at >= ?'),
        'uploads' => $seriesOf('SELECT m.created_at FROM materials m JOIN courses c ON c.id = m.course_id WHERE c.teacher_id = ? AND m.created_at >= ?'),
    ];
}

/** Headline totals for one teacher. */
function teacher_counts(int $teacherId): array
{
    $one = function (string $sql) use ($teacherId): int {
        $st = db()->prepare($sql);
        $st->execute([$teacherId]);
        return (int) $st->fetchColumn();
    };
    return [
        'courses' => $one('SELECT COUNT(*) FROM courses WHERE teacher_id = ?'),
        'students' => $one('SELECT COUNT(DISTINCT e.user_id) FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE c.teacher_id = ?'),
        'lessons' => $one('SELECT COUNT(*) FROM materials m JOIN courses c ON c.id = m.course_id WHERE c.teacher_id = ?'),
        'completions' => $one('SELECT COUNT(*) FROM progress p JOIN materials m ON m.id = p.material_id JOIN courses c ON c.id = m.course_id WHERE c.teacher_id = ?'),
        'visits_today' => $one('SELECT COUNT(*) FROM attendance a JOIN courses c ON c.id = a.course_id WHERE c.teacher_id = ? AND a.entered_at >= ' . strtotime('today')),
        'videos' => $one("SELECT COUNT(*) FROM materials m JOIN courses c ON c.id = m.course_id WHERE c.teacher_id = ? AND m.type IN ('video','youtube')"),
    ];
}

/** Students enrolled in this teacher's courses who are currently online. */
function teacher_online_students(int $teacherId, int $limit = 8): array
{
    $st = db()->prepare("SELECT u.id, u.name, p.last_seen FROM enrollments e
                         JOIN courses c ON c.id = e.course_id
                         JOIN users u ON u.id = e.user_id
                         JOIN presence p ON p.user_id = u.id
                         WHERE c.teacher_id = ? AND p.last_seen >= ? AND u.role = 'student'
                         GROUP BY u.id, u.name, p.last_seen ORDER BY p.last_seen DESC LIMIT " . (int) $limit);
    $st->execute([$teacherId, time() - PRESENCE_TIMEOUT]);
    return $st->fetchAll();
}

/** Today's attendance rows for a teacher's courses (newest first). */
function teacher_today_visits(int $teacherId, int $limit = 8): array
{
    $st = db()->prepare('SELECT a.id, a.user_id, a.entered_at, a.left_at, u.name AS student_name, c.title AS course_title
                         FROM attendance a
                         JOIN users u ON u.id = a.user_id
                         JOIN courses c ON c.id = a.course_id
                         WHERE c.teacher_id = ? AND a.entered_at >= ?
                         ORDER BY a.entered_at DESC LIMIT ' . (int) $limit);
    $st->execute([$teacherId, (int) strtotime('today')]);
    return $st->fetchAll();
}

/** Recent enrollments + lesson completions across a teacher's courses (merged, newest first). */
function teacher_recent_activity(int $teacherId, int $limit = 8): array
{
    $rows = [];
    $st = db()->prepare('SELECT e.created_at ts, u.name who, c.title course FROM enrollments e
                         JOIN users u ON u.id = e.user_id JOIN courses c ON c.id = e.course_id
                         WHERE c.teacher_id = ? ORDER BY e.created_at DESC LIMIT ' . (int) $limit);
    $st->execute([$teacherId]);
    foreach ($st->fetchAll() as $r) {
        $rows[] = ['ts' => (int) $r['ts'], 'kind' => 'enrolled', 'who' => (string) $r['who'], 'course' => (string) $r['course'], 'lesson' => ''];
    }
    $st = db()->prepare('SELECT p.completed_at ts, u.name who, c.title course, m.title lesson FROM progress p
                         JOIN users u ON u.id = p.user_id JOIN materials m ON m.id = p.material_id JOIN courses c ON c.id = m.course_id
                         WHERE c.teacher_id = ? ORDER BY p.completed_at DESC LIMIT ' . (int) $limit);
    $st->execute([$teacherId]);
    foreach ($st->fetchAll() as $r) {
        $rows[] = ['ts' => (int) $r['ts'], 'kind' => 'completed', 'who' => (string) $r['who'], 'course' => (string) $r['course'], 'lesson' => (string) $r['lesson']];
    }
    usort($rows, fn ($x, $y) => $y['ts'] <=> $x['ts']);
    return array_slice($rows, 0, $limit);
}

/** Enrolled students with the lowest completion rate across a teacher's courses. */
function teacher_attention_students(int $teacherId, int $limit = 6): array
{
    $totals = [];
    $st = db()->prepare('SELECT m.course_id cid, COUNT(*) n FROM materials m JOIN courses c ON c.id = m.course_id WHERE c.teacher_id = ? GROUP BY m.course_id');
    $st->execute([$teacherId]);
    foreach ($st->fetchAll() as $r) $totals[(int) $r['cid']] = (int) $r['n'];

    $done = [];
    $st = db()->prepare('SELECT mt.course_id cid, p.user_id uid, COUNT(*) n FROM progress p
                         JOIN materials mt ON mt.id = p.material_id JOIN courses c ON c.id = mt.course_id
                         WHERE c.teacher_id = ? GROUP BY mt.course_id, p.user_id');
    $st->execute([$teacherId]);
    foreach ($st->fetchAll() as $r) $done[(int) $r['cid']][(int) $r['uid']] = (int) $r['n'];

    $st = db()->prepare("SELECT e.course_id cid, u.id uid, u.name, c.title course FROM enrollments e
                         JOIN users u ON u.id = e.user_id JOIN courses c ON c.id = e.course_id
                         WHERE c.teacher_id = ? AND u.role = 'student'");
    $st->execute([$teacherId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $cid = (int) $r['cid'];
        $total = $totals[$cid] ?? 0;
        if ($total === 0) continue;
        $doneN = $done[$cid][(int) $r['uid']] ?? 0;
        $pct = (int) round($doneN * 100 / $total);
        if ($pct >= 100) continue;
        $key = (int) $r['uid'];
        if (isset($out[$key]) && $out[$key]['pct'] <= $pct) continue;
        $out[$key] = ['uid' => $key, 'name' => (string) $r['name'], 'course' => (string) $r['course'], 'course_id' => $cid, 'done' => $doneN, 'total' => $total, 'pct' => $pct];
    }
    usort($out, fn ($x, $y) => $x['pct'] <=> $y['pct']);
    return array_slice(array_values($out), 0, $limit);
}

/** Personal daily series for a student (completions + course visits over the last N days). */
function student_daily_series(int $userId, int $days = 14): array
{
    $today = (int) strtotime('today');
    $start = $today - ($days - 1) * 86400;
    $dayTs = [];
    $labels = [];
    for ($t = $start; $t <= $today; $t += 86400) { $dayTs[] = $t; $labels[] = date('M j', $t); }
    $bucket = function (array $timestamps) use ($dayTs): array {
        $out = array_fill(0, count($dayTs), 0);
        foreach ($timestamps as $ts) {
            $ts = (int) $ts;
            for ($i = count($dayTs) - 1; $i >= 0; $i--) { if ($ts >= $dayTs[$i]) { $out[$i]++; break; } }
        }
        return $out;
    };
    $st = db()->prepare('SELECT completed_at FROM progress WHERE user_id = ? AND completed_at >= ?');
    $st->execute([$userId, $start]);
    $completions = $bucket($st->fetchAll(PDO::FETCH_COLUMN));
    $st = db()->prepare('SELECT entered_at FROM attendance WHERE user_id = ? AND entered_at >= ?');
    $st->execute([$userId, $start]);
    return ['labels' => $labels, 'completions' => $completions, 'visits' => $bucket($st->fetchAll(PDO::FETCH_COLUMN))];
}

/* ---------------- private student <-> teacher messaging ---------------- */

/** Return the existing conversation between a student and a teacher, or create it. */
function get_or_create_conversation(int $studentId, int $teacherId): int
{
    db()->prepare('INSERT IGNORE INTO conversations (student_id, teacher_id, created_at) VALUES (?,?,?)')
        ->execute([$studentId, $teacherId, time()]);
    $st = db()->prepare('SELECT id FROM conversations WHERE student_id = ? AND teacher_id = ? LIMIT 1');
    $st->execute([$studentId, $teacherId]);
    return (int) ($st->fetchColumn() ?: 0);
}

/** True when a conversation belongs to the given user (as the student or the teacher). */
function user_owns_conversation(int $conversationId, int $userId): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM conversations WHERE id = ? AND (student_id = ? OR teacher_id = ?)');
    $st->execute([$conversationId, $userId, $userId]);
    return (int) $st->fetchColumn() > 0;
}

/** Send a private message. $toId is the OTHER party. Returns the message id (0 on empty body). */
function send_private_message(int $fromId, int $toId, int $conversationId, string $body): int
{
    $body = cut(trim($body), 2000);
    if ($body === '') return 0;
    db()->prepare('INSERT INTO messages (conversation_id, sender_id, body, is_read, created_at) VALUES (?,?,?,0,?)')
        ->execute([$conversationId, $fromId, $body, time()]);
    $id = (int) db()->lastInsertId();
    if ($toId > 0) {
        $peer = find_user_by_id($fromId);
        add_notification($toId, 'message',
            '💬 New message from ' . ((string) ($peer['name'] ?? 'a user')),
            cut($body, 140),
            'messages.php?with=' . $fromId);
        send_message_email($toId, $fromId, $body); // e-mail copy of the new message
    }
    return $id;
}

/**
 * Conversation list for a user (student sees teachers, teacher sees students),
 * each row carrying the latest message preview + unread count for that user.
 */
function conversations_for(int $userId, string $role): array
{
    $out = [];
    $st = db()->prepare('SELECT id, student_id, teacher_id, created_at FROM conversations
                         WHERE student_id = ? OR teacher_id = ? ORDER BY id DESC');
    $st->execute([$userId, $userId]);
    foreach ($st->fetchAll() as $c) {
        $peerId = $role === 'teacher' ? (int) $c['student_id'] : (int) $c['teacher_id'];
        $peer = find_user_by_id($peerId);
        $last = db()->prepare('SELECT body, created_at FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 1');
        $last->execute([(int) $c['id']]);
        $lr = $last->fetch() ?: [];
        $unread = db()->prepare('SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND sender_id <> ? AND is_read = 0');
        $unread->execute([(int) $c['id'], $userId]);
        $out[] = [
            'id' => (int) $c['id'],
            'peer_id' => $peerId,
            'peer_name' => (string) ($peer['name'] ?? 'User'),
            'last_body' => (string) ($lr['body'] ?? ''),
            'last_ts' => (int) ($lr['created_at'] ?? 0),
            'unread' => (int) $unread->fetchColumn(),
        ];
    }
    return $out;
}

/** People a user can start a conversation with (no conversation yet). */
function message_contacts_for(int $userId, string $role): array
{
    if ($role === 'teacher') {
        // students enrolled in any of the teacher's courses
        $st = db()->prepare('SELECT DISTINCT u.id, u.name FROM enrollments e
                             JOIN courses c ON c.id = e.course_id
                             JOIN users u ON u.id = e.user_id
                             WHERE c.teacher_id = ? AND u.role = \'student\'
                             ORDER BY u.name');
        $st->execute([$userId]);
        return array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'role' => 'student'], $st->fetchAll());
    }
    // student: teachers of their enrolled courses (or a course owner when enrolled)
    $st = db()->prepare('SELECT DISTINCT u.id, u.name FROM courses c
                         JOIN enrollments e ON e.course_id = c.id
                         JOIN users u ON u.id = c.teacher_id
                         WHERE e.user_id = ? AND u.role = \'teacher\'
                         ORDER BY u.name');
    $st->execute([$userId]);
    return array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'role' => 'teacher'], $st->fetchAll());
}
/** Messages in a conversation the user may access, newest-last. Marks them read for the viewer. */
function messages_in_conversation(int $conversationId, int $userId, int $limit = 60, bool $markRead = true): ?array
{
    // inline ownership guard (avoids a cross-function call that can unbind in this PHP build)
    $chk = db()->prepare('SELECT COUNT(*) FROM conversations WHERE id = ? AND (student_id = ? OR teacher_id = ?)');
    $chk->execute([$conversationId, $userId, $userId]);
    if ((int) $chk->fetchColumn() === 0) return null;
    $st = db()->prepare('SELECT id, sender_id, body, is_read, created_at FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT ?');
    $st->execute([$conversationId, $limit]);
    $rows = $st->fetchAll();                // DESC order
    $rev = [];
    for ($i = count($rows) - 1; $i >= 0; $i--) { $rev[] = $rows[$i]; }
    if ($markRead) {
        db()->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id <> ? AND is_read = 0')
            ->execute([$conversationId, $userId]);
    }
    return array_map(fn ($r) => [
        'id' => (int) $r['id'],
        'sender_id' => (int) $r['sender_id'],
        'body' => (string) $r['body'],
        'is_read' => (int) $r['is_read'],
        'created_at' => (int) $r['created_at'],
    ], $rev);
}

/** Total unread private messages for a user (across conversations). */
function unread_message_total(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id
                         WHERE (c.student_id = ? OR c.teacher_id = ?) AND m.sender_id <> ? AND m.is_read = 0');
    $st->execute([$userId, $userId, $userId]);
    return (int) $st->fetchColumn();
}

/** Mark every message in a conversation as read for the viewer (used on open). */
function mark_conversation_read(int $conversationId, int $userId): void
{
    $st = db()->prepare('SELECT COUNT(*) FROM conversations WHERE id = ? AND (student_id = ? OR teacher_id = ?)');
    $st->execute([$conversationId, $userId, $userId]);
    if ((int) $st->fetchColumn() === 0) return;
    db()->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id <> ? AND is_read = 0')
        ->execute([$conversationId, $userId]);
}

/* ---------------- notifications ---------------- */

function add_notification(int $userId, string $type, string $title, string $body = '', string $link = ''): void
{
    db()->prepare('INSERT INTO notifications (user_id, type, title, body, link, is_read, created_at) VALUES (?,?,?,?,?,0,?)')
        ->execute([$userId, cut($type, 40), cut($title, 200), cut($body, 500), cut($link, 500), time()]);
}

/** Notify every student enrolled in a course (used for real events like new lessons/quizzes).
 *  Every notified student ALSO gets an e-mail copy (same content + link). */
function notify_course_students(int $courseId, string $type, string $title, string $body = '', string $link = ''): void
{
    $st = db()->prepare('SELECT e.user_id, u.email FROM enrollments e JOIN users u ON u.id = e.user_id WHERE e.course_id = ?');
    $st->execute([$courseId]);
    foreach ($st->fetchAll() as $r) {
        add_notification((int) $r['user_id'], $type, $title, $body, $link);
        $mail = (string) ($r['email'] ?? '');
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) continue;
        $inner = '<p style="font-size:14px;line-height:1.6;color:#334155">' . e($body) . '</p>'
            . ($link !== '' ? email_button(app_link($link), 'Open it now') : '');
        send_email($mail, $title, email_shell('LearnHub update', $inner));
    }
}

/** Newest-first list. */
function notifications_for(int $userId, int $limit = 20): array
{
    $st = db()->prepare('SELECT id, type, title, body, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?');
    $st->execute([$userId, $limit]);
    return array_map(fn ($r) => [
        'id' => (int) $r['id'],
        'type' => (string) $r['type'],
        'title' => (string) $r['title'],
        'body' => (string) $r['body'],
        'link' => (string) $r['link'],
        'is_read' => (bool) $r['is_read'],
        'created_at' => (int) $r['created_at'],
    ], $st->fetchAll());
}

function unread_notification_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

/** Total notifications (read + unread) — used by the red bell badge. */
function total_notification_count(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

function mark_one_notification_read(int $notifId, int $userId): void
{
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$notifId, $userId]);
}

function mark_all_notifications_read(int $userId): void
{
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$userId]);
}

/* ---------------- e-mail delivery ---------------- */

function email_plain(string $html): string
{
    $t = preg_replace('/<[^>]+>/', ' ', $html);
    $t = preg_replace('/&amp;/', '&', $t);
    $t = preg_replace('/&lt;/', '<', $t);
    $t = preg_replace('/&gt;/', '>', $t);
    $t = preg_replace('/&quot;/', '"', $t);
    $t = preg_replace('/&#39;/', "'", $t);
    return preg_replace('/[ \t\r\n]+/', ' ', $t);
}

function email_log(string $to, string $subject, bool $ok, string $err = ''): void
{
    $line = date('Y-m-d H:i:s') . ' | ' . ($ok ? 'OK  ' : 'FAIL') . ' | ' . $to . ' | ' . cut($subject, 70) . ($err !== '' ? ' | ' . $err : '') . "\n";
    $path = DATA_DIR . '/mail.log';
    @mkdir(DATA_DIR, 0777, true);
    $old = is_file($path) ? (string) (@file_get_contents($path) ?? '') : '';
    if (@file_put_contents($path, $old . $line) === false) {
        @error_log('LearnHub mail: ' . trim($line));   /* host blocks the data folder */
    }
    setting_set('mail_last', trim($line));            /* visible in the app even then */
}

/** Deliver one e-mail. Returns true when a transport accepted it.
 *  Not-configured transports are a silent no-op (false), so e-mail is optional.
 *  Provider is detected from EMAIL_API_URL: brevo | sendgrid | resend (default). */
function email_from_parts(): array
{
    $from = trim(mail_from());
    $name = 'LearnHub LMS'; $addr = $from;
    if (preg_match('/^(.*?)\s*<([^>]+)>\s*$/', $from, $m)) { $name = trim((string) $m[1]); $addr = trim((string) $m[2]); }
    if ($name === '') $name = $addr;
    return ['name' => $name, 'email' => $addr];
}

function email_via_php_mail(string $to, string $subject, string $html, string $text): bool
{
    $res = @mail($to, $subject, $text, $html);
    $ok = (bool) $res;
    email_log($to, $subject, $ok, $ok ? '' : 'PHP mail() returned false (free hosting disables it)');
    return $ok;
}

/** Deliver one e-mail. Returns true when a transport accepted it.
 *  Transport: the provider's HTTP API (Brevo/SendGrid/Resend) whenever a key is
 *  configured — works on hosts that disable PHP mail(). If the provider cannot be
 *  reached at all (outbound blocked) it falls back to PHP mail() so a registration
 *  e-mail is never silently lost. */
function send_email(string $to, string $subject, string $html, string $text = ''): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $subject = cut($subject, 120);
    if ($text === '') $text = email_plain($html);

    $url = mail_api_url();
    $key = mail_api_key();
    if ($url === '' || $key === '') return email_via_php_mail($to, $subject, $html, $text);

    $from = email_from_parts();
    $u = strtolower($url);
    $headers = ['Content-Type: application/json'];
    if (strpos($u, 'brevo') !== false) {
        $headers[] = 'api-key: ' . $key;
        $payload = json_encode(['sender' => ['name' => $from['name'], 'email' => $from['email']], 'to' => [['email' => $to]], 'subject' => $subject, 'htmlContent' => $html, 'textContent' => $text]);
    } elseif (strpos($u, 'sendgrid') !== false) {
        $headers[] = 'Authorization: Bearer ' . $key;
        $payload = json_encode(['personalizations' => [['to' => [['email' => $to]]]], 'from' => ['name' => $from['name'], 'email' => $from['email']], 'subject' => $subject, 'content' => [['type' => 'text/plain', 'value' => $text], ['type' => 'text/html', 'value' => $html]]]);
    } else { /* Resend-style (default) */
        $headers[] = 'Authorization: Bearer ' . $key;
        $payload = json_encode(['from' => mail_from(), 'to' => [$to], 'subject' => $subject, 'html' => $html, 'text' => $text]);
    }

    $r  = lh_http($url, 'POST', $headers, (string) $payload, 10);
    $ok = $r['code'] >= 200 && $r['code'] < 300;
    if (!$ok && $r['code'] === 0) {
        /* the provider was never reached: outbound blocked, DNS, or no HTTP
         * transport. Do not drop the mail - try PHP mail() as a last resort. */
        email_log($to, $subject, false, $r['err'] . ' -> trying PHP mail()');
        return email_via_php_mail($to, $subject, $html, $text);
    }
    $err = $ok ? '' : ($r['err'] !== '' ? $r['err'] : 'HTTP ' . $r['code'] . ': ' . substr($r['body'], 0, 180));
    email_log($to, $subject, $ok, $err);
    return $ok;
}

/* ---- delivery verification (Brevo) -----------------------------------------
 * The provider's API returning 2xx only means the mail was QUEUED. It can still
 * be refused at delivery (e.g. "sender not valid", bounced, spam). These helpers
 * read the provider's event feed so the app can report what really happened. */

/** Recent Brevo transactional events, newest first. Empty when unsupported. */
function brevo_events(int $limit = 20): array
{
    if (mail_provider() !== 'brevo') return [];
    $r = lh_http('https://api.brevo.com/v3/smtp/statistics/events?limit=' . $limit . '&sort=desc', 'GET',
        ['accept: application/json', 'api-key: ' . mail_api_key()], '', 8);
    $j = json_decode($r['body'], true);
    return is_array($j['events'] ?? null) ? $j['events'] : [];
}

/** Wait briefly, then report what the provider really did with the newest mail
 *  whose subject contains $needle. state: delivered | error | queued | unknown. */
function email_delivery_state(string $needle, int $waitSeconds = 8): array
{
    $deadline = time() + max(0, $waitSeconds);
    $best = ['state' => 'unknown', 'reason' => ''];
    while (true) {
        foreach (brevo_events(15) as $ev) {
            if ($needle !== '' && stripos((string) ($ev['subject'] ?? ''), $needle) === false) continue;
            $name = strtolower((string) ($ev['event'] ?? ''));
            if ($name === 'delivered') return ['state' => 'delivered', 'reason' => ''];
            if (in_array($name, ['error', 'blocked', 'bounced', 'spam', 'complaint', 'hard_bounce', 'soft_bounce'], true)) {
                return ['state' => 'error', 'reason' => (string) ($ev['reason'] ?? $name)];
            }
            if ($name === 'requests') $best = ['state' => 'queued', 'reason' => ''];
        }
        if (time() >= $deadline) return $best;
        sleep(2);
    }
}

/** True when EMAIL_FROM's address is one of the provider's validated senders. */
function email_sender_is_verified(): ?bool
{
    if (mail_provider() !== 'brevo') return null;
    $r = lh_http('https://api.brevo.com/v3/senders', 'GET',
        ['accept: application/json', 'api-key: ' . mail_api_key()], '', 10);
    if ($r['code'] < 200 || $r['code'] >= 300) return null;
    $j = json_decode($r['body'], true);
    if (!is_array($j['senders'] ?? null)) return null;
    $want = strtolower(email_from_parts()['email']);
    foreach ($j['senders'] as $s) {
        if (strtolower((string) ($s['email'] ?? '')) === $want) return (bool) ($s['active'] ?? false);
    }
    return false;
}

/** Plain-language report of whether THIS server can actually deliver e-mail.
 *  Meant to be shown in the admin mail-test panel so a deployed site explains
 *  itself instead of silently failing. */
function mail_diagnostics(): array
{
    $out = ['transport' => 'none', 'reachable' => null, 'sender_ok' => null, 'notes' => []];
    $prov = mail_provider();
    $out['transport'] = $prov === 'php-mail' ? 'php-mail' : $prov;
    $out['http'] = lh_http_transport();
    $out['from'] = mail_from();
    $out['app_url'] = mail_app_url();

    if ($out['transport'] === 'none') {
        $out['notes'][] = 'No e-mail transport is configured on this server: paste a Brevo API key in Settings (or set EMAIL_API_KEY in config.php).';
    }
    if ($out['transport'] === 'php-mail') {
        $out['notes'][] = 'Only PHP mail() is available. Free hosting (InfinityFree) disables it - paste a Brevo API key in Settings instead.';
    }
    if ($out['http'] === 'none') {
        $out['notes'][] = 'This host has cURL disabled AND allow_url_fopen Off, so no provider API can be called from here.';
    }
    if ($out['app_url'] === '') {
        $out['notes'][] = 'APP_URL is unknown: links inside e-mails will be relative and may not open.';
    }
    $want = strtolower(email_from_parts()['email']);
    if ($want === '' || !filter_var($want, FILTER_VALIDATE_EMAIL)) {
        $out['notes'][] = 'The sending address is not a valid e-mail address - the provider will reject every mail.';
    }
    if ($out['transport'] !== 'none' && $out['transport'] !== 'php-mail') {
        /* can this server reach the provider at all? (a 4xx/2xx both prove it) */
        $auth = $prov === 'brevo' ? 'api-key: ' : 'Authorization: Bearer ';
        $r = lh_http(mail_api_url(), 'GET', ['accept: application/json', $auth . mail_api_key()], '', 10);
        $out['reachable'] = $r['code'] > 0;
        $out['http_code'] = $r['code'];
        if (!$out['reachable']) {
            $out['notes'][] = 'This server could not reach the e-mail provider: ' . ($r['err'] !== '' ? $r['err'] : 'connection failed') . '.';
        }
        $out['sender_ok'] = email_sender_is_verified();
        if ($out['sender_ok'] === false) {
            $out['notes'][] = 'The sending address (' . $want . ') is NOT in the provider\'s validated-sender list - mail is accepted then refused at delivery. Validate it in the provider dashboard, spelled exactly.';
        }
    }
    return $out;
}

/* ---- small HTML builders for the e-mail bodies ---- */

function email_shell(string $title, string $inner): string
{
    return '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:24px auto;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:28px">'
        . '<h2 style="margin:0 0 14px;font-size:19px;color:#0f172a">' . $title . '</h2>'
        . $inner
        . '<p style="margin:18px 0 0;font-size:12px;color:#94a3b8">— LearnHub LMS</p>'
        . '</div>';
}

function email_button(string $href, string $label): string
{
    return '<a href="' . e($href) . '" style="display:inline-block;margin-top:14px;border-radius:8px;background:#0f766e;color:#fff;font-weight:600;font-size:13px;padding:10px 18px;text-decoration:none">' . e($label) . '</a>';
}

function app_link(string $path): string
{
    $base = trim(mail_app_url(), '/');
    return $base !== '' ? $base . '/' . $path : $path;
}

/* ---------------- event e-mails ---------------- */

/** Greeting e-mail for EVERY new account. A student who enrolled with an
 *  invitation code gets their course details; a teacher (or a student with no
 *  course yet) gets a short "how to start" note. */
function send_welcome_email(int $studentId, int $courseId = 0): void
{
    $u = find_user_by_id($studentId);
    if (!$u) return;
    $mail = (string) ($u['email'] ?? '');
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) return;
    $c = $courseId > 0 ? course_row($courseId) : null;
    if (!$c) {
        /* teacher (or student without a course) - short "how to start" note */
        $inner = '<p style="font-size:14px;color:#334155">Hi ' . e((string) $u['name']) . ',</p>'
            . '<p style="font-size:14px;line-height:1.6;color:#334155">Your LearnHub account is ready. Log in anytime with <b>' . e($mail) . '</b>.</p>'
            . '<p style="font-size:14px;line-height:1.6;color:#334155">As a teacher you can create a course, upload lessons (files, video or a YouTube link), build quizzes, and invite students with a one-time enrollment code. Your students then receive every update by e-mail too.</p>'
            . email_button(app_link('dashboard.php'), 'Open your dashboard');
        send_email($mail, 'Welcome to LearnHub', email_shell('Welcome! ', $inner));
        return;
    }
    $inner = '<p style="font-size:14px;color:#334155">Hi ' . e((string) $u['name']) . ',</p>'
        . '<p style="font-size:14px;line-height:1.6;color:#334155">You are now enrolled in <b style="color:#0f172a">' . e((string) $c['title']) . '</b> · taught by ' . e((string) ($c['teacher_name'] ?? 'your teacher')) . '. New lessons, quizzes and messages will land here in your inbox.</p>'
        . '<p style="font-size:14px;color:#334155">Log in anytime with <b>' . e((string) $u['email']) . '</b>.</p>'
        . email_button(app_link('login.php'), 'Go to LearnHub');
    send_email((string) $u['email'], 'Welcome to ' . cut((string) $c['title'], 60) . ' 🎉', email_shell('Welcome! 🎉', $inner));
}

/** Quiz result pushed to the student's inbox. */
function send_quiz_result_email(int $studentId, string $quizTitle, array $result): void
{
    $u = find_user_by_id($studentId);
    if (!$u) return;
    $passed = ($result['status'] ?? '') === 'PASSED';
    $pct = rtrim(rtrim(number_format((float) ($result['percentage'] ?? 0), 2), '0'), '.');
    $inner = '<p style="font-size:14px;color:#334155">You scored <b>' . (int) ($result['correct'] ?? 0) . '/' . (int) ($result['total'] ?? 0)
        . '</b> (' . $pct . '%) on “' . e(cut($quizTitle, 80)) . '”.</p>'
        . '<p style="font-size:14px;' . ($passed ? 'color:#047857' : 'color:#b91c1c') . '">' . ($passed ? '✅ Passed — great job!' : '❌ Not passed — review your answers and ask your teacher if you need help.') . '</p>'
        . email_button(app_link('my_records.php'), 'View your records');
    send_email((string) $u['email'], ($passed ? '✅ ' : '📝 ') . 'Quiz result: ' . cut($quizTitle, 60), email_shell('Quiz result', $inner));
}

/** A private message lands in the recipient's inbox. */
function send_message_email(int $toId, int $fromId, string $body): void
{
    $dst = find_user_by_id($toId);
    $src = find_user_by_id($fromId);
    if (!$dst || !$src) return;
    if (!filter_var((string) ($dst['email'] ?? ''), FILTER_VALIDATE_EMAIL)) return;
    $inner = '<p style="font-size:14px;color:#334155"><b>' . e((string) $src['name']) . '</b> sent you a message:</p>'
        . '<p style="font-size:14px;color:#0f172a;white-space:pre-wrap">' . e(cut($body, 220)) . '</p>'
        . email_button(app_link('messages.php?with=' . $fromId), 'Open messages');
    send_email((string) $dst['email'], '💬 New message from ' . cut((string) $src['name'], 40), email_shell('New message', $inner));
}

/** Daily catch-up reminder — piggybacks on the presence heartbeat, max 1/day,
 *  only when something actually needs their attention (never spam). */
function maybe_send_digest(int $userId): void
{
    $now = time();
    $last = (int) user_meta_get($userId, 'digest_at');
    if ($now - $last < 86400) return;
    $n = unread_notification_count($userId);
    $m = unread_message_total($userId);
    /* ...and what is still waiting to be DONE in their courses */
    $lessons = 0; $quizzes = 0;
    try {
        $st = db()->prepare('SELECT COUNT(*) FROM enrollments e'
            . ' JOIN materials m ON m.course_id = e.course_id'
            . ' LEFT JOIN progress p ON p.material_id = m.id AND p.user_id = e.user_id'
            . ' WHERE e.user_id = ? AND p.material_id IS NULL');
        $st->execute([$userId]);
        $lessons = (int) $st->fetchColumn();

        $st = db()->prepare('SELECT COUNT(*) FROM enrollments e'
            . ' JOIN materials m ON m.course_id = e.course_id'
            . ' JOIN quizzes q ON q.material_id = m.id'
            . ' LEFT JOIN quiz_results r ON r.quiz_id = q.id AND r.user_id = e.user_id'
            . ' WHERE e.user_id = ? AND r.quiz_id IS NULL');
        $st->execute([$userId]);
        $quizzes = (int) $st->fetchColumn();
    } catch (Throwable $e) {
        /* an older or absent table must never break the presence heartbeat */
    }
    if ($n === 0 && $m === 0 && $lessons === 0 && $quizzes === 0) return;
    $u = find_user_by_id($userId);
    if (!$u || !filter_var((string) ($u['email'] ?? ''), FILTER_VALIDATE_EMAIL)) return;
    $bits = [];
    if ($n > 0) $bits[] = $n . ' new ' . ($n === 1 ? 'update' : 'updates');
    if ($m > 0) $bits[] = $m . ' unread ' . ($m === 1 ? 'message' : 'messages');
    if ($quizzes > 0) $bits[] = $quizzes . ($quizzes === 1 ? ' quiz to take' : ' quizzes to take');
    if ($lessons > 0) $bits[] = $lessons . ($lessons === 1 ? ' lesson to finish' : ' lessons to finish');
    $inner = '<p style="font-size:14px;color:#334155">Hi ' . e((string) $u['name']) . ',</p>'
        . '<p style="font-size:14px;color:#334155">Since your last visit you have:</p><ul>'
        . ($n > 0 ? '<li style="font-size:14px;color:#334155">' . $n . ' new update' . ($n === 1 ? '' : 's') . ' in your courses</li>' : '')
        . ($m > 0 ? '<li style="font-size:14px;color:#334155">' . $m . ' unread message' . ($m === 1 ? '' : 's') . '</li>' : '')
        . ($quizzes > 0 ? '<li style="font-size:14px;color:#334155">' . $quizzes . ' quiz' . ($quizzes === 1 ? '' : 'zes') . ' still to take</li>' : '')
        . ($lessons > 0 ? '<li style="font-size:14px;color:#334155">' . $lessons . ' lesson' . ($lessons === 1 ? '' : 's') . ' still to complete</li>' : '')
        . '</ul>'
        . email_button(app_link('dashboard.php'), 'See what is new')
        . '<p style="font-size:12px;color:#94a3b8;margin-top:6px">One reminder per day at most — log in to clear it.</p>';
    send_email((string) $u['email'], 'LearnHub: ' . implode(' and ', $bits) . ' waiting for you', email_shell('You have updates 👋', $inner));
    user_meta_set($userId, 'digest_at', (string) $now);
}

/* small per-user settings store (digest_at, …) — auto-created table */
function user_meta_set(int $uid, string $k, string $v): void
{
    user_meta_ensure();
    /* portable upsert — VALUES(col) is MySQL ≥ 8.0.19 only, so repeat the param */
    db()->prepare('INSERT INTO user_meta (user_id, k, v) VALUES (?,?,?) ON DUPLICATE KEY UPDATE v = ?')->execute([$uid, $k, $v, $v]);
}
function user_meta_get(int $uid, string $k): string
{
    user_meta_ensure();
    $st = db()->prepare('SELECT v FROM user_meta WHERE user_id = ? AND k = ?');
    $st->execute([$uid, $k]);
    return (string) ($st->fetchColumn() ?? '');
}
function user_meta_ensure(): void
{
    static $done = false;
    if ($done) return;
    db()->exec('CREATE TABLE IF NOT EXISTS user_meta (user_id INT UNSIGNED NOT NULL, k VARCHAR(40) NOT NULL, v VARCHAR(255) NOT NULL DEFAULT "", PRIMARY KEY (user_id, k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $done = true;
}

ensure_storage();
