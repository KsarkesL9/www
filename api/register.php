<?php

/**
 * API: Rejestracja użytkownika.
 *
 * Handler HTTP — parsuje request, wywołuje serwis, zwraca odpowiedź.
 * Zero logiki biznesowej, zero SQL.
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
