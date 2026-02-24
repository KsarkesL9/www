<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LookupRepositoryInterface;
use App\Repository\PasswordResetRepositoryInterface;
use App\Repository\SessionRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * Serwis resetowania haseł.
 *
 * Zero SQL, zero PDO, zero HTTP.
 * Transakcje zarządzane wewnętrznie przez repozytorium.
 */
class PasswordResetService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly PasswordResetRepositoryInterface $resetRepo,
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly LookupRepositoryInterface $lookupRepo,
    ) {
    }

    /**
     * Żądanie tokenu resetowania hasła.
     *
     * @return array{success: bool, message: string, token?: string, expires_in?: string}
     */
    public function requestReset(string $login, string $email): array
    {
        // Ogólna odpowiedź — nie ujawniamy czy konto istnieje
        $genericMessage = 'Jeśli podane dane są prawidłowe, token resetowania hasła został wygenerowany. Zapisz go poniżej.';

        $user = $this->userRepo->findByLoginAndEmail($login, $email);

        if (!$user) {
            return ['success' => true, 'message' => $genericMessage];
        }

        $userId = $user->getUserId();
        $this->resetRepo->revokeAllForUser($userId);
        $token = bin2hex(random_bytes(32));
        $this->resetRepo->create($userId, $token);

        return [
            'success' => true,
            'message' => 'Token wygenerowany pomyślnie. Użyj go do zresetowania hasła.',
            'token' => $token,
            'expires_in' => '30 minut',
        ];
    }

    /**
     * Resetowanie hasła za pomocą tokenu.
     *
     * @return array{success: bool, message: string}
     */
    public function resetPassword(string $token, string $newPassword, string $confirmPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Hasło musi mieć minimum 8 znaków.'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'Hasła nie są identyczne.'];
        }

        $resetToken = $this->resetRepo->findValid($token);
        if (!$resetToken) {
            return ['success' => false, 'message' => 'Token jest nieprawidłowy lub wygasł.'];
        }

        $userId = $resetToken->getUserId();
        $tokenId = $resetToken->getTokenId();
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        $this->resetRepo->beginTransaction();
        try {
            $this->userRepo->updatePassword($userId, $hashedPassword);
            $this->resetRepo->markUsed($tokenId);
            $this->sessionRepo->revokeAllForUser($userId);

            // Aktywuj konto (odblokowuje po zablokowanym koncie)
            $activeStatusId = $this->lookupRepo->getStatusIdByName('aktywny');
            if ($activeStatusId !== null) {
                $this->userRepo->updateStatus($userId, $activeStatusId);
            }

            $this->resetRepo->commit();

            return ['success' => true, 'message' => 'Hasło zostało zmienione. Zaloguj się nowym hasłem.'];
        } catch (\Exception $e) {
            $this->resetRepo->rollBack();
            return ['success' => false, 'message' => 'Błąd podczas zmiany hasła. Spróbuj ponownie.'];
        }
    }
}
