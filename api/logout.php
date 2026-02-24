<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$token = $_COOKIE['session_token'] ?? null;

if ($token) {
    revokeSession($token);
}

deleteSessionCookie();

jsonResponse(true, 'Wylogowano pomyślnie.', ['redirect' => '/pages/login.php?msg=logged_out']);
