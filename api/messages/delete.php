<?php

/**
 * @file
 * @brief HTTP API handler for soft deleting a message.
 *
 * @details This endpoint processes an authenticated POST request to remove a specific
 *          message. It delegates the deletion operation to the message service. 
 *          Contains zero business logic and zero SQL.
 *
 * @param array $input JSON payload containing 'message_id'.
 *
 * @return void Returns a JSON response with the success status and message.
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