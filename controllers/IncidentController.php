<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/IncidentModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class IncidentController
{
    private IncidentModel $model;
    private UserModel $userModel;

    public function __construct()
    {
        $this->model = new IncidentModel();
        $this->userModel = new UserModel();
    }

    // ─────────────────────────────────────────────
    // POST /api/teacher/incidents
    // Create a new incident report
    // ─────────────────────────────────────────────
    public function store(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRoles(['Teacher', 'Department Head']);

        $body = $this->parseBody();
        $authUser = AuthMiddleware::user();

        // Log incoming request for debugging
        error_log('[IncidentController::store] Request body: ' . json_encode($body));

        // ── Validation ──────────────────────────
        $errors = [];
        $studentId = null;
        $incidentTypeId = null;
        $description = '';
        $urgencyLevel = 'medium';

        $rawStudentId = $body['student_id'] ?? null;
        if ($rawStudentId === null || $rawStudentId === '') {
            $errors['student_id'] = 'Student ID is required.';
        } elseif (!is_numeric($rawStudentId)) {
            $errors['student_id'] = "Student ID must be a number (received: {$rawStudentId}).";
        } else {
            $studentId = (int) $rawStudentId;
        }

        $rawIncidentTypeId = $body['incident_type_id'] ?? null;
        if ($rawIncidentTypeId === null || $rawIncidentTypeId === '') {
            $errors['incident_type_id'] = 'Incident type is required.';
        } elseif (!is_numeric($rawIncidentTypeId)) {
            $errors['incident_type_id'] = "Incident type must be a number (received: {$rawIncidentTypeId}).";
        } else {
            $incidentTypeId = (int) $rawIncidentTypeId;
        }

        $description = trim($body['description'] ?? '');
        if ($description === '') {
            $errors['description'] = 'Description is required.';
        } elseif (strlen($description) < 10) {
            $length = strlen($description);
            $errors['description'] = "Description must be at least 10 characters (received {$length} chars).";
        }

        $urgencyLevel = strtolower(trim($body['urgency_level'] ?? 'medium'));
        if (!in_array($urgencyLevel, ['low', 'medium', 'high', 'critical'], true)) {
            $errors['urgency_level'] = "Invalid urgency level (received: {$urgencyLevel}). Must be: low, medium, high, or critical.";
        }

        if (!empty($errors)) {
            error_log('[IncidentController::store] Validation errors: ' . json_encode($errors));
            Response::error('Validation failed.', 422, $errors);
        }

        // ── Create incident ─────────────────────
        try {
            $incident = $this->model->create(
                (int) $studentId,
                (int) $authUser->id,
                (int) $incidentTypeId,
                $description,
                $urgencyLevel,
                isset($body['subject_id']) ? (int) $body['subject_id'] : null
            );

            Response::success(
                $incident,
                'Incident report created successfully.',
                201
            );
        } catch (Exception $e) {
            error_log('[IncidentController::store] ' . $e->getMessage());
            Response::serverError('Failed to create incident report.');
        }
    }

    // ─────────────────────────────────────────────
    // GET /api/teacher/incidents
    // List incidents reported by the authenticated teacher
    // ─────────────────────────────────────────────
    public function index(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRoles(['Teacher', 'Department Head', 'OSAS', 'Guidance Office']);

        $authUser = AuthMiddleware::user();
        $limit = (int) ($_GET['limit'] ?? 50);
        $offset = (int) ($_GET['offset'] ?? 0);

        $limit = min($limit, 100); // Cap at 100

        try {
            if (in_array($authUser->role, ['OSAS', 'Guidance Office'], true)) {
                // Admins can see all incidents
                $incidents = $this->model->getAll($limit, $offset);
            } else {
                // Teachers see only their own reports
                $incidents = $this->model->getByReporter((int) $authUser->id, $limit, $offset);
            }

            Response::success(
                ['incidents' => $incidents],
                'Incidents retrieved successfully.'
            );
        } catch (Exception $e) {
            error_log('[IncidentController::index] ' . $e->getMessage());
            Response::serverError('Failed to retrieve incidents.');
        }
    }

    // ─────────────────────────────────────────────
    // POST /api/teacher/incidents/refer
    // Forward an incident to another office (e.g. OSAS)
    // ─────────────────────────────────────────────
    public function refer(): void
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRoles(['Teacher', 'Department Head']);

        $body = $this->parseBody();
        $authUser = AuthMiddleware::user();

        $errors = [];

        $incidentId = $body['incident_id'] ?? null;
        if ($incidentId === null || !is_numeric($incidentId)) {
            $errors['incident_id'] = 'Incident ID is required.';
        }

        $destinationRole = $body['destination_role'] ?? 'OSAS';

        $remarks = trim($body['remarks'] ?? '');

        if (!empty($errors)) {
            Response::error('Validation failed.', 422, $errors);
        }

        try {
            $targetUser = $this->userModel->findByRoleName($destinationRole);
            if ($targetUser === null) {
                Response::error("No active user found with role '{$destinationRole}'.", 404);
            }

            $this->model->createReferral(
                (int) $incidentId,
                (int) $authUser->id,
                (int) $targetUser['id'],
                $remarks
            );

            Response::success(null, 'Incident forwarded successfully.');
        } catch (Exception $e) {
            error_log('[IncidentController::refer] ' . $e->getMessage());
            Response::serverError('Failed to forward incident.');
        }
    }

    // ── Private helpers ────────────────────────

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
}
