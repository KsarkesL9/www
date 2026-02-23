<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_COOKIE['session_token'] ?? null;

if ($token) {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'UPDATE user_sessions SET revoked_at = NOW() WHERE token = ?'
    );
    $stmt->execute([$token]);
}

// Usuń ciasteczko
setcookie('session_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
]);

jsonResponse(true, 'Wylogowano pomyślnie.', ['redirect' => '/pages/login.php?msg=logged_out']);
