<?php
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, 'Niedozwolona metoda.');
}

$session = getSessionFromCookie();
if (!$session) {
    http_response_code(401);
    jsonResponse(false, 'Sesja wygasła.');
}

$userId = (int) $session['user_id'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

$messageId = (int) ($input['message_id'] ?? 0);

if (!$messageId) {
    jsonResponse(false, 'Nieprawidłowy identyfikator wiadomości.');
}

$pdo = getDB();

// Sprawdź czy wiadomość należy do użytkownika i nie jest jeszcze usunięta
$stmt = $pdo->prepare(
    'SELECT message_id FROM messages
     WHERE message_id = ?
       AND sender_id  = ?
       AND deleted_at IS NULL
     LIMIT 1'
);
$stmt->execute([$messageId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    jsonResponse(false, 'Nie możesz usunąć tej wiadomości.');
}

// Soft delete – ustawiamy deleted_at
$stmt = $pdo->prepare(
    'UPDATE messages SET deleted_at = NOW() WHERE message_id = ?'
);
$stmt->execute([$messageId]);

jsonResponse(true, 'Wiadomość została usunięta.');