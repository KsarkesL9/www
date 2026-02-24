<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireMethod('GET');
$session = requireApiAuth();

$query = trim($_GET['q'] ?? '');

if (mb_strlen($query) < 2) {
    echo json_encode(['success' => true, 'users' => []]);
    exit;
}

$pdo = getDB();

try {
    $like = '%' . $query . '%';
    $stmt = $pdo->prepare(
        "SELECT
            u.user_id,
            CONCAT(u.first_name, ' ', u.surname) AS full_name,
            u.login,
            r.role_name
         FROM users u
         JOIN roles r    ON r.role_id  = u.role_id
         JOIN statuses s ON s.status_id = u.status_id
         WHERE s.name = 'aktywny'
           AND (
               u.first_name LIKE ?
               OR u.surname  LIKE ?
               OR CONCAT(u.first_name, ' ', u.surname) LIKE ?
               OR u.login    LIKE ?
           )
         ORDER BY u.surname, u.first_name
         LIMIT 15"
    );
    $stmt->execute([$like, $like, $like, $like]);
    $users = $stmt->fetchAll();

    echo json_encode(['success' => true, 'users' => $users]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'users' => [], 'message' => 'Błąd wyszukiwania.']);
}