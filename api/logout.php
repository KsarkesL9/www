<?php

/**
 * @file
 * @brief HTTP API handler for logging out a user.
 *
 * @details This endpoint retrieves the current session token from the cookies,
 *          calls the authentication service to invalidate the session, and 
 *          then deletes the session cookie from the client. It contains 
 *          zero business logic.
 *
 * @return void Returns a JSON response containing success status and a redirect URL.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$token = $_COOKIE['session_token'] ?? null;

if ($token) {
    container()->auth->logout($token);
}

deleteSessionCookie();

jsonResponse(true, 'Wylogowano pomyślnie.', ['redirect' => '/pages/login.php?msg=logged_out']);
