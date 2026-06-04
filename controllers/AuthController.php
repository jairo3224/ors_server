<?php

// controllers/AuthController.php

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

        $user = $this->userModel->findByEmail($email);

        $dummyHash = '$2y$12$invalidsaltXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
        $hash      = $user['password'] ?? $dummyHash;

        if (!password_verify($password, $hash) || $user === null) {
            Response::error('Invalid email or password.', 401);
        }

        if (!$user['is_active']) {
            Response::error('Your account has been deactivated. Please contact the administrator.', 403);
        }

        $accessToken  = JwtHelper::generateAccessToken($user);
        $refreshToken = JwtHelper::generateRefreshToken((int) $user['id']);

        $this->setRefreshCookie($refreshToken);

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
    // ─────────────────────────────────────────────
    public function logout(): void
    {
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

    // ─────────────────────────────────────────────
    // POST /api/auth/change-password
    // Body: { current_password, new_password, confirm_password }
    // ─────────────────────────────────────────────
    public function changePassword(): void
    {
        AuthMiddleware::handle();
        $authUser = AuthMiddleware::user();
        $body = $this->parseBody();

        $currentPassword = $body['current_password'] ?? '';
        $newPassword     = $body['new_password'] ?? '';
        $confirmPassword = $body['confirm_password'] ?? '';

        // Validation
        $errors = [];
        if ($currentPassword === '') {
            $errors['current_password'] = 'Current password is required.';
        }
        if ($newPassword === '') {
            $errors['new_password'] = 'New password is required.';
        } elseif (strlen($newPassword) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        }
        if ($confirmPassword === '') {
            $errors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            Response::error('Validation failed.', 422, $errors);
        }

        // Verify current password
        $user = $this->userModel->findById((int) $authUser->id);
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $errors['current_password'] = 'Current password is incorrect.';
            Response::error('Validation failed.', 422, $errors);
        }

        // Update password
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->userModel->updatePassword((int) $authUser->id, $hashed);

        Response::success(null, 'Password changed successfully.');
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