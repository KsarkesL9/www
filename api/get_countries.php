<?php

/**
 * @file
 * @brief HTTP API handler for retrieving the list of countries.
 *
 * @details This endpoint contains zero business logic. It calls the 
 *          repository container to fetch all available countries.
 *          It outputs a JSON array of country data.
 *
 * @return void Returns a JSON response containing success status and the countries array.
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
