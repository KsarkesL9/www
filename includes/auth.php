<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * Middleware – wymaga zalogowania. Jeśli brak sesji, przekierowuje na login.
 */
function requireAuth(): array
{
    $session = getSessionFromCookie();
    if (!$session) {
        header('Location: /pages/login.php?msg=session_expired');
        exit;
    }
    return $session;
}

/**
 * Przekierowuje zalogowanego użytkownika z formularza logowania/rejestracji.
 */
function redirectIfLoggedIn(): void
{
    $session = getSessionFromCookie();
    if ($session) {
        header('Location: /pages/dashboard.php');
        exit;
    }
}
