<?php

declare(strict_types=1);

/**
 * Bootstrap aplikacji — ładuje autoloader Composera, konfigurację i tworzy kontener DI.
 *
 * Każdy plik wejściowy (API handler, strona) dołącza ten plik,
 * aby uzyskać dostęp do serwisów i pomocniczych funkcji HTTP.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use App\Repository\PdoUserRepository;
use App\Repository\PdoSessionRepository;
use App\Repository\PdoPasswordResetRepository;
use App\Repository\PdoMessageRepository;
use App\Repository\PdoLookupRepository;
use App\Service\AuthService;
use App\Service\RegistrationService;
use App\Service\PasswordResetService;
use App\Service\MessageService;

// ─── PDO (singleton) ────────────────────────────────────

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Błąd połączenia z bazą danych.']));
        }
    }
    return $pdo;
}

// ─── Kontener serwisów (lazy singletons) ────────────────

function container(): object
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }

    $pdo = getDB();

    // Repozytoria
    $userRepo = new PdoUserRepository($pdo);
    $sessionRepo = new PdoSessionRepository($pdo);
    $resetRepo = new PdoPasswordResetRepository($pdo);
    $messageRepo = new PdoMessageRepository($pdo);
    $lookupRepo = new PdoLookupRepository($pdo);

    // Serwisy
    $authService = new AuthService($userRepo, $sessionRepo, $lookupRepo);
    $registrationService = new RegistrationService($userRepo, $lookupRepo);
    $passwordService = new PasswordResetService($userRepo, $resetRepo, $sessionRepo, $lookupRepo);
    $messageService = new MessageService($messageRepo, $userRepo);

    $c = (object) [
        'pdo' => $pdo,
        'userRepo' => $userRepo,
        'sessionRepo' => $sessionRepo,
        'resetRepo' => $resetRepo,
        'messageRepo' => $messageRepo,
        'lookupRepo' => $lookupRepo,
        'auth' => $authService,
        'registration' => $registrationService,
        'password' => $passwordService,
        'messages' => $messageService,
    ];

    return $c;
}

// ─── Pomocnicze funkcje HTTP (bez zmian) ────────────────

function jsonResponse(bool $success, string $message, array $data = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        http_response_code(405);
        jsonResponse(false, 'Niedozwolona metoda.');
    }
}

function getJsonInput(): array
{
    return json_decode(file_get_contents('php://input'), true) ?? $_POST;
}

function requireApiAuth(): array
{
    $token = $_COOKIE['session_token'] ?? null;
    if (!$token) {
        http_response_code(401);
        jsonResponse(false, 'Sesja wygasła.');
    }
    $session = container()->auth->getSession($token);
    if (!$session) {
        http_response_code(401);
        jsonResponse(false, 'Sesja wygasła.');
    }
    return $session;
}

// ─── Funkcje pomocnicze stron (auth middleware) ─────────

function requireAuth(): array
{
    $token = $_COOKIE['session_token'] ?? null;
    if (!$token) {
        header('Location: /pages/login.php?msg=session_expired');
        exit;
    }
    $session = container()->auth->getSession($token);
    if (!$session) {
        header('Location: /pages/login.php?msg=session_expired');
        exit;
    }
    return $session;
}

function redirectIfLoggedIn(): void
{
    $token = $_COOKIE['session_token'] ?? null;
    if ($token) {
        $session = container()->auth->getSession($token);
        if ($session) {
            header('Location: /pages/dashboard.php');
            exit;
        }
    }
}

// ─── Cookie helpers ─────────────────────────────────────

function setSessionCookie(string $token): void
{
    setcookie('session_token', $token, [
        'expires' => time() + 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

function deleteSessionCookie(): void
{
    setcookie('session_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
