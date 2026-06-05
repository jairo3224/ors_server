<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class IncidentModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function addResponse(int $incidentId, int $userId, string $typeName, string $text): void
    {
        $typeId = $this->getResponseTypeId($typeName);

        $sql = "
            INSERT INTO responses (incident_id, user_id, response_type_id, remarks)
            VALUES (:incident_id, :user_id, :type_id, :remarks)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':incident_id' => $incidentId,
            ':user_id'     => $userId,
            ':type_id'     => $typeId,
            ':remarks'     => $text,
        ]);
    }

    public function findByDepartment(int $departmentId): array
    {
        $sql = "
            SELECT
                ir.id,
                ir.report_code,
                ir.student_id,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                CONCAT(reporter.first_name, ' ', reporter.last_name) AS teacher_name,
                sub.subject_name AS subject,
                it.type_name AS type,
                ir.urgency_level AS severity,
                ir.description,
                ir.current_status AS status,
                ir.created_at AS date_submitted,
                ir.updated_at
            FROM incident_reports ir
            INNER JOIN students s ON s.id = ir.student_id
            INNER JOIN users reporter ON reporter.id = ir.reported_by
            LEFT JOIN subjects sub ON sub.id = ir.subject_id
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            WHERE s.department_id = :dept_id
              AND ir.deleted_at IS NULL
            ORDER BY ir.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':dept_id' => $departmentId]);
        return $stmt->fetchAll();
    }

    public function findAll(): array
    {
        $sql = "
            SELECT
                ir.id,
                ir.report_code,
                ir.student_id,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                CONCAT(reporter.first_name, ' ', reporter.last_name) AS teacher_name,
                sub.subject_name AS subject,
                it.type_name AS type,
                ir.urgency_level AS severity,
                ir.description,
                ir.current_status AS status,
                ir.created_at AS date_submitted,
                ir.updated_at
            FROM incident_reports ir
            INNER JOIN students s ON s.id = ir.student_id
            INNER JOIN users reporter ON reporter.id = ir.reported_by
            LEFT JOIN subjects sub ON sub.id = ir.subject_id
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            WHERE ir.deleted_at IS NULL
            ORDER BY ir.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getRemarks(int $incidentId): array
    {
        $sql = "
            SELECT
                r.id,
                CONCAT(u.first_name, ' ', u.last_name) AS author,
                r.remarks AS text,
                r.created_at AS date
            FROM responses r
            INNER JOIN users u ON u.id = r.user_id
            INNER JOIN response_types rt ON rt.id = r.response_type_id
            WHERE r.incident_id = :id
              AND rt.type_name = 'remark'
            ORDER BY r.created_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $incidentId]);
        return $stmt->fetchAll();
    }

    public function addRemark(int $incidentId, int $userId, string $text): void
    {
        $typeId = $this->getResponseTypeId('remark');

        $sql = "
            INSERT INTO responses (incident_id, user_id, response_type_id, remarks)
            VALUES (:incident_id, :user_id, :type_id, :remarks)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':incident_id' => $incidentId,
            ':user_id'     => $userId,
            ':type_id'     => $typeId,
            ':remarks'     => $text,
        ]);
    }

    public function forwardToOSAS(int $incidentId, int $chairpersonId, int $osasUserId): void
    {
        $this->db->beginTransaction();
        try {
            $sql = "
                UPDATE incident_reports
                SET assigned_to = :assigned_to,
                    current_status = 'referred',
                    updated_at = NOW()
                WHERE id = :id
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':assigned_to' => $osasUserId,
                ':id'          => $incidentId,
            ]);

            $refSql = "
                INSERT INTO referrals (incident_id, referred_to, referred_by, status, referred_at)
                VALUES (:incident_id, :referred_to, :referred_by, 'pending', NOW())
            ";
            $refStmt = $this->db->prepare($refSql);
            $refStmt->execute([
                ':incident_id' => $incidentId,
                ':referred_to' => $osasUserId,
                ':referred_by' => $chairpersonId,
            ]);

            $histSql = "
                INSERT INTO case_history (incident_id, action, previous_status, new_status, acted_by)
                VALUES (:incident_id, 'forwarded', 'under_review', 'referred', :acted_by)
            ";
            $histStmt = $this->db->prepare($histSql);
            $histStmt->execute([
                ':incident_id' => $incidentId,
                ':acted_by'    => $chairpersonId,
            ]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Create a new incident report.
     * 
     * @param int $studentId      ID of the student involved
     * @param int $reportedBy     ID of the teacher reporting
     * @param int $incidentTypeId ID of the incident type
     * @param string $description Detailed description of the incident
     * @param string $urgencyLevel  'low', 'medium', 'high', 'critical'
     * @param int|null $subjectId Optional subject ID
     * @return array Created incident with id and report_code
     */
    public function create(
        int $studentId,
        int $reportedBy,
        int $incidentTypeId,
        string $description,
        string $urgencyLevel = 'medium',
        ?int $subjectId = null
    ): array {
        $reportCode = 'INC-' . time() . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

        $sql = "
            INSERT INTO incident_reports (
                report_code,
                student_id,
                reported_by,
                subject_id,
                incident_type_id,
                description,
                urgency_level,
                current_status
            ) VALUES (
                :report_code,
                :student_id,
                :reported_by,
                :subject_id,
                :incident_type_id,
                :description,
                :urgency_level,
                :current_status
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':report_code'     => $reportCode,
            ':student_id'      => $studentId,
            ':reported_by'     => $reportedBy,
            ':subject_id'      => $subjectId,
            ':incident_type_id' => $incidentTypeId,
            ':description'     => $description,
            ':urgency_level'   => $urgencyLevel,
            ':current_status'  => 'reported',
        ]);

        $incidentId = (int) $this->db->lastInsertId();

        return [
            'id'             => $incidentId,
            'report_code'    => $reportCode,
            'student_id'     => $studentId,
            'incident_type_id' => $incidentTypeId,
            'urgency_level'  => $urgencyLevel,
            'current_status' => 'reported',
        ];
    }

    public function createReferral(int $incidentId, int $referredBy, int $referredTo, string $remarks): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO referrals (incident_id, referred_by, referred_to, remarks, status)
                VALUES (:incident_id, :referred_by, :referred_to, :remarks, 'pending')
            ");
            $stmt->execute([
                ':incident_id'  => $incidentId,
                ':referred_by'  => $referredBy,
                ':referred_to'  => $referredTo,
                ':remarks'      => $remarks,
            ]);

            $stmt2 = $this->db->prepare("
                UPDATE incident_reports
                SET current_status = 'referred', assigned_to = :assigned_to, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt2->execute([
                ':assigned_to' => $referredTo,
                ':id'          => $incidentId,
            ]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findById(int $id): ?array
    {
        $sql = "
            SELECT
                ir.id,
                ir.report_code,
                ir.student_id,
                ir.reported_by,
                ir.assigned_to,
                ir.subject_id,
                ir.incident_type_id,
                ir.description,
                ir.urgency_level,
                ir.current_status,
                ir.resolved_at,
                ir.created_at,
                ir.updated_at,
                it.type_name,
                u.first_name,
                u.last_name,
                u.email
            FROM incident_reports ir
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            LEFT JOIN users u ON u.id = ir.reported_by
            WHERE ir.id = :id
              AND ir.deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function getAll(int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT
                ir.id,
                ir.report_code,
                ir.student_id,
                ir.reported_by,
                ir.incident_type_id,
                ir.urgency_level,
                ir.current_status,
                ir.created_at,
                it.type_name,
                u.first_name,
                u.last_name
            FROM incident_reports ir
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            LEFT JOIN users u ON u.id = ir.reported_by
            WHERE ir.deleted_at IS NULL
            ORDER BY ir.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getByReporter(int $teacherId, int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT
                ir.id,
                ir.report_code,
                ir.student_id,
                ir.incident_type_id,
                ir.urgency_level,
                ir.current_status,
                ir.description,
                ir.created_at,
                ir.updated_at,
                it.type_name,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                s.student_number
            FROM incident_reports ir
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            LEFT JOIN students s ON s.id = ir.student_id
            WHERE ir.reported_by = :teacher_id
              AND ir.deleted_at IS NULL
            ORDER BY ir.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function getResponseTypeId(string $typeName): int
    {
        $sql = "SELECT id FROM response_types WHERE type_name = :name LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':name' => $typeName]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException("Response type '{$typeName}' not found.");
        }
        return (int) $row['id'];
    }
}
