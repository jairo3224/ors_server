<?php

// middleware/AuthMiddleware.php

declare(strict_types=1);

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthMiddleware
{
    /**
     * Verify the access token. Sets $GLOBALS['auth_user'] on success.
     * Call this at the top of any protected route handler.
     */
    public static function handle(): void
    {
        $token = JwtHelper::extractFromHeader();

        if ($token === null) {
            Response::unauthorized('No access token provided.');
        }

        try {
            $decoded = JwtHelper::decode($token);
        } catch (Firebase\JWT\ExpiredException $e) {
            Response::unauthorized('Access token has expired.');
        } catch (Firebase\JWT\SignatureInvalidException $e) {
            Response::unauthorized('Invalid token signature.');
        } catch (Exception $e) {
            Response::unauthorized('Invalid access token.');
        }

        // Ensure this is an access token, not a refresh token
        if (($decoded->type ?? '') !== 'access') {
            Response::unauthorized('Invalid token type.');
        }

        // Make decoded user data available globally for this request
        $GLOBALS['auth_user'] = $decoded->data;
    }

    /**
     * Restrict access to specific roles.
     * Call after handle().
     *
     * @param string[] $allowedRoles  e.g. ['OSAS', 'Guidance Office']
     */
    public static function requireRoles(array $allowedRoles): void
    {
        $user = $GLOBALS['auth_user'] ?? null;

        if ($user === null) {
            Response::unauthorized('Not authenticated.');
        }

        $userRole = $user->role ?? '';

        if (!in_array($userRole, $allowedRoles, true)) {
            Response::forbidden('You do not have permission to perform this action.');
        }
    }

    /**
     * Convenience: get the currently authenticated user data.
     */
    public static function user(): ?object
    {
        return $GLOBALS['auth_user'] ?? null;
    }
}
