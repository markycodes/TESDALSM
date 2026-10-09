<?php
/**
 * LearnHub LMS — core library.
 * PHP + MySQL (PDO). The database, schema and demo data are created automatically on first run.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    /* This is the ONLY cookie LearnHub sets — the login session (see
       cookie_policy.php for the full list of cookies and browser storage).
       The attributes are pinned here so the policy can state them exactly:
         HttpOnly  scripts cannot read it (a stolen XSS payload stays useless)
         SameSite  Lax — a cross-site POST never carries the session
         Secure    switched on by itself whenever the request arrives over HTTPS
       Nothing here is for advertising, analytics or profiling. */
    $lh_https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_set_cookie_params([
        'lifetime' => 0,          /* until the browser closes */
        'path'     => '/',
        'domain'   => '',
        'secure'   => $lh_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---- Where everything lives -------------------------------------------------
 * `paths.php` owns that one question: where this site's PHP sits, where
 * `assets/`, `logo/`, `signature/`, `data/` and `uploads/` are, and how they are
 * found when the private half of the app has been put ABOVE `public_html` on a
 * shared host (a Hostinger-style deploy). It also loads config.php — and
 * config.php may itself live above the document root, so the MySQL password and
 * the API keys never have to sit inside it.
 *
 * Required here, first, because the constants it defines are used by everything
 * below and by every page: DATA_DIR, UPLOAD_DIR, ASSET_DIR, LOGO_DIR,
 * SIGNATURE_DIR — plus lh_path(), the function that turns any
 * repository-relative path into the folder it really lives in.
 *
 * With no config at all every one of them is this folder, exactly as before. */
require_once __DIR__ . '/paths.php';

define('MAX_UPLOAD_BYTES', (PHP_INT_SIZE >= 8 ? 4 : 1) * 1024 * 1024 * 1024); // 4 GB on 64-bit PHP (1 GB fallback on 32-bit) — fits 1080p hour-long videos; php.ini/.htaccess may cap lower

/* ---- MySQL connection settings --------------------------------------------
 * Defaults are the XAMPP ones. For other hosts (InfinityFree, Hostinger, …)
 * create a config.php — beside this file OR one level above it — copy
 * config.sample.php and fill in the values from your hosting control panel.
 * config.php is git-ignored, so credentials are never committed.
 * ------------------------------------------------------------------------- */

/* ---- The clock the whole app keeps ------------------------------------------
 * Philippine time, everywhere, because that is the country this school teaches
 * in — not the zone of whichever machine happens to run PHP. Nothing else in
 * this codebase asks the database for a time (no NOW(), no CURRENT_TIMESTAMP):
 * every timestamp is written by PHP and read back as wall clock, so setting the
 * zone once, here, moves greetings, "today", "online now", quiz openings,
 * certificates and reminders together — and moves them the same way on XAMPP and
 * on the deployed site, which a php.ini line never does (a stock XAMPP ships
 * date.timezone=Europe/Berlin, six or seven hours behind Manila: "Good morning"
 * at two in the afternoon, "Good evening" before lunch).
 *
 * Put APP_TIMEZONE in config.php to move the app to another zone. */
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);

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

/* ---- hardening: crash output & secrets --------------------------------------
 * A failed query, an uncaught throwable or a PHP fatal must never print SQL,
 * file paths or credentials to a visitor. The detail goes to data/error.log
 * (a web-blocked folder) and the visitor gets a short apology page instead.
 * On the local dev machine (XAMPP) the real message is still shown — it helps
 * development and nothing there is public. PHP notices/warnings are never
 * displayed on a live domain.
 * -------------------------------------------------------------------------- */

/** True when this request runs on the local machine (XAMPP) or via the CLI. */
function lms_is_local(): bool
{
    static $local = null;
    if ($local !== null) return $local;
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = (string) preg_replace('/:\d+$/', '', $host);            /* 127.0.0.1:8099 -> 127.0.0.1 */
    return $local = PHP_SAPI === 'cli'
        || $host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1'
        || str_ends_with($host, '.local') || str_ends_with($host, '.test');
}

/** Append one line to data/error.log; falls back to the host's own error log. */
function lms_error_log(string $message): void
{
    $line = date('Y-m-d H:i:s') . ' | ' . str_replace(["\r", "\n"], ' ', trim($message)) . "\n";
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0777, true);
    if (@file_put_contents(DATA_DIR . '/error.log', $line, FILE_APPEND) === false) {
        @error_log('LearnHub: ' . trim($line));
    }
}

/** Last-resort page for an uncaught error: log the detail, show nothing risky. */
function lms_fatal_page(Throwable $e): void
{
    $detail = get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
    lms_error_log($detail);
    if (PHP_SAPI === 'cli') { fwrite(STDERR, '[fatal] ' . $detail . "\n"); return; }
    if (!headers_sent()) http_response_code(500);
    $shown = lms_is_local() ? $detail : 'Something went wrong while handling this request.';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Error · LearnHub</title></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f8fafc;font-family:Segoe UI,Arial,sans-serif">'
        . '<div style="max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;box-shadow:0 10px 30px rgba(0,0,0,.06)">'
        . '<h1 style="margin:0 0 10px;font-size:20px;color:#0f172a">⚠️ Something broke</h1>'
        . '<p style="margin:0 0 10px;color:#334155;font-size:14px;line-height:1.6">' . e($shown) . '</p>'
        . '<p style="margin:0;color:#64748b;font-size:14px;line-height:1.6">The full detail was written to '
        . '<code>data/error.log</code>. Reload the page — or try again in a moment.</p>'
        . '</div></body></html>';
}

/* Uncaught throwables -> safe page. */
set_exception_handler(static function (Throwable $e): void { lms_fatal_page($e); });
/* PHP fatals (E_ERROR, parse errors, out-of-memory) -> same safe page. */
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
    lms_fatal_page(new ErrorException($err['message'], 0, $err['type'], (string) $err['file'], (int) $err['line']));
});
if (!lms_is_local()) {
    @ini_set('display_errors', '0');            /* never echo warnings / SQL traces */
    @ini_set('display_startup_errors', '0');
    @ini_set('log_errors', '1');
}

/* ---- encrypted URL parameters ----------------------------------------------
 * Numeric ids in URLs (course.php?id=3, download.php?c=3&m=9, …) are encrypted
 * on the way out and decrypted on the way in, so visitors can never read, guess
 * or enumerate other users'/courses' ids by editing the address bar. Links keep
 * working either way: a plain `?id=3` (old e-mails, GET form submits, hand-
 * typed) is still accepted, it just reveals nothing new.
 * The key lives in data/url-secret.key (auto-created, folder web-blocked).
 * -------------------------------------------------------------------------- */

/** Secret key for URL encryption; generated once, then stable — links must not
 *  expire when a page is reloaded. Rotating the file invalidates old tokens
 *  (they simply fall back to "not found" — nothing breaks). */
function lh_url_secret(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $file = DATA_DIR . '/url-secret.key';
    $key = is_file($file) ? trim((string) @file_get_contents($file)) : '';
    if (strlen($key) < 64) {
        $key = bin2hex(random_bytes(32));
        @file_put_contents($file, $key);
    }
    return $key;
}

/** URL-safe base64 (no +/= padding, so tokens paste cleanly into any URL). */
function lh_b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}
function lh_b64url_decode(string $s): string
{
    return (string) base64_decode(strtr($s, '-_', '+/'));
}

/** Encrypt one URL parameter value. Non-numeric values pass through untouched
 *  (dates, categories, verification codes …) — only ids are worth hiding. */
function lh_enc_id(int|string $v): string
{
    $v = (string) $v;
    if ($v === '' || !ctype_digit($v)) return $v;
    $enc = hash('sha256', lh_url_secret() . ':enc', true);   /* 32-byte AES key */
    $mac = hash('sha256', lh_url_secret() . ':mac', true);   /* 32-byte MAC key */
    if (function_exists('openssl_encrypt')) {
        $iv = random_bytes(16);
        $ct = (string) openssl_encrypt($v, 'aes-256-cbc', $enc, OPENSSL_RAW_DATA, $iv);
        return 'e' . lh_b64url($iv . $ct . substr(hash_hmac('sha256', $iv . $ct, $mac, true), 0, 16));
    }
    /* no openssl on this host: still tamper-proof (signed), just not hidden */
    return 'p' . $v . '.' . lh_b64url(substr(hash_hmac('sha256', 'p' . $v, $mac, true), 0, 12));
}

/** Decrypt one URL parameter. Accepts encrypted tokens AND plain numbers (old
 *  links / form submits keep working). Unknown or tampered token -> 0, which
 *  every page already treats as "not found". */
function lh_dec_id(int|string $v): int
{
    $v = (string) $v;
    if ($v === '') return 0;
    if (ctype_digit($v)) return (int) $v;
    $mac = hash('sha256', lh_url_secret() . ':mac', true);
    if (strlen($v) > 2 && $v[0] === 'p' && preg_match('/^p(\d+)\.([A-Za-z0-9_-]+)$/', $v, $m)) {
        $want = lh_b64url(substr(hash_hmac('sha256', 'p' . $m[1], $mac, true), 0, 12));
        return hash_equals($want, $m[2]) ? (int) $m[1] : 0;
    }
    if (strlen($v) < 40 || $v[0] !== 'e' || !preg_match('/^e[A-Za-z0-9_-]+$/', $v)) return 0;
    if (!function_exists('openssl_decrypt')) return 0;
    $raw = lh_b64url_decode(substr($v, 1));
    if (strlen($raw) < 48) return 0;
    $iv = substr($raw, 0, 16);
    $ct = substr($raw, 16, -16);
    $tag = substr($raw, -16);
    if (!hash_equals(substr(hash_hmac('sha256', $iv . $ct, $mac, true), 0, 16), $tag)) return 0;
    $enc = hash('sha256', lh_url_secret() . ':enc', true);
    $plain = openssl_decrypt($ct, 'aes-256-cbc', $enc, OPENSSL_RAW_DATA, $iv);
    return ($plain !== false && ctype_digit($plain)) ? (int) $plain : 0;
}

/** Inbound: decrypt the known id params ONCE, in place, before any page logic
 *  runs — pages keep reading `$_GET['c']` exactly as before. */
function lh_decrypt_incoming(): void
{
    foreach (['id', 'c', 'm', 'with', 'course'] as $k) {
        if (isset($_GET[$k]) && is_string($_GET[$k])) $_GET[$k] = lh_dec_id($_GET[$k]);
    }
    /* presence.php?ids=1,2,3 — a comma list of user ids */
    if (isset($_GET['ids']) && is_string($_GET['ids']) && str_contains($_GET['ids'], 'e')) {
        $_GET['ids'] = implode(',', array_map('lh_dec_id', explode(',', $_GET['ids'])));
    }
}

/** Encrypt the id parameters inside ONE url string — used for `Location:`
 *  redirect headers, which the output filter cannot touch (headers are not
 *  part of the buffered body). Non-id params and non-numeric values pass
 *  through untouched, so anchors (&disp=inline, #tab, …) survive. */
function lh_enc_url(string $url): string
{
    if ($url === '' || !str_contains($url, '=')) return $url;
    return (string) preg_replace_callback(
        '~(\?|&(?:amp;)?)(id|c|m|with|course|ids)=(\d+(?:,\d+)*)~i',
        static function (array $m): string {
            $parts = explode(',', $m[3]);
            foreach ($parts as $i => $p) $parts[$i] = lh_enc_id($p);
            return $m[1] . $m[2] . '=' . implode(',', $parts);
        },
        $url
    );
}

/** Outbound: rewrite every numeric id parameter in the rendered page into an
 *  encrypted token. Covers href/src/action attributes AND the same URLs inside
 *  inline JavaScript, so fetch() calls made by app.js stay encrypted too.
 *
 *  The .php extension comes off here too, so a page never hands the browser a
 *  link that then has to bounce through the 302 in .htaccess. Doing it here (not
 *  by editing every link) means one edit covers href, src, form action and the
 *  URLs inside inline script, and it lands on the JSON endpoints app.js fetches
 *  as well as the pages it links to.
 *
 *  The excluded names are the ones .htaccess deliberately keeps on .php so they
 *  cost ONE request instead of a redirect pair — polling traffic doubled by a
 *  redirect would be pure waste, and a shared host's security layer can
 *  challenge every hop. avatar.php is in that list because the top bar asks for
 *  a picture on every single page view.
 *
 *  read.php and watch.php and the other readers keep their extension: they are
 *  only ever fetched with .php printed by the page that streams them, and they
 *  are not linked from navigation. Everything reachable by clicking goes clean.
 */
function lh_url_encrypt_html(string $html): string
{
    if ($html === '') return $html;

    /* 1) the id params, exactly as before */
    if (str_contains($html, '.php')) {
        $html = (string) preg_replace_callback(
            '~(\?|&(?:amp;)?)(id|c|m|with|course|ids)=(\d+(?:,\d+)*)~i',
            static function (array $m): string {
                $parts = explode(',', $m[3]);
                foreach ($parts as $i => $p) $parts[$i] = lh_enc_id($p);
                return $m[1] . $m[2] . '=' . implode(',', $parts);
            },
            $html
        );
    }

    /* 2) the .php extension, on the links a person clicks */
    return (string) preg_replace_callback(
        '~\b(href|src|action)=(["\'])([^"\'\s>?]*?)\.php((?:[?#][^"\'\s]*)?)\2~i',
        static function (array $m): string {
            $file = lh_url_clean($m[3] . '.php', false);
            return $m[1] . '=' . $m[2] . $file . $m[4] . $m[2];
        },
        $html
    );
}

/** One page address without its .php — returns the input unchanged when this
 *  script is on the keep-the-extension list. Shared by the output filter above and
 *  by the few Location: headers, which the filter cannot reach because headers are
 *  not part of the buffered body. */
function lh_url_clean(string $file, bool $withExt = false): string
{
    $stem = preg_replace('/\.php$/i', '', $file);
    if (in_array($stem, lh_php_url_keep(), true)) return $file;   /* keeps .php */
    return $withExt ? $stem . '.php' : $stem;
}

/** The scripts whose addresses keep their .php — the no-redirect list. This is
 *  the SAME list as the RewriteCond in .htaccess rule 2, and the two have to be
 *  kept in step: a script listed here but missing there would have every .php
 *  link it printed bounced through a 302, and one listed there but missing here
 *  would have PHP print an address that Apache then redirects away.
 *
 *  What is on it, and why:
 *    • the polled / streamed endpoints (realtime, ping, presence, watch,
 *      read_progress, live_class) — hit constantly, and a redirect on each one
 *      doubles the request count;
 *    • the binary/file endpoints (avatar, asset, download, read, upload,
 *      upload_chunk, offline, download_submission) — same, and the last two
 *      push a whole course as one download;
 *    • the POST endpoints (mark_messages_read, mark_notifications_read, login,
 *      logout) — rule 2 only ever redirects GET, but naming them here keeps the
 *      address PHP prints identical to the one that served it. */
function lh_php_url_keep(): array
{
    return ['realtime', 'ping', 'presence', 'read_progress', 'watch', 'live_class',
            'avatar', 'asset', 'read', 'download', 'download_submission', 'offline',
            'upload', 'upload_chunk', 'mark_messages_read', 'mark_notifications_read',
            'login', 'logout'];
}

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

/* ---------------- appearance / theme ---------------- */

/** The design shipped as the default (see ui_theme_choices() below). */
if (!defined('UI_THEME_DEFAULT')) define('UI_THEME_DEFAULT', 'material');

/** The designs a site can pick from (Settings → Appearance).
 *  Each key maps to assets/theme-<key>.css, which is loaded after the
 *  inline base theme in header.php. Add a file + an entry here to offer
 *  another design; nothing else needs changing. */
function ui_theme_choices(): array
{
    return [
        'paper' => [
            'Paper — taped to the wall',
            'The original LearnHub look: textured wall canvas, cream paper cards, and a tilted ruled-sheet hero pinned with emerald washi tape.',
        ],
        'material' => [
            'Material — creative dashboard',
            'Light-gray canvas, white floating sidebar with a dark active pill, white cards with dark icon tiles and green data accents.',
        ],
        'console' => [
            'Console — professional dashboard',
            'Dark navigation rail, flat hairline surfaces, dense data tables and compact controls. Built for reading numbers.',
        ],
        'bright' => [
            'Fresh & friendly',
            'Cool blue-white canvas, soft rounded cards with a gradient cap, pill buttons, emerald→teal accent.',
        ],
        'emerald' => [
            'Calm studio',
            'Emerald paper canvas, hairline cards, quiet depth, straight solid buttons.',
        ],
        'minimal' => [
            'Minimal — premium & quiet',
            'Off-white canvas, a single emerald accent, hairline cards with soft lift, Inter throughout. Content first, chrome last.',
        ],
    ];
}

/** Which design this site uses — config.php can pin it with UI_THEME,
 *  otherwise the saved setting decides, otherwise the default below. */
function ui_theme(): string
{
    $pinned = defined('UI_THEME') ? strtolower(trim((string) UI_THEME)) : '';
    $key    = $pinned !== '' ? $pinned : strtolower(trim(setting_get('ui_theme', '')));
    return isset(ui_theme_choices()[$key]) ? $key : UI_THEME_DEFAULT;
}

/** Stylesheet path of the chosen design, or '' when the file is missing —
 *  the inline base theme still styles every page in that case, so a
 *  half-finished upload never leaves the site unstyled. */
function ui_theme_file(): string
{
    $f = 'assets/theme-' . ui_theme() . '.css';
    return is_file(lh_path($f)) ? $f : '';
}

/** Spacing density of the app shell. 'compact' trims the rhythm so a
 *  dashboard shows more data per screen; 'comfortable' keeps each theme's
 *  roomier defaults. Public pages are unaffected either way. */
if (!defined('UI_DENSITY_DEFAULT')) define('UI_DENSITY_DEFAULT', 'compact');

function ui_density_choices(): array
{
    return [
        'compact' => [
            'Compact',
            'Tighter spacing, smaller stat tiles, denser tables — more rows and numbers on one screen.',
        ],
        'comfortable' => [
            'Comfortable',
            'Roomier spacing and larger touch targets, as each theme ships by default.',
        ],
    ];
}

function ui_density(): string
{
    $pinned = defined('UI_DENSITY') ? strtolower(trim((string) UI_DENSITY)) : '';
    $key    = $pinned !== '' ? $pinned : strtolower(trim(setting_get('ui_density', '')));
    return isset(ui_density_choices()[$key]) ? $key : UI_DENSITY_DEFAULT;
}

/** Visual treatment for the dashboard activity line charts. */
function ui_chart_style_choices(): array
{
    return [
        'area' => [
            'Soft area',
            'Keep the visits line lightly filled for an at-a-glance view of activity volume.',
        ],
        'points' => [
            'Lines & points',
            'Use clean unfilled lines with a marker for each day to make individual values easier to compare.',
        ],
        'smooth' => [
            'Smooth curves',
            'Use flowing curved lines without area fill for a softer view of activity trends.',
        ],
        'thin' => [
            'Thin lines',
            'Use a lightweight, minimal line stroke for a quieter chart with less visual weight.',
        ],
    ];
}

function ui_chart_style(): string
{
    $style = strtolower(trim(setting_get('ui_chart_style', '')));
    return isset(ui_chart_style_choices()[$style]) ? $style : 'area';
}

/** Convert numeric chart points to a smooth cubic Bezier path. */
function dashboard_chart_smooth_path(array $points): string
{
    if (!$points) return '';
    $first = $points[0];
    $path = 'M ' . $first[0] . ' ' . $first[1];
    $count = count($points);
    for ($i = 0; $i < $count - 1; $i++) {
        $p0 = $points[max(0, $i - 1)];
        $p1 = $points[$i];
        $p2 = $points[$i + 1];
        $p3 = $points[min($count - 1, $i + 2)];
        $c1x = $p1[0] + ($p2[0] - $p0[0]) / 6;
        $c1y = $p1[1] + ($p2[1] - $p0[1]) / 6;
        $c2x = $p2[0] - ($p3[0] - $p1[0]) / 6;
        $c2y = $p2[1] - ($p3[1] - $p1[1]) / 6;
        $path .= ' C ' . round($c1x, 1) . ' ' . round($c1y, 1)
            . ', ' . round($c2x, 1) . ' ' . round($c2y, 1)
            . ', ' . $p2[0] . ' ' . $p2[1];
    }
    return $path;
}

/** Extra stylesheet for the chosen density, or '' when none applies. */
function ui_density_file(): string
{
    if (ui_density() === 'comfortable') return '';
    $f = 'assets/density-' . ui_density() . '.css';
    return is_file(lh_path($f)) ? $f : '';
}

/** Same path with a cache-busting stamp, for the <link> tag. */
function ui_density_url(): string
{
    $f = ui_density_file();
    if ($f === '') return '';
    $v = (int) @filemtime(lh_path($f));
    return $v > 0 ? $f . '?v=' . $v : $f;
}

/** Same path with a cache-busting stamp — a re-uploaded theme is picked up
 *  immediately, with no hard refresh and no stale CSS in the browser. */
function ui_theme_url(): string
{
    $f = ui_theme_file();
    if ($f === '') return '';
    $v = (int) @filemtime(lh_path($f));
    return $v > 0 ? $f . '?v=' . $v : $f;
}

/* ---------------- appearance / layout ---------------- */

/** The shell arrangement shipped as the default (see ui_layout_choices()). */
if (!defined('UI_LAYOUT_DEFAULT')) define('UI_LAYOUT_DEFAULT', 'classic');

/** How the logged-in app shell is arranged (Settings → Appearance → Layout).
 *  Each key maps to assets/layout-<key>.css, which is loaded AFTER the theme
 *  and the density layer — so a layout owns the shell geometry (sidebar and
 *  content widths, chrome height, panel treatment) and wins any tie with them
 *  on equal specificity + !important, without needing a body class.
 *  'classic' is the shell header.php already ships, so it needs no file: it
 *  is the one entry whose ui_layout_file() is '' — the same reason a missing
 *  file is never an error, the inline base layout still styles every page.
 *  Add a file + an entry here to offer another arrangement; nothing else
 *  needs changing. Every layout is scoped to >=1024px, so phones always keep
 *  the drawer + normal document scroll and never lose navigation. */
function ui_layout_choices(): array
{
    return [
        'classic' => [
            'Classic shell',
            'What LearnHub ships: fixed sidebar, sticky top bar, 1152px content column. Loads no extra stylesheet.',
        ],
        'wide' => [
            'Wide canvas',
            'Reclaims the margins on big monitors: a slimmer sidebar, a content column that grows to 1800px and stat tiles that reflow into extra columns. Best for ultrawide screens, admin tables and roomy timetables.',
        ],
        'focus' => [
            'Focused column',
            'Drops to a 1088px centred column with roomier leading and a quieter top bar, so a lesson, quiz or long form reads top-to-bottom. Best for reading and one-thing-at-a-time work.',
        ],
        'dock' => [
            'Icon dock',
            'Keeps the sidebar as a permanent 76px icon rail with hover labels — the fold buttons step aside and every page keeps the extra width. Best for laptops and users who already know their way around.',
        ],
        'topnav' => [
            'Top navigation',
            'Turns the navigation into a single row under the top bar and gives the full screen width to the page content. Best for wide monitors and hopping between a handful of pages.',
        ],
        'ledger' => [
            'Data first',
            'Slim chrome and edge-to-edge tables: zebra rows, tabular figures, uppercase column heads and tight rows. Best for admin, enrolment lists, attendance and marks.',
        ],
        'float' => [
            'Floating panels',
            'Lifts the sidebar and top bar off the screen edge into rounded panels over the theme canvas, with the content in a soft inset pane. A modern, airy frame that works with every theme.',
        ],
    ];
}

/** Which arrangement this site uses — config.php can pin it with UI_LAYOUT,
 *  otherwise the saved setting decides, otherwise the default above. */
function ui_layout(): string
{
    $pinned = defined('UI_LAYOUT') ? strtolower(trim((string) UI_LAYOUT)) : '';
    $key    = $pinned !== '' ? $pinned : strtolower(trim(setting_get('ui_layout', '')));
    return isset(ui_layout_choices()[$key]) ? $key : UI_LAYOUT_DEFAULT;
}

/** Extra stylesheet for the chosen arrangement, or '' when there is none —
 *  'classic' is the built-in shell, and a half-finished upload keeps the base
 *  layout instead of leaving the site unstyled. */
function ui_layout_file(): string
{
    $f = 'assets/layout-' . ui_layout() . '.css';
    return is_file(lh_path($f)) ? $f : '';
}

