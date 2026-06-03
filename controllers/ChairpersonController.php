<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/StudentModel.php';
require_once __DIR__ . '/../models/IncidentModel.php';
require_once __DIR__ . '/../models/ReferralModel.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class ChairpersonController
{
    private StudentModel $studentModel;
    private IncidentModel $incidentModel;
    private ReferralModel $referralModel;

    public function __construct()
    {
        $this->studentModel = new StudentModel();
        $this->incidentModel = new IncidentModel();
        $this->referralModel = new ReferralModel();
    }

    /**
     * GET /api/chairperson/students
     * Return students belonging to the chairperson’s department.
     */
    public function students(): void
    {
        $user = $this->authenticateAndGetUser();
        $students = $this->studentModel->findByDepartment($user->department_id);
        Response::success(['students' => $students]);
    }

    /**
     * Dev-only: return all students (no department filter).
     * Accessible only from localhost to avoid accidental exposure.
     */
    public function studentsDebug(): void
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $allowed = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
        if (!in_array($remote, $allowed, true)) {
            Response::forbidden('Debug endpoint is restricted to localhost.');
        }

        $students = $this->studentModel->findAll();
        Response::success(['students' => $students]);
    }

    /**
     * GET /api/chairperson/reports
     * Return incident reports for students in the chairperson’s department.
     */
    public function reports(): void
    {
        $user = $this->authenticateAndGetUser();
        $reports = $this->incidentModel->findByDepartment($user->department_id);
        // Attach remarks to each report
        foreach ($reports as &$report) {
            $report['remarks'] = $this->incidentModel->getRemarks($report['id']);
        }
        Response::success(['reports' => $reports]);
    }

    /**
     * Dev-only: return all reports (no department filter).
     * Accessible only from localhost to avoid accidental exposure.
     */
    public function reportsDebug(): void
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $allowed = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
        if (!in_array($remote, $allowed, true)) {
            Response::forbidden('Debug endpoint is restricted to localhost.');
        }

        $reports = $this->incidentModel->findAll();
        // Attach remarks
        foreach ($reports as &$report) {
            $report['remarks'] = $this->incidentModel->getRemarks($report['id']);
        }
        Response::success(['reports' => $reports]);
    }

    /**
     * GET /api/chairperson/cases
     * For now, “cases” are the same incident reports but presented differently.
     * We can add business logic later; for frontend continuity we reuse the same data
     * but with a “case” view (status, priority, etc.).
     */
    public function cases(): void
    {
        $user = $this->authenticateAndGetUser();
        $incidents = $this->incidentModel->findByDepartment($user->department_id);

        // Map to case-like structure expected by the frontend
        $cases = array_map(function ($report) {
            return [
                'id'            => $report['id'],
                'student_id'    => $report['student_id'],
                'student_name'  => $report['student_name'],
                'title'         => $report['type'] ?? 'Incident',
                'type'          => $report['type'] ?? '',
                'status'        => $report['status'] === 'referred' ? 'referred' : ($report['status'] === 'closed' ? 'closed' : 'open'),
                'priority'      => $this->mapUrgencyToPriority($report['severity']),
                'assigned_to'   => null, // will be filled if status = referred
                'opened_date'   => $report['date_submitted'],
                'last_update'   => $report['updated_at'],
                'notes'         => $report['description'],
            ];
        }, $incidents);

        Response::success(['cases' => $cases]);
    }

    /**
     * Dev-only: return all cases (no department filter).
     * Accessible only from localhost to avoid accidental exposure.
     */
    public function casesDebug(): void
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $allowed = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
        if (!in_array($remote, $allowed, true)) {
            Response::forbidden('Debug endpoint is restricted to localhost.');
        }

        $reports = $this->incidentModel->findAll();
        $cases = array_map(function ($report) {
            return [
                'id'            => $report['id'],
                'student_id'    => $report['student_id'],
                'student_name'  => $report['student_name'],
                'title'         => $report['type'] ?? 'Incident',
                'type'          => $report['type'] ?? '',
                'status'        => $report['status'] === 'referred' ? 'referred' : ($report['status'] === 'closed' ? 'closed' : 'open'),
                'priority'      => $this->mapUrgencyToPriority($report['severity']),
                'assigned_to'   => null,
                'opened_date'   => $report['date_submitted'],
                'last_update'   => $report['updated_at'],
                'notes'         => $report['description'],
            ];
        }, $reports);

        Response::success(['cases' => $cases]);
    }

    /**
     * GET /api/chairperson/inbox
     * Return referrals sent to the chairperson’s department.
     */
    public function inbox(): void
    {
        $user = $this->authenticateAndGetUser();
        $referrals = $this->referralModel->findByDepartment($user->department_id);
        // Map to inbox item structure
        $inbox = array_map(function ($ref) {
            return [
                'id'            => $ref['id'],
                'student_name'  => $ref['student_name'],
                'subject'       => $ref['subject'] ?? 'Referral',
                'description'   => $ref['description'],
                'from_office'   => 'OSAS', // hardcoded for now
                'date_received' => $ref['date_received'],
                'status'        => $ref['status'] === 'completed' ? 'responded' : 'pending',
                'response'      => $ref['response'],
            ];
        }, $referrals);

        Response::success(['inbox' => $inbox]);
    }

    /**
     * Dev-only: return all referrals (no department filter).
     * Accessible only from localhost to avoid accidental exposure.
     */
    public function inboxDebug(): void
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $allowed = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
        if (!in_array($remote, $allowed, true)) {
            Response::forbidden('Debug endpoint is restricted to localhost.');
        }

        $referrals = $this->referralModel->findAll();
        $inbox = array_map(function ($ref) {
            return [
                'id'            => $ref['id'],
                'student_name'  => $ref['student_name'],
                'subject'       => $ref['subject'] ?? 'Referral',
                'description'   => $ref['description'],
                'from_office'   => 'OSAS',
                'date_received' => $ref['date_received'],
                'status'        => $ref['status'] === 'completed' ? 'responded' : 'pending',
                'response'      => $ref['response'],
            ];
        }, $referrals);

        Response::success(['inbox' => $inbox]);
    }

    /**
     * POST /api/chairperson/reports/:id/remark
     * Body: { text: string }
     */
    public function addRemark(int $reportId): void
    {
        $user = $this->authenticateAndGetUser();
        $body = $this->parseBody();
        $text = $body['text'] ?? '';
        if (empty(trim($text))) {
            Response::error('Remark text is required.', 422);
        }

        $this->incidentModel->addRemark($reportId, $user->id, $text);
        Response::success(null, 'Remark added.');
    }

    /**
     * POST /api/chairperson/reports/:id/forward   (or cases/:id/forward)
     * Body: { destination: 'OSAS', note: string }   (destination ignored, always OSAS)
     */
    public function forward(int $incidentId): void
    {
        $user = $this->authenticateAndGetUser();
        // Only forward to OSAS – we'll fetch an OSAS user ID (the first active OSAS user for simplicity)
        $osasUserId = $this->getOSASUserId();
        if (!$osasUserId) {
            Response::serverError('No OSAS user available to receive referral.');
        }

        $this->incidentModel->forwardToOSAS($incidentId, $user->id, $osasUserId);
        Response::success(null, 'Case forwarded to OSAS.');
    }

    /**
     * POST /api/chairperson/inbox/:id/respond
     * Body: { responseText: string }
     */
    public function respondToReferral(int $referralId): void
    {
        $user = $this->authenticateAndGetUser();
        $body = $this->parseBody();
        $text = $body['responseText'] ?? '';
        if (empty(trim($text))) {
            Response::error('Response text is required.', 422);
        }

        $this->referralModel->respond($referralId, $text);
        Response::success(null, 'Response submitted.');
    }

    // ─── Private helpers ──────────────────────────

    private function authenticateAndGetUser(): object
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRoles(['Department Head']);
        return AuthMiddleware::user();
    }

    private function mapUrgencyToPriority(?string $urgency): string
    {
        return match ($urgency) {
            'critical' => 'high',
            'high'     => 'high',
            'medium'   => 'medium',
            'low'      => 'low',
            default    => 'medium',
        };
    }

    private function getOSASUserId(): ?int
    {
        $db = Database::connect();
        $sql = "
            SELECT u.id
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.role_name = 'OSAS'
              AND u.is_active = 1
              AND u.deleted_at IS NULL
            LIMIT 1
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

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