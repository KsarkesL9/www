<?php

/**
 * @file
 * @brief HTTP API handler for user authentication.
 *
 * @details This endpoint parses the incoming HTTP POST request, extracts 
 *          the login and password, and delegates the work to the authentication 
 *          service. It contains zero business logic and zero SQL queries.
 *
 * @param array $input JSON payload containing 'login' and 'password'.
 *
 * @return void Returns a JSON response containing success status, message, and a session token on successful login.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

requireMethod('POST');

$input = getJsonInput();
$login = trim($input['login'] ?? '');
$password = $input['password'] ?? '';

if (empty($login) || empty($password)) {
    jsonResponse(false, 'Podaj login i hasło.');
}

$ip = $_SERVER['HTTP_CLIENT_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '0.0.0.0';
$ip = trim(explode(',', $ip)[0]);

$ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

$result = container()->auth->login($login, $password, $ip, $ua);

if ($result['success'] && isset($result['token'])) {
    setSessionCookie($result['token']);
    jsonResponse(true, $result['message'], ['redirect' => '/pages/dashboard.php']);
}

jsonResponse($result['success'], $result['message']);
