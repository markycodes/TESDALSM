<?php
/**
 * Static asset gateway — serves `assets/`, `logo/` and `signature/` even when
 * those folders live OUTSIDE the document root.
 *
 * WHY IT EXISTS
 * -------------
 * A stylesheet, a script and a signature PNG are fetched by the browser as
 * plain URLs (`assets/shell.css`, `logo/logo.png`, `signature/…png`), and Apache
 * can only serve a file that sits under its document root. Keep the folders
 * above `public_html` — which is what you want for `data/` and `uploads/` —
 * and every one of those URLs becomes a 404 unless something reads the file
 * from where it really is. That is this script.
 *
 * `.htaccess` reaches it with an INTERNAL rewrite, so the browser's URL never
 * changes:
 *
 *     GET /assets/shell.css?v=1234
 *       └─ not a file under the document root
 *          └─ asset.php?lh_asset=assets/shell.css&v=1234   (invisible to the page)
 *
 * Keeping the URL intact matters: a stylesheet's relative `url(../logo/x.png)`
 * is resolved by the browser against its own URL, so serving the CSS at a
 * different address (a query-string URL, say) would silently move every font and
 * every image inside it. It also means the pages keep the plain relative URLs
 * they have always printed, and nothing in the app had to change.
 *
 * When the folders ARE beside the PHP — XAMPP, or any host that keeps them in
 * `public_html` — the file exists, the rewrite never fires, and Apache hands
 * over the bytes itself. This script is simply not in the path.
 *
 * WHAT IT IS NOT
 * --------------
 * Deliberately NOT a general file server, and deliberately NOT built on
 * `lib.php`:
 *
 *   • it can only read from the three public folders named in `lh_public_dirs()`
 *     (`data/` and `uploads/` are absent — those stream through their own
 *     permission-checked endpoints), it refuses any path containing `.` or `..`
 *     as a segment, it refuses anything but an allowlist of extensions, and the
 *     file it finally opens must resolve — after `realpath()` — to a real file
 *     inside that folder;
 *   • including `lib.php` here would be a bug, not a convenience: lib.php runs
 *     the boot-time maintenance gate, which asks the database whether the site
 *     is shut down. A CSS request must not need MySQL, must start no session,
 *     and must never answer 503 — so this script includes only `paths.php`,
 *     which is pure path arithmetic.
 */

declare(strict_types=1);

require_once __DIR__ . '/paths.php';

/* Anything that is not a GET/HEAD (a probe, a POST) gets the same treatment as a
   missing file: nothing. */
