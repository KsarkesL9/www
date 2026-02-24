<?php

/**
 * API: Resetowanie hasła za pomocą tokenu.
 *
 * Handler HTTP — zero logiki biznesowej, zero SQL, zero transakcji.
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
