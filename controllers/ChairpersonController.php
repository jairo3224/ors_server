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
     */
    public function students(): void
    {
        $user = $this->authenticateAndGetUser();
        $students = $this->studentModel->findByDepartment($user->department_id);
        Response::success(['students' => $students]);
    }

    /**
     * Dev-only: all students (localhost only)
     */
    public function studentsDebug(): void
    {
        $this->blockNonLocalhost();
        $students = $this->studentModel->findAll();
        Response::success(['students' => $students]);
    }

    /**
     * GET /api/chairperson/reports
     */
    public function reports(): void
    {
        $user = $this->authenticateAndGetUser();
        $reports = $this->incidentModel->findByDepartment($user->department_id);
        foreach ($reports as &$report) {
            $report['remarks'] = $this->incidentModel->getRemarks($report['id']);
        }
        Response::success(['reports' => $reports]);
    }

    /**
     * Dev-only: all reports (localhost only)
     */
    public function reportsDebug(): void
    {
        $this->blockNonLocalhost();
        $reports = $this->incidentModel->findAll();
        foreach ($reports as &$report) {
            $report['remarks'] = $this->incidentModel->getRemarks($report['id']);
        }
        Response::success(['reports' => $reports]);
    }

    /**
     * GET /api/chairperson/cases
     */
    public function cases(): void
    {
        $user = $this->authenticateAndGetUser();
        $incidents = $this->incidentModel->findByDepartment($user->department_id);

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
        }, $incidents);

        Response::success(['cases' => $cases]);
    }

    /**
     * Dev-only: all cases (localhost only)
     */
    public function casesDebug(): void
    {
        $this->blockNonLocalhost();
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
     */
    public function inbox(): void
    {
        $user = $this->authenticateAndGetUser();
        $referrals = $this->referralModel->findByDepartment($user->department_id);
        $inbox = array_map(function ($ref) {
            return [
                'id'            => $ref['id'],
                'incident_id'   => $ref['incident_id'],
                'student_name'  => $ref['student_name'],
                'subject'       => $ref['subject'] ?? 'Referral',
                'description'   => $ref['description'],
                'from_office'   => 'OSAS',
                'date_received' => $ref['date_received'],
                'status'        => $ref['status'] === 'completed' ? 'responded' : $ref['status'],
                'response'      => $ref['response'],
            ];
        }, $referrals);

        Response::success(['inbox' => $inbox]);
    }

    /**
     * Dev-only: all referrals (localhost only)
     */
    public function inboxDebug(): void
    {
        $this->blockNonLocalhost();
        $referrals = $this->referralModel->findAll();
        $inbox = array_map(function ($ref) {
            return [
                'id'            => $ref['id'],
                'incident_id'   => $ref['incident_id'],
                'student_name'  => $ref['student_name'],
                'subject'       => $ref['subject'] ?? 'Referral',
                'description'   => $ref['description'],
                'from_office'   => 'OSAS',
                'date_received' => $ref['date_received'],
                'status'        => $ref['status'] === 'completed' ? 'responded' : $ref['status'],
                'response'      => $ref['response'],
            ];
        }, $referrals);

        Response::success(['inbox' => $inbox]);
    }

    /**
     * POST /api/chairperson/reports/:id/remark
     * Body: { text, response_type (optional, default 'remark') }
     */
    public function addRemark(int $reportId): void
    {
        $user = $this->authenticateAndGetUser();
        $body = $this->parseBody();
        $text = $body['text'] ?? '';
        $responseType = $body['response_type'] ?? 'remark';

        if (empty(trim($text))) {
            Response::error('Response text is required.', 422);
        }

        $this->incidentModel->addResponse($reportId, $user->id, $responseType, $text);
        Response::success(null, 'Response added.');
    }

    /**
     * POST /api/chairperson/reports/:id/forward
     * Body: { destination, note }
     */
    public function forward(int $incidentId): void
    {
        $user = $this->authenticateAndGetUser();
        $osasUserId = $this->getOSASUserId();
        if (!$osasUserId) {
            Response::serverError('No OSAS user available to receive referral.');
        }

        $this->incidentModel->forwardToOSAS($incidentId, $user->id, $osasUserId);
        Response::success(null, 'Case forwarded to OSAS.');
    }

    /**
     * POST /api/chairperson/inbox/:id/accept
     */
    public function acceptReferral(int $referralId): void
    {
        $this->authenticateAndGetUser();
        $this->referralModel->accept($referralId);
        Response::success(null, 'Referral accepted.');
    }

    /**
     * POST /api/chairperson/inbox/:id/reject
     * Body: { reason }
     */
    public function rejectReferral(int $referralId): void
    {
        $this->authenticateAndGetUser();
        $body = $this->parseBody();
        $reason = $body['reason'] ?? '';
        if (empty(trim($reason))) {
            Response::error('Rejection reason is required.', 422);
        }
        $this->referralModel->reject($referralId, $reason);
        Response::success(null, 'Referral rejected.');
    }

    /**
     * POST /api/chairperson/inbox/:id/respond
     * Body: { responseText, responseType (optional, default 'assessment') }
     * Creates a proper response record for the incident and marks referral as completed.
     */
    public function respondToReferral(int $referralId): void
    {
        $user = $this->authenticateAndGetUser();
        $body = $this->parseBody();
        $text = $body['responseText'] ?? '';
        $responseType = $body['responseType'] ?? 'assessment';

        if (empty(trim($text))) {
            Response::error('Response text is required.', 422);
        }

        // Get the incident_id from the referral
        $referral = $this->referralModel->findById($referralId);
        if (!$referral) {
            Response::notFound('Referral not found.');
        }

        // Insert proper response for the incident
        $this->incidentModel->addResponse(
            (int) $referral['incident_id'],
            $user->id,
            $responseType,
            $text
        );

        // Update the referral to completed
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

    private function blockNonLocalhost(): void
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $allowed = ['127.0.0.1', '::1', '::ffff:127.0.0.1'];
        if (!in_array($remote, $allowed, true)) {
            Response::forbidden('Debug endpoint is restricted to localhost.');
        }
    }
}