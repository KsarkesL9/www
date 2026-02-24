<?php

/**
 * API: Wylogowanie użytkownika.
 *
 * Handler HTTP — zero logiki biznesowej.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$token = $_COOKIE['session_token'] ?? null;

if ($token) {
    container()->auth->logout($token);
}

deleteSessionCookie();

jsonResponse(true, 'Wylogowano pomyślnie.', ['redirect' => '/pages/login.php?msg=logged_out']);
