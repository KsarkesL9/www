<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Repository\LookupRepositoryInterface;
use App\Repository\SessionRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * Serwis autoryzacji — logowanie, blokowanie kont, walidacja sessji.
 *
 * Zero SQL, zero PDO, zero $_POST / header() / json_encode().
 */
class AuthService
{
    private const MAX_FAILED_ATTEMPTS = 5;

    public function __construct(
        private UserRepositoryInterface $userRepo,
        private SessionRepositoryInterface $sessionRepo,
        private LookupRepositoryInterface $lookupRepo,
    ) {
    }

    /**
     * Logowanie użytkownika.
     *
     * @return array{success: bool, message: string, token?: string}
     */
    public function login(string $login, string $password, string $ip, string $userAgent): array
    {
        $user = $this->userRepo->findByLogin($login);

        if (!$user) {
            return ['success' => false, 'message' => 'Nieprawidłowy login lub hasło.'];
        }

        $userId = $user->getUserId();

        // Sprawdź status konta
        $blockedStatusId = $this->lookupRepo->getStatusIdByName('zablokowany');
        $pendingStatusId = $this->lookupRepo->getStatusIdByName('oczekujący');
        $activeStatusId = $this->lookupRepo->getStatusIdByName('aktywny');

        if ($user->getStatusId() === $blockedStatusId) {
            return [
                'success' => false,
                'message' => 'Konto zostało zablokowane z powodu zbyt wielu nieudanych prób logowania. Skontaktuj się z administratorem lub zresetuj hasło.',
            ];
        }

        if ($user->getStatusId() === $pendingStatusId) {
            return [
                'success' => false,
                'message' => 'Twoje konto oczekuje na aktywację przez administratora. Skontaktuj się z działem obsługi.',
            ];
        }

        if ($user->getStatusId() !== $activeStatusId) {
            return [
                'success' => false,
                'message' => 'Twoje konto jest nieaktywne. Skontaktuj się z administratorem.',
            ];
        }

        // Weryfikacja hasła
        if (!password_verify($password, $user->getPassword())) {
            $newAttempts = $this->userRepo->incrementFailedLogins($userId);

            if ($newAttempts >= self::MAX_FAILED_ATTEMPTS) {
                if ($blockedStatusId !== null) {
                    $this->userRepo->updateStatus($userId, $blockedStatusId);
                }
                return [
                    'success' => false,
                    'message' => 'Konto zostało zablokowane po 5 nieudanych próbach logowania. Zresetuj hasło, aby je odblokować.',
                ];
            }

            $remaining = self::MAX_FAILED_ATTEMPTS - $newAttempts;
            return [
                'success' => false,
                'message' => "Nieprawidłowy login lub hasło. Pozostało prób: $remaining.",
            ];
        }

        // Udane logowanie
        $this->userRepo->resetFailedLogins($userId);
        $token = bin2hex(random_bytes(32));
        $this->sessionRepo->create($userId, $token, $ip, $userAgent);

        return ['success' => true, 'message' => 'Zalogowano pomyślnie.', 'token' => $token];
    }

    /**
     * Wylogowanie — unieważnia sesję po tokenie.
     */
    public function logout(string $token): void
    {
        $this->sessionRepo->revoke($token);
    }

    /**
     * Pobiera aktywną sesję z tokenu.
     * Sprawdza czy konto jest aktywne i odświeża sesję.
     *
     * @return array|null  Tablica z danymi sesji (compatibility) lub null
     */
    public function getSession(string $token): ?array
    {
        $session = $this->sessionRepo->findActiveByToken($token);
        if (!$session) {
            return null;
        }

        $activeStatusId = $this->lookupRepo->getStatusIdByName('aktywny');
        if ($session->getStatusId() !== $activeStatusId) {
            return null;
        }

        $this->sessionRepo->refresh($token);

        return $session->toArray();
    }
}
