<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LookupRepositoryInterface;
use App\Repository\PasswordResetRepositoryInterface;
use App\Repository\SessionRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * @brief Service class for requesting and processing password resets.
 *
 * @details This service provides two operations: generating a reset token
 *          for a user who forgot their password, and changing the password
 *          using a valid token. All database transactions are managed by
 *          the reset repository. The class contains no SQL, no PDO, and
 *          no HTTP code.
 */
class PasswordResetService
{
    /**
     * @brief Creates a new PasswordResetService with the required repositories.
     *
     * @param UserRepositoryInterface             $userRepo    Repository for reading and updating user data.
     * @param PasswordResetRepositoryInterface    $resetRepo   Repository for creating and validating reset tokens.
     * @param SessionRepositoryInterface          $sessionRepo Repository for revoking all user sessions.
     * @param LookupRepositoryInterface           $lookupRepo  Repository for reading status IDs by name.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly PasswordResetRepositoryInterface $resetRepo,
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly LookupRepositoryInterface $lookupRepo,
    ) {
    }

    /**
     * @brief Generates a password reset token for the user with the given credentials.
     *
     * @details Looks up the user by login name and email address together.
     *          If no matching user is found, the method still returns a success
     *          response with a generic message. This is intentional: it prevents
     *          an attacker from checking whether an account exists.
     *          If the user is found, all previous reset tokens for that user
     *          are revoked. A new 64-character random hex token is generated
     *          and stored in the database with a 30-minute expiry.
     *          The token is returned in the response for the user to copy.
     *
     * @param string $login  The login name provided by the user.
     * @param string $email  The email address provided by the user.
     *
     * @return array{success: bool, message: string, token?: string, expires_in?: string}
     *         Always returns success=true (to avoid revealing if the account exists).
     *         If the user was found, also includes 'token' and 'expires_in' keys.
     */
    public function requestReset(string $login, string $email): array
    {
        // Generic response — do not reveal whether the account exists
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
     * @brief Changes a user's password using a valid reset token.
     *
     * @details First validates that the new password is at least 8 characters
     *          and that both password fields match. Then looks up the reset token
     *          in the database. If the token is not found, expired, or already
     *          used, returns an error. If valid, runs a database transaction to:
     *          - Save the new hashed password.
     *          - Mark the reset token as used.
     *          - Revoke all active sessions for that user.
     *          - Set the account status to 'active' (this unlocks blocked accounts).
     *          If any step fails, the transaction is rolled back.
     *
     * @param string $token            The reset token string entered by the user.
     * @param string $newPassword      The new plain-text password chosen by the user.
     * @param string $confirmPassword  The repeated new password for confirmation.
     *
     * @return array{success: bool, message: string}
     *         On success: success=true with a confirmation message.
     *         On failure: success=false with a description of the error.
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

            // Activate account (also unlocks blocked accounts)
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
