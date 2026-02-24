<?php

/**
 * API: Usunięcie wiadomości (soft delete).
 *
 * Handler HTTP — zero logiki biznesowej, zero SQL.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

requireMethod('POST');
$session = requireApiAuth();

$userId = (int) $session['user_id'];
$input = getJsonInput();
$messageId = (int) ($input['message_id'] ?? 0);

$result = container()->messages->deleteMessage($userId, $messageId);

if (isset($result['status'])) {
    http_response_code($result['status']);
}

jsonResponse($result['success'], $result['message']);