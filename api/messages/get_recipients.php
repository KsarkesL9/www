<?php

/**
 * @file
 * @brief HTTP API handler for getting allowed message recipients for the current user.
 *
 * @details This endpoint returns all users the current user is allowed to send a message to,
 *          grouped or sorted by role.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

requireMethod('GET');

try {
    $session = requireApiAuth();
    $userId = $session['user_id'];
    $roleId = $session['role_id'];
    $users = container()->userRepo->getAllowedMessageRecipients($userId, $roleId);

    // Obfuscate user IDs before sending to frontend
    foreach ($users as &$user) {
        $user['user_id'] = obfuscateId((int) $user['user_id']);
    }

    echo json_encode(['success' => true, 'users' => $users]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'users' => [], 'message' => 'Błąd podczas pobierania odbiorców: ' . $e->getMessage()]);
}
