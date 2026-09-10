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

/* ---- MySQL connection settings (XAMPP defaults — edit if yours differ) ---- */
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'learnhub';
const DB_USER = 'root';
const DB_PASS = '';

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
        . '<p style="margin:0;color:#64748b;font-size:14px;line-height:1.6">Start <b>MySQL</b> in the XAMPP Control Panel, then reload this page. '
        . 'Connection settings live at the top of <code>lib.php</code>.</p>'
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
        $pdo->exec('USE `' . DB_NAME . '`');
    } catch (PDOException $e) {
        db_error_page('Could not open the `' . DB_NAME . '` database. (' . $e->getMessage() . ')');
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
            role ENUM('teacher','student') NOT NULL DEFAULT 'student',
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
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
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
    if (($u['role'] ?? '') !== 'teacher') { header('Location: dashboard.php'); exit; }
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

function guess_mime(string $stored): string
{
    $path = UPLOAD_DIR . '/' . basename($stored);
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if (is_string($m) && $m !== '') return $m;
    }
    return match (ext_of($stored)) {
        'pdf' => 'application/pdf', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'png' => 'image/png', 'jpg', 'jpeg' => 'image/jpeg', 'txt' => 'text/plain',
        default => 'application/octet-stream',
    };
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
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($name) . '"');
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

/** Store watch time for a video lesson; auto-completes at >= 90% watched. */
function record_video_progress(int $userId, int $materialId, int $watched, int $duration, int $position): array
{
    $stmt = db()->prepare('SELECT watched_seconds, duration_seconds FROM video_progress WHERE user_id = ? AND material_id = ?');
    $stmt->execute([$userId, $materialId]);
    $prev = $stmt->fetch();
    $watched  = max(0, max($watched, (int) ($prev['watched_seconds'] ?? 0)));
    $duration = max(0, max($duration, (int) ($prev['duration_seconds'] ?? 0)));
    $position = max(0, $position);
    $percent  = $duration > 0 ? (int) min(100, round($watched / $duration * 100)) : 0;
    db()->prepare('INSERT INTO video_progress (user_id, material_id, watched_seconds, duration_seconds, position_seconds, percent, updated_at) VALUES (?,?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE watched_seconds = VALUES(watched_seconds), duration_seconds = VALUES(duration_seconds), position_seconds = VALUES(position_seconds), percent = VALUES(percent), updated_at = VALUES(updated_at)')
        ->execute([$userId, $materialId, $watched, $duration, $position, $percent, time()]);
    $complete = false;
    if ($percent >= 90) {
        mark_material_complete($userId, $materialId);
        $complete = true;
    }
    return ['percent' => $percent, 'watched' => $watched, 'duration' => $duration, 'complete' => $complete];
}

/** Store reading depth/time for a material; auto-completes at >= 95% depth after the minimum reading time. */
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
    $complete = $depth >= 95 && $seconds >= max(5, $minSeconds);
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

ensure_storage();
