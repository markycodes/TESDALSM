<?php
/**
 * Profile pictures — the read side of the avatar pipeline.
 *
 * uploads/ is web-blocked (see deny_web_access() in lib.php), so a picture can
 * never be linked straight into an <img src>: this endpoint streams it back,
 * the same way download.php streams lesson files. It is deliberately its own
 * endpoint instead of a mode of download.php — a picture is small, public-ish
 * inside the school, and wanted on EVERY page, which makes caching the whole
 * game here.
 *
 * Who may look: the account itself, an administrator, and the teacher of a
 * course the account is enrolled in — the people a picture identifies a person
 * TO, and nobody else (can_view_profile_of() is the single rule, and the pages
 * that print an <img> consult the same one, so a picture that would 404 is never
 * linked either). Anyone else — including a signed-in stranger or a classmate —
 * gets a plain 404, and no listing of pictures exists anywhere.
 *
 * ?u= is a guessable number, which is exactly why it is not treated as a
 * permission: the session decides, the URL never does.
 */
require_once __DIR__ . '/lib.php';

/* An <img> tag cannot follow a login redirect, and a redirect would be stored
   as a cached 302 by some proxies — a silent broken circle is the honest answer
   for a visitor who is not signed in. */
if (!current_user()) { http_response_code(404); exit('Not found'); }

$userId = profile_request_user_id();
$target = $userId > 0 ? find_user_by_id($userId) : null;
$path   = avatar_path_of(avatar_file_of((array) $target));

/* no picture, wrong id, a row that points at a deleted file, or a viewer this
   account shares nothing with (the 404 is deliberately the same answer: a
   picture that is not yours to see should not announce that it exists) */
if ($path === null || $path === '' || $target === null || !can_view_profile_of((int) $target['id'])) {
    header('Cache-Control: no-store');
    http_response_code(404);
    exit('Not found');
}

$mime = mime_for_ext(ext_of($path));
if (!str_starts_with($mime, 'image/')) {          /* never serve anything but an image */
    header('Cache-Control: no-store');
    http_response_code(404);
    exit('Not found');
}

/* lib.php buffers the page to encrypt URL ids; a binary stream must not sit in
   that buffer (it would arrive corrupted and the length header would be wrong). */
while (ob_get_level() > 0) { @ob_end_clean(); }

$size  = (int) @filesize($path);
$mtime = (int) @filemtime($path);
/* ETag carries the mtime + size, so a picture swap invalidates even if the
   ?t= token in the URL happened to be an old one. */
$etag  = '"' . dechex($mtime) . '-' . dechex($size) . '"';

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Cache-Control: public, max-age=31536000, immutable');
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
/* immutable + the mtime in the URL (?t=) is what keeps the top-bar circle from
   re-downloading on every page view; a new picture is a new URL, so nothing
   stale can linger. `private` because it is one person's face, not a poster. */
header('Cache-Control: private, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');   /* the bytes are an image; do not re-read them as anything else */
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Content-Disposition: inline');       /* shown, never saved as a download */

readfile($path);
