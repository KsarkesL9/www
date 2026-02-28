<?php

/**
 * @file
 * @brief HTTP API handler for user registration.
 *
 * @details This endpoint parses the incoming HTTP POST request containing
 *          the registration form data, and uses the registration service
 *          to create a new user account. It contains zero business logic 
 *          and zero SQL queries.
 *
 * @param array $input JSON payload containing registration data (e.g., login, password, email).
 *
 * @return void Returns a JSON response containing success status, message, and validation error fields if applicable.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

requireMethod('POST');

$input = getJsonInput();

try {
    $result = container()->registration->register($input);

    $data = [];
    if (isset($result['login'])) {
        $data['login'] = $result['login'];
    }
    if (isset($result['field'])) {
        $data['field'] = $result['field'];
    }

    jsonResponse($result['success'], $result['message'], $data);
} catch (PDOException $e) {
    jsonResponse(false, 'Błąd podczas rejestracji. Spróbuj ponownie.');
}
