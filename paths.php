<?php
/**
 * LearnHub LMS — where the folders are.
 *
 * The one file that decides which folder holds `assets/`, `logo/`,
 * `signature/`, `data/` and `uploads/`, and where this site's own PHP lives.
 * `lib.php` and `asset.php` both start by including it and then never build a
 * path by hand again.
 *
 * WHY THIS FILE EXISTS
 * --------------------
 * Everything used to sit beside the PHP: `__DIR__ . '/data'`,
 * `is_file(__DIR__ . '/assets/shell.css')`, `<img src="logo/logo.png">`. That
 * is correct on XAMPP and on any host that keeps the whole folder inside the
 * web root — and wrong the moment the folders are put *above* `public_html`,
 * which is exactly what a Hostinger-style deploy does (and what you want for
 * `data/` and `uploads/`, whose contents must never be a URL):
 *
 *     /home/uXXXXXXXX/domains/example.com/     ← the storage root (LH_STORAGE_DIR)
 *     ├── assets/
 *     ├── data/
 *     ├── logo/
 *     ├── signature/
 *     ├── uploads/
 *     ├── config.php            (optional: above public_html, out of the docroot)
 *     └── public_html/          ← only the PHP lives here (LMS_ROOT)
 *         ├── lib.php  paths.php  asset.php  header.php  …
 *         └── .htaccess
 *
 * One line in `config.php` switches to that layout — whichever of the two places
 * the config file itself is in (both are looked for):
 *
 *     define('LH_STORAGE_DIR', dirname(__DIR__));   // config.php inside public_html
 *     define('LH_STORAGE_DIR', __DIR__);            // config.php above public_html
 *
 * Any absolute path works too, for a deploy that keeps the folders somewhere
 * else entirely (a second disk, a test fixture). And with no config at all the
 * default is `__DIR__`, so the classic layout
 * (this repository as it stands, C:\xampp\htdocs\LMS) keeps working untouched.
 *
 * HOW THE PUBLIC HALF IS SERVED
 * -----------------------------
 * `assets/`, `logo/` and `signature/` are public by design — they are in
 * `href`/`src` attributes. Apache cannot serve a file that is not under the
 * document root, so when those folders sit above it the request for
 * `/assets/shell.css` is handed to `asset.php` by a rule in `.htaccess`, which
 * reads the file from wherever it really lives. The rewrite is INTERNAL: the
 * browser's URL never changes, so a relative `url(../logo/owl-body.png)` inside
 * a stylesheet still resolves exactly as it does today, and the pages keep the
 * relative URLs they have always printed. When the folders ARE beside the PHP,
 * the file exists, the rule does not fire, and Apache serves it directly — no
 * PHP in the path at all.
 *
 * `data/` and `uploads/` need none of that: they are never linked, they stream
 * through `download.php` / `avatar.php` / `read.php`, and every one of those
 * readers takes the folder from the constants below. Above the document root
 * they are also unreachable by URL, which is the point.
 */

declare(strict_types=1);

/* Never expose this file directly over HTTP (same guard as config.php). */
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Forbidden');
}

/* ---- 1) Where this site's PHP lives ----------------------------------------
 * Not configurable, and it needs no configuration: it is simply the folder this
 * file sits in. On a shared host this is `public_html` (or a subfolder of it) —
 * the folder Apache has as its document root — and every page, partial and
 * library is here beside it.
 * -------------------------------------------------------------------------- */
if (!defined('LMS_ROOT')) define('LMS_ROOT', __DIR__);


/* ---- 2) config.php ----------------------------------------------------------
 * Loaded here, and FIRST, for two reasons:
 *   • it is loaded before the folder constants below, so it can pin them;
 *   • it may live one level UP, above the document root, which keeps the MySQL
 *     password, the API key and the Turnstile secret out of `public_html/`
 *     entirely. Both places are tried, the local one first.
 * -------------------------------------------------------------------------- */
if (!defined('LH_CONFIG_LOADED')) {
    $lh_config_file = __DIR__ . '/config.php';
    if (!is_file($lh_config_file)) {
        $lh_above = dirname(__DIR__) . '/config.php';   /* beside the storage root */
        if (is_file($lh_above)) $lh_config_file = $lh_above;
    }
    define('LH_CONFIG_LOADED', true);                   /* required only once */
    if (is_file($lh_config_file)) require_once $lh_config_file;
    unset($lh_config_file);
    if (isset($lh_above)) unset($lh_above);
}

/* ---- 3) The storage root ----------------------------------------------------
 * The folder that holds `data/` and `uploads/` (private) and, when they have
 * been moved, `assets/`, `logo/` and `signature/` (public). Default: beside the
 * PHP — today's layout, and nothing changes.
 *
 * Set it once in config.php to deploy the private folder above public_html:
 *     define('LH_STORAGE_DIR', dirname(__DIR__));
 * Trailing slashes and Windows backslashes are tolerated — the helpers below
 * normalise every path to forward slashes before use.
 * -------------------------------------------------------------------------- */
if (!defined('LH_STORAGE_DIR')) define('LH_STORAGE_DIR', LMS_ROOT);

/** Forward-slashed, trailing-slash-free form of a path (PHP on Windows returns
 *  backslashes, and the two forms must never be compared as strings). */
function lh_slash(string $path): string
{
    return rtrim(str_replace('\\', '/', $path), '/');
}

/** The folder this site's PHP lives in — what Apache serves. */
function lh_app_dir(): string
{
    return lh_slash(LMS_ROOT);
}

