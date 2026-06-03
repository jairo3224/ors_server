<?php
declare(strict_types=1);

use Dotenv\Dotenv;

// ── Autoloader + env ──────────────────────────────────────────────────────
require_once __DIR__ . '/vendor/autoload.php';

// If you use a .env file, load it here.
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ── CORS ──────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config/cors.php';
applyCorsHeaders();

// ── Force JSON content type for all responses ─────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ── Route dispatcher ──────────────────────────────────────────────────────
require_once __DIR__ . '/routes/api.php';
