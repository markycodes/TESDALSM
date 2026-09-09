<?php
/** Presence lookup — returns which of the given user ids are online right now (JSON). */
require_once __DIR__ . '/lib.php';
if (current_user() === null) { http_response_code(403); exit; }

$raw = (string) ($_GET['ids'] ?? '');
$ids = [];
foreach (explode(',', $raw) as $part) {
    $n = (int) trim($part);
    if ($n > 0) $ids[] = $n;
}
$online = online_user_ids($ids);
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'online' => $online]);
exit;