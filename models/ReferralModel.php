<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class ReferralModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function findByDepartment(int $departmentId): array
    {
        $sql = "
            SELECT
                r.id,
                r.incident_id,
                r.status,
                r.remarks AS response,
                r.referred_at AS date_received,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                it.type_name AS subject,
                ir.description
            FROM referrals r
            INNER JOIN users u ON u.id = r.referred_to
            INNER JOIN incident_reports ir ON ir.id = r.incident_id
            INNER JOIN students s ON s.id = ir.student_id
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            WHERE u.department_id = :dept_id
              AND u.role_id = (SELECT id FROM roles WHERE role_name = 'Department Head' LIMIT 1)
            ORDER BY r.referred_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':dept_id' => $departmentId]);
        return $stmt->fetchAll();
    }

    public function findAll(): array
    {
        $sql = "
            SELECT
                r.id,
                r.incident_id,
                r.status,
                r.remarks AS response,
                r.referred_at AS date_received,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                it.type_name AS subject,
                ir.description
            FROM referrals r
            INNER JOIN incident_reports ir ON ir.id = r.incident_id
            INNER JOIN students s ON s.id = ir.student_id
            LEFT JOIN incident_types it ON it.id = ir.incident_type_id
            WHERE r.status IS NOT NULL
            ORDER BY r.referred_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function respond(int $referralId, string $responseText): void
    {
        $sql = "
            UPDATE referrals
            SET status = 'completed',
                remarks = :remarks,
                responded_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':remarks' => $responseText,
            ':id'      => $referralId,
        ]);
    }
}