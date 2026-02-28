<?php

/**
 * @file
 * @brief HTTP API handler for sending a reply in an existing thread.
 *
 * @details This endpoint handles authenticated POST requests to send a new message
 *          to a specific thread. It uses the message service to process the action. 
 *          Contains zero business logic, zero SQL, and zero transactions.
 *
 * @param array $input JSON payload containing 'thread_id' and 'content'.
 *
 * @return void Returns a JSON response containing the success status, message, and the new message data.
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