/** Same path with a cache-busting stamp, for the <link> tag. */
function ui_layout_url(): string
{
    $f = ui_layout_file();
    if ($f === '') return '';
    $v = (int) @filemtime(lh_path($f));
    return $v > 0 ? $f . '?v=' . $v : $f;
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

/* ---- profile pictures (see the profile block in this file) -------------------
 * The extension list is only a first, friendly gate: what decides whether an
 * upload is a picture is its CONTENT (getimagesize()), so a renamed .php or a
 * .svg — which can carry script — never gets through whatever it is called.
 * SVG is deliberately NOT allowed for that reason. */
const AVATAR_EXTS = ['jpg','jpeg','png','webp','gif'];
/** Stored size in px: square-cropped, big enough for the 120 px profile
 *  preview and every 36–40 px circle in the shell at 2× (retina). */
const AVATAR_SIDE = 512;
/** What a browser may POST for a picture. The browser shrinks it first when JS
 *  is on (≈60 KB), so this is the ceiling for the no-JavaScript route. */
const AVATAR_MAX_BYTES = 4 * 1024 * 1024;
/** Longest "about me" line on the profile page (VARCHAR(280) in users). */
const PROFILE_BIO_MAX = 280;

/* ---------------- database connection ---------------- */

function db_error_page(string $message): void
{
    lms_error_log('database: ' . $message);
    if (!headers_sent()) http_response_code(500);
    $local = lms_is_local();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Database error · LearnHub</title></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#f8fafc;font-family:Segoe UI,Arial,sans-serif">'
        . '<div style="max-width:560px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:32px;box-shadow:0 10px 30px rgba(0,0,0,.06)">'
        . '<h1 style="margin:0 0 10px;font-size:20px;color:#0f172a">🗄️ Database not reachable</h1>'
        /* The raw PDO text names the host, the database user and the table — that is
           for the log file, not for a visitor. A live domain only sees the notice. */
        . '<p style="margin:0 0 10px;color:#334155;font-size:14px;line-height:1.6">'
        . ($local ? e($message) : 'The site database is unreachable or not ready right now. Please try again in a moment.') . '</p>';
    if ($local) {
        echo '<p style="margin:0;color:#64748b;font-size:14px;line-height:1.6">'
            . '📍 Trying <code>' . e(DB_HOST) . '</code> — '
            . (DB_HOST === '127.0.0.1' || DB_HOST === 'localhost'
                ? 'Start <b>MySQL</b> in the XAMPP Control Panel, then reload this page. Connection settings live in <code>config.php</code> (LOCAL block) / top of <code>lib.php</code>.'
                : 'Re-upload the newest <code>config.php</code> and <code>lib.php</code> to the site, and confirm the PROD block matches your hosting control panel\'s "MySQL Databases" values.')
            . '</p>';
    } else {
        echo '<p style="margin:0;color:#64748b;font-size:14px;line-height:1.6">'
            . 'The reason was written to <code>data/error.log</code> on the server — an administrator can read it there.</p>';
    }
    echo '</div></body></html>';
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
    /* DB_NAME is interpolated into `CREATE DATABASE` / `USE` below, and SQL
       identifiers can never be bound as parameters (placeholders only work for
       values). config.php is a server-side file, but validate it anyway so a
       tampered config can never turn into an injection point. */
    if (!preg_match('/^[A-Za-z0-9_$]+$/', DB_NAME)) {
        db_error_page('DB_NAME contains characters that are not allowed in a MySQL database name.');
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
    try {
        db_ensure_schema($pdo);
        db_migrate_legacy_json($pdo);
        db_seed_if_empty($pdo);
        db_migrate_course_folders($pdo);
    } catch (Throwable $e) {
        /* Schema/seed problems (missing DDL privileges, hosting quota, a
           partially-created database, …) must never surface as the bare
           "Something broke" page: route them through the same channel as
           connection failures so the exact reason lands in data/error.log
           and the visitor gets the database notice page instead. */
        db_error_page('Database setup failed while preparing tables/seed data. (' . $e->getMessage() . ')');
    }
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
            bio VARCHAR(280) NOT NULL DEFAULT '',
            avatar VARCHAR(255) NULL,
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
        "CREATE TABLE IF NOT EXISTS course_folders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            parent_id INT UNSIGNED NULL,
            name VARCHAR(120) NOT NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_course_folders_course (course_id),
            INDEX idx_course_folders_parent (parent_id),
            CONSTRAINT fk_course_folders_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
            CONSTRAINT fk_course_folders_parent FOREIGN KEY (parent_id) REFERENCES course_folders (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS materials (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            folder_id INT UNSIGNED NULL,
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
        "CREATE TABLE IF NOT EXISTS course_trainees (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            saved_name VARCHAR(120) NOT NULL,
            archive_group VARCHAR(120) NOT NULL DEFAULT '',
            removed_by INT UNSIGNED NOT NULL,
            removed_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_course_trainees_course (course_id, removed_at),
            CONSTRAINT fk_course_trainees_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
            CONSTRAINT fk_course_trainees_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT fk_course_trainees_removed_by FOREIGN KEY (removed_by) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        /* The class timetable. One row is one slot and it is one of two things:
           a weekly rule (repeat_mode 'weekly' + weekday, 0 = Sunday … 6 =
           Saturday, as PHP's date('w')) or a one-off entry on an exact date
           (repeat_mode 'once' + sched_date) — an exam, a field trip, a deadline.
           DATE/TIME columns rather than the usual epoch seconds on purpose: a
           timetable is a wall-clock promise ("Mondays at 9"), not an instant, so
           it must read the same in every timezone and never shift over DST. */
        "CREATE TABLE IF NOT EXISTS schedules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL DEFAULT '',
            kind ENUM('class','exam','activity','deadline') NOT NULL DEFAULT 'class',
            repeat_mode ENUM('weekly','once') NOT NULL DEFAULT 'weekly',
            weekday TINYINT UNSIGNED NULL,
            sched_date DATE NULL,
            start_time TIME NOT NULL DEFAULT '08:00:00',
            end_time TIME NULL,
            place VARCHAR(160) NOT NULL DEFAULT '',
            notes VARCHAR(500) NOT NULL DEFAULT '',
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            updated_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_sched_course (course_id),
            INDEX idx_sched_teacher (teacher_id),
            INDEX idx_sched_day (repeat_mode, weekday),
            INDEX idx_sched_date (sched_date),
            CONSTRAINT fk_sched_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
            CONSTRAINT fk_sched_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
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
        "CREATE TABLE IF NOT EXISTS live_classes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            host_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL DEFAULT 'Live class',
            status ENUM('live','ended') NOT NULL DEFAULT 'live',
            started_at INT UNSIGNED NOT NULL DEFAULT 0,
            ended_at INT UNSIGNED NULL,
            INDEX idx_live_classes_course (course_id),
            INDEX idx_live_classes_host (host_id),
            CONSTRAINT fk_lc_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
            CONSTRAINT fk_lc_host FOREIGN KEY (host_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS live_class_state (
            class_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            joined_at INT UNSIGNED NOT NULL DEFAULT 0,
            last_seen INT UNSIGNED NOT NULL DEFAULT 0,
            hand_raised TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (class_id, user_id),
            CONSTRAINT fk_lcs_class FOREIGN KEY (class_id) REFERENCES live_classes (id) ON DELETE CASCADE,
            CONSTRAINT fk_lcs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
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
            material_id INT UNSIGNED NULL UNIQUE,
            folder_id INT UNSIGNED NULL,
            title VARCHAR(120) NOT NULL,
            pass_score TINYINT UNSIGNED NOT NULL DEFAULT 60,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            CONSTRAINT fk_quiz_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE,
            CONSTRAINT fk_quiz_folder FOREIGN KEY (folder_id) REFERENCES course_folders (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS quiz_questions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quiz_id INT UNSIGNED NOT NULL,
            prompt VARCHAR(500) NOT NULL,
            options TEXT NOT NULL,
            correct TINYINT UNSIGNED NOT NULL DEFAULT 0,
            option_explanations TEXT NULL,
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
        /* ---------------- assignments & submissions ----------------
           The one thing the app could not do before: a teacher sets work with a
           due date, a student hands something back, the teacher grades it in
           place. `due_at` is NULL = no deadline. A student keeps ONE current
           submission per assignment (uq_sub_once) and may resubmit until it is
           graded — the row is updated, so there is never a pile of drafts and
           the teacher's list is just "who is done, who is not". */
        "CREATE TABLE IF NOT EXISTS assignments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NOT NULL,
            title VARCHAR(160) NOT NULL,
            instructions TEXT NOT NULL,
            due_at INT UNSIGNED NULL DEFAULT NULL,
            max_points INT UNSIGNED NOT NULL DEFAULT 100,
            allow_late TINYINT(1) NOT NULL DEFAULT 1,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_assign_course (course_id),
            INDEX idx_assign_due (due_at),
            CONSTRAINT fk_assign_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
            CONSTRAINT fk_assign_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS submissions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            assignment_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            body TEXT NOT NULL,
            filename VARCHAR(255) NOT NULL DEFAULT '',
            orig_name VARCHAR(255) NOT NULL DEFAULT '',
            mime VARCHAR(100) NOT NULL DEFAULT '',
            size INT UNSIGNED NOT NULL DEFAULT 0,
            submitted_at INT UNSIGNED NOT NULL DEFAULT 0,
            grade INT NULL DEFAULT NULL,
            feedback TEXT NOT NULL,
            graded_at INT UNSIGNED NOT NULL DEFAULT 0,
            UNIQUE KEY uq_sub_once (assignment_id, user_id),
            INDEX idx_sub_assign (assignment_id),
            INDEX idx_sub_user (user_id),
            CONSTRAINT fk_sub_assign FOREIGN KEY (assignment_id) REFERENCES assignments (id) ON DELETE CASCADE,
            CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        /* ---------------- course announcements ----------------
           A teacher posts once, every enrolled student gets a notification row
           (fan-out at post time, so the bell is instant and the list needs no
           join to know who has read it). `pinned` keeps one at the top. */
        "CREATE TABLE IF NOT EXISTS announcements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id INT UNSIGNED NOT NULL,
            teacher_id INT UNSIGNED NOT NULL,
            title VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            pinned TINYINT(1) NOT NULL DEFAULT 0,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_ann_course (course_id, pinned, created_at),
            CONSTRAINT fk_ann_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
            CONSTRAINT fk_ann_teacher FOREIGN KEY (teacher_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        /* ---------------- per-lesson discussion ----------------
           Same shape as messages, but keyed to a lesson and open to everyone in
           the course. parent_id = 0 is a top-level post; a reply points at its
           parent (one level, the way a real lesson thread reads). */
        "CREATE TABLE IF NOT EXISTS lesson_posts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            material_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            parent_id INT UNSIGNED NOT NULL DEFAULT 0,
            body TEXT NOT NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            INDEX idx_post_material (material_id, created_at),
            CONSTRAINT fk_post_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE,
            CONSTRAINT fk_post_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS lesson_typing (
            material_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            typed_at INT UNSIGNED NOT NULL,
            PRIMARY KEY (material_id, user_id),
            INDEX idx_lesson_typing_time (typed_at),
            CONSTRAINT fk_lesson_typing_material FOREIGN KEY (material_id) REFERENCES materials (id) ON DELETE CASCADE,
            CONSTRAINT fk_lesson_typing_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS lesson_post_reactions (
            post_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            reaction VARCHAR(16) NOT NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (post_id, user_id),
            INDEX idx_post_reactions_user (user_id),
            CONSTRAINT fk_post_reactions_post FOREIGN KEY (post_id) REFERENCES lesson_posts (id) ON DELETE CASCADE,
            CONSTRAINT fk_post_reactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS announcement_reactions (
            announcement_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            reaction VARCHAR(16) NOT NULL,
            created_at INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (announcement_id, user_id),
            INDEX idx_announcement_reactions_user (user_id),
            CONSTRAINT fk_announcement_reactions_announcement FOREIGN KEY (announcement_id) REFERENCES announcements (id) ON DELETE CASCADE,
            CONSTRAINT fk_announcement_reactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    db_schema_post_migrate($pdo);
    db_migrate_quiz_results($pdo);
}

/** One-time-per-request post-schema work: widen the role ENUM and add the
 *  profile columns on old databases, then guarantee a main admin exists. */
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
    /* accounts created before the profile page existed have no profile columns —
       add them the same way, one cheap information_schema lookup per column.
       `bio` is the public "about me" line, `avatar` the stored picture name
       inside uploads/avatars/ (never a path, so it can never point outside). */
    db_ensure_column($pdo, 'users', 'bio', "ALTER TABLE users ADD COLUMN bio VARCHAR(280) NOT NULL DEFAULT '' AFTER role");
    db_ensure_column($pdo, 'users', 'avatar', 'ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER bio');
    /* Lessons used to be ordered by id alone, with no way to reorder them at all.
       sort_order lets a teacher move a batch (bulk.php) without renumbering rows;
       NULL means "just keep the id order", so existing courses are untouched. */
    db_ensure_column($pdo, 'materials', 'sort_order', 'ALTER TABLE materials ADD COLUMN sort_order INT NULL DEFAULT NULL AFTER size');
    db_ensure_column($pdo, 'course_trainees', 'archive_group', "ALTER TABLE course_trainees ADD COLUMN archive_group VARCHAR(120) NOT NULL DEFAULT '' AFTER saved_name");
    db_ensure_column($pdo, 'quiz_questions', 'option_explanations', 'ALTER TABLE quiz_questions ADD COLUMN option_explanations TEXT NULL AFTER correct');
    db_drop_column_if_exists($pdo, 'quiz_questions', 'explanation');
    admin_ensure($pdo);
}

/** Add course folders to older databases and keep all existing lessons together. */
function db_migrate_course_folders(PDO $pdo): void
{
    db_ensure_column($pdo, 'materials', 'folder_id', 'ALTER TABLE materials ADD COLUMN folder_id INT UNSIGNED NULL AFTER course_id');
    db_ensure_column($pdo, 'quizzes', 'folder_id', 'ALTER TABLE quizzes ADD COLUMN folder_id INT UNSIGNED NULL AFTER material_id');
    $hasColumn = static function (string $table, string $column) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        return (int) $st->fetchColumn() > 0;
    };
    if (!$hasColumn('materials', 'folder_id') || !$hasColumn('quizzes', 'folder_id')) {
        throw new RuntimeException('The course-folder database columns could not be created.');
    }
    $nullable = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quizzes' AND COLUMN_NAME = 'material_id'")
        ->fetchColumn();
    if (strtoupper((string) $nullable) !== 'YES') {
        $pdo->exec('ALTER TABLE quizzes MODIFY material_id INT UNSIGNED NULL');
    }

    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
        $st->execute([$table, $index]);
        return (int) $st->fetchColumn() > 0;
    };
    if (!$indexExists('materials', 'idx_materials_folder')) {
        $pdo->exec('ALTER TABLE materials ADD INDEX idx_materials_folder (folder_id)');
    }
    if (!$indexExists('quizzes', 'uq_quizzes_folder')) {
        $pdo->exec('ALTER TABLE quizzes ADD UNIQUE KEY uq_quizzes_folder (folder_id)');
    }

    $addRoots = $pdo->prepare('INSERT INTO course_folders (course_id, parent_id, name, created_at)
                               SELECT c.id, NULL, ?, ? FROM courses c
                               WHERE NOT EXISTS (SELECT 1 FROM course_folders f WHERE f.course_id = c.id AND f.parent_id IS NULL)');
    $addRoots->execute(['Main folder', time()]);
    $pdo->exec('UPDATE course_folders SET parent_id = NULL WHERE parent_id IS NOT NULL');
    $pdo->exec('UPDATE materials m
                LEFT JOIN course_folders assigned ON assigned.id = m.folder_id AND assigned.course_id = m.course_id
                SET m.folder_id = NULL
                WHERE m.folder_id IS NOT NULL AND assigned.id IS NULL');
    $pdo->exec('UPDATE materials m
                JOIN course_folders f ON f.course_id = m.course_id AND f.parent_id IS NULL
                SET m.folder_id = f.id
                WHERE m.folder_id IS NULL');
}

/** Run one ADD COLUMN when that column is genuinely missing (old installs). */
function db_ensure_column(PDO $pdo, string $table, string $column, string $sql): void
{
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$table, $column]);
        if ((int) $st->fetchColumn() > 0) return;
        $pdo->exec($sql);
    } catch (Throwable $e) { /* best-effort; fresh installs already carry the column */ }
}

/** Remove a retired column from existing databases when it is present. */
function db_drop_column_if_exists(PDO $pdo, string $table, string $column): void
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$table, $column]);
    if ((int) $st->fetchColumn() > 0) {
        $pdo->exec('ALTER TABLE `' . str_replace('`', '``', $table) . '` DROP COLUMN `' . str_replace('`', '``', $column) . '`');
    }
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

    // Demo timetable: two weekly classes plus a once-only midterm, so the
    // Schedule page (and the dashboard card) have something to show out of the box.
    $addSlot = $pdo->prepare('INSERT INTO schedules (course_id, teacher_id, title, kind, repeat_mode, weekday, sched_date,
                                                     start_time, end_time, place, notes, created_at, updated_at)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $addSlot->execute([$course1, $teacherId, 'HTML & CSS lab', 'class', 'weekly', 1, null,
        '09:00:00', '11:00:00', 'Computer Lab 1', 'Bring your laptop — we build the page live.', $now, $now]);
    $addSlot->execute([$course1, $teacherId, 'JavaScript workshop', 'class', 'weekly', 3, null,
        '09:00:00', '11:00:00', 'Computer Lab 1', '', $now, $now]);
    $addSlot->execute([$course2, $teacherId, 'Design studio', 'class', 'weekly', 5, null,
        '13:00:00', '15:00:00', 'Room 204', 'Bring your colour swatches.', $now, $now]);
    $addSlot->execute([$course1, $teacherId, 'Midterm exam', 'exam', 'once', null,
        date('Y-m-d', strtotime('next monday')), '09:00:00', '12:00:00', 'Computer Lab 1',
        'Covers lessons 1–4.', $now, $now]);
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

function main_course_folder(int $courseId): ?array
{
    $st = db()->prepare('SELECT * FROM course_folders WHERE course_id = ? AND parent_id IS NULL ORDER BY id LIMIT 1');
    $st->execute([$courseId]);
    return $st->fetch() ?: null;
}

function course_folder_row(int $courseId, int $folderId): ?array
{
    $st = db()->prepare('SELECT * FROM course_folders WHERE id = ? AND course_id = ? LIMIT 1');
    $st->execute([$folderId, $courseId]);
    return $st->fetch() ?: null;
}

function course_folders(int $courseId): array
{
    $st = db()->prepare('SELECT * FROM course_folders WHERE course_id = ? ORDER BY parent_id IS NOT NULL, name, id');
    $st->execute([$courseId]);
    return $st->fetchAll();
}

/** Ensure a course has its main folder and put any unassigned lessons inside it. */
function ensure_course_main_folder(int $courseId): int
{
    $st = db()->prepare('SELECT id FROM course_folders WHERE course_id = ? AND parent_id IS NULL ORDER BY id LIMIT 1');
    $st->execute([$courseId]);
    $folderId = (int) ($st->fetchColumn() ?: 0);
    if ($folderId <= 0) {
        db()->prepare('INSERT INTO course_folders (course_id, parent_id, name, created_at) VALUES (?,NULL,?,?)')
            ->execute([$courseId, 'Main folder', time()]);
        $folderId = (int) db()->lastInsertId();
    }
    db()->prepare('UPDATE materials m
                   LEFT JOIN course_folders assigned ON assigned.id = m.folder_id AND assigned.course_id = m.course_id
                   SET m.folder_id = ?
                   WHERE m.course_id = ? AND (m.folder_id IS NULL OR assigned.id IS NULL)')
        ->execute([$folderId, $courseId]);
    return $folderId;
}

function create_course_folder(int $courseId, string $name): int
{
    $name = cut(trim($name), 120);
    if ($name === '') throw new RuntimeException('Give the folder a name.');
    db()->prepare('INSERT INTO course_folders (course_id, parent_id, name, created_at) VALUES (?,NULL,?,?)')
        ->execute([$courseId, $name, time()]);
    return (int) db()->lastInsertId();
}

function rename_course_folder(int $courseId, int $folderId, string $name): bool
{
    if (!course_folder_row($courseId, $folderId)) return false;
    $name = cut(trim($name), 120);
    if ($name === '') throw new RuntimeException('Give the folder a name.');
    db()->prepare('UPDATE course_folders SET name = ? WHERE id = ? AND course_id = ?')
        ->execute([$name, $folderId, $courseId]);
    return true;
}

/** Delete a course folder, its lessons, quizzes, and return uploaded files for cleanup. */
function delete_course_folder(int $courseId, int $folderId): ?array
{
    if (!course_folder_row($courseId, $folderId)) return null;

    $children = db()->prepare('SELECT id FROM course_folders WHERE course_id = ? AND parent_id = ?');
    $children->execute([$courseId, $folderId]);
    $folderIds = array_merge([$folderId], array_map('intval', $children->fetchAll(PDO::FETCH_COLUMN)));
    $in = implode(',', array_fill(0, count($folderIds), '?'));
    $params = array_merge([$courseId], $folderIds);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $files = $pdo->prepare("SELECT filename FROM materials
                                WHERE course_id = ? AND folder_id IN ($in)
                                  AND type IN ('file','video') AND filename IS NOT NULL");
        $files->execute($params);
        $filenames = array_values(array_filter(array_map('strval', $files->fetchAll(PDO::FETCH_COLUMN))));

        $pdo->prepare("DELETE FROM materials WHERE course_id = ? AND folder_id IN ($in)")->execute($params);
        $pdo->prepare("DELETE FROM course_folders WHERE course_id = ? AND id IN ($in)")->execute($params);
        $pdo->commit();
        return $filenames;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Folder completion is based only on lessons assigned directly to that folder. */
function course_folder_progress(int $folderId, int $userId): array
{
    $st = db()->prepare('SELECT id FROM course_folders WHERE id = ? LIMIT 1');
    $st->execute([$folderId]);
    $folder = $st->fetch();
    if (!$folder) return ['total' => 0, 'done' => 0, 'pct' => 0];

    $counts = db()->prepare('SELECT COUNT(*) AS total, SUM(p.material_id IS NOT NULL) AS done
                             FROM materials m LEFT JOIN progress p ON p.material_id = m.id AND p.user_id = ?
                             WHERE m.folder_id = ?');
    $counts->execute([$userId, $folderId]);
    $row = $counts->fetch() ?: ['total' => 0, 'done' => 0];
    $total = (int) $row['total'];
    $done = (int) $row['done'];
    $pct = $total === 0 ? 0 : ($done >= $total ? 100 : min(99, (int) round(100 * $done / $total)));
    return ['total' => $total, 'done' => $done, 'pct' => $pct];
}

/** Batch folder progress for the course page; each folder counts only its own lessons. */
function course_folder_progress_map(int $courseId, int $userId): array
{
    $st = db()->prepare('SELECT f.id, COUNT(DISTINCT m.id) AS total,
                                COUNT(DISTINCT CASE WHEN p.material_id IS NOT NULL THEN m.id END) AS done
                         FROM course_folders f
                         LEFT JOIN materials m ON m.folder_id = f.id
                         LEFT JOIN progress p ON p.material_id = m.id AND p.user_id = ?
                         WHERE f.course_id = ?
                         GROUP BY f.id');
    $st->execute([$userId, $courseId]);
    $out = [];
    foreach ($st->fetchAll() as $row) {
        $total = (int) $row['total'];
        $done = (int) $row['done'];
        $pct = $total === 0 ? 0 : ($done >= $total ? 100 : min(99, (int) round(100 * $done / $total)));
        $out[(int) $row['id']] = ['total' => $total, 'done' => $done, 'pct' => $pct];
    }
    return $out;
}

/** All courses with nested materials / enrolled ids / progress map (same shape the pages already use). */
function load_courses(): array
{
    $courses = db()->query('SELECT c.*, u.name AS teacher_name FROM courses c JOIN users u ON u.id = c.teacher_id ORDER BY c.id')->fetchAll();
    if (!$courses) return [];

    $byId = [];
    foreach ($courses as &$c) {
        $c['materials'] = [];
        $c['folders'] = [];
        $c['enrolled'] = [];
        $c['progress'] = [];
        $byId[(int) $c['id']] = &$c;
    }
    unset($c);

    foreach (db()->query('SELECT * FROM course_folders ORDER BY parent_id IS NOT NULL, name, id')->fetchAll() as $folder) {
        if (isset($byId[(int) $folder['course_id']])) $byId[(int) $folder['course_id']]['folders'][] = $folder;
    }

    /* Lessons a teacher has never reordered (sort_order NULL) keep the old id
       order; once any lesson in the course is ordered, that column decides. This
       is one place, so the course page, the offline bundle and bulk.php all
       agree on what "the order of the lessons" means. */
    foreach (db()->query('SELECT * FROM materials ORDER BY id')->fetchAll() as $m) {
        if (!isset($byId[(int) $m['course_id']])) continue;
        $byId[(int) $m['course_id']]['materials'][] = $m;
    }
    foreach ($byId as $cid => &$course) {
        $ordered = array_filter($course['materials'], fn ($m) => $m['sort_order'] !== null);
        if (!$ordered) continue;
        usort($course['materials'], function ($a, $b) {
            $ao = $a['sort_order'] ?? PHP_INT_MAX;
            $bo = $b['sort_order'] ?? PHP_INT_MAX;
            return $ao === $bo ? $a['id'] <=> $b['id'] : $ao <=> $bo;
        });
    }
    unset($course);
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
    if (!$u) {
        /* Remember where they were heading (GET only — a POSTed form cannot be
           replayed) so a shared link or a bookmark still lands there after login. */
        $next = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? login_next_url() : '';
        header('Location: login.php' . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
        exit;
    }
    return $u;
}

/** The current request as a LOCAL path (e.g. "/LMS/live_join.php?course=…"),
 *  used to build `?next=`. '' when the request URI is not a local path. */
function login_next_url(): string
{
    $uri = str_replace(["\r", "\n"], '', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//')) return '';
    return $uri;
}

/** Where to continue after logging in: `next` from the URL or the form, but ONLY
 *  when it is a local path. Anything absolute, protocol-relative ("//evil.com")
 *  or containing a backslash is ignored, so the login page can never be turned
 *  into an open redirect. */
function login_next_path(string $default = 'dashboard.php'): string
{
    $n = trim((string) ($_GET['next'] ?? $_POST['next'] ?? ''));
    if ($n === '' || $n[0] !== '/' || str_starts_with($n, '//') || str_contains($n, '\\')) return $default;
    return $n;
}

/** True when the login page was reached from somewhere specific — used to show
 *  "log in to continue to your live class" instead of the generic greeting. */
function login_has_next(): bool
{
    return login_next_path('') !== '';
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
        http_response_code(403);   /* 419 is not in this PHP build's status table — it degrades to 500 */
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

/**
 * Partially hide an e-mail address before showing it to somebody else, so the
 * roster / attendance screens never disclose a full address:
 *   "maria.garcia@school.edu"  ->  "m***@s***.edu"
 * Only the first character of the local part and of the domain survive, the TLD
 * is kept so it still reads as an address, and the mask is a fixed "***" so the
 * real length is not revealed either.  The stored address is never modified —
 * masking happens at the point of display only.
 */
function mask_email(?string $email): string
{
    $email = trim((string) $email);
    if ($email === '') return '';

    $at = strrpos($email, '@');
    if ($at === false || $at === 0 || $at === strlen($email) - 1) {
        return cut($email, 1) . '***';   /* not a usable address — still never show it whole */
    }

    $local  = substr($email, 0, $at);
    $domain = substr($email, $at + 1);

    $tld = '';
    $dot = strrpos($domain, '.');
    if ($dot !== false && $dot > 0 && $dot < strlen($domain) - 1) {
        $tld    = substr($domain, $dot); /* ".edu" — kept so it still looks like an address */
        $domain = substr($domain, 0, $dot);
    }

    return cut($local, 1) . '***@' . cut($domain, 1) . '***' . $tld;
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

/** Parse a php.ini shorthand size ("512M", "4G", "2048K", "1048576") into bytes.
 *  ini_parse_quantity() exists only in PHP >= 8.2, so this stays portable. */
function ini_bytes(string $val): int
{
    $val = trim($val);
    if ($val === '') return 0;
    $n = (int) $val;
    return match (strtolower(substr($val, -1))) {
        'g' => $n * 1024 * 1024 * 1024,
        'm' => $n * 1024 * 1024,
        'k' => $n * 1024,
        default => $n,
    };
}

/** The REAL ceiling for one uploaded file, in bytes.
 *  The smallest of three independent limits wins — the app's own cap and the
 *  two PHP settings that silently abort bigger uploads:
 *    • upload_max_filesize — per-file cap (a multi-GB video dies here first)
 *    • post_max_size       — whole-request cap
 *  Reading them at runtime means the UI can show teachers the truth for the
 *  machine the app is actually running on, instead of a hard-coded number. */
function max_upload_bytes(): int
{
    $caps = [MAX_UPLOAD_BYTES];
    foreach (['upload_max_filesize', 'post_max_size'] as $k) {
        $v = ini_bytes((string) ini_get($k));
        if ($v > 0) $caps[] = $v;          // 0 or -1 means "no limit set here"
    }
    return min($caps);
}

/** Human-readable form of max_upload_bytes() — e.g. "4 GB". */
function max_upload_label(): string
{
    return format_size(max_upload_bytes());
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

/** May this user see a course's lessons (the list AND its counts)?
 *  The owning teacher: always. Any OTHER teacher: never — lessons are private
 *  to the teacher who uploaded them and their enrolled students (teachers can
 *  never enroll, so ownership is the only teacher path). Students, admins and
 *  everyone else keep the previous behaviour: enrolled students open the
 *  lessons, everybody else sees the invite-only lock screen. */
function can_view_lessons(array $course, array $user): bool
{
    if (($user['role'] ?? '') === 'teacher') {
        return (int) ($course['teacher_id'] ?? 0) === (int) ($user['id'] ?? 0);
    }
    return true;
}

/* ---------------- password policy ---------------- */

/** The one password rule for every place a password is set or changed
 *  (registration, e-mail reset, admin panel). Strong = at least 8 characters
 *  with an uppercase letter, a lowercase letter and a number. Returns the
 *  user-facing error message, or null when the password is acceptable. */
function password_strength_error(string $pw): ?string
{
    if (strlen($pw) < 8) return 'Password must be at least 8 characters long.';
    if (!preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Password must mix an uppercase letter, a lowercase letter and a number.';
    }
    return null;
}

/* ---------------- Cloudflare Turnstile (optional human check) ---------------- */

/* Keys come from config.php (wins) or from Settings → Registration (stored in the
   database). With no keys the registration form falls back to the built-in
   question below, so the site never depends on an external service. */
if (!defined('TURNSTILE_SITE_KEY'))   define('TURNSTILE_SITE_KEY', '');
if (!defined('TURNSTILE_SECRET_KEY')) define('TURNSTILE_SECRET_KEY', '');

const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

/** The effective Turnstile keys (constant beats saved setting, like every other
 *  integration in this app). */
function turnstile_cfg(): array
{
    return [
        'site'   => TURNSTILE_SITE_KEY !== '' ? trim((string) TURNSTILE_SITE_KEY) : trim(setting_get('turnstile_site_key', '')),
        'secret' => TURNSTILE_SECRET_KEY !== '' ? trim((string) TURNSTILE_SECRET_KEY) : trim(setting_get('turnstile_secret_key', '')),
    ];
}

/** Both halves configured? Only then does the widget replace the built-in question. */
function turnstile_ready(): bool
{
    $c = turnstile_cfg();
    return $c['site'] !== '' && $c['secret'] !== '';
}

/** Ask Cloudflare whether a widget token is genuine. Returns null when the visitor
 *  passes, or a user-facing message. Never throws. A network failure fails CLOSED
 *  (without Cloudflare we cannot tell a human from a robot) but says so plainly,
 *  and a wrong secret key is reported separately so the admin can fix it. */
function turnstile_verify(string $token, string $ip = ''): ?string
{
    if (!turnstile_ready()) return null;                    /* not configured: nothing to check */
    if (trim($token) === '') return 'Please complete the Cloudflare check below.';

    $c = turnstile_cfg();
    $r = lh_http(TURNSTILE_VERIFY_URL, 'POST', ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query(['secret' => $c['secret'], 'response' => $token, 'remoteip' => $ip]), 8);

    /* Cloudflare answers 400 WITH a JSON body when the secret key is malformed, so
       judge the body first: only a missing/unparseable one means the service was
       truly unreachable (and this then fails closed). */
    $j = json_decode((string) $r['body'], true);
    if (!is_array($j)) {
        return ($r['code'] >= 200 && $r['code'] < 300)
            ? 'The human check gave an unexpected answer — please try again.'
            : 'We could not reach the human-check service — please try again in a moment.';
    }
    if (!empty($j['success'])) return null;

    $codes = implode(',', array_map('strval', (array) ($j['error-codes'] ?? [])));
    if (strpos($codes, 'invalid-input-secret') !== false || strpos($codes, 'missing-input-secret') !== false) {
        return 'The human check is misconfigured (wrong secret key) — please tell the administrator.';
    }
    return 'The human check did not pass — please tick the box and try again.';
}

/** The Cloudflare widget for a form: the official script, the box, and one line
 *  of explanation — wrapped so the form treats it as a single field. Drop it in
 *  just above the submit button. Returns '' while Turnstile is not configured,
 *  so the form renders exactly as it always did. Only the PUBLIC site key is
 *  printed here; the secret never leaves this server. */
function turnstile_field(): string
{
    if (!turnstile_ready()) return '';
    return '<div>'
        . '<label class="block text-sm font-medium text-slate-700">Quick human check</label>'
        . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'
        . '<div class="cf-turnstile mt-1" data-sitekey="' . e(turnstile_cfg()['site']) . '" data-theme="light"></div>'
        . '<p class="mt-1 text-xs text-slate-400">Protected by Cloudflare Turnstile — it usually checks you silently, with no puzzle to solve.</p>'
        . '</div>';
}

/** The show/hide eye for ONE password field. It sits INSIDE the input box, so
 *  the caller wraps the input in <div class="lh-pw mt-1"> and prints this right
 *  after the input — shell.css positions it, and footer.php's single delegated
 *  handler does the toggling (keys off aria-pressed, so the icon and the input
 *  type stay in step). type="button" on purpose: the eye must never submit the
 *  form it lives in, and nothing here touches the CSRF field or the inputs. */
function password_toggle_btn(string $inputId): string
{
    $eye = '<svg class="lh-pw-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    $eyeOff = '<svg class="lh-pw-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>'
        . '<path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>'
        . '<path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>'
        . '<line x1="1" y1="1" x2="23" y2="23"/></svg>';
    return '<button type="button" class="lh-pw-btn" data-lh-pw-toggle="' . e($inputId) . '"'
        . ' aria-pressed="false" aria-label="Show password" title="Show password">'
        . $eye . $eyeOff . '</button>';
}

/* ---------------- registration: human check ---------------- */

const REG_HUMAN_TTL = 1800;         /* a question stays answerable for 30 minutes */
const REG_HUMAN_MIN_SECONDS = 2;    /* nobody reads a sentence in under two seconds */
const REG_HUMAN_MAX_TRIES = 8;      /* wrong answers from one session before the lock */
const REG_HUMAN_LOCK_SECONDS = 300; /* how long that lock lasts */

/**
 * A self-contained human check for the registration form: no third-party
 * service and no API keys, so it works on shared hosting (and offline).
 *
 * The question travels to the browser; the ANSWER never does — it is kept in
 * the session, single-use and short-lived. Two invisible traps ride along: a
 * honeypot field and a "answered too fast" timing check.
 *
 * Honest limits: a targeted bot could still do the arithmetic, so this stops
 * form spam and code-probing scripts, not a determined attacker. The real
 * barrier for this app stays the one-time invitation / access code.
 *
 * Cloudflare Turnstile takes over automatically once its keys are saved
 * (Settings → Registration) or defined in config.php — see the turnstile_*()
 * block above. This question is then just the fallback for installs without keys.
 */

/** Issue what the form shows for this render: the Cloudflare widget when keys are
 *  configured, otherwise the built-in question (plus whether this session already
 *  passed, so the question can be hidden). */
function reg_human_new(): array
{
    /* Turnstile draws its own widget — there is no question to keep in the session */
    if (turnstile_ready()) {
        return ['q' => '', 'pass' => false, 'turnstile' => true, 'site' => turnstile_cfg()['site']];
    }

    $ch = $_SESSION['reg_human'] ?? null;
    if (is_array($ch) && isset($ch['q'], $ch['a'], $ch['t'])
        && (time() - (int) $ch['t']) <= REG_HUMAN_TTL) {
        return ['q' => (string) $ch['q'], 'pass' => reg_human_pass(), 'turnstile' => false, 'site' => ''];
    }

    $a = mt_rand(3, 12);
    $b = mt_rand(2, 9);
    switch (mt_rand(1, 4)) {
        case 1:
            $q = "A class has $a students and $b more join. How many students are there now?";
            $ans = $a + $b;
            break;
        case 2:
            $m = mt_rand(2, 6);
            $q = "One lesson lasts $m minutes and you watch $b lessons. How many minutes is that in total?";
            $ans = $m * $b;
            break;
        case 3:
            $c = mt_rand(6, 15);
            $d = mt_rand(2, 5);
            $q = "A course has $c lessons and you have finished $d of them. How many lessons are left?";
            $ans = $c - $d;
            break;
        default:
            $m = mt_rand(2, 8);
            $q = "A room has $a rows of $m seats. How many seats is that in total?";
            $ans = $a * $m;
            break;
    }
    /* the number appears in the question, the answer is computed here only —
       $ans is never rendered, and array keys stay short so a bot cannot read
       the answer out of the session cookie (the session is server-side) */
    $_SESSION['reg_human'] = ['q' => $q, 'a' => $ans, 't' => time()];
    return ['q' => $q, 'pass' => reg_human_pass(), 'turnstile' => false, 'site' => ''];
}

/** True when this session already answered a question correctly. The pass is
 *  spent by reg_human_consume() when an account is really created, so a
 *  mistyped invitation code does not force a second sum. */
function reg_human_pass(): bool
{
    $t = (int) ($_SESSION['reg_human_ok'] ?? 0);
    return $t > 0 && (time() - $t) <= REG_HUMAN_TTL;
}

/** Spend the pass (called right before an account is created). */
function reg_human_consume(): void
{
    unset($_SESSION['reg_human_ok'], $_SESSION['reg_human']);
}

/**
 * Check a registration POST. Returns a user-facing error message, or null when
 * the visitor looks human. Call it FIRST: while it fails, the caller should not
 * look up invitation codes or touch the database.
 */
function reg_human_check(array $post): ?string
{
    /* trap 1 — the honeypot. Only a script fills a field it cannot see, so a
       filled one is rejected without saying why. */
    if (trim((string) ($post['lh_website'] ?? '')) !== '') {
        return 'Registration failed — please try again.';
    }

    /* Cloudflare Turnstile, when keys are configured. Each submit carries a fresh
       single-use token, because the widget re-issues one on every render. */
    if (turnstile_ready()) {
        return turnstile_verify((string) ($post['cf-turnstile-response'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    /* already answered (form re-submitted because another field was wrong) */
    if (reg_human_pass()) return null;

    /* too many wrong answers from this session: cool off */
    $locked = (int) ($_SESSION['reg_human_locked'] ?? 0);
    if ($locked > 0 && (time() - $locked) < REG_HUMAN_LOCK_SECONDS) {
        return 'Too many wrong answers — please wait a few minutes and try again.';
    }

    $ch = $_SESSION['reg_human'] ?? null;
    if (!is_array($ch) || !isset($ch['a'], $ch['t'])) {
        return 'Please reload the page and answer the question again.';
    }
    if (time() - (int) $ch['t'] > REG_HUMAN_TTL) {
        unset($_SESSION['reg_human']);
        return 'That question expired — please answer the new one below.';
    }
    /* trap 2 — timing. Submitted faster than a person can read: reject, but keep
       the question so a quick retry is not punished twice. Only the first submit
       of a question is timed, so somebody who mistyped an answer (and is handed a
       fresh sum) can resubmit as fast as they like. */
    if ((int) ($_SESSION['reg_human_tries'] ?? 0) === 0
        && time() - (int) $ch['t'] < REG_HUMAN_MIN_SECONDS) {
        return 'That was too quick — please read the question and try again.';
    }

    $given = trim((string) ($post['human'] ?? ''));
    if ($given === '') return 'Please answer the human check question.';

    $ok = preg_match('/^\d+$/', $given) === 1 && (int) $given === (int) $ch['a'];
    if (!$ok) {
        $tries = (int) ($_SESSION['reg_human_tries'] ?? 0) + 1;
        $_SESSION['reg_human_tries'] = $tries;
        unset($_SESSION['reg_human']);           /* next render shows a fresh sum */
        if ($tries >= REG_HUMAN_MAX_TRIES) {
            $_SESSION['reg_human_locked'] = time();
            return 'Too many wrong answers — please wait a few minutes and try again.';
        }
        return 'That answer is not right — please try the new question.';
    }

    /* a human: remember it, and clear the lockout counters */
    $_SESSION['reg_human_ok'] = time();
    unset($_SESSION['reg_human'], $_SESSION['reg_human_tries'], $_SESSION['reg_human_locked']);
    return null;
}

/* ---------------- password reset via e-mail ---------------- */

const PW_RESET_TTL = 1800;          /* reset links live 30 minutes */
const PW_RESET_THROTTLE = 60;       /* one e-mail per account per minute */

/** Issue a reset token for $email and mail the link. Returns the raw token
 *  (callers e-mail it; the page ignores the value) or '' when no account
 *  matches OR a fresh link is already pending (callers show the SAME generic
 *  message either way, so the endpoint can never be used to discover which
 *  e-mails are registered). Only the SHA-256 of the token is stored, so a
 *  database leak cannot be replayed against the reset form. */
function password_reset_request(string $email): string
{
    $u = find_user_by_email(trim($email));
    if (!$u || !filter_var((string) ($u['email'] ?? ''), FILTER_VALIDATE_EMAIL)) return '';
    user_meta_ensure();
    $uid = (int) $u['id'];

    /* throttle: a pending link is kept while it is still young — the first
     * e-mail's link stays valid, no mail-bombing */
    $prev = user_meta_get($uid, 'pw_reset');
    if ($prev !== '') {
        $prevExp = (int) substr($prev, strrpos($prev, '|') + 1);
        if ($prevExp - time() > PW_RESET_TTL - PW_RESET_THROTTLE) return '';
    }

    $token = bin2hex(random_bytes(32));
    user_meta_set($uid, 'pw_reset', hash('sha256', $token) . '|' . (time() + PW_RESET_TTL));
    $link = app_link('reset_password.php?t=' . $token);
    $inner = '<p style="margin:0;font-size:14px;line-height:1.6;color:#334155">We received a request to reset the password for your account (<b>' . e((string) $u['email']) . '</b>). This link works once and expires in 30 minutes.</p>'
        . '<p style="margin:10px 0 0;font-size:13px;color:#64748b">Did not ask for this? Ignore this e-mail — your password stays unchanged.</p>'
        . email_button($link, 'Choose a new password');
    send_email((string) $u['email'], 'Reset your LearnHub password', email_shell('Password reset', $inner));
    return $token;
}

/** Validate a reset token. Returns the user row while the token is unused and
 *  unexpired, null otherwise (expired/unknown tokens are cleaned up). */
function password_reset_user(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) return null;
    $given = hash('sha256', $token);
    user_meta_ensure();
    $st = db()->prepare("SELECT m.user_id, m.v FROM user_meta m WHERE m.k = 'pw_reset'");
    $st->execute();
    foreach ($st->fetchAll() as $row) {
        $v = (string) $row['v'];
        $sep = strrpos($v, '|');
        if ($sep === false) continue;
        if (!hash_equals(substr($v, 0, $sep), $given)) continue;
        $exp = (int) substr($v, $sep + 1);
        $uid = (int) $row['user_id'];
        if ($exp < time()) { db()->prepare('DELETE FROM user_meta WHERE user_id = ? AND k = ?')->execute([$uid, 'pw_reset']); return null; }
        return find_user_by_id($uid);
    }
    return null;
}

/** Set a new password from a validated token (single-use) and e-mail a
 *  confirmation. Returns true when the password was changed. */
function password_reset_apply(string $token, string $newPassword): bool
{
    $u = password_reset_user($token);
    if (!$u) return false;
    db()->prepare('UPDATE users SET password = ? WHERE id = ?')
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $u['id']]);
    db()->prepare('DELETE FROM user_meta WHERE user_id = ? AND k = ?')->execute([(int) $u['id'], 'pw_reset']);
    $mail = (string) ($u['email'] ?? '');
    if (filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        send_email($mail, 'Your LearnHub password was changed',
            email_shell('Password changed', '<p style="margin:0;font-size:14px;line-height:1.6;color:#334155">Your password was just reset. If this was not you, contact the administrator immediately — whoever has your e-mail inbox can request another reset.</p>'));
    }
    return true;
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
        // PHP never even handed the file to the script: it died in the ini layer,
        // so name the limit the teacher actually hit instead of a vague "too big".
        UPLOAD_ERR_INI_SIZE  => 'That file is larger than this server allows (' . max_upload_label() . '). Try a lower-bitrate export, or add it as a YouTube/Vimeo link instead.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds the form size limit.',
        UPLOAD_ERR_PARTIAL   => 'The upload was interrupted — please try again.',
        UPLOAD_ERR_NO_FILE   => 'No file was selected.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked this upload.',
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
    if ((int) $f['size'] > max_upload_bytes()) throw new RuntimeException('File is larger than ' . max_upload_label() . '.');
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
        if ($size > max_upload_bytes()) throw new RuntimeException('A file is larger than ' . max_upload_label() . '.');
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

/* ---------------- profile (account details + profile picture) ----------------
 * A picture is a file, and uploads/ is web-blocked — so it can never be linked
 * straight into an <img src>. avatar.php streams it back to signed-in sessions
 * instead, exactly like download.php does for lesson files.
 * Pictures get their own folder under uploads/ so deleting one (or a whole
 * account) can never touch lesson files, and so the folder is easy to spot,
 * back up or clean.
 * The browser is ASKED to square-crop and shrink the picture before uploading
 * (the script at the bottom of profile.php), which keeps a 12 MB phone photo
 * from tripping a shared host's per-request cap. Nothing on the server trusts
 * that: every file is checked by content, cropped, shrunk and re-encoded here
 * whenever this PHP has GD. Without GD the validated original is stored as is —
 * the page still works, it just keeps the bigger bytes (see README).
 * -------------------------------------------------------------------------- */

/** Folder that holds profile pictures — created and web-blocked on first use. */
function avatar_dir(): string
{
    $dir = UPLOAD_DIR . '/avatars';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    deny_web_access($dir);
    return $dir;
}

/** The stored file name of a user's picture, '' when they have none (or the row
 *  names something that is not a picture extension). */
function avatar_file_of(array $user): string
{
    $stored = basename(trim((string) ($user['avatar'] ?? '')));
    if ($stored === '' || !in_array(ext_of($stored), AVATAR_EXTS, true)) return '';
    return $stored;
}

/** Absolute path to a stored picture, '' when it is missing on disk. */
function avatar_path_of(string $stored): string
{
    if ($stored === '') return '';
    $path = avatar_dir() . '/' . basename($stored);   // basename(): never leaves the folder
    return is_file($path) ? $path : '';
}

/** Address for an <img> tag, '' when this user has no usable picture. */
function user_avatar_url(array $user): string
{
    $path = avatar_path_of(avatar_file_of($user));
    if ($path === '') return '';
    /* `u` + `t` on purpose: the id encryptor in this file only rewrites numeric
       id params (id/c/m/with/course/ids). A numeric ?id= here would be re-tokenised
       on every single page — a fresh URL each time, so the picture could never be
       cached and the top bar would re-download it on every click. `t` is the file's
       mtime, so a new picture is a new URL and the browser drops the old one. */
    return 'avatar.php?u=u' . (int) ($user['id'] ?? 0) . '&t=' . (int) @filemtime($path);
}

/** Which account's picture is being asked for (?u=u7 — 0 when unreadable). */
function profile_request_user_id(): int
{
    $raw = trim((string) ($_GET['u'] ?? ''));
    if ($raw === '') return 0;
    if ($raw[0] === 'u' || $raw[0] === 'U') $raw = substr($raw, 1);
    return ($raw !== '' && ctype_digit($raw)) ? (int) $raw : 0;
}

/* ---- the hover card: somebody else's profile, on their name ----------------
 * A teacher who already sees a student's name on a roster, an attendance sheet
 * or a chat list can hover that name and get the small card: the picture, the
 * name, the about line, when they joined. A student gets the same card for the
 * teacher of a course they are enrolled in, which is how a course card, a course
 * page or a timetable slot can show a face instead of a bare name. That is the
 * whole rule — it runs both ways along one shared course, and nobody gets a
 * directory, a search, or a card for a person they never share a course with.
 * avatar.php, user_peer_avatar_html() and
 * profile_hover_html() all consult can_view_profile_of(), so the picture a page
 * prints and the file the browser fetches are decided the same way: a card that
 * renders is a card whose bytes arrive, and one that is not allowed cannot be
 * smuggled in by editing the HTML.
 * -------------------------------------------------------------------------- */

/** Per-request cache of the profile rows the cards need, by reference so a
 *  whole list can be primed with one query. */
function &profile_card_cache(): array
{
    static $cache = [];
    return $cache;
}

/** One account's card row, or null when the id is nobody. Read through the
 *  per-request cache — see profile_cards_preload() for list pages. */
function profile_card_data(int $id): ?array
{
    $cache = &profile_card_cache();
    $id = (int) $id;
    if ($id <= 0) return null;
    if (array_key_exists($id, $cache)) return $cache[$id];
    $stmt = db()->prepare('SELECT id, name, role, bio, avatar, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $cache[$id] = $row ?: null;
    return $cache[$id];
}

/** Prime the cache for a list of accounts in a single query, so a roster of 200
 *  names costs one round trip and not two hundred. Ids with no row are remembered
 *  as misses, which keeps a deleted account from being asked for twice.
 *  An id this viewer may not see is dropped rather than fetched, so the cache only
 *  ever holds rows that can legitimately be printed on this page — a student's
 *  course cards prime their teachers, not every name on the page. */
function profile_cards_preload(array $ids, ?array $viewer = null): void
{
    $me = $viewer ?? current_user();
    $cache = &profile_card_cache();
    $todo = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id <= 0 || array_key_exists($id, $cache)) continue;
        if (!can_view_profile_of($id, $me)) continue;   /* nothing here would be printed */
        $todo[] = $id;
    }
    if (!$todo) return;
    $todo = array_values(array_unique($todo));
    $in = implode(',', array_fill(0, count($todo), '?'));
    $stmt = db()->prepare("SELECT id, name, role, bio, avatar, created_at FROM users WHERE id IN ($in)");
    $stmt->execute($todo);
    foreach ($stmt->fetchAll() as $row) $cache[(int) $row['id']] = $row;
    foreach ($todo as $id) if (!array_key_exists($id, $cache)) $cache[$id] = null;
}

/** May this person's profile details be shown?
 *  - their own, always;
 *  - anybody's, for an admin (they run the site and already have the user list);
 *  - a student's, for the teacher of a course that student is enrolled in — the
 *    same teacher who sees that name on their roster;
 *  - a teacher's, for the students of a course they teach — the other half of the
 *    same courtesy, and the reason a student's course card, dashboard and schedule
 *    can show a face instead of a bare name;
 *  - nothing else. This is a card between a class and its teacher, not a people
 *    browser: no directory, no search, and no card for a person you share no
 *    course with.
 * $viewer is the signed-in account; pass one only where there is no session to
 * read (a page built for somebody else, or the checks in _profile_check.php). */
function can_view_profile_of(int $targetId, ?array $viewer = null): bool
{
    /* One query per teacher per page load — the accounts enrolled in a course
       they own — so a 200-student roster costs one lookup, not two hundred. */
    static $scopes = [];
    $me = $viewer ?? current_user();
    $targetId = (int) $targetId;
    if (!$me || $targetId <= 0) return false;
    $viewerId = (int) ($me['id'] ?? 0);
    if ($viewerId === $targetId) return true;            // never gate a person on themselves
    $role = (string) ($me['role'] ?? '');
    if ($role === 'admin') return true;
    if ($role !== 'teacher' && $role !== 'student') return false;
    if (!isset($scopes[$viewerId])) {
        $scopes[$viewerId] = [];
        /* The mirror image of the roster query above, so the rule reads the same
           from both sides of a class: a teacher's scope is the accounts enrolled
           in a course they own, a student's scope is the accounts who own a course
           they are enrolled in. Still one query per viewer per page load. */
        $stmt = $role === 'teacher'
            ? db()->prepare('SELECT DISTINCT e.user_id FROM enrollments e'
                . ' JOIN courses c ON c.id = e.course_id WHERE c.teacher_id = ?')
            : db()->prepare('SELECT DISTINCT c.teacher_id FROM enrollments e'
                . ' JOIN courses c ON c.id = e.course_id WHERE e.user_id = ?');
        $stmt->execute([$viewerId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $scopes[$viewerId][(int) $id] = true;
    }
    return isset($scopes[$viewerId][$targetId]);
}

/** The circle for somebody OTHER than the viewer: their picture when this viewer
 *  is entitled to see it, the coloured initial when they are not. Use this on any
 *  list of other people; user_avatar_html() stays right for the viewer's own. */
function user_peer_avatar_html(array $user, string $sizeClass = 'h-9 w-9', ?array $viewer = null): string
{
    if (!can_view_profile_of((int) ($user['id'] ?? 0), $viewer)) $user['avatar'] = '';
    return user_avatar_html($user, $sizeClass);
}

/**
 * The attributes that turn any element into a profile card trigger: the words
 * the card is made of ride along as data attributes, and the title attribute is
 * the no-script version of the same card. '' when this viewer may not see this
 * person's profile — so a page cannot leak by forgetting to check.
 *
 * Use this on markup that already exists (a link, a table row); use
 * profile_hover_html() when there is nothing to decorate.
 */
function profile_hover_attrs($user, array $opt = []): string
{
    $id = is_array($user) ? (int) ($user['id'] ?? 0) : (int) $user;
    $viewer = (isset($opt['viewer']) && is_array($opt['viewer'])) ? $opt['viewer'] : current_user();
    if ($id <= 0 || !can_view_profile_of($id, $viewer)) return '';
    if (($opt['self'] ?? true) === false && $viewer && (int) ($viewer['id'] ?? 0) === $id) return '';
    $p = profile_card_data($id);
    if ($p === null) return '';                           // deleted since the list was built

    $name = trim((string) ($p['name'] ?? ''));
    if ($name === '') $name = 'Account #' . $id;
    $bio = trim((string) ($p['bio'] ?? ''));
    $role = strtolower((string) ($p['role'] ?? 'student'));
    $roleTxt = $role === 'teacher' ? 'Teacher' : ($role === 'admin' ? 'Administrator' : 'Student');
    $joined = (int) ($p['created_at'] ?? 0);
    $pic = user_avatar_url($p);                           // '' when they never uploaded one

    $attr = '';
    /* an element that is already tabbable (a link, a button) must not be given a
       second tab stop: pass focusable => false and the card still opens on focus */
    if (($opt['focusable'] ?? true) !== false) $attr .= ' tabindex="0"';
    $attr .= ' title="' . e($name . ($bio !== '' ? ' — ' . $bio : '')) . '"';
    $attr .= ' data-hcard data-hc-name="' . e($name) . '"'
          . ' data-hc-role="' . e($roleTxt) . '"'
          . ' data-hc-bio="' . e($bio !== '' ? $bio : 'No about line yet.') . '"'
          . ' data-hc-joined="' . e($joined > 0 ? 'Joined ' . date('F Y', $joined) : '') . '"'
          . ' data-hc-pic="' . e($pic) . '"';
    return $attr;
}

/**
 * Wrap the markup that stands for an account (their circle and name, usually) so
 * that pointing at it — or tabbing to it — floats a small card with that
 * account's profile: picture, name, role, about line, date joined.
 *
 * Nothing is fetched on hover: the words ride along as data attributes and the
 * script in app.js paints ONE shared floating card and moves it, so a long
 * roster carries no hidden boxes and the page stays as small as it was. With
 * scripting off the title attribute still carries the name and the about line.
 * $inner comes back untouched when the viewer may not see this person.
 * $opt understands 'block' (the wrapper is a <div>, for markup that owns a line),
 * 'class' (extra classes), 'focusable' => false (see above), 'self' => false (no
 * card for the viewer's own row — a list of names is not where you read your own
 * about line back to yourself) and 'viewer' (whose screen this is, where there is
 * no session to read).
 */
function profile_hover_html(string $inner, $user, array $opt = []): string
{
    $attr = profile_hover_attrs($user, $opt);
    if ($attr === '') return $inner;
    $block = (bool) ($opt['block'] ?? false);
    $tag = $block ? 'div' : 'span';
    $attr = ' class="lh-hcard' . ($block ? ' lh-hcard-block' : '')
          . (isset($opt['class']) ? ' ' . $opt['class'] : '') . '"' . $attr;
    return '<' . $tag . $attr . '>' . $inner . '</' . $tag . '>';
}

/**
 * Whoever teaches a course, as one piece of markup: their circle beside their
 * name, carrying the hover card when this viewer may see them.
 *
 * The course rows load_courses() hands back already carry the teacher's name and
 * id but not their picture, so this reads the picture through the same
 * profile_card_data() cache the roster uses — prime the ids first with
 * profile_cards_preload() and a page full of course cards costs one query, not
 * one per card. The circle and the card are both decided by can_view_profile_of():
 * a student enrolled in the course gets the teacher's picture and card, everyone
 * else gets the plain initial and no card, exactly as on a roster.
 *
 * 'self' => false throughout: a teacher looking at their own course should not
 * hover their own name to be told about themselves.
 * @param array  $course    one row from load_courses() or course_row()
 * @param string $sizeClass circle size, e.g. 'h-7 w-7'
 * @param bool   $withName  false prints the circle alone, for a line that already
 *                          names the course (a timetable slot) where a name too
 *                          would just crowd the row
 * @param array  $viewer    whose screen this is; leave null on a page and it reads
 *                          the signed-in account. Pass it only where there is no
 *                          session to read (the checks in _profile_check.php) —
 *                          current_user() memoises, so a later session change is
 *                          not seen.
 */
function course_teacher_html(array $course, string $sizeClass = 'h-7 w-7', bool $withName = true, ?array $viewer = null): string
{
    $teacherId = (int) ($course['teacher_id'] ?? 0);
    $name      = trim((string) ($course['teacher_name'] ?? ''));
    if ($teacherId <= 0 || $name === '') return '';
    $t = profile_card_data($teacherId);
    /* the teacher row is gone (deleted account): keep the name the course row
       still carries rather than printing an empty chip */
    if ($t === null) return $withName ? '<span>' . e($name) . '</span>' : '';
    $inner = '<span class="inline-flex items-center gap-2">'
        . user_peer_avatar_html($t, $sizeClass, $viewer);
    if ($withName) $inner .= '<span class="truncate">' . e($name) . '</span>';
    return profile_hover_html($inner . '</span>', $teacherId,
        ['self' => false] + ($viewer !== null ? ['viewer' => $viewer] : []));
}

/** The same chip for a page that only holds course ids (a timetable slot carries a
 *  course_id, not a course row). Keeps its own per-request course cache so a week
 *  of slots costs one course lookup per distinct course and not one per row, and
 *  hands what it finds straight to course_teacher_html(). */
function course_teacher_chip(int $courseId, string $sizeClass = 'h-5 w-5', bool $withName = false): string
{
    static $rows = [];
    $courseId = (int) $courseId;
    if ($courseId <= 0) return '';
    if (!array_key_exists($courseId, $rows)) $rows[$courseId] = course_row($courseId);
    return $rows[$courseId] === null ? '' : course_teacher_html($rows[$courseId], $sizeClass, $withName);
}

/**
 * The circle an account is represented by: their picture when there is one, the
 * coloured initial when there is not. The markup mirrors what the shell used to
 * hand-write, so themes and density sheets keep seeing the shapes they already
 * style — which is why callers wrap this instead of rebuilding it.
 * @param string $sizeClass size classes that already exist in the Tailwind build
 */
function user_avatar_html(array $user, string $sizeClass = 'h-9 w-9'): string
{
    $name = trim((string) ($user['name'] ?? ''));
    $url  = user_avatar_url($user);
    if ($url !== '') {
        $alt = $name === '' ? 'Profile picture' : 'Profile picture of ' . $name;
        /* the round shape and object-fit come from .lh-avatar-img in shell.css —
           object-cover is not in the compiled Tailwind build */
        return '<img src="' . e($url) . '" alt="' . e($alt) . '" loading="lazy" decoding="async"'
             . ' class="lh-avatar-img ' . $sizeClass . ' rounded-full">';
    }
    $initial = strtoupper(cut($name !== '' ? $name : '?', 1));
    return '<span class="lh-avatar-initial grid ' . $sizeClass . ' place-items-center rounded-full bg-emerald-600 text-sm font-bold text-white"'
         . ' aria-hidden="true">' . e($initial) . '</span>';
}

/**
 * What one uploaded picture becomes: square-cropped, at most AVATAR_SIDE px on a
 * side, re-encoded when this PHP has GD (which also strips EXIF and anything
 * tucked behind the pixels), kept as-is when it does not. The extension comes
 * from the file CONTENT, never from the name the browser sent — so a renamed
 * script or an SVG is refused on its own merits, not on its label.
 * @return array{ext:string, bytes:string}
 * @throws RuntimeException carrying a message that is safe to show the user
 */
function prepare_profile_image(string $tmpFile): array
{
    if (!is_file($tmpFile)) throw new RuntimeException('The upload never reached the server — please choose the picture again.');
    $info = @getimagesize($tmpFile);
    if (!is_array($info) || empty($info[2])) {
        throw new RuntimeException('That file is not a picture a browser can display — please choose a JPG, PNG, WEBP or GIF.');
    }
    $type   = (int) $info[2];
    $width  = (int) $info[0];
    $height = (int) $info[1];
    /* IMAGETYPE_* are core constants, so this gate works without GD too */
    $extByType = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!isset($extByType[$type])) {
        throw new RuntimeException('Only JPG, PNG, WEBP and GIF pictures can be used as a profile picture.');
    }
    if ($width < 64 || $height < 64) {
        throw new RuntimeException('A profile picture needs to be at least 64 × 64 pixels — this one is ' . $width . ' × ' . $height . '.');
    }
    if ($width > 8000 || $height > 8000) {
        throw new RuntimeException('That picture is far too large (' . $width . ' × ' . $height . ' pixels) — please use one under 8000 pixels on each side.');
    }
    $ext   = $extByType[$type];
    $bytes = (string) @file_get_contents($tmpFile);
    if ($bytes === '') throw new RuntimeException('The picture could not be read — please try again.');
    if (!function_exists('imagecreatetruecolor')) {
        /* no GD on this machine (plain XAMPP builds ship without it): the bytes are
           proven to be a real, sensibly sized image, so store them untouched */
        return ['ext' => $ext, 'bytes' => $bytes];
    }
    $img = @imagecreatefromstring($bytes);
    if (!$img) throw new RuntimeException('That picture could not be decoded — please choose a different file.');
    $side = (int) min($width, $height);                 /* centre square: a portrait
                                                           head or a logo both survive */
    $srcX = (int) (($width  - $side) / 2);
    $srcY = (int) (($height - $side) / 2);
    $canvas = imagecreatetruecolor(AVATAR_SIDE, AVATAR_SIDE);
    if ($ext === 'jpg') {
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));  /* JPEG has no alpha */
    } else {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    }
    imagecopyresampled($canvas, $img, 0, 0, $srcX, $srcY, AVATAR_SIDE, AVATAR_SIDE, $side, $side);
    imagedestroy($img);
    ob_start();
    $encoded = false;
    if ($ext === 'png') {
        $encoded = imagepng($canvas, null, 6);
    } elseif ($ext === 'gif') {
        $encoded = imagepng($canvas, null, 6);   /* flatten a gif into png: no animated */
        $ext = 'png';                             /* frames, no palette surprises       */
    } elseif (function_exists('imagewebp')) {
        $encoded = imagewebp($canvas, null, 86);
    } else {
        $encoded = imagejpeg($canvas, null, 88);
        $ext = 'jpg';
    }
    $out = (string) ob_get_clean();
    imagedestroy($canvas);
    if (!$encoded || $out === '') throw new RuntimeException('This picture could not be processed on this server — please try again.');
    return ['ext' => $ext, 'bytes' => $out];
}

/**
 * Store the picture posted in $_FILES[$field] for one account.
 * Nothing is deleted here: the caller swaps the row first, then unlinks the old
 * file, so a failed upload can never leave a user without a picture.
 * @return array{stored:string, size:int, ext:string}
 */
function save_profile_picture(int $userId, string $field = 'avatar'): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Please choose a picture first.');
    }
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException(upload_error_message($err));
    if ((int) $f['size'] > AVATAR_MAX_BYTES) {
        throw new RuntimeException('A profile picture must be ' . format_size(AVATAR_MAX_BYTES)
            . ' or smaller — yours is ' . format_size((int) $f['size'])
            . '. Turn JavaScript on and the browser will shrink it for you.');
    }
    $tmp = (string) ($f['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('The upload could not be verified — please choose the picture again.');
    }
    /* the name is only a hint that lets the form say "that is not a picture"
       early; prepare_profile_image() is the one that actually decides */
    $ext = ext_of((string) ($f['name'] ?? ''));
    if ($ext !== '' && !in_array($ext, AVATAR_EXTS, true)) {
        throw new RuntimeException('Please choose a JPG, PNG, WEBP or GIF picture.');
    }
    $pic = prepare_profile_image($tmp);
    if (strlen($pic['bytes']) > AVATAR_MAX_BYTES) {
        throw new RuntimeException('That picture is still larger than ' . format_size(AVATAR_MAX_BYTES) . ' after checking it — please choose a smaller one.');
    }
    $stored = 'avatar' . $userId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $pic['ext'];
    if (@file_put_contents(avatar_dir() . '/' . $stored, $pic['bytes']) === false) {
        throw new RuntimeException('The picture could not be stored — check the permissions of the uploads folder.');
    }
    return ['stored' => $stored, 'size' => strlen($pic['bytes']), 'ext' => $pic['ext']];
}

/** Point an account at a stored picture and hand back the file it replaces. */
function set_profile_picture(int $userId, string $stored): string
{
    $st = db()->prepare('SELECT avatar FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $old = avatar_file_of(['avatar' => (string) $st->fetchColumn()]);
    db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$stored, $userId]);
    return $old;                       /* caller unlinks it once the row is saved */
}

/** Take an account's picture away (row first, file after — same order as above). */
function clear_profile_picture(int $userId): string
{
    $st = db()->prepare('SELECT avatar FROM users WHERE id = ? LIMIT 1');
    $st->execute([$userId]);
    $old = avatar_file_of(['avatar' => (string) $st->fetchColumn()]);
    db()->prepare('UPDATE users SET avatar = NULL WHERE id = ?')->execute([$userId]);
    return $old;
}

/** Remove one stored picture, retrying: on Windows a just-written file can be
 *  locked for a moment (Defender), and delete_uploaded_file() hit the same wall. */
function delete_profile_picture_file(string $stored): bool
{
    $path = avatar_dir() . '/' . basename($stored);
    if ($stored === '' || !is_file($path)) return true;
    for ($i = 0; $i < 5; $i++) {
        if (@unlink($path)) return true;
        usleep(300000);
    }
    return !is_file($path);
}

/** Save the editable parts of an account (name, e-mail, about line). */
function update_profile_details(int $userId, string $name, string $email, string $bio): void
{
    db()->prepare('UPDATE users SET name = ?, email = ?, bio = ? WHERE id = ?')
        ->execute([$name, $email, $bio, $userId]);
}

/** New password for an account — the caller hashes it and verified the old one. */
function update_profile_password(int $userId, string $passwordHash): void
{
    db()->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$passwordHash, $userId]);
}

/** One line of text, safe for a VARCHAR: no newlines or control characters,
 *  trimmed, and cut to the column length so MySQL never silently truncates. */
function profile_one_line(string $text, int $limit): string
{
    $flat = trim((string) preg_replace('~\\s+~u', ' ', str_replace(["\r", "\n", "\t"], ' ', $text)));
    return cut($flat, $limit);
}

/* ---------------- chunked uploads (big lessons on capped hosts) -------------
 * Shared hosting caps ONE request — post_max_size / upload_max_filesize are
 * commonly 10–20 MB (InfinityFree and its resellers), and anything bigger is
 * dropped by PHP before a line of this app runs. A 200 MB lesson video can
 * therefore never arrive in a single POST.
 *
 * So the browser slices the file and posts it piece by piece instead. The
 * server appends every piece to a part file inside uploads/.parts/ (web-blocked
 * like uploads/ itself) and only moves the finished file into uploads/ once the
 * last piece has arrived. The database keeps metadata only — file bytes never
 * travel through MySQL.
 * -------------------------------------------------------------------------- */

/** Bytes one piece may carry — always comfortably under the host's request cap. */
function upload_chunk_bytes(): int
{
    $half = (int) floor(max_upload_bytes() / 2);          /* leave room for multipart + the other fields */
    return max(256 * 1024, min($half, 8 * 1024 * 1024));  /* 256 KB … 8 MB (≈25 pieces for a 200 MB video) */
}

/** The real ceiling for a CHUNKED upload — the host's per-request cap no longer applies. */
function max_chunked_bytes(): int
{
    return MAX_UPLOAD_BYTES;
}

/** Human-readable form of max_chunked_bytes() — e.g. "4 GB". */
function max_chunked_label(): string
{
    return format_size(max_chunked_bytes());
}

/** Folder holding in-flight part files (inside uploads/, so it is already web-blocked). */
function upload_parts_dir(): string
{
    $dir = UPLOAD_DIR . '/.parts';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    deny_web_access($dir);
    return $dir;
}

/** Delete part files abandoned by uploads that never finished (closed tab, lost connection). */
function upload_parts_sweep(int $olderThanSeconds = 21600): int
{
    $dir = UPLOAD_DIR . '/.parts';
    if (!is_dir($dir)) return 0;
    $removed = 0;
    foreach ((array) glob($dir . '/*.part') as $f) {
        if (is_file($f) && (time() - (int) @filemtime($f)) > $olderThanSeconds && @unlink($f)) $removed++;
    }
    return $removed;
}

/** Append one piece to its part file, streamed in 256 KB blocks (never loads the piece in memory). */
function append_upload_chunk(string $partPath, string $tmpName): int
{
    $in = @fopen($tmpName, 'rb');
    if (!$in) throw new RuntimeException('The uploaded piece could not be read.');
    $out = @fopen($partPath, 'ab');
    if (!$out) {
        fclose($in);
        throw new RuntimeException('Could not write to the uploads folder (check folder permissions).');
    }
    $written = 0;
    while (!feof($in)) {
        $buf = fread($in, 262144);
        if ($buf === false || $buf === '') break;
        $n = fwrite($out, $buf);
        if ($n === false) break;
        $written += $n;
    }
    fclose($in);
    fclose($out);
    return $written;
}

/**
 * Accept ONE piece of a chunked upload and — on the final piece — save the lesson.
 *
 * The browser sends: course_id, lesson_type (video|document), name (original file
 * name), title, description, upload_id (32 hex), index, total, size, chunk_size,
 * file_index, file_count. Everything is validated here; nothing is trusted.
 *
 * @param array $user the signed-in teacher
 * @param array $in   the request fields
 * @param array $file the $_FILES entry carrying the piece
 * @return array{received:int,total:int,done:bool,material_id:?int,title:?string,redirect:?string}
 */
function receive_upload_chunk(array $user, array $in, array $file): array
{
    $courseId  = (int) ($in['course_id'] ?? 0);
    $folderId  = (int) ($in['folder_id'] ?? 0);
    $isVideo   = ((string) ($in['lesson_type'] ?? 'video')) !== 'document';
    $uploadId  = strtolower(trim((string) ($in['upload_id'] ?? '')));
    $index     = (int) ($in['index'] ?? -1);
    $total     = (int) ($in['total'] ?? 0);
    $size      = (int) ($in['size'] ?? 0);
    $chunkSize = (int) ($in['chunk_size'] ?? 0);
    $fileIndex = (int) ($in['file_index'] ?? 0);
    $fileCount = (int) ($in['file_count'] ?? 1);
    $title     = trim((string) ($in['title'] ?? ''));
    $desc      = trim((string) ($in['description'] ?? ''));
    $name      = trim((string) ($in['name'] ?? ''));

    /* --- who may upload where ------------------------------------------- */
    $course = course_row($courseId);
    if (!$course) throw new RuntimeException('Course not found.');
    if ((int) $course['teacher_id'] !== (int) $user['id']) {
        throw new RuntimeException('You can only add lessons to your own courses.');
    }
    if (!course_folder_row($courseId, $folderId)) throw new RuntimeException('Choose a folder in this course.');

    /* --- shape of the request ------------------------------------------- */
    if (!preg_match('/^[a-f0-9]{32}$/', $uploadId)) throw new RuntimeException('Invalid upload id — please reload the page and try again.');
    if ($title === '') throw new RuntimeException('Please give the lesson a title.');
    if ($total < 1 || $total > 20000 || $index < 0 || $index >= $total) throw new RuntimeException('Invalid upload (piece numbering).');
    if ($fileCount < 1 || $fileCount > 50) $fileCount = 1;
    if ($fileIndex < 0 || $fileIndex >= $fileCount) $fileIndex = 0;
    if ($chunkSize < 1 || $chunkSize > max_upload_bytes()) throw new RuntimeException('Invalid upload (piece size).');

    /* --- the file itself ------------------------------------------------- */
    $size = min($size, max_chunked_bytes());   /* never promise more than the app allows */
    if ($size < 1) throw new RuntimeException('Invalid upload (file size).');
    $allowed = $isVideo ? VIDEO_EXTS : DOC_EXTS;
    $ext = ext_of($name);
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        throw new RuntimeException('File type ".' . $ext . '" is not allowed. Allowed: ' . implode(', ', $allowed));
    }
    $pieceBytes = (int) ($file['size'] ?? 0);
    if ($pieceBytes < 1) throw new RuntimeException('The uploaded piece was empty.');
    if ($pieceBytes > $chunkSize) throw new RuntimeException('Invalid upload (piece too large).');

    return receive_upload_chunk_store($uploadId, $index, $total, $size, $chunkSize, $fileIndex, $fileCount,
        $title, $desc, $name, $ext, $isVideo, $courseId, $folderId, $file);
}

/** Assemble a chunked upload: append the piece, and on the last one publish the lesson. */
function receive_upload_chunk_store(
    string $uploadId,
    int $index,
    int $total,
    int $size,
    int $chunkSize,
    int $fileIndex,
    int $fileCount,
    string $title,
    string $desc,
    string $name,
    string $ext,
    bool $isVideo,
    int $courseId,
    int $folderId,
    array $file
): array {
    $parts = upload_parts_dir();
    upload_parts_sweep();                       /* cheap opportunistic clean-up */
    $part  = $parts . '/' . $uploadId . '.part';
    $have  = is_file($part) ? (int) filesize($part) : 0;
    if ($have !== $index * $chunkSize) {        /* a gap or a repeat: the sequence is void */
        @unlink($part);
        throw new RuntimeException('Upload out of sync — please try again.');
    }

    /* best-effort disk-space guard: the file must actually fit */
    $free = @disk_free_space($parts);
    if (is_float($free) || is_int($free)) {
        if (($size - $have) > ((int) $free - 16 * 1024 * 1024)) {
            @unlink($part);
            throw new RuntimeException('The server does not have enough free disk space for a file this size.');
        }
    }

    $written  = append_upload_chunk($part, (string) ($file['tmp_name'] ?? ''));
    $received = $have + $written;
    if ($written < 1) {
        @unlink($part);
        throw new RuntimeException('The uploaded piece was empty.');
    }
    if ($received > $size) {                    /* more bytes than announced — refuse */
        @unlink($part);
        throw new RuntimeException('Upload is larger than announced — rejected.');
    }

    /* --- more pieces to come? ------------------------------------------- */
    if ($index < $total - 1) {
        return ['received' => $received, 'total' => $size, 'done' => false,
                'material_id' => null, 'title' => null, 'redirect' => null];
    }
    if ($received !== $size) {
        @unlink($part);
        throw new RuntimeException('Upload incomplete (' . format_size($received) . ' of ' . format_size($size) . ') — please try again.');
    }

    /* --- last piece: publish the file, then create the lesson ------------ */
    $stored = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!@rename($part, UPLOAD_DIR . '/' . $stored)) {
        /* Windows/antivirus can hold a freshly closed file briefly — copy instead */
        if (!@copy($part, UPLOAD_DIR . '/' . $stored)) {
            @unlink($part);
            throw new RuntimeException('Could not save the uploaded file (check folder permissions).');
        }
        @unlink($part);
    }

    /* extra files pick up "(2)", "(3)"… exactly like the single-request upload */
    $lessonTitle = $fileIndex === 0 ? $title : cut($title, 115) . ' (' . ($fileIndex + 1) . ')';
    $materialId = add_material($courseId, $isVideo ? 'video' : 'file', cut($lessonTitle, 120), cut($desc, 200), [
        'stored' => $stored,
        'orig'   => $name,
        'mime'   => mime_for_ext($ext),
        'size'   => $size,
    ], null, $folderId);

    /* real event -> notification for every enrolled student (same copy as
       upload.php). The chunked path publishes documents & videos and used to
       notify nobody — that is why uploading a new file lesson produced no bell
       entry on the student side (only pasted text/links, which post straight to
       upload.php, ever notified). Guarded: the lesson IS saved, so a
       notification hiccup must not turn a successful upload into an error. */
    try {
        $courseTitle = (string) (course_row($courseId)['title'] ?? 'Course');
        notify_course_students($courseId, 'lesson',
            '📚 New lesson in "' . cut($courseTitle, 60) . '"',
            cut($lessonTitle, 90),
            'course.php?id=' . $courseId);
    } catch (\Throwable $notifyEx) {
        lms_error_log('lesson notification failed: ' . $notifyEx->getMessage());
    }

    return [
        'received'    => $received,
        'total'       => $size,
        'done'        => true,
        'material_id' => $materialId,
        'title'       => $lessonTitle,
        'redirect'    => lh_enc_url('course.php?id=' . $courseId),
    ];
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

/** Put .htaccess + index.html in a private folder so the web server can never
 *  serve it, whatever the root .htaccess does. Written for Apache 2.4 AND 2.2
 *  so it works on XAMPP as well as shared hosts (InfinityFree & friends).
 *  Never overwrites a file that is already there. */
function deny_web_access(string $dir): void
{
    if (!is_dir($dir)) return;
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess',
            "# LearnHub — never serve this folder over the web\n"
            . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n"
            . "# No rewrite magic either: a direct hit on any file in here is refused.\n"
            . "<IfModule mod_rewrite.c>\n    RewriteEngine Off\n</IfModule>\n");
    }
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
}

function ensure_storage(): void
{
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);
    deny_web_access(UPLOAD_DIR);
    upload_parts_dir();          /* uploads/.parts — in-flight chunked uploads */
    /* data/ holds admin-credentials.txt, mail.log and error.log — files with the
       DB/e-mail secrets and the password of the auto-created admin. Block it at
       the folder level too, so a missing root .htaccess cannot expose them. */
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0777, true);
    deny_web_access(DATA_DIR);
}
/* ---------------- courses & lessons (write) ---------------- */

function create_course(int $teacherId, string $title, string $category, string $description): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO courses (teacher_id, title, category, description, created_at) VALUES (?,?,?,?,?)')
            ->execute([$teacherId, $title, $category !== '' ? $category : 'General', $description, time()]);
        $courseId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO course_folders (course_id, parent_id, name, created_at) VALUES (?,NULL,?,?)')
            ->execute([$courseId, 'Main folder', time()]);
        $pdo->commit();
        return $courseId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
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

function add_material(int $courseId, string $type, string $title, string $description, ?array $file = null, ?string $url = null, ?int $folderId = null): int
{
    if ($folderId === null) {
        $folder = main_course_folder($courseId);
        if (!$folder) throw new RuntimeException('This course does not have a main folder yet.');
        $folderId = (int) $folder['id'];
    }
    if (!course_folder_row($courseId, $folderId)) throw new RuntimeException('Choose a folder in this course.');
    db()->prepare('INSERT INTO materials (course_id, folder_id, type, title, description, filename, orig_name, mime, size, url, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $courseId, $folderId, $type, $title, $description,
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

/* ---------------- class schedule (the teacher's timetable) ---------------- */

/** The kinds of slot a teacher can put on the timetable, with the chip it wears. */
function schedule_kinds(): array
{
    return [
        'class'    => ['label' => 'Class',    'icon' => '📘', 'chip' => 'bg-indigo-50 text-indigo-700'],
        'exam'     => ['label' => 'Exam',     'icon' => '📝', 'chip' => 'bg-rose-50 text-rose-700'],
        'activity' => ['label' => 'Activity', 'icon' => '🧩', 'chip' => 'bg-amber-50 text-amber-700'],
        'deadline' => ['label' => 'Deadline', 'icon' => '⏰', 'chip' => 'bg-slate-100 text-slate-600'],
    ];
}

/** Weekday number => name, Monday first. The numbers are PHP's date('w') (0 = Sunday). */
function schedule_weekdays(): array
{
    return [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
}

/** The teacher who owns a schedule row as their own face, ready for a list or a
 *  calendar day. Goes through the peer helper, so the picture is replaced by the
 *  coloured initial when the viewer may not see that profile. */
function schedule_teacher_face_html(array $row, string $sizeClass = 'h-9 w-9', ?array $viewer = null): string
{
    $teacherId = (int) ($row['teacher_id'] ?? 0);
    $user = [
        'id' => $teacherId,
        'name' => (string) ($row['teacher_name'] ?? ''),
        'avatar' => (string) ($row['teacher_avatar'] ?? ''),
    ];
    return user_peer_avatar_html($user, $sizeClass, $viewer);
}

/** One teacher face per distinct teacher on this day, at most $limit of them.
 *  Deduplicated so a teacher with three classes that morning wears one face, not
 *  three — the calendar cell is small and the badge already says "3 slots". */
function schedule_day_faces(array $items, int $limit = 3, ?array $viewer = null): array
{
    if ($limit < 1) return [];
    $seen = [];
    foreach ($items as $it) {
        $id = (int) ($it['teacher_id'] ?? 0);
        if ($id <= 0) continue;
        $seen[$id] ??= $it;
        if (count($seen) >= $limit) break;
    }
    return array_map(fn ($it) => schedule_teacher_face_html($it, 'h-5 w-5', $viewer), array_values($seen));
}

/** A stored time ('09:30:00' or '09:30') as 'HH:MM'; anything unusable becomes ''. */
function schedule_hm($t): string
{
    $t = trim((string) $t);
    return preg_match('~^([01]\d|2[0-3]):[0-5]\d~', $t) ? substr($t, 0, 5) : '';
}

/** '09:30' as '9:30 AM'. '' (no end time set) stays ''. */
function schedule_clock($hm): string
{
    $hm = schedule_hm($hm);
    return $hm === '' ? '' : date('g:i A', (int) strtotime('2000-01-01 ' . $hm));
}

/** '9:00 AM – 11:00 AM', or just the start when the slot has no end time. */
function schedule_time_label(array $row): string
{
    $from = schedule_clock($row['start_time'] ?? '');
    $to = schedule_clock($row['end_time'] ?? '');
    return $to === '' ? $from : $from . ' – ' . $to;
}

/** One slot in words: 'Every Monday · 9:00 AM – 11:00 AM', 'Mon, Sep 1 · 9:00 AM – 12:00 PM'. */
function schedule_when_label(array $row): string
{
    if (($row['repeat_mode'] ?? 'weekly') === 'once') {
        $ts = strtotime((string) ($row['sched_date'] ?? '') . ' 12:00:00');
        return ($ts ? date('D, M j, Y', $ts) . ' · ' : '') . schedule_time_label($row);
    }
    $name = schedule_weekdays()[(int) ($row['weekday'] ?? -1)] ?? '';
    return 'Every ' . ($name !== '' ? $name : 'week') . ' · ' . schedule_time_label($row);
}

/** A raw row as the rest of this file expects it (ints, 'HH:MM' times, never-null mode). */
function schedule_shape(array $r): array
{
    $r['id'] = (int) $r['id'];
    $r['course_id'] = (int) $r['course_id'];
    $r['teacher_id'] = (int) $r['teacher_id'];
    $r['weekday'] = $r['weekday'] === null ? null : (int) $r['weekday'];
    $r['repeat_mode'] = ((string) $r['repeat_mode'] === 'once') ? 'once' : 'weekly';
    $r['start_time'] = schedule_hm($r['start_time'] ?? '');
    $r['end_time'] = schedule_hm($r['end_time'] ?? '');
    /* Carried so the calendar can put a teacher's own face on a day. A slot is
       deleted with its teacher (FK ON DELETE CASCADE), so this is always set. */
    $r['teacher_name'] ??= '';
    $r['teacher_avatar'] ??= '';
    return $r;
}

/**
 * Every slot of the given courses, earliest start first, each row carrying its
 * course title. This is the one gate on the schedule — it is always called with
 * courses the signed-in user is allowed to see, which is how a student can never
 * read another course's timetable.
 */
function schedule_rows_for_courses(array $courseIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $courseIds), fn ($i) => $i > 0)));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT s.*, c.title AS course_title, c.category AS course_category,
                                u.name AS teacher_name, u.avatar AS teacher_avatar
                         FROM schedules s JOIN courses c ON c.id = s.course_id
                         JOIN users u ON u.id = s.teacher_id
                         WHERE s.course_id IN ($in)
                         ORDER BY s.start_time ASC, s.id ASC");
    $st->execute($ids);
    return array_map('schedule_shape', $st->fetchAll());
}

/** The course ids a teacher owns — or every course, for the main admin. */
function schedule_teacher_course_ids(int $teacherId, bool $allCourses = false): array
{
    if ($allCourses) {
        return array_map('intval', db()->query('SELECT id FROM courses')->fetchAll(PDO::FETCH_COLUMN));
    }
    $st = db()->prepare('SELECT id FROM courses WHERE teacher_id = ?');
    $st->execute([$teacherId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Everything a teacher (or the admin) has put on their own courses. */
function schedules_for_teacher(int $teacherId, bool $allCourses = false, int $courseId = 0): array
{
    $ids = schedule_teacher_course_ids($teacherId, $allCourses);
    if ($courseId > 0) $ids = array_values(array_intersect($ids, [$courseId]));
    return schedule_rows_for_courses($ids);
}

/** Everything a student may see: the slots of the courses they are enrolled in. */
function schedules_for_student(int $userId): array
{
    $st = db()->prepare('SELECT course_id FROM enrollments WHERE user_id = ?');
    $st->execute([$userId]);
    return schedule_rows_for_courses($st->fetchAll(PDO::FETCH_COLUMN));
}

/** One slot by id. */
function schedule_row(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM schedules WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ? schedule_shape($row) : null;
}

/** May this user add, edit or delete slots of this course? A teacher owns their own
 *  courses; the main admin passes every teacher gate. */
function schedule_can_manage(array $user, int $courseId): bool
{
    $role = (string) ($user['role'] ?? '');
    if ($courseId <= 0) return false;
    if ($role === 'admin') return course_row($courseId) !== null;
    if ($role !== 'teacher') return false;
    return course_owner_id($courseId) === (int) $user['id'];
}

/**
 * Check one posted slot: ['ok' => bool, 'errors' => string[], 'data' => array].
 * A slot is either a weekly rule (a weekday, repeating forever) or a one-off
 * entry on an exact date — the two things a real timetable is made of, and they
 * are mutually exclusive, exactly as the columns are.
 */
function schedule_parse(array $in): array
{
    $errors = [];

    $kind = (string) ($in['kind'] ?? 'class');
    if (!array_key_exists($kind, schedule_kinds())) $kind = 'class';

    $mode = ((string) ($in['repeat_mode'] ?? 'weekly') === 'once') ? 'once' : 'weekly';
    $weekday = null;
    $date = null;
    if ($mode === 'weekly') {
        $weekday = (int) ($in['weekday'] ?? -1);
        if (!array_key_exists($weekday, schedule_weekdays())) {
            $errors[] = 'Choose which day of the week the class repeats on.';
            $weekday = null;
        }
    } else {
        $raw = trim((string) ($in['sched_date'] ?? ''));
        if (!preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $raw, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $errors[] = 'Choose a valid date for a one-off entry.';
        } else {
            $date = $raw;
        }
    }

    $start = schedule_hm($in['start_time'] ?? '');
    if ($start === '') $errors[] = 'Enter a start time.';
    $end = schedule_hm($in['end_time'] ?? '');
    if ($end !== '' && $start !== '' && $end <= $start) $errors[] = 'The end time must be after the start time.';

    $title = trim((string) ($in['title'] ?? ''));
    $place = trim((string) ($in['place'] ?? ''));
    $notes = trim((string) ($in['notes'] ?? ''));
    if (mb_strlen($title) > 120 || mb_strlen($place) > 160 || mb_strlen($notes) > 500) {
        $errors[] = 'One field is too long: title 120, place 160, notes 500 characters.';
    }

    return [
        'ok' => !$errors,
        'errors' => $errors,
        'data' => [
            'title' => $title,
            'kind' => $kind,
            'repeat_mode' => $mode,
            'weekday' => $weekday,
            'sched_date' => $date,
            'start_time' => $start === '' ? '08:00' : $start,
            'end_time' => $end === '' ? null : $end,
            'place' => $place,
            'notes' => $notes,
        ],
    ];
}

/**
 * Create (id = 0) or update one slot.
 * Returns ['ok' => bool, 'errors' => string[], 'id' => int, 'created' => bool, 'course_id' => int].
 */
function schedule_save(array $user, int $id, array $in): array
{
    $courseId = (int) ($in['course_id'] ?? 0);
    if (!schedule_can_manage($user, $courseId)) {
        return ['ok' => false, 'errors' => ['Pick one of your own courses — every slot belongs to a course.'],
                'id' => 0, 'created' => false, 'course_id' => 0];
    }
    if ($id > 0) {
        $existing = schedule_row($id);
        if (!$existing || !schedule_can_manage($user, (int) $existing['course_id'])) {
            return ['ok' => false, 'errors' => ['That schedule entry no longer exists.'],
                    'id' => 0, 'created' => false, 'course_id' => 0];
        }
    }

    $parsed = schedule_parse($in);
    if (!$parsed['ok']) {
        return ['ok' => false, 'errors' => $parsed['errors'], 'id' => $id, 'created' => false, 'course_id' => $courseId];
    }
    $d = $parsed['data'];
    $owner = (int) (course_row($courseId)['teacher_id'] ?? 0);  /* the course's own teacher, even when an admin edits */
    $now = time();

    if ($id > 0) {
        db()->prepare('UPDATE schedules SET course_id = ?, teacher_id = ?, title = ?, kind = ?, repeat_mode = ?,
                       weekday = ?, sched_date = ?, start_time = ?, end_time = ?, place = ?, notes = ?, updated_at = ?
                       WHERE id = ?')
            ->execute([$courseId, $owner, $d['title'], $d['kind'], $d['repeat_mode'], $d['weekday'], $d['sched_date'],
                       $d['start_time'], $d['end_time'], $d['place'], $d['notes'], $now, $id]);
        return ['ok' => true, 'errors' => [], 'id' => $id, 'created' => false, 'course_id' => $courseId];
    }

    db()->prepare('INSERT INTO schedules (course_id, teacher_id, title, kind, repeat_mode, weekday, sched_date,
                   start_time, end_time, place, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$courseId, $owner, $d['title'], $d['kind'], $d['repeat_mode'], $d['weekday'], $d['sched_date'],
                   $d['start_time'], $d['end_time'], $d['place'], $d['notes'], $now, $now]);
    return ['ok' => true, 'errors' => [], 'id' => (int) db()->lastInsertId(), 'created' => true, 'course_id' => $courseId];
}

/** Remove one slot — only the teacher who owns its course may. */
function schedule_delete(array $user, int $id): bool
{
    $row = schedule_row($id);
    if (!$row || !schedule_can_manage($user, (int) $row['course_id'])) return false;
    db()->prepare('DELETE FROM schedules WHERE id = ?')->execute([$id]);
    return true;
}

/**
 * The slots that fall on one calendar day, in start-time order: weekly rules
 * whose weekday matches, plus one-off entries dated that exact day. Each item is
 * flagged 'past' when it is today and it has already finished, so a page can dim
 * what is over instead of hiding it.
 */
function schedule_items_on(array $rows, string $date): array
{
    $wd = (int) date('w', (int) strtotime($date . ' 12:00:00'));
    $isToday = ($date === date('Y-m-d'));
    $now = date('H:i');
    $items = [];
    foreach ($rows as $r) {
        if (($r['repeat_mode'] ?? 'weekly') === 'once') {
            if ((string) ($r['sched_date'] ?? '') !== $date) continue;
        } elseif ((int) ($r['weekday'] ?? -1) !== $wd) {
            continue;
        }
        $endsAt = ($r['end_time'] ?? '') !== '' ? $r['end_time'] : $r['start_time'];
        $r['past'] = $isToday && $endsAt < $now;
        $items[] = $r;
    }
    usort($items, fn ($a, $b) => [$a['start_time'], (string) $a['title']] <=> [$b['start_time'], (string) $b['title']]);
    return $items;
}

/** One week laid out Monday → Sunday, each day carrying its own items.
 *  $weekStartTs is any timestamp inside the week to build (0 = the current week). */
function schedule_week(array $rows, int $weekStartTs = 0): array
{
    $base = (int) strtotime('monday this week', $weekStartTs > 0 ? $weekStartTs : time());
    $today = date('Y-m-d');
    $names = schedule_weekdays();
    $days = [];
    foreach (array_keys($names) as $i => $wd) {
        $ts = (int) strtotime('+' . $i . ' day', $base);
        $date = date('Y-m-d', $ts);
        $days[] = [
            'date' => $date,
            'ts' => $ts,
            'weekday' => $wd,
            'label' => $names[$wd],
            'short' => date('D', $ts),
            'day' => (int) date('j', $ts),
            'month' => date('M', $ts),
            'is_today' => $date === $today,
            'items' => schedule_items_on($rows, $date),
        ];
    }
    return $days;
}

/**
 * One calendar month as weeks of seven days (Monday first), each day carrying its
 * own items — the same day shape schedule_week() returns, plus 'in_month' so the
 * grid can grey the days that belong to the neighbouring months. Whole weeks are
 * always returned: the grid starts on the Monday on or before the 1st and ends on
 * the Sunday on or after the last day, which is what makes it read as a calendar
 * rather than as a list.
 *
 * $anchorTs is any timestamp inside the month to build (0 = the current month).
 */
function schedule_month(array $rows, int $anchorTs = 0): array
{
    $anchor = $anchorTs > 0 ? $anchorTs : time();
    $month = date('Y-m', $anchor);
    $today = date('Y-m-d');
    $names = schedule_weekdays();
    $start = (int) strtotime('monday this week', (int) strtotime(date('Y-m-01', $anchor) . ' 12:00:00'));
    $end = (int) strtotime('sunday this week', (int) strtotime(date('Y-m-t', $anchor) . ' 12:00:00'));

    $weeks = [];
    $week = [];
    for ($ts = $start; $ts <= $end; $ts = (int) strtotime('+1 day', $ts)) {
        $date = date('Y-m-d', $ts);
        $wd = (int) date('w', $ts);
        $week[] = [
            'date' => $date,
            'ts' => $ts,
            'weekday' => $wd,
            'label' => $names[$wd],
            'short' => date('D', $ts),
            'day' => (int) date('j', $ts),
            'month' => date('M', $ts),
            'is_today' => $date === $today,
            'in_month' => date('Y-m', $ts) === $month,
            'items' => schedule_items_on($rows, $date),
        ];
        if (count($week) === 7) {
            $weeks[] = $week;
            $week = [];
        }
    }
    if ($week) $weeks[] = $week;   /* defensive: never drop a trailing partial week */
    return $weeks;
}

/** Today's slots — weekly rules landing on today, plus entries dated today. */
function schedule_today(array $rows): array
{
    return schedule_items_on($rows, date('Y-m-d'));
}

/**
 * The next few slots from right now: what is left of today first, then the days
 * after it, scanning three weeks ahead. Each item gains 'on_date', 'day_short'
 * and a readable 'when' ("Today", "Tomorrow", "Wed, Sep 3").
 */
function schedule_next_up(array $rows, int $limit = 3, int $fromTs = 0): array
{
    $noon = (int) strtotime(date('Y-m-d', $fromTs > 0 ? $fromTs : time()) . ' 12:00:00');
    $today = date('Y-m-d', $noon);
    $tomorrow = date('Y-m-d', (int) strtotime('+1 day', $noon));
    $found = [];
    for ($i = 0; $i < 21; $i++) {
        $ts = (int) strtotime('+' . $i . ' day', $noon);
        $date = date('Y-m-d', $ts);
        foreach (schedule_items_on($rows, $date) as $item) {
            if (!empty($item['past'])) continue;      /* today, but already finished */
            $item['on_date'] = $date;
            $item['day_short'] = date('D', $ts);
            $item['when'] = $date === $today ? 'Today' : ($date === $tomorrow ? 'Tomorrow' : date('D, M j', $ts));
            $found[] = $item;
            if (count($found) >= $limit) return $found;
        }
    }
    return $found;
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

/** Every invitation code on the site, newest first, with course + that course's teacher (main admin). */
function admin_enroll_codes(): array
{
    return db()->query('SELECT ec.id, ec.code, ec.course_id, ec.created_at, ec.used_at, ec.used_by,
                        c.title AS course_title, u.name AS teacher_name, u2.name AS used_by_name
                        FROM enroll_codes ec
                        JOIN courses c ON c.id = ec.course_id
                        LEFT JOIN users u ON u.id = ec.teacher_id
                        LEFT JOIN users u2 ON u2.id = ec.used_by
                        ORDER BY ec.id DESC LIMIT 100')->fetchAll();
}

/** Revoke any unused code — the admin is not limited to one teacher's codes. */
function delete_enroll_code_admin(int $codeId): bool
{
    $st = db()->prepare('DELETE FROM enroll_codes WHERE id = ? AND used_by IS NULL');
    $st->execute([$codeId]);
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
 * so a shut-down site really is closed: every visitor gets HTTP 503 + a stock
 * "website has an error" page that shares nothing with the look of the rest of
 * the site, redrawn with a different error on every reload. Pages that bounce a
 * visitor to login.php before header.php ever paints would have walked straight
 * past a gate placed there, which is why this one sits in lib.php.
 * Still reachable while closed: admin.php (control panel), login.php/logout.php
 * (so the admin can sign in/out), ping.php (heartbeat), and any logged-in ADMIN
 * browsing the site. CLI scripts skip the gate entirely. */
if (PHP_SAPI !== 'cli') {
    $lh_self = strtolower(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
    if ($lh_self === '' || $lh_self === '/' ) $lh_self = 'home.php';   /* the front page the folder serves */
    if (!in_array($lh_self, ['admin.php', 'login.php', 'logout.php', 'ping.php'], true)) {
        $lh_me = null;
        try { $lh_me = current_user(); } catch (Throwable $e) { /* DB not ready */ }
        if (($lh_me['role'] ?? '') !== 'admin' && maintenance_enabled()) {
            http_response_code(503);
            header('Retry-After: 3600');

            /* A different error every reload. The code, the detail line, the
               reference and the trace are invented for the page alone - nothing
               real is printed: no path, version or user data. */
            $lh_codes = [500, 502, 503, 504, 507, 509, 520, 522, 524, 526];
            $lh_heads = [
                'Internal Server Error', 'Bad Gateway', 'Service Unavailable',
                'Gateway Time-out', 'Insufficient Storage', 'Bandwidth Limit Exceeded',
                'Origin Error', 'Connection Timed Out', 'A Timeout Occurred',
                'Revalidation Failed',
            ];
            $lh_detail = [
                'The application stopped while building this page.',
                'The upstream process closed the connection before it sent a response.',
                'A worker took too long to answer and was released.',
                'The request could not be handed to a handler.',
                'A required backend did not reply in time.',
                'The response was too large to buffer and was dropped.',
                'The last request in the chain failed without a status of its own.',
                'The cache could not be reached and the origin refused the request.',
            ];
            $lh_trace = [
                "PHP Fatal error:  Uncaught Error: Call to a member function get() on null in /srv/app/bootstrap/cache/services.php:%d\nStack trace:\n#0 /srv/app/public/index.php(37): illuminate()\n#1 {main}\n  thrown in /srv/app/bootstrap/cache/services.php on line %d",
                "connect to service at 127.0.0.1:%d failed (Connection refused)\n  at Connection.open (%h:%d)\n  at Pool.checkout (%h:%d)\n  at Route.dispatch (%h:%d)",
                "SQLSTATE[HY000] [2002] No such file or directory\n  in PDO->connect() (%h:%d)\n  in Database->reconnect() (%h:%d)",
                "ERR max number of clients reached\n  at Client.accept(%h:%d)\n  at Worker.loop(%h:%d)",
                "worker process %d exited on signal 11 (core dumped)\n  at Supervisor.reap(%h:%d)",
                "file_put_contents(/srv/app/storage/framework/views/%h.php): Failed to open stream: Read-only file system\n  at View->compile(%h:%d)",
                "SSL_do_handshake() failed (SSL: error:14094410:SSL routines:ssl3_read_bytes:sslv3 alert handshake failure)\n  at Proxy.forward(%h:%d)",
                "Premature end of script headers\n  at CGI::run(%h:%d)",
            ];
            $lh_hex  = static function (int $n): string {
                $s = '';
                for ($i = 0; $i < $n; $i++) $s .= dechex(mt_rand(0, 15));
                return $s;
            };
            $lh_code = $lh_codes[array_rand($lh_codes)];
            $lh_head = $lh_heads[array_rand($lh_heads)];
            $lh_note = $lh_detail[array_rand($lh_detail)];
            $lh_ref  = strtoupper(base_convert((string) mt_rand(100000000, 999999999), 10, 36)) . '-'
                     . strtoupper($lh_hex(4)) . '-' . strtoupper($lh_hex(4)) . '-'
                     . strtoupper(base_convert((string) mt_rand(100000, 999999), 10, 36));
            /* %d = a line number, %h = a host, filled in per occurrence so a
               template may carry as many of them as it likes. */
            $lh_trc  = $lh_trace[array_rand($lh_trace)]
                     . "\n  at Request->run(%h:%d)\n  at Server->accept(%h:%d)";
            $lh_trc  = preg_replace_callback('/%([dh])/', static function (array $m): string {
                return $m[1] === 'h'
                    ? '10.' . mt_rand(0, 40) . '.' . mt_rand(1, 250) . '.' . mt_rand(2, 250)
                    : (string) mt_rand(20, 899);
            }, $lh_trc);
            $lh_req  = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) . ' '
                     . preg_replace('/[^A-Za-z0-9\-._~\/?=&%#]/', '', (string) ($_SERVER['REQUEST_URI'] ?? '/'));
            $lh_when = gmdate('D, d M Y H:i:s') . ' GMT';
            $lh_size = mt_rand(4096, 262144);
            $lh_secs = sprintf('%.2f', mt_rand(100, 4500) / 100);
            ?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Website has an error</title>
<style>
  html,body{margin:0;padding:0;background:#fff;color:#000}
  body{padding:24px 28px 44px;font-family:"Times New Roman",Times,serif;font-size:15px;line-height:1.45}
  h1{margin:0;font-size:27px;font-weight:bold}
  h2{margin:24px 0 6px;font-size:14px;font-weight:bold;text-transform:uppercase;letter-spacing:.05em}
  hr{border:0;border-top:2px solid #000;margin:8px 0 16px}
  p{margin:8px 0}
  .num{color:#a00;font-weight:bold}
  table{border-collapse:collapse;font:13px Verdana,Arial,sans-serif}
  td{border:1px solid #999;padding:3px 9px;vertical-align:top}
  td.k{background:#eee;font-weight:bold;white-space:nowrap}
  pre{margin:6px 0 0;padding:10px;background:#eee;border:1px solid #999;font:12px/1.5 "Courier New",Courier,monospace;white-space:pre-wrap;word-break:break-all}
  ul{margin:6px 0 0;padding-left:22px}
  li{margin:3px 0}
  address{margin-top:28px;padding-top:6px;border-top:1px solid #999;font:12px Verdana,Arial,sans-serif;font-style:normal;color:#555}
</style></head>
<body>
  <h1>Error <span class="num"><?= (int) $lh_code ?></span> - <?= e($lh_head) ?></h1>
  <hr>
  <p><b>Website has an error.</b> <?= e($lh_note) ?> The page you asked for could not be
     delivered. Reloading it, or asking again in a few minutes, may or may not help.</p>

  <h2>Request</h2>
  <table>
    <tr><td class="k">Line</td><td><?= e($lh_req) ?></td></tr>
    <tr><td class="k">Status</td><td><?= (int) $lh_code ?> <?= e($lh_head) ?></td></tr>
    <tr><td class="k">Reference</td><td><?= e($lh_ref) ?></td></tr>
    <tr><td class="k">Recorded</td><td><?= e($lh_when) ?></td></tr>
  </table>

  <h2>Log entry</h2>
  <pre>[<?= e($lh_when) ?>] [client 10.<?= (int) mt_rand(0, 40) ?>.<?= (int) mt_rand(1, 250) ?>.<?= (int) mt_rand(2, 250) ?>] <?= (int) $lh_code ?> <?= e($lh_req) ?> ref=<?= e($lh_ref) ?> bytes=<?= (int) $lh_size ?><?= "\n" . e($lh_trc) ?></pre>

  <h2>Try this</h2>
  <ul>
    <li>Reload the page.</li>
    <li>Go back one step and take another route to it.</li>
    <li>Clear the stored copy of this page in your browser and reload.</li>
    <li>If it keeps happening, pass the reference <b><?= e($lh_ref) ?></b> to whoever runs this server.</li>
  </ul>

  <address>HTTP/1.1 <?= (int) $lh_code ?> - gateway worker <?= e($lh_hex(3)) ?> - <?= e($lh_secs) ?> s elapsed</address>
</body></html><?php
            exit;
        }
    }
    unset(
        $lh_self, $lh_me, $lh_codes, $lh_heads, $lh_detail, $lh_trace, $lh_hex,
        $lh_code, $lh_head, $lh_note, $lh_ref, $lh_trc, $lh_req, $lh_when,
        $lh_size, $lh_secs
    );
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

/** Every teacher with their courses and ALL their lessons nested — the admin's
 *  "lessons by teacher" page (admin_lessons.php). Three things shape it:
 *  - the course/lesson lists come from load_courses(), so the lesson ORDER is
 *    the same one the course page, the offline bundle and bulk.php use (the
 *    sort_order rule lives in that one function, on purpose);
 *  - teachers who have not created a course yet still appear with an empty
 *    list, because "every teacher" is the whole point of this view;
 *  - a non-teacher account that owns a course is included too (the WHERE keeps
 *    any course owner), so no lesson can hide behind a role label.
 *  Read-only: it reports what exists — opening lesson CONTENT stays where the
 *  app has always put it (the owning teacher and the enrolled students). */
function admin_lessons_by_teacher(): array
{
    $byId = [];
    $st = db()->query("SELECT u.id, u.name, u.avatar FROM users u
                       WHERE u.role = 'teacher'
                          OR EXISTS (SELECT 1 FROM courses c WHERE c.teacher_id = u.id)
                       ORDER BY u.name ASC, u.id ASC");
    foreach ($st->fetchAll() as $t) {
        $byId[(int) $t['id']] = [
            'id'      => (int) $t['id'],
            'name'    => (string) $t['name'],
            'avatar'  => (string) ($t['avatar'] ?? ''),
            'courses' => [],
            'lessons' => 0,
        ];
    }
    foreach (load_courses() as $c) {
        $tid = (int) ($c['teacher_id'] ?? 0);
        if (!isset($byId[$tid])) continue;      /* orphaned owner: nothing to attach to */
        $byId[$tid]['courses'][] = $c;
        $byId[$tid]['lessons'] += count($c['materials'] ?? []);
    }
    return array_values($byId);
}

/** Users currently online (heartbeat within PRESENCE_TIMEOUT), newest heartbeat first.
 *  u.avatar comes along so an online list can print a real circle through
 *  user_peer_avatar_html() instead of drawing the initial itself. */
function admin_online_users(array $roles = []): array
{
    $roles = array_values(array_intersect($roles, ['teacher', 'student', 'admin']));
    $sql = 'SELECT u.id, u.name, u.role, u.avatar, p.last_seen FROM presence p
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
        if ($withAnswers) {
            $item['correct'] = (int) $row['correct'];
            $item['option_explanations'] = json_decode((string) ($row['option_explanations'] ?? ''), true) ?: [];
        }
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
        if ($withAnswers) {
            $item['correct'] = (int) $row['correct'];
            $item['option_explanations'] = json_decode((string) ($row['option_explanations'] ?? ''), true) ?: [];
        }
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

/** Folder quizzes keyed by folder id. */
function course_folder_quizzes(int $courseId, bool $withAnswers = false): array
{
    $st = db()->prepare('SELECT q.* FROM quizzes q JOIN course_folders f ON f.id = q.folder_id WHERE f.course_id = ? ORDER BY q.id');
    $st->execute([$courseId]);
    $quizzes = [];
    foreach ($st->fetchAll() as $quiz) $quizzes[(int) $quiz['folder_id']] = $quiz;
    if (!$quizzes) return [];
    $in = implode(',', array_fill(0, count($quizzes), '?'));
    $q = db()->prepare("SELECT * FROM quiz_questions WHERE quiz_id IN ($in) ORDER BY sort_order, id");
    $q->execute(array_keys($quizzes));
    foreach ($q->fetchAll() as $row) {
        $item = ['id' => (int) $row['id'], 'prompt' => (string) $row['prompt'], 'options' => json_decode((string) $row['options'], true) ?: []];
        if ($withAnswers) {
            $item['correct'] = (int) $row['correct'];
            $item['option_explanations'] = json_decode((string) ($row['option_explanations'] ?? ''), true) ?: [];
        }
        $quizzes[(int) $row['quiz_id']]['questions'][] = $item;
    }
    foreach ($quizzes as &$quiz) {
        $quiz['id'] = (int) $quiz['id'];
        $quiz['folder_id'] = (int) $quiz['folder_id'];
        $quiz['pass_score'] = (int) $quiz['pass_score'];
        $quiz['questions'] = $quiz['questions'] ?? [];
    }
    unset($quiz);
    return $quizzes;
}

/** Create or fully replace the quiz of a lesson. $questions include prompt, options, correct and optional answer explanations. */
function save_quiz(int $materialId, string $title, int $passScore, array $questions): int
{
    return save_quiz_for('material_id', $materialId, $title, $passScore, $questions);
}

function save_folder_quiz(int $folderId, string $title, int $passScore, array $questions): int
{
    return save_quiz_for('folder_id', $folderId, $title, $passScore, $questions);
}

/** Shared validator and persistence for the two supported quiz owners. */
function save_quiz_for(string $ownerColumn, int $ownerId, string $title, int $passScore, array $questions): int
{
    if (!in_array($ownerColumn, ['material_id', 'folder_id'], true)) throw new InvalidArgumentException('Invalid quiz owner.');
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
        $optionExplanations = [];
        $rawCorrect = (int) ($q['correct'] ?? 0);
        $correct = -1;
        $rawOptions = array_values((array) ($q['options'] ?? []));
        $rawOptionExplanations = array_values((array) ($q['option_explanations'] ?? []));
        foreach (array_slice($rawOptions, 0, 4) as $rawIndex => $opt) {
            $opt = cut(trim((string) $opt), 200);
            if ($opt !== '') {
                if ($rawIndex === $rawCorrect) $correct = count($opts);
                $opts[] = $opt;
                $optionExplanations[] = cut(trim((string) ($rawOptionExplanations[$rawIndex] ?? '')), 1000);
            }
        }
        if (count($opts) < 2) throw new RuntimeException('Question #' . ($i + 1) . ' needs at least two answer options.');
        if ($correct < 0 || $correct >= count($opts)) throw new RuntimeException('Question #' . ($i + 1) . ': mark which option is the correct one.');
        $clean[] = ['prompt' => $prompt, 'options' => $opts, 'correct' => $correct, 'option_explanations' => $optionExplanations];
    }
    $db = db();
    $db->beginTransaction();
    try {
        // fresh quiz = fresh single attempt: wipe any abandoned in-progress answers
        $q = $db->prepare("SELECT id FROM quizzes WHERE $ownerColumn = ? LIMIT 1");
        $q->execute([$ownerId]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $oldQuizId) {
            $db->prepare('DELETE FROM quiz_progress WHERE quiz_id = ?')->execute([(int) $oldQuizId]);
        }
        $db->prepare("DELETE FROM quizzes WHERE $ownerColumn = ?")->execute([$ownerId]);
        $db->prepare("INSERT INTO quizzes ($ownerColumn, title, pass_score, created_at) VALUES (?,?,?,?)")
            ->execute([$ownerId, $title, $passScore, time()]);
        $quizId = (int) $db->lastInsertId();
        $ins = $db->prepare('INSERT INTO quiz_questions (quiz_id, prompt, options, correct, option_explanations, sort_order) VALUES (?,?,?,?,?,?)');
        foreach ($clean as $i => $q) {
            $ins->execute([$quizId, $q['prompt'], json_encode($q['options'], JSON_UNESCAPED_UNICODE), $q['correct'], json_encode($q['option_explanations'], JSON_UNESCAPED_UNICODE), $i]);
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

function delete_folder_quiz(int $folderId): bool
{
    $st = db()->prepare('DELETE FROM quizzes WHERE folder_id = ?');
    $st->execute([$folderId]);
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
                   SELECT q.id, ?, q.material_id, q.title, COALESCE(m.title, f.name), ?, ?, ?, ?, ?, ?
                   FROM quizzes q
                   LEFT JOIN materials m ON m.id = q.material_id
                   LEFT JOIN course_folders f ON f.id = q.folder_id
                   WHERE q.id = ?
                   ON DUPLICATE KEY UPDATE correct = VALUES(correct), total = VALUES(total), percentage = VALUES(percentage), status = VALUES(status), answers = VALUES(answers), created_at = VALUES(created_at)')
        ->execute([$userId, $correct, $total, $percentage, $status, json_encode($answers, JSON_UNESCAPED_UNICODE), time(), $quizId]);
    clear_quiz_progress($quizId, $userId);

    // real event -> notification: the student just got their result
    $pctTxt = rtrim(rtrim(number_format($percentage, 2), '0'), '.');
    add_notification($userId, 'result',
        $status === 'PASSED' ? '🏆 Quiz passed: ' . (string) $quiz['title'] : '📝 Quiz result: ' . (string) $quiz['title'],
        "You scored {$correct}/{$total} ({$pctTxt}%) — " . ($status === 'PASSED' ? 'passed!' : 'not passed yet.'),
        'my_records.php');
    $result = quiz_result_for($quizId, $userId);
    if ($result !== null) send_quiz_result_email($userId, (string) ($quiz['title'] ?? 'the quiz'), $result);
    return $result;
}

/** All quiz results of one student, newest first (course + lesson titles joined). */
function student_quiz_records(int $userId): array
{
    $st = db()->prepare('SELECT qr.*, c.title AS course_title, c.id AS course_id, q.folder_id AS folder_id
                         FROM quiz_results qr
                         JOIN quizzes q ON q.id = qr.quiz_id
                         LEFT JOIN materials m ON m.id = q.material_id
                         LEFT JOIN course_folders f ON f.id = q.folder_id
                         JOIN courses c ON c.id = COALESCE(m.course_id, f.course_id)
                         WHERE qr.user_id = ?
                         ORDER BY qr.created_at DESC, qr.id DESC');
    $st->execute([$userId]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $r['id'] = (int) $r['id'];
        $r['quiz_id'] = (int) $r['quiz_id'];
        $r['lesson_id'] = (int) $r['lesson_id'];
        $r['course_id'] = (int) $r['course_id'];
        $r['folder_id'] = $r['folder_id'] === null ? null : (int) $r['folder_id'];
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
    $st = db()->prepare('SELECT qr.*, u.name AS student_name, c.title AS course_title, c.id AS course_id, q.material_id AS material_id, q.folder_id AS folder_id
                         FROM quiz_results qr
                         JOIN quizzes q ON q.id = qr.quiz_id
                         LEFT JOIN materials m ON m.id = q.material_id
                         LEFT JOIN course_folders f ON f.id = q.folder_id
                         JOIN courses c ON c.id = COALESCE(m.course_id, f.course_id)
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
        $r['material_id'] = $r['material_id'] === null ? null : (int) $r['material_id'];
        $r['folder_id'] = $r['folder_id'] === null ? null : (int) $r['folder_id'];
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
 * enrolled AND have completed the lesson or folder the quiz is attached to.
 * The main admin passes this gate too — treated exactly like the owner, i.e. a
 * read-only preview (answers highlighted) that quiz_answer.php refuses to
 * record, same split as every other teacher gate in the app.
 * Returns [course, quiz (with answers), isOwner, folder]; exits with a friendly page otherwise.
 */
function require_quiz_access(int $userId, int $courseId, int $materialId, int $folderId = 0): array
{
    $course = course_row($courseId);
    if (!$course) {
        quiz_gate_page(404, '📘', 'Course not found', 'This course does not exist (or was deleted).', 'courses.php', 'Back to courses');
    }
    $isOwner = (int) $course['teacher_id'] === $userId;
    if (!$isOwner) {
        $me = current_user();
        if ($me !== null && (int) ($me['id'] ?? 0) === $userId && ($me['role'] ?? '') === 'admin') {
            $isOwner = true;
        }
    }
    if (!$isOwner && !is_enrolled_id($courseId, $userId)) {
        quiz_gate_page(403, '🔒', 'Not enrolled', 'Enroll in this course to open its folder and lesson quizzes.', 'course.php?id=' . $courseId, 'Back to the course');
    }
    $folder = null;
    if ($folderId > 0 && $materialId === 0) {
        $folder = course_folder_row($courseId, $folderId);
        if (!$folder) quiz_gate_page(404, '📁', 'Folder not found', 'This folder does not exist (or was deleted).', 'course.php?id=' . $courseId, 'Back to the course');
        $folderQuizzes = course_folder_quizzes($courseId, true);
        $quiz = $folderQuizzes[$folderId] ?? null;
    } elseif ($materialId > 0 && $folderId === 0 && course_material_exists($courseId, $materialId)) {
        $quiz = lesson_quiz($materialId, true);
    } else {
        quiz_gate_page(404, '🧪', 'Quiz not found', 'This quiz does not exist (or was deleted).', 'course.php?id=' . $courseId, 'Back to the course');
    }
    if (!$quiz || !$quiz['questions']) {
        quiz_gate_page(404, '🧪', 'No quiz assigned', 'The teacher has not assigned a quiz to this folder or lesson yet.', 'course.php?id=' . $courseId, 'Back to the course');
    }
    if (!$isOwner && $folderId > 0) {
        $folderProgress = course_folder_progress($folderId, $userId);
        if ($folderProgress['total'] === 0 || $folderProgress['pct'] < 100) {
            quiz_gate_page(403, '🔒', 'Quiz locked', 'Complete every lesson in this folder (100%) to unlock its quiz.', 'course.php?id=' . $courseId, 'Back to the course');
        }
    } elseif (!$isOwner) {
        if (!material_completed($userId, $materialId)) {
            quiz_gate_page(403, '🔒', 'Quiz locked', 'Complete this lesson to unlock its quiz.', 'course.php?id=' . $courseId, 'Back to the course');
        }
    }
    return ['course' => $course, 'quiz' => $quiz, 'isOwner' => $isOwner, 'folder' => $folder];
}

/** Friendly blocked/missing page used by the quiz gate. */
function quiz_gate_page(int $code, string $icon, string $title, string $msg, string $backHref, string $backLabel): void
{
    http_response_code($code);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title) . ' · LearnHub</title><link rel="stylesheet" href="assets/tailwind.min.css">'
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
    $optionExplanations = (array) ($q['option_explanations'] ?? []);
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
        $optionExplanation = (string) ($optionExplanations[$i] ?? '');
        $hasOptionExplanation = trim($optionExplanation) !== '';
        $h .= '<div><div class="flex items-center gap-1.5"><span class="w-4 shrink-0 text-center text-[11px] font-bold text-slate-400">' . ($i + 1) . '</span>'
            . '<input name="o' . ($i + 1) . '[]" maxlength="200" placeholder="' . $ph . '" value="' . e((string) ($opts[$i] ?? '')) . '"' . $req . ' class="' . $inp . '"></div>'
            . '<button type="button" data-explanation-toggle aria-expanded="' . ($hasOptionExplanation ? 'true' : 'false') . '" class="mt-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800">' . ($hasOptionExplanation ? '− Hide explanation' : '+ Add explanation') . '</button>'
            . '<label data-explanation-input' . ($hasOptionExplanation ? ' class="mt-1 block"' : ' class="mt-1 block hidden"') . '><span class="sr-only">Explanation for option ' . ($i + 1) . '</span>'
            . '<textarea name="option_explanation_' . ($i + 1) . '[]" maxlength="1000" rows="1" placeholder="Explain this answer" class="' . $inp . '">' . e($optionExplanation) . '</textarea></label></div>';
    }
    $h .= '</div>';
    $h .= '<label class="mt-2 flex items-center gap-2 text-xs font-medium text-slate-500">✔ Correct answer '
        . '<select name="correct[]" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs outline-none focus:border-indigo-500">';
    for ($i = 0; $i < 4; $i++) $h .= '<option value="' . $i . '"' . ($correct === $i ? ' selected' : '') . '>Option ' . ($i + 1) . '</option>';
    $h .= '</select></label>';
    $h .= '</div>';
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
/** And the exact opposite of touch_presence(): the user is offline RIGHT NOW, even
 *  though their PHP session lives on (they never logged out). Called by ping.php's
 *  v=offline beacon — the browser's `offline` event, fired while the link may still
 *  deliver — it backdates last_seen past PRESENCE_TIMEOUT, so every online list
 *  flips them off on its very next query and close_stale_attendance() finishes the
 *  open visit at that same moment. A beacon that never reaches us (a true outage,
 *  where there is nothing left to deliver it to) changes nothing: the untouched
 *  last_seen ages out of PRESENCE_TIMEOUT on its own, which remains the fallback. */
function offline_user(int $userId): void
{
    db()->prepare('UPDATE presence SET last_seen = ? WHERE user_id = ?')
        ->execute([time() - PRESENCE_TIMEOUT - 1, $userId]);
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

/** Log that a user entered a course (one row per visit). Returns the visit's entered_at (unix seconds). */
function record_attendance(int $userId, int $courseId): int
{
    // close the previous still-open session so each page visit becomes its own entry
    db()->prepare('UPDATE attendance SET left_at = ? WHERE user_id = ? AND course_id = ? AND left_at IS NULL')
        ->execute([time() - 5, $userId, $courseId]);
    $at = time();
    db()->prepare('INSERT INTO attendance (user_id, course_id, entered_at, left_at, ip) VALUES (?,?,?,NULL,?)')
        ->execute([$userId, $courseId, $at, $_SERVER['REMOTE_ADDR'] ?? '']);
    return $at;
}

/** Grace window for continuing a visit after an in-site hop (course → lesson /
 *  quiz / live room): the leave-beacon closed the row during navigation and the
 *  study page re-opens it, so one study session stays ONE attendance row. */
const ATTENDANCE_RESUME_GRACE = 15;

/** Continue the current visit instead of starting a new row: a still-open row is
 *  returned untouched, a row closed within ATTENDANCE_RESUME_GRACE is re-opened
 *  (entered_at preserved — the teacher's live counter never restarts), otherwise
 *  a fresh visit is recorded. Returns entered_at (unix seconds). */
function resume_attendance(int $userId, int $courseId): int
{
    $st = db()->prepare('SELECT entered_at FROM attendance
                         WHERE user_id = ? AND course_id = ? AND left_at IS NULL
                         ORDER BY id DESC LIMIT 1');
    $st->execute([$userId, $courseId]);
    if ($row = $st->fetch()) return (int) $row['entered_at'];

    $st = db()->prepare('SELECT id, entered_at FROM attendance
                         WHERE user_id = ? AND course_id = ? AND left_at IS NOT NULL AND left_at >= ?
                         ORDER BY id DESC LIMIT 1');
    $st->execute([$userId, $courseId, time() - ATTENDANCE_RESUME_GRACE]);
    if ($row = $st->fetch()) {
        db()->prepare('UPDATE attendance SET left_at = NULL WHERE id = ? AND left_at IS NOT NULL')
            ->execute([(int) $row['id']]);
        return (int) $row['entered_at'];
    }
    return record_attendance($userId, $courseId);
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

/* ---------------- live classes (teacher-initiated, Zoom-like) ---------------- */

/** Is this the teacher who owns the course — the only person who may start or end
 *  its live class? Deliberately narrower than live_class_can_join(): hosting is
 *  the teacher's alone, so the main admin can sit in on a class without ever
 *  being able to close one on its teacher. */
function live_class_is_host(array $user, array $course): bool
{
    return ($user['role'] ?? '') === 'teacher'
        && (int) ($course['teacher_id'] ?? 0) === (int) ($user['id'] ?? 0);
}

/** May this person sit in this course's live class? The owning teacher, the
 *  enrolled students, and the main admin — who is not enrolled in anything by
 *  definition but has to be able to observe any class in the school (see the
 *  live courses list on the admin page). Every page and API action that admits
 *  someone to a room goes through this one line, so the rule is stated once. */
function live_class_can_join(array $user, array $course): bool
{
    if (($user['role'] ?? '') === 'admin') return true;
    if (live_class_is_host($user, $course)) return true;
    return is_enrolled($course, (int) ($user['id'] ?? 0));
}

/** Every class running right now, newest first, each row naming its course and
 *  its teacher so the admin page can list them all at once. The one school-wide
 *  read in the app, and it is read-only: it exposes who is teaching, never a
 *  way to take a room over (starting and ending stay behind live_class_is_host). */
function live_classes_now(): array
{
    return db()->query("SELECT lc.id, lc.course_id, lc.title, lc.started_at, lc.host_id,
                               c.title AS course_title, u.name AS host_name
                        FROM live_classes lc
                        JOIN courses c ON c.id = lc.course_id
                        JOIN users u ON u.id = lc.host_id
                        WHERE lc.status = 'live'
                        ORDER BY lc.started_at DESC
                        LIMIT 50")->fetchAll();
}

/** The one currently-live class for a course (null when none). */
function live_class_active(int $courseId): ?array
{
    $st = db()->prepare("SELECT id, course_id, host_id, title, status, started_at FROM live_classes WHERE course_id = ? AND status = 'live' LIMIT 1");
    $st->execute([$courseId]);
    $row = $st->fetch();
    return $row === false ? null : array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $row);
}

/** The live class a user is currently sitting in (across all courses), if any. */
function live_class_for_user(int $userId): ?array
{
    $st = db()->prepare("SELECT lc.id, lc.course_id, lc.host_id, lc.title, lc.started_at, c.title AS course_title
                         FROM live_class_state s
                         JOIN live_classes lc ON lc.id = s.class_id AND lc.status = 'live'
                         JOIN courses c ON c.id = lc.course_id
                         WHERE s.user_id = ? AND s.last_seen >= ?
                         ORDER BY s.last_seen DESC LIMIT 1");
    $st->execute([$userId, time() - 90]);
    $row = $st->fetch();
    if ($row === false) return null;
    $row['id'] = (int) $row['id'];
    $row['course_id'] = (int) $row['course_id'];
    $row['host_id'] = (int) $row['host_id'];
    $row['started_at'] = (int) $row['started_at'];
    return $row;
}

/** Teacher starts a class; returns the live row. Only one live class per course (DB-enforced). */
function live_class_start(int $courseId, int $hostId, string $title = 'Live class'): array
{
    /* end any stale live row first (shouldn't exist thanks to the UNIQUE key, but be safe) */
    db()->prepare("UPDATE live_classes SET status = 'ended', ended_at = ? WHERE course_id = ? AND status = 'live'")
        ->execute([time(), $courseId]);
    db()->prepare('INSERT INTO live_classes (course_id, host_id, title, status, started_at) VALUES (?,?,?,\'live\',?)')
        ->execute([$courseId, $hostId, $title !== '' ? $title : 'Live class', time()]);
    $id = (int) db()->lastInsertId();
    /* the host is in the room from the start */
    db()->prepare('INSERT INTO live_class_state (class_id, user_id, joined_at, last_seen, hand_raised) VALUES (?,?,?,?,0)
                   ON DUPLICATE KEY UPDATE joined_at = VALUES(joined_at), last_seen = VALUES(last_seen)')
        ->execute([$id, $hostId, time(), time()]);
    $st = db()->prepare('SELECT id, course_id, host_id, title, started_at FROM live_classes WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    $row['id'] = (int) $row['id'];
    $row['course_id'] = (int) $row['course_id'];
    $row['host_id'] = (int) $row['host_id'];
    $row['started_at'] = (int) $row['started_at'];
    return $row;
}

/** Teacher ends the class. Only the host may end it. */
function live_class_end(int $classId, int $hostId): bool
{
    $st = db()->prepare("UPDATE live_classes SET status = 'ended', ended_at = ? WHERE id = ? AND host_id = ? AND status = 'live'");
    $st->execute([time(), $classId, $hostId]);
    return $st->rowCount() > 0;
}

/** Heartbeat from a participant still in the room (insert-or-refresh). */
function live_class_heartbeat(int $classId, int $userId): void
{
    db()->prepare('INSERT INTO live_class_state (class_id, user_id, joined_at, last_seen, hand_raised) VALUES (?,?,?,?,0)
                   ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)')
        ->execute([$classId, $userId, time(), time()]);
}

/**
 * Attendance for live classes: the first heartbeat of a participant continues
 * (or opens) an attendance row for that course — the same table (and thus the
 * same pages: attendance_day.php, enrollments.php, realtime.php) that records
 * course-page visits. Continuation matters here: the course page's leave-beacon
 * closed the visit while navigating into the room, and re-opening that same row
 * keeps the student's time counted as ONE session. The row stays open until the
 * participant leaves the site (pagehide beacon / stale-presence closer) and
 * re-opens automatically if they drop and come back. Students only, exactly like
 * course-page attendance (course.php).
 */
function live_class_attendance(int $userId, int $courseId, bool $isStudent = true): void
{
    if (!$isStudent) return;                 /* course.php logs student entries only */
    $st = db()->prepare('SELECT id FROM attendance WHERE user_id = ? AND course_id = ? AND left_at IS NULL LIMIT 1');
    $st->execute([$userId, $courseId]);
    if ($st->fetchColumn()) return;          /* row already open — nothing to do */
    resume_attendance($userId, $courseId);   /* continue the visit just closed by navigation (or open one) */
}

/** Raise / lower a participant's hand. */
function live_class_set_hand(int $classId, int $userId, bool $raised): void
{
    db()->prepare('UPDATE live_class_state SET hand_raised = ? WHERE class_id = ? AND user_id = ?')
        ->execute([$raised ? 1 : 0, $classId, $userId]);
}

/** Everyone currently in the room (fresh heartbeat in the last 90 s). */
function live_class_participants(int $classId): array
{
    $st = db()->prepare('SELECT s.user_id, s.joined_at, s.hand_raised, u.name, u.role
                         FROM live_class_state s JOIN users u ON u.id = s.user_id
                         WHERE s.class_id = ? AND s.last_seen >= ?
                         ORDER BY s.joined_at ASC');
    $st->execute([$classId, time() - 90]);
    return array_map(fn ($r) => [
        'id' => (int) $r['user_id'],
        'name' => (string) $r['name'],
        'role' => (string) $r['role'],
        'joined_at' => (int) $r['joined_at'],
        'hand' => (int) $r['hand_raised'] === 1,
    ], $st->fetchAll());
}

/** Send "live now" notifications to every enrolled student (skip the host). */
function live_class_notify_students(int $courseId, int $hostId, string $courseTitle, int $classId): void
{
    $st = db()->prepare('SELECT e.user_id FROM enrollments e JOIN users u ON u.id = e.user_id
                         WHERE e.course_id = ? AND u.role = \'student\' AND e.user_id <> ?');
    $st->execute([$courseId, $hostId]);
    /* straight to the room (not the course page): one tap to join */
    $link = live_class_join_link($courseId);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        add_notification((int) $uid, 'live', '🔴 ' . $courseTitle . ' is live now', 'Your teacher started a live class — tap to join.', $link);
    }
}

/* ---------------- live class: the shareable join link ----------------
 * The teacher copies ONE link and pastes it into Messenger, a group chat, an
 * SMS or an e-mail. It points at live_join.php instead of class_room.php
 * because a link pasted into a chat arrives in every state imaginable: the
 * person may not be logged in yet, may not be enrolled, or may tap it before
 * the teacher starts the room. live_join.php handles all three and drops them
 * into the room the moment it is live.
 *
 * The course id is encrypted (lh_enc_id), like every other id this app puts in
 * a URL, so a link cannot be guessed by counting courses.
 * -------------------------------------------------------------------- */

/** Relative join link — used for redirects and notifications. */
function live_class_join_link(int $courseId): string
{
    return 'live_join.php?course=' . rawurlencode(lh_enc_id($courseId));
}

/** Absolute join link — what the teacher copies and shares. */
function live_class_join_url(int $courseId): string
{
    return app_link(live_class_join_link($courseId));
}

/** The ready-to-send invitation message (course name + link). */
function live_class_invite_text(array $course): string
{
    $title = trim((string) ($course['title'] ?? ''));
    if ($title === '') $title = 'our class';
    $who = trim((string) ($course['teacher_name'] ?? ''));
    return '🔴 Live class now: ' . $title . ($who !== '' ? ' with ' . $who : '')
        . "\nJoin here: " . live_class_join_url((int) ($course['id'] ?? 0))
        . "\n(Log in to LearnHub if it asks — then you go straight into the room.)";
}

/* ---------------- live-class video server (Jitsi) ----------------
 * The free public server meet.jit.si will no longer be EMBEDDED. Inside an
 * iframe it pops up
 *   "Embedding meet.jit.si is only meant for demo purposes, so this call will
 *    disconnect in 5 minutes. Please use Jitsi as a Service for production
 *    embedding!"
 * and then hangs the call up. That is an 8x8 policy for that one domain — not a
 * bug here, and no config flag switches it off — so an hour-long class can never
 * be embedded from meet.jit.si. Three ways out, all wired up below:
 *
 *   1. window — do not embed at all: the call opens in its OWN browser window,
 *               free of the 5-minute embed cut. One catch: meet.jit.si still ends
 *               meetings opened while nobody is signed in after 60 minutes
 *               ("Meeting time limit reached") — the host lifts that cap by
 *               signing in once in the meeting window. Free and instant; this is
 *               what "auto" mode picks for meet.jit.si.
 *   2. JaaS   — Jitsi as a Service (jaas.8x8.vc). Free plan: unlimited minutes,
 *               25 endpoints, real embedding, host controls and no 5-minute cut.
 *               Paste AppID + API key id + private key in Settings → Live class
 *               video and this file signs the RS256 JWT for every member.
 *   3. self   — your own Jitsi server: set JITSI_DOMAIN (see config.sample.php).
 *
 * Resolution order per value: config.php constant -> saved setting -> default.
 * ------------------------------------------------------------------- */
if (!defined('JITSI_DOMAIN'))                define('JITSI_DOMAIN', '');
if (!defined('JITSI_EMBED'))                 define('JITSI_EMBED', '');
if (!defined('JITSI_JAAS_APP_ID'))           define('JITSI_JAAS_APP_ID', '');
if (!defined('JITSI_JAAS_KID'))              define('JITSI_JAAS_KID', '');
if (!defined('JITSI_JAAS_PRIVATE_KEY'))      define('JITSI_JAAS_PRIVATE_KEY', '');
if (!defined('JITSI_JAAS_PRIVATE_KEY_FILE')) define('JITSI_JAAS_PRIVATE_KEY_FILE', '');

/** Domains that refuse to be embedded: they cut an iframed call after 5 min. */
const LH_JITSI_DEMO_DOMAINS = ['meet.jit.si'];

/** Can this PHP sign a JaaS token at all? */
function jitsi_jwt_supported(): bool
{
    return function_exists('openssl_sign') && function_exists('openssl_pkey_get_private');
}

/** Keys pasted into a web form (or a one-line .env) lose their line breaks;
 *  rebuild a valid PEM so OpenSSL accepts them again. */
function jitsi_normalise_pem(string $key): string
{
    $key = trim(str_replace(["\r\n", "\r", '\\n', '\\r'], "\n", $key));
    $key = trim($key, " \t\n\"'");
    if ($key === '') return '';
    /* clipboard damage also eats the spaces inside the banner:
       "-----BEGINPRIVATEKEY-----" -> "-----BEGIN PRIVATE KEY-----" */
    $key = (string) preg_replace('#-----\s*BEGIN\s*([A-Za-z]*?)\s*KEY\s*-----#', '-----BEGIN $1 KEY-----', $key);
    $key = (string) preg_replace('#-----\s*END\s*([A-Za-z]*?)\s*KEY\s*-----#', '-----END $1 KEY-----', $key);
    if (preg_match('#(-----BEGIN [A-Za-z ]*KEY-----)(.*?)(-----END [A-Za-z ]*KEY-----)#s', $key, $m)) {
        $body = (string) preg_replace('#[^A-Za-z0-9+/=]#', '', $m[2]);
        return $m[1] . "\n" . chunk_split($body, 64, "\n") . $m[3] . "\n";
    }
    /* body only, no markers: wrap it as a PKCS#8 private key */
    $bare = (string) preg_replace('#\s#', '', $key);
    if (strlen($bare) > 100 && preg_match('#^[A-Za-z0-9+/=]+$#', $bare)) {
        return "-----BEGIN PRIVATE KEY-----\n" . chunk_split($bare, 64, "\n") . "-----END PRIVATE KEY-----\n";
    }
    return $key;
}

/** JaaS credentials — empty strings when JaaS is not configured. */
function jitsi_jaas_cfg(): array
{
    $app = JITSI_JAAS_APP_ID !== '' ? trim((string) JITSI_JAAS_APP_ID) : trim(setting_get('jitsi_jaas_app_id', ''));
    $kid = JITSI_JAAS_KID !== '' ? trim((string) JITSI_JAAS_KID) : trim(setting_get('jitsi_jaas_kid', ''));
    $key = JITSI_JAAS_PRIVATE_KEY !== '' ? (string) JITSI_JAAS_PRIVATE_KEY : setting_get('jitsi_jaas_private_key', '');
    if (trim($key) === '' && JITSI_JAAS_PRIVATE_KEY_FILE !== '' && is_readable((string) JITSI_JAAS_PRIVATE_KEY_FILE)) {
        $key = (string) file_get_contents((string) JITSI_JAAS_PRIVATE_KEY_FILE);
    }
    $key = jitsi_normalise_pem($key);
    /* the console shows only the key id; the JWT wants "<AppID>/<key id>" */
    if ($app !== '' && $kid !== '' && strpos($kid, '/') === false) $kid = $app . '/' . $kid;
    return ['app_id' => $app, 'kid' => $kid, 'key' => $key];
}

/** True when AppID + kid + private key are all present AND usable. The key must
 *  really be readable: otherwise JaaS would be selected but the tokens would
 *  come out empty and nobody could join the room. */
function jitsi_jaas_ready(): bool
{
    $c = jitsi_jaas_cfg();
    return $c['app_id'] !== '' && $c['kid'] !== '' && jitsi_key_ok($c['key']);
}

/** Is this private key actually readable by OpenSSL? (cached per key) */
function jitsi_key_ok(string $key): bool
{
    if ($key === '' || !jitsi_jwt_supported()) return false;
    static $cache = [];
    $h = md5($key);
    if (!array_key_exists($h, $cache)) $cache[$h] = @openssl_pkey_get_private($key) !== false;
    return $cache[$h];
}

/** Which server live classes use ("8x8.vc" once JaaS is configured). */
function jitsi_domain(): string
{
    if (jitsi_jaas_ready()) return '8x8.vc';
    $d = trim((string) JITSI_DOMAIN);
    if ($d === '') $d = trim(setting_get('jitsi_domain', ''));
    if ($d === '') $d = 'meet.jit.si';
    return strtolower($d);
}

/** True for the demo server that cuts embedded calls after 5 minutes. */
function jitsi_is_demo_domain(?string $domain = null): bool
{
    $d = strtolower(trim($domain ?? jitsi_domain()));
    $d = rtrim((string) preg_replace('#^https?://#', '', $d), '/');
    return in_array($d, LH_JITSI_DEMO_DOMAINS, true);
}

/** 'iframe' (video inside this page) or 'window' (own browser window = no cap). */
function jitsi_embed_mode(): string
{
    $mode = JITSI_EMBED !== '' ? strtolower(trim((string) JITSI_EMBED)) : strtolower(trim(setting_get('jitsi_embed', 'auto')));
    if ($mode !== 'iframe' && $mode !== 'window') {
        /* auto: never frame the demo server — it hangs up after 5 minutes */
        $mode = jitsi_is_demo_domain() ? 'window' : 'iframe';
    }
    return $mode;
}

/** The external_api.js URL of this server (JaaS serves one per AppID). */
function jitsi_api_script(): string
{
    $c = jitsi_jaas_cfg();
    if (jitsi_jaas_ready()) return 'https://8x8.vc/' . rawurlencode($c['app_id']) . '/external_api.js';
    return 'https://' . jitsi_domain() . '/external_api.js';
}

/** JaaS room names must carry the AppID prefix: "<AppID>/<room>". */
function jitsi_room_name(string $base): string
{
    $c = jitsi_jaas_cfg();
    return jitsi_jaas_ready() ? $c['app_id'] . '/' . $base : $base;
}

/** Sign one participant's JaaS token. Claims follow the 8x8 documentation
 *  (developer.8x8.com/jaas/docs/api-keys-jwt): aud=jitsi, iss=chat, sub=AppID,
 *  room=* and moderator as the string "true". '' when JaaS is not configured.
 *  The token also carries `exp`, so it must comfortably outlive the class. */
function jitsi_jaas_jwt(string $userId, string $userName, bool $moderator = false, int $hours = 12): string
{
    if (!jitsi_jaas_ready()) return '';
    $c = jitsi_jaas_cfg();
    $now = time();
    $hours = max(1, min(24, $hours));
    $header = ['alg' => 'RS256', 'kid' => $c['kid'], 'typ' => 'JWT'];
    $payload = [
        'aud' => 'jitsi',
        'iss' => 'chat',
        'sub' => $c['app_id'],
        'room' => '*',
        'nbf' => $now - 10,
        'exp' => $now + $hours * 3600,          /* room for a long class */
        'context' => [
            /* only the display name and a stable id travel to 8x8 — no e-mail */
            'user' => ['id' => $userId, 'name' => $userName, 'moderator' => $moderator ? 'true' : 'false'],
            'features' => [
                'livestreaming' => false,
                'recording' => false,
                'transcription' => false,
                'outbound-call' => false,
            ],
            'room' => ['regex' => false],
        ],
    ];
    $signing = lh_b64url((string) json_encode($header)) . '.' . lh_b64url((string) json_encode($payload));
    $private = @openssl_pkey_get_private($c['key']);
    if ($private === false) return '';
    $sig = '';
    if (!@openssl_sign($signing, $sig, $private, OPENSSL_ALGO_SHA256)) return '';
    return $signing . '.' . lh_b64url($sig);
}

/** Direct meeting URL — used by "own window" mode (and a plain browser tab).
 *  Jitsi reads its options from the #hash; values must be JSON-encoded. */
function jitsi_room_url(string $room, string $displayName = '', string $jwt = '', bool $prejoin = true): string
{
    $url  = 'https://' . jitsi_domain() . '/' . str_replace('%2F', '/', rawurlencode($room));
    $q    = $jwt !== '' ? '?jwt=' . rawurlencode($jwt) : '';
    $hash = [
        'config.disableDeepLinking' => 'true',
        'config.prejoinPageEnabled' => $prejoin ? 'true' : 'false',
        'config.toolbarConfig.alwaysVisible' => 'true',
        'interfaceConfig.TOOLBAR_ALWAYS_VISIBLE' => 'true',
        'config.startWithAudioMuted' => 'false',
        'config.startWithVideoMuted' => 'false',
        'config.subject' => json_encode('LearnHub live class'),
    ];
    if ($displayName !== '') $hash['userInfo.displayName'] = json_encode($displayName);
    $frag = [];
    foreach ($hash as $k => $v) $frag[] = rawurlencode($k) . '=' . rawurlencode((string) $v);
    return $url . $q . '#' . implode('&', $frag);
}

/** Summary of the live-class video setup (shown on the Settings page). */
function jitsi_status(): array
{
    $c = jitsi_jaas_cfg();
    $mode = jitsi_embed_mode();
    $has = $c['app_id'] !== '' || $c['kid'] !== '' || $c['key'] !== '';
    $keyOk = $c['key'] !== '' && jitsi_key_ok($c['key']);
    $warn = [];
    if ($has && !jitsi_jaas_ready()) {
        if (!jitsi_jwt_supported()) {
            $warn[] = 'This server\'s PHP has no OpenSSL, so JaaS tokens cannot be signed. “Own window” mode and a self-hosted server still work.';
        } elseif (!$keyOk) {
            $warn[] = 'The JaaS private key could not be read — paste the complete PEM block, BEGIN and END lines included.';
        } elseif ($c['app_id'] === '') {
            $warn[] = 'JaaS is missing its AppID.';
        } else {
            $warn[] = 'JaaS is missing its API key id (kid).';
        }
    }
    if ($mode === 'iframe' && !jitsi_jaas_ready() && jitsi_is_demo_domain()) {
        $warn[] = 'meet.jit.si disconnects embedded calls after 5 minutes — use “own window”, JaaS or your own server.';
    }
    return [
        'mode' => $mode,
        'domain' => jitsi_domain(),
        'demo' => jitsi_is_demo_domain(),
        'jaas_has' => $has,
        'jaas_ready' => jitsi_jaas_ready(),
        'key_ok' => $keyOk,
        'jwt_supported' => jitsi_jwt_supported(),
        'warnings' => $warn,
    ];
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

/** Zigzag sparkline for the teacher enrollment trend.
 *  Carries per-day hover strips (day label + value) for app.js's tooltip. */
function zigzag_svg(array $vals, string $stroke = '#4f46e5', string $fill = 'rgba(79,70,229,0.16)', ?array $labels = null, string $metricName = 'Enrollments'): string
{
    $vals = array_values(array_map('intval', $vals));
    if (!$vals) $vals = [0, 0];
    if (count($vals) === 1) $vals[] = $vals[0];
    $w = 100; $h = 30; $top = 4; $base = $h - 2;
    $max = max($vals);
    if ($max <= 0) $max = 1;
    $n = count($vals);
    if ($labels === null || !count($labels)) $labels = array_map(fn ($i) => 'Day ' . ($i + 1), range(0, $n - 1));
    $labels = array_pad(array_values(array_map('strval', $labels)), $n, '');
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
    $style = ui_chart_style();
    $svg  = '<svg viewBox="0 0 100 30" preserveAspectRatio="none" class="lh-chart lh-chart-style-' . e($style) . ' h-full w-full" aria-hidden="true">';
    if ($style === 'area') {
        $svg .= '<polygon points="2,' . $base . ' ' . $line . ' ' . $w . ',' . $base . '" fill="' . $fill . '"></polygon>';
    }
    $svg .= '<line x1="0" y1="' . $base . '" x2="' . $w . '" y2="' . $base . '" stroke="#cbd5e1" stroke-width="1" vector-effect="non-scaling-stroke"></line>';
    if ($style === 'smooth') {
        $svg .= '<path d="' . dashboard_chart_smooth_path($peaks) . '" fill="none" stroke="' . $stroke . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>';
    } else {
        $lineWidth = $style === 'thin' ? '1.1' : '2';
        $svg .= '<polyline points="' . $line . '" fill="none" stroke="' . $stroke . '" stroke-width="' . $lineWidth . '" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>';
    }
    if ($style === 'points') {
        foreach ($peaks as $point) {
            $svg .= '<circle cx="' . $point[0] . '" cy="' . $point[1] . '" r="1.8" fill="' . $stroke . '"></circle>';
        }
    }
    $svg .= '<circle cx="' . $last[0] . '" cy="' . $last[1] . '" r="2.6" fill="' . $stroke . '"></circle>';
    /* hover layer: guide + highlight dot, hidden until a day strip is hovered */
    $svg .= '<g class="js-chart-hover">'
        . '<line class="js-chart-guide" x1="0" y1="' . $top . '" x2="0" y2="' . $base . '" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke"></line>'
        . '<circle class="js-chart-dot" data-k="1" r="3" fill="' . $stroke . '" stroke="#fff" stroke-width="2"></circle>'
        . '</g>';
    /* day strips last → they sit on top and catch the pointer */
    for ($i = 0; $i < $n; $i++) {
        $left  = $i === 0 ? 0 : ($peaks[$i - 1][0] + $peaks[$i][0]) / 2;
        $right = $i === $n - 1 ? $w : ($peaks[$i][0] + $peaks[$i + 1][0]) / 2;
        $svg .= '<rect class="js-chart-col" x="' . round($left, 1) . '" y="0" width="' . round($right - $left, 1) . '" height="' . $h . '" fill="transparent"'
            . ' data-day="' . e((string) $labels[$i]) . '"'
            . ' data-n1="' . e($metricName) . '" data-c1="' . $stroke . '" data-a="' . $vals[$i] . '" data-ay="' . $peaks[$i][1] . '"></rect>';
    }
    $svg .= '</svg>';
    return $svg;
}

/** Dual-series activity chart (14-day activity) as inline SVG.
 *  Every day gets an invisible hover strip (js-chart-col) carrying the day label
 *  and both series values — app.js shows a tooltip + guide line + dots on hover.
 *  The site-wide ui_chart_style setting switches between the filled area and
 *  unfilled lines with daily markers. */
function activity_chart_svg(array $visits, array $completions, ?array $labels = null): string
{
    $visits = array_values(array_map('intval', $visits));
    $completions = array_values(array_map('intval', $completions));
    $n = max(count($visits), count($completions), 2);
    $visits = array_pad($visits, $n, 0);
    $completions = array_pad($completions, $n, 0);
    if ($labels === null || !count($labels)) $labels = array_map(fn ($i) => 'Day ' . ($i + 1), range(0, $n - 1));
    $labels = array_pad(array_values(array_map('strval', $labels)), $n, '');
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
    $style = ui_chart_style();
    $svg = '<svg viewBox="0 0 300 90" preserveAspectRatio="none" class="lh-chart lh-chart-style-' . e($style) . ' h-40 w-full sm:h-44" aria-hidden="true">' . $grid;
    if ($style === 'area') {
        $svg .= '<polygon points="' . $toLine($vPts) . ' ' . $w . ',' . $h . ' 0,' . $h . '" fill="rgba(79,70,229,0.10)"></polygon>';
    }
    if ($style === 'smooth') {
        $svg .= '<path d="' . dashboard_chart_smooth_path($vPts) . '" fill="none" stroke="#4f46e5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>';
        $svg .= '<path d="' . dashboard_chart_smooth_path($cPts) . '" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></path>';
    } else {
        $lineWidth = $style === 'thin' ? '1.3' : '2.5';
        $svg .= '<polyline points="' . $toLine($vPts) . '" fill="none" stroke="#4f46e5" stroke-width="' . $lineWidth . '" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>';
        $svg .= '<polyline points="' . $toLine($cPts) . '" fill="none" stroke="#10b981" stroke-width="' . $lineWidth . '" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>';
    }
    if ($style === 'points') {
        foreach ($vPts as $point) {
            $svg .= '<circle cx="' . $point[0] . '" cy="' . $point[1] . '" r="1.8" fill="#4f46e5"></circle>';
        }
        foreach ($cPts as $point) {
            $svg .= '<circle cx="' . $point[0] . '" cy="' . $point[1] . '" r="1.8" fill="#10b981"></circle>';
        }
    }
    $lv = $vPts[$n - 1]; $lc = $cPts[$n - 1];
    $svg .= '<circle cx="' . $lv[0] . '" cy="' . $lv[1] . '" r="3" fill="#4f46e5"></circle>';
    $svg .= '<circle cx="' . $lc[0] . '" cy="' . $lc[1] . '" r="3" fill="#10b981"></circle>';
    /* hover layer: guide + highlight dots, hidden until a day strip is hovered */
    $svg .= '<g class="js-chart-hover">'
        . '<line class="js-chart-guide" x1="0" y1="' . $padT . '" x2="0" y2="' . ($h - $padB) . '" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 3" vector-effect="non-scaling-stroke"></line>'
        . '<circle class="js-chart-dot" data-k="1" r="4" fill="#4f46e5" stroke="#fff" stroke-width="2"></circle>'
        . '<circle class="js-chart-dot" data-k="2" r="4" fill="#10b981" stroke="#fff" stroke-width="2"></circle>'
        . '</g>';
    /* day strips last → they sit on top and catch the pointer */
    for ($i = 0; $i < $n; $i++) {
        $left  = $i === 0 ? 0 : ($vPts[$i - 1][0] + $vPts[$i][0]) / 2;
        $right = $i === $n - 1 ? $w : ($vPts[$i][0] + $vPts[$i + 1][0]) / 2;
        $svg .= '<rect class="js-chart-col" x="' . round($left, 1) . '" y="0" width="' . round($right - $left, 1) . '" height="' . $h . '" fill="transparent"'
            . ' data-day="' . e((string) $labels[$i]) . '"'
            . ' data-n1="Course visits" data-c1="#4f46e5" data-a="' . $visits[$i] . '" data-ay="' . $vPts[$i][1] . '"'
            . ' data-n2="Completions" data-c2="#10b981" data-b="' . $completions[$i] . '" data-by="' . $cPts[$i][1] . '"></rect>';
    }
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
    $st = db()->prepare("SELECT u.id, u.name, u.avatar, p.last_seen FROM enrollments e
                         JOIN courses c ON c.id = e.course_id
                         JOIN users u ON u.id = e.user_id
                         JOIN presence p ON p.user_id = u.id
                         WHERE c.teacher_id = ? AND p.last_seen >= ? AND u.role = 'student'
                         GROUP BY u.id, u.name, u.avatar, p.last_seen ORDER BY p.last_seen DESC LIMIT " . (int) $limit);
    $st->execute([$teacherId, time() - PRESENCE_TIMEOUT]);
    return $st->fetchAll();
}

/** Today's attendance rows for a teacher's courses (newest first). */
function teacher_today_visits(int $teacherId, int $limit = 8): array
{
    $st = db()->prepare('SELECT a.id, a.user_id, a.entered_at, a.left_at, u.name AS student_name, u.avatar, c.title AS course_title
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
    $st = db()->prepare('SELECT e.created_at ts, u.id uid, u.avatar, u.name who, c.title course FROM enrollments e
                         JOIN users u ON u.id = e.user_id JOIN courses c ON c.id = e.course_id
                         WHERE c.teacher_id = ? ORDER BY e.created_at DESC LIMIT ' . (int) $limit);
    $st->execute([$teacherId]);
    foreach ($st->fetchAll() as $r) {
        $rows[] = ['ts' => (int) $r['ts'], 'kind' => 'enrolled', 'uid' => (int) $r['uid'], 'avatar' => (string) $r['avatar'], 'who' => (string) $r['who'], 'course' => (string) $r['course'], 'lesson' => ''];
    }
    $st = db()->prepare('SELECT p.completed_at ts, u.id uid, u.avatar, u.name who, c.title course, m.title lesson FROM progress p
                         JOIN users u ON u.id = p.user_id JOIN materials m ON m.id = p.material_id JOIN courses c ON c.id = m.course_id
                         WHERE c.teacher_id = ? ORDER BY p.completed_at DESC LIMIT ' . (int) $limit);
    $st->execute([$teacherId]);
    foreach ($st->fetchAll() as $r) {
        $rows[] = ['ts' => (int) $r['ts'], 'kind' => 'completed', 'uid' => (int) $r['uid'], 'avatar' => (string) $r['avatar'], 'who' => (string) $r['who'], 'course' => (string) $r['course'], 'lesson' => (string) $r['lesson']];
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

    $st = db()->prepare("SELECT e.course_id cid, u.id uid, u.name, u.avatar, c.title course FROM enrollments e
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
        $out[$key] = ['uid' => $key, 'name' => (string) $r['name'], 'avatar' => (string) $r['avatar'], 'course' => (string) $r['course'], 'course_id' => $cid, 'done' => $doneN, 'total' => $total, 'pct' => $pct];
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

/* ---------------- the owl that speaks in the dashboard banner ---------------- */

/** The owl's name. She introduces herself by it in the banner cloud, and this is
 *  the only place it is written: the banner, the live refresh and the docs all
 *  read it from here, so renaming her is one word. */
const OWL_NAME = 'Tala';

/** How many of her thoughts may be queued at the cloud. The banner shows one at
 *  a time and assets/app.js moves to the next every OWL_ROTATE_MS (20 s), so
 *  this is how many different things she can say in a single visit before the
 *  list wraps: 6 thoughts is two minutes of company. */
const OWL_THOUGHTS = 6;

/**
 * What the owl says out of her cloud, for the person looking at the dashboard:
 *   'name'     — who she is,
 *   'hi'       — she introduces herself,
 *   'say'      — the greeting for the part of the day, by their first name,
 *   'thoughts' — the things worth saying right now, ONE per line, the ones it is
 *                worth ACTING on first: a message waiting, a class coming up,
 *                notifications unread, lessons left — then what is happening
 *                (who is online, visits so far, who is falling behind, the
 *                plain tallies of what has been built).
 *   'news'     — the first of them, the one PHP prints so the cloud is not empty
 *                for a visitor whose script has not run yet.
 *
 * A thought is one line about one thing, and is NEVER two figures joined with a
 * "·". The cloud is a queue now rather than a sentence: one thought is on screen
 * and the next replaces it every 20 seconds (OWL_ROTATE_MS in assets/app.js), so
 * anything joined here would be two announcements wearing one hat — you would
 * spend the rotation re-reading the second half of a line you already heard.
 * The old line read "0 visits today · 3 students enrolled"; those are now two
 * thoughts, each in its own turn, and the zero one says so in English.
 *
 * Both the banner (dashboard.php) and the live refresh (realtime.php) call this,
 * so the cloud and the numbers below it can never tell two different stories.
 * Every figure in it is the viewer's own and nothing here reaches across an
 * account: a teacher's numbers come from queries filtered on courses.teacher_id
 * (their courses, and only the students enrolled in them), and a student's from
 * queries filtered on the student's own id. A main admin owns the whole site, so
 * their cloud is a roll-call of it — site totals with nobody named — rather than a
 * student's enrolment list.
 */
function owl_news(array $user): array
{
    $me = (int) ($user['id'] ?? 0);
    $role = (string) ($user['role'] ?? '');
    /* exactly the rule the banner itself is built on (see $isTeacher in
       dashboard.php): a teacher reads their own courses, everyone else reads
       their own enrolments — an admin lands on the enrolment side there, so the
       owl speaks the same side here and the two never contradict each other. */
    $isTeacher = $role === 'teacher';

    /* the first word of the name the heading above already uses — a whole name
       would not fit the cloud */
    $first = trim((string) ($user['name'] ?? ''));
    if ($first !== '') $first = explode(' ', $first)[0];
    if ($first === '') $first = $isTeacher ? 'Trainer' : 'Student';

    /* the part of the day, in Philippine time: lib.php sets the app's clock to
       Asia/Manila at the top of the file, so date('G') here is the hour on the
       wall of the classroom, whatever zone the web server itself runs in */
    $hour = (int) date('G');
    $part = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

    /** One scalar, scoped by the ids handed to it — every query below carries
     *  either the teacher id or the viewer's own id, never neither. */
    $count = function (string $sql, array $args): int {
        $st = db()->prepare($sql);
        $st->execute($args);
        return (int) $st->fetchColumn();
    };
    /** "3 visits" / "1 visit" — the owl writes plain English, not "1 visits". */
    $tally = function (int $n, string $word): string {
        return $n . ' ' . $word . ($n === 1 ? '' : 's');
    };

    $out = ['name' => OWL_NAME, 'hi' => 'I’m ' . OWL_NAME . ' 🦉', 'say' => $part . ', ' . $first . '!', 'news' => '', 'thoughts' => []];

    /** A name or a course title shortened to what still fits one line of the cloud. */
    $short = function (string $s, int $n): string {
        $s = trim($s);
        $len = function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
        return $len > $n ? rtrim(cut($s, $n - 1)) . '…' : $s;
    };
    /** One class, phrased as the single fact it is: '📅 Math 101 tomorrow at 9:00 AM'.
     *  Only 'Today'/'Tomorrow' are lower-cased — 'Wed, Sep 3' must keep its case. */
    $owl_slot = function (array $slot): string {
        $label = trim((string) ($slot['title'] ?? ''));
        if ($label === '') $label = trim((string) ($slot['course_title'] ?? ''));
        if ($label === '') $label = 'class';
        $when = trim((string) ($slot['when'] ?? ''));
        if ($when === 'Today' || $when === 'Tomorrow') $when = strtolower($when);
        $clock = schedule_clock($slot['start_time'] ?? '');
        return '📅 ' . $label . ($when !== '' ? ' ' . $when : '') . ($clock !== '' ? ' at ' . $clock : '');
    };
    /** Close the answer out of the lines collected. The first of them is also the
     *  one PHP prints, so the cloud is never empty before the script has run. */
    $fill = function (array $lines) use (&$out): array {
        $lines = array_values(array_filter(array_map(
            fn ($s) => trim((string) $s), $lines), fn ($s) => $s !== ''));
        $out['thoughts'] = array_slice($lines ?: ['All quiet over here 🌿'], 0, OWL_THOUGHTS);
        $out['news'] = $out['thoughts'][0];
        return $out;
    };

    /* A main admin owns the whole site, so their cloud is a roll-call of it rather
       than a student's enrolment list — being told to ask a teacher for an
       invitation code is nonsense for the person who runs the place. Aggregates
       only: an admin reading a count is not the same as an admin reading a name,
       so nothing here names anybody. */
    if ($role === 'admin') {
        $lines = [];
        $inbox = unread_message_total($me);
        if ($inbox > 0) $lines[] = '✉️ ' . $tally($inbox, 'message') . ' waiting for you';
        $bell = unread_notification_count($me);
        if ($bell > 0) $lines[] = '🔔 ' . $tally($bell, 'notification') . ' you haven’t read';
        $online = $count("SELECT COUNT(DISTINCT p.user_id) FROM presence p JOIN users u ON u.id = p.user_id
                          WHERE u.role <> 'admin' AND p.last_seen >= ?", [time() - PRESENCE_TIMEOUT]);
        $lines[] = $online > 0
            ? '🟢 ' . $tally($online, 'person') . ' online right now'
            : '🌙 Nobody is online right now';
        $lines[] = '🏫 ' . $tally($count('SELECT COUNT(*) FROM courses', []), 'course') . ' on the site';
        $lines[] = '👥 ' . $tally($count("SELECT COUNT(*) FROM users WHERE role = 'student'", []), 'student') . ' enrolled across them';
        $lines[] = '👩‍🏫 ' . $tally($count("SELECT COUNT(*) FROM users WHERE role = 'teacher'", []), 'teacher') . ' running them';
        return $fill($lines);
    }

    if ($isTeacher) {
        $tc = teacher_counts($me);
        if ($tc['courses'] === 0) return $fill(['No course yet — add your first one 📦']);
        if ($tc['students'] === 0) return $fill(['No student yet — share an invitation code 🎟']);

        /* first the things that can go stale on you — a message unread, a bell
           unread, a class that is coming up — then what is happening right now */
        $lines = [];
        $inbox = unread_message_total($me);
        if ($inbox > 0) $lines[] = '✉️ ' . $tally($inbox, 'message') . ' waiting for you';
        $bell = unread_notification_count($me);
        if ($bell > 0) $lines[] = '🔔 ' . $tally($bell, 'notification') . ' you haven’t read';
        $next = schedule_next_up(schedules_for_teacher($me), 1);
        if ($next) $lines[] = $owl_slot($next[0]);

        $online = count(teacher_online_students($me, 99));
        $lines[] = $online > 0
            ? '🟢 ' . $tally($online, 'student') . ' online right now'
            : '🌙 Nobody is online right now';
        $lines[] = $tc['visits_today'] > 0
            ? '📈 ' . $tally($tc['visits_today'], 'visit') . ' in your courses today'
            : '📈 No visit to your courses yet today';
        $watch = teacher_attention_students($me, 1);
        if ($watch) {
            $lines[] = '👀 ' . $short((string) $watch[0]['name'], 16) . ' is only '
                . (int) $watch[0]['pct'] . '% through ' . $short((string) $watch[0]['course'], 24);
        }
        $lines[] = '👥 ' . $tally($tc['students'], 'student') . ' enrolled with you';
        $lines[] = $tc['completions'] > 0
            ? '✅ ' . $tally($tc['completions'], 'lesson') . ' finished by your students'
            : ($tc['lessons'] > 0
                ? '📚 ' . $tally($tc['lessons'], 'lesson') . ' published, none finished yet'
                : '📚 No lesson yet — upload the first one');
        return $fill($lines);
    }

    /* A student: their own enrolments, the lessons of those courses, their own
       completions and their own visits — every query bound to their id, and the
       slots of schedules_for_student() are only ever those of enrolled courses. */
    $coursesN = $count('SELECT COUNT(*) FROM enrollments WHERE user_id = ?', [$me]);
    if ($coursesN === 0) return $fill(['Ask your teacher for an invitation code 🎒']);

    /* the same order a teacher's cloud is built in: what needs doing first, then
       how it is going, then the plain figures */
    $lines = [];
    $inbox = unread_message_total($me);
    if ($inbox > 0) $lines[] = '✉️ ' . $tally($inbox, 'message') . ' waiting for you';
    $bell = unread_notification_count($me);
    if ($bell > 0) $lines[] = '🔔 ' . $tally($bell, 'notification') . ' you haven’t read';
    $next = schedule_next_up(schedules_for_student($me), 1);
    if ($next) $lines[] = $owl_slot($next[0]);

    $lessonsN = $count('SELECT COUNT(DISTINCT m.id) FROM materials m
                        JOIN enrollments e ON e.course_id = m.course_id
                        WHERE e.user_id = ?', [$me]);
    $doneN = $count('SELECT COUNT(*) FROM progress p
                     JOIN materials m ON m.id = p.material_id
                     JOIN enrollments e ON e.course_id = m.course_id AND e.user_id = p.user_id
                     WHERE p.user_id = ?', [$me]);
    if ($lessonsN > 0) {
        $left = max(0, $lessonsN - $doneN);
        $lines[] = $left > 0
            ? '✍️ ' . $tally($left, 'lesson') . ' left to do'
            : '🎉 Every lesson finished — well done';
        /* the share of the way through only says something the line above did not */
        if ($doneN > 0 && $left > 0) {
            $lines[] = '🎯 ' . (int) round($doneN * 100 / $lessonsN) . '% of your lessons done';
        }
    }
    $mine = $count('SELECT COUNT(*) FROM attendance WHERE user_id = ? AND entered_at >= ?', [$me, (int) strtotime('today')]);
    $lines[] = $mine > 0
        ? '🕒 ' . $tally($mine, 'visit') . ' in your courses today'
        : '🕒 No visit to a course yet today';
    $lines[] = '📚 ' . $tally($coursesN, 'course') . ' enrolled';
    return $fill($lines);
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
            /* the peer row is already in hand, so the circle can show their
               picture — whether it may be printed is decided at print time by
               user_peer_avatar_html(), never here */
            'peer_avatar' => (string) ($peer['avatar'] ?? ''),
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

/* ---------------- assignments & submissions ---------------- */

/** Every assignment of a course, soonest deadline first (no deadline last),
 *  each carrying how many students have handed it in and how many are graded. */
function course_assignments(int $courseId): array
{
    $st = db()->prepare("SELECT a.*, COUNT(s.id) AS submitted,
                                SUM(CASE WHEN s.grade IS NOT NULL THEN 1 ELSE 0 END) AS graded
                         FROM assignments a
                         LEFT JOIN submissions s ON s.assignment_id = a.id
                         WHERE a.course_id = ?
                         GROUP BY a.id
                         ORDER BY a.due_at IS NULL, a.due_at ASC, a.id DESC");
    $st->execute([$courseId]);
    return array_map('assignment_shape', $st->fetchAll());
}

/** One assignment by id, or null. */
function assignment_row(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM assignments WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : assignment_shape($row);
}

/** A row as the rest of this file expects it (ints, '' not null, bool flag). */
function assignment_shape(array $r): array
{
    $r['id'] = (int) $r['id'];
    $r['course_id'] = (int) $r['course_id'];
    $r['teacher_id'] = (int) $r['teacher_id'];
    $r['max_points'] = (int) ($r['max_points'] ?? 100);
    $r['allow_late'] = (int) ($r['allow_late'] ?? 0) ? 1 : 0;
    $r['due_at'] = ($r['due_at'] === null || $r['due_at'] === '') ? null : (int) $r['due_at'];
    return $r;
}

/** May this person act on this course as its teacher? The owning teacher and the
 *  main admin, exactly like every other teacher gate in the app. */
function assignment_can_manage(array $user, int $courseId): bool
{
    return schedule_can_manage($user, $courseId);
}

/** The students of a course (announcement fan-out, gradebook). */
function course_student_ids(int $courseId): array
{
    $st = db()->prepare('SELECT user_id FROM enrollments WHERE course_id = ?');
    $st->execute([$courseId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Students who are NOT enrolled in a course any more but still have work,
 *  visits or results there — the trace a kick (or a voluntary leave) leaves
 *  behind. The roster, the gradebook and the attendance log print these rows
 *  flagged as "Removed", so nothing the teacher has already seen disappears
 *  the moment the enrolment row goes. */
function course_former_student_ids(int $courseId): array
{
    $cid = (int) $courseId;
    $st = db()->prepare(
        "SELECT u.id FROM users u
         WHERE u.role = 'student'
           AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.course_id = ? AND e.user_id = u.id)
           AND (EXISTS (SELECT 1 FROM attendance a WHERE a.course_id = ? AND a.user_id = u.id)
             OR EXISTS (SELECT 1 FROM progress p JOIN materials m ON m.id = p.material_id
                        WHERE m.course_id = ? AND p.user_id = u.id)
             OR EXISTS (SELECT 1 FROM quiz_results qr JOIN quizzes q ON q.id = qr.quiz_id
                        LEFT JOIN materials m2 ON m2.id = q.material_id
                        LEFT JOIN course_folders qf ON qf.id = q.folder_id
                        WHERE COALESCE(m2.course_id, qf.course_id) = ? AND qr.user_id = u.id)
             OR EXISTS (SELECT 1 FROM submissions s JOIN assignments a2 ON a2.id = s.assignment_id
                        WHERE a2.course_id = ? AND s.user_id = u.id))
         ORDER BY u.name");
    $st->execute([$cid, $cid, $cid, $cid, $cid]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/** Take one student out of a course and save the teacher-provided trainee name.
 *  The "Remove" action on the roster and
 *  gradebook. ONLY the enrollments row goes: the account, their lesson
 *  progress, hand-ins, grades, quiz results and visits all stay exactly where
 *  they are, so the teacher keeps the full record, a later re-enrolment puts
 *  everything back in place, and no other table even notices. Any visit still
 *  open is closed so the attendance log never holds a phantom session.
 *  Returns ['ok' => bool, 'msg' => string] — the message is flash-ready. */
function kick_student_from_course(array $actor, int $courseId, int $studentId, string $savedName, string $archiveGroup = ''): array
{
    $courseId = (int) $courseId;
    $studentId = (int) $studentId;
    $savedName = cut(trim($savedName), 120);
    $archiveGroup = cut(trim($archiveGroup), 120);
    $course = course_row($courseId);
    if (!$course) return ['ok' => false, 'msg' => 'Course not found.'];
    if (!schedule_can_manage($actor, $courseId)) {
        return ['ok' => false, 'msg' => 'You can only remove students from your own courses.'];
    }
    if ($studentId <= 0) return ['ok' => false, 'msg' => 'No student selected.'];
    if ($studentId === (int) ($actor['id'] ?? 0)) {
        return ['ok' => false, 'msg' => 'You cannot remove yourself from the course.'];
    }
    $student = find_user_by_id($studentId);
    if (!$student || (string) $student['role'] !== 'student') {
        return ['ok' => false, 'msg' => 'That account is not a student.'];
    }
    if ($savedName === '') $savedName = cut(trim((string) ($student['name'] ?? '')), 120);
    if ($savedName === '') return ['ok' => false, 'msg' => 'The student account has no name to save.'];
    if ($archiveGroup === '') $archiveGroup = $savedName;
    if (!is_enrolled_id($courseId, $studentId)) {
        return ['ok' => false, 'msg' => 'That student is not enrolled in this course.'];
    }

    $db = db();
    $db->beginTransaction();
    try {
        $db->prepare('INSERT INTO course_trainees (course_id, user_id, saved_name, archive_group, removed_by, removed_at) VALUES (?,?,?,?,?,?)')
            ->execute([$courseId, $studentId, $savedName, $archiveGroup, (int) $actor['id'], time()]);
        $db->prepare('DELETE FROM enrollments WHERE course_id = ? AND user_id = ?')
            ->execute([$courseId, $studentId]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    close_attendance($studentId, $courseId);

    add_notification($studentId, 'enrollment',
        'Removed from "' . cut((string) $course['title'], 120) . '"',
        'Your teacher took you out of this course. Everything you did there — lessons, hand-ins and grades — stays on record. Ask '
            . cut((string) $course['teacher_name'], 120) . ' for a new invitation code if you should rejoin.',
        lh_enc_url('course.php?id=' . $courseId));

    return ['ok' => true,
        'msg' => '"' . cut((string) $student['name'], 60) . '" removed and saved as "' . $savedName . '".'];
}

/** '2 days ago', 'in 3 hours' — how a deadline or a hand-in reads. */
function assignment_when(int $ts): string
{
    $d = $ts - time();
    $abs = abs($d);
    $txt = $abs < 3600 ? max(1, (int) round($abs / 60)) . ' min'
         : ($abs < 86400 ? (int) round($abs / 3600) . ' hour' . ($abs >= 7200 ? 's' : '')
         : (int) round($abs / 86400) . ' day' . ($abs >= 172800 ? 's' : ''));
    return $d < 0 ? $txt . ' ago' : 'in ' . $txt;
}

/** Has the deadline passed — and does it still accept work? 'open' | 'late' | 'closed'. */
function assignment_due_state(array $a, int $now = 0): string
{
    $now = $now > 0 ? $now : time();
    $due = $a['due_at'] ?? null;
    if ($due === null || (int) $due === 0) return 'open';
    if ($now > (int) $due) return $a['allow_late'] ? 'late' : 'closed';
    return 'open';
}

/** Word for the deadline state, for the badges. */
function assignment_due_label(array $a): string
{
    $due = $a['due_at'] ?? null;
    if ($due === null || (int) $due === 0) return 'No deadline';
    $state = assignment_due_state($a);
    $when = date('M j, g:i A', (int) $due);
    if ($state === 'closed') return 'Closed · was due ' . $when;
    if ($state === 'late') return 'Late submissions accepted · was due ' . $when;
    return 'Due ' . $when;
}

/** One submission by a student for an assignment (null when none yet). */
function submission_for(int $assignmentId, int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ? LIMIT 1');
    $st->execute([$assignmentId, $userId]);
    $row = $st->fetch();
    return $row === false ? null : submission_shape($row);
}

/** Every submission for one assignment, with the student's name and avatar —
 *  the teacher's grading queue. */
function assignment_submissions(int $assignmentId): array
{
    $st = db()->prepare('SELECT s.*, u.name AS student_name, u.avatar AS student_avatar
                         FROM submissions s JOIN users u ON u.id = s.user_id
                         WHERE s.assignment_id = ?
                         ORDER BY s.submitted_at ASC');
    $st->execute([$assignmentId]);
    return array_map('submission_shape', $st->fetchAll());
}

function submission_shape(array $r): array
{
    $r['id'] = (int) $r['id'];
    $r['assignment_id'] = (int) $r['assignment_id'];
    $r['user_id'] = (int) $r['user_id'];
    $r['size'] = (int) ($r['size'] ?? 0);
    $r['submitted_at'] = (int) ($r['submitted_at'] ?? 0);
    $r['graded_at'] = (int) ($r['graded_at'] ?? 0);
    $r['grade'] = ($r['grade'] === null || $r['grade'] === '') ? null : (int) $r['grade'];
    $r['feedback'] ??= '';
    return $r;
}

/** A stored timestamp as the `YYYY-MM-DDTHH:MM` string an <input
 *  type="datetime-local"> expects — so an edit form comes back pre-filled with
 *  the deadline it already had instead of silently blanking it. Null and 0 both
 *  mean "no deadline", which is an empty input. */
function lh_dt_local_value($ts): string
{
    $ts = (int) $ts;
    return $ts > 0 ? date('Y-m-d\TH:i', $ts) : '';
}

/** Save (create or edit) an assignment. Returns ['ok', 'id', 'errors']. Only the
 *  teacher may call this; the caller checks assignment_can_manage(). */
function assignment_save(array $user, int $id, array $in): array
{
    $courseId = (int) ($in['course_id'] ?? 0);
    $errors = [];
    $title = trim((string) ($in['title'] ?? ''));
    $instructions = trim((string) ($in['instructions'] ?? ''));
    $maxPoints = (int) ($in['max_points'] ?? 100);
    $allowLate = !empty($in['allow_late']) ? 1 : 0;

    if (!assignment_can_manage($user, $courseId)) $errors[] = 'You can only manage assignments on your own courses.';
    if ($title === '') $errors[] = 'Please give the assignment a title.';
    if ($instructions === '') $errors[] = 'Please write what the students have to do.';
    if ($maxPoints < 1 || $maxPoints > 10000) $errors[] = 'Maximum points must be between 1 and 10000.';

    /* The form sends a datetime-local; an empty one means "no deadline". */
    $dueAt = null;
    $dueRaw = trim((string) ($in['due_at'] ?? ''));
    if ($dueRaw !== '') {
        $ts = strtotime($dueRaw);
        if ($ts === false) $errors[] = 'That deadline could not be read.';
        else $dueAt = (int) $ts;
    }
    if ($errors) return ['ok' => false, 'id' => $id, 'errors' => $errors];

    if ($id > 0) {
        $row = assignment_row($id);
        if (!$row || !assignment_can_manage($user, (int) $row['course_id'])) {
            return ['ok' => false, 'id' => 0, 'errors' => ['That assignment no longer exists.']];
        }
        db()->prepare('UPDATE assignments SET title = ?, instructions = ?, due_at = ?, max_points = ?, allow_late = ?
                       WHERE id = ?')
            ->execute([cut($title, 160), $instructions, $dueAt, $maxPoints, $allowLate, $id]);
        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    db()->prepare('INSERT INTO assignments (course_id, teacher_id, title, instructions, due_at, max_points, allow_late, created_at)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$courseId, (int) $user['id'], cut($title, 160), $instructions, $dueAt, $maxPoints, $allowLate, time()]);
    return ['ok' => true, 'id' => (int) db()->lastInsertId(), 'errors' => []];
}

/** Hand in (or re-hand in) work. One row per student per assignment: resubmitting
 *  replaces it until it has been graded, after which the student's copy locks so
 *  a grade can never be left describing work that has since changed. */
function assignment_submit(array $user, array $assignment, array $in): array
{
    $studentId = (int) $user['id'];
    $courseId = (int) $assignment['course_id'];
    if (!is_enrolled_id($courseId, $studentId)) return ['ok' => false, 'errors' => ['Enroll in this course first.']];
    if (assignment_due_state($assignment) === 'closed') return ['ok' => false, 'errors' => ['This assignment is closed for submissions.']];

    $existing = submission_for((int) $assignment['id'], $studentId);
    if ($existing && $existing['grade'] !== null) {
        return ['ok' => false, 'errors' => ['This work has already been graded, so it can no longer be changed.']];
    }

    $body = trim((string) ($in['body'] ?? ''));
    $file = $in['file'] ?? null;
    $hasFile = is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($file['size'] ?? 0) > 0;
    if ($body === '' && !$hasFile) return ['ok' => false, 'errors' => ['Write something or attach a file before handing this in.']];

    $filename = ''; $origName = ''; $mime = ''; $size = 0;
    if ($hasFile) {
        $stored = submission_store_file($file);
        if (isset($stored['error'])) return ['ok' => false, 'errors' => [$stored['error']]];
        $filename = $stored['filename']; $origName = $stored['orig_name'];
        $mime = $stored['mime']; $size = $stored['size'];
    }

    /* Resubmitting replaces the row, so drop the file the old attempt owned. */
    if ($existing && $existing['filename'] !== '') submission_delete_file($existing['filename']);

    if ($existing) {
        db()->prepare('UPDATE submissions SET body = ?, filename = ?, orig_name = ?, mime = ?, size = ?, submitted_at = ?
                       WHERE id = ?')
            ->execute([$body, $filename, $origName, $mime, $size, time(), (int) $existing['id']]);
        return ['ok' => true, 'errors' => [], 'id' => (int) $existing['id']];
    }

    db()->prepare("INSERT INTO submissions (assignment_id, user_id, body, filename, orig_name, mime, size, submitted_at, grade, feedback, graded_at)
                   VALUES (?,?,?,?,?,?,?,?,NULL,'',0)")
        ->execute([(int) $assignment['id'], $studentId, $body, $filename, $origName, $mime, $size, time()]);
    return ['ok' => true, 'errors' => [], 'id' => (int) db()->lastInsertId()];
}

/** Record a grade (or clear it by passing null) and tell the student. */
function assignment_grade(array $user, int $submissionId, ?int $grade, string $feedback): array
{
    $st = db()->prepare('SELECT s.*, a.course_id, a.title AS assignment_title, a.teacher_id, a.max_points
                         FROM submissions s JOIN assignments a ON a.id = s.assignment_id
                         WHERE s.id = ? LIMIT 1');
    $st->execute([$submissionId]);
    $row = $st->fetch();
    if ($row === false) return ['ok' => false, 'errors' => ['That submission no longer exists.']];
    if (!assignment_can_manage($user, (int) $row['course_id'])) {
        return ['ok' => false, 'errors' => ['You can only grade work on your own courses.']];
    }
    if ($grade !== null && ($grade < 0 || $grade > (int) $row['max_points'])) {
        return ['ok' => false, 'errors' => ['The grade must be between 0 and ' . (int) $row['max_points'] . '.']];
    }

    db()->prepare('UPDATE submissions SET grade = ?, feedback = ?, graded_at = ? WHERE id = ?')
        ->execute([$grade, trim($feedback), $grade === null ? 0 : time(), $submissionId]);

    if ($grade !== null) {
        add_notification((int) $row['user_id'], 'grade',
            '📝 Graded: ' . (string) $row['assignment_title'],
            $grade . ' / ' . (int) $row['max_points'] . ($feedback !== '' ? ' — ' : '') . cut($feedback, 300),
            lh_enc_url('assignment.php?id=' . (int) $row['assignment_id']));
    }
    return ['ok' => true, 'errors' => []];
}

/** Delete an assignment (its submissions and files go with it). */
function assignment_delete(array $user, int $id): bool
{
    $row = assignment_row($id);
    if (!$row || !assignment_can_manage($user, (int) $row['course_id'])) return false;
    foreach (assignment_submissions($id) as $s) submission_delete_file((string) $s['filename']);
    db()->prepare('DELETE FROM assignments WHERE id = ?')->execute([$id]);
    return true;
}

/** The folder hand-ins live in, created on demand under uploads/. */
function submission_dir(): string
{
    $dir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'submissions';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    /* Belt and braces: even if a host misconfigures, nothing is served or run
       from here — hand-ins are downloaded through download.php behind a session
       check, and these two files block a direct hit. */
    $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    $idx = $dir . DIRECTORY_SEPARATOR . 'index.html';
    if (!is_file($idx)) @file_put_contents($idx, '');
    return $dir;
}

/** What a hand-in may be. Note there is no image/svg+xml and no text/html: an SVG
 *  or an HTML file can carry script, and these are read back by staff. */
function submission_allowed_mimes(): array
{
    return [
        'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'text/plain', 'text/markdown', 'text/csv',
        'application/zip', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
}

function submission_ext_for(string $mime): string
{
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'text/plain' => 'txt',
        'text/markdown' => 'md',
        'text/csv' => 'csv',
        'application/zip' => 'zip',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ][$mime] ?? 'bin';
}

/** The real type of the bytes, decided from the file itself. */
function submission_sniff_mime(string $path, string $clientName): string
{
    $lower = strtolower($clientName);
    /* Office formats are ZIP containers, so the container alone cannot tell a
       .docx from a .xlsx — the extension is the only signal, and it only ever
       narrows which allowlisted type is claimed, never widens it. */
    foreach ([
        '.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        '.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        '.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ] as $ext => $mime) {
        if (str_ends_with($lower, $ext)) return $mime;
    }

    $info = @getimagesize($path);
    if (is_array($info) && isset($info['mime'])) return (string) $info['mime'];

    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f) {
            $m = finfo_file($f, $path);
            finfo_close($f);
            if (is_string($m) && $m !== '') {
                $m = strtolower($m);
                if ($m === 'application/zip' || $m === 'application/x-zip-compressed') return 'application/zip';
                if (str_starts_with($m, 'text/')) return 'text/plain';
                if ($m === 'application/msword') return 'application/msword';
                return $m;
            }
        }
    }

    if (str_ends_with($lower, '.pdf')) return 'application/pdf';
    if (str_ends_with($lower, '.txt') || str_ends_with($lower, '.md')) return 'text/plain';
    if (str_ends_with($lower, '.csv')) return 'text/csv';
    if (str_ends_with($lower, '.doc')) return 'application/msword';
    return 'application/octet-stream';
}

/** Store one uploaded hand-in. The stored name is generated (never the client's)
 *  and the bytes are sniffed, so a submission cannot smuggle in a .php. */
function submission_store_file(array $file): array
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'That file is too large for the server to accept in one request.'];
    }
    if ($err !== UPLOAD_ERR_OK) return ['error' => 'That file could not be uploaded.'];

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['error' => 'That file could not be uploaded.'];

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) return ['error' => 'That file is empty.'];
    $cap = 25 * 1024 * 1024;   /* a hand-in, not a video — see upload.php for lessons */
    if ($size > $cap) return ['error' => 'Hand-ins are limited to 25 MB. Attach a link for anything bigger.'];

    $mime = submission_sniff_mime($tmp, (string) ($file['name'] ?? ''));
    if (!in_array($mime, submission_allowed_mimes(), true)) {
        return ['error' => 'That file type is not accepted. Attach a PDF, an image, a document or an archive.'];
    }

    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . submission_ext_for($mime);
    if (!@move_uploaded_file($tmp, submission_dir() . DIRECTORY_SEPARATOR . $name)) {
        return ['error' => 'That file could not be saved.'];
    }
    return [
        'filename' => $name,
        'orig_name' => cut(basename((string) ($file['name'] ?? 'file')), 255),
        'mime' => $mime,
        'size' => $size,
    ];
}

/** Delete a stored hand-in. Only our own generated names, and never a path. */
function submission_delete_file(string $filename): void
{
    if (!preg_match('/^\d{8}-[a-f0-9]{16}\.[a-z0-9]{1,5}$/', $filename)) return;
    @unlink(submission_dir() . DIRECTORY_SEPARATOR . $filename);
}

/** A stored hand-in, or null — the name is checked against the generated pattern
 *  so a tampered row can never point the download at anything else. */
function submission_file_path(string $filename): ?string
{
    if (!preg_match('/^\d{8}-[a-f0-9]{16}\.[a-z0-9]{1,5}$/', $filename)) return null;
    $full = submission_dir() . DIRECTORY_SEPARATOR . $filename;
    return is_file($full) ? $full : null;
}

/* ---------------- course announcements ---------------- */

/** A course's announcements, pinned first then newest. */
function course_announcements(int $courseId): array
{
    $st = db()->prepare('SELECT a.*, u.name AS teacher_name, u.avatar AS teacher_avatar
                         FROM announcements a JOIN users u ON u.id = a.teacher_id
                         WHERE a.course_id = ?
                         ORDER BY a.pinned DESC, a.created_at DESC
                         LIMIT 100');
    $st->execute([$courseId]);
    return $st->fetchAll();
}

function announcement_row(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM announcements WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Post an announcement and ring the bell for everyone enrolled. */
function announcement_post(array $user, int $courseId, string $title, string $body, bool $pinned = false): array
{
    if (!assignment_can_manage($user, $courseId)) {
        return ['ok' => false, 'errors' => ['You can only post announcements on your own courses.']];
    }
    $title = trim($title);
    $body = trim($body);
    $errors = [];
    if ($title === '') $errors[] = 'Please give the announcement a title.';
    if ($body === '') $errors[] = 'Please write the announcement.';
    if ($errors) return ['ok' => false, 'errors' => $errors];

    db()->prepare('INSERT INTO announcements (course_id, teacher_id, title, body, pinned, created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$courseId, (int) $user['id'], cut($title, 200), $body, $pinned ? 1 : 0, time()]);
    $id = (int) db()->lastInsertId();

    $link = lh_enc_url('announcements.php?id=' . $courseId);
    foreach (course_student_ids($courseId) as $sid) {
        add_notification($sid, 'announcement', '📣 ' . cut($title, 120), cut($body, 200), $link);
    }
    return ['ok' => true, 'id' => $id, 'errors' => []];
}

/* ---------------- per-lesson discussion ---------------- */

/** Supported reaction identifiers and their display emoji. */
function content_reaction_options(): array
{
    return ['like' => '👍', 'love' => '❤️', 'haha' => '😂', 'wow' => '😮', 'sad' => '😢', 'angry' => '😡'];
}

/** Fixed table/key mapping for the two reaction targets. */
function content_reaction_storage(string $type): ?array
{
    if ($type === 'lesson_post') return ['table' => 'lesson_post_reactions', 'key' => 'post_id'];
    if ($type === 'announcement') return ['table' => 'announcement_reactions', 'key' => 'announcement_id'];
    return null;
}

/** A single Like button with the remaining reactions revealed on hover/focus. */
function content_reaction_html(string $type, int $targetId, array $summary): string
{
    $icons = content_reaction_options();
    $counts = (array) ($summary['counts'] ?? []);
    $mine = (string) ($summary['mine'] ?? '');
    $active = $mine !== '' && isset($icons[$mine]) ? $mine : 'like';
    $total = array_sum(array_map('intval', $counts));
    $html = '<div class="lh-reaction-control relative mt-3 inline-flex border-t border-slate-100 pt-2"'
          . ' data-reaction-control data-reaction-type="' . e($type) . '" data-reaction-target="' . $targetId . '">';
    $html .= '<button type="button" data-reaction="like" data-reaction-primary aria-label="Like"'
          . ' aria-pressed="' . ($mine === 'like' ? 'true' : 'false') . '"'
          . ' class="inline-flex min-h-8 items-center gap-1.5 rounded-full px-2.5 py-1 text-sm font-semibold '
          . ($mine !== '' ? 'bg-indigo-50 text-indigo-700' : 'text-slate-500 hover:bg-slate-50')
          . '"><span data-primary-icon aria-hidden="true">' . $icons[$active] . '</span>'
          . '<span data-primary-label>' . e(ucfirst($active === 'like' ? 'Like' : $active)) . '</span></button>';
    $html .= '<div data-reaction-menu class="lh-reaction-menu absolute bottom-full left-0 z-20 mb-1 items-center gap-1 rounded-full border border-slate-200 bg-white p-1.5 shadow-lg">';
    foreach ($icons as $name => $emoji) {
        $count = (int) ($counts[$name] ?? 0);
        $selected = $mine === $name;
        $html .= '<button type="button" data-reaction="' . e($name) . '" aria-label="' . e(ucfirst($name)) . '"'
              . ' aria-pressed="' . ($selected ? 'true' : 'false') . '"'
              . ' title="' . e(ucfirst($name) . ($count > 0 ? ' (' . $count . ')' : '')) . '"'
              . ' class="inline-flex h-9 w-9 items-center justify-center rounded-full text-xl transition hover:scale-110 '
              . ($selected ? 'bg-indigo-50' : 'hover:bg-slate-100') . '">'
              . '<span aria-hidden="true">' . $emoji . '</span></button>';
    }
    $html .= '</div><div class="lh-reaction-people-row" data-reaction-people-row>'
          . content_reaction_people_html((array) ($summary['reactors'] ?? []))
          . '<span data-reaction-total class="text-xs font-semibold text-slate-500">' . ($total > 0 ? '+' . $total : '') . '</span></div>'
          . '<span data-reaction-status role="status" class="ml-1 text-xs text-rose-600"></span></div>';
    return $html;
}

/** Three most recent reactors, with the middle avatar raised between the others. */
function content_reaction_people_html(array $reactors): string
{
    $options = content_reaction_options();
    $html = '<span class="lh-reaction-people" aria-label="Latest reactions">';
    foreach (array_slice($reactors, 0, 3) as $index => $reactor) {
        $position = $index + 1;
        $reaction = (string) ($reactor['reaction'] ?? '');
        $name = (string) ($reactor['name'] ?? '');
        $user = [
            'id' => (int) ($reactor['user_id'] ?? 0),
            'name' => $name,
            'avatar' => (string) ($reactor['avatar'] ?? ''),
        ];
        $html .= '<span class="lh-reaction-face lh-reaction-face-' . $position . '" title="' . e($name . ' · ' . ucfirst($reaction)) . '">'
              . user_peer_avatar_html($user, 'h-7 w-7')
              . '<span class="lh-reaction-face-emoji" aria-hidden="true">' . ($options[$reaction] ?? '') . '</span></span>';
    }
    return $html . '</span>';
}

/** Return counts and the viewer's selected reaction for a list of target ids. */
function content_reaction_summaries(string $type, array $targetIds, int $userId): array
{
    $storage = content_reaction_storage($type);
    $ids = array_values(array_unique(array_filter(array_map('intval', $targetIds), static fn($id) => $id > 0)));
    if ($storage === null || !$ids) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT ' . $storage['key'] . ' AS target_id, reaction, COUNT(*) AS total, '
         . 'SUM(user_id = ?) AS mine FROM ' . $storage['table'] . ' WHERE '
         . $storage['key'] . ' IN (' . $placeholders . ') GROUP BY ' . $storage['key'] . ', reaction';
    $st = db()->prepare($sql);
    $st->execute(array_merge([$userId], $ids));
    $result = [];
    foreach ($st->fetchAll() as $row) {
        $targetId = (int) $row['target_id'];
        $reaction = (string) $row['reaction'];
        if (!isset(content_reaction_options()[$reaction])) continue;
        $result[$targetId]['counts'][$reaction] = (int) $row['total'];
        if ((int) $row['mine'] > 0) $result[$targetId]['mine'] = $reaction;
    }
    $reactorSql = 'SELECT r.' . $storage['key'] . ' AS target_id, r.user_id, r.reaction, r.created_at,'
                . ' u.name, u.avatar FROM ' . $storage['table'] . ' r JOIN users u ON u.id = r.user_id'
                . ' WHERE r.' . $storage['key'] . ' IN (' . $placeholders . ')'
                . ' ORDER BY r.' . $storage['key'] . ', r.created_at DESC, r.user_id DESC';
    $reactorStmt = db()->prepare($reactorSql);
    $reactorStmt->execute($ids);
    foreach ($reactorStmt->fetchAll() as $row) {
        $targetId = (int) $row['target_id'];
        if (count($result[$targetId]['reactors'] ?? []) >= 3) continue;
        $reaction = (string) $row['reaction'];
        if (!isset(content_reaction_options()[$reaction])) continue;
        $result[$targetId]['reactors'][] = [
            'user_id' => (int) $row['user_id'],
            'name' => (string) $row['name'],
            'avatar' => (string) $row['avatar'],
            'reaction' => $reaction,
        ];
    }
    foreach ($result as &$summary) {
        if (isset($summary['reactors'])) $summary['reactors'] = array_reverse($summary['reactors']);
    }
    unset($summary);
    return $result;
}

/** Toggle the viewer's reaction, or clear it when the same reaction is chosen. */
function content_reaction_toggle(string $type, int $targetId, int $userId, string $reaction): array
{
    $storage = content_reaction_storage($type);
    if ($storage === null || $targetId <= 0) return ['ok' => false, 'error' => 'Invalid reaction target.'];
    if ($reaction !== '' && !isset(content_reaction_options()[$reaction])) {
        return ['ok' => false, 'error' => 'Choose a supported reaction.'];
    }

    $table = $storage['table'];
    $key = $storage['key'];
    $st = db()->prepare('SELECT reaction FROM ' . $table . ' WHERE ' . $key . ' = ? AND user_id = ? LIMIT 1');
    $st->execute([$targetId, $userId]);
    $current = $st->fetchColumn();
    if ($reaction === '' || ($current !== false && (string) $current === $reaction)) {
        db()->prepare('DELETE FROM ' . $table . ' WHERE ' . $key . ' = ? AND user_id = ?')
            ->execute([$targetId, $userId]);
    } else {
        db()->prepare('INSERT INTO ' . $table . ' (' . $key . ', user_id, reaction, created_at) VALUES (?, ?, ?, ?) '
                    . 'ON DUPLICATE KEY UPDATE reaction = VALUES(reaction), created_at = VALUES(created_at)')
            ->execute([$targetId, $userId, $reaction, time()]);
    }
    $summary = content_reaction_summaries($type, [$targetId], $userId);
    return [
        'ok' => true,
        'counts' => $summary[$targetId]['counts'] ?? [],
        'mine' => $summary[$targetId]['mine'] ?? '',
        'reactors_html' => content_reaction_people_html($summary[$targetId]['reactors'] ?? []),
        'total' => array_sum(array_map('intval', $summary[$targetId]['counts'] ?? [])),
    ];
}

/** A lesson's thread: newest-first posts, nested under the exact replied-to item. */
function lesson_posts(int $materialId, int $viewerId = 0): array
{
    $st = db()->prepare('SELECT p.*, u.name AS author_name, u.role AS author_role, u.avatar AS author_avatar
                         FROM lesson_posts p JOIN users u ON u.id = p.user_id
                         WHERE p.material_id = ?
                         ORDER BY p.created_at DESC, p.id DESC');
    $st->execute([$materialId]);
    $all = $st->fetchAll();
    $reactions = content_reaction_summaries('lesson_post', array_column($all, 'id'), $viewerId);
    foreach ($all as &$post) {
        $post['reaction_summary'] = $reactions[(int) $post['id']] ?? [];
        $post['replies'] = [];
    }
    unset($post);

    $postsById = [];
    $children = [];
    foreach ($all as $p) {
        $postsById[(int) $p['id']] = $p;
        $parentId = (int) $p['parent_id'];
        if ($parentId > 0) $children[$parentId][] = (int) $p['id'];
    }

    $buildReplies = function (int $postId) use (&$buildReplies, &$children, &$postsById): array {
        $replies = [];
        foreach ($children[$postId] ?? [] as $childId) {
            if (!isset($postsById[$childId])) continue;
            $reply = $postsById[$childId];
            $reply['replies'] = $buildReplies($childId);
            $replies[] = $reply;
        }
        return $replies;
    };

    $top = [];
    foreach ($all as $p) {
        if ((int) $p['parent_id'] !== 0) continue;
        $post = $postsById[(int) $p['id']];
        $post['replies'] = $buildReplies((int) $p['id']);
        $top[] = $post;
    }
    return $top;
}

function lesson_post_count(int $materialId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM lesson_posts WHERE material_id = ?');
    $st->execute([$materialId]);
    return (int) $st->fetchColumn();
}

/** Add a post or a reply. The parent must belong to the same lesson, so a reply
 *  can never be smuggled onto a thread it does not belong to. */
function lesson_post_add(array $user, int $materialId, string $body, int $parentId = 0): array
{
    $body = trim($body);
    if ($body === '') return ['ok' => false, 'errors' => ['Write something first.']];
    if (mb_strlen($body) > 2000) $body = cut($body, 2000);

    $replyToUserId = 0;
    if ($parentId > 0) {
        $st = db()->prepare('SELECT material_id, parent_id, user_id FROM lesson_posts WHERE id = ? LIMIT 1');
        $st->execute([$parentId]);
        $parent = $st->fetch();
        if ($parent === false || (int) $parent['material_id'] !== $materialId) {
            return ['ok' => false, 'errors' => ['That reply target is no longer there.']];
        }
        $replyToUserId = (int) $parent['user_id'];
    }

    db()->prepare('INSERT INTO lesson_posts (material_id, user_id, parent_id, body, created_at) VALUES (?,?,?,?,?)')
        ->execute([$materialId, (int) $user['id'], $parentId, $body, time()]);
    $postId = (int) db()->lastInsertId();

    $courseStmt = db()->prepare('SELECT m.course_id, m.title, c.teacher_id, c.title AS course_title
                                 FROM materials m JOIN courses c ON c.id = m.course_id
                                 WHERE m.id = ? LIMIT 1');
    $courseStmt->execute([$materialId]);
    $context = $courseStmt->fetch();
    if ($context) {
        $recipientId = $parentId > 0 ? $replyToUserId : (int) $context['teacher_id'];
        if ($recipientId > 0 && $recipientId !== (int) $user['id']) {
            $posterName = trim((string) ($user['name'] ?? 'A course member'));
            $isReply = $parentId > 0;
            $title = $isReply ? $posterName . ' replied to your discussion' : $posterName . ' started a discussion';
            $preview = cut(preg_replace('/\s+/', ' ', $body) ?? $body, 180);
            $link = lh_enc_url('discussion.php?c=' . (int) $context['course_id']
                . '&m=' . $materialId . '#post-' . $postId);
            add_notification(
                $recipientId,
                'discussion',
                $title,
                cut((string) $context['title'] . ': ' . $preview, 500),
                $link
            );
        }
    }
    return ['ok' => true, 'errors' => [], 'id' => $postId];
}

function lesson_post_delete(array $user, int $postId): bool
{
    $st = db()->prepare('SELECT p.user_id, p.material_id, m.course_id
                         FROM lesson_posts p JOIN materials m ON m.id = p.material_id
                         WHERE p.id = ? LIMIT 1');
    $st->execute([$postId]);
    $row = $st->fetch();
    if ($row === false) return false;
    /* Your own post, or a moderator of THIS lesson. Moderation follows the same
       rule the lesson itself is gated by: the teacher who owns the course the
       post sits in, or an admin. A teacher from another course must not be able
       to delete a stranger's discussion — "any teacher can moderate" would let
       any of them walk into any class and clear its thread. */
    $mine = (int) $row['user_id'] === (int) $user['id'];
    if (!$mine && !lesson_can_moderate((int) $row['course_id'], $user)) return false;
    $deleteIds = [$postId];
    $pending = [$postId];
    $childrenStmt = db()->prepare('SELECT id FROM lesson_posts WHERE parent_id = ? AND material_id = ?');
    while ($pending) {
        $parentId = array_pop($pending);
        $childrenStmt->execute([$parentId, (int) $row['material_id']]);
        foreach ($childrenStmt->fetchAll(PDO::FETCH_COLUMN) as $childId) {
            $childId = (int) $childId;
            $deleteIds[] = $childId;
            $pending[] = $childId;
        }
    }
    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
    db()->prepare('DELETE FROM lesson_posts WHERE id IN (' . $placeholders . ')')->execute($deleteIds);
    return true;
}

/** Edit your own post, or any post as the teacher of the course/admin. */
function lesson_post_edit(array $user, int $postId, string $body): array
{
    $body = trim($body);
    if ($body === '') return ['ok' => false, 'errors' => ['Write something before saving.']];
    if (mb_strlen($body) > 2000) $body = cut($body, 2000);

    $st = db()->prepare('SELECT p.user_id, m.course_id
                         FROM lesson_posts p JOIN materials m ON m.id = p.material_id
                         WHERE p.id = ? LIMIT 1');
    $st->execute([$postId]);
    $row = $st->fetch();
    if ($row === false) return ['ok' => false, 'errors' => ['That post is no longer available.']];
    $mine = (int) $row['user_id'] === (int) $user['id'];
    if (!$mine && !lesson_can_moderate((int) $row['course_id'], $user)) {
        return ['ok' => false, 'errors' => ['You do not have permission to edit this post.']];
    }

    db()->prepare('UPDATE lesson_posts SET body = ? WHERE id = ?')->execute([$body, $postId]);
    return ['ok' => true, 'errors' => []];
}

/** May this user delete other people's posts in this course's lesson threads?
 *  The course's own teacher, or an admin — never a teacher from another course. */
function lesson_can_moderate(int $courseId, array $user): bool
{
    $role = (string) ($user['role'] ?? '');
    if ($role === 'admin') return true;
    if ($role !== 'teacher') return false;
    $course = course_row($courseId);
    return $course !== null && (int) ($course['teacher_id'] ?? 0) === (int) $user['id'];
}

/* ---------------- search ---------------- */

/** Find courses and lessons this user is allowed to see. Students are scoped to
 *  their enrolments, teachers to their own courses, the admin to everything —
 *  the same rule the rest of the app uses, so search can never widen access. */
function lh_search(string $q, array $user, int $limit = 40): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) return ['courses' => [], 'lessons' => []];
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
    $role = (string) ($user['role'] ?? '');
    $uid = (int) ($user['id'] ?? 0);   /* cast to int before it goes near SQL */
    $limit = max(1, min(100, $limit));

    /* Scope by role, and default to the NARROWEST scope. The old `else 1 = 1`
       handed every course on the site to any account whose role was neither
       'student' nor 'teacher' — a role typo, a half-made account or anything
       else unexpected saw all of it. Only the admin is meant to see everything;
       every other role falls back to the enrolment scope, which for an account
       with no enrolments means nothing. */
    if ($role === 'admin') {
        $where = '1 = 1';
    } elseif ($role === 'teacher') {
        $where = 'c.teacher_id = ' . $uid;
    } else {
        $where = 'c.id IN (SELECT course_id FROM enrollments WHERE user_id = ' . $uid . ')';
    }

    $cs = db()->prepare("SELECT c.id, c.title, c.category, u.name AS teacher_name
                         FROM courses c JOIN users u ON u.id = c.teacher_id
                         WHERE $where AND (c.title LIKE ? OR c.description LIKE ? OR c.category LIKE ?)
                         ORDER BY c.title ASC LIMIT $limit");
    $cs->execute([$like, $like, $like]);

    $ls = db()->prepare("SELECT m.id, m.title, m.type, m.course_id, c.title AS course_title
                         FROM materials m JOIN courses c ON c.id = m.course_id
                         WHERE $where AND (m.title LIKE ? OR m.description LIKE ?)
                         ORDER BY m.title ASC LIMIT $limit");
    $ls->execute([$like, $like]);

    return ['courses' => $cs->fetchAll(), 'lessons' => $ls->fetchAll()];
}

/* ---------------- gradebook ---------------- */

/** One student's standing in a course: lessons finished, quizzes taken, hand-ins
 *  graded, and the weighted total as a percentage. Quizzes and graded assignments
 *  each count half — and a half with nothing in it is not counted at all, so a
 *  course of pure videos is still gradable and is never dragged down. */
function course_gradebook_row(int $courseId, int $studentId): ?array
{
    $course = course_row($courseId);
    if (!$course) return null;
    $cid = (int) $courseId;
    $sid = (int) $studentId;   /* both cast to int before they go near SQL */

    $lessonTotal = (int) db()->query('SELECT COUNT(*) FROM materials WHERE course_id = ' . $cid)->fetchColumn();
    $lessonDone = (int) db()->query('SELECT COUNT(*) FROM progress p JOIN materials m ON m.id = p.material_id
                                     WHERE p.user_id = ' . $sid . ' AND m.course_id = ' . $cid)->fetchColumn();

    $qr = db()->query('SELECT COUNT(*) AS n, AVG(percentage) AS avg
                       FROM quiz_results qr JOIN materials m ON m.id = qr.lesson_id
                       WHERE qr.user_id = ' . $sid . ' AND m.course_id = ' . $cid)->fetch();
    $sr = db()->query('SELECT COUNT(*) AS n, AVG(grade / NULLIF(max_points,0) * 100) AS avg
                       FROM submissions s JOIN assignments a ON a.id = s.assignment_id
                       WHERE s.user_id = ' . $sid . ' AND s.grade IS NOT NULL AND a.course_id = ' . $cid)->fetch();

    $quizAvg = $qr['avg'] === null ? null : (float) $qr['avg'];
    $subAvg = $sr['avg'] === null ? null : (float) $sr['avg'];
    $parts = array_values(array_filter([$quizAvg, $subAvg], fn ($v) => $v !== null));
    $overall = $parts ? array_sum($parts) / count($parts) : null;

    return [
        'course' => $course,
        'student_id' => $sid,
        'lessons_total' => $lessonTotal,
        'lessons_done' => $lessonDone,
        'lessons_pct' => $lessonTotal > 0 ? round($lessonDone * 100 / $lessonTotal, 1) : 0.0,
        'quizzes_taken' => (int) $qr['n'],
        'quiz_avg' => $quizAvg === null ? null : round($quizAvg, 1),
        'graded' => (int) $sr['n'],
        'assign_avg' => $subAvg === null ? null : round($subAvg, 1),
        'overall' => $overall === null ? null : round($overall, 1),
        'passed' => $overall !== null && $overall >= 75,
    ];
}

/** The whole class's gradebook: every enrolled student, best first — plus any
 *  student who was removed (or left) but still has work or results in the
 *  course, kept visible at the bottom with 'removed' set, so a kick never
 *  erases a grade the teacher has already marked. */
function course_gradebook(int $courseId): array
{
    $rows = [];
    $enrolled = array_flip(course_student_ids($courseId));
    $ids = array_merge(array_keys($enrolled), course_former_student_ids($courseId));
    foreach ($ids as $sid) {
        $r = course_gradebook_row($courseId, $sid);
        if ($r === null) continue;
        $r['removed'] = !isset($enrolled[$sid]);
        $rows[] = $r;
    }
    usort($rows, function ($a, $b) {
        if (!empty($a['removed']) !== !empty($b['removed'])) {
            return !empty($a['removed']) ? 1 : -1;   /* removed students sink below the class */
        }
        if ($a['overall'] === null && $b['overall'] === null) return $a['student_id'] <=> $b['student_id'];
        if ($a['overall'] === null) return 1;    /* anyone still ungraded sits last */
        if ($b['overall'] === null) return -1;
        return $b['overall'] <=> $a['overall'];
    });
    return $rows;
}

/* ---------------- offline bundle ---------------- */

/**
 * A minimal ZIP writer, because the php-zip extension is often OFF on a plain
 * XAMPP build and on plenty of shared hosts — and "download your course" is not
 * a feature worth losing over a missing extension. Everything is stored (no
 * compression): the bundle is mostly PDF/JPEG/MP4, which deflate barely shrinks,
 * and a stored entry is far simpler to get right.
 *
 * It writes straight into an open stream as it goes and keeps only the central
 * directory (a few dozen bytes per entry) in memory — a course of videos would
 * exhaust PHP's memory limit if the archive were assembled in a string first,
 * which is exactly what ZipArchive would NOT have done.
 *
 * The result is a normal archive: local headers, a central directory and an
 * end-of-central-directory record, openable by any unzip, Finder or Explorer.
 */
class lh_zip_writer
{
    /** @var resource the stream the archive is written into */
    private $out;
    /** @var int bytes written so far — the central directory needs the offsets */
    private int $offset = 0;
    /** @var array<string> the central-directory records, flushed at the end */
    private array $central = [];

    public function __construct($out)
    {
        $this->out = $out;
    }

    /** Add one entry held in memory (the readme and the manifest). */
    public function addString(string $name, string $data): void
    {
        $this->addEntry($name, $data);
    }

    /** Add one real file from disk, copied in chunks so its size never matters. */
    public function addFile(string $path, string $nameInZip): bool
    {
        if (!is_file($path) || !is_readable($path)) return false;
        $in = fopen($path, 'rb');
        if (!$in) return false;
        $this->addEntry($nameInZip, null, $in);
        fclose($in);
        return true;
    }

    /** Write the central directory and the end record, and close the archive. */
    public function finish(): void
    {
        $central = '';
        foreach ($this->central as $rec) $central .= $rec;
        /* Where the central directory STARTS. The end record has to point here —
           reading $this->offset at the end would give the position reached AFTER
           writing it, which is the end of the directory, and every extractor
           would then fail to find a single entry. */
        $centralOffset = $this->offset;
        $this->write($central);
        $count = count($this->central);
        /* end-of-central-directory record */
        $this->write(pack('V', 0x06054b50)
            . pack('v', 0) . pack('v', 0)            /* this disk / start disk */
            . pack('v', $count) . pack('v', $count)  /* entries here / total   */
            . pack('V', strlen($central)) . pack('V', $centralOffset)
            . pack('v', 0));                          /* comment length */
    }

    private function write(string $s): void
    {
        if ($s === '') return;
        /* fwrite() may write fewer bytes than asked (a full disk, a pipe), and a
           short write would silently shift every offset after it — leaving a
           central directory pointing at the wrong place and an archive no
           extractor can open. So keep writing until the string is out, and take
           the new position from the stream itself rather than counting bytes. */
        $len = strlen($s);
        $done = 0;
        while ($done < $len) {
            $n = fwrite($this->out, substr($s, $done));
            if ($n === false || $n === 0) {
                throw new RuntimeException('Could not write to the offline archive.');
            }
            $done += $n;
        }
        $pos = ftell($this->out);
        if ($pos !== false) $this->offset = $pos;
    }

    private function addEntry(string $name, ?string $data, $fh = null): void
    {
        [$dosTime, $dosDate] = self::dosStamp();
        $crc = 0;
        $size = 0;
        $localOffset = $this->offset;

        /* A local header carries the CRC and both sizes, but for a file on disk
           neither is known until it has been read. Zip solves this with a data
           descriptor after the data; this writer instead reserves those fields,
           streams the bytes past while hashing and counting them, then seeks back
           and fills them in. That keeps the header in the simplest possible form
           (the one every extractor expects) without ever holding the file. */
        if ($fh !== null) {
            $this->write(pack('V', 0x04034b50)
                . pack('v', 20) . pack('v', 0) . pack('v', 0)     /* stored */
                . pack('v', $dosTime) . pack('v', $dosDate)
                . pack('V', 0) . pack('V', 0) . pack('V', 0)       /* patched below */
                . pack('v', strlen($name)) . pack('v', 0)
                . $name);
            $crcCtx = hash_init('crc32b');       /* CRC-32/BZIP2 — the flavour ZIP uses */
            $written = 0;
            while (!feof($fh)) {
                $chunk = fread($fh, 262144);
                if ($chunk === false || $chunk === '') break;
                hash_update($crcCtx, $chunk);
                $written += strlen($chunk);
                $this->write($chunk);
            }
            $size = $written;
            $crc = (int) hexdec(hash_final($crcCtx));
            /* patch the header: CRC at +14, compressed size at +18, raw at +22 */
            fseek($this->out, $localOffset + 14, SEEK_SET);
            fwrite($this->out, pack('V', $crc) . pack('V', $size) . pack('V', $size));
            fseek($this->out, 0, SEEK_END);
            $pos = ftell($this->out);
            if ($pos !== false) $this->offset = $pos;
        } else {
            $data ??= '';
            $size = strlen($data);
            $crc = crc32($data);
            $this->write(pack('V', 0x04034b50)
                . pack('v', 20) . pack('v', 0) . pack('v', 0)
                . pack('v', $dosTime) . pack('v', $dosDate)
                . pack('V', $crc) . pack('V', $size) . pack('V', $size)
                . pack('v', strlen($name)) . pack('v', 0)
                . $name . $data);
        }

        $this->central[] = pack('V', 0x02014b50)
            . pack('v', 20) . pack('v', 20) . pack('v', 0)
            . pack('v', 0)                           /* stored                */
            . pack('v', $dosTime) . pack('v', $dosDate)
            . pack('V', $crc) . pack('V', $size) . pack('V', $size)
            . pack('v', strlen($name)) . pack('v', 0) . pack('v', 0)
            . pack('v', 0) . pack('v', 0) . pack('V', 32)
            . pack('V', $localOffset)
            . $name;
    }

    private static function dosStamp(): array
    {
        $t = getdate();
        $year = max(1980, (int) $t['year']);   /* MS-DOS counts from 1980 */
        return [
            ($t['hours'] << 11) | ($t['minutes'] >> 1),   /* stored to 2 seconds */
            (($year - 1980) << 9) | ($t['mon'] << 5) | $t['mday'],
        ];
    }
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

/** Total notifications (read + unread) — returned by realtime.php for reference;
 *  the red bell badge uses unread_notification_count() instead. */
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

        $st = db()->prepare('SELECT COUNT(*) FROM quizzes q'
            . ' LEFT JOIN materials qm ON qm.id = q.material_id'
            . ' JOIN course_folders f ON f.id = COALESCE(q.folder_id, qm.folder_id)'
            . ' JOIN enrollments e ON e.course_id = f.course_id'
            . ' LEFT JOIN quiz_results r ON r.quiz_id = q.id AND r.user_id = e.user_id'
            . ' WHERE e.user_id = ? AND r.quiz_id IS NULL'
            . ' AND EXISTS (SELECT 1 FROM materials fm WHERE fm.folder_id = f.id'
            . ' OR (f.parent_id IS NULL AND fm.folder_id IN (SELECT sf.id FROM course_folders sf WHERE sf.parent_id = f.id)))'
            . ' AND NOT EXISTS (SELECT 1 FROM materials fm WHERE (fm.folder_id = f.id'
            . ' OR (f.parent_id IS NULL AND fm.folder_id IN (SELECT sf.id FROM course_folders sf WHERE sf.parent_id = f.id)))'
            . ' AND NOT EXISTS (SELECT 1 FROM progress fp WHERE fp.user_id = e.user_id AND fp.material_id = fm.id))');
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

/* ---------------- the public pages anybody can reach ------------------------
 * home.php is the front door — the document .htaccess serves for the folder
 * itself. learnhub.php is the "How it works" tour that used to be index.php
 * (renamed so that no page and the folder index were fighting over one name),
 * and about.php / faq.php sit beside them.
 *
 * The top bar and the footer both loop THIS list, so renaming or re-labelling
 * one of these pages is a single edit instead of a hunt through every
 * navigation — and every row here is reachable while signed out.
 * ------------------------------------------------------------------------- */
const PUBLIC_PAGES = [
    'home'     => ['file' => 'home.php',     'label' => 'Home'],
    'learnhub' => ['file' => 'learnhub.php', 'label' => 'How it works'],
    'about'    => ['file' => 'about.php',    'label' => 'About'],
    'faq'      => ['file' => 'faq.php',      'label' => 'FAQ'],
];

/** Address of one public page ('' when the key is unknown). */
function public_url(string $key): string
{
    return PUBLIC_PAGES[$key]['file'] ?? '';
}

/* ---------------- the public policy pages & recorded consent ---------------
 * privacy_policy.php, terms_conditions.php and cookie_policy.php are ordinary
 * pages, and every form that collects something links to them from ONE place,
 * so a re-worded policy never goes out of sync with the checkbox that points
 * at it:
 *   legal_url()       where a policy lives — the only copy of those file names
 *   legal_links()     the three links, styled like the rest of the app
 *   consent_field()   the "I have read and accept…" box (register.php)
 *   consent_notice()  the one-line version (login.php, reset_password.php)
 *   consent_check()   server-side proof the box really was ticked
 *   consent_record()  what was accepted, and when, per account
 * Bump LEGAL_VERSION whenever a policy changes materially: every account then
 * keeps the version it actually agreed to (user_meta: consent_at /
 * consent_version), which is what a data-protection review asks to see.
 * ------------------------------------------------------------------------- */
const LEGAL_VERSION = '2026-10-01';           /* the "Last updated" date on all three */

/** key => [file, label] — the single source of the three policy URLs. */
const LEGAL_PAGES = [
    'privacy' => ['file' => 'privacy_policy.php',   'label' => 'Privacy Policy'],
    'terms'   => ['file' => 'terms_conditions.php', 'label' => 'Terms & Conditions'],
    'cookies' => ['file' => 'cookie_policy.php',    'label' => 'Cookie Policy'],
];

/** Address of one policy page ('' when the key is unknown). */
function legal_url(string $key): string
{
    return LEGAL_PAGES[$key]['file'] ?? '';
}

/** The three policy links as inline anchors, for forms, footers and notices. */
function legal_links(string $sep = ' · '): string
{
    $out = [];
    foreach (LEGAL_PAGES as $p) {
        $out[] = '<a class="font-semibold text-indigo-600 hover:underline" href="' . e($p['file']) . '">'
            . e($p['label']) . '</a>';
    }
    return implode(e($sep), $out);
}

/** The visible consent checkbox. An unticked box never reaches PHP at all. */
function consent_field(bool $checked = false): string
{
    return '<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">'
        . '<label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-slate-600">'
        . '<input type="checkbox" name="agree" value="1" required' . ($checked ? ' checked' : '')
        . ' class="mt-0.5 h-4 w-4 shrink-0 accent-indigo-600">'
        . '<span>I have read and accept the ' . legal_links() . ' — and I confirm I am 18 or older, '
        . 'or that a parent or guardian agrees on my behalf.</span>'
        . '</label>'
        . '<p class="mt-2 text-xs leading-5 text-slate-400">Your name and e-mail are used to run your account, '
        . 'course and certificate — nothing more. The <a class="font-semibold text-slate-500 hover:underline" href="'
        . e(legal_url('privacy')) . '">Privacy Policy</a> lists exactly what is stored and for how long, and the '
        . '<a class="font-semibold text-slate-500 hover:underline" href="' . e(legal_url('cookies')) . '">Cookie Policy</a> '
        . 'explains the one necessary cookie we set.</p>'
        . '</div>';
}

/** One line of the same agreement, for forms that collect nothing new. */
function consent_notice(string $extra = '', string $align = 'text-center'): string
{
    return '<p class="mt-4 ' . e($align) . ' text-xs leading-5 text-slate-400">By continuing you agree to our '
        . legal_links() . '.' . ($extra !== '' ? ' ' . e($extra) : '') . '</p>';
}

/** Ticked the box? Returns the message to show, or null when it was ticked. */
function consent_check(array $post): ?string
{
    return !empty($post['agree']) ? null
        : 'Please tick the box to accept the Terms & Conditions and the Privacy Policy.';
}

/** Remember what this account accepted, and when (keys must fit user_meta.k). */
function consent_record(int $uid): void
{
    user_meta_set($uid, 'consent_at', date('Y-m-d H:i:s'));
    user_meta_set($uid, 'consent_version', LEGAL_VERSION);
}


ensure_storage();
/* URLs: decrypt ids coming in (old plain links keep working), then start the
   output buffer that encrypts every id link the page produces. lib.php is
   included at the top of every page, before any output or $_GET read. */
lh_decrypt_incoming();
ob_start('lh_url_encrypt_html');
