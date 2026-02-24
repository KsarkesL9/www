<?php

/**
 * API: Lista krajów.
 *
 * Handler HTTP — zero logiki biznesowej.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $countries = container()->lookupRepo->getAllCountries();
    echo json_encode(['success' => true, 'countries' => $countries]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd pobierania krajów.']);
}
