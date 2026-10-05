<?php
/**
 * Secure download for a hand-in (submissions/), same idea as download.php: the
 * folder is never served directly, and every request is checked against the row
 * that owns the file.
 *
 * Who may open one — the narrowest that still makes sense:
 *   • the student who handed it in (their own work, any time)
 *   • the teacher who owns the assignment's course
 * A student cannot read a classmate's hand-in even inside the same course, which
 * is why this is not simply "is_enrolled".
 */
require_once __DIR__ . '/lib.php';
$user = require_login();
$userId = (int) $user['id'];

$filename = (string) ($_GET['f'] ?? '');
/* The name must be one WE generated: this is what stops a crafted ?f=../.. or a
   tampered row from pointing the stream at some other file on the host. */
$path = submission_file_path($filename);
if (!$path) { http_response_code(404); exit('File not found.'); }

$st = db()->prepare('SELECT s.*, a.course_id, a.teacher_id
                     FROM submissions s JOIN assignments a ON a.id = s.assignment_id
                     WHERE s.filename = ? LIMIT 1');
$st->execute([$filename]);
$row = $st->fetch();
if ($row === false) { http_response_code(404); exit('File not found.'); }

$isOwner = (int) $row['user_id'] === $userId;
$isTeacher = assignment_can_manage($user, (int) $row['course_id']);
if (!$isOwner && !$isTeacher) { http_response_code(403); exit('That hand-in is not yours to open.'); }

$mime = (string) ($row['mime'] ?? '');
if (!in_array($mime, submission_allowed_mimes(), true)) $mime = 'application/octet-stream';
/* Never inline anything that could execute in a browser context, and never
   inline text either — a hand-in is downloaded and opened, not rendered here. */
$disp = 'attachment';
if (($mime === 'application/pdf' || str_starts_with($mime, 'image/')) && ($_GET['disp'] ?? '') === 'inline') {
    $disp = 'inline';
}
$name = (string) ($row['orig_name'] ?? basename($path));

while (ob_get_level() > 0) { @ob_end_clean(); }

serve_file_with_range($path, $mime, $name, $disp);
