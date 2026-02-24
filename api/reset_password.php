<?php

/**
 * API: Resetowanie hasła za pomocą tokenu.
 *
 * Handler HTTP — zero logiki biznesowej, zero SQL.
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

$pdo = getDB();

try {
    $pdo->beginTransaction();

    $result = container()->password->resetPassword($token, $newPassword, $confirmPassword);

    if ($result['success']) {
        $pdo->commit();
        deleteSessionCookie();
    } else {
        $pdo->rollBack();
    }

    jsonResponse($result['success'], $result['message']);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(false, 'Błąd podczas zmiany hasła. Spróbuj ponownie.');
}
