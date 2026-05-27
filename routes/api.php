<?php

// routes/api.php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize: strip the script directory if the app is hosted in a subfolder, then strip the /api prefix.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath   = '';

if ($scriptName !== '') {
    $scriptDir = dirname($scriptName);
    if ($scriptDir !== '/' && $scriptDir !== '\\') {
        $basePath = rtrim(str_replace('\\', '/', $scriptDir), '/');
    }
}

if ($basePath !== '' && str_starts_with($path, $basePath)) {
    $path = substr($path, strlen($basePath));
}

$path = rtrim($path, '/');
$path = preg_replace('#^/api#', '', $path);

$auth = new AuthController();

// ─────────────────────────────────────────────────
// AUTH ROUTES  (public — no token required)
// ─────────────────────────────────────────────────
if ($path === '/auth/login'   && $method === 'POST') { $auth->login();   }
if ($path === '/auth/logout'  && $method === 'POST') { $auth->logout();  }
if ($path === '/auth/refresh' && $method === 'POST') { $auth->refresh(); }

// ─────────────────────────────────────────────────
// PROTECTED ROUTES  (token required)
// ─────────────────────────────────────────────────
if ($path === '/auth/me' && $method === 'GET') { $auth->me(); }

// ── Role-specific route examples ─────────────────
// Uncomment and expand as you build each feature.

// if ($path === '/incidents' && $method === 'GET') {
//     AuthMiddleware::handle();
//     AuthMiddleware::requireRoles(['OSAS', 'Guidance Office', 'Department Head', 'Teacher', 'Chaplain']);
//     // $incidentController->index();
// }

// if ($path === '/incidents' && $method === 'POST') {
//     AuthMiddleware::handle();
//     AuthMiddleware::requireRoles(['Teacher', 'Department Head']);
//     // $incidentController->store();
// }

// ─────────────────────────────────────────────────
// 404 fallback
// ─────────────────────────────────────────────────
Response::notFound("Route {$method} /api{$path} not found.");
