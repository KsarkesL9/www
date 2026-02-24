<?php

/**
 * API: Tworzenie nowego wątku z wiadomością.
 *
 * Handler HTTP — zero logiki biznesowej, zero SQL, zero transakcji.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

requireMethod('POST');
$session = requireApiAuth();

$userId = (int) $session['user_id'];
$input = getJsonInput();

$subject = trim($input['subject'] ?? '');
$content = trim($input['content'] ?? '');
$recipientIds = $input['recipient_ids'] ?? [];

$result = container()->messages->createThread($userId, $recipientIds, $content, $subject);

$data = [];
if (isset($result['thread_id']))
    $data['thread_id'] = $result['thread_id'];
if (isset($result['message_id']))
    $data['message_id'] = $result['message_id'];

jsonResponse($result['success'], $result['message'], $data);