$lh_method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($lh_method !== 'GET' && $lh_method !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

$lh_rel = $_GET['lh_asset'] ?? '';
$lh_rel = is_string($lh_rel) ? trim(str_replace('\\', '/', $lh_rel)) : '';

/* ---- 1) the shape of the request -------------------------------------------
   One to four path segments, each of plain URL-safe characters: no dot-segment
   (`..` climbs out of the folder, `.` is noise), no leading or doubled slash,
   no control bytes, no percent signs (the value is already decoded), nothing
   from a UNC path. Everything outside this is refused before a path exists. */
$lh_ok_shape = $lh_rel !== ''
    && strlen($lh_rel) <= 255
    && $lh_rel[0] !== '/'
    && !str_contains($lh_rel, '//')
    && preg_match('~^[A-Za-z0-9][A-Za-z0-9._/@-]*$~', $lh_rel) === 1
    && !preg_match('~(^|/)\.\.?(/|$)~', $lh_rel)
    && count(explode('/', $lh_rel)) <= 4;

$lh_folder = '';
$lh_file   = '';
if ($lh_ok_shape) {
    $lh_cut    = (int) strpos($lh_rel, '/');
    $lh_folder = substr($lh_rel, 0, $lh_cut);
    $lh_file   = substr($lh_rel, $lh_cut + 1);   /* 'shell.css', or 'fonts/x.woff2' */
}

/* ---- 2) the allowlist -------------------------------------------------------
   Folder, then extension. Both must pass; a request for nothing, or for a file
   type the app never ships, is a 404 like any other missing asset. */
$lh_ext  = strtolower((string) pathinfo($lh_file, PATHINFO_EXTENSION));
$lh_mime = lh_asset_mime($lh_ext);
$lh_path = $lh_folder !== '' && $lh_file !== '' && $lh_mime !== ''
    ? lh_public_path($lh_folder, $lh_file)
    : '';

if ($lh_path === '' || !is_file($lh_path)) {
    lh_asset_fail(404, 'Not found');
}

/* ---- 3) containment --------------------------------------------------------
   The belt to the braces above: open the folder and the file for real, and make
   sure the file is inside the folder. A symlink pointing out, a path that only
   looked clean, an odd filename — all refused. */
$lh_dir_real  = realpath(dirname($lh_path));
$lh_file_real = realpath($lh_path);
if ($lh_dir_real === false || $lh_file_real === false
    || !str_starts_with($lh_file_real, $lh_dir_real . DIRECTORY_SEPARATOR)
    || !is_file($lh_file_real)) {
    lh_asset_fail(404, 'Not found');
}


/* ---- 4) the response --------------------------------------------------------
   Straight off the disk with the three headers that make a static file static:
   a type, a validator pair (ETag + Last-Modified) and a life span. The pages
   stamp the URLs they care about with `?v=<mtime>`, so a week of cache costs
   nothing when a file is replaced — and Last-Modified brings the entry back for
   a cheap 304 rather than the whole file when it expires. */
$lh_mtime = (int) @filemtime($lh_file_real);
$lh_size  = (int) @filesize($lh_file_real);

/* Only text is worth compressing — an already-compressed PNG or WOFF2 gets
   bigger, and spends CPU doing it. This is the one thing a raw Apache serve
   does for free (mod_deflate) that a gateway has to do itself: `shell.css` is
   54 KB and `app.js` 109 KB, so without it every cold load would hand a phone
   on a slow link ~200 KB of text it could have had in ~50 KB. */
$lh_text = str_starts_with($lh_mime, 'text/')
    || $lh_mime === 'application/javascript'
    || $lh_mime === 'image/svg+xml'
    || $lh_mime === 'application/manifest+json';
$lh_gz = $lh_text
    && function_exists('gzencode')
    && str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')), 'gzip');

/* The ETag carries the encoding, so a shared cache can never answer a client
   that asked for plain bytes with the gzipped body of the same file. */
$lh_etag = '"' . dechex($lh_mtime) . '-' . dechex($lh_size) . ($lh_gz ? '-gz' : '') . '"';

/* Is what the browser already holds still good? If-None-Match wins when it is
   present (per RFC 9110 a mismatch there means "send it", not "ask the date").
   `*` means any, and a W/ prefix is a weak comparison — for a static file the
   weak and strong validators are the same bytes. */
$lh_inm = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$lh_ims = trim((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
$lh_fresh = false;
if ($lh_inm !== '') {
    foreach (explode(',', $lh_inm) as $lh_tag) {
        $lh_tag = trim($lh_tag);
        if ($lh_tag === '*' || ltrim($lh_tag, 'W/') === $lh_etag) { $lh_fresh = true; break; }
    }
} elseif ($lh_ims !== '') {
    $lh_since = strtotime($lh_ims);
    $lh_fresh = $lh_since !== false && $lh_since >= $lh_mtime;
}

while (ob_get_level() > 0) { @ob_end_clean(); }

header('ETag: ' . $lh_etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lh_mtime) . ' GMT');
header('Cache-Control: public, max-age=604800');
if ($lh_gz) header('Vary: Accept-Encoding');

if ($lh_fresh) {
    http_response_code(304);
    exit;
}

$lh_body = '';
if ($lh_gz) {
    $lh_body = (string) gzencode((string) file_get_contents($lh_file_real), 6);
    if ($lh_body === '') $lh_gz = false;            /* zlib said no: send it plain */
}

http_response_code(200);
header('Content-Type: ' . $lh_mime);
header('X-Content-Type-Options: nosniff');
header('Accept-Ranges: none');
if ($lh_gz) header('Content-Encoding: gzip');
header('Content-Length: ' . ($lh_gz ? strlen($lh_body) : $lh_size));

if ($lh_method === 'HEAD') exit;                    /* headers only, as HEAD means */

if ($lh_gz) {
    echo $lh_body;
} else {
    readfile($lh_file_real);                        /* images stream, never buffered whole */
}
exit;


/* ---------------------------------------------------------------------------
 * The two helpers this script needs. They live here rather than in lib.php on
 * purpose: lib.php is the application (database, sessions, permissions) and a
 * request for a PNG must not wake any of it. The type list is the set of files
 * the app actually ships in the three public folders, which is why it is
 * shorter than lib.php's upload list — a lesson's PDF or MP4 travels through
 * download.php, never through here.
 * ------------------------------------------------------------------------ */

/** Content type for an extension, or '' when the type is not served at all —
 *  which is what makes the extension itself part of the allowlist. */
function lh_asset_mime(string $ext): string
{
    static $map = [
        /* markup and code */
        'css'         => 'text/css; charset=utf-8',
        'js'          => 'application/javascript; charset=utf-8',
        'mjs'         => 'application/javascript; charset=utf-8',
        'html'        => 'text/html; charset=utf-8',   /* the dev fixtures in assets/ */
        'txt'         => 'text/plain; charset=utf-8',
        'json'        => 'application/json; charset=utf-8',
        'webmanifest' => 'application/manifest+json',
        'xml'         => 'application/xml; charset=utf-8',
        /* pictures */
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'avif'  => 'image/avif',
        'bmp'   => 'image/bmp',
        'ico'   => 'image/x-icon',
        'svg'   => 'image/svg+xml',
        /* fonts, if a design ever ships one */
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'eot'   => 'application/vnd.ms-fontobject',
    ];
    return $map[$ext] ?? '';
}

/** Refuse a request. The body is plain text and never repeats what was asked
 *  for — a 404 that echoes a path is a way to probe a server, and there is
 *  nothing here a visitor could do with the value anyway. The hint that follows
 *  is for the one reader who can act on it: the operator who moved the folders
 *  and mistyped the path in config.php. */
function lh_asset_fail(int $code, string $message): void
{
    while (ob_get_level() > 0) { @ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message . "\n";
    if ($code === 404) {
        echo "\nThis asset was not found in the folder it should live in. If it is\n"
            . "there on disk, the folder itself was not: check LH_STORAGE_DIR (and\n"
            . "LH_ASSET_DIR / LH_LOGO_DIR / LH_SIGNATURE_DIR) in config.php — paths.php\n"
            . "explains what each one means.\n";
    }
    exit;
}