/** The folder that holds the storage folders. Equals lh_app_dir() unless
 *  config.php moved it above the document root. */
function lh_storage_root(): string
{
    $root = lh_slash(LH_STORAGE_DIR);
    return $root === '' ? lh_app_dir() : $root;
}

/** True when the storage root and the PHP folder are the same place — the
 *  classic, everything-beside-everything layout. */
function lh_storage_is_local(): bool
{
    return lh_storage_root() === lh_app_dir();
}

/** Resolve a PUBLIC folder (`assets/`, `logo/`, `signature/`) — the ones a
 *  browser fetches, and therefore the ones that may legitimately live in either
 *  place.
 *  Precedence:
 *    1. the pin from config.php (`LH_ASSET_DIR`, `LH_LOGO_DIR`, …) — a full path
 *    2. the storage root, when it really holds a folder of that name
 *    3. beside the PHP (today's layout, and the fallback for a deploy that
 *       moved only some of the folders)
 *  A private folder is never sniffed this way: `data/` and `uploads/` are where
 *  the operator says they are, so a fresh deploy creates them in the right
 *  place instead of quietly inside public_html. */
function lh_dir(string $name, string $pin = ''): string
{
    if ($pin !== '' && defined($pin)) {
        $pinned = lh_slash((string) constant($pin));
        if ($pinned !== '') return $pinned;
    }
    if (!lh_storage_is_local()) {
        $outside = lh_storage_root() . '/' . $name;
        if (is_dir($outside)) return $outside;
    }
    return lh_app_dir() . '/' . $name;
}

/* The three public folders. Each may also be pinned on its own — handy when
   only one of them has been moved, or when `assets/` is deliberately left
   inside public_html so Apache serves the CSS and JavaScript as raw files
   (fastest) while `logo/` and `signature/` come from the storage root. */
if (!defined('ASSET_DIR'))     define('ASSET_DIR',     lh_dir('assets',    'LH_ASSET_DIR'));
if (!defined('LOGO_DIR'))      define('LOGO_DIR',      lh_dir('logo',      'LH_LOGO_DIR'));
if (!defined('SIGNATURE_DIR')) define('SIGNATURE_DIR', lh_dir('signature', 'LH_SIGNATURE_DIR'));

/* The two private folders. `LH_STORAGE_DIR` decides, and `LH_DATA_DIR` /
   `LH_UPLOAD_DIR` can each override it for a host that insists on one of them
   being somewhere else (a bigger disk for uploads, say). They are NOT created
   here — `ensure_storage()` in lib.php does that, with the folder blockers. */
if (!defined('DATA_DIR')) {
    define('DATA_DIR', defined('LH_DATA_DIR') && (string) LH_DATA_DIR !== ''
        ? lh_slash((string) LH_DATA_DIR)
        : lh_storage_root() . '/data');          /* legacy JSON storage (auto-imported once) */
}
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', defined('LH_UPLOAD_DIR') && (string) LH_UPLOAD_DIR !== ''
        ? lh_slash((string) LH_UPLOAD_DIR)
        : lh_storage_root() . '/uploads');
}


/** The folder names a browser is allowed to fetch through asset.php, mapped to
 *  the constant holding their location. `data/` and `uploads/` are deliberately
 *  absent: those two are streamed by their own permission-checked endpoints
 *  (download.php, avatar.php, read.php), never by the public gateway. */
function lh_public_dirs(): array
{
    return ['assets' => ASSET_DIR, 'logo' => LOGO_DIR, 'signature' => SIGNATURE_DIR];
}

/** The real location of a repository-relative path, whichever layout is in
 *  force — the single function every path-building caller should use:
 *
 *      lh_path('assets/shell.css')        ->  …/example.com/assets/shell.css
 *      lh_path('logo/owl-body.png')       ->  …/example.com/logo/owl-body.png
 *      lh_path('data/mail.log')           ->  …/example.com/data/mail.log
 *      lh_path('uploads/avatars/x.png')   ->  …/example.com/uploads/avatars/x.png
 *      lh_path('header.php')              ->  …/example.com/public_html/header.php
 *
 *  Paths that do not exist yet are fine (a log, or a folder about to be
 *  created); only the top folder is rewritten, so nothing is resolved against
 *  the current working directory and no `..` can reach the result. */
function lh_path(string $rel): string
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    $cut = strpos($rel, '/');
    $top = $cut === false ? $rel : substr($rel, 0, $cut);          /* 'assets' … */
    $rest = $cut === false ? '' : substr($rel, $cut);              /* '/shell.css' */

    switch ($top) {
        case 'assets':    return ASSET_DIR . $rest;
        case 'logo':      return LOGO_DIR . $rest;
        case 'signature': return SIGNATURE_DIR . $rest;
        case 'data':      return DATA_DIR . $rest;
        case 'uploads':   return UPLOAD_DIR . $rest;
    }
    return lh_app_dir() . '/' . $rel;                              /* a page, a partial */
}

/** Same, but in the form asset.php works in — a folder name plus the file
 *  inside it: `lh_public_path('assets', 'shell.css')`. Returns '' when the
 *  folder is not one a browser may fetch, so a bad request can never fall back
 *  to some other folder. */
function lh_public_path(string $folder, string $file): string
{
    $dirs = lh_public_dirs();
    if (!isset($dirs[$folder])) return '';
    return $dirs[$folder] . '/' . ltrim(str_replace('\\', '/', $file), '/');
}
