<?php

// models/IncidentModel.php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class IncidentModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
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
        // Generate a unique report code: INC-TIMESTAMP-RANDOMSTR
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
            'id'           => $incidentId,
            'report_code'  => $reportCode,
            'student_id'   => $studentId,
            'incident_type_id' => $incidentTypeId,
            'urgency_level' => $urgencyLevel,
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

    /**
     * Find an incident by ID.
     */
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

    /**
     * Get all incidents (with pagination).
     */
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

    /**
     * Get incidents reported by a specific teacher.
     */
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
}
