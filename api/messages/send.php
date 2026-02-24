<?php

/**
 * API: Wysłanie wiadomości w istniejącym wątku.
 *
 * Handler HTTP — zero logiki biznesowej, zero SQL, zero transakcji.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

requireMethod('POST');
$session = requireApiAuth();

$userId = (int) $session['user_id'];
$input = getJsonInput();
$threadId = (int) ($input['thread_id'] ?? 0);
$content = trim($input['content'] ?? '');

$result = container()->messages->sendMessage($userId, $threadId, $content);

if (isset($result['status'])) {
    http_response_code($result['status']);
}

$data = [];
if (isset($result['data'])) {
    $data['message'] = $result['data'];
}

jsonResponse($result['success'], $result['message'], $data);