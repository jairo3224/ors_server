<?php
declare(strict_types=1);

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ChairpersonController.php';
require_once __DIR__ . '/../controllers/GuidanceController.php';
require_once __DIR__ . '/../controllers/IncidentController.php';
require_once __DIR__ . '/../controllers/StudentController.php';
require_once __DIR__ . '/../controllers/ClassController.php';
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
$chairpersonController = new ChairpersonController();
$guidanceController = new GuidanceController();
$incidentController = new IncidentController();
$studentController = new StudentController();
$classController = new ClassController();

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

// ─────────────────────────────────────────────────
// CHAIRPERSON ROUTES (Department Head only)
// ─────────────────────────────────────────────────
if ($path === '/chairperson/students' && $method === 'GET') {
    $chairpersonController->students();
}
if ($path === '/chairperson/reports' && $method === 'GET') {
    $chairpersonController->reports();
}
if ($path === '/chairperson/cases' && $method === 'GET') {
    $chairpersonController->cases();
}
if ($path === '/chairperson/inbox' && $method === 'GET') {
    $chairpersonController->inbox();
}

// Dynamic routes with ID
if (preg_match('#^/chairperson/reports/(\d+)/remark$#', $path, $matches) && $method === 'POST') {
    $chairpersonController->addRemark((int) $matches[1]);
}
if (preg_match('#^/chairperson/reports/(\d+)/forward$#', $path, $matches) && $method === 'POST') {
    $chairpersonController->forward((int) $matches[1]);
}
if (preg_match('#^/chairperson/inbox/(\d+)/respond$#', $path, $matches) && $method === 'POST') {
    $chairpersonController->respondToReferral((int) $matches[1]);
}

// ─────────────────────────────────────────────────
// DEV / DEBUG ROUTES (local only)
// ─────────────────────────────────────────────────
if ($path === '/debug/students' && $method === 'GET') {
    $chairpersonController->studentsDebug();
}
if ($path === '/debug/reports' && $method === 'GET') {
    $chairpersonController->reportsDebug();
}
if ($path === '/debug/cases' && $method === 'GET') {
    $chairpersonController->casesDebug();
}
if ($path === '/debug/inbox' && $method === 'GET') {
    $chairpersonController->inboxDebug();
}

// ─────────────────────────────────────────────────
// INCIDENT ROUTES  (token + role required)
// ─────────────────────────────────────────────────
if ($path === '/teacher/incidents' && $method === 'GET') {
    $incidentController->index();
}

if ($path === '/teacher/incidents' && $method === 'POST') {
    $incidentController->store();
}

if ($path === '/teacher/incidents/refer' && $method === 'POST') {
    $incidentController->refer();
}

// ─────────────────────────────────────────────────
// STUDENT ROUTES  (token + role required)
// ─────────────────────────────────────────────────
if ($path === '/teacher/students/search' && $method === 'GET') {
    $studentController->search();
}

// ─────────────────────────────────────────────────
// CLASS ROUTES  (token + role required)
// ─────────────────────────────────────────────────
if ($path === '/teacher/classes' && $method === 'GET') {
    $classController->index();
}

if ($method === 'GET' && preg_match('#^/teacher/classes/(\d+)/roster$#', $path, $matches)) {
    $classController->roster((int) $matches[1]);
}

// ─────────────────────────────────────────────────
// GUIDANCE ROUTES (Guidance Office only)
// ─────────────────────────────────────────────────
if ($path === '/guidance/overview' && $method === 'GET') {
    $guidanceController->overview();
}
if ($path === '/guidance/inbox' && $method === 'GET') {
    $guidanceController->inbox();
}
if ($path === '/guidance/sent' && $method === 'GET') {
    $guidanceController->sent();
}
if ($path === '/guidance/incidents' && $method === 'GET') {
    $guidanceController->incidents();
}
if ($path === '/guidance/responses' && $method === 'GET') {
    $guidanceController->responses();
}
if ($path === '/guidance/notifications' && $method === 'GET') {
    $guidanceController->notifications();
}
if ($path === '/guidance/students/search' && $method === 'GET') {
    $guidanceController->searchStudents();
}
if ($path === '/guidance/students/history' && $method === 'GET') {
    $guidanceController->studentHistory();
}

// Guidance - dynamic referral routes
if (preg_match('#^/guidance/referrals/(\d+)/accept$#', $path, $matches) && $method === 'POST') {
    $guidanceController->acceptReferral((int) $matches[1]);
}
if (preg_match('#^/guidance/referrals/(\d+)/reject$#', $path, $matches) && $method === 'POST') {
    $guidanceController->rejectReferral((int) $matches[1]);
}
if (preg_match('#^/guidance/referrals/(\d+)/respond$#', $path, $matches) && $method === 'POST') {
    $guidanceController->respondToReferral((int) $matches[1]);
}
if (preg_match('#^/guidance/referrals/(\d+)/return-to-osas$#', $path, $matches) && $method === 'POST') {
    $guidanceController->returnToOSAS((int) $matches[1]);
}
if (preg_match('#^/guidance/referrals/(\d+)/refer-to-chaplain$#', $path, $matches) && $method === 'POST') {
    $guidanceController->referToChaplain((int) $matches[1]);
}

// Guidance - sessions & attachments
if ($path === '/guidance/sessions' && $method === 'POST') {
    $guidanceController->createSession();
}
if ($path === '/guidance/attachments' && $method === 'POST') {
    $guidanceController->addAttachment();
}

// Guidance - notifications
if (preg_match('#^/guidance/mark-notification-read/(\d+)$#', $path, $matches) && $method === 'POST') {
    $guidanceController->markNotificationRead((int) $matches[1]);
}

// ─────────────────────────────────────────────────
// 404 fallback
// ─────────────────────────────────────────────────
Response::notFound("Route {$method} /api{$path} not found.");
