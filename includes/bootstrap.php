<?php

declare(strict_types=1);

/**
 * Bootstrap aplikacji — ładuje autoloader Composera, konfigurację .env i tworzy kontener DI.
 *
 * Każdy plik wejściowy (API handler, strona) dołącza ten plik,
 * aby uzyskać dostęp do serwisów i pomocniczych funkcji HTTP.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ─── Ładowanie .env ─────────────────────────────────────

(function () {
    $envFile = __DIR__ . '/../.env';
    if (!file_exists($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Usuń otaczające cudzysłowy
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
                $value = $m[2];
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
})();

// ─── Import klas ────────────────────────────────────────

use App\Repository\PdoUserRepository;
use App\Repository\PdoSessionRepository;
use App\Repository\PdoPasswordResetRepository;
use App\Repository\PdoMessageRepository;
use App\Repository\PdoLookupRepository;
use App\Repository\PdoDashboardRepository;
use App\Repository\PdoThreadViewRepository;
use App\Service\AuthService;
use App\Service\RegistrationService;
use App\Service\PasswordResetService;
use App\Service\MessageService;
use App\Service\DashboardService;
use App\Service\ThreadViewService;

// ─── PDO (singleton) ────────────────────────────────────

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $_ENV['DB_HOST'] ?? 'localhost',
            (int) ($_ENV['DB_PORT'] ?? 3306),
            $_ENV['DB_NAME'] ?? 'edux',
            $_ENV['DB_CHARSET'] ?? 'utf8mb4'
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $pdo = new PDO(
                $dsn,
                $_ENV['DB_USER'] ?? 'root',
                $_ENV['DB_PASS'] ?? '',
                $options
            );
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
    $dashboardRepo = new PdoDashboardRepository($pdo);
    $threadViewRepo = new PdoThreadViewRepository($pdo);

    // Serwisy
    $authService = new AuthService($userRepo, $sessionRepo, $lookupRepo);
    $registrationService = new RegistrationService($userRepo, $lookupRepo);
    $passwordService = new PasswordResetService($userRepo, $resetRepo, $sessionRepo, $lookupRepo);
    $messageService = new MessageService($messageRepo, $userRepo);
    $dashboardService = new DashboardService($dashboardRepo);
    $threadViewService = new ThreadViewService($threadViewRepo);

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
        'dashboard' => $dashboardService,
        'threadView' => $threadViewService,
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
