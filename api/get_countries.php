<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo  = getDB();
    $stmt = $pdo->query('SELECT country_id, name FROM countries ORDER BY name ASC');
    $countries = $stmt->fetchAll();
    echo json_encode(['success' => true, 'countries' => $countries]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Błąd pobierania krajów.']);
}
