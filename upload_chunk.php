<?php
/**
 * Chunked upload endpoint — receives ONE piece of a large lesson file.
 *
 * Why this exists: shared hosts cap a single request (post_max_size /
 * upload_max_filesize, often 10–20 MB). A 200 MB video can never be delivered
 * in one POST, so assets/app.js slices the file and posts the pieces here in
 * order. Each piece is appended to uploads/.parts/<upload_id>.part; the final
 * piece publishes the file into uploads/ and creates the lesson row. The
 * database stores metadata only — the video bytes stay on disk.
 *
 * Answers JSON: {ok, received, total, done, title?, redirect?} or {ok:false, error}.
 */
require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');

/** Every exit goes through here so the browser always gets JSON, never an HTML error page. */
function chunk_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chunk_json(['ok' => false, 'error' => 'This endpoint accepts POST only.'], 405);
}

/* Point the client at the next piece instead of letting it retry a doomed one. */
function chunk_error(string $msg, int $code = 200): void
{
    chunk_json(['ok' => false, 'error' => $msg, 'retry' => false], $code);
}

$user = current_user();
if (!$user) {
    chunk_error('Your session expired — please sign in again and retry.', 401);
}
if (!in_array((string) ($user['role'] ?? ''), ['teacher', 'admin'], true)) {
    chunk_error('Only teachers can upload lessons.', 403);
}

/* A POST bigger than post_max_size arrives with an empty $_POST/$_FILES —
   PHP has already discarded it, so tell the client to use smaller pieces. */
if (empty($_POST) && empty($_FILES)) {
    chunk_error('This server refused the piece as too large. Reduce the piece size or upload a smaller file.', 413);
}

/* CSRF — the same token every other POST form in the app uses. */
$token = (string) ($_POST['csrf'] ?? '');
if ($token === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
    chunk_error('Your session expired — please reload the page and try again.', 403);
}

$file = null;
foreach (['chunk', 'file'] as $field) {
    if (isset($_FILES[$field]) && is_array($_FILES[$field])) { $file = $_FILES[$field]; break; }
}
if ($file === null) {
    chunk_error('No piece was received.');
}
$err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err !== UPLOAD_ERR_OK) {
    chunk_error(upload_error_message($err));
}

try {
    $result = receive_upload_chunk($user, $_POST, $file);
    chunk_json([
        'ok'       => true,
        'received' => (int) $result['received'],
        'total'    => (int) $result['total'],
        'done'     => (bool) $result['done'],
        'title'    => $result['title'],
        'redirect' => $result['redirect'],
    ]);
} catch (Throwable $ex) {
    $msg = $ex->getMessage();
    if ($msg === '') $msg = 'The upload could not be saved.';
    lms_error_log('chunk upload: ' . $msg);
    chunk_json(['ok' => false, 'error' => $msg, 'retry' => false]);
}
