<?php

/**
 * API: Wyszukiwanie aktywnych użytkowników.
 *
 * Handler HTTP — zero logiki biznesowej, zero SQL.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

requireMethod('GET');
requireApiAuth();

$query = trim($_GET['q'] ?? '');

try {
    $users = container()->messages->searchUsers($query);
    echo json_encode(['success' => true, 'users' => $users]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'users' => [], 'message' => 'Błąd wyszukiwania.']);
}