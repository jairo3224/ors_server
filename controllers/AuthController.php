<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    // ─────────────────────────────────────────────
    // POST /api/auth/login
    // Body: { email, password }
    // ─────────────────────────────────────────────
    public function login(): void
    {
        $body = $this->parseBody();

        // ── Validation ──────────────────────────
        $errors = [];
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email is not valid.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if (!empty($errors)) {
            Response::error('Validation failed.', 422, $errors);
        }

        // ── Lookup user ─────────────────────────
        $user = $this->userModel->findByEmail($email);

        // Use constant-time comparison regardless of whether user exists
        // to prevent timing-based email enumeration
        $dummyHash = '$2y$12$invalidsaltXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
        $hash      = $user['password'] ?? $dummyHash;

        if (!password_verify($password, $hash) || $user === null) {
            Response::error('Invalid email or password.', 401);
        }

        if (!$user['is_active']) {
            Response::error('Your account has been deactivated. Please contact the administrator.', 403);
        }

        // ── Issue tokens ─────────────────────────
        $accessToken  = JwtHelper::generateAccessToken($user);
        $refreshToken = JwtHelper::generateRefreshToken((int) $user['id']);

        // Send refresh token as HttpOnly cookie (safer than localStorage)
        $this->setRefreshCookie($refreshToken);

        // Strip password before sending any user data
        unset($user['password']);

        Response::success(
            [
                'access_token' => $accessToken,
                'token_type'   => 'Bearer',
                'expires_in'   => JwtConfig::$accessTTL,
                'user'         => $user,
            ],
            'Login successful.'
        );
    }

    // ─────────────────────────────────────────────
    // POST /api/auth/refresh
    // No body — reads refresh token from HttpOnly cookie
    // ─────────────────────────────────────────────
    public function refresh(): void
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;

        if ($refreshToken === null) {
            Response::unauthorized('No refresh token found.');
        }

        try {
            $decoded = JwtHelper::decode($refreshToken);
        } catch (Firebase\JWT\ExpiredException $e) {
            Response::unauthorized('Refresh token has expired. Please log in again.');
        } catch (Exception $e) {
            Response::unauthorized('Invalid refresh token.');
        }

        if (($decoded->type ?? '') !== 'refresh') {
            Response::unauthorized('Invalid token type.');
        }

        $userId = (int) $decoded->sub;
        $user   = $this->userModel->findById($userId);

        if ($user === null || !$user['is_active']) {
            Response::unauthorized('User not found or deactivated.');
        }

        $newAccessToken  = JwtHelper::generateAccessToken($user);
        $newRefreshToken = JwtHelper::generateRefreshToken($userId);

        $this->setRefreshCookie($newRefreshToken);

        Response::success(
            [
                'access_token' => $newAccessToken,
                'token_type'   => 'Bearer',
                'expires_in'   => JwtConfig::$accessTTL,
            ],
            'Token refreshed.'
        );
    }

    // ─────────────────────────────────────────────
    // POST /api/auth/logout
    // Clears the refresh token cookie
    // ─────────────────────────────────────────────
    public function logout(): void
    {
        // Expire the cookie immediately
        $cookiePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/api/auth';

        setcookie(
            'refresh_token',
            '',
            [
                'expires'  => time() - 3600,
                'path'     => $cookiePath,
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $this->isSecure(),
            ]
        );

        Response::success(null, 'Logged out successfully.');
    }

    // ─────────────────────────────────────────────
    // GET /api/auth/me
    // Returns the current authenticated user
    // ─────────────────────────────────────────────
    public function me(): void
    {
        AuthMiddleware::handle();

        $authUser = AuthMiddleware::user();
        $user     = $this->userModel->findById((int) $authUser->id);

        if ($user === null) {
            Response::notFound('User not found.');
        }

        unset($user['password']);

        Response::success(['user' => $user]);
    }

    // ─── Private helpers ──────────────────────────

    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    private function setRefreshCookie(string $token): void
    {
        $cookiePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/api/auth';

        setcookie(
            'refresh_token',
            $token,
            [
                'expires'  => time() + JwtConfig::$refreshTTL,
                'path'     => $cookiePath,
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $this->isSecure(),
            ]
        );
    }

    private function isSecure(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $_SERVER['SERVER_PORT'] == 443
        );
    }
}
