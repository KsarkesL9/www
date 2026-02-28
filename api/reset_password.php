<?php

/**
 * @file
 * @brief HTTP API handler for resetting a password using a token.
 *
 * @details This endpoint expects a valid token along with a new password and
 *          its confirmation. It passes the data to the password service
 *          to change the user's password. It contains zero business logic, 
 *          zero SQL, and zero transactions.
 *
 * @param array $input JSON payload containing 'token', 'new_password', and 'confirm_password'.
 *
 * @return void Returns a JSON response containing the success status and a message.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

requireMethod('POST');

$input = getJsonInput();
$token = trim($input['token'] ?? '');
$newPassword = $input['new_password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
    jsonResponse(false, 'Wypełnij wszystkie pola.');
}

$result = container()->password->resetPassword($token, $newPassword, $confirmPassword);

if ($result['success']) {
    deleteSessionCookie();
}

jsonResponse($result['success'], $result['message']);
