<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/GuidanceModel.php';
require_once __DIR__ . '/../models/IncidentModel.php';
require_once __DIR__ . '/../models/ReferralModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class GuidanceController
{
    private GuidanceModel $model;
    private IncidentModel $incidentModel;
    private ReferralModel $referralModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->model = new GuidanceModel();
        $this->incidentModel = new IncidentModel();
        $this->referralModel = new ReferralModel();
        $this->userModel = new UserModel();
    }

    private function auth(): object
    {
        AuthMiddleware::handle();
        AuthMiddleware::requireRoles(['Guidance Office']);
        return AuthMiddleware::user();
    }

    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            return is_array($data) ? $data : [];
        }
        return $_POST;
    }

    // ─────────────────────────────────────────────
    // GET /guidance/overview
    // ─────────────────────────────────────────────
    public function overview(): void
    {
        $user = $this->auth();
        $stats = $this->model->getOverviewStats((int) $user->id);
        Response::success($stats, 'Overview stats retrieved.');
    }

    // ─────────────────────────────────────────────
    // GET /guidance/inbox
    // ─────────────────────────────────────────────
    public function inbox(): void
    {
        $user = $this->auth();
        $referrals = $this->model->getInbox((int) $user->id);
        $mapped = array_map([$this, 'mapInboundReferral'], $referrals);
        Response::success(['referrals' => $mapped]);
    }

    // ─────────────────────────────────────────────
    // GET /guidance/sent
    // ─────────────────────────────────────────────
    public function sent(): void
    {
        $user = $this->auth();
        $referrals = $this->model->getSent((int) $user->id);
        $mapped = array_map([$this, 'mapOutboundReferral'], $referrals);
        Response::success(['referrals' => $mapped]);
    }

    // ─────────────────────────────────────────────
    // GET /guidance/incidents
    // ─────────────────────────────────────────────
    public function incidents(): void
    {
        $user = $this->auth();
        $assigned = $this->model->getAssignedIncidents((int) $user->id);
        $all = $this->model->getAllIncidents();
        $mappedAssigned = array_map([$this, 'mapIncident'], $assigned);
        $mappedAll = array_map([$this, 'mapIncident'], $all);
        Response::success([
            'assigned' => $mappedAssigned,
            'all'      => $mappedAll,
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /guidance/responses
    // ─────────────────────────────────────────────
    public function responses(): void
    {
        $user = $this->auth();
        $responses = $this->model->getResponsesByUser((int) $user->id);
        $meetings = [];
        $assessments = [];
        foreach ($responses as $resp) {
            $item = $this->mapResponse($resp);
            if ($resp['response_type'] === 'counseling_note' || $resp['response_type'] === 'parent_meeting') {
                $meetings[] = $item;
            } elseif ($resp['response_type'] === 'assessment' || $resp['response_type'] === 'recommendation') {
                $assessments[] = $item;
            }
        }
        Response::success([
            'meetings'    => $meetings,
            'assessments'  => $assessments,
            'all'          => array_map([$this, 'mapResponse'], $responses),
        ]);
    }

    // ─────────────────────────────────────────────
    // GET /guidance/notifications
    // ─────────────────────────────────────────────
    public function notifications(): void
    {
        $user = $this->auth();
        $notifs = $this->model->getNotifications((int) $user->id);
        $mapped = array_map([$this, 'mapNotification'], $notifs);
        Response::success(['notifications' => $mapped]);
    }

    // ─────────────────────────────────────────────
    // GET /guidance/students/history?name=...&student_id=...
    // ─────────────────────────────────────────────
    public function studentHistory(): void
    {
        $this->auth();
        $studentId = (int) ($_GET['student_id'] ?? 0);
        if (!$studentId) {
            Response::error('student_id is required.', 422);
        }
        $history = $this->model->getStudentHistory($studentId);
        Response::success($history);
    }

    // ─────────────────────────────────────────────
    // GET /guidance/students/search?q=...
    // ─────────────────────────────────────────────
    public function searchStudents(): void
    {
        $this->auth();
        $keyword = trim($_GET['q'] ?? '');
        if ($keyword === '') {
            Response::error('Search keyword is required.', 422);
        }
        $model = new StudentModel();
        $students = $model->searchByKeyword($keyword);
        Response::success(['students' => $students]);
    }

    // ─────────────────────────────────────────────
    // POST /guidance/referrals/{id}/accept
    // ─────────────────────────────────────────────
    public function acceptReferral(int $referralId): void
    {
        $this->auth();
        $this->referralModel->accept($referralId);
        Response::success(null, 'Referral accepted.');
    }

    // ─────────────────────────────────────────────
    // POST /guidance/referrals/{id}/reject
    // ─────────────────────────────────────────────
    public function rejectReferral(int $referralId): void
    {
        $this->auth();
        $body = $this->parseBody();
        $reason = $body['reason'] ?? '';
        if (empty(trim($reason))) {
            Response::error('Rejection reason is required.', 422);
        }
        $this->referralModel->reject($referralId, $reason);
        Response::success(null, 'Referral rejected.');
    }

    // ─────────────────────────────────────────────
    // POST /guidance/referrals/{id}/respond
    // Body: { responseText, responseType }
    // ─────────────────────────────────────────────
    public function respondToReferral(int $referralId): void
    {
        $user = $this->auth();
        $body = $this->parseBody();
        $text = $body['responseText'] ?? '';
        $responseType = $body['responseType'] ?? 'assessment';

        if (empty(trim($text))) {
            Response::error('Response text is required.', 422);
        }

        $referral = $this->referralModel->findById($referralId);
        if (!$referral) {
            Response::notFound('Referral not found.');
        }

        $this->incidentModel->addResponse(
            (int) $referral['incident_id'],
            (int) $user->id,
            $responseType,
            $text
        );

        $this->referralModel->respond($referralId, $text);
        Response::success(null, 'Response submitted.');
    }

    // ─────────────────────────────────────────────
    // POST /guidance/referrals/{id}/return-to-osas
    // ─────────────────────────────────────────────
    public function returnToOSAS(int $referralId): void
    {
        $user = $this->auth();
        $body = $this->parseBody();
        $findings = $body['findings'] ?? '';
        if (empty(trim($findings))) {
            Response::error('Findings text is required.', 422);
        }

        $referral = $this->referralModel->findById($referralId);
        if (!$referral) {
            Response::notFound('Referral not found.');
        }

        $this->incidentModel->addResponse(
            (int) $referral['incident_id'],
            (int) $user->id,
            'resolution',
            $findings
        );

        $this->referralModel->respond($referralId, $findings);

        $osasUser = $this->userModel->findByRoleName('OSAS');
        if ($osasUser) {
            $this->createNotification(
                (int) $osasUser['id'],
                'Guidance Office Case Findings',
                "Guidance Office has returned a case with complete findings: {$findings}",
                '/osas/referrals'
            );
        }

        Response::success(null, 'Case returned to OSAS.');
    }

    // ─────────────────────────────────────────────
    // POST /guidance/referrals/{id}/refer-to-chaplain
    // Body: { student_name, student_id, subject, description }
    // ─────────────────────────────────────────────
    public function referToChaplain(int $referralId): void
    {
        $user = $this->auth();
        $body = $this->parseBody();

        $chaplainUser = $this->userModel->findByRoleName('Chaplain');
        if (!$chaplainUser) {
            Response::serverError('No active Chaplain user found.');
        }

        $referral = $this->referralModel->findById($referralId);
        if (!$referral) {
            Response::notFound('Referral not found.');
        }

        $this->incidentModel->addResponse(
            (int) $referral['incident_id'],
            (int) $user->id,
            'recommendation',
            $body['description'] ?? 'Referred to Chaplain for spiritual support.'
        );

        $this->incidentModel->createReferral(
            (int) $referral['incident_id'],
            (int) $user->id,
            (int) $chaplainUser['id'],
            $body['description'] ?? 'Referred from Guidance Office for spiritual counseling.'
        );

        Response::success(null, 'Referred to Chaplain.');
    }

    // ─────────────────────────────────────────────
    // POST /guidance/sessions
    // Body: { student_name, title, date, time, location, agenda }
    // ─────────────────────────────────────────────
    public function createSession(): void
    {
        $user = $this->auth();
        $body = $this->parseBody();

        $studentName = trim($body['student_name'] ?? '');
        if ($studentName === '') {
            Response::error('student_name is required.', 422);
        }

        $details = json_encode([
            'title'    => $body['title'] ?? 'Counseling Session',
            'date'     => $body['date'] ?? date('Y-m-d'),
            'time'     => $body['time'] ?? '',
            'location' => $body['location'] ?? 'Guidance Office',
            'agenda'   => $body['agenda'] ?? '',
        ]);

        $this->createResponseForStudent($studentName, (int) $user->id, 'counseling_note', $details);
        Response::success(null, 'Session created.', 201);
    }

    // ─────────────────────────────────────────────
    // POST /guidance/attachments
    // Body: { incident_id, file_name, file_type, file_size }
    // ─────────────────────────────────────────────
    public function addAttachment(): void
    {
        $user = $this->auth();
        $body = $this->parseBody();

        $incidentId = (int) ($body['incident_id'] ?? 0);
        $fileName = trim($body['file_name'] ?? '');
        $fileType = trim($body['file_type'] ?? '');
        $fileSize = (int) ($body['file_size'] ?? 0);

        if (!$incidentId || $fileName === '') {
            Response::error('incident_id and file_name are required.', 422);
        }

        $sql = "
            INSERT INTO attachments (incident_id, uploaded_by, file_url, file_name, file_type, file_size)
            VALUES (:incident_id, :uploaded_by, :file_url, :file_name, :file_type, :file_size)
        ";
        $db = \Database::connect();
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':incident_id'  => $incidentId,
            ':uploaded_by'  => $user->id,
            ':file_url'     => '/uploads/' . $fileName,
            ':file_name'    => $fileName,
            ':file_type'    => $fileType,
            ':file_size'    => $fileSize,
        ]);

        Response::success(['id' => (int) $db->lastInsertId()], 'Attachment added.', 201);
    }

    // ─────────────────────────────────────────────
    // POST /guidance/mark-notification-read/{id}
    // ─────────────────────────────────────────────
    public function markNotificationRead(int $notifId): void
    {
        $this->auth();
        $db = \Database::connect();
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $notifId]);
        Response::success(null, 'Notification marked as read.');
    }

    // ─── Private helpers ──────────────────────────

    private function mapInboundReferral(array $ref): array
    {
        return [
            'id'           => (int) $ref['id'],
            'incident_id'  => (int) $ref['incident_id'],
            'student_name' => $ref['student_name'],
            'student_id'   => $ref['student_id'],
            'from_office'  => $ref['from_office'],
            'to_office'    => 'Guidance Office',
            'subject'      => $ref['subject'] ?? 'Referral',
            'description'  => $ref['description'],
            'date_sent'    => $ref['date_sent'],
            'status'       => $ref['status'],
            'response'     => $ref['response'],
            'responded_at' => $ref['responded_at'],
        ];
    }

    private function mapOutboundReferral(array $ref): array
    {
        return [
            'id'           => (int) $ref['id'],
            'incident_id'  => (int) $ref['incident_id'],
            'student_name' => $ref['student_name'],
            'student_id'   => $ref['student_id'],
            'from_office'  => 'Guidance Office',
            'to_office'    => $ref['to_office'],
            'subject'      => $ref['subject'] ?? 'Referral',
            'description'  => $ref['description'],
            'date_sent'    => $ref['date_sent'],
            'status'       => $ref['status'],
            'response'     => $ref['response'],
            'responded_at' => $ref['responded_at'],
        ];
    }

    private function mapIncident(array $inc): array
    {
        return [
            'id'             => (int) $inc['id'],
            'student_name'   => $inc['student_name'],
            'student_id'     => $inc['student_id'] ?? null,
            'type'           => $inc['type'] ?? 'Unknown',
            'priority'       => $inc['urgency_level'] ?? 'medium',
            'status'         => $inc['status'],
            'description'    => $inc['description'] ?? '',
            'date_reported'  => $inc['date_reported'],
            'last_updated'   => $inc['last_updated'] ?? $inc['date_reported'],
            'assigned_to'    => $inc['assigned_to_name'] ?? null,
            'teacher_name'   => $inc['teacher_name'] ?? null,
        ];
    }

    private function mapResponse(array $resp): array
    {
        $details = json_decode($resp['remarks'], true);
        return [
            'id'             => (int) $resp['id'],
            'incident_id'    => (int) $resp['incident_id'],
            'student_name'   => $resp['student_name'],
            'type'           => $resp['response_type'],
            'response_type'  => $resp['response_type'],
            'assessment'     => $resp['remarks'],
            'date'           => $resp['date'],
            'title'          => $details['title'] ?? ucfirst(str_replace('_', ' ', $resp['response_type'])),
            'time'           => $details['time'] ?? '',
            'location'       => $details['location'] ?? 'Guidance Office',
            'agenda'         => $details['agenda'] ?? '',
            'status'         => 'completed',
        ];
    }

    private function mapNotification(array $n): array
    {
        return [
            'id'         => (int) $n['id'],
            'title'      => $n['title'],
            'message'    => $n['message'],
            'read'       => (bool) $n['read'],
            'priority'   => 'moderate',
            'link'       => $n['link'],
            'created_at' => $n['created_at'],
        ];
    }

    private function createResponseForStudent(string $studentName, int $userId, string $typeName, string $remarks): ?int
    {
        $db = \Database::connect();

        $studentStmt = $db->prepare("
            SELECT id FROM students
            WHERE CONCAT(first_name, ' ', last_name) = :name
               OR CONCAT(last_name, ', ', first_name) = :name2
            LIMIT 1
        ");
        $studentStmt->execute([':name' => $studentName, ':name2' => $studentName]);
        $student = $studentStmt->fetch();

        if (!$student) {
            return null;
        }

        $incidentStmt = $db->prepare("
            SELECT id FROM incident_reports
            WHERE student_id = :student_id AND deleted_at IS NULL
            ORDER BY created_at DESC LIMIT 1
        ");
        $incidentStmt->execute([':student_id' => $student['id']]);
        $incident = $incidentStmt->fetch();

        if (!$incident) {
            return null;
        }

        $typeStmt = $db->prepare("SELECT id FROM response_types WHERE type_name = :name LIMIT 1");
        $typeStmt->execute([':name' => $typeName]);
        $type = $typeStmt->fetch();
        if (!$type) {
            return null;
        }

        $stmt = $db->prepare("
            INSERT INTO responses (incident_id, user_id, response_type_id, remarks)
            VALUES (:incident_id, :user_id, :type_id, :remarks)
        ");
        $stmt->execute([
            ':incident_id' => $incident['id'],
            ':user_id'     => $userId,
            ':type_id'     => $type['id'],
            ':remarks'     => $remarks,
        ]);

        return (int) $db->lastInsertId();
    }

    private function createNotification(int $userId, string $title, string $message, ?string $link): void
    {
        $db = \Database::connect();
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, link)
            VALUES (:user_id, :title, :message, :link)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':title'   => $title,
            ':message' => $message,
            ':link'    => $link,
        ]);
    }
}
