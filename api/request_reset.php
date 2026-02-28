<?php

/**
 * @file
 * @brief HTTP API handler for requesting a password reset token.
 *
 * @details This endpoint accepts a user's login and email address via POST,
 *          validates the email format, and delegates token generation to the
 *          password service. It contains zero business logic and zero SQL.
 *
 * @param array $input JSON payload containing 'login' and 'email_address'.
 *
 * @return void Returns a JSON response containing success status, message, the generated token, and expiration time.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

requireMethod('POST');

$input = getJsonInput();
$login = trim($input['login'] ?? '');
$email = trim($input['email_address'] ?? '');

if (empty($login) || empty($email)) {
    jsonResponse(false, 'Podaj login i adres e-mail.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Podaj prawidłowy adres e-mail.');
}

$result = container()->password->requestReset($login, $email);

$data = [];
if (isset($result['token'])) {
    $data['token'] = $result['token'];
}
if (isset($result['expires_in'])) {
    $data['expires_in'] = $result['expires_in'];
}

jsonResponse($result['success'], $result['message'], $data);
