<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireMethod('POST');
$session = requireApiAuth();

$userId = (int) $session['user_id'];
$input = getJsonInput();

$subject = trim($input['subject'] ?? '');
$content = trim($input['content'] ?? '');
$recipientIds = $input['recipient_ids'] ?? [];

if (empty($content)) {
    jsonResponse(false, 'Treść wiadomości jest wymagana.');
}

if (empty($recipientIds) || !is_array($recipientIds)) {
    jsonResponse(false, 'Podaj co najmniej jednego odbiorcę.');
}

// Sanitize recipient IDs
$recipientIds = array_values(array_unique(array_map('intval', $recipientIds)));
$recipientIds = array_filter($recipientIds, fn($id) => $id > 0 && $id !== $userId);

if (empty($recipientIds)) {
    jsonResponse(false, 'Nieprawidłowi odbiorcy.');
}

$pdo = getDB();

// Weryfikacja czy odbiorcy istnieją i mają aktywne konta
$placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
$stmt = $pdo->prepare(
    "SELECT u.user_id
     FROM users u
     JOIN statuses s ON s.status_id = u.status_id
     WHERE u.user_id IN ($placeholders)
       AND s.name = 'aktywny'"
);
$stmt->execute($recipientIds);
$validRecipients = array_column($stmt->fetchAll(), 'user_id');

if (empty($validRecipients)) {
    jsonResponse(false, 'Żaden z podanych odbiorców nie ma aktywnego konta.');
}

try {
    $pdo->beginTransaction();

    // 1. Utwórz wątek
    $stmt = $pdo->prepare(
        'INSERT INTO message_threads (subject) VALUES (?)'
    );
    $stmt->execute([$subject ?: null]);
    $threadId = (int) $pdo->lastInsertId();

    // 2. Dodaj uczestników (nadawca + odbiorcy)
    $allParticipants = array_unique(array_merge([$userId], $validRecipients));
    $stmtPart = $pdo->prepare(
        'INSERT INTO message_thread_participants (thread_id, user_id) VALUES (?, ?)'
    );
    foreach ($allParticipants as $participantId) {
        $stmtPart->execute([$threadId, $participantId]);
    }

    // 3. Wyślij pierwszą wiadomość
    $stmtMsg = $pdo->prepare(
        'INSERT INTO messages (thread_id, sender_id, content) VALUES (?, ?, ?)'
    );
    $stmtMsg->execute([$threadId, $userId, $content]);
    $messageId = (int) $pdo->lastInsertId();

    // 4. Zaktualizuj last_read_at nadawcy
    $pdo->prepare(
        'UPDATE message_thread_participants
         SET last_read_at = NOW()
         WHERE thread_id = ? AND user_id = ?'
    )->execute([$threadId, $userId]);

    $pdo->commit();

    jsonResponse(true, 'Wiadomość wysłana.', [
        'thread_id' => $threadId,
        'message_id' => $messageId,
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Błąd podczas wysyłania wiadomości. Spróbuj ponownie.');
}