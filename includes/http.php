<?php
/**
 * Pomocnicze funkcje HTTP — odpowiedzi JSON, walidacja metod, parsowanie inputu.
 */

/**
 * Wysyła odpowiedź JSON i kończy skrypt
 */
function jsonResponse(bool $success, string $message, array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

/**
 * Wymaga określonej metody HTTP — zwraca 405 przy niezgodności
 */
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        http_response_code(405);
        jsonResponse(false, 'Niedozwolona metoda.');
    }
}

/**
 * Parsuje JSON z body requestu z fallbackiem na $_POST
 */
function getJsonInput(): array
{
    return json_decode(file_get_contents('php://input'), true) ?? $_POST;
}

/**
 * Wymaga zalogowanego użytkownika w kontekście API — zwraca sesję lub 401
 */
function requireApiAuth(): array
{
    $session = getSessionFromCookie();
    if (!$session) {
        http_response_code(401);
        jsonResponse(false, 'Sesja wygasła.');
    }
    return $session;
}
