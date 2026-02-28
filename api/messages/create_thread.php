<?php

/**
 * @file
 * @brief HTTP API handler for creating a new message thread.
 *
 * @details This endpoint processes a POST request to create a new conversation thread
 *          and send the first message. It ensures the request is authenticated via the 
 *          API auth handler. Contains zero business logic, zero SQL, and zero transactions.
 *
 * @param array $input JSON payload containing 'subject', 'content', and an array of 'recipient_ids'.
 *
 * @return void Returns a JSON response containing the success status, message, thread ID, and message ID.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

requireMethod('POST');
$session = requireApiAuth();

$userId = (int) $session['user_id'];
$input = getJsonInput();

$subject = trim($input['subject'] ?? '');
$content = trim($input['content'] ?? '');
$rawRecipientIds = $input['recipient_ids'] ?? [];
$recipientIds = [];
// Deobfuscate recipient IDs
foreach ($rawRecipientIds as $obfuscatedId) {
    if (is_string($obfuscatedId)) {
        $realId = deobfuscateId($obfuscatedId);
        if ($realId !== null) {
            $recipientIds[] = $realId;
        }
    }
}

$result = container()->messages->createThread($userId, $recipientIds, $content, $subject);

$data = [];
if (isset($result['thread_id']))
    $data['thread_id'] = $result['thread_id'];
if (isset($result['message_id']))
    $data['message_id'] = $result['message_id'];

jsonResponse($result['success'], $result['message'], $data);