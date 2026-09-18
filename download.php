<?php
/**
 * Secure file streaming endpoint.
 * Only the course teacher and enrolled students may open materials/videos.
 * Students view documents in the browser (inline); only teachers get downloads.
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$userId = (int) $user['id'];
$courseId = (int) ($_GET['c'] ?? 0);
$materialId = (int) ($_GET['m'] ?? 0);

$course = course_row($courseId);
if (!$course) { http_response_code(404); exit('Course not found.'); }

$isOwner = (int) $course['teacher_id'] === $userId;
if (!$isOwner && !is_enrolled_id($courseId, $userId)) {
    http_response_code(403);
    exit('Enroll in this course to access its files.');
}

$material = get_material($courseId, $materialId);
if (!$material || !in_array($material['type'], ['file', 'video'], true)) {
    http_response_code(404);
    exit('File not found.');
}

$path = UPLOAD_DIR . '/' . basename((string) ($material['filename'] ?? ''));
if (!is_file($path)) { http_response_code(404); exit('File is missing on the server.'); }

$ext = ext_of($path);
$userRow = find_user_by_id($userId);
$isTeacher = $isOwner || (($userRow['role'] ?? '') === 'teacher');
// Students always view inline (read directly on the website, no download).
// Teachers keep the original download behaviour for their own copy.
$disposition = 'inline';
if ($isTeacher && ($_GET['disp'] ?? '') !== 'inline' && !in_array($ext, INLINE_EXTS, true)) {
    $disposition = 'attachment';
}
/* the stored file keeps its original extension (enforced on upload), so the
   extension decides the type — never re-wrap or convert the bytes; this makes
   every material come back in exactly the format it was uploaded in, even for
   older rows whose DB mime was mislabeled by mime_content_type() */
$mime = mime_for_ext($ext);
if ($mime === '') {
    $mime = trim((string) ($material['mime'] ?? ''));
    if ($mime === '') $mime = guess_mime((string) $material['filename']);
}
$name = (string) ($material['orig_name'] ?? basename($path));

/* lib.php starts an output buffer to encrypt URL ids in page HTML; a file
   stream must not sit in that buffer (a big video would be buffered whole in
   memory), so hand the raw stream the empty buffer only. */
while (ob_get_level() > 0) { @ob_end_clean(); }

serve_file_with_range($path, $mime, $name, $disposition);
