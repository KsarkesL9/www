<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireMethod('POST');
$session = requireApiAuth();

$userId = (int) $session['user_id'];
$input = getJsonInput();

$threadId = (int) ($input['thread_id'] ?? 0);
$content = trim($input['content'] ?? '');

if (!$threadId) {
    jsonResponse(false, 'Nieprawidłowy wątek.');
}

if (empty($content)) {
    jsonResponse(false, 'Treść wiadomości jest wymagana.');
}

$pdo = getDB();

// Sprawdź czy użytkownik jest uczestnikiem wątku
$stmt = $pdo->prepare(
    'SELECT thread_id FROM message_thread_participants
     WHERE thread_id = ? AND user_id = ? LIMIT 1'
);
$stmt->execute([$threadId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    jsonResponse(false, 'Nie jesteś uczestnikiem tego wątku.');
}

try {
    $pdo->beginTransaction();

    // Wstaw wiadomość
    $stmt = $pdo->prepare(
        'INSERT INTO messages (thread_id, sender_id, content) VALUES (?, ?, ?)'
    );
    $stmt->execute([$threadId, $userId, $content]);
    $messageId = (int) $pdo->lastInsertId();

    // Zaktualizuj last_read_at nadawcy
    $pdo->prepare(
        'UPDATE message_thread_participants
         SET last_read_at = NOW()
         WHERE thread_id = ? AND user_id = ?'
    )->execute([$threadId, $userId]);

    $pdo->commit();

    // Pobierz dane nowej wiadomości do odpowiedzi
    $stmt = $pdo->prepare(
        'SELECT message_id, sender_id, content, created_at FROM messages WHERE message_id = ? LIMIT 1'
    );
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();

    jsonResponse(true, 'Wiadomość wysłana.', ['message' => $msg]);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Błąd podczas wysyłania wiadomości. Spróbuj ponownie.');
}