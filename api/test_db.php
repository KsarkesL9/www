<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pdo = container()->pdo;

// Fetch statuses
try {
    $stmt = $pdo->query('SELECT * FROM statuses');
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $statuses = $e->getMessage();
}

// Test getAllowedMessageRecipients directly
try {
    $users = container()->userRepo->getAllowedMessageRecipients(1, 4); // admin
    $debug = "OK";
} catch (Exception $e) {
    $debug = $e->getMessage();
}

echo json_encode(['statuses' => $statuses, 'debug' => $debug]);
