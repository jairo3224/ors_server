<?php

// config/jwt.php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtConfig
{
    // ─── Pull secret from environment — NEVER hardcode in production ───
    public static function secret(): string
    {
        $secret = getenv('JWT_SECRET') ?: ($_ENV['JWT_SECRET'] ?? null) ?: ($_SERVER['JWT_SECRET'] ?? null);

        if (!$secret) {
            throw new RuntimeException('JWT_SECRET environment variable is not set.');
        }

        return $secret;
    }

    public static string $algorithm   = 'HS256';
    public static int    $accessTTL   = 60 * 60 * 2;       // 2 hours
    public static int    $refreshTTL  = 60 * 60 * 24 * 7;  // 7 days
}

class JwtHelper
{
    /**
     * Issue an access token (short-lived).
     * Payload carries the minimum needed — never store sensitive data here.
     */
    public static function generateAccessToken(array $user): string
    {
        $now = time();

        $payload = [
            'iss'  => getenv('APP_URL') ?: 'http://localhost',   // issuer
            'iat'  => $now,                                       // issued at
            'nbf'  => $now,                                       // not before
            'exp'  => $now + JwtConfig::$accessTTL,              // expiry
            'type' => 'access',
            'sub'  => $user['id'],                               // subject
            'data' => [
                'id'          => $user['id'],
                'employee_id' => $user['employee_id'],
                'email'       => $user['email'],
                'first_name'  => $user['first_name'],
                'last_name'   => $user['last_name'],
                'role'        => $user['role_name'],
                'role_id'     => $user['role_id'],
                'department'  => $user['department_name'] ?? null,
            ],
        ];

        return JWT::encode($payload, JwtConfig::secret(), JwtConfig::$algorithm);
    }

    /**
     * Issue a refresh token (long-lived, minimal payload).
     * Used only to re-issue access tokens — stored securely by the client.
     */
    public static function generateRefreshToken(int $userId): string
    {
        $now = time();

        $payload = [
            'iss'  => getenv('APP_URL') ?: 'http://localhost',
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + JwtConfig::$refreshTTL,
            'type' => 'refresh',
            'sub'  => $userId,
        ];

        return JWT::encode($payload, JwtConfig::secret(), JwtConfig::$algorithm);
    }

    /**
     * Decode and validate a token. Returns the decoded payload or throws.
     */
    public static function decode(string $token): object
    {
        return JWT::decode(
            $token,
            new Key(JwtConfig::secret(), JwtConfig::$algorithm)
        );
    }

    /**
     * Extract Bearer token from Authorization header.
     * Returns null if not found or malformed.
     */
    public static function extractFromHeader(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? apache_request_headers()['Authorization']
            ?? null;

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));
        return $token !== '' ? $token : null;
    }
}
