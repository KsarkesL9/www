<?php

/**
 * API: Żądanie tokenu resetowania hasła.
 *
 * Handler HTTP — zero logiki biznesowej, zero SQL.
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
