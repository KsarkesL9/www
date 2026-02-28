<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LookupRepositoryInterface;
use App\Repository\SessionRepositoryInterface;
use App\Repository\UserRepositoryInterface;

/**
 * @brief Service class for user authentication and session management.
 *
 * @details This service handles user login, logout, and session validation.
 *          It checks the account status, verifies the password, counts
 *          failed login attempts, and blocks accounts when needed.
 *          The class contains no SQL, no PDO, and no HTTP code.
 *          All data access is done through repository interfaces.
 */
class AuthService
{
    /** @brief Maximum number of failed login attempts before the account is blocked. */
    private const MAX_FAILED_ATTEMPTS = 5;

    /**
     * @brief Creates a new AuthService with the required repositories.
     *
     * @param UserRepositoryInterface    $userRepo    Repository for reading and updating user data.
     * @param SessionRepositoryInterface $sessionRepo Repository for creating and revoking sessions.
     * @param LookupRepositoryInterface  $lookupRepo  Repository for reading status and role names.
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly LookupRepositoryInterface $lookupRepo,
    ) {
    }

    /**
     * @brief Tries to log in a user with the given credentials.
     *
     * @details The method looks up the user by login name. If not found,
     *          it returns an error. If found, it checks the account status:
     *          - Blocked accounts: returns an error and stops.
     *          - Pending accounts: returns an error and stops.
     *          - Inactive accounts: returns an error and stops.
     *          If the account is active, it verifies the password using
     *          PHP's password_verify() function. On a wrong password, it
     *          increments the failed attempts counter. If the counter
     *          reaches MAX_FAILED_ATTEMPTS (5), it sets the account status
     *          to 'blocked'. On a correct password, it resets the counter,
     *          generates a 64-character random hex token, creates a new
     *          session in the database, and returns success with the token.
     *
     * @param string $login     The login name entered by the user.
     * @param string $password  The plain-text password entered by the user.
     * @param string $ip        The client IP address from the HTTP request.
     * @param string $userAgent The browser user agent string from the HTTP request.
     *
     * @return array{success: bool, message: string, token?: string}
     *         An array with a 'success' boolean and a 'message' string.
     *         On success, also includes a 'token' key with the session token.
     */
    public function login(string $login, string $password, string $ip, string $userAgent): array
    {
        $user = $this->userRepo->findByLogin($login);

        if (!$user) {
            return ['success' => false, 'message' => 'Nieprawidłowy login lub hasło.'];
        }

        $userId = $user->getUserId();

        // Verify password FIRST to prevent account status enumeration/data leak
        if (!password_verify($password, $user->getPassword())) {
            $newAttempts = $this->userRepo->incrementFailedLogins($userId);

            if ($newAttempts >= self::MAX_FAILED_ATTEMPTS) {
                $blockedStatusId = $this->lookupRepo->getStatusIdByName('zablokowany');
                if ($blockedStatusId !== null && !$user->isBlocked()) {
                    $this->userRepo->updateStatus($userId, $blockedStatusId);
                }
            }

            return [
                'success' => false,
                'message' => 'Nieprawidłowy login lub hasło.',
            ];
        }

        // Check account status ONLY AFTER verifying the correct password
        if ($user->isBlocked()) {
            return [
                'success' => false,
                'message' => 'Konto zostało zablokowane z powodu zbyt wielu nieudanych prób logowania. Skontaktuj się z administratorem lub zresetuj hasło.',
            ];
        }

        if ($user->isPending()) {
            return [
                'success' => false,
                'message' => 'Twoje konto oczekuje na aktywację przez administratora. Skontaktuj się z działem obsługi.',
            ];
        }

        if (!$user->isActive()) {
            return [
                'success' => false,
                'message' => 'Twoje konto jest nieaktywne. Skontaktuj się z administratorem.',
            ];
        }

        // Successful login
        $this->userRepo->resetFailedLogins($userId);
        $token = bin2hex(random_bytes(32));
        $this->sessionRepo->create($userId, $token, $ip, $userAgent);

        return ['success' => true, 'message' => 'Zalogowano pomyślnie.', 'token' => $token];
    }

    /**
     * @brief Logs out a user by revoking the session identified by the given token.
     *
     * @details Calls the session repository to mark the session as revoked
     *          in the database. After this call, the token can no longer
     *          be used to access protected pages.
     *
     * @param string $token  The session token from the browser cookie.
     *
     * @return void
     */
    public function logout(string $token): void
    {
        $this->sessionRepo->revoke($token);
    }

    /**
     * @brief Looks up an active session by token and returns the session data.
     *
     * @details Finds the session in the database. If not found, returns null.
     *          Then checks that the user's account status is 'active'.
     *          If the account is not active, returns null. If everything is
     *          valid, the session expiry time is refreshed (extended by 1 hour)
     *          and the session data is returned as a plain array.
     *
     * @param string $token  The session token from the browser cookie.
     *
     * @return array|null  An associative array with all session and user fields,
     *                     or null if the session is not valid or the account
     *                     is not active.
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
